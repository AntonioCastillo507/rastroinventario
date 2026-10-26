<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Proceso;

/**
 * "Añadir al carrito" (N6): con fetch (cabecera X-Requested-With: fetch) responde JSON y no redirige; sin JavaScript vuelve a la
 * página de origen, solo si la ruta es interna. El token CSRF es de un solo uso: cada respuesta JSON trae el siguiente.
 * PedidoController real en un proceso aparte (termina en exit); usa commits y borra lo suyo por id al final.
 */
final class AgregarCarritoTest extends TestCase {
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

    private function parte(int $stock, bool $activa = true, string $nombre = "Faro de prueba"): int {
        $id = Datos::parte($this->db, 15.00, $stock, $nombre);
        $this->partes[] = $id;
        if (!$activa) $this->db->exec("UPDATE partes SET activo = 0 WHERE id = $id");
        return $id;
    }

    private function agregar(int $id, bool $ajax = true, bool $csrf = true, array $sesion = [], array $post = []): array {
        return Proceso::accion(["clase" => "PedidoController", "accion" => "agregarCarrito", "csrf" => $csrf, "ip" => "203.0.113.78",
            "get" => ["id" => (string)$id], "post" => $post, "sesion" => $sesion,
            "servidor" => $ajax ? ["HTTP_X_REQUESTED_WITH" => "fetch"] : []]);
    }

    private function json(array $r): array {
        $d = json_decode(trim($r["salida"]), true);
        $this->assertIsArray($d, "la respuesta debe ser JSON: " . $r["salida"]);
        return $d;
    }

    // ── Con fetch ──

    public function testConFetchResponde200ConElContadorElMensajeYElSiguienteToken(): void {
        $id = $this->parte(5);
        $r = $this->agregar($id);
        $d = $this->json($r);
        $this->assertSame(200, $r["codigo"]);
        $this->assertTrue($d["ok"]);
        $this->assertSame(1, $d["count"]);
        $this->assertSame(1, $d["cantidad"]);
        $this->assertStringContainsString("Faro de prueba", $d["msg"]);
        $this->assertFalse($d["alerta"]);
        $this->assertStringEndsWith("/?action=carrito", $d["carrito"]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $d["csrf"], "el token nuevo");
        $this->assertSame($d["csrf"], $r["sesion"]["csrf_token"], "es el de la sesión: el siguiente envío con él pasa");
        $this->assertSame(1, $r["sesion"]["cart"][$id]["cantidad"]);
        $this->assertArrayNotHasKey("flash", $r["sesion"], "no deja un aviso pendiente para la próxima página");
        if (Proceso::veCabeceras()) {
            $this->assertNull(Proceso::ubicacion($r), "no redirige");
            $this->assertContains("Content-Type: application/json; charset=utf-8", $r["cabeceras"]);
        }
    }

    public function testUnaPiezaDeUnaSolaVezSumaYElContadorCuentaPiezasDistintas(): void {
        $a = $this->parte(5, true, "Pieza A");
        $b = $this->parte(5, true, "Pieza B");
        $sesion = ["cart" => [$a => ["parte_id" => $a, "nombre" => "Pieza A", "precio" => 15.0, "cantidad" => 1, "max" => 5, "thumbnail" => null, "marca" => " "]]];
        $d = $this->json($this->agregar($a, true, true, $sesion));
        $this->assertSame(2, $d["cantidad"]);
        $this->assertSame(1, $d["count"], "una pieza distinta en el carrito");
        $d = $this->json($this->agregar($b, true, true, $sesion));
        $this->assertSame(2, $d["count"]);
    }

    public function testEnElTopeDelStockAvisaYNoPasaDelMaximo(): void {
        $id = $this->parte(2);
        $sesion = ["cart" => [$id => ["parte_id" => $id, "nombre" => "Faro de prueba", "precio" => 15.0, "cantidad" => 2, "max" => 2, "thumbnail" => null, "marca" => " "]]];
        $r = $this->agregar($id, true, true, $sesion);
        $d = $this->json($r);
        $this->assertTrue($d["ok"]);
        $this->assertTrue($d["alerta"]);
        $this->assertStringContainsString("todas las unidades disponibles", $d["msg"]);
        $this->assertSame(2, $d["cantidad"]);
        $this->assertSame(2, $r["sesion"]["cart"][$id]["cantidad"]);
    }

    public function testSinTokenResponde403ConUnTokenNuevoYNoTocaElCarrito(): void {
        $id = $this->parte(5);
        $r = $this->agregar($id, true, false);
        $d = $this->json($r);
        $this->assertSame(403, $r["codigo"]);
        $this->assertFalse($d["ok"]);
        $this->assertStringContainsString("caducó", $d["msg"]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $d["csrf"]);
        $this->assertArrayNotHasKey("cart", $r["sesion"]);
    }

