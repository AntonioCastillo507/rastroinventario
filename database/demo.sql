-- Datos de demostración, todos inventados: marca "Demo Motors", correos @example.invalid,
-- teléfonos 555-01xx, sin imágenes y sin contraseñas reales.
-- Cárgalo una sola vez en una base recién creada (schema.sql + seed_basico.sql), antes o
-- después de crear tu administrador. Para entrar con un usuario demo, ponle clave con:
--   php database\crear_usuario.php --actualizar
-- (hasta entonces su contraseña es '!', que no es un hash válido: nadie puede entrar).
-- Las firmas digitales quedan en NULL (necesitan la HMAC_KEY, que no va en un .sql) y no hay
-- filas en vendido_parte, así que "Ventas" y "Más vendidas" salen vacías.

SET NAMES utf8mb4;

INSERT INTO autos (id, marca, modelo, anio) VALUES
  (1, 'Demo Motors', 'Sedán',     2020),
  (2, 'Demo Motors', 'Hatchback', 2021),
  (3, 'Demo Motors', 'Pickup',    2019),
  (4, 'Demo Motors', 'SUV',       2022);

INSERT INTO secciones (id, nombre, descripcion) VALUES
  (1, 'Motor',     'Piezas del motor y filtros'),
  (2, 'Frenos',    'Pastillas, discos y líquido'),
  (3, 'Suspensión', 'Amortiguadores y rótulas'),
  (4, 'Eléctrico', 'Batería, faros y alternadores');

-- thumbnail e imagen_grande en NULL: la tienda muestra la pieza sin imagen
INSERT INTO partes (id, nombre, descripcion, auto_id, seccion_id, precio, cantidad) VALUES
  (1,  'Filtro de aceite',              'Pieza de demostración.', 1, 1,   8.50, 40),
  (2,  'Bujía de encendido',            'Pieza de demostración.', 1, 1,   6.00, 60),
  (3,  'Correa de distribución',        'Pieza de demostración.', 2, 1,  35.00, 12),
  (4,  'Pastillas de freno delanteras', 'Pieza de demostración.', 1, 2,  28.00, 24),
  (5,  'Disco de freno ventilado',      'Pieza de demostración.', 2, 2,  45.00, 10),
  (6,  'Amortiguador trasero',          'Pieza de demostración.', 3, 3,  52.00,  8),
  (7,  'Rótula de suspensión',          'Pieza de demostración.', 3, 3,  18.50, 20),
  (8,  'Batería 12V',                   'Pieza de demostración.', 4, 4,  95.00,  5),
  (9,  'Alternador reacondicionado',    'Pieza de demostración.', 4, 4, 120.00,  3),
  (10, 'Faro delantero derecho',        'Pieza de demostración.', 2, 4,  64.00,  5),
  (11, 'Kit de embrague',               'Pieza de demostración (agotada).', 1, 1, 150.00, 0),
  (12, 'Filtro de aire',                'Pieza de demostración.', 3, 1,  12.00, 29);

-- Usuarios de ejemplo; password '!' = nadie puede iniciar sesión
INSERT INTO usuarios (nombre, email, telefono, direccion, password) VALUES
  ('Vendedor Demo',  'vendedor.demo@example.invalid',  '555-0101', '', '!'),
  ('Consultor Demo', 'consultor.demo@example.invalid', '555-0102', '', '!'),
  ('Cliente Demo',   'cliente.demo@example.invalid',   '555-0103', 'Calle Inventada 123, Ciudad Demo', '!');

INSERT INTO usuario_roles (usuario_id, rol_id)
SELECT u.id, r.id FROM usuarios u JOIN roles r
  ON (u.email = 'vendedor.demo@example.invalid'  AND r.nombre = 'Vendedor')
  OR (u.email = 'consultor.demo@example.invalid' AND r.nombre = 'Consultor')
  OR (u.email = 'cliente.demo@example.invalid'   AND r.nombre = 'Cliente');

-- Total = subtotal + ITBMS 7 % (redondeado una vez sobre el subtotal). El stock de las
-- partes ya refleja los pedidos no cancelados.
INSERT INTO pedidos (id, cliente_nombre, cliente_telefono, cliente_email, cliente_direccion, cliente_notas,
                     usuario_id, estado, subtotal, total, creado_en)
SELECT p.id, 'Cliente Demo', '555-0103', 'cliente.demo@example.invalid', 'Calle Inventada 123, Ciudad Demo',
       p.notas, u.id, p.estado, p.subtotal, p.total, p.creado_en
FROM usuarios u
JOIN (
  SELECT 1 AS id, 'entregado' AS estado, 45.00 AS subtotal,  48.15 AS total, 'Pedido de ejemplo' AS notas, '2026-01-10 10:00:00' AS creado_en
  UNION ALL SELECT 2, 'pendiente', 107.00, 114.49, NULL, '2026-01-15 15:30:00'
  UNION ALL SELECT 3, 'cancelado',  12.00,  12.84, 'Pedido cancelado de ejemplo', '2026-01-20 09:15:00'
) p
WHERE u.email = 'cliente.demo@example.invalid';

INSERT INTO pedido_items (pedido_id, parte_id, parte_nombre, precio_unitario, cantidad) VALUES
  (1, 1,  'Filtro de aceite',              8.50, 2),
  (1, 4,  'Pastillas de freno delanteras', 28.00, 1),
  (2, 8,  'Batería 12V',                   95.00, 1),
  (2, 12, 'Filtro de aire',                12.00, 1),
  (3, 2,  'Bujía de encendido',            6.00, 2);
