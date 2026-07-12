<?php
/**
 * HmacCrypto — Implementación HMAC-SHA256 de CryptoInterface.
 * Usada para firmar ventas, partes y pedidos en la aplicación.
 */
class HmacCrypto implements CryptoInterface {
    public function firmar(string $datos, string $clave): string {
        return hash_hmac("sha256", $datos, $clave);
    }
    public function verificar(string $datos, string $firma, string $clave): bool {
        return hash_equals($this->firmar($datos, $clave), $firma);
    }
    public function algoritmo(): string { return "HMAC-SHA256"; }
}
