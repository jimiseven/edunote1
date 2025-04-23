-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-04-2025 a las 18:05:18
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
(102, 'Inicial', 1, 'A'),
(103, 'Inicial', 1, 'B'),
(104, 'Inicial', 2, 'A'),
(105, 'Inicial', 2, 'B'),
(106, 'Primaria', 1, 'A'),
(107, 'Primaria', 1, 'B'),
(108, 'Primaria', 2, 'A'),
(109, 'Primaria', 2, 'B'),
(110, 'Primaria', 3, 'A'),
(111, 'Primaria', 3, 'B'),
(112, 'Primaria', 4, 'A'),
(113, 'Primaria', 4, 'B'),
(114, 'Primaria', 5, 'A'),
(115, 'Primaria', 5, 'B'),
(116, 'Primaria', 6, 'A'),
(117, 'Primaria', 6, 'B'),
(118, 'Secundaria', 1, 'A'),
(119, 'Secundaria', 1, 'B'),
(120, 'Secundaria', 2, 'A'),
(121, 'Secundaria', 2, 'B'),
(122, 'Secundaria', 3, 'A'),
(123, 'Secundaria', 3, 'B'),
(124, 'Secundaria', 4, 'A'),
(125, 'Secundaria', 4, 'B'),
(126, 'Secundaria', 5, 'A'),
(127, 'Secundaria', 5, 'B'),
(128, 'Secundaria', 6, 'A'),
(129, 'Secundaria', 6, 'B');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos_materias`
--

CREATE TABLE `cursos_materias` (
  `id_curso_materia` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL COMMENT 'FK a cursos',
  `id_materia` int(11) NOT NULL COMMENT 'FK a materias'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `id_curso` int(11) DEFAULT NULL COMMENT 'FK al curso en el que está matriculado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estudiantes`
--

