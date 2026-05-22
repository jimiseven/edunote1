<?php
session_start();
require_once '../config/database.php';
require_once '../includes/asistencia_auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();
$userRole = (int)($_SESSION['user_role'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$lectorInfo = asistencia_auth_get_lector($conn, $userId);

if (!asistencia_auth_puede_ver_reportes($userRole, $lectorInfo)) {
    http_response_code(403);
    echo '<h3>Acceso denegado</h3><p>No tienes permisos para ver reportes de asistencia.</p>';
    exit();
}

$nivelesPermitidos = ['Inicial', 'Primaria', 'Secundaria'];
$nivel = $_GET['nivel'] ?? '';
if (!in_array($nivel, $nivelesPermitidos, true)) {
    $nivel = '';
}

$turno = strtoupper((string)($_GET['turno'] ?? 'MANANA'));
if (!in_array($turno, ['MANANA', 'TARDE'], true)) {
    $turno = 'MANANA';
}

$idCurso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
$fecha = $_GET['fecha'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $stmtFecha = $conn->prepare("SELECT MAX(fecha) AS ultima_fecha FROM asistencia WHERE turno = ?");
    $stmtFecha->execute([$turno]);
    $ultimaFecha = (string)($stmtFecha->fetchColumn() ?: '');
    if ($ultimaFecha === '') {
        $stmtFechaGlobal = $conn->query("SELECT MAX(fecha) AS ultima_fecha FROM asistencia");
        $ultimaFecha = (string)($stmtFechaGlobal->fetchColumn() ?: '');
    }
    $fecha = $ultimaFecha !== '' ? $ultimaFecha : date('Y-m-d');
}
$tipoReporte = $_GET['tipo_reporte'] ?? 'llegada';
if (!in_array($tipoReporte, ['llegada', 'puntualidad'], true)) {
    $tipoReporte = 'llegada';
}

$stmtTurnosActivos = $conn->prepare("SELECT turno
    FROM asistencia_horarios_turno_global
    WHERE estado = 1 AND ? BETWEEN fecha_inicio AND fecha_fin");
$stmtTurnosActivos->execute([$fecha]);
$turnosActivos = [];
foreach ($stmtTurnosActivos->fetchAll(PDO::FETCH_COLUMN) as $turnoRow) {
    $turnoNorm = strtoupper((string)$turnoRow);
    if ($turnoNorm === 'MANANA' || $turnoNorm === 'TARDE') {
        $turnosActivos[$turnoNorm] = true;
    }
}
$turnoHabilitadoPorHorario = isset($turnosActivos[$turno]);

$stmtHorariosGlobales = $conn->prepare("SELECT turno, hora_ingreso, tolerancia_min
    FROM asistencia_horarios_turno_global
    WHERE estado = 1 AND ? BETWEEN fecha_inicio AND fecha_fin
    ORDER BY fecha_inicio DESC, id_horario_global DESC");
$stmtHorariosGlobales->execute([$fecha]);
$horariosGlobales = [
    'MANANA' => '',
    'TARDE' => '',
];
$toleranciasGlobales = [
    'MANANA' => 0,
    'TARDE' => 0,
];
foreach ($stmtHorariosGlobales->fetchAll(PDO::FETCH_ASSOC) as $hRow) {
    $hTurno = strtoupper((string)($hRow['turno'] ?? ''));
    if (($hTurno === 'MANANA' || $hTurno === 'TARDE') && $horariosGlobales[$hTurno] === '') {
        $horariosGlobales[$hTurno] = (string)($hRow['hora_ingreso'] ?? '');
        $toleranciasGlobales[$hTurno] = max((int)($hRow['tolerancia_min'] ?? 0), 0);
    }
}

$usarTurnoInferidoPorHora = $horariosGlobales['MANANA'] !== '' && $horariosGlobales['TARDE'] !== '';
$turnoFiltroSql = "UPPER(COALESCE(a.turno, 'MANANA')) = ?";
$turnoFiltroParams = [$turno];
if ($usarTurnoInferidoPorHora) {
    $turnoFiltroSql = "CASE
            WHEN a.hora_entrada IS NULL THEN UPPER(COALESCE(a.turno, 'MANANA'))
            WHEN ABS(TIME_TO_SEC(TIMEDIFF(a.hora_entrada, ?))) <= ABS(TIME_TO_SEC(TIMEDIFF(a.hora_entrada, ?))) THEN 'MANANA'
            ELSE 'TARDE'
        END = ?";
    $turnoFiltroParams = [$horariosGlobales['MANANA'], $horariosGlobales['TARDE'], $turno];
}

$usarPuntualidadPorHorario = $horariosGlobales[$turno] !== '';
$puntualidadTempranoSql = "COUNT(DISTINCT CASE WHEN a.id_asistencia IS NOT NULL AND UPPER(COALESCE(a.estado_puntualidad, '')) <> 'TARDE' THEN a.id_estudiante END) AS temprano";
$puntualidadRetrasoSql = "COUNT(DISTINCT CASE WHEN UPPER(COALESCE(a.estado_puntualidad, '')) = 'TARDE' THEN a.id_estudiante END) AS retrasados";
$puntualidadDetalleSql = "CASE
        WHEN a.id_asistencia IS NULL THEN NULL
        WHEN UPPER(COALESCE(a.estado_puntualidad, '')) = 'TARDE' THEN 'RETRASADO'
        ELSE 'TEMPRANO'
    END AS puntualidad_calculada";
$paramsPuntualidadResumen = [];
$paramsPuntualidadDetalle = [];

if ($usarPuntualidadPorHorario) {
    $horaTurno = $horariosGlobales[$turno];
    $toleranciaTurno = $toleranciasGlobales[$turno];
    $puntualidadTempranoSql = "COUNT(DISTINCT CASE
            WHEN a.id_asistencia IS NOT NULL
             AND a.hora_entrada <= ADDTIME(?, SEC_TO_TIME(? * 60))
            THEN a.id_estudiante
        END) AS temprano";
    $puntualidadRetrasoSql = "COUNT(DISTINCT CASE
            WHEN a.id_asistencia IS NOT NULL
             AND a.hora_entrada > ADDTIME(?, SEC_TO_TIME(? * 60))
            THEN a.id_estudiante
        END) AS retrasados";
    $puntualidadDetalleSql = "CASE
            WHEN a.id_asistencia IS NULL THEN NULL
            WHEN a.hora_entrada <= ADDTIME(?, SEC_TO_TIME(? * 60)) THEN 'TEMPRANO'
            ELSE 'RETRASADO'
        END AS puntualidad_calculada";
    $paramsPuntualidadResumen = [$horaTurno, $toleranciaTurno, $horaTurno, $toleranciaTurno];
    $paramsPuntualidadDetalle = [$horaTurno, $toleranciaTurno];
}

if ($nivel !== '') {
    if ($turno === 'TARDE') {
        $stmtCursos = $conn->prepare("SELECT c.id_curso, c.nivel, c.curso, c.paralelo
            FROM cursos c
            INNER JOIN asistencia_cursos_turnos act ON act.id_curso = c.id_curso AND act.estado = 1 AND act.doble_turno = 1
            WHERE c.nivel = ?
            ORDER BY c.curso, c.paralelo");
        $stmtCursos->execute([$nivel]);
    } else {
        $stmtCursos = $conn->prepare("SELECT id_curso, nivel, curso, paralelo FROM cursos WHERE nivel = ? ORDER BY curso, paralelo");
        $stmtCursos->execute([$nivel]);
    }
} else {
    if ($turno === 'TARDE') {
        $stmtCursos = $conn->query("SELECT c.id_curso, c.nivel, c.curso, c.paralelo
            FROM cursos c
            INNER JOIN asistencia_cursos_turnos act ON act.id_curso = c.id_curso AND act.estado = 1 AND act.doble_turno = 1
            ORDER BY c.nivel, c.curso, c.paralelo");
    } else {
        $stmtCursos = $conn->query("SELECT id_curso, nivel, curso, paralelo FROM cursos ORDER BY nivel, curso, paralelo");
    }
}
$cursos = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);
$idsCursosDisponibles = [];
foreach ($cursos as $cursoTmp) {
    $idsCursosDisponibles[(int)$cursoTmp['id_curso']] = true;
}
if ($idCurso > 0 && !isset($idsCursosDisponibles[$idCurso])) {
    $idCurso = 0;
}

$resumenCursos = [];
if ($nivel !== '') {
    $stmtResumen = $conn->prepare("SELECT
            c.id_curso,
            c.nivel,
            c.curso,
            c.paralelo,
            COUNT(DISTINCT e.id_estudiante) AS total_estudiantes,
            COUNT(DISTINCT a.id_estudiante) AS llegaron,
            " . $puntualidadTempranoSql . ",
            " . $puntualidadRetrasoSql . "
        FROM cursos c
        LEFT JOIN estudiantes e ON e.id_curso = c.id_curso
        LEFT JOIN asistencia a ON a.id_estudiante = e.id_estudiante AND a.fecha = ? AND " . $turnoFiltroSql . "
        LEFT JOIN asistencia_cursos_turnos act ON act.id_curso = c.id_curso
        WHERE c.nivel = ?
          AND (? <> 'TARDE' OR (act.estado = 1 AND act.doble_turno = 1))
        GROUP BY c.id_curso, c.nivel, c.curso, c.paralelo
        ORDER BY c.curso ASC, c.paralelo ASC");
    $paramsResumen = array_merge($paramsPuntualidadResumen, [$fecha], $turnoFiltroParams, [$nivel, $turno]);
    $stmtResumen->execute($paramsResumen);
} else {
    $stmtResumen = $conn->prepare("SELECT
            c.id_curso,
            c.nivel,
            c.curso,
            c.paralelo,
            COUNT(DISTINCT e.id_estudiante) AS total_estudiantes,
            COUNT(DISTINCT a.id_estudiante) AS llegaron,
            " . $puntualidadTempranoSql . ",
            " . $puntualidadRetrasoSql . "
        FROM cursos c
        LEFT JOIN estudiantes e ON e.id_curso = c.id_curso
        LEFT JOIN asistencia a ON a.id_estudiante = e.id_estudiante AND a.fecha = ? AND " . $turnoFiltroSql . "
        LEFT JOIN asistencia_cursos_turnos act ON act.id_curso = c.id_curso
        WHERE (? <> 'TARDE' OR (act.estado = 1 AND act.doble_turno = 1))
        GROUP BY c.id_curso, c.nivel, c.curso, c.paralelo
        ORDER BY c.nivel ASC, c.curso ASC, c.paralelo ASC");
    $paramsResumen = array_merge($paramsPuntualidadResumen, [$fecha], $turnoFiltroParams, [$turno]);
    $stmtResumen->execute($paramsResumen);
}
$resumenCursos = $stmtResumen->fetchAll(PDO::FETCH_ASSOC);

$registros = [];
if ($idCurso > 0) {
    $stmt = $conn->prepare("SELECT e.apellido_paterno, e.apellido_materno, e.nombres,
            a.fecha, a.hora_entrada, a.estado_puntualidad,
            " . $puntualidadDetalleSql . ",
            CASE WHEN a.id_asistencia IS NULL THEN 0 ELSE 1 END AS llego
        FROM estudiantes e
        LEFT JOIN asistencia a ON a.id_estudiante = e.id_estudiante AND a.fecha = ? AND " . $turnoFiltroSql . "
        WHERE e.id_curso = ?
        ORDER BY llego DESC, a.hora_entrada ASC, e.apellido_paterno ASC, e.apellido_materno ASC, e.nombres ASC");
    $paramsDetalle = array_merge($paramsPuntualidadDetalle, [$fecha], $turnoFiltroParams, [$idCurso]);
    $stmt->execute($paramsDetalle);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Asistencia por Curso</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <main class="w-100 px-md-4 position-relative py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Reporte de Asistencia por Curso</h1>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <?php if (!$turnoHabilitadoPorHorario): ?>
                            <div class="alert alert-info mb-3">
                                Para la fecha <strong><?= htmlspecialchars($fecha) ?></strong> no hay horario global activo en
                                <strong><?= htmlspecialchars($turno) ?></strong>. Se muestran igualmente los registros guardados en asistencia.
                            </div>
                        <?php endif; ?>
                        <?php if ($usarTurnoInferidoPorHora): ?>
                            <div class="alert alert-secondary mb-3">
                                Clasificacion de turno por hora activa para <?= htmlspecialchars($fecha) ?>:
                                MANANA <?= htmlspecialchars($horariosGlobales['MANANA']) ?> y TARDE <?= htmlspecialchars($horariosGlobales['TARDE']) ?>.
                            </div>
                        <?php endif; ?>
                        <?php if ($tipoReporte === 'puntualidad' && !$usarPuntualidadPorHorario): ?>
                            <div class="alert alert-warning mb-3">
                                No hay horario global del turno <?= htmlspecialchars($turno) ?> para <?= htmlspecialchars($fecha) ?>.
                                La puntualidad se calcula usando el estado guardado en cada registro.
                            </div>
                        <?php endif; ?>
                        <form class="row g-3" method="GET" action="">
                            <div class="col-md-4">
                                <label class="form-label">Nivel</label>
                                <select class="form-select" id="nivel" name="nivel">
                                    <option value="">Seleccione un nivel</option>
                                    <?php foreach ($nivelesPermitidos as $nivelOpt): ?>
                                        <option value="<?= htmlspecialchars($nivelOpt) ?>" <?= $nivel === $nivelOpt ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($nivelOpt) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Curso</label>
                                <select class="form-select" name="id_curso" id="id_curso" <?= $nivel === '' ? 'disabled' : '' ?>>
                                    <option value="">Seleccione un curso</option>
                                    <?php foreach ($cursos as $curso): ?>
                                        <option value="<?= (int)$curso['id_curso'] ?>" <?= $idCurso === (int)$curso['id_curso'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($curso['nivel'] . ' ' . $curso['curso'] . ' "' . $curso['paralelo'] . '"') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" name="fecha" value="<?= htmlspecialchars($fecha) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Turno</label>
                                <select class="form-select" name="turno">
                                    <option value="MANANA" <?= $turno === 'MANANA' ? 'selected' : '' ?>>Manana</option>
                                    <option value="TARDE" <?= $turno === 'TARDE' ? 'selected' : '' ?>>Tarde</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo de reporte</label>
                                <select class="form-select" name="tipo_reporte">
                                    <option value="llegada" <?= $tipoReporte === 'llegada' ? 'selected' : '' ?>>Llegada (llegaron vs no llegaron)</option>
                                    <option value="puntualidad" <?= $tipoReporte === 'puntualidad' ? 'selected' : '' ?>>Puntualidad (temprano vs retrasados)</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Ver reporte</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                        <div class="card-header">
                        Resumen por curso (<?= htmlspecialchars($fecha) ?> - <?= htmlspecialchars($turno) ?>)
                        - <?= $tipoReporte === 'puntualidad' ? 'Puntualidad' : 'Llegada' ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Curso</th>
                                        <th>Total estudiantes</th>
                                        <?php if ($tipoReporte === 'puntualidad'): ?>
                                            <th>Temprano</th>
                                            <th>Retrasados</th>
                                        <?php else: ?>
                                            <th>Llegaron</th>
                                            <th>No llegaron</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($resumenCursos)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">No hay cursos para los filtros seleccionados.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($resumenCursos as $i => $rc): ?>
                                            <?php
                                                $totalEst = (int)($rc['total_estudiantes'] ?? 0);
                                                $llegaron = (int)($rc['llegaron'] ?? 0);
                                                $noLlegaron = max($totalEst - $llegaron, 0);
                                            ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars(($rc['nivel'] ?? '') . ' ' . ($rc['curso'] ?? '') . ' "' . ($rc['paralelo'] ?? '') . '"') ?></td>
                                                <td><?= $totalEst ?></td>
                                                <?php if ($tipoReporte === 'puntualidad'): ?>
                                                    <td class="text-primary"><?= (int)($rc['temprano'] ?? 0) ?></td>
                                                    <td class="text-warning"><?= (int)($rc['retrasados'] ?? 0) ?></td>
                                                <?php else: ?>
                                                    <td class="text-success"><?= $llegaron ?></td>
                                                    <td class="text-danger"><?= $noLlegaron ?></td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if ($idCurso > 0): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Reporte del curso seleccionado (<?= htmlspecialchars($fecha) ?> - <?= htmlspecialchars($turno) ?>)</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Estudiante</th>
                                            <th>Llegada</th>
                                            <th>Hora de entrada</th>
                                            <th>Puntualidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($registros)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">No hay registros para los filtros seleccionados.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($registros as $index => $row): ?>
                                                <?php $llego = (int)($row['llego'] ?? 0) === 1; ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($row['apellido_paterno'] . ' ' . $row['apellido_materno'] . ', ' . $row['nombres']) ?></td>
                                                    <td class="<?= $llego ? 'text-success' : 'text-danger' ?>"><?= $llego ? 'LLEGO' : 'NO LLEGO' ?></td>
                                                    <td><?= htmlspecialchars($llego ? (string)($row['hora_entrada'] ?? '-') : '-') ?></td>
                                                    <td>
                                                        <?php if (!$llego): ?>
                                                            -
                                                        <?php else: ?>
                                                            <?= htmlspecialchars((string)($row['puntualidad_calculada'] ?? '-')) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.0/dist/feather.min.js"></script>
    <script>
        feather.replace();
    </script>
</body>
</html>
