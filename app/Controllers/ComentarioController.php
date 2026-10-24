<?php
class ComentarioController extends BaseController {
    private ComentarioModel $cm;
    public function __construct() { $this->cm=new ComentarioModel(); }
    public function index(): void   {
        $this->requirePermission("comentarios");
        $pag=Paginador::crear(Sanitizer::get("page"), $this->cm->contar(), Paginador::PANEL);
        $comentarios=$this->cm->findAllWithParte($pag);
        $pagParams=["mod"=>"comentarios"];
        $this->view("admin/comentarios/index",compact("comentarios","pag","pagParams"));
    }
    public function toggle(): void  {
        $this->requirePermission("comentarios","puede_editar");
        if (!$this->isPost()) $this->redirect(APP_URL."/admin/?mod=comentarios"); // cambia datos: solo POST
        CsrfToken::verify();
        $this->cm->togglePublicado(Sanitizer::get("id","int")); $this->redirect(APP_URL."/admin/?mod=comentarios","Estado actualizado.");
    }
    public function eliminar(): void{
        $this->requirePermission("comentarios","puede_borrar");
        if (!$this->isPost()) $this->redirect(APP_URL."/admin/?mod=comentarios"); // cambia datos: solo POST
        CsrfToken::verify();
        $this->cm->hardDelete(Sanitizer::get("id","int")); $this->redirect(APP_URL."/admin/?mod=comentarios","Comentario eliminado.");
    }
}
