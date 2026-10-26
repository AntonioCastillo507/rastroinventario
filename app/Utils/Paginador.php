<?php
/**
 * Paginador — Cálculo de páginas para los listados (catálogo y tablas del panel).
 * El número de página llega de la URL, así que se trata como dato hostil: nunca produce un error
 * ni un SQL raro. Las consultas que lo usan deben tener un ORDER BY con desempate único (id),
 * si no, filas con el mismo valor pueden repetirse o saltarse entre una página y otra.
 */
final class Paginador {
    public const CATALOGO = 24; // piezas por página en la tienda
    public const PANEL    = 20; // filas por página en el panel

    private function __construct(
        public readonly int $pagina,
        public readonly int $paginas,
        public readonly int $porPagina,
        public readonly int $total,
    ) {}

    /**
     * @param mixed $pedida   el valor crudo de ?page= (texto, número, arreglo, null...)
     * @param int   $total    filas que cumplen el filtro (SELECT COUNT(*) con el mismo WHERE)
     * @param int   $porPagina tamaño de página, 1 o más (si no es un error del programa)
     */
    public static function crear(mixed $pedida, int $total, int $porPagina): self {
        if ($porPagina < 1) throw new InvalidArgumentException("porPagina debe ser 1 o más");
        $total   = max(0, $total);
        $paginas = max(1, intdiv($total + $porPagina - 1, $porPagina));
        $n = 1;
        // Solo dígitos 0-9 sin signo: -1, 3.5, 1e3, abc, 2abc, arreglos y null valen página 1
        if (is_int($pedida) || is_string($pedida)) {
            $s = ltrim((string)$pedida, "0");
            if ((string)$pedida !== "" && ctype_digit((string)$pedida) && $s !== "") {
                // Más de 15 dígitos no cabe en un entero seguro: cuenta como "enorme" y va a la última
                $n = strlen($s) > 15 ? $paginas : min((int)$s, $paginas);
            }
        }
        return new self(max(1, $n), $paginas, $porPagina, $total);
    }

    public function hayVarias(): bool { return $this->paginas > 1; }
    public function limite(): int     { return $this->porPagina; }
    public function offset(): int     { return ($this->pagina - 1) * $this->porPagina; }

    /** " LIMIT 12 OFFSET 24": solo enteros ya validados, nada del usuario */
    public function sql(): string {
        return " LIMIT " . $this->limite() . " OFFSET " . $this->offset();
    }

    /** Primera fila que se muestra (1 en base), o 0 si no hay filas */
    public function desdeFila(): int { return $this->total === 0 ? 0 : $this->offset() + 1; }
    public function hastaFila(): int { return min($this->offset() + $this->porPagina, $this->total); }

    /**
     * Números de página a mostrar, con null donde va "…": 1 … 4 5 [6] 7 8 … 20
     * @return list<int|null>
     */
    public function ventana(int $vecinas = 2): array {
        $set = [1 => true, $this->paginas => true];
        for ($i = $this->pagina - $vecinas; $i <= $this->pagina + $vecinas; $i++) {
            if ($i >= 1 && $i <= $this->paginas) $set[$i] = true;
        }
        $nums = array_keys($set);
        sort($nums);
        $out = [];
        foreach ($nums as $i => $n) {
            if ($i > 0) {
                $salto = $n - $nums[$i - 1];
                if ($salto === 2) $out[] = $n - 1;    // un solo hueco: se muestra la página en vez de "…"
                elseif ($salto > 2) $out[] = null;
            }
            $out[] = $n;
        }
        return $out;
    }

    /**
     * Enlace a la página $n conservando los filtros. Los valores vacíos se omiten y la página 1 no
     * lleva ?page= (es la URL normal). Devuelve el texto SIN escapar para HTML: la vista lo escapa.
     * @param array<string,mixed> $params filtros activos (se ignoran arreglos, null, "" y false)
     */
    public function url(int $n, array $params, string $base = ""): string {
        unset($params["page"]);
        $q = [];
        foreach ($params as $k => $v) {
            if ($v === null || $v === "" || $v === false || is_array($v) || is_object($v)) continue;
            $q[(string)$k] = is_bool($v) ? "1" : (string)$v;
        }
        $n = max(1, min($n, $this->paginas));
        if ($n > 1) $q["page"] = (string)$n;
        return $base . ($q ? "?" . http_build_query($q, "", "&", PHP_QUERY_RFC3986) : "");
    }
}
