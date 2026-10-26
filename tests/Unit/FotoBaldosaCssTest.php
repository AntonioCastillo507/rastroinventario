<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * P1: la proporción de la foto de pieza es una sola en todas las pantallas. Antes la caja de la tarjeta tenía a la vez
 * height:200px (style.css) y aspect-ratio:4/3 (extra.css) y mandaba la altura: la proporción cambiaba con el ancho
 * (0,87 en 390 px, 1,33 en 1280 px, 1,60 en 1024 px). Ahora solo la baldosa 1:1 decide, y es blanca fija en los dos modos.
 */
final class FotoBaldosaCssTest extends TestCase {
    private static function css(string $archivo): string {
        return (string)file_get_contents(BASE_PATH . "/assets/css/$archivo.css");
    }

    /** Declaraciones de la regla cuyo selector es exactamente $selector (sin comentarios) */
    private function declaraciones(string $css, string $selector): string {
        $css = preg_replace('~/\*.*?\*/~s', "", $css);
        $this->assertSame(1, preg_match('~(?:^|\})\s*' . preg_quote($selector, "~") . '\s*\{([^}]*)\}~', $css, $m), "no hay regla $selector");
        return $m[1];
    }

    public function testLaBaldosaEsCuadradaBlancaYMuestraLaFotoCompleta(): void {
        $base = self::css("base");
        $d = $this->declaraciones($base, ".foto-baldosa");
        $this->assertMatchesRegularExpression('~aspect-ratio:\s*1\s*/\s*1~', $d);
        $this->assertMatchesRegularExpression('~background:\s*var\(--color-canvas-light\)~', $d);
        $this->assertDoesNotMatchRegularExpression('~(?<![-a-z])height:\s*\d~', $d, "la baldosa no fija su alto");
        $img = $this->declaraciones($base, ".foto-baldosa img");
        $this->assertMatchesRegularExpression('~object-fit:\s*contain~', $img);
        $this->assertDoesNotMatchRegularExpression('~object-fit:\s*cover~', $img);
    }

    public function testElBlancoDeLaBaldosaNoCambiaConElModo(): void {
        $tema = self::css("tema");
        $this->assertMatchesRegularExpression('~--color-canvas-light:\s*#ffffff~i', $tema);
        // ninguna regla de modo claro u oscuro redefine el fondo de la baldosa
        foreach (["base", "extra", "style", "tema"] as $archivo) {
            $this->assertDoesNotMatchRegularExpression('~data-theme[^{]*\.foto-baldosa~', self::css($archivo), $archivo);
            $this->assertDoesNotMatchRegularExpression('~\.foto-baldosa[^{]*data-theme~', self::css($archivo), $archivo);
        }
    }

    public function testNingunaReglaFijaElAltoDeLaCajaDeLaFotoNiLaRecorta(): void {
        foreach (["style", "extra", "base"] as $archivo) {
            $css = preg_replace('~/\*.*?\*/~s', "", self::css($archivo));
            foreach ([".pub-card-img-wrap", ".pub-card-img", ".pub-card-no-img", ".foto-baldosa"] as $sel) {
                if (preg_match_all('~(?:^|\})\s*' . preg_quote($sel, "~") . '\s*\{([^}]*)\}~', $css, $m)) {
                    foreach ($m[1] as $d) {
                        $this->assertDoesNotMatchRegularExpression('~(?<![-a-z])height:\s*\d+px~', $d, "$archivo $sel fija un alto en píxeles");
                        $this->assertDoesNotMatchRegularExpression('~object-fit:\s*cover~', $d, "$archivo $sel recorta la foto");
                    }
                }
            }
        }
    }

    public function testLasReglasViejasDeFotoYaNoExisten(): void {
        $todo = self::css("style") . self::css("extra") . self::css("base");
        foreach (["thumb-sm", "thumb-preview", "thumb-placeholder", "pub-card-no-img"] as $clase) {
            $this->assertStringNotContainsString(".$clase", $todo, $clase);
        }
    }

    public function testLosTamanosDeBaldosaSonDeLaRejillaDe8(): void {
        $base = self::css("base");
        foreach ([48, 64, 80, 96, 160] as $px) $this->assertMatchesRegularExpression('~\.foto-' . $px . ' \{ width: ' . $px . 'px; \}~', $base);
    }
}
