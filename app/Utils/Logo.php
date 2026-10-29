<?php
/**
 * Logo — el logo de la marca como SVG en línea, para que tome el color del tema (fill="currentColor").
 * El SVG se lee de assets/ (el mismo nombre que el logo PNG de la marca, con extensión .svg) y SIEMPRE se sanea
 * antes de imprimirlo: se reconstruye solo con etiquetas y atributos de una lista blanca, así que nada de lo que
 * traiga el archivo (scripts, atributos on*, enlaces, estilos, entidades externas) llega a la página.
 * Si falta, pesa de más o no se puede sanear, devuelve "" y la vista usa el PNG.
 *
 * Perfil «marca» (saneaMarca): el mismo saneador para los logos de las marcas de autos que sube el administrador.
 * Admite además elipses, líneas, trazos y transform, y hasta 100 KB. Lo que se guarda es el SVG reconstruido, no el subido.
 */
final class Logo {
    public const MAX_BYTES = 10240;
    public const MAX_BYTES_MARCA = 102400;
    private const MAX_RUTA = 20000; // largo máximo del atributo d de un trazado

    /** Etiqueta => atributos permitidos */
    private const PERMITIDO = [
        "svg"     => ["viewBox", "preserveAspectRatio"],
        "g"       => ["fill", "fill-rule", "opacity"],
        "path"    => ["d", "fill", "fill-rule", "clip-rule", "opacity"],
        "circle"  => ["cx", "cy", "r", "fill", "opacity"],
        "rect"    => ["x", "y", "width", "height", "rx", "ry", "fill", "opacity"],
        "polygon" => ["points", "fill", "fill-rule", "opacity"],
    ];

    private const TRAZO = ["stroke", "stroke-width", "stroke-linecap", "stroke-linejoin", "stroke-miterlimit", "fill-opacity", "stroke-opacity", "transform"];

    /** Perfil «marca»: Etiqueta => atributos permitidos (los de PERMITIDO más trazos y transform) */
    private const PERMITIDO_MARCA = [
        "svg"      => ["viewBox", "preserveAspectRatio"],
        "g"        => ["fill", "fill-rule", "opacity", ...self::TRAZO],
        "path"     => ["d", "fill", "fill-rule", "clip-rule", "opacity", ...self::TRAZO],
        "circle"   => ["cx", "cy", "r", "fill", "opacity", ...self::TRAZO],
        "ellipse"  => ["cx", "cy", "rx", "ry", "fill", "opacity", ...self::TRAZO],
        "rect"     => ["x", "y", "width", "height", "rx", "ry", "fill", "opacity", ...self::TRAZO],
        "line"     => ["x1", "y1", "x2", "y2", "fill", "opacity", ...self::TRAZO],
        "polyline" => ["points", "fill", "opacity", ...self::TRAZO],
        "polygon"  => ["points", "fill", "fill-rule", "opacity", ...self::TRAZO],
    ];

    /** SVG saneado del logo de la marca, listo para imprimir en línea; "" si no hay uno utilizable */
    public static function enLinea(string $clase = "marca-logo"): string {
        $ruta = (string)Marca::get("logo");
        if ($ruta === "") return "";
        $svg = preg_replace('/\.[a-z0-9]+$/i', ".svg", ltrim($ruta, "/"));
        $archivo = BASE_PATH . "/" . $svg;
        if (!is_file($archivo) || filesize($archivo) > self::MAX_BYTES) return "";
        return self::sanear((string)file_get_contents($archivo), $clase) ?? "";
    }

    /** SVG de un logo de marca de autos reconstruido con el perfil «marca»; null si no sirve */
    public static function saneaMarca(string $svg, string $clase = "marca-logo"): ?string {
        return self::sanear($svg, $clase, true);
    }

