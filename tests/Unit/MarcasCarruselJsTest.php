<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * assets/js/marcas_carrusel.js: las funciones puras que deciden cuántas veces se repiten las marcas, cuándo se anima
 * y cuándo salta la posición de un bloque (con Node). El movimiento real, la pausa y las flechas los cubre el navegador integrado.
 */
final class MarcasCarruselJsTest extends TestCase {
    private static array $r = [];

    public static function setUpBeforeClass(): void {
        $p = proc_open(["node", BASE_PATH . "/tests/Support/marcas_carrusel_js_prueba.js"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) self::markTestSkipped("No hay Node para probar marcas_carrusel.js.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        if ($codigo !== 0) self::fail("Node falló ($codigo): $err");
        self::$r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testVelocidadYMinimoDeMarcasSonLosAprobados(): void {
        $this->assertSame(30, self::$r["velocidad"], "30 px/s: unos 40 s por vuelta a 1200 px");
        $this->assertSame(4, self::$r["minMarcas"]);
    }

    public function testUnBloqueSiempreLlenaLaVentana(): void {
        // [ventana 1216 con 912 de marcas, 390 con 648, justo, un píxel menos (1216 / 1217 cabe en 1), sin ancho de marcas, ventana 0, marcas muy angostas]
        $this->assertSame([2, 1, 1, 1, 1, 1, 20], self::$r["rep"]);
    }

    public function testSoloSeAnimaConCuatroMarcasOMasYSinMovimientoReducido(): void {
        $a = self::$r["anima"];
        $this->assertFalse($a["tres"]);
        $this->assertTrue($a["cuatro"]);
        $this->assertTrue($a["seis"]);
        $this->assertFalse($a["reducido"], "prefers-reduced-motion: fila con scroll, sin movimiento");
        $this->assertFalse($a["cero"]);
    }

    public function testElSaltoDeUnBloqueSoloOcurreFueraDelRangoYNoCambiaLoQueSeVe(): void {
        $n = self::$r["norm"];
        $this->assertSame(1500, $n["dentro"]);
        $this->assertSame(2000, $n["bordeAlto"], "el borde alto (2 bloques) todavía no salta");
        $this->assertSame(1001, $n["pasaAlto"], "pasado el borde alto baja un bloque");
        $this->assertSame(1600, $n["muyAlto"]);
        $this->assertSame(500, $n["bordeBajo"], "el borde bajo (medio bloque) todavía no salta");
        $this->assertSame(1499, $n["pasaBajo"], "pasado el borde bajo sube un bloque");
        $this->assertSame(1000, $n["cero"]);
        $this->assertSame(123, $n["sinBloque"], "sin bloque no hay salto");
    }

    public function testElScriptNoUsaInnerHtmlNiEval(): void {
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/marcas_carrusel.js");
        $this->assertDoesNotMatchRegularExpression('/innerHTML\s*=/', $js);
        $this->assertStringNotContainsString("eval(", $js);
        $this->assertStringContainsString("prefers-reduced-motion: reduce", $js);
    }

    public function testLasPausasCubrenCursorFocoBotonTactoPestanaYPantalla(): void {
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/marcas_carrusel.js");
        foreach (["mouseenter", "focusin", "visibilitychange", "IntersectionObserver", "pointerdown", "touchstart", "wheel", "aria-pressed"] as $clave) {
            $this->assertStringContainsString($clave, $js, $clave);
        }
        $this->assertSame(1, preg_match('/function pausado\(\) \{ return pausaBoton \|\| encima \|\| conFoco \|\| oculta \|\| fuera \|\| Date\.now\(\) < hastaUsuario; \}/', $js));
    }
}
