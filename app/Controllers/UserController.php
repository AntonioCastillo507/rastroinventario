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
                    CsrfToken::rotar();
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

    /** Id del rol Administrador, o null si no existe */
    private function idRolAdministrador(): ?int {
        foreach ($this->rm->findAll() as $r) if ($r["nombre"] === UserModel::ROL_ADMIN) return (int)$r["id"];
        return null;
    }
    /**
     * Reglas sobre quién toca a los Administradores. Devuelve el motivo del rechazo o "" si se puede.
     * $nuevos son los ids de rol que se guardarían (null si no cambian los roles).
     */
    private function errorDeRoles(int $objetivoId, bool $objetivoEsAdmin, bool $objetivoActivo, bool $actorEsAdmin, ?array $nuevos): string {
        if ($nuevos === null) return "";
        $adminId = $this->idRolAdministrador();
        if ($adminId === null) return "";
        $quedaAdmin = in_array($adminId, $nuevos, true);
        if ($quedaAdmin && !$objetivoEsAdmin && !$actorEsAdmin)
            return "Solo un Administrador puede dar el rol de Administrador.";
        if ($objetivoEsAdmin && !$quedaAdmin) {
            if (!$actorEsAdmin) return "Solo un Administrador puede cambiar los roles de otro Administrador.";
            if ($objetivoId === (int)$_SESSION["user"]["id"]) return "No puedes quitarte tu propio rol de Administrador.";
            if ($this->um->contarAdministradoresActivos() - ($objetivoActivo ? 1 : 0) < 1)
                return "No se puede dejar el sistema sin un Administrador activo.";
        }
        return "";
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
            $errRoles = $this->errorDeRoles(0, false, true, $this->um->esAdministrador((int)$_SESSION["user"]["id"]), $rols);
            $v = (new Validator())
                ->required("nombre", $nombre, "Nombre")
                ->required("email",  $email,  "Email")
                ->email("email", $email)
                ->password("password", $pass, $email, $nombre);
            if ($errRoles !== "") $error = $errRoles;
            elseif ($v->isValid()) {
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
        $actorId         = (int)$_SESSION["user"]["id"];
        $actorEsAdmin    = $this->um->esAdministrador($actorId);
        $objetivoEsAdmin = $this->um->esAdministrador($id);
        if ($objetivoEsAdmin && !$actorEsAdmin)
            $this->redirect(APP_URL."/admin/?mod=usuarios","Solo un Administrador puede editar a otro Administrador.","danger");
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
            $nuevos = $puedeRoles ? $this->rolesDelPost() : null; // sin permiso de roles, se conservan los actuales
            if ($error === "") $error = $this->errorDeRoles($id, $objetivoEsAdmin, !empty($user["activo"]), $actorEsAdmin, $nuevos);
            if ($error === "") {
                $this->um->update($id, $update);
                if ($nuevos !== null) $this->um->setRoles($id, $nuevos);
                $this->redirect(APP_URL."/admin/?mod=usuarios","Usuario actualizado.");
            }
        }
        $this->view("admin/usuarios/form", compact("roles","userRoles","user","error","id","puedeRoles"));
    }
    public function toggle(): void {
        $this->requirePermission("usuarios", "puede_editar");
        if (!$this->isPost()) $this->redirect(APP_URL."/admin/?mod=usuarios"); // cambia datos: solo POST
        CsrfToken::verify();
        $id   = Sanitizer::get("id","int");
        $user = $this->um->findById($id);
        $volver = APP_URL."/admin/?mod=usuarios";
        if (!$user) $this->redirect($volver,"Usuario no encontrado.","danger");
        if ($id === (int)$_SESSION["user"]["id"]) $this->redirect($volver,"No puedes desactivar tu propia cuenta.","danger");
        if ($this->um->esAdministrador($id)) {
            if (!$this->um->esAdministrador((int)$_SESSION["user"]["id"]))
                $this->redirect($volver,"Solo un Administrador puede desactivar o reactivar a otro Administrador.","danger");
            if (!empty($user["activo"]) && $this->um->contarAdministradoresActivos() <= 1)
                $this->redirect($volver,"No se puede dejar el sistema sin un Administrador activo.","danger");
        }
        $this->um->toggleActivo($id);
        $this->redirect($volver,"Estado actualizado.");
    }
}
