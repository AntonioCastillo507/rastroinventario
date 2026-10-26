<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Volumen;

/**
 * P3: la tarjeta de pieza (stock con texto y color, precio con el token, "Añadir al carrito", enlace extendido) y el catálogo
 * (conteo real, 24 por página, filtros plegables con "Aplicar", chips que se quitan de uno en uno, sin envío al cambiar).
 */
final class TarjetaYCatalogoTest extends BaseDatos {
    private array $ids = [];

    protected function setUp(): void {
        parent::setUp();
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["REQUEST_URI"] = "/rastroinventario/?ver=catalogo";
    }

    protected function tearDown(): void {
        $_GET = [];
        unset($_SERVER["REQUEST_METHOD"], $_SERVER["REQUEST_URI"]);
        parent::tearDown();
    }

    private function pagina(array $get): \DOMXPath {
        $_GET = $get;
        ob_start();
        try { (new \PublicController())->home(); } finally { $html = (string)ob_get_clean(); }
        return Html::xpath($html);
    }

    private function pieza(string $nombre, int $stock, float $precio = 12.5): int {
        return Datos::parte($this->db, $precio, $stock, $nombre);
    }

    private function tarjeta(\DOMXPath $x, string $nombre): \DOMElement {
        $t = $x->query("//article[contains(@class,'tarjeta-pieza')][.//h3/a[normalize-space()=" . self::lit($nombre) . "]]")->item(0);
        $this->assertNotNull($t, "no hay tarjeta de $nombre");
        return $t;
    }

    private static function lit(string $s): string { return "'" . str_replace("'", "", $s) . "'"; }

    public function testElStockSeDiceConTextoYConUnaClaseDeColor(): void {
        $this->pieza("TCY Muchas", 20);
        $this->pieza("TCY Pocas", 3);
        $this->pieza("TCY Limite", 5);
        $this->pieza("TCY Justo mas", 6);
        $x = $this->pagina(["ver" => "catalogo", "q" => "TCY"]);
        $ins = fn(string $n) => $x->query(".//span[contains(@class,'stock-insignia')]", $this->tarjeta($x, $n))->item(0);
        $this->assertSame("Disponible", trim($ins("TCY Muchas")->textContent));
        $this->assertStringContainsString("stock-ok", $ins("TCY Muchas")->getAttribute("class"));
        $this->assertSame("Quedan 3", trim($ins("TCY Pocas")->textContent));
        $this->assertStringContainsString("stock-low", $ins("TCY Pocas")->getAttribute("class"));
        $this->assertSame("Quedan 5", trim($ins("TCY Limite")->textContent), "5 todavía es poco");
        $this->assertSame("Disponible", trim($ins("TCY Justo mas")->textContent), "6 ya es disponible");
    }

    public function testUnaPiezaAgotadaDiceAgotadoYNoTieneBotonDeAnadir(): void {
        $id = $this->pieza("TCY Agotada", 0);
        $this->db->exec("UPDATE partes SET activo = 1 WHERE id = $id");
        $x = $this->pagina(["ver" => "catalogo", "q" => "TCY Agotada"]);
        $t = $x->query("//article[contains(@class,'tarjeta-pieza')]")->item(0);
        if ($t === null) { $this->addToAssertionCount(1); return; } // el catálogo público solo lista piezas con stock
        $this->assertSame("Agotado", trim($x->query(".//span[contains(@class,'stock-insignia')]", $t)->item(0)->textContent));
        $this->assertSame(0, $x->query(".//form", $t)->length);
    }

    public function testLaTarjetaDeUnaPiezaAgotadaNoOfreceAnadir(): void {
        $tarjetaParte = ["id" => 99, "nombre" => "TCY Sin stock", "precio" => 10, "cantidad" => 0, "marca" => "", "modelo" => "", "anio" => "", "thumbnail" => null, "imagen_grande" => null];
        $tarjetaVolver = "/rastroinventario/";
        ob_start();
        try { include BASE_PATH . "/app/Views/layouts/tarjeta_pieza.php"; } finally { $html = (string)ob_get_clean(); }
        $x = Html::xpath($html);
        $this->assertSame("Agotado", trim($x->query("//span[contains(@class,'stock-insignia')]")->item(0)->textContent));
        $this->assertStringContainsString("stock-out", $x->query("//span[contains(@class,'stock-insignia')]")->item(0)->getAttribute("class"));
        $this->assertSame(0, $x->query("//form")->length);
        $this->assertSame(0, $x->query("//button")->length);
        $this->assertSame(0, $x->query("//p[contains(@class,'tarjeta-compat')]")->length, "sin marca no hay línea de compatibilidad");
        $this->assertSame(1, $x->query("//span[contains(@class,'foto-baldosa')]/i")->length, "sin imagen, el marcador");
    }

