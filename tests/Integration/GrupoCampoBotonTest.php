<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Los grupos campo + botón (buscador del encabezado y del hero, ojo de la contraseña, buscador de la 404) son un solo control:
 * misma altura, borde compartido y esquinas redondeadas solo en los extremos exteriores. Aquí se vigila el CSS de base.css y la
 * estructura de las vistas; las alturas reales se miden en el navegador.
 */
final class GrupoCampoBotonTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    private static function css(): string {
        return (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
    }

    private static function regla(string $selector): string {
        preg_match('/(?:^|[}\s])' . preg_quote($selector, "/") . '\s*\{([^}]*)\}/m', self::css(), $m);
        return $m[1] ?? "";
    }

    public function testElGrupoEstiraAlturasYNoPartePorLaMitad(): void {
        $g = self::regla(".input-group, .hero-buscar");
        $this->assertMatchesRegularExpression('/align-items:\s*stretch/', $g, "campo y botón con la misma altura");
        $this->assertMatchesRegularExpression('/flex-wrap:\s*nowrap/', $g);
    }

    public function testLasEsquinasSoloSonRedondasEnLosExtremosExteriores(): void {
        $c = self::regla(".input-group > .form-control, .hero-buscar > .form-control");
        $b = self::regla(".input-group > .btn, .hero-buscar > .btn");
        $this->assertMatchesRegularExpression('/border-radius:\s*var\(--rounded-lg\)\s+0\s+0\s+var\(--rounded-lg\)\s*!important/', $c, "campo: redondo a la izquierda");
        $this->assertMatchesRegularExpression('/border-radius:\s*0\s+var\(--rounded-lg\)\s+var\(--rounded-lg\)\s+0\s*!important/', $b, "botón: redondo a la derecha");
        $this->assertMatchesRegularExpression('/margin-left:\s*-1px/', $b, "el borde se comparte, sin hueco");
    }

    public function testElHeroYaNoSeparaElCampoDelBoton(): void {
        $this->assertStringNotContainsString("gap", self::regla(".hero-buscar"), "sin hueco entre el campo y el botón");
        $this->assertDoesNotMatchRegularExpression('/\.hero-buscar\s*\{[^}]*display:\s*flex[^}]*gap/', self::css());
    }

    public static function pantallas(): array {
        return ["inicio" => ["home"], "login" => ["login"], "registro" => ["registro"], "restablecer" => ["restablecer_valido"], "error 404" => ["error_404"]];
    }

    #[DataProvider("pantallas")]
    public function testCadaGrupoTieneCampoYBotonComoHermanosDirectos(string $n): void {
        $x = Html::xpath($n === "error_404" ? \PaginaError::html(404) : Pantallas::render($this->db, $n));
        $grupos = $x->query("//*[contains(concat(' ',normalize-space(@class),' '),' input-group ') or contains(concat(' ',normalize-space(@class),' '),' hero-buscar ')]");
        $this->assertGreaterThan(0, $grupos->length, $n);
        foreach ($grupos as $g) {
            $this->assertSame(1, $x->query("./input[contains(@class,'form-control')]", $g)->length, "$n: un campo");
            $this->assertSame(1, $x->query("./button[contains(@class,'btn')]", $g)->length, "$n: un botón");
        }
    }
}
