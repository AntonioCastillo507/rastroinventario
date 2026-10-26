<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Ajustes de la ronda 4a-4: menús a 14 px, menú "Más" y opción activa del panel decidida por PHP,
 * información solo como icono y la clase num en todas las cifras de precio.
 */
final class MenusYCifrasTest extends BaseDatos {
    private const PRINCIPALES = ["Dashboard", "Partes", "Pedidos", "Ventas", "Usuarios"];
    private const EN_MAS = ["Autos", "Secciones", "Roles", "Comentarios", "Reportes"];

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

    /** @return array<string,array{0:string,1:string,2:bool}> pantalla del panel => [opción activa, está en "Más"] */
    public static function delPanel(): array {
        return [
            "admin_dashboard" => ["admin_dashboard", "Dashboard", false],
            "admin_partes" => ["admin_partes", "Partes", false],
            "admin_partes_editar" => ["admin_partes_editar", "Partes", false],
            "admin_pedidos" => ["admin_pedidos", "Pedidos", false],
            "admin_pedido_detalle" => ["admin_pedido_detalle", "Pedidos", false],
            "admin_ventas_crear" => ["admin_ventas_crear", "Ventas", false],
            "admin_usuarios" => ["admin_usuarios", "Usuarios", false],
            "admin_usuarios_clave" => ["admin_usuarios_clave", "Usuarios", false],
            "admin_autos" => ["admin_autos", "Autos", true],
            "admin_secciones_crear" => ["admin_secciones_crear", "Secciones", true],
            "admin_roles_editar" => ["admin_roles_editar", "Roles", true],
            "admin_comentarios" => ["admin_comentarios", "Comentarios", true],
            "admin_reportes" => ["admin_reportes", "Reportes", true],
        ];
    }

    #[DataProvider("delPanel")]
    public function testElMenuDelPanelMarcaLaOpcionYAgrupaElRestoEnMas(string $pantalla, string $activa, bool $enMas): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $this->assertSame(1, Html::contar($x, "//nav[contains(@class,'navbar-expand-xl')]"), "el panel se pliega por debajo de 1200 px");

        // lista plegable / principal: principales a la vista y el resto con d-xl-none
        $enlaces = [];
        foreach ($x->query("(//*[@id='navA']//ul[contains(@class,'navbar-nav')])[1]/li[not(contains(@class,'dropdown'))]/a") as $a) $enlaces[trim($a->textContent)] = $a;
        $this->assertSame(array_merge(self::PRINCIPALES, self::EN_MAS), array_keys($enlaces), $pantalla);
        foreach (self::EN_MAS as $t) $this->assertStringContainsString("d-xl-none", $enlaces[$t]->parentNode->getAttribute("class"), "$t en la lista plegable solo se ve por debajo de xl");
        foreach (self::PRINCIPALES as $t) $this->assertStringNotContainsString("d-xl-none", $enlaces[$t]->parentNode->getAttribute("class"), $t);

        // desplegable "Más": los mismos cinco, solo desde xl
        $mas = $x->query("//*[@id='navA']//li[contains(@class,'dropdown')]")->item(0);
        $this->assertNotNull($mas, $pantalla);
        $this->assertStringContainsString("d-none d-xl-block", $mas->getAttribute("class"));
        $items = [];
        foreach ($x->query(".//ul[contains(@class,'dropdown-menu')]//a", $mas) as $a) $items[trim($a->textContent)] = $a;
        $this->assertSame(self::EN_MAS, array_keys($items), $pantalla);
        $toggle = $x->query("./a[contains(@class,'dropdown-toggle')]", $mas)->item(0);
        $this->assertSame("Más", trim($toggle->textContent));
        $this->assertSame("false", $toggle->getAttribute("aria-expanded"));

        // la opción activa: en el menú plegable, en "Más" (y su botón) y nunca otra
        $marcadasLista = array_keys(array_filter($enlaces, fn($a) => $a->getAttribute("aria-current") === "page"));
        $this->assertSame([$activa], $marcadasLista, "$pantalla: opción activa del menú");
        foreach ($enlaces as $t => $a) $this->assertSame($t === $activa, str_contains(" " . $a->getAttribute("class") . " ", " active "), "$pantalla: $t");
        $marcadasMas = array_keys(array_filter($items, fn($a) => $a->getAttribute("aria-current") === "page"));
        $this->assertSame($enMas ? [$activa] : [], $marcadasMas, "$pantalla: opción activa en Más");
        $this->assertSame($enMas, str_contains(" " . $toggle->getAttribute("class") . " ", " active "), "$pantalla: el botón Más se marca si la opción activa está dentro");
    }

    public function testLosTextosDelMenuSonDe14Px(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/extra.css");
        $tema = (string)file_get_contents(BASE_PATH . "/assets/css/tema.css");
        $this->assertMatchesRegularExpression('/--type-nav-link-size:\s*14px;/', $tema);
        $this->assertMatchesRegularExpression('/--type-button-size:\s*14px;/', $tema);
        foreach (["admin-nav", "public-nav"] as $nav) {
            $this->assertMatchesRegularExpression('/\.' . $nav . ' \.nav-link\s*\{[^}]*font-size:\s*var\(--type-nav-link-size\)/', $css, "$nav: enlaces del menú");
        }
        $this->assertMatchesRegularExpression('/\.public-nav \.btn-outline-warning,\s*\.public-nav \.btn-outline-secondary\s*\{[^}]*font-size:\s*var\(--type-button-size\)/', $css);
        $this->assertMatchesRegularExpression('/\.admin-nav \.btn-outline-secondary,\s*\.admin-nav \.btn-danger\s*\{[^}]*font-size:\s*var\(--type-button-size\)/', $css);
        // ningún tamaño en rem menor a 14 px suelto en las reglas de los menús
        preg_match_all('/\.(?:admin|public)-nav[^{]*\{[^}]*\}/', $css, $m);
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
        $this->assertGreaterThan(0, Html::contar($x, "//span[contains(@class,'num')][following-sibling::text()[contains(.,'uds.')]]"));
        $v = Html::xpath(Pantallas::render($this->db, "admin_ventas"));
        $this->assertGreaterThan(0, Html::contar($v, "//td[contains(@class,'num')]"));
        $p = Html::xpath(Pantallas::render($this->db, "admin_pedido_detalle"));
        $this->assertGreaterThan(0, Html::contar($p, "//td[contains(@class,'num')][not(contains(.,'$'))]"), "la cantidad de cada línea del pedido");
    }
}
