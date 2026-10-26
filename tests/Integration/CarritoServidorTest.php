<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Proceso;

/**
 * Acciones del carrito (paso 1) con PedidoController real en un proceso aparte: cantidad absoluta con tope de stock real,
 * quitar con deshacer, totales leídos de la base (nunca de la sesión) y respuestas JSON con el estado completo.
 * Usa commits (el hijo es otra conexión) y borra lo suyo al final por ids.
 */
final class CarritoServidorTest extends TestCase {
    private const TOKEN = "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef";
    private PDO $db;
    private array $partes = [];

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
    }

    protected function tearDown(): void {
        if ($this->partes) {
            $in = implode(",", array_map("intval", $this->partes));
            $this->db->exec("DELETE FROM partes WHERE id IN ($in)");
        }
        parent::tearDown();
    }

    private function parte(int $stock, float $precio = 10.00, string $nombre = ""): int {
        $id = Datos::parte($this->db, $precio, $stock, $nombre);
        $this->partes[] = $id;
        return $id;
    }

    private function accion(string $metodo, int $id, array $post, array $sesion, bool $ajax = true, bool $csrf = true): array {
        return Proceso::accion(["clase" => "PedidoController", "accion" => $metodo, "csrf" => $csrf, "ip" => "203.0.113.80",
            "get" => $id ? ["id" => (string)$id] : [], "post" => $post, "sesion" => $sesion + ["csrf_token" => self::TOKEN],
            "servidor" => $ajax ? ["HTTP_X_REQUESTED_WITH" => "fetch"] : []]);
    }

    private function json(array $r): array {
        $d = json_decode(trim($r["salida"]), true);
        $this->assertIsArray($d, "debe ser JSON: " . $r["salida"]);
        return $d;
    }

    private function linea(array $d, int $id): ?array {
        foreach ($d["lineas"] as $l) if ($l["id"] === $id) return $l;
        return null;
    }

    // ── Cantidad ──

    public function testLaCantidadEsAbsolutaYLaRespuestaTraeTodoElEstadoFormateadoPorElServidor(): void {
        $a = $this->parte(10, 12.50); $b = $this->parte(10, 3.25);
        $r = $this->accion("actualizarCarrito", $a, ["cantidad" => "3"], ["cart" => [$a => 1, $b => 2]]);
        $d = $this->json($r);
        $this->assertSame(200, $r["codigo"]);
        $this->assertTrue($d["ok"]);
        $this->assertSame(3, $r["sesion"]["cart"][$a]);
        $this->assertSame(2, $r["sesion"]["cart"][$b], "las demás líneas no se tocan");
        $this->assertSame(["id" => $a, "cantidad" => 3, "max" => 10, "subtotal" => "37.50"], $this->linea($d, $a));
        $this->assertSame(2, $d["count"]);
        $this->assertSame(5, $d["piezas"]);
        $this->assertSame(["subtotal" => "44.00", "itbms" => "3.08", "total" => "47.08"], $d["resumen"], "44.00 + 7 %");
        $this->assertSame(self::TOKEN, $d["csrf"]);
        $this->assertStringContainsString("3 unidades", $d["msg"]);
        // repetir la misma petición da el mismo resultado (dos pestañas)
        $r2 = $this->accion("actualizarCarrito", $a, ["cantidad" => "3"], $r["sesion"]);
        $this->assertSame($d["resumen"], $this->json($r2)["resumen"]);
    }

    public function testElTotalUsaElPrecioDeLaBaseAunqueLaSesionTraigaOtro(): void {
        $a = $this->parte(10, 20.00);
        $sesion = ["cart" => [$a => ["parte_id" => $a, "nombre" => "Nombre viejo", "precio" => 0.01, "cantidad" => 2, "max" => 99, "thumbnail" => null, "marca" => ""]]];
        $d = $this->json($this->accion("actualizarCarrito", $a, ["cantidad" => "2"], $sesion));
        $this->assertSame("40.00", $d["resumen"]["subtotal"], "20.00 de la base, no 0.01 de la sesión");
        $this->db->exec("UPDATE partes SET precio = 21.50 WHERE id = $a");
        $d = $this->json($this->accion("actualizarCarrito", $a, ["cantidad" => "2"], $sesion));
        $this->assertSame("43.00", $d["resumen"]["subtotal"], "si el precio cambia, el carrito lo refleja");
    }

    public function testNoPasaDelStockRealYLoDice(): void {
        $a = $this->parte(4);
        $r = $this->accion("actualizarCarrito", $a, ["cantidad" => "99"], ["cart" => [$a => 1]]);
        $d = $this->json($r);
        $this->assertSame(4, $r["sesion"]["cart"][$a]);
        $this->assertTrue($d["alerta"]);
        $this->assertSame(4, $this->linea($d, $a)["cantidad"]);
        $this->assertStringContainsString("Solo quedan 4", implode(" ", $d["avisos"]));
    }

    public function testElStockSeLeeDeLaBaseNoDelMaxViejoDeLaSesion(): void {
        $a = $this->parte(6);
        $sesion = ["cart" => [$a => ["parte_id" => $a, "nombre" => "x", "precio" => 10.0, "cantidad" => 1, "max" => 2, "thumbnail" => null, "marca" => ""]]];
        $r = $this->accion("actualizarCarrito", $a, ["cantidad" => "5"], $sesion);
        $this->assertSame(5, $r["sesion"]["cart"][$a], "el max de la sesión (2) ya no manda");
    }

    /** @return array<string,array{0:mixed}> */
    public static function cantidadesInvalidas(): array {
        return ["texto" => ["abc"], "negativa" => ["-3"], "decimal" => ["2.5"], "vacía" => [""], "con espacio" => [" 3"], "notación científica" => ["1e1"], "arreglo" => [["3"]]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("cantidadesInvalidas")]
    public function testUnaCantidadInvalidaNoCambiaNada(mixed $mala): void {
        $a = $this->parte(10);
        $r = $this->accion("actualizarCarrito", $a, ["cantidad" => $mala], ["cart" => [$a => 2]]);
        $d = $this->json($r);
        $this->assertSame(422, $r["codigo"]);
        $this->assertFalse($d["ok"]);
        $this->assertSame(2, $r["sesion"]["cart"][$a]);
        $this->assertSame(2, $this->linea($d, $a)["cantidad"], "la respuesta trae el estado real para que la pantalla se corrija");
    }

    public function testCeroSeTrataComoUnaUnidadNoComoQuitar(): void {
        $a = $this->parte(10);
        $r = $this->accion("actualizarCarrito", $a, ["cantidad" => "0"], ["cart" => [$a => 3]]);
        $this->assertSame(1, $r["sesion"]["cart"][$a], "quitar es una acción aparte, con deshacer");
    }

    public function testUnaPiezaQueNoEstaEnElCarritoDa409ConElEstado(): void {
        $a = $this->parte(10); $b = $this->parte(10);
        $r = $this->accion("actualizarCarrito", $b, ["cantidad" => "2"], ["cart" => [$a => 1]]);
        $d = $this->json($r);
        $this->assertSame(409, $r["codigo"]);
        $this->assertFalse($d["ok"]);
        $this->assertArrayNotHasKey($b, $r["sesion"]["cart"]);
        $this->assertNotNull($this->linea($d, $a));
    }

    public function testLosPreciosEnElPostSeIgnoran(): void {
        $a = $this->parte(10, 50.00);
        $d = $this->json($this->accion("actualizarCarrito", $a, ["cantidad" => "2", "precio" => "0.01", "total" => "0.02", "subtotal" => "0.01"], ["cart" => [$a => 1]]));
        $this->assertSame("100.00", $d["resumen"]["subtotal"]);
    }

    public function testSinTokenDa403SinTocarElCarrito(): void {
        $a = $this->parte(10);
        $r = $this->accion("actualizarCarrito", $a, ["cantidad" => "5"], ["cart" => [$a => 1]], true, false);
        $this->assertSame(403, $r["codigo"]);
        $this->assertFalse($this->json($r)["ok"]);
        $this->assertSame(1, $r["sesion"]["cart"][$a]);
    }

    public function testPorGetNoCambiaNada(): void {
        $a = $this->parte(10);
        $r = Proceso::accion(["clase" => "PedidoController", "accion" => "actualizarCarrito", "metodo_http" => "GET", "get" => ["id" => (string)$a, "cantidad" => "5"], "sesion" => ["cart" => [$a => 1]]]);
        $this->assertSame(1, $r["sesion"]["cart"][$a]);
    }

    // ── Piezas que dejan de estar disponibles ──

    public function testUnaPiezaDesactivadaOAgotadaSeQuitaYSeAvisaConSuNombre(): void {
        $viva = $this->parte(10, 10.0, "Pieza viva"); $muerta = $this->parte(10, 10.0, "Pieza desactivada"); $agotada = $this->parte(10, 10.0, "Pieza agotada");
        $this->db->exec("UPDATE partes SET activo = 0 WHERE id = $muerta");
        $this->db->exec("UPDATE partes SET cantidad = 0 WHERE id = $agotada");
        $r = $this->accion("actualizarCarrito", $viva, ["cantidad" => "2"], ["cart" => [$viva => 1, $muerta => 1, $agotada => 3]]);
        $d = $this->json($r);
        $this->assertSame([$viva => 2], $r["sesion"]["cart"]);
        $this->assertSame(1, $d["count"]);
        $avisos = implode(" | ", $d["avisos"]);
        $this->assertStringContainsString("«Pieza desactivada» ya no está disponible", $avisos);
        $this->assertStringContainsString("«Pieza agotada» ya no está disponible", $avisos);
        $this->assertSame("20.00", $d["resumen"]["subtotal"], "lo no disponible no suma");
    }

    public function testUnaPiezaBorradaDeLaBaseSeQuitaSinRomperNada(): void {
        $viva = $this->parte(10);
        $r = $this->accion("actualizarCarrito", $viva, ["cantidad" => "1"], ["cart" => [$viva => 1, 99999999 => 2]]);
        $this->assertSame([$viva => 1], $r["sesion"]["cart"]);
        $this->assertStringContainsString("Una pieza ya no está disponible", implode(" ", $this->json($r)["avisos"]));
    }

    // ── Quitar y deshacer ──

    public function testQuitarDejaDeshacerYRestaurarLaVuelveConSuCantidad(): void {
        $a = $this->parte(10, 10.0, "Faro quitado"); $b = $this->parte(10);
        $r = $this->accion("quitarCarrito", $a, [], ["cart" => [$a => 3, $b => 1]]);
        $d = $this->json($r);
        $this->assertSame([$b => 1], $r["sesion"]["cart"]);
        $this->assertSame(["id" => $a, "nombre" => "Faro quitado"], $d["deshacer"]);
        $this->assertNull($this->linea($d, $a));
        $this->assertSame(3, $r["sesion"]["cart_deshacer"]["cantidad"]);

        $r2 = $this->accion("restaurarCarrito", $a, [], $r["sesion"]);
        $d2 = $this->json($r2);
        $this->assertTrue($d2["ok"]);
        $this->assertSame(3, $r2["sesion"]["cart"][$a]);
        $this->assertNull($d2["deshacer"]);
        $this->assertArrayNotHasKey("cart_deshacer", $r2["sesion"]);
        // un segundo "deshacer" no duplica nada
        $r3 = $this->accion("restaurarCarrito", $a, [], $r2["sesion"]);
        $this->assertSame(409, $r3["codigo"]);
        $this->assertSame(3, $r3["sesion"]["cart"][$a]);
    }

    public function testQuitarLaUltimaLineaDejaElCarritoVacioYSeSigueEnLaRespuesta(): void {
        $a = $this->parte(10);
        $d = $this->json($this->accion("quitarCarrito", $a, [], ["cart" => [$a => 1]]));
        $this->assertSame(0, $d["count"]);
        $this->assertSame([], $d["lineas"]);
        $this->assertSame("0.00", $d["resumen"]["total"]);
        $this->assertSame($a, $d["deshacer"]["id"]);
    }

    public function testDeshacerNoVuelveSiLaPiezaYaNoEstaDisponible(): void {
        $a = $this->parte(10, 10.0, "Faro sin stock");
        $r = $this->accion("quitarCarrito", $a, [], ["cart" => [$a => 2]]);
        $this->db->exec("UPDATE partes SET activo = 0 WHERE id = $a");
        $r2 = $this->accion("restaurarCarrito", $a, [], $r["sesion"]);
        $this->assertSame(409, $r2["codigo"]);
        $this->assertStringContainsString("ya no está disponible", $this->json($r2)["msg"]);
        $this->assertArrayNotHasKey("cart", $r2["sesion"]);
    }

    public function testDeshacerRecortaAlStockActual(): void {
        $a = $this->parte(10);
        $r = $this->accion("quitarCarrito", $a, [], ["cart" => [$a => 6]]);
        $this->db->exec("UPDATE partes SET cantidad = 4 WHERE id = $a");
        $r2 = $this->accion("restaurarCarrito", $a, [], $r["sesion"]);
        $this->assertSame(4, $r2["sesion"]["cart"][$a]);
    }

    public function testDeshacerNoVaParaOtraPiezaNiCaducado(): void {
        $a = $this->parte(10); $b = $this->parte(10);
        $r = $this->accion("quitarCarrito", $a, [], ["cart" => [$a => 2, $b => 1]]);
        $otra = $this->accion("restaurarCarrito", $b, [], $r["sesion"]);
        $this->assertSame(409, $otra["codigo"], "el id no es el de lo quitado");
        $this->assertArrayNotHasKey($a, $otra["sesion"]["cart"]);
        $vieja = $r["sesion"]; $vieja["cart_deshacer"]["hasta"] = time() - 5;
        $caducada = $this->accion("restaurarCarrito", $a, [], $vieja);
        $this->assertSame(409, $caducada["codigo"]);
        $this->assertArrayNotHasKey($a, $caducada["sesion"]["cart"]);
    }

    public function testQuitarUnaPiezaQueNoEstaDa409(): void {
        $a = $this->parte(10);
        $r = $this->accion("quitarCarrito", $a, [], ["cart" => []]);
        $this->assertSame(409, $r["codigo"]);
        $this->assertArrayNotHasKey("cart_deshacer", $r["sesion"]);
    }

    public function testSinTokenNiQuitaNiRestaura(): void {
        $a = $this->parte(10);
        $r = $this->accion("quitarCarrito", $a, [], ["cart" => [$a => 2]], true, false);
        $this->assertSame(403, $r["codigo"]);
        $this->assertSame(2, $r["sesion"]["cart"][$a]);
        $r = $this->accion("restaurarCarrito", $a, [], ["cart_deshacer" => ["id" => $a, "cantidad" => 1, "nombre" => "x", "hasta" => time() + 100]], true, false);
        $this->assertSame(403, $r["codigo"]);
        $this->assertArrayNotHasKey("cart", $r["sesion"]);
    }

    // ── Sin JavaScript ──

    public function testSinJavaScriptCambiarLaCantidadVuelveAlCarritoConAvisoSoloSiAjusto(): void {
        $a = $this->parte(3);
        $r = $this->accion("actualizarCarrito", $a, ["cantidad" => "2"], ["cart" => [$a => 1]], false);
        $this->assertSame(2, $r["sesion"]["cart"][$a]);
        if (Proceso::veCabeceras()) $this->assertStringEndsWith("/?action=carrito", (string)Proceso::ubicacion($r));
        $r = $this->accion("actualizarCarrito", $a, ["cantidad" => "50"], ["cart" => [$a => 1]], false);
        $this->assertSame(3, $r["sesion"]["cart"][$a]);
        $this->assertSame("warning", $r["sesion"]["flash"]["type"]);
    }

    public function testSinJavaScriptQuitarNoDejaAvisoDuplicadoPeroSiElDeshacer(): void {
        $a = $this->parte(10);
        $r = $this->accion("quitarCarrito", $a, [], ["cart" => [$a => 2]], false);
        $this->assertArrayNotHasKey("flash", $r["sesion"]);
        $this->assertSame($a, $r["sesion"]["cart_deshacer"]["id"], "la pantalla pinta el aviso desde la sesión");
    }

    // ── Compra desde el carrito nuevo ──

    public function testElPedidoSeCobraConLaBaseYBloqueaEnOrdenDeId(): void {
        $a = $this->parte(10, 10.00, "Pieza A"); $b = $this->parte(10, 4.00, "Pieza B");
        $mayor = max($a, $b); $menor = min($a, $b);
        $email = "orden." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->db->prepare("INSERT INTO usuarios (nombre, email, password) VALUES ('Cliente orden', ?, '!')")->execute([$email]);
        $uid = (int)$this->db->lastInsertId();
        try {
            $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = 'Cliente'")->execute([$uid]);
            // el carrito viene en orden DESCENDENTE y con precios falsos en la sesión
            $cart = [$mayor => ["parte_id" => $mayor, "nombre" => "x", "precio" => 0.01, "cantidad" => 1, "max" => 9, "thumbnail" => null, "marca" => ""],
                     $menor => ["parte_id" => $menor, "nombre" => "y", "precio" => 0.01, "cantidad" => 2, "max" => 9, "thumbnail" => null, "marca" => ""]];
            $r = Proceso::accion(["clase" => "PedidoController", "accion" => "procesarCarrito", "csrf" => true, "ip" => "203.0.113.81", "correo" => "ok",
                "post" => ["cliente_direccion" => "Calle 1", "cliente_notas" => ""], "sesion" => ["csrf_token" => self::TOKEN, "user" => ["id" => $uid, "nombre" => "Cliente orden", "email" => $email], "user_roles" => ["Cliente"], "cart" => $cart,
                    "cart_deshacer" => ["id" => $menor, "cantidad" => 1, "nombre" => "x", "hasta" => time() + 100]]]);
            $pid = (int)$r["sesion"]["pedido_exitoso"];
            $this->assertGreaterThan(0, $pid);
            $this->assertArrayNotHasKey("cart", $r["sesion"]);
            $this->assertArrayNotHasKey("cart_deshacer", $r["sesion"], "el deshacer no sobrevive a la compra");
            $items = $this->db->query("SELECT parte_id, parte_nombre, precio_unitario, cantidad FROM pedido_items WHERE pedido_id = $pid ORDER BY id")->fetchAll();
            $this->assertSame([$menor, $mayor], array_map(fn($i) => (int)$i["parte_id"], $items), "las líneas se procesan por id ascendente");
            $porId = []; foreach ($items as $i) $porId[(int)$i["parte_id"]] = $i;
            $this->assertSame("Pieza A", $porId[$a]["parte_nombre"], "el nombre sale de la base");
            $esperado = $a === $mayor ? [10.00 * 1 + 4.00 * 2, 10.00] : [10.00 * 2 + 4.00 * 1, 10.00];
            $total = (float)$this->db->query("SELECT subtotal FROM pedidos WHERE id = $pid")->fetchColumn();
            $this->assertEqualsWithDelta($esperado[0], $total, 0.001, "el subtotal usa los precios de la base");
            $this->db->exec("DELETE FROM pedido_items WHERE pedido_id = $pid");
            $this->db->exec("DELETE FROM pedidos WHERE id = $pid");
        } finally {
            $this->db->exec("DELETE FROM usuarios WHERE id = $uid");
        }
    }

    public function testUnCarritoVacioNoCreaPedidoYVuelveAlCarrito(): void {
        $r = $this->accion("procesarCarrito", 0, ["cliente_direccion" => "Calle 1"], ["cart" => []], false);
        $this->assertSame("warning", $r["sesion"]["flash"]["type"]);
        $this->assertArrayNotHasKey("pedido_exitoso", $r["sesion"]);
    }

    public function testElPaso2ConElCarritoVacioVuelveAlPaso1ConAviso(): void {
        $r = Proceso::accion(["clase" => "PedidoController", "accion" => "datosCarrito", "metodo_http" => "GET", "sesion" => []]);
        $this->assertSame("warning", $r["sesion"]["flash"]["type"]);
        $this->assertStringContainsString("vacío", $r["sesion"]["flash"]["msg"]);
        if (Proceso::veCabeceras()) $this->assertStringEndsWith("/?action=carrito", (string)Proceso::ubicacion($r));
        $this->assertSame("", trim($r["salida"]), "no pinta el formulario");
    }

    public function testElPaso2SoloConPiezasQueYaNoEstanVuelveAlPaso1ConElMotivo(): void {
        $a = $this->parte(5, 10.0, "Pieza que se fue");
        $this->db->exec("UPDATE partes SET activo = 0 WHERE id = $a");
        $r = Proceso::accion(["clase" => "PedidoController", "accion" => "datosCarrito", "metodo_http" => "GET", "sesion" => ["cart" => [$a => 2]]]);
        $this->assertStringContainsString("«Pieza que se fue» ya no está disponible", $r["sesion"]["flash"]["msg"]);
        $this->assertArrayNotHasKey("cart", $r["sesion"]);
    }

    public function testElPaso2PintaElFormularioSiHayPiezasYAvisaLoQueCambio(): void {
        $a = $this->parte(2, 10.0, "Pieza con poco stock");
        $r = Proceso::accion(["clase" => "PedidoController", "accion" => "datosCarrito", "metodo_http" => "GET", "sesion" => ["cart" => [$a => 5]]]);
        $this->assertStringContainsString('id="form-datos"', $r["salida"]);
        $this->assertStringContainsString("Solo quedan 2 de «Pieza con poco stock»", $r["salida"]);
        $this->assertSame(2, $r["sesion"]["cart"][$a], "el carrito queda ajustado al stock real");
    }

    public function testElDetalleDelCarritoHaceUnaSolaConsultaDePiezas(): void {
        $ids = [];
        for ($i = 0; $i < 10; $i++) $ids[] = $this->parte(5);
        $db = \Database::get();
        $antes = (int)$db->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch(PDO::FETCH_NUM)[1];
        $_SESSION = ["cart" => array_fill_keys($ids, 1)];
        $d = (new \PedidoController())->detallarCarrito();
        $despues = (int)$db->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch(PDO::FETCH_NUM)[1];
        $_SESSION = [];
        $this->assertCount(10, $d["lineas"]);
        $this->assertSame(1, $despues - $antes, "10 líneas, una sola consulta de piezas");
    }
}
