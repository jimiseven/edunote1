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
    return $userRole === 1 || asistencia_auth_es_lector_admin($lectorInfo);
}

function asistencia_auth_puede_gestionar_permisos(int $userRole, ?array $lectorInfo): bool
{
    return asistencia_auth_puede_ver_reportes($userRole, $lectorInfo);
}
