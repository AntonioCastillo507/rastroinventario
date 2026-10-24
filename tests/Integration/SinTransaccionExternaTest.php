<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;

/**
 * PedidoModel::cambiarEstado por su rama real de producción: sin transacción externa, con su propio
 * COMMIT / ROLLBACK. Aquí no se envuelve nada, así que lo creado se borra al final por ids exactos,
 * y el estado final se lee desde otra conexión para comprobar que de verdad quedó confirmado.
 */
final class SinTransaccionExternaTest extends TestCase {
    private PDO $db;
    private PDO $otra;
    private array $partes = [];
    private array $pedidos = [];

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction(), "esta prueba corre sin transacción externa");
        $this->otra = new PDO("mysql:host=" . DB_HOST . ";dbname=rastroinventario_test;charset=utf8mb4", DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        BaseDatos::verificarBase($this->otra);
    }

    protected function tearDown(): void {
        if ($this->db->inTransaction()) $this->db->rollBack();
        if ($this->partes) {
            $in = implode(",", array_map("intval", $this->partes));
            $this->db->exec("DELETE FROM vendido_parte WHERE parte_id IN ($in)");
        }
        if ($this->pedidos) {
            $in = implode(",", array_map("intval", $this->pedidos));
            $this->db->exec("DELETE FROM pedido_items WHERE pedido_id IN ($in)");
            $this->db->exec("DELETE FROM pedidos WHERE id IN ($in)");
        }
        if ($this->partes) {
            $in = implode(",", array_map("intval", $this->partes));
            $this->db->exec("DELETE FROM partes WHERE id IN ($in)");
        }
        parent::tearDown();
    }

    private function pedido(int $stock, int $cantidad): array {
        $parte = Datos::parte($this->db, 10.00, $stock);
        $this->partes[] = $parte;
        $pedido = Datos::pedido($this->db, [[$parte, $cantidad]]);
        $this->pedidos[] = $pedido;
        return [$pedido, $parte];
    }

    private function leerDesdeOtraConexion(int $pedido, int $parte): array {
        $e = $this->otra->prepare("SELECT estado FROM pedidos WHERE id = ?");
        $e->execute([$pedido]);
        $s = $this->otra->prepare("SELECT cantidad FROM partes WHERE id = ?");
        $s->execute([$parte]);
        return [$e->fetchColumn(), (int)$s->fetchColumn()];
    }

    public function testCancelarConfirmaElCambioYDevuelveElStock(): void {
        [$pedido, $parte] = $this->pedido(10, 3);
        $this->assertSame(7, Datos::stock($this->db, $parte));

        $r = (new \PedidoModel())->cambiarEstado($pedido, "cancelado");
        $this->assertTrue($r["ok"], $r["msg"]);
        $this->assertFalse($this->db->inTransaction(), "no deja la transacción abierta");
        $this->assertSame(["cancelado", 10], $this->leerDesdeOtraConexion($pedido, $parte), "confirmado y visible desde otra conexión");
    }

    public function testReabrirSinStockDeshaceTodoYNoDejaNadaAbierto(): void {
        [$pedido, $parte] = $this->pedido(10, 3);
        $pm = new \PedidoModel();
        $pm->cambiarEstado($pedido, "cancelado");
        $this->db->prepare("UPDATE partes SET cantidad = 1 WHERE id = ?")->execute([$parte]);

        $r = $pm->cambiarEstado($pedido, "pendiente");
        $this->assertFalse($r["ok"]);
        $this->assertStringContainsString("No hay stock suficiente", $r["msg"]);
        $this->assertFalse($this->db->inTransaction(), "el rechazo cierra su propia transacción");
        $this->assertSame(["cancelado", 1], $this->leerDesdeOtraConexion($pedido, $parte));
    }

    public function testEntregarConfirmaLasVentas(): void {
        [$pedido, $parte] = $this->pedido(10, 2);
        $r = (new \PedidoModel())->cambiarEstado($pedido, "entregado");
        $this->assertTrue($r["ok"], $r["msg"]);

        $v = $this->otra->prepare("SELECT COUNT(*) FROM vendido_parte WHERE parte_id = ?");
        $v->execute([$parte]);
        $this->assertSame(1, (int)$v->fetchColumn(), "venta confirmada, visible desde otra conexión");
        $this->assertSame(["entregado", 8], $this->leerDesdeOtraConexion($pedido, $parte));
    }
}
