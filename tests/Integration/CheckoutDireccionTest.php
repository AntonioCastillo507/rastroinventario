<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Proceso;

/**
 * Dirección de Panamá en la compra (S8.4): invitado, cliente y carrito guardan una copia fija con los nombres de la base, el texto compuesto
 * y la casilla «Guardar esta dirección en mi perfil»; cambiar el perfil después no toca pedidos pasados. Controladores reales en un
 * proceso aparte; usa commits y borra lo suyo al final por ids y correos exactos.
 */
final class CheckoutDireccionTest extends TestCase {
    private const CLAVE = "Frase de prueba para la compra 2026";
    private PDO $db;
    private array $partes = [];
    private array $uids = [];
    private array $correos = [];
    private string $logs;

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->logs = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "checkout_" . uniqid();
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

    private function parte(int $stock = 10): int {
        $id = Datos::parte($this->db, 10.00, $stock);
        $this->partes[] = $id;
        return $id;
    }

    /** @return array{0:int,1:string} */
    private function cliente(bool $conDireccion = false): array {
        $email = "checkout." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $email;
        $this->db->prepare("INSERT INTO usuarios (nombre, email, password) VALUES ('Cliente Checkout', ?, ?)")->execute([$email, password_hash(self::CLAVE, PASSWORD_BCRYPT)]);
        $id = (int)$this->db->lastInsertId();
        $this->uids[] = $id;
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = 'Cliente'")->execute([$id]);
        if ($conDireccion) (new \UserModel())->update($id, \DireccionPanama::paraUsuario(\DireccionPanama::validar(Datos::dirPost(["calle" => "Calle del perfil 9"]), new \GeoPanamaModel())["datos"]));
        return [$id, $email];
    }

    private function sesionDe(int $uid, string $email, array $extra = []): array {
        return ["user" => ["id" => $uid, "nombre" => "Cliente Checkout", "email" => $email], "user_roles" => ["Cliente"]] + $extra;
    }

    private function accion(string $metodo, array $post, array $sesion = []): array {
        if ($metodo === "procesarCarrito" && !array_key_exists("resumen_visto", $post)) $post["resumen_visto"] = Datos::huella($this->db, $sesion["cart"] ?? []);
        return Proceso::accion(["clase" => "PedidoController", "accion" => $metodo, "csrf" => true, "ip" => "203.0.113.78",
            "post" => $post, "sesion" => $sesion, "correo" => "ok", "dir_logs" => $this->logs]);
    }

    private function carrito(array $lineas): array {
        $c = [];
        foreach ($lineas as [$parte, $cant]) $c[$parte] = ["parte_id" => $parte, "nombre" => "x", "precio" => 0.01, "cantidad" => $cant, "max" => 99, "thumbnail" => null, "marca" => ""];
        return $c;
    }

    private function mensaje(array $r): string {
        return (string)($r["sesion"]["flash"]["msg"] ?? $r["sesion"]["compra_error"] ?? $r["sesion"]["carrito_error"] ?? "");
    }

    private function ultimoPedido(string $email): array {
        $s = $this->db->prepare("SELECT * FROM pedidos WHERE cliente_email = ? ORDER BY id DESC LIMIT 1");
        $s->execute([$email]);
        return $s->fetch() ?: [];
    }

    private function usuario(int $uid): array {
        return (new \UserModel())->findById($uid);
    }

    private function datosInvitado(int $parte, array $dir = []): array {
        $email = "invitado." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $email;
        return ["parte_id" => $parte, "cantidad" => 1, "cliente_nombre" => "Invitado Checkout", "cliente_email" => $email, "nueva_pass" => self::CLAVE, "cliente_notas" => ""]
            + Datos::dirPost($dir);
    }

    // ── La copia fija ────────────────────────────────────────────────

