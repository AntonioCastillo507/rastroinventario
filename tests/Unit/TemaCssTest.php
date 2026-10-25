<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Lee assets/css/tema.css (el archivo real, no el generador) y comprueba que:
 *  - contiene todos los tokens de docs/DESIGN.md con sus valores (colores, radios, espaciado, tipografía);
 *  - cumple el contraste de cada par en oscuro y en claro;
 *  - las variables de Bootstrap están conectadas a esos tokens y no quedan colores de Bootstrap por defecto.
 */
final class TemaCssTest extends TestCase {
    private static function css(): string {
        return (string)file_get_contents(BASE_PATH . "/assets/css/tema.css");
    }

    private static function diseno(): string {
        $d = (string)file_get_contents(BASE_PATH . "/docs/DESIGN.md");
        $this_ = explode("\n---\n", $d, 3);
        return $this_[0] . "\n" . ($this_[1] ?? "");
    }

    /** Texto del front matter de DESIGN.md entre dos claves de primer nivel */
    private static function seccion(string $desde, string $hasta): string {
        $d = self::diseno();
        $a = strpos($d, "\n$desde:\n");
        $b = strpos($d, "\n$hasta:\n", $a);
        self::assertNotFalse($a, "DESIGN.md sin la sección $desde");
        self::assertNotFalse($b, "DESIGN.md sin la sección $hasta");
        return substr($d, $a, $b - $a);
    }

    public function testTemaTieneTodosLosColoresDeDesignMd(): void {
        $css = self::css();
        preg_match_all('/^  ([a-z-]+): "(#[0-9a-fA-F]{6})"/m', self::seccion("colors", "typography"), $m, PREG_SET_ORDER);
        $this->assertGreaterThan(35, count($m), "No pude leer los colores de DESIGN.md");
        $faltan = [];
        foreach ($m as [, $nombre, $valor]) {
            if (!preg_match('/--color-' . preg_quote($nombre, "/") . ':\s*' . preg_quote(strtolower($valor), "/") . ';/', $css)) $faltan[] = "$nombre $valor";
        }
        $this->assertSame([], $faltan, "Colores de DESIGN.md ausentes o distintos en tema.css: " . implode(", ", $faltan));
    }

    public function testTemaTieneRadiosYEspaciadoDeDesignMd(): void {
        $css = self::css();
        foreach ([["rounded", "spacing", "rounded"], ["spacing", "components", "spacing"]] as [$desde, $hasta, $prefijo]) {
            preg_match_all('/^  ([a-z]+): (\d+px)\s*$/m', self::seccion($desde, $hasta), $m, PREG_SET_ORDER);
            $this->assertGreaterThanOrEqual(7, count($m), "No pude leer $desde de DESIGN.md");
            foreach ($m as [, $nombre, $valor]) {
                $this->assertMatchesRegularExpression('/--' . $prefijo . '-' . $nombre . ':\s*' . $valor . ';/', $css, "$prefijo $nombre");
            }
        }
    }

    public function testTemaTieneLaTipografiaDeDesignMd(): void {
        $css = self::css();
        $this->assertSame(18, preg_match_all(
            '/^  ([a-z-]+):\s*\n\s+fontFamily: "([^"]*)"\s*\n\s+fontSize: (\d+px)\s*\n\s+fontWeight: (\d+)\s*\n\s+lineHeight: ([\d.]+)\s*\n\s+letterSpacing: (-?[\d.]+(?:px)?)/m',
            self::seccion("typography", "rounded"), $m, PREG_SET_ORDER
        ), "Debe haber 18 estilos tipográficos en DESIGN.md");
        foreach ($m as [, $n, $familia, $size, $peso, $lh, $ls]) {
            $esCifra = str_starts_with($familia, "'IBM Plex Sans'");
            $this->assertMatchesRegularExpression('/--type-' . $n . '-family:\s*var\(--font-' . ($esCifra ? "numeric" : "text") . '\);/', $css, "$n familia");
            $this->assertMatchesRegularExpression('/--type-' . $n . '-size:\s*' . $size . ';/', $css, "$n tamaño");
            $this->assertMatchesRegularExpression('/--type-' . $n . '-weight:\s*' . $peso . ';/', $css, "$n peso");
            $this->assertMatchesRegularExpression('/--type-' . $n . '-line-height:\s*' . preg_quote($lh, "/") . ';/', $css, "$n interlineado");
            $this->assertMatchesRegularExpression('/--type-' . $n . '-letter-spacing:\s*' . preg_quote($ls, "/") . ';/', $css, "$n espaciado");
        }
        $this->assertMatchesRegularExpression('/--font-text:\s*Manrope,/', $css);
        $this->assertMatchesRegularExpression('/--font-numeric:\s*"IBM Plex Sans",/', $css);
    }

    public function testOscuroEsElModoPorDefectoYClaroSeActivaConDataTheme(): void {
        $css = self::css();
        $this->assertMatchesRegularExpression('/:root,\s*\[data-theme="dark"\],\s*\.hero-section\s*\{/', $css);
        $this->assertMatchesRegularExpression('/\[data-theme="light"\]\s*\{/', $css);
        $this->assertStringNotContainsString("prefers-color-scheme", $css, "El modo no debe depender del sistema: oscuro por defecto");
    }

    public function testContrasteDeTodosLosParesEnAmbosModos(): void {
        $css = self::css();
        $porModo = ["dark" => \TemaGenerador::leerVariables($css, "dark"), "light" => \TemaGenerador::leerVariables($css, "light")];
        $this->assertSame([], \TemaGenerador::validarVariables($porModo));
        $this->assertGreaterThanOrEqual(40, count(\TemaGenerador::pares()));
    }

