<?php
/** SeguridadController — bitácora de accesos (solo quien tenga el permiso «seguridad», el Administrador) */
class SeguridadController extends BaseController {
    public function index(): void {
        $this->requirePermission("seguridad");
        $lm   = new LogModel();
        $pag  = Paginador::crear(Sanitizer::get("page"), $lm->contar(), Paginador::PANEL);
        $logs = $lm->pagina($pag);
        $pagParams = ["mod" => "seguridad"];
        $this->view("admin/seguridad/index", compact("logs", "pag", "pagParams"));
    }
}
