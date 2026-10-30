-- Esquema de Rastroinventario: solo estructura (sin datos ni AUTO_INCREMENT fijos).
-- Crea antes la base:  CREATE DATABASE <nombre> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Despues carga este archivo y database/seed_basico.sql sobre esa base.

SET NAMES utf8mb4;

CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Division politico-administrativa de Panama (datos en seed_panama.sql). implicito = 1: unidad que solo completa
-- la cadena (distrito ficticio de Guna Yala, area unica de Naso Tjer Di); el formulario no la muestra.
CREATE TABLE `provincias` (
  `codigo` char(2) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `tipo` enum('provincia','comarca') NOT NULL DEFAULT 'provincia',
  PRIMARY KEY (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `distritos` (
  `codigo` char(4) NOT NULL,
  `provincia_codigo` char(2) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `implicito` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`codigo`),
  KEY `provincia_codigo` (`provincia_codigo`),
  CONSTRAINT `distritos_ibfk_1` FOREIGN KEY (`provincia_codigo`) REFERENCES `provincias` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `corregimientos` (
  `codigo` char(6) NOT NULL,
  `distrito_codigo` char(4) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `implicito` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`codigo`),
  KEY `distrito_codigo` (`distrito_codigo`),
  CONSTRAINT `corregimientos_ibfk_1` FOREIGN KEY (`distrito_codigo`) REFERENCES `distritos` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `direccion` text,
  `dir_provincia` char(2) DEFAULT NULL,
  `dir_distrito` char(4) DEFAULT NULL,
  `dir_corregimiento` char(6) DEFAULT NULL,
  `dir_calle` varchar(80) DEFAULT NULL,
  `dir_casa` varchar(60) DEFAULT NULL,
  `dir_barriada` varchar(80) DEFAULT NULL,
  `dir_referencia` varchar(120) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `intentos_fallidos` tinyint DEFAULT '0',
  `bloqueado` tinyint(1) DEFAULT '0',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `dir_provincia` (`dir_provincia`),
  KEY `dir_distrito` (`dir_distrito`),
  KEY `dir_corregimiento` (`dir_corregimiento`),
  CONSTRAINT `usuarios_ibfk_dir_provincia` FOREIGN KEY (`dir_provincia`) REFERENCES `provincias` (`codigo`),
  CONSTRAINT `usuarios_ibfk_dir_distrito` FOREIGN KEY (`dir_distrito`) REFERENCES `distritos` (`codigo`),
  CONSTRAINT `usuarios_ibfk_dir_corregimiento` FOREIGN KEY (`dir_corregimiento`) REFERENCES `corregimientos` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `marcas` (
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

CREATE TABLE `autos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `marca` varchar(100) NOT NULL,
  `marca_id` int DEFAULT NULL,
  `modelo` varchar(100) NOT NULL,
  `anio` year NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `marca_id` (`marca_id`),
  CONSTRAINT `autos_ibfk_marca` FOREIGN KEY (`marca_id`) REFERENCES `marcas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `secciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `partes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text,
  `auto_id` int DEFAULT NULL,
  `seccion_id` int DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cantidad` int NOT NULL DEFAULT '0',
  `thumbnail` varchar(255) DEFAULT NULL,
  `imagen_grande` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `destacado` tinyint(1) NOT NULL DEFAULT '0',
  `firma_digital` varchar(64) DEFAULT NULL,
  `creado_por` int DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `auto_id` (`auto_id`),
  KEY `seccion_id` (`seccion_id`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `partes_ibfk_1` FOREIGN KEY (`auto_id`) REFERENCES `autos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `partes_ibfk_2` FOREIGN KEY (`seccion_id`) REFERENCES `secciones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `partes_ibfk_3` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pedidos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cliente_nombre` varchar(150) NOT NULL,
  `cliente_telefono` varchar(30) NOT NULL,
  `cliente_email` varchar(150) NOT NULL,
  `cliente_direccion` text NOT NULL,
  `entrega_provincia` varchar(80) DEFAULT NULL,
  `entrega_distrito` varchar(100) DEFAULT NULL,
  `entrega_corregimiento` varchar(100) DEFAULT NULL,
  `entrega_calle` varchar(80) DEFAULT NULL,
  `entrega_casa` varchar(60) DEFAULT NULL,
  `entrega_barriada` varchar(80) DEFAULT NULL,
  `entrega_referencia` varchar(120) DEFAULT NULL,
  `cliente_notas` text,
  `usuario_id` int DEFAULT NULL,
  `estado` enum('pendiente','procesando','entregado','cancelado') DEFAULT 'pendiente',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `firma_digital` varchar(64) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pedido_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pedido_id` int NOT NULL,
  `parte_id` int DEFAULT NULL,
  `parte_nombre` varchar(150) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `cantidad` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `pedido_id` (`pedido_id`),
  KEY `parte_id` (`parte_id`),
  CONSTRAINT `pedido_items_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pedido_items_ibfk_2` FOREIGN KEY (`parte_id`) REFERENCES `partes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permisos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rol_id` int NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `puede_ver` tinyint(1) DEFAULT '1',
  `puede_crear` tinyint(1) DEFAULT '0',
  `puede_editar` tinyint(1) DEFAULT '0',
  `puede_borrar` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rol_mod` (`rol_id`,`modulo`),
  CONSTRAINT `permisos_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_roles` (
  `usuario_id` int NOT NULL,
  `rol_id` int NOT NULL,
  PRIMARY KEY (`usuario_id`,`rol_id`),
  KEY `rol_id` (`rol_id`),
  CONSTRAINT `usuario_roles_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `usuario_roles_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `comentarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parte_id` int NOT NULL,
  `nombre_visitante` varchar(100) NOT NULL,
  `comentario` text NOT NULL,
  `rating` tinyint DEFAULT NULL,
  `auto_pub` tinyint(1) DEFAULT '0',
  `publicado` tinyint(1) DEFAULT '0',
  `destacado` tinyint(1) NOT NULL DEFAULT '0',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parte_id` (`parte_id`),
  CONSTRAINT `comentarios_ibfk_1` FOREIGN KEY (`parte_id`) REFERENCES `partes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int DEFAULT NULL,
  `email_intento` varchar(150) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `exitoso` tinyint(1) DEFAULT '0',
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vendido_parte` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parte_id` int NOT NULL,
  `cantidad` int NOT NULL DEFAULT '1',
  `precio_venta` decimal(10,2) NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `firma_digital` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parte_id` (`parte_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `vendido_parte_ibfk_1` FOREIGN KEY (`parte_id`) REFERENCES `partes` (`id`),
  CONSTRAINT `vendido_parte_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recuperacion de contrasena (hallazgo 14b). Una fila por solicitud con un correo valido: con cuenta
-- activa lleva usuario_id y token_hash (SHA-256 del token; el token nunca se guarda); sin cuenta, ambos
-- NULL, asi los limites cuentan igual los correos que no existen. usado_en = consumido o reemplazado.
CREATE TABLE `password_resets` (
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
