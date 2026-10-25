<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * assets/js/envio.js: el botón de envío se desactiva mientras el formulario se envía (contra el doble clic).
 * Se ejecuta con Node sobre un DOM falso (tests/Support/envio_js_prueba.js); el navegador integrado cubre el render.
 */
final class EnvioJsTest extends TestCase {
    /** @var array<string,mixed> */
    private static array $r = [];

    public static function setUpBeforeClass(): void {
        $p = proc_open(["node", BASE_PATH . "/tests/Support/envio_js_prueba.js"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) self::markTestSkipped("No hay Node para probar envio.js.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        if ($codigo !== 0) self::fail("Node falló ($codigo): $err");
        self::$r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testElBotonSeDesactivaYDiceEnviandoPeroDespuesDelEvento(): void {
        $this->assertSame([false], self::$r["normal"]["antes"], "durante el evento aún no está desactivado (así se envía su valor y su formaction)");
        $d = self::$r["normal"]["despues"];
        $this->assertTrue($d["disabled"]);
        $this->assertSame("true", $d["busy"]);
        $this->assertStringContainsString("Enviando…", $d["html"]);
        $this->assertSame('<i class="bi bi-save"></i> Guardar', $d["guardado"], "guarda el contenido original");
    }

    public function testUnSegundoEnvioNoPisaElOriginal(): void {
        $this->assertSame("Pagar", self::$r["dobleEnvio"]["guardado"]);
    }

    public function testUnFormularioGetNoSeToca(): void {
        $this->assertFalse(self::$r["get"]["disabled"]);
        $this->assertSame("Enviar", self::$r["get"]["html"]);
    }

    public function testLosExentosNoSeDesactivan(): void {
        foreach (["formularioExento", "botonExento"] as $k) {
            $this->assertFalse(self::$r[$k]["disabled"], $k);
            $this->assertSame("Enviar", self::$r[$k]["html"], $k);
        }
    }

    public function testUnEnvioCanceladoNoDesactivaNada(): void {
        $this->assertFalse(self::$r["cancelado"]["disabled"]);
    }

    public function testConSubmitterSoloSeDesactivaEseBoton(): void {
        [$actualizar, $quitar] = self::$r["conSubmitter"];
        $this->assertFalse($actualizar["disabled"]);
        $this->assertTrue($quitar["disabled"]);
    }

    public function testAlVolverConAtrasElBotonSeRestaura(): void {
        $r = self::$r["restaurado"];
        $this->assertFalse($r["disabled"]);
        $this->assertSame("<b>Confirmar</b>", $r["html"]);
        $this->assertNull($r["busy"]);
        $this->assertTrue(self::$r["noRestauradoSiNoEsBfcache"]["disabled"], "sin bfcache no se toca");
    }

    public function testElScriptEstaEnLasPaginasConFormularios(): void {
        foreach (["app/Views/layouts/public_footer.php", "app/Views/layouts/admin_footer.php", "app/Views/auth/login.php", "app/Views/auth/registro.php", "app/Views/auth/olvide.php", "app/Views/auth/restablecer.php"] as $f) {
            $this->assertStringContainsString('js/envio.js', (string)file_get_contents(BASE_PATH . "/$f"), $f);
        }
    }
}
