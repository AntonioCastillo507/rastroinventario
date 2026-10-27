<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Pie (S4.5.2 y rehecho después): marca con descripción y redes circulares | Comprar | Empresa | Contacto, con una línea bajo cada
 * título, iconos de contacto sin caja y alineados con su texto, barra inferior con el copyright y «Volver arriba», y la dirección
 * con enlace a Google Maps. Sin columna de marcas (cambiaría de tamaño con cada marca nueva) ni Términos ni Privacidad.
 */
final class PieSitioTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    private function pie(array $cambios = []): \DOMXPath {
        \Marca::usarParaPruebas(array_merge(\Marca::todo(), $cambios));
        return Html::xpath(Pantallas::render($this->db, "home"));
    }

    private static function css(): string {
        return (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
    }

    private static function regla(string $selector): string {
        preg_match('/(?:^|[}\s])' . preg_quote($selector, "/") . '\s*\{([^}]*)\}/m', self::css(), $m);
        return $m[1] ?? "";
    }

    private function columna(\DOMXPath $x, string $titulo): \DOMNode {
        return $x->query("//footer//h2[normalize-space()='$titulo']/ancestor::div[contains(@class,'pie-col')][1]")->item(0);
    }

    // ── estructura ──
    public function testLasRedesVanEnLaColumnaDeLaMarcaYLaBarraSoloLlevaCopyrightYVolverArriba(): void {
        $x = $this->pie();
        $this->assertGreaterThan(0, $x->query("//footer//div[contains(@class,'pie-fila')]/div[1]//ul[contains(@class,'pie-redes')]//a[contains(@class,'pie-red')]")->length, "redes en la primera columna");
        $this->assertSame(1, $x->query("//footer//div[contains(@class,'pie-barra')]//a")->length, "la barra solo lleva un enlace");
        $arriba = $x->query("//footer//div[contains(@class,'pie-barra')]//a[contains(@class,'pie-arriba')]");
        $this->assertSame(1, $arriba->length);
        $this->assertSame("#", $arriba->item(0)->getAttribute("href"));
        $this->assertStringContainsString("Volver arriba", $arriba->item(0)->textContent);
        $this->assertSame(1, $x->query("//footer//div[contains(@class,'pie-barra')]//p[contains(@class,'pie-copy')]")->length);
        $this->assertSame(1, $x->query("//footer//div[contains(@class,'pie-fila')]/div[1]//p[contains(@class,'pie-descripcion')]")->length, "descripción de la marca");
    }

    public function testCuatroColumnasEnElOrdenPedido(): void {
        $x = $this->pie();
        $this->assertSame(4, $x->query("//footer//div[contains(@class,'pie-fila')]/div")->length);
        $this->assertSame(["Comprar", "Empresa", "Contacto"], array_map(fn($e) => trim($e->textContent), iterator_to_array($x->query("//footer//h2"))));
        $this->assertSame(1, $x->query("//footer//div[contains(@class,'pie-fila')]/div[1]//*[contains(@class,'marca-nombre')]")->length, "la primera columna es la marca");
    }

    public function testComprarLlevaCatalogoYLasCategoriasConElMismoEnlaceDeLaPortada(): void {
        $x = $this->pie();
        $enlaces = $x->query(".//a", $this->columna($x, "Comprar"));
        $this->assertSame(APP_URL . "/?ver=catalogo", $enlaces->item(0)->getAttribute("href"));
        $this->assertSame("Catálogo", trim($enlaces->item(0)->textContent));
        $secciones = $x->query("//ul[contains(@class,'cat-lista')]//a");
        $this->assertGreaterThan(0, $secciones->length);
        $this->assertSame($secciones->length + 1, $enlaces->length, "Catálogo y una por categoría");
        foreach ($secciones as $k => $s) {
            $this->assertSame($s->getAttribute("href"), $enlaces->item($k + 1)->getAttribute("href"), "mismo enlace que la tarjeta de categoría");
            $this->assertSame(trim($x->query(".//*[contains(@class,'cat-nombre')]", $s)->item(0)->textContent), trim($enlaces->item($k + 1)->textContent), "mismo nombre, leído de la base");
        }
    }

    public function testEmpresaLlevaCuatroEnlaces(): void {
        $x = $this->pie();
        $enlaces = $x->query(".//a", $this->columna($x, "Empresa"));
        $this->assertSame(["Quiénes somos", "Contacto", "Mi cuenta", "Carrito"], array_map(fn($a) => trim($a->textContent), iterator_to_array($enlaces)));
        $this->assertSame([APP_URL . "/?action=nosotros", APP_URL . "/?action=contacto", APP_URL . "/login.php", APP_URL . "/?action=carrito"], array_map(fn($a) => $a->getAttribute("href"), iterator_to_array($enlaces)));
    }

    public function testNoHayColumnaDeMarcasNiTerminosNiPrivacidad(): void {
        $x = $this->pie();
        $pie = $x->query("//footer")->item(0);
        foreach (["Marcas", "Términos", "Terminos", "Privacidad"] as $t) $this->assertStringNotContainsString($t, $pie->textContent, $t);
        $this->assertStringNotContainsString("marca=", $x->document->saveHTML($pie), "ningún enlace de marca en el pie");
    }

    public function testLasRedesSonBotonesCircularesDeSoloIconoEnUnaFila(): void {
        $r = self::regla(".pie-red");
        $this->assertMatchesRegularExpression('/width:\s*44px/', $r);
        $this->assertMatchesRegularExpression('/height:\s*44px/', $r);
        $this->assertMatchesRegularExpression('/border-radius:\s*var\(--rounded-pill\)/', $r);
        $this->assertMatchesRegularExpression('/flex-wrap:\s*nowrap/', self::regla(".pie-redes"), "una sola fila");
    }

    // ── mapa y datos ──
    public function testLaDireccionEnlazaAGoogleMapsEnOtraPestanaConAviso(): void {
        $x = $this->pie(["mapa" => "", "direccion" => "Calle 50, Ciudad de Panamá"]);
        $a = $x->query("//footer//a[contains(@class,'pie-mapa')]")->item(0);
        $this->assertNotNull($a);
        $this->assertSame("https://www.google.com/maps/search/?api=1&query=Calle%2050%2C%20Ciudad%20de%20Panam%C3%A1", $a->getAttribute("href"), "sin clave mapa, búsqueda con el texto de la dirección");
        $this->assertSame("_blank", $a->getAttribute("target"));
        $this->assertSame("noopener noreferrer", $a->getAttribute("rel"));
        $this->assertStringContainsString("Calle 50, Ciudad de Panamá", $a->textContent);
        $this->assertStringContainsString("otra pestaña", $a->textContent, "aviso accesible");
    }

    public function testSinDireccionNoHayFilaNiEnlace(): void {
        $x = $this->pie(["direccion" => ""]);
        $this->assertSame(0, $x->query("//footer//a[contains(@class,'pie-mapa')]")->length);
        $this->assertSame(0, $x->query("//footer//i[contains(@class,'bi-geo-alt')]")->length);
    }

    public function testElEnlaceDelMapaDeMarcaSeUsaSoloSiEsHttp(): void {
        \Marca::usarParaPruebas(array_merge(\Marca::todo(), ["mapa" => "https://maps.example.invalid/x?y=1", "direccion" => "Algún sitio"]));
        $this->assertSame("https://maps.example.invalid/x?y=1", \Marca::mapa());
        foreach (["javascript:alert(1)", "//evil.example.invalid", "maps/x", "   "] as $malo) {
            \Marca::usarParaPruebas(array_merge(\Marca::todo(), ["mapa" => $malo, "direccion" => "Algún sitio"]));
            $this->assertSame("https://www.google.com/maps/search/?api=1&query=Alg%C3%BAn%20sitio", \Marca::mapa(), $malo);
        }
    }

    public function testMarcaPhpTraeElMapaDeEjemploMarcadoParaReemplazar(): void {
        $src = (string)file_get_contents(BASE_PATH . "/app/Config/marca.php");
        $this->assertMatchesRegularExpression('~"mapa"\s*=>\s*"https://www\.google\.com/maps/search/~', $src);
        $this->assertMatchesRegularExpression('~EJEMPLO[^\n]*reemplazar[^\n]*antes de publicar~u', $src, "el enlace de ejemplo lleva su aviso");
    }

    public function testElHorarioSaleEnFilasDiaYHorasSeparadasPorUnEspacio(): void {
        $x = $this->pie(["horario" => [["dia" => "Lun – Vie", "horas" => "8:00 AM – 5:00 PM", "abierto" => true], ["dia" => "Sábado", "horas" => "8:00 AM – 12:00 PM", "abierto" => true]]]);
        $filas = $x->query("//footer//span[contains(@class,'pie-horario-fila')]");
        $this->assertSame(2, $filas->length);
        $this->assertSame("Lun – Vie 8:00 AM – 5:00 PM", trim($filas->item(0)->textContent), "con espacio entre día y horas para el lector de pantalla");
    }

    // ── CSS ──
    public function testLosIconosDeContactoVanSinCajaEnUnaColumnaFijaYCentradosConSuTexto(): void {
        $icono = self::regla(".pie-icono");
        $this->assertMatchesRegularExpression('/width:\s*20px/', $icono);
        $this->assertDoesNotMatchRegularExpression('/background|border/', $icono, "sin cajitas en los iconos");
        $fila = self::regla(".pie-contacto li");
        $this->assertMatchesRegularExpression('/grid-template-columns:\s*20px\s+minmax\(0,\s*1fr\)/', $fila, "el texto arranca siempre en el mismo borde");
        $this->assertMatchesRegularExpression('/column-gap:\s*var\(--spacing-sm\)/', $fila, "12 px entre icono y texto");
        $this->assertDoesNotMatchRegularExpression('/padding-top:\s*5px/', self::css(), "sin el parche de 5 px");
        $this->assertMatchesRegularExpression('/overflow-wrap:\s*anywhere/', self::regla(".pie-contacto li > a, .pie-contacto li > .pie-horario"));
    }

    public function testElTituloLlevaUnaLineaFinaDebajoConElColorDelTema(): void {
        $t = self::regla(".pie-titulo");
        $this->assertMatchesRegularExpression('/border-bottom:\s*1px solid var\(--text-muted\)/', $t, "la línea sigue al modo claro u oscuro por el token");
        $this->assertMatchesRegularExpression('/font-weight:\s*700/', $t);
        $this->assertMatchesRegularExpression('/color:\s*var\(--text-strong\)/', $t);
    }

    public function testElPieEsUnaRejillaDeCuatroColumnasConProporcionesParejasDesde992Px(): void {
        $css = self::css();
        $this->assertMatchesRegularExpression('/\.pie-fila \{ display: grid; grid-template-columns: repeat\(2,/', $css, "en tablet y móvil, 2 columnas");
        $this->assertMatchesRegularExpression('/@media \(min-width: 992px\) \{ \.pie-fila \{ grid-template-columns: 1\.6fr 1fr 1fr 1\.3fr; \}/', $css);
        $this->assertMatchesRegularExpression('/\.pie-marca, \.pie-col-contacto \{ grid-column: 1 \/ -1; \}/', $css, "la marca y Contacto ocupan el ancho en tablet y móvil");
        $this->assertStringNotContainsString("pie-col-empresa", $css);
    }

    public function testElFondoDelPieEsUnPasoDistintoConLineaSuperiorYRelleno64Y48(): void {
        $s = self::regla(".pie-sitio");
        $this->assertMatchesRegularExpression('/background:\s*var\(--surface-strong\)/', $s);
        $this->assertMatchesRegularExpression('/border-top:\s*1px solid var\(--hairline\)/', $s);
        $this->assertMatchesRegularExpression('/padding-top:\s*var\(--spacing-xxl\)/', $s, "48 px en móvil");
        $this->assertMatchesRegularExpression('/@media \(min-width: 992px\) \{ \.pie-sitio \{ padding-top: calc\(var\(--spacing-xxl\) \+ var\(--spacing-md\)\)/', self::css(), "64 px en escritorio");
        $this->assertMatchesRegularExpression('/padding-bottom:\s*var\(--spacing-xxl\)/', self::regla(".pie-fila"));
    }

    public function testLosEnlacesMiden36PxEnEscritorioY44PxEnTactilYMovil(): void {
        $this->assertMatchesRegularExpression('/min-height:\s*36px/', self::regla(".pie-lista a"));
        $this->assertStringNotContainsString("margin-top", self::regla(".pie-lista a"));
        $this->assertMatchesRegularExpression('/@media \(max-width: 767\.98px\), \(pointer: coarse\) \{ \.pie-lista a \{ min-height: 44px; \}/', self::css());
        $this->assertMatchesRegularExpression('/@media \(max-width: 767\.98px\), \(pointer: coarse\) \{ \.pie-contacto li > a \{ min-height: 44px;/', self::css());
        $this->assertMatchesRegularExpression('/min-height:\s*44px/', self::regla(".pie-arriba"));
    }

    public function testElHoverEsColorDeTitularConSubrayadoAmarilloFinoSinMoverElTexto(): void {
        $h = self::regla(".pie-lista a:hover");
        $this->assertMatchesRegularExpression('/color:\s*var\(--text-strong\)/', $h);
        $this->assertMatchesRegularExpression('/text-decoration-color:\s*var\(--btn-primary-bg\)/', $h);
        $this->assertMatchesRegularExpression('/text-decoration-thickness:\s*1px/', $h);
        $this->assertDoesNotMatchRegularExpression('/transform|padding|margin|font-weight|letter-spacing/', $h, "el texto no se mueve ni cambia de tamaño");
        $t = self::regla(".pie-lista a");
        $this->assertMatchesRegularExpression('/transition:\s*color \.15s ease-out/', $t, "solo el color, 150 ms");
    }
}
