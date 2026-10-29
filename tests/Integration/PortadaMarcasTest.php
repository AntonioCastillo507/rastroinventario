<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/** Sección «Compra por marca» de la portada: logos, monogramas, enlaces al catálogo, qué marcas salen y textos de marca.php */
final class PortadaMarcasTest extends BaseDatos {
    private \MarcaAutoModel $m;
    private array $archivos = [];

    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
        $this->m = new \MarcaAutoModel();
    }

    protected function tearDown(): void {
        foreach ($this->archivos as $f) @unlink($f);
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    /** Marca con un auto y una pieza a la venta; con $conLogo crea además el archivo en la carpeta de logos de prueba */
    private function marcaConPieza(string $nombre, bool $conLogo = false, int $cantidad = 5, bool $activa = true): int {
        $id = $this->m->crear($nombre);
        if ($conLogo) {
            $archivo = "marca_" . bin2hex(random_bytes(16)) . ".png";
            @mkdir(\LogoMarca::dir(), 0777, true);
            file_put_contents(\LogoMarca::dir() . $archivo, "x");
            $this->archivos[] = \LogoMarca::dir() . $archivo;
            $this->m->guardarLogo($id, $archivo);
        }
        $this->db->prepare("INSERT INTO autos (marca, marca_id, modelo, anio) VALUES (?, ?, 'M', 2020)")->execute([$nombre, $id]);
        $auto = (int)$this->db->lastInsertId();
        $p = Datos::parte($this->db, 10.00, $cantidad, "Pieza $nombre");
        $this->db->prepare("UPDATE partes SET auto_id = ? WHERE id = ?")->execute([$auto, $p]);
        if (!$activa) $this->m->activar($id, false);
        return $id;
    }

    private function home(): \DOMXPath {
        return Html::xpath(Pantallas::render($this->db, "home"));
    }

    private function enlace(\DOMXPath $x, string $nombre): ?\DOMElement {
        foreach ($x->query("//ul[@data-marcas-pista]//a") as $a) {
            $texto = trim($x->query(".//span[contains(@class,'visually-hidden')]", $a)->item(0)->textContent);
            if (str_starts_with($texto, $nombre . ",")) return $a;
        }
        return null;
    }

    public function testLaMarcaConLogoUsaLaMascaraConSuArchivoYLaSinLogoSuMonograma(): void {
        $this->marcaConPieza("Zq Con Logo", true);
        $this->marcaConPieza("Zq Mercedes Benz");
        $x = $this->home();
        $con = $this->enlace($x, "Zq Con Logo");
        $this->assertNotNull($con);
        $logo = $x->query(".//span[contains(@class,'marca-logo')]", $con)->item(0);
        $this->assertNotNull($logo, "máscara del logo");
        $this->assertMatchesRegularExpression("~^--logo-url:url\('" . preg_quote(APP_URL, "~") . "/uploads/marcas/marca_[0-9a-f]{32}\.png'\)$~", $logo->getAttribute("style"));
        $this->assertSame(0, $x->query(".//*[contains(@class,'marca-monograma')]", $con)->length);

        $sin = $this->enlace($x, "Zq Mercedes Benz");
        $mono = $x->query(".//span[contains(@class,'marca-monograma')]", $sin)->item(0);
        $this->assertNotNull($mono);
        $this->assertSame("ZM", trim($mono->textContent), "iniciales de las dos primeras palabras");
    }

    public function testSiElArchivoDelLogoFaltaSeUsaElMonogramaYNoUnaImagenRota(): void {
        $id = $this->marcaConPieza("Zq Sin Archivo");
        $this->m->guardarLogo($id, "marca_" . str_repeat("a", 32) . ".png"); // el nombre existe en la base pero no en disco
        $x = $this->home();
        $a = $this->enlace($x, "Zq Sin Archivo");
        $this->assertSame(0, $x->query(".//span[contains(@class,'marca-logo')]", $a)->length);
        $this->assertSame(1, $x->query(".//span[contains(@class,'marca-monograma')]", $a)->length);
    }

    public function testCadaMarcaLlevaAlCatalogoFiltradoConElNombreCodificado(): void {
        $this->marcaConPieza("Zq Marca & Co");
        $x = $this->home();
        $a = $this->enlace($x, "Zq Marca & Co");
        $this->assertNotNull($a);
        $this->assertSame(APP_URL . "/?ver=catalogo&marca=Zq%20Marca%20%26%20Co", $a->getAttribute("href"));
        $this->assertSame("Zq Marca & Co, 1 pieza", trim($x->query(".//span[contains(@class,'visually-hidden')]", $a)->item(0)->textContent), "nombre y conteo accesibles");
    }

    public function testSoloSalenMarcasActivasConPiezasALaVenta(): void {
        $this->marcaConPieza("Zq Sale");
        $this->marcaConPieza("Zq Agotada", false, 0);
        $this->marcaConPieza("Zq Apagada", false, 5, false);
        $this->m->crear("Zq Sin Autos");
        $x = $this->home();
        $this->assertNotNull($this->enlace($x, "Zq Sale"));
        foreach (["Zq Agotada", "Zq Apagada", "Zq Sin Autos"] as $fuera) $this->assertNull($this->enlace($x, $fuera), $fuera);
    }

    public function testElCarruselTraeControlesOcultosConTextosDeMarcaPhpYElScript(): void {
        $this->marcaConPieza("Zq Textos");
        $html = Pantallas::render($this->db, "home");
        $x = Html::xpath($html);
        $ctrl = $x->query("//*[@data-marcas-controles]")->item(0);
        $this->assertTrue($ctrl->hasAttribute("hidden"), "sin JavaScript los controles no se ven");
        $botones = ["data-marcas-anterior" => "portada.marcas_anterior", "data-marcas-siguiente" => "portada.marcas_siguiente", "data-marcas-pausa" => "portada.marcas_pausar"];
        foreach ($botones as $attr => $clave) {
            $b = $x->query(".//button[@$attr]", $ctrl)->item(0);
            $this->assertNotNull($b, $attr);
            $this->assertSame((string)\Marca::get($clave), $b->getAttribute("aria-label"), $attr);
            $this->assertSame("button", $b->getAttribute("type"));
            $this->assertSame("true", $x->query(".//i", $b)->item(0)->getAttribute("aria-hidden"));
        }
        $pausa = $x->query(".//button[@data-marcas-pausa]", $ctrl)->item(0);
        $this->assertSame("false", $pausa->getAttribute("aria-pressed"));
        $this->assertSame((string)\Marca::get("portada.marcas_reanudar"), $pausa->getAttribute("data-texto-reanudar"));
        $this->assertStringContainsString("marcas_carrusel.js", $html);
        $this->assertSame(1, $x->query("//section[@aria-labelledby='t-marcas']//h2[@id='t-marcas']")->length);
    }

    public function testLosTextosSalenDeMarcaPhpYSeEscapan(): void {
        $m = \Marca::todo();
        $m["portada"]["marcas_titulo"] = "Marcas <b>X</b>";
        $m["portada"]["marcas_anterior"] = "Atrás \"ya\"";
        \Marca::usarParaPruebas($m);
        $this->marcaConPieza("Zq Escape");
        $x = $this->home();
        $this->assertSame("Marcas <b>X</b>", trim($x->query("//h2[@id='t-marcas']")->item(0)->textContent));
        $this->assertSame("Atrás \"ya\"", $x->query("//button[@data-marcas-anterior]")->item(0)->getAttribute("aria-label"));
    }
}
