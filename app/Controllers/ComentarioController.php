<?php
class ComentarioController extends BaseController {
    private ComentarioModel $cm;
    public function __construct() { $this->cm=new ComentarioModel(); }
    public function index(): void   { $this->requirePermission("comentarios"); $comentarios=$this->cm->findAllWithParte(); $this->view("admin/comentarios/index",compact("comentarios")); }
    public function toggle(): void  { $this->requirePermission("comentarios","puede_editar"); $this->cm->togglePublicado(Sanitizer::get("id","int")); $this->redirect(APP_URL."/admin/?mod=comentarios","Estado actualizado."); }
    public function eliminar(): void{ $this->requirePermission("comentarios","puede_borrar"); $this->cm->hardDelete(Sanitizer::get("id","int")); $this->redirect(APP_URL."/admin/?mod=comentarios","Comentario eliminado."); }
}
