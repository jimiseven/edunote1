-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-04-2025 a las 11:45:46
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
-- Estructura de tabla para la tabla `calificaciones`
--

CREATE TABLE `calificaciones` (
  `id_calificacion` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL COMMENT 'FK a estudiantes',
  `id_materia` int(11) NOT NULL COMMENT 'FK a materias',
  `bimestre` int(11) NOT NULL COMMENT 'Número del bimestre: 1, 2, 3, 4',
  `calificacion` decimal(5,2) NOT NULL COMMENT 'Nota obtenida en el bimestre'
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
(1, 'Primaria', 1, 'A'),
(2, 'Primaria', 2, 'B'),
(3, 'Secundaria', 1, 'A'),
(4, 'Secundaria', 2, 'B');

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
(6, 4, 3);

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
) ;

--
-- Volcado de datos para la tabla `estudiantes`
--

INSERT INTO `estudiantes` (`id_estudiante`, `nombres`, `apellido_paterno`, `apellido_materno`, `genero`, `rude`, `carnet_identidad`, `fecha_nacimiento`, `id_curso`, `id_responsable`) VALUES
(5, 'Luis', 'Martínez', 'Rodríguez', 'Masculino', 'RUDE0000001', '65748392', '2010-05-12', 1, 1),
(6, 'Sofía', 'Fernández', 'García', 'Femenino', 'RUDE0000002', '84759321', '2011-07-23', 2, 2),
(7, 'Miguel', 'Ortiz', 'Castro', 'Masculino', 'RUDE0000003', '93847365', '2009-02-16', 3, 3),
(8, 'Valeria', 'Paz', 'Suárez', 'Femenino', 'RUDE0000004', '74839265', '2008-11-30', 4, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

CREATE TABLE `materias` (
  `id_materia` int(11) NOT NULL,
  `nombre_materia` varchar(255) NOT NULL COMMENT 'Nombre de la materia, ej: Matemáticas, Física'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`id_materia`, `nombre_materia`) VALUES
(1, 'Matemáticas'),
(2, 'Lenguaje'),
(3, 'Ciencias Naturales'),
(4, 'Historia');

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
  `id_rol` int(11) NOT NULL COMMENT 'FK a roles'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `personal`
--

INSERT INTO `personal` (`id_personal`, `nombres`, `apellidos`, `celular`, `carnet_identidad`, `id_rol`) VALUES
(1, 'Juan', 'Pérez', '789456123', '1234567', 1),
(2, 'María', 'Gómez', '654123987', '2345678', 2),
(3, 'Carlos', 'Rojas', '741852963', '3456789', 2),
(4, 'Ana', 'López', '852963741', '4567890', 3);

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
(1, 2, 1, 'FALTA'),
(2, 2, 2, 'FALTA'),
(3, 3, 3, 'FALTA'),
(4, 3, 4, 'FALTA'),
(5, 3, 5, 'FALTA');

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
(3, 'Pedro', 'Ortiz', '74683921', '741258963', 'Tutor');

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
-- Indices de la tabla `calificaciones`
--
ALTER TABLE `calificaciones`
  ADD PRIMARY KEY (`id_calificacion`),
  ADD UNIQUE KEY `id_estudiante` (`id_estudiante`,`id_materia`,`bimestre`),
  ADD KEY `id_materia` (`id_materia`);

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
-- AUTO_INCREMENT de la tabla `calificaciones`
--
ALTER TABLE `calificaciones`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `cursos_materias`
--
ALTER TABLE `cursos_materias`
  MODIFY `id_curso_materia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id_materia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `personal`
--
ALTER TABLE `personal`
  MODIFY `id_personal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `profesores_materias_cursos`
--
ALTER TABLE `profesores_materias_cursos`
  MODIFY `id_profesor_materia_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `responsables`
--
ALTER TABLE `responsables`
  MODIFY `id_responsable` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
