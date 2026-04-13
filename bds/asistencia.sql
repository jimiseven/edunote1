-- Tabla para registrar la asistencia de estudiantes
CREATE TABLE IF NOT EXISTS `asistencia` (
  `id_asistencia` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL COMMENT 'FK a estudiantes',
  `fecha` date NOT NULL,
  `hora_entrada` time NOT NULL,
  `tipo_registro` enum('QR','MANUAL') DEFAULT 'QR' COMMENT 'QR: escaneado con QR, MANUAL: registrado manualmente',
  `registrado_por` int(11) DEFAULT NULL COMMENT 'FK a personal (si es manual)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_asistencia`),
  UNIQUE KEY `uk_estudiante_fecha` (`id_estudiante`, `fecha`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_estudiante` (`id_estudiante`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
