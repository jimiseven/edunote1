-- Detalle por casilla (SER / SABER / HACER) vinculado a calificaciones_parciales.
-- Si no usas la creación automática desde cargar_notas.php, ejecuta este script en tu base (ej. colegiov2).

CREATE TABLE IF NOT EXISTS calificaciones_parciales_detalle (
  id_detalle int(11) NOT NULL AUTO_INCREMENT,
  id_calificacion_parcial int(11) NOT NULL,
  area varchar(10) NOT NULL,
  indice tinyint(4) NOT NULL,
  nota decimal(8,2) DEFAULT NULL,
  creado_por int(11) DEFAULT NULL,
  PRIMARY KEY (id_detalle),
  UNIQUE KEY uk_calif_area_idx (id_calificacion_parcial, area, indice),
  KEY idx_calificacion (id_calificacion_parcial),
  CONSTRAINT fk_cpd_calificacion FOREIGN KEY (id_calificacion_parcial)
    REFERENCES calificaciones_parciales (id_calificacion_parcial) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
