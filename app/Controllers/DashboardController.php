<?php
class DashboardController extends BaseController {
    private const MESES  = ["ene", "feb", "mar", "abr", "may", "jun", "jul", "ago", "sep", "oct", "nov", "dic"];
    private const ESTADOS = ["pendiente" => "Pendiente", "procesando" => "Procesando", "entregado" => "Entregado", "cancelado" => "Cancelado"];

    private static function fecha(string $clave, string $agrupar): string {
        $t = strtotime($clave . ($agrupar === "mes" ? "-01" : ""));
        return $agrupar === "mes" ? self::MESES[(int)date("n", $t) - 1] . " " . date("Y", $t) : (int)date("j", $t) . " " . self::MESES[(int)date("n", $t) - 1];
    }

    /** Qué widgets ve quien entra: salen de los permisos de sus roles, no del nombre del rol */
    public static function widgets(array $modulosVisibles): array {
        $v = fn(string $m) => in_array($m, $modulosVisibles, true);
        return ["ventas" => $v("ventas"), "pedidos" => $v("pedidos"), "partes" => $v("partes"), "comentarios" => $v("comentarios")];
    }

    public function index(): void {
        $this->requireAuth();
        $uid  = (int)$_SESSION["user"]["id"];
        $um   = new UserModel();
        $w    = self::widgets($um->modulosVisibles($uid));
        $botones = [
            "venta" => $um->hasPermission($uid, "ventas", "puede_crear"),
            "parte" => $um->hasPermission($uid, "partes", "puede_crear"),
        ];

        $dias = EstadisticaModel::rangoValido($_GET["r"] ?? null, EstadisticaModel::rangoValido(Marca::get("dashboard.rango_defecto", 365), 365));
        $em   = new EstadisticaModel();
        $todo = $dias === EstadisticaModel::TODO;
        $p    = $todo ? EstadisticaModel::periodoTodo($em->primeraVenta()) : EstadisticaModel::periodo($dias);
        $d    = ["w" => $w, "botones" => $botones, "dias" => $dias, "todo" => $todo, "rangos" => EstadisticaModel::RANGOS];
        $d["desdeTexto"] = self::fecha(substr($p["desde"], 0, 10), "dia") . " " . date("Y", strtotime($p["desde"]));
        $d["hastaTexto"] = self::fecha(date("Y-m-d", strtotime($p["hasta"] . " -1 day")), "dia") . " " . date("Y", strtotime($p["hasta"] . " -1 day"));

        $d["kpis"] = [];
        if ($w["ventas"]) {
            $act = $em->resumen($p["desde"], $p["hasta"]);
            $ant = $todo ? null : $em->resumen($p["anteriorDesde"], $p["desde"]);
            $d["kpis"][] = ["etiqueta" => "Ventas", "valor" => $act["ventas"], "dinero" => true, "var" => $ant ? EstadisticaModel::variacion($act["ventas"], $ant["ventas"]) : null, "nota" => $act["pedidos"] ? "Pedidos entregados" : "Sin ventas en este periodo"];
            $d["kpis"][] = ["etiqueta" => "Pedidos entregados", "valor" => $act["pedidos"], "dinero" => false, "var" => $ant ? EstadisticaModel::variacion($act["pedidos"], $ant["pedidos"]) : null, "nota" => $act["pedidos"] ? "Cerrados en el periodo" : "Sin pedidos entregados"];
            $d["kpis"][] = ["etiqueta" => "Ticket promedio", "valor" => $act["ticket"], "dinero" => true, "var" => $ant ? EstadisticaModel::variacion($act["ticket"], $ant["ticket"]) : null, "nota" => "Lo que gasta cada cliente por pedido"];

            $agr   = ($dias === 365 || $todo) ? "mes" : "dia";
            $serie = $em->ventasPorPeriodo($p["desde"], $p["hasta"], $agr);
            $prev  = $todo ? [] : $em->ventasPorPeriodo($p["anteriorDesde"], $p["desde"], $agr);
            $d["serie"] = [
                "labels"   => array_map(fn($s) => self::fecha($s["clave"], $agr), $serie),
                "actual"   => array_column($serie, "total"),
                "anterior" => array_slice(array_column($prev, "total"), 0, count($serie)),
                "total"    => $act["ventas"], "totalAnterior" => $ant ? $ant["ventas"] : null,
            ];
            $d["categorias"] = $em->ventasPorCategoria($p["desde"], $p["hasta"]);
            $d["partes"]     = $em->topPartes($p["desde"], $p["hasta"]);
            $d["marcas"]     = $em->topMarcas($p["desde"], $p["hasta"]);
        }
        if ($w["pedidos"]) {
            $d["estados"] = array_map(fn($e) => ["nombre" => self::ESTADOS[$e["estado"]], "n" => $e["pedidos"], "estado" => $e["estado"]], $em->pedidosPorEstado($p["desde"], $p["hasta"]));
            $d["porAtender"]       = $em->porAtender(5);
            $d["totalPorAtender"]  = $em->contarPorAtender();
        }
        if ($w["partes"]) {
            $pm = new ParteModel();
            $d["kpis"][]       = ["etiqueta" => "Piezas activas", "valor" => $pm->countAll(), "dinero" => false, "var" => null, "nota" => $pm->countStockBajo() . " con poco stock"];
            $d["stockBajo"]    = $em->stockBajo(5);
            $d["totalStockBajo"] = $pm->countStockBajo();
            $d["totalAgotadas"]  = $em->contarAgotadas();
        }
        if ($w["comentarios"]) {
            $d["comentarios"]      = $em->comentariosRecientes(5);
            $d["comentariosSemana"] = $em->contarComentariosDesde(date("Y-m-d H:i:s", strtotime("-7 days")));
        }
        $this->view("admin/dashboard", $d);
    }
}
