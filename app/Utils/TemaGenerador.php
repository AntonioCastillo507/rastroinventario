<?php
/**
 * Genera y valida assets/css/tema.css a partir de un color principal (docs/DESIGN.md, "Personalización").
 * Lo usa bin/generar_tema.php (solo consola) y las pruebas. No toca la web ni la base de datos.
 *
 * Con el dorado de la marca (#FCD535) los tokens son exactamente los aprobados en DESIGN.md. Con
 * cualquier otro color se derivan: texto sobre el principal, estado pulsado, deshabilitado, enlace en
 * claro, principal como texto en oscuro y anillo de foco. Cada derivación se comprueba contra los
 * pares de PARES y, si no llega al contraste mínimo, se corrige y se avisa.
 */
final class TemaGenerador {
    public const DORADO = "#fcd535";

    /** Colores de DESIGN.md (sección colors) con los valores aprobados para el dorado, más los dos anillos de foco por modo */
    public const BASE = [
        "primary" => "#fcd535", "primary-active" => "#f0b90b", "primary-disabled" => "#3a3a1f",
        "ink" => "#181a20", "body" => "#eaecef", "body-on-light" => "#181a20",
        "muted" => "#707a8a", "muted-strong" => "#929aa5",
        "hairline-on-light" => "#eaecef", "hairline-on-dark" => "#2b3139", "border-strong" => "#cdd1d6",
        "canvas-light" => "#ffffff", "canvas-dark" => "#0b0e11",
        "surface-card-dark" => "#1e2329", "surface-elevated-dark" => "#2b3139",
        "surface-soft-light" => "#f7f3e8", "surface-strong-light" => "#f2ecde", "surface-elevated-light" => "#f2ecde",
        "on-primary" => "#181a20", "on-dark" => "#ffffff",
        "trading-up" => "#0ecb81", "trading-down" => "#f6465d",
        "info" => "#3b82f6", "info-ring" => "#3b82f6",
        "primary-text-on-dark" => "#fcd535", "muted-on-light" => "#5e6673", "link-on-light" => "#7a5c00",
        "success" => "#0ecb81", "success-on-light" => "#0a7a4f",
        "danger" => "#f6475e", "danger-on-elevated" => "#f8677a", "danger-on-light" => "#c0233a",
        "warning" => "#f0b90b", "warning-on-light" => "#8a5a00",
        "input-border-dark" => "#707a8a", "input-border-light" => "#838d9c",
        "focus-ring" => "#3b82f6", "focus-ring-dark" => "#3b82f6", "focus-ring-light" => "#3b82f6",
    ];

    /** Tokens que cambian con el color principal */
    public const DERIVADOS = ["primary", "primary-active", "primary-disabled", "on-primary", "primary-text-on-dark", "link-on-light", "focus-ring", "focus-ring-dark", "focus-ring-light"];

    public const RADIOS = ["xs" => "2px", "sm" => "4px", "md" => "6px", "lg" => "8px", "xl" => "12px", "pill" => "9999px", "full" => "9999px"];
    public const ESPACIOS = ["xxs" => "4px", "xs" => "8px", "sm" => "12px", "md" => "16px", "lg" => "24px", "xl" => "32px", "xxl" => "48px", "section" => "80px"];

    public const FUENTE_TEXTO = "Manrope, system-ui, -apple-system, \"Segoe UI\", Roboto, sans-serif";
    public const FUENTE_NUMERICA = "\"IBM Plex Sans\", Manrope, system-ui, sans-serif";

