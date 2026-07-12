<?php
/**
 * VentaController — Registro de ventas con firma HMAC-SHA256.
 * RNF-06: Cada venta es firmada digitalmente al registrarla.
 */
class VentaController extends BaseController {
    private VentaModel $vm;
    private ParteModel $pm;
    public function __construct() { $this->vm=new VentaModel(); $this->pm=new ParteModel(); }

    public function index(): void {
        $this->requirePermission("ventas");
        $ventas = $this->vm->findAllWithDetails();
        foreach ($ventas as &$v) $v["firma_ok"] = DigitalSignature::verifyVenta($v);
        $this->view("admin/ventas/index",compact("ventas"));
    }
    public function crear(): void {
        $this->requirePermission("ventas","puede_crear");
        $partes = $this->pm->findPublic(); $error = "";
        if ($this->isPost()) {
            CsrfToken::verify();
            $parteId  = Sanitizer::post("parte_id","int");
            $cantidad = Sanitizer::post("cantidad","int");
            $precio   = Sanitizer::post("precio_venta","float");
            $v = (new Validator())
                ->required("parte_id",$parteId,"Parte")
                ->numeric("cantidad",$cantidad,1)
                ->numeric("precio_venta",$precio,0.01);
            if ($v->isValid()) {
                $parte = $this->pm->findById($parteId);
                if (!$parte) { $error = "Parte no encontrada."; }
                elseif ($parte["cantidad"] < $cantidad) { $error = "Stock insuficiente. Disponible: " . $parte["cantidad"] . " unidades."; }
                else {
                    if ($this->pm->decrementarStock($parteId,$cantidad)) {
                        $this->vm->registrar(["parte_id"=>$parteId,"cantidad"=>$cantidad,"precio_venta"=>$precio,"usuario_id"=>$_SESSION["user"]["id"],"fecha"=>date("Y-m-d H:i:s")]);
                        $this->redirect(APP_URL."/admin/?mod=ventas","Venta registrada y firmada digitalmente. ✔");
                    } else $error = "Error al actualizar el inventario.";
                }
            } else $error = $v->first();
        }
        $this->view("admin/ventas/form",compact("partes","error"));
    }
}
