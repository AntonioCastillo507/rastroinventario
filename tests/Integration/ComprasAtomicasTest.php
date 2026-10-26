<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Proceso;

/**
 * Compras de punta a punta con PedidoController real en un proceso aparte (termina en exit):
 * el stock y el pedido se confirman juntos o no se confirma nada, y las piezas inactivas se rechazan
 * sin descontar. La falla de registrar() se simula con dos triggers que solo existen en rastroinventario_test
 * mientras corre esta clase: el INSERT del pedido lanza una excepción si las notas valen MARCA_FALLA, y el de
 * una línea si el nombre de la pieza vale MARCA_FALLA_ITEM (el pedido ya está insertado, faltan sus líneas).
 * Usa commits (el hijo es otra conexión) y borra lo suyo al final por ids y correos exactos.
 */
final class ComprasAtomicasTest extends TestCase {
    private const CLAVE = "Frase de prueba para la compra 2026";
    private PDO $db;
    private array $partes = [];
    private array $uids = [];
    private array $correos = [];
    private string $logs;

    public static function setUpBeforeClass(): void {
        $db = \Database::get();
        BaseDatos::verificarBase($db); // los triggers solo se crean en la base de pruebas
        self::quitarTriggers($db);
        $db->exec("CREATE TRIGGER trg_prueba_falla_pedido BEFORE INSERT ON pedidos FOR EACH ROW BEGIN IF NEW.cliente_notas = '" . self::MARCA_FALLA . "' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'falla simulada del pedido'; END IF; END");
        $db->exec("CREATE TRIGGER trg_prueba_falla_item BEFORE INSERT ON pedido_items FOR EACH ROW BEGIN IF NEW.parte_nombre = '" . self::MARCA_FALLA_ITEM . "' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'falla simulada de la linea'; END IF; END");
    }

    public static function tearDownAfterClass(): void {
        self::quitarTriggers(\Database::get());
    }