    /** nombre => [familia (t texto, n cifras), tamaño, peso, interlineado, espaciado de letras] */
    public const TIPOGRAFIA = [
        "hero-display" => ["t", "64px", 700, "1.1", "-1px"],
        "hero-display-mobile" => ["t", "36px", 700, "1.15", "-0.5px"],
        "display-lg" => ["t", "48px", 700, "1.1", "-0.5px"],
        "display-md" => ["t", "40px", 600, "1.15", "-0.3px"],
        "display-sm" => ["t", "32px", 600, "1.2", "0"],
        "title-lg" => ["t", "24px", 600, "1.3", "0"],
        "title-md" => ["t", "20px", 600, "1.35", "0"],
        "title-sm" => ["t", "16px", 600, "1.4", "0"],
        "number-display" => ["n", "40px", 700, "1.1", "-0.3px"],
        "number-md" => ["n", "16px", 500, "1.4", "0"],
        "number-sm" => ["n", "14px", 500, "1.4", "0"],
        "body-lg" => ["t", "16px", 400, "1.5", "0"],
        "body-md" => ["t", "14px", 400, "1.5", "0"],
        "body-sm" => ["t", "13px", 400, "1.5", "0"],
        "caption" => ["t", "12px", 500, "1.4", "0"],
        "button" => ["t", "14px", 600, "1", "0"],
        "input-text" => ["t", "16px", 400, "1.4", "0"],
        "nav-link" => ["t", "14px", 500, "1.4", "0"],
    ];

    /** Variable semántica => color de la paleta, en cada modo */
    public const MODOS = [
        "dark" => [
            "canvas" => "canvas-dark", "surface" => "surface-card-dark", "surface-elevated" => "surface-elevated-dark",
            "surface-strong" => "surface-card-dark",
            "text" => "body", "text-strong" => "on-dark", "text-muted" => "muted-strong",
            "hairline" => "hairline-on-dark", "border-strong" => "muted", "input-border" => "input-border-dark",
            "link" => "primary-text-on-dark", "link-hover" => "on-dark", "success-text" => "success", "danger-text" => "danger",
            "danger-text-elevated" => "danger-on-elevated", "warning-text" => "warning",
            "focus-ring" => "focus-ring-dark",
            "btn-primary-bg" => "primary", "btn-primary-bg-active" => "primary-active", "btn-primary-fg" => "on-primary",
            "btn-primary-disabled-bg" => "primary-disabled", "btn-primary-disabled-fg" => "muted", "btn-primary-disabled-border" => "primary-disabled",
            "btn-secondary-bg" => "surface-card-dark", "btn-secondary-fg" => "on-dark", "btn-secondary-border" => "hairline-on-dark",
            "btn-danger-bg" => "danger", "btn-danger-fg" => "ink",
            "btn-success-bg" => "success", "btn-success-fg" => "ink",
        ],
        "light" => [
            "canvas" => "surface-soft-light", "surface" => "canvas-light", "surface-elevated" => "surface-elevated-light",
            "surface-strong" => "surface-strong-light",
            "text" => "body-on-light", "text-strong" => "ink", "text-muted" => "muted-on-light",
            "hairline" => "hairline-on-light", "border-strong" => "border-strong", "input-border" => "input-border-light",
            "link" => "link-on-light", "link-hover" => "ink", "success-text" => "success-on-light", "danger-text" => "danger-on-light",
            "danger-text-elevated" => "danger-on-light", "warning-text" => "warning-on-light",
            "focus-ring" => "focus-ring-light",
            "btn-primary-bg" => "primary", "btn-primary-bg-active" => "primary-active", "btn-primary-fg" => "on-primary",
            "btn-primary-disabled-bg" => "surface-strong-light", "btn-primary-disabled-fg" => "muted-on-light", "btn-primary-disabled-border" => "border-strong",
            "btn-secondary-bg" => "canvas-light", "btn-secondary-fg" => "ink", "btn-secondary-border" => "hairline-on-light",
            "btn-danger-bg" => "danger-on-light", "btn-danger-fg" => "on-dark",
            "btn-success-bg" => "success-on-light", "btn-success-fg" => "on-dark",
        ],
    ];

