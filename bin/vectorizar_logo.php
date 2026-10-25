<?php
/**
 * Vectoriza un logo PNG de UN solo color y fondo transparente en un SVG de un solo trazado.
 *
 *   php bin/vectorizar_logo.php assets/img/logo.png assets/img/logo.svg [--tolerancia=0.35] [--margen=1]
 *
 * Traza el contorno donde la opacidad cruza el 50 % (marching squares con interpolación, así que respeta el
 * suavizado de los bordes), lo simplifica con Douglas-Peucker y escribe un <svg> con viewBox ajustado a la
 * figura, sin colores propios (fill="currentColor") para que tome el del tema. La fidelidad respecto al PNG
 * la comprueba tests/Unit/LogoSvgTest.php. Solo consola.
 */
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

$args = array_slice($argv, 1);
$opc = ["tolerancia" => 0.35, "margen" => 1.0];
$pos = [];
foreach ($args as $a) {
    if (preg_match('/^--(tolerancia|margen)=([\d.]+)$/', $a, $m)) $opc[$m[1]] = (float)$m[2];
    else $pos[] = $a;
}
if (count($pos) !== 2) {
    fwrite(STDERR, "Uso: php bin/vectorizar_logo.php entrada.png salida.svg [--tolerancia=0.35] [--margen=1]\n");
    exit(1);
}
[$entrada, $salida] = $pos;
$im = @imagecreatefrompng($entrada);
if (!$im) {
    fwrite(STDERR, "No pude leer $entrada como PNG.\n");
    exit(1);
}
$W = imagesx($im);
$H = imagesy($im);

// Opacidad 0..1 por píxel, con un borde de 1 px transparente para que todo contorno se cierre
$op = [];
for ($y = -1; $y <= $H; $y++) {
    for ($x = -1; $x <= $W; $x++) {
        $op[$y][$x] = ($x < 0 || $y < 0 || $x >= $W || $y >= $H) ? 0.0 : 1.0 - (((imagecolorat($im, $x, $y) >> 24) & 127) / 127.0);
    }
}

// Marching squares sobre las muestras en los centros de píxel (x + 0.5, y + 0.5), umbral 0,5
$umbral = 0.5;
$punto = function (float $x1, float $y1, float $v1, float $x2, float $y2, float $v2) use ($umbral): array {
    $t = ($umbral - $v1) / (($v2 - $v1) ?: 1e-9);
    return [$x1 + ($x2 - $x1) * $t, $y1 + ($y2 - $y1) * $t];
};
$segmentos = []; // clave del extremo A => [A, B]
$clave = fn(array $p): string => sprintf("%.4f,%.4f", $p[0], $p[1]);
for ($y = -1; $y < $H; $y++) {
    for ($x = -1; $x < $W; $x++) {
        $v = [$op[$y][$x], $op[$y][$x + 1], $op[$y + 1][$x + 1], $op[$y + 1][$x]]; // arriba-izq, arriba-der, abajo-der, abajo-izq
        $cx = [$x + 0.5, $x + 1.5, $x + 1.5, $x + 0.5];
        $cy = [$y + 0.5, $y + 0.5, $y + 1.5, $y + 1.5];
        $caso = 0;
        for ($i = 0; $i < 4; $i++) if ($v[$i] >= $umbral) $caso |= 1 << $i;
        if ($caso === 0 || $caso === 15) continue;
        $borde = function (int $a, int $b) use ($cx, $cy, $v, $punto): array {
            return $punto($cx[$a], $cy[$a], $v[$a], $cx[$b], $cy[$b], $v[$b]);
        };
        $arriba = fn() => $borde(0, 1); $derecha = fn() => $borde(1, 2); $abajo = fn() => $borde(3, 2); $izq = fn() => $borde(0, 3);
        $tabla = [
            1 => [[$izq, $arriba]], 2 => [[$arriba, $derecha]], 3 => [[$izq, $derecha]], 4 => [[$derecha, $abajo]],
            6 => [[$arriba, $abajo]], 7 => [[$izq, $abajo]], 8 => [[$abajo, $izq]], 9 => [[$abajo, $arriba]],
            11 => [[$abajo, $derecha]], 12 => [[$derecha, $izq]], 13 => [[$derecha, $arriba]], 14 => [[$arriba, $izq]],
        ];
        if ($caso === 5 || $caso === 10) { // silla: se decide por el valor medio
            $medio = array_sum($v) / 4 >= $umbral;
            if ($caso === 5) $lista = $medio ? [[$izq, $abajo], [$derecha, $arriba]] : [[$izq, $arriba], [$derecha, $abajo]];
            else $lista = $medio ? [[$arriba, $izq], [$abajo, $derecha]] : [[$arriba, $derecha], [$abajo, $izq]];
        } else {
            $lista = $tabla[$caso];
        }
        foreach ($lista as [$fa, $fb]) {
            $a = $fa(); $b = $fb();
            $segmentos[$clave($a)][] = [$a, $b];
        }
    }
}

