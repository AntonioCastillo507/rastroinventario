-- Migracion 003 (S7): tabla marcas y autos.marca_id.
-- Crea la tabla, agrega la columna con su llave foranea (solo si falta), da de alta una marca por cada nombre
-- distinto de autos.marca (sin importar mayusculas ni espacios de mas: «Honda», «honda» y «Honda » son una sola)
-- y deja autos.marca escrita con el nombre de la tabla. No borra nada. Se puede correr mas de una vez.
-- Aplicala con:  .\database\migraciones\aplicar.ps1 -Base rastroinventario -Archivo 003_marcas.sql -Confirmar
-- (hace antes un respaldo en C:\wamp64\respaldo_rastro\).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `marcas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_marcas_nombre` (`nombre`),
  UNIQUE KEY `uk_marcas_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @falta = (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'autos' AND COLUMN_NAME = 'marca_id');
SET @ddl = IF(@falta,
  'ALTER TABLE `autos` ADD COLUMN `marca_id` int DEFAULT NULL AFTER `marca`, ADD KEY `marca_id` (`marca_id`), ADD CONSTRAINT `autos_ibfk_marca` FOREIGN KEY (`marca_id`) REFERENCES `marcas` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE paso FROM @ddl;
EXECUTE paso;
DEALLOCATE PREPARE paso;

-- DATOS (las pruebas ejecutan solo lo que sigue a esta linea)
-- Una marca por nombre distinto; el nombre es el del auto de menor id, sin espacios en los extremos
INSERT INTO marcas (nombre, slug)
SELECT n.nombre, LOWER(REPLACE(n.nombre, ' ', '-'))
FROM (
  SELECT TRIM(a.marca) AS nombre FROM autos a
  WHERE TRIM(a.marca) <> ''
    AND a.id = (SELECT MIN(b.id) FROM autos b WHERE TRIM(b.marca) = TRIM(a.marca))
) n
WHERE NOT EXISTS (SELECT 1 FROM marcas m WHERE m.nombre = n.nombre);

-- Cada auto apunta a su marca y toma su nombre exacto (BINARY: la coleccion ignora mayusculas y espacios finales)
UPDATE autos a
JOIN marcas m ON m.nombre = TRIM(a.marca)
SET a.marca_id = m.id, a.marca = m.nombre
WHERE a.marca_id IS NULL OR BINARY a.marca <> BINARY m.nombre;
