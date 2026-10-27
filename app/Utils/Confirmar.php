<?php
/**
 * Datos del modal de confirmación del panel (assets/js/confirmar.js) como atributos de un <form>.
 * El formulario sigue siendo un POST con CsrfToken: el modal solo pregunta antes de enviarlo.
 */
class Confirmar {
    /** @param array{titulo:string,texto:string,boton:string,cancelar?:string,peligro?:bool} $d */
    public static function attrs(array $d): string {
        return ' data-confirmar="' . self::json($d) . '"';
    }

    /** Pregunta solo según el valor de un campo del formulario ($mapa: valor => datos); con otros valores se envía sin preguntar */
    public static function attrsPorCampo(string $campo, array $mapa): string {
        return ' data-confirmar-campo="' . htmlspecialchars($campo, ENT_QUOTES, "UTF-8") . '" data-confirmar="' . self::json($mapa) . '"';
    }

    private static function json(array $d): string {
        return htmlspecialchars((string)json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, "UTF-8");
    }
}
