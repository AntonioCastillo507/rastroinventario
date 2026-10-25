<?php
/**
 * CsrfToken — Protección CSRF en todos los formularios POST.
 * Toda petición POST valida un token CSRF de sesión (OWASP).
 */
class CsrfToken {
    public static function generate(): string {
        if (empty($_SESSION["csrf_token"]))
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        return $_SESSION["csrf_token"];
    }
    public static function field(): string {
        return "<input type=\"hidden\" name=\"csrf_token\" value=\"" . self::generate() . "\">";
    }
    /** Comprueba el token del POST sin cortar la petición; si vale, lo consume */
    public static function check(): bool {
        $token = $_POST["csrf_token"] ?? "";
        // Token vacío nunca vale (sin token en sesión, "" == "" pasaba la comparación)
        if (!is_string($token) || $token === "" || !hash_equals($_SESSION["csrf_token"] ?? "", $token)) return false;
        unset($_SESSION["csrf_token"]);
        return true;
    }
    public static function verify(): void {
        if (!self::check()) {
            http_response_code(403);
            die("Token CSRF inválido. <a href=\"javascript:history.back()\">Volver</a>");
        }
    }
}
