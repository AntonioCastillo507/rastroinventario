<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Volumen;

/**
 * Consultas del dashboard. Los datos propios van en 2031 (la base de pruebas no tiene nada ahí),
 * así los números salen exactos aunque haya datos de demo o de volumen.
 */
final class EstadisticaModelTest extends BaseDatos {
    private \EstadisticaModel $m;
    private const D = "2031-03-";

    protected function setUp(): void {
        parent::setUp();
        $this->m = new \EstadisticaModel();
    }

    private function seccion(string $n): int {
        $this->db->prepare("INSERT INTO secciones (nombre) VALUES (?)")->execute([$n]);
        return (int)$this->db->lastInsertId();
    }

    private function parte(string $nombre, ?int $seccion, ?string $marca): int {
        $auto = null;
        if ($marca !== null) {
            $this->db->prepare("INSERT INTO autos (marca, modelo, anio) VALUES (?, 'M', 2020)")->execute([$marca]);
            $auto = (int)$this->db->lastInsertId();
        }
        $this->db->prepare("INSERT INTO partes (nombre, auto_id, seccion_id, precio, cantidad) VALUES (?, ?, ?, 10, 5)")->execute([$nombre, $auto, $seccion]);
        return (int)$this->db->lastInsertId();
    }

    /** @param list<array{0:?int,1:string,2:float,3:int}> $items parte_id, nombre, precio, cantidad */
    private function pedido(string $estado, string $fecha, array $items): int {
        $sub = array_sum(array_map(fn($i) => $i[2] * $i[3], $items));
        $this->db->prepare("INSERT INTO pedidos (cliente_nombre, cliente_telefono, cliente_email, cliente_direccion, estado, subtotal, total, creado_en)
            VALUES ('C', '1', 'c@example.invalid', 'd', ?, ?, ?, ?)")->execute([$estado, $sub, $sub * 1.07, $fecha]);
        $id = (int)$this->db->lastInsertId();
        foreach ($items as $i) {
            $this->db->prepare("INSERT INTO pedido_items (pedido_id, parte_id, parte_nombre, precio_unitario, cantidad) VALUES (?, ?, ?, ?, ?)")->execute([$id, ...$i]);
        }
        return $id;
    }

    // ── Rango y periodo ─────────────────────────────────────────────────────

    public function testRangoValidoCaeAlDefecto(): void {
        foreach ([7, 30, 90, 365] as $d) $this->assertSame($d, \EstadisticaModel::rangoValido($d));
        foreach (["abc", 0, -5, 31, "1 OR 1=1", null] as $malo) $this->assertSame(30, \EstadisticaModel::rangoValido($malo));
        $this->assertSame(365, \EstadisticaModel::rangoValido("x", 365));
    }

    public function testPeriodoActualYAnteriorDeIgualDuracion(): void {
        $p = \EstadisticaModel::periodo(7, new \DateTimeImmutable("2031-03-15 17:45"));
        $this->assertSame("2031-03-09 00:00:00", $p["desde"]);
        $this->assertSame("2031-03-16 00:00:00", $p["hasta"]);
        $this->assertSame("2031-03-02 00:00:00", $p["anteriorDesde"]);
    }

    // ── Rango «Todo» ────────────────────────────────────────────────────────

    public function testRangoTodoSePideConLaPalabraYElCeroNoValeComoRango(): void {
        $this->assertSame(\EstadisticaModel::TODO, \EstadisticaModel::rangoValido("todo"));
        $this->assertSame(30, \EstadisticaModel::rangoValido("0"));
        $this->assertSame(30, \EstadisticaModel::rangoValido(0));
        $this->assertContains(\EstadisticaModel::TODO, \EstadisticaModel::RANGOS);
    }

    public function testPeriodoTodoEmpiezaElPrimerDiaDelMesDeLaPrimeraVentaYNoTieneAnterior(): void {
        $p = \EstadisticaModel::periodoTodo("2031-03-17 10:00:00", new \DateTimeImmutable("2031-05-02 08:00"));
        $this->assertSame(0, $p["dias"]);
        $this->assertSame("2031-03-01 00:00:00", $p["desde"]);
        $this->assertSame("2031-05-03 00:00:00", $p["hasta"]);
        $this->assertNull($p["anteriorDesde"]);
        $sin = \EstadisticaModel::periodoTodo(null, new \DateTimeImmutable("2031-05-02"));
        $this->assertSame("2031-05-01 00:00:00", $sin["desde"], "sin ventas, el mes actual");
    }

    public function testLaPrimeraVentaEsLaMasAntiguaEntregadaYLaSerieTodoVaPorMes(): void {
        $this->pedido("cancelado", "1998-01-05 10:00:00", [[null, "A", 9.0, 1]]);
        $this->pedido("entregado", "1999-05-17 10:00:00", [[null, "A", 10.0, 1]]);
        $this->pedido("entregado", "1999-07-02 10:00:00", [[null, "A", 30.0, 1]]);
        $this->assertSame("1999-05-17 10:00:00", $this->m->primeraVenta(), "los cancelados no cuentan");
        $p = \EstadisticaModel::periodoTodo($this->m->primeraVenta(), new \DateTimeImmutable("1999-08-20"));
        $s = $this->m->ventasPorPeriodo($p["desde"], $p["hasta"], "mes");
        $this->assertSame(["1999-05", "1999-06", "1999-07", "1999-08"], array_column($s, "clave"));
        $this->assertSame([10.0, 0.0, 30.0, 0.0], array_column($s, "total"));
    }

    // ── Resumen ─────────────────────────────────────────────────────────────

    public function testResumenSoloCuentaEntregadosYRespetaLimites(): void {
        $this->pedido("entregado", self::D . "10 00:00:00", [[null, "A", 50.0, 2]]);   // entra (borde inferior)
        $this->pedido("entregado", self::D . "10 23:59:59", [[null, "A", 20.0, 1]]);   // entra
        $this->pedido("entregado", self::D . "11 00:00:00", [[null, "A", 999.0, 1]]);  // fuera (borde superior)
        $this->pedido("entregado", self::D . "09 23:59:59", [[null, "A", 999.0, 1]]);  // fuera
        foreach (["pendiente", "procesando", "cancelado"] as $e) $this->pedido($e, self::D . "10 12:00:00", [[null, "A", 999.0, 1]]);
        $r = $this->m->resumen(self::D . "10 00:00:00", self::D . "11 00:00:00");
        $this->assertSame(["ventas" => 120.0, "pedidos" => 2, "ticket" => 60.0], $r);
    }

    public function testResumenVacioNoDivideEntreCero(): void {
        $this->assertSame(["ventas" => 0.0, "pedidos" => 0, "ticket" => 0.0], $this->m->resumen("2031-01-01 00:00:00", "2031-02-01 00:00:00"));
    }

    public function testVariacion(): void {
        $this->assertSame(25.0, \EstadisticaModel::variacion(125, 100));
        $this->assertSame(-50.0, \EstadisticaModel::variacion(50, 100));
        $this->assertSame(0.0, \EstadisticaModel::variacion(100, 100));
        $this->assertNull(\EstadisticaModel::variacion(100, 0));
    }

    // ── Serie ───────────────────────────────────────────────────────────────

    public function testSerieDiariaRellenaLosDiasSinVentas(): void {
        $this->pedido("entregado", self::D . "02 09:00:00", [[null, "A", 10.0, 1]]);
        $this->pedido("entregado", self::D . "02 18:00:00", [[null, "A", 5.0, 1]]);
        $this->pedido("entregado", self::D . "04 09:00:00", [[null, "A", 7.0, 1]]);
        $this->pedido("pendiente", self::D . "03 09:00:00", [[null, "A", 100.0, 1]]);
        $s = $this->m->ventasPorPeriodo(self::D . "01 00:00:00", self::D . "06 00:00:00");
        $this->assertSame(["2031-03-01", "2031-03-02", "2031-03-03", "2031-03-04", "2031-03-05"], array_column($s, "clave"));
        $this->assertSame([0.0, 15.0, 0.0, 7.0, 0.0], array_column($s, "total"));
        $this->assertSame([0, 2, 0, 1, 0], array_column($s, "pedidos"));
    }

    public function testSerieMensualAgrupaPorMes(): void {
        $this->pedido("entregado", "2031-01-20 10:00:00", [[null, "A", 10.0, 1]]);
        $this->pedido("entregado", "2031-03-05 10:00:00", [[null, "A", 30.0, 1]]);
        $this->pedido("entregado", "2031-03-25 10:00:00", [[null, "A", 20.0, 1]]);
        $s = $this->m->ventasPorPeriodo("2031-01-15 00:00:00", "2031-04-01 00:00:00", "mes");
        $this->assertSame(["2031-01", "2031-02", "2031-03"], array_column($s, "clave"));
        $this->assertSame([10.0, 0.0, 50.0], array_column($s, "total"));
    }

    // ── Categorías, partes y marcas ─────────────────────────────────────────

    public function testCategoriasOrdenadasConDesempatePorNombre(): void {
        $b = $this->parte("P1", $this->seccion("Zeta cat"), "X");
        $a = $this->parte("P2", $this->seccion("Alfa cat"), "X");
        $g = $this->parte("P3", $this->seccion("Grande cat"), "X");
        $this->pedido("entregado", self::D . "10 10:00:00", [[$b, "P1", 10.0, 3], [$a, "P2", 10.0, 3], [$g, "P3", 10.0, 9]]);
        $this->pedido("cancelado", self::D . "10 10:00:00", [[$g, "P3", 500.0, 1]]);
        $r = $this->m->ventasPorCategoria(self::D . "01 00:00:00", self::D . "30 00:00:00");
        $this->assertSame(["Grande cat", "Alfa cat", "Zeta cat"], array_column($r, "nombre"));
        $this->assertSame([90.0, 30.0, 30.0], array_column($r, "total"));
    }

    /** Crea $n categorías con ventas 10·n, 10·(n-1)… y devuelve [ids, ventas totales] */
    private function categoriasConVentas(int $n, string $fecha = "10"): array {
        $items = []; $ids = [];
        for ($k = 1; $k <= $n; $k++) {
            $s = $this->seccion("Var $k");
            $ids[] = $s;
            $items[] = [$this->parte("PV$k", $s, "M$k"), "PV$k", 10.0, $n - $k + 1];
        }
        $this->pedido("entregado", self::D . "$fecha 10:00:00", $items);
        return $ids;
    }

    public static function cantidades(): array {
        return ["1" => [1, 1], "4" => [4, 4], "5" => [5, 5], "6" => [6, 5], "9" => [9, 5]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("cantidades")]
    public function testDonaConNCategorias(int $n, int $segmentos): void {
        $this->categoriasConVentas($n);
        $r = $this->m->ventasPorCategoria(self::D . "01 00:00:00", self::D . "30 00:00:00");
        $this->assertCount($segmentos, $r);
        $total = 0.0; for ($k = 1; $k <= $n; $k++) $total += 10 * ($n - $k + 1);
        $this->assertEqualsWithDelta($total, array_sum(array_column($r, "total")), 0.001, "la dona suma lo vendido");
        if ($n > 5) {
            $this->assertSame("Otras", end($r)["nombre"]);
            $this->assertSame(0, end($r)["color"], "«Otras» va en gris");
            $this->assertSame(["Var 1", "Var 2", "Var 3", "Var 4"], array_column(array_slice($r, 0, 4), "nombre"), "quedan las 4 mayores");
        } else {
            $this->assertNotContains("Otras", array_column($r, "nombre"));
        }
    }

    public function testCategoriaSinVentasNoAparece(): void {
        $this->categoriasConVentas(3);
        $this->seccion("Sin ventas jamás");
        $this->pedido("cancelado", self::D . "10 10:00:00", [[$this->parte("PX", $this->seccion("Solo cancelada"), "Q"), "PX", 99.0, 1]]);
        $r = $this->m->ventasPorCategoria(self::D . "01 00:00:00", self::D . "30 00:00:00");
        $this->assertCount(3, $r);
        $this->assertNotContains("Sin ventas jamás", array_column($r, "nombre"));
        $this->assertNotContains("Solo cancelada", array_column($r, "nombre"));
    }

    public function testElColorSeAsignaPorIdYNoCambiaAlLlegarUnaNueva(): void {
        $ids = $this->categoriasConVentas(4);
        $antes = $this->m->coloresDeCategorias();
        $nuevaId = $this->seccion("Llegó después");
        $despues = $this->m->coloresDeCategorias();
        foreach ($antes as $id => $c) $this->assertSame($c, $despues[$id], "la categoría $id cambió de color");
        $this->assertArrayHasKey($nuevaId, $despues);
        $this->assertSame((count($antes) % 6) + 1, $despues[$nuevaId], "toma el siguiente color libre");
        // el color de una categoría no depende de cuántas haya vendido ni de su lugar en la dona
        $this->pedido("entregado", self::D . "12 10:00:00", [[$this->parte("PN", $nuevaId, "Z"), "PN", 1000.0, 1]]);
        $r = $this->m->ventasPorCategoria(self::D . "01 00:00:00", self::D . "30 00:00:00");
        $this->assertSame("Llegó después", $r[0]["nombre"]);
        $this->assertSame($despues[$nuevaId], $r[0]["color"]);
        foreach (array_slice($r, 1) as $f) $this->assertSame($antes[$f["id"]], $f["color"]);
    }

    public function testPiezaDesactivadaYCategoriaRenombradaSiguenContando(): void {
        $s = $this->seccion("Nombre viejo");
        $p = $this->parte("Pieza que se retira", $s, "Marca Vieja");
        $this->pedido("entregado", self::D . "10 10:00:00", [[$p, "Pieza que se retira", 25.0, 2]]);
        $this->db->prepare("UPDATE partes SET activo=0 WHERE id=?")->execute([$p]);
        $this->db->prepare("UPDATE secciones SET nombre='Nombre nuevo', activo=0 WHERE id=?")->execute([$s]);
        $d = self::D . "01 00:00:00"; $h = self::D . "30 00:00:00";
        $this->assertSame(50.0, $this->m->resumen($d, $h)["ventas"]);
        $this->assertSame("Nombre nuevo", $this->m->ventasPorCategoria($d, $h)[0]["nombre"]);
        $this->assertSame("Pieza que se retira", $this->m->topPartes($d, $h)[0]["nombre"]);
        $this->assertSame("Marca Vieja", $this->m->topMarcas($d, $h)[0]["nombre"]);
        // y si la pieza se borra del todo, el pedido conserva su nombre y la venta cae en «Sin categoría»
        $this->db->prepare("UPDATE pedido_items SET parte_id=NULL WHERE parte_id=?")->execute([$p]);
        $this->assertSame("Sin categoría", $this->m->ventasPorCategoria($d, $h)[0]["nombre"]);
        $this->assertSame("Pieza que se retira", $this->m->topPartes($d, $h)[0]["nombre"]);
    }

    public function testTopFijoDe5ConMenosYUnaMarcaNuevaQueEntra(): void {
        $d = self::D . "01 00:00:00"; $h = self::D . "30 00:00:00";
        $this->categoriasConVentas(3); // 3 marcas (M1..M3), 3 piezas
        $this->assertCount(3, $this->m->topMarcas($d, $h), "menos de 5: salen las que hay");
        $this->categoriasConVentas(9, "11"); // 9 marcas más, 5 por tope
        $this->assertCount(5, $this->m->topMarcas($d, $h));
        $nueva = $this->parte("Pieza estrella", null, "Marca recién llegada");
        $this->pedido("entregado", self::D . "12 10:00:00", [[$nueva, "Pieza estrella", 5.0, 100]]);
        $this->assertSame("Marca recién llegada", $this->m->topMarcas($d, $h)[0]["nombre"], "entra sola al top");
        $this->assertSame("Pieza estrella", $this->m->topPartes($d, $h)[0]["nombre"]);
        $this->assertCount(5, $this->m->topPartes($d, $h));
    }

    public function testCategoriasSobrantesVanEnOtrasYSinCategoria(): void {
        $items = [];
        foreach (range(1, 6) as $n) $items[] = [$this->parte("P$n", $this->seccion("Cat $n"), "X"), "P$n", 10.0, 10 - $n]; // 90..40
        $items[] = [null, "Suelta", 10.0, 1];                                                                              // 10 sin categoría
        $this->pedido("entregado", self::D . "10 10:00:00", $items);
        $r = $this->m->ventasPorCategoria(self::D . "01 00:00:00", self::D . "30 00:00:00");
        $this->assertCount(5, $r, "la dona no pasa de 5 segmentos");
        $this->assertSame("Otras", $r[4]["nombre"]);
        $this->assertSame(round(40 + 50 + 10, 2), $r[4]["total"]);
        $this->assertSame(["Cat 1", "Cat 2", "Cat 3", "Cat 4"], array_column(array_slice($r, 0, 4), "nombre"));
        $this->assertEqualsWithDelta(0.0, array_sum(array_column($r, "total")) - (9 + 8 + 7 + 6 + 5 + 4) * 10 - 10, 0.001);
    }

    public function testTopPartesYMarcasConDesempateYLimite(): void {
        $h = $this->parte("Faro", null, "Honda");
        $k = $this->parte("Espejo", null, "Kia");
        $n = $this->parte("Bomba", null, null);
        $this->pedido("entregado", self::D . "10 10:00:00", [[$h, "Faro", 5.0, 4], [$k, "Espejo", 5.0, 4], [$n, "Bomba", 5.0, 2]]);
        $this->pedido("entregado", self::D . "11 10:00:00", [[$h, "Faro", 5.0, 1]]);
        $this->pedido("pendiente", self::D . "11 10:00:00", [[$n, "Bomba", 5.0, 50]]);
        $d = self::D . "01 00:00:00"; $h2 = self::D . "30 00:00:00";
        $this->assertSame([["nombre" => "Faro", "unidades" => 5], ["nombre" => "Espejo", "unidades" => 4], ["nombre" => "Bomba", "unidades" => 2]], array_map(fn($r) => array_diff_key($r, ["color" => 1]), $this->m->topPartes($d, $h2)));
        $this->assertSame([["nombre" => "Faro", "unidades" => 5]], array_map(fn($r) => array_diff_key($r, ["color" => 1]), $this->m->topPartes($d, $h2, 1)));
        $this->assertSame([["nombre" => "Honda", "unidades" => 5], ["nombre" => "Kia", "unidades" => 4], ["nombre" => "Sin marca", "unidades" => 2]], array_map(fn($r) => array_diff_key($r, ["color" => 1]), $this->m->topMarcas($d, $h2)));
    }

    public function testEmpateTotalSeResuelveSiempreIgual(): void {
        $ids = [];
        foreach (["Bravo", "Alfa", "Charlie"] as $x) $ids[] = [$this->parte($x, null, $x), $x, 5.0, 2];
        $this->pedido("entregado", self::D . "10 10:00:00", $ids);
        $r = $this->m->topPartes(self::D . "01 00:00:00", self::D . "30 00:00:00");
        $this->assertSame(["Alfa", "Bravo", "Charlie"], array_column($r, "nombre"));
    }

    // ── Estados ─────────────────────────────────────────────────────────────

    public function testPedidosPorEstadoDevuelveLosCuatroEnOrden(): void {
        $this->pedido("entregado", self::D . "10 10:00:00", [[null, "A", 1.0, 1]]);
        $this->pedido("entregado", self::D . "10 10:00:00", [[null, "A", 1.0, 1]]);
        $this->pedido("cancelado", self::D . "10 10:00:00", [[null, "A", 1.0, 1]]);
        $this->pedido("pendiente", "2031-05-10 10:00:00", [[null, "A", 1.0, 1]]); // fuera del rango
        $r = $this->m->pedidosPorEstado(self::D . "01 00:00:00", self::D . "30 00:00:00");
        $this->assertSame([["estado" => "pendiente", "pedidos" => 0], ["estado" => "procesando", "pedidos" => 0], ["estado" => "entregado", "pedidos" => 2], ["estado" => "cancelado", "pedidos" => 1]], $r);
    }

    // ── Datos de volumen (todo con la misma fecha: sin desempate se mezclarían) ──

    public function testConDatosDeVolumen(): void {
        $d = "2026-01-01 00:00:00"; $h = "2026-01-02 00:00:00";
        $antes = $this->m->resumen($d, $h);
        Volumen::crear($this->db);
        $r = $this->m->resumen($d, $h);
        $this->assertSame($antes["pedidos"] + 11, $r["pedidos"], "11 de los 45 pedidos de volumen están entregados");
        $this->assertEqualsWithDelta($antes["ventas"] + 110.0, $r["ventas"], 0.001);
        $serie = $this->m->ventasPorPeriodo($d, $h);
        $this->assertCount(1, $serie);
        $e = array_column($this->m->pedidosPorEstado($d, $h), "pedidos", "estado");
        $this->assertSame(11, $e["entregado"] - ($antes["pedidos"]) );
        $this->assertSame($this->m->topPartes($d, $h), $this->m->topPartes($d, $h), "mismo orden en dos llamadas");
        $this->assertLessThanOrEqual(5, count($this->m->ventasPorCategoria($d, $h)));
    }
}
