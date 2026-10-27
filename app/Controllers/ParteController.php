<?php
class ParteController extends BaseController {
    private ParteModel   $pm;
    private AutoModel    $am;
    private SeccionModel $sm;
    public function __construct() { $this->pm=new ParteModel(); $this->am=new AutoModel(); $this->sm=new SeccionModel(); }

    /** Estados de stock del filtro: clave => texto de la lista y de la ficha */
    public const FILTROS_STOCK = ["all"=>"Todos","disponible"=>"Disponible (más de 5)","stock_bajo"=>"Stock bajo (1 a 5)","agotado"=>"Agotado"];
    public const ORDENES = ["recientes"=>"Más recientes","precio_asc"=>"Precio ↑","precio_desc"=>"Precio ↓","stock_asc"=>"Stock ↑","stock_desc"=>"Stock ↓","nombre"=>"Nombre A–Z","mas_vendidas"=>"Más vendidas"];

    /** Fichas de los filtros activos: [texto, URL que quita solo ese filtro]; lo demás se conserva */
    public static function fichas(string $q, string $filtro, string $orden, int $seccion, array $secciones, string $base): array {
        $activos = ["q"=>$q, "seccion"=>$seccion?:"", "filtro"=>$filtro==="all"?"":$filtro, "orden"=>$orden==="recientes"?"":$orden];
        $nombres = [];
        foreach ($secciones as $s) $nombres[(int)$s["id"]] = (string)$s["nombre"];
        $textos = [
            "q"=>$q!=="" ? "Búsqueda: «".$q."»" : "",
            "seccion"=>$seccion ? "Sección: ".($nombres[$seccion] ?? "#".$seccion) : "",
            "filtro"=>$filtro!=="all" ? (self::FILTROS_STOCK[$filtro] ?? "Con stock") : "",
            "orden"=>$orden!=="recientes" ? "Orden: ".self::ORDENES[$orden] : "",
        ];
        $fichas = [];
        foreach ($textos as $k => $t) {
            if ($t === "") continue;
            $resto = array_filter(array_merge(["mod"=>"partes"], array_diff_key($activos, [$k=>1])), fn($v) => $v !== "" && $v !== null);
            $fichas[] = [$t, $base."?".http_build_query($resto)];
        }
        return $fichas;
    }

    public function index(): void {
        $this->requirePermission("partes");
        $q       = Sanitizer::get("q");
        $filtro  = Sanitizer::get("filtro") ?: "all";
        if (!isset(self::FILTROS_STOCK[$filtro]) && $filtro !== "con_stock") $filtro = "all"; // valor desconocido: sin filtro
        $orden   = Sanitizer::get("orden")  ?: "recientes";
        if (!isset(self::ORDENES[$orden])) $orden = "recientes";
        $seccion = Sanitizer::get("seccion","int"); // filtro por sección
        $pag     = Paginador::crear(Sanitizer::get("page"), $this->pm->contarAdmin($q, $filtro, $seccion), Paginador::PANEL);
        $partes  = $this->pm->findAllWithDetails($q, $filtro, $orden, $seccion, $pag);
        $secciones = $this->sm->findActive(); // para el select de filtro
        $pagParams = ["mod"=>"partes", "q"=>$q, "filtro"=>$filtro==="all"?"":$filtro, "orden"=>$orden==="recientes"?"":$orden, "seccion"=>$seccion?:""];
        $fichas  = self::fichas($q, $filtro, $orden, $seccion, $secciones, APP_URL."/admin/");
        $this->view("admin/partes/index", compact("partes","q","filtro","orden","seccion","secciones","pag","pagParams","fichas"));
    }

    /** Mensaje y tipo del aviso tras guardar: si la foto trae avisos (no bloqueos) se anotan y el aviso pasa a amarillo */
    private static function mensajeConAvisos(string $msg, array $avisos): array {
        return $avisos ? [$msg." Aviso de la foto: ".implode(" ",$avisos), "warning"] : [$msg, "success"];
    }
    public function crear(): void {
        $this->requirePermission("partes","puede_crear");
        $autos=$this->am->findActive(); $secciones=$this->sm->findActive(); $error=""; $viejo=[];
        if ($this->isPost()) {
            CsrfToken::verify();
            // Con un error, el formulario vuelve con lo escrito (la foto hay que elegirla otra vez: un navegador no deja rellenarla)
            $viejo=array_intersect_key($_POST,array_flip(["nombre","descripcion","auto_id","seccion_id","precio","cantidad"]));
            $nombre=Sanitizer::post("nombre"); $precio=Sanitizer::post("precio","float"); $cant=Sanitizer::post("cantidad","int");
            $v=(new Validator())->required("nombre",$nombre,"Nombre")->numeric("precio",$precio,0.01)->numeric("cantidad",$cant,0);
            if ($v->isValid()) {
                $imgData=["thumbnail"=>null,"original"=>null];
                if (isset($_FILES["imagen"])&&($_FILES["imagen"]["error"]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
                    $r=ImageHelper::upload($_FILES["imagen"],"parte"); if ($r) $imgData=$r; else $error=ImageHelper::$error?:"Error al subir imagen.";
                }
                if ($error==="") {
                    $id=$this->pm->create(["nombre"=>$nombre,"descripcion"=>Sanitizer::post("descripcion"),"auto_id"=>Sanitizer::post("auto_id","int")?:null,"seccion_id"=>Sanitizer::post("seccion_id","int")?:null,"precio"=>$precio,"cantidad"=>$cant,"thumbnail"=>$imgData["thumbnail"],"imagen_grande"=>$imgData["original"],"creado_por"=>$_SESSION["user"]["id"]]);
                    if ($id) { $p=$this->pm->findById($id); $this->pm->updateFirma($id,DigitalSignature::signParte($p)); $this->redirect(APP_URL."/admin/?mod=partes",...self::mensajeConAvisos("Parte creada. ✔",$imgData["avisos"]??[])); }
                    $error="Error al guardar.";
                }
            } else $error=$v->first();
        }
        $this->view("admin/partes/form",compact("autos","secciones","error","viejo"));
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
                    $this->redirect(APP_URL."/admin/?mod=partes",...self::mensajeConAvisos("Parte actualizada.",$r["avisos"]??[]));
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
