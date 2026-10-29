<?php
/**
 * LogoMarca — logos de las marcas de autos que sube el administrador (PNG, WebP o SVG).
 * Falla cerrado: lo que se guarda lo crea el servidor (GD re-codifica los PNG y WebP; el SVG se reconstruye con
 * Logo::saneaMarca) y se llama marca_<32 hex>.<ext>. Del archivo subido no se usa ni el nombre ni la extensión: el tipo
 * sale del contenido. Los logos se muestran en una sola tinta (máscara), así que los PNG y WebP deben tener transparencia.
 */
final class LogoMarca {
    public const MAX_BYTES_RASTER = 524288;  // 512 KB
    public const MAX_LADO_ENTRADA = 4000;    // px; más grande se rechaza antes de decodificar con GD
    public const LADO_SALIDA      = 600;     // px; lo que sea más grande se reduce
    private const TIPOS = [IMAGETYPE_PNG => ["image/png", "png"], IMAGETYPE_WEBP => ["image/webp", "webp"]];
    private const MIME_SVG = ["image/svg+xml", "text/xml", "application/xml", "text/plain"];

    /** Motivo del último rechazo, para mostrarlo en pantalla */
    public static string $error = "";

    private static function fail(string $msg): ?string { self::$error = $msg; return null; }

    public static function dir(): string { return UPLOAD_PATH . "marcas" . DIRECTORY_SEPARATOR; }

    /** Solo nombres que genera esta clase; cualquier otra cosa ni se sirve ni se borra */
    public static function nombreSeguro(?string $n): bool {
        return $n !== null && preg_match('/^marca_[0-9a-f]{32}\.(png|webp|svg)\z/', $n) === 1;
    }

    public static function existe(?string $n): bool { return self::nombreSeguro($n) && is_file(self::dir() . $n); }

    /** Dirección pública del logo ("" si no hay o el archivo falta) */
    public static function url(?string $n): string {
        return self::existe($n) ? APP_URL . "/uploads/marcas/" . $n : "";
    }

    public static function borrar(?string $n): void {
        if (self::nombreSeguro($n)) @unlink(self::dir() . $n);
    }

