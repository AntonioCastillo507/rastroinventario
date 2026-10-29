<?php
class AutoController extends BaseController {
    private AutoModel $am;
    private MarcaAutoModel $mm;
    public function __construct() { $this->am = new AutoModel(); $this->mm = new MarcaAutoModel(); }

    public function index(): void   { $this->requirePermission("autos"); $autos=$this->am->findAll(); $this->view("admin/autos/index",compact("autos")); }

    /** Marca elegida en el formulario: marca_id y el nombre que guarda autos.marca; null si no hay una marca activa válida */
    private function marcaElegida(): ?array {
        $m = $this->mm->findById(Sanitizer::post("marca_id","int"));
        return ($m && $m["activo"]) ? ["marca_id"=>(int)$m["id"],"marca"=>$m["nombre"]] : null;
    }
    private function datosAuto(array $marca): array {
        return $marca + ["modelo"=>Sanitizer::post("modelo"),"anio"=>Sanitizer::post("anio","int")];
    }

    public function crear(): void {
        $this->requirePermission("autos","puede_crear"); $error=""; $marcas=$this->mm->activas();
        if ($this->isPost()) {
            CsrfToken::verify();
            $marca=$this->marcaElegida();
            $v=(new Validator())->required("modelo",Sanitizer::post("modelo"),"Modelo");
            if ($marca && $v->isValid()) { $this->am->create($this->datosAuto($marca)); $this->redirect(APP_URL."/admin/?mod=autos","Auto agregado."); }
            $error=$marca ? $v->first() : "Elige una marca de la lista. Si no está, agrégala primero en Marcas.";
            $auto=["marca_id"=>Sanitizer::post("marca_id","int"),"modelo"=>Sanitizer::post("modelo"),"anio"=>Sanitizer::post("anio","int")];
        }
        $this->view("admin/autos/form",compact("error","marcas")+(isset($auto)?compact("auto"):[]));
    }
    public function editar(): void {
        $this->requirePermission("autos","puede_editar");
        $id=Sanitizer::get("id","int"); $auto=$this->am->findById($id);
        if (!$auto) $this->redirect(APP_URL."/admin/?mod=autos","No encontrado.","danger");
        $error=""; $marcas=$this->mm->activas();
        if ($this->isPost()) {
            CsrfToken::verify();
            $marca=$this->marcaElegida();
            if ($marca) { $this->am->update($id,$this->datosAuto($marca)); $this->redirect(APP_URL."/admin/?mod=autos","Auto actualizado."); }
            $error="Elige una marca de la lista. Si no está, agrégala primero en Marcas.";
            $auto=["marca_id"=>Sanitizer::post("marca_id","int"),"modelo"=>Sanitizer::post("modelo"),"anio"=>Sanitizer::post("anio","int")]+$auto;
        }
        $this->view("admin/autos/form",compact("auto","error","id","marcas"));
    }
    public function eliminar(): void {
        $this->requirePermission("autos","puede_borrar");
        if (!$this->isPost()) $this->redirect(APP_URL."/admin/?mod=autos"); // cambia datos: solo POST
        CsrfToken::verify();
        $this->am->softDelete(Sanitizer::get("id","int")); $this->redirect(APP_URL."/admin/?mod=autos","Auto desactivado."); }
}
