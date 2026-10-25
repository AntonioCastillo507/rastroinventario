<?php
/**
 * Logo — el logo de la marca como SVG en línea, para que tome el color del tema (fill="currentColor").
 * El SVG se lee de assets/ (el mismo nombre que el logo PNG de la marca, con extensión .svg) y SIEMPRE se sanea
 * antes de imprimirlo: se reconstruye solo con etiquetas y atributos de una lista blanca, así que nada de lo que
 * traiga el archivo (scripts, atributos on*, enlaces, estilos, entidades externas) llega a la página.
 * Si falta, pesa de más o no se puede sanear, devuelve "" y la vista usa el PNG.
 */
final class Logo {
    public const MAX_BYTES = 10240;
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

    /** SVG saneado del logo de la marca, listo para imprimir en línea; "" si no hay uno utilizable */
    public static function enLinea(string $clase = "marca-logo"): string {
        $ruta = (string)Marca::get("logo");
        if ($ruta === "") return "";
        $svg = preg_replace('/\.[a-z0-9]+$/i', ".svg", ltrim($ruta, "/"));
        $archivo = BASE_PATH . "/" . $svg;
        if (!is_file($archivo) || filesize($archivo) > self::MAX_BYTES) return "";
        return self::sanear((string)file_get_contents($archivo), $clase) ?? "";
    }

    /** Devuelve el SVG reconstruido con solo lo permitido, o null si no sirve (no es SVG, tiene DOCTYPE, no tiene viewBox...) */
    public static function sanear(string $svg, string $clase = "marca-logo"): ?string {
        if ($svg === "" || strlen($svg) > self::MAX_BYTES) return null;
        // Sin DOCTYPE ni entidades: son la puerta de las entidades externas y de la expansión
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $svg)) return null;
        $previo = libxml_use_internal_errors(true);
        $origen = new DOMDocument();
        $ok = $origen->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        $raiz = $ok ? $origen->documentElement : null;
        if (!$raiz || strtolower($raiz->localName) !== "svg") return null;

        $destino = new DOMDocument("1.0", "UTF-8");
        $nuevo = self::copiar($raiz, $destino);
        if (!$nuevo || !$nuevo->hasAttribute("viewBox")) return null;
        if (!preg_match('/^\s*-?[\d.]+[\s,]+-?[\d.]+[\s,]+[\d.]+[\s,]+[\d.]+\s*$/', $nuevo->getAttribute("viewBox"))) return null;
        $destino->appendChild($nuevo);
        $nuevo->setAttribute("xmlns", "http://www.w3.org/2000/svg");
        $nuevo->setAttribute("class", $clase);
        $nuevo->setAttribute("aria-hidden", "true");
        $nuevo->setAttribute("focusable", "false");
        if (!$nuevo->getElementsByTagName("path")->length && !$nuevo->getElementsByTagName("circle")->length
            && !$nuevo->getElementsByTagName("rect")->length && !$nuevo->getElementsByTagName("polygon")->length) return null;
        return trim((string)$destino->saveXML($nuevo));
    }

    private static function copiar(DOMElement $e, DOMDocument $doc): ?DOMElement {
        $nombre = strtolower($e->localName);
        if (!isset(self::PERMITIDO[$nombre])) return null; // etiqueta fuera de la lista: se descarta con todo lo que lleva dentro
        $n = $doc->createElement($nombre);
        foreach (self::PERMITIDO[$nombre] as $attr) {
            if (!$e->hasAttribute($attr)) continue;
            $v = trim($e->getAttribute($attr));
            if (self::valorValido($nombre, $attr, $v)) $n->setAttribute($attr, $v);
        }
        if ($nombre === "path" && !$n->hasAttribute("d")) return null;
        foreach ($e->childNodes as $hijo) {
            if ($hijo instanceof DOMElement && ($c = self::copiar($hijo, $doc))) $n->appendChild($c);
        }
        return $n;
    }

    private static function valorValido(string $etiqueta, string $attr, string $v): bool {
        return match ($attr) {
            "d" => strlen($v) <= self::MAX_RUTA && (bool)preg_match('/^[MmLlHhVvCcSsQqTtAaZz\d\s.,eE+-]*$/', $v),
            "points" => strlen($v) <= self::MAX_RUTA && (bool)preg_match('/^[\d\s.,eE+-]*$/', $v),
            "fill" => in_array($v, ["currentColor", "none"], true),   // ningún otro color: el logo toma el del tema
            "fill-rule", "clip-rule" => in_array($v, ["evenodd", "nonzero"], true),
            "preserveAspectRatio" => (bool)preg_match('/^[a-zA-Z\s]{1,40}$/', $v),
            "viewBox", "cx", "cy", "r", "x", "y", "width", "height", "rx", "ry", "opacity" => (bool)preg_match('/^[\d\s.,eE+-]+$/', $v),
            default => false,
        };
    }
}
