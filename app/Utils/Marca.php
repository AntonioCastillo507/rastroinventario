<?php
/**
 * Marca — acceso único a los datos del negocio de app/Config/marca.php (nombre, logo, contacto, textos, impuesto).
 * Si falta una clave, devuelve un valor neutro y deja una línea en el registro de errores; nunca rompe la página.
 * Las vistas escapan con Marca::h(); el resto del código usa Marca::get().
 */
final class Marca {
    /** Valores neutros para cuando falta una clave o el archivo entero */
    private const DEFECTOS = [
        "nombre" => "Mi tienda", "eslogan" => "", "descripcion" => "", "logo" => "",
        "telefono" => "", "correo" => "", "direccion" => "", "ciudad" => "", "pais" => "",
        "horario" => [], "redes" => [],
        "portada" => [
            "titulo_pagina" => "", "etiqueta" => "", "titulo" => "", "titulo_resalte" => "", "subtitulo" => "", "buscador" => "",
            "secciones_titulo" => "", "secciones_resalte" => "", "secciones_subtitulo" => "",
            "destacadas_titulo" => "", "destacadas_resalte" => "", "destacadas_subtitulo" => "",
        ],
        "nosotros" => ["titulo" => "", "titulo_resalte" => "", "parrafos" => [], "imagen" => "", "destacados" => [], "destacados_visibles" => false],
        "contacto" => ["titulo" => "Contacto", "intro" => ""],
        "impuesto" => ["nombre" => "Impuesto", "tasa" => 0.0, "descripcion" => ""],
        "pie" => "© {anio} {nombre}",
    ];

    private static ?array $datos = null;
    private static array $avisadas = [];

    /** Todos los datos: los del archivo, y lo que falte, de los valores neutros */
    public static function todo(): array {
        if (self::$datos === null) {
            $archivo = BASE_PATH . "/app/Config/marca.php";
            $leidos = is_file($archivo) ? require $archivo : [];
            if (!is_array($leidos)) $leidos = [];
            self::$datos = $leidos;
        }
        return self::$datos;
    }

    /** Valor de una clave con puntos ("impuesto.tasa"). $defecto solo cuenta si tampoco hay valor neutro. */
    public static function get(string $clave, mixed $defecto = ""): mixed {
        $valor = self::buscar(self::todo(), $clave, $hay);
        if ($hay) return $valor;
        if (!isset(self::$avisadas[$clave])) {
            self::$avisadas[$clave] = true;
            error_log("[RASTRO] Marca: falta la clave \"$clave\" en app/Config/marca.php; se usa el valor neutro.");
        }
        $neutro = self::buscar(self::DEFECTOS, $clave, $hay);
        return $hay ? $neutro : $defecto;
    }

    /** Texto de una clave, escapado para HTML */
    public static function h(string $clave): string {
        return htmlspecialchars((string)self::get($clave), ENT_QUOTES, "UTF-8");
    }

    /** URL pública de un archivo de la marca (logo, imagen de Quiénes somos); "" si la clave está vacía */
    public static function url(string $clave): string {
        $ruta = ltrim((string)self::get($clave), "/");
        return $ruta === "" ? "" : APP_URL . "/" . $ruta;
    }

    /** Como url(), pero "" también si el archivo no está en disco (así la vista no pinta una imagen rota ni necesita onerror) */
    public static function urlSiExiste(string $clave): string {
        $ruta = ltrim((string)self::get($clave), "/");
        if ($ruta === "" || str_contains($ruta, "..") || !is_file(BASE_PATH . "/" . $ruta)) return "";
        return APP_URL . "/" . $ruta;
    }

    /** Dirección de WhatsApp: la de la entrada de "redes" con el icono "whatsapp" y una dirección http(s) válida; "" si no hay */
    public static function whatsapp(): string {
        foreach (self::redes() as $r) if ($r["icono"] === "whatsapp") return $r["url"];
        return "";
    }

    public static function impuestoTasa(): float {
        return (float)self::get("impuesto.tasa", 0.0);
    }

    /** Porcentaje del impuesto sin ceros de más: 7, 12.5 */
    public static function impuestoPorcentaje(): string {
        return rtrim(rtrim(number_format(self::impuestoTasa() * 100, 2, ".", ""), "0"), ".");
    }

    /** "ITBMS (7%)" */
    public static function impuestoEtiqueta(): string {
        return self::get("impuesto.nombre") . " (" . self::impuestoPorcentaje() . "%)";
    }

    /** Texto del pie con {anio}, {nombre} y {pais} ya sustituidos (sin escapar) */
    public static function pie(): string {
        return strtr((string)self::get("pie"), [
            "{anio}" => date("Y"), "{nombre}" => (string)self::get("nombre"), "{pais}" => (string)self::get("pais"),
        ]);
    }

    /**
     * Redes y mensajería con dirección http(s) válida, en el orden del archivo; las de dirección vacía no salen.
     * @return list<array{nombre:string,url:string,icono:string}>
     */
    public static function redes(): array {
        $out = [];
        foreach ((array)self::get("redes", []) as $r) {
            $url = trim((string)($r["url"] ?? ""));
            $nombre = trim((string)($r["nombre"] ?? ""));
            if ($nombre === "" || !preg_match('~^https?://~i', $url)) continue;
            $icono = preg_replace('/[^a-z0-9-]/', "", strtolower((string)($r["icono"] ?? ""))) ?: "link-45deg";
            $out[] = ["nombre" => $nombre, "url" => $url, "icono" => $icono];
        }
        return $out;
    }

    /** Solo para pruebas: usa estos datos en lugar del archivo; null vuelve al archivo */
    public static function usarParaPruebas(?array $datos): void {
        self::$datos = $datos;
        self::$avisadas = [];
    }

    private static function buscar(array $datos, string $clave, ?bool &$hay): mixed {
        $hay = false;
        $nodo = $datos;
        foreach (explode(".", $clave) as $parte) {
            if (!is_array($nodo) || !array_key_exists($parte, $nodo)) return null;
            $nodo = $nodo[$parte];
        }
        $hay = true;
        return $nodo;
    }
}
