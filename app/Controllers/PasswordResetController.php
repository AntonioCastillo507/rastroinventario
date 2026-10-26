<?php
/**
 * PasswordResetController — Recuperación de contraseña (hallazgo 14b).
 * Pedir enlace: la respuesta es la misma exista o no el correo, y el correo se envía DESPUÉS de cerrar
 * la respuesta al navegador (EmailJS tarda hasta 8 s y el tiempo delataría a las cuentas que existen).
 * Usar el enlace: el token pasa a la sesión (solo su hash) y se redirige a una URL sin token.
 */
class PasswordResetController extends BaseController {
    public const MSG_SOLICITUD = "Si ese correo está registrado, te enviamos instrucciones para restablecer la contraseña. "
        . "Revisa tu bandeja de entrada y el correo no deseado; el enlace dura 30 minutos.";
    public const MSG_ENLACE = "El enlace no es válido o ya caducó. Pide uno nuevo.";
    public const MSG_LIMITE_IP = "Hay demasiadas solicitudes desde tu red. Intenta de nuevo más tarde.";

    // ── Pedir el enlace ──────────────────────────────────────────────
    public function olvide(): void {
        if (!empty($_SESSION["user"])) $this->redirect(APP_URL . "/");
        $err = $_SESSION["reset_error"] ?? ""; unset($_SESSION["reset_error"]);
        $ok  = $_SESSION["reset_ok"] ?? "";    unset($_SESSION["reset_ok"]);
        $this->sinCache();
        $this->view("auth/olvide", compact("err", "ok"));
    }

    public function procesarOlvide(): void {
        CsrfToken::verify();
        $email = Sanitizer::email(Sanitizer::post("email"));
        // El formato no depende de si la cuenta existe: se puede avisar sin delatar a nadie
        if ($email === "" || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION["reset_error"] = "Escribe un correo electrónico válido.";
            $this->redirect(APP_URL . "/olvide.php");
        }

        $m  = new PasswordResetModel();
        $ip = $this->ip();
        $m->limpiarViejos();
        $n = $m->solicitudesRecientes($email, $ip);

        if ($n["ip"] >= PasswordResetModel::MAX_POR_IP) { // no depende del correo: puede tener su propio mensaje
            $_SESSION["reset_error"] = self::MSG_LIMITE_IP;
            $this->redirect(APP_URL . "/olvide.php");
        }

        // Pasado el límite del correo no se anota nada (así quien insista no alarga la espera de nadie)
        // y la respuesta es la misma de siempre.
        $enviar = null;
        $token  = null;
        if ($n["correo"] < PasswordResetModel::MAX_POR_CORREO) {
            $user = (new UserModel())->findByEmail($email);
            $vale = $user && !empty($user["activo"]) && empty($user["bloqueado"]);
            if ($vale) { $enviar = $user; $token = PasswordResetModel::generarToken(); }
            // Siempre una fila: con cuenta lleva el hash del token; sin cuenta (o desactivada) solo cuenta
            $m->registrarSolicitud($email, $ip, $vale ? (int)$user["id"] : null, $vale ? PasswordResetModel::hash($token) : null);
        }

        $_SESSION["reset_ok"] = self::MSG_SOLICITUD;
        $this->cerrarRespuesta(APP_URL . "/olvide.php");

        // La respuesta ya salió: lo lento (el correo) no se nota desde afuera
        if ($enviar !== null) {
            EmailJsService::get()->enviarRecuperacion($enviar["email"], $enviar["nombre"], PasswordResetModel::enlace($token, APP_URL));
        }
    }

    // ── Usar el enlace ───────────────────────────────────────────────
    public function restablecer(): void {
        if (isset($_GET["token"])) { $this->recibirEnlace(); return; }

        $m = new PasswordResetModel();
        $hash = $_SESSION["reset_hash"] ?? null;
        $err  = $_SESSION["reset_error"] ?? ""; unset($_SESSION["reset_error"]);
        $valido = is_string($hash) && $m->buscarValido($hash) !== false;
        if (!$valido) { unset($_SESSION["reset_hash"]); $err = $err !== "" ? $err : self::MSG_ENLACE; }
        $this->sinCache();
        header("Referrer-Policy: no-referrer");
        $this->view("auth/restablecer", compact("err", "valido"));
    }

    /** GET con ?token=: valida sin consumir, guarda solo el hash en la sesión y limpia la URL */
    private function recibirEnlace(): void {
        $token = $_GET["token"];
        $hash  = null;
        if (PasswordResetModel::formatoValido($token)) {
            $h = PasswordResetModel::hash($token);
            if ((new PasswordResetModel())->buscarValido($h) !== false) $hash = $h;
        }
        header("Referrer-Policy: no-referrer");
        $this->sinCache();
        if ($hash === null) {
            unset($_SESSION["reset_hash"]);
            $_SESSION["reset_error"] = self::MSG_ENLACE; // el mismo texto para inventado, usado, caducado o de cuenta desactivada
        } else {
            session_regenerate_id(true);
            $_SESSION["reset_hash"] = $hash;
        }
        $this->redirect(APP_URL . "/restablecer.php");
    }

    public function procesarRestablecer(): void {
        CsrfToken::verify();
        $m    = new PasswordResetModel();
        $hash = $_SESSION["reset_hash"] ?? null;
        $fila = is_string($hash) ? $m->buscarValido($hash) : false;
        if ($fila === false) {
            unset($_SESSION["reset_hash"]);
            $_SESSION["reset_error"] = self::MSG_ENLACE;
            $this->redirect(APP_URL . "/restablecer.php");
        }

        $p1 = $_POST["password"] ?? "";
        $p2 = $_POST["password2"] ?? "";
        $v  = (new Validator())->password("pass", $p1, $fila["email"], $fila["nombre"]);
        // Una clave floja o que no coincide no gasta el enlace: se puede corregir y reintentar
        if (!is_string($p2) || $p1 !== $p2) {
            $_SESSION["reset_error"] = "Las contraseñas no coinciden.";
            $this->redirect(APP_URL . "/restablecer.php");
        }
        if (!$v->isValid()) {
            $_SESSION["reset_error"] = $v->first();
            $this->redirect(APP_URL . "/restablecer.php");
        }

        if (!$m->completar($hash, password_hash($p1, PASSWORD_BCRYPT))) {
            unset($_SESSION["reset_hash"]);
            $_SESSION["reset_error"] = self::MSG_ENLACE;
            $this->redirect(APP_URL . "/restablecer.php");
        }
        unset($_SESSION["reset_hash"]);
        session_regenerate_id(true); // no inicia sesión sola: la persona entra con su clave nueva
        CsrfToken::rotar();
        $_SESSION["login_ok"] = "Contraseña actualizada. Ya puedes iniciar sesión.";
        $this->redirect(APP_URL . "/login.php");
    }

    // ── Ayudas ───────────────────────────────────────────────────────
    private function sinCache(): void {
        header("Cache-Control: no-store");
    }

    /**
     * Cierra la respuesta (redirección vacía) y deja el script seguir trabajando.
     * Primero se suelta la sesión para que otras pestañas de la misma persona no queden esperando.
     */
    private function cerrarRespuesta(string $url): void {
        ignore_user_abort(true);
        @set_time_limit(30);
        session_write_close();
        header("Location: " . $url);
        header("Content-Length: 0");
        header("Connection: close");
        while (ob_get_level() > 0) @ob_end_flush();
        flush();
    }
}
