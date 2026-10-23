<?php
/**
 * AuthController — Login / Logout
 * RNF-04: Bitácora de accesos + bloqueo al 3er intento fallido.
 * OWASP: Bcrypt + CSRF en formularios.
 */
class AuthController extends BaseController {
    public function showLogin(): void {
        if (!empty($_SESSION["user"])) $this->redirect(APP_URL . "/admin/");
        $this->view("auth/login");
    }
    public function doLogin(): void {
        CsrfToken::verify();
        $email = Sanitizer::email(Sanitizer::post("email"));
        $pass  = $_POST["password"] ?? "";
        $log   = new LogModel();
        $um    = new UserModel();
        $ip    = $this->ip();
        $user  = $um->findByEmail($email);

        if (!$user) {
            $log->registrar(["usuario_id" => null, "email" => $email, "ip" => $ip, "exitoso" => false]);
            $_SESSION["login_error"] = "Credenciales incorrectas.";
            $this->redirect(APP_URL . "/login.php");
        }
        if ($user["bloqueado"]) {
            $log->registrar(["usuario_id" => $user["id"], "email" => $email, "ip" => $ip, "exitoso" => false]);
            $_SESSION["login_error"] = "Cuenta bloqueada por múltiples intentos fallidos. Contacta al administrador.";
            $this->redirect(APP_URL . "/login.php");
        }
        if (!$user["activo"]) {
            $log->registrar(["usuario_id" => $user["id"], "email" => $email, "ip" => $ip, "exitoso" => false]);
            $_SESSION["login_error"] = "Esta cuenta está desactivada.";
            $this->redirect(APP_URL . "/login.php");
        }
        if (!password_verify($pass, $user["password"])) {
            $um->incrementIntentos($user["id"]);
            $intentos = (int)$user["intentos_fallidos"] + 1;
            if ($intentos >= 3) {
                $um->bloquear($user["id"]);
                $_SESSION["login_error"] = "Cuenta bloqueada después de 3 intentos fallidos.";
            } else {
                $_SESSION["login_error"] = "Contraseña incorrecta. Intentos restantes: " . (3 - $intentos) . ".";
            }
            $log->registrar(["usuario_id" => $user["id"], "email" => $email, "ip" => $ip, "exitoso" => false]);
            $this->redirect(APP_URL . "/login.php");
        }
        // Éxito
        $um->resetIntentos($user["id"]);
        $log->registrar(["usuario_id" => $user["id"], "email" => $email, "ip" => $ip, "exitoso" => true]);
        session_regenerate_id(true); // nuevo ID al autenticar (anti fijación de sesión)
        $_SESSION["user"] = ["id" => $user["id"], "nombre" => $user["nombre"], "email" => $user["email"]];
        $_SESSION["last_activity"] = time();
        $roles = array_column($um->getRoles($user["id"]), "nombre");
        $_SESSION["user_roles"] = $roles;
        // Solo el personal (Administrador/Vendedor/Consultor) entra al panel admin.
        // Un Cliente que use este mismo login regresa a la tienda pública.
        $esStaff = array_intersect(["Administrador", "Vendedor", "Consultor"], $roles);
        $this->redirect($esStaff ? APP_URL . "/admin/" : APP_URL . "/");
    }
    public function doLogout(): void { destruirSesion(); header("Location: " . APP_URL . "/login.php"); exit; }

    // ── Registro público de clientes (separado del checkout) ─────────
    public function registro(): void {
        if (!empty($_SESSION["user"])) $this->redirect(APP_URL . "/");
        $err = $_SESSION["registro_error"] ?? ""; unset($_SESSION["registro_error"]);
        $this->view("auth/registro", compact("err"));
    }

    public function procesarRegistro(): void {
        CsrfToken::verify();
        $nombre = Sanitizer::post("nombre");
        $email  = Sanitizer::email(Sanitizer::post("email"));
        $tel    = Sanitizer::post("telefono");
        $pass   = $_POST["password"] ?? "";

        $v = (new Validator())->required("nombre", $nombre, "Nombre")
              ->required("email", $email, "Correo")->email("email", $email)
              ->password("pass", $pass);
        if (!$v->isValid()) {
            $_SESSION["registro_error"] = $v->first();
            $this->redirect(APP_URL . "/registro.php");
        }

        $um = new UserModel();
        if ($um->findByEmail($email)) {
            $_SESSION["registro_error"] = "Ya existe una cuenta con ese correo.";
            $this->redirect(APP_URL . "/registro.php");
        }

        $uid = $um->create([
            "nombre" => $nombre, "email" => $email,
            "password" => password_hash($pass, PASSWORD_BCRYPT),
            "telefono" => $tel, "direccion" => "",
        ]);
        if (!$uid) {
            $_SESSION["registro_error"] = "No se pudo crear la cuenta. Intenta de nuevo.";
            $this->redirect(APP_URL . "/registro.php");
        }
        $um->assignClienteRole($uid);

        EmailJsService::get()->enviarBienvenida($email, $nombre);

        $_SESSION["user"] = ["id" => $uid, "nombre" => $nombre, "email" => $email];
        $_SESSION["user_roles"] = ["Cliente"];
        session_regenerate_id(true);
        $this->redirect(APP_URL . "/", "¡Cuenta creada! Bienvenido/a.", "success");
    }
}
