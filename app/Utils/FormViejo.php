<?php
/**
 * FormViejo — lo que la persona escribió en un formulario que volvió con error.
 * Se guarda en sesión de un solo uso y la vista lo recoge para rellenar los campos.
 * Solo campos no secretos: la contraseña nunca se guarda ni se devuelve.
 */
class FormViejo {
    private const MAX = 2000; // por campo

    /** Guarda los campos permitidos; $permitidos lista los nombres (una contraseña jamás va ahí) */
    public static function guardar(string $form, array $origen, array $permitidos): void {
        $v = [];
        foreach ($permitidos as $campo) {
            if (isset($origen[$campo]) && is_scalar($origen[$campo])) $v[$campo] = mb_substr((string)$origen[$campo], 0, self::MAX);
        }
        $_SESSION["form_viejo"][$form] = $v;
    }

    /** Devuelve lo guardado para ese formulario y lo borra */
    public static function tomar(string $form): array {
        $v = $_SESSION["form_viejo"][$form] ?? [];
        unset($_SESSION["form_viejo"][$form]);
        if (empty($_SESSION["form_viejo"])) unset($_SESSION["form_viejo"]);
        return is_array($v) ? $v : [];
    }
}
