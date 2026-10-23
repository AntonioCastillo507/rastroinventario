<?php
/**
 * Itbms — Cálculo único del ITBMS (7 %). Los precios del catálogo son SIN impuesto.
 * El impuesto se redondea una sola vez, sobre el subtotal del pedido.
 * En la base no hay columna de ITBMS: itbms = total - subtotal.
 */
class Itbms {
    public const TASA = 0.07;

    /** Desglose a partir del subtotal (sin impuesto) */
    public static function calcular(float $subtotal): array {
        $subtotal = round($subtotal, 2);
        $itbms    = round($subtotal * self::TASA, 2);
        return ["subtotal" => $subtotal, "itbms" => $itbms, "total" => round($subtotal + $itbms, 2)];
    }

    /** Desglose de un pedido ya guardado: lo que se cobró es lo que quedó en la base */
    public static function dePedido(array $pedido): array {
        $subtotal = round((float)$pedido["subtotal"], 2);
        $total    = round((float)$pedido["total"], 2);
        return ["subtotal" => $subtotal, "itbms" => round($total - $subtotal, 2), "total" => $total];
    }
}
