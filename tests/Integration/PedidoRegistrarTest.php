<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;

/** PedidoModel::registrar: el total guardado es el subtotal más el ITBMS de 7 % */
final class PedidoRegistrarTest extends BaseDatos {
    private function datosPedido(): array {
        return [
            "cliente_nombre" => "Cliente de prueba", "cliente_telefono" => "555-0100",
            "cliente_email" => "cliente.prueba@example.invalid", "cliente_direccion" => "Calle Inventada 123",
            "cliente_notas" => null, "usuario_id" => null,
        ];
    }

    private function fila(int $id): array {
        return (new \PedidoModel())->findById($id);
    }

    public function testTotalEsSubtotalMasItbms(): void {
        $filtro   = Datos::parte($this->db, 8.50, 20);
        $pastilla = Datos::parte($this->db, 28.00, 20);
        $id = Datos::pedido($this->db, [[$filtro, 2], [$pastilla, 1]], apartar: false);

        $p = $this->fila($id);
        $this->assertEqualsWithDelta(45.00, (float)$p["subtotal"], 0.001);
        $this->assertEqualsWithDelta(48.15, (float)$p["total"], 0.001, "45.00 + ITBMS 3.15");
        $this->assertEquals(\Itbms::calcular(45.00)["total"], (float)$p["total"]);
    }

    public function testElImpuestoSeRedondeaUnaSolaVezSobreElSubtotal(): void {
        $a = Datos::parte($this->db, 10.07, 5);
        $p = $this->fila(Datos::pedido($this->db, [[$a, 1]], apartar: false));
        $this->assertEqualsWithDelta(10.07, (float)$p["subtotal"], 0.001);
        $this->assertEqualsWithDelta(10.77, (float)$p["total"], 0.001);

        // Tres líneas de 3.33: el ITBMS se calcula sobre 9.99, no línea por línea
        $b = Datos::parte($this->db, 3.33, 9);
        $q = $this->fila(Datos::pedido($this->db, [[$b, 3]], apartar: false));
        $this->assertEqualsWithDelta(9.99, (float)$q["subtotal"], 0.001);
        $this->assertEqualsWithDelta(\Itbms::calcular(9.99)["total"], (float)$q["total"], 0.001);
    }

    public function testGuardaLosItemsConSuPrecioYNombre(): void {
        $a = Datos::parte($this->db, 12.00, 9, "Filtro de aire de prueba");
        $id = Datos::pedido($this->db, [[$a, 3]], apartar: false);
        $items = (new \PedidoModel())->findWithItems($id)["items"];
        $this->assertCount(1, $items);
        $this->assertSame($a, (int)$items[0]["parte_id"]);
        $this->assertSame("Filtro de aire de prueba", $items[0]["parte_nombre"]);
        $this->assertEqualsWithDelta(12.00, (float)$items[0]["precio_unitario"], 0.001);
        $this->assertSame(3, (int)$items[0]["cantidad"]);
    }

    public function testNaceComoPendienteYConFirma(): void {
        $a = Datos::parte($this->db, 5.00, 5);
        $p = $this->fila(Datos::pedido($this->db, [[$a, 1]], apartar: false));
        $this->assertSame("pendiente", $p["estado"]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $p["firma_digital"]);
    }

    public function testRegistrarNoTocaElStock(): void {
        $a = Datos::parte($this->db, 5.00, 7);
        Datos::pedido($this->db, [[$a, 3]], apartar: false);
        $this->assertSame(7, Datos::stock($this->db, $a), "el descuento lo hace el controlador al comprar");
    }

    public function testGuardaElUsuarioYLosDatosDelCliente(): void {
        $u = Datos::usuario($this->db);
        $a = Datos::parte($this->db, 5.00, 5);
        $p = $this->fila(Datos::pedido($this->db, [[$a, 1]], usuarioId: $u, apartar: false));
        $this->assertSame($u, (int)$p["usuario_id"]);
        $this->assertSame("cliente.prueba@example.invalid", $p["cliente_email"]);
    }

    public function testUnPedidoSinItemsTieneTotalCero(): void {
        $id = (new \PedidoModel())->registrar($this->datosPedido(), []);
        $this->assertIsInt($id);
        $p = $this->fila($id);
        $this->assertEqualsWithDelta(0.0, (float)$p["total"], 0.001);
    }

    public function testElTotalCoincideConItbmsDePedido(): void {
        $a = Datos::parte($this->db, 19.99, 9);
        $p = $this->fila(Datos::pedido($this->db, [[$a, 3]], apartar: false));
        $d = \Itbms::dePedido($p);
        $this->assertEqualsWithDelta(59.97, $d["subtotal"], 0.001);
        $this->assertEqualsWithDelta($d["subtotal"] + $d["itbms"], $d["total"], 0.001);
    }
}
