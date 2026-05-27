-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 29-04-2026 a las 14:59:01
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

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
-- Estructura de tabla para la tabla `asistencia`
--

CREATE TABLE `asistencia` (
  `id_asistencia` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL COMMENT 'FK a estudiantes',
  `fecha` date NOT NULL,
  `hora_entrada` time NOT NULL,
  `tipo_registro` enum('QR','MANUAL') DEFAULT 'QR' COMMENT 'QR: escaneado con QR, MANUAL: registrado manualmente',
  `estado_puntualidad` enum('TEMPRANO','TARDE') DEFAULT NULL,
  `hora_ingreso_programada` time DEFAULT NULL,
  `tolerancia_min` int(11) DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL COMMENT 'FK a personal (si es manual)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `asistencia`
--

INSERT INTO `asistencia` (`id_asistencia`, `id_estudiante`, `fecha`, `hora_entrada`, `tipo_registro`, `estado_puntualidad`, `hora_ingreso_programada`, `tolerancia_min`, `registrado_por`, `created_at`) VALUES
(75, 2327, '2026-04-26', '23:09:56', 'QR', NULL, NULL, NULL, NULL, '2026-04-27 03:09:56'),
(76, 2328, '2026-04-26', '23:09:58', 'QR', NULL, NULL, NULL, NULL, '2026-04-27 03:09:58'),
(77, 2329, '2026-04-26', '23:10:00', 'QR', NULL, NULL, NULL, NULL, '2026-04-27 03:10:00'),
(78, 2330, '2026-04-26', '23:10:02', 'QR', NULL, NULL, NULL, NULL, '2026-04-27 03:10:02'),
(79, 2336, '2026-04-26', '23:11:44', 'QR', NULL, NULL, NULL, NULL, '2026-04-27 03:11:44'),
(80, 2337, '2026-04-26', '23:11:54', 'QR', NULL, NULL, NULL, NULL, '2026-04-27 03:11:54'),
(81, 2338, '2026-04-26', '23:12:17', 'QR', NULL, NULL, NULL, NULL, '2026-04-27 03:12:17'),
(82, 2340, '2026-04-26', '23:16:47', 'QR', 'TEMPRANO', '23:20:00', 0, NULL, '2026-04-27 03:16:47'),
(83, 2341, '2026-04-26', '23:16:50', 'QR', 'TEMPRANO', '23:20:00', 0, NULL, '2026-04-27 03:16:50'),
(84, 2342, '2026-04-26', '23:16:54', 'QR', 'TEMPRANO', '23:20:00', 0, NULL, '2026-04-27 03:16:54'),
(85, 2343, '2026-04-26', '23:16:56', 'QR', 'TEMPRANO', '23:20:00', 0, NULL, '2026-04-27 03:16:56'),
(86, 2348, '2026-04-26', '23:21:24', 'QR', 'TARDE', '23:20:00', 0, NULL, '2026-04-27 03:21:24'),
(87, 2349, '2026-04-26', '23:21:26', 'QR', 'TARDE', '23:20:00', 0, NULL, '2026-04-27 03:21:26'),
(88, 2350, '2026-04-26', '23:21:28', 'QR', 'TARDE', '23:20:00', 0, NULL, '2026-04-27 03:21:28'),
(89, 2351, '2026-04-26', '23:21:30', 'QR', 'TARDE', '23:20:00', 0, NULL, '2026-04-27 03:21:30'),
(90, 2352, '2026-04-26', '23:21:33', 'QR', 'TARDE', '23:20:00', 0, NULL, '2026-04-27 03:21:33'),
(91, 2328, '2026-04-28', '18:29:36', 'QR', 'TEMPRANO', '23:20:00', 0, NULL, '2026-04-28 22:29:36'),
(92, 2327, '2026-04-28', '18:29:38', 'QR', 'TEMPRANO', '23:20:00', 0, NULL, '2026-04-28 22:29:38'),
(93, 2329, '2026-04-28', '18:29:47', 'QR', 'TEMPRANO', '23:20:00', 0, NULL, '2026-04-28 22:29:47'),
(94, 2330, '2026-04-28', '18:29:51', 'QR', 'TEMPRANO', '23:20:00', 0, NULL, '2026-04-28 22:29:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia_horarios_ingreso`
--

CREATE TABLE `asistencia_horarios_ingreso` (
  `id_horario` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `hora_ingreso` time NOT NULL,
  `tolerancia_min` int(11) NOT NULL DEFAULT 0,
  `estado` tinyint(4) NOT NULL DEFAULT 1,
  `creado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `asistencia_horarios_ingreso`
--

INSERT INTO `asistencia_horarios_ingreso` (`id_horario`, `fecha_inicio`, `fecha_fin`, `hora_ingreso`, `tolerancia_min`, `estado`, `creado_por`, `created_at`) VALUES
(3, '2026-02-02', '2026-05-30', '23:20:00', 0, 1, 731, '2026-04-27 03:16:13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia_lectores`
--

CREATE TABLE `asistencia_lectores` (
  `id_lector` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL,
  `alcance` enum('GLOBAL','POR_CURSO') NOT NULL DEFAULT 'GLOBAL',
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=habilitado, 0=inhabilitado',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `asistencia_lectores`
--

INSERT INTO `asistencia_lectores` (`id_lector`, `id_personal`, `alcance`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(7, 705, 'GLOBAL', 1, '2026-04-27 03:05:22', '2026-04-27 03:05:22'),
(8, 724, 'GLOBAL', 1, '2026-04-28 11:25:53', '2026-04-28 11:25:53'),
(9, 731, 'GLOBAL', 1, '2026-04-28 22:19:09', '2026-04-28 22:19:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia_lectores_cursos`
--

CREATE TABLE `asistencia_lectores_cursos` (
  `id_lector_curso` int(11) NOT NULL,
  `id_lector` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=habilitado, 0=inhabilitado',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 1, 1, '2025-04-15', '2025-04-17', '2026-03-17 04:41:32'),
(2, 2, 0, '2025-08-23', '2025-08-30', '2025-09-12 16:45:41'),
(3, 3, 0, '2025-11-08', '2025-12-05', '2026-03-17 04:07:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificaciones`
--

CREATE TABLE `calificaciones` (
  `id_calificacion` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL COMMENT 'FK a estudiantes',
  `id_materia` int(11) NOT NULL COMMENT 'FK a materias',
  `bimestre` int(11) NOT NULL COMMENT 'Número del bimestre: 1, 2, 3, 4',
  `calificacion` float NOT NULL DEFAULT 0,
  `comentario` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificaciones_parciales`
--

CREATE TABLE `calificaciones_parciales` (
  `id_calificacion_parcial` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `id_periodo_evaluacion` int(11) NOT NULL,
  `calificacion` float NOT NULL DEFAULT 0,
  `ser_total` decimal(6,2) NOT NULL DEFAULT 0.00,
  `saber_total` decimal(6,2) NOT NULL DEFAULT 0.00,
  `hacer_total` decimal(6,2) NOT NULL DEFAULT 0.00,
  `autoevaluacion` decimal(6,2) NOT NULL DEFAULT 0.00,
  `puntaje_extra` decimal(6,2) NOT NULL DEFAULT 0.00,
  `id_profesor` int(11) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `comentario` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificaciones_parciales_detalle`
--

CREATE TABLE `calificaciones_parciales_detalle` (
  `id_detalle` int(11) NOT NULL,
  `id_calificacion_parcial` int(11) NOT NULL,
  `area` enum('SER','SABER','HACER') NOT NULL,
  `indice` tinyint(4) NOT NULL,
  `nota` decimal(6,2) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificaciones_trimestrales`
--

CREATE TABLE `calificaciones_trimestrales` (
  `id` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `gestion` varchar(9) NOT NULL,
  `trimestre` tinyint(4) NOT NULL,
  `autoevaluacion` float DEFAULT NULL COMMENT 'Nota de autoevaluaci├│n (0-5)',
  `nota_extra` float DEFAULT NULL COMMENT 'Nota extra / puntaje adicional',
  `id_profesor` int(11) DEFAULT NULL,
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
  `modalidad_carga_notas` varchar(20) NOT NULL DEFAULT 'parciales',
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion_sistema`
--

INSERT INTO `configuracion_sistema` (`id`, `cantidad_bimestres`, `bimestre_actual`, `anio_escolar`, `modalidad_carga_notas`, `fecha_modificacion`) VALUES
(1, 3, 1, '2026', 'parciales', '2026-04-05 07:51:24');

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

--
-- Volcado de datos para la tabla `cursos_materias`
--

INSERT INTO `cursos_materias` (`id_curso_materia`, `id_curso`, `id_materia`) VALUES
(914, 102, 810),
(921, 103, 810),
(928, 104, 810),
(935, 105, 810),
(941, 106, 813),
(942, 106, 814),
(943, 106, 815),
(944, 106, 816),
(945, 106, 817),
(946, 106, 818),
(947, 106, 819),
(948, 106, 820),
(949, 106, 821),
(956, 107, 813),
(957, 107, 814),
(958, 107, 815),
(959, 107, 816),
(960, 107, 817),
(961, 107, 818),
(962, 107, 819),
(963, 107, 820),
(964, 107, 821),
(971, 108, 813),
(972, 108, 814),
(973, 108, 815),
(974, 108, 816),
(975, 108, 817),
(976, 108, 818),
(977, 108, 819),
(978, 108, 820),
(979, 108, 821),
(986, 109, 813),
(987, 109, 814),
(988, 109, 815),
(989, 109, 816),
(990, 109, 817),
(991, 109, 818),
(992, 109, 819),
(993, 109, 820),
(994, 109, 821),
(1001, 110, 813),
(1002, 110, 814),
(1003, 110, 815),
(1004, 110, 816),
(1005, 110, 817),
(1006, 110, 818),
(1007, 110, 819),
(1008, 110, 820),
(1009, 110, 821),
(1016, 111, 813),
(1017, 111, 814),
(1018, 111, 815),
(1019, 111, 816),
(1020, 111, 817),
(1021, 111, 818),
(1022, 111, 819),
(1023, 111, 820),
(1024, 111, 821),
(1031, 112, 813),
(1032, 112, 814),
(1033, 112, 815),
(1034, 112, 816),
(1035, 112, 817),
(1036, 112, 818),
(1037, 112, 819),
(1038, 112, 820),
(1039, 112, 821),
(1046, 113, 813),
(1047, 113, 814),
(1048, 113, 815),
(1049, 113, 816),
(1050, 113, 817),
(1051, 113, 818),
(1052, 113, 819),
(1053, 113, 820),
(1054, 113, 821),
(1061, 114, 813),
(1062, 114, 814),
(1063, 114, 815),
(1064, 114, 816),
(1065, 114, 817),
(1066, 114, 818),
(1067, 114, 819),
(1068, 114, 820),
(1069, 114, 821),
(1076, 115, 813),
(1077, 115, 814),
(1078, 115, 815),
(1079, 115, 816),
(1080, 115, 817),
(1081, 115, 818),
(1082, 115, 819),
(1083, 115, 820),
(1084, 115, 821),
(1091, 116, 813),
(1092, 116, 814),
(1093, 116, 815),
(1094, 116, 816),
(1095, 116, 817),
(1096, 116, 818),
(1097, 116, 819),
(1098, 116, 820),
(1099, 116, 821),
(1106, 117, 813),
(1107, 117, 814),
(1108, 117, 815),
(1109, 117, 816),
(1110, 117, 817),
(1111, 117, 818),
(1112, 117, 819),
(1113, 117, 820),
(1114, 117, 821),
(1135, 118, 833),
(1136, 118, 834),
(1137, 118, 835),
(1138, 118, 836),
(1139, 118, 837),
(1140, 118, 838),
(1141, 118, 839),
(1142, 118, 840),
(1143, 118, 841),
(1144, 118, 847),
(1145, 118, 848),
(1146, 118, 849),
(1147, 118, 842),
(1148, 118, 843),
(1149, 119, 833),
(1150, 119, 834),
(1151, 119, 835),
(1152, 119, 836),
(1153, 119, 837),
(1154, 119, 838),
(1155, 119, 839),
(1156, 119, 840),
(1157, 119, 841),
(1158, 119, 847),
(1159, 119, 848),
(1160, 119, 849),
(1161, 119, 842),
(1162, 119, 843),
(1163, 120, 850),
(1164, 120, 851),
(1165, 120, 852),
(1166, 120, 853),
(1167, 120, 854),
(1168, 120, 855),
(1169, 120, 856),
(1170, 120, 857),
(1171, 120, 858),
(1172, 120, 859),
(1173, 120, 860),
(1174, 120, 861),
(1175, 120, 862),
(1176, 120, 863),
(1177, 120, 864),
(1178, 121, 850),
(1179, 121, 851),
(1180, 121, 852),
(1181, 121, 853),
(1182, 121, 854),
(1183, 121, 855),
(1184, 121, 856),
(1185, 121, 857),
(1186, 121, 858),
(1187, 121, 859),
(1188, 121, 860),
(1189, 121, 861),
(1190, 121, 862),
(1191, 121, 863),
(1192, 121, 864),
(1193, 120, 865),
(1194, 121, 865),
(1195, 122, 866),
(1196, 122, 867),
(1197, 122, 868),
(1198, 122, 869),
(1199, 122, 870),
(1200, 122, 871),
(1201, 122, 872),
(1202, 122, 873),
(1203, 122, 874),
(1204, 122, 875),
(1205, 122, 876),
(1206, 122, 877),
(1207, 122, 878),
(1208, 122, 879),
(1209, 122, 880),
(1210, 123, 866),
(1211, 123, 867),
(1212, 123, 868),
(1213, 123, 869),
(1214, 123, 870),
(1215, 123, 871),
(1216, 123, 872),
(1217, 123, 873),
(1218, 123, 874),
(1219, 123, 875),
(1220, 123, 876),
(1221, 123, 877),
(1222, 123, 878),
(1223, 123, 879),
(1224, 123, 880),
(1225, 124, 894),
(1226, 124, 895),
(1227, 124, 896),
(1228, 124, 897),
(1229, 124, 898),
(1230, 124, 899),
(1231, 124, 900),
(1232, 124, 888),
(1233, 124, 902),
(1234, 124, 903),
(1235, 124, 904),
(1236, 124, 905),
(1237, 124, 906),
(1238, 125, 894),
(1239, 125, 895),
(1240, 125, 896),
(1241, 125, 897),
(1242, 125, 898),
(1243, 125, 899),
(1244, 125, 900),
(1245, 125, 901),
(1246, 125, 902),
(1247, 125, 903),
(1248, 125, 904),
(1249, 125, 905),
(1250, 125, 906),
(1251, 126, 907),
(1252, 126, 908),
(1253, 126, 909),
(1254, 126, 920),
(1255, 126, 921),
(1256, 126, 910),
(1257, 126, 911),
(1258, 126, 912),
(1259, 126, 913),
(1260, 126, 914),
(1261, 126, 915),
(1262, 126, 916),
(1263, 126, 917),
(1264, 126, 918),
(1265, 126, 919),
(1266, 127, 922),
(1267, 127, 923),
(1268, 127, 924),
(1269, 127, 935),
(1270, 127, 936),
(1271, 127, 925),
(1272, 127, 926),
(1273, 127, 927),
(1274, 127, 928),
(1275, 127, 929),
(1276, 127, 930),
(1277, 127, 931),
(1278, 127, 932),
(1279, 127, 933),
(1280, 127, 934),
(1281, 128, 937),
(1282, 128, 938),
(1283, 128, 939),
(1284, 128, 950),
(1285, 128, 951),
(1286, 128, 940),
(1287, 128, 941),
(1288, 128, 942),
(1289, 128, 943),
(1290, 128, 944),
(1291, 128, 945),
(1292, 128, 946),
(1293, 128, 947),
(1294, 128, 948),
(1295, 128, 949),
(1296, 129, 952),
(1297, 129, 953),
(1298, 129, 954),
(1299, 129, 965),
(1300, 129, 966),
(1301, 129, 955),
(1302, 129, 956),
(1303, 129, 957),
(1304, 129, 958),
(1305, 129, 959),
(1306, 129, 960),
(1307, 129, 961),
(1308, 129, 962),
(1309, 129, 963),
(1310, 129, 964),
(1311, 106, 967),
(1312, 107, 967),
(1313, 108, 967),
(1314, 109, 967),
(1315, 110, 967),
(1316, 111, 967),
(1317, 112, 967),
(1318, 113, 967),
(1319, 114, 967),
(1320, 115, 967),
(1321, 116, 967),
(1322, 117, 967),
(1326, 112, 968),
(1327, 113, 968),
(1328, 114, 968),
(1329, 115, 968),
(1330, 116, 968),
(1331, 117, 968),
(1333, 118, 968),
(1334, 119, 968),
(1335, 120, 968),
(1336, 121, 968),
(1337, 122, 968),
(1338, 123, 968),
(1339, 124, 968),
(1340, 125, 968),
(1341, 126, 968),
(1342, 127, 968),
(1343, 128, 968),
(1344, 129, 968);

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
  `rude` varchar(20) DEFAULT NULL COMMENT 'Registro Único de Estudiante',
  `carnet_identidad` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `id_curso` int(11) DEFAULT NULL COMMENT 'FK al curso en el que está matriculado',
  `id_responsable` int(11) DEFAULT NULL,
  `estado_1` enum('EFECTIVO','NO_EFECTIVO') DEFAULT NULL,
  `estado_2` enum('APROBADO','REPROBADO','NO_INCORPORADO','RETIRO_ABANDONO','RETIRO_TRASLADO') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estudiantes`
--

INSERT INTO `estudiantes` (`id_estudiante`, `nombres`, `apellido_paterno`, `apellido_materno`, `genero`, `rude`, `carnet_identidad`, `fecha_nacimiento`, `id_curso`, `id_responsable`, `estado_1`, `estado_2`) VALUES
(1660, 'YEIMI LUNA', 'AGUILAR', 'SALAZAR', 'Femenino', '4090000520247A', NULL, '2020-03-02', 106, NULL, 'EFECTIVO', NULL),
(1661, 'NICOL', 'ANGULO', 'RUIZ', 'Femenino', '409000052024559', NULL, '2020-03-10', 106, NULL, 'EFECTIVO', NULL),
(1662, 'JOSUE ABDIEL', 'APAZA', 'ALVARADO', 'Masculino', '409000052024384', NULL, '2019-07-15', 106, NULL, 'EFECTIVO', NULL),
(1664, 'ELISEO', 'CHAMBI', 'VICENTE', 'Masculino', '4090000520242762', NULL, '2020-03-08', 106, NULL, 'EFECTIVO', NULL),
(1665, 'ELIAZAR KALEP', 'CHIPANA', 'MAMANI', 'Masculino', '4090000520243640', NULL, '2020-04-29', 106, NULL, 'EFECTIVO', NULL),
(1666, 'DARELL EDRIK', 'CHOQUE', 'CESPEDES', 'Masculino', '4090000520248259', '16581382', '2020-01-04', 107, NULL, 'EFECTIVO', NULL),
(1667, 'MARIO ROMAN', 'CONDORI', 'ROCHA', 'Masculino', '4090000520243349', NULL, '2020-04-13', 106, NULL, 'EFECTIVO', NULL),
(1668, 'BIDANEYRA ANNDY', 'CRUZ', 'GUTIERREZ', 'Femenino', '4090000520245488', NULL, '2019-11-15', 106, NULL, 'EFECTIVO', NULL),
(1670, 'IAN JHAIR', 'GARCIA', 'REINAGA', 'Masculino', '4090000520249896', NULL, '2020-01-04', 106, NULL, 'EFECTIVO', NULL),
(1671, 'ALEX JHUNIOR', 'GUTIERREZ', 'MARTINEZ', 'Masculino', '4090000520244227', NULL, '2019-07-10', 106, NULL, 'EFECTIVO', NULL),
(1673, 'YADIL', 'MAMANI', 'TACURI', 'Masculino', '4090000520248670', NULL, '2019-09-27', 106, NULL, 'EFECTIVO', NULL),
(1674, 'DAYER ALTES', 'MAMANI', 'VILLARROEL', 'Masculino', '409000052024834A', NULL, '2019-11-05', 106, NULL, 'EFECTIVO', NULL),
(1675, 'BRITTANY BRIANA', 'MAMANI', 'GARCIA', 'Femenino', '409000052024741A', NULL, '2019-09-30', 106, NULL, 'EFECTIVO', NULL),
(1676, 'MIA NAYELY', 'MARCA', 'OYARDO', 'Femenino', '4090000520246548', '17307123', '2020-05-26', 107, NULL, 'EFECTIVO', NULL),
(1677, 'JEICOB MARCELINO', 'MARCANI', 'ESCOBAR', 'Masculino', '4090000520247273', NULL, '2020-02-20', 106, NULL, 'EFECTIVO', NULL),
(1678, 'RAZIEL AITANA', 'PALLA', 'CONDORI', 'Femenino', '409000052024497A', NULL, '2020-04-13', 106, NULL, 'EFECTIVO', NULL),
(1679, 'MADILIN ZULEYKA', 'SOLIZ', 'ROCHA', 'Femenino', '4090000520241479', NULL, '2019-09-16', 106, NULL, 'EFECTIVO', NULL),
(1680, 'VALENTINA TATIANA', 'VELIZ', 'AIRA', 'Femenino', '4090000520242607', NULL, '2020-06-03', 106, NULL, 'EFECTIVO', NULL),
(1681, 'TATIANA', 'ZEGARRA', 'PADILLA', 'Femenino', '409000052024306', '16498228', '2020-04-02', 107, NULL, 'EFECTIVO', NULL),
(1682, 'JORGE MISAEL', 'ZURITA', 'VERA', 'Masculino', '4090000520242727', '16419078', '2020-03-11', 106, NULL, 'EFECTIVO', NULL),
(1683, 'ALDAIR VICENTE', 'ALMANZA', 'FLORES', 'Masculino', '4090000520254643', NULL, '2020-01-22', 107, NULL, 'EFECTIVO', NULL),
(1684, 'SEBASTIAN', 'CALI', 'VELIZ', 'Masculino', '4090000520255430', '16211225', '2019-10-21', 106, NULL, 'EFECTIVO', NULL),
(1685, 'BRAYAN', 'CASILLA', 'UGARTE', 'Masculino', '4090000520257865', NULL, '2019-08-22', 107, NULL, 'EFECTIVO', NULL),
(1686, 'ALESSANDRA MARIELA', 'CHAMBILLA', 'LLAVE', 'Femenino', '409000042024431A', NULL, '2020-06-12', 107, NULL, 'EFECTIVO', NULL),
(1687, 'MATIAS', 'CHOQUE', 'MARTINEZ', 'Masculino', '8098051520241485', NULL, '2020-04-14', 107, NULL, 'EFECTIVO', NULL),
(1688, 'JUAN ANDERSON', 'COLQUE', 'FLORES', 'Masculino', '4090000520252436', '16204648', '2019-10-16', 106, NULL, 'EFECTIVO', NULL),
(1689, 'EVELIN', 'FLORES', 'LEON', 'Femenino', '4090000520247490', NULL, '2020-02-27', 107, NULL, 'EFECTIVO', NULL),
(1691, 'IFFET', 'LIMACHI', 'MAMANI', 'Femenino', '4090000520251992', NULL, '2019-12-13', 106, NULL, 'EFECTIVO', NULL),
(1693, 'KAELY JHARIANNÉ', 'LOZA', 'PEREZ', 'Masculino', '4090000520258321', NULL, '2020-05-03', 107, NULL, 'EFECTIVO', NULL),
(1695, 'JHOANA JHOY', 'PADILLA', 'CHAVARRIA', 'Femenino', '4090002720249771', NULL, '2019-09-19', 107, NULL, 'EFECTIVO', NULL),
(1696, 'RONAL MOISES', 'QUISPE', 'MARTINEZ', 'Masculino', '8172004020258047', NULL, '2020-06-13', 107, NULL, 'EFECTIVO', NULL),
(1697, 'THIAGO MANUEL', 'RAMIREZ', 'CAMACHO', 'Masculino', '4090000920246346', NULL, '2020-06-18', 107, NULL, 'EFECTIVO', NULL),
(1698, 'BRITANI', 'REVOLLO', 'CONDORI', 'Femenino', '4090000520257129', NULL, '2019-07-10', 107, NULL, 'EFECTIVO', NULL),
(1699, 'JUAN DANIEL', 'SOLIS', 'CARRILLO', 'Masculino', '8098001720241130', NULL, '2020-04-14', 107, NULL, 'EFECTIVO', NULL),
(1702, 'SALVADOR ESTEBAN', 'ROCHA', 'YANA', 'Masculino', '8090002420236197', NULL, '2019-01-15', 107, NULL, 'EFECTIVO', NULL),
(1703, 'NICOLETTE GLORIA', 'JANCO', 'AYALA', 'Femenino', '409000052023586A', NULL, '2019-01-02', 107, NULL, 'EFECTIVO', NULL),
(1704, 'MIGUEL ANGEL', 'ARIAS', 'VILLARROEL', 'Masculino', '4090002720232786', NULL, '2018-10-02', 108, NULL, 'EFECTIVO', NULL),
(1705, 'DEBRAN', 'CABALLERO', 'SILVA', 'Masculino', '4090000520231656', NULL, '2018-10-13', 108, NULL, 'EFECTIVO', NULL),
(1706, 'CARLOS DILAN', 'CARRILLO', 'QUISPE', 'Masculino', '4090000520235574', NULL, '2018-07-18', 108, NULL, 'EFECTIVO', NULL),
(1707, 'MATIAS EZEQUIEL', 'CARRILLO', 'VILLARROEL', 'Masculino', '4090000520247329', NULL, '2018-08-07', 108, NULL, 'EFECTIVO', NULL),
(1708, 'BRIANA KEILA', 'CARTAGENA', 'MERCADO', 'Femenino', '4090000520237353', NULL, '2019-02-11', 108, NULL, 'EFECTIVO', NULL),
(1709, 'DAVID', 'CORDOVA', 'MOSQUEZ', 'Masculino', '4090000520231542', NULL, '2018-11-29', 108, NULL, 'EFECTIVO', NULL),
(1710, 'FRANCO', 'CRUZ', 'CHAMBI', 'Masculino', '4090000520233276', NULL, '2018-12-14', 108, NULL, 'EFECTIVO', NULL),
(1711, 'BRYANA RAFAELA', 'CRUZ', 'HERNANDEZ', 'Femenino', '4090000520235266', NULL, '2018-09-30', 108, NULL, 'EFECTIVO', NULL),
(1712, 'JOSUE', 'DIAS', 'VARGAS', 'Masculino', '4090000520233578', NULL, '2018-01-14', 108, NULL, 'EFECTIVO', NULL),
(1713, 'PABLO AURELIO', 'FLORES', 'ESCOBAR', 'Masculino', '4090000520241148', NULL, '2018-10-19', 108, NULL, 'EFECTIVO', NULL),
(1714, 'DILAN KAEL', 'FUENTES', 'GASPAR', 'Masculino', '409000062023729A', NULL, '2018-10-25', 108, NULL, 'EFECTIVO', NULL),
(1715, 'ALEXANDER', 'FUENTES', 'VALENCIA', 'Masculino', '8089003520242997', NULL, '2018-11-25', 108, NULL, 'EFECTIVO', NULL),
(1717, 'IBETH', 'MAMANI', 'ORTIZ', 'Femenino', '4090000520239502', NULL, '2019-02-22', 108, NULL, 'EFECTIVO', NULL),
(1718, 'NEIMAR JHOAN', 'MONTECINOS', 'CUBA', 'Masculino', '4090000520239200', NULL, '2018-08-17', 108, NULL, 'EFECTIVO', NULL),
(1719, 'CAMILA', 'ROJAS', 'VILLARROEL', 'Femenino', '4090000520233059', NULL, '2018-09-24', 108, NULL, 'EFECTIVO', NULL),
(1720, 'BRYAN MARIO', 'SOCOMPI', 'ROCHA', 'Masculino', '409000352023364A', NULL, '2019-03-26', 108, NULL, 'EFECTIVO', NULL),
(1721, 'HELEN AYLEN', 'TICONA', 'HINOJOSA', 'Femenino', '8098002020233334', '15416085', '2018-09-17', 108, NULL, 'EFECTIVO', NULL),
(1722, 'JHOANNY CATALINA', 'TORIBIO', 'RIVERA', 'Femenino', '809802112023658A', NULL, '2019-05-05', 108, NULL, 'EFECTIVO', NULL),
(1723, 'YENIFER', 'TORREZ', 'TOAQUE', 'Femenino', '409000052023817A', NULL, '2018-07-26', 108, NULL, 'EFECTIVO', NULL),
(1724, 'CAMILA SHANDIRA', 'VASQUEZ', 'CARRILLO', 'Femenino', '4090000520236657', NULL, '2019-01-07', 108, NULL, 'EFECTIVO', NULL),
(1726, 'DAVID FRANCO', 'VILLARROEL', 'MAMANI', 'Masculino', '4090000520239269', NULL, '2019-01-11', 108, NULL, 'EFECTIVO', NULL),
(1727, 'CARMEN TERESA', 'ALEGRE', 'GONZALES', 'Femenino', '4090000520233185', NULL, '2018-10-18', 109, NULL, 'EFECTIVO', NULL),
(1728, 'JHISSEL ANTHONELA', 'CARATA', 'LAUREANO', 'Femenino', '409000052024566', NULL, '2019-06-12', 109, NULL, 'EFECTIVO', NULL),
(1729, 'SANTIAGO', 'CORDOVA', 'HERBAS', 'Masculino', '4090000520248304', NULL, '2018-10-19', 109, NULL, 'EFECTIVO', NULL),
(1730, 'RUTH NOEMI', 'ESTRADA', 'ESTRADA', 'Femenino', '409000052024567', NULL, '2018-12-27', 109, NULL, 'EFECTIVO', NULL),
(1731, 'NEYMAR', 'GOMEZ', 'CABEROS', 'Masculino', '4090000520257619', NULL, '2019-01-01', 109, NULL, 'EFECTIVO', NULL),
(1732, 'EIDAN RIDER', 'INOCENTE', 'SOTO', 'Masculino', '4090000520249195', NULL, '2019-06-02', 109, NULL, 'EFECTIVO', NULL),
(1734, 'DILAVER', 'LOPEZ', 'VERA', 'Masculino', '8123000720231360', NULL, '2018-07-30', 109, NULL, 'EFECTIVO', NULL),
(1735, 'EYDEN', 'MAMANI', 'MARTINEZ', 'Masculino', '4090000520245990', NULL, '2019-01-30', 109, NULL, 'EFECTIVO', NULL),
(1736, 'RAMIRO', 'MICACIO', 'GERONIMO', 'Masculino', '4090000520247306', NULL, '2018-10-30', 109, NULL, 'EFECTIVO', NULL),
(1737, 'HECTOR JHAIR', 'OTALORA', 'SALAZAR', 'Masculino', '8098031120233266', NULL, '2019-04-18', 109, NULL, 'EFECTIVO', NULL),
(1739, 'DENNIS LEYDI', 'POMA', 'ALBA', 'Femenino', '4090000520248567', NULL, '2018-12-17', 109, NULL, 'EFECTIVO', NULL),
(1740, 'IAN MATIAS', 'QUISPE', 'MAITA', 'Masculino', '4090000920234163', NULL, '2019-02-21', 109, NULL, 'EFECTIVO', NULL),
(1741, 'GUSTAVO', 'QUISPE', 'MARTINEZ', 'Masculino', '4090000520248784', NULL, '2019-01-06', 109, NULL, 'EFECTIVO', NULL),
(1743, 'ABEL', 'ROCHA', 'PEÑA', 'Masculino', '4090000520248920', NULL, '2019-04-17', 109, NULL, 'EFECTIVO', NULL),
(1744, 'MIGUEL ANGEL', 'ROJAS', 'QUECAÑA', 'Masculino', '4090000520241952', NULL, '2018-08-18', 109, NULL, 'EFECTIVO', NULL),
(1745, 'JHON ABDIL', 'SOLIZ', 'CHOQUEHUANCA', 'Masculino', '809805762023602A', NULL, '2018-11-29', 109, NULL, 'EFECTIVO', NULL),
(1746, 'NIJAN JHAMILET', 'VARGAS', 'MONTECINOS', 'Femenino', '4090000520248453', NULL, '2019-02-02', 109, NULL, 'EFECTIVO', NULL),
(1747, 'EDUARS ANDRES', 'VERA', 'CHOQUE', 'Masculino', '4090000520235289', NULL, '2018-12-07', 109, NULL, 'EFECTIVO', NULL),
(1749, 'MELANY ASHLEY', 'ALBA', 'SALVATIERRA', 'Femenino', '4090000520221918', NULL, '2017-08-09', 110, NULL, 'EFECTIVO', NULL),
(1750, 'MEGAN LUANA', 'ALBA', 'VERA', 'Femenino', '8090011620227243', NULL, '2018-03-02', 110, NULL, 'EFECTIVO', NULL),
(1751, 'JHONATAN', 'ARCE', 'CAMPOS', 'Masculino', '8089006020235059', NULL, '2018-05-21', 110, NULL, 'EFECTIVO', NULL),
(1752, 'ABIGAIL MIA', 'AVILA', 'HUARITO', 'Femenino', '4090000520229417', NULL, '2018-02-21', 110, NULL, 'EFECTIVO', NULL),
(1753, 'CRISTAL MISHEL', 'BUSTAMANTE', 'ROCHA', 'Femenino', '8090004020227732', NULL, '2017-08-15', 110, NULL, 'EFECTIVO', NULL),
(1754, 'AARON', 'CHAMBI', 'VICENTE', 'Masculino', '4090000520222432', NULL, '2018-04-04', 110, NULL, 'EFECTIVO', NULL),
(1755, 'NIRA LALE', 'CRUZ', 'CAYARA', 'Femenino', '4090001820234569', NULL, '2018-02-04', 110, NULL, 'EFECTIVO', NULL),
(1756, 'JOSUE ABDIEL', 'EDUARDO', 'MAMANI', 'Masculino', '4090000420229688', NULL, '2018-03-14', 110, NULL, 'EFECTIVO', NULL),
(1757, 'DEAN ADDIEL', 'GALLEGO', 'CHUCA', 'Masculino', '8144010120225050', NULL, '2018-03-12', 110, NULL, 'EFECTIVO', NULL),
(1758, 'ITAMI ANIFER', 'LIMA', 'CONDORI', 'Femenino', '8198067520232565', '17303769', '2018-01-29', 111, NULL, 'EFECTIVO', NULL),
(1759, 'ARLET LIESEL', 'LUQUE', 'CHALAR', 'Femenino', '6090002820225174', NULL, '2017-12-18', 110, NULL, 'EFECTIVO', NULL),
(1760, 'YEICOB DOMINIC', 'MALDONADO', 'ALAVE', 'Masculino', '4090000520227735', NULL, '2018-01-28', 110, NULL, 'EFECTIVO', NULL),
(1761, 'MATHEUS', 'MENESES', 'ACHO', 'Masculino', '4090003520228156', NULL, '2018-02-17', 110, NULL, 'EFECTIVO', NULL),
(1762, 'AYDA', 'MICACIO', 'GERONIMO', 'Femenino', '4090000520225865', NULL, '2016-02-05', 110, NULL, 'EFECTIVO', NULL),
(1763, 'NELSON', 'OLGUIN', 'REJAS', 'Masculino', '4090000520225323', NULL, '2017-07-08', 110, NULL, 'EFECTIVO', NULL),
(1764, 'EZEQUIEL YAIR', 'OTALORA', 'CHOQUE', 'Masculino', '409000052022672', NULL, '2017-10-03', 110, NULL, 'EFECTIVO', NULL),
(1766, 'THIAGO', 'RAMIREZ', 'PEREDO', 'Masculino', '8083003420235664', NULL, '2018-06-15', 110, NULL, 'EFECTIVO', NULL),
(1767, 'SANTIAGO', 'REVOLLO', 'VERA', 'Masculino', '409000052022204A', NULL, '2018-02-27', 110, NULL, 'EFECTIVO', NULL),
(1768, 'JESUS ROLANDO', 'ROCHA', 'ALEGRE', 'Masculino', '4090000520232842', NULL, '2017-08-13', 110, NULL, 'EFECTIVO', NULL),
(1769, 'IAN JHOHAN', 'RODRIGUEZ', 'ALACORE', 'Masculino', '4090000520224017', NULL, '2017-09-07', 110, NULL, 'EFECTIVO', NULL),
(1770, 'LIAM EVANS', 'RODRIGUEZ', 'BEDOYA', 'Masculino', '8098036420236479', NULL, '2017-11-23', 110, NULL, 'EFECTIVO', NULL),
(1771, 'BRANDON EDISON', 'SALAS', 'AGUILAR', 'Masculino', '409000352023408A', NULL, '2018-01-10', 110, NULL, 'EFECTIVO', NULL),
(1772, 'NATHALY ALBA', 'TICONA', 'PORCE', 'Femenino', '4090000520224536', NULL, '2017-08-16', 110, NULL, 'EFECTIVO', NULL),
(1773, 'JHOANA JASMIN', 'VARGAS', 'MORALES', 'Femenino', '4090000420227036', NULL, '2017-10-27', 110, NULL, 'EFECTIVO', NULL),
(1774, 'JAIRON', 'GUTIERREZ', 'HERRERA', 'Masculino', '4090000520224480', NULL, '2017-01-04', 110, NULL, 'EFECTIVO', NULL),
(1775, 'VALENTINA JAZMIN', 'RAMIREZ', 'CAMACHO', 'Femenino', '4090003320216649', NULL, '2017-02-08', 110, NULL, 'EFECTIVO', NULL),
(1776, 'BISMAR', 'ZUÑIGA', 'SOLIZ', 'Masculino', '8089003520224229', NULL, '2017-02-14', 110, NULL, 'EFECTIVO', NULL),
(1799, 'JALDY XANDER', 'BELTRAN', 'HUANCA', 'Masculino', '4090000520239366', NULL, '2017-10-12', 111, NULL, 'EFECTIVO', NULL),
(1800, 'ELIANOR ESCARLET', 'CARATA', 'LAUREANO', 'Femenino', '409000052023281', NULL, '2017-11-08', 111, NULL, 'EFECTIVO', NULL),
(1801, 'MARIA LIZ', 'COPA', 'SOLIZ', 'Femenino', '4090000520235090', NULL, '2018-04-02', 111, NULL, 'EFECTIVO', NULL),
(1802, 'LINETH', 'FATTY', 'VELLIZ', 'Femenino', '40900005202391', NULL, '2018-03-25', 111, NULL, 'EFECTIVO', NULL),
(1803, 'SANTIAGO', 'FERRUFINO', 'CALI', 'Masculino', '409000052022601', NULL, '2017-11-03', 111, NULL, 'EFECTIVO', NULL),
(1805, 'ARIEL GUSTAVO', 'HERRERA', 'CRUZ', 'Masculino', '4090000520221862', NULL, '2018-06-26', 111, NULL, 'EFECTIVO', NULL),
(1807, 'YHULITZA KHATERIN', 'HUIZA', 'ANGULO', 'Femenino', '4090000520221736', NULL, '2018-03-24', 111, NULL, 'EFECTIVO', NULL),
(1808, 'DYLAN NAITAN', 'LAUREANO', 'CABRERA', 'Masculino', '4090000520224633', NULL, '2018-05-13', 111, NULL, 'EFECTIVO', NULL),
(1809, 'BRAYAN EHITAN', 'MENECES', 'QUISPE', 'Masculino', '4090000520221184', NULL, '2016-12-23', 111, NULL, 'EFECTIVO', NULL),
(1810, 'NAYELI', 'NICOLAS', 'RODRIGUEZ', 'Femenino', '4087009720228032', NULL, '2018-03-23', 111, NULL, 'EFECTIVO', NULL),
(1811, 'NICOL', 'REVOLLO', 'CONDORI', 'Femenino', '4090000520239548', NULL, '2017-11-04', 111, NULL, 'EFECTIVO', NULL),
(1812, 'JHOSNEL YONIER', 'RIVERO', 'CHAPPY', 'Masculino', '4090000520231912', NULL, '2018-06-30', 111, NULL, 'EFECTIVO', NULL),
(1813, 'JOSE LUIS', 'SALVATIERRA', 'ROJAS', 'Masculino', '4090000520223236', NULL, '2018-06-05', 111, NULL, 'EFECTIVO', NULL),
(1814, 'PAOLA', 'SOLIZ', 'CONDORI', 'Femenino', '4090000520233065', NULL, '2017-07-29', 111, NULL, 'EFECTIVO', NULL),
(1815, 'MAXIMILIANO', 'UREÑA', 'GUZMAN', 'Masculino', '8090012820223663', NULL, '2018-06-15', 111, NULL, 'EFECTIVO', NULL),
(1816, 'LUIS BRANDON', 'VILLARROEL', 'LIQUEN', 'Masculino', '409000052022503', NULL, '2017-07-05', 111, NULL, 'EFECTIVO', NULL),
(1817, 'MARIAM EYSAN', 'VILLCA', 'ALA', 'Femenino', '409000052023645', NULL, '2018-01-08', 111, NULL, 'EFECTIVO', NULL),
(1818, 'JOSUE BENJAMIN', 'YUCRA', 'MOYA', 'Masculino', '80900113202288A', NULL, '2018-03-02', 111, NULL, 'EFECTIVO', NULL),
(1820, 'MARIA RENE', 'ROMERO', 'ROJAS', 'Femenino', '8098020720215940', NULL, '2017-05-03', 111, NULL, 'EFECTIVO', NULL),
(1821, 'DYLAN RODRIGO', 'GONZALES', NULL, 'Masculino', '409000042021429A', NULL, '2017-02-13', 112, NULL, 'EFECTIVO', NULL),
(1822, 'ANDREA NICOL', 'ANTONIO', 'ROMERO', 'Femenino', '409000062019085', NULL, '2014-04-03', 112, NULL, 'EFECTIVO', NULL),
(1823, 'NAYELI', 'AVILA', 'PADILLA', 'Femenino', '409000052022699A', NULL, '2017-03-23', 112, NULL, 'EFECTIVO', NULL),
(1824, 'MATIAS GERSON', 'CABRERA', 'MONTECINOS', 'Masculino', '4090000520229851', NULL, '2017-04-25', 112, NULL, 'EFECTIVO', NULL),
(1825, 'EMILY LINETH', 'CARRILLO', 'VILLARROEL', 'Femenino', '4090000420217852', NULL, '2017-05-15', 112, NULL, 'EFECTIVO', NULL),
(1826, 'JHEMS ANGEL', 'DELGADILLO', 'TRUJILLO', 'Masculino', '809802112021507A', NULL, '2016-12-30', 112, NULL, 'EFECTIVO', NULL),
(1827, 'DAIANA', 'ESTRADA', 'VENEGAS', 'Femenino', '4090000520222620', NULL, '2016-11-30', 112, NULL, 'EFECTIVO', NULL),
(1828, 'ANGHELO ARIEL', 'FLORES', 'QUIROGA', 'Masculino', '6090003220227484', NULL, '2017-06-14', 112, NULL, 'EFECTIVO', NULL),
(1829, 'HENRY', 'GALINDO', 'MITA', 'Masculino', '4090000520221389', NULL, '2017-04-13', 112, NULL, 'EFECTIVO', NULL),
(1830, 'ADOLFO', 'GARCIA', 'TECILLO', 'Masculino', '4090000520225745', NULL, '2016-07-13', 112, NULL, 'EFECTIVO', NULL),
(1831, 'ELIZABET', 'GUIZADA', 'AVILA', 'Femenino', '4090000520224627', NULL, '2016-11-06', 112, NULL, 'EFECTIVO', NULL),
(1832, 'BENJAMIN', 'LAUREANO', 'CONDORI', 'Masculino', '409000052021236A', NULL, '2016-06-06', 112, NULL, 'EFECTIVO', NULL),
(1833, 'JASMIN CELENA', 'LONASCO', 'RUIZ', 'Femenino', '4090000520226983', NULL, '2017-06-22', 112, NULL, 'EFECTIVO', NULL),
(1834, 'ESTEFAN LUCAS', 'MAMANI', 'MENECES', 'Masculino', '4090003520226987', NULL, '2017-02-12', 112, NULL, 'EFECTIVO', NULL),
(1835, 'BISMAR ANGEL', 'NUÑEZ', 'FUENTES', 'Masculino', '4090003520218846', NULL, '2016-10-22', 112, NULL, 'EFECTIVO', NULL),
(1836, 'VICTORIA', 'SALAZAR', 'LAZARO', 'Femenino', '6090002220216127', NULL, '2016-09-02', 112, NULL, 'EFECTIVO', NULL),
(1837, 'JUAN DIEGO', 'SOCOMPI', 'ROCHA', 'Masculino', '4090003520211667', NULL, '2016-12-05', 112, NULL, 'EFECTIVO', NULL),
(1838, 'XIOMARA NATALIA', 'SOLIZ', 'ROCHA', 'Femenino', '8098011520218549', NULL, '2016-08-09', 112, NULL, 'EFECTIVO', NULL),
(1839, 'JOEL OBERMAIER', 'SOLIZ', 'ROCHA', 'Masculino', '8098011520214118', NULL, '2016-08-09', 112, NULL, 'EFECTIVO', NULL),
(1840, 'LIZBETH GRACIELA', 'TECILLO', 'VALERIANO', 'Femenino', '4090000520228967', NULL, '2016-11-16', 112, NULL, 'EFECTIVO', NULL),
(1841, 'KIMBERLY KAROLAY', 'VILLARROEL', 'LIQUEN', 'Femenino', '809800172020030', NULL, '2016-02-13', 112, NULL, 'EFECTIVO', NULL),
(1842, 'DAINOR', 'VILLARROEL', 'ALMANZA', 'Masculino', '4090000520222598', NULL, '2017-05-12', 112, NULL, 'EFECTIVO', NULL),
(1843, 'JHUDIT MARLLOLY', 'VILLARROEL', 'ESCOBAR', 'Femenino', '4090000520218346', NULL, '2016-05-30', 112, NULL, 'EFECTIVO', NULL),
(1844, 'JHUAN', 'ALVAREZ', 'GONZALES', 'Masculino', '4090000520226892', NULL, '2017-03-01', 113, NULL, 'EFECTIVO', NULL),
(1845, 'JAMIL NEYMAR', 'CABALLERO', 'SILVA', 'Masculino', '4090000420214504', NULL, '2016-08-04', 113, NULL, 'EFECTIVO', NULL),
(1846, 'PABLO EFRAIN', 'CABALLERO', 'AGUILAR', 'Masculino', '8098005220215166', NULL, '2017-03-13', 113, NULL, 'EFECTIVO', NULL),
(1847, 'ANGHELO ALAN', 'CALDERON', 'LOPEZ', 'Masculino', '409000052022290A', NULL, '2014-10-26', 113, NULL, 'EFECTIVO', NULL),
(1848, 'AYLEN AMBAR', 'CHOQUE', 'CESPEDES', 'Femenino', '409000352021903A', NULL, '2016-07-15', 113, NULL, 'EFECTIVO', NULL),
(1849, 'ELENA', 'CHUQUIMIA', 'COPA', 'Femenino', '409000052024436A', NULL, '2015-08-30', 113, NULL, 'EFECTIVO', NULL),
(1850, 'XAVI JUVENAL', 'CORONEL', 'MAMANI', 'Masculino', '408701052021814A', NULL, '2016-12-27', 113, NULL, 'EFECTIVO', NULL),
(1851, 'BRAYAN ALEJANDRO', 'CRUZ', 'HERNANDEZ', 'Masculino', '8098001720214359', NULL, '2016-05-12', 113, NULL, 'EFECTIVO', NULL),
(1852, 'DIEGO OLIVER', 'CUELLAR', 'MICO', 'Masculino', '4090000520225888', NULL, '2017-02-15', 113, NULL, 'EFECTIVO', NULL),
(1854, 'KEVIN JAIRO', 'FUENTES', 'AJATA', 'Masculino', '409000052022744A', NULL, '2016-10-06', 113, NULL, 'EFECTIVO', NULL),
(1856, 'MIGUEL ANGEL', 'MAMANI', 'ALBA', 'Masculino', '4090000520214577', NULL, '2015-12-02', 113, NULL, 'EFECTIVO', NULL),
(1857, 'DAMARIS SARAHI', 'MAMANI', 'JAILLITA', 'Femenino', '4090000520227119', NULL, '2017-05-10', 113, NULL, 'EFECTIVO', NULL),
(1858, 'MELIA ANALY', 'MENECES', 'CARRILLO', 'Femenino', '4090000520217993', NULL, '2016-02-04', 113, NULL, 'EFECTIVO', NULL),
(1859, 'IKER', 'MOLLO', 'CHURQUI', 'Masculino', '8111002020215289', NULL, '2016-12-26', 113, NULL, 'EFECTIVO', NULL),
(1860, 'GUADALUPE MARIETA', 'ROCHA', 'YANA', 'Femenino', '8090002420228432', NULL, '2017-03-24', 113, NULL, 'EFECTIVO', NULL),
(1861, 'MELANI ARIELI', 'RODRIGUEZ', 'MENESES', 'Femenino', '4090000520211349', NULL, '2016-05-07', 113, NULL, 'EFECTIVO', NULL),
(1862, 'ISABEL WARA', 'SOLIZ', 'CHOQUEHUANCA', 'Femenino', '8098057620217006', NULL, '2017-06-05', 113, NULL, 'EFECTIVO', NULL),
(1863, 'DELICIA', 'TINTA', 'VARGAS', 'Femenino', '409000052020015', NULL, '2015-04-20', 113, NULL, 'EFECTIVO', NULL),
(1864, 'JOSE MANUEL', 'TOLA', 'PILLCO', 'Masculino', '4090000520236874', NULL, '2015-12-28', 113, NULL, 'EFECTIVO', NULL),
(1865, 'EMILY BRIANA', 'VERA', 'PADILLA', 'Femenino', '409000352021846A', NULL, '2016-10-27', 113, NULL, 'EFECTIVO', NULL),
(1866, 'DILAN GAEL', 'VERA', 'FUENTES', 'Masculino', '4090003520219718', NULL, '2016-09-15', 113, NULL, 'EFECTIVO', NULL),
(1867, 'DARSON', 'VILLARROEL', 'ALMANZA', 'Masculino', '4090000520221087', NULL, '2017-05-12', 113, NULL, 'EFECTIVO', NULL),
(1868, 'PAMELA', 'ALBA', 'ALMANZA', 'Femenino', '409000052021204', '14834718', '2015-08-07', 114, NULL, 'EFECTIVO', NULL),
(1869, 'JHONATAN', 'CALI', 'ESPINOZA', 'Masculino', '4090000520211868', '16425624', '2016-06-12', 114, NULL, 'EFECTIVO', NULL),
(1870, 'MATEO', 'CHAMBI', 'VICENTE', 'Masculino', '4090000520215546', '16414746', '2016-06-16', 114, NULL, 'EFECTIVO', NULL),
(1872, 'ALEX LEONARDO', 'COPA', 'TITO', 'Masculino', '409000092020036', '15621317', '2016-02-20', 114, NULL, 'EFECTIVO', NULL),
(1873, 'ALEXSANDER', 'CORIA', 'VILLARROEL', 'Masculino', '409000272020030', '16780191', '2015-10-08', 114, NULL, 'EFECTIVO', NULL),
(1874, 'BRITANI ASHLEY', 'CRESPO', 'HERRERA', 'Femenino', '4090000520219042', '16833870', '2016-04-22', 114, NULL, 'EFECTIVO', NULL),
(1875, 'MIKEL', 'ESCALERA', 'TOLA', 'Masculino', '6090001420219425', '15896955', '2015-12-04', 114, NULL, 'EFECTIVO', NULL),
(1877, 'MARIA BELINDA', 'INOCENTE', 'SOTO', 'Femenino', '409000332020038', '15369524', '2014-08-27', 114, NULL, 'EFECTIVO', NULL),
(1878, 'JHORDI', 'LIMA', 'ESPINOZA', 'Masculino', '4090000520212039', '16425649', '2016-02-06', 114, NULL, 'EFECTIVO', NULL),
(1879, 'JHON EDUAR', 'LIMACHI', 'TACURI', 'Masculino', '409000052021577A', '16131886', '2015-10-13', 114, NULL, 'EFECTIVO', NULL),
(1880, 'ARON', 'MAMANI', 'JAILLITA', 'Masculino', '4090000520217451', '15613938', '2015-08-20', 114, NULL, 'EFECTIVO', NULL),
(1883, 'KEYLA', 'MENDOZA', 'MAMANI', 'Femenino', '4090000520217912', '15697059', '2016-02-01', 114, NULL, 'EFECTIVO', NULL),
(1884, 'PAUL', 'MORALES', 'PEÑA', 'Masculino', '4090000520212113', '15767664', '2016-01-07', 114, NULL, 'EFECTIVO', NULL),
(1885, 'AXEL', 'NINAVIA', 'REVOLLO', 'Masculino', '4090000520213020', '16275711', '2016-04-26', 114, NULL, 'EFECTIVO', NULL),
(1886, 'DANA JAZMIN', 'PACHECO', 'MENECES', 'Femenino', '409000312020010', '16831579', '2015-08-12', 114, NULL, 'EFECTIVO', NULL),
(1887, 'ROBERTO OLIVER', 'QUENTA', 'QUENTA', 'Masculino', '4073024920223529', '14675732', '2015-09-06', 114, NULL, 'EFECTIVO', NULL),
(1888, 'DANAHE REBECA', 'QUIROZ', 'MAMANI', 'Femenino', '809801242020006', '15771328', '2015-10-12', 114, NULL, 'EFECTIVO', NULL),
(1889, 'BRIANNA EMILY', 'VELIZ', 'AIRA', 'Femenino', '4090000720216164', '15835657', '2015-10-06', 114, NULL, 'EFECTIVO', NULL),
(1891, 'BRAYAN', 'CHALLAPA', 'PADILLA', 'Masculino', '4090000520217302', '16514007', '2015-07-06', 115, NULL, 'EFECTIVO', NULL),
(1892, 'CRISTIAN', 'CHOQUE', 'MARTINEZ', 'Masculino', '809805152020020', '16248013', '2015-09-02', 115, NULL, 'EFECTIVO', NULL),
(1893, 'ISMAEL', 'COPA', 'SOLIZ', 'Masculino', '4090000520211475', '15611805', '2016-02-07', 115, NULL, 'EFECTIVO', NULL),
(1896, 'JHOJAN JAEL', 'FATTY', 'LAUREANO', 'Masculino', '409000352020900', NULL, '2015-11-21', 115, NULL, 'EFECTIVO', NULL),
(1897, 'YAIR ANDRE', 'FERNANDEZ', 'VILLARROEL', 'Masculino', '809801682020036', '15447721', '2015-08-13', 115, NULL, 'EFECTIVO', NULL),
(1898, 'CINTHIA', 'FUENTES', 'VALENCIA', 'Femenino', '8089003520217634', '15796702', '2016-03-09', 115, NULL, 'EFECTIVO', NULL),
(1900, 'MARISABEL', 'LIMACHI', 'MAMANI', 'Femenino', '4073012620215271', '15956242', '2015-10-14', 114, NULL, 'EFECTIVO', NULL),
(1901, 'ADAIR GAEL', 'MAMANI', 'VILLARROEL', 'Masculino', '409000272020034', '15736172', '2016-05-04', 115, NULL, 'EFECTIVO', NULL),
(1902, 'JHORDDY', 'MAMANI', 'REVOLLO', 'Masculino', '40900005202129A', '16594665', '2015-06-30', 115, NULL, 'EFECTIVO', NULL),
(1903, 'ANDRES', 'MERCADO', 'QUISPE', 'Masculino', '409000042020011', '16325388', '2015-09-03', 115, NULL, 'EFECTIVO', NULL),
(1905, 'KEYLA JANEL', 'OTALORA', 'SALAZAR', 'Femenino', '409000272020056', '16833132', '2016-01-24', 115, NULL, 'EFECTIVO', NULL),
(1906, 'JHEFFERSON YHAIR', 'QUIROZ', 'SOLIZ', 'Masculino', '4090000520214252', '17054936', '2015-12-31', 115, NULL, 'EFECTIVO', NULL),
(1907, 'ERICK', 'REINAGA', 'MONTECINOS', 'Masculino', '4090000520211965', '15992944', '2015-10-01', 115, NULL, 'EFECTIVO', NULL),
(1909, 'VERONICA VALERIA', 'ROMERO', 'ROJAS', 'Femenino', '809802072019008', '16929207', '2015-02-07', 114, NULL, 'EFECTIVO', NULL),
(1910, 'MAYRA YARA', 'SOLIZ', 'CHOQUEHUANCA', 'Femenino', '809805762020019', '15989479', '2015-12-17', 115, NULL, 'EFECTIVO', NULL),
(1911, 'ABRAHAM LUIS', 'TICONA', 'CONDORI', 'Masculino', '409000092018028', '16627228', '2013-09-16', 115, NULL, 'EFECTIVO', NULL),
(1913, 'VALENTINA', 'VIDAURRE', 'VILLCA', 'Femenino', '4090000520217782', '16128118', '2016-06-04', 115, NULL, 'EFECTIVO', NULL),
(1914, 'ANDEL ALEXANDER', 'ZEGARRA', 'PADILLA', 'Masculino', '4090003520207905', '15715575', '2016-05-02', 115, NULL, 'EFECTIVO', NULL),
(1915, 'YANCARLA', 'ALA', 'SOTO', 'Femenino', '409000052020016', '15216381', '2014-12-13', 116, NULL, 'EFECTIVO', NULL),
(1916, 'DELIA', 'BARRETA', 'CRUZ', 'Femenino', '409000052020013', '17012399', '2014-08-17', 116, NULL, 'EFECTIVO', NULL),
(1917, 'ADRIAN SANTIAGO', 'CADENA', 'SARAVIA', 'Masculino', '809804552019009', '14853168', '2015-03-05', 116, NULL, 'EFECTIVO', NULL),
(1918, 'JOEL ESTEBAN', 'CLAROS', 'MARIN', 'Masculino', '409000052020005', '15637428', '2014-11-15', 116, NULL, 'EFECTIVO', NULL),
(1919, 'JHOSEP GAEL', 'ESPINOZA', 'AIRA', 'Masculino', '409000062019013', '14384425', '2014-11-24', 116, NULL, 'EFECTIVO', NULL),
(1920, 'YURI', 'ESPINOZA', 'AGUILAR', 'Masculino', '409000052020008', '15715404', '2014-07-15', 116, NULL, 'EFECTIVO', NULL),
(1921, 'ABIGAIL', 'ESTRADA', 'VENEGAS', 'Femenino', '409000052020017', '15182435', '2014-12-03', 116, NULL, 'EFECTIVO', NULL),
(1922, 'ALEXANDER', 'FATTY', 'TOCO', 'Masculino', '409000052020006', '14121616', '2014-10-12', 116, NULL, 'EFECTIVO', NULL),
(1923, 'LUZ JHENNY', 'LLAMPA', 'BORDA', 'Femenino', '609000052019046', '16935862', '2014-12-08', 116, NULL, 'EFECTIVO', NULL),
(1925, 'JUAN MARCOS', 'MENDOZA', 'MAMANI', 'Masculino', '808900202020057', '15697124', '2014-08-03', 116, NULL, 'EFECTIVO', NULL),
(1926, 'LISBETH', 'OLGUIN', 'REJAS', 'Femenino', '409000052020014', '14817692', '2014-11-19', 116, NULL, 'EFECTIVO', NULL),
(1927, 'LUCIANA YANDI', 'OTALORA', 'SALAZAR', 'Femenino', '409000272019011', '16833157', '2014-09-08', 116, NULL, 'EFECTIVO', NULL),
(1929, 'JHON NEYMAR', 'POMA', 'CONDORI', 'Masculino', '409000052020009', '15630971', '2014-11-09', 116, NULL, 'EFECTIVO', NULL),
(1930, 'AVIMAEL', 'ROCHA', 'ESCOBAR', 'Masculino', '409000052020019', '16641122', '2014-11-05', 116, NULL, 'EFECTIVO', NULL),
(1931, 'ELISA', 'RODRIGUEZ', 'PANIAGUA', 'Femenino', '409000062019026', '15478895', '2014-09-13', 116, NULL, 'EFECTIVO', NULL),
(1932, 'IKER ROMEL', 'SOLIZ', 'CHOQUEHUANCA', 'Masculino', '809805762019026', '15989478', '2014-08-10', 116, NULL, 'EFECTIVO', NULL),
(1933, 'PAOLA ALEJANDRA', 'VARGAS', 'MONTECINOS', 'Femenino', '409000052020011', '14512973', '2015-01-16', 116, NULL, 'EFECTIVO', NULL),
(1934, 'ALEXIS', 'VERA', 'PADILLA', 'Masculino', '4090003520205084', '16570470', '2015-05-08', 116, NULL, 'EFECTIVO', NULL),
(1935, 'AYELEN JHULIET', 'VILLARROEL', 'CESPEDES', 'Femenino', '809000442020004', '16575491', '2015-02-25', 116, NULL, 'EFECTIVO', NULL),
(1936, 'JHEYSON', 'VIZALLA', 'PUMA', 'Masculino', '409000052020001', '16833238', '2015-04-21', 116, NULL, 'EFECTIVO', NULL),
(1937, 'CELESTE', 'ESCOBAR', 'ZURIA', 'Femenino', '409000052019025', '16888911', '2014-01-22', 117, NULL, 'EFECTIVO', NULL),
(1938, 'MIJAEL LEONARDO', 'CACERES', 'YANARI', 'Masculino', '809800522020025', '16409839', '2015-06-16', 117, NULL, 'EFECTIVO', NULL),
(1939, 'CRISTIAN', 'CASILLA', 'UGARTE', 'Masculino', '809802352020108', '15720055', '2015-06-03', 117, NULL, 'EFECTIVO', NULL),
(1940, 'EGBERTO GROVER', 'CHIPANA', 'MAMANI', 'Masculino', '409000042019055', '16195113', '2014-08-31', 117, NULL, 'EFECTIVO', NULL),
(1941, 'JHILMAR SLIMDER', 'CUELLAR', 'MICO', 'Masculino', '409000052020027', '16214745', '2015-01-05', 117, NULL, 'EFECTIVO', NULL),
(1942, 'MICAELA', 'GUTIERREZ', 'MARTINEZ', 'Femenino', '814801592019009', '15429081', '2014-08-05', 117, NULL, 'EFECTIVO', NULL),
(1943, 'ANAHI SELENA', 'GUTIERREZ', 'HERRERA', 'Femenino', '409000052020028', '16833854', '2014-11-22', 117, NULL, 'EFECTIVO', NULL),
(1944, 'CAMILA ALEJANDRA', 'GUZMAN', 'POMA', 'Femenino', '819805022019991A', '17586050', '2014-09-04', 117, NULL, 'EFECTIVO', NULL),
(1945, 'CAMILA SAORI', 'GUZMAN', 'POMA', 'Femenino', '8198050220192166', '17586051', '2014-09-04', 117, NULL, 'EFECTIVO', NULL),
(1946, 'LUIS MIGUEL', 'MAMANI', 'ORTIZ', 'Masculino', '4090003520201319', '15616928', '2015-05-19', 117, NULL, 'EFECTIVO', NULL),
(1947, 'THIAGO JULIAN', 'MEDRANO', 'RODRIGUEZ', 'Masculino', '809800452019114', '14700440', '2014-12-26', 117, NULL, 'EFECTIVO', NULL),
(1948, 'ALINA', 'MERIDA', 'QUIROZ', 'Femenino', '808600752020046', '15929303', '2015-06-18', 117, NULL, 'EFECTIVO', NULL),
(1949, 'MARJHOLI', 'PEDRO', 'HERRERA', 'Femenino', '409000052020030', '16126447', '2015-02-06', 117, NULL, 'EFECTIVO', NULL),
(1950, 'LIA MELANY', 'PEREZ', 'ALACORE', 'Femenino', '409000052020010', '15608455', '2015-06-28', 117, NULL, 'EFECTIVO', NULL),
(1951, 'EMILY DANIELA', 'QUISPE', 'QUIROZ', 'Femenino', '409000042019038', '16516562', '2015-06-06', 117, NULL, 'EFECTIVO', NULL),
(1952, 'JENNIFER', 'RAMIREZ', 'PEREDO', 'Femenino', '409000052020012', '16527835', '2014-11-09', 116, NULL, 'EFECTIVO', NULL),
(1953, 'JHOEL', 'ROJAS', 'QUECAÑA', 'Masculino', '409000052020024', '16833574', '2015-05-06', 117, NULL, 'EFECTIVO', NULL),
(1954, 'VICTOR ALEJANDRO', 'SANDOVAL', 'VILLARROEL', 'Masculino', '409000062019031', '14735074', '2014-09-08', 117, NULL, 'EFECTIVO', NULL),
(1955, 'JHESSICA', 'TOLA', 'PILLCO', 'Femenino', '409000052020025', '17256755', '2014-07-12', 117, NULL, 'EFECTIVO', NULL),
(1956, 'ALEX', 'VARGAS', 'TOMAS', 'Masculino', '709000762020029', '16700109', '2014-08-09', 117, NULL, 'EFECTIVO', NULL),
(1957, 'AINHOA VALENTINA', 'ZURITA', 'VERA', 'Femenino', '409000272019017', '15068529', '2014-12-20', 117, NULL, 'EFECTIVO', NULL),
(1958, 'JOSE LUIS', 'VERA', 'FUENTES', 'Masculino', '409000052019051', '15839223', '2014-05-11', 117, NULL, 'EFECTIVO', NULL),
(1959, 'LIZETH', 'CALI', 'VELIZ', 'Femenino', '409000052019009', '15193840', '2013-09-24', 117, NULL, 'EFECTIVO', NULL),
(1961, 'SAUL', 'APAZA', 'MAMANI', 'Masculino', '409000052019040', '15698447', '2014-02-22', 118, NULL, 'EFECTIVO', NULL),
(1962, 'SAMUEL', 'APAZA', 'MAMANI', 'Masculino', '409000052019021', '15698455', '2014-02-22', 118, NULL, 'EFECTIVO', NULL),
(1963, 'NEYMAR', 'ARCE', 'GUTIERREZ', 'Masculino', '409000062019087', '13231087', '2013-11-18', 118, NULL, 'EFECTIVO', NULL),
(1964, 'JOSE ANGEL', 'AVILA', 'PADILLA', 'Masculino', '409000052018062', '17526206', '2013-01-08', 119, NULL, 'EFECTIVO', NULL),
(1965, 'DANIELA', 'CARRILLO', 'PADILLA', 'Femenino', '409000052019012', '17742964', '2013-11-13', 118, NULL, 'EFECTIVO', NULL),
(1967, 'BEIMAR', 'COCA', 'CALLAHUARA', 'Masculino', '409000052019033', '15726564', '2014-03-25', 118, NULL, 'EFECTIVO', NULL),
(1968, 'RODRIGO', 'COTARI', 'JIMENEZ', 'Masculino', '409000052019018', '16495938', '2013-08-26', 119, NULL, 'EFECTIVO', NULL),
(1970, 'JOSE LUIS', 'FATTY', 'NICASIO', 'Masculino', '409000052019039', '15589685', '2014-03-07', 118, NULL, 'EFECTIVO', NULL),
(1971, 'SAMEER CRISTOFER', 'GARCIA', 'CHOQUE', 'Masculino', '609500202018073', '15028080', '2013-09-11', 119, NULL, 'EFECTIVO', NULL),
(1972, 'JESSICA', 'HERRERA', 'OYARDO', 'Femenino', '806100362017003', '15666854', '2011-12-25', 119, NULL, 'EFECTIVO', NULL),
(1974, 'MARCO', 'HUANCA', 'CRESPO', 'Masculino', '809000062019071', '16092353', '2014-02-19', 118, NULL, 'EFECTIVO', NULL),
(1975, 'JUAN ABAD', 'INOCENTE', 'CUISARA', 'Masculino', '409000052019032', '15646918', '2014-01-10', 118, NULL, 'EFECTIVO', NULL),
(1976, 'LEONIDAS SANTIAGO', 'JANCO', 'AYALA', 'Masculino', '812302032017045', '15382796', '2013-06-12', 119, NULL, 'EFECTIVO', NULL),
(1977, 'JOSUE', 'LIMA', 'ESPINOZA', 'Masculino', '409000052019023', '16425619', '2014-03-04', 118, NULL, 'EFECTIVO', NULL),
(1978, 'FABIAN', 'LIMACHI', 'MAMANI', 'Masculino', '407301262019042', '15956159', '2013-09-30', 118, NULL, 'EFECTIVO', NULL),
(1979, 'DILMAR EDUARDO', 'LONASCO', 'RUIZ', 'Masculino', '409000052019044', '15705362', '2014-03-23', 118, NULL, 'EFECTIVO', NULL),
(1980, 'DEIMAR', 'LOZA', 'MEDRANO', 'Masculino', '409000042018028', '15798323', '2014-03-21', 119, NULL, 'EFECTIVO', NULL),
(1982, 'SHEYLA GRACIELA', 'MERINO', 'PANAMA', 'Femenino', '409000042018010', '17021603', '2013-10-20', 119, NULL, 'EFECTIVO', NULL),
(1983, 'DANIEL', 'PAREDES', 'LIQUEN', 'Masculino', '409000052018001', '16767693', '2011-08-08', 119, NULL, 'EFECTIVO', NULL),
(1984, 'DILAN NEYDER', 'PEÑA', 'MENESES', 'Masculino', '409000052018034', '15799003', '2013-01-09', 118, NULL, 'EFECTIVO', NULL),
(1985, 'BELINDA', 'PEREZ', 'TOAQUE', 'Femenino', '409000052019056', '14850766', '2014-05-27', 118, NULL, 'EFECTIVO', NULL),
(1986, 'JHENIFER ESDENKA', 'QUENTA', 'QUENTA', 'Femenino', '809802132019089', '13844241', '2013-10-05', 118, NULL, 'EFECTIVO', NULL),
(1987, 'NEYMAR', 'RAMIREZ', 'LEON', 'Masculino', '409000052019027', '15589067', '2014-03-27', 118, NULL, 'EFECTIVO', NULL),
(1988, 'JHISSELA', 'ROJAS', 'ROJAS', 'Femenino', '809804012019017', '15530764', '2014-06-01', 118, NULL, 'EFECTIVO', NULL),
(1989, 'DAYANA BELEN', 'ROJAS', 'QUECAÑA', 'Femenino', '409000052019035', '16833601', '2013-12-03', 119, NULL, 'EFECTIVO', NULL),
(1991, 'JOEL JHASMANI', 'VERA', 'ROCHA', 'Masculino', '409000052019015', '15834516', '2013-10-23', 118, NULL, 'EFECTIVO', NULL),
(1992, 'MAURICIO JAVIER', 'QUIROZ', 'SOLIZ', 'Masculino', '409000052016016', '16915634', '2011-03-20', 119, NULL, 'EFECTIVO', NULL),
(1996, 'AYLIN', 'BRAVO', 'AVALOS', 'Femenino', '409000062018066', '15546996', '2014-04-19', 119, NULL, 'EFECTIVO', NULL),
(1998, 'JOSE LUIS', 'CASILLA', 'UGARTE', 'Masculino', '809802352017113', '15719970', '2013-01-25', 119, NULL, 'EFECTIVO', NULL),
(1999, 'ANDREA SOFIA', 'CASTELLON', 'RIVERA', 'Femenino', '6090005720195831', '16892612', '2013-11-10', 119, NULL, 'EFECTIVO', NULL),
(2000, 'ROY NELSON', 'CHIPATA', 'CABEZAS', 'Masculino', '409000042019011', '14195509', '2013-10-21', 119, NULL, 'EFECTIVO', NULL),
(2001, 'JOSE ARIEL', 'CONDORI', 'ROCHA', 'Masculino', '706400012019081', '17000155', '2013-11-07', 118, NULL, 'EFECTIVO', NULL),
(2002, 'MATHIAS', 'FLORES', 'HERRERA', 'Masculino', '809801072019006', '16323337', '2014-01-30', 119, NULL, 'EFECTIVO', NULL),
(2003, 'LUZ CLARITA', 'GARCIA', 'PADILLA', 'Femenino', '409000052019050', '17524677', '2014-06-01', 118, NULL, 'EFECTIVO', NULL),
(2004, 'WILBER', 'GARCIA', 'TECILLO', 'Masculino', '409000052019054', '16016651', '2014-02-19', 119, NULL, 'EFECTIVO', NULL),
(2005, 'ROSELINDA', 'GOMEZ', 'CABEROS', 'Femenino', '409000052020032', '13588764', '2014-06-09', 119, NULL, 'EFECTIVO', NULL),
(2006, 'JOSE LUIS', 'GUARAYO', 'HUMEREZ', 'Masculino', '409000052018014', '16249655', '2013-06-13', 119, NULL, 'EFECTIVO', NULL),
(2007, 'DAYANA', 'GUTIERREZ', 'MAQUERA', 'Femenino', '407304572018027', '15220897', '2014-02-14', 119, NULL, 'EFECTIVO', NULL),
(2008, 'THIAGO YAIR', 'HUARACHI', 'APAZA', 'Masculino', '809800682018073', '16010652', '2013-08-19', 119, NULL, 'EFECTIVO', NULL),
(2010, 'DAVID DEYMAR', 'MAMANI', 'CHOQUE', 'Masculino', '809802892018034', '16087243', '2013-11-20', 119, NULL, 'EFECTIVO', NULL),
(2012, 'JHONN JAIRO', 'MENDOZA', 'VERA', 'Masculino', '409000042018015', '14090279', '2014-01-27', 119, NULL, 'EFECTIVO', NULL),
(2013, 'LUCIANA', 'MENESES', 'ROJAS', 'Femenino', '409000252018002', '17030472', '2013-09-19', 118, NULL, 'EFECTIVO', NULL),
(2014, 'DELIA', 'MICACIO', 'GERONIMO', 'Femenino', '409000052018018', '15609305', '2013-06-18', 118, NULL, 'EFECTIVO', NULL),
(2015, 'BRIANA KATERIN', 'NUÑEZ', 'FUENTES', 'Femenino', '809802352018041', '16409004', '2014-03-12', 119, NULL, 'EFECTIVO', NULL),
(2016, 'KEYLA MARIANA', 'OROSCO', 'ROCHA', 'Femenino', '409000272018014', '14094575', '2014-01-21', 118, NULL, 'EFECTIVO', NULL),
(2017, 'THIAGO MIJAHIL', 'QUISPE', 'MAITA', 'Masculino', '409000092018068', '15598616', '2013-11-26', 118, NULL, 'EFECTIVO', NULL),
(2018, 'GRICEL', 'ROCHA', 'PEÑA', 'Femenino', '409000052019045', '15644288', '2013-10-24', 119, NULL, 'EFECTIVO', NULL),
(2019, 'DAMARIS DIANA', 'SALVATIERRA', 'ACHO', 'Femenino', '409000052019006', '15783906', '2014-06-19', 119, NULL, 'EFECTIVO', NULL),
(2021, 'VICTORIA', 'ULUNQUE', 'SALVATIERRA', 'Femenino', '409000062018023', '16834116', '2013-07-18', 119, NULL, 'EFECTIVO', NULL),
(2022, 'BELEN', 'VIZALLA', 'PUMA', 'Femenino', '409000052019031', '15129374', '2013-03-12', 119, NULL, 'EFECTIVO', NULL),
(2023, 'ELVIS', 'ZUÑIGA', 'SOLIZ', 'Masculino', '708700992019051', NULL, '2014-04-09', 118, NULL, 'EFECTIVO', NULL),
(2024, 'JHON ARLIN', 'ALANOCA', 'LAZO', 'Masculino', '407302632017061', '14884367', '2013-03-22', 119, NULL, 'EFECTIVO', NULL),
(2027, 'LUIS FABIAN', 'LIMACHI', 'TACURI', 'Masculino', '409000052018009', '14266313', '2013-06-30', 118, NULL, 'EFECTIVO', NULL),
(2028, 'JHONATHAN ALEXANDER', 'SALAS', 'AGUILAR', 'Masculino', '409000052018054', '16304271', '2013-02-20', 119, NULL, 'EFECTIVO', NULL),
(2029, 'MARY CARMEN', 'VELA', 'BATALLANOS', 'Femenino', '409000042017045', '16048778', '2012-07-07', 119, NULL, 'EFECTIVO', NULL),
(2030, 'BORIS', 'ACHU', 'LAZARO', 'Masculino', '409000052017030', '15689708', '2011-06-07', 120, NULL, 'EFECTIVO', ''),
(2031, 'NOELIA NATALY', 'ALVARADO', 'LAUREANO', 'Femenino', '409000052018040', '12623437', '2012-10-22', 120, NULL, 'EFECTIVO', ''),
(2032, 'JUAN', 'ALVAREZ', 'GONZALES', 'Masculino', '409000052017022', '14829206', '2012-04-10', 120, NULL, 'EFECTIVO', ''),
(2033, 'DAVID', 'ARCE', 'CAMPOS', 'Masculino', '808900602018013', '15202034', '2013-03-11', 120, NULL, 'EFECTIVO', ''),
(2034, 'MATEO AGUSTIN', 'BELTRAN', 'HUANCA', 'Masculino', '809800832018005', '16244107', '2013-03-21', 120, NULL, 'EFECTIVO', ''),
(2035, 'PEDRO PABLO', 'CALIZAYA', 'SALVATIERRA', 'Masculino', '409000052018015', '16179792', '2012-09-22', 120, NULL, 'EFECTIVO', ''),
(2036, 'KEVIN', 'CHOQUE', 'ROJAS', 'Masculino', '809804662015107', '13099467', '2011-02-17', 120, NULL, 'EFECTIVO', ''),
(2037, 'JHISU JHASMIN', 'CHOQUEHUANCA', 'VASQUEZ', 'Femenino', '509000042018009', '14235744', '2013-01-19', 120, NULL, 'EFECTIVO', ''),
(2038, 'DANIEL', 'COPA', 'SOLIZ', 'Masculino', '409000052018063', '14833723', '2012-10-05', 120, NULL, 'EFECTIVO', ''),
(2039, 'MILER', 'CRESPO', 'HERRERA', 'Masculino', '409000052018057', '16833844', '2013-04-24', 120, NULL, 'EFECTIVO', ''),
(2040, 'JADE', 'ESPINOZA', 'AGUILAR', 'Femenino', '409000052018038', '15587751', '2012-07-25', 120, NULL, 'EFECTIVO', ''),
(2041, 'MAGDYEL GABRIELA', 'FERRUFINO', 'MAIZO', 'Femenino', '409000052018035', '15364524', '2013-02-25', 120, NULL, 'EFECTIVO', ''),
(2042, 'DAVID ELOY', 'GUTIERREZ', 'MARTINEZ', 'Masculino', '814800472017050', '14755413', '2012-11-25', 120, NULL, 'EFECTIVO', ''),
(2043, 'JHASMIN', 'JANKORI', 'DELGADILLO', 'Femenino', '409000052019003', '16249468', '2013-02-10', 120, NULL, 'EFECTIVO', ''),
(2044, 'NEIMAR', 'JORA', 'VENEGAS', 'Masculino', '409000052018046', '14856182', '2012-12-27', 120, NULL, 'EFECTIVO', ''),
(2045, 'JAZEL SOFFI', 'LUQUE', 'CHALAR', 'Femenino', '609000282017056', '13622936', '2013-04-25', 120, NULL, 'EFECTIVO', ''),
(2046, 'FERNANDO', 'MAMANI', 'PACO', 'Masculino', '409000052018037', '15718323', '2012-11-23', 120, NULL, 'EFECTIVO', ''),
(2047, 'NOEMI', 'OLGUIN', 'REJAS', 'Femenino', '409000052018047', '13347219', '2012-09-30', 120, NULL, 'EFECTIVO', ''),
(2048, 'EDUARD DILAN', 'OTALORA', 'CHOQUE', 'Masculino', '409000052018056', '15409147', '2013-04-24', 120, NULL, 'EFECTIVO', ''),
(2049, 'JUAN RAMIRO', 'PADILLA', 'ROSAS', 'Masculino', '409000052018053', '12776017', '2012-07-17', 120, NULL, 'EFECTIVO', ''),
(2050, 'ANDERSON', 'PEDRO', 'HERRERA', 'Masculino', '409000052016029', '13825936', '2011-04-14', 120, NULL, 'EFECTIVO', ''),
(2052, 'AYLEN MARIA', 'ROCHA', 'AYALA', 'Femenino', '409000052018039', '12873609', '2012-08-06', 120, NULL, 'EFECTIVO', ''),
(2053, 'ALEJANDRA', 'ROCHA', 'PEÑA', 'Femenino', '409000052016033', '15079922', '2010-12-08', 120, NULL, 'EFECTIVO', ''),
(2054, 'JHOVANA', 'SALVATIERRA', 'ROJAS', 'Femenino', '409000052018032', '16628648', '2012-11-08', 120, NULL, 'EFECTIVO', ''),
(2055, 'DEYSI', 'SANGRERI', 'MARCANI', 'Femenino', '409000052018044', '14831262', '2013-05-27', 120, NULL, 'EFECTIVO', ''),
(2056, 'JHEXON', 'SOLIZ', 'CONDORI', 'Masculino', '409000052018045', '15718356', '2012-11-19', 120, NULL, 'EFECTIVO', ''),
(2057, 'YASMANI', 'TINTA', 'VARGAS', 'Masculino', '409000052017027', '13478163', '2011-12-05', 120, NULL, 'EFECTIVO', ''),
(2058, 'CRISTIAN', 'TOLA', 'PILLCO', 'Masculino', '409000052019014', '17256724', '2012-10-31', 120, NULL, 'EFECTIVO', ''),
(2059, 'GABRIEL', 'CALI', 'ESPINOZA', 'Masculino', '409000052017045', '14834062', '2012-06-18', 120, NULL, 'EFECTIVO', 'REPROBADO'),
(2060, 'FRANKLIN', 'GUARAYO', 'HUMEREZ', 'Masculino', '409000052017017', '17031913', '2011-12-06', 120, NULL, 'EFECTIVO', 'REPROBADO'),
(2063, 'MARIA ISABEL', 'ESTRADA', NULL, 'Femenino', '409000052018016', '16832896', '2013-01-31', 121, NULL, 'EFECTIVO', ''),
(2064, 'JHON ISRAEL', 'BUSTOS', 'MARTINEZ', 'Masculino', '409000302017025', '13588169', '2012-07-08', 121, NULL, 'EFECTIVO', ''),
(2065, 'EVELIZ', 'CATUNTA', 'LAUREANO', 'Femenino', '409000052018031', '16833700', '2012-07-14', 121, NULL, 'EFECTIVO', ''),
(2066, 'ISAI JOAS', 'CHOQUE', 'ESCOBAR', 'Masculino', '40900005201477', '16219338', '2009-02-27', 121, NULL, 'EFECTIVO', ''),
(2067, 'SULEYDA', 'COCA', 'CALLAHUARA', 'Femenino', '409000302017027', '15726485', '2012-07-04', 121, NULL, 'EFECTIVO', ''),
(2068, 'BELINDA JARUSI', 'COCA', 'SANDOVAL', 'Femenino', '409000052018017', '15727470', '2013-03-24', 121, NULL, 'EFECTIVO', ''),
(2069, 'DAVID', 'CONDORI', 'SANCARI', 'Masculino', '409000052018055', '15997302', '2012-12-15', 121, NULL, 'EFECTIVO', ''),
(2070, 'KIMBERLY CELESTE', 'CORONEL', 'MAMANI', 'Femenino', '408701052017004', '16309764', '2013-03-24', 121, NULL, 'EFECTIVO', ''),
(2071, 'CRISTIAN', 'CUYO', 'TAQUICHIRI', 'Masculino', '814300822017005', '15146051', '2012-09-20', 121, NULL, 'EFECTIVO', ''),
(2072, 'ARIANA', 'DELGADILLO', 'TRUJILLO', 'Femenino', '608700322017008', '14648420', '2013-01-14', 121, NULL, 'EFECTIVO', ''),
(2073, 'OSMAR LEANDRO', 'FERRUFINO', 'LEDEZMA', 'Masculino', '409000272017531A', '14603935', '2012-11-14', 121, NULL, 'EFECTIVO', ''),
(2074, 'MAGDIEL NAYELI', 'FUENTES', 'AJATA', 'Femenino', '409000062017026', '16833082', '2013-01-16', 121, NULL, 'EFECTIVO', ''),
(2075, 'JOSE ELIO', 'GARCIA', 'FERNANDEZ', 'Masculino', '509000492017010', '14983126', '2012-03-12', 121, NULL, 'EFECTIVO', ''),
(2076, 'KEVIN RAMIRO', 'GONZALES', 'SORIA', 'Masculino', '409000042017073', '14193374', '2012-07-28', 121, NULL, 'EFECTIVO', ''),
(2077, 'GROVER', 'INOCENTE', 'COCA', 'Masculino', '409000052018023', '16235387', '2012-10-22', 121, NULL, 'EFECTIVO', ''),
(2078, 'BELINDA', 'IQUISE', 'MAMANI', 'Femenino', '409000052019013', '17677586', '2011-12-27', 121, NULL, 'EFECTIVO', ''),
(2080, 'YHEYSON', 'NOGALES', 'MORALES', 'Masculino', '409000052018050', '14829288', '2012-12-16', 121, NULL, 'EFECTIVO', ''),
(2082, 'EMILY KIARA', 'ORELLANA', 'VERA', 'Femenino', '409000272017040', '15798207', '2012-07-28', 121, NULL, 'EFECTIVO', ''),
(2083, 'ALEJANDRA', 'PAI', 'CONDORI', 'Femenino', '409000052018012', '13420510', '2013-03-13', 121, NULL, 'EFECTIVO', ''),
(2084, 'ISAYANA', 'ROJAS', 'ESPINOZA', 'Femenino', '706800562017013', '13960929', '2013-05-12', 121, NULL, 'EFECTIVO', ''),
(2085, 'ARIANA STEFANY', 'TICONA', 'PORCE', 'Femenino', '809804772017025', '15003838', '2012-07-02', 121, NULL, 'EFECTIVO', ''),
(2086, 'MOISES PERCY', 'VARGAS', 'CARRILLO', 'Masculino', '409000052017055', '16339806', '2011-10-27', 121, NULL, 'EFECTIVO', ''),
(2087, 'MARIA LUZ', 'VARGAS', 'TOMAS', 'Femenino', '7090007620173564', '16148058', '2012-12-02', 121, NULL, 'EFECTIVO', ''),
(2088, 'JASMIN PILAR', 'VILLARROEL', 'ALMANZA', 'Femenino', '409000052018028', '15697992', '2012-07-11', 121, NULL, 'EFECTIVO', ''),
(2091, 'JHON', 'REVOLLO', 'CONDORI', 'Masculino', '409000052016002', '14515875', '2011-05-25', 120, NULL, 'EFECTIVO', 'REPROBADO'),
(2093, 'ANEIDA', 'VILLARROEL', NULL, 'Femenino', '409000052017052', '15940930', '2011-09-15', 122, NULL, 'EFECTIVO', NULL),
(2094, 'GABRIEL GROVER', 'VALVERDE', NULL, 'Masculino', '409000052017039', '16715127', '2011-10-08', 123, NULL, 'EFECTIVO', NULL),
(2095, 'ANAHI', 'CUSI', NULL, 'Femenino', '409000052017064', '15084476', '2011-06-16', 122, NULL, 'EFECTIVO', NULL),
(2096, 'JHAMIR JOSE', 'ALMANZA', 'HERRERA', 'Masculino', '4090001820186407', '16328830', '2011-07-15', 123, NULL, 'EFECTIVO', NULL),
(2097, 'DANA MAGDIEL', 'CABALLERO', 'SILVA', 'Femenino', '406100262016002', '14625335', '2012-03-05', 122, NULL, 'EFECTIVO', ''),
(2098, 'SOLEDAD', 'CLAROS', 'MARIN', 'Femenino', '409000062016033', '15430411', '2012-01-11', 122, NULL, 'EFECTIVO', ''),
(2099, 'FABIOLA', 'ESPINOZA', 'RIVAS', 'Femenino', '409000052017011', '14735423', '2012-04-06', 122, NULL, 'EFECTIVO', ''),
(2100, 'JUAN DAVID', 'FATTY', 'VELLIZ', 'Masculino', '409000052017019', '12491618', '2011-07-29', 122, NULL, 'EFECTIVO', ''),
(2101, 'CRISTIAN', 'FATTY', 'TOCO', 'Masculino', '409000052017021', '13067115', '2012-06-17', 122, NULL, 'EFECTIVO', ''),
(2102, 'GUIDO ALICIO', 'FERRUFINO', 'CASTRO', 'Masculino', '409000052017034', '14268051', '2012-04-24', 122, NULL, 'EFECTIVO', ''),
(2103, 'ANGELICA BRIGITH', 'FLORES', 'CHURA', 'Femenino', '804802912017003', '15621712', '2011-08-05', 122, NULL, 'EFECTIVO', ''),
(2104, 'NOELIA', 'FUENTES', 'VALENCIA', 'Femenino', '808900352017006', '17515236', '2011-12-24', 122, NULL, 'EFECTIVO', ''),
(2105, 'JHOELMA ESMERALDA', 'JANCO', 'AYALA', 'Femenino', '812302032018002', '15382750', '2011-09-02', 122, NULL, 'EFECTIVO', ''),
(2107, 'ANAHI PRIMITIVA', 'LLAMPA', 'BORDA', 'Femenino', '609000052017037', '15490806', '2012-05-21', 122, NULL, 'EFECTIVO', ''),
(2108, 'AIDE', 'MENECES', 'SALVATIERRA', 'Femenino', '409000052017007', '13417900', '2011-07-18', 122, NULL, 'EFECTIVO', ''),
(2109, 'MARI JHANNINA', 'PAUCARA', 'CAMACHO', 'Femenino', '409000092016029', '16563480', '2012-02-07', 122, NULL, 'EFECTIVO', ''),
(2110, 'LUIS DANIEL', 'QUISPE', 'MAITA', 'Masculino', '409000042016027', '14421357', '2011-07-25', 122, NULL, 'EFECTIVO', ''),
(2112, 'JHONATAN', 'TERCEROS', 'CARRASCO', 'Masculino', '409000052014506', '14440891', '2009-06-26', 122, NULL, 'EFECTIVO', ''),
(2113, 'LUZ RIHANNA', 'VALENCIA', 'CALLE', 'Femenino', '809800782017135', '13590823', '2011-07-13', 122, NULL, 'EFECTIVO', ''),
(2115, 'MARIA BELEN', 'VASQUEZ', 'CARRILLO', 'Femenino', '409000052016034', '14514747', '2010-08-12', 122, NULL, 'EFECTIVO', ''),
(2116, 'JESUS NOEL', 'VELASCO', 'VILLARROEL', 'Masculino', '409000272016025', '16780204', '2011-12-25', 122, NULL, 'EFECTIVO', ''),
(2117, 'ABDIAS', 'ZENTENO', 'CRESPO', 'Masculino', '409000042017012', '15352535', '2012-04-06', 122, NULL, 'EFECTIVO', ''),
(2118, 'LEYDI', 'ZUÑIGA', 'SOLIZ', 'Femenino', '708700992017049', '14543338', '2011-12-15', 122, NULL, 'EFECTIVO', ''),
(2119, 'NAHUEL', 'ZURITA', 'RODRIGUEZ', 'Masculino', '80980551201514', '12524771', '2010-11-04', 122, NULL, 'EFECTIVO', ''),
(2121, 'IVER', 'ACHU', 'LAZARO', 'Masculino', '409000052014296', '14381612', '2008-11-02', 122, NULL, 'EFECTIVO', 'REPROBADO'),
(2123, 'CELSO MICHAEL', 'ROJAS', 'ROJAS', 'Masculino', '8098020720152046', '17514040', '2011-01-03', 122, NULL, 'EFECTIVO', 'REPROBADO'),
(2125, 'ANET', 'PANIAGUA', NULL, 'Femenino', '4090000720158549', '15482120', '2010-05-09', 123, NULL, 'EFECTIVO', ''),
(2126, 'ARACELY', 'ESTRADA', NULL, 'Femenino', '409000052017058', '16832654', '2011-10-21', 123, NULL, 'EFECTIVO', ''),
(2128, 'ADRIAN DEIMAR', 'APAZA', 'CHOQUE', 'Masculino', '809802352016013', '15163007', '2012-02-17', 123, NULL, 'EFECTIVO', ''),
(2129, 'CAMILA NOHEMI', 'BAYA', 'COPA', 'Femenino', '809800852016007', '14038293', '2012-06-12', 123, NULL, 'EFECTIVO', ''),
(2130, 'BEYMAR', 'CABRERA', 'MONTECINOS', 'Masculino', '409000052017028', '15665107', '2011-08-26', 122, NULL, 'EFECTIVO', NULL),
(2132, 'BENJAMIN', 'CHOQUE', 'AGUAYO', 'Masculino', '409000052017043', '15954429', '2011-12-02', 123, NULL, 'EFECTIVO', ''),
(2134, 'GHILMAR', 'FLORES', 'GUTIERREZ', 'Masculino', '809800172017001', '15657764', '2011-07-27', 123, NULL, 'EFECTIVO', ''),
(2135, 'SOFIA', 'GARCIA', 'TECILLO', 'Femenino', '409000052017047', '16016751', '2011-11-22', 123, NULL, 'EFECTIVO', ''),
(2136, 'RODRIGO', 'GUTIERREZ', 'HERRERA', 'Masculino', '409000052017026', '15066077', '2012-01-28', 123, NULL, 'EFECTIVO', ''),
(2137, 'KATERIN LEYDA', 'LONASCO', 'RUIZ', 'Femenino', '409000052017012', '15317679', '2011-07-12', 123, NULL, 'EFECTIVO', ''),
(2138, 'JHOSELIN', 'MAMANI', 'ORTIZ', 'Femenino', '809000382016015', '14512173', '2011-01-26', 123, NULL, 'EFECTIVO', ''),
(2139, 'JHOEL', 'NINAVIA', 'REVOLLO', 'Masculino', '409000052017036', '14997601', '2011-10-29', 123, NULL, 'EFECTIVO', ''),
(2140, 'JHADIEL JOSTIN', 'PARDO', 'GARCIA', 'Masculino', '409000052017020', '14381683', '2011-08-17', 123, NULL, 'EFECTIVO', ''),
(2142, 'DAIANE', 'QUISPE', 'GARCIA', 'Femenino', '809802872018002', '16250310', '2010-07-30', 123, NULL, 'EFECTIVO', ''),
(2143, 'MARIBEL', 'SANGRERI', 'TORRES', 'Femenino', '809805172017062', '15708614', '2012-06-11', 123, NULL, 'EFECTIVO', ''),
(2144, 'CAMILA', 'TOLA', 'PILLCO', 'Femenino', '409000052017008', '15065968', '2011-09-13', 123, NULL, 'EFECTIVO', ''),
(2145, 'RAUL', 'TORREZ', 'ALEJO', 'Masculino', '409000182015200', '14122669', '2011-06-05', 123, NULL, 'EFECTIVO', '');
INSERT INTO `estudiantes` (`id_estudiante`, `nombres`, `apellido_paterno`, `apellido_materno`, `genero`, `rude`, `carnet_identidad`, `fecha_nacimiento`, `id_curso`, `id_responsable`, `estado_1`, `estado_2`) VALUES
(2146, 'THIAGO JOAQUIN', 'VARGAS', 'MORALES', 'Masculino', '409000052017025', '17080174', '2012-03-20', 123, NULL, 'EFECTIVO', ''),
(2147, 'ABNER', 'ZENTENO', 'CRESPO', 'Masculino', '409000042017013', '15352514', '2012-04-06', 123, NULL, 'EFECTIVO', ''),
(2150, 'MOISES WILLIAN', 'ESPINOZA', 'CALI', 'Masculino', '409000052016041', '14722147', '2010-09-08', 123, NULL, 'EFECTIVO', 'REPROBADO'),
(2152, 'JHON OLIVER', 'ALVARADO', 'LAUREANO', 'Masculino', '409000052016008', '12623434', '2011-03-17', 125, NULL, 'EFECTIVO', NULL),
(2153, 'ALLISON', 'ANGULO', 'RODRIGUEZ', 'Femenino', '409000052016018', '15224720', '2011-03-10', 125, NULL, 'EFECTIVO', NULL),
(2154, 'MIRIAM', 'BARRETA', 'CRUZ', 'Femenino', '409000052016020', '15068119', '2011-06-21', 125, NULL, 'EFECTIVO', NULL),
(2155, 'NEYMAR ALEXIS', 'CORONEL', 'MAMANI', 'Masculino', '408700922016031', '16309814', '2011-05-18', 124, NULL, 'EFECTIVO', NULL),
(2156, 'CRISTIAN', 'HERRERA', 'OYARDO', 'Masculino', '80610036201422', '11077123', '2010-03-13', 124, NULL, 'EFECTIVO', NULL),
(2157, 'ELVI LIZBETH', 'LAZARO', 'CONDORI', 'Femenino', '409000052016006', '13994292', '2011-06-19', 125, NULL, 'EFECTIVO', NULL),
(2158, 'KAREN', 'LIMA', 'ESPINOZA', 'Femenino', '409000052016014', '15077270', '2011-03-20', 124, NULL, 'EFECTIVO', NULL),
(2159, 'JHULIANA', 'LIMACHI', 'TACURI', 'Femenino', '409000052016021', '14266314', '2010-09-12', 124, NULL, 'EFECTIVO', NULL),
(2160, 'DAVID', 'MARTINEZ', 'VIZALLA', 'Masculino', '4090000520152825', '15142566', '2009-09-26', 124, NULL, 'EFECTIVO', NULL),
(2161, 'JOEL', 'MARTINEZ', 'MAMANI', 'Masculino', '409000052016015', '14121532', '2010-08-22', 124, NULL, 'EFECTIVO', NULL),
(2162, 'CARLOS CRISTOBAL', 'MENESES', 'GUTIERREZ', 'Masculino', '409000052015283A', '15075182', '2010-05-05', 124, NULL, 'EFECTIVO', NULL),
(2163, 'DAYANA', 'MERCADO', 'MONTECINOS', 'Femenino', '409000052016013', '14512670', '2010-07-17', 124, NULL, 'EFECTIVO', NULL),
(2165, 'DAYLIN', 'MURUCHI', 'IMPA', 'Femenino', '8123016720152066', '15383028', '2010-03-04', 125, NULL, 'EFECTIVO', NULL),
(2166, 'KATERIN DIANA', 'REVOLLO', 'VERA', 'Femenino', '409000052016001', '14383772', '2010-08-15', 124, NULL, 'EFECTIVO', NULL),
(2167, 'ELVIS', 'RODRIGUEZ', 'VENEGAS', 'Masculino', '80920009201573', '15919874', '2010-03-08', 124, NULL, 'EFECTIVO', NULL),
(2168, 'JOSUE GERMAIDER', 'ROJAS', 'ROJAS', 'Masculino', '809804012016026', '15530788', '2010-09-13', 125, NULL, 'EFECTIVO', NULL),
(2169, 'JOSE DANIEL', 'VERA', 'ROCHA', 'Masculino', '409000052016043', '12464934', '2011-02-08', 124, NULL, 'EFECTIVO', ''),
(2170, 'RUDY ANGEL', 'VILLARROEL', 'MAMANI', 'Masculino', '809804182015647', '14971255', '2010-09-22', 125, NULL, 'EFECTIVO', NULL),
(2173, 'JHOSELIN', 'ALVAREZ', 'GONZALES', 'Femenino', '4090000520152677', '16367989', '2009-11-27', 125, NULL, 'EFECTIVO', NULL),
(2174, 'ESNAYDER', 'CALICHO', 'ARNEZ', 'Masculino', '8189011120151582', '13302256', '2011-02-17', 125, NULL, 'EFECTIVO', NULL),
(2175, 'JOSUEL', 'CARRILLO', 'PADILLA', 'Masculino', '4090000520152442', '15077082', '2009-08-12', 124, NULL, 'EFECTIVO', NULL),
(2176, 'JHONATAN', 'CATUNTA', 'LAUREANO', 'Masculino', '7118001320152843', '16833675', '2010-03-08', 125, NULL, 'EFECTIVO', NULL),
(2177, 'ERLINDA', 'CHAMACA', 'CRUZ', 'Femenino', '609000592016014', '16181082', '2011-03-09', 124, NULL, 'EFECTIVO', NULL),
(2178, 'IKER', 'CHIPATA', 'CABEZAS', 'Masculino', '409000042016002', '14195508', '2011-03-10', 125, NULL, 'EFECTIVO', NULL),
(2179, 'JUAN DAVID', 'CUELLAR', 'MAMANI', 'Masculino', '409000052016032', '16214732', '2010-11-04', 125, NULL, 'EFECTIVO', NULL),
(2180, 'DELINA', 'CUYO', 'TAQUICHIRI', 'Femenino', '814300822015297', '15146110', '2011-01-15', 125, NULL, 'EFECTIVO', NULL),
(2181, 'ANALIA', 'ESCOBAR', 'CARRILLO', 'Femenino', '4090000520152639', '14834568', '2010-02-25', 125, NULL, 'EFECTIVO', NULL),
(2183, 'MATILDE', 'FUENTES', 'VALENCIA', 'Femenino', '808900352017011', '15797041', '2010-07-04', 125, NULL, 'EFECTIVO', NULL),
(2184, 'JAVIER', 'GUTIERREZ', 'SIACARI', 'Masculino', '6090002420152428', '13260636', '2011-01-20', 125, NULL, 'EFECTIVO', NULL),
(2185, 'AMELIA DAYANA', 'JUYARI', 'NINA', 'Femenino', '811700282015642', '14146628', '2011-05-31', 125, NULL, 'EFECTIVO', NULL),
(2186, 'JHOSTIN FAVIAN', 'MAMANI', 'JAILLITA', 'Masculino', '409000052016044', '12524293', '2010-10-01', 124, NULL, 'EFECTIVO', NULL),
(2187, 'BRITHANNY DAYANA', 'MEDINA', 'GUTIERREZ', 'Femenino', '4090002020152650', '15833211', '2010-11-12', 124, NULL, 'EFECTIVO', NULL),
(2188, 'ELVIS JAMIL', 'MENECES', 'CARRILLO', 'Masculino', '4090000520152366', '17257360', '2009-07-14', 124, NULL, 'EFECTIVO', NULL),
(2190, 'LIZ VANIA', 'ROJAS', 'QUECAÑA', 'Femenino', '409000052017062', '17606027', '2011-01-28', 125, NULL, 'EFECTIVO', NULL),
(2191, 'JUAN JOSE', 'ROTALES', 'SOLIZ', 'Masculino', '809803522015613', '13994972', '2010-11-18', 125, NULL, 'EFECTIVO', NULL),
(2192, 'ELVIS CRISTIAN', 'TRIVEÑO', 'PADILLA', 'Masculino', '409000052016028', '16059467', '2011-04-10', 125, NULL, 'EFECTIVO', NULL),
(2193, 'JHOSET SILENY', 'CABALLERO', 'SILVA', 'Femenino', '409000042015372', '14625334', '2009-10-12', 125, NULL, 'EFECTIVO', NULL),
(2195, 'FERNANDA MICHEL', 'BAYA', 'COPA', 'Femenino', '809800852014381', '14038292', '2009-09-14', 127, NULL, 'EFECTIVO', NULL),
(2196, 'JHON ALVEIRO', 'CABALLERO', 'AGUILAR', 'Masculino', '40900007201511206', '13825108', '2009-08-29', 127, NULL, 'EFECTIVO', NULL),
(2197, 'ALEX', 'CARI CARI', 'MORCO', 'Masculino', '80890067201462', '13227914', '2009-11-16', 127, NULL, 'EFECTIVO', NULL),
(2198, 'ALEYDA', 'CASILLA', 'UGARTE', 'Femenino', '809802352015693', '9513156', '2010-05-30', 127, NULL, 'EFECTIVO', NULL),
(2199, 'YANINA CAMILA', 'CHOQUE', 'CESPEDES', 'Femenino', '6090000520142240', '15476072', '2010-04-28', 127, NULL, 'EFECTIVO', NULL),
(2200, 'JOEL', 'CHOQUE', 'AGUAYO', 'Masculino', '4090000520152749', '15079486', '2010-02-04', 127, NULL, 'EFECTIVO', NULL),
(2201, 'NEISA', 'CHOQUE', 'CAYARA', 'Femenino', '8198122120157357', '16990439', '2009-10-06', 127, NULL, 'EFECTIVO', NULL),
(2202, 'KEVIN', 'CLAROS', 'CONDORI', 'Masculino', '40900007201511214', '14834549', '2010-03-06', 127, NULL, 'EFECTIVO', NULL),
(2203, 'LEONEL ANDRE', 'COCA', 'LEDEZMA', 'Masculino', '6089003220142690', '14233573', '2009-09-17', 127, NULL, 'EFECTIVO', NULL),
(2204, 'VICTORIA', 'GARCIA', 'TECILLO', 'Femenino', '409000052014415', '14834548', '2009-04-27', 127, NULL, 'EFECTIVO', NULL),
(2205, 'VICTOR MANUEL', 'GARCIA', 'VILLARROEL', 'Masculino', '409000272014246', '15075085', '2009-11-27', 127, NULL, 'EFECTIVO', NULL),
(2206, 'MIGUEL ANGEL', 'GUTIERREZ', 'HERRERA', 'Masculino', '4090000520152586', '15079693', '2009-07-04', 127, NULL, 'EFECTIVO', NULL),
(2207, 'ARON ISRAEL', 'MANTILLA', 'MAMANI', 'Masculino', '809802892014656', '13375162', '2009-11-15', 127, NULL, 'EFECTIVO', NULL),
(2208, 'CAMILA WENDY', 'MERINO', 'PANAMA', 'Femenino', '409000042014200', '15333284', '2010-04-06', 127, NULL, 'EFECTIVO', NULL),
(2209, 'MARIA RENE', 'PALLA', 'CONDORI', 'Femenino', '8117002720141352', '14146804', '2010-04-05', 127, NULL, 'EFECTIVO', NULL),
(2210, 'HIERKO ALEJANDRO', 'QUELCA', 'CONDORI', 'Masculino', '4090000520152423', '15896802', '2010-03-18', 127, NULL, 'EFECTIVO', NULL),
(2211, 'MAIRA CELESTE', 'QUISPE', 'HINOJOSA', 'Femenino', '4090000520152476', '14357977', '2009-10-10', 127, NULL, 'EFECTIVO', NULL),
(2212, 'JUDITH LIA', 'ROJAS', 'ESPINOZA', 'Femenino', '606800202014354', '13960423', '2009-07-15', 127, NULL, 'EFECTIVO', NULL),
(2213, 'SHEYLA NATALY', 'SALAS', 'AGUILAR', 'Femenino', '4090000520152878', '16304315', '2009-08-05', 127, NULL, 'EFECTIVO', NULL),
(2214, 'BRITANI MELANI', 'SANTA MARIA', 'CONDORI', 'Femenino', '4090000520152882', '10720201', '2009-07-27', 127, NULL, 'EFECTIVO', NULL),
(2215, 'LUZ CLARITA', 'TERCEROS', 'ANTONIO', 'Femenino', '4090000520152901', '15062737', '2009-10-11', 127, NULL, 'EFECTIVO', NULL),
(2217, 'JUAN FLAVIO', 'VEGA', 'ANCASI', 'Masculino', '4090000520152514', '14582812', '2009-10-23', 127, NULL, 'EFECTIVO', NULL),
(2218, 'LIZBETH', 'ZUÑIGA', 'SOLIZ', 'Femenino', '7087009920159167', '14543337', '2009-11-03', 127, NULL, 'EFECTIVO', NULL),
(2220, 'KEVIN IVAN', 'ALACORE', 'CHAMBI', 'Masculino', '40900005201425', '12492908', '2008-12-26', 126, NULL, 'EFECTIVO', NULL),
(2221, 'DANIEL', 'ALANOCA', 'LAZO', 'Masculino', '4073026320141378', '13815871', '2010-01-12', 126, NULL, 'EFECTIVO', NULL),
(2222, 'DILAN ANTHONY', 'ALVARADO', 'MARTINEZ', 'Masculino', '8089001520155133', '16718288', '2010-01-09', 126, NULL, 'EFECTIVO', NULL),
(2223, 'MARLENI', 'CALI', 'ESPINOZA', 'Femenino', '4090000520152715', '14834547', '2010-06-06', 126, NULL, 'EFECTIVO', NULL),
(2224, 'CESAR', 'COLQUE', 'ZEBALLOS', 'Masculino', '8098000720145238', '13378854', '2009-12-20', 126, NULL, 'EFECTIVO', NULL),
(2225, 'JOSE SANDRO', 'GARCIA', 'FERNANDEZ', 'Masculino', '5090004920142998', '15329576', '2009-04-08', 126, NULL, 'EFECTIVO', NULL),
(2226, 'DAVID RONALD', 'HUANACO', 'CASILLA', 'Masculino', '4090000620144190', '17023424', '2008-04-17', 126, NULL, 'EFECTIVO', NULL),
(2227, 'JUAN DE DIOS', 'IRIARTE', 'QUINTEROS', 'Masculino', '4090002420151038', '13225123', '2007-06-08', 126, NULL, 'EFECTIVO', NULL),
(2229, 'MARCO DANIEL', 'LAYME', 'NINA', 'Masculino', '605700042013153', '13797657', '2009-03-30', 126, NULL, 'EFECTIVO', NULL),
(2230, 'JHEYSON', 'LIMA', 'ESPINOZA', 'Masculino', '409000052014179', '15077265', '2009-02-22', 126, NULL, 'EFECTIVO', NULL),
(2232, 'ANGELA ROSARIO', 'MAMANI', 'LAZO', 'Femenino', '409000122014226', '16747203', '2010-03-14', 126, NULL, 'EFECTIVO', NULL),
(2233, 'HILARIA', 'MAMANI', 'PACO', 'Femenino', '4090000520152810', '15082146', '2009-10-14', 126, NULL, 'EFECTIVO', NULL),
(2235, 'ELMER', 'QUINTEROS', 'CARRILLO', 'Masculino', '409000052014483', '15035796', '2009-06-09', 126, NULL, 'EFECTIVO', NULL),
(2236, 'ROSARIO', 'QUINTEROS', 'CARRILLO', 'Femenino', '409000052014478', '15039715', '2009-06-09', 126, NULL, 'EFECTIVO', NULL),
(2237, 'TAIDE DANIELA', 'QUIROGA', 'HINOJOSA', 'Femenino', '409000272014940', '16643525', '2009-04-30', 126, NULL, 'EFECTIVO', NULL),
(2238, 'MIQUE EDWIN', 'QUISPE', 'HUIRACOCHA', 'Masculino', '4090000520152859', '16091192', '2009-12-22', 126, NULL, 'EFECTIVO', NULL),
(2239, 'MAXIMO', 'RAMIREZ', 'LEON', 'Masculino', '4090000520152552', '15027740', '2010-03-19', 126, NULL, 'EFECTIVO', NULL),
(2240, 'ELIAS', 'SANGRERI', 'TORRES', 'Masculino', '8098051720154419', '14697816', '2010-04-25', 126, NULL, 'EFECTIVO', NULL),
(2241, 'JHOSSELINE BRITTANY', 'TERRAZAS', 'RIVERA', 'Femenino', '809801202014246', '14581643', '2009-07-23', 126, NULL, 'EFECTIVO', NULL),
(2242, 'ISRAEL', 'TOLA', 'PILLCO', 'Masculino', '4090000520153510', '15079289', '2008-03-19', 126, NULL, 'EFECTIVO', NULL),
(2243, 'ALEJANDRO ULICES', 'VELIZ', 'LEON', 'Masculino', '409000052015294A', '14722299', '2009-11-26', 126, NULL, 'EFECTIVO', NULL),
(2245, 'DEYSI', 'CAISINA', 'VILLEZ', 'Femenino', '40900005201433A', '14440357', '2008-12-22', 128, NULL, 'EFECTIVO', NULL),
(2246, 'SEVERO', 'CAISINA', 'SOTO', 'Masculino', '40900005201448', '14195846', '2009-04-22', 128, NULL, 'EFECTIVO', NULL),
(2247, 'DIEGO', 'CARRILLO', 'FLORES', 'Masculino', '40900005201460', '14722265', '2009-04-27', 128, NULL, 'EFECTIVO', NULL),
(2248, 'LUIS MARIO', 'CHAMACA', 'CRUZ', 'Masculino', '6090005920145772', '14263002', '2009-04-24', 128, NULL, 'EFECTIVO', NULL),
(2249, 'ARIEL MARIO', 'CHIRI', 'TOLA', 'Masculino', '409000052014345', '14234490', '2008-08-03', 128, NULL, 'EFECTIVO', NULL),
(2250, 'DAVID', 'COCA', 'CALLAHUARA', 'Masculino', '4090000520149A', '14235534', '2008-12-28', 128, NULL, 'EFECTIVO', NULL),
(2251, 'ANABEL', 'COCA', 'SANDOVAL', 'Femenino', '409000052014100', '14447810', '2009-02-18', 128, NULL, 'EFECTIVO', NULL),
(2252, 'CHAIR JHILMER', 'COTARI', 'JIMENEZ', 'Masculino', '4090000520125526', '14834467', '2007-05-31', 128, NULL, 'EFECTIVO', NULL),
(2253, 'CESAR', 'ESPINOZA', 'CALI', 'Masculino', '409000052014371', '14722048', '2008-10-21', 128, NULL, 'EFECTIVO', NULL),
(2254, 'JAVIER', 'GARCIA', 'ESCOBAR', 'Masculino', '8098003220145572', '9624317', '2008-08-29', 128, NULL, 'EFECTIVO', NULL),
(2255, 'LUIS ANGEL', 'HEREDIA', 'MOLINA', 'Masculino', '8098010720133069', '13893704', '2008-05-19', 128, NULL, 'EFECTIVO', NULL),
(2256, 'JAZMIN', 'INOCENTE', 'TOAQUE', 'Femenino', '409000052014420', '14447505', '2009-03-19', 128, NULL, 'EFECTIVO', NULL),
(2257, 'WENS ABNER', 'JIMENEZ', 'SOLIZ', 'Masculino', '409000122014408', '12968825', '2008-11-26', 128, NULL, 'EFECTIVO', NULL),
(2258, 'JUAN JHOEL', 'LIMACHI', 'TACURI', 'Masculino', '409000052014184', '14266312', '2008-09-30', 128, NULL, 'EFECTIVO', NULL),
(2259, 'ZENAIDA', 'MAMANI', 'ORTIZ', 'Femenino', '809000382014611', '13944702', '2008-12-11', 128, NULL, 'EFECTIVO', NULL),
(2260, 'LEONARDO DAVID', 'MENESES', 'GUTIERREZ', 'Masculino', '4090000520125655', '14834118', '2006-08-09', 128, NULL, 'EFECTIVO', NULL),
(2261, 'JHON SAUL', 'MORALES', 'ROCHA', 'Masculino', '4090000520136314', '14512896', '2008-03-30', 128, NULL, 'EFECTIVO', NULL),
(2262, 'ESTRELLA ELEONOR', 'OROPEZA', 'GERONIMO', 'Femenino', '40900027201485A', '13894459', '2008-09-17', 128, NULL, 'EFECTIVO', NULL),
(2263, 'HERNAN ALEJANDRO', 'OTALORA', 'GUTIERREZ', 'Masculino', '809800172012235', '17099714', '2007-12-27', 128, NULL, 'EFECTIVO', NULL),
(2264, 'DIANA GABRIELA', 'PEREZ', 'SALAZAR', 'Femenino', '4090000520227005', '9508180', '2009-03-16', 128, NULL, 'EFECTIVO', NULL),
(2265, 'GROBER', 'PINEDO', 'ROCHA', 'Masculino', '70930004201368', '13824898', '2006-11-09', 128, NULL, 'EFECTIVO', NULL),
(2266, 'SUSAN', 'QUISPE', 'AGUILAR', 'Femenino', '4090000720146242', '13259472', '2009-03-30', 128, NULL, 'EFECTIVO', NULL),
(2267, 'SANTIAGO ISRAEL', 'REVOLLO', 'MONTAÑO', 'Masculino', '40900004201348A', '12524763', '2008-09-17', 128, NULL, 'EFECTIVO', NULL),
(2268, 'LETICIA', 'VARGAS', 'CONDORI', 'Femenino', '409000072013309', '13943787', '2007-12-03', 128, NULL, 'EFECTIVO', NULL),
(2269, 'JHEISON', 'VELIZ', 'MENECES', 'Masculino', '40900005201426A', '15077099', '2008-11-07', 128, NULL, 'EFECTIVO', NULL),
(2270, 'YOSELIN LUCERO', 'VERA', 'CHOQUE', 'Femenino', '409000052014275', '9429739', '2009-01-30', 128, NULL, 'EFECTIVO', NULL),
(2272, 'JOEL', 'ALVAREZ', 'MAMANI', 'Masculino', '609000352018124', '13096832', '2008-03-26', 129, NULL, 'EFECTIVO', NULL),
(2273, 'MADAY DANIA', 'BUSTAMANTE', 'VELIZ', 'Femenino', '40900005201431', '13379027', '2009-03-08', 129, NULL, 'EFECTIVO', NULL),
(2274, 'NILTON', 'CABRERA', 'MONTECINOS', 'Masculino', '409000052014324', '15078636', '2008-12-25', 129, NULL, 'EFECTIVO', NULL),
(2275, 'NOEL DAVID', 'CHOQUE', 'CHIRI', 'Masculino', '409000052014350', '14834593', '2009-03-23', 129, NULL, 'EFECTIVO', NULL),
(2276, 'JHOAN ANDY', 'CHOQUE', 'CESPEDES', 'Masculino', '60900005201324', '9392757', '2008-07-18', 129, NULL, 'EFECTIVO', NULL),
(2277, 'MAYCOL', 'CRESPO', 'HERRERA', 'Masculino', '409000052014366', '15077035', '2009-02-04', 129, NULL, 'EFECTIVO', NULL),
(2278, 'ELIZABETH', 'FATTY', 'LAUREANO', 'Femenino', '409000052014387', '14868026', '2009-03-14', 129, NULL, 'EFECTIVO', NULL),
(2279, 'NOELIA', 'FERREL', 'QUIROZ', 'Femenino', '409000052014121', '15962994', '2008-08-19', 129, NULL, 'EFECTIVO', NULL),
(2280, 'JESSICA', 'FLORES', 'GARCIA', 'Femenino', '409000182013825', '14328258', '2008-03-24', 129, NULL, 'EFECTIVO', NULL),
(2281, 'RONALD', 'FLORES', 'NINA', 'Masculino', '809800462014310', '13718083', '2007-09-25', 129, NULL, 'EFECTIVO', NULL),
(2282, 'ABEL', 'FUENTES', 'VALENCIA', 'Masculino', '8089003520132722', '14070848', '2006-09-08', 129, NULL, 'EFECTIVO', NULL),
(2283, 'CARLOS JHOAN', 'GALARZA', 'ESCOBAR', 'Masculino', '40900005201440A', '17118778', '2008-08-07', 129, NULL, 'EFECTIVO', NULL),
(2284, 'ALEX ANGEL', 'GUZMAN', 'POMA', 'Masculino', '809800232012718', '13066204', '2008-04-18', 129, NULL, 'EFECTIVO', NULL),
(2285, 'ROSMERY', 'INOCENTE', 'CUISARA', 'Femenino', '409000052014158', '14783882', '2008-05-20', 129, NULL, 'EFECTIVO', NULL),
(2286, 'ELIANA', 'INOCENTE', 'COCA', 'Femenino', '409000052014163', '14123161', '2008-08-03', 129, NULL, 'EFECTIVO', NULL),
(2287, 'FABIOLA', 'IQUISE', 'MAMANI', 'Femenino', '812300162013135', '7405016', '2008-05-16', 129, NULL, 'EFECTIVO', NULL),
(2288, 'CINTHIA', 'MAMANI', 'SANCHEZ', 'Femenino', '409000052014207', '14852812', '2008-04-22', 129, NULL, 'EFECTIVO', NULL),
(2289, 'ANABEL', 'QUISPE', 'GARCIA', 'Femenino', '407304562014482A', '9733126', '2009-02-10', 129, NULL, 'EFECTIVO', NULL),
(2290, 'ANA SOLEDAD', 'ROTALES', 'SOLIZ', 'Femenino', '809803522013580', '13994971', '2008-09-23', 129, NULL, 'EFECTIVO', NULL),
(2291, 'ALISON BRITANNY', 'SALAS', 'AGUILAR', 'Femenino', '409000052014249', '16304257', '2008-07-16', 129, NULL, 'EFECTIVO', NULL),
(2292, 'SOLEDAD', 'TECILLO', 'VALERIANO', 'Femenino', '8123027520153888', '15698852', '2009-01-16', 129, NULL, 'EFECTIVO', NULL),
(2293, 'JUAN DAVID', 'TERCEROS', 'CARRASCO', 'Masculino', '4090000520116142', '14447003', '2006-05-13', 129, NULL, 'EFECTIVO', NULL),
(2294, 'YARA ARLETH', 'TICONA', 'PORCE', 'Femenino', '8098031320141023', '10817646', '2009-05-13', 129, NULL, 'EFECTIVO', NULL),
(2295, 'NEYDA AMELY', 'VERA', 'TARIFA', 'Femenino', '409000042013362', '15077026', '2008-08-04', 129, NULL, 'EFECTIVO', NULL),
(2296, 'AMILCAR', 'VILLARROEL', 'ALMANZA', 'Masculino', '409000052014548', '9457972', '2008-10-08', 129, NULL, 'EFECTIVO', NULL),
(2304, 'JUAN CARLOS MAX', 'ANGULO', 'RUIZ', 'Masculino', '4090000520261974', '17090734', '2021-09-26', 102, NULL, 'EFECTIVO', NULL),
(2305, 'WILDER', 'ARGOTE', 'ROCHA', 'Masculino', '4090000520261974', '17071628', '2021-10-21', 102, NULL, 'EFECTIVO', NULL),
(2306, 'LUZZ ABIGAIL', 'CABEROS', 'LOPEZ', 'Femenino', '4090000520263987', '17086066', '2022-01-09', 102, NULL, 'EFECTIVO', NULL),
(2307, 'LUS FERNANDO', 'CHOQUE', 'ORDOÑEZ', 'Masculino', '409000052026502A', '16937043', '2021-08-08', 102, NULL, 'EFECTIVO', NULL),
(2308, 'YAIR KALEB', 'COAQUIRA', 'RIVERA', 'Masculino', '4090000520261672', '17136291', '2021-12-31', 102, NULL, 'EFECTIVO', NULL),
(2309, 'IRIS MARY', 'CORONEL', 'MAMANI', 'Femenino', '4090000520265396', '17165174', '2022-02-02', 102, NULL, 'EFECTIVO', NULL),
(2310, 'JENNY AIDA', 'GABRIEL', 'TAQUICHIRI', 'Femenino', '4090000520266576', '17009549', '2021-08-23', 102, NULL, 'EFECTIVO', NULL),
(2311, 'JHOSUA KEYDAN', 'GUZMAN', 'QUISPE', 'Masculino', '4090000520268777', '16990510', '2021-08-28', 102, NULL, 'EFECTIVO', NULL),
(2312, 'DANNA ANTONELLA', 'JIMENEZ', 'CHOQUE', 'Femenino', '4090000520261581', '17131519', '2021-11-29', 102, NULL, 'EFECTIVO', NULL),
(2313, 'LUCAS MATHIAS', 'MEDRANO', 'RODRIGUEZ', 'Masculino', '4090000520264073', '17028756', '2021-09-14', 102, NULL, 'EFECTIVO', NULL),
(2314, 'LUCAS THIAGO', 'MONTAÑO', 'RIVERA', 'Masculino', '4090000520263873', '17136795', '2022-01-14', 102, NULL, 'EFECTIVO', NULL),
(2315, 'ALEXANDER', 'MONTECINOS', 'CUBA', 'Masculino', '4090000520261010', '18034306', '2022-05-20', 102, NULL, 'EFECTIVO', NULL),
(2316, 'AITANA STEISY', 'RIVERO', 'VALENZUELA', 'Femenino', '409000052026976', '18039823', '2022-04-09', 102, NULL, 'EFECTIVO', NULL),
(2317, 'JHOSEPH', 'SALVATIERRA', 'ROJAS', 'Masculino', '409000052026880A', '16971692', '2021-07-17', 102, NULL, 'EFECTIVO', NULL),
(2318, 'YAMILET ELIF', 'SOLIZ', 'GONZALES', 'Femenino', '4090000520262213', '17042166', '2021-12-17', 102, NULL, 'EFECTIVO', NULL),
(2319, 'EDELSON MOISES', 'TECILLO', 'VALERIANO', 'Masculino', '4090000520266890', '17160893', '2021-10-31', 102, NULL, 'EFECTIVO', NULL),
(2320, 'GISEL ESCARLETT', 'TERCEROS', 'FUERTES', 'Femenino', '4090000520264203', '17181751', '2022-03-08', 102, NULL, 'EFECTIVO', NULL),
(2321, 'JAZMIN', 'VARGAS', 'TOMAS', 'Femenino', '4090000520266706', '17021028', '2021-11-28', 102, NULL, 'EFECTIVO', NULL),
(2322, 'PABLO DARIO', 'VEGA', 'CORTEZ', 'Masculino', '4090000520263507', '17028917', '2021-10-30', 102, NULL, 'EFECTIVO', NULL),
(2323, 'DAVID', 'VILLARPANDO', 'GARCIA', 'Masculino', '4090000520265321', '17190240', '2021-12-26', 102, NULL, 'EFECTIVO', NULL),
(2324, 'KEYLA GRETHEL', 'VILLARROEL', 'LIQUEN', 'Femenino', '4090000520264807', '17245257', '2022-05-10', 102, NULL, 'EFECTIVO', NULL),
(2325, 'ISAAC OSEIAS', 'VILLARROEL', 'MAMANI', 'Masculino', '4090000520263941', '17248165', '2922-06-10', 102, NULL, 'EFECTIVO', NULL),
(2326, 'MARTIN NAHIR', 'ZURITA', 'VERA', 'Masculino', '4090000520264962', '17257191', '2021-08-10', 102, NULL, 'EFECTIVO', NULL),
(2327, 'MAYER DAID', 'AYAVIRI', 'SOCOMPI', 'Masculino', '4090000520251769', '16578203', '2020-12-01', 104, NULL, 'EFECTIVO', NULL),
(2328, 'ALESSANDRO LEON', 'BUSTAMANTE', 'ROCHA', 'Masculino', '4090000520252397', '16604527', '2020-12-21', 104, NULL, 'EFECTIVO', NULL),
(2329, 'ZOE ARLETH', 'CHOQUE', 'ROMERO', 'Femenino', '409000052025745A', '16471193', '2020-07-25', 104, NULL, 'EFECTIVO', NULL),
(2330, 'FERMIN', 'COLQUE', 'BORDE', 'Masculino', '4090000520257472', '16556913', '2020-11-19', 104, NULL, 'EFECTIVO', NULL),
(2331, 'MOISES', 'COPA', 'SOLIZ', 'Masculino', '4090000520268082', '16799247', '2021-04-25', 104, NULL, 'EFECTIVO', NULL),
(2332, 'CARMEN ROSSY', 'FATTY', 'TOCO', 'Femenino', '409000052025887A', '16709933', '2021-02-27', 104, NULL, 'EFECTIVO', NULL),
(2333, 'JAIR AARON', 'FERNANDEZ', 'FRANCO', 'Masculino', '409000052025683', '16586802', '2020-12-07', 104, NULL, 'EFECTIVO', NULL),
(2335, 'MATIAS JHOAN', 'FLORES', 'MAMANI', 'Masculino', '4090000520253411', '16841750', '2021-04-09', 104, NULL, 'EFECTIVO', NULL),
(2336, 'AYSE CHARLOTTE', 'LEDEZMA', 'SALAZAR', 'Femenino', '4090000520257106', '17647362', '2021-02-16', 104, NULL, 'EFECTIVO', NULL),
(2337, 'MAYTE BELEN', 'LIMACHI', 'TACURI', 'Femenino', '4090001820256159', '16491612', '2020-09-10', 104, NULL, 'EFECTIVO', NULL),
(2338, 'ANGELES VALERIA', 'LOZA', 'LAUREANO', 'Femenino', '4090001820256940', '16464959', '2020-07-06', 104, NULL, 'EFECTIVO', NULL),
(2339, 'ANDRES JHUNIOR', 'MENECES', 'QUISPE', 'Masculino', '4090000520259160', '16539901', '2020-11-06', 104, NULL, 'EFECTIVO', NULL),
(2340, 'TAYLOR JAMES', 'MENESES', 'VILLARROEL', 'Masculino', '4090000520256172', '16922846', '2021-05-14', 104, NULL, 'EFECTIVO', NULL),
(2341, 'DAMARIS', 'NICOLAS', 'RODRIGEZ', 'Femenino', '4090000520253314', '16483886', '2020-08-28', 104, NULL, 'EFECTIVO', NULL),
(2342, 'SALOME CRISTAL', 'PADILLA', 'CHAVARRIA', 'Femenino', '4090000520252796', '16900582', '2021-01-25', 104, NULL, 'EFECTIVO', NULL),
(2343, 'MADISON ESCARLET', 'REINAGA', 'MONTECINOS', 'Femenino', '4090001820259598', '16480330', '2020-08-19', 104, NULL, 'EFECTIVO', NULL),
(2344, 'GABRIEL JESUS', 'REINAGA', 'REVOLLO', 'Masculino', '4090000520255168', '16832463', '2021-06-28', 104, NULL, 'EFECTIVO', NULL),
(2345, 'XIOMARA', 'REINAGA', 'VALDA', 'Femenino', '4090000520253269', '16685547', '2021-02-10', 104, NULL, 'EFECTIVO', NULL),
(2346, 'LUANA VALENTINA', 'SANCHEZ', 'ABAN', 'Femenino', '4090000520251171', '16487247', '2020-09-03', 104, NULL, 'EFECTIVO', NULL),
(2347, 'HANSSEL PABLO', 'SESPEDES', 'APAZA', 'Masculino', '4090000520253492', '16715675', '2021-03-03', 104, NULL, 'EFECTIVO', NULL),
(2348, 'MARIBEL', 'SOLIZ', 'CHOQUEHUANCA', 'Femenino', '4090000520253583', '16686690', '2021-02-07', 104, NULL, 'EFECTIVO', NULL),
(2349, 'ARIANA GISSEL', 'TAPIA', 'CHOQUE', 'Femenino', '4090000520257414', '16823421', '2021-04-29', 104, NULL, 'EFECTIVO', NULL),
(2350, 'ALAN GONEI', 'VARGAS', 'MONTECINOS', 'Masculino', '4090000520251529', '16483769', '2020-08-21', 104, NULL, 'EFECTIVO', NULL),
(2351, 'KALESSY FABIOLA', 'VEGA', 'HERNANDEZ', 'Masculino', '4090000520251107', '16669825', '2020-08-17', 104, NULL, 'EFECTIVO', NULL),
(2352, 'EVELIN', 'ZEBALLOS', 'TUDELA', 'Femenino', '409000052025168', '16475359', '2020-08-05', 104, NULL, 'EFECTIVO', NULL),
(2353, 'DEMIR MATIAS', 'ARIAS', 'VILLARROEL', 'Masculino', '4090000520255641', '16511091', '2020-10-08', 105, NULL, 'EFECTIVO', NULL),
(2354, 'GUADALUPE JHANCARLA', 'CAMACHO', 'PEDRO', 'Femenino', '4090000520264774', '16471423', '2020-07-26', 105, NULL, 'EFECTIVO', NULL),
(2356, 'MARIA CRISTINA', 'CARI CARI', 'MORCO', 'Femenino', '4090000520261461', '16750078', '2021-02-01', 105, NULL, 'EFECTIVO', NULL),
(2357, 'INCI BRYONY', 'COCA', 'FLORES', 'Femenino', '4090000520268099', '16691885', '2021-02-15', 105, NULL, 'EFECTIVO', NULL),
(2358, 'ANGEL', 'CORDOVA', 'MOSQUEZ', 'Masculino', '4090000520265110', '16498319', '2020-09-22', 105, NULL, 'EFECTIVO', NULL),
(2359, 'NEYMAR', 'ESTALLA', 'PADILLA', 'Masculino', '4090000520262908', '17690765', '2021-04-28', 105, NULL, 'EFECTIVO', NULL),
(2360, 'MICAEL', 'FERRUFINO', 'MAIZO', 'Masculino', '409000052025721A', '16581991', '2013-12-03', 105, NULL, 'EFECTIVO', NULL),
(2361, 'SEBASTIAN MIJAEL', 'FLORES', 'MAMANI', 'Masculino', '4090000520262749', '16731656', '2021-03-16', 105, NULL, 'EFECTIVO', NULL),
(2362, 'MIRIAM', 'GARCIA', 'TECILLO', 'Femenino', '409000052026474', '16753232', '2021-01-01', 105, NULL, 'EFECTIVO', NULL),
(2363, 'VALENTINA', 'GUTIERREZ', 'DELGADILLO', 'Femenino', '8198114220252017', '16533851', '2020-11-02', 105, NULL, 'EFECTIVO', NULL),
(2364, 'MAIKEL LIAN', 'JIMENEZ', 'AGUILAR', 'Masculino', '4090001820254785', '16481346', '2020-08-15', 105, NULL, 'EFECTIVO', NULL),
(2365, 'GENESIS ALEXIA', 'LUNA', 'VARGAS', 'Femenino', '4090001820253518', '16818498', '2021-06-08', 105, NULL, 'EFECTIVO', NULL),
(2366, 'JHOMAR', 'MAMANI', 'JAILLITA', 'Masculino', '409000052026975', '18034432', '2021-05-08', 105, NULL, 'EFECTIVO', NULL),
(2367, 'BAYRON', 'MAMANI', 'MENECES', 'Masculino', '409000052026818A', '16573302', '2020-11-27', 105, NULL, 'EFECTIVO', NULL),
(2368, 'JEREMI RICARDO', 'PEREZ', 'POMA', 'Masculino', '4090000520267220', '16680818', '2021-02-07', 105, NULL, 'EFECTIVO', NULL),
(2369, 'IAN ASIEL', 'ROCHA', 'NIETO', 'Masculino', '8110002920257335', '16664737', '2021-02-01', 105, NULL, 'EFECTIVO', NULL),
(2370, 'JOSUE DANIEL', 'SARABIA', 'FERNANDEZ', 'Masculino', '16664737', '16601900', '2020-12-17', 105, NULL, 'EFECTIVO', NULL),
(2371, 'ALISON MAITE', 'SILVESTRE', 'LEDEZMA', 'Femenino', '4090000520262385', '16559199', '2020-07-20', 105, NULL, 'EFECTIVO', NULL),
(2372, 'VALENTINA', 'SOTO', 'BELTRAN', 'Femenino', '807301462025270', '16761730', '2021-04-16', 105, NULL, 'EFECTIVO', NULL),
(2373, 'NAYELY ESTRELLA', 'TICONA', 'HINOJOSA', 'Femenino', '4090000520259297', '16865331', '2021-06-14', 105, NULL, 'EFECTIVO', NULL),
(2374, 'IAN LUCIO', 'ACOSTA', 'SOLANO', 'Masculino', '8098005820239452', '15897875', '2019-05-16', 106, NULL, 'EFECTIVO', NULL),
(2375, 'JHON ABDIE', 'CABEZAS', 'JUÑEZ', 'Masculino', '4090000520253548', '16416887', '2019-11-28', 106, NULL, 'EFECTIVO', NULL),
(2376, 'LUZ VANIA ABIGAIL', 'CUYO', 'TAQUICHIRI', 'Femenino', '4090000620249797', '16297022', '2019-12-27', 106, NULL, 'EFECTIVO', NULL),
(2377, 'JHAIR', 'ESPINOZA', 'PADILLA', 'Masculino', '4090000520235363', '17734865', '2019-03-04', 106, NULL, 'EFECTIVO', NULL),
(2378, 'GENESIS NICOLE', 'HEREDIA', 'FLORES', 'Femenino', '4090003520256484', '16302531', '2020-01-04', 106, NULL, 'EFECTIVO', NULL),
(2379, 'YILMAS MOISES', 'IQUISE', 'MAMANI', 'Masculino', '4090000520268423', '16447680', '2020-05-22', 106, NULL, 'EFECTIVO', NULL),
(2380, 'JHEICO MATIAS', 'MENECES', 'CARRILLO', 'Masculino', '4090000520243150', '17746671', '2019-06-01', 106, NULL, 'EFECTIVO', NULL),
(2381, 'ANA MARIBEL', 'TECILLO', 'SANGRERI', 'Femenino', '4090003520241489', '17577438', '2019-05-07', 106, NULL, 'EFECTIVO', NULL),
(2382, 'EZEQUIEL', 'ALVAREZ', 'GONZALES', 'Masculino', '4090000720256919', '16364863', '2019-10-03', 107, NULL, 'EFECTIVO', NULL),
(2383, 'LIMBERT ADRIAN', 'ANGULO', 'CARIDE', 'Masculino', 'LIMBERT ADRIAN', '16139582', '2019-09-17', 107, NULL, 'EFECTIVO', NULL),
(2384, 'DAMIAN', 'COCA', 'GARCIA', 'Masculino', '4090000520257540', '17220512', '2020-03-11', 107, NULL, 'EFECTIVO', NULL),
(2385, 'YARETZI ANTHONELA', 'DAMIAN', 'COCA', 'Femenino', '7092001320245604', '16283100', '2019-12-20', 107, NULL, 'EFECTIVO', NULL),
(2386, 'ABDIEL ORLANDO', 'DURAN', 'CORAJE', 'Masculino', '6090003520242133', '16848842', '2020-01-20', 107, NULL, 'EFECTIVO', NULL),
(2387, 'IRIS', 'ESPINOZA', 'ALMANZA', 'Femenino', '409000072025898A', '16135057', '2019-07-06', 107, NULL, 'EFECTIVO', NULL),
(2388, 'YEMIL FERNANDO', 'HERRERA', 'CHINO', 'Masculino', '7064000120245775', '16334141', '2020-01-17', 107, NULL, 'EFECTIVO', NULL),
(2389, 'ARON', 'MAMANI', 'VIZALLA', 'Masculino', '5090005620241639', '16196711', '2019-07-07', 107, NULL, 'EFECTIVO', NULL),
(2390, 'MOISES FERNANDO', 'MARTINEZ', 'MAMANI', 'Masculino', '8098007820245207', '16299725', '2020-01-02', 107, NULL, 'EFECTIVO', NULL),
(2391, 'KALEP', 'PADILLA', 'MALDONADO', 'Masculino', '4090000620238577', '15883210', '2019-05-10', 107, NULL, 'EFECTIVO', NULL),
(2392, 'OSEIAS ABNER', 'VILLARROEL', 'ORTIZ', 'Masculino', '4090000420248546', '16274684', '2019-12-15', 107, NULL, 'EFECTIVO', NULL),
(2393, 'VALESKA', 'VILLEGAS', 'VEGA', 'Femenino', '40900033202520', '16453050', '2020-06-06', 107, NULL, 'EFECTIVO', NULL),
(2394, 'VALERIA HAEL', 'COCA', 'LEDEZMA', 'Femenino', '4090000520237409', '16677436', '2018-12-13', 108, NULL, 'EFECTIVO', NULL),
(2395, 'ERICK RUBEN', 'HEREDIA', 'MOLINA', 'Masculino', '4090000520244165', '15655611', '2019-01-31', 108, NULL, 'EFECTIVO', NULL),
(2396, 'JAZMIN LIZETH', 'LEDEZMA', 'ROCHA', 'Femenino', '4090002720238733', '15629182', '2019-01-26', 108, NULL, 'EFECTIVO', NULL),
(2397, 'ZAIDA', 'RIOS', 'LUIZAGA', 'Femenino', '8087004820232523', '15356968', '2018-07-30', 108, NULL, 'EFECTIVO', NULL),
(2398, 'JAZRETH EVANGELYN', 'ROCHA', 'NIETO', 'Femenino', '4090000520232540', '15887220', '2019-05-14', 108, NULL, 'EFECTIVO', NULL),
(2399, 'JOSE MIGUEL', 'VILLARROEL', 'ROCHA', 'Masculino', '4090002720232130', NULL, '2018-05-29', 108, NULL, 'EFECTIVO', NULL),
(2400, 'LEONARDO FAVIO', 'VELIZ', 'ROCHA', 'Masculino', '409000062013482', '14446592', '2009-03-14', 129, NULL, 'EFECTIVO', NULL),
(2401, 'KANDY', 'AVILA', 'PADILLA', 'Femenino', '409000052014303', '16514017', '2008-12-11', 128, NULL, 'EFECTIVO', NULL),
(2402, 'AYDE', 'OLGUIN', 'REJAS', 'Femenino', '409000052014233', '12713133', '2009-05-10', 128, NULL, 'EFECTIVO', NULL),
(2403, 'INES', 'VARGAS', 'TOMAS', 'Femenino', '709000762015482', '16148005', '2010-01-27', 128, NULL, 'EFECTIVO', NULL),
(2404, 'JHANET', 'ALBA', 'ALMANZA', 'Femenino', '4090000520152662', '15082353', '2009-10-01', 126, NULL, 'EFECTIVO', NULL),
(2405, 'YADY WILLIAMS', 'CALDERON', 'SALA', 'Femenino', '8098020720141410', '13498721', '2009-07-23', 126, NULL, 'EFECTIVO', NULL),
(2406, 'NOELIA JAZMIN', 'ARANIBAR', 'ARGOTE', 'Femenino', '8090004020156433', '15063033', '2009-10-17', 126, NULL, 'EFECTIVO', NULL),
(2407, 'GABRIEL', 'FLORES', 'CRUZ', 'Masculino', '409000222013114', '12712503', '2007-12-21', 126, NULL, 'EFECTIVO', NULL),
(2408, 'FELIX', 'RAMIREZ', 'LEON', 'Masculino', '4090000520152567', '15027734', '2010-03-19', 126, NULL, 'EFECTIVO', NULL),
(2409, 'JUAN JOSE', 'LUQUE', NULL, 'Masculino', '805400032015910', '13340832', '2009-10-08', 127, NULL, 'EFECTIVO', NULL),
(2410, 'CAMILA', 'BAUTISTA', 'MANCILLA', 'Femenino', '4090000620129657', '14858712', '2007-11-07', 127, NULL, 'EFECTIVO', NULL),
(2411, 'NATALY', 'CARRILLO', 'ESPINOZA', 'Femenino', '4090000520152624', '15077348', '2010-06-29', 127, NULL, 'EFECTIVO', NULL),
(2412, 'MARIANA', 'SOCOMPI', 'ROCHA', 'Femenino', '409000052015239A', '15077273', '2010-05-14', 127, NULL, 'EFECTIVO', NULL),
(2413, 'DAFNE KIARA', 'BALTAZAR', 'PILLAY', 'Femenino', '809801472016024', '13345713', '2011-03-24', 124, NULL, 'EFECTIVO', NULL),
(2414, 'ERICK', 'CALIZAYA', 'SALVATIERRA', 'Masculino', '409000052015272A', '16179746', '2009-12-06', 124, NULL, 'EFECTIVO', NULL),
(2415, 'JONAS', 'LIMACHI', 'DELGADILLO', 'Masculino', '409000052017059', '15081398', '2010-11-12', 124, NULL, 'EFECTIVO', NULL),
(2416, 'MARISABEL', 'NICASIO', 'JANCKO', 'Femenino', '409000052014228', '14329916', '2008-11-05', 124, NULL, 'EFECTIVO', NULL),
(2417, 'JOSE MIGUEL', 'ROJAS', 'LAZARTE', 'Masculino', '8098026420126719', '15686885', '2007-06-07', 124, NULL, 'EFECTIVO', NULL),
(2418, 'JHEREMY', 'TORRES', 'ROCHA', 'Masculino', '409000052016007', '15079277', '2010-09-23', 124, NULL, 'EFECTIVO', NULL),
(2420, 'BENJHI', 'CHOQUEHUENCA', 'VASQUEZ', 'Masculino', '50900004201268', '14235743', '2007-02-07', 125, NULL, 'EFECTIVO', NULL),
(2421, 'ANGHELO', 'MURILLO', 'TARIFA', 'Masculino', '5090003220118552', '13257681', '2006-03-15', 125, NULL, 'EFECTIVO', NULL),
(2422, 'EVELIN', 'RODRIGUEZ', 'SUYO', 'Femenino', '809805592015591', '9424299', '2009-11-05', 125, NULL, 'EFECTIVO', NULL),
(2423, 'HUGO ALBERTO', 'HUALLPA', 'MALDONADO', 'Masculino', '608900162017003', '15093750', '2012-01-29', 122, NULL, 'EFECTIVO', NULL),
(2424, 'ADHAY YEFREN', 'QUISPE', 'GONZALES', 'Masculino', '809800072016048', '14835021', '2011-10-08', 122, NULL, 'EFECTIVO', NULL),
(2425, 'JHOEL MATIAS', 'SERNA', 'LUQUE', 'Masculino', '805400032017006', '14531752', '2012-03-08', 122, NULL, 'EFECTIVO', NULL),
(2426, 'JASIEL', 'SILVESTRE', 'CESPEDES', 'Femenino', '80920031201568', '12617711', '2010-11-19', 122, NULL, 'EFECTIVO', NULL),
(2427, 'DULCE CRISTINA', 'APAZA', 'PEREZ', 'Femenino', '409000042016039', '16832104', '2011-09-16', 123, NULL, 'EFECTIVO', NULL),
(2428, 'ANGELINA ANDREA', 'CACERES', 'JALDIN', 'Femenino', '409000072017030', '15798411', '2012-02-20', 123, NULL, 'EFECTIVO', NULL),
(2429, 'HECTOR', 'INOCENTE', 'SOTO', 'Masculino', '4090000720146022', '12403426', '2009-01-30', 123, NULL, 'EFECTIVO', NULL),
(2430, 'JUANA ROSA', 'INOSENTE', 'CHARACAYO', 'Femenino', '409000052017048', '15081468', '2011-09-20', 123, NULL, 'EFECTIVO', NULL),
(2431, 'HILDA ELY', 'TICONA', 'GABRIEL', 'Femenino', '81230285201612296', '15980531', '2011-11-30', 123, NULL, 'EFECTIVO', NULL),
(2432, 'ESTEFANI', 'MIRANDA', 'COLQUE', 'Femenino', '409000062016010', '16832917', '2010-12-02', 123, NULL, 'EFECTIVO', NULL),
(2433, 'LUZ KAREN', 'FLORES', 'CHURA', 'Femenino', '804802912017017', '15621664', '2012-12-16', 120, NULL, 'EFECTIVO', NULL),
(2434, 'RUBEN', 'MAMANI', 'GERONIMO', 'Masculino', '709000272016027', '14722471', '2010-05-16', 120, NULL, 'EFECTIVO', NULL),
(2435, 'ALAN LEONEL', 'CONDO', 'MORALES', 'Masculino', '4090000520261062', '1000000', '2013-04-12', 120, NULL, 'EFECTIVO', NULL),
(2436, 'ROSA GUADALUPE', 'CHOQUE', 'TERCEROS', 'Femenino', '808600472018001', '15722508', '2013-04-27', 121, NULL, 'EFECTIVO', NULL),
(2437, 'JHON', 'CHOQUE', 'FUENTES', 'Masculino', '809802352017015', '16765689', '2012-07-09', 121, NULL, 'EFECTIVO', NULL),
(2438, 'IVIS MASSIEL', 'IRIGOYEN', 'GARCIA', 'Femenino', '809804632016030', '15342404', '2011-01-11', 121, NULL, 'EFECTIVO', NULL),
(2439, 'GERALDINE ANAHI', 'RIOJA', 'PRADO', 'Femenino', '8098026320152522', '16281812', '2010-08-05', 121, NULL, 'EFECTIVO', NULL),
(2440, 'ABIGAIL', 'ROJAS', 'ESCOBAR', 'Femenino', '809805192016012', '13400486', '2011-04-20', 121, NULL, 'EFECTIVO', NULL),
(2441, 'DANNY JHORDAN', 'SOCOMPI', 'ROCHA', 'Masculino', '409000052018020', '16097294', '2012-11-20', 121, NULL, 'EFECTIVO', NULL),
(2442, 'RUBEN GAEL', 'LAIME', NULL, 'Masculino', '812301202019068', '15605816', '2014-01-06', 118, NULL, 'EFECTIVO', NULL),
(2443, 'ROSALIA', 'CABRERA', 'MONTECINOS', 'Femenino', '409000052019037', '16834521', '2013-07-17', 118, NULL, 'EFECTIVO', NULL),
(2444, 'JHOAN', 'CACERES', 'REVOLLO JHOAN', 'Masculino', '409000052019007', '15675110', '2013-11-02', 118, NULL, 'EFECTIVO', NULL),
(2445, 'JHASMANY', 'FLORES', 'CRUZ', 'Masculino', '409000222017013', '15500377', '2013-03-13', 118, NULL, 'EFECTIVO', NULL),
(2446, 'RUDY NEYMAR', 'MENECES', 'CARRILLO', 'Masculino', '409000052017057', NULL, '2011-11-26', 118, NULL, 'EFECTIVO', NULL),
(2447, 'SAYUMI', 'MURUCHI', 'IMPA', 'Femenino', '812301662017014', '15383063', '2013-02-19', 118, NULL, 'EFECTIVO', NULL),
(2448, 'ALEXIS  OZIEL', 'QUISPÉ', 'GONZALES', 'Masculino', '8098001720182404', '16694321', '2014-03-22', 118, NULL, 'EFECTIVO', NULL),
(2449, 'ESTRELLA NICOL', 'ROMAN', 'CORAJE', 'Femenino', '409000122018045', '15910667', '2014-04-27', 118, NULL, 'EFECTIVO', NULL),
(2450, 'DAEL ALBERTH', 'SOLA', 'COPA', 'Masculino', '409000272019046', '16104123', '2013-12-15', 118, NULL, 'EFECTIVO', NULL),
(2451, 'JHON ALEX', 'TECILLO', 'SANGRERI', 'Masculino', '409000052017005', '17572448', '2012-06-25', 118, NULL, 'EFECTIVO', NULL),
(2452, 'PETER EMANUEL', 'VENEGAS', 'LLANOS', 'Masculino', '609000242018018', '15124627', '2013-10-24', 118, NULL, 'EFECTIVO', NULL),
(2453, 'MARY YSABEL', 'CABEZAS', 'HUANCA', 'Femenino', '409000052019005', '16964026', '2011-06-20', 119, NULL, 'EFECTIVO', NULL),
(2454, 'JOSE LUIS', 'CALI', 'VELIZ', 'Masculino', '409000052017044', '14856734', '2011-08-11', 119, NULL, 'EFECTIVO', NULL),
(2455, 'ANGHELINA', 'ROSELIO', 'SOLIZ', 'Femenino', '619500172018045', '14285462', '2014-03-13', 119, NULL, 'EFECTIVO', NULL),
(2456, 'SANTIAGO', 'MIRANDA', 'COLQUE', 'Masculino', '409000062018004', '16832935', '2012-07-23', 119, NULL, 'EFECTIVO', NULL),
(2457, 'DIANA CONZUELO', 'SEJAS', 'CABRERA', 'Femenino', '809001102018003', '14383621', '2013-03-21', 119, NULL, 'EFECTIVO', NULL),
(2458, 'JHEYSON', 'SOLIZ', 'FERRUFINO', 'Masculino', '409000072020062', '16203662', '2014-06-13', 119, NULL, 'EFECTIVO', NULL),
(2459, 'MATIAS', 'CACERES', 'SALVADOR', 'Masculino', '4090003520237055', '16121423', '2019-06-10', 109, NULL, 'EFECTIVO', NULL),
(2460, 'JHONATAN', 'CHOQUE', 'ORDOÑEZ', 'Masculino', '6064000420232148', '16133240', '2019-05-26', 109, NULL, 'EFECTIVO', NULL),
(2461, 'MATTEO ANDRE', 'GONZALES', 'DELGADILLO', 'Masculino', '8089012720223360', '15318945', '2018-05-09', 109, NULL, 'EFECTIVO', NULL),
(2462, 'THAILY LUPE', 'JORGE', 'CHOQUE', 'Femenino', '8098056620237935', '15806769', '2019-01-09', 109, NULL, 'EFECTIVO', NULL),
(2463, 'HAROL', 'SALVATIERRA', 'ROCHA', 'Masculino', '4090000520235175', '15512468', '2018-11-16', 109, NULL, 'EFECTIVO', NULL),
(2464, 'WILLIAM', 'TECILLO', 'SANGRERI', 'Masculino', '4090000520212016', '15722208', '2015-08-05', 109, NULL, 'EFECTIVO', NULL),
(2465, 'ANTHONY LUCIANO', 'ROCHA', 'CONDORI', 'Masculino', '4090000520269171', NULL, '2019-01-22', 109, NULL, 'EFECTIVO', NULL),
(2466, 'JOSE ANTONIO', 'VILLARROEL', 'ROCHA', 'Masculino', '4090002720238214', NULL, '2018-05-29', 109, NULL, 'EFECTIVO', NULL),
(2467, 'KEVIN', 'CASILLA', 'UGARTE', 'Masculino', '409000052023751', '15719915', '2017-09-20', 110, NULL, 'EFECTIVO', NULL),
(2468, 'DILAN FABRICIO', 'FRANCO', 'GONZALES', 'Masculino', '409000352023761', '15750797', '2018-05-16', 110, NULL, 'EFECTIVO', NULL),
(2469, 'SHERLYN MONSERRAT', 'ANGULO', 'CARIDE', 'Femenino', '8223009720226652', '15554340', '2017-07-18', 111, NULL, 'EFECTIVO', NULL),
(2470, 'YASMIN HELEN', 'CABELLO', 'SOTO', 'Femenino', '4090000620229473', '15877462', '2017-12-13', 111, NULL, 'EFECTIVO', NULL),
(2471, 'IRIS ISAMARY', 'CONDE', 'REINAGA', 'Femenino', '8098003220227920', '15747183', '2018-04-12', 111, NULL, 'EFECTIVO', NULL),
(2472, 'JHEREMY', 'COPALI', 'CAMACHO', 'Masculino', '409000052022371A', '15916826', '2017-05-22', 111, NULL, 'EFECTIVO', NULL),
(2473, 'ANAHI', 'HUANCA', 'CHINO', 'Femenino', '407304882020041', '16231535', '2015-06-18', 111, NULL, 'EFECTIVO', NULL),
(2474, 'MADELEEN RASHEL', 'HUANCO', 'SAAVEDRA', 'Femenino', '4090000420228388', '16165250', '2017-09-28', 111, NULL, 'EFECTIVO', NULL),
(2475, 'SHAIRA GEORGINA', 'LOZA', 'PEREZ', 'Femenino', '8098056620221698', '15315006', '2017-09-19', 111, NULL, 'EFECTIVO', NULL),
(2476, 'HANSEL GAEL', 'SERNA', 'LUQUE', 'Masculino', '8054000320222848', '15181023', '2017-07-06', 111, NULL, 'EFECTIVO', NULL),
(2477, 'JHANDI', 'SILVESTRE', 'LEDEZMA', 'Femenino', '8098051920231237', '15793848', '2017-09-23', 111, NULL, 'EFECTIVO', NULL),
(2478, 'NIKEYRA GERALDINE', 'COCA', 'ROCHA', 'Femenino', '8090003720211702', '16998597', '2016-07-10', 112, NULL, 'EFECTIVO', NULL),
(2479, 'DENISE ASHELEN', 'GUZMAN', 'AYALA', 'Femenino', '4090000520222997', '14539695', '2016-07-02', 112, NULL, 'EFECTIVO', NULL),
(2480, 'NATALY', 'RIOS', 'PEÑA', 'Femenino', '8098011820229062', '16129207', '2017-05-30', 112, NULL, 'EFECTIVO', NULL),
(2481, 'ROUSS', 'ROJAS', 'REVOLLO', 'Femenino', '4090003520226474', '15675165', '2017-04-19', 112, NULL, 'EFECTIVO', NULL),
(2482, 'JHOEL', 'CALI', 'VELIZ', 'Masculino', '4090000520223629', '15693663', '2017-05-18', 113, NULL, 'EFECTIVO', NULL),
(2483, 'LEYDI', 'CHOQUE', 'ORDOÑEZ', 'Femenino', '6064000420215691', '14816543', '2016-10-21', 113, NULL, 'EFECTIVO', NULL),
(2484, 'AMELIA', 'PINTO', 'CORAJE', 'Femenino', '6090003520218424', '16648617', '2016-12-13', 113, NULL, 'EFECTIVO', NULL),
(2485, 'ISMAEL', 'ROJAS', 'ESCOBAR', 'Masculino', '809805192019128', NULL, '2014-05-08', 113, NULL, 'EFECTIVO', NULL),
(2486, 'REICHELT ANTONELA', 'LAIME', NULL, 'Femenino', '8123012020211625', '16558284', '2016-02-06', 114, NULL, 'EFECTIVO', NULL),
(2488, 'MARIA JOSE', 'CALDERON', 'MAMANI', 'Femenino', '809805532022576A', '16941095', '2016-05-31', 114, NULL, 'EFECTIVO', NULL),
(2489, 'ARLETH MADHELIE', 'CONDORI', 'LLANOS', 'Femenino', '609000242020043', '15943713', '2016-06-06', 114, NULL, 'EFECTIVO', NULL),
(2490, 'VISMAR', 'ESPINOZA', 'ALMANZA', 'Masculino', '4090000720212856', '15730996', '2015-09-18', 114, NULL, 'EFECTIVO', NULL),
(2491, 'JHESICA', 'FERNANDEZ', 'REVOLLO', 'Femenino', '4090000520218985', '14864930', '2016-06-14', 114, NULL, 'EFECTIVO', NULL),
(2492, 'JENNIFER BRIANA', 'TICONA', 'GABRIEL', 'Femenino', '812302852020009', '16660270', '2015-06-28', 114, NULL, 'EFECTIVO', NULL),
(2493, 'ALEXIS', 'ALVAREZ', 'HINOJOSA', 'Masculino', '4090000520218112', '16675398', '2015-07-21', 115, NULL, 'EFECTIVO', NULL),
(2494, 'LIZBETH YAMILIZ', 'CHOQUE', 'ORDOÑEZ', 'Femenino', '606400042020025', '14816575', '2015-09-11', 115, NULL, 'EFECTIVO', NULL),
(2495, 'ELMER RODRIGO', 'CUYO', 'TAQUICHIRI', 'Masculino', '809802352019069', '16461537', '2014-12-30', 115, NULL, 'EFECTIVO', NULL),
(2496, 'AMAIRANY', 'FERREL', 'QUIROZ', 'Femenino', '16461537', '15962981', '2016-04-12', 115, NULL, 'EFECTIVO', NULL),
(2497, 'MARISOL', 'ROJAS', 'ESCOBAR', 'Femenino', '809805192018104', NULL, '2012-12-24', 115, NULL, 'EFECTIVO', NULL),
(2498, 'LEIDY ARIANA', 'SOTO', 'BELTRAN', 'Femenino', '409000062020043', '14481800', '2016-03-16', 115, NULL, 'EFECTIVO', NULL),
(2499, 'ORIANA JENNIFER', 'TICONA', 'GABRIEL', 'Femenino', '812302852020008', '16660151', '2015-06-28', 115, NULL, 'EFECTIVO', NULL),
(2500, 'FIORELA NICCOL', 'ZURITA', 'RODRIGUEZ', 'Femenino', '809805072019029', '15218699', '2014-11-15', 115, NULL, 'EFECTIVO', NULL),
(2501, 'DIVER GROVER', 'CABEZAS', 'JUÑES', 'Masculino', '409000052020004', '15825962', '2015-04-20', 116, NULL, 'EFECTIVO', NULL),
(2502, 'CAMILA CELESTE', 'CORO', 'GUTIERREZ', 'Femenino', '409000062019048', '14775380', '2014-11-24', 116, NULL, 'EFECTIVO', NULL),
(2503, 'JHENIFER MELANI', 'CUENCA', 'CALDERON', 'Femenino', '809804662020046', '17008925', '2014-09-07', 116, NULL, 'EFECTIVO', NULL),
(2504, 'ADAN DANNER', 'IRIGOYEN', 'GARCIA', 'Masculino', '809804632020042', '17042992', '2015-01-07', 116, NULL, 'EFECTIVO', NULL),
(2505, 'KIMBERLY', 'LEDEZMA', 'ROCHA', 'Femenino', '809800852018101', '14697051', '2013-10-09', 116, NULL, 'EFECTIVO', NULL),
(2506, 'MONICA CONNIE', 'YUJRA', '', 'Femenino', '409000052021614', '14077795', '2014-09-11', 117, NULL, 'EFECTIVO', NULL),
(2507, 'SEBASTIAN MOISES', 'CABELLO', 'SOTO', 'Masculino', '409000042019022', '15877307', '2015-05-19', 117, NULL, 'EFECTIVO', NULL),
(2508, 'ERIK GEICO', 'DURAN', 'CORAJE', 'Masculino', '609000352019109', '14857472', '2015-05-02', 117, NULL, 'EFECTIVO', NULL),
(2509, 'BRISA KELLY', 'GUZMAN', 'ENCINAS', 'Femenino', '409000222020032', '17020901', '2014-09-26', 117, NULL, 'EFECTIVO', NULL),
(2510, 'SINDERLI', 'HUANCA', 'CHINO', 'Femenino', '407304882017016', '14801910', '2012-10-30', 117, NULL, 'EFECTIVO', NULL),
(2511, 'MATEO DAVID', 'APATA', 'ROJAS', 'Masculino', '4090000520235289', '14812501', '2012-06-04', 118, NULL, 'EFECTIVO', NULL);

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
(809, 'COMUNIDAD Y SOCIEDAD', 0, NULL, 0),
(810, 'COMUNIDAD Y SOCIEDAD, CIENCIA TECNOLOGIA Y PRODUCCION, VIDA TIERRA Y TERRITORIO, COSMOS Y PENSAMIENTO', 0, NULL, 0),
(811, 'VIDA TIERRA Y TERRITORIO', 0, NULL, 0),
(812, 'COSMOS Y PENSAMIENTO', 0, NULL, 0),
(813, 'LENGUAJE', 0, NULL, 0),
(814, 'CIENCIAS SOCIALES', 0, NULL, 0),
(815, 'EDUCACION FISICA Y DEPORTES', 0, NULL, 0),
(816, 'EDUCACION MUSICA', 0, NULL, 0),
(817, 'ARTES PLASTICAS', 0, NULL, 0),
(818, 'MATEMATICAS', 0, NULL, 0),
(819, 'TECNICA TECNOLOGIA', 0, NULL, 0),
(820, 'CIENCIAS NATURALES', 0, NULL, 0),
(821, 'VALORES ESPIRITUALIDAD Y RELIGIONES', 0, NULL, 0),
(822, 'LENGUAJE', 0, NULL, 0),
(823, 'LENGUA EXTRANGERA', 0, NULL, 0),
(824, 'CIENCIAS SOCIALES', 0, NULL, 0),
(825, 'EDUCACION FISICA Y DEPORTES', 0, NULL, 0),
(826, 'EDUCACION MUSICA', 0, NULL, 0),
(827, 'ARTES PLASTICAS', 0, NULL, 0),
(828, 'MATEMATICAS', 0, NULL, 0),
(829, 'TECNICA TECNOLOGIA GENERAL', 0, NULL, 0),
(830, 'CIENCIAS NATURALES BIOLOGIA GEOGRAFIA', 0, NULL, 0),
(831, 'COSMOVISIONES, FILOSOFIA Y SICOLOGIA', 0, NULL, 0),
(832, 'VALORES ESPIRITUALIDAD Y RELIGIONES', 0, NULL, 0),
(833, 'LENGUAJE', 0, NULL, 0),
(834, 'LENGUA EXTRANJERA', 0, NULL, 0),
(835, 'CIENCIAS SOCIALES', 0, NULL, 0),
(836, 'EDUCACION FISICA Y DEPORTES', 0, NULL, 0),
(837, 'EDUCACION MUSICA', 0, NULL, 0),
(838, 'ARTES PLASTICAS', 0, NULL, 0),
(839, 'MATEMATICAS', 0, NULL, 0),
(840, 'TECNICA TECNOLOGIA GENERAL', 0, NULL, 0),
(841, 'CIENCIAS NATURALES BIOLOGIA GEOGRAFIA', 0, NULL, 0),
(842, 'COSMOVISIONES, FILOSOFIA Y SICOLOGIA', 0, NULL, 0),
(843, 'VALORES ESPIRITUALIDAD Y RELIGIONES', 0, NULL, 0),
(844, 'CIENCIAS NATURALES', 1, 841, 0),
(845, 'FISICA', 1, 841, 0),
(846, 'QUIMICA', 1, 841, 0),
(847, 'CIENCIAS NATURALES', 1, 841, 0),
(848, 'FISICA', 1, 841, 0),
(849, 'QUIMICA', 1, 841, 0),
(850, 'LENGUA EXTRANJERA', 0, NULL, 0),
(851, 'CIENCIAS SOCIALES', 0, NULL, 0),
(852, 'EDUCACION FISICA Y DEPORTES', 0, NULL, 0),
(853, 'EDUCACION MUSICA', 0, NULL, 0),
(854, 'ARTES PLASTICAS', 0, NULL, 0),
(855, 'MATEMATICAS', 0, NULL, 0),
(856, 'TECNICA TECNOLOGIA GENERAL', 0, NULL, 0),
(857, 'CIENCIAS NATURALES BIOLOGIA GEOGRAFIA', 0, NULL, 0),
(858, 'COSMOVISIONES, FILOSOFIA Y SICOLOGIA', 0, NULL, 0),
(859, 'VALORES ESPIRITUALIDAD Y RELIGIONES', 0, NULL, 0),
(860, 'CONTABILIDAD', 0, NULL, 1),
(861, 'TRANSFORMACION DE ALIMENTOS', 0, NULL, 1),
(862, 'CIENCIAS NATURALES', 1, 857, 0),
(863, 'FISICA', 1, 857, 0),
(864, 'QUIMICA', 1, 857, 0),
(865, 'LENGUAJE', 0, NULL, 0),
(866, 'LENGUAJE', 0, NULL, 0),
(867, 'LENGUA EXTRANJERA', 0, NULL, 0),
(868, 'CIENCIAS SOCIALES', 0, NULL, 0),
(869, 'EDUCACION FISICA Y DEPORTES', 0, NULL, 0),
(870, 'EDUCACION MUSICA', 0, NULL, 0),
(871, 'ARTES PLASTICAS', 0, NULL, 0),
(872, 'MATEMATICAS', 0, NULL, 0),
(873, 'TECNICA TECNOLOGIA GENERAL', 0, NULL, 0),
(874, 'CONTABILIDAD', 0, NULL, 1),
(875, 'TRANSFORMACION DE ALIMENTOS', 0, NULL, 1),
(876, 'CIENCIAS NATURALES BIOLOGIA GEOGRAFIA', 0, NULL, 0),
(877, 'FISICA', 0, NULL, 0),
(878, 'QUIMICA', 0, NULL, 0),
(879, 'COSMOVISIONES, FILOSOFIA Y SICOLOGIA', 0, NULL, 0),
(880, 'VALORES ESPIRITUALIDAD Y RELIGIONES', 0, NULL, 0),
(881, 'LENGUAJE', 0, NULL, 0),
(882, 'LENGUA EXTRANJERA', 0, NULL, 0),
(883, 'CIENCIAS SOCIALES', 0, NULL, 0),
(884, 'EDUCACION FISICA Y DEPORTES', 0, NULL, 0),
(885, 'EDUCACION MUSICA', 0, NULL, 0),
(886, 'ARTES PLASTICAS', 0, NULL, 0),
(887, 'MATEMATICAS', 0, NULL, 0),
(888, 'TECNICA TECNOLOGIA ESPECIALIZADA-TRANSFORMACION DE ALIMENTOS', 0, NULL, 0),
(889, 'CIENCIAS NATURALES BIOLOGIA GEOGRAFIA', 0, NULL, 0),
(890, 'FISICA', 0, NULL, 0),
(891, 'QUIMICA', 0, NULL, 0),
(892, 'COSMOVISIONES, FILOSOFIA Y SICOLOGIA', 0, NULL, 0),
(893, 'VALORES ESPIRITUALIDAD Y RELIGIONES', 0, NULL, 0),
(894, 'LENGUAJE', 0, NULL, 0),
(895, 'LENGUA EXTRANJERA', 0, NULL, 0),
(896, 'CIENCIAS SOCIALES', 0, NULL, 0),
(897, 'EDUCACION FISICA Y DEPORTES', 0, NULL, 0),
(898, 'EDUCACION MUSICA', 0, NULL, 0),
(899, 'ARTES PLASTICAS', 0, NULL, 0),
(900, 'MATEMATICAS', 0, NULL, 0),
(901, 'TECNICA TECNOLOGIA ESPECIALIZADA-CONTABILIDAD', 0, NULL, 0),
(902, 'CIENCIAS NATURALES BIOLOGIA GEOGRAFIA', 0, NULL, 0),
(903, 'FISICA', 0, NULL, 0),
(904, 'QUIMICA', 0, NULL, 0),
(905, 'COSMOVISIONES, FILOSOFIA Y SICOLOGIA', 0, NULL, 0),
(906, 'VALORES ESPIRITUALIDAD Y RELIGIONES', 0, NULL, 0),
(907, 'LENGUAJE', 0, NULL, 0),
(908, 'LENGUA EXTRANJERA', 0, NULL, 0),
(909, 'CIENCIAS SOCIALES', 0, NULL, 0),
(910, 'EDUCACION FISICA Y DEPORTES', 0, NULL, 0),
(911, 'EDUCACION MUSICA', 0, NULL, 0),
(912, 'ARTES PLASTICAS', 0, NULL, 0),
(913, 'MATEMATICAS', 0, NULL, 0),
(914, 'TECNICA TECNOLOGIA ESPECIALIZADA-TRANSFORMACION DE ALIMENTOS', 0, NULL, 0),
(915, 'CIENCIAS NATURALES BIOLOGIA GEOGRAFIA', 0, NULL, 0),
(916, 'FISICA', 0, NULL, 0),
(917, 'QUIMICA', 0, NULL, 0),
(918, 'COSMOVISIONES, FILOSOFIA Y SICOLOGIA', 0, NULL, 0),
(919, 'VALORES ESPIRITUALIDAD Y RELIGIONES', 0, NULL, 0),
(920, 'HISTORIA', 1, 909, 0),
(921, 'GEOGRAFIA', 1, 909, 0),
(922, 'LENGUAJE', 0, NULL, 0),
(923, 'LENGUA EXTRANJERA', 0, NULL, 0),
(924, 'CIENCIAS SOCIALES', 0, NULL, 0),
(925, 'EDUCACION FISICA Y DEPORTES', 0, NULL, 0),
(926, 'EDUCACION MUSICA', 0, NULL, 0),
(927, 'ARTES PLASTICAS', 0, NULL, 0),
(928, 'MATEMATICAS', 0, NULL, 0),
(929, 'TECNICA TECNOLOGIA ESPECIALIZADA-CONTABILIDAD', 0, NULL, 0),
(930, 'CIENCIAS NATURALES BIOLOGIA GEOGRAFIA', 0, NULL, 0),
(931, 'FISICA', 0, NULL, 0),
(932, 'QUIMICA', 0, NULL, 0),
(933, 'COSMOVISIONES, FILOSOFIA Y SICOLOGIA', 0, NULL, 0),
(934, 'VALORES ESPIRITUALIDAD Y RELIGIONES', 0, NULL, 0),
(935, 'HISTORIA', 1, 924, 0),
(936, 'GEOGRAFIA', 1, 924, 0),
(937, 'LENGUAJE', 0, NULL, 0),
(938, 'LENGUA EXTRANJERA', 0, NULL, 0),
(939, 'CIENCIAS SOCIALES', 0, NULL, 0),
(940, 'EDUCACION FISICA Y DEPORTES', 0, NULL, 0),
(941, 'EDUCACION MUSICA', 0, NULL, 0),
(942, 'ARTES PLASTICAS', 0, NULL, 0),
(943, 'MATEMATICAS', 0, NULL, 0),
(944, 'TECNICA TECNOLOGIA ESPECIALIZADA-TRANSFORMACION DE ALIMENTOS', 0, NULL, 0),
(945, 'CIENCIAS NATURALES BIOLOGIA GEOGRAFIA', 0, NULL, 0),
(946, 'FISICA', 0, NULL, 0),
(947, 'QUIMICA', 0, NULL, 0),
(948, 'COSMOVISIONES, FILOSOFIA Y SICOLOGIA', 0, NULL, 0),
(949, 'VALORES ESPIRITUALIDAD Y RELIGIONES', 0, NULL, 0),
(950, 'HISTORIA', 1, 939, 0),
(951, 'GEOGRAFIA', 1, 939, 0),
(952, 'LENGUAJE', 0, NULL, 0),
(953, 'LENGUA EXTRANJERA', 0, NULL, 0),
(954, 'CIENCIAS SOCIALES', 0, NULL, 0),
(955, 'EDUCACION FISICA Y DEPORTES', 0, NULL, 0),
(956, 'EDUCACION MUSICA', 0, NULL, 0),
(957, 'ARTES PLASTICAS', 0, NULL, 0),
(958, 'MATEMATICAS', 0, NULL, 0),
(959, 'TECNICA TECNOLOGIA ESPECIALIZADA-CONTABILIDAD', 0, NULL, 0),
(960, 'CIENCIAS NATURALES BIOLOGIA GEOGRAFIA', 0, NULL, 0),
(961, 'FISICA', 0, NULL, 0),
(962, 'QUIMICA', 0, NULL, 0),
(963, 'COSMOVISIONES, FILOSOFIA Y SICOLOGIA', 0, NULL, 0),
(964, 'VALORES ESPIRITUALIDAD Y RELIGIONES', 0, NULL, 0),
(965, 'HISTORIA', 1, 954, 0),
(966, 'GEOGRAFIA', 1, 954, 0),
(967, 'INGLES', 0, NULL, 1),
(968, 'Computacion', 0, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias_complementarias`
--

CREATE TABLE `materias_complementarias` (
  `id` int(11) NOT NULL,
  `id_materia_principal` int(11) NOT NULL,
  `id_materia_complementaria` int(11) NOT NULL,
  `porcentaje_transferencia` decimal(5,2) NOT NULL DEFAULT 5.00,
  `gestion` varchar(9) NOT NULL DEFAULT '' COMMENT 'Vacío = aplica a todas las gestiones',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `parciales_etiquetas_actividades`
--

CREATE TABLE `parciales_etiquetas_actividades` (
  `id_materia` int(11) NOT NULL,
  `id_periodo_evaluacion` int(11) NOT NULL,
  `area` enum('SER','SABER','HACER') NOT NULL,
  `indice` tinyint(3) UNSIGNED NOT NULL,
  `etiqueta` varchar(120) NOT NULL DEFAULT '',
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `parciales_etiquetas_actividades`
--

INSERT INTO `parciales_etiquetas_actividades` (`id_materia`, `id_periodo_evaluacion`, `area`, `indice`, `etiqueta`, `actualizado_en`) VALUES
(967, 2, 'SER', 1, 'tareas de mate', '2026-04-10 18:50:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `periodos_evaluacion`
--

CREATE TABLE `periodos_evaluacion` (
  `id_periodo_evaluacion` int(11) NOT NULL,
  `gestion` varchar(9) NOT NULL,
  `trimestre` tinyint(4) NOT NULL,
  `parcial` tinyint(4) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `esta_activo` tinyint(1) DEFAULT 0,
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `periodos_evaluacion`
--

INSERT INTO `periodos_evaluacion` (`id_periodo_evaluacion`, `gestion`, `trimestre`, `parcial`, `nombre`, `fecha_inicio`, `fecha_fin`, `esta_activo`, `fecha_modificacion`) VALUES
(1, '2026', 1, 1, '1er Trimestre - 1er Parcial', '2026-03-17', '2026-12-31', 1, '2026-04-05 14:24:55'),
(2, '2026', 1, 2, '1er Trimestre - 2do Parcial', '2026-03-17', '2026-12-31', 1, '2026-04-09 23:33:07'),
(3, '2026', 1, 3, '1er Trimestre - 3er Parcial', '2026-03-17', '2026-12-31', 1, '2026-04-10 18:50:49'),
(4, '2026', 2, 1, '2do Trimestre - 1er Parcial', '2026-03-17', '2026-12-31', 0, '2026-04-05 14:24:50'),
(5, '2026', 2, 2, '2do Trimestre - 2do Parcial', '2026-03-17', '2026-12-31', 0, '2026-04-05 14:24:50'),
(6, '2026', 2, 3, '2do Trimestre - 3er Parcial', '2026-03-17', '2026-12-31', 0, '2026-04-05 14:24:50'),
(7, '2026', 3, 1, '3er Trimestre - 1er Parcial', '2026-03-17', '2026-12-31', 0, '2026-03-17 04:50:12'),
(8, '2026', 3, 2, '3er Trimestre - 2do Parcial', '2026-03-17', '2026-12-31', 0, '2026-03-17 04:50:12'),
(9, '2026', 3, 3, '3er Trimestre - 3er Parcial', '2026-03-17', '2026-12-31', 0, '2026-03-17 04:50:12');

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
  `password` varchar(255) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=habilitado, 0=inhabilitado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `personal`
--

INSERT INTO `personal` (`id_personal`, `nombres`, `apellidos`, `celular`, `carnet_identidad`, `id_rol`, `password`, `estado`) VALUES
(1, 'Juan', 'Pérez', '789456123', '1234567', 1, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(703, 'CLAUDIA ROMINA', 'ACHA AUCA', '76960279', '7920844', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(704, 'GABY EUGENIA', 'ARISPE GONZALES', '71711440', '3773503', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(705, 'NATIVIDAD MILENKA', 'AYALA PACHECO', '71443440', '6405399', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(706, 'SUSI SOLEDAD', 'CALLE CALLE', '67682116', '5284717', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(707, 'JUAN', 'CARO COCA', '65343738', '661005', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(709, 'RITA', 'CESPEDES JAIMES', '77976614', '6508642', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(710, 'LORENZO', 'CHONONO MOCORO', '67512155', '1738638', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(711, 'JUAN CARLOS', 'ECHEVERRIA CLAURE', '76959516', '3794225', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(712, 'CECILIA', 'ENCINAS BUSTAMANTE', '69468979', '3771879', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(713, 'AMANDA LUZ', 'ESPINOZA MONTAÑO', '72775025', '13417216', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(714, 'JUSTA', 'GARCIA PEÑARRIETA', '77424540', '987868', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(715, 'MARIA TERESA', 'GOYTIA SERRANO', '77491158', '3089987', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(716, 'JOSE LUIS', 'GRANADO VALVERDE', '70370328', '3569867', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(717, 'DAYANA', 'GUTIERREZ VIRREYRA', 'sn', '5746100', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(718, 'NORKA VILMA', 'MALDONADO ROCHA', '79796319', '3078015', 3, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(719, 'SILVIA EUGENIA', 'MAMANI HUARACHI', '74300367', '7964434', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(720, 'ESTHER', 'MAMANI QUISPE', '72769455', '4472153', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(721, 'YERSON', 'MENDOZA REA', '74357351', '8718840', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(722, 'NIRUSBA PATRICIA', 'MERIDA PEREZ', '70796077', '3539428', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(723, 'JHERSON HAROL', 'MERIDA ACUÑA', '76468926', '8059677', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(724, 'MARIA VALENTINA', 'BERRIOS', '71438214', '4288712', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(725, 'DELIA', 'MOLINA RICALDI', '65333215', '3510957', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(726, 'ARACELY', 'MONTERO HERBAS', '79732128', '7908874', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(727, 'OSEAS', 'MORALES ARZE', '76484846', '5183181', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(728, 'CAROL', 'ORELLANA FERNANDEZ', '67578688', '5218074', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(729, 'GINA', 'PACHECO BALTAZAR', '72290566', '4078354', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(730, 'JANNETTE', 'PADILLA ARAUCO', '76933282', '6405198', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(731, 'SANDRA LAURA', 'PEREDO FERRUFINO', '79350801', '3739743', 1, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(732, 'ELENA MARCIA', 'PEREDO VEZAGA', '60798005', '4194441', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(733, 'GLADYS', 'PINAYA LUNA', '72768461', '3520974', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(734, 'VALERIANO', 'PINAYA FERNANDEZ', '73759910', '3506935', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(735, 'HENRY ALBER', 'RIVERO SEJAS', '70756019', '5311823', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(736, 'GERVAN', 'ROJAS PALMA', '71404750', '4498768', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(737, 'ELY', 'ROMAN ACHA', '79998614', '6484436', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(738, 'JHANNET', 'ROSALES VEIZAGA', '76455237', '4406919', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(739, 'JHOVANA DARMA', 'ROSSEL COLQUE', '77943572', '3977199', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(740, 'ERIKA', 'SALAZAR VIDAURRE', '60709667', '6546457', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(741, 'RILMA', 'SALAZAR ANTEZANA', '79709788', '3027702', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(742, 'PAOLA MARCELA', 'SIÑANIZ ZAMBRANA', '72263293', '3793276', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(743, 'SIMÓN', 'SORIA ROQUE', '60381444', '956523', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(744, 'ELIAZAR', 'TORRICO ESTEVES', '74829105', '7980872', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(745, 'Nicole', 'Coca', '61599280', '64787151', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(746, 'ISAAC', 'URIONA LÓPEZ', '73772439', '5376554', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(747, 'ALICIA', 'VALENZUELA VALERIO', '79957041', '5920138', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(748, 'MARIA CLEOFE', 'VASQUEZ TORRICO', '67199510', '3147456', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(749, 'MARTHA', 'VASQUEZ CLAROS', '71406477', '4395770', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(750, 'LISSET TANIA', 'GARCÍA', '72740022', '6400440', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(751, 'MERY MAGDALENA', 'MALLCU FLORES', '72077312', '4754861', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(752, 'LOURDES', 'MAMANI', '71486397', '8746602', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(753, 'EDWIN DIEGO', 'JIMENEZ', '74839607', '2903737', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(754, 'jimi', 'torrico', '76777897', '5218800', 1, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(755, 'CLAUDIA', 'SELAYA', '70391543', '8049284', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(756, 'Mauricio', 'Rocha', '62665104', '62665104', 2, '$2y$10$8WFqNqysavjpzFKw.upO9eIYbADNserocTS.vbEY3k3B9ltJJCB5q', 1),
(757, 'testj', 'testj', '76444444', '5219900', 2, '$2y$10$y4rasfY.p/.3z3NWaSSc5exPxGEQZ1GuOFYsxW6k7Fir13reaV1H2', 1);

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
(1012, 733, 945, 'CARGADO'),
(1015, 743, 943, 'CARGADO'),
(1020, 710, 949, 'CARGADO'),
(1021, 715, 914, 'CARGADO'),
(1025, 750, 928, 'CARGADO'),
(1029, 705, 935, 'CARGADO'),
(1033, 743, 958, 'CARGADO'),
(1034, 710, 964, 'CARGADO'),
(1035, 747, 960, 'CARGADO'),
(1036, 747, 963, 'CARGADO'),
(1037, 747, 957, 'CARGADO'),
(1038, 747, 959, 'CARGADO'),
(1039, 747, 956, 'CARGADO'),
(1040, 747, 961, 'CARGADO'),
(1041, 747, 962, 'CARGADO'),
(1042, 743, 973, 'CARGADO'),
(1043, 710, 979, 'CARGADO'),
(1044, 739, 975, 'CARGADO'),
(1045, 739, 978, 'CARGADO'),
(1046, 739, 972, 'CARGADO'),
(1047, 739, 974, 'CARGADO'),
(1048, 739, 971, 'CARGADO'),
(1049, 739, 976, 'CARGADO'),
(1050, 716, 977, 'CARGADO'),
(1051, 743, 988, 'CARGADO'),
(1053, 704, 990, 'CARGADO'),
(1054, 704, 993, 'CARGADO'),
(1055, 704, 987, 'CARGADO'),
(1056, 704, 989, 'CARGADO'),
(1057, 704, 986, 'CARGADO'),
(1058, 704, 991, 'CARGADO'),
(1059, 716, 992, 'CARGADO'),
(1060, 743, 1003, 'CARGADO'),
(1061, 710, 1009, 'CARGADO'),
(1062, 730, 1005, 'CARGADO'),
(1063, 730, 1008, 'CARGADO'),
(1064, 730, 1002, 'CARGADO'),
(1065, 730, 1004, 'CARGADO'),
(1066, 730, 1001, 'CARGADO'),
(1067, 730, 1006, 'CARGADO'),
(1068, 716, 1007, 'CARGADO'),
(1069, 729, 1020, 'CARGADO'),
(1070, 743, 1018, 'CARGADO'),
(1072, 729, 1023, 'CARGADO'),
(1073, 729, 1017, 'CARGADO'),
(1074, 729, 1019, 'CARGADO'),
(1075, 729, 1016, 'CARGADO'),
(1076, 729, 1021, 'CARGADO'),
(1077, 716, 1022, 'CARGADO'),
(1078, 734, 1031, 'CARGADO'),
(1079, 707, 1032, 'CARGADO'),
(1080, 743, 1033, 'CARGADO'),
(1081, 753, 1034, 'CARGADO'),
(1082, 707, 1035, 'CARGADO'),
(1083, 753, 1036, 'CARGADO'),
(1084, 753, 1037, 'CARGADO'),
(1085, 736, 1038, 'CARGADO'),
(1086, 724, 1039, 'CARGADO'),
(1087, 712, 1046, 'CARGADO'),
(1088, 707, 1047, 'CARGADO'),
(1089, 743, 1048, 'CARGADO'),
(1090, 716, 1049, 'CARGADO'),
(1091, 707, 1050, 'CARGADO'),
(1092, 736, 1051, 'CARGADO'),
(1093, 716, 1052, 'CARGADO'),
(1094, 753, 1053, 'CARGADO'),
(1095, 724, 1054, 'CARGADO'),
(1096, 712, 1061, 'CARGADO'),
(1097, 707, 1062, 'CARGADO'),
(1098, 743, 1063, 'CARGADO'),
(1099, 736, 1064, 'CARGADO'),
(1100, 707, 1065, 'CARGADO'),
(1101, 736, 1066, 'CARGADO'),
(1102, 716, 1067, 'CARGADO'),
(1103, 736, 1068, 'CARGADO'),
(1104, 724, 1069, 'CARGADO'),
(1105, 734, 1076, 'CARGADO'),
(1106, 707, 1077, 'CARGADO'),
(1107, 743, 1078, 'CARGADO'),
(1108, 716, 1079, 'CARGADO'),
(1109, 707, 1080, 'CARGADO'),
(1110, 753, 1081, 'CARGADO'),
(1111, 753, 1082, 'CARGADO'),
(1112, 753, 1083, 'CARGADO'),
(1113, 724, 1084, 'CARGADO'),
(1114, 734, 1091, 'CARGADO'),
(1115, 707, 1092, 'CARGADO'),
(1116, 743, 1093, 'CARGADO'),
(1118, 707, 1095, 'CARGADO'),
(1119, 753, 1096, 'CARGADO'),
(1122, 724, 1099, 'CARGADO'),
(1123, 712, 1106, 'CARGADO'),
(1124, 707, 1107, 'CARGADO'),
(1125, 743, 1108, 'CARGADO'),
(1126, 716, 1109, 'CARGADO'),
(1127, 707, 1110, 'CARGADO'),
(1128, 736, 1111, 'CARGADO'),
(1129, 716, 1112, 'CARGADO'),
(1130, 753, 1113, 'CARGADO'),
(1131, 724, 1114, 'CARGADO'),
(1132, 744, 1140, 'CARGADO'),
(1133, 714, 1137, 'CARGADO'),
(1134, 719, 1147, 'CARGADO'),
(1136, 723, 1139, 'CARGADO'),
(1137, 722, 1136, 'CARGADO'),
(1138, 749, 1135, 'CARGADO'),
(1139, 738, 1141, 'CARGADO'),
(1140, 720, 1142, 'CARGADO'),
(1141, 706, 1144, 'CARGADO'),
(1142, 706, 1146, 'CARGADO'),
(1143, 744, 1145, 'CARGADO'),
(1145, 722, 1148, 'CARGADO'),
(1146, 709, 1149, 'CARGADO'),
(1147, 722, 1150, 'CARGADO'),
(1148, 732, 1151, 'CARGADO'),
(1149, 743, 1152, 'CARGADO'),
(1150, 723, 1153, 'CARGADO'),
(1151, 744, 1154, 'CARGADO'),
(1152, 738, 1155, 'CARGADO'),
(1153, 720, 1156, 'CARGADO'),
(1155, 744, 1159, 'CARGADO'),
(1156, 706, 1160, 'CARGADO'),
(1157, 721, 1161, 'CARGADO'),
(1158, 724, 1162, 'CARGADO'),
(1159, 749, 1193, 'CARGADO'),
(1160, 722, 1163, 'CARGADO'),
(1161, 714, 1164, 'CARGADO'),
(1162, 740, 1165, 'CARGADO'),
(1163, 723, 1166, 'CARGADO'),
(1164, 720, 1167, 'CARGADO'),
(1165, 728, 1168, 'CARGADO'),
(1166, 720, 1169, 'CARGADO'),
(1167, 706, 1175, 'CARGADO'),
(1168, 744, 1176, 'CARGADO'),
(1169, 706, 1177, 'CARGADO'),
(1170, 719, 1171, 'CARGADO'),
(1171, 725, 1172, 'CARGADO'),
(1172, 749, 1194, 'CARGADO'),
(1173, 722, 1178, 'CARGADO'),
(1174, 727, 1179, 'CARGADO'),
(1175, 740, 1180, 'CARGADO'),
(1176, 723, 1181, 'CARGADO'),
(1177, 720, 1182, 'CARGADO'),
(1178, 728, 1183, 'CARGADO'),
(1179, 720, 1184, 'CARGADO'),
(1180, 706, 1190, 'CARGADO'),
(1181, 744, 1191, 'CARGADO'),
(1182, 706, 1192, 'CARGADO'),
(1183, 719, 1186, 'CARGADO'),
(1184, 725, 1187, 'CARGADO'),
(1185, 726, 1195, 'CARGADO'),
(1186, 722, 1196, 'CARGADO'),
(1187, 714, 1197, 'CARGADO'),
(1188, 740, 1198, 'CARGADO'),
(1189, 723, 1199, 'CARGADO'),
(1190, 720, 1200, 'CARGADO'),
(1191, 728, 1201, 'CARGADO'),
(1192, 721, 1202, 'CARGADO'),
(1193, 741, 1205, 'CARGADO'),
(1194, 744, 1206, 'CARGADO'),
(1195, 706, 1207, 'CARGADO'),
(1196, 719, 1208, 'CARGADO'),
(1197, 724, 1209, 'CARGADO'),
(1198, 749, 1210, 'CARGADO'),
(1199, 722, 1211, 'CARGADO'),
(1200, 732, 1212, 'CARGADO'),
(1201, 740, 1213, 'CARGADO'),
(1202, 723, 1214, 'CARGADO'),
(1203, 720, 1215, 'CARGADO'),
(1204, 728, 1216, 'CARGADO'),
(1205, 703, 1217, 'CARGADO'),
(1207, 744, 1221, 'CARGADO'),
(1208, 706, 1222, 'CARGADO'),
(1209, 719, 1223, 'CARGADO'),
(1211, 703, 1225, 'CARGADO'),
(1212, 722, 1226, 'CARGADO'),
(1213, 711, 1227, 'CARGADO'),
(1214, 740, 1228, 'CARGADO'),
(1215, 723, 1229, 'CARGADO'),
(1216, 728, 1231, 'CARGADO'),
(1217, 752, 1232, 'CARGADO'),
(1218, 741, 1233, 'CARGADO'),
(1219, 744, 1234, 'CARGADO'),
(1220, 706, 1235, 'CARGADO'),
(1221, 721, 1236, 'CARGADO'),
(1222, 724, 1237, 'CARGADO'),
(1223, 720, 1230, 'CARGADO'),
(1224, 726, 1238, 'CARGADO'),
(1225, 722, 1239, 'CARGADO'),
(1226, 711, 1240, 'CARGADO'),
(1227, 740, 1241, 'CARGADO'),
(1228, 723, 1242, 'CARGADO'),
(1229, 720, 1243, 'CARGADO'),
(1230, 728, 1244, 'CARGADO'),
(1232, 741, 1246, 'CARGADO'),
(1233, 744, 1247, 'CARGADO'),
(1234, 706, 1248, 'CARGADO'),
(1235, 721, 1249, 'CARGADO'),
(1236, 724, 1250, 'CARGADO'),
(1237, 703, 1251, 'CARGADO'),
(1238, 722, 1252, 'CARGADO'),
(1239, 714, 1254, 'CARGADO'),
(1240, 711, 1255, 'CARGADO'),
(1241, 740, 1256, 'CARGADO'),
(1242, 723, 1257, 'CARGADO'),
(1243, 746, 1258, 'CARGADO'),
(1244, 738, 1259, 'CARGADO'),
(1245, 751, 1260, 'CARGADO'),
(1246, 741, 1261, 'CARGADO'),
(1247, 744, 1262, 'CARGADO'),
(1248, 706, 1263, 'CARGADO'),
(1249, 748, 1264, 'CARGADO'),
(1250, 724, 1265, 'CARGADO'),
(1251, 726, 1266, 'CARGADO'),
(1252, 722, 1267, 'CARGADO'),
(1253, 714, 1269, 'CARGADO'),
(1254, 727, 1270, 'CARGADO'),
(1255, 740, 1271, 'CARGADO'),
(1256, 723, 1272, 'CARGADO'),
(1257, 746, 1273, 'CARGADO'),
(1258, 738, 1274, 'CARGADO'),
(1259, 737, 1275, 'CARGADO'),
(1260, 741, 1276, 'CARGADO'),
(1261, 744, 1277, 'CARGADO'),
(1262, 706, 1278, 'CARGADO'),
(1263, 748, 1279, 'CARGADO'),
(1264, 724, 1280, 'CARGADO'),
(1265, 749, 1281, 'CARGADO'),
(1266, 722, 1282, 'CARGADO'),
(1267, 714, 1284, 'CARGADO'),
(1268, 711, 1285, 'CARGADO'),
(1269, 740, 1286, 'CARGADO'),
(1270, 723, 1287, 'CARGADO'),
(1271, 746, 1288, 'CARGADO'),
(1272, 738, 1289, 'CARGADO'),
(1273, 751, 1290, 'CARGADO'),
(1274, 741, 1291, 'CARGADO'),
(1275, 744, 1292, 'CARGADO'),
(1276, 706, 1293, 'CARGADO'),
(1277, 748, 1294, 'CARGADO'),
(1278, 724, 1295, 'CARGADO'),
(1279, 726, 1296, 'CARGADO'),
(1280, 722, 1297, 'CARGADO'),
(1281, 714, 1299, 'CARGADO'),
(1282, 727, 1300, 'CARGADO'),
(1283, 740, 1301, 'CARGADO'),
(1284, 723, 1302, 'CARGADO'),
(1285, 738, 1304, 'CARGADO'),
(1286, 737, 1305, 'CARGADO'),
(1287, 741, 1306, 'CARGADO'),
(1288, 744, 1307, 'CARGADO'),
(1289, 706, 1308, 'CARGADO'),
(1290, 748, 1309, 'CARGADO'),
(1291, 724, 1310, 'CARGADO'),
(1292, 746, 1303, 'CARGADO'),
(1293, 755, 1311, 'CARGADO'),
(1294, 755, 1312, 'CARGADO'),
(1295, 755, 1313, 'CARGADO'),
(1296, 755, 1314, 'CARGADO'),
(1297, 755, 1315, 'CARGADO'),
(1298, 755, 1316, 'CARGADO'),
(1299, 755, 1317, 'CARGADO'),
(1300, 755, 1318, 'CARGADO'),
(1301, 755, 1319, 'CARGADO'),
(1302, 755, 1320, 'CARGADO'),
(1303, 755, 1321, 'CARGADO'),
(1304, 755, 1322, 'CARGADO'),
(1305, 713, 1173, 'CARGADO'),
(1307, 713, 1188, 'CARGADO'),
(1308, 745, 1189, 'CARGADO'),
(1309, 713, 1203, 'CARGADO'),
(1310, 745, 1204, 'CARGADO'),
(1311, 713, 1218, 'CARGADO'),
(1312, 745, 1219, 'CARGADO'),
(1313, 743, 1138, 'CARGADO'),
(1314, 710, 994, 'CARGADO'),
(1315, 710, 1024, 'CARGADO'),
(1317, 756, 1326, 'CARGADO'),
(1318, 756, 1327, 'CARGADO'),
(1319, 756, 1328, 'CARGADO'),
(1320, 756, 1329, 'CARGADO'),
(1321, 756, 1330, 'CARGADO'),
(1322, 756, 1331, 'CARGADO'),
(1323, 756, 1333, 'CARGADO'),
(1324, 756, 1334, 'CARGADO'),
(1325, 756, 1335, 'CARGADO'),
(1326, 756, 1336, 'CARGADO'),
(1327, 756, 1337, 'CARGADO'),
(1328, 756, 1338, 'CARGADO'),
(1329, 756, 1339, 'CARGADO'),
(1330, 756, 1340, 'CARGADO'),
(1331, 756, 1341, 'CARGADO'),
(1332, 756, 1342, 'CARGADO'),
(1333, 756, 1343, 'CARGADO'),
(1334, 756, 1344, 'CARGADO'),
(1335, 706, 1158, 'CARGADO'),
(1336, 741, 1220, 'CARGADO'),
(1337, 735, 1245, 'CARGADO'),
(1338, 724, 1224, 'CARGADO'),
(1339, 733, 948, 'CARGADO'),
(1340, 733, 942, 'CARGADO'),
(1341, 733, 944, 'CARGADO'),
(1342, 733, 941, 'CARGADO'),
(1343, 733, 946, 'CARGADO'),
(1344, 733, 947, 'CARGADO'),
(1345, 745, 1174, 'CARGADO'),
(1346, 736, 1098, 'CARGADO'),
(1347, 716, 1094, 'CARGADO'),
(1348, 716, 1097, 'CARGADO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `responsables`
--

CREATE TABLE `responsables` (
  `id_responsable` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `apellido` varchar(255) NOT NULL,
  `carnet_identidad` varchar(20) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL
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
-- Indices de la tabla `asistencia`
--
ALTER TABLE `asistencia`
  ADD PRIMARY KEY (`id_asistencia`),
  ADD UNIQUE KEY `uk_estudiante_fecha` (`id_estudiante`,`fecha`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_estudiante` (`id_estudiante`);

--
-- Indices de la tabla `asistencia_horarios_ingreso`
--
ALTER TABLE `asistencia_horarios_ingreso`
  ADD PRIMARY KEY (`id_horario`),
  ADD KEY `idx_rango` (`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `asistencia_lectores`
--
ALTER TABLE `asistencia_lectores`
  ADD PRIMARY KEY (`id_lector`),
  ADD UNIQUE KEY `uk_asistencia_lectores_personal` (`id_personal`),
  ADD KEY `idx_asistencia_lectores_estado` (`estado`);

--
-- Indices de la tabla `asistencia_lectores_cursos`
--
ALTER TABLE `asistencia_lectores_cursos`
  ADD PRIMARY KEY (`id_lector_curso`),
  ADD UNIQUE KEY `uk_asistencia_lector_curso` (`id_lector`,`id_curso`),
  ADD KEY `idx_asistencia_lector_curso_estado` (`estado`),
  ADD KEY `fk_asistencia_lectores_cursos_curso` (`id_curso`);

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
-- Indices de la tabla `calificaciones_parciales`
--
ALTER TABLE `calificaciones_parciales`
  ADD PRIMARY KEY (`id_calificacion_parcial`),
  ADD UNIQUE KEY `uk_estudiante_materia_periodo` (`id_estudiante`,`id_materia`,`id_periodo_evaluacion`),
  ADD KEY `idx_cp_materia` (`id_materia`),
  ADD KEY `idx_cp_periodo` (`id_periodo_evaluacion`),
  ADD KEY `idx_cp_profesor` (`id_profesor`);

--
-- Indices de la tabla `calificaciones_parciales_detalle`
--
ALTER TABLE `calificaciones_parciales_detalle`
  ADD PRIMARY KEY (`id_detalle`),
  ADD UNIQUE KEY `uk_cp_detalle` (`id_calificacion_parcial`,`area`,`indice`),
  ADD KEY `idx_detalle_cp` (`id_calificacion_parcial`),
  ADD KEY `idx_detalle_creado_por` (`creado_por`);

--
-- Indices de la tabla `calificaciones_trimestrales`
--
ALTER TABLE `calificaciones_trimestrales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_est_mat_gest_trim` (`id_estudiante`,`id_materia`,`gestion`,`trimestre`),
  ADD KEY `idx_ct_materia` (`id_materia`),
  ADD KEY `idx_ct_gestion_trim` (`gestion`,`trimestre`);

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
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `idx_estudiantes_id_responsable` (`id_responsable`);

--
-- Indices de la tabla `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id_materia`);

--
-- Indices de la tabla `materias_complementarias`
--
ALTER TABLE `materias_complementarias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_mc_unica_relacion` (`id_materia_principal`,`id_materia_complementaria`,`gestion`),
  ADD KEY `fk_mc_materia_complementaria` (`id_materia_complementaria`);

--
-- Indices de la tabla `parciales_etiquetas_actividades`
--
ALTER TABLE `parciales_etiquetas_actividades`
  ADD PRIMARY KEY (`id_materia`,`id_periodo_evaluacion`,`area`,`indice`),
  ADD KEY `idx_periodo_materia` (`id_materia`,`id_periodo_evaluacion`);

--
-- Indices de la tabla `periodos_evaluacion`
--
ALTER TABLE `periodos_evaluacion`
  ADD PRIMARY KEY (`id_periodo_evaluacion`),
  ADD UNIQUE KEY `uk_gestion_trimestre_parcial` (`gestion`,`trimestre`,`parcial`);

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
  ADD UNIQUE KEY `uk_responsables_ci` (`carnet_identidad`);

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
-- AUTO_INCREMENT de la tabla `asistencia`
--
ALTER TABLE `asistencia`
  MODIFY `id_asistencia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT de la tabla `asistencia_horarios_ingreso`
--
ALTER TABLE `asistencia_horarios_ingreso`
  MODIFY `id_horario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `asistencia_lectores`
--
ALTER TABLE `asistencia_lectores`
  MODIFY `id_lector` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `asistencia_lectores_cursos`
--
ALTER TABLE `asistencia_lectores_cursos`
  MODIFY `id_lector_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `bimestres_activos`
--
ALTER TABLE `bimestres_activos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `calificaciones`
--
ALTER TABLE `calificaciones`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calificaciones_parciales`
--
ALTER TABLE `calificaciones_parciales`
  MODIFY `id_calificacion_parcial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calificaciones_parciales_detalle`
--
ALTER TABLE `calificaciones_parciales_detalle`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calificaciones_trimestrales`
--
ALTER TABLE `calificaciones_trimestrales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id_curso_materia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1348;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2512;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id_materia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=969;

--
-- AUTO_INCREMENT de la tabla `materias_complementarias`
--
ALTER TABLE `materias_complementarias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `periodos_evaluacion`
--
ALTER TABLE `periodos_evaluacion`
  MODIFY `id_periodo_evaluacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `personal`
--
ALTER TABLE `personal`
  MODIFY `id_personal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=758;

--
-- AUTO_INCREMENT de la tabla `profesores_materias_cursos`
--
ALTER TABLE `profesores_materias_cursos`
  MODIFY `id_profesor_materia_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1349;

--
-- AUTO_INCREMENT de la tabla `responsables`
--
ALTER TABLE `responsables`
  MODIFY `id_responsable` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asistencia_lectores`
--
ALTER TABLE `asistencia_lectores`
  ADD CONSTRAINT `fk_asistencia_lectores_personal` FOREIGN KEY (`id_personal`) REFERENCES `personal` (`id_personal`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `asistencia_lectores_cursos`
--
ALTER TABLE `asistencia_lectores_cursos`
  ADD CONSTRAINT `fk_asistencia_lectores_cursos_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asistencia_lectores_cursos_lector` FOREIGN KEY (`id_lector`) REFERENCES `asistencia_lectores` (`id_lector`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `calificaciones`
--
ALTER TABLE `calificaciones`
  ADD CONSTRAINT `calificaciones_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `calificaciones_ibfk_2` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `calificaciones_parciales`
--
ALTER TABLE `calificaciones_parciales`
  ADD CONSTRAINT `fk_cp_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_materia` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_periodo` FOREIGN KEY (`id_periodo_evaluacion`) REFERENCES `periodos_evaluacion` (`id_periodo_evaluacion`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_profesor` FOREIGN KEY (`id_profesor`) REFERENCES `personal` (`id_personal`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `calificaciones_parciales_detalle`
--
ALTER TABLE `calificaciones_parciales_detalle`
  ADD CONSTRAINT `fk_cpd_cp` FOREIGN KEY (`id_calificacion_parcial`) REFERENCES `calificaciones_parciales` (`id_calificacion_parcial`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cpd_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `personal` (`id_personal`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Filtros para la tabla `materias_complementarias`
--
ALTER TABLE `materias_complementarias`
  ADD CONSTRAINT `fk_mc_materia_complementaria` FOREIGN KEY (`id_materia_complementaria`) REFERENCES `materias` (`id_materia`),
  ADD CONSTRAINT `fk_mc_materia_principal` FOREIGN KEY (`id_materia_principal`) REFERENCES `materias` (`id_materia`);

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
