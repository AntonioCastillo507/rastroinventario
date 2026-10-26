<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * El precio (E2 de DESIGN.md): token --precio, amarillo en oscuro y bronce en claro, 20 px a 600;
 * el verde queda para éxito y stock disponible. Contraste en los dos modos sobre cada superficie.
 */
final class PrecioTokenTest extends TestCase {
    private static function css(string $archivo): string {
        return (string)file_get_contents(BASE_PATH . "/assets/css/$archivo");
    }

    /** declaraciones de la primera regla cuyo selector es exactamente $selector */
    private static function regla(string $css, string $selector): string {
        preg_match('/(?:^|\})\s*' . preg_quote($selector, "/") . '\s*\{([^}]*)\}/m', $css, $m);
        return $m[1] ?? "";
    }

    private function modo(string $modo): array {
        return \TemaGenerador::leerVariables(self::css("tema.css"), $modo);
    }

    public function testElTokenPrecioEsAmarilloEnOscuroYBronceEnClaro(): void {
        $this->assertSame("#fcd535", strtolower($this->modo("dark")["precio"]));
        $this->assertSame("#7a5c00", strtolower($this->modo("light")["precio"]));
        $this->assertSame($this->modo("dark")["link"], $this->modo("dark")["precio"]);
        $this->assertSame($this->modo("light")["link"], $this->modo("light")["precio"]);
    }

    public function testElPrecioNuncaEsElVerde(): void {
        foreach (["dark", "light"] as $m) {
            $v = $this->modo($m);
            $this->assertNotSame(strtolower($v["success-text"]), strtolower($v["precio"]), "$m: el precio no puede usar el color de éxito");
        }
    }

    public function testContrasteDelPrecioEnLosDosModosSobreCadaSuperficie(): void {
        foreach (["dark" => ["canvas", "surface", "surface-elevated", "surface-strong"], "light" => ["canvas", "surface", "surface-elevated", "surface-strong"]] as $m => $fondos) {
            $v = $this->modo($m);
            foreach ($fondos as $f) {
                $this->assertGreaterThanOrEqual(4.5, \TemaGenerador::contraste($v["precio"], $v[$f]), "$m: precio sobre $f");
            }
        }
        $l = $this->modo("light");
        $this->assertEqualsWithDelta(6.25, \TemaGenerador::contraste($l["precio"], $l["surface"]), 0.01, "bronce sobre blanco: la cifra de DESIGN.md");
        $d = $this->modo("dark");
        $this->assertGreaterThan(10.0, \TemaGenerador::contraste($d["precio"], $d["surface"]));
    }

    public function testLosParesVigiladosIncluyenElPrecio(): void {
        $conPrecio = array_filter(\TemaGenerador::pares(), fn($p) => $p[0] === "precio");
        $this->assertGreaterThanOrEqual(4, count($conPrecio), "precio sobre lienzo, tarjeta, elevada y atenuada");
        $this->assertSame([], \TemaGenerador::validarVariables(["dark" => $this->modo("dark"), "light" => $this->modo("light")]));
    }

    public function testElGeneradorSacaElPrecioParaOtrosColoresPrincipales(): void {
        foreach (["#F97316", "#2563EB"] as $principal) {
            $r = \TemaGenerador::generar($principal);
            $css = $r["css"] ?? "";
            $this->assertMatchesRegularExpression('/--precio:\s*var\(--color-primary-text-on-dark\);/', $css, $principal);
            $this->assertMatchesRegularExpression('/--precio:\s*var\(--color-link-on-light\);/', $css, $principal);
        }
    }

    public function testTarjetaYDetalleMidenVeintePixelesAPesoSeiscientosConElToken(): void {
        $style = self::css("style.css");
        // el precio de la tarjeta (.precio, en base.css) y el del detalle (.detail-price, en style.css)
        foreach ([["base.css", ".precio", '20px'], ["style.css", ".detail-price", '1\.25rem']] as [$archivo, $sel, $tam]) {
            $r = self::regla(self::css($archivo), $sel);
            $this->assertNotSame("", $r, "no encuentro la regla $sel");
            $this->assertMatchesRegularExpression('/font-size:\s*' . $tam . '/', $r, "$sel: 20 px");
            $this->assertMatchesRegularExpression('/font-weight:\s*600\b/', $r, "$sel: peso 600");
            $this->assertMatchesRegularExpression('/color:\s*var\(--precio\)/', $r, "$sel: color del token");
            $this->assertStringNotContainsString("success", $r, "$sel: sin verde");
        }
        // ninguna regla (ni una de móvil) cambia el tamaño del precio del detalle: 20 px en todos los anchos
        foreach (["style.css", "extra.css", "base.css"] as $archivo) {
            $this->assertSame(1 - (int)($archivo !== "style.css"), preg_match_all('/\.detail-price\s*\{[^}]*font-size/', self::css($archivo)), "$archivo: .detail-price solo fija su tamaño una vez, en style.css");
        }
        $this->assertMatchesRegularExpression('/\.text-precio\s*\{\s*color:\s*var\(--precio\)\s*!important/', self::css("base.css"));
        $this->assertMatchesRegularExpression('/\.stat-val\.precio\s*\{\s*color:\s*var\(--precio\)/', $style);
        $this->assertStringContainsString("font-family: var(--font-numeric)", self::css("base.css"), "las cifras van en la fuente numérica");
    }
}
