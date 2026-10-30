<?php
define("BASE_PATH", __DIR__);
require_once __DIR__."/bootstrap.php";

$action = Sanitizer::get("action") ?: "home";
try {
    $ctrl   = new PublicController();

    match($action) {
        "nosotros"           => $ctrl->nosotros(),
        "contacto"           => $ctrl->contacto(),
        "detalle"            => $ctrl->detalle(),
        "comprar"            => $ctrl->comprar(),
        "procesar_compra"    => $ctrl->procesarCompra(),
        "confirmar_compra"   => $ctrl->confirmarCompra(),
        "compra_exitosa"     => $ctrl->exitosa(),
        "mis_pedidos"        => $ctrl->misPedidos(),
        "mi_perfil"          => $ctrl->miPerfil(),
        "mi_direccion"       => $ctrl->miDireccion(),
        "mi_clave"           => $ctrl->miClave(),
        "geo"                => $ctrl->geo(),
        "carrito"            => $ctrl->carrito(),
        "carrito_datos"      => $ctrl->datosCarrito(),
        "restaurar_carrito"  => $ctrl->restaurarCarrito(),
        "agregar_carrito"    => $ctrl->agregarCarrito(),
        "quitar_carrito"     => $ctrl->quitarCarrito(),
        "actualizar_carrito" => $ctrl->actualizarCarrito(),
        "procesar_carrito"   => $ctrl->procesarCarrito(),
        "factura"            => (new FacturaController())->generarPublico(),
        "home"               => $ctrl->home(),
        default              => PaginaError::mostrar(404), // una acción que no existe ya no se disfraza de portada con código 200
    };
} catch (\Throwable $e) {
    // Si falla la propia portada, redirigir a ella sería un bucle
    if ($action === "home") paginaError(registrarError($e), 500);
    if (isset($ctrl) && $ctrl instanceof ErrorHandlerInterface) { $ctrl->handle($e); }
    else { error_log("[RASTRO] " . $e->getMessage()); }
    header("Location: " . APP_URL . "/"); exit;
}
