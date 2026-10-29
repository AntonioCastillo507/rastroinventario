<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ContrasteHero;

/**
 * Hero de la portada: sin degradados, subtítulo en el texto de cuerpo, título y subtítulo con contraste en los dos modos
 * sobre el fondo del hero (el del lienzo, o la capa de la foto, que mide HeroFotoTest) y el cálculo que usa esa prueba.
 */
final class HeroContrasteTest extends TestCase {
    private string $base;
    private string $tema;

    protected function setUp(): void {
        parent::setUp();
        $this->base = (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
        $this->tema = (string)file_get_contents(BASE_PATH . "/assets/css/tema.css");
    }

    private function modo(string $modo): array {
        return \TemaGenerador::leerVariables($this->tema, $modo);
    }

    public function testElHeroNoLlevaDegradados(): void {
        foreach (["style.css", "extra.css", "base.css"] as $css) {
            $t = (string)file_get_contents(BASE_PATH . "/assets/css/$css");
            preg_match_all('/\.(?:portada-hero|hero-[a-z-]+)\s*\{[^}]*\}/', $t, $m);
            foreach ($m[0] as $regla) $this->assertStringNotContainsString("gradient", $regla, "$css: DESIGN.md no admite degradados");
        }
    }

    public function testElTituloYElSubtituloUsanLosTokensDeTextoYNoElSecundario(): void {
        $this->assertMatchesRegularExpression('/\.hero-titulo\s*\{[^}]*color:\s*var\(--text-strong\)/', $this->base);
        $this->assertMatchesRegularExpression('/\.hero-sub\s*\{[^}]*color:\s*var\(--text\)/', $this->base, "el texto de cuerpo: sobre la foto el secundario deja poco margen");
        $home = (string)file_get_contents(BASE_PATH . "/app/Views/public/home.php");
        $this->assertSame(1, preg_match('/<p class="([^"]*)"[^>]*>\s*<\?=Marca::h\("portada\.subtitulo"\)/', $home, $m));
        $this->assertSame("hero-sub", $m[1]);
    }

    public function testElTituloYElSubtituloTienenContrasteSobreElLienzoEnLosDosModos(): void {
        foreach (["dark", "light"] as $modo) {
            $v = $this->modo($modo);
            foreach (["text-strong", "text", "link"] as $t) {
                $this->assertGreaterThanOrEqual(4.5, \TemaGenerador::contraste($v[$t], $v["canvas"]), "$modo: $t sobre el lienzo del hero");
            }
        }
    }

    public function testElCalculoDetectaUnaCapaInsuficiente(): void {
        $im = imagecreatetruecolor(8, 8);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        $png = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "blanco_" . uniqid() . ".png";
        imagepng($im, $png);
        $blanco = [255, 255, 255];
        $capa = ContrasteHero::hex("#0b0e11");
        $this->assertEqualsWithDelta(1.0, ContrasteHero::peorContraste($png, $blanco, $capa, 0.0), 0.001, "texto blanco sobre foto blanca sin capa");
        $this->assertLessThan(4.5, ContrasteHero::peorContraste($png, $blanco, $capa, 0.5));
        $this->assertGreaterThan(4.5, ContrasteHero::peorContraste($png, $blanco, $capa, 0.9));
        @unlink($png);
    }
}
