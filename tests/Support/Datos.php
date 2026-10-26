<?php
namespace Tests\Support;

use PDO;

/** Filas de apoyo para las pruebas de integración (todo inventado, correos @example.invalid). */
final class Datos {
    private static int $n = 0;

    public static function parte(PDO $db, float $precio = 10.00, int $cantidad = 10, string $nombre = ""): int {
        self::$n++;
        $nombre = $nombre !== "" ? $nombre : "Pieza de prueba " . self::$n;
        $db->prepare("INSERT INTO partes (nombre, descripcion, precio, cantidad) VALUES (?, 'Prueba', ?, ?)")
           ->execute([$nombre, $precio, $cantidad]);
        return (int)$db->lastInsertId();
    }

    public static function usuario(PDO $db): int {
        self::$n++;
        $email = "prueba" . self::$n . "." . bin2hex(random_bytes(4)) . "@example.invalid";
        $db->prepare("INSERT INTO usuarios (nombre, email, password) VALUES (?, ?, '!')")
           ->execute(["Usuario de prueba " . self::$n, $email]);
        return (int)$db->lastInsertId();
    }

    /**
     * Huella (resumen_visto) de un carrito [parte_id => cantidad] con los precios que hay AHORA en la base: lo que mandaría
     * el formulario del paso 2 si se hubiera abierto en este momento. Las cantidades deben caber en el stock.
     */
    public static function huella(PDO $db, array $cart): string {
        $lineas = [];
        foreach ($cart as $id => $cant) {
            $cant = is_array($cant) ? ($cant["cantidad"] ?? 0) : $cant;
            $s = $db->prepare("SELECT precio FROM partes WHERE id = ?");
            $s->execute([(int)$id]);
            $lineas[] = ["id" => (int)$id, "cantidad" => (int)$cant, "precio" => (float)$s->fetchColumn()];
        }
        return \Carrito::huella($lineas);
    }

    public static function stock(PDO $db, int $parteId): int {
        $s = $db->prepare("SELECT cantidad FROM partes WHERE id = ?");
        $s->execute([$parteId]);
        return (int)$s->fetchColumn();
    }

    /**
     * Crea un pedido con PedidoModel::registrar. $items: [[parte_id, cantidad], ...]; el precio y el
     * nombre se leen de la parte. No toca el stock (eso lo hace el controlador al comprar).
     * Con $apartar = true descuenta el stock como lo haría la compra.
     */
    public static function pedido(PDO $db, array $items, string $estado = "pendiente", ?int $usuarioId = null, bool $apartar = true): int {
        $lineas = [];
        foreach ($items as [$parteId, $cantidad]) {
            $p = $db->prepare("SELECT nombre, precio FROM partes WHERE id = ?");
            $p->execute([$parteId]);
            $r = $p->fetch();
            $lineas[] = ["parte_id" => $parteId, "parte_nombre" => $r["nombre"], "precio_unitario" => (float)$r["precio"], "cantidad" => $cantidad];
            if ($apartar) {
                $db->prepare("UPDATE partes SET cantidad = cantidad - ? WHERE id = ?")->execute([$cantidad, $parteId]);
            }
        }
        $id = (new \PedidoModel())->registrar([
            "cliente_nombre" => "Cliente de prueba", "cliente_telefono" => "555-0100",
            "cliente_email" => "cliente.prueba@example.invalid", "cliente_direccion" => "Calle Inventada 123",
            "cliente_notas" => null, "usuario_id" => $usuarioId,
        ], $lineas);
        if ($estado !== "pendiente") {
            $db->prepare("UPDATE pedidos SET estado = ? WHERE id = ?")->execute([$estado, $id]);
        }
        return (int)$id;
    }

    public static function estado(PDO $db, int $pedidoId): string {
        $s = $db->prepare("SELECT estado FROM pedidos WHERE id = ?");
        $s->execute([$pedidoId]);
        return (string)$s->fetchColumn();
    }

    public static function ventas(PDO $db, int $parteId): int {
        $s = $db->prepare("SELECT COUNT(*) FROM vendido_parte WHERE parte_id = ?");
        $s->execute([$parteId]);
        return (int)$s->fetchColumn();
    }
}
