<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * assets/js/parte_imagen.js: los avisos de la vista previa usan los mismos umbrales que el servidor (ImageHelper::avisos).
 * Se ejecuta con Node (tests/Support/parte_imagen_js_prueba.js); la vista previa en el navegador la cubre el navegador integrado.
 */
final class ParteImagenJsTest extends TestCase {
    private static array $r = [];

    public static function setUpBeforeClass(): void {
        $p = proc_open(["node", BASE_PATH . "/tests/Support/parte_imagen_js_prueba.js"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) self::markTestSkipped("No hay Node para probar parte_imagen.js.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        if ($codigo !== 0) self::fail("Node falló ($codigo): $err");
        self::$r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testLosAvisosDelNavegadorCoincidenConLosDelServidor(): void {
        $tamanos = ["normal" => [1600, 1200], "cuadrada" => [1000, 1000], "alargada" => [2000, 400], "alta" => [500, 1600], "baja" => [700, 600], "justo800" => [800, 800], "ambos" => [600, 100], "ancha21" => [2000, 1000], "alta58" => [1000, 1600], "alta57" => [1000, 1400], "baja799" => [799, 799]];
        foreach ($tamanos as $caso => [$w, $h]) {
            $this->assertSame(\ImageHelper::avisos($w, $h), self::$r[$caso], $caso);
        }
    }

    public function testUnAltoCeroNoRompeNiDivideEntreCero(): void {
        $this->assertIsArray(self::$r["ceroAlto"]);
    }

    public function testElScriptNoUsaInnerHtmlConTextoDelArchivo(): void {
        // el texto de los avisos se agrega como nodo de texto; nada del archivo elegido se interpreta como HTML
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/parte_imagen.js");
        $this->assertDoesNotMatchRegularExpression('/innerHTML\s*=\s*+[^"\']/', $js);
        $this->assertStringNotContainsString("eval(", $js);
    }
}
