-- Datos minimos para que el sistema arranque: roles y permisos por modulo.
-- No lleva usuarios, contrasenas, pedidos ni inventario. El administrador se
-- crea despues con:  php database\crear_usuario.php
-- Carga esto despues de database/schema.sql, una sola vez por base.

SET NAMES utf8mb4;

INSERT INTO roles (id, nombre, descripcion) VALUES
  (1, 'Administrador', 'Control total del sistema'),
  (2, 'Vendedor',      'Registra ventas y consulta inventario'),
  (3, 'Consultor',     'Solo consulta el inventario'),
  (4, 'Cliente',       'Usuario registrado desde la tienda pública');

-- El rol Cliente no lleva permisos: eso es lo que le cierra el panel /admin/.
-- Los permisos se enlazan por nombre de rol, no por id.
INSERT INTO permisos (rol_id, modulo, puede_ver, puede_crear, puede_editar, puede_borrar)
SELECT r.id, p.modulo, p.ver, p.crear, p.editar, p.borrar
FROM roles r
JOIN (
  SELECT 'Administrador' AS rol, 'autos'       AS modulo, 1 AS ver, 1 AS crear, 1 AS editar, 1 AS borrar
  UNION ALL SELECT 'Administrador', 'comentarios', 1, 1, 1, 1
  UNION ALL SELECT 'Administrador', 'partes',      1, 1, 1, 1
  UNION ALL SELECT 'Administrador', 'pedidos',     1, 1, 1, 1
  UNION ALL SELECT 'Administrador', 'roles',       1, 1, 1, 1
  UNION ALL SELECT 'Administrador', 'secciones',   1, 1, 1, 1
  UNION ALL SELECT 'Administrador', 'usuarios',    1, 1, 1, 1
  UNION ALL SELECT 'Administrador', 'ventas',      1, 1, 1, 1
  UNION ALL SELECT 'Vendedor', 'autos',       1, 0, 0, 0
  UNION ALL SELECT 'Vendedor', 'comentarios', 1, 0, 0, 0
  UNION ALL SELECT 'Vendedor', 'partes',      1, 0, 0, 0
  UNION ALL SELECT 'Vendedor', 'pedidos',     1, 1, 1, 0
  UNION ALL SELECT 'Vendedor', 'roles',       1, 0, 0, 0
  UNION ALL SELECT 'Vendedor', 'secciones',   1, 0, 0, 0
  UNION ALL SELECT 'Vendedor', 'usuarios',    1, 0, 0, 0
  UNION ALL SELECT 'Vendedor', 'ventas',      1, 1, 0, 0
  UNION ALL SELECT 'Consultor', 'autos',       1, 0, 0, 0
  UNION ALL SELECT 'Consultor', 'comentarios', 1, 0, 0, 0
  UNION ALL SELECT 'Consultor', 'partes',      1, 0, 0, 0
  UNION ALL SELECT 'Consultor', 'roles',       1, 0, 0, 0
  UNION ALL SELECT 'Consultor', 'secciones',   1, 0, 0, 0
  UNION ALL SELECT 'Consultor', 'usuarios',    1, 0, 0, 0
  UNION ALL SELECT 'Consultor', 'ventas',      1, 0, 0, 0
) p ON p.rol = r.nombre;
