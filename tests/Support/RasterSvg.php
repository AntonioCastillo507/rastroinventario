<?php
namespace Tests\Support;

/**
 * Rasteriza el trazado de un SVG hecho solo de líneas rectas (M, L, Z con coordenadas absolutas, relleno
 * par-impar) para compararlo con un PNG. Es lo que genera bin/vectorizar_logo.php; no es un renderizador general.
 */
final class RasterSvg {
    /** @return list<list<array{0:float,1:float}>> polígonos del atributo d del primer <path> */
    public static function poligonos(string $svg): array {
        if (!preg_match('/<path\b[^>]*\sd="([^"]+)"/', $svg, $m)) throw new \RuntimeException("El SVG no tiene un trazado con d");
        $polis = [];
        $actual = null;
        preg_match_all('/([MLZ])|(-?\d*\.?\d+(?:e-?\d+)?)/i', $m[1], $t, PREG_SET_ORDER);
        $nums = [];
        $orden = null;
        $cerrar = function () use (&$actual, &$polis): void { if ($actual && count($actual) >= 3) $polis[] = $actual; $actual = null; };
        $volcar = function () use (&$nums, &$orden, &$actual): void {
            for ($i = 0; $i + 1 < count($nums); $i += 2) $actual[] = [(float)$nums[$i], (float)$nums[$i + 1]];
            $nums = [];
        };
        foreach ($t as $tok) {
            if (isset($tok[1]) && $tok[1] !== "") {
                $volcar();
                $c = strtoupper($tok[1]);
                if ($c === "M") { $cerrar(); $actual = []; }
                elseif ($c === "Z") { $cerrar(); }
                $orden = $c;
            } else {
                $nums[] = $tok[2];
            }
        }
        $volcar();
        $cerrar();
        if (!$polis) throw new \RuntimeException("No pude leer ningún polígono del SVG");
        return $polis;
    }

    /**
     * Cobertura 0..1 de cada píxel de un lienzo ancho x alto, con el (0, 0) del SVG colocado en ($ox, $oy), con $ss x $ss muestras por píxel
     * @return list<list<float>>
     */
    public static function cobertura(string $svg, int $ancho, int $alto, float $ox, float $oy, int $ss = 4): array {
        $polis = self::poligonos($svg);
        $aristas = [];
        foreach ($polis as $p) {
            $n = count($p);
            for ($i = 0; $i < $n; $i++) {
                [$x1, $y1] = $p[$i];
                [$x2, $y2] = $p[($i + 1) % $n];
                if ($y1 == $y2) continue;
                $aristas[] = [$x1 + $ox, $y1 + $oy, $x2 + $ox, $y2 + $oy];
            }
        }
        $cov = array_fill(0, $alto, array_fill(0, $ancho, 0.0));
        for ($fila = 0; $fila < $alto * $ss; $fila++) {
            $y = ($fila + 0.5) / $ss;
            $xs = [];
            foreach ($aristas as [$x1, $y1, $x2, $y2]) {
                if (($y1 <= $y && $y < $y2) || ($y2 <= $y && $y < $y1)) $xs[] = $x1 + ($y - $y1) * ($x2 - $x1) / ($y2 - $y1);
            }
            sort($xs);
            for ($k = 0; $k + 1 < count($xs); $k += 2) {
                $desde = (int)ceil($xs[$k] * $ss - 0.5);
                $hasta = (int)ceil($xs[$k + 1] * $ss - 0.5) - 1;
                for ($c = max(0, $desde); $c <= min($ancho * $ss - 1, $hasta); $c++) $cov[intdiv($fila, $ss)][intdiv($c, $ss)] += 1.0 / ($ss * $ss);
            }
        }
        return $cov;
    }

    /** Opacidad 0..1 de cada píxel de un PNG con transparencia */
    public static function opacidadPng(string $ruta): array {
        $im = imagecreatefrompng($ruta);
        $w = imagesx($im); $h = imagesy($im);
        $o = [];
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) $o[$y][$x] = 1.0 - (((imagecolorat($im, $x, $y) >> 24) & 127) / 127.0);
        return $o;
    }
}
