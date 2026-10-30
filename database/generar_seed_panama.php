<?php
/**
 * Genera database/seed_panama.sql: provincias, distritos y corregimientos de Panama.
 *
 * Fuente oficial: capa "Division Politica Administrativa" del Instituto Geografico Nacional "Tommy Guardia"
 * (servicio ANATI), hecha con informacion del INEC y la Contraloria, la Comision Nacional de Limites
 * Politicos Administrativos (Ministerio de Gobierno), la Asamblea Nacional y el Tribunal Electoral.
 * Datos: actualizados al 2024 (servicio "2025a"). Contraste con el INEC: Cuadro 10 del Censo 2023.
 *
 * Es una herramienta de una sola vez: el sitio NUNCA llama a ese servicio. Se vuelve a correr solo si
 * se crean provincias, distritos o corregimientos nuevos, y el resultado se revisa antes de confirmarlo.
 *
 *   php database\generar_seed_panama.php                 (lee el servicio por internet)
 *   php database\generar_seed_panama.php C:\carpeta      (lee capa6.json, capa7.json y capa8.json guardadas antes)
 */
const FUENTE_URL   = "https://sigigntg.anati.gob.pa/arcgisserver/rest/services/Mapa_Web_Divisi%C3%B3n_Pol%C3%ADtica_Administrativa_2025a_MIL1/MapServer";
const FUENTE_FECHA = "2024";
const CAPAS        = [6 => "provincias", 7 => "distritos", 8 => "corregimientos"];

// Unidades que existen en la capa solo para completar la cadena provincia > distrito > corregimiento.
// El cliente no las ve: se asignan solas y no aparecen en la copia de la direccion de un pedido.
//  - 1001: la comarca Guna Yala no tiene distritos; la capa usa uno ficticio con el nombre de la comarca.
//  - 010501: la comarca Naso Tjer Di (distrito) tiene sus corregimientos sin cargar; la capa usa un area unica.
const DISTRITOS_IMPLICITOS       = ["1001"];
const CORREGIMIENTOS_IMPLICITOS  = ["010501"];

// Nombres que la capa trae con el nombre anterior. Guna Yala se llama asi por la Ley 99 de 2011.
const NOMBRES_AJUSTADOS = ["Comarca Kuna Yala" => "Comarca Guna Yala"];

function leerCapa(int $capa, ?string $carpeta): array {
    $json = $carpeta !== null
        ? file_get_contents(rtrim($carpeta, "\\/") . DIRECTORY_SEPARATOR . "capa$capa.json")
        : file_get_contents(FUENTE_URL . "/$capa/query?" . http_build_query(
            ["where" => "1=1", "outFields" => "*", "returnGeometry" => "false", "resultRecordCount" => 2000, "f" => "json"]));
    if ($json === false) { fwrite(STDERR, "No pude leer la capa $capa.\n"); exit(1); }
    $d = json_decode($json, true);
    if (!isset($d["features"]) || !empty($d["exceededTransferLimit"])) { fwrite(STDERR, "Respuesta incompleta en la capa $capa.\n"); exit(1); }
    return array_map(fn($f) => $f["attributes"], $d["features"]);
}

function limpio(string $n): string {
    $n = trim(preg_replace('/\s+/u', " ", $n));
    return NOMBRES_AJUSTADOS[$n] ?? $n;
}
function sql(string $s): string { return "'" . str_replace(["\\", "'"], ["\\\\", "''"], $s) . "'"; }

$carpeta = $argv[1] ?? null;
$prov = $dist = $corr = [];
foreach (leerCapa(6, $carpeta) as $r) if ($r["uso"] === "Tierra") $prov[$r["cod_prov"]] = limpio($r["nomb_prov"]);
foreach (leerCapa(7, $carpeta) as $r) if ($r["uso"] === "Tierra") $dist[$r["cod_dist"]] = [$r["cod_prov"], limpio($r["nomb_dist"])];
foreach (leerCapa(8, $carpeta) as $r) if ($r["uso"] === "Tierra") $corr[$r["cod_corr"]] = [$r["cod_dist"], limpio($r["nomb_corr"])];
// Las claves como "1001" pasan a ser enteros en PHP: los codigos se usan siempre como texto (string)
ksort($prov, SORT_STRING); ksort($dist, SORT_STRING); ksort($corr, SORT_STRING);

