<?php
// Proceso hijo de las pruebas: ejecuta UNA acción de un controlador real (con su exit y sus header())
// y escribe en la salida las cabeceras y la sesión resultantes como JSON.
// Uso (lo lanza Tests\Support\Proceso, no se corre a mano):  php ejecutar_accion.php entrada.json

function abortar(string $m): never { fwrite(STDERR, "[HIJO ABORTADO] $m\n"); exit(2); }

if (PHP_SAPI !== "cli") abortar("solo por consola");
if (getenv("RASTRO_DB_NAME") !== "rastroinventario_test") abortar("RASTRO_DB_NAME debe ser rastroinventario_test");

$in = json_decode((string)file_get_contents($argv[1] ?? ""), true);
if (!is_array($in)) abortar("entrada inválida");

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "rastro_test_hijo_" . getmypid();
@mkdir("$tmp/uploads", 0777, true);
@mkdir("$tmp/sessions", 0777, true);

define("BASE_PATH", dirname(__DIR__, 2));
define("UPLOAD_PATH", rtrim($in["upload_path"] ?? "$tmp/uploads", "/\\") . "/");

$_SERVER["REQUEST_METHOD"] = $in["metodo_http"] ?? "POST";
$_SERVER["REMOTE_ADDR"]    = $in["ip"] ?? "127.0.0.1";

ini_set("session.use_cookies", "0");
ini_set("session.use_only_cookies", "0");
session_cache_limiter("");
session_save_path("$tmp/sessions");
session_start();
$_SESSION = $in["sesion"] ?? [];

require_once BASE_PATH . "/vendor/autoload.php";
require_once BASE_PATH . "/bootstrap.php";
ini_set("error_log", "$tmp/error.log");

if (Database::get()->query("SELECT DATABASE()")->fetchColumn() !== "rastroinventario_test") abortar("conexión a otra base");

$_POST = $in["post"] ?? [];
$_GET  = $in["get"] ?? [];
if (!empty($in["csrf"])) $_POST["csrf_token"] = CsrfToken::generate();
foreach (($in["servidor"] ?? []) as $k => $v) $_SERVER[$k] = $v; // p. ej. un Host hostil

// Correo: "ok" o "falla" sustituyen el envío real y anotan cada intento; "dir_logs" mueve emailjs.log y reset_dev.log
$correos = [];
if (isset($in["dir_logs"])) EmailJsService::$dirLogs = $in["dir_logs"];
if (isset($in["correo"])) {
    EmailJsService::$transporte = function (string $plantilla, array $params) use (&$correos, $in): bool {
        $correos[] = ["plantilla" => $plantilla, "params" => $params, "sesion_activa" => session_status() === PHP_SESSION_ACTIVE];
        return $in["correo"] === "ok";
    };
}

register_shutdown_function(function () use ($tmp, &$correos): void {
    $sesion = $_SESSION;
    echo "\n@@RESULTADO@@" . json_encode([
        "cabeceras" => function_exists("xdebug_get_headers") ? xdebug_get_headers() : headers_list(), // en consola solo Xdebug guarda las cabeceras
        "codigo"    => http_response_code() ?: 200,
        "sesion"    => $sesion,
        "correos"   => $correos,
    ], JSON_UNESCAPED_UNICODE) . "\n";
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    foreach (glob("$tmp/{uploads,sessions}/*", GLOB_BRACE) ?: [] as $f) @unlink($f);
    @rmdir("$tmp/uploads"); @rmdir("$tmp/sessions"); @rmdir($tmp);
});

$clase  = $in["clase"];
$metodo = $in["accion"];
(new $clase())->$metodo();
