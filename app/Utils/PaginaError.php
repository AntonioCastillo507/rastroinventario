<?php
/**
 * PaginaError — la página temática de los errores (403, 404, 500, 503), con la marca y los estilos del sitio.
 * Es autocontenida: no usa la base de datos ni los controladores, así que también sirve cuando algo falló.
 * Nunca muestra datos internos: solo un texto fijo y, si se da, el código de referencia que quedó en logs/error.log.
 * Si armar la página temática falla (sin marca, sin estilos, sin APP_URL), quien llama cae en la página neutra de bootstrap.php.
 */
final class PaginaError {
    public const CODIGOS = [403, 404, 500, 503];

    private const TEXTOS = [
        403 => ["Acceso no permitido", "No tienes permiso para ver esta página."],
        404 => ["Página no encontrada", "No encontramos lo que buscas. Revisa la dirección o busca una pieza en el catálogo."],
        500 => ["Algo salió mal", "Ocurrió un error interno. Intenta de nuevo en unos minutos."],
        503 => ["Servicio no disponible", "No pudimos atender tu solicitud ahora mismo. Intenta de nuevo en unos minutos."],
    ];

    /** Título y mensaje fijos de un código (el mensaje se puede reemplazar por uno propio, siempre sin datos internos) */
    public static function textos(int $codigo): array {
        return self::TEXTOS[$codigo] ?? self::TEXTOS[500];
    }

    /** HTML completo de la página; lanza si no se puede armar (el HTML a medias nunca sale) */
    public static function html(int $codigo, ?string $mensaje = null, ?string $ref = null, ?string $titulo = null): string {
        [$tituloBase, $mensajeBase] = self::textos($codigo);
        $titulo  = $titulo ?? $tituloBase;
        $mensaje = $mensaje ?? $mensajeBase;
        $nivel = ob_get_level();
        ob_start();
        try {
            include BASE_PATH . "/app/Views/public/error.php";
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $nivel) ob_end_clean();
            throw $e;
        }
    }

    /** Responde con el código HTTP y termina. Sin cabeceras enviadas, marca la página como no indexable y sin caché */
    public static function mostrar(int $codigo, ?string $mensaje = null, ?string $ref = null, ?string $titulo = null): never {
        if (!in_array($codigo, self::CODIGOS, true)) $codigo = 500;
        if (!headers_sent()) {
            http_response_code($codigo);
            header("Content-Type: text/html; charset=UTF-8");
            header("Cache-Control: no-store");
            header("X-Robots-Tag: noindex");
        }
        try {
            echo self::html($codigo, $mensaje, $ref, $titulo);
        } catch (\Throwable) {
            echo paginaErrorNeutra($ref ?? "", $codigo);
        }
        exit;
    }
}
