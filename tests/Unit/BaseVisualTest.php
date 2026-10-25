<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Comportamiento global (sub-paso 3): assets/css/base.css y el cableado del interruptor de tema.
 * Son comprobaciones del código; la medición real (fuentes, contraste, desbordes) la hace el navegador integrado.
 */
final class BaseVisualTest extends TestCase {
    private static function base(): string {
        return (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
    }

    private static function tema(): string {
        return (string)file_get_contents(BASE_PATH . "/assets/css/tema.css");
    }

    /** Cuerpo del bloque @media cuyo encabezado contiene $cabecera */
    private static function media(string $css, string $cabecera): string {
        $a = strpos($css, $cabecera);
        self::assertNotFalse($a, "base.css sin $cabecera");
        $i = strpos($css, "{", $a);
        $n = 1;
        for ($j = $i + 1; $j < strlen($css) && $n > 0; $j++) {
            if ($css[$j] === "{") $n++;
            elseif ($css[$j] === "}") $n--;
        }
        return substr($css, $i + 1, $j - $i - 2);
    }

    public function testFocoVisibleSolidoDeDosPixelesConSeparacion(): void {
        $css = self::base();
        $this->assertMatchesRegularExpression('/(?:^|\n):focus-visible\s*\{\s*outline:\s*2px solid var\(--focus-ring\);\s*outline-offset:\s*2px;\s*\}/', $css);
        // y no se deja que Bootstrap lo anule con su sombra translúcida en botones, campos y enlaces de menú
        foreach ([".btn:focus-visible", ".form-control:focus", ".form-select:focus", ".page-link:focus", ".nav-link:focus-visible", ".dropdown-item:focus-visible"] as $s) {
            $this->assertStringContainsString($s, $css, $s);
        }
        $this->assertMatchesRegularExpression('/outline:\s*2px solid var\(--focus-ring\) !important;\s*outline-offset:\s*2px !important;\s*box-shadow:\s*none !important;/', $css);
        $this->assertStringNotContainsString("outline: none", preg_replace('/body:focus-visible \{ outline: none; \}/', "", $css), "Ningún elemento puede quitar el contorno de foco");
        $this->assertStringNotContainsString("outline: 0", $css);
    }

    public function testRespetaPrefersReducedMotion(): void {
        $m = self::media(self::base(), "@media (prefers-reduced-motion: reduce)");
        $this->assertStringContainsString("animation-duration: 0.01ms !important", $m);
        $this->assertStringContainsString("transition-duration: 0.01ms !important", $m);
        $this->assertStringContainsString("scroll-behavior: auto !important", $m);
        $this->assertStringContainsString(".pub-card:hover", $m);
        $this->assertStringContainsString("transform: none !important", $m);
    }

    public function testBotonesYCamposTienen44PxEnTactilYEnMovil(): void {
        $m = self::media(self::base(), "@media (pointer: coarse), (max-width: 767.98px)");
        $this->assertMatchesRegularExpression('/\.btn\s*\{[^}]*min-height:\s*44px/', $m);
        $this->assertMatchesRegularExpression('/\.form-control[^{]*\{[^}]*min-height:\s*44px/', $m);
        $this->assertMatchesRegularExpression('/\.form-select[^{]*\{[^}]*min-height:\s*44px/', $m);
        $this->assertMatchesRegularExpression('/\.page-link\s*\{[^}]*min-height:\s*44px/', $m);
        $this->assertMatchesRegularExpression('/\.navbar-toggler[^{]*\{[^}]*min-height:\s*44px/', $m);
    }

    public function testCamposA16PxYTextoBaseSegunDesignMd(): void {
        $css = self::base();
        $tema = self::tema();
        $this->assertMatchesRegularExpression('/--type-input-text-size:\s*16px;/', $tema);
        $this->assertMatchesRegularExpression('/--type-body-lg-size:\s*16px;/', $tema);
        $this->assertMatchesRegularExpression('/--type-body-md-size:\s*14px;/', $tema);
        $this->assertMatchesRegularExpression('/\.form-control-sm[^{]*\{[^}]*font-size:\s*var\(--type-input-text-size\) !important/', $css, "También los campos pequeños van a 16 px");
        $this->assertMatchesRegularExpression('/\nbody \{[^}]*font-family:\s*var\(--font-text\);[^}]*font-size:\s*var\(--type-body-lg-size\)/', $css);
        $this->assertMatchesRegularExpression('/\.admin-body \{[^}]*font-size:\s*var\(--type-body-md-size\)/', $css);
        $this->assertMatchesRegularExpression('/\.btn \{[^}]*font-size:\s*var\(--type-button-size\);[^}]*font-weight:\s*var\(--type-button-weight\)/', $css);
    }

    public function testLasCifrasUsanLaFuenteNumericaConCifrasTabulares(): void {
        $css = self::base();
        $this->assertMatchesRegularExpression('/\.pub-price[^{]*\{\s*font-family:\s*var\(--font-numeric\);\s*font-variant-numeric:\s*tabular-nums;/', $css);
        foreach ([".detail-price", ".stat-val", ".stat-pill-num", ".cart-count", 'input[type="number"]', ".num"] as $s) $this->assertStringContainsString($s, substr($css, 0, (int)strpos($css, "font-variant-numeric")), $s);
    }

    public function testBaseCssSoloUsaTokensNoColoresSueltos(): void {
        $css = (string)preg_replace('~/\*.*?\*/~s', "", self::base());
        $css = (string)preg_replace('/url\("data:[^"]*"\)/', "url()", $css); // las marcas de verificación son SVG con la tinta de DESIGN.md
        $this->assertSame(0, preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css, $m), "Colores en base.css: " . implode(", ", $m[0] ?? []));
        $this->assertSame(0, preg_match_all('/\brgba?\(|\bhsla?\(/', $css), "base.css no debe llevar rgb()/hsl() sueltos");
        foreach (["#fff", "#000", "white", "black"] as $c) $this->assertDoesNotMatchRegularExpression('/:\s*' . preg_quote($c, "/") . '\b/i', $css);
        $svg = self::base();
        $this->assertSame(2, substr_count($svg, "%23181a20"), "Solo las dos marcas SVG llevan la tinta escrita");
        $this->assertStringContainsString("--color-ink: #181a20;", self::tema(), "La tinta de las marcas SVG es la de DESIGN.md");
    }

