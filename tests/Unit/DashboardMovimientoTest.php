<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** S6c.3: entrada escalonada, agrandado en hover y animación de las gráficas del Dashboard. */
final class DashboardMovimientoTest extends TestCase {
    private static function css(): string { return (string)file_get_contents(BASE_PATH . "/assets/css/dashboard.css"); }
    private static function js(): string { return (string)file_get_contents(BASE_PATH . "/assets/js/dashboard.js"); }

    public function testLaEntradaEsEscalonadaDe40MsYDura250MsSoloSinMovimientoReducido(): void {
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\)\s*\{\s*\.dash-rv\s*\{[^}]*animation:\s*dash-entra 250ms ease-out backwards[^}]*calc\(var\(--i, 0\) \* 40ms\)/', self::css());
        $this->assertMatchesRegularExpression('/@keyframes dash-entra\s*\{\s*from\s*\{\s*opacity:\s*0;\s*transform:\s*translateY\(8px\)/', self::css(), "fade y subida corta");
    }

    public function testElTransformDelHoverNoLoPisaElRellenoDeLaAnimacion(): void {
        $this->assertDoesNotMatchRegularExpression('/\.dash-rv\s*\{[^}]*\b(both|forwards)\b/', self::css());
    }

    public function testElHoverAgrandaPocoSoloConRatonYSinMovimientoReducido(): void {
        $css = self::css();
        $this->assertMatchesRegularExpression('/@media \(hover: hover\) and \(pointer: fine\) and \(prefers-reduced-motion: no-preference\)\s*\{[^@]*\.dash-card:hover[^{]*\{[^}]*transform:\s*scale\(1\.02\)/', $css);
        preg_match_all('/scale\(([\d.]+)\)/', $css, $m);
        foreach ($m[1] as $v) $this->assertLessThanOrEqual(1.02, (float)$v, "el agrandado no pasa de 1,02");
        $this->assertMatchesRegularExpression('/transition:\s*transform 180ms ease-out/', $css, "150 a 200 ms");
        $this->assertDoesNotMatchRegularExpression('/transition:[^;}]*(width|height|padding|box-shadow)/', $css, "solo transform y opacity");
    }

    public function testLasGraficasAnimanAlCargarYResaltanAlPasarElCursor(): void {
        $js = self::js();
        $this->assertStringContainsString('Chart.defaults.animation.duration = 500', $js);
        $this->assertStringContainsString('Chart.defaults.animation.easing = "easeOutCubic"', $js);
        $this->assertStringContainsString("if (sinMovimiento) Chart.defaults.animation = false", $js, "sin animación con movimiento reducido");
        $this->assertStringNotContainsString("Chart.defaults.animation = {", $js, "reemplazar el objeto rompe las animaciones de Chart.js");
        $this->assertStringContainsString("hoverOffset", $js, "la dona resalta el segmento");
        $this->assertStringContainsString("hoverBorderWidth", $js, "las barras resaltan");
    }

    public function testLaDonaTieneHolguraParaElResalteYElTooltipNoTapaElCentro(): void {
        $js = self::js();
        preg_match('/hoverOffset:\s*(\d+)/', $js, $h);
        preg_match('/cutout: "68%", layout: \{ padding: (\d+) \}/', $js, $p);
        $this->assertGreaterThanOrEqual((int)$h[1], (int)$p[1], "el padding de la dona cubre el hoverOffset");
        $this->assertStringContainsString('position: "cursor"', $js);
        $this->assertMatchesRegularExpression('/\.dash-dona\s*\{[^}]*overflow:\s*visible/', self::css());
    }

    public function testLasResenasLlevanSeparadorEntreSiYNoAlBorde(): void {
        $css = self::css();
        $this->assertMatchesRegularExpression('/\.dash-resena-item \+ \.dash-resena-item\s*\{[^}]*border-top:\s*1px solid var\(--hairline\)/', $css);
        $this->assertMatchesRegularExpression('/\.dash-resena-item:first-child > \.dash-resena\s*\{\s*padding-top:\s*0/', $css, "sin línea antes de la primera");
        $this->assertMatchesRegularExpression('/\.dash-resena-item:last-child > \.dash-resena\s*\{\s*padding-bottom:\s*0/', $css, "nada después de la última");
    }
}
