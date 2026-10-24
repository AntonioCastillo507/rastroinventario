<?php
class UserController extends BaseController {
    public function miClave(): void {
        $this->requireAuth();
        $error = ""; $success = "";
        if ($this->isPost()) {
            CsrfToken::verify();
            $actual = $_POST["actual"] ?? "";
            $nueva  = $_POST["nueva"]  ?? "";
            $conf   = $_POST["confirmar"] ?? "";
            $user   = $this->um->findById($_SESSION["user"]["id"]);
            if (!$user || !password_verify($actual, $user["password"])) {
                $error = "La contraseña actual no es correcta.";
            } elseif ($nueva !== $conf) {
                $error = "La nueva contraseña y su confirmación no coinciden.";
            } else {
                $v = (new Validator())->password("nueva", $nueva, $user["email"], $user["nombre"]);
                if ($v->isValid()) {
                    $this->um->updatePassword($_SESSION["user"]["id"], password_hash($nueva, PASSWORD_BCRYPT));
                    $success = "Contraseña actualizada correctamente.";
                } else {
                    $error = $v->first();
                }
            }
        }
        $this->view("admin/usuarios/clave", compact("error","success"));
    }
    private UserModel $um;
    private RolModel  $rm;
    public function __construct() { $this->um = new UserModel(); $this->rm = new RolModel(); }

    /** Asignar roles exige permiso de editar roles, no solo de gestionar usuarios */
    private function puedeAsignarRoles(): bool {
        return $this->um->hasPermission($_SESSION["user"]["id"], "roles", "puede_editar");
    }
    /** Ids de rol del POST, solo los que existen */
    private function rolesDelPost(): array {
        $validos = array_map("intval", array_column($this->rm->findAll(), "id"));
        return array_values(array_intersect(array_map("intval", (array)($_POST["roles"] ?? [])), $validos));
    }

    public function index(): void {
        $this->requirePermission("usuarios");
        $pag      = Paginador::crear(Sanitizer::get("page"), $this->um->contar(), Paginador::PANEL);
        $usuarios = $this->um->findAllWithRoles($pag);
        $pagParams = ["mod"=>"usuarios"];
        $this->view("admin/usuarios/index", compact("usuarios","pag","pagParams"));
    }
    public function crear(): void {
        $this->requirePermission("usuarios", "puede_crear");
        $roles = $this->rm->findAll();
        $error = "";
        $puedeRoles = $this->puedeAsignarRoles();
        if ($this->isPost()) {
            CsrfToken::verify();
            $nombre = Sanitizer::post("nombre");
            $email  = Sanitizer::email(Sanitizer::post("email"));
            $pass   = $_POST["password"] ?? "";
            $rols   = $puedeRoles ? $this->rolesDelPost() : []; // sin permiso: usuario sin roles
            $v = (new Validator())
                ->required("nombre", $nombre, "Nombre")
                ->required("email",  $email,  "Email")
                ->email("email", $email)
                ->password("password", $pass, $email, $nombre);
            if ($v->isValid()) {
                $id = $this->um->create(["nombre"=>$nombre,"email"=>$email,"password"=>password_hash($pass,PASSWORD_BCRYPT)]);
                if ($id) { $this->um->setRoles($id, $rols); $this->redirect(APP_URL."/admin/?mod=usuarios","Usuario creado."); }
                $error = "El correo ya está registrado.";
            } else $error = $v->first();
        }
        $this->view("admin/usuarios/form", compact("roles","error","puedeRoles"));
    }
    public function editar(): void {
        $this->requirePermission("usuarios", "puede_editar");
        $id        = Sanitizer::get("id", "int");
        $user      = $this->um->findById($id);
        if (!$user) $this->redirect(APP_URL."/admin/?mod=usuarios","Usuario no encontrado.","danger");
        $roles     = $this->rm->findAll();
        $userRoles = array_column($this->um->getRoles($id), "id");
        $error     = "";
        $puedeRoles = $this->puedeAsignarRoles();
        if ($this->isPost()) {
            CsrfToken::verify();
            $update = ["nombre"=>Sanitizer::post("nombre"), "email"=>Sanitizer::email(Sanitizer::post("email"))];
            $pass   = $_POST["password"] ?? "";
            if ($pass !== "") {
                $v = (new Validator())->password("password", $pass, $update["email"], $update["nombre"]);
                if ($v->isValid()) {
                    $update["password"] = password_hash($pass, PASSWORD_BCRYPT);
                } else {
                    $error = $v->first();
                }
            }
            if ($error === "") {
                $this->um->update($id, $update);
                if ($puedeRoles) $this->um->setRoles($id, $this->rolesDelPost()); // si no, se conservan los actuales
                $this->redirect(APP_URL."/admin/?mod=usuarios","Usuario actualizado.");
            }
        }
        $this->view("admin/usuarios/form", compact("roles","userRoles","user","error","id","puedeRoles"));
    }
    public function toggle(): void {
        $this->requirePermission("usuarios", "puede_editar");
        if (!$this->isPost()) $this->redirect(APP_URL."/admin/?mod=usuarios"); // cambia datos: solo POST
        CsrfToken::verify();
        $this->um->toggleActivo(Sanitizer::get("id","int"));
        $this->redirect(APP_URL."/admin/?mod=usuarios","Estado actualizado.");
    }
}
