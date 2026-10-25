<?php
/**
 * Genera assets/css/tema.css a partir del color principal de la marca (docs/DESIGN.md, "Personalización").
 *
 *   php bin/generar_tema.php #FCD535                  escribe assets/css/tema.css
 *   php bin/generar_tema.php #F97316 --salida=ruta    escribe en otra ruta (para probar)
 *   php bin/generar_tema.php --validar [ruta]         solo comprueba el contraste de un tema.css existente
 *
 * Solo consola. Deriva el resto de colores, valida el contraste en oscuro y claro y, si algún par no
 * llega al mínimo, lo corrige y lo avisa. Salida 0 si todo cumple, 1 por argumentos no válidos, 2 si
 * el color no se puede corregir o el tema.css revisado tiene pares bajo el mínimo.
 */
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . "/app/Utils/TemaGenerador.php";

function salir(string $msg, int $codigo): never {
    fwrite($codigo === 0 ? STDOUT : STDERR, $msg . PHP_EOL);
    exit($codigo);
}

$destino = dirname(__DIR__) . "/assets/css/tema.css";
$principal = null;
$validar = false;
$rutaValidar = null;
foreach (array_slice($argv, 1) as $a) {
    if ($a === "--validar") $validar = true;
    elseif (str_starts_with($a, "--salida=")) $destino = substr($a, 9);
    elseif (str_starts_with($a, "--")) salir("Opción desconocida: $a", 1);
    elseif ($validar && $rutaValidar === null && !str_starts_with($a, "#") && is_file($a)) $rutaValidar = $a;
    elseif ($principal === null) $principal = $a;
    else salir("Sobra el argumento: $a", 1);
}

if ($validar) {
    $ruta = $rutaValidar ?? $destino;
    if (!is_file($ruta)) salir("No existe $ruta", 1);
    $css = (string)file_get_contents($ruta);
    try {
        $fallos = TemaGenerador::validarVariables([
            "dark" => TemaGenerador::leerVariables($css, "dark"),
            "light" => TemaGenerador::leerVariables($css, "light"),
        ]);
    } catch (RuntimeException $e) {
        salir("No pude leer $ruta: " . $e->getMessage(), 2);
    }
    if ($fallos) salir("Pares bajo el mínimo en $ruta:\n - " . implode("\n - ", $fallos), 2);
    salir("Contraste correcto en oscuro y claro (" . count(TemaGenerador::pares()) * 2 . " pares revisados).", 0);
}

if ($principal === null) salir("Uso: php bin/generar_tema.php #RRGGBB [--salida=ruta]   |   php bin/generar_tema.php --validar [ruta]", 1);

try {
    $r = TemaGenerador::generar($principal);
} catch (InvalidArgumentException $e) {
    salir($e->getMessage(), 1);
} catch (RuntimeException $e) {
    salir($e->getMessage(), 2);
}

if (!is_dir(dirname($destino))) salir("No existe la carpeta de destino: " . dirname($destino), 1);
file_put_contents($destino, $r["css"]);
$msg = "Escrito $destino con el principal " . TemaGenerador::normalizar($principal) . ". Contraste correcto en oscuro y claro.";
if ($r["avisos"]) $msg .= PHP_EOL . "Correcciones:" . PHP_EOL . " - " . implode(PHP_EOL . " - ", $r["avisos"]);
salir($msg, 0);
