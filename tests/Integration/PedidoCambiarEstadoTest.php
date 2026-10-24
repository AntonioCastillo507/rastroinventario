<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;

/** PedidoModel::cambiarEstado: transiciones, stock y ventas (Error 2). Corre dentro de la transacción de la prueba (usa SAVEPOINT). */
final class PedidoCambiarEstadoTest extends BaseDatos {
    private \PedidoModel $pm;

    protected function setUp(): void {
        parent::setUp();
        $this->pm = new \PedidoModel();
    }

    /** Pedido en el estado indicado con su stock coherente: apartado salvo que esté cancelado */
    private function pedidoEn(string $estado, int $stock = 10, int $cantidad = 3): array {
        $parte = Datos::parte($this->db, 10.00, $stock);
        $pedido = Datos::pedido($this->db, [[$parte, $cantidad]], $estado, apartar: $estado !== "cancelado");
        return [$pedido, $parte];
    }

    // ── Matriz completa de 4 x 4 ───────────────────────────────────────

    public static function matriz(): array {
        $c = [];
        foreach ([
            // desde, hacia, permitida
            ["pendiente", "pendiente", false], ["pendiente", "procesando", true],  ["pendiente", "entregado", true],  ["pendiente", "cancelado", true],
            ["procesando", "pendiente", true], ["procesando", "procesando", false], ["procesando", "entregado", true], ["procesando", "cancelado", true],
            ["entregado", "pendiente", false], ["entregado", "procesando", false], ["entregado", "entregado", false], ["entregado", "cancelado", false],
            ["cancelado", "pendiente", true],  ["cancelado", "procesando", true],  ["cancelado", "entregado", false], ["cancelado", "cancelado", false],
        ] as [$d, $h, $ok]) $c["$d → $h"] = [$d, $h, $ok];
        return $c;
    }

    #[DataProvider("matriz")]
    public function testMatrizDeTransiciones(string $desde, string $hacia, bool $permitida): void {
        [$pedido, $parte] = $this->pedidoEn($desde);
        $antes = Datos::stock($this->db, $parte);

        $r = $this->pm->cambiarEstado($pedido, $hacia);

        $this->assertSame($permitida, $r["ok"], "$desde → $hacia: " . $r["msg"]);
        $this->assertNotSame("", $r["msg"]);
        $this->assertSame($permitida ? $hacia : $desde, Datos::estado($this->db, $pedido), "estado final");

        // Efecto esperado en el stock
        $esperado = $antes;
        if ($permitida && $hacia === "cancelado") $esperado = $antes + 3;       // devuelve
        if ($permitida && $desde === "cancelado") $esperado = $antes - 3;       // reabre: descuenta
        $this->assertSame($esperado, Datos::stock($this->db, $parte), "stock");

        // Las ventas solo se registran al entregar, una por ítem
        $this->assertSame($permitida && $hacia === "entregado" ? 1 : 0, Datos::ventas($this->db, $parte), "ventas");
    }

    public function testLaTablaDeTransicionesCoincideConLaMatriz(): void {
        foreach (self::matriz() as [$desde, $hacia, $permitida]) {
            $this->assertSame($permitida, in_array($hacia, \PedidoModel::transicionesPermitidas($desde), true), "$desde → $hacia");
        }
        $this->assertSame([], \PedidoModel::transicionesPermitidas("entregado"), "entregado es final");
        $this->assertSame([], \PedidoModel::transicionesPermitidas("inventado"));
    }

    // ── Stock al cancelar y reabrir ───────────────────────────────────────

    public function testCancelarDevuelveElStockUnaSolaVez(): void {
        [$pedido, $parte] = $this->pedidoEn("pendiente");
        $this->assertSame(7, Datos::stock($this->db, $parte));

        $r = $this->pm->cambiarEstado($pedido, "cancelado");
        $this->assertTrue($r["ok"]);
        $this->assertStringContainsString("Stock devuelto de 1 pieza(s)", $r["msg"]);
        $this->assertSame(10, Datos::stock($this->db, $parte));

        // Segundo clic: no se devuelve otra vez
        $r2 = $this->pm->cambiarEstado($pedido, "cancelado");
        $this->assertFalse($r2["ok"]);
        $this->assertStringContainsString("ya está en estado", $r2["msg"]);
        $this->assertSame(10, Datos::stock($this->db, $parte), "sin doble restitución");
    }

