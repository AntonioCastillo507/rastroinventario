-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 12-07-2026 a las 05:34:59
-- Versión del servidor: 8.4.7
-- Versión de PHP: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `rastroinventario`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `autos`
--

DROP TABLE IF EXISTS `autos`;
CREATE TABLE IF NOT EXISTS `autos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `marca` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `anio` year NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `autos`
--

INSERT INTO `autos` (`id`, `marca`, `modelo`, `anio`, `activo`, `creado_en`) VALUES
(1, 'Toyota', 'Corolla', '2018', 1, '2026-06-10 13:38:05'),
(2, 'Honda', 'Civic', '2020', 1, '2026-06-10 13:38:05'),
(3, 'Hyundai', 'Elantra', '2019', 1, '2026-06-10 13:38:05'),
(4, 'Chevrolet', 'Cruze', '2017', 1, '2026-06-10 13:38:05'),
(5, 'Nissan', 'Sentra', '2021', 1, '2026-06-10 13:38:05'),
(6, 'Kia', 'Rio', '2022', 1, '2026-06-10 13:38:05'),
(7, 'Toyota', 'Corolla', '2026', 1, '2026-06-24 15:11:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comentarios`
--

DROP TABLE IF EXISTS `comentarios`;
CREATE TABLE IF NOT EXISTS `comentarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parte_id` int NOT NULL,
  `nombre_visitante` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comentario` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` tinyint DEFAULT NULL,
  `auto_pub` tinyint(1) DEFAULT '0',
  `publicado` tinyint(1) DEFAULT '0',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parte_id` (`parte_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `comentarios`
--

