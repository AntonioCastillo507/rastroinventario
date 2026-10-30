<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Proceso;

/**
 * resumen_visto (paso 2): la huella de lo que la persona vio tiene que coincidir con lo que hay al enviar; si no, NO se crea
 * ningún pedido ni cuenta y se vuelve al paso 2 con lo escrito y el carrito sin tocar. Y las carreras reales: dos compras
 * simultáneas de la última unidad (procesos hijos a la vez) y dos carritos con las mismas piezas en orden opuesto.
 * PedidoController real en procesos aparte; usa commits y borra lo suyo al final por ids y correos exactos.
 */
final class CarritoResumenVistoTest extends TestCase {
    private const CLAVE = "Frase de prueba del resumen visto 2026";
    private PDO $db;
    private array $partes = [];
    private array $correos = [];

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
    }

    protected function tearDown(): void {
        $ids = [];
        if ($this->correos) {
            $inE = implode(",", array_map([$this->db, "quote"], $this->correos));
            $ids = $this->db->query("SELECT id FROM pedidos WHERE cliente_email IN ($inE)")->fetchAll(PDO::FETCH_COLUMN);
            $this->db->exec("DELETE FROM usuarios WHERE email IN ($inE)");
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
        parent::tearDown();
    }

    private function parte(int $stock, float $precio = 10.00, string $nombre = ""): int {
        $id = Datos::parte($this->db, $precio, $stock, $nombre);
        $this->partes[] = $id;
        return $id;
    }

    private function correo(): string {
        return $this->correos[] = "visto." . bin2hex(random_bytes(4)) . "@example.invalid";
    }

    /** Lo que el formulario del paso 2 manda para un invitado; $extra pisa cualquier campo (p. ej. resumen_visto) */
    private function post(array $cart, array $extra = [], ?string $email = null): array {
        return $extra + ["resumen_visto" => Datos::huella($this->db, $cart), "cliente_nombre" => "Invitado Visto", "cliente_telefono" => "555-0100",
            "cliente_email" => $email ?? $this->correo(), ...Datos::dirPost(), "cliente_notas" => "", "nueva_pass" => self::CLAVE];
    }

    private function comprar(array $post, array $cart, string $ip = "203.0.113.83"): array {
        return Proceso::accion(["clase" => "PedidoController", "accion" => "procesarCarrito", "csrf" => true, "ip" => $ip, "correo" => "ok",
            "post" => $post, "sesion" => ["csrf_token" => str_repeat("e", 64), "cart" => $cart]]);
    }

    private function pedidos(string $email): int {
        $s = $this->db->prepare("SELECT COUNT(*) FROM pedidos WHERE cliente_email = ?");
        $s->execute([$email]);
        return (int)$s->fetchColumn();
    }

    private function cuentas(string $email): int {
        $s = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $s->execute([$email]);
        return (int)$s->fetchColumn();
    }

    /** Afirma que no pasó nada de dinero y que se volvió al paso 2 con lo escrito */
    private function assertRechazado(array $r, string $email, array $cart, array $stockEsperado): void {
        $this->assertArrayNotHasKey("pedido_exitoso", $r["sesion"]);
        $this->assertSame(0, $this->pedidos($email), "ningún pedido");
        $this->assertSame(0, $this->cuentas($email), "ninguna cuenta nueva");
        $this->assertArrayNotHasKey("user", $r["sesion"]);
        foreach ($stockEsperado as $id => $stock) $this->assertSame($stock, Datos::stock($this->db, $id), "stock de $id");
        $this->assertSame("warning", $r["sesion"]["flash"]["type"]);
        $this->assertStringContainsString("Tu pedido cambió", $r["sesion"]["flash"]["msg"]);
        $this->assertSame("Invitado Visto", $r["sesion"]["form_viejo"]["carrito"]["cliente_nombre"], "lo escrito se conserva");
        $this->assertArrayNotHasKey("nueva_pass", $r["sesion"]["form_viejo"]["carrito"]);
        $this->assertArrayHasKey("cart", $r["sesion"], "el carrito sigue");
        if (Proceso::veCabeceras()) $this->assertStringEndsWith("/?action=carrito_datos", (string)Proceso::ubicacion($r));
    }

    // ── La huella coincide: se compra ──

    public function testConLaHuellaDeLoQueSeVioSeCompraYElOrdenDeLasLineasNoImporta(): void {
        $a = $this->parte(5, 10.00); $b = $this->parte(5, 4.50);
        $email = $this->correo();
        $r = $this->comprar($this->post([$b => 1, $a => 2], ["cliente_email" => $email], $email), [$b => 1, $a => 2]);
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], json_encode($r["sesion"]["flash"] ?? $r["sesion"]["carrito_error"] ?? ""));
        $this->assertSame([3, 4], [Datos::stock($this->db, $a), Datos::stock($this->db, $b)]);
        $this->assertSame(1, $this->pedidos($email));
    }

    // ── La huella no coincide: no se crea nada ──

    /** @return array<string,array{0:mixed}> */
    public static function huellasMalas(): array {
        return ["ausente" => [null], "vacía" => [""], "inventada" => [str_repeat("a", 64)], "un arreglo" => [["x"]], "número" => [123]];
    }

    #[DataProvider("huellasMalas")]
    public function testUnaHuellaAusenteOFalsaNoCreaNada(mixed $mala): void {
        $a = $this->parte(5);
        $email = $this->correo();
        $post = $this->post([$a => 2], ["cliente_email" => $email]);
        if ($mala === null) unset($post["resumen_visto"]); else $post["resumen_visto"] = $mala;
        $r = $this->comprar($post, [$a => 2], "203.0.113.84");
        $this->assertRechazado($r, $email, [$a => 2], [$a => 5]);
        $this->assertSame(5, Datos::stock($this->db, $a));
    }

    public function testElPrecioQueCambiaDespuesDeAbrirElPaso2NoSeCobraSinVerlo(): void {
        $a = $this->parte(5, 10.00);
        $email = $this->correo();
        $post = $this->post([$a => 2], ["cliente_email" => $email]); // lo que se vio: 2 × 10.00
        $this->db->exec("UPDATE partes SET precio = 10.01 WHERE id = $a"); // un centavo
        $r = $this->comprar($post, [$a => 2], "203.0.113.85");
        $this->assertRechazado($r, $email, [$a => 2], [$a => 5]);
    }

    public function testElStockQueBajaPorDebajoDeLoPedidoNoSeAjustaEnSilencio(): void {
        $a = $this->parte(5);
        $email = $this->correo();
        $post = $this->post([$a => 4], ["cliente_email" => $email]);
        $this->db->exec("UPDATE partes SET cantidad = 3 WHERE id = $a");
        $r = $this->comprar($post, [$a => 4], "203.0.113.86");
        $this->assertRechazado($r, $email, [$a => 4], [$a => 3]);
        $this->assertSame(4, $r["sesion"]["cart"][$a], "no se recortó la sesión al enviar: eso lo hace el paso 2 al volver a pintarse y avisar");
    }

    public function testUnaPiezaDesactivadaDespuesDeAbrirElPaso2NoSeCompra(): void {
        $a = $this->parte(5); $b = $this->parte(5);
        $email = $this->correo();
        $post = $this->post([$a => 1, $b => 1], ["cliente_email" => $email]);
        $this->db->exec("UPDATE partes SET activo = 0 WHERE id = $b");
        $r = $this->comprar($post, [$a => 1, $b => 1], "203.0.113.87");
        $this->assertRechazado($r, $email, [$a => 1, $b => 1], [$a => 5, $b => 5]);
    }

    public function testOtraPestanaQueCambioLaCantidadDetieneElEnvioDeLaVieja(): void {
        $a = $this->parte(5);
        $email = $this->correo();
        $post = $this->post([$a => 1], ["cliente_email" => $email]); // la pestaña vieja vio 1 unidad
        $r = $this->comprar($post, [$a => 3], "203.0.113.88");       // la sesión ya tiene 3
        $this->assertRechazado($r, $email, [$a => 3], [$a => 5]);
    }

    public function testUnaLineaNuevaDeOtraPestanaTambienLaDetiene(): void {
        $a = $this->parte(5); $b = $this->parte(5);
        $email = $this->correo();
        $post = $this->post([$a => 1], ["cliente_email" => $email]);
        $r = $this->comprar($post, [$a => 1, $b => 1], "203.0.113.89");
        $this->assertRechazado($r, $email, [$a => 1, $b => 1], [$a => 5, $b => 5]);
    }

    public function testElStockQueSubeNoCambiaLoVistoYLaCompraSigue(): void {
        $a = $this->parte(5);
        $email = $this->correo();
        $post = $this->post([$a => 2], ["cliente_email" => $email]);
        $this->db->exec("UPDATE partes SET cantidad = 9 WHERE id = $a"); // se repuso mercancía: nada de lo visto cambió
        $r = $this->comprar($post, [$a => 2], "203.0.113.90");
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"]);
        $this->assertSame(7, Datos::stock($this->db, $a));
    }

    // ── Carreras reales: procesos simultáneos ──

    public function testDosComprasSimultaneasDeLaUltimaUnidadDejanUnSoloPedidoYStockCero(): void {
        for ($vez = 1; $vez <= 6; $vez++) {
            $u = $this->parte(1, 25.00, "Última unidad $vez");
            $e1 = $this->correo(); $e2 = $this->correo();
            $huella = Datos::huella($this->db, [$u => 1]);
            $entrada = fn(string $email, string $ip) => ["clase" => "PedidoController", "accion" => "procesarCarrito", "csrf" => true, "ip" => $ip, "correo" => "ok",
                "post" => $this->post([$u => 1], ["cliente_email" => $email, "resumen_visto" => $huella], $email), "sesion" => ["csrf_token" => str_repeat("e", 64), "cart" => [$u => 1]]];
            [$r1, $r2] = Proceso::paralelo([$entrada($e1, "203.0.113.91"), $entrada($e2, "203.0.113.92")]);
            $ganadores = (isset($r1["sesion"]["pedido_exitoso"]) ? 1 : 0) + (isset($r2["sesion"]["pedido_exitoso"]) ? 1 : 0);
            $this->assertSame(1, $ganadores, "vez $vez: solo uno compra la última unidad");
            $this->assertSame(0, Datos::stock($this->db, $u), "vez $vez: ni sobrante ni negativo");
            $this->assertSame(1, $this->pedidos($e1) + $this->pedidos($e2), "vez $vez: un solo pedido");
            $perdedor = isset($r1["sesion"]["pedido_exitoso"]) ? [$r2, $e2] : [$r1, $e1];
            $this->assertSame(0, $this->cuentas($perdedor[1]), "vez $vez: la cuenta del que no compró no queda");
            $this->assertStringContainsString("Stock insuficiente", (string)($perdedor[0]["sesion"]["carrito_error"] ?? $perdedor[0]["sesion"]["flash"]["msg"] ?? ""));
        }
    }

    public function testDosCarritosConLasMismasPiezasEnOrdenOpuestoNoSeInterbloquean(): void {
        for ($vez = 1; $vez <= 8; $vez++) {
            $a = $this->parte(5, 10.00); $b = $this->parte(5, 20.00);
            $e1 = $this->correo(); $e2 = $this->correo();
            $huella = Datos::huella($this->db, [$a => 1, $b => 1]);
            $entrada = fn(array $cart, string $email, string $ip) => ["clase" => "PedidoController", "accion" => "procesarCarrito", "csrf" => true, "ip" => $ip, "correo" => "ok",
                "post" => $this->post($cart, ["cliente_email" => $email, "resumen_visto" => $huella], $email), "sesion" => ["csrf_token" => str_repeat("e", 64), "cart" => $cart]];
            [$r1, $r2] = Proceso::paralelo([$entrada([$a => 1, $b => 1], $e1, "203.0.113.93"), $entrada([$b => 1, $a => 1], $e2, "203.0.113.94")]);
            $this->assertArrayHasKey("pedido_exitoso", $r1["sesion"], "vez $vez: " . json_encode($r1["sesion"]["carrito_error"] ?? ""));
            $this->assertArrayHasKey("pedido_exitoso", $r2["sesion"], "vez $vez: " . json_encode($r2["sesion"]["carrito_error"] ?? ""));
            $this->assertSame([3, 3], [Datos::stock($this->db, $a), Datos::stock($this->db, $b)], "vez $vez");
        }
    }
}
