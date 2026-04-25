-- Configuración de horarios de ingreso por rango de fechas
CREATE TABLE IF NOT EXISTS `asistencia_horarios_ingreso` (
  `id_horario` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `hora_ingreso` time NOT NULL,
  `tolerancia_min` int(11) NOT NULL DEFAULT 0,
  `estado` tinyint(4) NOT NULL DEFAULT 1,
  `creado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_horario`),
  KEY `idx_rango` (`fecha_inicio`,`fecha_fin`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `asistencia`
  ADD COLUMN `estado_puntualidad` enum('TEMPRANO','TARDE') DEFAULT NULL AFTER `tipo_registro`;

ALTER TABLE `asistencia`
  ADD COLUMN `hora_ingreso_programada` time DEFAULT NULL AFTER `estado_puntualidad`;

ALTER TABLE `asistencia`
  ADD COLUMN `tolerancia_min` int(11) DEFAULT NULL AFTER `hora_ingreso_programada`;
