-- Migración: tabla para autoevaluación y nota extra por trimestre
-- Ejecutar sobre la base de datos colegiov2

CREATE TABLE IF NOT EXISTS `calificaciones_trimestrales` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` INT(11) NOT NULL,
  `id_materia` INT(11) NOT NULL,
  `gestion` VARCHAR(9) NOT NULL,
  `trimestre` TINYINT(4) NOT NULL,
  `autoevaluacion` FLOAT DEFAULT NULL COMMENT 'Nota de autoevaluación (0-5)',
  `nota_extra` FLOAT DEFAULT NULL COMMENT 'Nota extra / puntaje adicional',
  `id_profesor` INT(11) DEFAULT NULL,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_est_mat_gest_trim` (`id_estudiante`, `id_materia`, `gestion`, `trimestre`),
  KEY `idx_ct_materia` (`id_materia`),
  KEY `idx_ct_gestion_trim` (`gestion`, `trimestre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
