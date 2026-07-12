<?php
/**
 * ImageHelper — Subida de imágenes con triple fallback para Windows/WampServer.
 * Si GD falla, copia el original. Si copy() falla, usa file_get/put_contents.
 */
class ImageHelper {
    const ALLOWED  = ["image/jpeg","image/png","image/gif","image/webp"];
    const MAX_SIZE = 8388608; // 8 MB

    /** Construye rutas absolutas con DIRECTORY_SEPARATOR (Windows-safe). */
private static function dir(string $sub): string {
    return UPLOAD_PATH . $sub . DIRECTORY_SEPARATOR;
}
    public static function upload(array $file, string $prefix = "parte"): ?array {
        // Validaciones básicas
        if (!isset($file["error"]) || $file["error"] !== UPLOAD_ERR_OK) return null;
        if ($file["size"] <= 0 || $file["size"] > self::MAX_SIZE)       return null;

        $mime = @mime_content_type($file["tmp_name"]);
        if (!$mime || !in_array($mime, self::ALLOWED)) {
            // Intentar por extensión si mime falla
            $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
            $mime = match($ext) {
                "jpg","jpeg" => "image/jpeg",
                "png"        => "image/png",
                "gif"        => "image/gif",
                "webp"       => "image/webp",
                default      => null
            };
            if (!$mime) return null;
        }

        $fileExt   = match($mime) {"image/png"=>"png","image/gif"=>"gif","image/webp"=>"webp",default=>"jpg"};
        $uid       = uniqid($prefix."_",true);
        $origName  = $uid.".".$fileExt;
        $thumbName = $uid."_th.jpg";

        $partsDir = self::dir("parts");
        $thumbDir = self::dir("thumbnails");

        // Crear dirs si no existen
        @mkdir($partsDir, 0755, true);
        @mkdir($thumbDir, 0755, true);

        $destOrig  = $partsDir . $origName;
        $destThumb = $thumbDir . $thumbName;

        // ── Guardar archivo original ──────────────────────────────
        $saved = false;
        // Método 1: move_uploaded_file
        if (is_uploaded_file($file["tmp_name"])) {
            $saved = @move_uploaded_file($file["tmp_name"], $destOrig);
        }
        // Método 2: file_get/put_contents (fallback para algunos configs de WampServer)
        if (!$saved) {
            $content = @file_get_contents($file["tmp_name"]);
            if ($content !== false && strlen($content) > 100) {
                $saved = (@file_put_contents($destOrig, $content) !== false);
            }
        }
        if (!$saved || !file_exists($destOrig) || filesize($destOrig) === 0) return null;

        // ── Crear thumbnail ───────────────────────────────────────
        $thumbOk = false;
        if (extension_loaded("gd")) {
            try {
                $src = match($mime) {
                    "image/png"  => @imagecreatefrompng($destOrig),
                    "image/gif"  => @imagecreatefromgif($destOrig),
                    "image/webp" => @imagecreatefromwebp($destOrig),
                    default      => @imagecreatefromjpeg($destOrig),
                };
                if ($src) {
                    [$sw, $sh] = [imagesx($src), imagesy($src)];
                    $r  = min(420/$sw, 315/$sh, 1);
                    $nw = max(1,(int)($sw*$r));
                    $nh = max(1,(int)($sh*$r));
                    $dst = imagecreatetruecolor($nw, $nh);
                    imagefill($dst, 0, 0, imagecolorallocate($dst, 30, 32, 38));
                    imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$sw,$sh);
                    imagejpeg($dst, $destThumb, 88);
                    imagedestroy($src); imagedestroy($dst);
                    $thumbOk = file_exists($destThumb) && filesize($destThumb) > 100;
                }
            } catch(\Throwable $e) { /* GD falló */ }
        }
        // Fallback 1: copy()
        if (!$thumbOk) { @copy($destOrig, $destThumb); $thumbOk = file_exists($destThumb) && filesize($destThumb) > 0; }
        // Fallback 2: file_put_contents
        if (!$thumbOk) {
            $c = @file_get_contents($destOrig);
            if ($c) { @file_put_contents($destThumb, $c); $thumbOk = file_exists($destThumb); }
        }
        // Si todo falló, thumbnail = original
        if (!$thumbOk) { $thumbName = $origName; @copy($destOrig, $thumbDir.$origName); }

        return ["original" => $origName, "thumbnail" => $thumbName];
    }

    public static function delete(?string $orig, ?string $thumb): void {
        if ($orig)  { @unlink(self::dir("parts").$orig);      @unlink(self::dir("thumbnails").$orig); }
        if ($thumb) { @unlink(self::dir("thumbnails").$thumb); }
    }
}