    public function testCancelarVariasPiezasLasDevuelveTodas(): void {
        $a = Datos::parte($this->db, 5.00, 10);
        $b = Datos::parte($this->db, 7.00, 10);
        $pedido = Datos::pedido($this->db, [[$a, 2], [$b, 4]]);
        $this->assertSame([8, 6], [Datos::stock($this->db, $a), Datos::stock($this->db, $b)]);

        $r = $this->pm->cambiarEstado($pedido, "cancelado");
        $this->assertStringContainsString("2 pieza(s)", $r["msg"]);
        $this->assertSame([10, 10], [Datos::stock($this->db, $a), Datos::stock($this->db, $b)]);
    }

    public function testReabrirDescuentaElStockDeNuevo(): void {
        [$pedido, $parte] = $this->pedidoEn("pendiente");
        $this->pm->cambiarEstado($pedido, "cancelado");
        $this->assertSame(10, Datos::stock($this->db, $parte));

        $r = $this->pm->cambiarEstado($pedido, "procesando");
        $this->assertTrue($r["ok"]);
        $this->assertStringContainsString("descontó de nuevo", $r["msg"]);
        $this->assertSame(7, Datos::stock($this->db, $parte));
        $this->assertSame("procesando", Datos::estado($this->db, $pedido));
    }

    public function testReabrirSinStockSeRechazaYNoCambiaNada(): void {
        [$pedido, $parte] = $this->pedidoEn("pendiente");
        $this->pm->cambiarEstado($pedido, "cancelado");
        $this->db->prepare("UPDATE partes SET cantidad = 2 WHERE id = ?")->execute([$parte]); // se vendió a otra persona

        $r = $this->pm->cambiarEstado($pedido, "pendiente");
        $this->assertFalse($r["ok"]);
        $this->assertStringContainsString("No hay stock suficiente", $r["msg"]);
        $this->assertSame("cancelado", Datos::estado($this->db, $pedido));
        $this->assertSame(2, Datos::stock($this->db, $parte));

        // La transacción de la prueba sigue sana y, con stock, ya se puede reabrir
        $this->db->prepare("UPDATE partes SET cantidad = 5 WHERE id = ?")->execute([$parte]);
        $this->assertTrue($this->pm->cambiarEstado($pedido, "pendiente")["ok"]);
        $this->assertSame(2, Datos::stock($this->db, $parte));
    }

    public function testReabrirConUnaPiezaSinStockDeshaceLasDemas(): void {
        $a = Datos::parte($this->db, 5.00, 10);
        $b = Datos::parte($this->db, 7.00, 10);
        $pedido = Datos::pedido($this->db, [[$a, 2], [$b, 4]]);
        $this->pm->cambiarEstado($pedido, "cancelado");
        $this->db->prepare("UPDATE partes SET cantidad = 1 WHERE id = ?")->execute([$b]);

        $r = $this->pm->cambiarEstado($pedido, "pendiente");
        $this->assertFalse($r["ok"]);
        $this->assertSame(10, Datos::stock($this->db, $a), "la pieza A que sí alcanzaba vuelve a como estaba");
        $this->assertSame(1, Datos::stock($this->db, $b));
        $this->assertSame("cancelado", Datos::estado($this->db, $pedido));
    }

    // ── Entregado ──────────────────────────────────────────────────────

    public function testEntregadoEsFinal(): void {
        [$pedido, $parte] = $this->pedidoEn("pendiente");
        $this->assertTrue($this->pm->cambiarEstado($pedido, "entregado")["ok"]);
        $stock = Datos::stock($this->db, $parte);

        foreach (["pendiente", "procesando", "cancelado", "entregado"] as $nuevo) {
            $r = $this->pm->cambiarEstado($pedido, $nuevo);
            $this->assertFalse($r["ok"], $nuevo);
        }
        $this->assertStringContainsString("ya no se puede cambiar", $this->pm->cambiarEstado($pedido, "cancelado")["msg"]);
        $this->assertSame("entregado", Datos::estado($this->db, $pedido));
        $this->assertSame($stock, Datos::stock($this->db, $parte), "no se devuelve stock de lo entregado");
        $this->assertSame(1, Datos::ventas($this->db, $parte), "una sola venta");
    }

