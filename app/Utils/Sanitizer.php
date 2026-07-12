<?php
/**
 * Sanitizer — Sanitización de entradas de usuario.
 * Requisito: Clase de sanitizar y validar datos. [OWASP — Anti XSS]
 */
class Sanitizer {
    public static function string(string $v): string {
        return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, "UTF-8");
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
        return match($type) {
            "int"   => self::int($v),
            "float" => self::float($v),
            "email" => self::email($v),
            default => self::string((string)$v),
        };
    }
    public static function get(string $key, string $type = "string"): mixed {
        $v = $_GET[$key] ?? "";
        return match($type) {
            "int"   => self::int($v),
            default => self::string((string)$v),
        };
    }
}
