<?php
class RolController extends BaseController {
    private RolModel $rm;
    const MODULOS = ["usuarios","roles","partes","autos","secciones","ventas","comentarios","pedidos"];
    public function __construct() { $this->rm = new RolModel(); }

    /** Solo módulos conocidos: ignora cualquier clave inventada en el POST */
    private function permisosDelPost(): array {
        return array_intersect_key((array)($_POST["permisos"] ?? []), array_flip(self::MODULOS));
    }

    public function index(): void {
        $this->requirePermission("roles");
        $roles = $this->rm->findAll();
        $this->view("admin/roles/index", compact("roles"));
    }
    public function crear(): void {
        $this->requirePermission("roles","puede_crear");
        $modulos = self::MODULOS; $error = "";
        if ($this->isPost()) {
            CsrfToken::verify();
            $nombre = Sanitizer::post("nombre");
            $desc   = Sanitizer::post("descripcion");
            $v = (new Validator())->required("nombre", $nombre, "Nombre del rol");
            if ($v->isValid()) {
                $id = $this->rm->create(["nombre"=>$nombre,"descripcion"=>$desc]);
                if ($id) { $this->rm->savePermisos($id,$this->permisosDelPost()); $this->redirect(APP_URL."/admin/?mod=roles","Rol creado."); }
                $error = "Ese nombre de rol ya existe.";
            } else $error = $v->first();
        }
        $this->view("admin/roles/form", compact("modulos","error"));
    }
    public function editar(): void {
        $this->requirePermission("roles","puede_editar");
        $id  = Sanitizer::get("id","int");
        $rol = $this->rm->findById($id);
        if (!$rol) $this->redirect(APP_URL."/admin/?mod=roles","Rol no encontrado.","danger");
        $raw = $this->rm->getPermisos($id);
        $permisos = [];
        foreach ($raw as $p) $permisos[$p["modulo"]] = $p;
        $modulos = self::MODULOS; $error = "";
        if ($this->isPost()) {
            CsrfToken::verify();
            $this->rm->update($id,["nombre"=>Sanitizer::post("nombre"),"descripcion"=>Sanitizer::post("descripcion")]);
            $this->rm->savePermisos($id,$this->permisosDelPost());
            $this->redirect(APP_URL."/admin/?mod=roles","Rol actualizado.");
        }
        $this->view("admin/roles/form", compact("rol","permisos","modulos","error","id"));
    }
}
