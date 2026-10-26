<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/** S4.5.4: la foto del equipo vuelve a Quiénes somos con nosotros.imagen, fuera de git y de la plantilla. */
final class FotoNosotrosTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    public function testMarcaPhpApuntaALaFotoDelEquipo(): void {
        $this->assertSame("assets/img/nosotros/equipo.jpg", \Marca::todo()["nosotros"]["imagen"]);
    }

    public function testLaCarpetaDeLaFotoEstaFueraDeGit(): void {
        $ignorado = (string)file_get_contents(BASE_PATH . "/.gitignore");
        $this->assertMatchesRegularExpression('~^/assets/img/nosotros/$~m', $ignorado);
        $git = trim((string)shell_exec("git -C " . escapeshellarg(BASE_PATH) . " ls-files assets/img/nosotros 2>NUL"));
        $this->assertSame("", $git, "la foto no está versionada");
    }

    public function testQuienesSomosMuestraLaFotoSoloSiElArchivoEstaEnDisco(): void {
        $x = Html::xpath(Pantallas::render($this->db, "nosotros"));
        $img = $x->query("//main//img[contains(@class,'pagina-foto')]");
        if (is_file(BASE_PATH . "/assets/img/nosotros/equipo.jpg")) {
            $this->assertSame(1, $img->length);
            $this->assertStringEndsWith("/assets/img/nosotros/equipo.jpg", $img->item(0)->getAttribute("src"));
            $this->assertSame("", $img->item(0)->getAttribute("alt"), "decorativa: el texto de la página ya lo dice todo");
        } else {
            $this->assertSame(0, $img->length, "sin archivo no hay imagen rota (la plantilla no trae la foto)");
        }
    }

    public function testLaPlantillaSinLaFotoNoMuestraImagenRota(): void {
        $m = \Marca::todo();
        $m["nosotros"]["imagen"] = "";
        \Marca::usarParaPruebas($m);
        $this->assertSame(0, Html::xpath(Pantallas::render($this->db, "nosotros"))->query("//main//img[contains(@class,'pagina-foto')]")->length);
    }

    public function testLaFotoRestauradaEsUnJpegDePesoRazonable(): void {
        $f = BASE_PATH . "/assets/img/nosotros/equipo.jpg";
        if (!is_file($f)) $this->markTestSkipped("la foto no está en esta copia (es del dueño, no se versiona)");
        $i = getimagesize($f);
        $this->assertSame(IMAGETYPE_JPEG, $i[2]);
        $this->assertLessThan(400 * 1024, filesize($f));
    }
}
