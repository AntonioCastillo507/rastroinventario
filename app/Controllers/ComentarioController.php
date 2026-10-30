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
        $puedeEditar=(new UserModel())->hasPermission((int)$_SESSION["user"]["id"],"comentarios","puede_editar");
        $destacadas=$this->cm->contarDestacadas();
        $pagParams=["mod"=>"comentarios"]+($orden!==ComentarioModel::ORDEN_POR_DEFECTO?["orden"=>$orden]:[]);
        $this->view("admin/comentarios/index",compact("comentarios","pag","pagParams","orden","puedeModerar","puedeEditar","destacadas"));
    }
    /** Ocultar o mostrar un comentario: solo el Administrador. Ya no hay forma de borrar un comentario desde el panel */
    public function toggle(): void  {
        $this->requirePermission("comentarios","puede_editar");
        if (!$this->isPost()) $this->redirect(APP_URL."/admin/?mod=comentarios"); // cambia datos: solo POST
        CsrfToken::verify();
        if (!(new UserModel())->esAdministrador((int)$_SESSION["user"]["id"])) $this->redirect(APP_URL."/admin/?mod=comentarios","Solo un Administrador puede ocultar o mostrar comentarios.","danger");
        $this->cm->togglePublicado(Sanitizer::get("id","int")); $this->redirect(APP_URL."/admin/?mod=comentarios","Estado actualizado.");
    }
    /** Destacar o quitar de la portada: por POST con token y permiso de editar comentarios */
    public function destacar(): void { $this->cambiarPortada(true); }
    public function quitarPortada(): void { $this->cambiarPortada(false); }
    private function cambiarPortada(bool $destacar): void {
        $this->requirePermission("comentarios","puede_editar");
        if (!$this->isPost()) $this->redirect(APP_URL."/admin/?mod=comentarios");
        CsrfToken::verify();
        $id=Sanitizer::get("id","int");
        if ($destacar) {
            $error=$this->cm->destacar($id);
            if ($error!=="") $this->redirect(APP_URL."/admin/?mod=comentarios",$error,"danger");
            $this->redirect(APP_URL."/admin/?mod=comentarios","Comentario destacado en la portada.");
        }
        $this->cm->quitarDestacado($id);
        $this->redirect(APP_URL."/admin/?mod=comentarios","Comentario quitado de la portada.");
    }
}