// Comprobaciones: si algo no cuadra, no se escribe nada
foreach ($dist as $c => [$p, $n]) if (!isset($prov[$p]) || strlen($c) !== 4 || !str_starts_with($c, $p)) { fwrite(STDERR, "Distrito $c sin provincia valida.\n"); exit(1); }
foreach ($corr as $c => [$d, $n]) if (!isset($dist[$d]) || strlen($c) !== 6 || !str_starts_with($c, $d)) { fwrite(STDERR, "Corregimiento $c sin distrito valido.\n"); exit(1); }
foreach (DISTRITOS_IMPLICITOS as $c) if (!isset($dist[$c])) /* las claves numericas de PHP son enteros, pero isset acepta el texto */ { fwrite(STDERR, "Falta el distrito implicito $c.\n"); exit(1); }
foreach (CORREGIMIENTOS_IMPLICITOS as $c) if (!isset($corr[$c])) { fwrite(STDERR, "Falta el corregimiento implicito $c.\n"); exit(1); }

$out  = "-- Division politico-administrativa de Panama: provincias, comarcas, distritos y corregimientos.\n";
$out .= "-- NO EDITAR A MANO: lo genera database/generar_seed_panama.php.\n";
$out .= "--\n";
$out .= "-- Fuente: capa \"Division Politica Administrativa\" del Instituto Geografico Nacional \"Tommy Guardia\" (ANATI),\n";
$out .= "--   con informacion del INEC y la Contraloria General de la Republica, la Comision Nacional de Limites Politicos\n";
$out .= "--   Administrativos (Ministerio de Gobierno), la Asamblea Nacional y el Tribunal Electoral.\n";
$out .= "--   " . FUENTE_URL . "\n";
$out .= "-- Fecha de los datos: actualizados al " . FUENTE_FECHA . " (servicio 2025a). Generado el " . date("Y-m-d") . ".\n";
$out .= "-- Contraste: INEC, Cuadro 10 del Censo 2023 (publicado el 2023-11-09),\n";
$out .= "--   https://www.inec.gob.pa/archivos/P0705547520231109105354CUADRO%2010.pdf\n";
$out .= "-- Contenido: " . count($prov) . " provincias y comarcas con rango de provincia, " . count($dist) . " distritos, " . count($corr) . " corregimientos.\n";
$out .= "-- implicito = 1: unidad que solo completa la cadena (distrito ficticio de Guna Yala, area unica de Naso Tjer Di);\n";
$out .= "--   el formulario no la muestra y la copia de la direccion de un pedido no la incluye.\n";
$out .= "-- Se puede cargar mas de una vez: actualiza los nombres y no duplica filas.\n\n";
$out .= "SET NAMES utf8mb4;\n\n";

$filas = [];
foreach ($prov as $c => $n) $filas[] = "  (" . sql($c) . ", " . sql($n) . ", " . sql(str_starts_with($n, "Comarca") ? "comarca" : "provincia") . ")";
$out .= "INSERT INTO `provincias` (`codigo`, `nombre`, `tipo`) VALUES\n" . implode(",\n", $filas) . "\nAS nuevo ON DUPLICATE KEY UPDATE `nombre` = nuevo.`nombre`, `tipo` = nuevo.`tipo`;\n\n";

$filas = [];
foreach ($dist as $c => [$p, $n]) $filas[] = "  (" . sql($c) . ", " . sql($p) . ", " . sql($n) . ", " . (int)in_array((string)$c, DISTRITOS_IMPLICITOS, true) . ")";
$out .= "INSERT INTO `distritos` (`codigo`, `provincia_codigo`, `nombre`, `implicito`) VALUES\n" . implode(",\n", $filas) . "\nAS nuevo ON DUPLICATE KEY UPDATE `provincia_codigo` = nuevo.`provincia_codigo`, `nombre` = nuevo.`nombre`, `implicito` = nuevo.`implicito`;\n\n";

$filas = [];
foreach ($corr as $c => [$d, $n]) $filas[] = "  (" . sql($c) . ", " . sql($d) . ", " . sql($n) . ", " . (int)in_array((string)$c, CORREGIMIENTOS_IMPLICITOS, true) . ")";
$out .= "INSERT INTO `corregimientos` (`codigo`, `distrito_codigo`, `nombre`, `implicito`) VALUES\n" . implode(",\n", $filas) . "\nAS nuevo ON DUPLICATE KEY UPDATE `distrito_codigo` = nuevo.`distrito_codigo`, `nombre` = nuevo.`nombre`, `implicito` = nuevo.`implicito`;\n";

file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "seed_panama.sql", $out);
echo "seed_panama.sql: " . count($prov) . " provincias, " . count($dist) . " distritos, " . count($corr) . " corregimientos.\n";
