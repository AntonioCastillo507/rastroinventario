<?php
/**
 * HerramientasController — utilidades de administración.
 * Por ahora: botón para borrar pedidos/ventas/cuentas de cliente de PRUEBA
 * antes de la presentación oficial, sin tocar autos/partes/secciones ni
 * las cuentas de Administrador/Vendedor/Consultor.
 * Solo accesible para el rol Administrador.
 */
class HerramientasController extends BaseController {

    public function index(): void {
        $this->soloAdmin();
        $db = Database::get();
        $totalPedidos  = (int)$db->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
        $totalVentas   = (int)$db->query("SELECT COUNT(*) FROM vendido_parte")->fetchColumn();
        $totalClientes = (int)$db->query(
            "SELECT COUNT(*) FROM usuarios u
             INNER JOIN usuario_roles ur ON ur.usuario_id=u.id
             INNER JOIN roles r ON r.id=ur.rol_id WHERE r.nombre='Cliente'"
        )->fetchColumn();
        $this->view("admin/herramientas/index", compact("totalPedidos", "totalVentas", "totalClientes"));
    }

    public function limpiarPrueba(): void {
        $this->soloAdmin();
        CsrfToken::verify();
        $db = Database::get();
        try {
            $nPedidos = $db->exec("DELETE FROM pedidos");
            $nVentas  = $db->exec("DELETE FROM vendido_parte");

            $stmt = $db->query(
                "SELECT u.id FROM usuarios u
                 INNER JOIN usuario_roles ur ON ur.usuario_id=u.id
                 INNER JOIN roles r ON r.id=ur.rol_id WHERE r.nombre='Cliente'"
            );
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $nClientes = 0;
            if ($ids) {
                $in  = implode(",", array_fill(0, count($ids), "?"));
                $del = $db->prepare("DELETE FROM usuarios WHERE id IN ($in)");
                $del->execute($ids);
                $nClientes = count($ids);
            }
            $this->redirect(
                APP_URL . "/admin/?mod=herramientas",
                "Datos de prueba eliminados: {$nPedidos} pedido(s), {$nVentas} venta(s), {$nClientes} cuenta(s) de cliente. Autos, partes y secciones no se tocaron.",
                "success"
            );
        } catch (\Throwable $e) {
            $this->redirect(APP_URL . "/admin/?mod=herramientas", "Error al limpiar: " . $e->getMessage(), "danger");
        }
    }

    private function soloAdmin(): void {
        $this->requireAuth();
        $roles = $_SESSION["user_roles"] ?? [];
        if (!in_array("Administrador", $roles, true)) {
            $this->flash("Solo un Administrador puede usar esta herramienta.", "danger");
            header("Location: " . APP_URL . "/admin/"); exit;
        }
    }
}
