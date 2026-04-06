-- Corrige chk_cpd_nota cuando solo permite 0–10 y fallan notas SABER (hasta 45) o HACER (hasta 40).
-- Ejecutar en phpMyAdmin sobre colegiov2 si el guardado sigue fallando.

ALTER TABLE calificaciones_parciales_detalle DROP CHECK chk_cpd_nota;

-- Si el anterior falla (MariaDB u otra versión), probar:
-- ALTER TABLE calificaciones_parciales_detalle DROP CONSTRAINT chk_cpd_nota;

ALTER TABLE calificaciones_parciales_detalle
  ADD CONSTRAINT chk_cpd_nota CHECK (
    (nota IS NULL) OR
    (area = 'SER' AND nota >= 0 AND nota <= 10) OR
    (area = 'SABER' AND nota >= 0 AND nota <= 45) OR
    (area = 'HACER' AND nota >= 0 AND nota <= 40)
  );
