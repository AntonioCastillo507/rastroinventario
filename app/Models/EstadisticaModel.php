<?php
/**
 * EstadisticaModel — consultas de agregación del dashboard.
 * Una venta es un pedido en estado «entregado»; su monto es el subtotal (neto, sin ITBMS)
 * y su fecha la de creación del pedido. Todas las consultas van parametrizadas y con desempate fijo.
 */
class EstadisticaModel extends BaseModel {
    protected string $table = "pedidos";

    public const RANGOS  = [7, 30, 90, 365];
    public const ESTADOS = ["pendiente", "procesando", "entregado", "cancelado"];
    private const MAX_SEGMENTOS = 5;

    /** Rango pedido (cualquier valor raro cae en 30 días) */
    public static function rangoValido(mixed $dias, int $defecto = 30): int {
        $d = (int)$dias;
        return in_array($d, self::RANGOS, true) ? $d : $defecto;
    }

    /**
     * Periodo actual [desde, hasta) y el anterior de igual duración [anteriorDesde, desde).
     * @return array{dias:int,desde:string,hasta:string,anteriorDesde:string}
     */
    public static function periodo(int $dias, ?DateTimeImmutable $hoy = null): array {
        $hoy   = ($hoy ?? new DateTimeImmutable("today"))->setTime(0, 0);
        $desde = $hoy->modify("-" . ($dias - 1) . " days");
        return [
            "dias"          => $dias,
            "desde"         => $desde->format("Y-m-d H:i:s"),
            "hasta"         => $hoy->modify("+1 day")->format("Y-m-d H:i:s"),
            "anteriorDesde" => $desde->modify("-{$dias} days")->format("Y-m-d H:i:s"),
        ];
    }

    /** Ventas, pedidos entregados y ticket promedio de [desde, hasta) */
    public function resumen(string $desde, string $hasta): array {
        $r = $this->query(
            "SELECT COALESCE(SUM(subtotal), 0) AS ventas, COUNT(*) AS pedidos
             FROM pedidos WHERE estado='entregado' AND creado_en >= ? AND creado_en < ?",
            [$desde, $hasta])[0];
        $ventas  = round((float)$r["ventas"], 2);
        $pedidos = (int)$r["pedidos"];
        return ["ventas" => $ventas, "pedidos" => $pedidos, "ticket" => $pedidos ? round($ventas / $pedidos, 2) : 0.0];
    }

    /** Variación porcentual; null si el periodo anterior es cero (no hay base) */
    public static function variacion(float $actual, float $anterior): ?float {
        return $anterior > 0 ? round(($actual - $anterior) / $anterior * 100, 1) : null;
    }

    /**
     * Serie de ventas con todos los días (o meses) del rango, también los que no vendieron.
     * @return list<array{clave:string,total:float,pedidos:int}>
     */
    public function ventasPorPeriodo(string $desde, string $hasta, string $agrupar = "dia"): array {
        $fmt  = $agrupar === "mes" ? "%Y-%m" : "%Y-%m-%d";
        $rows = $this->query(
            "SELECT DATE_FORMAT(creado_en, '$fmt') AS clave, SUM(subtotal) AS total, COUNT(*) AS pedidos
             FROM pedidos WHERE estado='entregado' AND creado_en >= ? AND creado_en < ?
             GROUP BY clave ORDER BY clave ASC",
            [$desde, $hasta]);
        $por = [];
        foreach ($rows as $r) $por[$r["clave"]] = $r;

        $serie = [];
        $paso  = $agrupar === "mes" ? "+1 month" : "+1 day";
        $f     = $agrupar === "mes" ? "Y-m" : "Y-m-d";
        $cur   = new DateTimeImmutable($desde);
        if ($agrupar === "mes") $cur = $cur->modify("first day of this month");
        $fin   = new DateTimeImmutable($hasta);
        while ($cur < $fin) {
            $k       = $cur->format($f);
            $serie[] = ["clave" => $k, "total" => round((float)($por[$k]["total"] ?? 0), 2), "pedidos" => (int)($por[$k]["pedidos"] ?? 0)];
            $cur     = $cur->modify($paso);
        }
        return $serie;
    }

    public const COLORES = 6;

