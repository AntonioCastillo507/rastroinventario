<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Menú público: la opción activa la decide PHP en cada pantalla (sin ScrollSpy ni JavaScript),
 * el menú plegable se cierra al elegir una opción y ninguna fila de la tienda desborda por su gutter.
 */
final class MenuPublicoTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    /** @return array<string,array{0:string,1:?string}> pantalla => opción que debe estar activa (null = ninguna) */
    public static function activas(): array {
        $o = ["home" => ["home", "Inicio"], "nosotros" => ["nosotros", "Quiénes somos"], "contacto" => ["contacto", "Contacto"]];
        foreach (["catalogo", "catalogo_filtros", "catalogo_vacio", "detalle_invitado", "detalle_cliente", "comprar_invitado", "comprar_cliente"] as $n) $o[$n] = [$n, "Catálogo"];
        foreach (["carrito_vacio", "carrito_invitado", "carrito_cliente", "compra_exitosa", "mis_pedidos", "mis_pedidos_vacio", "mi_perfil", "con_aviso_exito", "con_aviso_error"] as $n) $o[$n] = [$n, null];
        return $o;
    }

    #[DataProvider("activas")]
    public function testElMenuMarcaLaOpcionCorrecta(string $pantalla, ?string $esperada): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $enlaces = $x->query("//*[@id='navP']//ul[contains(@class,'navbar-nav')]//a");
        $this->assertSame(["Inicio", "Catálogo", "Quiénes somos", "Contacto"], array_map(fn($a) => trim($a->textContent), iterator_to_array($enlaces)), $pantalla);
        $activas = [];
        foreach ($enlaces as $a) {
            $marcada = str_contains(" " . $a->getAttribute("class") . " ", " active ");
            $this->assertSame($marcada, $a->getAttribute("aria-current") === "page", "$pantalla: la clase active y aria-current van juntas en " . trim($a->textContent));
            if ($marcada) $activas[] = trim($a->textContent);
        }
        $this->assertSame($esperada === null ? [] : [$esperada], $activas, "$pantalla: opción activa del menú");
    }

    #[DataProvider("activas")]
    public function testNoQuedaScrollSpyNiOtroMecanismoDeActivo(string $pantalla): void {
        $html = Pantallas::render($this->db, $pantalla);
        $this->assertStringNotContainsString("data-bs-spy", $html);
        $this->assertStringNotContainsString("data-bs-smooth-scroll", $html);
        $this->assertStringNotContainsString("nav-active", $html);
    }

    public function testLosMecanismosViejosDeActivoYaNoExisten(): void {
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/main.js");
        $this->assertStringNotContainsString("nav-active", $js);
        $this->assertStringNotContainsString("Highlight nav", $js);
        foreach (["extra.css", "base.css", "style.css"] as $css) $this->assertStringNotContainsString("nav-active", (string)file_get_contents(BASE_PATH . "/assets/css/$css"), $css);
        $header = (string)file_get_contents(BASE_PATH . "/app/Views/layouts/public_header.php");
        $this->assertStringNotContainsString("_GET", $header, "El encabezado no adivina la pantalla por la URL: la dice cada vista");
        $this->assertStringNotContainsString("esHome", $header);
    }

    #[DataProvider("activas")]
    public function testQuienesSomosYContactoSonPaginasConSuPropiaUrl(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $this->assertSame(APP_URL . "/?action=nosotros", $x->query("//*[@id='navP']//a[normalize-space()='Quiénes somos']/@href")->item(0)->nodeValue, $pantalla);
        $this->assertSame(APP_URL . "/?action=contacto", $x->query("//*[@id='navP']//a[normalize-space()='Contacto']/@href")->item(0)->nodeValue, $pantalla);
    }

    public function testNingunaFilaDeLaTiendaTieneUnGutterMayorQueElDelContenedor(): void {
        $malas = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . "/app/Views", \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== "php") continue;
            preg_match_all('/class="([^"]*\brow\b[^"]*)"/', (string)file_get_contents($f->getPathname()), $m);
            foreach ($m[1] as $clases) {
                // g-5 o gx-5 sin límite de ancho: 3rem de gutter sobresalen 12 px del contenedor y desbordan en móvil y tableta
                if (preg_match('/(^|\s)g[x]?-5(\s|$)/', $clases)) $malas[] = basename($f->getPathname()) . ": $clases";
            }
        }
        $this->assertSame([], $malas, "Filas con gutter horizontal de 3rem en todos los anchos: " . implode("; ", $malas));
        foreach (["nosotros", "detalle", "compra", "compra_confirmar"] as $v) {
            $this->assertMatchesRegularExpression('/class="row [^"]*\bg-4\b[^"]*\bgx-xl-5\b/', (string)file_get_contents(BASE_PATH . "/app/Views/public/$v.php"), $v);
        }
    }
    public function testElBuscadorTieneSuPropiaFilaYElMenuPlegableSoloLlevaLosCuatroEnlaces(): void {
        foreach (["home", "catalogo", "detalle_cliente", "carrito_invitado"] as $n) {
            $x = Html::xpath(Pantallas::render($this->db, $n));
            $this->assertSame(0, $x->query("//*[@id='navP']//form[@role='search']")->length, "$n: el buscador no está dentro del menú plegable");
            $this->assertSame(1, $x->query("//div[contains(@class,'nav-fila')]/form[@role='search']//input[@type='search']")->length, "$n: buscador en la fila del encabezado");
            $this->assertSame(4, $x->query("//*[@id='navP']//ul[contains(@class,'navbar-nav')]//a")->length, $n);
            $this->assertSame(1, $x->query("//*[@id='navP']//div[contains(@class,'nav-cuenta')]")->length, "$n: la cuenta va dentro del menú plegable");
        }
    }
}
