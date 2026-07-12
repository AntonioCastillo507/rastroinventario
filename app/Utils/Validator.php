<?php
/**
 * Validator — Validación de reglas de negocio.
 * RNF-05: Contraseña entre 8 y 12 caracteres.
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
    public function password(string $f, string $v): static {
        $l = strlen($v);
        if ($l < 8 || $l > 12) $this->errors[$f] = "La contraseña debe tener entre 8 y 12 caracteres (RNF-05).";
        return $this;
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
