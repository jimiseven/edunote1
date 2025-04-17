-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 17-04-2025 a las 07:28:18
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

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
(1, 'Inicial', 1, 'A'),
(2, 'Inicial', 1, 'B'),
(3, 'Inicial', 2, 'A'),
(4, 'Inicial', 2, 'B'),
(5, 'Primaria', 1, 'A'),
(6, 'Primaria', 1, 'B'),
(7, 'Primaria', 2, 'A'),
(8, 'Primaria', 2, 'B'),
(9, 'Primaria', 3, 'A'),
(10, 'Primaria', 3, 'B'),
(11, 'Primaria', 4, 'A'),
(12, 'Primaria', 4, 'B'),
(13, 'Primaria', 5, 'A'),
(14, 'Primaria', 5, 'B'),
(15, 'Primaria', 6, 'A'),
(16, 'Primaria', 6, 'B'),
(17, 'Secundaria', 1, 'A'),
(18, 'Secundaria', 1, 'B'),
(19, 'Secundaria', 2, 'A'),
(20, 'Secundaria', 2, 'B'),
(21, 'Secundaria', 3, 'A'),
(22, 'Secundaria', 3, 'B'),
(23, 'Secundaria', 4, 'A'),
(24, 'Secundaria', 4, 'B'),
(25, 'Secundaria', 5, 'A'),
(26, 'Secundaria', 5, 'B'),
(27, 'Secundaria', 6, 'A'),
(28, 'Secundaria', 6, 'B');

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
  `rude` varchar(255) DEFAULT NULL,
  `carnet_identidad` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `id_curso` int(11) DEFAULT NULL COMMENT 'FK al curso en el que está matriculado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estudiantes`
--

