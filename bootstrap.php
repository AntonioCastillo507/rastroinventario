<?php
if (!defined("BASE_PATH")) define("BASE_PATH", __DIR__);
if (!defined("UPLOAD_PATH")) define("UPLOAD_PATH", BASE_PATH . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR);

date_default_timezone_set("America/Panama");

// Errores: nada interno a pantalla, todo al registro del servidor (logs/error.log)
error_reporting(E_ALL);
ini_set("display_errors", "0");
ini_set("display_startup_errors", "0");
ini_set("html_errors", "0");
ini_set("log_errors", "1");
ini_set("zend.exception_ignore_args", "1");
if (!is_dir(BASE_PATH . "/logs")) @mkdir(BASE_PATH . "/logs", 0755, true);
ini_set("error_log", BASE_PATH . "/logs/error.log");
header_remove("X-Powered-By");

// Guarda el error real en el registro y devuelve un código corto para buscarlo
function registrarError(\Throwable $e, string $donde = ""): string {
    $ref = strtoupper(bin2hex(random_bytes(3)));
    // Una sola línea: un dato del usuario con saltos de línea no puede falsificar entradas
    $msg = preg_replace('/\s+/', " ", $e->getMessage());
    error_log("[RASTRO][$ref] $donde" . get_class($e) . ": $msg en " . $e->getFile() . ":" . $e->getLine());
    return $ref;
}

// Página neutra de último recurso: HTML fijo, sin base de datos, sin vistas, sin clases. Solo sale si la temática no se pudo armar
function paginaErrorNeutra(string $ref, int $codigo = 500): string {
    $volver = defined("APP_URL") ? '<p><a href="' . htmlspecialchars(APP_URL) . '/">Volver al inicio</a></p>' : "";
    $aviso = $ref !== "" ? 'Ocurrió un error interno. Intenta de nuevo; si sigue, indica el código de referencia <b>' . htmlspecialchars($ref) . '</b>.' : 'No se pudo mostrar la página. Intenta de nuevo en unos minutos.';
    return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Algo salió mal</title>'
         . '<meta name="viewport" content="width=device-width, initial-scale=1"></head>'
         . '<body style="font-family:sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem">'
         . '<h1>Algo salió mal</h1><p>' . $aviso . '</p>' . $volver . '</body></html>';
}

// Página de error para el visitante: la temática (PaginaError, con la marca y los estilos) y, si esa falla por lo que sea
// (sin autoload, sin configuración, sin marca), la neutra. Siempre solo con el código de referencia, nunca el error real.
function paginaError(string $ref, int $codigo = 500): void {
    if (!headers_sent()) {
        http_response_code($codigo);
        header("Content-Type: text/html; charset=UTF-8");
        header("Cache-Control: no-store");
        header("X-Robots-Tag: noindex");
    }
    try {
        if (!class_exists("PaginaError") || !defined("APP_URL")) throw new \RuntimeException("sin página temática");
        $html = PaginaError::html($codigo, null, $ref);
    } catch (\Throwable) {
        $html = paginaErrorNeutra($ref, $codigo);
    }
    echo $html;
    exit;
}

// Excepciones que nadie capturó (login, registro, logout, sesión, guardia de admin)
set_exception_handler(function (\Throwable $e): void {
    paginaError(registrarError($e), 500);
});

// Errores fatales y de compilación: no se pueden capturar, pero sí registrar
register_shutdown_function(function (): void {
    $err = error_get_last();
    $fatales = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR;
    if ($err === null || !($err["type"] & $fatales)) return;
    $ref = strtoupper(bin2hex(random_bytes(3)));
    $msg = preg_replace('/\s+/', " ", $err["message"]);
    error_log("[RASTRO][$ref] Fatal: $msg en " . $err["file"] . ":" . $err["line"]);
    if (!headers_sent()) paginaError($ref, 500);
});

require_once BASE_PATH."/app/Config/config.php";

// Las clases de app/ las carga el autoload de Composer (classmap en composer.json).
// Tras añadir una clase nueva: composer dump-autoload. Tras clonar el proyecto: composer install.
$autoload = BASE_PATH."/vendor/autoload.php";
if (!is_file($autoload)) {
    paginaError(registrarError(new RuntimeException("Falta vendor/autoload.php: ejecuta composer install"), "Arranque: "), 500);
}
require_once $autoload;

// Cierra la sesión y borra la cookie en el navegador
function destruirSesion(): void {
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), "", [
        "expires" => time() - 42000, "path" => $p["path"], "domain" => $p["domain"],
        "secure" => $p["secure"], "httponly" => $p["httponly"], "samesite" => $p["samesite"],
    ]);
    session_destroy();
}

// En cada petición con usuario: expira por inactividad, revisa que la cuenta siga
// activa y refresca los roles. Si falla, la página sigue como visitante.
function validarSesion(): void {
    if (empty($_SESSION["user"])) return;
    $um = new UserModel();
    $uid = (int)$_SESSION["user"]["id"];
    $motivo = "";
    if (time() - ($_SESSION["last_activity"] ?? time()) > SESSION_IDLE_TIMEOUT) {
        $motivo = "Tu sesión expiró por inactividad.";
    } else {
        $u = $um->findById($uid);
        if (!$u || !$u["activo"] || $u["bloqueado"]) $motivo = "Tu cuenta fue desactivada o bloqueada.";
    }
    if ($motivo !== "") {
        destruirSesion();
        session_id(session_create_id());
        session_start();
        $_SESSION["login_error"] = $motivo;
        return;
    }
    $_SESSION["last_activity"] = time();
    $_SESSION["user_roles"] = array_column($um->getRoles($uid), "nombre");
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    session_set_cookie_params([
        "lifetime" => 0,
        "path"     => parse_url(APP_URL, PHP_URL_PATH) ?: "/",
        "secure"   => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    session_start();
    validarSesion();
}