    /** Valida el código de error de PHP, que venga de un POST real y procesa el logo. Devuelve el nombre guardado o null (motivo en self::$error). */
    public static function subir(array $file): ?string {
        self::$error = "";
        $code = $file["error"] ?? UPLOAD_ERR_NO_FILE;
        if ($code !== UPLOAD_ERR_OK) {
            return self::fail(match ($code) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "El logo supera el tamaño máximo permitido.",
                UPLOAD_ERR_PARTIAL => "El logo llegó incompleto. Intenta de nuevo.",
                UPLOAD_ERR_NO_FILE => "No se recibió ningún logo.",
                default => "No se pudo recibir el logo en el servidor.",
            });
        }
        $tmp = $file["tmp_name"] ?? null;
        if (!is_string($tmp) || !is_uploaded_file($tmp)) return self::fail("El archivo no llegó por el formulario.");
        return self::procesar($tmp);
    }

    /** Revisa el archivo por contenido y guarda el logo. Devuelve el nombre generado o null. */
    public static function procesar(string $ruta): ?string {
        self::$error = "";
        $bytes = is_file($ruta) ? filesize($ruta) : 0;
        if (!$bytes) return self::fail("El archivo está vacío.");

        $info = @getimagesize($ruta);
        if ($info && isset(self::TIPOS[$info[2]])) return self::raster($ruta, $bytes, $info);
        if ($info) return self::fail("El logo debe ser PNG, WebP o SVG.");
        return self::svg($ruta, $bytes);
    }

    private static function svg(string $ruta, int $bytes): ?string {
        // El límite va antes de leer o parsear nada
        if ($bytes > Logo::MAX_BYTES_MARCA) return self::fail("El SVG pesa más de " . intdiv(Logo::MAX_BYTES_MARCA, 1024) . " KB.");
        if (!in_array((new finfo(FILEINFO_MIME_TYPE))->file($ruta), self::MIME_SVG, true)) return self::fail("El logo debe ser PNG, WebP o SVG.");
        $texto = (string)file_get_contents($ruta);
        $texto = preg_replace('/^\xEF\xBB\xBF/', "", $texto);
        $limpio = Logo::saneaMarca((string)$texto);
        if ($limpio === null) return self::fail("El SVG no se pudo usar: debe ser un SVG sencillo con viewBox y formas (trazados, círculos, rectángulos), sin scripts, estilos, imágenes ni DOCTYPE.");
        return self::escribir($limpio, "svg");
    }

    private static function raster(string $ruta, int $bytes, array $info): ?string {
        if ($bytes > self::MAX_BYTES_RASTER) return self::fail("El logo pesa más de " . intdiv(self::MAX_BYTES_RASTER, 1024) . " KB.");
        [$w, $h, $tipo] = $info;
        [$mime, $ext] = self::TIPOS[$tipo];
        if ((new finfo(FILEINFO_MIME_TYPE))->file($ruta) !== $mime) return self::fail("El contenido del archivo no coincide con una imagen válida.");
        // Antes de gastar memoria en GD
        if ($w < 1 || $h < 1 || max($w, $h) > self::MAX_LADO_ENTRADA) return self::fail("El logo es demasiado grande (máximo " . self::MAX_LADO_ENTRADA . " px de lado).");
        if (!extension_loaded("gd")) return self::fail("El servidor no tiene GD para procesar imágenes.");
        $limite = ini_parse_quantity((string)ini_get("memory_limit"));
        if ($limite > 0 && $w * $h * 5 + 16 * 1048576 > $limite - memory_get_usage()) return self::fail("El logo es demasiado grande para procesarlo.");

        set_error_handler(function (int $n, string $s) {
            if (!(error_reporting() & $n)) return false;
            throw new ErrorException($s, 0, $n);
        });
        $img = $fin = null;
        $nivel = ob_get_level();
        try {
            $img = $tipo === IMAGETYPE_PNG ? imagecreatefrompng($ruta) : imagecreatefromwebp($ruta);
            if (!$img) throw new RuntimeException("no decodifica");
            $lado = max($w, $h);
            if ($lado > self::LADO_SALIDA) {
                $fin = imagescale($img, (int)max(1, round($w * self::LADO_SALIDA / $lado)), (int)max(1, round($h * self::LADO_SALIDA / $lado)), IMG_BICUBIC);
                if (!$fin) throw new RuntimeException("no reduce");
            } else {
                $fin = $img;
            }
            imagealphablending($fin, false);
            imagesavealpha($fin, true);
            $motivo = self::revisarAlfa($fin);
            if ($motivo !== null) return self::fail($motivo);
            ob_start();
            $ok = $tipo === IMAGETYPE_PNG ? imagepng($fin, null, 9) : imagewebp($fin, null, 90);
            $datos = (string)ob_get_clean();
            if (!$ok || $datos === "") throw new RuntimeException("no codifica");
        } catch (Throwable $e) {
            while (ob_get_level() > $nivel) ob_end_clean();
            return self::fail("La imagen está incompleta o dañada.");
        } finally {
            restore_error_handler();
            unset($img, $fin);
        }
        return self::escribir($datos, $ext);
    }

    /** null si hay transparencia real y también algo visible; si no, el motivo del rechazo */
    private static function revisarAlfa(\GdImage $im): ?string {
        $w = imagesx($im); $h = imagesy($im);
        $total = $w * $h; $transp = 0; $visibles = 0;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $a = (imagecolorat($im, $x, $y) >> 24) & 0x7F; // 0 opaco .. 127 transparente
                if ($a >= 64) $transp++; else $visibles++;
            }
        }
        if ($visibles < $total * 0.005) return "El logo está vacío o es casi transparente.";
        if ($transp < $total * 0.01) return "El logo necesita fondo transparente (un PNG o WebP con fondo sólido se vería como un bloque). Exporta el logo sin fondo.";
        return null;
    }

    /** Escribe los bytes con un nombre generado por el servidor; null si no se pudo */
    private static function escribir(string $datos, string $ext): ?string {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return self::fail("No se pudo crear la carpeta de logos.");
        $nombre = "marca_" . bin2hex(random_bytes(16)) . "." . $ext;
        if (file_put_contents($dir . $nombre, $datos, LOCK_EX) === false) return self::fail("No se pudo guardar el logo.");
        return $nombre;
    }
}
