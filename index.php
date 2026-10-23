<?php
define("BASE_PATH", __DIR__);
require_once __DIR__."/bootstrap.php";

$action = Sanitizer::get("action") ?: "home";
try {
    $ctrl   = new PublicController();

    match($action) {
        "detalle"            => $ctrl->detalle(),
        "comprar"            => $ctrl->comprar(),
        "procesar_compra"    => $ctrl->procesarCompra(),
        "confirmar_compra"   => $ctrl->confirmarCompra(),
        "compra_exitosa"     => $ctrl->exitosa(),
        "mis_pedidos"        => $ctrl->misPedidos(),
        "mi_perfil"          => $ctrl->miPerfil(),
        "carrito"            => $ctrl->carrito(),
        "agregar_carrito"    => $ctrl->agregarCarrito(),
        "quitar_carrito"     => $ctrl->quitarCarrito(),
        "actualizar_carrito" => $ctrl->actualizarCarrito(),
        "procesar_carrito"   => $ctrl->procesarCarrito(),
        "factura"            => (new FacturaController())->generarPublico(),
        default              => $ctrl->home(),
    };
} catch (\Throwable $e) {
    // Si falla la propia portada, redirigir a ella sería un bucle
    if ($action === "home") paginaError(registrarError($e), 500);
    if (isset($ctrl) && $ctrl instanceof ErrorHandlerInterface) { $ctrl->handle($e); }
    else { error_log("[RASTRO] " . $e->getMessage()); }
    header("Location: " . APP_URL . "/"); exit;
}
