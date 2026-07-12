<?php
class DigitalSignature {
    public static function sign(string $data): string {
        return hash_hmac("sha256", $data, HMAC_KEY);
    }
    public static function verify(string $data, string $firma): bool {
        return hash_equals(self::sign($data), $firma);
    }
    private static function ventaStr(array $v): string {
        // Precio SIEMPRE con 2 decimales para evitar mismatch PHP vs MySQL
        return $v["parte_id"]."|".$v["cantidad"]."|".
               number_format((float)$v["precio_venta"],2,".",""). "|".
               ($v["usuario_id"]??"0")."|".$v["fecha"];
    }
    public static function signVenta(array $v): string   { return self::sign(self::ventaStr($v)); }
    public static function verifyVenta(array $v): bool   {
        if (empty($v["firma_digital"])) return false;
        return self::verify(self::ventaStr($v), $v["firma_digital"]);
    }
    public static function signParte(array $p): string {
        return self::sign($p["id"]."|".$p["nombre"]."|".$p["precio"]."|".$p["creado_en"]);
    }
}
