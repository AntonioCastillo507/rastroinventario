<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Imagenes;

/**
 * P1: en todas las pantallas la foto de una pieza va dentro de su baldosa 1:1 (foto-baldosa), con la derivada del tamaño que
 * toca (640 en tarjetas, carrito y panel; 1200 en el detalle) y alt solo donde el nombre no está al lado. El formulario de
 * partes lleva la vista previa y los umbrales de los avisos.
 */
final class FotoBaldosaVistasTest extends BaseDatos {
    private int $pieza;
    private array $img;

    protected function setUp(): void {
        parent::setUp();
        Imagenes::limpiarGuardados();
        $_SERVER["REQUEST_METHOD"] = "GET";
        $uid = Datos::usuario($this->db);
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = 'Administrador'")->execute([$uid]);
        $_SESSION["user"] = ["id" => $uid, "nombre" => "Admin", "email" => "a@example.invalid"];
        $_SESSION["user_roles"] = ["Administrador"];
        $this->img = \ImageHelper::procesar(Imagenes::jpeg(900, 700));
        $this->pieza = Datos::parte($this->db, 10.00, 5, "Pieza con foto");
        $this->db->prepare("UPDATE partes SET thumbnail = ?, imagen_grande = ? WHERE id = ?")->execute([$this->img["thumbnail"], $this->img["original"], $this->pieza]);
    }

    protected function tearDown(): void {
        $_GET = [];
        unset($_SERVER["REQUEST_METHOD"], $_SESSION["user"], $_SESSION["user_roles"]);
        Imagenes::limpiarGuardados();
        parent::tearDown();
    }

    private function html(callable $accion, array $get): \DOMXPath {
        $_GET = $get;
        ob_start();
        try { $accion(); } finally { $html = (string)ob_get_clean(); }
        return Html::xpath($html);
    }

    private function fotosDePiezas(\DOMXPath $x): \DOMNodeList {
        return $x->query("//img[contains(@src,'/uploads/')]");
    }

    private function assertTodasEnBaldosa(\DOMXPath $x, string $pantalla): void {
        $fotos = $this->fotosDePiezas($x);
        $this->assertGreaterThan(0, $fotos->length, "$pantalla: debe haber foto");
        foreach ($fotos as $img) {
            $this->assertSame("span", $img->parentNode->nodeName, $pantalla);
            $this->assertStringContainsString("foto-baldosa", $img->parentNode->getAttribute("class"), "$pantalla: la foto va en la baldosa");
            $this->assertSame("", $img->getAttribute("style"), "$pantalla: sin estilos en línea que cambien la proporción");
        }
    }

    public function testCatalogoYPortadaUsanLaBaldosaConLaMiniatura(): void {
        $x = $this->html(fn() => (new \PublicController())->home(), ["ver" => "catalogo", "q" => "Pieza con foto"]);
        $this->assertTodasEnBaldosa($x, "catalogo");
        $this->assertSame(1, Html::contar($x, "//img[contains(@src,'/uploads/thumbnails/')]"));
        $this->assertSame("Pieza con foto", $this->fotosDePiezas($x)->item(0)->getAttribute("alt"), "en la tarjeta la foto lleva el nombre de la pieza");
    }

    public function testDetalleUsaLaDerivadaDe1200EnLaBaldosa(): void {
        $x = $this->html(fn() => (new \PublicController())->detalle(), ["id" => (string)$this->pieza]);
        $this->assertTodasEnBaldosa($x, "detalle");
        $this->assertSame(1, Html::contar($x, "//img[contains(@src,'/uploads/medium/')]"));
    }

    public function testPanelYFormularioUsanLaBaldosa(): void {
        $x = $this->html(fn() => (new \ParteController())->index(), ["q" => "Pieza con foto"]);
        $this->assertTodasEnBaldosa($x, "panel");
        $this->assertStringContainsString("foto-48", $this->fotosDePiezas($x)->item(0)->parentNode->getAttribute("class"));

        $x = $this->html(fn() => (new \ParteController())->editar(), ["id" => (string)$this->pieza]);
        $this->assertTodasEnBaldosa($x, "formulario");
        $zona = $x->query("//*[@data-foto-previa]")->item(0);
        $this->assertNotNull($zona, "el formulario lleva la vista previa");
        $this->assertSame((string)\ImageHelper::PROPORCION_MIN, $zona->getAttribute("data-prop-min"));
        $this->assertSame((string)\ImageHelper::PROPORCION_MAX, $zona->getAttribute("data-prop-max"));
        $this->assertSame((string)\ImageHelper::LADO_BAJO, $zona->getAttribute("data-lado-bajo"));
        $this->assertSame(1, Html::contar($x, "//*[@data-foto-avisos][@role='status'][@aria-live='polite']"), "los avisos se anuncian");
        $this->assertSame(1, Html::contar($x, "//script[contains(@src,'parte_imagen.js')]"));
    }

    public function testElFormularioNuevoMuestraLaBaldosaVaciaYNoUnaImagenRota(): void {
        $x = $this->html(fn() => (new \ParteController())->crear(), []);
        $this->assertSame(0, Html::contar($x, "//*[@data-foto-previa]//img"));
        $this->assertSame(1, Html::contar($x, "//*[@data-foto-previa]//span[contains(@class,'foto-baldosa')]/i[@aria-hidden='true']"));
    }

    public function testElMarcadorSinFotoNoTieneImagenYSeOcultaALosLectores(): void {
        $this->db->prepare("UPDATE partes SET thumbnail = NULL, imagen_grande = NULL WHERE id = ?")->execute([$this->pieza]);
        $x = $this->html(fn() => (new \PublicController())->detalle(), ["id" => (string)$this->pieza]);
        $this->assertSame(0, $this->fotosDePiezas($x)->length);
        $this->assertSame(1, Html::contar($x, "//span[contains(@class,'foto-baldosa')]/i[@aria-hidden='true']"));
    }

    public function testElAltDeLaFotoSeEscapa(): void {
        $this->db->prepare("UPDATE partes SET nombre = ? WHERE id = ?")->execute(['Faro "X" <b>', $this->pieza]);
        $x = $this->html(fn() => (new \PublicController())->detalle(), ["id" => (string)$this->pieza]);
        $this->assertSame('Faro "X" <b>', $this->fotosDePiezas($x)->item(0)->getAttribute("alt"));
        $this->assertSame(0, Html::contar($x, "//span[contains(@class,'foto-baldosa')]//b"));
    }
}
