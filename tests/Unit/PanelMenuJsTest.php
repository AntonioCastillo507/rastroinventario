<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * assets/js/panel.js: el menú lateral se contrae y se recuerda. Se ejecuta con Node sobre un DOM falso
 * (tests/Support/panel_js_prueba.js); el navegador integrado cubre el render.
 */
final class PanelMenuJsTest extends TestCase {
    /** @var array<string,mixed> */
    private static array $r = [];

    public static function setUpBeforeClass(): void {
        $p = proc_open(["node", BASE_PATH . "/tests/Support/panel_js_prueba.js"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) self::markTestSkipped("No hay Node para probar panel.js.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        if ($codigo !== 0) self::fail("Node falló ($codigo): $err");
        self::$r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testSinNadaGuardadoElMenuEstaAbierto(): void {
        $e = self::$r["sinGuardado"];
        $this->assertSame("abierto", $e["inicial"]["menu"], "se fija antes de pintar, sin esperar a la carga");
        $this->assertSame("true", $e["inicial"]["expandido"]);
        $this->assertNull($e["inicial"]["titulo"], "abierto: los enlaces no repiten su nombre como ayuda");
    }

    public function testPulsarContraeYVuelveAExpandirYLoRecuerda(): void {
        $e = self::$r["sinGuardado"];
        $this->assertSame(["menu" => "colapsado", "expandido" => "false", "texto" => "Expandir menú", "titulo" => "Partes"], $e["trasPulsar"], "contraído: aria-expanded en false y el nombre sale como ayuda");
        $this->assertSame("abierto", $e["trasOtraVez"]["menu"]);
        $this->assertSame([["rastro-panel-menu", "colapsado"], ["rastro-panel-menu", "abierto"]], $e["escritos"]);
    }

    public function testLoGuardadoComoContraidoArrancaContraido(): void {
        $e = self::$r["colapsado"];
        $this->assertSame("colapsado", $e["inicial"]["menu"]);
        $this->assertSame("false", $e["inicial"]["expandido"]);
        $this->assertSame("Expandir menú", $e["inicial"]["texto"]);
        $this->assertSame("colapsado", $e["trasCarga"]["menu"], "al terminar de cargar no lo cambia");
    }

    public function testUnValorGuardadoRaroSeTomaComoAbierto(): void {
        $this->assertSame("abierto", self::$r["basura"]["inicial"]["menu"]);
    }

    public function testSinAlmacenamientoFuncionaYNoGuarda(): void {
        $e = self::$r["sinAlmacen"];
        $this->assertSame("abierto", $e["inicial"]["menu"]);
        $this->assertSame("colapsado", $e["trasPulsar"]["menu"], "en esta página sí cambia");
        $this->assertSame([], $e["escritos"]);
    }

    public function testUnClicFueraDelBotonNoHaceNada(): void {
        $this->assertSame(self::$r["sinGuardado"]["trasOtraVez"], self::$r["sinGuardado"]["ajeno"]);
    }
}