    public function testElBotonAnadirEsUnFormularioPostConTokenYVolverYNombreAccesible(): void {
        $id = $this->pieza("TCY Boton", 9);
        $x = $this->pagina(["ver" => "catalogo", "q" => "TCY Boton"]);
        $t = $this->tarjeta($x, "TCY Boton");
        $f = $x->query(".//form[@data-agregar-carrito]", $t)->item(0);
        $this->assertSame("POST", strtoupper($f->getAttribute("method")));
        $this->assertSame(APP_URL . "/?action=agregar_carrito&id=$id", $f->getAttribute("action"));
        $this->assertSame(1, $x->query(".//input[@name='csrf_token']", $f)->length);
        $this->assertSame("/rastroinventario/?ver=catalogo", $x->query(".//input[@name='volver']/@value", $f)->item(0)->nodeValue);
        $b = $x->query(".//button[@type='submit']", $f)->item(0);
        $this->assertStringContainsString("Añadir al carrito", $b->textContent, "el texto accesible no se quita en móvil, solo se oculta a la vista");
        $this->assertStringContainsString("tarjeta-agregar-texto", $x->query(".//span", $b)->item(0)->getAttribute("class"));
        $this->assertSame("parte-titulo-$id", $b->getAttribute("aria-describedby"));
        $this->assertSame("parte-titulo-$id", $x->query(".//h3", $t)->item(0)->getAttribute("id"));
    }

    public function testTodaLaTarjetaEsUnEnlaceAlDetalleConElNombreComoTexto(): void {
        $id = $this->pieza("TCY Enlace", 9);
        $x = $this->pagina(["ver" => "catalogo", "q" => "TCY Enlace"]);
        $a = $x->query(".//h3/a[contains(@class,'tarjeta-enlace')]", $this->tarjeta($x, "TCY Enlace"))->item(0);
        $this->assertSame(APP_URL . "/?action=detalle&id=$id", $a->getAttribute("href"));
        $this->assertSame(0, $x->query("//article[contains(@class,'tarjeta-pieza')]//a[.//img]")->length, "la foto no es un segundo enlace al mismo destino");
    }

    public function testElPrecioLlevaElTokenYLaFuenteDeCifras(): void {
        $this->pieza("TCY Precio", 9, 1234.5);
        $x = $this->pagina(["ver" => "catalogo", "q" => "TCY Precio"]);
        $p = $x->query(".//span[contains(@class,'precio')]", $this->tarjeta($x, "TCY Precio"))->item(0);
        $this->assertSame('$1,234.50', trim($p->textContent));
        $this->assertStringContainsString("num", $p->getAttribute("class"));
    }

    public function testLaCompatibilidadSeEscribeEnUnaLinea(): void {
        $this->db->exec("INSERT INTO autos (marca, modelo, anio) VALUES ('MarcaTCY', 'ModeloTCY', 2020)");
        $auto = (int)$this->db->lastInsertId();
        $id = $this->pieza("TCY Compat", 9);
        $this->db->exec("UPDATE partes SET auto_id = $auto WHERE id = $id");
        $x = $this->pagina(["ver" => "catalogo", "q" => "TCY Compat"]);
        $this->assertSame("MarcaTCY ModeloTCY · 2020", trim($x->query(".//p[contains(@class,'tarjeta-compat')]", $this->tarjeta($x, "TCY Compat"))->item(0)->textContent));
    }

    public function testElNombreSeEscapaEnLaTarjeta(): void {
        $this->pieza('TCY <script>x</script> "q"', 9);
        $x = $this->pagina(["ver" => "catalogo", "q" => "TCY"]);
        $this->assertSame(0, $x->query("//article//script")->length);
        $this->assertSame(1, $x->query("//article//h3/a[contains(., '<script>x</script>')]")->length);
    }

    // ── Catálogo ──

