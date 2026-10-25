<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;

/** La factura PDF toma el nombre, los datos del negocio y el impuesto de la marca (sin firma: no usa la llave real) */
final class FacturaMarcaTest extends BaseDatos {
    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    /** Texto del PDF sin comprimir; los paréntesis y las barras van escapados dentro del PDF */
    private function pdf(): string {
        $parte = Datos::parte($this->db, 20.00, 10, "Pieza de factura");
        $cliente = Datos::usuario($this->db);
        $pedido = (new \PedidoModel())->findWithItems(Datos::pedido($this->db, [[$parte, 2]], "pendiente", $cliente));
        $pdf = (new \FacturaController())->construirPDF($pedido, null);
        $pdf->setCompression(false);
        $crudo = $pdf->Output("factura.pdf", "S");
        return str_replace(["\\(", "\\)", "\\\\"], ["(", ")", "\\"], $crudo);
    }

    public function testSinCambiosLaFacturaLlevaLosValoresDelArchivo(): void {
        $pdf = $this->pdf();
        $m = \Marca::todo();
        $latin = fn(string $t) => mb_convert_encoding($t, "ISO-8859-1", "UTF-8"); // el PDF guarda los acentos en Latin-1
        $this->assertStringContainsString("(" . mb_strtoupper($m["nombre"], "UTF-8") . ")", $pdf);
        $this->assertStringContainsString($m["telefono"], $pdf);
        $this->assertStringContainsString($m["correo"], $pdf);
        $this->assertStringContainsString($latin($m["ciudad"]), $pdf);
        $this->assertStringContainsString("ITBMS (7%):", $pdf);
        $this->assertStringContainsString("incl. ITBMS 7%", $pdf);
        $this->assertStringContainsString($latin($m["nombre"] . " - " . $m["pais"] . "."), $pdf);
        $this->assertStringContainsString($latin($m["impuesto"]["descripcion"]), $pdf);
    }

    public function testCambiadaLaMarcaLaFacturaMuestraLosValoresNuevos(): void {
        \Marca::usarParaPruebas(array_replace_recursive(\Marca::todo(), [
            "nombre" => "Taller Cambiado Zeta", "telefono" => "+999 8888-7777", "correo" => "cambiado@example.invalid",
            "ciudad" => "Ciudad Cambiada", "pais" => "Paisandu",
            "impuesto" => ["nombre" => "IVAX", "tasa" => 0.12, "descripcion" => "Impuesto cambiado de prueba"],
        ]));
        $pdf = $this->pdf();
        foreach (["(TALLER CAMBIADO ZETA)", "+999 8888-7777", "cambiado@example.invalid", "Ciudad Cambiada", "IVAX (12%):", "incl. IVAX 12%", "Taller Cambiado Zeta - Paisandu.", "Nota: El IVAX corresponde al Impuesto cambiado de prueba."] as $t) {
            $this->assertStringContainsString($t, $pdf, $t);
        }
        foreach (["ITBMS", "6542", "chapimaster", "ISTMO", "Antonio"] as $viejo) {
            $this->assertStringNotContainsStringIgnoringCase($viejo, $pdf, "queda un dato viejo ($viejo) en la factura");
        }
        // 2 x 20.00 = 40.00 sin impuesto; con 12 %: 4.80 y 44.80
        $this->assertStringContainsString("$ 40.00", $pdf);
    }

    public function testElImpuestoDeUnPedidoYaGuardadoNoCambiaSiCambiaLaTasaDespues(): void {
        // lo cobrado es lo que quedó en la base (total - subtotal): cambiar la marca no reescribe facturas viejas
        $parte = Datos::parte($this->db, 20.00, 10, "Pieza");
        $pedido = (new \PedidoModel())->findWithItems(Datos::pedido($this->db, [[$parte, 1]], "pendiente", Datos::usuario($this->db)));
        $antes = \Itbms::dePedido($pedido);
        \Marca::usarParaPruebas(array_replace_recursive(\Marca::todo(), ["impuesto" => ["tasa" => 0.5]]));
        $this->assertSame($antes, \Itbms::dePedido($pedido));
    }
}
