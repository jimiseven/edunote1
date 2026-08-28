-- Migración incremental: convertir el esquema "día puntual" (columna `fecha`)
-- de `dias_no_lectivos` al de rango (fecha_inicio / fecha_fin).
-- Aplicar SOLO si la tabla ya existe con el esquema antiguo (columna `fecha`).

ALTER TABLE `dias_no_lectivos`
  CHANGE COLUMN `fecha` `fecha_inicio` date NOT NULL COMMENT 'Inicio del día o rango sin asistencia',
  ADD COLUMN `fecha_fin` date NOT NULL DEFAULT '1970-01-01' COMMENT 'Fin del rango sin asistencia' AFTER `fecha_inicio`;

-- Los registros existentes quedan como día puntual (inicio = fin).
UPDATE `dias_no_lectivos` SET `fecha_fin` = `fecha_inicio` WHERE `fecha_fin` = '1970-01-01';

ALTER TABLE `dias_no_lectivos`
  DROP INDEX `uk_dia_no_lectivo_fecha`,
  DROP INDEX `idx_dia_no_lectivo_fecha_estado`,
  ADD UNIQUE KEY `uk_dia_no_lectivo_rango` (`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_dia_no_lectivo_rango_estado` (`fecha_inicio`,`fecha_fin`,`estado`);