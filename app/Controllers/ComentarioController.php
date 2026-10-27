<?php
class ComentarioController extends BaseController {
    private ComentarioModel $cm;
    public function __construct() { $this->cm=new ComentarioModel(); }
    public function index(): void   {
        $this->requirePermission("comentarios");
        $orden=ComentarioModel::ordenValido(Sanitizer::get("orden"));
        $pag=Paginador::crear(Sanitizer::get("page"), $this->cm->contar(), Paginador::PANEL);
        $comentarios=$this->cm->findAllWithParte($pag,$orden);
        $puedeModerar=(new UserModel())->esAdministrador((int)$_SESSION["user"]["id"]);
        $pagParams=["mod"=>"comentarios"]+($orden!==ComentarioModel::ORDEN_POR_DEFECTO?["orden"=>$orden]:[]);
        $this->view("admin/comentarios/index",compact("comentarios","pag","pagParams","orden","puedeModerar"));
    }
    /** Ocultar o mostrar un comentario: solo el Administrador. Ya no hay forma de borrar un comentario desde el panel */
    public function toggle(): void  {
        $this->requirePermission("comentarios","puede_editar");
        if (!$this->isPost()) $this->redirect(APP_URL."/admin/?mod=comentarios"); // cambia datos: solo POST
        CsrfToken::verify();
        if (!(new UserModel())->esAdministrador((int)$_SESSION["user"]["id"])) $this->redirect(APP_URL."/admin/?mod=comentarios","Solo un Administrador puede ocultar o mostrar comentarios.","danger");
        $this->cm->togglePublicado(Sanitizer::get("id","int")); $this->redirect(APP_URL."/admin/?mod=comentarios","Estado actualizado.");
    }
}
