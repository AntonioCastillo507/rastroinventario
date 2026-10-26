<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * P7, páginas informativas: Contacto con las acciones "Llamar" y "WhatsApp" (con los enlaces de ejemplo de marca.php) y Quiénes somos
 * con texto de línea corta, foto solo si el archivo existe y las insignias sin confirmar ocultas.
 */
final class ContactoYNosotrosTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    private function marca(array $cambios): void {
        \Marca::usarParaPruebas(array_replace_recursive(\Marca::todo(), $cambios));
    }

    private function x(string $n): \DOMXPath {
        return Html::xpath(Pantallas::render($this->db, $n));
    }

    // ── Contacto ──

    public function testLlamarEsElBotonPrincipalConElNumeroEnSuNombreAccesible(): void {
        $x = $this->x("contacto");
        $a = $x->query("//main//div[contains(@class,'contacto-acciones')]/a[contains(@class,'btn-gold')]")->item(0);
        $this->assertNotNull($a);
        $this->assertSame("tel:" . preg_replace('/[^\d+]/', "", \Marca::get("telefono")), $a->getAttribute("href"));
        $this->assertStringStartsWith("Llamar", trim($a->textContent));
        $this->assertStringContainsString(\Marca::get("telefono"), $a->textContent, "el número, en el texto accesible");
        $this->assertSame(0, $x->query(".//i", $a)->length, "sin icono en el botón");
        $this->assertSame(1, $x->query("//main//*[contains(@class,'btn-gold')]")->length, "un solo botón principal");
    }

    public function testWhatsAppEsSecundarioYUsaElEnlaceDeEjemploDeLaMarca(): void {
        $x = $this->x("contacto");
        $w = $x->query("//main//div[contains(@class,'contacto-acciones')]/a[contains(@class,'btn-outline-secondary')]")->item(0);
        $this->assertNotNull($w);
        $this->assertSame(\Marca::whatsapp(), $w->getAttribute("href"));
        $this->assertStringStartsWith("https://wa.me/", $w->getAttribute("href"));
        $this->assertSame("_blank", $w->getAttribute("target"));
        $this->assertSame("noopener noreferrer", $w->getAttribute("rel"));
        $this->assertStringContainsString("se abre en otra pestaña", $w->textContent);
        $this->assertStringStartsWith("WhatsApp", trim($w->textContent));
    }

    public function testWhatsAppYaNoSeRepiteEnSiguenos(): void {
        $x = $this->x("contacto");
        $siguenos = $x->query("//section[.//h2[normalize-space()='Síguenos']]")->item(0);
        $this->assertNotNull($siguenos);
        $nombres = array_map(fn($n) => trim(str_replace("(se abre en otra pestaña)", "", $n->textContent)), iterator_to_array($x->query(".//a", $siguenos)));
        $this->assertNotContains("WhatsApp", $nombres);
        $this->assertContains("Instagram", $nombres);
        $this->assertContains("TikTok", $nombres);
    }

    public function testSinDireccionDeWhatsAppNoHayBoton(): void {
        $m = \Marca::todo();
        foreach ($m["redes"] as &$r) if ($r["icono"] === "whatsapp") $r["url"] = "";
        unset($r);
        \Marca::usarParaPruebas($m);
        $x = $this->x("contacto");
        $this->assertSame(0, $x->query("//main//a[contains(@class,'btn-outline-secondary')]")->length);
        $this->assertSame(1, $x->query("//main//a[contains(@class,'btn-gold')]")->length, "Llamar sigue");
        $this->assertSame("", \Marca::whatsapp());
    }

    public function testUnaDireccionDeWhatsAppQueNoEsHttpNoSePublica(): void {
        $m = \Marca::todo();
        foreach ($m["redes"] as &$r) if ($r["icono"] === "whatsapp") $r["url"] = "javascript:alert(1)";
        unset($r);
        \Marca::usarParaPruebas($m);
        $this->assertSame("", \Marca::whatsapp());
        $this->assertStringNotContainsString("javascript:", Pantallas::render($this->db, "contacto"));
    }

    public function testSinTelefonoNoHayLlamar(): void {
        $this->marca(["telefono" => ""]);
        $x = $this->x("contacto");
        $this->assertSame(0, $x->query("//main//a[contains(@class,'btn-gold')]")->length);
        $this->assertSame(1, $x->query("//main//div[contains(@class,'contacto-acciones')]/a")->length, "queda WhatsApp");
    }

    public function testSinTelefonoNiWhatsAppNoQuedaUnaBarraVacia(): void {
        $m = \Marca::todo();
        $m["telefono"] = "";
        $m["redes"] = [];
        \Marca::usarParaPruebas($m);
        $this->assertSame(0, $this->x("contacto")->query("//*[contains(@class,'contacto-acciones')]")->length);
    }

    public function testLosBotonesDeContactoMidenAlMenos44ConLaClaseDeBoton(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
        $this->assertMatchesRegularExpression('/\.contacto-acciones \.btn \{[^}]*min-height: 44px/', $css);
    }

    // ── Quiénes somos ──

    public function testLasInsigniasSinConfirmarNoSePublicanPorDefecto(): void {
        $this->assertFalse(\Marca::get("nosotros.destacados_visibles", false), "marca.php las deja ocultas");
        $x = $this->x("nosotros");
        $this->assertSame(0, $x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' trust-badge ')]")->length);
        foreach (["Calidad garantizada", "Entrega segura", "Soporte directo", "Mejores precios"] as $afirmacion) {
            $this->assertStringNotContainsString($afirmacion, $x->query("//main")->item(0)->textContent, "$afirmacion: sin confirmar, no se publica");
        }
    }

    public function testConLasInsigniasConfirmadasSeMuestran(): void {
        $this->marca(["nosotros" => ["destacados_visibles" => true]]);
        $x = $this->x("nosotros");
        $this->assertSame(4, $x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' trust-badge ')]")->length);
    }

    public function testElValorDebeSerExactamenteTrue(): void {
        foreach (["true", 1, "1", "si"] as $v) {
            $this->marca(["nosotros" => ["destacados_visibles" => $v]]);
            $this->assertSame(0, $this->x("nosotros")->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' trust-badge ')]")->length, var_export($v, true));
        }
    }

    public function testLaFotoSoloSalePorSuArchivoYSinOnerror(): void {
        $this->marca(["nosotros" => ["imagen" => "assets/img/logo.png"]]); // un archivo que existe
        $x = $this->x("nosotros");
        $img = $x->query("//main//img[contains(@class,'pagina-foto')]");
        $this->assertSame(1, $img->length, "el archivo existe");
        $this->assertSame("", $img->item(0)->getAttribute("alt"), "decorativa");
        $this->assertSame(0, $x->query("//main//*[@onerror]")->length, "sin onerror en línea");
        $this->marca(["nosotros" => ["imagen" => "assets/img/no-existe-" . bin2hex(random_bytes(3)) . ".jpg"]]);
        $x = $this->x("nosotros");
        $this->assertSame(0, $x->query("//main//img[contains(@class,'pagina-foto')]")->length, "sin archivo no hay imagen rota");
        $this->assertSame(1, $x->query("//main//div[contains(@class,'col-12')]//*[contains(@class,'pagina-texto')]")->length, "el texto ocupa el ancho");
        $this->marca(["nosotros" => ["imagen" => ""]]);
        $this->assertSame(0, $this->x("nosotros")->query("//main//img[contains(@class,'pagina-foto')]")->length);
    }

    public function testUnaRutaConPuntosNoSale(): void {
        $this->marca(["nosotros" => ["imagen" => "assets/../README.md"]]);
        $this->assertSame("", \Marca::urlSiExiste("nosotros.imagen"));
        $this->assertSame(0, $this->x("nosotros")->query("//main//img")->length);
    }

    public function testElTextoVaEnUnaColumnaDeLineaCorta(): void {
        $x = $this->x("nosotros");
        $this->assertSame(1, $x->query("//main//*[contains(@class,'pagina-texto')][.//h1]")->length);
        $this->assertSame("65ch", $this->maxAncho());
    }

    private function maxAncho(): string {
        return preg_match('/\.pagina-texto \{ max-width: (\d+ch);/', (string)file_get_contents(BASE_PATH . "/assets/css/base.css"), $m) ? $m[1] : "";
    }

    public function testLaBandaFinalNoRepiteElTituloDeLaPortada(): void {
        $x = $this->x("nosotros");
        $titulo = trim($x->query("//main//div[contains(@class,'cta-band')]//h2")->item(0)->textContent);
        $this->assertNotSame(trim(\Marca::get("portada.titulo") . " " . \Marca::get("portada.titulo_resalte")), $titulo);
        $this->assertSame(1, $x->query("//main//*[contains(@class,'btn-gold')]")->length, "un solo botón principal");
    }
}
