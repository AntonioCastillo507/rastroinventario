<?php
define("BASE_PATH", dirname(__DIR__));
require_once BASE_PATH."/bootstrap.php";

$mod    = Sanitizer::get("mod")    ?: "dashboard";
$raw    = Sanitizer::get("action") ?: "index";
$action = lcfirst(str_replace('_', '', ucwords($raw, '_')));

// Guardia global: nadie entra a /admin/ (ningun modulo) sin sesion iniciada.
if (empty($_SESSION["user"])) {
    header("Location: " . APP_URL . "/login.php");
    exit;
}
// Guardia de rol: una cuenta sin ningún permiso (como un Cliente) no es staff, no entra al panel.
if (!(new UserModel())->tieneAccesoPanel((int)$_SESSION["user"]["id"])) {
    $_SESSION["flash"] = ["msg" => "Tu cuenta no tiene acceso al panel administrativo.", "type" => "danger"];
    header("Location: " . APP_URL . "/");
    exit;
}

$map = [
    "dashboard"   => DashboardController::class,
    "partes"      => ParteController::class,
    "ventas"      => VentaController::class,
    "autos"       => AutoController::class,
    "secciones"   => SeccionController::class,
    "usuarios"    => UserController::class,
    "roles"       => RolController::class,
    "comentarios" => ComentarioController::class,
    "pedidos"     => PedidoAdminController::class,
    "reportes"    => ReporteController::class,
    "seguridad"   => SeguridadController::class,
    "factura"     => FacturaController::class,
];

if (!isset($map[$mod])) { header("Location: ".APP_URL."/admin/"); exit; }
try {
    $ctrl   = new $map[$mod]();
    $method = method_exists($ctrl,$action) ? $action : "index";
    $ctrl->$method();
} catch (\Throwable $e) {
    // Si falla el propio dashboard, redirigir a él sería un bucle
    if ($mod === "dashboard") paginaError(registrarError($e), 500);
    if (isset($ctrl) && $ctrl instanceof ErrorHandlerInterface) { $ctrl->handle($e); }
    else { error_log("[RASTRO] " . $e->getMessage()); }
    header("Location: " . APP_URL . "/admin/"); exit;
}
