<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Sanitizer (hallazgo 17): guarda el texto limpio, sin escapar; el escape se hace al imprimir. */
final class SanitizerTest extends TestCase {
    protected function tearDown(): void {
        $_POST = [];
        $_GET  = [];
        parent::tearDown();
    }

    public function testNoEscapaNiQuitaEtiquetas(): void {
        $texto = '<script>alert("x")</script> & \'comillas\' <5 y <LED> a&amp;b';
        $this->assertSame($texto, \Sanitizer::string($texto));
    }

    public function testRecortaLosBordes(): void {
        $this->assertSame("hola mundo", \Sanitizer::string("  hola mundo \t\n"));
    }

    public function testQuitaCaracteresDeControlPeroConservaTabYSaltos(): void {
        $this->assertSame("abc", \Sanitizer::string("a\x00b\x07c\x7f"));
        $this->assertSame("a\tb\r\nc", \Sanitizer::string("a\tb\r\nc"));
    }

    public function testUtf8InvalidoSeSustituyeYNoVaciaElCampo(): void {
        $r = \Sanitizer::string("abc\xffdef");
        $this->assertTrue(mb_check_encoding($r, "UTF-8"));
        $this->assertStringStartsWith("abc", $r);
        $this->assertStringEndsWith("def", $r);
    }

    public function testConservaAcentosYEmojis(): void {
        $this->assertSame("Sección ñandú 🚗", \Sanitizer::string("Sección ñandú 🚗"));
    }

    public function testNumerosYCorreo(): void {
        $this->assertSame(12, \Sanitizer::int("12abc"));
        $this->assertSame(-7, \Sanitizer::int("-7"));
        $this->assertSame(3.5, \Sanitizer::float("3.5abc"));
        $this->assertSame("a@b.com", \Sanitizer::email("  a@b.com "));
        $this->assertStringNotContainsString(" ", \Sanitizer::email("a b@x.com"));
    }

    public function testPostYGetTratanUnArregloComoVacio(): void {
        $_POST["x"] = ["uno", "dos"];
        $_GET["y"]  = ["uno"];
        $this->assertSame("", \Sanitizer::post("x"));
        $this->assertSame(0, \Sanitizer::post("x", "int"));
        $this->assertSame("", \Sanitizer::get("y"));
        $this->assertSame("", \Sanitizer::post("no_existe"));
    }

    public function testPostYGetGuardanElTextoLimpioSinEscapar(): void {
        $_POST["nombre"] = '  <b>Ana</b> & "Co"  ';
        $_GET["q"]       = "a&b<c";
        $this->assertSame('<b>Ana</b> & "Co"', \Sanitizer::post("nombre"));
        $this->assertSame("a&b<c", \Sanitizer::get("q"));
    }

    public function testEditarSinCambiosNoReEscapa(): void {
        $texto = "Pastillas & discos <5mm";
        $this->assertSame($texto, \Sanitizer::string(\Sanitizer::string($texto)), "dos pasadas dan lo mismo");
    }
}
