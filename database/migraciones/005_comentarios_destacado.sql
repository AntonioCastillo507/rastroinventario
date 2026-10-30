-- Migracion 005 (S7b-D): comentarios.destacado (resena elegida para la portada; 0 = no, 1 = si).
-- Agrega la columna solo si falta, con todas las resenas actuales en 0. No borra ni cambia datos. Se puede correr mas de una vez.
-- Aplicala con:  .\database\migraciones\aplicar.ps1 -Base rastroinventario -Archivo 005_comentarios_destacado.sql -Confirmar
-- (hace antes un respaldo en C:\wamp64\respaldo_rastro\).

SET NAMES utf8mb4;

SET @falta = (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comentarios' AND COLUMN_NAME = 'destacado');
SET @ddl = IF(@falta,
  'ALTER TABLE `comentarios` ADD COLUMN `destacado` tinyint(1) NOT NULL DEFAULT ''0'' AFTER `publicado`',
  'SELECT 1');
PREPARE paso FROM @ddl;
EXECUTE paso;
DEALLOCATE PREPARE paso;
