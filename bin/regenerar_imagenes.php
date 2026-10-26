<?php
/**
 * Genera las derivadas (640, 1200 y 2400 px) de todas las fotos que ya están en uploads/parts/, a partir de sus originales.
 * Las originales no se tocan ni se borran. La miniatura de 640 px reemplaza a la anterior de 420x315 con el mismo nombre,
 * así que la base de datos no cambia.
 *
 *   php bin/regenerar_imagenes.php                  muestra qué haría y no escribe nada
 *   php bin/regenerar_imagenes.php --confirmar      escribe (haz antes una copia de uploads/ fuera del proyecto)
 *   php bin/regenerar_imagenes.php --uploads=RUTA   usa otra carpeta de uploads (por ejemplo una copia para ensayar)
 */
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

define("BASE_PATH", dirname(__DIR__));
$confirmar = in_array("--confirmar", $argv, true);
$uploads = BASE_PATH . DIRECTORY_SEPARATOR . "uploads";
foreach ($argv as $a) if (str_starts_with($a, "--uploads=")) $uploads = rtrim(substr($a, 10), "\\/");
define("UPLOAD_PATH", $uploads . DIRECTORY_SEPARATOR);
require BASE_PATH . "/vendor/autoload.php";

$partes = glob(UPLOAD_PATH . "parts" . DIRECTORY_SEPARATOR . "*.*") ?: [];
sort($partes);
echo ($confirmar ? "Escribiendo" : "Ensayo (no escribe nada)") . " en " . UPLOAD_PATH . ": " . count($partes) . " originales\n";
$bien = $mal = 0;
foreach ($partes as $ruta) {
    $nombre = basename($ruta);
    if (!$confirmar) { echo "  $nombre\n"; continue; }
    $r = ImageHelper::regenerar($nombre);
    if ($r === null) { $mal++; echo "  FALLA $nombre: " . ImageHelper::$error . "\n"; }
    else $bien++;
}
if ($confirmar) echo "Listo: $bien regeneradas, $mal con error.\n";
exit($mal > 0 ? 1 : 0);