    /**
     * Pares de contraste que se vigilan en cada modo: [texto o componente, fondo, mínimo, tipo].
     * Mínimo 4,5 para texto normal y 3 para componentes. Los nombres son las variables semánticas de MODOS
     * o de los avisos (alert-*), así que la comprobación se puede hacer sobre el tema.css ya escrito.
     * @return list<array{0:string,1:string,2:float,3:string}>
     */
    public static function pares(): array {
        $p = [];
        foreach (["canvas", "surface", "surface-elevated"] as $f) {
            foreach (["text", "text-strong", "text-muted", "link", "link-hover", "success-text", "warning-text"] as $t) $p[] = [$t, $f, 4.5, "texto"];
            $p[] = ["focus-ring", $f, 3.0, "componente"];
        }
        foreach (["canvas", "surface"] as $f) $p[] = ["danger-text", $f, 4.5, "texto"];
        $p[] = ["danger-text-elevated", "surface-elevated", 4.5, "texto"];
        $p[] = ["text", "surface-strong", 4.5, "texto"];
        $p[] = ["text-muted", "surface-strong", 4.5, "texto"];
        $p[] = ["link", "surface-strong", 4.5, "texto"];
        $p[] = ["input-border", "surface", 3.0, "componente"];
        $p[] = ["input-border", "canvas", 3.0, "componente"];
        $p[] = ["btn-primary-fg", "btn-primary-bg", 4.5, "texto"];
        $p[] = ["btn-primary-fg", "btn-primary-bg-active", 4.5, "texto"];
        $p[] = ["btn-secondary-fg", "btn-secondary-bg", 4.5, "texto"];
        $p[] = ["btn-danger-fg", "btn-danger-bg", 4.5, "texto"];
        $p[] = ["btn-success-fg", "btn-success-bg", 4.5, "texto"];
        foreach (["success", "danger", "warning", "info", "primary"] as $a) $p[] = ["alert-$a-text", "alert-$a-bg", 4.5, "texto"];
        return $p;
    }

    /** Variable de Bootstrap => variable semántica de la que sale su valor en forma R, G, B */
    public const PUENTE_RGB = [
        "--bs-body-bg-rgb" => "canvas", "--bs-body-color-rgb" => "text", "--bs-emphasis-color-rgb" => "text-strong",
        "--bs-secondary-rgb" => "text-muted", "--bs-link-color-rgb" => "link", "--bs-link-hover-color-rgb" => "link-hover",
        "--bs-primary-rgb" => "btn-primary-bg", "--bs-success-rgb" => "success-text", "--bs-danger-rgb" => "danger-text",
        "--bs-warning-rgb" => "link", "--bs-info-rgb" => "text",
    ];

    // ---------------------------------------------------------------- color

    public static function normalizar(string $hex): string {
        $h = strtolower(ltrim(trim($hex), "#"));
        if (preg_match('/^[0-9a-f]{3}$/', $h)) $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        if (!preg_match('/^[0-9a-f]{6}$/', $h)) throw new InvalidArgumentException("Color no válido: $hex (se espera #RRGGBB)");
        return "#" . $h;
    }

