<?php
namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** ITBMS 7 % (Error 1): Itbms::calcular y Itbms::dePedido */
final class ItbmsTest extends TestCase {
    public static function casos(): array {
        return [
            "45.00"   => [45.00, 3.15, 48.15],
            "107.00"  => [107.00, 7.49, 114.49],
            "12.00"   => [12.00, 0.84, 12.84],
            "15.00"   => [15.00, 1.05, 16.05],
            "65.00"   => [65.00, 4.55, 69.55],
            "cero"    => [0.0, 0.0, 0.0],
            "un cent" => [0.01, 0.0, 0.01],
        ];
    }

    #[DataProvider("casos")]
    public function testCalcular(float $subtotal, float $itbms, float $total): void {
        $this->assertEquals(["subtotal" => $subtotal, "itbms" => $itbms, "total" => $total], \Itbms::calcular($subtotal));
    }

    public function testLaTasaEsSietePorCiento(): void {
        $this->assertSame(0.07, \Itbms::TASA);
    }

    public function testEsRedondeaUnaSolaVezSobreElSubtotal(): void {
        // El ITBMS de la suma, no la suma de los ITBMS de cada línea
        $this->assertSame(0.21, \Itbms::calcular(3.00)["itbms"]);
        $r = \Itbms::calcular(10.07);
        $this->assertSame(10.77, $r["total"]);
        $this->assertEqualsWithDelta(10.07 * 0.07, $r["itbms"], 0.005);
    }

    public function testElSubtotalSeRedondeaADosDecimales(): void {
        $this->assertSame(10.01, \Itbms::calcular(10.0099)["subtotal"]);
    }

    public function testElTotalSiempreEsSubtotalMasItbms(): void {
        foreach ([0.5, 1.99, 19.99, 123.45, 999.99, 5000.01] as $s) {
            $r = \Itbms::calcular($s);
            $this->assertEqualsWithDelta($r["subtotal"] + $r["itbms"], $r["total"], 0.0001, "subtotal $s");
        }
    }

    public function testDePedidoDevuelveLoQueSeCobro(): void {
        $this->assertEquals(["subtotal" => 45.0, "itbms" => 3.15, "total" => 48.15],
            \Itbms::dePedido(["subtotal" => "45.00", "total" => "48.15"]));
    }

    public function testDePedidoAntiguoSinImpuestoDaCero(): void {
        $this->assertEquals(["subtotal" => 20.0, "itbms" => 0.0, "total" => 20.0],
            \Itbms::dePedido(["subtotal" => "20.00", "total" => "20.00"]));
    }

    public function testDePedidoNoRecalculaConLaTasaActual(): void {
        // Un pedido guardado con otro total conserva ese total
        $this->assertSame(5.0, \Itbms::dePedido(["subtotal" => 100, "total" => 105])["itbms"]);
    }
}
