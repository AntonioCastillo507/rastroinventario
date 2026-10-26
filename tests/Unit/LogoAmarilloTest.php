<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * El icono de la R del logo va en el amarillo de marca (el color principal) en los dos modos.
 * Es una excepción documentada a la regla de contraste: el nombre va escrito al lado.
 */
final class LogoAmarilloTest extends TestCase {
    private static function css(string $archivo): string {
        return (string)file_get_contents(BASE_PATH . "/assets/css/$archivo");
    }

    private function modo(string $modo): array {
        return \TemaGenerador::leerVariables(self::css("tema.css"), $modo);
    }

    public function testElTokenLogoEsElColorPrincipalEnLosDosModos(): void {
        foreach (["dark", "light"] as $m) {
            $v = $this->modo($m);
            $this->assertSame("#fcd535", strtolower($v["logo"]), "$m: el logo es el amarillo principal");
        }
        $this->assertNotSame(strtolower($this->modo("light")["link"]), strtolower($this->modo("light")["logo"]), "en claro ya no es el bronce del enlace");
    }

    public function testElTokenLogoSigueAlColorPrincipalDeOtrasMarcas(): void {
        foreach (["#F97316", "#2563EB"] as $principal) {
            $r = \TemaGenerador::generar($principal);
            $css = $r["css"] ?? "";
            $this->assertSame(2, preg_match_all('/--logo:\s*var\(--color-primary\);/', $css), "$principal: --logo en oscuro y en claro");
        }
    }

    public function testElLogoNoDependeDeLosColoresDeTexto(): void {
        $c = self::css("base.css");
        preg_match('/\.marca-logo \{([^}]*)\}/', $c, $m);
        $this->assertStringContainsString("color: var(--logo)", $m[1] ?? "");
        // nada más pinta el logo con otro color (ni encabezado, ni pie, ni acceso, ni panel)
        foreach (["style.css", "extra.css", "base.css"] as $a) {
            $this->assertDoesNotMatchRegularExpression('/\.marca-(?:pie|panel|acceso)?[^{,]*\.marca-logo[^{]*\{[^}]*color:/', self::css($a), "$a: color propio del logo");
        }
    }

    public function testDesignMdDocumentaLaExcepcionDeContraste(): void {
        $d = (string)file_get_contents(BASE_PATH . "/docs/DESIGN.md");
        $this->assertStringContainsString("excepción a la regla de contraste", $d);
        $this->assertStringContainsString("amarillo de marca", $d);
    }

    public function testElContrasteDelLogoSobreElFondoDeCadaModoSeMideYQuedaRegistrado(): void {
        // el logo no es texto: la exención se apoya en el nombre escrito al lado. Se registran las cifras reales.
        $d = $this->modo("dark");
        $l = $this->modo("light");
        $this->assertGreaterThan(10.0, \TemaGenerador::contraste($d["logo"], $d["canvas"]));
        $this->assertEqualsWithDelta(1.4, \TemaGenerador::contraste($l["logo"], $l["canvas"]), 0.2, "en claro el amarillo sobre el suelo cálido da ~1,4:1; por eso el nombre va al lado");
        $this->assertGreaterThanOrEqual(4.5, \TemaGenerador::contraste($l["text-strong"], $l["canvas"]), "el nombre, en tinta, sí cumple");
    }
}