    /** @return array{0:int,1:int,2:int} */
    public static function rgb(string $hex): array {
        $h = ltrim(self::normalizar($hex), "#");
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    public static function hex(int $r, int $g, int $b): string {
        return sprintf("#%02x%02x%02x", max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }

    public static function luminancia(string $hex): float {
        $c = array_map(function (int $v): float {
            $v /= 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contraste(string $a, string $b): float {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** @return array{0:float,1:float,2:float} tono 0-360, saturación 0-1, luminosidad 0-1 */
    public static function hsl(string $hex): array {
        [$r, $g, $b] = array_map(fn($v) => $v / 255, self::rgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        if ($d == 0) return [0.0, 0.0, $l];
        $s = $d / (1 - abs(2 * $l - 1));
        $h = match (true) {
            $max == $r => fmod(($g - $b) / $d + 6, 6),
            $max == $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };
        return [$h * 60, $s, $l];
    }

    public static function desdeHsl(float $h, float $s, float $l): string {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match ((int)floor(fmod($h, 360) / 60)) {
            0 => [$c, $x, 0], 1 => [$x, $c, 0], 2 => [0, $c, $x], 3 => [0, $x, $c], 4 => [$x, 0, $c], default => [$c, 0, $x],
        };
        return self::hex((int)round(($r + $m) * 255), (int)round(($g + $m) * 255), (int)round(($b + $m) * 255));
    }

    /** Mezcla $a con $b: $parte de $a (0 a 1) */
    public static function mezclar(string $a, string $b, float $parte): string {
        $x = self::rgb($a);
        $y = self::rgb($b);
        return self::hex((int)round($x[0] * $parte + $y[0] * (1 - $parte)), (int)round($x[1] * $parte + $y[1] * (1 - $parte)), (int)round($x[2] * $parte + $y[2] * (1 - $parte)));
    }

    /** Distancia angular entre dos tonos, 0 a 180 */
    private static function distanciaTono(float $a, float $b): float {
        $d = abs($a - $b);
        return $d > 180 ? 360 - $d : $d;
    }

    /**
     * Mueve la luminosidad de $hex (conservando tono y saturación) en pasos de 0,001 hacia $sentido
     * (+1 aclara, -1 oscurece) hasta que $cumple devuelva true. Devuelve el color o null si se acaba el rango.
     */
    private static function ajustar(string $hex, int $sentido, callable $cumple): ?string {
        [$h, $s, $l] = self::hsl($hex);
        for ($i = 0; $i <= 1000; $i++) {
            $c = self::desdeHsl($h, $s, max(0, min(1, $l + $sentido * 0.001 * $i)));
            if ($cumple($c)) return $c;
        }
        return null;
    }

    // ------------------------------------------------------------- derivación

    /**
     * @return array{paleta:array<string,string>,avisos:list<string>}
     */
    public static function derivar(string $principal): array {
        $p = self::normalizar($principal);
        $b = self::BASE;
        $avisos = [];
        if ($p === self::DORADO) return ["paleta" => $b, "avisos" => []];

        $b["primary"] = $p;
        $oscuras = [$b["canvas-dark"], $b["surface-card-dark"], $b["surface-elevated-dark"]];
        $claras = [$b["surface-soft-light"], $b["canvas-light"], $b["surface-strong-light"], $b["surface-elevated-light"]];

        // Texto sobre el principal: la tinta o blanco, el que dé más contraste (al menos 4,5)
        $conTinta = self::contraste($b["ink"], $p);
        $conBlanco = self::contraste("#ffffff", $p);
        $b["on-primary"] = $conTinta >= $conBlanco ? $b["ink"] : "#ffffff";
        if (max($conTinta, $conBlanco) < 4.5) {
            $b["on-primary"] = self::contraste("#000000", $p) >= self::contraste("#ffffff", $p) ? "#000000" : "#ffffff";
            $avisos[] = sprintf("on-primary: ni la tinta ni el blanco llegan a 4,5:1 sobre %s; se usa %s", $p, $b["on-primary"]);
        }
        $fg = $b["on-primary"];

        // Pulsado: el principal más oscuro (11 puntos de luminosidad); si el texto deja de leerse, se oscurece menos
        [$h, $s, $l] = self::hsl($p);
        $activo = null;
        $delta = 0.11;
        for ($d = $delta; $d >= 0.0; $d -= 0.01) {
            $c = self::desdeHsl($h, $s, max(0, $l - $d));
            if (self::contraste($fg, $c) >= 4.5) { $activo = $c; break; }
        }
        if ($activo === null) {
            $activo = $p;
            $avisos[] = "primary-active: no hay un tono más oscuro con texto legible; queda igual al principal";
        } elseif (abs($d - $delta) > 0.001) {
            $avisos[] = sprintf("primary-active: oscurecer 11 puntos dejaba el texto bajo 4,5:1; se corrige a %s", $activo);
        }
        $b["primary-active"] = $activo;

        // Deshabilitado: el principal mezclado con el lienzo oscuro
        $b["primary-disabled"] = self::mezclar($p, $b["canvas-dark"], 0.2);

        // Enlace sobre claro: se oscurece hasta 4,5:1 sobre todas las superficies claras
        $cumpleClaro = fn(string $c): bool => min(array_map(fn($f) => self::contraste($c, $f), $claras)) >= 4.5;
        $link = $cumpleClaro($p) ? $p : self::ajustar($p, -1, $cumpleClaro);
        if ($link === null) throw new RuntimeException("No hay un tono de $p con 4,5:1 sobre las superficies claras.");
        if ($link !== $p) $avisos[] = sprintf("link-on-light: el principal %s no llega a 4,5:1 sobre claro; se oscurece a %s", $p, $link);
        $b["link-on-light"] = $link;

        // Principal como texto sobre oscuro: se aclara hasta 4,5:1 sobre lienzo, tarjeta y superficie elevada
        $cumpleOscuro = fn(string $c): bool => min(array_map(fn($f) => self::contraste($c, $f), $oscuras)) >= 4.5;
        $texto = $cumpleOscuro($p) ? $p : self::ajustar($p, 1, $cumpleOscuro);
        if ($texto === null) throw new RuntimeException("No hay un tono de $p con 4,5:1 sobre las superficies oscuras.");
        if ($texto !== $p) $avisos[] = sprintf("primary-text-on-dark: el principal %s no llega a 4,5:1 sobre oscuro; se aclara a %s", $p, $texto);
        $b["primary-text-on-dark"] = $texto;

        // Foco: se mantiene el azul de info salvo que el principal esté a menos de 35° del azul
        $cercaDelAzul = self::distanciaTono(self::hsl($p)[0], self::hsl($b["info"])[0]) < 35 && self::hsl($p)[1] > 0.15;
        if ($cercaDelAzul) {
            $b["focus-ring-dark"] = "#ffffff";
            $b["focus-ring-light"] = $b["ink"];
            $b["focus-ring"] = $b["info"];
            $avisos[] = "focus-ring: el principal está a menos de 35° del azul; el foco pasa a blanco en oscuro y tinta en claro";
        }
        return ["paleta" => $b, "avisos" => $avisos];
    }

    // ------------------------------------------------------------------ CSS

    /** Valores de un modo ya resueltos a hex, incluidos los de los avisos (alert-*) */
    public static function valoresModo(array $paleta, string $modo): array {
        $v = [];
        foreach (self::MODOS[$modo] as $nombre => $clave) $v[$nombre] = $paleta[$clave];
        // Aviso (alert de DESIGN.md): fondo de tarjeta, texto normal y el estado en el borde (y en el icono)
        foreach ([
            "success" => $v["success-text"], "danger" => $v["danger-text"], "warning" => $v["warning-text"],
            "info" => $paleta["info"], "primary" => $v["link"],
        ] as $a => $estado) {
            $v["alert-$a-text"] = $v["text"];
            $v["alert-$a-bg"] = $v["surface"];
            $v["alert-$a-border"] = $estado;
        }
        return $v;
    }

    private static function triple(string $hex): string {
        return implode(", ", self::rgb($hex));
    }

    private static function bloqueModo(string $modo, array $paleta): string {
        $v = self::valoresModo($paleta, $modo);
        $o = "";
        $o .= "  color-scheme: $modo;\n";
        foreach (self::MODOS[$modo] as $nombre => $clave) $o .= "  --$nombre: var(--color-$clave);\n";
        $o .= "\n  /* Avisos (alert): fondo de tarjeta, texto normal y el estado en el borde */\n";
        foreach (["success", "danger", "warning", "info", "primary"] as $a) {
            $o .= "  --alert-$a-text: {$v["alert-$a-text"]};\n  --alert-$a-bg: {$v["alert-$a-bg"]};\n  --alert-$a-border: {$v["alert-$a-border"]};\n";
        }
        $o .= "\n  /* Puente con Bootstrap 5.3: sus variables apuntan a los tokens de arriba */\n";
        foreach (self::PUENTE_RGB as $bs => $sem) {
            $hex = $sem === "text" && $bs === "--bs-info-rgb" ? $v["text"] : $v[$sem];
            $o .= "  $bs: " . self::triple($hex) . ";\n";
        }
        $o .= <<<CSS
  --bs-body-bg: var(--canvas);
  --bs-body-color: var(--text);
  --bs-emphasis-color: var(--text-strong);
  --bs-heading-color: var(--text-strong);
  --bs-secondary-color: var(--text-muted);
  --bs-tertiary-color: var(--text-muted);
  --bs-secondary-bg: var(--surface-elevated);
  --bs-tertiary-bg: var(--surface);
  --bs-border-color: var(--hairline);
  --bs-border-color-translucent: var(--hairline);
  --bs-link-color: var(--link);
  --bs-link-hover-color: var(--link-hover);
  --bs-primary: var(--btn-primary-bg);
  --bs-success: var(--success-text);
  --bs-danger: var(--danger-text);
  --bs-warning: var(--link);
  --bs-info: var(--text);
  --bs-secondary: var(--text-muted);
  --bs-primary-text-emphasis: var(--alert-primary-text);
  --bs-success-text-emphasis: var(--alert-success-text);
  --bs-danger-text-emphasis: var(--alert-danger-text);
  --bs-warning-text-emphasis: var(--alert-warning-text);
  --bs-info-text-emphasis: var(--alert-info-text);
  --bs-primary-bg-subtle: var(--alert-primary-bg);
  --bs-success-bg-subtle: var(--alert-success-bg);
  --bs-danger-bg-subtle: var(--alert-danger-bg);
  --bs-warning-bg-subtle: var(--alert-warning-bg);
  --bs-info-bg-subtle: var(--alert-info-bg);
  --bs-primary-border-subtle: var(--alert-primary-border);
  --bs-success-border-subtle: var(--alert-success-border);
  --bs-danger-border-subtle: var(--alert-danger-border);
  --bs-warning-border-subtle: var(--alert-warning-border);
  --bs-info-border-subtle: var(--alert-info-border);
  --bs-form-valid-color: var(--success-text);
  --bs-form-valid-border-color: var(--success-text);
  --bs-form-invalid-color: var(--danger-text);
  --bs-form-invalid-border-color: var(--danger-text);
  --bs-focus-ring-width: 2px;
  --bs-focus-ring-opacity: 1;
  --bs-focus-ring-color: var(--focus-ring);
  --bs-focus-ring-x: 0;
  --bs-focus-ring-y: 0;
  --bs-focus-ring-blur: 0;
  --bs-highlight-bg: var(--alert-warning-bg);
  --bs-highlight-color: var(--text);
CSS;
        return $o . "\n";
    }

    public static function css(array $paleta, string $principal): string {
        $o = "/*\n * Tokens de diseño de Rastro del Istmo (docs/DESIGN.md): oscuro por defecto y claro con data-theme=\"light\".\n";
        $o .= " * Generado por bin/generar_tema.php con el color principal " . self::normalizar($principal) . ".\n";
        $o .= " * Para cambiar el color de la marca: php bin/generar_tema.php #RRGGBB (valida el contraste en ambos modos).\n";
        $o .= " * Si se edita a mano, php bin/generar_tema.php --validar comprueba que los pares sigan cumpliendo.\n */\n\n";
        $o .= ":root {\n  /* Colores de DESIGN.md */\n";
        foreach ($paleta as $k => $v) $o .= "  --color-$k: $v;\n";
        $o .= "\n  /* Radios */\n";
        foreach (self::RADIOS as $k => $v) $o .= "  --rounded-$k: $v;\n";
        $o .= "\n  /* Espaciado */\n";
        foreach (self::ESPACIOS as $k => $v) $o .= "  --spacing-$k: $v;\n";
        $o .= "\n  /* Tipografía: Manrope para el texto, IBM Plex Sans para las cifras */\n";
        $o .= "  --font-text: " . self::FUENTE_TEXTO . ";\n  --font-numeric: " . self::FUENTE_NUMERICA . ";\n";
        foreach (self::TIPOGRAFIA as $k => [$fam, $size, $peso, $lh, $ls]) {
            $o .= "  --type-$k-family: var(--font-" . ($fam === "n" ? "numeric" : "text") . ");\n";
            $o .= "  --type-$k-size: $size;\n  --type-$k-weight: $peso;\n  --type-$k-line-height: $lh;\n  --type-$k-letter-spacing: $ls;\n";
        }
        $o .= "}\n\n/* Modo oscuro (por defecto) */\n:root,\n:root[data-theme=\"dark\"] {\n" . self::bloqueModo("dark", $paleta) . "}\n\n";
        $o .= "/* Modo claro */\n:root[data-theme=\"light\"] {\n" . self::bloqueModo("light", $paleta) . "}\n";
        return $o;
    }

    // ------------------------------------------------------------ validación

    /**
     * Lee las variables de un modo de un tema.css (paleta de :root más el bloque del modo) y resuelve los var(--x).
     * @return array<string,string> nombre sin guiones => valor resuelto (hex en minúsculas cuando es un color)
     */
    public static function leerVariables(string $css, string $modo): array {
        $css = preg_replace('~/\*.*?\*/~s', "", $css);
        $selector = $modo === "dark" ? ':root,\s*:root\[data-theme="dark"\]' : ':root\[data-theme="light"\]';
        $bloques = [];
        if (!preg_match('/(?:^|\})\s*:root\s*\{(.*?)\}/s', $css, $m)) throw new RuntimeException("tema.css no tiene el bloque :root");
        $bloques[] = $m[1];
        if (!preg_match('/' . $selector . '\s*\{(.*?)\n\}/s', $css, $m)) throw new RuntimeException("tema.css no tiene el bloque del modo $modo");
        $bloques[] = $m[1];
        $vars = [];
        foreach ($bloques as $b) {
            preg_match_all('/--([a-z0-9-]+):\s*([^;]+);/', $b, $mm, PREG_SET_ORDER);
            foreach ($mm as $x) $vars[$x[1]] = trim($x[2]);
        }
        $resolver = function (string $n, int $prof = 0) use (&$resolver, &$vars) {
            $v = $vars[$n] ?? throw new RuntimeException("Variable --$n sin definir");
            while (preg_match('/^var\(--([a-z0-9-]+)\)$/', $v, $r)) {
                if (++$prof > 10) throw new RuntimeException("Referencia circular en --$n");
                $v = $vars[$r[1]] ?? throw new RuntimeException("--$n apunta a --{$r[1]}, que no existe");
            }
            return $v;
        };
        $out = [];
        foreach (array_keys($vars) as $n) $out[$n] = strtolower($resolver($n));
        return $out;
    }

    /** @return list<string> descripción de cada par que no llega a su mínimo; vacío si todo cumple */
    public static function validarVariables(array $porModo): array {
        $fallos = [];
        foreach ($porModo as $modo => $v) {
            foreach (self::pares() as [$fg, $bg, $min]) {
                $a = $v[$fg] ?? null;
                $b = $v[$bg] ?? null;
                if ($a === null || $b === null) { $fallos[] = "$modo: falta --" . ($a === null ? $fg : $bg); continue; }
                $c = self::contraste($a, $b);
                if ($c + 1e-9 < $min) $fallos[] = sprintf("%s: %s %s sobre %s %s = %.2f:1 (mínimo %s)", $modo, $fg, $a, $bg, $b, $c, $min);
            }
        }
        return $fallos;
    }

    /** Valida una paleta en memoria (sin pasar por el CSS) */
    public static function validar(array $paleta): array {
        $porModo = [];
        foreach (array_keys(self::MODOS) as $modo) $porModo[$modo] = self::valoresModo($paleta, $modo);
        return self::validarVariables($porModo);
    }

    /**
     * Genera el CSS para un color principal: deriva, valida y, si algún par sigue fallando, lanza la excepción.
     * @return array{css:string,paleta:array<string,string>,avisos:list<string>}
     */
    public static function generar(string $principal): array {
        $r = self::derivar($principal);
        $fallos = self::validar($r["paleta"]);
        if ($fallos) throw new RuntimeException("El color $principal no se puede corregir automáticamente:\n - " . implode("\n - ", $fallos));
        return ["css" => self::css($r["paleta"], $principal), "paleta" => $r["paleta"], "avisos" => $r["avisos"]];
    }
}
