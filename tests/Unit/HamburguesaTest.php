<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Icono de la hamburguesa (menú plegable de móvil): del color de los titulares en los dos modos, sin filtros.
 * Antes llevaba un filtro sobre un trazo oscuro fijo y en modo oscuro casi no se veía.
 */
final class HamburguesaTest extends TestCase {
    private static function css(string $a): string {
        return (string)file_get_contents(BASE_PATH . "/assets/css/$a");
    }

    private function modo(string $m): array {
        return \TemaGenerador::leerVariables(self::css("tema.css"), $m);
    }

    public function testElIconoSeDibujaConElColorDelTextoYSinFiltros(): void {
        $b = self::css("base.css");
        $this->assertMatchesRegularExpression('/\.navbar-toggler\s*\{\s*color:\s*var\(--text-strong\);\s*\}/', $b);
        $this->assertMatchesRegularExpression('/\.navbar-toggler \.navbar-toggler-icon\s*\{[^}]*background:\s*currentColor/', $b);
        $this->assertMatchesRegularExpression('/\.navbar-toggler-icon::before,[^{]*::after\s*\{[^}]*background:\s*currentColor/', $b, "las barras de arriba y de abajo");
        foreach (["style.css", "extra.css", "base.css"] as $a) {
            $this->assertDoesNotMatchRegularExpression('/\.navbar-toggler-icon[^{]*\{[^}]*\bfilter\s*:/', self::css($a), "$a: filtro sobre el icono");
        }
    }

    public function testElIconoDa3A1ContraElFondoDeLaBarraEnLosDosModos(): void {
        foreach (["dark", "light"] as $m) {
            $v = $this->modo($m);
            foreach (["canvas", "surface"] as $fondo) {
                $this->assertGreaterThanOrEqual(3.0, \TemaGenerador::contraste($v["text-strong"], $v[$fondo]), "$m: icono sobre $fondo (componente: 3:1)");
            }
        }
    }

    public function testLosDosEncabezadosUsanElBotonConSuIcono(): void {
        foreach (["public_header.php", "admin_header.php"] as $h) {
            $php = (string)file_get_contents(BASE_PATH . "/app/Views/layouts/$h");
            $this->assertMatchesRegularExpression('/<button class="navbar-toggler[^"]*"[^>]*aria-label="Abrir o cerrar el men[úu]"[^>]*><span class="navbar-toggler-icon" aria-hidden="true"><\/span><\/button>/u', $php, "$h: botón con nombre accesible");
        }
    }
}
