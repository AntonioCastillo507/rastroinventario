<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * S6c.1: la barra lateral ocupa siempre el alto completo del viewport (fija, 100dvh), la lista de enlaces
 * hace scroll interno y «Contraer menú» queda anclado abajo. Misma regla para el cajón móvil.
 */
final class BarraLateralAltoTest extends TestCase {
    private static function css(): string { return (string)file_get_contents(BASE_PATH . "/assets/css/panel.css"); }

    public function testEnEscritorioLaBarraEsFijaYMideElAltoDelViewport(): void {
        $this->assertMatchesRegularExpression('/@media \(min-width: 992px\)\s*\{[^@]*\.admin-sidebar\.offcanvas-lg\s*\{[^}]*position:\s*fixed[^}]*top:\s*0[^}]*height:\s*100dvh/', self::css());
        $this->assertDoesNotMatchRegularExpression('/\.admin-sidebar\.offcanvas-lg\s*\{[^}]*position:\s*sticky/', self::css(), "sticky dependía del alto del documento");
        $this->assertMatchesRegularExpression('/\.admin-main\s*\{[^}]*grid-column:\s*2/', self::css(), "al salir del flujo, el contenido sigue en la segunda columna");
    }

    public function testElCajonMovilTambienUsaElAltoDinamicoDelViewport(): void {
        $this->assertMatchesRegularExpression('/\.admin-sidebar\.offcanvas-lg\s*\{\s*max-height:\s*100vh;\s*max-height:\s*100dvh/', self::css());
    }

    public function testLaListaHaceScrollInternoYElPieQuedaAbajo(): void {
        $css = self::css();
        $this->assertMatchesRegularExpression('/\.admin-sidebar\s*\{[^}]*display:\s*flex[^}]*flex-direction:\s*column/', $css);
        $this->assertMatchesRegularExpression('/\.admin-sidebar-nav\s*\{[^}]*flex:\s*1 1 auto[^}]*overflow-y:\s*auto/', $css);
        $this->assertMatchesRegularExpression('/\.admin-sidebar-pie\s*\{[^}]*flex-shrink:\s*0/', $css);
    }

    public function testElContenidoEsContenedorDeLosElementosAbsolutosOcultos(): void {
        $this->assertMatchesRegularExpression('/\.admin-main\s*\{[^}]*position:\s*relative/', self::css(), "las tablas visually-hidden no deben alargar el documento");
    }
}
