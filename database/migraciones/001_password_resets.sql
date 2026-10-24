-- Migracion 001 (hallazgo 14b): tabla de recuperacion de contrasena.
-- Solo AGREGA una tabla; no toca ninguna fila ni columna existente. Es la misma definicion que
-- esta al final de database/schema.sql. Se puede correr mas de una vez (IF NOT EXISTS).
-- Aplicala con:  .\database\migraciones\aplicar.ps1 -Base rastroinventario -Confirmar
-- (hace antes un respaldo en C:\wamp64\respaldo_rastro\).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `ip` varchar(45) NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expira_en` timestamp NULL DEFAULT NULL,
  `usado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `usuario_id` (`usuario_id`),
  KEY `email_creado` (`email`,`creado_en`),
  KEY `ip_creado` (`ip`,`creado_en`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
