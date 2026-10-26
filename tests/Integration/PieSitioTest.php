<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Pie profesional (S4.5.2): marca con descripción y redes | Comprar | Empresa | Contacto y horario, iconos de contacto del
 * mismo tamaño y alineados con su texto, barra inferior solo con el copyright y dirección con enlace a Google Maps.
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

    public function testLasRedesVanEnLaColumnaDeLaMarcaYLaBarraSoloLlevaElCopyright(): void {
        $x = $this->pie();
        $this->assertGreaterThan(0, $x->query("//footer//div[contains(@class,'pie-fila')]/div[1]//ul[contains(@class,'pie-redes')]//a[contains(@class,'pie-red')]")->length, "redes en la primera columna");
        $this->assertSame(0, $x->query("//footer//div[contains(@class,'pie-barra')]//a")->length, "la barra inferior no lleva enlaces");
        $this->assertSame(1, $x->query("//footer//div[contains(@class,'pie-barra')]//p[contains(@class,'pie-copy')]")->length);
        $this->assertSame(1, $x->query("//footer//div[contains(@class,'pie-fila')]/div[1]//p[contains(@class,'pie-descripcion')]")->length, "descripción de la marca");
    }

    public function testCuatroColumnasEnElOrdenPedido(): void {
        $x = $this->pie();
        $this->assertSame(4, $x->query("//footer//div[contains(@class,'pie-fila')]/div")->length);
        $this->assertSame(["Comprar", "Empresa", "Contacto y horario"], array_map(fn($e) => trim($e->textContent), iterator_to_array($x->query("//footer//h2"))));
        $this->assertSame(1, $x->query("//footer//div[contains(@class,'pie-fila')]/div[1]//*[contains(@class,'marca-nombre')]")->length, "la primera columna es la marca");
    }

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

    public function testLosIconosDeContactoMidenLoMismoYSeCentranConSuTexto(): void {
        $this->assertMatchesRegularExpression('/width:\s*32px/', self::regla(".pie-icono"));
        $this->assertMatchesRegularExpression('/height:\s*32px/', self::regla(".pie-icono"));
        $fila = self::regla(".pie-contacto li");
        $this->assertMatchesRegularExpression('/align-items:\s*center/', $fila, "icono y texto centrados entre sí");
        $this->assertMatchesRegularExpression('/gap:\s*var\(--spacing-sm\)/', $fila, "12 px entre icono y texto");
        $this->assertDoesNotMatchRegularExpression('/padding-top:\s*5px/', self::css(), "sin el parche de 5 px que alineaba a ojo");
        $this->assertMatchesRegularExpression('/gap:\s*var\(--spacing-sm\)/', self::regla(".pie-contacto"), "misma separación vertical entre datos");
    }

    public function testLosEnlacesDeLaColumnaDeContactoNoSeSalenDeLaColumna(): void {
        $this->assertMatchesRegularExpression('/overflow-wrap:\s*anywhere/', self::regla(".pie-contacto li > :not(.pie-icono)"));
        $this->assertMatchesRegularExpression('/min-width:\s*0/', self::regla(".pie-contacto li > :not(.pie-icono)"));
    }
}
