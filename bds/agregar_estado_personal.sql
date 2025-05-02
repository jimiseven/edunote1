-- Script para agregar columna estado a la tabla personal
ALTER TABLE personal ADD COLUMN estado TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=habilitado, 0=inhabilitado';

-- Actualizar todos los registros existentes como habilitados por defecto
UPDATE personal SET estado = 1 WHERE estado IS NULL;