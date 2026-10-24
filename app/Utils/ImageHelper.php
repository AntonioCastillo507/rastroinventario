<?php
/**
 * ImageHelper — Subida de imágenes. Solo entran imágenes reales (se valida por contenido)
 * y lo que se guarda lo crea GD al volver a codificarlas, nunca el archivo del usuario.
 * Falla cerrado: si algo no es una imagen válida no se guarda nada.
 */
class ImageHelper {
    const MAX_SIZE   = 5242880;   // 5 MB; el límite real es el menor entre este y upload_max_filesize
    const MAX_SIDE   = 6000;      // px por lado
    const MAX_PIXELS = 16000000;  // 16 MP: unos 64 MB en GD, cabe en memory_limit
    const THUMB_W    = 420;
    const THUMB_H    = 315;
    const TYPES      = [
        IMAGETYPE_JPEG => ["image/jpeg", "jpg"],
        IMAGETYPE_PNG  => ["image/png",  "png"],
        IMAGETYPE_GIF  => ["image/gif",  "gif"],
        IMAGETYPE_WEBP => ["image/webp", "webp"],
    ];

    /** Motivo del último rechazo, para mostrarlo en pantalla. */
    public static string $error = "";

    private static function dir(string $sub): string {
        return UPLOAD_PATH . $sub . DIRECTORY_SEPARATOR;
    }

    private static function fail(string $msg): ?array {
        self::$error = $msg;
        return null;
    }

    /** Límite real en bytes: el menor entre MAX_SIZE y upload_max_filesize de PHP. */
    public static function limiteBytes(): int {
        $ini = ini_parse_quantity((string)ini_get("upload_max_filesize"));
        return $ini > 0 ? min(self::MAX_SIZE, $ini) : self::MAX_SIZE;
    }

    /** El límite real escrito para mensajes, por ejemplo "5 MB". */
    public static function limiteTexto(): string {
        $mb = self::limiteBytes() / 1048576;
        return (floor($mb) == $mb ? (int)$mb : round($mb, 1)) . " MB";
    }