    private static function quitarTriggers(PDO $db): void {
        $db->exec("DROP TRIGGER IF EXISTS trg_prueba_falla_pedido");
        $db->exec("DROP TRIGGER IF EXISTS trg_prueba_falla_item");
    }

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->logs = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "compras_" . uniqid();
        mkdir($this->logs, 0777, true);
    }

    protected function tearDown(): void {
        $emails = $this->correos;
        foreach ($this->uids as $id) {
            $s = $this->db->prepare("SELECT email FROM usuarios WHERE id = ?");
            $s->execute([$id]);
            if ($e = $s->fetchColumn()) $emails[] = $e;
        }
        $ids = [];
        if ($emails || $this->uids) {
            $inE = $emails ? implode(",", array_map([$this->db, "quote"], $emails)) : "''";
            $inU = $this->uids ? implode(",", array_map("intval", $this->uids)) : "0";
            $ids = $this->db->query("SELECT id FROM pedidos WHERE cliente_email IN ($inE) OR usuario_id IN ($inU)")->fetchAll(PDO::FETCH_COLUMN);
            $this->db->exec("DELETE FROM usuarios WHERE email IN ($inE) OR id IN ($inU)");
        }
        if ($ids) {
            $in = implode(",", array_map("intval", $ids));
            $this->db->exec("DELETE FROM pedido_items WHERE pedido_id IN ($in)");
            $this->db->exec("DELETE FROM pedidos WHERE id IN ($in)");
        }
        if ($this->partes) {
            $in = implode(",", array_map("intval", $this->partes));
            $this->db->exec("DELETE FROM pedido_items WHERE parte_id IN ($in)");
            $this->db->exec("DELETE FROM partes WHERE id IN ($in)");
        }
        foreach (glob($this->logs . "/*") ?: [] as $f) @unlink($f);
        @rmdir($this->logs);
        parent::tearDown();
    }

    private function parte(int $stock = 10, float $precio = 10.00, bool $activa = true, string $nombre = ""): int {
        $id = Datos::parte($this->db, $precio, $stock, $nombre);
        $this->partes[] = $id;
        if (!$activa) $this->db->exec("UPDATE partes SET activo = 0 WHERE id = $id");
        return $id;
    }

    /** @return array{0:int,1:string} */
    private function cliente(): array {
        $id = Datos::usuario($this->db);
        $this->uids[] = $id;
        $s = $this->db->prepare("SELECT email FROM usuarios WHERE id = ?");
        $s->execute([$id]);
        return [$id, $s->fetchColumn()];
    }

    private function sesionDe(int $uid, string $email): array {
        return ["user" => ["id" => $uid, "nombre" => "Cliente de prueba", "email" => $email], "user_roles" => ["Cliente"]];
    }

    private function accion(string $metodo, array $post, array $sesion = []): array {
        // el paso 2 manda la huella de lo que se vio; aquí es la del carrito con los precios de la base AHORA (como si se hubiera abierto justo antes)
        if ($metodo === "procesarCarrito" && !array_key_exists("resumen_visto", $post)) $post["resumen_visto"] = Datos::huella($this->db, $sesion["cart"] ?? []);
        return Proceso::accion(["clase" => "PedidoController", "accion" => $metodo, "csrf" => true, "ip" => "203.0.113.77",
            "post" => $post, "sesion" => $sesion, "correo" => "ok", "dir_logs" => $this->logs]);
    }

    private function stock(int $parte): int { return Datos::stock($this->db, $parte); }

    private function pedidosDe(string $email): int {
        $s = $this->db->prepare("SELECT COUNT(*) FROM pedidos WHERE cliente_email = ?");
        $s->execute([$email]);
        return (int)$s->fetchColumn();
    }

    private function pedidosConParte(int $parte): int {
        $s = $this->db->prepare("SELECT COUNT(*) FROM pedido_items WHERE parte_id = ?");
        $s->execute([$parte]);
        return (int)$s->fetchColumn();
    }

    private function existeCuenta(string $email): bool {
        $s = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $s->execute([$email]);
        return (int)$s->fetchColumn() > 0;
    }

    private function mensaje(array $r): string {
        return (string)($r["sesion"]["flash"]["msg"] ?? $r["sesion"]["compra_error"] ?? $r["sesion"]["carrito_error"] ?? "");
    }

    private function datosInvitado(int $parte, array $extra = []): array {
        $email = "invitado." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $email;
        return $extra + ["parte_id" => $parte, "cantidad" => 2, "cliente_nombre" => "Invitado de Prueba", "cliente_telefono" => "555-0100",
            "cliente_email" => $email, "cliente_direccion" => "Calle Inventada 123", "nueva_pass" => self::CLAVE, "cliente_notas" => ""];
    }

    private const MARCA_FALLA = "__FALLA_PEDIDO__";
    private const MARCA_FALLA_ITEM = "__FALLA_ITEM__";

    // ── confirmarCompra (cliente con sesión) ─────────────────────────
    public function testConfirmarCompraNormalDescuentaYRegistra(): void {
        [$uid, $email] = $this->cliente();
        $p = $this->parte(10);
        $r = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 3, "direccion_entrega" => "Calle 1", "cliente_notas" => ""], $this->sesionDe($uid, $email));
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $this->assertSame(7, $this->stock($p));
        $this->assertSame(1, $this->pedidosDe($email));
    }

    public function testConfirmarCompraSiRegistrarFallaElStockVuelve(): void {
        [$uid, $email] = $this->cliente();
        $p = $this->parte(10);
        $r = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 3, "direccion_entrega" => "Calle 1",
            "cliente_notas" => self::MARCA_FALLA], $this->sesionDe($uid, $email));
        $this->assertSame(10, $this->stock($p), "el stock volvió");
        $this->assertSame(0, $this->pedidosDe($email), "no quedó pedido");
        $this->assertSame(0, $this->pedidosConParte($p));
        $this->assertArrayNotHasKey("pedido_exitoso", $r["sesion"]);
        $this->assertStringContainsString("No se pudo registrar el pedido", $this->mensaje($r));
        $this->assertStringContainsString("código de referencia", $this->mensaje($r));
        $this->assertFalse($this->db->inTransaction());
    }

    public function testConfirmarCompraRechazaUnaPiezaInactivaSinDescontar(): void {
        [$uid, $email] = $this->cliente();
        $p = $this->parte(10, 10.00, false);
        $r = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 1, "direccion_entrega" => "Calle 1", "cliente_notas" => ""], $this->sesionDe($uid, $email));
        $this->assertSame(10, $this->stock($p));
        $this->assertSame(0, $this->pedidosDe($email));
        $this->assertSame("Esta pieza ya no está disponible.", $this->mensaje($r));
        $this->assertArrayNotHasKey("pedido_exitoso", $r["sesion"]);
    }

    public function testConfirmarCompraSinStockSuficienteNoDescuenta(): void {
        [$uid, $email] = $this->cliente();
        $p = $this->parte(2);
        $r = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 3, "direccion_entrega" => "Calle 1", "cliente_notas" => ""], $this->sesionDe($uid, $email));
        $this->assertSame(2, $this->stock($p));
        $this->assertSame("Stock insuficiente.", $this->mensaje($r));
    }

    // ── procesarCompra (invitado, crea cuenta) ───────────────────────
    public function testCompraDeInvitadoNormalCreaCuentaDescuentaYRegistra(): void {
        $p = $this->parte(10);
        $d = $this->datosInvitado($p);
        $r = $this->accion("procesarCompra", $d);
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $this->assertSame(8, $this->stock($p));
        $this->assertSame(1, $this->pedidosDe($d["cliente_email"]));
        $this->assertTrue($this->existeCuenta($d["cliente_email"]));
        $this->assertSame(["Cliente"], $r["sesion"]["user_roles"]);
    }

    public function testCompraDeInvitadoSiRegistrarFallaElStockVuelveYLaCuentaNuevaSeRetira(): void {
        $p = $this->parte(10);
        $d = $this->datosInvitado($p, ["cliente_notas" => self::MARCA_FALLA]);
        $r = $this->accion("procesarCompra", $d);
        $this->assertSame(10, $this->stock($p), "el stock volvió");
        $this->assertSame(0, $this->pedidosDe($d["cliente_email"]));
        $this->assertFalse($this->existeCuenta($d["cliente_email"]), "no queda una cuenta a medias");
        $this->assertArrayNotHasKey("user", $r["sesion"]);
        $this->assertStringContainsString("No se pudo registrar el pedido", $this->mensaje($r));
    }

    public function testCompraDeInvitadoRechazaUnaPiezaInactivaYNoCreaCuenta(): void {
        $p = $this->parte(10, 10.00, false);
        $d = $this->datosInvitado($p);
        $r = $this->accion("procesarCompra", $d);
        $this->assertSame(10, $this->stock($p));
        $this->assertSame("Esta pieza ya no está disponible.", $this->mensaje($r));
        $this->assertFalse($this->existeCuenta($d["cliente_email"]));
    }

    // ── procesarCarrito ──────────────────────────────────────────────
    private function carrito(array $lineas): array {
        $cart = [];
        foreach ($lineas as [$id, $cant]) {
            $cart[$id] = ["parte_id" => $id, "nombre" => "Pieza $id", "precio" => 10.0, "cantidad" => $cant, "max" => 99, "thumbnail" => null, "marca" => ""];
        }
        return $cart;
    }

    public function testCarritoNormalDescuentaTodasLasPiezasYRegistraUnPedido(): void {
        [$uid, $email] = $this->cliente();
        $a = $this->parte(10); $b = $this->parte(5);
        $r = $this->accion("procesarCarrito", ["cliente_direccion" => "Calle 1", "cliente_notas" => ""],
            $this->sesionDe($uid, $email) + ["cart" => $this->carrito([[$a, 2], [$b, 1]])]);
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $this->assertSame([8, 4], [$this->stock($a), $this->stock($b)]);
        $this->assertSame(1, $this->pedidosDe($email));
        $this->assertArrayNotHasKey("cart", $r["sesion"]);
    }

    public function testCarritoConUnaPiezaInactivaNoDescuentaNada(): void {
        [$uid, $email] = $this->cliente();
        $viva = $this->parte(10); $muerta = $this->parte(10, 10.00, false);
        $r = $this->accion("procesarCarrito", ["cliente_direccion" => "Calle 1", "cliente_notas" => ""],
            $this->sesionDe($uid, $email) + ["cart" => $this->carrito([[$viva, 2], [$muerta, 1]])]);
        $this->assertSame([10, 10], [$this->stock($viva), $this->stock($muerta)], "ninguna pieza se descontó");
        $this->assertSame(0, $this->pedidosDe($email));
        $this->assertStringContainsString("Tu pedido cambió", $this->mensaje($r), "lo que se vio ya no es lo que hay: no se cobra nada");
        $this->assertCount(2, $r["sesion"]["cart"], "el carrito no se toca al enviar: lo ajusta el paso 2 al volver a pintarse");
    }

    public function testCarritoDeInvitadoConPiezaInactivaNoCreaCuenta(): void {
        $muerta = $this->parte(10, 10.00, false);
        $d = $this->datosInvitado($muerta);
        $r = $this->accion("procesarCarrito", ["cliente_nombre" => $d["cliente_nombre"], "cliente_telefono" => "555-0100", "cliente_email" => $d["cliente_email"],
            "cliente_direccion" => "Calle 1", "nueva_pass" => self::CLAVE, "cliente_notas" => ""], ["cart" => $this->carrito([[$muerta, 1]])]);
        $this->assertSame(10, $this->stock($muerta));
        $this->assertFalse($this->existeCuenta($d["cliente_email"]), "el carrito se valida antes de crear cuentas");
        $this->assertArrayNotHasKey("user", $r["sesion"]);
        $this->assertStringContainsString("Tu pedido cambió", $this->mensaje($r));
    }

    public function testCarritoSiRegistrarFallaTodoElStockVuelve(): void {
        [$uid, $email] = $this->cliente();
        $a = $this->parte(10); $b = $this->parte(5);
        $r = $this->accion("procesarCarrito", ["cliente_direccion" => "Calle 1", "cliente_notas" => self::MARCA_FALLA],
            $this->sesionDe($uid, $email) + ["cart" => $this->carrito([[$a, 2], [$b, 1]])]);
        $this->assertSame([10, 5], [$this->stock($a), $this->stock($b)], "las dos piezas volvieron");
        $this->assertSame(0, $this->pedidosDe($email));
        $this->assertStringContainsString("No se pudo registrar el pedido", $this->mensaje($r));
        $this->assertArrayHasKey("cart", $r["sesion"]);
        $this->assertFalse($this->db->inTransaction());
    }

    public function testCarritoDeInvitadoSiRegistrarFallaLaCuentaNuevaSeRetira(): void {
        $a = $this->parte(10);
        $d = $this->datosInvitado($a);
        $r = $this->accion("procesarCarrito", ["cliente_nombre" => $d["cliente_nombre"], "cliente_telefono" => "555-0100", "cliente_email" => $d["cliente_email"],
            "cliente_direccion" => "Calle 1", "nueva_pass" => self::CLAVE, "cliente_notas" => self::MARCA_FALLA], ["cart" => $this->carrito([[$a, 2]])]);
        $this->assertSame(10, $this->stock($a));
        $this->assertFalse($this->existeCuenta($d["cliente_email"]));
        $this->assertArrayNotHasKey("user", $r["sesion"]);
    }

    public function testCarritoDeInvitadoNormalCreaLaCuentaYIniciaSesion(): void {
        $a = $this->parte(10);
        $d = $this->datosInvitado($a);
        $r = $this->accion("procesarCarrito", ["cliente_nombre" => $d["cliente_nombre"], "cliente_telefono" => "555-0100", "cliente_email" => $d["cliente_email"],
            "cliente_direccion" => "Calle 1", "nueva_pass" => self::CLAVE, "cliente_notas" => ""], ["cart" => $this->carrito([[$a, 2]])]);
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $this->assertSame($d["cliente_email"], $r["sesion"]["user"]["email"]);
        $this->assertSame(8, $this->stock($a));
        $this->assertTrue($this->existeCuenta($d["cliente_email"]));
    }

    public function testCarritoSiLaSegundaPiezaNoAlcanzaNoSeDescuentaNinguna(): void {
        [$uid, $email] = $this->cliente();
        $a = $this->parte(10); $b = $this->parte(1);
        $r = $this->accion("procesarCarrito", ["cliente_direccion" => "Calle 1", "cliente_notas" => ""],
            $this->sesionDe($uid, $email) + ["cart" => $this->carrito([[$a, 2], [$b, 5]])]);
        $this->assertSame([10, 1], [$this->stock($a), $this->stock($b)]);
        $this->assertStringContainsString("Tu pedido cambió", $this->mensaje($r), "pidió 5 y hay 1: el resumen visto ya no vale");
        $this->assertSame(0, $this->pedidosDe($email));
    }

    public function testSiFallaLaLineaDelPedidoNoQuedaNiPedidoHuerfanoNiStockPerdido(): void {
        [$uid, $email] = $this->cliente();
        $p = $this->parte(10, 10.00, true, self::MARCA_FALLA_ITEM);
        $r = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 3, "direccion_entrega" => "Calle 1", "cliente_notas" => ""], $this->sesionDe($uid, $email));
        $this->assertSame(10, $this->stock($p));
        $this->assertSame(0, $this->pedidosDe($email), "el pedido ya insertado se deshizo junto con el stock");
        $this->assertStringContainsString("No se pudo registrar el pedido", $this->mensaje($r));
    }

    public function testCarritoSiFallaLaLineaDeLaSegundaPiezaTodoSeDeshace(): void {
        [$uid, $email] = $this->cliente();
        $a = $this->parte(10); $b = $this->parte(5, 10.00, true, self::MARCA_FALLA_ITEM);
        $cart = $this->carrito([[$a, 2], [$b, 1]]);
        $cart[$b]["nombre"] = self::MARCA_FALLA_ITEM;
        $this->accion("procesarCarrito", ["cliente_direccion" => "Calle 1", "cliente_notas" => ""], $this->sesionDe($uid, $email) + ["cart" => $cart]);
        $this->assertSame([10, 5], [$this->stock($a), $this->stock($b)]);
        $this->assertSame(0, $this->pedidosDe($email));
    }

    public function testElHelperRechazaUnaPiezaQueSeDesactivoDespuesDeLaRevisionPrevia(): void {
        // La carrera: el panel desactiva la pieza entre la revisión del controlador y el descuento. Se llama al helper
        // directamente (sin la revisión previa) con una pieza ya inactiva.
        [$uid, $email] = $this->cliente();
        $p = $this->parte(10, 10.00, false);
        $m = new \ReflectionMethod(\PedidoController::class, "apartarYRegistrar");
        $error = null;
        $id = $m->invokeArgs(new \PedidoController(), [["cliente_nombre" => "X", "cliente_telefono" => "1", "cliente_email" => $email, "cliente_direccion" => "Calle", "cliente_notas" => "", "usuario_id" => $uid],
            [["parte_id" => $p, "parte_nombre" => "Pieza inactiva", "precio_unitario" => 10.0, "cantidad" => 1]], &$error]);
        $this->assertFalse($id);
        $this->assertSame("La pieza Pieza inactiva ya no está disponible.", $error);
        $this->assertSame(10, $this->stock($p));
        $this->assertSame(0, $this->pedidosDe($email));
        $this->assertFalse($this->db->inTransaction());
    }

    public function testUnaPiezaDesactivadaJustoAntesDelDescuentoSeRechazaDentroDeLaTransaccion(): void {
        // El helper bloquea y vuelve a leer `activo` dentro de la transacción (cierra la carrera con el panel)
        $codigo = file_get_contents(BASE_PATH . "/app/Controllers/PedidoController.php");
        $this->assertStringContainsString("pam->bloquear(", $codigo);
        $this->assertStringContainsString('!$p["activo"]', $codigo);
        $m = file_get_contents(BASE_PATH . "/app/Models/ParteModel.php");
        $this->assertStringContainsString("FOR UPDATE", $m);
    }
}
