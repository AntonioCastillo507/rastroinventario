<?php
class SeccionController extends BaseController {
    private SeccionModel $sm;
    public function __construct() { $this->sm = new SeccionModel(); }

    public function index(): void { $this->requirePermission("secciones"); $secciones=$this->sm->findAll(); $this->view("admin/secciones/index",compact("secciones")); }
    public function crear(): void {
        $this->requirePermission("secciones","puede_crear"); $error="";
        if ($this->isPost()) {
            CsrfToken::verify();
            $nombre=Sanitizer::post("nombre");
            $v=(new Validator())->required("nombre",$nombre,"Nombre");
            if ($v->isValid()) { $this->sm->create(["nombre"=>$nombre,"descripcion"=>Sanitizer::post("descripcion")]); $this->redirect(APP_URL."/admin/?mod=secciones","Sección creada."); }
            $error=$v->first();
        }
        $this->view("admin/secciones/form",compact("error"));
    }
    public function editar(): void {
        $this->requirePermission("secciones","puede_editar");
        $id=Sanitizer::get("id","int"); $sec=$this->sm->findById($id);
        if (!$sec) $this->redirect(APP_URL."/admin/?mod=secciones","No encontrada.","danger");
        $error="";
        if ($this->isPost()) { CsrfToken::verify(); $this->sm->update($id,["nombre"=>Sanitizer::post("nombre"),"descripcion"=>Sanitizer::post("descripcion")]); $this->redirect(APP_URL."/admin/?mod=secciones","Sección actualizada."); }
        $this->view("admin/secciones/form",compact("sec","error","id"));
    }
    public function eliminar(): void { $this->requirePermission("secciones","puede_borrar"); $this->sm->softDelete(Sanitizer::get("id","int")); $this->redirect(APP_URL."/admin/?mod=secciones","Sección desactivada."); }
}
