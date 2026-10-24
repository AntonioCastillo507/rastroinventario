<?php
namespace Tests\Unit;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

/** Inyección de fórmulas en Excel (hallazgo 15): ReporteController::texto() (privado, se prueba por reflexión) */
final class ReporteTextoTest extends TestCase {
    private const PELIGROSOS = [
        "=1+1", "=SUM(1,2)", "==", "=", "+cmd|' /C calc'!A0", "-2+3", "@SUM(1)",
        "\t=x", "\r=x", '=HYPERLINK("http://example.invalid","clic")',
    ];
    private const NORMALES = ["Pastillas de freno", "texto con = adentro", "a+b", "  =con espacio delante"];

    private function escribir(array $valores): Spreadsheet {
        $libro = new Spreadsheet();
        $hoja  = $libro->getActiveSheet();
        $m = new \ReflectionMethod(\ReporteController::class, "texto");
        $c = new \ReporteController();
        foreach ($valores as $i => $v) $m->invoke($c, $hoja, "A" . ($i + 1), $v);
        return $libro;
    }

    private function guardarYRecargar(Spreadsheet $libro): array {
        $ruta = RASTRO_TEST_TMP . "/reporte_" . uniqid() . ".xlsx";
        (new Xlsx($libro))->save($ruta);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($ruta) === true);
        $xml = (string)$zip->getFromName("xl/worksheets/sheet1.xml");
        $zip->close();
        return [IOFactory::load($ruta), $xml];
    }

    public function testNingunTextoSeConvierteEnFormula(): void {
        $valores = array_merge(self::PELIGROSOS, self::NORMALES);
        [, $xml] = $this->guardarYRecargar($this->escribir($valores));
        $this->assertDoesNotMatchRegularExpression('/<f[ >]/', $xml, "el .xlsx no debe tener fórmulas");
    }

    public function testLosTextosVuelvenIdenticosYComoCadena(): void {
        $valores = array_merge(self::PELIGROSOS, self::NORMALES);
        [$libro] = $this->guardarYRecargar($this->escribir($valores));
        $hoja = $libro->getActiveSheet();
        foreach ($valores as $i => $v) {
            $celda = $hoja->getCell("A" . ($i + 1));
            // El XML del .xlsx normaliza el retorno de carro a salto de línea; el texto sigue siendo texto
            $this->assertSame(str_replace("\r", "\n", $v), (string)$celda->getValue(), "fila " . ($i + 1));
            $this->assertSame(DataType::TYPE_STRING, $celda->getDataType(), "fila " . ($i + 1));
        }
    }

    public function testLosPeligrososLlevanComillaDeTextoYLosNormalesNo(): void {
        $libro = $this->escribir(array_merge(self::PELIGROSOS, self::NORMALES));
        $hoja  = $libro->getActiveSheet();
        foreach (self::PELIGROSOS as $i => $v) {
            $this->assertTrue($hoja->getStyle("A" . ($i + 1))->getQuotePrefix(), "peligroso " . json_encode($v));
        }
        foreach (self::NORMALES as $j => $v) {
            $this->assertFalse($hoja->getStyle("A" . (count(self::PELIGROSOS) + $j + 1))->getQuotePrefix(), "normal " . json_encode($v));
        }
    }

    public function testNulosYVaciosNoFallan(): void {
        $libro = $this->escribir([null, "", 0, "0", 12.5]);
        [$otro] = $this->guardarYRecargar($libro);
        $h = $otro->getActiveSheet();
        $this->assertSame("0", (string)$h->getCell("A3")->getValue());
        $this->assertSame("12.5", (string)$h->getCell("A5")->getValue());
    }
}
