<?php
/**
 * AuthController — Login / Logout
 * RNF-04: Bitácora de accesos + bloqueo temporal tras 3 intentos fallidos.
 * OWASP: Bcrypt + CSRF en formularios.
 */
class AuthController extends BaseController {
    private const MAX_INTENTOS = 3;   // fallos permitidos...
    private const VENTANA_MIN  = 15;  // ...en estos minutos, por correo + IP
    // Hash bcrypt de relleno: se compara cuando el correo no existe para que tarde igual
    private const HASH_RELLENO = '$2y$10$k6FjGhsouwIYf5OYupl1U.N7/DI7eFOzdhwCFRMTsyEVwg58CLeM.';

    public function showLogin(): void {
        if (!empty($_SESSION["user"])) $this->redirect(APP_URL . "/admin/");
        $this->view("auth/login");
    }
    public function doLogin(): void {
        CsrfToken::verify();
        $email = Sanitizer::email(Sanitizer::post("email"));
        $pass  = is_string($_POST["password"] ?? null) ? $_POST["password"] : ""; // un arreglo cuenta como clave vacía
        $log   = new LogModel();
        $um    = new UserModel();
        $ip    = $this->ip();

        // Bloqueo temporal por correo + IP; igual exista o no el correo. El intento
        // bloqueado no se registra, así la espera no se alarga sola.
        if ($log->fallosRecientes($email, $ip, self::VENTANA_MIN) >= self::MAX_INTENTOS) {
            $_SESSION["login_error"] = "Demasiados intentos fallidos. Intenta de nuevo en unos minutos.";
            $this->redirect(APP_URL . "/login.php");
        }

        $user = $um->findByEmail($email);
        $passOk = password_verify($pass, $user ? $user["password"] : self::HASH_RELLENO);
        // Un solo mensaje para: correo inexistente, clave mala, cuenta desactivada o bloqueada
        if (!$user || !$passOk || !$user["activo"] || $user["bloqueado"]) {
            $log->registrar(["usuario_id" => $user["id"] ?? null, "email" => $email, "ip" => $ip, "exitoso" => false]);
            $_SESSION["login_error"] = "Correo o contraseña incorrectos.";
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
              ->password("pass", $pass, $email, $nombre);
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
