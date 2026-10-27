<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Recorre TODAS las pantallas reales ya armadas (tienda, acceso y panel) y exige que ningún enlace interno
 * vuelva a abrir pestaña nueva, que las que quedan (PDF y externas) lleven rel y aviso, y que "Salir"
 * sea siempre un formulario POST con token (nunca un enlace a logout.php).
 */
final class EnlacesRenderizadosTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    /** @return array<string,array{0:string}> */
    public static function pantallas(): array {
        $out = [];
        foreach (Pantallas::nombres() as $n) $out[$n] = [$n];
        return $out;
    }

    #[DataProvider("pantallas")]
    public function testNingunEnlaceInternoAbrePestanaNuevaEnNingunaPantalla(string $nombre): void {
        $x = Html::xpath(Pantallas::render($this->db, $nombre));
        foreach ($x->query("//a[@target]") as $a) {
            $href = $a->getAttribute("href");
            $this->assertSame("_blank", $a->getAttribute("target"), "$nombre: target raro en $href");
            $externo = (bool)preg_match('~^https?://~i', $href) && !str_starts_with($href, APP_URL);
            $pdf = (bool)preg_match('/(action=factura|mod=factura)/', $href);
            $this->assertTrue($externo || $pdf, "$nombre: enlace interno que abre pestaña nueva: $href");
            $this->assertStringContainsString("noopener", $a->getAttribute("rel"), "$nombre: sin rel=noopener: $href");
            $nombreAccesible = $a->getAttribute("aria-label") . " " . $a->textContent;
            $this->assertMatchesRegularExpression('/pestaña nueva|otra pestaña/u', $nombreAccesible, "$nombre: sin aviso accesible: $href");
        }
        $this->assertSame(0, $x->query("//form[@target]")->length, "$nombre: un formulario con target");
    }

    #[DataProvider("pantallas")]
    public function testSalirEsSiempreUnFormularioPostConToken(string $nombre): void {
        $x = Html::xpath(Pantallas::render($this->db, $nombre));
        $this->assertSame(0, $x->query("//a[contains(@href,'logout.php')]")->length, "$nombre: Salir no puede ser un enlace");
        foreach ($x->query("//form[contains(@action,'logout.php')]") as $f) {
            $this->assertSame("post", strtolower($f->getAttribute("method")), "$nombre: Salir debe ser POST");
            $token = (new \DOMXPath($f->ownerDocument))->query(".//input[@type='hidden'][@name='csrf_token']", $f);
            $this->assertSame(1, $token->length, "$nombre: Salir sin token CSRF");
            $this->assertNotSame("", $token->item(0)->getAttribute("value"));
        }
    }

    /** @return array<string,array{0:string,1:int}> pantalla con sesión → cuántos "Salir" debe tener */
    public static function conSalir(): array {
        return [
            "admin_dashboard" => ["admin_dashboard", 1],
            "admin_partes" => ["admin_partes", 1],
            "mi_perfil" => ["mi_perfil", 2],       // el del encabezado y el del perfil
            "home_cliente" => ["detalle_cliente", 1],
        ];
    }

    #[DataProvider("conSalir")]
    public function testLasPantallasConSesionTienenSuBotonSalir(string $nombre, int $cuantos): void {
        $x = Html::xpath(Pantallas::render($this->db, $nombre));
        $this->assertSame($cuantos, $x->query("//form[contains(@action,'logout.php')]//button[@type='submit']")->length, $nombre);
    }

    public function testSalirDelPanelNoLlevaTargetYTiendaSeAbreEnLaMismaPestana(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_dashboard"));
        $tienda = $x->query("//header//a[normalize-space()='Ver la tienda']");
        $this->assertSame(1, $tienda->length);
        $this->assertSame("", $tienda->item(0)->getAttribute("target"), "Tienda abre en la misma pestaña");
    }

    public function testVerPublicoDeLaListaDePartesSeAbreEnLaMismaPestana(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_partes"));
        $enlaces = $x->query("//a[@title='Ver público']");
        $this->assertGreaterThan(0, $enlaces->length);
        foreach ($enlaces as $a) {
            $this->assertSame("", $a->getAttribute("target"));
            $this->assertStringNotContainsString("pestaña nueva", $a->getAttribute("aria-label"));
        }
    }
}
