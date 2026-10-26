<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * P2: bloque de marca (logo + nombre + lema en un solo enlace), encabezado de 64 px con el buscador en su propia
 * fila en móvil, menú de cuenta, pie de 4 columnas sin cuadritos de icono y el logo en acceso y panel.
 * Las medidas se miden en el navegador; aquí se comprueba la estructura del HTML y las reglas de CSS que las fijan.
 */
final class MarcaEncabezadoTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    private static function css(): string {
        return (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
    }

    /** declaraciones de la regla cuyo selector es exactamente $selector, buscándola también dentro de un @media */
    private static function regla(string $selector, ?string $media = null): string {
        $css = self::css();
        if ($media !== null) {
            // la regla dentro de ese @media: puede haber otras reglas antes dentro del mismo bloque
            preg_match('/' . preg_quote($media, "/") . '\s*\{(?:[^{}]*\{[^}]*\}\s*)*?\s*' . preg_quote($selector, "/") . '\s*\{([^}]*)\}/', $css, $m);
            return $m[1] ?? "";
        }
        preg_match('/(?:^|[}\s])' . preg_quote($selector, "/") . '\s*\{([^}]*)\}/m', $css, $m);
        return $m[1] ?? "";
    }

    /** @return array<string,array{0:string}> */
    public static function deLaTienda(): array {
        $o = [];
        foreach (Pantallas::publicas() as $n) $o[$n] = [$n];
        return $o;
    }

    // ── El bloque de marca ──

    #[DataProvider("deLaTienda")]
    public function testElBloqueDeMarcaEsUnSoloEnlaceConLogoSvgNombreYLema(string $n): void {
        $x = Html::xpath(Pantallas::render($this->db, $n));
        $marca = $x->query("//nav//a[contains(@class,'navbar-brand') and contains(@class,'marca')]");
        $this->assertSame(1, $marca->length, "$n: un único bloque de marca en el encabezado");
        $a = $marca->item(0);
        $this->assertSame(APP_URL . "/", $a->getAttribute("href"));
        $q = new \DOMXPath($a->ownerDocument);
        $this->assertSame(1, $q->query(".//svg[contains(@class,'marca-logo')]", $a)->length, "$n: logo SVG en línea dentro del enlace");
        $this->assertSame(0, $q->query(".//img", $a)->length, "$n: con SVG no hace falta el PNG");
        $this->assertSame(\Marca::get("nombre"), trim($q->query(".//span[contains(@class,'marca-nombre')]", $a)->item(0)->textContent));
        $this->assertSame(\Marca::get("eslogan"), trim($q->query(".//span[contains(@class,'marca-lema')]", $a)->item(0)->textContent));
        $this->assertSame("", $a->getAttribute("style"));
        $this->assertSame(0, $q->query(".//*[@style]", $a)->length, "$n: nada de estilos en línea (ni filtros) en el bloque");
    }

    public function testElSvgDelLogoTomaElColorDelTema(): void {
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        $svg = $x->query("//nav//svg[contains(@class,'marca-logo')]")->item(0);
        $this->assertSame("true", $svg->getAttribute("aria-hidden"));
        $this->assertSame("currentColor", $x->query(".//path", $svg)->item(0)->getAttribute("fill"));
        $this->assertStringContainsString("var(--link)", self::regla(".marca-logo"), "amarillo en oscuro y bronce en claro, vía --link");
    }

    public function testSinSvgElBloqueUsaElPngSinFiltros(): void {
        \Marca::usarParaPruebas(["nombre" => "Sin Svg", "eslogan" => "Lema", "logo" => "assets/img/carro1.jpg"]); // no existe carro1.svg
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        $a = $x->query("//nav//a[contains(@class,'marca')]")->item(0);
        $q = new \DOMXPath($a->ownerDocument);
        $this->assertSame(0, $q->query(".//svg", $a)->length);
        $img = $q->query(".//img[contains(@class,'marca-logo')]", $a);
        $this->assertSame(1, $img->length, "respaldo: el PNG");
        $this->assertSame("", $img->item(0)->getAttribute("style"), "sin filtro CSS que lo tiña");
        $this->assertSame("", $img->item(0)->getAttribute("alt"), "decorativo: el nombre ya está en el texto");
    }

    public function testSinLogoElBloqueQuedaSoloConElTexto(): void {
        \Marca::usarParaPruebas(["nombre" => "Solo Texto", "eslogan" => "", "logo" => ""]);
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        $a = $x->query("//nav//a[contains(@class,'marca')]")->item(0);
        $q = new \DOMXPath($a->ownerDocument);
        $this->assertSame(0, $q->query(".//svg|.//img", $a)->length);
        $this->assertSame(0, $q->query(".//span[contains(@class,'marca-lema')]", $a)->length, "sin lema no se imprime uno vacío");
        $this->assertSame("Solo Texto", trim($a->textContent));
    }

