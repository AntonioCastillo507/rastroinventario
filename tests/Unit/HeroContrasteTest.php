<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ContrasteHero;

/**
 * Texto sobre la foto de la portada: scrim plano (sin degradado) y 4,5:1 contra el píxel más claro de la foto,
 * en las dos fotos (escritorio y móvil). El hero es oscuro en los dos modos, así que vale para ambos.
 */
final class HeroContrasteTest extends TestCase {
    private string $base;
    private string $tema;

    protected function setUp(): void {
        parent::setUp();
        $this->base = (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
        $this->tema = (string)file_get_contents(BASE_PATH . "/assets/css/tema.css");
    }

    private function color(string $token): array {
        $this->assertSame(1, preg_match('/--color-' . preg_quote($token, "/") . ':\s*(#[0-9a-fA-F]{6})/', $this->tema, $m), "token $token");
        return ContrasteHero::hex($m[1]);
    }

    private function alfaDelScrim(): float {
        $this->assertSame(1, preg_match('/\.hero-overlay\s*\{[^}]*background:\s*color-mix\(in srgb,\s*var\(--color-canvas-dark\)\s*(\d+)%,\s*transparent\)/', $this->base, $m), "el scrim es un color-mix plano del lienzo oscuro");
        return ((int)$m[1]) / 100;
    }

    /** @return array<string,array{0:string}> */
    public static function fotos(): array {
        return ["escritorio" => ["hero-bg.jpg"], "movil" => ["hero-bg-800.jpg"]];
    }

    public function testElScrimEsPlanoYNoHayDegradadosEnLaPortada(): void {
        foreach (["style.css", "extra.css", "base.css"] as $css) {
            $t = (string)file_get_contents(BASE_PATH . "/assets/css/$css");
            preg_match_all('/\.hero-overlay\s*\{[^}]*\}/', $t, $m);
            foreach ($m[0] as $regla) $this->assertStringNotContainsString("gradient", $regla, "$css: DESIGN.md no admite degradados");
        }
        $this->assertGreaterThanOrEqual(0.5, $this->alfaDelScrim());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("fotos")]
    public function testElTextoDeLaPortadaDa4_5ContraElPuntoMasClaroDeLaFoto(string $foto): void {
        $alfa = $this->alfaDelScrim();
        $lienzo = $this->color("canvas-dark");
        $textos = ["titular" => "on-dark", "cuerpo (subtítulo)" => "body", "acento (etiqueta y resalte)" => "primary"];
        foreach ($textos as $nombre => $token) {
            $peor = ContrasteHero::peorContraste(BASE_PATH . "/assets/img/$foto", $this->color($token), $lienzo, $alfa);
            $this->assertGreaterThanOrEqual(4.5, $peor, sprintf("%s sobre %s con scrim %d%%: %.2f:1", $nombre, $foto, $alfa * 100, $peor));
        }
    }

    public function testElSubtituloDeLaPortadaUsaElTextoDeCuerpoYNoElSecundario(): void {
        $home = (string)file_get_contents(BASE_PATH . "/app/Views/public/home.php");
        $this->assertSame(1, preg_match('/<p class="([^"]*)"[^>]*>\s*<\?=Marca::h\("portada\.subtitulo"\)/', $home, $m));
        $this->assertStringContainsString("hero-subtitulo", $m[1]);
        $this->assertStringNotContainsString("text-secondary", $m[1], "el texto secundario deja poco margen sobre la foto");
        $this->assertMatchesRegularExpression('/\.hero-subtitulo\s*\{\s*color:\s*var\(--text\)/', $this->base);
    }

    public function testLaPortadaNoLlevaLaFotoEnLinea(): void {
        $home = (string)file_get_contents(BASE_PATH . "/app/Views/public/home.php");
        $this->assertStringNotContainsString("background-image", $home, "la foto va en base.css, con su copia chica para móvil");
        $this->assertMatchesRegularExpression('/\.hero-section\s*\{\s*background-image:\s*url\("\.\.\/img\/hero-bg-800\.jpg"\)/', $this->base);
        $this->assertMatchesRegularExpression('/@media \(min-width: 801px\)\s*\{\s*\.hero-section\s*\{\s*background-image:\s*url\("\.\.\/img\/hero-bg\.jpg"\)/', $this->base);
    }

    public function testElCalculoDetectaUnScrimInsuficiente(): void {
        $im = imagecreatetruecolor(8, 8);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        $png = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "blanco_" . uniqid() . ".png";
        imagepng($im, $png);
        $blanco = [255, 255, 255];
        $lienzo = $this->color("canvas-dark");
        $this->assertEqualsWithDelta(1.0, ContrasteHero::peorContraste($png, $blanco, $lienzo, 0.0), 0.001, "texto blanco sobre foto blanca sin scrim");
        $this->assertLessThan(4.5, ContrasteHero::peorContraste($png, $blanco, $lienzo, 0.5));
        $this->assertGreaterThan(4.5, ContrasteHero::peorContraste($png, $blanco, $lienzo, 0.9));
        @unlink($png);
    }
}
