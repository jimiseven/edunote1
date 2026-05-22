-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 22, 2026 at 02:17 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `wiredcom_uni3t`
--

-- --------------------------------------------------------

--
-- Table structure for table `anuncios`
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
-- Table structure for table `asistencia`
--

CREATE TABLE `asistencia` (
  `id_asistencia` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL COMMENT 'FK a estudiantes',
  `fecha` date NOT NULL,
  `turno` enum('MANANA','TARDE') NOT NULL DEFAULT 'MANANA',
  `hora_entrada` time NOT NULL,
  `tipo_registro` enum('QR','MANUAL') DEFAULT 'QR' COMMENT 'QR: escaneado con QR, MANUAL: registrado manualmente',
  `estado_puntualidad` enum('TEMPRANO','TARDE') DEFAULT NULL,
  `hora_ingreso_programada` time DEFAULT NULL,
  `tolerancia_min` int(11) DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL COMMENT 'FK a personal (si es manual)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asistencia_cursos_turnos`
--

CREATE TABLE `asistencia_cursos_turnos` (
  `id_curso_turno` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `doble_turno` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=una marca diaria, 1=manana+tarde',
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asistencia_horarios_curso_turno`
--

CREATE TABLE `asistencia_horarios_curso_turno` (
  `id_horario_curso` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `turno` enum('MANANA','TARDE') NOT NULL,
  `hora_ingreso` time NOT NULL,
  `tolerancia_min` int(11) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asistencia_horarios_ingreso`
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

-- --------------------------------------------------------

--
-- Table structure for table `asistencia_horarios_turno_global`
--

CREATE TABLE `asistencia_horarios_turno_global` (
  `id_horario_global` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `turno` enum('MANANA','TARDE') NOT NULL,
  `hora_ingreso` time NOT NULL,
  `tolerancia_min` int(11) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asistencia_lectores`
--

CREATE TABLE `asistencia_lectores` (
  `id_lector` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL,
  `alcance` enum('GLOBAL','POR_CURSO') NOT NULL DEFAULT 'GLOBAL',
  `tipo_lector` enum('ADMINISTRADOR','LECTURADOR') NOT NULL DEFAULT 'LECTURADOR',
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=habilitado, 0=inhabilitado',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asistencia_lectores_cursos`
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
-- Table structure for table `asistencia_permisos`
--

CREATE TABLE `asistencia_permisos` (
  `id_permiso` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `motivo` varchar(150) NOT NULL,
  `detalle` text DEFAULT NULL,
  `estado` enum('APROBADO','RECHAZADO') NOT NULL DEFAULT 'APROBADO',
  `registrado_por` int(11) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bimestres_activos`
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
-- Table structure for table `calificaciones`
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
-- Table structure for table `calificaciones_parciales`
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
-- Table structure for table `calificaciones_parciales_detalle`
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
-- Table structure for table `calificaciones_trimestrales`
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
-- Table structure for table `configuracion_sistema`
--

CREATE TABLE `configuracion_sistema` (
  `id` int(11) NOT NULL,
  `cantidad_bimestres` int(11) NOT NULL DEFAULT 3,
  `bimestre_actual` int(11) NOT NULL DEFAULT 1,
  `anio_escolar` varchar(9) NOT NULL,
  `modalidad_carga_notas` varchar(20) NOT NULL DEFAULT 'parciales',
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cursos`
--

CREATE TABLE `cursos` (
  `id_curso` int(11) NOT NULL,
  `nivel` varchar(20) NOT NULL COMMENT 'Ej: Kinder, Primaria, Secundaria',
  `curso` int(11) NOT NULL COMMENT 'Número del curso, ej: 1, 2, 3',
  `paralelo` varchar(5) NOT NULL COMMENT 'Ej: A, B, C'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cursos_materias`
--

CREATE TABLE `cursos_materias` (
  `id_curso_materia` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL COMMENT 'FK a cursos',
  `id_materia` int(11) NOT NULL COMMENT 'FK a materias'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `estudiantes`
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

-- --------------------------------------------------------

--
-- Table structure for table `materias`
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
-- Table structure for table `materias_complementarias`
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
-- Table structure for table `parciales_etiquetas_actividades`
--

CREATE TABLE `parciales_etiquetas_actividades` (
  `id_curso` int(11) NOT NULL DEFAULT 0,
  `id_materia` int(11) NOT NULL,
  `id_periodo_evaluacion` int(11) NOT NULL,
  `area` enum('SER','SABER','HACER') NOT NULL,
  `indice` tinyint(3) UNSIGNED NOT NULL,
  `etiqueta` varchar(120) NOT NULL DEFAULT '',
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `periodos_evaluacion`
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

-- --------------------------------------------------------

--
-- Table structure for table `personal`
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

-- --------------------------------------------------------

--
-- Table structure for table `profesores_materias_cursos`
--

CREATE TABLE `profesores_materias_cursos` (
  `id_profesor_materia_curso` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL COMMENT 'FK a personal (profesor)',
  `id_curso_materia` int(11) NOT NULL COMMENT 'FK a cursos_materias',
  `estado` enum('FALTA','CARGADO') NOT NULL DEFAULT 'FALTA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `responsables`
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
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL COMMENT 'Ej: Administrador, Profesor, Secretario'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios_activos`
--

CREATE TABLE `usuarios_activos` (
  `id_activo` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `nombre_usuario` varchar(160) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `ruta_actual` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ultimo_ping` datetime NOT NULL DEFAULT current_timestamp(),
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios_ingresos`
--

CREATE TABLE `usuarios_ingresos` (
  `id_ingreso` int(11) NOT NULL,
  `id_personal` int(11) NOT NULL,
  `nombre_usuario` varchar(160) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `fecha_ingreso` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `anuncios`
--
ALTER TABLE `anuncios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asistencia`
--
ALTER TABLE `asistencia`
  ADD PRIMARY KEY (`id_asistencia`),
  ADD UNIQUE KEY `uk_estudiante_fecha_turno` (`id_estudiante`,`fecha`,`turno`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_estudiante` (`id_estudiante`),
  ADD KEY `idx_asistencia_fecha_est_hora` (`fecha`,`id_estudiante`,`hora_entrada`),
  ADD KEY `idx_fecha_turno` (`fecha`,`turno`);

--
-- Indexes for table `asistencia_cursos_turnos`
--
ALTER TABLE `asistencia_cursos_turnos`
  ADD PRIMARY KEY (`id_curso_turno`),
  ADD UNIQUE KEY `uk_curso_turno` (`id_curso`),
  ADD KEY `idx_doble_turno_estado` (`doble_turno`,`estado`);

--
-- Indexes for table `asistencia_horarios_curso_turno`
--
ALTER TABLE `asistencia_horarios_curso_turno`
  ADD PRIMARY KEY (`id_horario_curso`),
  ADD KEY `idx_curso_rango_estado` (`id_curso`,`fecha_inicio`,`fecha_fin`,`estado`),
  ADD KEY `idx_turno_estado` (`turno`,`estado`);

--
-- Indexes for table `asistencia_horarios_ingreso`
--
ALTER TABLE `asistencia_horarios_ingreso`
  ADD PRIMARY KEY (`id_horario`),
  ADD KEY `idx_rango` (`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_horario_estado_rango` (`estado`,`fecha_inicio`,`fecha_fin`);

--
-- Indexes for table `asistencia_horarios_turno_global`
--
ALTER TABLE `asistencia_horarios_turno_global`
  ADD PRIMARY KEY (`id_horario_global`),
  ADD KEY `idx_turno_global_rango_estado` (`turno`,`estado`,`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_global_estado` (`estado`);

--
-- Indexes for table `asistencia_lectores`
--
ALTER TABLE `asistencia_lectores`
  ADD PRIMARY KEY (`id_lector`),
  ADD UNIQUE KEY `uk_asistencia_lectores_personal` (`id_personal`),
  ADD KEY `idx_asistencia_lectores_estado` (`estado`),
  ADD KEY `idx_lector_personal_estado` (`id_personal`,`estado`);

--
-- Indexes for table `asistencia_lectores_cursos`
--
ALTER TABLE `asistencia_lectores_cursos`
  ADD PRIMARY KEY (`id_lector_curso`),
  ADD UNIQUE KEY `uk_asistencia_lector_curso` (`id_lector`,`id_curso`),
  ADD KEY `idx_asistencia_lector_curso_estado` (`estado`),
  ADD KEY `fk_asistencia_lectores_cursos_curso` (`id_curso`),
  ADD KEY `idx_lector_curso_estado` (`id_lector`,`id_curso`,`estado`);

--
-- Indexes for table `asistencia_permisos`
--
ALTER TABLE `asistencia_permisos`
  ADD PRIMARY KEY (`id_permiso`),
  ADD UNIQUE KEY `uk_permiso_estudiante_fecha` (`id_estudiante`,`fecha`),
  ADD KEY `idx_permiso_fecha` (`fecha`),
  ADD KEY `idx_permiso_estado` (`estado`),
  ADD KEY `idx_permiso_estudiante` (`id_estudiante`),
  ADD KEY `idx_permiso_registrado_por` (`registrado_por`);

--
-- Indexes for table `bimestres_activos`
--
ALTER TABLE `bimestres_activos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `calificaciones`
--
ALTER TABLE `calificaciones`
  ADD PRIMARY KEY (`id_calificacion`),
  ADD UNIQUE KEY `id_estudiante` (`id_estudiante`,`id_materia`,`bimestre`),
  ADD KEY `id_materia` (`id_materia`);

--
-- Indexes for table `calificaciones_parciales`
--
ALTER TABLE `calificaciones_parciales`
  ADD PRIMARY KEY (`id_calificacion_parcial`),
  ADD UNIQUE KEY `uk_estudiante_materia_periodo` (`id_estudiante`,`id_materia`,`id_periodo_evaluacion`),
  ADD KEY `idx_cp_materia` (`id_materia`),
  ADD KEY `idx_cp_periodo` (`id_periodo_evaluacion`),
  ADD KEY `idx_cp_profesor` (`id_profesor`);

--
-- Indexes for table `calificaciones_parciales_detalle`
--
ALTER TABLE `calificaciones_parciales_detalle`
  ADD PRIMARY KEY (`id_detalle`),
  ADD UNIQUE KEY `uk_cp_detalle` (`id_calificacion_parcial`,`area`,`indice`),
  ADD KEY `idx_detalle_cp` (`id_calificacion_parcial`),
  ADD KEY `idx_detalle_creado_por` (`creado_por`);

--
-- Indexes for table `calificaciones_trimestrales`
--
ALTER TABLE `calificaciones_trimestrales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_est_mat_gest_trim` (`id_estudiante`,`id_materia`,`gestion`,`trimestre`),
  ADD KEY `idx_ct_materia` (`id_materia`),
  ADD KEY `idx_ct_gestion_trim` (`gestion`,`trimestre`);

--
-- Indexes for table `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`);

--
-- Indexes for table `cursos_materias`
--
ALTER TABLE `cursos_materias`
  ADD PRIMARY KEY (`id_curso_materia`),
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `id_materia` (`id_materia`);

--
-- Indexes for table `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id_estudiante`),
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `idx_estudiantes_id_responsable` (`id_responsable`),
  ADD KEY `idx_estudiantes_curso_est` (`id_curso`,`id_estudiante`);

--
-- Indexes for table `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id_materia`);

--
-- Indexes for table `materias_complementarias`
--
ALTER TABLE `materias_complementarias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_mc_unica_relacion` (`id_materia_principal`,`id_materia_complementaria`,`gestion`),
  ADD KEY `fk_mc_materia_complementaria` (`id_materia_complementaria`);

--
-- Indexes for table `parciales_etiquetas_actividades`
--
ALTER TABLE `parciales_etiquetas_actividades`
  ADD PRIMARY KEY (`id_curso`,`id_materia`,`id_periodo_evaluacion`,`area`,`indice`),
  ADD KEY `idx_periodo_materia` (`id_materia`,`id_periodo_evaluacion`);

--
-- Indexes for table `periodos_evaluacion`
--
ALTER TABLE `periodos_evaluacion`
  ADD PRIMARY KEY (`id_periodo_evaluacion`),
  ADD UNIQUE KEY `uk_gestion_trimestre_parcial` (`gestion`,`trimestre`,`parcial`);

--
-- Indexes for table `personal`
--
ALTER TABLE `personal`
  ADD PRIMARY KEY (`id_personal`),
  ADD UNIQUE KEY `carnet_identidad` (`carnet_identidad`),
  ADD KEY `id_rol` (`id_rol`);

--
-- Indexes for table `profesores_materias_cursos`
--
ALTER TABLE `profesores_materias_cursos`
  ADD PRIMARY KEY (`id_profesor_materia_curso`),
  ADD UNIQUE KEY `id_personal` (`id_personal`,`id_curso_materia`),
  ADD KEY `id_curso_materia` (`id_curso_materia`);

--
-- Indexes for table `responsables`
--
ALTER TABLE `responsables`
  ADD PRIMARY KEY (`id_responsable`),
  ADD UNIQUE KEY `uk_responsables_ci` (`carnet_identidad`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`);

--
-- Indexes for table `usuarios_activos`
--
ALTER TABLE `usuarios_activos`
  ADD PRIMARY KEY (`id_activo`),
  ADD UNIQUE KEY `uk_usuarios_activos_session` (`session_id`),
  ADD KEY `idx_usuarios_activos_ultimo_ping` (`ultimo_ping`),
  ADD KEY `idx_usuarios_activos_personal` (`id_personal`);

--
-- Indexes for table `usuarios_ingresos`
--
ALTER TABLE `usuarios_ingresos`
  ADD PRIMARY KEY (`id_ingreso`),
  ADD KEY `idx_usuarios_ingresos_personal` (`id_personal`),
  ADD KEY `idx_usuarios_ingresos_fecha` (`fecha_ingreso`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `anuncios`
--
ALTER TABLE `anuncios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asistencia`
--
ALTER TABLE `asistencia`
  MODIFY `id_asistencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asistencia_cursos_turnos`
--
ALTER TABLE `asistencia_cursos_turnos`
  MODIFY `id_curso_turno` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asistencia_horarios_curso_turno`
--
ALTER TABLE `asistencia_horarios_curso_turno`
  MODIFY `id_horario_curso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asistencia_horarios_ingreso`
--
ALTER TABLE `asistencia_horarios_ingreso`
  MODIFY `id_horario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asistencia_horarios_turno_global`
--
ALTER TABLE `asistencia_horarios_turno_global`
  MODIFY `id_horario_global` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asistencia_lectores`
--
ALTER TABLE `asistencia_lectores`
  MODIFY `id_lector` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asistencia_lectores_cursos`
--
ALTER TABLE `asistencia_lectores_cursos`
  MODIFY `id_lector_curso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asistencia_permisos`
--
ALTER TABLE `asistencia_permisos`
  MODIFY `id_permiso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bimestres_activos`
--
ALTER TABLE `bimestres_activos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calificaciones`
--
ALTER TABLE `calificaciones`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calificaciones_parciales`
--
ALTER TABLE `calificaciones_parciales`
  MODIFY `id_calificacion_parcial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calificaciones_parciales_detalle`
--
ALTER TABLE `calificaciones_parciales_detalle`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calificaciones_trimestrales`
--
ALTER TABLE `calificaciones_trimestrales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cursos_materias`
--
ALTER TABLE `cursos_materias`
  MODIFY `id_curso_materia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `materias`
--
ALTER TABLE `materias`
  MODIFY `id_materia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `materias_complementarias`
--
ALTER TABLE `materias_complementarias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `periodos_evaluacion`
--
ALTER TABLE `periodos_evaluacion`
  MODIFY `id_periodo_evaluacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal`
--
ALTER TABLE `personal`
  MODIFY `id_personal` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profesores_materias_cursos`
--
ALTER TABLE `profesores_materias_cursos`
  MODIFY `id_profesor_materia_curso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `responsables`
--
ALTER TABLE `responsables`
  MODIFY `id_responsable` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios_activos`
--
ALTER TABLE `usuarios_activos`
  MODIFY `id_activo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios_ingresos`
--
ALTER TABLE `usuarios_ingresos`
  MODIFY `id_ingreso` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `asistencia_cursos_turnos`
--
ALTER TABLE `asistencia_cursos_turnos`
  ADD CONSTRAINT `fk_asistencia_cursos_turnos_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `asistencia_horarios_curso_turno`
--
ALTER TABLE `asistencia_horarios_curso_turno`
  ADD CONSTRAINT `fk_asistencia_horarios_curso_turno_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `asistencia_lectores`
--
ALTER TABLE `asistencia_lectores`
  ADD CONSTRAINT `fk_asistencia_lectores_personal` FOREIGN KEY (`id_personal`) REFERENCES `personal` (`id_personal`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `asistencia_lectores_cursos`
--
ALTER TABLE `asistencia_lectores_cursos`
  ADD CONSTRAINT `fk_asistencia_lectores_cursos_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asistencia_lectores_cursos_lector` FOREIGN KEY (`id_lector`) REFERENCES `asistencia_lectores` (`id_lector`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `asistencia_permisos`
--
ALTER TABLE `asistencia_permisos`
  ADD CONSTRAINT `fk_asistencia_permisos_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asistencia_permisos_registrado_por` FOREIGN KEY (`registrado_por`) REFERENCES `personal` (`id_personal`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `calificaciones`
--
ALTER TABLE `calificaciones`
  ADD CONSTRAINT `calificaciones_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `calificaciones_ibfk_2` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `calificaciones_parciales`
--
ALTER TABLE `calificaciones_parciales`
  ADD CONSTRAINT `fk_cp_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_materia` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_periodo` FOREIGN KEY (`id_periodo_evaluacion`) REFERENCES `periodos_evaluacion` (`id_periodo_evaluacion`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_profesor` FOREIGN KEY (`id_profesor`) REFERENCES `personal` (`id_personal`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `calificaciones_parciales_detalle`
--
ALTER TABLE `calificaciones_parciales_detalle`
  ADD CONSTRAINT `fk_cpd_cp` FOREIGN KEY (`id_calificacion_parcial`) REFERENCES `calificaciones_parciales` (`id_calificacion_parcial`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cpd_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `personal` (`id_personal`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `cursos_materias`
--
ALTER TABLE `cursos_materias`
  ADD CONSTRAINT `cursos_materias_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cursos_materias_ibfk_2` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD CONSTRAINT `estudiantes_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `estudiantes_ibfk_2` FOREIGN KEY (`id_responsable`) REFERENCES `responsables` (`id_responsable`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `materias_complementarias`
--
ALTER TABLE `materias_complementarias`
  ADD CONSTRAINT `fk_mc_materia_complementaria` FOREIGN KEY (`id_materia_complementaria`) REFERENCES `materias` (`id_materia`),
  ADD CONSTRAINT `fk_mc_materia_principal` FOREIGN KEY (`id_materia_principal`) REFERENCES `materias` (`id_materia`);

--
-- Constraints for table `personal`
--
ALTER TABLE `personal`
  ADD CONSTRAINT `personal_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;

--
-- Constraints for table `profesores_materias_cursos`
--
ALTER TABLE `profesores_materias_cursos`
  ADD CONSTRAINT `profesores_materias_cursos_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `personal` (`id_personal`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `profesores_materias_cursos_ibfk_2` FOREIGN KEY (`id_curso_materia`) REFERENCES `cursos_materias` (`id_curso_materia`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
