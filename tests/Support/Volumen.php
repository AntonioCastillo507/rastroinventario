<?php
namespace Tests\Support;

use PDO;

/**
 * Datos de volumen para probar la paginación (todo inventado, correos @example.invalid).
 * Los valores se repiten a propósito (mismo precio, mismo stock, misma fecha y hora) para que un
 * ORDER BY sin desempate único duplique o salte filas entre páginas.
 * Dentro de una prueba se deshace con el rollback; para verlo en el navegador: composer test:volumen.
 */
final class Volumen {
    public const PARTES = 55;
    public const OTROS  = 45; // usuarios, pedidos, comentarios y ventas

    /** @return array{autos:list<int>,secciones:list<int>} ids creados */
    public static function crear(PDO $db): array {
        $autos = [];
        foreach (["Volumen Alfa", "Volumen Beta", "Volumen Gamma"] as $m) {
            $db->prepare("INSERT INTO autos (marca, modelo, anio) VALUES (?, 'Modelo V', 2020)")->execute([$m]);
            $autos[] = (int)$db->lastInsertId();
        }
        $secciones = [];
        foreach (["Volumen S1", "Volumen S2", "Volumen S3"] as $s) {
            $db->prepare("INSERT INTO secciones (nombre) VALUES (?)")->execute([$s]);
            $secciones[] = (int)$db->lastInsertId();
        }

        // Partes: 7 nombres, 5 precios y 4 niveles de stock que se repiten; cada 11.ª está inactiva
        $db->prepare("INSERT INTO partes (nombre, descripcion, auto_id, seccion_id, precio, cantidad, activo)
            WITH RECURSIVE n(i) AS (SELECT 1 UNION ALL SELECT i + 1 FROM n WHERE i < " . self::PARTES . ")
            SELECT CONCAT('Pieza Volumen ', i % 7), CONCAT('Descripción volumen ', i % 3),
                   ELT(1 + i % 3, ?, ?, ?), ELT(1 + i % 3, ?, ?, ?), 10 * (1 + i % 5), i % 4, IF(i % 11 = 0, 0, 1)
            FROM n")->execute([...$autos, ...$secciones]);

        $db->exec("INSERT INTO usuarios (nombre, email, password)
            WITH RECURSIVE n(i) AS (SELECT 1 UNION ALL SELECT i + 1 FROM n WHERE i < " . self::OTROS . ")
            SELECT CONCAT('Usuario volumen ', i % 5), CONCAT('volumen.', i, '@example.invalid'), '!' FROM n");

        // Pedidos y comentarios y ventas: todos con la misma fecha y hora
        $db->exec("INSERT INTO pedidos (cliente_nombre, cliente_telefono, cliente_email, cliente_direccion, estado, subtotal, total, creado_en)
            WITH RECURSIVE n(i) AS (SELECT 1 UNION ALL SELECT i + 1 FROM n WHERE i < " . self::OTROS . ")
            SELECT 'Cliente volumen', '555-0100', 'volumen@example.invalid', 'Calle Inventada 123',
                   ELT(1 + i % 4, 'pendiente', 'procesando', 'entregado', 'cancelado'), 10, 10.70, '2026-01-01 10:00:00' FROM n");
        $db->exec("INSERT INTO pedido_items (pedido_id, parte_id, parte_nombre, precio_unitario, cantidad)
            SELECT p.id, (SELECT MIN(id) FROM partes WHERE nombre LIKE 'Pieza Volumen%'), 'Pieza Volumen', 10, 1
            FROM pedidos p WHERE p.cliente_email = 'volumen@example.invalid'");
        $db->exec("INSERT INTO comentarios (parte_id, nombre_visitante, comentario, publicado, creado_en)
            WITH RECURSIVE n(i) AS (SELECT 1 UNION ALL SELECT i + 1 FROM n WHERE i < " . self::OTROS . ")
            SELECT (SELECT MIN(id) FROM partes WHERE nombre LIKE 'Pieza Volumen%'), CONCAT('Visitante ', i % 4), CONCAT('Comentario volumen ', i), 1, '2026-01-01 11:00:00' FROM n");
        $db->exec("INSERT INTO vendido_parte (parte_id, cantidad, precio_venta, fecha)
            WITH RECURSIVE n(i) AS (SELECT 1 UNION ALL SELECT i + 1 FROM n WHERE i < " . self::OTROS . ")
            SELECT (SELECT MIN(id) FROM partes WHERE nombre LIKE 'Pieza Volumen%'), 1 + i % 2, 10.00, '2026-01-01 12:00:00' FROM n");

        return ["autos" => $autos, "secciones" => $secciones];
    }
}
