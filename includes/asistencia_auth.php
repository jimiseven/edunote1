<?php

function asistencia_auth_get_lector(PDO $conn, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT id_lector, id_personal, alcance, tipo_lector, estado
        FROM asistencia_lectores
        WHERE id_personal = ? AND estado = 1
        LIMIT 1");
    $stmt->execute([$userId]);
    $lector = $stmt->fetch(PDO::FETCH_ASSOC);

    return $lector ?: null;
}

function asistencia_auth_es_lector_admin(?array $lectorInfo): bool
{
    return isset($lectorInfo['tipo_lector']) && $lectorInfo['tipo_lector'] === 'ADMINISTRADOR';
}

function asistencia_auth_puede_ver_reportes(int $userRole, ?array $lectorInfo): bool
{
    return $userRole === 1 || $userRole === 4 || asistencia_auth_es_lector_admin($lectorInfo);
}

function asistencia_auth_puede_gestionar_permisos(int $userRole, ?array $lectorInfo): bool
{
    return asistencia_auth_puede_ver_reportes($userRole, $lectorInfo);
}

function asistencia_auth_turno_habilitado_para_fecha(PDO $conn, int $idCurso, string $turno, string $fecha): array
{
    $resultado = [
        'habilitado' => false,
        'motivo' => '',
    ];

    if ($idCurso <= 0 || $fecha === '' || !in_array($turno, ['MANANA', 'TARDE'], true)) {
        $resultado['motivo'] = 'Parametros invalidos para validar el turno.';
        return $resultado;
    }

    if ($turno === 'MANANA') {
        $resultado['habilitado'] = true;
        return $resultado;
    }

    $stmtDoble = $conn->prepare("SELECT doble_turno
        FROM asistencia_cursos_turnos
        WHERE id_curso = ? AND estado = 1
        LIMIT 1");
    $stmtDoble->execute([$idCurso]);
    $esDoble = ((int)$stmtDoble->fetchColumn()) === 1;

    if (!$esDoble) {
        $resultado['motivo'] = 'Este curso no tiene turno TARDE habilitado.';
        return $resultado;
    }

    static $cacheTablaExiste = null;
    if ($cacheTablaExiste === null) {
        $stmtTbl = $conn->prepare("SHOW TABLES LIKE 'asistencia_curso_turno_dias'");
        $stmtTbl->execute();
        $cacheTablaExiste = (bool)$stmtTbl->fetchColumn();
    }

    if (!$cacheTablaExiste) {
        $resultado['habilitado'] = true;
        return $resultado;
    }

    $diaSemana = (int)date('N', strtotime($fecha));

    $stmtDias = $conn->prepare("SELECT 1
        FROM asistencia_curso_turno_dias
        WHERE id_curso = ?
          AND turno = 'TARDE'
          AND estado = 1
          AND dia_semana = ?
          AND (fecha_inicio IS NULL OR fecha_inicio <= ?)
          AND (fecha_fin IS NULL OR fecha_fin >= ?)
        LIMIT 1");
    $stmtDias->execute([$idCurso, $diaSemana, $fecha, $fecha]);
    $tardeHabilitada = (bool)$stmtDias->fetchColumn();

    if (!$tardeHabilitada) {
        $nombresDias = [1 => 'lunes', 2 => 'martes', 3 => 'miercoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sabado', 7 => 'domingo'];
        $nombreDia = $nombresDias[$diaSemana] ?? '';
        $resultado['motivo'] = 'Este curso no tiene turno TARDE configurado para el dia ' . $nombreDia . ' (' . $fecha . ').';
        return $resultado;
    }

    $resultado['habilitado'] = true;
    return $resultado;
}
