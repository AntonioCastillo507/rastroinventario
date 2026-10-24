<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;

/** ParteModel::decrementarStock / incrementarStock (hallazgo 7: sin sobreventa) */
final class ParteStockTest extends BaseDatos {
    private \ParteModel $pm;

    protected function setUp(): void {
        parent::setUp();
        $this->pm = new \ParteModel();
    }

    public function testDescuentaLaCantidadExacta(): void {
        $id = Datos::parte($this->db, 5.00, 10);
        $this->assertTrue($this->pm->decrementarStock($id, 3));
        $this->assertSame(7, Datos::stock($this->db, $id));
    }

    public function testPuedeVenderTodoElStock(): void {
        $id = Datos::parte($this->db, 5.00, 4);
        $this->assertTrue($this->pm->decrementarStock($id, 4));
        $this->assertSame(0, Datos::stock($this->db, $id));
    }

    public function testNoVendeMasDeLoQueHay(): void {
        $id = Datos::parte($this->db, 5.00, 4);
        $this->assertFalse($this->pm->decrementarStock($id, 5));
        $this->assertSame(4, Datos::stock($this->db, $id), "sin cambios");
        $this->assertTrue($this->pm->decrementarStock($id, 4));
        $this->assertFalse($this->pm->decrementarStock($id, 1), "agotada");
        $this->assertSame(0, Datos::stock($this->db, $id), "nunca queda negativo");
    }

    public function testCantidadCeroONegativaSeRechaza(): void {
        $id = Datos::parte($this->db, 5.00, 4);
        foreach ([0, -1, -100] as $q) {
            $this->assertFalse($this->pm->decrementarStock($id, $q), "decrementar $q");
            $this->assertFalse($this->pm->incrementarStock($id, $q), "incrementar $q");
        }
        $this->assertSame(4, Datos::stock($this->db, $id));
    }

    public function testParteInexistente(): void {
        $this->assertFalse($this->pm->decrementarStock(2000000000, 1));
        $this->assertFalse($this->pm->incrementarStock(2000000000, 1));
    }

    public function testIncrementaLaCantidadExacta(): void {
        $id = Datos::parte($this->db, 5.00, 0);
        $this->assertTrue($this->pm->incrementarStock($id, 6));
        $this->assertSame(6, Datos::stock($this->db, $id));
    }

    public function testSoloTocaLaPiezaIndicada(): void {
        $a = Datos::parte($this->db, 5.00, 10);
        $b = Datos::parte($this->db, 5.00, 10);
        $this->pm->decrementarStock($a, 2);
        $this->pm->incrementarStock($b, 5);
        $this->assertSame([8, 15], [Datos::stock($this->db, $a), Datos::stock($this->db, $b)]);
    }

    public function testDescuentosSeguidosNuncaDejanElStockNegativo(): void {
        $id = Datos::parte($this->db, 5.00, 5);
        $aceptadas = 0;
        for ($i = 0; $i < 10; $i++) if ($this->pm->decrementarStock($id, 2)) $aceptadas++;
        $this->assertSame(2, $aceptadas, "5 de stock alcanza para dos ventas de 2");
        $this->assertSame(1, Datos::stock($this->db, $id));
    }
}