    /** Valida el código de error de PHP, que venga de un POST real y procesa la imagen. */
    public static function upload(array $file, string $prefix = "parte"): ?array {
        self::$error = "";
        $code = $file["error"] ?? UPLOAD_ERR_NO_FILE;
        if ($code !== UPLOAD_ERR_OK) {
            return self::fail(match($code) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "La imagen supera el máximo de " . self::limiteTexto() . ".",
                UPLOAD_ERR_PARTIAL  => "La imagen llegó incompleta. Intenta de nuevo.",
                UPLOAD_ERR_NO_FILE  => "No se recibió ninguna imagen.",
                default             => "No se pudo recibir la imagen en el servidor.",
            });
        }
        $tmp = $file["tmp_name"] ?? null;
        if (!is_string($tmp) || !is_uploaded_file($tmp)) return self::fail("El archivo no llegó por el formulario.");
        return self::procesar($tmp, $prefix);
    }

    /**
     * Revisa el archivo por contenido, lo decodifica con GD y guarda original y miniatura nuevos.
     * Devuelve ["original" => nombre, "thumbnail" => nombre] o null (motivo en self::$error).
     */
    public static function procesar(string $ruta, string $prefix = "parte"): ?array {
        self::$error = "";
        $bytes = is_file($ruta) ? filesize($ruta) : 0;
        if (!$bytes)                  return self::fail("El archivo está vacío.");
        if ($bytes > self::limiteBytes()) return self::fail("La imagen supera el máximo de " . self::limiteTexto() . ".");
        if (!extension_loaded("gd"))  return self::fail("El servidor no tiene GD para procesar imágenes.");

        // Cabecera: tipo y medidas sin gastar memoria. La extensión del nombre no se mira nunca.
        $info = @getimagesize($ruta);
        if (!$info || !isset(self::TYPES[$info[2]])) return self::fail("El archivo no es una imagen JPG, PNG, GIF o WebP.");
        [$w, $h, $tipo] = $info;
        [$mime, $ext]   = self::TYPES[$tipo];
        if ($w < 1 || $h < 1 || $w > self::MAX_SIDE || $h > self::MAX_SIDE || $w * $h > self::MAX_PIXELS) {
            return self::fail("La imagen es demasiado grande (máximo " . self::MAX_SIDE . " px por lado y 16 megapíxeles).");
        }
        if ((new finfo(FILEINFO_MIME_TYPE))->file($ruta) !== $mime) return self::fail("El contenido del archivo no coincide con una imagen válida.");

        // Orientación de las fotos de teléfono: GD pierde el EXIF, así que se aplica antes
        $giro = 0;
        if ($tipo === IMAGETYPE_JPEG && function_exists("exif_read_data")) {
            $exif = @exif_read_data($ruta);
            $giro = [3 => 180, 6 => -90, 8 => 90][$exif["Orientation"] ?? 1] ?? 0;
        }
        if (!self::cabeEnMemoria($w, $h, $giro !== 0)) return self::fail("La imagen es demasiado grande para procesarla.");

        $base      = preg_replace('/[^a-z0-9]/i', '', $prefix) . "_" . bin2hex(random_bytes(16));
        $origName  = $base . "." . $ext;
        $thumbName = $base . "_th.jpg";
        $partsDir  = self::dir("parts");
        $thumbDir  = self::dir("thumbnails");
        foreach ([$partsDir, $thumbDir] as $d) if (!is_dir($d)) @mkdir($d, 0755, true);
        $destOrig  = $partsDir . $origName;
        $destThumb = $thumbDir . $thumbName;

        // GD acepta JPEG y GIF cortados como si fueran imágenes enteras: se exige el final del archivo
        if (!self::estaCompleta($ruta, $tipo)) return self::fail("La imagen está incompleta o dañada.");

        // Cualquier aviso de GD (PNG o WebP truncado, corrupto) cuenta como rechazo
        $ok = false;
        set_error_handler(function (int $n, string $s) {
            if (!(error_reporting() & $n)) return false; // respeta el @
            throw new ErrorException($s, 0, $n);
        });
        try {
            $img = match($tipo) {
                IMAGETYPE_PNG  => imagecreatefrompng($ruta),
                IMAGETYPE_GIF  => imagecreatefromgif($ruta),
                IMAGETYPE_WEBP => imagecreatefromwebp($ruta),
                default        => imagecreatefromjpeg($ruta),
            };
            if (!$img) throw new RuntimeException("no decodifica");
            if ($giro) {
                $rot = imagerotate($img, $giro, 0);
                if (!$rot) throw new RuntimeException("no gira");
                $img = $rot;
                unset($rot);
            }
            [$sw, $sh] = [imagesx($img), imagesy($img)];

            // Original re-codificado: no queda nada del archivo subido (metadatos, bytes pegados al final)
            if ($tipo === IMAGETYPE_PNG || $tipo === IMAGETYPE_WEBP) { imagealphablending($img, false); imagesavealpha($img, true); }
            $guardado = match($tipo) {
                IMAGETYPE_PNG  => imagepng($img, $destOrig, 6),
                IMAGETYPE_GIF  => imagegif($img, $destOrig),
                IMAGETYPE_WEBP => imagewebp($img, $destOrig, 85),
                default        => imagejpeg($img, $destOrig, 90),
            };
            if (!$guardado) throw new RuntimeException("no guarda el original");

            // Miniatura JPEG
            $r   = min(self::THUMB_W / $sw, self::THUMB_H / $sh, 1);
            $nw  = max(1, (int)($sw * $r));
            $nh  = max(1, (int)($sh * $r));
            $dst = imagecreatetruecolor($nw, $nh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 30, 32, 38));
            imagealphablending($dst, true);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
            if (!imagejpeg($dst, $destThumb, 88)) throw new RuntimeException("no guarda la miniatura");
            unset($img, $dst);

            // Lo escrito tiene que ser una imagen válida; si no, no se queda nada
            foreach ([$destOrig, $destThumb] as $f) {
                if (filesize($f) <= 0 || !getimagesize($f)) throw new RuntimeException("salida inválida");
            }
            $ok = true;
        } catch (\Throwable $e) {
            $ok = false;
        } finally {
            restore_error_handler();
        }
        if (!$ok) {
            @unlink($destOrig);
            @unlink($destThumb);
            return self::fail("El archivo no es una imagen válida o está dañado.");
        }

        return ["original" => $origName, "thumbnail" => $thumbName];
    }

    /** JPEG: EOI después del último SOS. GIF: termina en el trailer 0x3B. PNG y WebP ya fallan solos en GD. */
    private static function estaCompleta(string $ruta, int $tipo): bool {
        if ($tipo === IMAGETYPE_JPEG) {
            $d = file_get_contents($ruta);
            $sos = $d === false ? false : strrpos($d, "\xFF\xDA");
            return $sos !== false && strpos($d, "\xFF\xD9", $sos) !== false;
        }
        if ($tipo === IMAGETYPE_GIF) {
            $d = file_get_contents($ruta);
            return $d !== false && substr($d, -1) === "\x3B";
        }
        return true;
    }

    /** Estima si GD puede cargar la imagen (4 bytes por píxel, el doble si hay que girarla). */
    private static function cabeEnMemoria(int $w, int $h, bool $gira): bool {
        $limite = ini_parse_quantity((string)ini_get("memory_limit"));
        if ($limite <= 0) return true; // sin límite
        $necesita = $w * $h * 4 * ($gira ? 2 : 1) + 16 * 1048576;
        return $necesita <= $limite - memory_get_usage();
    }

    /** Solo nombres simples generados por esta clase; cualquier otra cosa no se borra. */
    private static function nombreSeguro(?string $n): bool {
        return $n !== null && $n !== "" && $n === basename($n) && !str_contains($n, "..")
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*\.(jpe?g|png|gif|webp)$/i', $n) === 1;
    }

    /**
     * ¿Hay en disco una imagen con ese nombre? $sub es "parts" (original) o "thumbnails" (miniatura).
     * Las vistas la usan antes de imprimir un <img>: si el archivo falta (borrado a mano, pieza editada,
     * carrito viejo) se muestra el marcador de "sin imagen" en vez de una imagen rota.
     */
    public static function existe(?string $nombre, string $sub): bool {
        return in_array($sub, ["parts", "thumbnails"], true)
            && self::nombreSeguro($nombre)
            && is_file(self::dir($sub) . $nombre);
    }

    public static function delete(?string $orig, ?string $thumb): void {
        if (self::nombreSeguro($orig))  @unlink(self::dir("parts") . $orig);
        if (self::nombreSeguro($thumb)) @unlink(self::dir("thumbnails") . $thumb);
    }
}
