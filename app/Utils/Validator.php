<?php
/**
 * Validator — Validación de reglas de negocio.
 * Política de contraseñas: 8 caracteres como mínimo y 72 bytes como máximo, sin reglas de
 * mayúsculas o símbolos, que no sea común ni igual al correo o al nombre.
 */
class Validator {
    private array $errors = [];

    public function required(string $f, mixed $v, string $label = ""): static {
        if ($v === null || $v === "") $this->errors[$f] = ($label ?: $f) . " es requerido.";
        return $this;
    }
    public function minLen(string $f, string $v, int $min): static {
        if (strlen($v) < $min) $this->errors[$f] = "Mínimo $min caracteres requeridos.";
        return $this;
    }
    public function maxLen(string $f, string $v, int $max): static {
        if (strlen($v) > $max) $this->errors[$f] = "Máximo $max caracteres permitidos.";
        return $this;
    }
    // Política de contraseñas (hallazgo 14): el mínimo cuenta caracteres, el máximo cuenta bytes
    // porque bcrypt solo lee los primeros 72 y no avisa del resto.
    public const PASS_MIN = 8;
    public const PASS_MAX = 72;
    private static ?array $comunes = null;

    public function password(string $f, mixed $v, string $email = "", string $nombre = ""): static {
        $e = self::errorPassword($v, $email, $nombre);
        if ($e !== "") $this->errors[$f] = $e;
        return $this;
    }
    private static function errorPassword(mixed $v, string $email, string $nombre): string {
        if (!is_string($v) || str_contains($v, "\0") || !mb_check_encoding($v, "UTF-8"))
            return "La contraseña contiene caracteres no permitidos.";
        if (mb_strlen($v) < self::PASS_MIN)
            return "La contraseña debe tener al menos " . self::PASS_MIN . " caracteres.";
        if (strlen($v) > self::PASS_MAX)
            return "La contraseña es demasiado larga (máximo " . self::PASS_MAX . " bytes; las letras con acento y los emojis cuentan más).";
        if (count(array_unique(mb_str_split($v))) < 4)
            return "La contraseña es demasiado simple.";
        if (self::esComun($v))
            return "Esa contraseña es demasiado común. Prueba con una frase de varias palabras.";
        $p = self::plano($v);
        $local = strstr($email, "@", true);
        foreach ([$email, $local === false ? "" : $local, $nombre] as $dato) {
            if ($dato !== "" && $p === self::plano($dato)) return "La contraseña no puede ser igual a tu correo ni a tu nombre.";
        }
        return "";
    }
    // Minúsculas, sin acentos y sin espacios, para comparar contra la lista y contra el correo o el nombre
    private static function plano(string $s): string {
        $s = mb_strtolower($s, "UTF-8");
        $n = class_exists("Normalizer") ? Normalizer::normalize($s, Normalizer::FORM_D) : false;
        $s = $n !== false ? preg_replace('/\p{Mn}/u', "", $n) : strtr($s, ["á"=>"a","é"=>"e","í"=>"i","ó"=>"o","ú"=>"u","ü"=>"u","ñ"=>"n"]);
        return preg_replace('/\s+/u', "", $s);
    }
    private static function esComun(string $v): bool {
        self::$comunes ??= require __DIR__ . "/contrasenas_comunes.php";
        $p = self::plano($v);
        $base = preg_replace('/^[\d\W_]+|[\d\W_]+$/u', "", $p); // sin números ni símbolos en las puntas
        // El nombre de la marca (con o sin números en las puntas) tampoco vale como contraseña
        $marca = self::plano((string)Marca::get("nombre"));
        if ($marca !== "" && ($p === $marca || $base === $marca)) return true;
        return in_array($p, self::$comunes["exactas"], true)
            || in_array($p, self::$comunes["bases"], true)
            || ($base !== "" && in_array($base, self::$comunes["bases"], true));
    }
    public function email(string $f, string $v): static {
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) $this->errors[$f] = "El correo electrónico no es válido.";
        return $this;
    }
    public function numeric(string $f, mixed $v, float $min = 0): static {
        if (!is_numeric($v) || (float)$v < $min) $this->errors[$f] = "Debe ser un número mayor o igual a $min.";
        return $this;
    }
    public function isValid(): bool    { return empty($this->errors); }
    public function getErrors(): array { return $this->errors; }
    public function first(): string    { return array_values($this->errors)[0] ?? ""; }
}
