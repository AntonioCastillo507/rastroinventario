<?php
class ParteController extends BaseController {
    private ParteModel   $pm;
    private AutoModel    $am;
    private SeccionModel $sm;
    public function __construct() { $this->pm=new ParteModel(); $this->am=new AutoModel(); $this->sm=new SeccionModel(); }

    public function index(): void {
        $this->requirePermission("partes");
        $q       = Sanitizer::get("q");
        $filtro  = Sanitizer::get("filtro") ?: "all";
        $orden   = Sanitizer::get("orden")  ?: "recientes";
        $seccion = Sanitizer::get("seccion","int"); // <-- NUEVO: filtro por sección
        $pag     = Paginador::crear(Sanitizer::get("page"), $this->pm->contarAdmin($q, $filtro, $seccion), Paginador::PANEL);
        $partes  = $this->pm->findAllWithDetails($q, $filtro, $orden, $seccion, $pag);
        $secciones = $this->sm->findActive(); // para el select de filtro
        $pagParams = ["mod"=>"partes", "q"=>$q, "filtro"=>$filtro==="all"?"":$filtro, "orden"=>$orden==="recientes"?"":$orden, "seccion"=>$seccion?:""];
        $this->view("admin/partes/index", compact("partes","q","filtro","orden","seccion","secciones","pag","pagParams"));
    }

    public function crear(): void {
        $this->requirePermission("partes","puede_crear");
        $autos=$this->am->findActive(); $secciones=$this->sm->findActive(); $error="";
        if ($this->isPost()) {
            CsrfToken::verify();
            $nombre=Sanitizer::post("nombre"); $precio=Sanitizer::post("precio","float"); $cant=Sanitizer::post("cantidad","int");
            $v=(new Validator())->required("nombre",$nombre,"Nombre")->numeric("precio",$precio,0.01)->numeric("cantidad",$cant,0);
            if ($v->isValid()) {
                $imgData=["thumbnail"=>null,"original"=>null];
                if (isset($_FILES["imagen"])&&($_FILES["imagen"]["error"]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
                    $r=ImageHelper::upload($_FILES["imagen"],"parte"); if ($r) $imgData=$r; else $error=ImageHelper::$error?:"Error al subir imagen.";
                }
                if ($error==="") {
                    $id=$this->pm->create(["nombre"=>$nombre,"descripcion"=>Sanitizer::post("descripcion"),"auto_id"=>Sanitizer::post("auto_id","int")?:null,"seccion_id"=>Sanitizer::post("seccion_id","int")?:null,"precio"=>$precio,"cantidad"=>$cant,"thumbnail"=>$imgData["thumbnail"],"imagen_grande"=>$imgData["original"],"creado_por"=>$_SESSION["user"]["id"]]);
                    if ($id) { $p=$this->pm->findById($id); $this->pm->updateFirma($id,DigitalSignature::signParte($p)); $this->redirect(APP_URL."/admin/?mod=partes","Parte creada. ✔"); }
                    $error="Error al guardar.";
                }
            } else $error=$v->first();
        }
        $this->view("admin/partes/form",compact("autos","secciones","error"));
    }
    public function editar(): void {
        $this->requirePermission("partes","puede_editar");
        $id=Sanitizer::get("id","int"); $parte=$this->pm->findById($id);
        if (!$parte) $this->redirect(APP_URL."/admin/?mod=partes","No encontrada.","danger");
        $autos=$this->am->findActive(); $secciones=$this->sm->findActive(); $error="";
        if ($this->isPost()) {
            CsrfToken::verify();
            $upd=["nombre"=>Sanitizer::post("nombre"),"descripcion"=>Sanitizer::post("descripcion"),"auto_id"=>Sanitizer::post("auto_id","int")?:null,"seccion_id"=>Sanitizer::post("seccion_id","int")?:null,"precio"=>Sanitizer::post("precio","float"),"cantidad"=>Sanitizer::post("cantidad","int")];
            $v=(new Validator())->required("nombre",$upd["nombre"],"Nombre")->numeric("precio",$upd["precio"],0.01)->numeric("cantidad",$upd["cantidad"],0);
            if ($v->isValid()) {
                $r=null;
                if (isset($_FILES["imagen"])&&($_FILES["imagen"]["error"]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
                    $r=ImageHelper::upload($_FILES["imagen"],"parte");
                    if (!$r) $error=ImageHelper::$error?:"Error al subir imagen."; // no se guarda nada si la imagen falla
                }
                if ($error==="") {
                    if ($r){$upd["thumbnail"]=$r["thumbnail"];$upd["imagen_grande"]=$r["original"];}
                    $this->pm->update($id,$upd); $nueva=$this->pm->findById($id);
                    if ($r) ImageHelper::delete($parte["imagen_grande"],$parte["thumbnail"]); // la anterior se borra ya guardada la nueva
                    $this->pm->updateFirma($id,DigitalSignature::signParte($nueva));
                    $this->redirect(APP_URL."/admin/?mod=partes","Parte actualizada.");
                }
            } else $error=$v->first();
            $parte=array_merge($parte,$upd); // conserva lo escrito
        }
        $this->view("admin/partes/form",compact("parte","autos","secciones","error","id"));
    }
    public function eliminar(): void {
        $this->requirePermission("partes","puede_borrar");
        if (!$this->isPost()) $this->redirect(APP_URL."/admin/?mod=partes"); // cambia datos: solo POST
        CsrfToken::verify();
        $id=Sanitizer::get("id","int");
        // Solo se desactiva: las fotos se quedan en disco porque los pedidos y las ventas viejas siguen refiriéndose a la pieza
        $this->pm->softDelete($id);
        $this->redirect(APP_URL."/admin/?mod=partes","Parte desactivada.");
    }
}
