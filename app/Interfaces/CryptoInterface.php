<?php
/**
 * CryptoInterface — Contrato unificado para servicios criptográficos.
 * Unifica los servicios criptográficos de la aplicación detrás de un contrato.
 */
interface CryptoInterface {
    /** Genera un hash/firma de los datos usando la clave proporcionada. */
    public function firmar(string $datos, string $clave): string;
    /** Verifica que la firma sea válida para los datos. */
    public function verificar(string $datos, string $firma, string $clave): bool;
    /** Devuelve el nombre del algoritmo utilizado. */
    public function algoritmo(): string;
}