    public function testLasMedidasDelBloqueDeMarca(): void {
        $this->assertMatchesRegularExpression('/gap:\s*12px/', self::regla(".marca"), "12 px entre logo y texto");
        $this->assertMatchesRegularExpression('/align-items:\s*center/', self::regla(".marca"));
        $this->assertMatchesRegularExpression('/min-height:\s*44px/', self::regla(".marca"), "zona de clic de 44 px");
        $this->assertMatchesRegularExpression('/height:\s*var\(--marca-alto,\s*40px\)/', self::regla(".marca-logo"), "logo de 40 px");
        $this->assertMatchesRegularExpression('/--marca-alto:\s*36px/', self::regla(".public-nav .marca", "@media (max-width: 767.98px)"), "36 px en móvil");
        $this->assertMatchesRegularExpression('/gap:\s*2px/', self::regla(".marca-texto"), "nombre y lema a 2 px");
        $n = self::regla(".marca-nombre");
        $this->assertMatchesRegularExpression('/font-size:\s*16px/', $n);
        $this->assertMatchesRegularExpression('/font-weight:\s*700/', $n);
        $l = self::regla(".marca-lema");
        $this->assertMatchesRegularExpression('/font-size:\s*12px/', $l);
        $this->assertMatchesRegularExpression('/font-weight:\s*500/', $l);
        $this->assertMatchesRegularExpression('/display:\s*none/', $l, "oculto por defecto (móvil)");
        $this->assertStringNotContainsString("uppercase", $l, "sin mayúsculas forzadas");
        $this->assertMatchesRegularExpression('/display:\s*block/', self::regla(".marca-lema", "@media (min-width: 768px)"), "visible desde 768 px");
        $this->assertMatchesRegularExpression('/--marca-alto:\s*32px/', self::regla(".marca-pie"));
        $this->assertMatchesRegularExpression('/--marca-alto:\s*32px/', self::regla(".marca-panel"));
        $this->assertMatchesRegularExpression('/--marca-alto:\s*48px/', self::regla(".marca-acceso .marca-logo"));
    }

    public function testLasBarrasMiden64Px(): void {
        $this->assertMatchesRegularExpression('/min-height:\s*64px/', self::regla(".public-nav"), "tienda");
        $this->assertMatchesRegularExpression('/min-height:\s*64px/', self::regla(".admin-nav"), "panel");
        $this->assertMatchesRegularExpression('/min-height:\s*64px/', self::regla(".public-nav .nav-fila", "@media (min-width: 992px)"));
        $this->assertMatchesRegularExpression('/min-height:\s*56px/', self::regla(".public-nav .nav-fila"), "fila de 56 px en móvil");
        $this->assertMatchesRegularExpression('/margin-block:\s*6px/', self::regla(".public-nav .nav-fila > .marca, .public-nav .nav-fila > .nav-carrito, .public-nav .nav-fila > .navbar-toggler"), "44 de objetivo + 12 de aire = 56");
        $this->assertMatchesRegularExpression('/padding-bottom:\s*4px/', self::regla(".public-nav .nav-buscador"), "fila del buscador de 48 px: 44 + 4");
    }

    // ── Buscador, carrito y menú de cuenta ──

    public function testElBuscadorEstaFueraDelMenuPlegableYEnSuPropiaFila(): void {
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        $this->assertSame(1, $x->query("//nav//form[@role='search']")->length, "un solo buscador en el encabezado (no hay copia para móvil)");
        $this->assertSame(0, $x->query("//*[@id='navP']//form[@role='search']")->length, "no está dentro del menú plegable");
        $this->assertSame(1, $x->query("//div[contains(@class,'nav-fila')]/form[@role='search']")->length);
        $this->assertSame(1, $x->query("//div[contains(@class,'nav-fila')]/a[contains(@class,'cart-btn')]")->length, "el carrito se ve sin abrir el menú");
        $this->assertMatchesRegularExpression('/order:\s*3/', self::regla(".public-nav .nav-buscador"));
        $this->assertMatchesRegularExpression('/flex:\s*1 0 100%/', self::regla(".public-nav .nav-buscador"), "ocupa toda la fila en móvil");
        $this->assertMatchesRegularExpression('/min-width:\s*44px/', self::regla(".public-nav .navbar-toggler"));
    }

