<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** El nombre de la marca llega a los correos y a los títulos de los reportes de Excel */
final class MarcaCorreosYReportesTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Marca::usarParaPruebas(array_merge(\Marca::todo(), ["nombre" => "Taller Cambiado Zeta"]));
    }

    protected function tearDown(): void {
        \EmailJsService::$transporte = null;
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    public function testLosTresCorreosLlevanElNombreDeLaMarca(): void {
        $recibidos = [];
        \EmailJsService::$transporte = function (string $plantilla, array $params) use (&$recibidos): bool { $recibidos[] = $params; return true; };
        $s = \EmailJsService::get();
        $s->enviarBienvenida("a@example.invalid", "Ana");
        $s->enviarConfirmacionCompra("a@example.invalid", "Ana", 7, 10.0);
        $s->enviarRecuperacion("a@example.invalid", "Ana", "http://localhost/x");
        $this->assertCount(3, $recibidos);
        foreach ($recibidos as $i => $p) $this->assertSame("Taller Cambiado Zeta", $p["app_nombre"], "correo $i");
    }

    public function testElTituloDeCadaHojaDeExcelLlevaElNombreDeLaMarca(): void {
        $hoja = (new \PhpOffice\PhpSpreadsheet\Spreadsheet())->getActiveSheet();
        $ctrl = (new \ReflectionClass(\ReporteController::class))->newInstanceWithoutConstructor();
        $mayusculas = (new \ReflectionMethod(\ReporteController::class, "marcaMayusculas"))->invoke($ctrl);
        $this->assertSame("TALLER CAMBIADO ZETA", $mayusculas);
        (new \ReflectionMethod(\ReporteController::class, "tituloPagina"))->invoke($ctrl, $hoja, "REPORTE X — " . $mayusculas, 4);
        $this->assertSame("REPORTE X — TALLER CAMBIADO ZETA", $hoja->getCell("A1")->getValue());
        $this->assertStringStartsWith("Taller Cambiado Zeta — Generado el ", $hoja->getCell("A2")->getValue());
    }

    public function testLosTresTitulosDeReporteSalenDeLaMarcaYNoDeUnTextoFijo(): void {
        $codigo = (string)file_get_contents(BASE_PATH . "/app/Controllers/ReporteController.php");
        $this->assertSame(3, preg_match_all('/tituloPagina\(\$sh,\s*"[^"]*— "\.\$this->marcaMayusculas\(\)/', $codigo));
    }
}
