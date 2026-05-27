ALTER TABLE asistencia
  ADD INDEX idx_asistencia_fecha_est_hora (fecha, id_estudiante, hora_entrada);

ALTER TABLE estudiantes
  ADD INDEX idx_estudiantes_curso_est (id_curso, id_estudiante);

ALTER TABLE asistencia_horarios_ingreso
  ADD INDEX idx_horario_estado_rango (estado, fecha_inicio, fecha_fin);

ALTER TABLE asistencia_lectores
  ADD INDEX idx_lector_personal_estado (id_personal, estado);

ALTER TABLE asistencia_lectores_cursos
  ADD INDEX idx_lector_curso_estado (id_lector, id_curso, estado);