    public function testElBotonDeLaCuentaAbiertoNoQuedaAmarilloSobreAmarillo(): void {
        $r = self::regla(".nav-cuenta .btn-outline-warning");
        foreach (["hover", "active"] as $e) {
            $this->assertStringContainsString("--bs-btn-$e-bg: var(--surface-elevated)", $r, $e);
            $this->assertStringContainsString("--bs-btn-$e-color: var(--link)", $r, $e);
        }
        foreach (["dark", "light"] as $m) {
            $v = \TemaGenerador::leerVariables((string)file_get_contents(BASE_PATH . "/assets/css/tema.css"), $m);
            $this->assertGreaterThanOrEqual(4.5, \TemaGenerador::contraste($v["link"], $v["surface-elevated"]), "$m: enlace sobre superficie elevada");
        }
    }

    public function testEnEscritorioElMenuPlegableSeAplanaParaQueTodoQuedeEnUnaFila(): void {
        $this->assertMatchesRegularExpression('/display:\s*contents\s*!important/', self::regla(".public-nav .navbar-collapse", "@media (min-width: 992px)"));
        $this->assertMatchesRegularExpression('/flex-wrap:\s*nowrap/', self::regla(".public-nav .nav-fila", "@media (min-width: 992px)"));
        $this->assertMatchesRegularExpression('/display:\s*none/', self::regla(".public-nav .navbar-toggler", "@media (min-width: 992px)"));
    }

    public function testElInvitadoVeIniciarSesionYCrearCuenta(): void {
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        $this->assertSame(1, $x->query("//*[@id='navP']//div[contains(@class,'nav-cuenta')]//a[normalize-space()='Iniciar sesión']")->length);
        $crear = $x->query("//*[@id='navP']//div[contains(@class,'nav-cuenta')]//a[normalize-space()='Crear cuenta']");
        $this->assertSame(1, $crear->length);
        $this->assertSame(APP_URL . "/registro.php", $crear->item(0)->getAttribute("href"));
        $this->assertSame(0, $x->query("//*[contains(@class,'dropdown-toggle')]")->length, "sin menú de cuenta si no hay sesión");
    }

    public function testElClienteTieneMenuDeCuentaConSalirPorPost(): void {
        $x = Html::xpath(Pantallas::render($this->db, "detalle_cliente"));
        $boton = $x->query("//*[@id='navP']//button[contains(@class,'dropdown-toggle')]");
        $this->assertSame(1, $boton->length);
        $this->assertSame("false", $boton->item(0)->getAttribute("aria-expanded"));
        $this->assertStringContainsString("Menú de la cuenta de", $boton->item(0)->getAttribute("aria-label"));
        $items = array_map(fn($e) => trim($e->textContent), iterator_to_array($x->query("//*[@id='navP']//ul[contains(@class,'dropdown-menu')]//*[self::a or self::button][contains(@class,'dropdown-item')]")));
        $this->assertSame(["Mi Perfil", "Mis Pedidos", "Cerrar sesión"], $items);
        $this->assertSame(1, $x->query("//*[@id='navP']//ul[contains(@class,'dropdown-menu')]//form[contains(@action,'logout.php')][@method='post']//button[@type='submit']")->length);
        $this->assertSame(0, $x->query("//a[normalize-space()='Admin']")->length);
    }

    public function testElPersonalDelPanelTieneIrAlPanelEnElMenuDeCuentaYNoUnBotonAdmin(): void {
        $_SESSION["user"] = ["id" => 1, "nombre" => "Admin de prueba", "email" => "a@example.invalid"];
        $_SESSION["user_roles"] = ["Administrador"];
        try {
            $_SERVER["REQUEST_METHOD"] = "GET";
            $_GET = [];
            ob_start();
            $navActivo = "inicio";
            include BASE_PATH . "/app/Views/layouts/public_header.php";
            echo "</main></body></html>";
            $x = Html::xpath((string)ob_get_clean());
        } finally {
            unset($_SESSION["user"], $_SESSION["user_roles"]);
        }
        $items = array_map(fn($e) => trim($e->textContent), iterator_to_array($x->query("//ul[contains(@class,'dropdown-menu')]//*[contains(@class,'dropdown-item')]")));
        $this->assertSame(["Ir al panel", "Cerrar sesión"], $items);
        $this->assertSame(APP_URL . "/admin/", $x->query("//ul[contains(@class,'dropdown-menu')]//a[normalize-space()='Ir al panel']/@href")->item(0)->nodeValue);
        $this->assertSame(0, $x->query("//a[normalize-space()='Admin']")->length);
    }

    // ── Pie ──

