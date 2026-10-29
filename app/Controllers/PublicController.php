<?php
class PublicController extends BaseController {
    private ParteModel       $pam;
    private ComentarioModel  $cm;
    private PedidoController $pc;
    public function __construct(){ $this->pam=new ParteModel(); $this->cm=new ComentarioModel(); $this->pc=new PedidoController(); }

    private function sharedData(): array {
        $db=Database::get();
        $secciones=$db->query("SELECT s.*,COUNT(p.id) AS total FROM secciones s LEFT JOIN partes p ON p.seccion_id=s.id AND p.activo=1 AND p.cantidad>0 WHERE s.activo=1 GROUP BY s.id ORDER BY s.nombre")->fetchAll();
        $stats=["partes"=>(int)$db->query("SELECT COUNT(*) FROM partes WHERE activo=1 AND cantidad>0")->fetchColumn(),"marcas"=>(int)$db->query("SELECT COUNT(DISTINCT marca) FROM autos WHERE activo=1")->fetchColumn(),"secciones"=>count($secciones)];
        return compact("secciones","stats");
    }

    public function home(): void {
        $ver=$_GET["ver"]??""; $q=Sanitizer::get("q"); $secId=Sanitizer::get("seccion","int");
        $marca=Sanitizer::get("marca"); $pMin=(float)(Sanitizer::get("precio_min")?:0); $pMax=(float)(Sanitizer::get("precio_max")?:0); $orden=Sanitizer::get("orden")?:"recientes";
        $shared=$this->sharedData();
        if ($ver==="catalogo"||$q||$secId||$marca||$pMin||$pMax){
            $pag=Paginador::crear(Sanitizer::get("page"),$this->pam->contarPublic($q,$secId,$marca,$pMin,$pMax),Paginador::CATALOGO);
            $partes=$this->pam->findPublic($q,$secId,0,$marca,$pMin,$pMax,$orden,$pag);
            $marcas=$this->pam->getMarcas(); [$precioMin,$precioMax]=$this->pam->getPrecioRango();
            // Filtros que viajan en los enlaces de página (lo vacío o por defecto se omite)
            $pagParams=["ver"=>"catalogo","q"=>$q,"seccion"=>$secId?:"","marca"=>$marca,"precio_min"=>$pMin>0?$pMin:"","precio_max"=>$pMax>0?$pMax:"","orden"=>$orden==="recientes"?"":$orden];
            $this->view("public/catalogo",array_merge($shared,compact("partes","q","secId","marca","pMin","pMax","orden","marcas","precioMin","precioMax","pag","pagParams")));
        } else {
            $recientes=$this->pam->findPublic("",0,4,"",0,0,"recientes");
            $marcasConteo=(new MarcaAutoModel())->conLogoYPiezas();
            $this->view("public/home",array_merge($shared,compact("recientes","marcasConteo","q")));
        }
    }

    public function nosotros(): void { $this->view("public/nosotros"); }
    public function contacto(): void { $this->view("public/contacto"); }

    public function detalle(): void {
        $id=Sanitizer::get("id","int"); $parte=$this->pam->findById($id);
        if (!$parte||!$parte["activo"]) PaginaError::mostrar(404, "Esa pieza no existe o ya no está disponible. Busca otra en el catálogo.", null, "Pieza no encontrada");
        $comentarios=$this->cm->findByParte($id); $promedio=$this->cm->promedioRating($id); $totalRating=$this->cm->countRating($id);
        $isCliente=!empty($_SESSION["user"])&&in_array("Cliente",$_SESSION["user_roles"]??[]);
        $error="";
        if ($this->isPost()){
            CsrfToken::verify();
            $nombre=Sanitizer::post("nombre_visitante"); $texto=Sanitizer::post("comentario"); $rating=(int)($_POST["rating"]??0);
            $v=(new Validator())->required("nombre",$nombre,"Nombre")->required("comentario",$texto,"Comentario")->minLen("comentario",$texto,5);
            if ($v->isValid()){
                $rat=($isCliente&&$rating>=1&&$rating<=5)?$rating:null;
                // Publicado inmediatamente — libertad de expresión
                $this->cm->create(["parte_id"=>$id,"nombre_visitante"=>$nombre,"comentario"=>$texto,"publicado"=>1,"auto_pub"=>1,"rating"=>$rat]);
                $this->flash("¡Reseña publicada!","success");
                $this->redirect(APP_URL."/?action=detalle&id=".$id);
            } $error=$v->first();
        }
        $relacionadas=$this->pam->findRelacionadas($id,$parte["seccion_id"]?(int)$parte["seccion_id"]:null,$parte["auto_id"]?(int)$parte["auto_id"]:null,4);
        $this->view("public/detalle",compact("parte","comentarios","promedio","totalRating","isCliente","error","relacionadas"));
    }

    public function comprar():         void { $this->pc->formCompra(); }
    public function procesarCompra():  void { $this->pc->procesarCompra(); }
    public function confirmarCompra(): void { $this->pc->confirmarCompra(); }
    public function exitosa():         void { $this->pc->exitosa(); }
    public function misPedidos():      void { $this->pc->misPedidos(); }
    public function miPerfil():        void { $this->pc->miPerfil(); }
    public function carrito():         void { $this->pc->verCarrito(); }
    public function datosCarrito():    void { $this->pc->datosCarrito(); }
    public function restaurarCarrito():void { $this->pc->restaurarCarrito(); }
    public function agregarCarrito():  void { $this->pc->agregarCarrito(); }
    public function quitarCarrito():   void { $this->pc->quitarCarrito(); }
    public function actualizarCarrito():void { $this->pc->actualizarCarrito(); }
    public function procesarCarrito(): void { $this->pc->procesarCarrito(); }
}
