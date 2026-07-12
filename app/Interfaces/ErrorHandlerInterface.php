<?php
/**
 * ErrorHandlerInterface — Contrato para manejo de errores y redirecciones.
 */
interface ErrorHandlerInterface {
    public function handle(\Throwable $e): void;
    public function redirect(string $url, string $msg = "", string $type = "error"): void;
}