    /** Devuelve el SVG reconstruido con solo lo permitido, o null si no sirve (no es SVG, tiene DOCTYPE, no tiene viewBox...) */
    public static function sanear(string $svg, string $clase = "marca-logo", bool $marca = false): ?string {
        // El límite va antes de parsear nada: un archivo enorme no llega a libxml
        if ($svg === "" || strlen($svg) > ($marca ? self::MAX_BYTES_MARCA : self::MAX_BYTES)) return null;
        // Sin DOCTYPE ni entidades: son la puerta de las entidades externas y de la expansión
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $svg)) return null;
        $previo = libxml_use_internal_errors(true);
        $origen = new DOMDocument();
        $ok = $origen->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS); // sin LIBXML_NOENT: las entidades no se sustituyen
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        $raiz = $ok ? $origen->documentElement : null;
        if (!$raiz || strtolower($raiz->localName) !== "svg") return null;

        $destino = new DOMDocument("1.0", "UTF-8");
        $nuevo = self::copiar($raiz, $destino, $marca);
        if (!$nuevo || !$nuevo->hasAttribute("viewBox")) return null;
        if (!preg_match('/^\s*-?[\d.]+[\s,]+-?[\d.]+[\s,]+[\d.]+[\s,]+[\d.]+\s*$/', $nuevo->getAttribute("viewBox"))) return null;
        $destino->appendChild($nuevo);
        $nuevo->setAttribute("xmlns", "http://www.w3.org/2000/svg");
        $nuevo->setAttribute("class", $clase);
        $nuevo->setAttribute("aria-hidden", "true");
        $nuevo->setAttribute("focusable", "false");
        $hayForma = false;
        foreach (["path", "circle", "rect", "polygon", "ellipse", "line", "polyline"] as $forma) {
            if ($nuevo->getElementsByTagName($forma)->length) { $hayForma = true; break; }
        }
        if (!$hayForma) return null;
        return trim((string)$destino->saveXML($nuevo));
    }

    private static function copiar(DOMElement $e, DOMDocument $doc, bool $marca): ?DOMElement {
        $nombre = strtolower($e->localName);
        $tabla = $marca ? self::PERMITIDO_MARCA : self::PERMITIDO;
        if (!isset($tabla[$nombre])) return null; // etiqueta fuera de la lista: se descarta con todo lo que lleva dentro
        $n = $doc->createElement($nombre);
        foreach ($tabla[$nombre] as $attr) {
            if (!$e->hasAttribute($attr)) continue;
            $v = trim($e->getAttribute($attr));
            if (self::valorValido($nombre, $attr, $v)) $n->setAttribute($attr, $v);
        }
        if ($nombre === "path" && !$n->hasAttribute("d")) return null;
        foreach ($e->childNodes as $hijo) {
            if ($hijo instanceof DOMElement && ($c = self::copiar($hijo, $doc, $marca))) $n->appendChild($c);
        }
        return $n;
    }

    private static function valorValido(string $etiqueta, string $attr, string $v): bool {
        return match ($attr) {
            "d" => strlen($v) <= self::MAX_RUTA && (bool)preg_match('/^[MmLlHhVvCcSsQqTtAaZz\d\s.,eE+-]*$/', $v),
            "points" => strlen($v) <= self::MAX_RUTA && (bool)preg_match('/^[\d\s.,eE+-]*$/', $v),
            "fill", "stroke" => in_array($v, ["currentColor", "none"], true),   // ningún otro color: el logo toma el del tema
            "fill-rule", "clip-rule" => in_array($v, ["evenodd", "nonzero"], true),
            "stroke-linecap" => in_array($v, ["butt", "round", "square"], true),
            "stroke-linejoin" => in_array($v, ["miter", "round", "bevel"], true),
            "transform" => strlen($v) <= 300 && (bool)preg_match('/^(\s*(matrix|translate|scale|rotate|skewX|skewY)\s*\(\s*[\d\s.,eE+-]+\)\s*)+$/', $v),
            "preserveAspectRatio" => (bool)preg_match('/^[a-zA-Z\s]{1,40}$/', $v),
            "viewBox", "cx", "cy", "r", "x", "y", "width", "height", "rx", "ry", "x1", "y1", "x2", "y2",
            "opacity", "fill-opacity", "stroke-opacity", "stroke-width", "stroke-miterlimit" => (bool)preg_match('/^[\d\s.,eE+-]+$/', $v),
            default => false,
        };
    }
}
