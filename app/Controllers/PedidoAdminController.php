<?php
class PedidoAdminController extends BaseController {
    private PedidoModel $pm;
    public function __construct() { $this->pm=new PedidoModel(); }

    public function index(): void {
        $this->requirePermission("pedidos");
        $pedidos=$this->pm->findAllWithItems();
        $this->view("admin/pedidos/index",compact("pedidos"));
    }
    public function detalle(): void {
        $this->requirePermission("pedidos");
        $id=$id=Sanitizer::get("id","int");
        $pedido=$this->pm->findWithItems($id);
        if (!$pedido) $this->redirect(APP_URL."/admin/?mod=pedidos","Pedido no encontrado.","danger");
        $this->view("admin/pedidos/detalle",compact("pedido"));
    }
    public function cambiarEstado(): void {
        $this->requirePermission("pedidos","puede_editar");
        CsrfToken::verify();
        $id     = Sanitizer::get("id","int");
        $estado = Sanitizer::post("estado");
        $validos = ["pendiente","procesando","entregado","cancelado"];
        if (!in_array($estado,$validos)) $this->redirect(APP_URL."/admin/?mod=pedidos","Estado inválido.","danger");

        // Stock y ventas se ajustan dentro del modelo, en una sola transacción
        $r = $this->pm->cambiarEstado($id,$estado);
        $this->redirect(APP_URL."/admin/?mod=pedidos&action=detalle&id=".$id,$r["msg"],$r["ok"]?"success":"danger");
    }
}
