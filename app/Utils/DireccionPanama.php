<?php
/**
 * DireccionPanama — Validación de la dirección de entrega y del teléfono de contacto.
 *
 * Qué comprueba: que provincia, distrito y corregimiento existan y encajen (contra la base, nunca contra lo que diga el
 * formulario), el largo, los caracteres permitidos y reglas contra el texto basura.
 * Qué NO puede comprobar: que la calle, la casa o la barriada existan, ni que el teléfono sea de esa persona. "Calle Falsa 123"
 * pasa. El filtro solo corta lo evidente (símbolos, letras repetidas, teclas seguidas, relleno como "ninguno").
 * Las letras se cuentan sobre todo el campo, no por palabra: "Calle 50", "Vía España" o "Av. 4ta Sur" son válidas.
 */
class DireccionPanama {
    public const CAMPOS_TEXTO = ["calle", "casa", "barriada", "referencia"];

    // [mínimo de caracteres, máximo, mínimo de letras en todo el campo]
    private const LIMITES = [
        "calle"      => [3, 80, 3],
        "casa"       => [1, 60, 0],
        "barriada"   => [3, 80, 3],
        "referencia" => [3, 120, 3],
    ];
    private const ETIQUETAS = ["calle" => "La calle", "casa" => "La casa o edificio", "barriada" => "La barriada o urbanización", "referencia" => "El punto de referencia"];

    // Secuencias seguidas de teclado que no son parte de una dirección (cuatro letras seguidas)
    // (se buscan dentro de cada palabra; "wert" y "erty" no van porque salen en palabras reales como "Tower" o "Liberty")
    private const TECLADO = ["asdf", "sdfg", "dfgh", "fghj", "ghjk", "hjkl", "qwer", "rtyu", "tyui", "yuio", "uiop", "zxcv", "xcvb", "cvbn", "vbnm"];
    // Relleno que se escribe para saltarse el campo; se compara el campo ENTERO (sin acentos ni símbolos), así "Calle Prueba" pasa
    private const RELLENO = ["xx", "xxx", "xxxx", "na", "nd", "sd", "no", "nose", "ninguno", "ninguna", "nada", "noaplica", "prueba", "pruebas", "test", "testing",
        "asd", "abc", "abcd", "none", "null", "vacio", "desconocido", "0", "00", "000", "0000"];

    /**
     * Valida y normaliza la dirección. $in: provincia, distrito y corregimiento (códigos) y calle, casa, barriada y referencia (texto).
     * @return array{ok:bool,errores:array<string,string>,datos:?array}
     *   datos: los códigos y nombres de la división, los cuatro textos limpios y "texto" (la dirección compuesta en una línea)
     */
    public static function validar(array $in, GeoPanamaModel $geo): array {
        $errores = [];
        $t = [];
        foreach (self::CAMPOS_TEXTO as $c) {
            $v = self::limpiar($in[$c] ?? "");
            $e = self::errorTexto($c, $v);
            if ($e !== "") $errores[$c] = $e;
            $t[$c] = $v;
        }

        $cod = fn(string $k) => is_string($in[$k] ?? null) ? trim($in[$k]) : "";
        $geoRes = $geo->resolver($cod("provincia"), $cod("distrito"), $cod("corregimiento"));
        if (isset($geoRes["error"])) {
            $campo = $geoRes["error"] === "combinacion" ? "corregimiento" : $geoRes["error"];
            $errores[$campo] = match ($geoRes["error"]) {
                "provincia"     => "Elige la provincia o comarca.",
                "distrito"      => "Elige el distrito.",
                "corregimiento" => "Elige el corregimiento.",
                default         => "Esa combinación de provincia, distrito y corregimiento no existe. Elígela de nuevo de las listas.",
            };
        }
        if ($errores) return ["ok" => false, "errores" => $errores, "datos" => null];

        $datos = $geoRes + [
            "calle" => $t["calle"], "casa" => $t["casa"], "barriada" => $t["barriada"],
            "referencia" => $t["referencia"] === "" ? null : $t["referencia"],
        ];
        $datos["texto"] = self::texto($datos);
        return ["ok" => true, "errores" => [], "datos" => $datos];
    }

    /** Dirección compuesta en una línea: «Calle, casa, barriada, corregimiento, distrito, provincia. Ref.: …» (sin las unidades implícitas) */
    public static function texto(array $d): string {
        $partes = array_filter([
            $d["calle"] ?? null, $d["casa"] ?? null, $d["barriada"] ?? null,
            $d["corregimiento_nombre"] ?? null, $d["distrito_nombre"] ?? null, $d["provincia_nombre"] ?? null,
        ], fn($p) => $p !== null && $p !== "");
        $t = implode(", ", $partes);
        $ref = (string)($d["referencia"] ?? "");
        return $ref !== "" ? $t . ". Ref.: " . $ref : $t;
    }

    /** Columnas de pedidos para la copia fija (los nombres, no los códigos) más el texto compuesto */
    public static function paraPedido(array $d): array {
        return [
            "cliente_direccion" => $d["texto"] ?? self::texto($d),
            "entrega_provincia" => $d["provincia_nombre"], "entrega_distrito" => $d["distrito_nombre"], "entrega_corregimiento" => $d["corregimiento_nombre"],
            "entrega_calle" => $d["calle"], "entrega_casa" => $d["casa"], "entrega_barriada" => $d["barriada"], "entrega_referencia" => $d["referencia"],
        ];
    }

