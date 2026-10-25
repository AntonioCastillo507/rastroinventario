<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Pie: botones de redes y mensajería con su icono y su nombre visible, tomados de marca.php. Sin dirección no salen;
 * los enlaces externos abren otra pestaña con rel y aviso accesible; los iconos de función son decorativos (aria-hidden).
 */
final class PieRedesTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    private function pie(array $redes): \DOMXPath {
        $m = \Marca::todo();
        $m["redes"] = $redes;
        \Marca::usarParaPruebas($m);
        return Html::xpath(Pantallas::render($this->db, "home"));
    }

    public function testLosBotonesAparecenConDireccionConIconoYNombreVisible(): void {
        $x = $this->pie([
            ["nombre" => "WhatsApp", "url" => "https://wa.me/50760000000", "icono" => "whatsapp"],
            ["nombre" => "Instagram", "url" => "https://instagram.com/tienda", "icono" => "instagram"],
            ["nombre" => "TikTok", "url" => "https://tiktok.com/@tienda", "icono" => "tiktok"],
        ]);
        $botones = $x->query("//footer//a[contains(@class,'pie-red')]");
        $this->assertSame(3, $botones->length);
        $this->assertSame(["WhatsApp", "Instagram", "TikTok"], array_map(fn($a) => trim($x->query("./span[1]", $a)->item(0)->textContent), iterator_to_array($botones)));
        $this->assertSame("https://wa.me/50760000000", $botones->item(0)->getAttribute("href"));
        foreach (["whatsapp", "instagram", "tiktok"] as $k => $icono) {
            $i = $x->query(".//i", $botones->item($k))->item(0);
            $this->assertStringContainsString("bi-$icono", $i->getAttribute("class"));
            $this->assertSame("true", $i->getAttribute("aria-hidden"));
        }
    }

    public function testSinDireccionElBotonNoSeMuestra(): void {
        $x = $this->pie([
            ["nombre" => "WhatsApp", "url" => "", "icono" => "whatsapp"],
            ["nombre" => "Instagram", "url" => "https://instagram.com/tienda", "icono" => "instagram"],
            ["nombre" => "Mala", "url" => "javascript:alert(1)", "icono" => "link"],
        ]);
        $this->assertSame(1, $x->query("//footer//a[contains(@class,'pie-red')]")->length);
        $this->assertStringNotContainsString("WhatsApp", $x->query("//footer")->item(0)->textContent);
        $this->assertStringNotContainsString("javascript:", $x->document->saveHTML());
    }

    public function testSinNingunaDireccionNoQuedaLaListaVacia(): void {
        $x = $this->pie([]);
        $this->assertSame(0, $x->query("//footer//ul[contains(@class,'pie-redes')]")->length);
        $this->assertSame(1, $x->query("//footer//p[contains(@class,'pie-copy')]")->length, "el copyright sigue");
    }

    public function testLosEnlacesExternosLlevanRelYAvisoDePestanaNueva(): void {
        $x = $this->pie([["nombre" => "Instagram", "url" => "https://instagram.com/tienda", "icono" => "instagram"]]);
        $a = $x->query("//footer//a[contains(@class,'pie-red')]")->item(0);
        $this->assertSame("_blank", $a->getAttribute("target"));
        $this->assertSame("noopener noreferrer", $a->getAttribute("rel"));
        $this->assertStringContainsString("se abre en otra pestaña", $x->query(".//span[contains(@class,'visually-hidden')]", $a)->item(0)->textContent);
    }

    public function testElNombreYLaDireccionDeLaRedSeEscapan(): void {
        $x = $this->pie([["nombre" => "<b>Red</b>", "url" => "https://example.invalid/?a=1&b=\"2", "icono" => "x\"onload=\"y"]]);
        $a = $x->query("//footer//a[contains(@class,'pie-red')]")->item(0);
        $this->assertSame(0, $x->query(".//b", $a)->length);
        $this->assertSame("https://example.invalid/?a=1&b=\"2", $a->getAttribute("href"));
        $this->assertSame(0, $x->query(".//i[@onload]", $a)->length);
    }

    public function testLosDatosDeContactoLlevanSuIconoDecorativoYElHorarioSuTextoAccesible(): void {
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        $iconos = $x->query("//footer//ul[contains(@class,'pie-contacto')]//span[contains(@class,'pie-icono')]");
        $this->assertSame(4, $iconos->length, "ubicación, teléfono, correo y reloj");
        foreach ($iconos as $s) $this->assertSame("true", $s->getAttribute("aria-hidden"));
        $clases = implode(" ", array_map(fn($s) => $x->query("./i", $s)->item(0)->getAttribute("class"), iterator_to_array($iconos)));
        foreach (["geo-alt", "telephone", "envelope", "clock"] as $n) $this->assertStringContainsString("bi-$n", $clases);
        $this->assertStringContainsString("Horario:", $x->query("//footer//span[contains(@class,'pie-horario')]")->item(0)->textContent);
    }

    public function testLaReglaDeIconosSigueVigenteFueraDelPie(): void {
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        $this->assertSame(0, $x->query("//main//h1//i | //main//h2//i | //header//*[contains(@class,'pie-icono')]")->length);
    }
}