    public function testInvitadoGuardaLaCopiaConNombresDeLaBaseYCreaLaCuentaConSuDireccion(): void {
        $p = $this->parte();
        $d = $this->datosInvitado($p, ["referencia" => "Portón blanco", "provincia_nombre" => "Inventada", "distrito_nombre" => "Falso"]);
        $r = $this->accion("procesarCompra", $d);
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $ped = $this->ultimoPedido($d["cliente_email"]);
        $this->assertSame("Panamá", $ped["entrega_provincia"]);
        $this->assertSame("Panamá", $ped["entrega_distrito"]);
        $this->assertSame("Bella Vista", $ped["entrega_corregimiento"]);
        $this->assertSame("Calle 50", $ped["entrega_calle"]);
        $this->assertSame("Portón blanco", $ped["entrega_referencia"]);
        $this->assertSame("Calle 50, Casa 5-B, Bella Vista, Bella Vista, Panamá, Panamá. Ref.: Portón blanco", $ped["cliente_direccion"]);
        $this->assertSame("6123-4567", $ped["cliente_telefono"]);
        $u = $this->usuario((int)$ped["usuario_id"]);
        $this->assertSame("08", $u["dir_provincia"], "la cuenta nueva guarda su dirección por códigos");
        $this->assertSame("Calle 50", $u["dir_calle"]);
    }