    /** Columnas de usuarios (dirección del perfil, por códigos) */
    public static function paraUsuario(array $d): array {
        return [
            "dir_provincia" => $d["provincia"], "dir_distrito" => $d["distrito"], "dir_corregimiento" => $d["corregimiento"],
            "dir_calle" => $d["calle"], "dir_casa" => $d["casa"], "dir_barriada" => $d["barriada"], "dir_referencia" => $d["referencia"],
        ];
    }

    // ── Texto ────────────────────────────────────────────────────────

    /** Recorta y junta los espacios; lo que no es texto queda vacío y el UTF-8 inválido se marca con un carácter de control para rechazarlo */
    private static function limpiar(mixed $v): string {
        if (!is_string($v)) return "";
        if (!mb_check_encoding($v, "UTF-8")) return "\0";
        $v = trim(preg_replace('/\s+/u', " ", $v));
        return $v;
    }

    private static function errorTexto(string $campo, string $v): string {
        [$min, $max, $minLetras] = self::LIMITES[$campo];
        $nombre = self::ETIQUETAS[$campo];
        if ($v === "") return $campo === "referencia" ? "" : "$nombre es obligatorio.";
        if (preg_match('/\p{C}/u', $v)) return "$nombre tiene caracteres no permitidos.";
        $largo = mb_strlen($v);
        if ($largo < $min) return "$nombre es demasiado corto.";
        if ($largo > $max) return "$nombre es demasiado largo (máximo $max caracteres).";
        if (!preg_match('/^[\p{L}\p{N} .,#\-\/°ºª\'’()]+$/u', $v)) return "$nombre solo puede llevar letras, números, espacios y . , # - / ° ( ).";

        $plano   = self::plano($v);
        $letras  = preg_replace('/[^a-z]/', "", $plano);
        $alfanum = preg_replace('/[^a-z0-9]/', "", $plano);
        if ($alfanum === "") return "$nombre no tiene letras ni números.";
        if (mb_strlen(preg_replace('/\P{L}/u', "", $v)) < $minLetras) return "$nombre debe tener al menos $minLetras letras.";
        if (in_array($alfanum, self::RELLENO, true)) return "$nombre no es una dirección válida.";
        if (preg_match('/(\p{L})\1{3,}/iu', $v)) return "$nombre tiene letras repetidas que no parecen una dirección.";

        $n = strlen($letras);
        if ($n >= 5) {
            $conteo = count_chars($letras, 1);
            if (count($conteo) < 3) return "$nombre no parece una dirección.";
            if (max($conteo) / $n > 0.6) return "$nombre tiene letras repetidas que no parecen una dirección.";
        }
        if ($n >= 4 && !preg_match('/[aeiou]/', $letras)) return "$nombre no parece una dirección: revísalo.";
        foreach (preg_split('/[^a-z]+/', $plano, -1, PREG_SPLIT_NO_EMPTY) as $palabra)
            foreach (self::TECLADO as $seq) if (str_contains($palabra, $seq)) return "$nombre no parece una dirección: revísalo.";
        return "";
    }

    /** Minúsculas y sin acentos, para comparar (la ñ cuenta como n) */
    private static function plano(string $s): string {
        $s = mb_strtolower($s, "UTF-8");
        $n = class_exists("Normalizer") ? Normalizer::normalize($s, Normalizer::FORM_D) : false;
        return $n !== false ? preg_replace('/\p{Mn}/u', "", $n) : strtr($s, ["á" => "a", "é" => "e", "í" => "i", "ó" => "o", "ú" => "u", "ü" => "u", "ñ" => "n"]);
    }

    // ── Teléfono ─────────────────────────────────────────────────────

    /**
     * Teléfono de Panamá: celular de 8 dígitos que empieza por 6 o fijo de 7 dígitos (sin restringir el primero), con +507, 507 o
     * 00507 al inicio, espacios, guiones, puntos o paréntesis opcionales. Devuelve el número normalizado («6123-4567» o «123-4567»)
     * o null si no vale. Rechaza dígitos todos iguales y la escalera 1234567 / 12345678.
     */
    public static function telefono(mixed $v): ?string {
        if (!is_string($v) || !mb_check_encoding($v, "UTF-8")) return null;
        $v = trim($v);
        if (!preg_match('/^[+\d(][\d\s\-.()]*$/', $v)) return null;
        $d = preg_replace('/\D/', "", $v);
        if (str_starts_with($v, "+")) {
            if (!str_starts_with($d, "507")) return null;
            $d = substr($d, 3);
        } elseif (str_starts_with($d, "00507")) $d = substr($d, 5);
        elseif (str_starts_with($d, "507") && in_array(strlen($d), [10, 11], true)) $d = substr($d, 3);
        if (!preg_match('/^(?:6\d{7}|\d{7})$/', $d)) return null;
        if (preg_match('/^(\d)\1+$/', $d) || str_contains("0123456789", $d) || str_contains("9876543210", $d)) return null;
        return strlen($d) === 8 ? substr($d, 0, 4) . "-" . substr($d, 4) : substr($d, 0, 3) . "-" . substr($d, 3);
    }
}
