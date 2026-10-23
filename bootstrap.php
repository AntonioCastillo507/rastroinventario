<?php
if (!defined("BASE_PATH")) define("BASE_PATH", __DIR__);
define("UPLOAD_PATH", BASE_PATH . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR);

date_default_timezone_set("America/Panama");

require_once BASE_PATH."/app/Config/config.php";
require_once BASE_PATH."/app/Interfaces/CrudInterface.php";
require_once BASE_PATH."/app/Interfaces/ErrorHandlerInterface.php";
require_once BASE_PATH."/app/Interfaces/CryptoInterface.php";
require_once BASE_PATH."/app/Config/Database.php";
require_once BASE_PATH."/app/Utils/Sanitizer.php";
require_once BASE_PATH."/app/Utils/Validator.php";
require_once BASE_PATH."/app/Utils/CsrfToken.php";
require_once BASE_PATH."/app/Utils/DigitalSignature.php";
require_once BASE_PATH."/app/Utils/Itbms.php";
require_once BASE_PATH."/app/Utils/HmacCrypto.php";
require_once BASE_PATH."/app/Utils/RsaCrypto.php";
require_once BASE_PATH."/app/Utils/ImageHelper.php";
require_once BASE_PATH."/app/Utils/objImagenes.php";
require_once BASE_PATH."/app/Utils/EmailJsService.php";
require_once BASE_PATH."/app/Models/BaseModel.php";
require_once BASE_PATH."/app/Models/LogModel.php";
require_once BASE_PATH."/app/Models/UserModel.php";
require_once BASE_PATH."/app/Models/RolModel.php";
require_once BASE_PATH."/app/Models/AutoModel.php";
require_once BASE_PATH."/app/Models/SeccionModel.php";
require_once BASE_PATH."/app/Models/ParteModel.php";
require_once BASE_PATH."/app/Models/VentaModel.php";
require_once BASE_PATH."/app/Models/ComentarioModel.php";
require_once BASE_PATH."/app/Models/PedidoModel.php";
require_once BASE_PATH."/app/Controllers/BaseController.php";
require_once BASE_PATH."/app/Controllers/AuthController.php";
require_once BASE_PATH."/app/Controllers/DashboardController.php";
require_once BASE_PATH."/app/Controllers/UserController.php";
require_once BASE_PATH."/app/Controllers/RolController.php";
require_once BASE_PATH."/app/Controllers/AutoController.php";
require_once BASE_PATH."/app/Controllers/SeccionController.php";
require_once BASE_PATH."/app/Controllers/ParteController.php";
require_once BASE_PATH."/app/Controllers/VentaController.php";
require_once BASE_PATH."/app/Controllers/ComentarioController.php";
require_once BASE_PATH."/app/Controllers/PedidoController.php";
require_once BASE_PATH."/app/Controllers/PedidoAdminController.php";
require_once BASE_PATH."/app/Controllers/ReporteController.php";
require_once BASE_PATH."/app/Controllers/FacturaController.php";
require_once BASE_PATH."/app/Controllers/PublicController.php";

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
