<?php

/**
 * Helpers para controlar los días en los que NO se toma asistencia
 * (vacaciones, feriados, suspensión de actividades, etc.).
 * Los días marcados en la tabla `dias_no_lectivos` bloquean el registro
 * de asistencia para todos los cursos en la fecha indicada.
 * Cada registro puede ser un día puntual o un rango (fecha_inicio / fecha_fin).
 */

/**
 * Indica si la fecha dada está dentro de un día no lectivo activo.
 * Retorna true si ese día NO se debe tomar asistencia.
 */
function dias_no_lectivos_fecha_no_habilitada(PDO $conn, string $fecha): bool
{
    if ($fecha === '') {
        return false;
    }

    static $tablaExiste = null;
    if ($tablaExiste === null) {
        try {
            $stmtTbl = $conn->prepare("SHOW TABLES LIKE 'dias_no_lectivos'");
            $stmtTbl->execute();
            $tablaExiste = (bool)$stmtTbl->fetchColumn();
        } catch (Throwable $e) {
            $tablaExiste = false;
        }
    }

    if (!$tablaExiste) {
        return false;
    }

    $stmt = $conn->prepare("SELECT 1
        FROM dias_no_lectivos
        WHERE estado = 1 AND ? BETWEEN fecha_inicio AND fecha_fin
        LIMIT 1");
    $stmt->execute([$fecha]);
    return (bool)$stmt->fetchColumn();
}