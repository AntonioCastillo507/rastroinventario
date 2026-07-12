<?php
class AutoController extends BaseController {
    private AutoModel $am;
    public function __construct() { $this->am = new AutoModel(); }

    public function index(): void   { $this->requirePermission("autos"); $autos=$this->am->findAll(); $this->view("admin/autos/index",compact("autos")); }
    public function crear(): void {
        $this->requirePermission("autos","puede_crear"); $error="";
        if ($this->isPost()) {
            CsrfToken::verify();
            $v=(new Validator())->required("marca",Sanitizer::post("marca"),"Marca")->required("modelo",Sanitizer::post("modelo"),"Modelo");
            if ($v->isValid()) { $this->am->create(["marca"=>Sanitizer::post("marca"),"modelo"=>Sanitizer::post("modelo"),"anio"=>Sanitizer::post("anio","int")]); $this->redirect(APP_URL."/admin/?mod=autos","Auto agregado."); }
            $error=$v->first();
        }
        $this->view("admin/autos/form",compact("error"));
    }
    public function editar(): void {
        $this->requirePermission("autos","puede_editar");
        $id=Sanitizer::get("id","int"); $auto=$this->am->findById($id);
        if (!$auto) $this->redirect(APP_URL."/admin/?mod=autos","No encontrado.","danger");
        $error="";
        if ($this->isPost()) { CsrfToken::verify(); $this->am->update($id,["marca"=>Sanitizer::post("marca"),"modelo"=>Sanitizer::post("modelo"),"anio"=>Sanitizer::post("anio","int")]); $this->redirect(APP_URL."/admin/?mod=autos","Auto actualizado."); }
        $this->view("admin/autos/form",compact("auto","error","id"));
    }
    public function eliminar(): void { $this->requirePermission("autos","puede_borrar"); $this->am->softDelete(Sanitizer::get("id","int")); $this->redirect(APP_URL."/admin/?mod=autos","Auto desactivado."); }
}