    public function testCambiarElPerfilDespuesNoAlteraElPedido(): void {
        [$uid, $email] = $this->cliente();
        $p = $this->parte();
        $r = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 1, ...Datos::dirPost(), "guardar_direccion" => "1", "cliente_notas" => ""], $this->sesionDe($uid, $email));
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $antes = $this->ultimoPedido($email);
        (new \UserModel())->update($uid, ["dir_calle" => "Otra calle 77", "dir_barriada" => "Otra barriada", "direccion" => "Otra"]);
        $despues = $this->ultimoPedido($email);
        $this->assertSame($antes, $despues);
        $this->assertSame("Calle 50", $despues["entrega_calle"]);
        // y al revés: lo que se compra después no toca el pedido anterior
        $r2 = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 1, ...Datos::dirPost(["calle" => "Calle 13 Sur"]), "cliente_notas" => ""], $this->sesionDe($uid, $email));
        $this->assertArrayHasKey("pedido_exitoso", $r2["sesion"]);
        $this->assertSame("Calle 50", $this->db->query("SELECT entrega_calle FROM pedidos WHERE id = " . (int)$r["sesion"]["pedido_exitoso"])->fetchColumn());
    }

    public function testNombresImplicitosNoVanALaCopia(): void {
        [$uid, $email] = $this->cliente();
        $p = $this->parte();
        $r = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 1, ...Datos::dirPost(["provincia" => "10", "distrito" => "", "corregimiento" => "100101", "barriada" => "Narganá"]), "cliente_notas" => ""], $this->sesionDe($uid, $email));
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $ped = $this->ultimoPedido($email);
        $this->assertSame("Comarca Guna Yala", $ped["entrega_provincia"]);
        $this->assertNull($ped["entrega_distrito"], "el distrito ficticio no se copia");
        $this->assertSame("Narganá", $ped["entrega_corregimiento"]);
        $r = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 1, ...Datos::dirPost(["provincia" => "01", "distrito" => "0105", "corregimiento" => "", "barriada" => "Siey"]), "cliente_notas" => ""], $this->sesionDe($uid, $email));
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $ped = $this->ultimoPedido($email);
        $this->assertSame("Comarca Naso Tjër Di", $ped["entrega_distrito"]);
        $this->assertNull($ped["entrega_corregimiento"], "el área única de Naso no se copia");
    }

    // ── Validación en el checkout ────────────────────────────────────

    public function testInvitadoConCombinacionInvalidaNoCreaNadaYConservaLoEscrito(): void {
        $p = $this->parte(10);
        $d = $this->datosInvitado($p, ["distrito" => "0301"]);
        $r = $this->accion("procesarCompra", $d);
        $this->assertArrayNotHasKey("pedido_exitoso", $r["sesion"]);
        $this->assertStringContainsString("combinación", $this->mensaje($r));
        $this->assertSame(10, Datos::stock($this->db, $p));
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM usuarios WHERE email = " . $this->db->quote($d["cliente_email"]))->fetchColumn(), "ni cuenta");
        $this->assertSame("Calle 50", $r["sesion"]["form_viejo"]["compra"]["calle"]);
    }

    public function testClienteConTextoBasuraONadaDeDireccionNoComprada(): void {
        [$uid, $email] = $this->cliente();
        $p = $this->parte(10);
        foreach ([["calle" => "asdfgh"], ["barriada" => "!!!!"], ["provincia" => ""], ["corregimiento" => "999999"]] as $mala) {
            $r = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 1, ...Datos::dirPost($mala), "cliente_notas" => ""], $this->sesionDe($uid, $email));
            $this->assertArrayNotHasKey("pedido_exitoso", $r["sesion"], json_encode($mala));
            $this->assertNotSame("", $this->mensaje($r));
        }
        $this->assertSame(10, Datos::stock($this->db, $p));
        $this->assertSame([], $this->ultimoPedido($email));
    }

    public function testElTelefonoDebeSerDePanamaEnLosTresFlujos(): void {
        [$uid, $email] = $this->cliente();
        $p = $this->parte(10);
        $r = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 1, ...Datos::dirPost(["cliente_telefono" => "12345"]), "cliente_notas" => ""], $this->sesionDe($uid, $email));
        $this->assertStringContainsString("teléfono de Panamá", $this->mensaje($r));
        $r = $this->accion("procesarCarrito", [...Datos::dirPost(["cliente_telefono" => "7542-0519-9"]), "cliente_notas" => ""], $this->sesionDe($uid, $email, ["cart" => $this->carrito([[$p, 1]])]));
        $this->assertStringContainsString("teléfono de Panamá", $this->mensaje($r));
        $d = $this->datosInvitado($p, ["cliente_telefono" => "abc"]);
        $r = $this->accion("procesarCompra", $d);
        $this->assertStringContainsString("teléfono de Panamá", $this->mensaje($r));
        $this->assertSame(10, Datos::stock($this->db, $p));
    }

    // ── Casilla «Guardar esta dirección en mi perfil» ────────────────

    public function testSinCasillaLaDireccionSoloVaAlPedido(): void {
        [$uid, $email] = $this->cliente(true);
        $p = $this->parte();
        $r = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 1, ...Datos::dirPost(["calle" => "Solo para este pedido 1"]), "cliente_notas" => ""], $this->sesionDe($uid, $email));
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $this->assertSame("Solo para este pedido 1", $this->ultimoPedido($email)["entrega_calle"]);
        $this->assertSame("Calle del perfil 9", $this->usuario($uid)["dir_calle"], "el perfil no cambió");
    }

    public function testConLaCasillaMarcadaElPerfilQuedaConLaDireccion(): void {
        [$uid, $email] = $this->cliente();
        $p = $this->parte();
        $r = $this->accion("confirmarCompra", ["parte_id" => $p, "cantidad" => 1, ...Datos::dirPost(["calle" => "Guardada en el perfil 3"]), "guardar_direccion" => "1", "cliente_notas" => ""], $this->sesionDe($uid, $email));
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $u = $this->usuario($uid);
        $this->assertSame("Guardada en el perfil 3", $u["dir_calle"]);
        $this->assertSame("6123-4567", $u["telefono"], "el teléfono se guarda si el perfil no tenía");
    }

    public function testCarritoDeClienteTambienRespetaLaCasillaYLaCopia(): void {
        [$uid, $email] = $this->cliente(true);
        $p = $this->parte();
        $r = $this->accion("procesarCarrito", [...Datos::dirPost(["calle" => "Del carrito 8"]), "cliente_notas" => ""], $this->sesionDe($uid, $email, ["cart" => $this->carrito([[$p, 1]])]));
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $this->assertSame("Del carrito 8", $this->ultimoPedido($email)["entrega_calle"]);
        $this->assertSame("Calle del perfil 9", $this->usuario($uid)["dir_calle"]);
        $r = $this->accion("procesarCarrito", [...Datos::dirPost(["calle" => "Guardada desde el carrito 2"]), "guardar_direccion" => "1", "cliente_notas" => ""], $this->sesionDe($uid, $email, ["cart" => $this->carrito([[$p, 1]])]));
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $this->assertSame("Guardada desde el carrito 2", $this->usuario($uid)["dir_calle"]);
    }

    public function testCarritoDeInvitadoGuardaLaCopiaYLaCuenta(): void {
        $p = $this->parte();
        $d = $this->datosInvitado($p) + ["cliente_telefono" => "6123-4567"];
        unset($d["parte_id"], $d["cantidad"]);
        $r = $this->accion("procesarCarrito", $d, ["cart" => $this->carrito([[$p, 1]])]);
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], $this->mensaje($r));
        $ped = $this->ultimoPedido($d["cliente_email"]);
        $this->assertSame("Bella Vista", $ped["entrega_corregimiento"]);
        $this->assertSame("Calle 50", $this->usuario((int)$ped["usuario_id"])["dir_calle"]);
    }

    // ── Cómo se muestra ──────────────────────────────────────────────

    private function pedidoConDireccion(array $extra = []): array {
        $this->db->prepare("INSERT INTO pedidos (cliente_nombre, cliente_telefono, cliente_email, cliente_direccion) VALUES ('A', '1', ?, 'texto')")->execute([$e = "vista." . bin2hex(random_bytes(3)) . "@example.invalid"]);
        $this->correos[] = $e;
        $id = (int)$this->db->lastInsertId();
        foreach ($extra as $c => $v) $this->db->prepare("UPDATE pedidos SET $c = ? WHERE id = ?")->execute([$v, $id]);
        return (new \PedidoModel())->findWithItems($id);
    }

    private function parcial(array $pedido): string {
        ob_start();
        include BASE_PATH . "/app/Views/layouts/direccion_texto.php";
        return (string)ob_get_clean();
    }

    public function testLaDireccionEstructuradaSeMuestraEnLineasYEscapada(): void {
        $ped = $this->pedidoConDireccion(["entrega_calle" => "Calle <b>50</b>", "entrega_casa" => "Casa 5", "entrega_barriada" => "Bella Vista", "entrega_corregimiento" => "Bella Vista",
            "entrega_distrito" => "Panamá", "entrega_provincia" => "Panamá", "entrega_referencia" => '"><script>x</script>']);
        $html = $this->parcial($ped);
        $this->assertSame(4, substr_count($html, 'class="direccion-linea"'));
        $this->assertStringContainsString("Calle &lt;b&gt;50&lt;/b&gt;, Casa 5", $html);
        $this->assertStringContainsString("Bella Vista, Panamá, Panamá", $html);
        $this->assertStringNotContainsString("<script>", $html);
        $this->assertStringNotContainsString("<b>", $html);
        $this->assertStringNotContainsString("direccion-nota", $html);
    }

    public function testUnPedidoAnteriorMuestraSuTextoLibreEscapado(): void {
        $ped = $this->pedidoConDireccion(["cliente_direccion" => "Calle 13, Monterrico <i>x</i>"]);
        $html = $this->parcial($ped);
        $this->assertStringContainsString("Calle 13, Monterrico &lt;i&gt;x&lt;/i&gt;", $html);
        $this->assertStringContainsString("Escrita antes del formulario de Panamá", $html);
        $this->assertSame(["Calle 13, Monterrico <i>x</i>"], \DireccionPanama::lineas($ped));
    }

    public function testLaFacturaLlevaLaDireccionEnVariasLineasYLaAnteriorEnUna(): void {
        $nuevo = $this->pedidoConDireccion(["entrega_calle" => "Calle 50", "entrega_casa" => "Casa 5-B", "entrega_barriada" => "Barriada Linda", "entrega_corregimiento" => "Bella Vista",
            "entrega_distrito" => "Panamá", "entrega_provincia" => "Panamá", "entrega_referencia" => "Portón blanco"]);
        $viejo = $this->pedidoConDireccion(["cliente_direccion" => "Cabra, Santa Isabel"]);
        foreach ([[$nuevo, ["Calle 50, Casa 5-B", "Barriada Linda", "Bella Vista, Panam", "Ref.: Port"]], [$viejo, ["Cabra, Santa Isabel"]]] as [$ped, $esperado]) {
            $pdf = (new \FacturaController())->construirPDF($ped);
            $pdf->setCompression(false);
            $bin = $pdf->Output("f.pdf", "S");
            foreach ($esperado as $t) $this->assertStringContainsString($t, $bin, "la factura debe llevar «{$t}»");
        }
    }
}
