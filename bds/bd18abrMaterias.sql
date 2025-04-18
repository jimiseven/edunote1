-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 18-04-2025 a las 23:57:04
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `colegiov2`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `anuncios`
--

CREATE TABLE `anuncios` (
  `id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `anuncios`
--

INSERT INTO `anuncios` (`id`, `mensaje`, `fecha_inicio`, `fecha_fin`, `creado_por`, `creado_en`) VALUES
(13, 'profesores de religion cargar notas hasta antes del lunes 13', '2025-04-17', '2025-04-19', 1, '2025-04-17 14:01:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bimestres_activos`
--

CREATE TABLE `bimestres_activos` (
  `id` int(11) NOT NULL,
  `numero_bimestre` int(11) NOT NULL,
  `esta_activo` tinyint(1) DEFAULT 0,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bimestres_activos`
--

INSERT INTO `bimestres_activos` (`id`, `numero_bimestre`, `esta_activo`, `fecha_inicio`, `fecha_fin`, `fecha_modificacion`) VALUES
(1, 1, 1, '2025-04-15', '2025-04-17', '2025-04-18 19:20:25'),
(2, 2, 1, NULL, NULL, '2025-04-18 19:20:25'),
(3, 3, 1, NULL, NULL, '2025-04-18 19:20:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificaciones`
--

CREATE TABLE `calificaciones` (
  `id_calificacion` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL COMMENT 'FK a estudiantes',
  `id_materia` int(11) NOT NULL COMMENT 'FK a materias',
  `bimestre` int(11) NOT NULL COMMENT 'Número del bimestre: 1, 2, 3, 4',
  `calificacion` decimal(5,2) NOT NULL COMMENT 'Nota obtenida en el bimestre',
  `comentario` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `calificaciones`
--

INSERT INTO `calificaciones` (`id_calificacion`, `id_estudiante`, `id_materia`, `bimestre`, `calificacion`, `comentario`) VALUES
(2, 7, 4, 1, 12.00, NULL),
(3, 5, 1, 1, 77.00, NULL),
(21, 5, 2, 1, 66.00, NULL),
(22, 315, 3, 1, 72.00, NULL),
(23, 311, 3, 1, 73.00, NULL),
(24, 6, 3, 1, 74.00, NULL),
(25, 320, 3, 1, 75.00, NULL),
(26, 318, 3, 1, 76.00, NULL),
(27, 317, 3, 1, 77.00, NULL),
(28, 314, 3, 1, 78.00, NULL),
(29, 312, 3, 1, 79.00, NULL),
(30, 316, 3, 1, 80.00, NULL),
(31, 319, 3, 1, 81.00, NULL),
(32, 313, 3, 1, 82.00, NULL),
(66, 315, 3, 2, 78.00, NULL),
(67, 311, 3, 2, 83.00, NULL),
(68, 6, 3, 2, 84.00, NULL),
(69, 320, 3, 2, 85.00, NULL),
(70, 318, 3, 2, 86.00, NULL),
(71, 317, 3, 2, 87.00, NULL),
(72, 314, 3, 2, 88.00, NULL),
(73, 312, 3, 2, 89.00, NULL),
(74, 316, 3, 2, 90.00, NULL),
(75, 319, 3, 2, 91.00, NULL),
(76, 313, 3, 2, 92.00, NULL),
(77, 315, 3, 3, 76.00, NULL),
(78, 311, 3, 3, 77.00, NULL),
(79, 6, 3, 3, 78.00, NULL),
(80, 320, 3, 3, 79.00, NULL),
(81, 318, 3, 3, 80.00, NULL),
(82, 317, 3, 3, 81.00, NULL),
(83, 314, 3, 3, 82.00, NULL),
(84, 312, 3, 3, 83.00, NULL),
(85, 316, 3, 3, 84.00, NULL),
(86, 319, 3, 3, 85.00, NULL),
(87, 313, 3, 3, 86.00, NULL),
(223, 306, 1, 1, 72.00, NULL),
(224, 304, 1, 1, 73.00, NULL),
(228, 301, 1, 1, 74.00, NULL),
(229, 309, 1, 1, 75.00, NULL),
(230, 302, 1, 1, 76.00, NULL),
(232, 305, 1, 1, 78.00, NULL),
(233, 310, 1, 1, 79.00, NULL),
(234, 303, 1, 1, 80.00, NULL),
(235, 308, 1, 1, 81.00, NULL),
(236, 307, 1, 1, 34.00, NULL),
(248, 306, 1, 2, 82.00, NULL),
(249, 304, 1, 2, 83.00, NULL),
(250, 301, 1, 2, 84.00, NULL),
(251, 309, 1, 2, 85.00, NULL),
(252, 302, 1, 2, 86.00, NULL),
(253, 5, 1, 2, 87.00, NULL),
(254, 305, 1, 2, 88.00, NULL),
(255, 310, 1, 2, 89.00, NULL),
(256, 303, 1, 2, 90.00, NULL),
(257, 308, 1, 2, 91.00, NULL),
(258, 307, 1, 2, 34.00, NULL),
(281, 607, 800, 1, 0.00, 'comentario asdfh3uiohakjsb 1'),
(283, 604, 800, 1, 0.00, 'comentario asdfh3uiohakjsb 2'),
(284, 600, 800, 1, 0.00, 'comentario asdfh3uiohakjsb 3'),
(285, 601, 800, 1, 0.00, 'comentario asdfh3uiohakjsb 4'),
(286, 602, 800, 1, 0.00, 'comentario asdfh3uiohakjsb 5'),
(287, 605, 800, 1, 0.00, 'comentario asdfh3uiohakjsb 6'),
(288, 609, 800, 1, 0.00, 'comentario asdfh3uiohakjsb 7'),
(289, 603, 800, 1, 0.00, 'comentario asdfh3uiohakjsb 8'),
(290, 606, 800, 1, 0.00, 'comentario asdfh3uiohakjsb 9'),
(291, 608, 800, 1, 0.00, 'comentario asdfh3uiohakjsb 10'),
(312, 607, 801, 1, 0.00, 'comentario asdfh3uiohakjsb 1'),
(313, 604, 801, 1, 0.00, 'comentario asdfh3uiohakjsb 2'),
(314, 600, 801, 1, 0.00, 'comentario asdfh3uiohakjsb 3'),
(315, 601, 801, 1, 0.00, 'comentario asdfh3uiohakjsb 4'),
(316, 602, 801, 1, 0.00, 'comentario asdfh3uiohakjsb 5'),
(317, 605, 801, 1, 0.00, 'comentario asdfh3uiohakjsb 6'),
(318, 609, 801, 1, 0.00, 'comentario asdfh3uiohakjsb 7'),
(319, 603, 801, 1, 0.00, 'comentario asdfh3uiohakjsb 8'),
(320, 606, 801, 1, 0.00, 'comentario asdfh3uiohakjsb 9'),
(321, 608, 801, 1, 0.00, 'comentario de torres ramires'),
(343, 306, 1, 3, 72.00, NULL),
(344, 304, 1, 3, 73.00, NULL),
(345, 301, 1, 3, 74.00, NULL),
(346, 309, 1, 3, 75.00, NULL),
(347, 302, 1, 3, 76.00, NULL),
(348, 5, 1, 3, 77.00, NULL),
(349, 305, 1, 3, 78.00, NULL),
(350, 310, 1, 3, 79.00, NULL),
(351, 303, 1, 3, 80.00, NULL),
(352, 308, 1, 3, 81.00, NULL),
(353, 307, 1, 3, 34.00, NULL),
(387, 612, 802, 1, 0.00, 'comentario asdfh3uiohakjsb 1'),
(388, 617, 802, 1, 0.00, 'comentario asdfh3uiohakjsb 2'),
(389, 619, 802, 1, 0.00, 'comentario asdfh3uiohakjsb 3'),
(390, 610, 802, 1, 0.00, 'comentario asdfh3uiohakjsb 4'),
(391, 613, 802, 1, 0.00, 'comentario asdfh3uiohakjsb 5'),
(392, 615, 802, 1, 0.00, 'comentario asdfh3uiohakjsb 6'),
(393, 614, 802, 1, 0.00, 'comentario asdfh3uiohakjsb 7'),
(394, 616, 802, 1, 0.00, 'comentario asdfh3uiohakjsb 8'),
(395, 611, 802, 1, 0.00, 'comentario asdfh3uiohakjsb 9'),
(396, 618, 802, 1, 0.00, 'comentario asdfh3uiohakjsb 10'),
(412, 306, 2, 1, 61.00, NULL),
(413, 306, 2, 2, 82.00, NULL),
(414, 306, 2, 3, 91.00, NULL),
(433, 304, 2, 1, 62.00, NULL),
(434, 304, 2, 2, 83.00, NULL),
(435, 304, 2, 3, 83.00, NULL),
(439, 301, 2, 1, 63.00, NULL),
(462, 301, 2, 2, 84.00, NULL),
(472, 301, 2, 3, 84.00, NULL),
(483, 309, 2, 1, 64.00, NULL),
(503, 302, 2, 2, 86.00, NULL),
(519, 309, 2, 2, 85.00, NULL),
(525, 302, 2, 1, 65.00, NULL),
(527, 305, 2, 1, 67.00, NULL),
(528, 310, 2, 1, 68.00, NULL),
(529, 303, 2, 1, 69.00, NULL),
(530, 308, 2, 1, 70.00, NULL),
(531, 307, 2, 1, 34.00, NULL),
(537, 5, 2, 2, 87.00, NULL),
(538, 305, 2, 2, 88.00, NULL),
(539, 310, 2, 2, 89.00, NULL),
(540, 303, 2, 2, 90.00, NULL),
(541, 308, 2, 2, 91.00, NULL),
(542, 307, 2, 2, 34.00, NULL),
(568, 309, 2, 3, 85.00, NULL),
(569, 302, 2, 3, 86.00, NULL),
(570, 5, 2, 3, 87.00, NULL),
(571, 305, 2, 3, 88.00, NULL),
(572, 310, 2, 3, 89.00, NULL),
(573, 303, 2, 3, 90.00, NULL),
(574, 308, 2, 3, 91.00, NULL),
(575, 307, 2, 3, 92.00, NULL),
(675, 306, 805, 1, 82.00, NULL),
(676, 304, 805, 1, 83.00, NULL),
(677, 301, 805, 1, 84.00, NULL),
(678, 309, 805, 1, 85.00, NULL),
(679, 302, 805, 1, 86.00, NULL),
(680, 5, 805, 1, 87.00, NULL),
(681, 305, 805, 1, 88.00, NULL),
(682, 310, 805, 1, 89.00, NULL),
(683, 303, 805, 1, 90.00, NULL),
(684, 308, 805, 1, 91.00, NULL),
(685, 307, 805, 1, 34.00, NULL),
(686, 306, 805, 2, 82.00, NULL),
(687, 304, 805, 2, 83.00, NULL),
(688, 301, 805, 2, 84.00, NULL),
(689, 309, 805, 2, 85.00, NULL),
(690, 302, 805, 2, 86.00, NULL),
(691, 5, 805, 2, 87.00, NULL),
(692, 305, 805, 2, 88.00, NULL),
(693, 310, 805, 2, 89.00, NULL),
(694, 303, 805, 2, 90.00, NULL),
(695, 308, 805, 2, 91.00, NULL),
(696, 307, 805, 2, 34.00, NULL),
(697, 306, 805, 3, 76.00, NULL),
(698, 304, 805, 3, 77.00, NULL),
(699, 301, 805, 3, 78.00, NULL),
(700, 309, 805, 3, 79.00, NULL),
(701, 302, 805, 3, 80.00, NULL),
(702, 5, 805, 3, 81.00, NULL),
(703, 305, 805, 3, 82.00, NULL),
(704, 310, 805, 3, 83.00, NULL),
(705, 303, 805, 3, 84.00, NULL),
(706, 308, 805, 3, 85.00, NULL),
(707, 307, 805, 3, 34.00, NULL),
(741, 306, 806, 1, 76.00, NULL),
(742, 304, 806, 1, 77.00, NULL),
(743, 301, 806, 1, 78.00, NULL),
(744, 309, 806, 1, 79.00, NULL),
(745, 302, 806, 1, 80.00, NULL),
(746, 5, 806, 1, 81.00, NULL),
(747, 305, 806, 1, 82.00, NULL),
(748, 310, 806, 1, 83.00, NULL),
(749, 303, 806, 1, 84.00, NULL),
(750, 308, 806, 1, 85.00, NULL),
(751, 307, 806, 1, 34.00, NULL),
(752, 306, 806, 2, 82.00, NULL),
(753, 304, 806, 2, 83.00, NULL),
(754, 301, 806, 2, 84.00, NULL),
(755, 309, 806, 2, 85.00, NULL),
(756, 302, 806, 2, 86.00, NULL),
(757, 5, 806, 2, 87.00, NULL),
(758, 305, 806, 2, 88.00, NULL),
(759, 310, 806, 2, 89.00, NULL),
(760, 303, 806, 2, 90.00, NULL),
(761, 308, 806, 2, 91.00, NULL),
(762, 307, 806, 2, 34.00, NULL),
(763, 306, 806, 3, 49.00, NULL),
(764, 304, 806, 3, 50.00, NULL),
(765, 301, 806, 3, 51.00, NULL),
(766, 309, 806, 3, 52.00, NULL),
(767, 302, 806, 3, 53.00, NULL),
(768, 5, 806, 3, 54.00, NULL),
(769, 305, 806, 3, 55.00, NULL),
(770, 310, 806, 3, 56.00, NULL),
(771, 303, 806, 3, 57.00, NULL),
(772, 308, 806, 3, 58.00, NULL),
(773, 307, 806, 3, 59.00, NULL),
(840, 306, 807, 1, 20.00, NULL),
(841, 304, 807, 1, 20.00, NULL),
(842, 301, 807, 1, 21.00, NULL),
(843, 309, 807, 1, 22.00, NULL),
(844, 302, 807, 1, 23.00, NULL),
(845, 5, 807, 1, 24.00, NULL),
(846, 305, 807, 1, 25.00, NULL),
(847, 310, 807, 1, 26.00, NULL),
(848, 303, 807, 1, 27.00, NULL),
(849, 308, 807, 1, 28.00, NULL),
(850, 307, 807, 1, 43.00, NULL),
(851, 306, 807, 2, 1.00, NULL),
(852, 304, 807, 2, 2.00, NULL),
(853, 301, 807, 2, 3.00, NULL),
(854, 309, 807, 2, 4.00, NULL),
(855, 302, 807, 2, 5.00, NULL),
(856, 5, 807, 2, 6.00, NULL),
(857, 305, 807, 2, 7.00, NULL),
(858, 310, 807, 2, 8.00, NULL),
(859, 303, 807, 2, 9.00, NULL),
(860, 308, 807, 2, 10.00, NULL),
(861, 307, 807, 2, 11.00, NULL),
(862, 306, 807, 3, 76.00, NULL),
(863, 304, 807, 3, 77.00, NULL),
(864, 301, 807, 3, 78.00, NULL),
(865, 309, 807, 3, 79.00, NULL),
(866, 302, 807, 3, 80.00, NULL),
(867, 5, 807, 3, 81.00, NULL),
(868, 305, 807, 3, 82.00, NULL),
(869, 310, 807, 3, 83.00, NULL),
(870, 303, 807, 3, 84.00, NULL),
(871, 308, 807, 3, 85.00, NULL),
(872, 307, 807, 3, 34.00, NULL),
(1258, 306, 808, 1, 45.00, NULL),
(1259, 304, 808, 1, 46.00, NULL),
(1260, 301, 808, 1, 47.00, NULL),
(1261, 309, 808, 1, 48.00, NULL),
(1262, 302, 808, 1, 49.00, NULL),
(1263, 5, 808, 1, 50.00, NULL),
(1264, 305, 808, 1, 51.00, NULL),
(1265, 310, 808, 1, 52.00, NULL),
(1266, 303, 808, 1, 53.00, NULL),
(1267, 308, 808, 1, 54.00, NULL),
(1268, 307, 808, 1, 55.00, NULL),
(1280, 306, 808, 2, 1.00, NULL),
(1281, 304, 808, 2, 2.00, NULL),
(1282, 301, 808, 2, 3.00, NULL),
(1283, 309, 808, 2, 4.00, NULL),
(1284, 302, 808, 2, 5.00, NULL),
(1285, 5, 808, 2, 6.00, NULL),
(1286, 305, 808, 2, 7.00, NULL),
(1287, 310, 808, 2, 8.00, NULL),
(1288, 303, 808, 2, 9.00, NULL),
(1289, 308, 808, 2, 10.00, NULL),
(1290, 307, 808, 2, 11.00, NULL),
(1291, 306, 808, 3, 1.00, NULL),
(1292, 304, 808, 3, 2.00, NULL),
(1293, 301, 808, 3, 3.00, NULL),
(1294, 309, 808, 3, 4.00, NULL),
(1295, 302, 808, 3, 5.00, NULL),
(1296, 5, 808, 3, 6.00, NULL),
(1297, 305, 808, 3, 7.00, NULL),
(1298, 310, 808, 3, 8.00, NULL),
(1299, 303, 808, 3, 9.00, NULL),
(1300, 308, 808, 3, 10.00, NULL),
(1301, 307, 808, 3, 11.00, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_sistema`
--

CREATE TABLE `configuracion_sistema` (
  `id` int(11) NOT NULL,
  `cantidad_bimestres` int(11) NOT NULL DEFAULT 3,
  `bimestre_actual` int(11) NOT NULL DEFAULT 1,
  `anio_escolar` varchar(9) NOT NULL,
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion_sistema`
--

INSERT INTO `configuracion_sistema` (`id`, `cantidad_bimestres`, `bimestre_actual`, `anio_escolar`, `fecha_modificacion`) VALUES
(1, 3, 1, '2025-2026', '2025-04-11 22:05:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos`
--

CREATE TABLE `cursos` (
  `id_curso` int(11) NOT NULL,
  `nivel` varchar(20) NOT NULL COMMENT 'Ej: Kinder, Primaria, Secundaria',
  `curso` int(11) NOT NULL COMMENT 'Número del curso, ej: 1, 2, 3',
  `paralelo` varchar(5) NOT NULL COMMENT 'Ej: A, B, C'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cursos`
--

INSERT INTO `cursos` (`id_curso`, `nivel`, `curso`, `paralelo`) VALUES
(1, 'Primaria', 1, 'A'),
(2, 'Primaria', 2, 'B'),
(3, 'Secundaria', 1, 'A'),
(4, 'Secundaria', 2, 'B'),
(5, 'Secundaria', 1, 'A'),
(6, 'Secundaria', 1, 'B'),
(100, 'Inicial', 1, 'A'),
(101, 'Inicial', 1, 'B');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos_materias`
--

CREATE TABLE `cursos_materias` (
  `id_curso_materia` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL COMMENT 'FK a cursos',
  `id_materia` int(11) NOT NULL COMMENT 'FK a materias'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cursos_materias`
--

INSERT INTO `cursos_materias` (`id_curso_materia`, `id_curso`, `id_materia`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 2, 1),
(4, 2, 3),
(5, 3, 4),
(6, 4, 3),
(900, 100, 800),
(901, 100, 801),
(902, 100, 802),
(903, 100, 803),
(904, 101, 800),
(905, 101, 801),
(906, 101, 802),
(907, 101, 803),
(908, 1, 804),
(909, 1, 805),
(910, 1, 806),
(911, 1, 807),
(912, 1, 808);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes`
--

CREATE TABLE `estudiantes` (
  `id_estudiante` int(11) NOT NULL,
  `nombres` varchar(255) NOT NULL,
  `apellido_paterno` varchar(255) DEFAULT NULL,
  `apellido_materno` varchar(255) DEFAULT NULL,
  `genero` enum('Masculino','Femenino') DEFAULT NULL,
  `rude` varchar(20) NOT NULL COMMENT 'Registro Único de Estudiante',
  `carnet_identidad` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `id_curso` int(11) DEFAULT NULL COMMENT 'FK al curso en el que está matriculado',
  `id_responsable` int(11) DEFAULT NULL COMMENT 'FK a responsable principal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estudiantes`
--

INSERT INTO `estudiantes` (`id_estudiante`, `nombres`, `apellido_paterno`, `apellido_materno`, `genero`, `rude`, `carnet_identidad`, `fecha_nacimiento`, `id_curso`, `id_responsable`) VALUES
(5, 'Luis', 'Martínez', 'Rodríguez', 'Masculino', 'RUDE0000001', '65748392', '2010-05-12', 1, 1),
(6, 'Sofía', 'Fernández', 'García', 'Femenino', 'RUDE0000002', '84759321', '2011-07-23', 2, 2),
(7, 'Miguel', 'Ortiz', 'Castro', 'Masculino', 'RUDE0000003', '93847365', '2009-02-16', 3, 3),
(8, 'Valeria', 'Paz', 'Suárez', 'Femenino', 'RUDE0000004', '74839265', '2008-11-30', 4, NULL),
(301, 'Matías', 'García', 'López', 'Masculino', 'I1A301', '12345601', '2020-03-15', 1, 201),
(302, 'Valentina', 'López', 'Pérez', 'Femenino', 'I1A302', '12345602', '2020-04-23', 1, 202),
(303, 'Santiago', 'Pérez', 'Flores', 'Masculino', 'I1A303', '12345603', '2020-05-10', 1, 203),
(304, 'Sofía', 'Flores', 'García', 'Femenino', 'I1A304', '12345604', '2020-02-18', 1, 204),
(305, 'Sebastián', 'Mendoza', 'Castillo', 'Masculino', 'I1A305', '12345605', '2020-06-25', 1, 205),
(306, 'Isabella', 'Castillo', 'Torres', 'Femenino', 'I1A306', '12345606', '2020-01-30', 1, 206),
(307, 'Benjamín', 'Torres', 'Ramos', 'Masculino', 'I1A307', '12345607', '2020-07-12', 1, 207),
(308, 'Emilia', 'Ramos', 'Gutiérrez', 'Femenino', 'I1A308', '12345608', '2020-08-05', 1, 208),
(309, 'Nicolás', 'Gutiérrez', 'Paz', 'Masculino', 'I1A309', '12345609', '2020-09-17', 1, 209),
(310, 'Luciana', 'Paz', 'Chávez', 'Femenino', 'I1A310', '12345610', '2020-10-29', 1, 210),
(311, 'Leonardo', 'Chávez', 'Rojas', 'Masculino', 'I1B311', '12345611', '2020-11-03', 2, 211),
(312, 'Victoria', 'Rojas', 'Velasco', 'Femenino', 'I1B312', '12345612', '2020-12-14', 2, 212),
(313, 'Maximiliano', 'Velasco', 'Prado', 'Masculino', 'I1B313', '12345613', '2020-04-08', 2, 213),
(314, 'Antonella', 'Prado', 'Castro', 'Femenino', 'I1B314', '12345614', '2020-05-19', 2, 214),
(315, 'Joaquín', 'Castro', 'Suárez', 'Masculino', 'I1B315', '12345615', '2020-06-22', 2, 215),
(316, 'Valeria', 'Suárez', 'Morales', 'Femenino', 'I1B316', '12345616', '2020-07-11', 2, 216),
(317, 'Tomás', 'Morales', 'Luna', 'Masculino', 'I1B317', '12345617', '2020-08-25', 2, 217),
(318, 'Renata', 'Luna', 'Vargas', 'Femenino', 'I1B318', '12345618', '2020-09-14', 2, 218),
(319, 'Thiago', 'Vargas', 'González', 'Masculino', 'I1B319', '12345619', '2020-10-05', 2, 219),
(320, 'Catalina', 'González', 'Mendoza', 'Femenino', 'I1B320', '12345620', '2020-11-17', 2, 220),
(321, 'Gabriel', 'Mendoza', 'Peralta', 'Masculino', 'P1A321', '12345621', '2019-01-12', 3, 221),
(322, 'Martina', 'Peralta', 'López', 'Femenino', 'P1A322', '12345622', '2019-02-25', 3, 222),
(323, 'Lucas', 'López', 'Torres', 'Masculino', 'P1A323', '12345623', '2019-03-18', 3, 223),
(324, 'Valentina', 'Torres', 'Rivas', 'Femenino', 'P1A324', '12345624', '2019-04-05', 3, 224),
(325, 'Mateo', 'Rivas', 'Díaz', 'Masculino', 'P1A325', '12345625', '2019-05-20', 3, 225),
(326, 'Emma', 'Díaz', 'Santana', 'Femenino', 'P1A326', '12345626', '2019-06-08', 3, 226),
(327, 'Benjamín', 'Santana', 'Pérez', 'Masculino', 'P1A327', '12345627', '2019-07-15', 3, 227),
(328, 'Sofía', 'Pérez', 'Vega', 'Femenino', 'P1A328', '12345628', '2019-08-22', 3, 228),
(329, 'Daniel', 'Vega', 'Cruz', 'Masculino', 'P1A329', '12345629', '2019-09-11', 3, 229),
(330, 'Isabella', 'Cruz', 'Martínez', 'Femenino', 'P1A330', '12345630', '2019-10-27', 3, 230),
(331, 'Samuel', 'Martínez', 'Ramírez', 'Masculino', 'P1B331', '12345631', '2019-11-14', 4, 231),
(332, 'Mía', 'Ramírez', 'Moreno', 'Femenino', 'P1B332', '12345632', '2019-12-01', 4, 232),
(333, 'Alexander', 'Moreno', 'Castillo', 'Masculino', 'P1B333', '12345633', '2019-01-15', 4, 233),
(334, 'Regina', 'Castillo', 'Sánchez', 'Femenino', 'P1B334', '12345634', '2019-02-28', 4, 234),
(335, 'Diego', 'Sánchez', 'Ortiz', 'Masculino', 'P1B335', '12345635', '2019-03-09', 4, 235),
(336, 'Valeria', 'Ortiz', 'Flores', 'Femenino', 'P1B336', '12345636', '2019-04-19', 4, 236),
(337, 'Emiliano', 'Flores', 'Rivera', 'Masculino', 'P1B337', '12345637', '2019-05-26', 4, 237),
(338, 'Camila', 'Rivera', 'Gómez', 'Femenino', 'P1B338', '12345638', '2019-06-18', 4, 238),
(339, 'Maximiliano', 'Gómez', 'Torres', 'Masculino', 'P1B339', '12345639', '2019-07-24', 4, 239),
(340, 'Amanda', 'Torres', 'Mendoza', 'Femenino', 'P1B340', '12345640', '2019-08-09', 4, 240),
(440, 'Santiago', 'Mendoza', 'Cruz', 'Masculino', 'S1A440', '91234641', '2013-01-21', 5, 241),
(441, 'Valentina', 'Cruz', 'Ramos', 'Femenino', 'S1A441', '91234642', '2013-02-14', 5, 242),
(442, 'Facundo', 'Ramos', 'Vega', 'Masculino', 'S1A442', '91234643', '2013-03-09', 5, 243),
(443, 'Luciana', 'Vega', 'Suárez', 'Femenino', 'S1A443', '91234644', '2013-04-17', 5, 244),
(444, 'Sebastián', 'Suárez', 'Méndez', 'Masculino', 'S1A444', '91234645', '2013-05-23', 5, 245),
(445, 'Agustina', 'Méndez', 'Romero', 'Femenino', 'S1A445', '91234646', '2013-06-12', 5, 201),
(446, 'Matías', 'Romero', 'Díaz', 'Masculino', 'S1A446', '91234647', '2013-07-05', 5, 202),
(447, 'Mariana', 'Díaz', 'Reyes', 'Femenino', 'S1A447', '91234648', '2013-08-19', 5, 203),
(448, 'Felipe', 'Reyes', 'Castro', 'Masculino', 'S1A448', '91234649', '2013-09-08', 5, 204),
(449, 'Antonella', 'Castro', 'Morales', 'Femenino', 'S1A449', '91234650', '2013-10-26', 5, 205),
(600, 'Sofía', 'García', 'López', 'Femenino', 'I1A600', '11223301', '2020-05-15', 100, 500),
(601, 'Mateo', 'López', 'García', 'Masculino', 'I1A601', '11223302', '2020-04-20', 100, 501),
(602, 'Valentina', 'Martínez', 'Rodríguez', 'Femenino', 'I1A602', '11223303', '2020-06-10', 100, 502),
(603, 'Thiago', 'Rodríguez', 'Fernández', 'Masculino', 'I1A603', '11223304', '2020-03-25', 100, 503),
(604, 'Emma', 'Fernández', 'Pérez', 'Femenino', 'I1A604', '11223305', '2020-07-12', 100, 504),
(605, 'Lucas', 'Pérez', 'Sánchez', 'Masculino', 'I1A605', '11223306', '2020-02-18', 100, 505),
(606, 'Mía', 'Sánchez', 'Díaz', 'Femenino', 'I1A606', '11223307', '2020-08-30', 100, 506),
(607, 'Benjamín', 'Díaz', 'Torres', 'Masculino', 'I1A607', '11223308', '2020-01-05', 100, 507),
(608, 'Renata', 'Torres', 'Ramírez', 'Femenino', 'I1A608', '11223309', '2020-09-22', 100, 508),
(609, 'Emiliano', 'Ramírez', 'Gómez', 'Masculino', 'I1A609', '11223310', '2020-11-14', 100, 509),
(610, 'Delfina', 'Gómez', 'Suárez', 'Femenino', 'I1B610', '11223311', '2020-05-10', 101, 500),
(611, 'Joaquín', 'Suárez', 'Álvarez', 'Masculino', 'I1B611', '11223312', '2020-04-15', 101, 501),
(612, 'Valeria', 'Álvarez', 'Gutiérrez', 'Femenino', 'I1B612', '11223313', '2020-06-05', 101, 502),
(613, 'Santino', 'Gutiérrez', 'Rojas', 'Masculino', 'I1B613', '11223314', '2020-03-20', 101, 503),
(614, 'Olivia', 'Rojas', 'Mendoza', 'Femenino', 'I1B614', '11223315', '2020-07-01', 101, 504),
(615, 'Matías', 'Mendoza', 'Silva', 'Masculino', 'I1B615', '11223316', '2020-02-10', 101, 505),
(616, 'Amanda', 'Silva', 'Castillo', 'Femenino', 'I1B616', '11223317', '2020-09-18', 101, 506),
(617, 'Felipe', 'Castillo', 'Vargas', 'Masculino', 'I1B617', '11223318', '2020-12-05', 101, 507),
(618, 'Antonella', 'Vargas', 'Castro', 'Femenino', 'I1B618', '11223319', '2020-10-30', 101, 508),
(619, 'Nicolás', 'Castro', 'Romero', 'Masculino', 'I1B619', '11223320', '2020-01-25', 101, 509);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

CREATE TABLE `materias` (
  `id_materia` int(11) NOT NULL,
  `nombre_materia` varchar(255) NOT NULL COMMENT 'Nombre de la materia, ej: Matemáticas, Física',
  `es_submateria` tinyint(1) DEFAULT 0,
  `materia_padre_id` int(11) DEFAULT NULL,
  `es_extra` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`id_materia`, `nombre_materia`, `es_submateria`, `materia_padre_id`, `es_extra`) VALUES
(1, 'Matemáticas', 0, NULL, 0),
(2, 'Lenguaje', 0, NULL, 0),
(3, 'Ciencias Naturales', 0, NULL, 0),
(4, 'Historia', 0, NULL, 0),
(800, 'Desarrollo Socioemocional', 0, NULL, 0),
(801, 'Lenguaje y Comunicación', 0, NULL, 0),
(802, 'Exploración del Entorno', 0, NULL, 0),
(803, 'Psicomotricidad', 0, NULL, 0),
(804, 'Biologia', 0, NULL, 0),
(805, 'Fisica', 1, 804, 0),
(806, 'Quimica', 1, 804, 0),
(807, 'Ciencias Naturales', 1, 804, 0),
(808, 'Inglés', 0, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal`
--

CREATE TABLE `personal` (
  `id_personal` int(11) NOT NULL,
  `nombres` varchar(255) NOT NULL,
  `apellidos` varchar(255) NOT NULL,
  `celular` varchar(20) DEFAULT NULL COMMENT 'Ej: Número de contacto del usuario',
  `carnet_identidad` varchar(20) NOT NULL,
  `id_rol` int(11) NOT NULL COMMENT 'FK a roles',
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `personal`
--

INSERT INTO `personal` (`id_personal`, `nombres`, `apellidos`, `celular`, `carnet_identidad`, `id_rol`, `password`) VALUES
(1, 'Juan', 'Pérez', '789456123', '1234567', 1, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(2, 'María', 'Gómez', '654123987', '2345678', 2, '$2y$10$Rsb7xmKNYFLEaiILD7cIwOw2TxnULqw.XOIVF6jQsayqUj5YOYHSa'),
(3, 'Carlos', 'Rojas', '741852963', '3456789', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(4, 'Ana', 'López', '852963741', '4567890', 3, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(101, 'Carlos', 'Mendoza López', '71234567', '91234501', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(102, 'María', 'Gonzales Tórrez', '72345678', '91234502', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(103, 'Juan', 'Pérez Cabrera', '73456789', '91234503', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(104, 'Ana', 'Flores Vega', '74567890', '91234504', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(105, 'Roberto', 'Vargas Suárez', '75678901', '91234505', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(106, 'Laura', 'Mamani Quispe', '76789012', '91234506', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(107, 'Fernando', 'Rojas Castro', '77890123', '91234507', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(108, 'Sofía', 'Condori Apaza', '78901234', '91234508', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(109, 'Luis', 'Gutiérrez Luna', '79012345', '91234509', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(110, 'Patricia', 'Cruz Velasco', '70123456', '91234510', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(111, 'Miguel', 'Choque Huanca', '71122334', '91234511', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(112, 'Carla', 'Aguilar Pérez', '72233445', '91234512', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(113, 'Daniel', 'Fernández Díaz', '73344556', '91234513', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(114, 'Daniela', 'Quispe Mamani', '74455667', '91234514', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(115, 'José', 'Morales Ibáñez', '75566778', '91234515', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(700, 'Laura', 'Chávez Fernández', '75554433', '700001', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(701, 'Roberto', 'Mamani Quispe', '76665544', '700002', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK'),
(702, 'Carolina', 'Vega Ríos', '77776655', '700003', 2, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesores_materias_cursos`
--

CREATE TABLE `profesores_materias_cursos` (
  `id_profesor_materia_curso` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL COMMENT 'FK a personal (profesor)',
  `id_curso_materia` int(11) NOT NULL COMMENT 'FK a cursos_materias',
  `estado` enum('FALTA','CARGADO') NOT NULL DEFAULT 'FALTA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `profesores_materias_cursos`
--

INSERT INTO `profesores_materias_cursos` (`id_profesor_materia_curso`, `id_personal`, `id_curso_materia`, `estado`) VALUES
(1, 2, 1, 'CARGADO'),
(2, 2, 2, 'CARGADO'),
(3, 3, 3, 'CARGADO'),
(4, 3, 4, 'CARGADO'),
(5, 3, 5, 'CARGADO'),
(1000, 700, 900, 'CARGADO'),
(1001, 700, 901, 'CARGADO'),
(1002, 701, 902, ''),
(1003, 701, 903, ''),
(1004, 702, 904, ''),
(1005, 702, 905, ''),
(1006, 700, 906, 'CARGADO'),
(1007, 701, 907, ''),
(1008, 2, 909, 'CARGADO'),
(1009, 2, 910, 'CARGADO'),
(1010, 2, 911, 'CARGADO'),
(1011, 2, 912, 'CARGADO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `responsables`
--

CREATE TABLE `responsables` (
  `id_responsable` int(11) NOT NULL,
  `nombre_responsable` varchar(255) NOT NULL COMMENT 'Nombre del tutor o responsable',
  `apellido_responsable` varchar(255) NOT NULL COMMENT 'Apellido del tutor o responsable',
  `carnet_identidad_responsable` varchar(20) DEFAULT NULL,
  `celular_responsable` varchar(20) DEFAULT NULL,
  `relacion_estudiante` varchar(50) DEFAULT NULL COMMENT 'Relación con el estudiante: Padre, Madre, Tutor'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `responsables`
--

INSERT INTO `responsables` (`id_responsable`, `nombre_responsable`, `apellido_responsable`, `carnet_identidad_responsable`, `celular_responsable`, `relacion_estudiante`) VALUES
(1, 'Jorge', 'Martínez', '56273849', '789654123', 'Padre'),
(2, 'Clara', 'Fernández', '98456231', '654987321', 'Madre'),
(3, 'Pedro', 'Ortiz', '74683921', '741258963', 'Tutor'),
(201, 'Marcelo', 'García Torres', '91234551', '71589632', 'Padre'),
(202, 'Elena', 'López Mendoza', '91234552', '72596341', 'Madre'),
(203, 'Ricardo', 'Pérez Rojas', '91234553', '73654789', 'Padre'),
(204, 'Claudia', 'Flores Vega', '91234554', '74123658', 'Madre'),
(205, 'Gabriel', 'Mendoza Cruz', '91234555', '75896321', 'Padre'),
(206, 'Paola', 'Castillo Morales', '91234556', '76541239', 'Madre'),
(207, 'Alejandro', 'Torres Vargas', '91234557', '77896541', 'Padre'),
(208, 'Natalia', 'Ramos Suárez', '91234558', '78963214', 'Madre'),
(209, 'Javier', 'Gutiérrez Flores', '91234559', '79841236', 'Padre'),
(210, 'Valeria', 'Paz Mendoza', '91234560', '70123698', 'Madre'),
(211, 'Rodrigo', 'Chávez Luna', '91234561', '71478523', 'Padre'),
(212, 'Camila', 'Rojas Vargas', '91234562', '72365478', 'Madre'),
(213, 'Eduardo', 'Velasco Pérez', '91234563', '73214569', 'Padre'),
(214, 'Mónica', 'Prado Mendoza', '91234564', '74587412', 'Madre'),
(215, 'Guillermo', 'Castro Molina', '91234565', '75236987', 'Padre'),
(216, 'Verónica', 'Suárez Flores', '91234566', '76321458', 'Madre'),
(217, 'Hernán', 'Morales Vega', '91234567', '77145896', 'Padre'),
(218, 'Silvana', 'Luna Torres', '91234568', '78541236', 'Madre'),
(219, 'Oscar', 'Vargas Pérez', '91234569', '79632541', 'Padre'),
(220, 'Isabel', 'González Ramos', '91234570', '70258963', 'Madre'),
(221, 'Raúl', 'Mendoza García', '91234571', '71236547', 'Padre'),
(222, 'Diana', 'Peralta Rojas', '91234572', '72365412', 'Madre'),
(223, 'Jorge', 'López Cruz', '91234573', '73698521', 'Padre'),
(224, 'Sandra', 'Torres Díaz', '91234574', '74125896', 'Madre'),
(225, 'Roberto', 'Rivas Morales', '91234575', '75896321', 'Padre'),
(226, 'Carmen', 'Díaz Pérez', '91234576', '76325417', 'Madre'),
(227, 'Felipe', 'Santana Ramos', '91234577', '77896541', 'Padre'),
(228, 'Ana María', 'Pérez Suárez', '91234578', '78963214', 'Madre'),
(229, 'Marcos', 'Vega Torres', '91234579', '79841236', 'Padre'),
(230, 'Victoria', 'Cruz Mendoza', '91234580', '70123698', 'Madre'),
(231, 'Gustavo', 'Martínez López', '91234581', '71478523', 'Padre'),
(232, 'Julia', 'Ramírez Vega', '91234582', '72365478', 'Madre'),
(233, 'Sergio', 'Moreno Torres', '91234583', '73214569', 'Padre'),
(234, 'Lucía', 'Castillo Díaz', '91234584', '74587412', 'Madre'),
(235, 'Pedro', 'Sánchez Rivas', '91234585', '75236987', 'Padre'),
(236, 'Mariana', 'Ortiz Pérez', '91234586', '76321458', 'Madre'),
(237, 'Martín', 'Flores Morales', '91234587', '77145896', 'Padre'),
(238, 'Teresa', 'Rivera Luna', '91234588', '78541236', 'Madre'),
(239, 'Pablo', 'Gómez Vargas', '91234589', '79632541', 'Padre'),
(240, 'Carolina', 'Torres López', '91234590', '70258963', 'Madre'),
(241, 'Rafael', 'Mendoza González', '91234591', '71236547', 'Padre'),
(242, 'Daniela', 'Cruz Pérez', '91234592', '72365412', 'Madre'),
(243, 'Eduardo', 'Ramos Torres', '91234593', '73698521', 'Padre'),
(244, 'Luciana', 'Vega Morales', '91234594', '74125896', 'Madre'),
(245, 'Joaquín', 'Suárez Rojas', '91234595', '75896321', 'Padre'),
(500, 'Carlos', 'García', '1122334', '71122334', 'Padre'),
(501, 'María', 'López', '2233445', '72233445', 'Madre'),
(502, 'Pedro', 'Martínez', '3344556', '73344556', 'Padre'),
(503, 'Ana', 'Rodríguez', '4455667', '74455667', 'Madre'),
(504, 'Jorge', 'Fernández', '5566778', '75566778', 'Padre'),
(505, 'Lucía', 'Pérez', '6677889', '76677889', 'Madre'),
(506, 'Fernando', 'Sánchez', '7788990', '77788990', 'Padre'),
(507, 'Carmen', 'Díaz', '8899001', '78899001', 'Madre'),
(508, 'Diego', 'Torres', '9900112', '79900112', 'Padre'),
(509, 'Isabel', 'Ramírez', '0011223', '70011223', 'Madre');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL COMMENT 'Ej: Administrador, Profesor, Secretario'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`) VALUES
(1, 'Administrador'),
(2, 'Profesor'),
(3, 'Secretario');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `anuncios`
--
ALTER TABLE `anuncios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `bimestres_activos`
--
ALTER TABLE `bimestres_activos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `calificaciones`
--
ALTER TABLE `calificaciones`
  ADD PRIMARY KEY (`id_calificacion`),
  ADD UNIQUE KEY `id_estudiante` (`id_estudiante`,`id_materia`,`bimestre`),
  ADD KEY `id_materia` (`id_materia`);

--
-- Indices de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`);

--
-- Indices de la tabla `cursos_materias`
--
ALTER TABLE `cursos_materias`
  ADD PRIMARY KEY (`id_curso_materia`),
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `id_materia` (`id_materia`);

--
-- Indices de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id_estudiante`),
  ADD UNIQUE KEY `rude` (`rude`),
  ADD UNIQUE KEY `carnet_identidad` (`carnet_identidad`),
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `id_responsable` (`id_responsable`);

--
-- Indices de la tabla `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id_materia`);

--
-- Indices de la tabla `personal`
--
ALTER TABLE `personal`
  ADD PRIMARY KEY (`id_personal`),
  ADD UNIQUE KEY `carnet_identidad` (`carnet_identidad`),
  ADD KEY `id_rol` (`id_rol`);

--
-- Indices de la tabla `profesores_materias_cursos`
--
ALTER TABLE `profesores_materias_cursos`
  ADD PRIMARY KEY (`id_profesor_materia_curso`),
  ADD UNIQUE KEY `id_personal` (`id_personal`,`id_curso_materia`),
  ADD KEY `id_curso_materia` (`id_curso_materia`);

--
-- Indices de la tabla `responsables`
--
ALTER TABLE `responsables`
  ADD PRIMARY KEY (`id_responsable`),
  ADD UNIQUE KEY `carnet_identidad_responsable` (`carnet_identidad_responsable`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `anuncios`
--
ALTER TABLE `anuncios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `bimestres_activos`
--
ALTER TABLE `bimestres_activos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `calificaciones`
--
ALTER TABLE `calificaciones`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1335;

--
-- AUTO_INCREMENT de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT de la tabla `cursos_materias`
--
ALTER TABLE `cursos_materias`
  MODIFY `id_curso_materia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=913;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=620;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id_materia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=809;

--
-- AUTO_INCREMENT de la tabla `personal`
--
ALTER TABLE `personal`
  MODIFY `id_personal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=703;

--
-- AUTO_INCREMENT de la tabla `profesores_materias_cursos`
--
ALTER TABLE `profesores_materias_cursos`
  MODIFY `id_profesor_materia_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1012;

--
-- AUTO_INCREMENT de la tabla `responsables`
--
ALTER TABLE `responsables`
  MODIFY `id_responsable` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=510;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `calificaciones`
--
ALTER TABLE `calificaciones`
  ADD CONSTRAINT `calificaciones_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `calificaciones_ibfk_2` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `cursos_materias`
--
ALTER TABLE `cursos_materias`
  ADD CONSTRAINT `cursos_materias_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cursos_materias_ibfk_2` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD CONSTRAINT `estudiantes_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `estudiantes_ibfk_2` FOREIGN KEY (`id_responsable`) REFERENCES `responsables` (`id_responsable`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `personal`
--
ALTER TABLE `personal`
  ADD CONSTRAINT `personal_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `profesores_materias_cursos`
--
ALTER TABLE `profesores_materias_cursos`
  ADD CONSTRAINT `profesores_materias_cursos_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `personal` (`id_personal`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `profesores_materias_cursos_ibfk_2` FOREIGN KEY (`id_curso_materia`) REFERENCES `cursos_materias` (`id_curso_materia`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
