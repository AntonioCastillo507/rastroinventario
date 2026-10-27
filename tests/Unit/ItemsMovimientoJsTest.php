<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * main.js: aparición escalonada de tarjetas de pieza, filas de listas y tarjetas de formulario.
 * Node sobre un DOM falso (tests/Support/items_js_prueba.js).
 */
final class ItemsMovimientoJsTest extends TestCase {
    /** @var array<string,mixed> */
    private static array $r = [];

    public static function setUpBeforeClass(): void {
        $p = proc_open(["node", BASE_PATH . "/tests/Support/items_js_prueba.js"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) self::markTestSkipped("No hay Node para probar main.js.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        if ($codigo !== 0) self::fail("Node falló ($codigo): $err");
        self::$r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testMarcaLosElementosYNadaSeVeAntesDeEntrar(): void {
        $this->assertTrue(self::$r["marcaTodos"]);
        $this->assertTrue(self::$r["antesDeEntrarNadaVisto"]);
        $this->assertSame(0.05, self::$r["umbral"]);
        $this->assertStringContainsString(".lista-tabla tbody tr:not(.lista-vacia)", self::$r["selector"], "la fila de estado vacío no se anima");
    }

    public function testElRetrasoSubeDe50En50HastaElTope(): void {
        $this->assertSame(["0ms", "50ms", "100ms", "150ms", "200ms", "250ms", "300ms", "300ms", "300ms", "300ms"], self::$r["retrasos"]);
        $this->assertTrue(self::$r["todosVistos"]);
        $this->assertTrue(self::$r["dejaDeVigilar"], "cada uno se anima una sola vez");
    }

    public function testLosQueNoSonVisiblesNoCuentanEnElEscalonYCadaLoteEmpiezaEnCero(): void {
        $this->assertSame(["conInvisible" => [null, "0ms", "50ms"], "tresVisto" => false, "dejados" => 2], ["conInvisible" => self::$r["conInvisible"]["retrasos"], "tresVisto" => self::$r["conInvisible"]["tresVisto"], "dejados" => self::$r["conInvisible"]["dejados"]]);
        $this->assertSame("0ms", self::$r["otroLote"]);
    }

    public function testConMovimientoReducidoOSinObservadorNoToca(): void {
        $this->assertTrue(self::$r["reducidoNoToca"]);
        $this->assertTrue(self::$r["sinObservadorNoToca"]);
        $this->assertTrue(self::$r["sinItemsNoCrea"]);
    }
}