    #[DataProvider("deLaTienda")]
    public function testElPieTieneCuatroColumnasYSoloLosIconosConFuncion(string $n): void {
        $html = Pantallas::render($this->db, $n);
        $x = Html::xpath($html);
        $this->assertSame(4, $x->query("//footer//div[contains(@class,'row')]/div")->length, "$n: 4 columnas");
        $this->assertSame(0, $x->query("//footer//div[contains(@class,'row')]/div[contains(@class,'d-none')]")->length, "$n: ninguna columna oculta");
        $titulos = array_map(fn($e) => trim($e->textContent), iterator_to_array($x->query("//footer//h2")));
        $this->assertSame(["Comprar", "Empresa", "Contacto y horario"], $titulos, $n);
        $this->assertSame(0, $x->query("//footer//*[contains(@class,'bi-') and not(parent::*[contains(@class,'pie-icono') or contains(@class,'pie-red')])]")->length, "$n: icono del pie fuera de su pastilla o botón");
        $this->assertStringNotContainsString("contact-icon", $html);
        $this->assertStringNotContainsString("contact-item", $html);
    }

    public function testLosDatosDeContactoDelPieSonEnlacesTelYMailto(): void {
        \Marca::usarParaPruebas(["nombre" => "Marca X", "telefono" => "+507 6000-1111", "correo" => "hola@example.invalid", "direccion" => "Calle 1", "horario" => [["dia" => "Lun", "horas" => "8-5", "abierto" => true]], "pie" => "Pie {nombre}"]);
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        $this->assertSame("tel:+50760001111", $x->query("//footer//a[starts-with(@href,'tel:')]/@href")->item(0)->nodeValue);
        $this->assertSame("mailto:hola@example.invalid", $x->query("//footer//a[starts-with(@href,'mailto:')]/@href")->item(0)->nodeValue);
        $this->assertStringContainsString("Lun: 8-5", $x->query("//footer")->item(0)->textContent);
    }

    public function testElPieEnlazaLasPaginasDeLaTienda(): void {
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        $href = fn(string $t) => $x->query("//footer//a[normalize-space()='$t']/@href")->item(0)?->nodeValue;
        $this->assertSame(APP_URL . "/?ver=catalogo", $href("Catálogo"));
        $this->assertSame(APP_URL . "/?action=carrito", $href("Carrito"));
        $this->assertSame(APP_URL . "/login.php", $href("Mi cuenta"), "invitado: lleva a iniciar sesión");
        $this->assertSame(APP_URL . "/?action=nosotros", $href("Quiénes somos"));
        $this->assertSame(APP_URL . "/?action=contacto", $href("Contacto"));
        $c = Html::xpath(Pantallas::render($this->db, "detalle_cliente"));
        $this->assertSame(APP_URL . "/?action=mi_perfil", $c->query("//footer//a[normalize-space()='Mi cuenta']/@href")->item(0)->nodeValue, "con sesión: a su perfil");
    }

    // ── Acceso y panel ──

    /** @return array<string,array{0:string}> */
    public static function deAcceso(): array {
        $o = [];
        foreach (Pantallas::deAcceso() as $n) $o[$n] = [$n];
        return $o;
    }

    #[DataProvider("deAcceso")]
    public function testElAccesoMuestraElLogoEnLugarDelIconoDeHerramientas(string $n): void {
        $html = Pantallas::render($this->db, $n);
        $x = Html::xpath($html);
        $this->assertSame(1, $x->query("//main//div[contains(@class,'marca-acceso')]//svg[contains(@class,'marca-logo')]")->length, "$n: logo de acceso");
        $enlace = $x->query("//main//div[contains(@class,'marca-acceso')]/a[contains(@class,'marca-acceso-enlace')]")->item(0);
        $this->assertNotNull($enlace, "$n: el bloque de marca es un enlace al inicio");
        $this->assertSame(APP_URL . "/", $enlace->getAttribute("href"));
        $this->assertSame(\Marca::get("nombre"), trim($x->query(".//*[contains(@class,'marca-nombre')]", $enlace)->item(0)->textContent), "$n: con el nombre visible");
        $this->assertSame(0, $x->query("//*[contains(@class,'login-icon')]")->length, "$n: ya no hay icono genérico");
        $this->assertSame(0, $x->query("//*[contains(@class,'bi-tools')]")->length);
    }

    public function testElPanelMuestraElBloqueDeMarcaDe32PxSinLema(): void {
        foreach (["admin_dashboard", "admin_partes"] as $n) {
            $x = Html::xpath(Pantallas::render($this->db, $n));
            $a = $x->query("//nav//a[contains(@class,'navbar-brand') and contains(@class,'marca-panel')]");
            $this->assertSame(1, $a->length, $n);
            $q = new \DOMXPath($a->item(0)->ownerDocument);
            $this->assertSame(1, $q->query(".//svg[contains(@class,'marca-logo')]", $a->item(0))->length, $n);
            $this->assertSame(0, $q->query(".//span[contains(@class,'marca-lema')]", $a->item(0))->length, "$n: el panel no lleva lema");
            $this->assertSame(APP_URL . "/admin/", $a->item(0)->getAttribute("href"));
        }
    }
}
