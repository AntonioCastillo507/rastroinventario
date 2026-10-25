<?php
/**
 * URL de los archivos propios de assets/ (CSS, JS y la hoja de fuentes) con ?v=<fecha de modificación>.
 * Así, tras editar o desplegar un archivo, el navegador deja de servir la copia vieja que tenía guardada.
 * Los archivos de assets/vendor/ llevan la versión en su ruta y no pasan por aquí.
 */
final class Assets {
    public static function url(string $ruta): string {
        $ruta = ltrim($ruta, "/");
        $archivo = BASE_PATH . "/assets/" . $ruta;
        $version = is_file($archivo) ? (int)filemtime($archivo) : 0;
        return APP_URL . "/assets/" . $ruta . ($version > 0 ? "?v=" . $version : "");
    }
}