    public function testCanceladoNoPasaDirectoAEntregado(): void {
        [$pedido, $parte] = $this->pedidoEn("cancelado");
        $r = $this->pm->cambiarEstado($pedido, "entregado");
        $this->assertFalse($r["ok"]);
        $this->assertStringContainsString("No se puede pasar de", $r["msg"]);
        $this->assertSame(0, Datos::ventas($this->db, $parte));
    }

    public function testEntregarRegistraUnaVentaFirmadaPorItem(): void {
        $u = Datos::usuario($this->db);
        $a = Datos::parte($this->db, 8.50, 10);
        $b = Datos::parte($this->db, 28.00, 10);
        $pedido = Datos::pedido($this->db, [[$a, 2], [$b, 1]], usuarioId: $u);

        $r = $this->pm->cambiarEstado($pedido, "entregado");
        $this->assertTrue($r["ok"]);
        $this->assertStringContainsString("2 venta(s)", $r["msg"]);
        $this->assertSame([8, 9], [Datos::stock($this->db, $a), Datos::stock($this->db, $b)], "entregar no vuelve a descontar");

        $s = $this->db->prepare("SELECT * FROM vendido_parte WHERE parte_id IN (?, ?) ORDER BY parte_id");
        $s->execute([$a, $b]);
        $ventas = $s->fetchAll();
        $this->assertCount(2, $ventas);
        $this->assertSame([2, 1], [(int)$ventas[0]["cantidad"], (int)$ventas[1]["cantidad"]]);
        $this->assertEqualsWithDelta(8.50, (float)$ventas[0]["precio_venta"], 0.001);
        $this->assertSame($u, (int)$ventas[0]["usuario_id"]);
        foreach ($ventas as $v) {
            $this->assertTrue(\DigitalSignature::verifyVenta($v), "la firma de la venta verifica");
        }
    }

    public function testItemsSinParteSeSaltan(): void {
        [$pedido, $parte] = $this->pedidoEn("pendiente");
        $this->db->prepare("UPDATE pedido_items SET parte_id = NULL WHERE pedido_id = ?")->execute([$pedido]);

        $r = $this->pm->cambiarEstado($pedido, "entregado");
        $this->assertTrue($r["ok"]);
        $this->assertSame("Estado actualizado.", $r["msg"]);
        $this->assertSame(0, Datos::ventas($this->db, $parte));

        [$p2] = $this->pedidoEn("pendiente");
        $this->db->prepare("UPDATE pedido_items SET parte_id = NULL WHERE pedido_id = ?")->execute([$p2]);
        $c = $this->pm->cambiarEstado($p2, "cancelado");
        $this->assertTrue($c["ok"]);
        $this->assertStringContainsString("0 pieza(s)", $c["msg"]);
    }

    // ── Otros ──────────────────────────────────────────────────────────

    public function testPedidoInexistente(): void {
        $r = $this->pm->cambiarEstado(2000000000, "cancelado");
        $this->assertFalse($r["ok"]);
        $this->assertStringContainsString("no encontrado", $r["msg"]);
    }

    public function testUnEstadoInventadoSeRechaza(): void {
        [$pedido] = $this->pedidoEn("pendiente");
        $r = $this->pm->cambiarEstado($pedido, "volando");
        $this->assertFalse($r["ok"]);
        $this->assertSame("pendiente", Datos::estado($this->db, $pedido));
    }

    public function testElMensajeConNombreDeEstadoNoSeInterpolaMal(): void {
        [$pedido] = $this->pedidoEn("pendiente");
        $r = $this->pm->cambiarEstado($pedido, "pendiente");
        $this->assertStringContainsString("«pendiente»", $r["msg"], "el nombre del estado aparece, no una variable sin resolver");
    }
}
