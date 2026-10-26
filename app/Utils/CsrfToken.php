<?php
/**
 * CsrfToken — Protección CSRF en todos los formularios POST.
 * Un token por sesión (patrón "synchronizer token"): no se gasta al usarlo, así varias pestañas y las
 * acciones sin recarga del carrito siguen valiendo. Se renueva al iniciar sesión, al registrarse y al
 * cambiar la contraseña (rotar()), y desaparece con la sesión.
 */
class CsrfToken {
    public static function generate(): string {
        if (empty($_SESSION["csrf_token"]))
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        return $_SESSION["csrf_token"];
    }
    /** Token nuevo para esta sesión (el anterior deja de valer) */
    public static function rotar(): string {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        return $_SESSION["csrf_token"];
    }
    public static function field(): string {
        return "<input type=\"hidden\" name=\"csrf_token\" value=\"" . self::generate() . "\">";
    }
    /** Comprueba el token del POST sin cortar la petición */
    public static function check(): bool {
        $token = $_POST["csrf_token"] ?? "";
        // Token vacío nunca vale (sin token en sesión, "" == "" pasaba la comparación)
        return is_string($token) && $token !== "" && hash_equals($_SESSION["csrf_token"] ?? "", $token);
    }
    /**
     * Corta con 403 si el token no vale. Con $volverA (rutas de compra) no deja una página en blanco:
     * guarda lo escrito con $conservar, avisa que la página caducó y devuelve a esa dirección.
     */
    public static function verify(?string $volverA = null, ?callable $conservar = null): void {
        if (self::check()) return;
        if ($volverA !== null) {
            if ($conservar) $conservar();
            $_SESSION["flash"] = ["msg" => "La página caducó. Revisa tus datos e inténtalo de nuevo.", "type" => "warning"];
            header("Location: " . $volverA); exit;
        }
        PaginaError::mostrar(403, "La página caducó o no se pudo comprobar. Vuelve a la página anterior, revisa tus datos e inténtalo de nuevo.", null, "La página caducó");
    }
}
