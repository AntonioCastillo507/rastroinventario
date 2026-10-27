<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;

/** Barras del Dashboard con color propio: piezas por categoría (mapa de la dona), marcas estables y marcas sin duplicados. */
final class DashboardBarrasColorTest extends BaseDatos {
    private function venta(string $marca, string $pieza, int $sec, int $cant): void {
        $this->db->prepare("INSERT INTO autos (marca, modelo, anio) VALUES (?, 'M', 2020)")->execute([$marca]);
        $auto = (int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO partes (nombre, auto_id, seccion_id, precio, cantidad) VALUES (?, ?, ?, 10, 9)")->execute([$pieza, $auto, $sec]);
        $parte = (int)$this->db->lastInsertId();
        $this->db->exec("INSERT INTO pedidos (cliente_nombre, cliente_telefono, cliente_email, cliente_direccion, estado, subtotal, total, creado_en) VALUES ('C','1','c@example.invalid','d','entregado',10,10.7,NOW())");
        $ped = (int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO pedido_items (pedido_id, parte_id, parte_nombre, precio_unitario, cantidad) VALUES (?,?,?,10,?)")->execute([$ped, $parte, $pieza, $cant]);
    }

    private function rango(): array { return [date("Y-m-d 00:00:00", strtotime("-1 day")), date("Y-m-d 00:00:00", strtotime("+1 day"))]; }

    public function testHondaConEspacioYSinEspacioSumanEnUnaFila(): void {
        $this->db->exec("DELETE FROM pedido_items"); $this->db->exec("DELETE FROM pedidos");
        $this->db->exec("INSERT INTO secciones (nombre) VALUES ('Sec barras')"); $sec = (int)$this->db->lastInsertId();
        $this->venta("Honda", "P1", $sec, 2); $this->venta("Honda ", "P2", $sec, 3); $this->venta("HONDA", "P3", $sec, 1); $this->venta("Toyota", "P4", $sec, 1);
        [$d, $h] = $this->rango();
        $m = (new \EstadisticaModel())->topMarcas($d, $h);
        $this->assertCount(2, $m);
        $this->assertSame(["Honda" => 6, "Toyota" => 1], array_column($m, "unidades", "nombre") + [] ?: []);
        $this->assertNotSame($m[0]["color"], $m[1]["color"]);
        $this->assertSame($m[0]["color"], (new \EstadisticaModel())->topMarcas($d, $h, 1)[0]["color"], "el color no depende del límite");
    }

    public function testLasPiezasTomanElColorDeSuCategoriaDelMapaDeLaDona(): void {
        $this->db->exec("DELETE FROM pedido_items"); $this->db->exec("DELETE FROM pedidos");
        $this->db->exec("INSERT INTO secciones (nombre) VALUES ('Sec color')"); $sec = (int)$this->db->lastInsertId();
        $this->venta("Marca X", "Pieza color", $sec, 4);
        [$d, $h] = $this->rango();
        $em = new \EstadisticaModel();
        $p = $em->topPartes($d, $h);
        $this->assertSame($em->coloresDeCategorias()[$sec], $p[0]["color"]);
        $dona = array_column($em->ventasPorCategoria($d, $h), "color", "id");
        $this->assertSame($dona[$sec], $p[0]["color"], "mismo color que el segmento de la dona");
    }

    public function testElJsUsaLosTokensDeGraficaYDeEstado(): void {
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/dashboard.js");
        foreach (["--warning-text", "--alert-info-border", "--success-text", "--danger-text"] as $t) $this->assertStringContainsString($t, $js);
        $this->assertStringContainsString('"--chart-" + x.color', $js);
        $this->assertSame(3, substr_count($js, "porColor(") + substr_count($js, "ESTADO_TOKEN[x.estado]") - 1, "partes, marcas y estados pasan colores");
    }
}
