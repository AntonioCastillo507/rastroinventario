<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Ajustes de la ronda 4a-4: menús a 14 px, información solo como icono y la clase num en todas las cifras de precio.
 * (El menú del panel, desde S5.1, lo cubre PanelEstructuraTest.)
 */
final class MenusYCifrasTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    private static function base(): string { return (string)file_get_contents(BASE_PATH . "/assets/css/base.css"); }

    /** @return array<string,array{0:string}> */
    public static function todas(): array {
        $o = [];
        foreach (Pantallas::nombres() as $n) $o[$n] = [$n];
        return $o;
    }

    public function testLosTextosDelMenuSonDe14Px(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/extra.css");
        $tema = (string)file_get_contents(BASE_PATH . "/assets/css/tema.css");
        $this->assertMatchesRegularExpression('/--type-nav-link-size:\s*14px;/', $tema);
        $this->assertMatchesRegularExpression('/--type-button-size:\s*14px;/', $tema);
        $this->assertMatchesRegularExpression('/\.public-nav \.nav-link\s*\{[^}]*font-size:\s*var\(--type-nav-link-size\)/', $css, "enlaces del menú público");
        $panel = (string)file_get_contents(BASE_PATH . "/assets/css/panel.css");
        $this->assertMatchesRegularExpression('/\.admin-enlace\s*\{[^}]*font-size:\s*var\(--type-nav-link-size\)/', $panel, "enlaces de la barra lateral");
        $this->assertMatchesRegularExpression('/\.admin-icono, \.admin-cuenta\s*\{[^}]*font-size:\s*var\(--type-button-size\)/', $panel, "botones del encabezado");
        $this->assertMatchesRegularExpression('/\.public-nav \.btn-outline-warning,\s*\.public-nav \.btn-outline-secondary\s*\{[^}]*font-size:\s*var\(--type-button-size\)/', $css);
        // ningún tamaño en rem menor a 14 px suelto en las reglas de los menús
        preg_match_all('/\.public-nav[^{]*\{[^}]*\}/', $css, $m);
        foreach ($m[0] as $regla) {
            preg_match_all('/font-size:\s*([0-9.]+)rem/', $regla, $t);
            foreach ($t[1] as $rem) $this->assertGreaterThanOrEqual(14.0, (float)$rem * 16, "Tamaño menor a 14 px en: " . substr($regla, 0, 60));
        }
    }

    public function testLaInformacionSoloVaComoIcono(): void {
        $css = self::base();
        $this->assertStringNotContainsString(".text-info", $css, "base.css ya no remapea .text-info: no se usa");
        $this->assertMatchesRegularExpression('/\.icon-info, \.stat-icon\.blue \{ color: var\(--color-info\); \}/', $css);
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . "/app/Views", \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() === "php") $this->assertDoesNotMatchRegularExpression('/class="[^"]*\btext-info\b/', (string)file_get_contents($f->getPathname()), basename($f->getPathname()));
        }
        // el azul de información no se usa como color de texto
        $info = \TemaGenerador::leerVariables((string)file_get_contents(BASE_PATH . "/assets/css/tema.css"), "light");
        $this->assertSame($info["text"], $info["bs-info"] ?? $info["text"]);
    }

    #[DataProvider("todas")]
    public function testNingunTextoConInfoYTodosLosPreciosLlevanNum(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $this->assertSame(0, Html::contar($x, "//*[contains(concat(' ', normalize-space(@class), ' '), ' text-info ')]"), "$pantalla: text-info");
        $sin = [];
        foreach ($x->query("//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::option)]") as $t) {
            if (!preg_match('/\$\s?\d[\d,]*(\.\d+)?/', $t->nodeValue)) continue;
            $e = $t->parentNode;
            if (!str_contains(" " . $e->getAttribute("class") . " ", " num ")) $sin[] = trim($t->nodeValue) . " en <" . $e->nodeName . " class='" . $e->getAttribute("class") . "'>";
        }
        $this->assertSame([], $sin, "$pantalla: precios sin la clase num: " . implode("; ", $sin));
    }

    public function testElCodigoDeLasVistasNoDejaPreciosSinNum(): void {
        $sin = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . "/app/Views", \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== "php") continue;
            $php = (string)file_get_contents($f->getPathname());
            $todos = preg_match_all('/\$<\?=number_format\(/', $php);
            $conNum = preg_match_all('/class="[^"]*\bnum\b[^"]*"[^<>]*>\$<\?=number_format\(/', $php);
            if ($todos !== $conNum) $sin[] = basename($f->getPathname()) . ": $conNum de $todos";
        }
        $this->assertSame([], $sin, "Precios sin class num en el código: " . implode("; ", $sin));
        $this->assertMatchesRegularExpression('/\.num, \.precio,/', self::base(), "base.css pone .num en la fuente numérica");
    }

    public function testLasCantidadesDelDetalleYDelPanelTambienLlevanNum(): void {
        $x = Html::xpath(Pantallas::render($this->db, "detalle_invitado"));
        $this->assertGreaterThan(0, Html::contar($x, "//input[contains(@class,'cantidad-campo')]"), "la cantidad del detalle");
        $this->assertMatchesRegularExpression('/\.cantidad-campo \{[^}]*font-family: var\(--font-numeric\)/', (string)file_get_contents(BASE_PATH . "/assets/css/base.css"));
        $v = Html::xpath(Pantallas::render($this->db, "admin_ventas"));
        $this->assertGreaterThan(0, Html::contar($v, "//td[contains(@class,'num')]"));
        $p = Html::xpath(Pantallas::render($this->db, "admin_pedido_detalle"));
        $this->assertGreaterThan(0, Html::contar($p, "//td[contains(@class,'num')][not(contains(.,'$'))]"), "la cantidad de cada línea del pedido");
    }
}