// Une los segmentos en lazos cerrados
$lazos = [];
while ($segmentos) {
    $k = array_key_first($segmentos);
    [$inicio, $sig] = array_shift($segmentos[$k]);
    if (!$segmentos[$k]) unset($segmentos[$k]);
    $lazo = [$inicio];
    $actual = $sig;
    $guarda = 0;
    while ($clave($actual) !== $clave($inicio) && $guarda++ < 100000) {
        $lazo[] = $actual;
        $kc = $clave($actual);
        if (!isset($segmentos[$kc])) break;
        [, $sig] = array_shift($segmentos[$kc]);
        if (!$segmentos[$kc]) unset($segmentos[$kc]);
        $actual = $sig;
    }
    if (count($lazo) >= 3) $lazos[] = $lazo;
}

// Douglas-Peucker sobre un tramo abierto
$distancia = function (array $p, array $a, array $b): float {
    $dx = $b[0] - $a[0]; $dy = $b[1] - $a[1];
    $l2 = $dx * $dx + $dy * $dy;
    if ($l2 == 0) return hypot($p[0] - $a[0], $p[1] - $a[1]);
    return abs($dy * $p[0] - $dx * $p[1] + $b[0] * $a[1] - $b[1] * $a[0]) / sqrt($l2);
};
$dp = function (array $pts, float $eps) use (&$dp, $distancia): array {
    $n = count($pts);
    if ($n < 3) return $pts;
    $max = 0; $idx = 0;
    for ($i = 1; $i < $n - 1; $i++) {
        $d = $distancia($pts[$i], $pts[0], $pts[$n - 1]);
        if ($d > $max) { $max = $d; $idx = $i; }
    }
    if ($max <= $eps) return [$pts[0], $pts[$n - 1]];
    $izq = $dp(array_slice($pts, 0, $idx + 1), $eps);
    $der = $dp(array_slice($pts, $idx), $eps);
    return array_merge(array_slice($izq, 0, -1), $der);
};
$simplificar = function (array $lazo) use ($dp, $opc): array {
    // se parte el lazo por los dos puntos más alejados entre sí
    $n = count($lazo); $a = 0; $b = 0; $max = -1;
    for ($i = 0; $i < $n; $i += max(1, intdiv($n, 60))) {
        for ($j = $i + 1; $j < $n; $j += max(1, intdiv($n, 60))) {
            $d = hypot($lazo[$i][0] - $lazo[$j][0], $lazo[$i][1] - $lazo[$j][1]);
            if ($d > $max) { $max = $d; $a = $i; $b = $j; }
        }
    }
    $t1 = array_slice($lazo, $a, $b - $a + 1);
    $t2 = array_merge(array_slice($lazo, $b), array_slice($lazo, 0, $a + 1));
    return array_merge(array_slice($dp($t1, $opc["tolerancia"]), 0, -1), array_slice($dp($t2, $opc["tolerancia"]), 0, -1));
};
$lazos = array_values(array_filter(array_map($simplificar, $lazos), fn($l) => count($l) >= 3));

// Caja de la figura
$minx = $miny = INF; $maxx = $maxy = -INF;
foreach ($lazos as $l) foreach ($l as [$px, $py]) { $minx = min($minx, $px); $maxx = max($maxx, $px); $miny = min($miny, $py); $maxy = max($maxy, $py); }
if (!is_finite($minx)) {
    fwrite(STDERR, "El PNG no tiene figura opaca que vectorizar.\n");
    exit(2);
}
$ox = floor($minx - $opc["margen"]); $oy = floor($miny - $opc["margen"]);
$ancho = ceil($maxx + $opc["margen"]) - $ox; $alto = ceil($maxy + $opc["margen"]) - $oy;
$num = fn(float $v): string => rtrim(rtrim(number_format($v, 2, ".", ""), "0"), ".");
$d = "";
foreach ($lazos as $l) {
    $d .= "M" . implode("L", array_map(fn($p) => $num($p[0] - $ox) . " " . $num($p[1] - $oy), $l)) . "Z";
}
// data-origen: dónde cae el (0, 0) del viewBox dentro del PNG; solo lo usa la prueba de fidelidad (el saneado lo descarta al servir el logo)
$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $num($ancho) . ' ' . $num($alto) . '" data-origen="' . $num($ox) . ' ' . $num($oy) . '"><path fill="currentColor" fill-rule="evenodd" d="' . $d . '"/></svg>' . "\n";
file_put_contents($salida, $svg);
fwrite(STDOUT, sprintf("%s: %d trazados, %d vértices, viewBox %s x %s, %d bytes (origen en el PNG: %s, %s)\n",
    $salida, count($lazos), array_sum(array_map("count", $lazos)), $num($ancho), $num($alto), strlen($svg), $num($ox), $num($oy)));