    public function testCifrasDeContrasteQueDeclaraDesignMd(): void {
        $d = \TemaGenerador::leerVariables(self::css(), "dark");
        $l = \TemaGenerador::leerVariables(self::css(), "light");
        $c = fn(array $v, string $a, string $b) => \TemaGenerador::contraste($v[$a], $v[$b]);
        $this->assertEqualsWithDelta(12.18, $c($d, "btn-primary-fg", "btn-primary-bg"), 0.01);
        $this->assertEqualsWithDelta(5.56, $c($d, "text-muted", "surface"), 0.01);
        $this->assertEqualsWithDelta(6.81, $c($d, "text-muted", "canvas"), 0.01);
        $this->assertEqualsWithDelta(4.51, $c($d, "danger-text", "surface"), 0.01);
        $this->assertEqualsWithDelta(4.50, $c($d, "danger-text-elevated", "surface-elevated"), 0.01);
        $this->assertEqualsWithDelta(3.65, $c($d, "input-border", "surface"), 0.01);
        $this->assertEqualsWithDelta(3.36, $c($l, "input-border", "surface"), 0.01);
        $this->assertEqualsWithDelta(3.03, $c($l, "input-border", "canvas"), 0.01);
        $this->assertEqualsWithDelta(5.79, $c($l, "text-muted", "surface"), 0.01);
        $this->assertEqualsWithDelta(5.23, $c($l, "text-muted", "canvas"), 0.01);
        $this->assertEqualsWithDelta(6.25, $c($l, "link", "surface"), 0.01);
        $this->assertEqualsWithDelta(5.37, $c($l, "success-text", "surface"), 0.01);
        $this->assertEqualsWithDelta(5.93, $c($l, "danger-text", "surface"), 0.01);
    }

    public function testVariablesDeBootstrapConectadasALosTokens(): void {
        $css = self::css();
        $requeridas = [
            "--bs-body-bg", "--bs-body-color", "--bs-primary-rgb", "--bs-success-rgb", "--bs-danger-rgb", "--bs-warning-rgb", "--bs-info-rgb",
            "--bs-link-color", "--bs-link-hover-color", "--bs-border-color", "--bs-secondary-color", "--bs-emphasis-color",
            "--bs-focus-ring-width", "--bs-focus-ring-opacity", "--bs-focus-ring-color",
            "--bs-success-bg-subtle", "--bs-danger-border-subtle", "--bs-warning-text-emphasis", "--bs-form-invalid-color",
        ];
        foreach (["dark", "light"] as $modo) {
            $v = \TemaGenerador::leerVariables($css, $modo);
            foreach ($requeridas as $r) $this->assertArrayHasKey(ltrim($r, "-"), $v, "$modo: falta $r");
            // los triples R, G, B tienen que ser el color real del token del que salen
            foreach (\TemaGenerador::PUENTE_RGB as $bs => $sem) {
                $this->assertSame(implode(", ", \TemaGenerador::rgb($v[$sem])), $v[ltrim($bs, "-")], "$modo: $bs no coincide con --$sem");
            }
            $this->assertSame($v["canvas"], $v["bs-body-bg"]);
            $this->assertSame($v["text"], $v["bs-body-color"]);
            $this->assertSame($v["link"], $v["bs-link-color"]);
            $this->assertSame($v["focus-ring"], $v["bs-focus-ring-color"]);
        }
    }

    public function testNoQuedanColoresPorDefectoDeBootstrapNiLaPaletaVieja(): void {
        $defectoBootstrap = ["#0d6efd", "#198754", "#ffc107", "#dc3545", "#0dcaf0", "#6c757d", "#212529", "#6610f2", "#fd7e14", "#20c997"];
        $paletaVieja = ["#F0B90B", "#D4A309", "#03C076", "#1890FF", "#1E2026", "#2B2F36", "#363C45", "#848E9C", "#474D57"];
        $tema = strtolower(self::css());
        foreach ($defectoBootstrap as $c) $this->assertStringNotContainsString($c, $tema, "tema.css lleva el color de Bootstrap $c");
        $estilo = strtolower((string)file_get_contents(BASE_PATH . "/assets/css/style.css"));
        $raiz = substr($estilo, strpos($estilo, ":root"), 1200);
        foreach (array_merge($defectoBootstrap, $paletaVieja) as $c) $this->assertStringNotContainsString(strtolower($c), $raiz, "style.css :root conserva $c");
    }

    public function testLasPlantillasCarganElTemaYLoDeclaranOscuroPorDefecto(): void {
        $parcial = (string)file_get_contents(BASE_PATH . "/app/Views/layouts/recursos_head.php");
        $this->assertGreaterThan(strpos($parcial, "fuentes.css"), strpos($parcial, "tema.css"));
        foreach (["auth/login", "auth/registro", "auth/olvide", "auth/restablecer", "layouts/public_header", "layouts/admin_header"] as $v) {
            $php = (string)file_get_contents(BASE_PATH . "/app/Views/$v.php");
            $this->assertStringContainsString('<html lang="es" data-theme="dark" data-bs-theme="dark">', $php, $v);
            // tema.css (parcial) va antes que style.css y extra.css para que los alias heredados lo usen
            $this->assertLessThan(strpos($php, "style.css") ?: PHP_INT_MAX, strpos($php, "recursos_head.php"), $v);
        }
    }
}
