<?php
/**
 * BaseController — Base con sesión, permisos, flash y vistas.
 * Implementa ErrorHandlerInterface.
 */
abstract class BaseController implements ErrorHandlerInterface {
    protected function requireAuth(): void {
        if (empty($_SESSION["user"])) {
            header("Location: " . APP_URL . "/login.php"); exit;
        }
    }
    protected function requirePermission(string $mod, string $acc = "puede_ver"): void {
        $this->requireAuth();
        if (!(new UserModel())->hasPermission($_SESSION["user"]["id"], $mod, $acc)) {
            $this->flash("Sin permiso para esta acción.", "danger");
            header("Location: " . APP_URL . "/admin/"); exit;
        }
    }
    protected function view(string $tpl, array $data = []): void {
        extract($data);
        include BASE_PATH . "/app/Views/" . $tpl . ".php";
    }
    public function redirect(string $url, string $msg = "", string $type = "success"): void {
        if ($msg) $_SESSION["flash"] = ["msg" => $msg, "type" => $type];
        header("Location: " . $url); exit;
    }
    protected function flash(string $msg, string $type = "success"): void {
        $_SESSION["flash"] = ["msg" => $msg, "type" => $type];
    }
    public function handle(\Throwable $e): void {
        $ref = registrarError($e);
        $this->flash("Ocurrió un error interno. Intenta de nuevo; si sigue, indica el código de referencia $ref.", "danger");
    }
    protected function isPost(): bool { return $_SERVER["REQUEST_METHOD"] === "POST"; }
    protected function ip(): string {
        // Solo REMOTE_ADDR: X-Forwarded-For lo escribe el cliente y permitiría esquivar el bloqueo de login
        return $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";
    }
}