    /**
     * Color estable de cada categoría: su lugar entre todas las secciones por id (activas o no),
     * módulo 6. Una sección nueva toma el siguiente color libre y las demás no cambian.
     * @return array<int,int> id de sección => color 1..6
     */
    public function coloresDeCategorias(): array {
        $ids = $this->db->query("SELECT id FROM secciones ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ($ids as $pos => $id) $out[(int)$id] = ($pos % self::COLORES) + 1;
        return $out;
    }

    /**
     * Ventas por categoría leídas de los pedidos entregados: una pieza desactivada o una sección
     * renombrada siguen contando. Solo salen las que vendieron. Con más de 5 segmentos quedan las
     * 4 mayores y el resto se junta en «Otras» (color 0 = gris, igual que «Sin categoría»).
     * @return list<array{id:?int,nombre:string,total:float,color:int}>
     */
    public function ventasPorCategoria(string $desde, string $hasta): array {
        $rows = $this->query(
            "SELECT s.id AS id, COALESCE(s.nombre, 'Sin categoría') AS nombre, SUM(i.cantidad * i.precio_unitario) AS total
             FROM pedido_items i
             JOIN pedidos p ON p.id = i.pedido_id
             LEFT JOIN partes pa ON pa.id = i.parte_id
             LEFT JOIN secciones s ON s.id = pa.seccion_id
             WHERE p.estado='entregado' AND p.creado_en >= ? AND p.creado_en < ?
             GROUP BY s.id, s.nombre
             ORDER BY total DESC, nombre ASC, s.id ASC",
            [$desde, $hasta]);
        $colores = $this->coloresDeCategorias();
        $out = array_map(fn($r) => [
            "id"     => $r["id"] === null ? null : (int)$r["id"],
            "nombre" => $r["nombre"],
            "total"  => round((float)$r["total"], 2),
            "color"  => $r["id"] === null ? 0 : ($colores[(int)$r["id"]] ?? 0),
        ], $rows);
        if (count($out) > self::MAX_SEGMENTOS) {
            $resto = array_splice($out, self::MAX_SEGMENTOS - 1);
            $out[] = ["id" => null, "nombre" => "Otras", "total" => round(array_sum(array_column($resto, "total")), 2), "color" => 0];
        }
        return $out;
    }

    /** @return list<array{nombre:string,unidades:int}> */
    public function topPartes(string $desde, string $hasta, int $limite = 5): array {
        return $this->unidades(
            "SELECT i.parte_nombre AS nombre, SUM(i.cantidad) AS unidades
             FROM pedido_items i JOIN pedidos p ON p.id = i.pedido_id
             WHERE p.estado='entregado' AND p.creado_en >= ? AND p.creado_en < ?
             GROUP BY i.parte_nombre ORDER BY unidades DESC, nombre ASC LIMIT " . max(1, $limite),
            $desde, $hasta);
    }

    /** @return list<array{nombre:string,unidades:int}> */
    public function topMarcas(string $desde, string $hasta, int $limite = 5): array {
        return $this->unidades(
            "SELECT COALESCE(a.marca, 'Sin marca') AS nombre, SUM(i.cantidad) AS unidades
             FROM pedido_items i
             JOIN pedidos p ON p.id = i.pedido_id
             LEFT JOIN partes pa ON pa.id = i.parte_id
             LEFT JOIN autos a ON a.id = pa.auto_id
             WHERE p.estado='entregado' AND p.creado_en >= ? AND p.creado_en < ?
             GROUP BY nombre ORDER BY unidades DESC, nombre ASC LIMIT " . max(1, $limite),
            $desde, $hasta);
    }

    private function unidades(string $sql, string $desde, string $hasta): array {
        return array_map(fn($r) => ["nombre" => $r["nombre"], "unidades" => (int)$r["unidades"]], $this->query($sql, [$desde, $hasta]));
    }

    /** Pedidos creados en el rango por estado; siempre los cuatro estados, en orden fijo */
    public function pedidosPorEstado(string $desde, string $hasta): array {
        $rows = $this->query(
            "SELECT estado, COUNT(*) AS n FROM pedidos
             WHERE creado_en >= ? AND creado_en < ? GROUP BY estado",
            [$desde, $hasta]);
        $por = array_column($rows, "n", "estado");
        return array_map(fn($e) => ["estado" => $e, "pedidos" => (int)($por[$e] ?? 0)], self::ESTADOS);
    }
}