INSERT INTO `comentarios` (`id`, `parte_id`, `nombre_visitante`, `comentario`, `rating`, `auto_pub`, `publicado`, `creado_en`) VALUES
(1, 15, 'Anthony Fetuccinne', 'Buena calidas, aunque me gustaria que el empaque tuviera papel burbuja porque en el viaje se pudo haber estropeado.', 3, 1, 1, '2026-07-12 05:21:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_logs`
--

DROP TABLE IF EXISTS `login_logs`;
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int DEFAULT NULL,
  `email_intento` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exitoso` tinyint(1) DEFAULT '0',
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `login_logs`
--

INSERT INTO `login_logs` (`id`, `usuario_id`, `email_intento`, `ip`, `exitoso`, `fecha`) VALUES
(1, 1, 'admin@rastroinventario.com', '::1', 1, '2026-07-10 22:33:33'),
(2, 1, 'admin@rastroinventario.com', '::1', 1, '2026-07-10 22:53:36'),
(3, 1, 'admin@rastroinventario.com', '::1', 1, '2026-07-11 17:24:34'),
(4, 19, 'tonybusinesspty@gmail.com', '::1', 1, '2026-07-11 18:24:38'),
(5, 19, 'tonybusinesspty@gmail.com', '::1', 0, '2026-07-11 18:24:55'),
(6, 19, 'tonybusinesspty@gmail.com', '::1', 1, '2026-07-11 18:25:06'),
(7, 1, 'admin@rastroinventario.com', '::1', 1, '2026-07-11 18:45:15'),
(8, 20, 'toni507hd@gmail.com', '::1', 1, '2026-07-11 18:49:45'),
(9, 1, 'admin@rastroinventario.com', '::1', 1, '2026-07-11 18:52:01'),
(10, 19, 'tonybusinesspty@gmail.com', '::1', 1, '2026-07-12 00:18:09'),
(11, 19, 'tonybusinesspty@gmail.com', '::1', 1, '2026-07-12 00:23:34'),
(12, 3, 'vendedor1@rastroinventario.com', '::1', 1, '2026-07-12 02:05:12'),
(13, NULL, 'toni507pro@gmail.com', '::1', 0, '2026-07-12 05:15:47'),
(14, 21, 'duncanrules7f@gmail.com', '::1', 1, '2026-07-12 05:15:59'),
(15, 1, 'admin@rastroinventario.com', '::1', 1, '2026-07-12 05:23:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `partes`
--

DROP TABLE IF EXISTS `partes`;
CREATE TABLE IF NOT EXISTS `partes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `auto_id` int DEFAULT NULL,
  `seccion_id` int DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cantidad` int NOT NULL DEFAULT '0',
  `thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagen_grande` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `firma_digital` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_por` int DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `auto_id` (`auto_id`),
  KEY `seccion_id` (`seccion_id`),
  KEY `creado_por` (`creado_por`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `partes`
--

INSERT INTO `partes` (`id`, `nombre`, `descripcion`, `auto_id`, `seccion_id`, `precio`, `cantidad`, `thumbnail`, `imagen_grande`, `activo`, `firma_digital`, `creado_por`, `creado_en`) VALUES
(1, 'Bujías DENSO (set x4)', 'Piezas originales DENSO. Experimente las bujías de larga vida útil DENSO Iridium. Una chispa más grande se traduce en más potencia y economía de combustible superior. DENSO ofrece un rendimiento integral del vehículo para una amplia gama de vehículos OE.', 7, 4, 40.00, 20, 'parte_6a527cbf386a15.06940514_th.jpg', 'parte_6a527cbf386a15.06940514.jpg', 1, '2c3e4f3127fbe37bc16e2126c805bda689df6fe06b42a5c14b58fdb8f14f8e9d', 1, '2026-06-24 15:14:13'),
(2, 'Puerta Delantera Izquierda', 'Puerta completa con vidrio, en buen estado. Apta para reemplazo directo.', 1, 1, 150.00, 12, 'parte_6a51c687c18f53.72563660_th.jpg', 'parte_6a51c687c18f53.72563660.webp', 1, '07c350f2aed7ed46af1d6050a90992ba8bf402512461848758c2d2efae0b842e', 1, '2026-07-06 14:17:44'),
(3, 'Capó Completo', 'Capó original sin daños de pintura ni abolladuras.', 2, 1, 280.00, 4, 'parte_6a51c63d4e57c7.47011241_th.jpg', 'parte_6a51c63d4e57c7.47011241.webp', 1, 'f5a07364a93fcb8076e8a2698c1ed48533eeed712aaa891f444f0f3e53a71359', 1, '2026-07-06 14:17:44'),
(4, 'Guardafango Delantero Derecho', 'Guardafango metálico, mínimo óxido superficial, restaurable.', 3, 1, 85.00, 18, 'parte_6a51c5fd29a4f0.92761096_th.jpg', 'parte_6a51c5fd29a4f0.92761096.jpg', 1, '169ebe08b478a271ee4037914a8b900e4287875112c27643af600414e60a4ada', 1, '2026-07-06 14:17:44'),
(5, 'Parachoques Frontal', 'Parachoques delantero original con molduras laterales incluidas.', 5, 1, 120.00, 7, 'parte_6a51c5c38cb033.24502676_th.jpg', 'parte_6a51c5c38cb033.24502676.webp', 1, '4ec954c8645da07559c5435e5103d9704472f9d087030021511d957dfcc3cd5e', 1, '2026-07-06 14:17:44'),
(6, 'Parachoques Trasero', 'Parachoques trasero en excelente estado, color gris perlado.', 4, 1, 110.00, 9, 'parte_6a51c4b4c74b08.19601664_th.jpg', 'parte_6a51c4b4c74b08.19601664.jpg', 1, 'a5f0788312e7fae693ad45db6a0ee352f94a3693775d6c0498861bacad4b1e60', 1, '2026-07-06 14:17:44'),
(7, 'Puerta Trasera Izquierda', 'Puerta trasera izq. con mecanismo de seguro funcional.', 6, 1, 130.00, 3, 'parte_6a51c46d795f02.45710155_th.jpg', 'parte_6a51c46d795f02.45710155.webp', 1, 'd4ab0aa376aaa4e085a3045273b37e30c8c1fb41e80e0ef4d1dba7b0124c1d13', 1, '2026-07-06 14:17:44'),
(8, 'Guardafango Trasero Derecho', 'Guardafango trasero der. original. Sin golpes ni corrosión.', 1, 1, 75.00, 22, 'parte_6a51c31c03e658.98123408_th.jpg', 'parte_6a51c31c03e658.98123408.jpg', 1, '0e6a8e2a3ef551bb4088eb936b0b030b07c5d56e73f1c0e680c3438e4f61ab82', 1, '2026-07-06 14:17:44'),
(9, 'Techo Completo con Riel', 'Panel de techo completo con riel deslizante de quemacocos.', 2, 1, 350.00, 2, 'parte_6a51c2e2b5e973.29526889_th.jpg', 'parte_6a51c2e2b5e973.29526889.jpg', 1, 'da1c5d57c80baabc3f62e1e08bdafdab6cfca6b11f526b786a7c636cee19a5e8', 1, '2026-07-06 14:17:44'),
(10, 'Estribo Lateral Derecho', 'Estribo plástico lateral derecho, color negro, sin grietas.', 3, 1, 65.00, 14, 'parte_6a51c0d74c83a0.56087373_th.jpg', 'parte_6a51c0d74c83a0.56087373.webp', 1, '0c88ef7bb112ca61878e547f61bc95a6a54d21426c06923af2a12b96779eb838', 1, '2026-07-06 14:17:44'),
(11, 'Defensa Lateral Delantera', 'Moldura de defensa lateral delantera izquierda, original.', 5, 1, 45.00, 30, 'parte_6a51bf7a3e54d6.33646364_th.jpg', 'parte_6a51bf7a3e54d6.33646364.jpg', 1, 'ff3722b273bd59b024212eef99e90203e9695f68de0107e5d907f99353f74ab6', 1, '2026-07-06 14:17:44'),
(12, 'Moldura de Puerta Delantera', 'Moldura decorativa de puerta delantera, cromo/negro.', 4, 1, 25.00, 50, 'parte_6a51bf3332c1c2.29562969_th.jpg', 'parte_6a51bf3332c1c2.29562969.webp', 1, '0a78b1e501f3138672882cfcb73c53686cf99a9129c940fba2db7f909a9c2a5c', 1, '2026-07-06 14:17:44'),
(13, 'Tapa de Maletero', 'Tapa de maletero completa con cerradura y mecanismo.', 6, 1, 200.00, 5, 'parte_6a51bef93b15b8.50762468_th.jpg', 'parte_6a51bef93b15b8.50762468.webp', 1, '306a1b26c322b9d8386d39fb40b7e8ae9f6b4f6259ac2b25051409f997e28bd0', 1, '2026-07-06 14:17:44'),
(14, 'Motor Completo 1.8L', 'Motor 2ZR-FE completo, 60,000 km. Incluye accesorios.', 1, 2, 2500.00, 1, 'parte_6a51be9bade3b8.27627322_th.jpg', 'parte_6a51be9bade3b8.27627322.jpg', 1, '3b048e22fd71a0f4800e41ab8e45bce127789559ea7b476e645b363e1f65cad0', 1, '2026-07-06 14:17:44'),
(15, 'Transmisión Automática', 'Caja automática de 6 velocidades, funcional, probada.', 2, 2, 1800.00, 1, 'parte_6a51ba2f56b739.74494586_th.jpg', 'parte_6a51ba2f56b739.74494586.webp', 1, 'a5f63a88ceab4a5a6005bb895037ccd0dc7f55b5c39e67a2422d9506f1643d6c', 1, '2026-07-06 14:17:44'),
(16, 'Alternador 90A', 'Alternador remanufacturado con garantía, genera voltaje correcto.', 3, 2, 180.00, 8, 'parte_6a51b16d7966e5.72584823_th.jpg', 'parte_6a51b16d7966e5.72584823.jpg', 1, '10bdaa088703019e7dfda436f3a58aefd638199018eb5b6ee3f3f7c70cc0e86c', 1, '2026-07-06 14:17:44'),
(17, 'Motor de Arranque', 'Arranque 12V original, reconstruido. Fácil instalación.', 5, 2, 95.00, 11, 'parte_6a51b0985d4067.71784661_th.jpg', 'parte_6a51b0985d4067.71784661.webp', 1, '91009cb9b35d7ac31389f855dfd40d8be355d592f1b396dfaac86e899737c128', 1, '2026-07-06 14:17:44'),
(18, 'Bomba de Agua', 'Bomba de agua original con empaque. Previene sobrecalentamiento.', 4, 2, 55.00, 23, 'parte_6a51b0667f5eb5.21908769_th.jpg', 'parte_6a51b0667f5eb5.21908769.jpg', 1, '30e5a2068e1f73e0c87b441c64160c102b5608a07f1721c76ec9ad725db3dcf1', 1, '2026-07-06 14:17:44'),
(19, 'Compresor de A/C', 'Compresor de aire acondicionado original, enfría correctamente.', 6, 2, 320.00, 6, 'parte_6a51ae40df2e30.21285495_th.jpg', 'parte_6a51ae40df2e30.21285495.jpg', 1, '9d3534c70e17e55e772d21175fb087b1b47b6843ef9be243aa6c97ffc94df66d', 1, '2026-07-06 14:17:44'),
(20, 'Radiador de Aluminio', 'Radiador de aluminio doble fila, sin fugas, buen flujo.', 1, 2, 210.00, 9, 'parte_6a51ade1080423.46631479_th.jpg', 'parte_6a51ade1080423.46631479.jpg', 1, '4921166049ac24b23cb131717e3b9a3722e9121d019bf4195210068915cafebc', 1, '2026-07-06 14:17:44'),
(21, 'Bloque de Motor', 'Bloque de motor maquinado, listo para reconstrucción.', 2, 2, 1200.00, 1, 'parte_6a51ad99c1b036.04728040_th.jpg', 'parte_6a51ad99c1b036.04728040.webp', 1, '3608f628f5c87052e21d5e984fafd95802c839b5e4a620c34c0de00669111f79', 1, '2026-07-06 14:17:44'),
(22, 'Bomba de Aceite', 'Bomba de aceite original, garantiza circulación adecuada.', 3, 2, 70.00, 15, 'parte_6a51acff52a364.19078291_th.jpg', 'parte_6a51acff52a364.19078291.jpg', 1, '9fad2f8c287839366c20bb4be9e362c773ff9ee773d6fdf6c55b2a286e579761', 1, '2026-07-06 14:17:44'),
(23, 'Turbocargador', 'Turbo original reconstruido, sin juego en eje, funcional.', 5, 2, 850.00, 3, 'parte_6a51accd20d083.61206707_th.jpg', 'parte_6a51accd20d083.61206707.jpg', 1, 'bdd3369fd9c31f7b2ca5168c0ce10d469cb288fbb3743c75d26aca7ed7f0d499', 1, '2026-07-06 14:17:44'),
(24, 'Culata de Motor', 'Culata rectificada, válvulas nuevas, sin grietas.', 4, 2, 450.00, 4, 'parte_6a51ac2e7aed99.30281261_th.jpg', 'parte_6a51ac2e7aed99.30281261.webp', 1, '6d89ff0d70d422a357c38326c20ee1ad83cdc83293ef0bbb73b5294686882bc8', 1, '2026-07-06 14:17:44'),
(25, 'Caja de Velocidades Manual', 'Caja manual 5 velocidades, sincros en buen estado.', 6, 2, 950.00, 2, 'parte_6a51abfae00892.34250794_th.jpg', 'parte_6a51abfae00892.34250794.webp', 1, 'a5bdb49d59b624223756a102d23975d93cd34bf859fcd6df2e5994844629344c', 1, '2026-07-06 14:17:44'),
(26, 'Parabrisas Delantero', 'Parabrisas original sin fisuras ni rayaduras profundas.', 1, 3, 180.00, 7, 'parte_6a51ab02739654.73185807_th.jpg', 'parte_6a51ab02739654.73185807.jpg', 1, '8a6dbe1ca717858fa5860be990567e532204f2610dcac9ca3fc89eff6aba5530', 1, '2026-07-06 14:17:44'),
(27, 'Vidrio Puerta Delantera Der.', 'Vidrio eléctrico puerta delantera derecha, mecanismo incluido.', 2, 3, 65.00, 13, 'parte_6a51a8de9f3705.14623039_th.jpg', 'parte_6a51a8de9f3705.14623039.jpg', 1, '299237b0377916505c6d45bf0b4f25cef4ae85b3bd6cbe5b467e635ab11d513d', 1, '2026-07-06 14:17:44'),
(28, 'Faro Delantero Izquierdo', 'Faro halógeno izquierdo original, sin empañamiento.', 3, 3, 140.00, 10, 'parte_6a51a8afefddf7.72234933_th.jpg', 'parte_6a51a8afefddf7.72234933.jpg', 1, '8608c738462a84e2d94cea4768de2bf567674cb609a42240b1f63b0993d94d2a', 1, '2026-07-06 14:17:44'),
(29, 'Faro Trasero Derecho', 'Faro trasero completo, cristal en perfectas condiciones.', 5, 3, 90.00, 18, 'parte_6a51a84cdef295.57992425_th.jpg', 'parte_6a51a84cdef295.57992425.jpg', 1, 'a7a010f56977d8b3984e94fa1e8b3453fd52fb32eb03eddcb0b4462a70db7034', 1, '2026-07-06 14:17:44'),
(30, 'Espejo Retrovisor Ext. Izq.', 'Espejo lateral eléctrico izquierdo, funciona correctamente.', 4, 3, 75.00, 20, 'parte_6a51a80c568d84.18697315_th.jpg', 'parte_6a51a80c568d84.18697315.jpg', 1, '5ef14bee9156648d43b22657ae774b47ad23249d9d3e2944bb836e64abf7f6b4', 1, '2026-07-06 14:17:44'),
(31, 'Luneta Trasera', 'Vidrio trasero original con resistencia integrada, funcional.', 6, 3, 150.00, 5, 'parte_6a51a77a8c9845.93464275_th.jpg', 'parte_6a51a77a8c9845.93464275.jpg', 1, '025dc540a2b21019f4a851d863ba12de2172bfc504bd63756b9889d69ac6a5f6', 1, '2026-07-06 14:17:44'),
(32, 'Faro Antiniebla Delantero', 'Faro niebla original con soporte. Par disponible.', 1, 3, 45.00, 28, 'parte_6a51a6967e92f4.80375919_th.jpg', 'parte_6a51a6967e92f4.80375919.jpg', 1, 'f6924bbaf65add2d2e197fe440f82d3cda0b8b4387111d62859c116049dbaeef', 1, '2026-07-06 14:17:44'),
(33, 'Vidrio Puerta Trasera Izq.', 'Vidrio corredizo puerta trasera izquierda, sin rayaduras.', 2, 3, 55.00, 16, 'parte_6a51a65e1f8914.00876283_th.jpg', 'parte_6a51a65e1f8914.00876283.webp', 1, 'e6c553fadafbc558b306008ac152f2ef6667fd92de6190497064243c7d0f3233', 1, '2026-07-06 14:17:44'),
(34, 'Faro de Posición Lateral', 'Faro de posición lateral ambar OEM, fácil cambio.', 3, 3, 15.00, 55, 'parte_6a51a6212ebb36.83039669_th.jpg', 'parte_6a51a6212ebb36.83039669.jpg', 1, 'fb8ed5fba5be2b54bda652418fdb11ed455385582f88df529681369bc23ebec5', 1, '2026-07-06 14:17:44'),
(35, 'Luz de Placa Trasera', 'Luz de placa completa con soporte y bombillo LED.', 5, 3, 12.00, 45, 'parte_6a51a5cc0da7c4.54224190_th.jpg', 'parte_6a51a5cc0da7c4.54224190.jpg', 1, 'a78129f3283202068961f73537699b31585a15e6688176148e7a747492f769d4', 1, '2026-07-06 14:17:44'),
(36, 'Conjunto Óptico Trasero', 'Óptico trasero completo bicolor. Sellado, sin humedad interna.', 6, 3, 195.00, 8, 'parte_6a51a4cd787950.67798654_th.jpg', 'parte_6a51a4cd787950.67798654.webp', 1, 'cd49b33af5e7ddc9c2095bc14c02cdcfa16c7cf189dd8373c678ca0b242282f9', 1, '2026-07-06 14:17:44'),
(37, 'Espejo Retrovisor Interior', 'Espejo interior abatible con función antideslumbrante.', 4, 3, 35.00, 19, 'parte_6a51a2c64848f1.30324317_th.jpg', 'parte_6a51a2c64848f1.30324317.webp', 1, 'db3c4cf5d46514dfcf7be40032a2834fa6083bebf93ed3c489a866b9876f6e16', 1, '2026-07-06 14:17:44'),
(38, 'Batería 12V 55Ah', 'Batería libre mantenimiento, 550 CCA, carga completa.', 1, 4, 85.00, 15, 'parte_6a51a0e52d91c6.17622441_th.jpg', 'parte_6a51a0e52d91c6.17622441.jpg', 1, '84a84319c0e757ffe9cbc38e1ed034e769139611a231c64a245437f4797c831c', 1, '2026-07-06 14:17:44'),
(39, 'Bujías NGK (set x4)', 'Set de 4 bujías NGK Iridium. Mayor rendimiento de combustión.', 2, 4, 35.00, 39, 'parte_6a51a0a9858cc5.65696958_th.jpg', 'parte_6a51a0a9858cc5.65696958.jpg', 1, '6556381faa1d4c9aeee3356c714dd439162416df0491ae954326070a75a3ef29', 1, '2026-07-06 14:17:44'),
(40, 'Caja de Fusibles Completa', 'Caja de fusibles con relays, totalmente funcional.', 3, 4, 45.00, 12, 'parte_6a51a05f04d908.59874958_th.jpg', 'parte_6a51a05f04d908.59874958.jpg', 1, '618cad6dc09af185da4bd751644207aafd14d561901369b62ceea6089110bc47', 1, '2026-07-06 14:17:44'),
(41, 'Sensor MAF Flujo de Masa', 'Sensor de flujo de masa de aire, calibrado de fábrica.', 5, 4, 95.00, 7, 'parte_6a51a031d220d3.02642355_th.jpg', 'parte_6a51a031d220d3.02642355.jpg', 1, '12b60b27c508d5fb4aa776640de586f32c82af169acca87bf3465cf94ad95ff3', 1, '2026-07-06 14:17:44'),
(42, 'ECU / Computadora', 'Módulo de control del motor, compatible plug-and-play.', 4, 4, 380.00, 2, 'parte_6a51a001c65468.82842889_th.jpg', 'parte_6a51a001c65468.82842889.webp', 1, '02e9531eaa72a105f7f9c0c528fa7d1ef360ff5d77d421ea29dae9ee94617687', 1, '2026-07-06 14:17:44'),
(43, 'Bobina de Encendido', 'Bobina de encendido original, alta tensión, fácil instalación.', 6, 4, 55.00, 19, 'parte_6a519f919aeae2.81458560_th.jpg', 'parte_6a519f919aeae2.81458560.jpg', 1, 'd9bb00de62b3c4529d86490688440a02965be1ac0d71fdd06645a2c183c5af54', 1, '2026-07-06 14:17:44'),
(44, 'Sensor de Oxígeno Upstream', 'Sensor lambda aguas arriba, mejora consumo y emisiones.', 1, 4, 65.00, 7, 'parte_6a519f5327fe40.36118985_th.jpg', 'parte_6a519f5327fe40.36118985.jpg', 1, '2afb635ee2e88753ee82b3dd16c3c4971110c135497e80798cabd0eca4f8e9dd', 1, '2026-07-06 14:17:44'),
(45, 'Cable Positivo de Batería', 'Cable de batería positivo con fusible de protección integrado.', 2, 4, 25.00, 30, 'parte_6a517b51e47995.82570979_th.jpg', 'parte_6a517b51e47995.82570979.jpg', 1, '81458d511a81788bbc316be65d6834759aff7da0c55744d65a726737b464e269', 1, '2026-07-06 14:17:44'),
(46, 'Relay de Ventilador A/C', 'Relay de ventilador de condensador, 40A, original.', 3, 4, 18.00, 22, 'parte_6a517a1eac9f90.47747993_th.jpg', 'parte_6a517a1eac9f90.47747993.jpg', 1, '1ea9b6773ca5d8271aff9acd5763293d7558d31e66a87a7ea0a6235b0f633d4b', 1, '2026-07-06 14:17:44'),
(47, 'Módulo de Ventanas Eléctricas', 'Módulo de control central de ventanas eléctricas.', 5, 4, 110.00, 5, 'parte_6a5179bfcb7fa6.84253492_th.jpg', 'parte_6a5179bfcb7fa6.84253492.png', 1, 'f704b78a5ba55ae4be1072ac363f3792965995a219d309a0e29fc0d7f47b7eca', 1, '2026-07-06 14:17:44'),
(48, 'Switch de Encendido', 'Interruptor de encendido con llave, garantiza arranque seguro.', 4, 4, 40.00, 9, 'parte_6a51793aa38bb4.85066540_th.jpg', 'parte_6a51793aa38bb4.85066540.jpg', 1, '1dfa6c8eea26050eac0dba3fe99d00a64d7f752927ea791d78165fe056456427', 1, '2026-07-06 14:17:44'),
(49, 'Sensor ABS de Velocidad', 'Sensor de velocidad de rueda para sistema ABS, original.', 6, 4, 75.00, 13, 'parte_6a517489a69689.54968813_th.jpg', 'parte_6a517489a69689.54968813.jpg', 1, 'ec153ed772d7ebb1900098171a473fc1a63482913b7fe0f99c0949b4dca0e406', 1, '2026-07-06 14:17:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

DROP TABLE IF EXISTS `pedidos`;
CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cliente_nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_telefono` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_direccion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_notas` text COLLATE utf8mb4_unicode_ci,
  `usuario_id` int DEFAULT NULL,
  `estado` enum('pendiente','procesando','entregado','cancelado') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `firma_digital` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `cliente_nombre`, `cliente_telefono`, `cliente_email`, `cliente_direccion`, `cliente_notas`, `usuario_id`, `estado`, `subtotal`, `total`, `firma_digital`, `creado_en`) VALUES
(1, 'Antonio Castillo', '65420519', 'tonybusinesspty@gmail.com', '24 de diciembre, calle 13, casa u227. La vereda donde se ve una fabrica naranja enorme', '', 19, 'entregado', 15.00, 15.00, '913ac3f0ce303fde87671c6a5cb937a2abc966612a8af1f8d9e4806bbf8cdaa2', '2026-07-11 18:31:05'),
(2, 'Tony Perez', '65323661', 'toni507hd@gmail.com', 'Pacora, Cabra Santa Isabael Calle 3era, al lado de la casa que vende hot dogs', '', 20, 'entregado', 35.00, 35.00, 'de75d9943cfa98e9a02cf7bfd5d17208b7ddf2961b864894262a94b1e04eff03', '2026-07-11 18:50:32'),
(3, 'Tony Perez', '65323661', 'toni507hd@gmail.com', 'Cabra, Santa Isabel', '', 20, 'entregado', 260.00, 260.00, 'd0f5d344b1cb34679324432d746f516ffe3c00875679a4e3a28453efb55711f5', '2026-07-11 18:51:28'),
(4, 'Antonio Castillo', '65420519', 'tonybusinesspty@gmail.com', 'Calle 13, Monterrico', '', 19, 'entregado', 45.00, 45.00, '753576b0de66be49dc61dd71cfaea6baf4bbfef4e8b4802f2e0c8c2c49cedc61', '2026-07-12 00:19:12'),
(5, 'Anthony Fetuccinne', '65420518', 'duncanrules7f@gmail.com', '24 de diciembre, Vista Hermosa, Casa E33', '', 21, 'entregado', 110.00, 110.00, 'a7acfc15c96091cb42500e90d505816e376978b768d897da4470b0399fc076cb', '2026-07-12 05:16:53'),
(6, 'Anthony Fetuccinne', '65420518', 'duncanrules7f@gmail.com', 'Vista Hermosa, Calle 27, La 24', '', 21, 'entregado', 1800.00, 1800.00, 'da0119be5e6daf632949f8d31c49907054a4643f422103fe98a9d84ca80b9239', '2026-07-12 05:22:43');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_items`
--

DROP TABLE IF EXISTS `pedido_items`;
CREATE TABLE IF NOT EXISTS `pedido_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pedido_id` int NOT NULL,
  `parte_id` int DEFAULT NULL,
  `parte_nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `cantidad` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `pedido_id` (`pedido_id`),
  KEY `parte_id` (`parte_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pedido_items`
--

INSERT INTO `pedido_items` (`id`, `pedido_id`, `parte_id`, `parte_nombre`, `precio_unitario`, `cantidad`) VALUES
(1, 1, 34, 'Faro de Posición Lateral', 15.00, 1),
(2, 2, 39, 'Bujías NGK (set x4)', 35.00, 1),
(3, 3, 44, 'Sensor de Oxígeno Upstream', 65.00, 4),
(4, 4, 34, 'Faro de Posición Lateral', 15.00, 3),
(5, 5, 18, 'Bomba de Agua', 55.00, 2),
(6, 6, 15, 'Transmisión Automática', 1800.00, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

DROP TABLE IF EXISTS `permisos`;
CREATE TABLE IF NOT EXISTS `permisos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rol_id` int NOT NULL,
  `modulo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `puede_ver` tinyint(1) DEFAULT '1',
  `puede_crear` tinyint(1) DEFAULT '0',
  `puede_editar` tinyint(1) DEFAULT '0',
  `puede_borrar` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rol_mod` (`rol_id`,`modulo`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id`, `rol_id`, `modulo`, `puede_ver`, `puede_crear`, `puede_editar`, `puede_borrar`) VALUES
(1, 1, 'usuarios', 1, 1, 1, 1),
(2, 1, 'roles', 1, 1, 1, 1),
(3, 1, 'partes', 1, 1, 1, 1),
(4, 1, 'autos', 1, 1, 1, 1),
(5, 1, 'secciones', 1, 1, 1, 1),
(6, 1, 'ventas', 1, 1, 1, 1),
(7, 1, 'comentarios', 1, 1, 1, 1),
(8, 2, 'usuarios', 1, 0, 0, 0),
(9, 2, 'roles', 1, 0, 0, 0),
(10, 2, 'partes', 1, 0, 0, 0),
(11, 2, 'autos', 1, 0, 0, 0),
(12, 2, 'secciones', 1, 0, 0, 0),
(13, 2, 'ventas', 1, 1, 0, 0),
(14, 2, 'comentarios', 1, 0, 0, 0),
(15, 3, 'usuarios', 1, 0, 0, 0),
(16, 3, 'roles', 1, 0, 0, 0),
(17, 3, 'partes', 1, 0, 0, 0),
(18, 3, 'autos', 1, 0, 0, 0),
(19, 3, 'secciones', 1, 0, 0, 0),
(20, 3, 'ventas', 1, 0, 0, 0),
(21, 3, 'comentarios', 1, 0, 0, 0),
(43, 1, 'pedidos', 1, 1, 1, 1),
(44, 2, 'pedidos', 1, 1, 1, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `creado_en`) VALUES
(1, 'Administrador', 'Control total del sistema', '2026-06-10 13:38:05'),
(2, 'Vendedor', 'Registra ventas y consulta inventario', '2026-06-10 13:38:05'),
(3, 'Consultor', 'Solo consulta el inventario', '2026-06-10 13:38:05'),
(5, 'Cliente', 'Usuario registrado desde la tienda pública', '2026-07-04 20:52:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `secciones`
--

DROP TABLE IF EXISTS `secciones`;
CREATE TABLE IF NOT EXISTS `secciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `secciones`
--

INSERT INTO `secciones` (`id`, `nombre`, `descripcion`, `activo`, `creado_en`) VALUES
(1, 'Sección A', 'Carrocería, puertas y paneles', 1, '2026-06-10 13:38:05'),
(2, 'Sección B', 'Motores y transmisiones', 1, '2026-06-10 13:38:05'),
(3, 'Sección C', 'Vidrios, espejos y luces', 1, '2026-06-10 13:38:05'),
(4, 'Sección D', 'Componentes eléctricos y accesorios', 1, '2026-06-10 13:38:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` text COLLATE utf8mb4_unicode_ci,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `intentos_fallidos` tinyint DEFAULT '0',
  `bloqueado` tinyint(1) DEFAULT '0',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `telefono`, `direccion`, `password`, `activo`, `intentos_fallidos`, `bloqueado`, `creado_en`) VALUES
(1, 'Administrador', 'admin@rastroinventario.com', NULL, NULL, '$2y$10$89qZeigNJMoXwbHIFa9uReZ7OG6wca1.UF0AQyrejYcM8BDkTdn32', 1, 0, 0, '2026-06-10 13:38:05'),
(3, 'Vendedor Uno', 'vendedor1@rastroinventario.com', NULL, NULL, '$2y$10$7dPK4zNU9tvuMgicAb5Fp.7FrBwUPGu97yMrFuf3d3tWwHbq3o2r6', 1, 0, 0, '2026-07-04 20:52:10'),
(4, 'Vendedor Dos', 'vendedor2@rastroinventario.com', NULL, NULL, '$2y$10$fT/eYm8nnT.RPqs.1zJiF.SGaiSGmZeNlCzT1X/CqGqfUld0dgiPW', 1, 0, 0, '2026-07-04 20:52:10'),
(5, 'Vendedor Tres', 'vendedor3@rastroinventario.com', NULL, NULL, '$2y$10$yWIfH6kSZ276g2VbbcAls.aL.ah/mTfsDOlScNaopfCn35rIKXEXa', 1, 0, 0, '2026-07-04 20:52:10'),
(6, 'Consultor Uno', 'consultor1@rastroinventario.com', NULL, NULL, '$2y$10$OrL3j6qzoISJsyRYpH3yp.I7e.gSEVsdwuPSrnXQFrwjsKdqvRsqy', 1, 0, 0, '2026-07-04 20:52:10'),
(7, 'Consultor Dos', 'consultor2@rastroinventario.com', NULL, NULL, '$2y$10$YxtiHK6.sc1GU67ZFQV1.uwEVrL5DfyWxv1JGNw9Lv0xk.DofAXfu', 1, 0, 0, '2026-07-04 20:52:10'),
(8, 'Consultor Tres', 'consultor3@rastroinventario.com', NULL, NULL, '$2y$10$vAvp/nGEnY2MZBc06S6APe0dQq.mARm2SAO8FkDeGXvw63Cv2/uPq', 1, 0, 0, '2026-07-04 20:52:10'),
(19, 'Antonio Castillo', 'tonybusinesspty@gmail.com', '65420519', '', '$2y$10$qHGmD/lro5Djv6lmZYpJk.0CqrVcjoRJvCF4P96nfPMo8QJkBxP9.', 1, 0, 0, '2026-07-11 10:26:00'),
(20, 'Tony Perez', 'toni507hd@gmail.com', '65323661', '', '$2y$10$ASOXSeZ7/EE23RV78eBD0uzQU5lKPp2kEJczZG4dRXOJa2zP96HW.', 1, 0, 0, '2026-07-11 10:38:45'),
(21, 'Anthony Fetuccinne', 'duncanrules7f@gmail.com', '65420518', '', '$2y$10$QEFcsIYMYny/U3/imcdheOjIV3i0GwzOOVeKG/yfso.8V4YOqtkee', 1, 0, 0, '2026-07-11 16:21:38'),
(22, 'Emily Rodriguez', 'emilyrodriguezofc@gmail.com', '65323645', '', '$2y$10$76tL8qdJnkRuijNqyRNCKe34Rc.sme5r7D9oFd6Mr.zeOtAZ7n1ty', 1, 0, 0, '2026-07-11 16:32:00'),
(23, 'Tineps', 'tonytineps17@gmail.com', '65420520', '', '$2y$10$.r3D6TPjG3eUwCQr1nBtxO1Qzd1QuzDtOR7AtVKHk9E7yv0/Mr2Pe', 1, 0, 0, '2026-07-11 16:36:03'),
(24, 'Rappi', 'elrappixd@gmail.com', '65420535', '', '$2y$10$BIsBLft8YKLCsH7vUqAbRuFMd1HagwkF2aSAM3xJucVoQt7uoTDtG', 1, 0, 0, '2026-07-11 16:40:04'),
(25, 'Tony Conta', 'tonycontactoalv@gmail.com', '65420344', '', '$2y$10$gW5X359har.6IhON8T/rLuixO080uUg1zBgLrW4GZRMl9CCZctJLK', 1, 0, 0, '2026-07-11 16:46:30'),
(26, 'Panameno', 'panamaeuropa507@gmail.com', '65323345', '', '$2y$10$Fk.LUw7pf3tbx2FPPJJP7.uK9tXTHLGIVowsc83XZ.Ahvpwm.SWqa', 1, 0, 0, '2026-07-11 16:54:12'),
(27, 'TikTok', 'tonytiktokxpp@gmail.com', '65420345', '', '$2y$10$d065ejYq3Lhg.zA2E6pYce.hkLZkjrJxdfFWPsYybh0u9XvFTZVYG', 1, 0, 0, '2026-07-11 17:03:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_roles`
--

DROP TABLE IF EXISTS `usuario_roles`;
CREATE TABLE IF NOT EXISTS `usuario_roles` (
  `usuario_id` int NOT NULL,
  `rol_id` int NOT NULL,
  PRIMARY KEY (`usuario_id`,`rol_id`),
  KEY `rol_id` (`rol_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuario_roles`
--

INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES
(1, 1),
(3, 2),
(4, 2),
(5, 2),
(6, 3),
(7, 3),
(8, 3),
(19, 5),
(20, 5),
(21, 5),
(22, 5),
(23, 5),
(24, 5),
(25, 5),
(26, 5),
(27, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vendido_parte`
--

DROP TABLE IF EXISTS `vendido_parte`;
CREATE TABLE IF NOT EXISTS `vendido_parte` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parte_id` int NOT NULL,
  `cantidad` int NOT NULL DEFAULT '1',
  `precio_venta` decimal(10,2) NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `firma_digital` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parte_id` (`parte_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `vendido_parte`
--

INSERT INTO `vendido_parte` (`id`, `parte_id`, `cantidad`, `precio_venta`, `usuario_id`, `fecha`, `firma_digital`) VALUES
(1, 34, 1, 15.00, 19, '2026-07-11 18:45:51', 'edf1589ef146a9c8806fa85b5d33d59e8b7409040949eb8007b43fd9c5c7234c'),
(2, 44, 4, 65.00, 20, '2026-07-11 18:52:15', '76b7db51b4eb90bbb70903f0ac7beb6a55f703cf41f8c0514ed41808c678c63b'),
(3, 39, 1, 35.00, 20, '2026-07-11 18:52:31', '217b0d077afa07b91f00f1790fe4acfcb81aabd58f66291fe466135859c42584'),
(4, 34, 3, 15.00, 19, '2026-07-12 02:05:30', '1014f0f7361d97847a29366e618517ff4ea6fbdac2f15c0983109e3fafbba7b7'),
(5, 15, 1, 1800.00, 21, '2026-07-12 05:23:23', '197d083f152bd9f23dcdc8a8d253020f71ff829289735987d069197891c95f50'),
(6, 18, 2, 55.00, 21, '2026-07-12 05:23:37', 'cb56a2f712c45140b02078ae014c9f602dcdcb0ea25913db3fe53275a5ce2f22');

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `comentarios`
--
ALTER TABLE `comentarios`
  ADD CONSTRAINT `comentarios_ibfk_1` FOREIGN KEY (`parte_id`) REFERENCES `partes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `partes`
--
ALTER TABLE `partes`
  ADD CONSTRAINT `partes_ibfk_1` FOREIGN KEY (`auto_id`) REFERENCES `autos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `partes_ibfk_2` FOREIGN KEY (`seccion_id`) REFERENCES `secciones` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `partes_ibfk_3` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  ADD CONSTRAINT `pedido_items_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pedido_items_ibfk_2` FOREIGN KEY (`parte_id`) REFERENCES `partes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD CONSTRAINT `permisos_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuario_roles`
--
ALTER TABLE `usuario_roles`
  ADD CONSTRAINT `usuario_roles_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_roles_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `vendido_parte`
--
ALTER TABLE `vendido_parte`
  ADD CONSTRAINT `vendido_parte_ibfk_1` FOREIGN KEY (`parte_id`) REFERENCES `partes` (`id`),
  ADD CONSTRAINT `vendido_parte_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
