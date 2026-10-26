<?php
/**
 * Carrito — lo que la sesión sabe del carrito: SOLO pieza y cantidad ($_SESSION["cart"][parte_id] = cantidad).
 * Nombres, precios y stock se leen siempre de la base al mostrar y al cobrar; así lo que se ve es lo que se cobra.
 * Las sesiones anteriores guardaban un arreglo por línea (nombre, precio, cantidad, max...): se normalizan al leer.
 * También guarda el último "quitar" de una línea (un nivel, con caducidad) para poder deshacerlo.
 */
class Carrito {
    public const DESHACER_SEGUNDOS = 600;

    /** @return array<int,int> parte_id => cantidad (>= 1), ya normalizado en la sesión */
    public static function leer(): array {
        $bruto = $_SESSION["cart"] ?? [];
        if (!is_array($bruto)) { unset($_SESSION["cart"]); return []; }
        $limpio = [];
        foreach ($bruto as $id => $v) {
            $cant = is_array($v) ? ($v["cantidad"] ?? 0) : $v;
            $id = (int)$id; $cant = is_numeric($cant) ? (int)$cant : 0;
            if ($id > 0 && $cant >= 1) $limpio[$id] = $cant;
        }
        if ($limpio) $_SESSION["cart"] = $limpio; else unset($_SESSION["cart"]);
        return $limpio;
    }

    public static function cantidad(int $id): int {
        return self::leer()[$id] ?? 0;
    }

    public static function poner(int $id, int $cantidad): void {
        $c = self::leer();
        if ($cantidad >= 1) $c[$id] = $cantidad; else unset($c[$id]);
        if ($c) $_SESSION["cart"] = $c; else unset($_SESSION["cart"]);
    }

    public static function quitar(int $id): void {
        self::poner($id, 0);
    }

    public static function vaciar(): void {
        unset($_SESSION["cart"], $_SESSION["cart_deshacer"]);
    }

    // ── Deshacer el último "quitar" ──

    public static function recordarQuitada(int $id, int $cantidad, string $nombre): void {
        $_SESSION["cart_deshacer"] = ["id" => $id, "cantidad" => $cantidad, "nombre" => mb_substr($nombre, 0, 200), "hasta" => time() + self::DESHACER_SEGUNDOS];
    }

    /** @return array{id:int,cantidad:int,nombre:string}|null lo que se puede deshacer ahora, o null si no hay o caducó */
    public static function quitadaReciente(): ?array {
        $d = $_SESSION["cart_deshacer"] ?? null;
        if (!is_array($d) || !isset($d["id"], $d["cantidad"], $d["hasta"]) || (int)$d["hasta"] < time()) { unset($_SESSION["cart_deshacer"]); return null; }
        return ["id" => (int)$d["id"], "cantidad" => (int)$d["cantidad"], "nombre" => (string)($d["nombre"] ?? "")];
    }

    public static function olvidarQuitada(): void {
        unset($_SESSION["cart_deshacer"]);
    }

    /**
     * Huella de lo que la persona vio: pieza, cantidad y precio en centavos de cada línea. El paso "Tus datos" la manda
     * con el formulario; el servidor la recalcula desde la base y, si cambió, no crea el pedido. Solo detecta cambios.
     * @param list<array{id:int,cantidad:int,precio:float}> $lineas
     */
    public static function huella(array $lineas): string {
        $partes = [];
        foreach ($lineas as $l) $partes[] = (int)$l["id"] . ":" . (int)$l["cantidad"] . ":" . (int)round($l["precio"] * 100);
        sort($partes, SORT_STRING);
        return hash("sha256", implode("|", $partes));
    }
}
