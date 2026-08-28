-- Migración: tabla de días no lectivos (días en los que NO se toma asistencia)
-- Ej: vacaciones, feriados, días pedagógicos u otras suspensiones de actividades.
-- Soporta un día puntual (fecha_inicio = fecha_fin) o un rango de fechas.
-- Este archivo debe ejecutarse una sola vez en la base de datos.
-- Si la tabla ya existe con el esquema antiguo (solo fecha), aplicar primero
-- bds/26 ago/dias_no_lectivos_rangos.sql.

CREATE TABLE IF NOT EXISTS `dias_no_lectivos` (
  `id_dia_no_lectivo` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL COMMENT 'Inicio del día o rango sin asistencia',
  `fecha_fin` date NOT NULL COMMENT 'Fin del rango sin asistencia (igual a fecha_inicio si es un solo día)',
  `motivo` varchar(150) NOT NULL DEFAULT '' COMMENT 'Descripción del motivo (vacación, feriado, etc.)',
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=activo (suspende asistencia), 0=inactivo',
  `creado_por` int(11) DEFAULT NULL COMMENT 'FK a personal',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `dias_no_lectivos`
  ADD PRIMARY KEY (`id_dia_no_lectivo`),
  ADD UNIQUE KEY `uk_dia_no_lectivo_rango` (`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_dia_no_lectivo_estado` (`estado`),
  ADD KEY `idx_dia_no_lectivo_rango_estado` (`fecha_inicio`,`fecha_fin`,`estado`),
  ADD KEY `fk_dias_no_lectivos_personal` (`creado_por`);

ALTER TABLE `dias_no_lectivos`
  MODIFY `id_dia_no_lectivo` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `dias_no_lectivos`
  ADD CONSTRAINT `fk_dias_no_lectivos_personal` FOREIGN KEY (`creado_por`) REFERENCES `personal` (`id_personal`) ON DELETE SET NULL ON UPDATE CASCADE;