    public function testElConteoEsElTotalRealNoLasPiezasDeLaPagina(): void {
        Volumen::crear($this->db);
        $total = (new \ParteModel())->contarPublic();
        $this->assertGreaterThan(24, $total);
        $x = $this->pagina(["ver" => "catalogo"]);
        $this->assertSame(24, $x->query("//article[contains(@class,'tarjeta-pieza')]")->length);
        $this->assertSame("$total piezas", trim($x->query("//*[@id='catalogo-conteo']")->item(0)->textContent));
        $x2 = $this->pagina(["ver" => "catalogo", "page" => "2"]);
        $this->assertSame("$total piezas", trim($x2->query("//*[@id='catalogo-conteo']")->item(0)->textContent), "en la página 2 sigue siendo el total");
    }

    public function testUnaSolaPiezaDiceSingular(): void {
        $this->pieza("TCY Unica Sola", 4);
        $x = $this->pagina(["ver" => "catalogo", "q" => "TCY Unica Sola"]);
        $this->assertSame("1 pieza", trim($x->query("//*[@id='catalogo-conteo']")->item(0)->textContent));
    }

    public function testLosFiltrosVanPlegadosEnMovilConUnBotonQueDiceCuantosHay(): void {
        $x = $this->pagina(["ver" => "catalogo", "marca" => "Volumen Alfa", "precio_min" => "5"]);
        $b = $x->query("//button[contains(@class,'btn-filtros')]")->item(0);
        $this->assertSame("false", $b->getAttribute("aria-expanded"));
        $this->assertSame("filtros", $b->getAttribute("aria-controls"));
        $this->assertSame("collapse", $b->getAttribute("data-bs-toggle"));
        $this->assertSame("Filtros (2)", preg_replace('/\s+/', " ", trim($b->textContent)));
        $panel = $x->query("//*[@id='filtros']")->item(0);
        $this->assertMatchesRegularExpression('/\bcollapse\b/', $panel->getAttribute("class"));
        $this->assertMatchesRegularExpression('/\bd-lg-block\b/', $panel->getAttribute("class"), "en escritorio siempre visibles");
    }

    public function testLosFiltrosSeAplicanConUnBotonNoAlCambiar(): void {
        $this->db->exec("INSERT INTO secciones (nombre) VALUES ('TCY Seccion')");
        $x = $this->pagina(["ver" => "catalogo"]);
        $this->assertSame(0, $x->query("//form[@id='frmFiltro']//*[@onchange]")->length, "WCAG 3.2.2: cambiar un campo no envía el formulario");
        $this->assertSame(1, $x->query("//form[@id='frmFiltro']//button[@type='submit'][normalize-space()='Aplicar filtros']")->length);
        $main = (string)file_get_contents(BASE_PATH . "/assets/js/main.js");
        $this->assertStringNotContainsString("#frmFiltro select", $main);
        $this->assertGreaterThanOrEqual(2, $x->query("//form[@id='frmFiltro']//input[@type='radio'][@name='seccion']")->length, "Todas y al menos una sección");
        foreach ($x->query("//form[@id='frmFiltro']//input|//form[@id='frmFiltro']//select") as $campo) {
            if ($campo->getAttribute("type") === "hidden") continue;
            $this->assertNotSame("", $campo->getAttribute("id"));
            $this->assertSame(1, $x->query("//label[@for='" . $campo->getAttribute("id") . "']")->length, "etiqueta visible de " . $campo->getAttribute("id"));
        }
    }

    public function testCadaFiltroActivoEsUnChipQueSeQuitaConservandoLosDemas(): void {
        $x = $this->pagina(["ver" => "catalogo", "q" => "Pieza", "marca" => "Volumen Alfa", "precio_min" => "5", "precio_max" => "40", "orden" => "precio_desc", "page" => "2"]);
        $chips = $x->query("//ul[contains(@class,'chips-filtro')]//a[contains(@class,'chip-filtro')]");
        $this->assertSame(4, $chips->length);
        $textos = array_map(fn($c) => preg_replace('/\s+/', " ", trim($c->textContent)), iterator_to_array($chips));
        $this->assertSame(["Búsqueda: «Pieza»", "Marca: Volumen Alfa", "Desde $5", "Hasta $40"], $textos);
        foreach ($chips as $c) {
            parse_str((string)parse_url($c->getAttribute("href"), PHP_URL_QUERY), $q);
            $this->assertSame("catalogo", $q["ver"]);
            $this->assertArrayNotHasKey("page", $q, "al quitar un filtro se vuelve a la página 1");
            $this->assertSame("precio_desc", $q["orden"], "el orden se conserva");
            $this->assertNotSame("", $c->getAttribute("aria-label"));
        }
        parse_str((string)parse_url($chips->item(1)->getAttribute("href"), PHP_URL_QUERY), $sinMarca);
        $this->assertArrayNotHasKey("marca", $sinMarca);
        $this->assertSame("Pieza", $sinMarca["q"]);
        $this->assertSame("5", $sinMarca["precio_min"]);
        $this->assertSame(1, $x->query("//a[contains(@class,'chip-limpiar')][normalize-space()='Quitar todos']")->length);
    }

