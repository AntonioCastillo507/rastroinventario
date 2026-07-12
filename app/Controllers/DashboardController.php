<?php
class DashboardController extends BaseController {
    public function index(): void {
        $this->requireAuth();
        $db             = Database::get();
        $pm             = new ParteModel();
        $pem            = new PedidoModel();
        $totalPartes    = $pm->countAll();
        $totalStockBajo = $pm->countStockBajo();
        $totalAutos     = (int)$db->query("SELECT COUNT(*) FROM autos WHERE activo=1")->fetchColumn();
        $pedidosPendientes = $pem->countPendientes();
        $totalVendido   = $pem->totalVendido();
        $comentPend     = (new ComentarioModel())->countPendientes();
        $logs           = (new LogModel())->recientes(10);
        $this->view("admin/dashboard",
            compact("totalPartes","totalStockBajo","totalAutos",
                    "pedidosPendientes","totalVendido","comentPend","logs"));
    }
}