INSERT INTO `estudiantes` (`id_estudiante`, `nombres`, `apellido_paterno`, `apellido_materno`, `genero`, `rude`, `carnet_identidad`, `fecha_nacimiento`, `id_curso`) VALUES
(1, 'DEMIR MATIAS', 'ARIAS', 'VILLARROEL', 'Masculino', NULL, '16511091', '2020-10-08', 1),
(2, 'MAYER SAID', 'AYAVIRI', 'SOCOMPI', 'Masculino', '16578203', '16578203', '2020-12-01', 1),
(3, 'ALESSANDRO LEON', 'BUSTAMANTE', 'ROCHA', 'Masculino', '16604527', '16604527', '2020-12-21', 1),
(4, 'ANDRES SEBASTIAN', 'CAMPOS', 'SELAYA', 'Masculino', NULL, '16685986', '2021-02-10', 1),
(5, 'ZOE ARLETH', 'CHOQUE', 'ROMERO', 'Femenino', '16471193', '16471193', '2020-07-25', 1),
(6, 'SHEYLA SIN COLN-NI DOC', 'CHUQUIMIA', 'COPA', 'Femenino', NULL, NULL, '2020-02-20', 1),
(7, 'ALEJANDRA DENISE', 'COCA', 'OTALORA', 'Femenino', '16599623', '16599623', '2020-08-18', 1),
(8, 'FERMIN', 'COLQUE', 'BORDA', 'Masculino', '16556913', '16556913', '2020-11-19', 1),
(9, 'CARMEN ROSSY', 'FATTY', 'TOCO', 'Femenino', '16709933', '16709933', '2021-02-27', 1),
(10, 'JAIR AARON', 'FERNANDEZ', 'FRANCO', 'Masculino', '16586802', '16586802', '2020-12-07', 1),
(11, 'MICAEL', 'FERRUFINO', 'MAIZO', 'Masculino', '16581991', '16581991', '2020-12-03', 1),
(12, 'MATIAS', 'FLORES', 'MAMANI', 'Masculino', '16841750', '16841750', '2021-04-09', 1),
(13, 'AYSE CHARLOTTE', 'LEDEZMA', 'SALAZAR', 'Femenino', '17647362', '17647362', '2021-02-16', 1),
(14, 'MAYTE BELEN', 'LIMACHI', 'TACURI', 'Femenino', '16491612', '16491612', '2020-09-10', 1),
(15, 'ANGELES VALERIA', 'LOZA', 'LAUREANO', 'Femenino', '16464959', '16464959', '2020-07-06', 1),
(16, 'ANDRES JHUNIOR', 'MENESES', 'QUISPE', 'Masculino', '16539901', '16539901', '2020-11-06', 1),
(17, 'TAYLOR JAMES', 'MENESES', 'VILLARROEL', 'Masculino', '16922846', '16922846', '2021-05-14', 1),
(18, 'DAMARIS', 'NICOLAS', 'RODRIGUEZ', 'Femenino', NULL, '16483886', '2020-08-28', 1),
(19, 'SALOME CRISTAL', 'PADILLA', 'CHABARRIA', 'Femenino', '16900582', '16900582', '2021-01-25', 1),
(20, 'MADISON ESCARLET', 'REINAGA', 'MONTECINOS', 'Femenino', '16480330', '16480330', '2020-08-19', 1),
(21, 'GABRIEL JESUS', 'REINAGA', 'REVOLLO', 'Masculino', '16832463', '16832463', '2024-05-20', 1),
(22, 'XIOMARA', 'REINAGA', 'VALDA', 'Femenino', '16885457', '16885457', '2021-02-10', 1),
(23, 'LUANA VALENTINA', 'SANCHEZ', 'ABAN', 'Femenino', '16487247', '16487247', '2020-09-03', 1),
(24, 'HANSSEL PABLO', 'SESPEDES', 'APAZA', 'Masculino', '16715675', '16715675', '2021-03-03', 1),
(25, 'MARIBEL', 'SOLIZ', 'CHOQUEHUANCA', 'Femenino', NULL, NULL, '2021-02-07', 1),
(26, 'ARIANA GISSEL', 'TAPIA', 'CHOQUE', 'Femenino', NULL, '16823421', '2021-04-29', 1),
(27, 'NAYELI ESTRELLA', 'TICONA', 'HINOJOSA', 'Femenino', '16865331', '16865331', '2021-06-14', 1),
(28, 'ALAN GONEL', 'VARGAS', 'MONTECINOS', 'Masculino', '16483769', '16483769', '2020-08-21', 1),
(29, 'EVELYN', 'ZEBALLOS', 'TUDELA', 'Femenino', '16475359', '16475359', '2020-08-05', 1),
(30, 'YEIMI LUNA', 'AGUILAR', 'SALAZAR', 'Femenino', '4090005020247A', '16400177', '2020-03-02', 3),
(31, 'NICOL', 'ANGULO', 'RUIZ', 'Femenino', '409000502024559', '16413450', '2020-03-10', 3),
(32, 'JOSUE ABIDIEL', 'APAZA', 'ALVARADO', 'Masculino', '409000502024566', '16416115', '2019-07-16', 3),
(33, 'GAEL JORDY', 'BURGUILLA', 'FLORES', 'Masculino', '409000502024572', '16433496', '2019-05-24', 3),
(34, 'ELISEO', 'CHAMBI', 'VICENTE', 'Masculino', '409000502024762', '16489124', '2020-03-10', 3),
(35, 'ELIAZAR KALEP', 'CHIPANA', 'MAMANI', 'Masculino', '409000502024560', '16519023', '2020-12-03', 3),
(36, 'DARELL EDRIK', 'CHOQUE', 'Cespedes', 'Masculino', '409000502024265', '16581382', '2020-03-28', 3),
(37, 'MARIO ROMAN', 'CONDORI', 'ROCHA', 'Masculino', '409000502024334', '16432713', '2020-04-13', 3),
(38, 'BIDANEYRA ANNDY', 'CRUZ', 'GUTIERREZ', 'Femenino', '409000502024350', '16342715', '2020-01-16', 3),
(39, 'NIJAN ZOE', 'ESCOBAR', 'PINTO', 'Femenino', '409000502024881', '16900871', '2020-02-12', 3),
(40, 'IAN JHAIR', 'GARCIA', 'REINAGA', 'Masculino', '409000502024949', '16916390', '2020-03-22', 3),
(41, 'ALEX JHUNIOR', 'GUTIERREZ', 'MARTINEZ', 'Masculino', '409000502024218', '16910780', '2019-09-10', 3),
(42, 'LUCAS THIAGO', 'LUIZAGA', 'VEDIA', 'Masculino', '409000502024980', '16292414', '2020-08-12', 3),
(43, 'BRITTANY BRIANA', 'MAMANI', 'GARCIA', 'Femenino', '409000502024579', '16599623', '2020-04-14', 3),
(44, 'YADIL', 'MAMANI', 'TACURI', 'Masculino', '409000502024616', '16596935', '2019-11-28', 3),
(45, 'DAYER ALTES', 'MAMANI', 'VILLARROEL', 'Masculino', '409000502024344', '16610870', '2020-08-05', 3),
(46, 'MIA NAYELY', 'MARCANI', 'OYARDO', 'Femenino', '409000502024469', '16392799', '2020-10-08', 3),
(47, 'JEICOB MARCEILINO', 'MARCANI', 'ESCOBAR', 'Masculino', '409000502024679', '16392199', '2020-04-04', 3),
(48, 'RAZIEL ATANA', 'PALLA', 'CONDORI', 'Masculino', '409000502024434', '16487471', '2020-09-23', 3),
(49, 'MADILIN ZULEYKA', 'SOLIZ', 'ROCHA', 'Femenino', '409000502024123', '16711945', '2020-07-09', 3),
(50, 'VALENTINA TATIANA', 'VELIZ', 'AIRA', 'Femenino', '409000502024733', '16834497', '2020-04-16', 3),
(51, 'TATIANA', 'ZEGARRA', 'PADILLA', 'Femenino', '409000502024372', '16823453', '2020-10-24', 3),
(52, 'JORGE MISAEL', 'ZURITA', 'VERA', 'Masculino', '409000502024727', '16419078', '2020-03-11', 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

CREATE TABLE `materias` (
  `id_materia` int(11) NOT NULL,
  `nombre_materia` varchar(255) NOT NULL COMMENT 'Nombre de la materia, ej: Matemáticas, Física',
  `es_submateria` tinyint(1) DEFAULT 0,
  `materia_padre_id` int(11) DEFAULT NULL
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
(1, 'drmoon', 'lite', '76992490', '5151515', 1, '$2y$10$1CZhd0w9MnVmI5JarVmr1Os6Y2jBZkh7D.I.IdCkG/LJvNL.ZH4aC');

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
(2, 'Profesor');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `bimestres_activos`
--
ALTER TABLE `bimestres_activos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calificaciones`
--
ALTER TABLE `calificaciones`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `cursos_materias`
--
ALTER TABLE `cursos_materias`
  MODIFY `id_curso_materia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id_materia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal`
--
ALTER TABLE `personal`
  MODIFY `id_personal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `profesores_materias_cursos`
--
ALTER TABLE `profesores_materias_cursos`
  MODIFY `id_profesor_materia_curso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
