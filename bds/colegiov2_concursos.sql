-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-04-2025 a las 10:20:53
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
  ADD UNIQUE KEY `rude` (`rude`),
  ADD UNIQUE KEY `carnet_identidad` (`carnet_identidad`),
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
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=646;

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