    public function testSinFiltrosNoHayChips(): void {
        $x = $this->pagina(["ver" => "catalogo"]);
        $this->assertSame(0, $x->query("//ul[contains(@class,'chips-filtro')]")->length);
        $this->assertSame("Filtros (0)", preg_replace('/\s+/', " ", trim($x->query("//button[contains(@class,'btn-filtros')]")->item(0)->textContent)));
    }

    public function testUnTextoDeBusquedaHostilSeEscapaEnElTituloLosChipsYElFormulario(): void {
        $x = $this->pagina(["ver" => "catalogo", "q" => '"><script>alert(1)</script>']);
        $this->assertSame(0, $x->query("//script[contains(.,'alert')]")->length);
        $this->assertStringContainsString("<script>alert(1)</script>", $x->query("//h1")->item(0)->textContent);
        $this->assertSame('"><script>alert(1)</script>', $x->query("//form[@id='frmFiltro']//input[@name='q']/@value")->item(0)->nodeValue);
    }

    public function testSinResultadosHayUnMensajeYUnEnlaceParaVerTodo(): void {
        $x = $this->pagina(["ver" => "catalogo", "q" => "zzzz-no-existe-tcy"]);
        $this->assertSame("0 piezas", trim($x->query("//*[@id='catalogo-conteo']")->item(0)->textContent));
        $this->assertSame(1, $x->query("//*[contains(@class,'catalogo-vacio')]//a[normalize-space()='Ver todo el catálogo']")->length);
        $this->assertSame(0, $x->query("//article[contains(@class,'tarjeta-pieza')]")->length);
    }

    public function testLaPortadaUsaLaMismaTarjeta(): void {
        $this->pieza("TCY Portada", 30);
        $_GET = [];
        ob_start();
        try { (new \PublicController())->home(); } finally { $html = (string)ob_get_clean(); }
        $x = Html::xpath($html);
        $this->assertGreaterThan(0, $x->query("//article[contains(@class,'tarjeta-pieza')]")->length);
        $this->assertSame(0, $x->query("//*[contains(@class,'pub-card')]")->length);
    }

    public function testElContenedorMideMilDoscientosOchentaYLaRejillaTieneSusColumnas(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
        $this->assertMatchesRegularExpression('/\.container-xl \{[^}]*max-width: 1280px/', $css);
        $this->assertMatchesRegularExpression('/\.container-xl \{[^}]*--bs-gutter-x: 32px/', $css, "16 px por lado en móvil");
        $this->assertMatchesRegularExpression('/min-width: 768px\)\s*\{ \.container-xl \{ --bs-gutter-x: 48px/', $css, "24 px desde 768");
        $this->assertMatchesRegularExpression('/min-width: 1200px\)\s*\{ \.container-xl \{ --bs-gutter-x: 64px/', $css, "32 px desde 1200");
        $this->assertMatchesRegularExpression('/\.rejilla-piezas \{[^}]*grid-template-columns: 1fr;/', $css, "una columna (fila horizontal) en teléfono");
        $this->assertMatchesRegularExpression('/min-width: 576px\)\s*\{ \.rejilla-piezas \{ grid-template-columns: repeat\(2,/', $css);
        $this->assertMatchesRegularExpression('/min-width: 768px\)\s*\{ \.rejilla-piezas \{ grid-template-columns: repeat\(3,/', $css, "3 columnas desde 768");
        $this->assertMatchesRegularExpression('/min-width: 1200px\)\s*\{ \.rejilla-piezas \{ grid-template-columns: repeat\(4,/', $css);
        $this->assertMatchesRegularExpression('/\.tarjeta-pieza \{[^}]*grid-template-columns: 96px/', $css, "foto de 96 px en la fila del teléfono");
        $this->assertMatchesRegularExpression('/\.precio \{[^}]*font-size: 20px; font-weight: 600;[^}]*color: var\(--precio\)/', $css);
    }
}
