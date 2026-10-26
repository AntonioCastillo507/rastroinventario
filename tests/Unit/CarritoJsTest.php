<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * assets/js/carrito.js: "Añadir al carrito" con fetch sin recargar ni mover al usuario. Se ejecuta con Node sobre un DOM
 * falso y un fetch simulado (tests/Support/carrito_js_prueba.js); el navegador integrado cubre el render y el foco.
 */
final class CarritoJsTest extends TestCase {
    private static array $r = [];

    public static function setUpBeforeClass(): void {
        $p = proc_open(["node", BASE_PATH . "/tests/Support/carrito_js_prueba.js"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) self::markTestSkipped("No hay Node para probar carrito.js.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        if ($codigo !== 0) self::fail("Node falló ($codigo): $err");
        self::$r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testElEnvioSeHaceConFetchSinRecargarYElBotonQuedaComoEstaba(): void {
        $e = self::$r["exito"];
        $this->assertSame(1, $e["previnido"], "se frena el envío normal del formulario");
        $this->assertSame(1, $e["peticiones"]);
        $this->assertSame("POST", $e["metodo"]);
        $this->assertSame("fetch", $e["cabecera"], "el servidor distingue la petición por esta cabecera");
        $this->assertSame(0, $e["normal"], "no recarga la página");
        $this->assertFalse($e["boton"]["disabled"]);
        $this->assertSame("ORIGINAL", $e["boton"]["html"]);
        $this->assertNull($e["boton"]["ocupado"]);
    }

    public function testElContadorDelEncabezadoYSuNombreAccesibleCambian(): void {
        $this->assertSame(["3"], self::$r["exito"]["contador"]);
        $this->assertSame("Carrito, 3 piezas", self::$r["exito"]["etiqueta"]);
    }

    public function testElAvisoLlevaElMensajeYUnEnlaceAlCarritoYSePuedeCerrar(): void {
        $this->assertSame(["Añadida al carrito: «Faro».", "Ver carrito", "×"], self::$r["exito"]["aviso"]);
        $this->assertSame("aviso-carrito", self::$r["exito"]["clase"]);
    }

    public function testElTokenNuevoSePoneEnTodosLosFormularios(): void {
        $this->assertSame(["token-nuevo", "token-nuevo"], self::$r["exito"]["tokens"]);
        $this->assertSame(["token-2", "token-2"], self::$r["error"]["tokens"], "también tras un error: el token anterior ya se gastó");
    }

    public function testUnErrorDelServidorSeMuestraComoErrorSinCambiarElContador(): void {
        $e = self::$r["error"];
        $this->assertSame(["La página caducó.", "×"], $e["aviso"], "sin «Ver carrito»");
        $this->assertSame("aviso-carrito es-error", $e["clase"]);
        $this->assertSame(0, $e["contador"]);
        $this->assertSame(0, $e["normal"]);
    }

    public function testSinRedSeEnviaElFormularioNormalYElBotonSeRestaura(): void {
        $this->assertSame(1, self::$r["sinRed"]["normal"]);
        $this->assertFalse(self::$r["sinRed"]["boton"]["disabled"]);
        $this->assertSame("ORIGINAL", self::$r["sinRed"]["boton"]["html"]);
    }

    public function testLasPeticionesVanDeUnaEnUnaYElSegundoUsaElTokenDelPrimero(): void {
        $c = self::$r["cola"];
        $this->assertSame(2, $c["peticiones"]);
        $this->assertSame(["token-viejo", "A"], $c["tokensAlSalir"], "la segunda sale con el token que trajo la primera");
        $this->assertSame(["B", "B"], $c["final"]);
        $this->assertSame(["2"], $c["contador"]);
    }

    public function testUnFormularioQueNoEsDeAnadirNoSeToca(): void {
        $this->assertSame(0, self::$r["otroFormulario"]["previnido"]);
        $this->assertSame(0, self::$r["otroFormulario"]["peticiones"]);
    }

    public function testElScriptCargaAntesQueEnvioJsParaQueElPrimeroFrenaElEnvio(): void {
        $pie = (string)file_get_contents(BASE_PATH . "/app/Views/layouts/public_footer.php");
        $this->assertLessThan(strpos($pie, "js/envio.js"), strpos($pie, "js/carrito.js"));
        $this->assertStringContainsString('id="avisos-carrito"', $pie);
        $this->assertMatchesRegularExpression('/id="avisos-carrito"[^>]*role="status"[^>]*aria-live="polite"/', $pie);
    }
}
