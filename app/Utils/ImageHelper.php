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
    /**
     * Derivadas de cada foto, todas JPEG con el lado mayor al valor indicado (nunca se amplía), completas y sobre fondo
     * blanco: la caja de la pantalla las muestra con object-fit: contain, así que no se estira ni se recorta nada.
     * El original se conserva tal como se re-codificó al subirlo. La de 640 px es la "miniatura" que guarda la base.
     * Carpeta dentro de uploads/ => [lado mayor, sufijo del nombre, calidad JPEG]
     */
    const DERIVADAS = [
        "thumbnails" => [640,  "_th.jpg",   82],  // tarjetas, carrito, panel
        "medium"     => [1200, "_1200.jpg", 85],  // imagen principal del detalle
        "large"      => [2400, "_2400.jpg", 85],  // ampliar (zoom)
    ];
    /** Fuera de esta proporción (ancho/alto) la foto se muestra con bordes en la baldosa 1:1 */
    const PROPORCION_MIN = 0.67;
    const PROPORCION_MAX = 1.5;
    /** Por debajo de este lado mayor la foto se ve borrosa al ampliar */
    const LADO_BAJO = 800;
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
     * Revisa el archivo por contenido, lo decodifica con GD y guarda el original y las derivadas (640, 1200 y 2400 px).
     * Devuelve ["original" => nombre, "thumbnail" => nombre, "avisos" => [...]] o null (motivo en self::$error).
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
        $partsDir  = self::dir("parts");
        if (!is_dir($partsDir)) @mkdir($partsDir, 0755, true);
        $destOrig  = $partsDir . $origName;

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

            // Derivadas de 640, 1200 y 2400 px (si la foto es más chica, a su tamaño)
            $derivadas = self::derivar($img, $base);
            $avisos    = self::avisos($sw, $sh);
            unset($img);

            // Lo escrito tiene que ser una imagen válida; si no, no se queda nada
            foreach (array_merge([$destOrig], array_values($derivadas)) as $f) {
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
            foreach (self::DERIVADAS as $sub => [, $sufijo]) @unlink(self::dir($sub) . $base . $sufijo);
            return self::fail("El archivo no es una imagen válida o está dañado.");
        }

        return ["original" => $origName, "thumbnail" => $base . self::DERIVADAS["thumbnails"][1], "avisos" => $avisos];
    }

    /**
     * Escribe las derivadas de $img (recurso GD) con el nombre $base y devuelve sus rutas por carpeta.
     * La imagen se reduce con el lado mayor en el tamaño pedido, sin recortar ni estirar, y se aplana sobre blanco
     * (una foto con transparencia no queda sobre negro). Si falla una, borra las que ya escribió.
     * @return array<string,string> carpeta => ruta
     */
    private static function derivar(\GdImage $img, string $base): array {
        [$sw, $sh] = [imagesx($img), imagesy($img)];
        $rutas = [];
        try {
            foreach (self::DERIVADAS as $sub => [$lado, $sufijo, $calidad]) {
                $dir = self::dir($sub);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $r   = min($lado / max($sw, $sh), 1);
                $nw  = max(1, (int)round($sw * $r));
                $nh  = max(1, (int)round($sh * $r));
                $dst = imagecreatetruecolor($nw, $nh);
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                imagealphablending($dst, true);
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
                $ruta = $dir . $base . $sufijo;
                if (!imagejpeg($dst, $ruta, $calidad)) throw new RuntimeException("no guarda la derivada $sub");
                $rutas[$sub] = $ruta;
                unset($dst);
            }
        } catch (\Throwable $e) {
            foreach ($rutas as $f) @unlink($f);
            throw $e;
        }
        return $rutas;
    }

    /** Avisos (no bloqueos) sobre una foto de $w x $h: proporción alargada y resolución baja. */
    public static function avisos(int $w, int $h): array {
        $avisos = [];
        $prop = $w / max(1, $h);
        if ($prop < self::PROPORCION_MIN || $prop > self::PROPORCION_MAX) $avisos[] = "Proporción muy alargada: se mostrará con bordes.";
        if (max($w, $h) < self::LADO_BAJO) $avisos[] = "Resolución baja: se verá borrosa al ampliar.";
        return $avisos;
    }

    /**
     * Genera las derivadas de una foto ya guardada en parts/ (la original no se toca ni se mueve).
     * Para las piezas anteriores a las derivadas. Devuelve las carpetas escritas o null si no se pudo.
     * @return list<string>|null
     */
    public static function regenerar(string $original): ?array {
        self::$error = "";
        if (!self::nombreSeguro($original) || !is_file(self::dir("parts") . $original)) return self::fail("La original no existe.");
        $ruta = self::dir("parts") . $original;
        $info = @getimagesize($ruta);
        if (!$info || !isset(self::TYPES[$info[2]])) return self::fail("La original no es una imagen válida.");
        if (!self::cabeEnMemoria($info[0], $info[1], false)) return self::fail("La imagen es demasiado grande para procesarla.");
        $img = @match($info[2]) {
            IMAGETYPE_PNG  => imagecreatefrompng($ruta),
            IMAGETYPE_GIF  => imagecreatefromgif($ruta),
            IMAGETYPE_WEBP => imagecreatefromwebp($ruta),
            default        => imagecreatefromjpeg($ruta),
        };
        if (!$img) return self::fail("La original no se pudo leer.");
        try {
            $rutas = self::derivar($img, pathinfo($original, PATHINFO_FILENAME));
        } catch (\Throwable $e) {
            return self::fail("No se pudieron escribir las derivadas.");
        }
        return array_keys($rutas);
    }

    /** Nombre de la derivada de una original ("parte_x.jpg" + "medium" => "parte_x_1200.jpg") o null si el nombre no vale */
    public static function derivada(?string $original, string $sub): ?string {
        if (!isset(self::DERIVADAS[$sub]) || !self::nombreSeguro($original)) return null;
        return pathinfo((string)$original, PATHINFO_FILENAME) . self::DERIVADAS[$sub][1];
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
     * ¿Hay en disco una imagen con ese nombre? $sub es "parts" (original), "thumbnails" (640), "medium" (1200) o "large" (2400).
     * Las vistas la usan antes de imprimir un <img>: si el archivo falta (borrado a mano, pieza editada,
     * carrito viejo) se muestra el marcador de "sin imagen" en vez de una imagen rota.
     */
    public static function existe(?string $nombre, string $sub): bool {
        return in_array($sub, ["parts", "thumbnails", "medium", "large"], true)
            && self::nombreSeguro($nombre)
            && is_file(self::dir($sub) . $nombre);
    }

    /**
     * Ruta (dentro de uploads/) de la mejor imagen que haya en disco para mostrar una pieza en el tamaño $tam
     * ("thumbnails", "medium" o "large"): la derivada pedida; si falta, la siguiente que exista; "" si no hay ninguna.
     * Así las piezas anteriores a las derivadas y las fotos borradas a mano no dejan una imagen rota.
     */
    public static function fuente(?string $original, ?string $thumb, string $tam = "thumbnails"): string {
        $candidatas = match($tam) {
            "large"  => [["large", self::derivada($original, "large")], ["medium", self::derivada($original, "medium")], ["parts", $original], ["thumbnails", $thumb]],
            "medium" => [["medium", self::derivada($original, "medium")], ["parts", $original], ["thumbnails", $thumb]],
            default  => [["thumbnails", $thumb], ["medium", self::derivada($original, "medium")], ["parts", $original]],
        };
        foreach ($candidatas as [$sub, $nombre]) if (self::existe($nombre, $sub)) return "$sub/$nombre";
        return "";
    }

    /** Borra la original, la miniatura y las derivadas de esa foto (las de 1200 y 2400 salen del nombre de la original). */
    public static function delete(?string $orig, ?string $thumb): void {
        if (self::nombreSeguro($orig))  @unlink(self::dir("parts") . $orig);
        if (self::nombreSeguro($thumb)) @unlink(self::dir("thumbnails") . $thumb);
        foreach (["medium", "large"] as $sub) {
            $d = self::derivada($orig, $sub);
            if ($d !== null) @unlink(self::dir($sub) . $d);
        }
    }
}
