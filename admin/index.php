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
// Guardia de rol: una cuenta Cliente no es staff, no entra al panel.
$chkStaff = Database::get()->prepare(
    "SELECT 1 FROM permisos p INNER JOIN usuario_roles ur ON ur.rol_id = p.rol_id WHERE ur.usuario_id = ? LIMIT 1"
);
$chkStaff->execute([$_SESSION["user"]["id"]]);
if (!$chkStaff->fetchColumn()) {
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
    "factura"     => FacturaController::class,
];

if (!isset($map[$mod])) { header("Location: ".APP_URL."/admin/"); exit; }
try {
    $ctrl   = new $map[$mod]();
    $method = method_exists($ctrl,$action) ? $action : "index";
    $ctrl->$method();
} catch (\Throwable $e) {
    if (isset($ctrl) && $ctrl instanceof ErrorHandlerInterface) { $ctrl->handle($e); }
    else { error_log("[RASTRO] " . $e->getMessage()); }
    header("Location: " . APP_URL . "/admin/"); exit;
}
