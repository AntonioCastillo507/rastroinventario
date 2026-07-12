<?php
/**
 * CryptoInterface — Contrato unificado para servicios criptográficos.
 * Requisito: Implementar un patrón de diseño basado en contratos (Interfaces)
 * para unificar los servicios criptográficos de la aplicación.
 */
interface CryptoInterface {
    /** Genera un hash/firma de los datos usando la clave proporcionada. */
    public function firmar(string $datos, string $clave): string;
    /** Verifica que la firma sea válida para los datos. */
    public function verificar(string $datos, string $firma, string $clave): bool;
    /** Devuelve el nombre del algoritmo utilizado. */
    public function algoritmo(): string;
}
