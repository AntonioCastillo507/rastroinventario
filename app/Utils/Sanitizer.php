<?php
/**
 * Sanitizer — Limpieza de entradas de usuario.
 * Guarda el texto tal cual lo escribió la persona (sin escapar y sin quitar etiquetas).
 * El escape contra XSS se hace al imprimir, según el contexto: htmlspecialchars() en las vistas.
 * El SQL va siempre con parámetros (?), no depende de esta clase.
 */
class Sanitizer {
    public static function string(string $v): string {
        $v = mb_scrub(trim($v), "UTF-8");                                     // UTF-8 inválido se sustituye, no vacía el campo
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', "", $v); // sin controles; se conservan \t \n \r
    }
    public static function int(mixed $v): int {
        return (int) filter_var($v, FILTER_SANITIZE_NUMBER_INT);
    }
    public static function float(mixed $v): float {
        return (float) filter_var($v, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
    public static function email(string $v): string {
        return (string) filter_var(trim($v), FILTER_SANITIZE_EMAIL);
    }
    public static function post(string $key, string $type = "string"): mixed {
        $v = $_POST[$key] ?? "";
        if (is_array($v)) $v = "";
        return match($type) {
            "int"   => self::int($v),
            "float" => self::float($v),
            "email" => self::email($v),
            default => self::string((string)$v),
        };
    }
    public static function get(string $key, string $type = "string"): mixed {
        $v = $_GET[$key] ?? "";
        if (is_array($v)) $v = "";
        return match($type) {
            "int"   => self::int($v),
            default => self::string((string)$v),
        };
    }
}
