<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * assets/js/detalle.js: la cantidad con botones − y + (entre 1 y el stock) y el enlace "Comprar ahora" con la cantidad elegida.
 * Se ejecuta con Node sobre un DOM falso (tests/Support/detalle_js_prueba.js).
 */
final class DetalleJsTest extends TestCase {
    private static array $r = [];

    public static function setUpBeforeClass(): void {
        $p = proc_open(["node", BASE_PATH . "/tests/Support/detalle_js_prueba.js"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) self::markTestSkipped("No hay Node para probar detalle.js.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        if ($codigo !== 0) self::fail("Node falló ($codigo): $err");
        self::$r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testAlEmpezarEstaEn1ConMenosDesactivado(): void {
        $this->assertSame("1", self::$r["inicio"]["valor"]);
        $this->assertTrue(self::$r["inicio"]["menosOff"]);
        $this->assertFalse(self::$r["inicio"]["masOff"]);
    }

    public function testElMasSubeYElMenosBajaConTopeEnElStockYPisoEn1(): void {
        $this->assertSame("3", self::$r["sube"]["valor"]);
        $this->assertFalse(self::$r["sube"]["menosOff"]);
        $this->assertSame("5", self::$r["tope"]["valor"]);
        $this->assertTrue(self::$r["tope"]["masOff"], "en el stock máximo el + se desactiva");
        $this->assertSame("1", self::$r["piso"]["valor"]);
        $this->assertTrue(self::$r["piso"]["menosOff"]);
    }

    public function testComprarAhoraLlevaLaCantidadElegida(): void {
        $this->assertStringEndsWith("&cantidad=1", self::$r["inicio"]["href"]);
        $this->assertStringEndsWith("&cantidad=3", self::$r["sube"]["href"]);
        $this->assertStringEndsWith("&cantidad=5", self::$r["tope"]["href"]);
        $this->assertStringEndsWith("&cantidad=1", self::$r["piso"]["href"]);
        $this->assertSame(1, substr_count(self::$r["tope"]["href"], "cantidad="), "no se acumula en cada clic");
    }

    public function testLoEscritoSeCorrige(): void {
        $this->assertSame("5", self::$r["escribeEnorme"]["valor"]);
        $this->assertSame("1", self::$r["escribeLetras"]["valor"]);
        $this->assertSame("1", self::$r["escribeNegativo"]["valor"]);
        $this->assertSame("", self::$r["vacioMientrasEscribe"]["valor"], "mientras escribe se deja el campo vacío; se corrige al salir");
    }

    public function testConUnaSolaUnidadAmbosBotonesQuedanDesactivados(): void {
        $this->assertTrue(self::$r["unaUnidad"]["menosOff"]);
        $this->assertTrue(self::$r["unaUnidad"]["masOff"]);
    }
}
