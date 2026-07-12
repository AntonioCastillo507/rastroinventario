<?php
/**
 * objImagenes — Clase para tratamiento y redimensionado de imágenes con GD.
 * Estructura basada en los materiales de la Prof. Ing. Irina Fong.
 * Usada en: ParteController al subir imágenes de piezas.
 */
class objImagenes {
    public string  $rutaImg      = "";
    public string  $ImgNombre    = "";
    public string  $ImgTipo      = "";
    public int     $EscalaThumb  = 1;
    public int     $numImagen    = 1;
    public int     $id           = 0;
    public string  $dir_Thumb    = "";
    public string  $dir_Orig     = "";
    public int     $calidad      = 85;
    public int     $thumAncho    = 420;
    public int     $thumAlto     = 315;
    private ?string $imgNombreThumb = null;

    public function __construct(
        string $rutaImg, string $nombre, string $tipo,
        int $escala, int $num, int $id,
        string $dirThumb = "", string $dirOrig = ""
    ) {
        $this->rutaImg     = $rutaImg;
        $this->ImgNombre   = $nombre;
        $this->ImgTipo     = $tipo;
        $this->EscalaThumb = $escala;
        $this->numImagen   = $num;
        $this->id          = $id;
        $this->dir_Thumb   = $dirThumb ?: UPLOAD_PATH."thumbnails".DIRECTORY_SEPARATOR;
        $this->dir_Orig    = $dirOrig  ?: UPLOAD_PATH."parts".DIRECTORY_SEPARATOR;
    }

    /** Obtiene el tipo MIME real del archivo. */
    public function getTipoImagen(string $archivo): string {
        return @mime_content_type($archivo) ?: "image/jpeg";
    }

    /** Calcula ancho y alto del thumbnail manteniendo proporción. */
    public function redimensionarImagen(int $ancho, int $alto): array {
        // Las imágenes pueden ser Verticales u Horizontales
        $max_ancho = $this->thumAncho;
        $max_alto  = $this->thumAlto;

        $x_radio = $max_ancho / $ancho;
        $y_radio = $max_alto  / $alto;

        if ($ancho >= $alto) {
            // Imagen HORIZONTAL: se modifica el alto
            $thumAlto  = abs($x_radio * $alto);
            $thumAlto  = (int) round($thumAlto);
            $thumAncho = $max_ancho;
        } else {
            // Imagen VERTICAL: se modifica el ancho
            $thumAncho = abs($y_radio * $ancho);
            $thumAncho = (int) round($thumAncho);
            $thumAlto  = $max_alto;
        }
        return ["ancho" => $thumAncho, "alto" => $thumAlto];
    }

    /** Crea el thumbnail redimensionado y lo guarda. Devuelve el nombre del archivo. */
    public function crear_thumbnail(string $archivoOrigen): ?string {
        $tipo = $this->getTipoImagen($archivoOrigen);

        // Cargar imagen según tipo
        $img = match($tipo) {
            "image/png"  => @imagecreatefrompng($archivoOrigen),
            "image/gif"  => @imagecreatefromgif($archivoOrigen),
            "image/webp" => @imagecreatefromwebp($archivoOrigen),
            default      => @imagecreatefromjpeg($archivoOrigen),
        };
        if (!$img) return null;

        $ancho = imagesx($img);
        $alto  = imagesy($img);

        // Redimensionar con respecto al ancho y alto de la imagen vs el que deseamos
        $dims = $this->redimensionarImagen($ancho, $alto);

        // CREAR UN LIENZO: imagecreatetruecolor soporta millones de colores (24 bits)
        $thumb = imagecreatetruecolor($dims["ancho"], $dims["alto"]);

        // Preservar transparencia en PNG
        if ($tipo === "image/png") {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        // REDIMENSIONAR: imagen original copiándola en el lienzo. Se reajustará al nuevo tamaño
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $dims["ancho"], $dims["alto"], $ancho, $alto);

        // Nombre del thumbnail
        $this->imgNombreThumb = pathinfo($this->ImgNombre, PATHINFO_FILENAME)."_thumb.jpg";
        $destino = $this->dir_Thumb.$this->imgNombreThumb;

        // Guardar según tipo (Imagejpeg / Imagegif / Imagepng)
        switch ($tipo) {
            case "image/jpeg": imagejpeg($thumb, $destino, $this->calidad); break;
            case "image/gif":  imagegif($thumb,  $destino); break;
            case "image/png":  imagepng($thumb,  $destino, 9); break;
            default:           imagejpeg($thumb, $destino, $this->calidad);
        }

        // Liberar memoria
        imagedestroy($img);
        imagedestroy($thumb);

        return $this->imgNombreThumb;
    }

    public function getNombreThumb(): ?string { return $this->imgNombreThumb; }
}
