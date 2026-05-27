-- Permisos de lectores de asistencia (global o por curso)
CREATE TABLE IF NOT EXISTS `asistencia_lectores` (
  `id_lector` int(11) NOT NULL AUTO_INCREMENT,
  `id_personal` int(11) NOT NULL,
  `alcance` enum('GLOBAL','POR_CURSO') NOT NULL DEFAULT 'GLOBAL',
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=habilitado, 0=inhabilitado',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_lector`),
  UNIQUE KEY `uk_asistencia_lectores_personal` (`id_personal`),
  KEY `idx_asistencia_lectores_estado` (`estado`),
  KEY `idx_lector_personal_estado` (`id_personal`,`estado`),
  CONSTRAINT `fk_asistencia_lectores_personal` FOREIGN KEY (`id_personal`) REFERENCES `personal` (`id_personal`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `asistencia_lectores_cursos` (
  `id_lector_curso` int(11) NOT NULL AUTO_INCREMENT,
  `id_lector` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=habilitado, 0=inhabilitado',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_lector_curso`),
  UNIQUE KEY `uk_asistencia_lector_curso` (`id_lector`, `id_curso`),
  KEY `idx_asistencia_lector_curso_estado` (`estado`),
  KEY `idx_lector_curso_estado` (`id_lector`,`id_curso`,`estado`),
  CONSTRAINT `fk_asistencia_lectores_cursos_lector` FOREIGN KEY (`id_lector`) REFERENCES `asistencia_lectores` (`id_lector`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencia_lectores_cursos_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