    public function testUnaPiezaAgotadaOInactivaResponde409YNoSeAnade(): void {
        foreach ([[0, true], [5, false]] as [$stock, $activa]) {
            $id = $this->parte($stock, $activa);
            $r = $this->agregar($id);
            $d = $this->json($r);
            $this->assertSame(409, $r["codigo"], "stock $stock, activa " . (int)$activa);
            $this->assertFalse($d["ok"]);
            $this->assertArrayNotHasKey("cart", $r["sesion"]);
        }
    }

    public function testUnaPiezaQueNoExisteResponde409(): void {
        $r = $this->agregar(2147483000);
        $this->assertSame(409, $r["codigo"]);
        $this->assertFalse($this->json($r)["ok"]);
    }

    public function testPorGetNoSeAnadeNada(): void {
        $id = $this->parte(5);
        $r = Proceso::accion(["clase" => "PedidoController", "accion" => "agregarCarrito", "metodo_http" => "GET", "csrf" => true,
            "get" => ["id" => (string)$id], "servidor" => ["HTTP_X_REQUESTED_WITH" => "fetch"]]);
        $this->assertArrayNotHasKey("cart", $r["sesion"]);
    }

    public function testElNombreDeLaPiezaViajaComoTextoNoComoHtml(): void {
        $id = $this->parte(5, true, 'Faro <b>"X"</b>');
        $d = $this->json($this->agregar($id));
        $this->assertStringContainsString('Faro <b>"X"</b>', $d["msg"], "JSON crudo: el navegador lo pone con textContent, no como HTML");
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/carrito.js");
        $this->assertStringContainsString("span.textContent = texto", $js);
        $this->assertDoesNotMatchRegularExpression('/innerHTML\s*=\s*+(?!textoOriginal)/', preg_replace('/boton\.innerHTML = textoOriginal;/', "", $js));
    }

    // ── Sin JavaScript ──

    public function testSinJavaScriptVuelveALaMismaPaginaYALaMismaTarjeta(): void {
        if (!Proceso::veCabeceras()) $this->markTestSkipped("sin Xdebug no se leen las cabeceras");
        $id = $this->parte(5);
        $r = $this->agregar($id, false, true, [], ["volver" => "/rastroinventario/?ver=catalogo&page=2"]);
        $this->assertSame(APP_URL . "/?ver=catalogo&page=2#parte-$id", Proceso::ubicacion($r));
        $this->assertSame("success", $r["sesion"]["flash"]["type"]);
        $this->assertSame(1, $r["sesion"]["cart"][$id]["cantidad"]);
    }

    public function testSinJavaScriptYSinRutaVuelveAlDetalle(): void {
        if (!Proceso::veCabeceras()) $this->markTestSkipped("sin Xdebug no se leen las cabeceras");
        $id = $this->parte(5);
        $r = $this->agregar($id, false);
        $this->assertSame(APP_URL . "/?action=detalle&id=$id", Proceso::ubicacion($r));
    }

    public static function rutas(): array {
        $base = parse_url(APP_URL, PHP_URL_PATH);
        return [
            "catálogo con filtros" => [$base . "/?ver=catalogo&q=faro&page=2", true],
            "portada"              => [$base . "/", true],
            "sin barra final"      => [$base, true],
            "con ancla vieja"      => [$base . "/?ver=catalogo#otra", true],
            "otro sitio, //"       => ["//evil.example/" . ltrim($base, "/"), false],
            "otro sitio, https"    => ["https://evil.example" . $base . "/", false],
            "esquema javascript"   => ["javascript:alert(1)", false],
            "ruta fuera de la app" => ["/otra-app/?x=1", false],
            "prefijo parecido"     => [$base . "evil/?x=1", false],
            "barra invertida"      => [$base . "/\\evil.example", false],
            "salto de línea"       => [$base . "/?x=1\r\nSet-Cookie: a=b", false],
            "nulo"                 => [$base . "/?x=\0", false],
            "vacía"                => ["", false],
            "relativa"             => ["catalogo", false],
        ];
    }

    #[DataProvider("rutas")]
    public function testRutaDeRegresoSoloAceptaRutasInternas(string $ruta, bool $valida): void {
        $r = \PedidoController::rutaDeRegreso($ruta, 7);
        if ($valida) {
            $this->assertStringStartsWith(APP_URL === "" ? "" : parse_url(APP_URL, PHP_URL_SCHEME) . "://" . parse_url(APP_URL, PHP_URL_HOST), $r);
            $this->assertStringEndsWith("#parte-7", $r);
        } else {
            $this->assertSame(APP_URL . "/?action=detalle&id=7", $r);
        }
        $this->assertStringNotContainsString("evil", $valida ? "" : $r);
    }
}
