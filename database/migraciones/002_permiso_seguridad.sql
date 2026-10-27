-- Migracion 002 (S6a.4): modulo «seguridad» (bitacora de accesos) y su permiso para el Administrador.
-- Solo AGREGA una fila a permisos (si no existe); no toca nada mas. Se puede correr mas de una vez.
-- Aplicala con:  .\database\migraciones\aplicar.ps1 -Base rastroinventario -Archivo 002_permiso_seguridad.sql -Confirmar
-- (hace antes un respaldo en C:\wamp64\respaldo_rastro\).

SET NAMES utf8mb4;

INSERT INTO permisos (rol_id, modulo, puede_ver, puede_crear, puede_editar, puede_borrar)
SELECT r.id, 'seguridad', 1, 0, 0, 0
FROM roles r
WHERE r.nombre = 'Administrador'
  AND NOT EXISTS (SELECT 1 FROM permisos p WHERE p.rol_id = r.id AND p.modulo = 'seguridad');
