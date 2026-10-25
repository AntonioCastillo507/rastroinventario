<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** El menú plegable (público y del panel) se cierra al elegir una opción: main.js con Node sobre un DOM falso. */
final class MenuPlegableTest extends TestCase {
    /** @var array<string,mixed> */
    private static array $r = [];

    public static function setUpBeforeClass(): void {
        $p = proc_open(["node", BASE_PATH . "/tests/Support/menu_js_prueba.js"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) self::markTestSkipped("No hay Node para probar main.js.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        if ($codigo !== 0) self::fail("Node falló ($codigo): $err");
        self::$r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testElMenuEscuchaLosClics(): void {
        $this->assertTrue(self::$r["registraElClic"]);
    }

    public function testElegirUnaOpcionCierraElMenuAbierto(): void {
        $this->assertSame(1, self::$r["opcionDelMenu"], "un enlace del menú lo cierra");
        $this->assertSame(2, self::$r["opcionDeDesplegable"], "un elemento de un desplegable también");
        $this->assertSame(["mismoMenu" => true, "toggle" => false], self::$r["detalle"], "cierra ese menú y no lo alterna");
    }

    public function testUnClicFueraDeLasOpcionesNoHaceNada(): void {
        $this->assertSame(2, self::$r["clicEnOtroLado"]);
        $this->assertSame(2, self::$r["clicSinClosest"]);
    }

    public function testSiElMenuYaEstaCerradoNoHaceNada(): void {
        $this->assertSame(0, self::$r["menuYaCerrado"], "no se alterna un menú cerrado (lo abriría)");
    }
}
