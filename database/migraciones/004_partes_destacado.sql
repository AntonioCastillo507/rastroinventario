-- Migracion 004 (S7b-C.2): partes.destacado (pieza destacada en la portada; 0 = no, 1 = si).
-- Agrega la columna solo si falta, con todas las piezas actuales en 0. No borra ni cambia datos. Se puede correr mas de una vez.
-- Aplicala con:  .\database\migraciones\aplicar.ps1 -Base rastroinventario -Archivo 004_partes_destacado.sql -Confirmar
-- (hace antes un respaldo en C:\wamp64\respaldo_rastro\).

SET NAMES utf8mb4;

SET @falta = (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partes' AND COLUMN_NAME = 'destacado');
SET @ddl = IF(@falta,
  'ALTER TABLE `partes` ADD COLUMN `destacado` tinyint(1) NOT NULL DEFAULT ''0'' AFTER `activo`',
  'SELECT 1');
PREPARE paso FROM @ddl;
EXECUTE paso;
DEALLOCATE PREPARE paso;