    public function testBaseCssSeCargaDespuesDeLosEstilosHeredados(): void {
        foreach (["auth/login" => "style.css", "auth/registro" => "style.css", "auth/olvide" => "style.css", "auth/restablecer" => "style.css",
                  "layouts/public_header" => "extra.css", "layouts/admin_header" => "extra.css"] as $v => $anterior) {
            $php = (string)file_get_contents(BASE_PATH . "/app/Views/$v.php");
            $this->assertGreaterThan(strpos($php, $anterior), strpos($php, "base.css"), "$v: base.css debe ir después de $anterior");
            $this->assertSame(1, substr_count($php, "base.css"), $v);
        }
    }

    public function testElScriptDelTemaSeCargaEnElHeadSinDiferirse(): void {
        $parcial = (string)file_get_contents(BASE_PATH . "/app/Views/layouts/recursos_head.php");
        $this->assertMatchesRegularExpression('~<script src="<\?=APP_URL\?>/assets/js/tema\.js"></script>~', $parcial, "Síncrono: sin defer ni async, para fijar el tema antes de pintar");
        $this->assertGreaterThan(strpos($parcial, "tema.css"), strpos($parcial, "tema.js"));
        $this->assertFileExists(BASE_PATH . "/assets/js/tema.js");
    }

    public function testElInterruptorEstaEnElMenuPublicoYEnElPanelConNombreAccesible(): void {
        foreach (["layouts/public_header", "layouts/admin_header"] as $v) {
            $php = (string)file_get_contents(BASE_PATH . "/app/Views/$v.php");
            $this->assertSame(1, substr_count($php, "data-tema-toggle"), $v);
            $this->assertMatchesRegularExpression('/<button type="button"[^>]*data-tema-toggle[^>]*aria-label="Cambiar a modo claro"/', $php, $v);
            $this->assertMatchesRegularExpression('/data-tema-toggle[^>]*><i class="bi bi-sun" aria-hidden="true"><\/i><\/button>/', $php, "$v: el icono es decorativo");
        }
    }

    public function testElModoClaroDesactivaLosFiltrosYSombrasDelModoOscuro(): void {
        $css = self::base();
        $this->assertStringContainsString('[data-theme="light"] .btn-close { filter: none; }', $css);
        $this->assertStringContainsString('[data-theme="light"] .navbar-toggler-icon { filter: none; }', $css);
        $this->assertStringContainsString('[data-theme="light"] { --shadow: none; }', $css);
    }

    public function testElBotonPrincipalEsIgualEnLosDosModos(): void {
        foreach (["dark", "light"] as $modo) {
            $v = \TemaGenerador::leerVariables(self::tema(), $modo);
            $this->assertSame("#fcd535", $v["btn-primary-bg"], $modo);
            $this->assertSame("#181a20", $v["btn-primary-fg"], $modo);
            $this->assertSame("#f0b90b", $v["btn-primary-bg-active"], $modo);
        }
        $this->assertMatchesRegularExpression('/\.btn-gold, \.btn-warning, \.btn-primary \{\s*background: var\(--btn-primary-bg\) !important;\s*border-color: var\(--btn-primary-bg\) !important;\s*color: var\(--btn-primary-fg\) !important;/', self::base());
    }
}
