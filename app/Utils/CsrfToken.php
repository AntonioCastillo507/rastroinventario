<?php
/**
 * CsrfToken — Protección CSRF en todos los formularios POST.
 * OWASP RNF-01: Toda petición POST valida un token CSRF de sesión.
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
    public static function verify(): void {
        $token = $_POST["csrf_token"] ?? "";
        if (!hash_equals($_SESSION["csrf_token"] ?? "", $token)) {
            http_response_code(403);
            die("Token CSRF inválido. <a href=\"javascript:history.back()\">Volver</a>");
        }
        unset($_SESSION["csrf_token"]);
    }
}
