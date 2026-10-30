-- Migracion 006 (S8.1): direccion estructurada de Panama.
-- Crea provincias, distritos y corregimientos (los datos van aparte, en database/seed_panama.sql), agrega los campos de
-- direccion a usuarios (dir_*) y la copia fija de la direccion de entrega a pedidos (entrega_*).
-- Solo AGREGA: no cambia ni borra ninguna fila. Las direcciones escritas antes (usuarios.direccion y
-- pedidos.cliente_direccion) se conservan tal cual. Se puede correr mas de una vez.
-- Aplicala con:
--   .\database\migraciones\aplicar.ps1 -Base rastroinventario -Archivo 006_direccion_panama.sql -Confirmar
--   .\database\migraciones\aplicar.ps1 -Base rastroinventario -Archivo seed_panama.sql -Confirmar
-- (cada una hace antes un respaldo en C:\wamp64\respaldo_rastro\; el seed va DESPUES de esta migracion).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `provincias` (
  `codigo` char(2) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `tipo` enum('provincia','comarca') NOT NULL DEFAULT 'provincia',
  PRIMARY KEY (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `distritos` (
  `codigo` char(4) NOT NULL,
  `provincia_codigo` char(2) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `implicito` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`codigo`),
  KEY `provincia_codigo` (`provincia_codigo`),
  CONSTRAINT `distritos_ibfk_1` FOREIGN KEY (`provincia_codigo`) REFERENCES `provincias` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corregimientos` (
  `codigo` char(6) NOT NULL,
  `distrito_codigo` char(4) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `implicito` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`codigo`),
  KEY `distrito_codigo` (`distrito_codigo`),
  CONSTRAINT `corregimientos_ibfk_1` FOREIGN KEY (`distrito_codigo`) REFERENCES `distritos` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ayuda temporal: corre el ALTER solo si la columna o la clave aun no existe
DROP PROCEDURE IF EXISTS m006_agregar;
DELIMITER $$
CREATE PROCEDURE m006_agregar(IN tipo VARCHAR(10), IN tabla VARCHAR(64), IN nombre VARCHAR(64), IN ddl TEXT)
BEGIN
  DECLARE existe INT DEFAULT 0;
  IF tipo = 'COLUMN' THEN
    SELECT COUNT(*) INTO existe FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tabla AND COLUMN_NAME = nombre;
  ELSE
    SELECT COUNT(*) INTO existe FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tabla AND CONSTRAINT_NAME = nombre;
  END IF;
  IF existe = 0 THEN
    SET @ddl006 = CONCAT('ALTER TABLE `', tabla, '` ', ddl);
    PREPARE paso FROM @ddl006;
    EXECUTE paso;
    DEALLOCATE PREPARE paso;
  END IF;
END$$
DELIMITER ;

CALL m006_agregar('COLUMN', 'usuarios', 'dir_provincia',     'ADD COLUMN `dir_provincia` char(2) DEFAULT NULL AFTER `direccion`');
CALL m006_agregar('COLUMN', 'usuarios', 'dir_distrito',      'ADD COLUMN `dir_distrito` char(4) DEFAULT NULL AFTER `dir_provincia`');
CALL m006_agregar('COLUMN', 'usuarios', 'dir_corregimiento', 'ADD COLUMN `dir_corregimiento` char(6) DEFAULT NULL AFTER `dir_distrito`');
CALL m006_agregar('COLUMN', 'usuarios', 'dir_calle',         'ADD COLUMN `dir_calle` varchar(80) DEFAULT NULL AFTER `dir_corregimiento`');
CALL m006_agregar('COLUMN', 'usuarios', 'dir_casa',          'ADD COLUMN `dir_casa` varchar(60) DEFAULT NULL AFTER `dir_calle`');
CALL m006_agregar('COLUMN', 'usuarios', 'dir_barriada',      'ADD COLUMN `dir_barriada` varchar(80) DEFAULT NULL AFTER `dir_casa`');
CALL m006_agregar('COLUMN', 'usuarios', 'dir_referencia',    'ADD COLUMN `dir_referencia` varchar(120) DEFAULT NULL AFTER `dir_barriada`');
CALL m006_agregar('FK', 'usuarios', 'usuarios_ibfk_dir_provincia',
  'ADD KEY `dir_provincia` (`dir_provincia`), ADD CONSTRAINT `usuarios_ibfk_dir_provincia` FOREIGN KEY (`dir_provincia`) REFERENCES `provincias` (`codigo`)');
CALL m006_agregar('FK', 'usuarios', 'usuarios_ibfk_dir_distrito',
  'ADD KEY `dir_distrito` (`dir_distrito`), ADD CONSTRAINT `usuarios_ibfk_dir_distrito` FOREIGN KEY (`dir_distrito`) REFERENCES `distritos` (`codigo`)');
CALL m006_agregar('FK', 'usuarios', 'usuarios_ibfk_dir_corregimiento',
  'ADD KEY `dir_corregimiento` (`dir_corregimiento`), ADD CONSTRAINT `usuarios_ibfk_dir_corregimiento` FOREIGN KEY (`dir_corregimiento`) REFERENCES `corregimientos` (`codigo`)');

CALL m006_agregar('COLUMN', 'pedidos', 'entrega_provincia',     'ADD COLUMN `entrega_provincia` varchar(80) DEFAULT NULL AFTER `cliente_direccion`');
CALL m006_agregar('COLUMN', 'pedidos', 'entrega_distrito',      'ADD COLUMN `entrega_distrito` varchar(100) DEFAULT NULL AFTER `entrega_provincia`');
CALL m006_agregar('COLUMN', 'pedidos', 'entrega_corregimiento', 'ADD COLUMN `entrega_corregimiento` varchar(100) DEFAULT NULL AFTER `entrega_distrito`');
CALL m006_agregar('COLUMN', 'pedidos', 'entrega_calle',         'ADD COLUMN `entrega_calle` varchar(80) DEFAULT NULL AFTER `entrega_corregimiento`');
CALL m006_agregar('COLUMN', 'pedidos', 'entrega_casa',          'ADD COLUMN `entrega_casa` varchar(60) DEFAULT NULL AFTER `entrega_calle`');
CALL m006_agregar('COLUMN', 'pedidos', 'entrega_barriada',      'ADD COLUMN `entrega_barriada` varchar(80) DEFAULT NULL AFTER `entrega_casa`');
CALL m006_agregar('COLUMN', 'pedidos', 'entrega_referencia',    'ADD COLUMN `entrega_referencia` varchar(120) DEFAULT NULL AFTER `entrega_barriada`');

DROP PROCEDURE m006_agregar;