INSERT INTO `estudiantes` (`id_estudiante`, `nombres`, `apellido_paterno`, `apellido_materno`, `genero`, `rude`, `carnet_identidad`, `fecha_nacimiento`, `id_curso`) VALUES
(672, 'DEMIR MATIAS', 'ARIAS', 'VILLARROEL', 'Masculino', '4090000520255641', '16511091', '2020-10-08', 102),
(673, 'MAYER SAID', 'AYAVIRI', 'SOCOMPI', 'Masculino', '4090000520251769', '16883192', '2020-12-01', 102),
(674, 'ALESSANDRO LEON', 'BUSTAMANTE', 'ROCHA', 'Masculino', '4090000520253732', '16604327', '2020-12-21', 102),
(675, 'ANDRES SEBASTIAN', 'CAMPOS', 'SELAYA', 'Masculino', '4090000520254728', '16858986', '2021-02-16', 102),
(676, 'ZOE ARLETH', 'CHOQUE', 'ROMERO', 'Femenino', '4090000520254741', '16471391', '2020-07-25', 102),
(677, 'ALEJANDRA DENISE', 'COCA', 'OTALORA', 'Femenino', '4090000520256924', '16996923', '2020-08-16', 102),
(678, 'FERMIN', 'COLQUE', 'BORDA', 'Masculino', '4090000520257472', '16596931', '2020-11-19', 102),
(679, 'CARMEN ROSSY', 'FATTY', 'TOCO', 'Femenino', '4090000520258373', '16986213', '2020-02-27', 102),
(680, 'JAIR AARON', 'FERNANDEZ', 'FRANCO', 'Masculino', '4090000520257471', '16889545', '2020-12-07', 102),
(681, 'MICAEL', 'FERRUFINO', 'MAIZO', 'Masculino', '4090000520257411', '16471397', '2021-04-09', 102),
(682, 'MATIAS JHOAN', 'FLORES', 'MAMANI', 'Masculino', '4090000520253411', '17463792', '2021-09-04', 102),
(683, 'AYSE CHARLOTTE', 'LEDEZMA', 'SALAZAR', 'Femenino', '4090000520256927', '17643762', '2020-10-16', 102),
(684, 'ANDRES JHUNIOR', 'MENESES', 'QUISPE', 'Masculino', '4090000520253101', '16936901', '2021-05-14', 102),
(685, 'TAYLOR JAMES', 'MENESES', 'VILLARROEL', 'Masculino', '4090000520253104', '16936901', '2021-01-04', 102),
(686, 'DAMARIS NICOLAS', 'RODRIGUEZ', '', 'Femenino', '4090000520259241', '16832421', '2021-06-28', 102),
(687, 'SALOME CRISTAL', 'PADILLA', 'CHAVARRIA', 'Femenino', '4090000520253111', '16832421', '2021-06-25', 102),
(688, 'GABRIEL JESUS', 'REINAGA', 'REVOLLO', 'Masculino', '4090000520253831', '16384231', '2021-06-23', 102),
(689, 'XIOMARA', 'REINAGA', 'VALDA', 'Femenino', '4090000520253116', '16384216', '2021-02-10', 102),
(690, 'LUANA VALENTINA', 'SANCHEZ', 'ABAN', 'Femenino', '4090000520253492', '16715857', '2021-03-03', 102),
(691, 'HANSSEL PABLO', 'CÉSPEDES', 'APAZA', 'Masculino', '4090000520255838', '16886890', '2021-02-07', 102),
(692, 'MARIBEL', 'SOLIZ', 'CHOQUEHUANCA', 'Femenino', '4090000520253583', '16886890', '2021-02-07', 102),
(693, 'ARIANA GISSEL', 'CHOQUE', 'TAPIA', 'Femenino', '4090000520253117', '16865331', '2020-12-13', 102),
(694, 'NAYELI ESTRELLA', 'TICONA', 'HINOJOSA', 'Femenino', '4090000520253119', '16865331', '2020-08-17', 102),
(695, 'ALAN GONEI', 'VARGAS', 'MONTECINOS', 'Masculino', '4090000520251107', '16487391', '2020-08-17', 102),
(696, 'KALESSY FABIOLA', 'VEGA', 'HERNANDEZ', 'Femenino', '4090000520251866', NULL, '2020-08-17', 102),
(697, 'EVELYN', 'ZEBALLOS', 'TUDELA', 'Femenino', '4090000520251866', '16475369', '2020-09-05', 102),
(698, 'YEIMI LUNA', 'AGUILAR', 'SALAZAR', 'Femenino', '40900005020475478', '1640177', '2023-03-02', 104),
(699, 'NICOL', 'ANGULO', 'RUIZ', 'Femenino', '40900005020449541', '1640351', '2020-04-07', 104),
(700, 'JOSUE ABIDEL', 'APAZA', 'ALVARADO', 'Masculino', '40900005020445368', '1640116', '2019-11-15', 104),
(701, 'GAEL JORDY', 'BURGUILA', 'FLORES', 'Masculino', '40900005020475444', '1643254', '2020-09-12', 104),
(702, 'ELISEO', 'CHAMBI', 'VICENTE', 'Masculino', '40900005020424762', '1640194', '2023-03-08', 104),
(703, 'ELIAZAR KALEP', 'CHIPANA', 'MAMANI', 'Masculino', '40900005020436410', '1643879', '2020-04-29', 104),
(704, 'DARELL EDRIK', 'CHOQUE', 'CESPEDES', 'Masculino', '40900005020428529', '1651382', '2020-05-13', 104),
(705, 'MARIO ROMAN', 'CONDORI', 'ROCHA', 'Masculino', '40900005020436496', '1639476', '2023-03-13', 104),
(706, 'BIDANEYRA ANNDY', 'CRUZ', 'GUTIERREZ', 'Femenino', '40900005020475478', '1623842', '2019-11-16', 104),
(707, 'NIJAN ZOE', 'ESCOBAR', 'PINTO', 'Femenino', '40900005020429881', '1691301', '2020-06-14', 104),
(708, 'IAN JHAIR', 'GARCIA', 'REINAGA', 'Masculino', '40900005020449881', '1691301', '2020-04-01', 104),
(709, 'ALEX JHUNIOR', 'GUTIERREZ', 'MARTINEZ', 'Masculino', '40900005020432277', '1700360', '2020-04-14', 104),
(710, 'LUCAS THIAGO', 'LUIZAGA', 'VEDIA', 'Masculino', '40900005020475478', NULL, '2023-03-06', 104),
(711, 'BRITTANY BRIANA', 'MAMANI', 'GARCIA', 'Femenino', '40900005020475478', '1632595', '2019-09-29', 104),
(712, 'YADIL', 'MAMANI', 'TACURI', 'Masculino', '4090000502048670', '16596235', '2019-09-27', 104),
(713, 'DAYER ALTES', 'MAMANI', 'VILLARROEL', 'Masculino', '40900005020475478', '1640194', '2020-04-13', 104),
(714, 'MIA NAYELY', 'MARCA', 'OYARDO', 'Femenino', '40900005020475478', '1640194', '2019-05-26', 104),
(715, 'JEICOB MARCELINO', 'MARCANI', 'ESCOBAR', 'Masculino', '40900005020475478', '1730732', '2020-03-25', 104),
(716, 'RAZIEL AITANA', 'PALLA', 'CONDORI', 'Femenino', '40900005020475478', '1640194', '2019-06-15', 104),
(717, 'MADLIN ZULEYKA', 'SOLIZ', 'ROCHA', 'Femenino', '4090000502041479', '1641978', '2019-10-26', 104),
(718, 'VALENTINA TATIANA', 'VELIZ', 'AIRA', 'Femenino', '40900005020475478', '1640194', '2020-06-13', 104),
(719, 'TATIANA', 'ZEGARRA', 'PADILLA', 'Femenino', '40900005020475478', '1640194', '2020-03-18', 104),
(720, 'JORGE MISAEL', 'ZURITA', 'VERA', 'Masculino', '40900005020475478', '1641978', '2020-03-17', 104),
(721, 'ALDAIR VICENTE', 'ALMANZA', 'FLORES', NULL, '40900005020254643', '16343905', '2020-01-22', 105),
(722, 'JOHN ABIDIEL', 'CABEZAS', 'JUÑES', NULL, '40900005020255324', '16416887', '2019-10-25', 105),
(723, 'SEBASTIAN', 'CALI', 'VELIZ', NULL, '40900005020255430', '16411225', '2019-10-21', 105),
(724, 'BRAYAN', 'CASILLA', 'UGARTE', NULL, '40900005020257865', '17829198', '2019-08-22', 105),
(725, 'ALESSANDRA MARIELA', 'CHAMBILLA', 'LLAVE', NULL, '40900005020244314', '16455522', '2020-06-12', 105),
(726, 'MATIAS', 'CHOQUE', 'MARTINEZ', NULL, '8090861550241485', '16344032', '2020-04-15', 105),
(727, 'DAMIAN', 'COCA', 'GARCIA', NULL, '40900005020257457', '16411225', '2020-01-14', 105),
(728, 'JUAN ANDERSON', 'COLQUE', 'FLORES', NULL, '40900005020252436', '16320466', '2019-10-18', 105),
(729, 'EVELIN', 'FLORES', 'LEON', NULL, '40900005020247490', '16519021', '2020-02-27', 105),
(730, 'LIAN ZAID', 'FUENTES', 'CALI', NULL, '40900005020252079', '16419049', '2020-04-08', 105),
(731, 'IFFET', 'LIMACHI', 'MAMANI', NULL, '40900005020254431', '16416887', '2020-04-09', 105),
(732, 'ZOE LUCIA', 'LIZARAZU', 'FLORES', NULL, '40900005020248556', '16461126', '2020-05-05', 105),
(733, 'KAELY JHARIANNE', 'LOZA', 'PEREZ', NULL, '40900005020254321', '16451244', '2019-03-05', 105),
(734, 'HAFID', 'LUNA', 'MAMANI', NULL, '8090848620247558', '16501363', '2019-08-29', 105),
(735, 'MOISES FERNANDO', 'MARTINEZ', 'MAMANI', NULL, '40900005020254457', '16416887', '2020-02-21', 105),
(736, 'JHOANA JHOY', 'PADILLA', 'CHAVARRIA', NULL, '40900005020254437', '16419493', '2019-09-23', 105),
(737, 'RONAL MOISES', 'QUISPE', 'MARTINEZ', NULL, '40900005020254431', '16416887', '2020-03-13', 105),
(738, 'THIAGO MANUEL', 'RAMIREZ', 'CAMACHO', NULL, '40900005020254437', '16419493', '2020-01-11', 105),
(739, 'BRITANI', 'REVOLLO', 'CONDORI', NULL, '40900005020254457', '16974086', '2020-02-13', 105),
(740, 'JUAN DANIEL', 'SOLIS', 'CARRILLO', NULL, '80908001720241130', '16502629', '2020-04-13', 105),
(741, 'DIANA GENESIS', 'TABOADA', 'SAAVEDRA', NULL, '40900005020247387', '16626010', '2020-01-27', 105),
(742, 'EDSON MAGMET', 'VALVERDE', 'MORALES', NULL, '40900005020249452', '17112646', '2020-01-27', 105),
(743, 'MIGUEL ANGEL', 'ARIAS', 'VILLARROEL', NULL, '409000027202323786', '15433307', '2018-10-02', 106),
(744, 'DEBRAN', 'CABALLERO', 'SILVA', NULL, '40900005202315456', '15475856', '2018-09-15', 106),
(745, 'CARLOS DILAN', 'CARRILLO', 'QUISPE', NULL, '17211447', '15455307', '2018-07-18', 106),
(746, 'MATÍAS EZEQUIEL', 'CARRILLO', 'VILLARROEL', NULL, '40900005202427320', '15362400', '2018-09-21', 106),
(747, 'BRIANA KEILA', 'CARTAGENA', 'MERCADO', NULL, '40900005202323757', '15665222', '2019-02-11', 106),
(748, 'VALERIA HAEL', 'COCA', 'LÉDIZMA', NULL, '40900005202323786', '15433307', '2018-11-29', 106),
(749, 'DAVID', 'CORDOVA', 'MOSQUEZ', NULL, '40900005202321456', '15433307', '2018-11-29', 106),
(750, 'FRANCO', 'CRUZ', 'CHAMBI', NULL, '40900005202323736', '15999929', '2018-12-14', 106),
(751, 'BRYANA RAFAELA', 'CRUZ', 'HERNANDEZ', NULL, '40900005202325636', '15432625', '2018-09-30', 106),
(752, 'JOSUE', 'DIAS', 'VARGAS', NULL, '40900005202325823', '15382866', '2018-10-14', 106),
(753, 'PABLO AURELIO', 'FLORES', 'ESCOBAR', NULL, '40900005202323786', '15433307', '2018-09-21', 106),
(754, 'DILAN KAEL', 'FUENTES', 'GASPAR', NULL, '40900005202324929', '15433307', '2018-10-25', 106),
(755, 'ALEXANDER', 'FUENTES', 'VALENCIA', NULL, '809000352024297', NULL, '2019-11-25', 106),
(756, 'ERICK RUBEN', 'HEREDIA', 'MOLINA', NULL, '40900005202441655', '16556611', '2019-01-31', 106),
(757, 'GENESIS ABIGAIL', 'MAMANI', 'IQUISE', NULL, '4090000520239541', '15759929', '2019-03-23', 106),
(758, 'IBETH', 'MAMANI', 'ORTIZ', NULL, '4090000520239541', '15759929', '2019-03-23', 106),
(759, 'NEIMAR JHOAN', 'MONTECINOS', 'CUBA', NULL, '40900005202323786', '15433307', '2018-11-29', 106),
(760, 'ISMAEL', 'MORALES', 'PEÑA', NULL, '40900005202323786', '15433307', '2018-11-29', 106),
(761, 'SALVADOR ESTEBAN', 'ROCHA', 'YANA', NULL, '809004202036197', '15594809', '2019-01-11', 106),
(762, 'CAMILA', 'ROJAS', 'VILLARROEL', NULL, '40900005202323786', '15433307', '2018-11-29', 106),
(763, 'BRYAN MARIO', 'SOCOMPI', 'ROCHA', NULL, '40900005202323786', '15433307', '2018-11-29', 106),
(764, 'HELEN AYLEN', 'TICONA', 'HINOJOSA', NULL, '40900005202323786', '15433307', '2018-11-29', 106),
(765, 'JHOANNY CATALINA', 'TORIBIO', 'RIVERA', NULL, '80900021023658A', '15867607', '2019-01-23', 106),
(766, 'YENIFER', 'TORREZ', 'TOAQUE', NULL, '40900005202323786', '15433307', '2018-11-29', 106),
(767, 'CAMILA SHANDIRA', 'VASQUEZ', 'CARRILLO', NULL, '40900005202323786', '15433307', '2018-11-29', 106),
(768, 'ANYELO MATEO', 'VILLARROEL', 'PINTO', NULL, '409000052023269', '15874177', '2019-01-17', 106),
(769, 'DAVID FRANCO', 'VILLARROEL', 'MAMANI', NULL, '409000052023269', '15874177', '2019-01-13', 106),
(770, 'CARMEN TERESA', 'ALEGRE', 'GONZALES', NULL, '4090000502233185', '15466137', '2018-10-18', 107),
(771, 'EYDEN ASBEL', 'ALIAGA', 'MEDINA', NULL, '4090000502245965', '15496587', '2018-09-26', 107),
(772, 'JHISSEL ANTHONELA', 'CARATA', 'LAUREANO', NULL, '4090000502245965', '15935658', '2019-06-12', 107),
(773, 'SANTIAGO', 'CORDOVA', 'HERBAS', NULL, '4090000502243804', '15468991', '2018-10-19', 107),
(774, 'RUTH NOEMI', 'ESTRADA', 'ESTRADA', NULL, '4090000502245767', '15551132', '2019-12-27', 107),
(775, 'NEYMAR', 'GOMEZ', 'CABEROS', NULL, '4090000502257619', '15966853', '2019-01-01', 107),
(776, 'SEBASTIAN', 'GUERRA', 'CACERES', NULL, '8090843420233499', '15366103', '2018-08-02', 107),
(777, 'EIDAN RIDER', 'INOCENTE', 'SOTO', NULL, '4090000502249195', '15935521', '2019-06-02', 107),
(778, 'NICOLETTE GLORIA', 'JANCO', 'AYALA', NULL, '4090000502238563', '15564859', '2019-01-02', 107),
(779, 'JAZMIN LIZETH', 'LEDEZMA', 'ROCHA', NULL, '40900002720237633', '15629182', '2019-01-26', 107),
(780, 'LIA DANITZA', 'LIZARAZU', 'FLORES', NULL, '40900002720237633', '15629182', '2019-01-26', 107),
(781, 'DILAVER', 'LOPEZ', 'VERA', NULL, '81230002720245360', '15743229', '2019-07-30', 107),
(782, 'JHEICO MATIAS', 'MENECES', 'CARRILLO', NULL, '4090000502243150', '17746671', '2019-06-01', 107),
(783, 'RAMIRO', 'MICACIO', 'GERONIMO', NULL, '4090000502243066', '15490578', '2018-10-30', 107),
(784, 'LAIZ VALENTINA', 'OMONTE', 'ARACAYO', NULL, '40900002720237633', '15629182', '2019-01-26', 107),
(785, 'HECTOR JHAIR', 'OTALORA', 'SALAZAR', NULL, '8090831020243066', '15490578', '2018-07-20', 107),
(786, 'DENNIS LEYDI', 'POMA', 'ALBA', NULL, '40900002720237633', '15629182', '2019-12-17', 107),
(787, 'KENDRA ISABELLA', 'POMA', 'CHOQUE', NULL, '4090000272023707A', '15629182', '2019-01-02', 107),
(788, 'IAN MATIAS', 'QUISPE', 'MAITA', NULL, '4090000502243163', '15691420', '2019-02-21', 107),
(789, 'GUSTAVO', 'QUISPE', 'MARTINEZ', NULL, '4090000502243163', '15571961', '2019-02-21', 107),
(790, 'ZAIDA', 'RIOS', 'LUIZAGA', NULL, '4090000502243163', '15691420', '2019-02-21', 107),
(791, 'ABEL', 'ROCHA', 'PEÑA', NULL, '4090000502254929', '15825794', '2019-01-12', 107),
(792, 'MIGUEL ANGEL', 'ROJAS', 'QUECAÑA', NULL, '4090000502243163', '15691420', '2019-02-21', 107),
(793, 'JHON ABDIL', 'SOLIZ', 'CHOQUEHUANCA', NULL, '4090000502243163', '15691420', '2019-02-21', 107),
(794, 'NIJAN JHAMILET', 'VARGAS', 'MONTECINOS', NULL, '4090000502243163', '15691420', '2019-02-21', 107),
(795, 'EDUARS ANDRES', 'VERA', 'CHOQUE', NULL, '4090000502253539', '15361330', '2018-12-27', 107);

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
(1, 'Juan', 'Pérez', '789456123', '1234567', 1, '$2y$10$gptllkXafWZRnjfLTpVzhe5Y6R0a5HzfnxhWxP9FyS73uCk4Dq3JK');

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
  ADD KEY `id_curso` (`id_curso`);

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
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT de la tabla `cursos_materias`
--
ALTER TABLE `cursos_materias`
  MODIFY `id_curso_materia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=913;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=796;

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
  ADD CONSTRAINT `estudiantes_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE SET NULL ON UPDATE CASCADE;

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
