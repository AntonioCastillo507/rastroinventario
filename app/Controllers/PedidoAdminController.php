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

        $pedido = $this->pm->findWithItems($id);
        $anterior = $pedido["estado"] ?? "";

        $this->pm->updateEstado($id,$estado);

        // ── Auto-registrar venta cuando se marca como ENTREGADO ──
        $msg = "Estado actualizado.";
        if ($estado === "entregado" && $anterior !== "entregado" && $pedido) {
            $vm = new VentaModel();
            $registradas = 0;
            foreach ($pedido["items"] as $item) {
                if ($item["parte_id"]) {
                    $vm->registrar([
                        "parte_id"    => $item["parte_id"],
                        "cantidad"    => $item["cantidad"],
                        "precio_venta"=> $item["precio_unitario"],
                        "usuario_id"  => $pedido["usuario_id"],
                        "fecha"       => date("Y-m-d H:i:s"),
                    ]);
                    $registradas++;
                }
            }
            if ($registradas > 0) $msg = "Pedido entregado. $registradas venta(s) registrada(s) automáticamente. ✔";
        }

        $this->redirect(APP_URL."/admin/?mod=pedidos&action=detalle&id=".$id,$msg);
    }
}
