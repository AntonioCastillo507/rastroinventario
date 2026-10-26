<?php
namespace Tests\Support;

/**
 * Fábrica de imágenes y archivos hostiles para las pruebas. Todo se crea al vuelo en la carpeta
 * temporal de la ejecución; no hay binarios en git.
 */
final class Imagenes {
    private static int $n = 0;

    public static function dir(): string {
        $d = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "imgs";
        if (!is_dir($d)) mkdir($d, 0777, true);
        return $d;
    }

    private static function ruta(string $ext): string {
        return self::dir() . DIRECTORY_SEPARATOR . "f" . (++self::$n) . "." . $ext;
    }

    public static function guardar(string $bytes, string $ext = "bin"): string {
        $r = self::ruta($ext);
        file_put_contents($r, $bytes);
        return $r;
    }

    private static function lienzo(int $w, int $h, bool $alfa = false): \GdImage {
        $im = imagecreatetruecolor($w, $h);
        if ($alfa) { imagealphablending($im, false); imagesavealpha($im, true); }
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 60, 40));
        imagefilledrectangle($im, 0, 0, intdiv($w, 2), intdiv($h, 2), imagecolorallocate($im, 20, 120, 220));
        return $im;
    }

    public static function jpeg(int $w = 80, int $h = 60): string {
        $r = self::ruta("jpg");
        imagejpeg(self::lienzo($w, $h), $r, 90);
        return $r;
    }

    public static function png(int $w = 80, int $h = 60): string {
        $r = self::ruta("png");
        imagepng(self::lienzo($w, $h, true), $r);
        return $r;
    }

    public static function gif(int $w = 80, int $h = 60): string {
        $r = self::ruta("gif");
        imagegif(self::lienzo($w, $h), $r);
        return $r;
    }

    public static function webp(int $w = 80, int $h = 60): string {
        $r = self::ruta("webp");
        imagewebp(self::lienzo($w, $h), $r, 80);
        return $r;
    }

    /** Copia solo la primera parte del archivo (descarga cortada) */
    public static function truncado(string $origen, float $fraccion = 0.5): string {
        $b = file_get_contents($origen);
        return self::guardar(substr($b, 0, max(1, (int)(strlen($b) * $fraccion))), pathinfo($origen, PATHINFO_EXTENSION));
    }

    /** Un PNG cuya cabecera declara estas medidas (sin datos reales): prueba el límite sin gastar memoria */
    public static function pngCabecera(int $w, int $h): string {
        $chunk = fn(string $tipo, string $datos) => pack("N", strlen($datos)) . $tipo . $datos . pack("N", crc32($tipo . $datos));
        $bytes = "\x89PNG\r\n\x1a\n" . $chunk("IHDR", pack("NNCCCCC", $w, $h, 8, 2, 0, 0, 0)) . $chunk("IEND", "");
        return self::guardar($bytes, "png");
    }

    /** JPEG válido con una etiqueta EXIF de orientación (1 a 8) escrita a mano */
    public static function jpegConOrientacion(int $orientacion, int $w = 40, int $h = 20): string {
        $b = file_get_contents(self::jpeg($w, $h));
        $tiff = "MM\x00\x2A" . pack("N", 8)                  // cabecera TIFF, big-endian, IFD en el byte 8
              . pack("n", 1)                                 // una entrada
              . pack("nnN", 0x0112, 3, 1) . pack("n", $orientacion) . "\x00\x00" // Orientation, SHORT, 1 valor
              . pack("N", 0);                                // sin más IFD
        $datos = "Exif\x00\x00" . $tiff;
        $app1  = "\xFF\xE1" . pack("n", strlen($datos) + 2) . $datos;
        return self::guardar(substr($b, 0, 2) . $app1 . substr($b, 2), "jpg");
    }

    /** JPEG válido con código PHP pegado al final */
    public static function polyglot(): string {
        return self::guardar(file_get_contents(self::jpeg()) . "\n<?php system(\$_GET['x']); ?>\n", "jpg");
    }

    public static function phpDisfrazado(): string {
        return self::guardar("<?php echo 'hola'; system(\$_GET['x']); ?>", "jpg");
    }

    public static function html(): string {
        return self::guardar("<html><body><script>alert(1)</script></body></html>", "jpg");
    }

    public static function svg(): string {
        return self::guardar('<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>', "svg");
    }

    public static function vacio(): string {
        return self::guardar("", "jpg");
    }

    /** Archivo que supera el límite en bytes, con cabecera de JPEG */
    public static function pesado(int $bytes): string {
        return self::guardar("\xFF\xD8\xFF\xE0" . str_repeat("\x00", $bytes), "jpg");
    }

    /** Cuenta los archivos que dejó ImageHelper en las carpetas de subida de prueba */
    public static function archivosGuardados(): int {
        $n = 0;
        foreach (["parts", "thumbnails", "medium", "large"] as $sub) {
            $d = UPLOAD_PATH . $sub;
            if (is_dir($d)) $n += count(array_diff(scandir($d), [".", ".."]));
        }
        return $n;
    }

    public static function limpiarGuardados(): void {
        foreach (["parts", "thumbnails", "medium", "large"] as $sub) {
            $d = UPLOAD_PATH . $sub;
            if (!is_dir($d)) continue;
            foreach (array_diff(scandir($d), [".", ".."]) as $f) @unlink($d . DIRECTORY_SEPARATOR . $f);
        }
    }
}
