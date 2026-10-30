-- Quita TODOS los datos de demostración antes de entregar la tienda a un cliente real.
-- Qué borra (solo lo marcado como demo):
--   · comentarios con auto_pub = 2          (database/demo_opiniones.sql)
--   · pedidos con correo @example.invalid y sus líneas (pedido_items cae en cascada)
--   · usuarios con correo @example.invalid y sus roles (usuario_roles cae en cascada; login_logs queda con usuario_id NULL)
-- No toca piezas, secciones, autos ni marcas: si cargaste database/demo.sql ("Demo Motors"), esas filas hay que revisarlas a mano.
-- Hacer antes: respaldo en C:\wamp64\respaldo_rastro\ . Las ventas (vendido_parte) de piezas demo tampoco se borran aquí.
-- Uso:  mysql -u root -h 127.0.0.1 -P 3306 --default-character-set=utf8mb4 NOMBRE_BASE < database\quitar_demo.sql

SET NAMES utf8mb4;

START TRANSACTION;

DELETE FROM comentarios WHERE auto_pub = 2;
DELETE FROM pedidos     WHERE cliente_email LIKE '%@example.invalid';
DELETE FROM usuarios    WHERE email         LIKE '%@example.invalid';

COMMIT;

-- Comprobación: las tres cifras deben dar 0
SELECT (SELECT COUNT(*) FROM comentarios WHERE auto_pub = 2)                    AS comentarios_demo,
       (SELECT COUNT(*) FROM pedidos     WHERE cliente_email LIKE '%@example.invalid') AS pedidos_demo,
       (SELECT COUNT(*) FROM usuarios    WHERE email LIKE '%@example.invalid')  AS usuarios_demo;
