<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * assets/js/marca_logo.js: el aviso del navegador usa los mismos tipos y límites que LogoMarca (los pasa el servidor en data-*).
 * Se ejecuta con Node; la vista previa en el navegador la cubre el navegador integrado.
 */
final class MarcaLogoJsTest extends TestCase {
    private static array $r = [];

    public static function setUpBeforeClass(): void {
        $p = proc_open(["node", BASE_PATH . "/tests/Support/marca_logo_js_prueba.js"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) self::markTestSkipped("No hay Node para probar marca_logo.js.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        if ($codigo !== 0) self::fail("Node falló ($codigo): $err");
        self::$r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testAceptaLosTiposYTamanosPermitidos(): void {
        foreach (["png", "webp", "svg", "pngJusto", "svgJusto"] as $caso) $this->assertSame("", self::$r[$caso], $caso);
    }

    public function testAvisaDeLoQueElServidorRechazaria(): void {
        $this->assertStringContainsString("512 KB", self::$r["pngPesado"]);
        $this->assertStringContainsString("100 KB", self::$r["svgPesado"]);
        foreach (["jpeg", "gif", "vacio"] as $caso) $this->assertSame("El logo debe ser PNG, WebP o SVG.", self::$r[$caso], $caso);
    }

    public function testLosLimitesDelFormularioSonLosDelServidor(): void {
        $this->assertSame(524288, \LogoMarca::MAX_BYTES_RASTER);
        $this->assertSame(102400, \Logo::MAX_BYTES_MARCA);
    }

    public function testElScriptNoUsaInnerHtmlNiEval(): void {
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/marca_logo.js");
        $this->assertDoesNotMatchRegularExpression('/innerHTML\s*=/', $js);
        $this->assertStringNotContainsString("eval(", $js);
    }
}
