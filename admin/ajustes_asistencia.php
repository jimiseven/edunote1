<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array((int)($_SESSION['user_role'] ?? 0), [1, 4], true)) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

$diasSemanaEtiquetas = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miercoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sabado',
    7 => 'Domingo',
];

$tablaDiasTurnoExiste = false;
try {
    $stmtTablaDias = $conn->prepare("SHOW TABLES LIKE 'asistencia_curso_turno_dias'");
    $stmtTablaDias->execute();
    $tablaDiasTurnoExiste = (bool)$stmtTablaDias->fetchColumn();
} catch (Throwable $e) {
    $tablaDiasTurnoExiste = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isAjax = (($_POST['ajax'] ?? '') === '1') || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');
    $ajaxPayload = [
        'ok' => false,
        'message' => 'Accion no procesada.',
        'action' => $action,
    ];

    try {
        if ($action === 'crear_horario_global') {
            $fechaInicio = trim((string)($_POST['fecha_inicio'] ?? ''));
            $fechaFin = trim((string)($_POST['fecha_fin'] ?? ''));
            $turno = strtoupper(trim((string)($_POST['turno'] ?? 'MANANA')));
            $horaIngreso = trim((string)($_POST['hora_ingreso'] ?? ''));
            $toleranciaMin = (int)($_POST['tolerancia_min'] ?? 0);
            $estado = (int)($_POST['estado'] ?? 1) === 1 ? 1 : 0;
            $creadoPor = (int)$_SESSION['user_id'];

            if ($fechaInicio === '' || $fechaFin === '' || $horaIngreso === '') {
                throw new RuntimeException('Completa fecha inicio, fecha fin y hora de ingreso.');
            }
            if ($fechaInicio > $fechaFin) {
                throw new RuntimeException('La fecha de inicio no puede ser mayor a la fecha fin.');
            }
            if ($toleranciaMin < 0 || $toleranciaMin > 120) {
                throw new RuntimeException('La tolerancia debe estar entre 0 y 120 minutos.');
            }
            if (!in_array($turno, ['MANANA', 'TARDE'], true)) {
                throw new RuntimeException('Turno inválido.');
            }

            $stmt = $conn->prepare("INSERT INTO asistencia_horarios_turno_global
                (fecha_inicio, fecha_fin, turno, hora_ingreso, tolerancia_min, estado, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fechaInicio, $fechaFin, $turno, $horaIngreso, $toleranciaMin, $estado, $creadoPor]);

            $_SESSION['ajustes_asistencia_flash'] = [
                'type' => 'success',
                'message' => 'Horario global por turno guardado correctamente.'
            ];
            $ajaxPayload = [
                'ok' => true,
                'message' => 'Horario global creado correctamente.',
                'action' => $action,
                'horario' => [
                    'id_horario_global' => (int)$conn->lastInsertId(),
                    'turno' => $turno,
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'hora_ingreso' => $horaIngreso,
                    'tolerancia_min' => $toleranciaMin,
                    'estado' => $estado,
                    'created_at' => date('Y-m-d H:i:s'),
                ],
            ];
        }

        if ($action === 'guardar_curso_turno') {
            $idCurso = (int)($_POST['id_curso'] ?? 0);
            $dobleTurno = 1;
            $estado = 1;
            $creadoPor = (int)$_SESSION['user_id'];

            if ($idCurso <= 0) {
                throw new RuntimeException('Debe seleccionar un curso válido.');
            }

            $stmt = $conn->prepare("INSERT INTO asistencia_cursos_turnos
                (id_curso, doble_turno, estado, creado_por)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    doble_turno = VALUES(doble_turno),
                    estado = VALUES(estado),
                    creado_por = VALUES(creado_por)");
            $stmt->execute([$idCurso, $dobleTurno, $estado, $creadoPor]);

            $stmtCurso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ? LIMIT 1");
            $stmtCurso->execute([$idCurso]);
            $cursoInfo = $stmtCurso->fetch(PDO::FETCH_ASSOC) ?: [];
            $cursoLabel = trim((string)($cursoInfo['nivel'] ?? '') . ' - ' . (string)($cursoInfo['curso'] ?? '') . ' "' . (string)($cursoInfo['paralelo'] ?? '') . '"');

            $_SESSION['ajustes_asistencia_flash'] = [
                'type' => 'success',
                'message' => 'Configuración de curso actualizada.'
            ];
            $ajaxPayload = [
                'ok' => true,
                'message' => 'Curso agregado a turno tarde.',
                'action' => $action,
                'curso' => [
                    'id_curso' => $idCurso,
                    'label' => $cursoLabel,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
            ];
        }

        if ($action === 'quitar_curso_tarde') {
            $idCurso = (int)($_POST['id_curso'] ?? 0);
            if ($idCurso <= 0) {
                throw new RuntimeException('Curso inválido para quitar turno tarde.');
            }

            $stmtCurso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ? LIMIT 1");
            $stmtCurso->execute([$idCurso]);
            $cursoInfo = $stmtCurso->fetch(PDO::FETCH_ASSOC) ?: [];
            $cursoLabel = trim((string)($cursoInfo['nivel'] ?? '') . ' - ' . (string)($cursoInfo['curso'] ?? '') . ' "' . (string)($cursoInfo['paralelo'] ?? '') . '"');

            $stmt = $conn->prepare("DELETE FROM asistencia_cursos_turnos WHERE id_curso = ?");
            $stmt->execute([$idCurso]);

            $_SESSION['ajustes_asistencia_flash'] = [
                'type' => 'success',
                'message' => 'Curso retirado de turno tarde correctamente.'
            ];
            $ajaxPayload = [
                'ok' => true,
                'message' => 'Curso retirado de turno tarde.',
                'action' => $action,
                'id_curso' => $idCurso,
                'curso' => [
                    'id_curso' => $idCurso,
                    'label' => $cursoLabel,
                ],
            ];
        }

        if ($action === 'toggle_estado_global') {
            $idHorario = (int)($_POST['id_horario_global'] ?? 0);
            $estado = (int)($_POST['estado'] ?? 0) === 1 ? 1 : 0;

            if ($idHorario <= 0) {
                throw new RuntimeException('Horario inválido.');
            }

            $stmt = $conn->prepare("UPDATE asistencia_horarios_turno_global SET estado = ? WHERE id_horario_global = ?");
            $stmt->execute([$estado, $idHorario]);

            $_SESSION['ajustes_asistencia_flash'] = [
                'type' => 'success',
                'message' => 'Estado del horario actualizado.'
            ];
            $ajaxPayload = [
                'ok' => true,
                'message' => 'Estado del horario actualizado.',
                'action' => $action,
                'id_horario_global' => $idHorario,
                'estado_nuevo' => $estado,
            ];
        }

        if ($action === 'guardar_dias_tarde_curso') {
            if (!$tablaDiasTurnoExiste) {
                throw new RuntimeException('No existe la tabla asistencia_curso_turno_dias. Ejecuta primero los SQL de migracion.');
            }

            $idCurso = (int)($_POST['id_curso'] ?? 0);
            $diasInput = $_POST['dias_tarde'] ?? [];
            if (!is_array($diasInput)) {
                $diasInput = [];
            }

            if ($idCurso <= 0) {
                throw new RuntimeException('Seleccione un curso valido.');
            }

            $stmtCursoHabilitado = $conn->prepare("SELECT 1
                FROM asistencia_cursos_turnos
                WHERE id_curso = ? AND estado = 1 AND doble_turno = 1
                LIMIT 1");
            $stmtCursoHabilitado->execute([$idCurso]);
            if (!$stmtCursoHabilitado->fetchColumn()) {
                throw new RuntimeException('El curso no esta habilitado para turno tarde.');
            }

            $diasSeleccionados = [];
            foreach ($diasInput as $diaRaw) {
                $dia = (int)$diaRaw;
                if ($dia >= 1 && $dia <= 7) {
                    $diasSeleccionados[$dia] = true;
                }
            }
            $diasSeleccionados = array_keys($diasSeleccionados);
            sort($diasSeleccionados);

            $conn->beginTransaction();

            $stmtDel = $conn->prepare("DELETE FROM asistencia_curso_turno_dias
                WHERE id_curso = ?
                  AND turno = 'TARDE'
                  AND fecha_inicio IS NULL
                  AND fecha_fin IS NULL");
            $stmtDel->execute([$idCurso]);

            if (!empty($diasSeleccionados)) {
                $stmtIns = $conn->prepare("INSERT INTO asistencia_curso_turno_dias
                    (id_curso, turno, dia_semana, estado, fecha_inicio, fecha_fin, creado_por)
                    VALUES (?, 'TARDE', ?, 1, NULL, NULL, ?)");
                $creadoPor = (int)$_SESSION['user_id'];
                foreach ($diasSeleccionados as $dia) {
                    $stmtIns->execute([$idCurso, $dia, $creadoPor]);
                }
            }

            $conn->commit();

            $stmtCurso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ? LIMIT 1");
            $stmtCurso->execute([$idCurso]);
            $cursoInfo = $stmtCurso->fetch(PDO::FETCH_ASSOC) ?: [];
            $cursoLabel = trim((string)($cursoInfo['nivel'] ?? '') . ' - ' . (string)($cursoInfo['curso'] ?? '') . ' "' . (string)($cursoInfo['paralelo'] ?? '') . '"');

            $diasTxt = [];
            foreach ($diasSeleccionados as $dia) {
                if (isset($diasSemanaEtiquetas[$dia])) {
                    $diasTxt[] = $diasSemanaEtiquetas[$dia];
                }
            }
            $diasDesc = empty($diasTxt) ? 'Sin dias habilitados' : implode(', ', $diasTxt);

            $_SESSION['ajustes_asistencia_flash'] = [
                'type' => 'success',
                'message' => 'Dias de turno tarde actualizados para ' . ($cursoLabel !== '' ? $cursoLabel : ('curso #' . $idCurso)) . ': ' . $diasDesc . '.'
            ];

            $ajaxPayload = [
                'ok' => true,
                'message' => 'Dias de turno tarde actualizados.',
                'action' => $action,
            ];
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['ajustes_asistencia_flash'] = [
            'type' => 'danger',
            'message' => 'Error: ' . $e->getMessage()
        ];
        $ajaxPayload = [
            'ok' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'action' => $action,
        ];
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($ajaxPayload);
        exit();
    }

    header('Location: ajustes_asistencia.php');
    exit();
}

$horariosGlobales = $conn->query("SELECT id_horario_global, fecha_inicio, fecha_fin, turno, hora_ingreso, tolerancia_min, estado, created_at
    FROM asistencia_horarios_turno_global
    ORDER BY fecha_inicio DESC, id_horario_global DESC")->fetchAll(PDO::FETCH_ASSOC);

$hoy = date('Y-m-d');
$stmtVigentes = $conn->prepare("SELECT turno, fecha_inicio, fecha_fin, hora_ingreso, tolerancia_min
    FROM asistencia_horarios_turno_global
    WHERE estado = 1 AND ? BETWEEN fecha_inicio AND fecha_fin
    ORDER BY turno ASC, fecha_inicio DESC, id_horario_global DESC");
$stmtVigentes->execute([$hoy]);
$vigentesRows = $stmtVigentes->fetchAll(PDO::FETCH_ASSOC);
$vigenteHoy = [];
foreach ($vigentesRows as $r) {
    $turno = strtoupper((string)($r['turno'] ?? ''));
    if (($turno === 'MANANA' || $turno === 'TARDE') && !isset($vigenteHoy[$turno])) {
        $vigenteHoy[$turno] = $r;
    }
}

$cursos = $conn->query("SELECT id_curso, nivel, curso, paralelo
    FROM cursos
    ORDER BY FIELD(nivel, 'Inicial', 'Primaria', 'Secundaria'), curso, paralelo")->fetchAll(PDO::FETCH_ASSOC);

$cursosTurnos = $conn->query("SELECT act.id_curso, c.nivel, c.curso, c.paralelo, act.doble_turno, act.estado, act.updated_at
    FROM asistencia_cursos_turnos act
    INNER JOIN cursos c ON c.id_curso = act.id_curso
    ORDER BY FIELD(c.nivel, 'Inicial', 'Primaria', 'Secundaria'), c.curso, c.paralelo")->fetchAll(PDO::FETCH_ASSOC);

$cursosDobleTurno = array_values(array_filter($cursosTurnos, static function ($r) {
    return (int)($r['doble_turno'] ?? 0) === 1 && (int)($r['estado'] ?? 0) === 1;
}));

$idsCursosTarde = [];
foreach ($cursosDobleTurno as $ct) {
    $idsCursosTarde[(int)$ct['id_curso']] = true;
}

$cursosDisponiblesTarde = array_values(array_filter($cursos, static function ($c) use ($idsCursosTarde) {
    return !isset($idsCursosTarde[(int)$c['id_curso']]);
}));

$diasTardeCurso = [];
if ($tablaDiasTurnoExiste) {
    $stmtDias = $conn->query("SELECT id_curso, dia_semana
        FROM asistencia_curso_turno_dias
        WHERE turno = 'TARDE'
          AND estado = 1
          AND fecha_inicio IS NULL
          AND fecha_fin IS NULL
        ORDER BY id_curso ASC, dia_semana ASC");
    foreach ($stmtDias->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $cid = (int)($d['id_curso'] ?? 0);
        $dia = (int)($d['dia_semana'] ?? 0);
        if ($cid > 0 && $dia >= 1 && $dia <= 7) {
            if (!isset($diasTardeCurso[$cid])) {
                $diasTardeCurso[$cid] = [];
            }
            $diasTardeCurso[$cid][$dia] = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes de Asistencia</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        .section-block { margin-bottom: 1.25rem; }
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: #1f4f78;
            margin-bottom: 0.6rem;
        }
        .section-title .step {
            display: inline-block;
            min-width: 1.5rem;
            text-align: center;
            background: #1f4f78;
            color: #fff;
            border-radius: 999px;
            margin-right: 0.4rem;
            font-size: 0.78rem;
            line-height: 1.5rem;
        }
        .section-help {
            font-size: 0.87rem;
            color: #55626f;
            margin-bottom: 0.75rem;
        }
        .card-header strong { font-size: 0.95rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <main class="w-100 px-md-4 position-relative py-4">
                <?php if (isset($_SESSION['ajustes_asistencia_flash'])): ?>
                    <?php $flash = $_SESSION['ajustes_asistencia_flash']; unset($_SESSION['ajustes_asistencia_flash']); ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" role="alert">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Ajustes de Asistencia</h1>
                </div>

                <div class="section-block">
                    <div class="section-title"><span class="step">1</span>Resumen Operativo</div>
                    <div class="section-help">Revise rápidamente qué turnos están activos hoy antes de registrar asistencia.</div>
                    <div class="card mb-4">
                        <div class="card-header"><strong>Horarios globales vigentes para hoy</strong></div>
                        <div class="card-body">
                        <?php foreach (['MANANA', 'TARDE'] as $turno): ?>
                            <?php if (isset($vigenteHoy[$turno])): ?>
                                <div class="alert alert-success mb-2">
                                    <strong><?= htmlspecialchars($turno) ?>:</strong>
                                    <?= htmlspecialchars($vigenteHoy[$turno]['fecha_inicio']) ?> a <?= htmlspecialchars($vigenteHoy[$turno]['fecha_fin']) ?> |
                                    Ingreso: <strong><?= htmlspecialchars(substr((string)$vigenteHoy[$turno]['hora_ingreso'], 0, 5)) ?></strong> |
                                    Tolerancia: <strong><?= (int)$vigenteHoy[$turno]['tolerancia_min'] ?> min</strong>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mb-2">
                                    No hay horario activo para el turno <?= htmlspecialchars($turno) ?> hoy.
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-12 col-xl-6">
                        <div class="section-block h-100">
                            <div class="section-title"><span class="step">2</span>Cursos Con Turno Tarde</div>
                            <div class="section-help">Aquí solo agregas excepciones. Si un curso no está en esta lista, registra una sola marca diaria en MANANA.</div>
                            <div class="card mb-0 h-100">
                                <div class="card-header"><strong>Configurar cursos con turno tarde</strong></div>
                                <div class="card-body">
                                <form method="POST" action="" class="row g-3 js-async-form" data-async-action="guardar_curso_turno">
                                    <input type="hidden" name="action" value="guardar_curso_turno">

                                    <div class="col-md-8">
                                        <label class="form-label">Curso</label>
                                        <select name="id_curso" class="form-select" required>
                                            <option value="">Seleccione curso para agregar turno tarde</option>
                                            <?php foreach ($cursosDisponiblesTarde as $curso): ?>
                                                <option value="<?= (int)$curso['id_curso'] ?>">
                                                    <?= htmlspecialchars($curso['nivel'] . ' - ' . $curso['curso'] . ' "' . $curso['paralelo'] . '"') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">Agregar curso</button>
                                    </div>
                                </form>

                                <hr>

                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Curso</th>
                                                <th>Actualizado</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($cursosTurnos)): ?>
                                                <tr><td colspan="3" class="text-center py-3">Sin cursos con turno de tarde.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($cursosTurnos as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['nivel'] . ' - ' . $row['curso'] . ' "' . $row['paralelo'] . '"') ?></td>
                                                        <td><?= htmlspecialchars((string)$row['updated_at']) ?></td>
                                                        <td>
                                                            <form method="POST" action="" class="d-inline js-async-form" data-async-action="quitar_curso_tarde" data-remove-on-success="tr">
                                                                <input type="hidden" name="action" value="quitar_curso_tarde">
                                                                <input type="hidden" name="id_curso" value="<?= (int)$row['id_curso'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">Quitar</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (empty($cursosDobleTurno)): ?>
                                    <div class="alert alert-warning mt-3 mb-0">
                                        No hay cursos con turno tarde. Todo el colegio está en modo de una sola marca diaria (MANANA).
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info mt-3 mb-0">
                                        Cursos con turno tarde: <strong><?= count($cursosDobleTurno) ?></strong>.
                                        En estos cursos se registra MANANA primero y luego TARDE. El resto registra solo MANANA.
                                    </div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <div class="section-block h-100">
                            <div class="section-title"><span class="step">3</span>Horarios Globales</div>
                            <div class="section-help">Define una hora global para MANANA y otra para TARDE. Los cursos con turno tarde usarán ambas.</div>
                            <div class="card mb-0 h-100">
                                <div class="card-header"><strong>Crear horario global por turno</strong></div>
                                <div class="card-body">
                                <form method="POST" action="" class="row g-3 js-async-form" data-async-action="crear_horario_global">
                                    <input type="hidden" name="action" value="crear_horario_global">

                                    <div class="col-md-4">
                                        <label class="form-label">Turno</label>
                                        <select name="turno" class="form-select" required>
                                            <option value="MANANA">MANANA</option>
                                            <option value="TARDE">TARDE</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Fecha inicio</label>
                                        <input type="date" name="fecha_inicio" class="form-control" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Fecha fin</label>
                                        <input type="date" name="fecha_fin" class="form-control" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Hora ingreso</label>
                                        <input type="time" name="hora_ingreso" class="form-control" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Tolerancia (min)</label>
                                        <input type="number" min="0" max="120" name="tolerancia_min" class="form-control" value="0" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Estado</label>
                                        <select name="estado" class="form-select">
                                            <option value="1">Activo</option>
                                            <option value="0">Inactivo</option>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">Guardar horario</button>
                                    </div>
                                </form>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    <div class="col-12">
                        <div class="section-block">
                            <div class="section-title"><span class="step">4</span>Dias de Turno Tarde por Curso</div>
                            <div class="section-help">Define que dias de la semana cada curso habilitado en turno tarde puede registrar asistencia en TARDE.</div>
                            <div class="card mb-4">
                                <div class="card-header"><strong>Configuracion por curso (turno TARDE)</strong></div>
                                <div class="card-body p-0">
                                    <?php if (!$tablaDiasTurnoExiste): ?>
                                        <div class="alert alert-warning m-3 mb-0">
                                            No existe la tabla <code>asistencia_curso_turno_dias</code>. Ejecuta primero los SQL de migracion en
                                            <code>bds/mods trakeov3</code> para habilitar esta funcionalidad.
                                        </div>
                                    <?php elseif (empty($cursosDobleTurno)): ?>
                                        <div class="alert alert-info m-3 mb-0">
                                            No hay cursos en turno tarde para configurar dias.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped mb-0 align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Curso</th>
                                                        <th>Dias habilitados para TARDE</th>
                                                        <th style="width: 160px;">Accion</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($cursosDobleTurno as $row): ?>
                                                        <?php $cursoId = (int)$row['id_curso']; ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($row['nivel'] . ' - ' . $row['curso'] . ' "' . $row['paralelo'] . '"') ?></td>
                                                            <td>
                                                                <form method="POST" action="" class="d-flex flex-wrap gap-3">
                                                                    <input type="hidden" name="action" value="guardar_dias_tarde_curso">
                                                                    <input type="hidden" name="id_curso" value="<?= $cursoId ?>">
                                                                    <?php foreach ($diasSemanaEtiquetas as $numDia => $etqDia): ?>
                                                                        <?php $checked = isset($diasTardeCurso[$cursoId][$numDia]); ?>
                                                                        <div class="form-check form-check-inline me-0">
                                                                            <input class="form-check-input" type="checkbox" id="c<?= $cursoId ?>d<?= $numDia ?>" name="dias_tarde[]" value="<?= $numDia ?>" <?= $checked ? 'checked' : '' ?>>
                                                                            <label class="form-check-label" for="c<?= $cursoId ?>d<?= $numDia ?>"><?= htmlspecialchars($etqDia) ?></label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                            </td>
                                                            <td>
                                                                    <button type="submit" class="btn btn-sm btn-primary">Guardar dias</button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                    <div class="card">
                        <div class="card-header"><strong>Horarios globales configurados</strong></div>
                        <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Turno</th>
                                        <th>Rango</th>
                                        <th>Hora ingreso</th>
                                        <th>Tolerancia</th>
                                        <th>Estado</th>
                                        <th>Creado</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($horariosGlobales)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">No hay horarios globales configurados.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($horariosGlobales as $idx => $h): ?>
                                            <tr>
                                                <td><?= $idx + 1 ?></td>
                                                <td><?= htmlspecialchars((string)$h['turno']) ?></td>
                                                <td><?= htmlspecialchars($h['fecha_inicio'] . ' a ' . $h['fecha_fin']) ?></td>
                                                <td><?= htmlspecialchars(substr($h['hora_ingreso'], 0, 5)) ?></td>
                                                <td><?= (int)$h['tolerancia_min'] ?> min</td>
                                                <td><?= (int)$h['estado'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                                                <td><?= htmlspecialchars($h['created_at']) ?></td>
                                                <td>
                                                    <form method="POST" action="" class="d-inline js-async-form" data-async-action="toggle_estado_global">
                                                        <input type="hidden" name="action" value="toggle_estado_global">
                                                        <input type="hidden" name="id_horario_global" value="<?= (int)$h['id_horario_global'] ?>">
                                                        <input type="hidden" name="estado" value="<?= (int)$h['estado'] === 1 ? 0 : 1 ?>">
                                                        <button type="submit" class="btn btn-sm <?= (int)$h['estado'] === 1 ? 'btn-warning' : 'btn-success' ?>">
                                                            <?= (int)$h['estado'] === 1 ? 'Desactivar' : 'Activar' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="accionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="accionModalTitle">Resultado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="accionModalBody"></div>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const modalEl = document.getElementById('accionModal');
            const modalTitle = document.getElementById('accionModalTitle');
            const modalBody = document.getElementById('accionModalBody');
            const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
            let modalTimer = null;

            function showResult(ok, message) {
                if (!modal) return;
                modalTitle.textContent = ok ? 'Operacion completada' : 'Operacion con error';
                modalBody.textContent = message || (ok ? 'Cambios aplicados.' : 'No se pudo completar la operacion.');
                modal.show();
                if (modalTimer) {
                    clearTimeout(modalTimer);
                }
                modalTimer = setTimeout(() => {
                    modal.hide();
                }, 2000);
            }

            function updateToggleFormState(form, estadoNuevo) {
                const btn = form.querySelector('button[type="submit"]');
                const estadoInput = form.querySelector('input[name="estado"]');
                const row = form.closest('tr');
                if (!btn || !estadoInput) return;
                if (Number(estadoNuevo) === 1) {
                    btn.textContent = 'Desactivar';
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-warning');
                    estadoInput.value = '0';
                    if (row && row.children[5]) row.children[5].textContent = 'Activo';
                } else {
                    btn.textContent = 'Activar';
                    btn.classList.remove('btn-warning');
                    btn.classList.add('btn-success');
                    estadoInput.value = '1';
                    if (row && row.children[5]) row.children[5].textContent = 'Inactivo';
                }
            }

            function ensureNoDataRow(tbody, colspan, text) {
                if (!tbody) return;
                if (tbody.querySelectorAll('tr').length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td colspan="${colspan}" class="text-center py-3">${text}</td>`;
                    tbody.appendChild(row);
                }
            }

            function removeNoDataRow(tbody) {
                if (!tbody) return;
                const onlyRow = tbody.querySelector('tr td[colspan]');
                if (onlyRow) {
                    onlyRow.parentElement.remove();
                }
            }

            function formatDateTime(value) {
                return value || new Date().toISOString().slice(0, 19).replace('T', ' ');
            }

            function renderCursoTardeRow(curso) {
                return `<tr>
                    <td>${curso.label}</td>
                    <td>${formatDateTime(curso.updated_at)}</td>
                    <td>
                        <form method="POST" action="" class="d-inline js-async-form" data-async-action="quitar_curso_tarde" data-remove-on-success="tr">
                            <input type="hidden" name="action" value="quitar_curso_tarde">
                            <input type="hidden" name="id_curso" value="${curso.id_curso}">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Quitar</button>
                        </form>
                    </td>
                </tr>`;
            }

            function renderHorarioGlobalRow(h) {
                const estadoTxt = Number(h.estado) === 1 ? 'Activo' : 'Inactivo';
                const btnClass = Number(h.estado) === 1 ? 'btn-warning' : 'btn-success';
                const btnText = Number(h.estado) === 1 ? 'Desactivar' : 'Activar';
                const estadoNext = Number(h.estado) === 1 ? 0 : 1;
                const hora = String(h.hora_ingreso || '').slice(0, 5);
                return `<tr>
                    <td>Nuevo</td>
                    <td>${h.turno}</td>
                    <td>${h.fecha_inicio} a ${h.fecha_fin}</td>
                    <td>${hora}</td>
                    <td>${h.tolerancia_min} min</td>
                    <td>${estadoTxt}</td>
                    <td>${formatDateTime(h.created_at)}</td>
                    <td>
                        <form method="POST" action="" class="d-inline js-async-form" data-async-action="toggle_estado_global">
                            <input type="hidden" name="action" value="toggle_estado_global">
                            <input type="hidden" name="id_horario_global" value="${h.id_horario_global}">
                            <input type="hidden" name="estado" value="${estadoNext}">
                            <button type="submit" class="btn btn-sm ${btnClass}">${btnText}</button>
                        </form>
                    </td>
                </tr>`;
            }

            function refreshInfoAlert() {
                const info = document.querySelector('.section-block .alert-info');
                const warn = document.querySelector('.section-block .alert-warning');
                const tbody = document.querySelector('.section-block .table-responsive tbody');
                if (!tbody) return;
                const count = tbody.querySelectorAll('tr').length;
                if (count === 0) {
                    if (info) info.style.display = 'none';
                    if (warn) warn.style.display = '';
                } else {
                    if (warn) warn.style.display = 'none';
                    if (info) {
                        info.style.display = '';
                        info.innerHTML = `Cursos con turno tarde: <strong>${count}</strong>. En estos cursos se registra MANANA primero y luego TARDE. El resto registra solo MANANA.`;
                    }
                }
            }

            async function handleAsyncSubmit(form) {
                const data = new FormData(form);
                data.set('ajax', '1');

                const submitBtn = form.querySelector('button[type="submit"]');
                const prevText = submitBtn ? submitBtn.textContent : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Guardando...';
                }

                try {
                    const response = await fetch(form.getAttribute('action') || window.location.pathname, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: data,
                    });
                    const json = await response.json();
                    showResult(Boolean(json.ok), json.message || 'Operacion completada.');

                    if (json.ok && (form.dataset.asyncAction || '') === 'guardar_curso_turno' && json.curso) {
                        const tbody = form.closest('.card-body').querySelector('table tbody');
                        removeNoDataRow(tbody);
                        tbody.insertAdjacentHTML('afterbegin', renderCursoTardeRow(json.curso));
                        const select = form.querySelector('select[name="id_curso"]');
                        if (select) {
                            const option = select.querySelector(`option[value="${json.curso.id_curso}"]`);
                            if (option) option.remove();
                            select.value = '';
                        }
                        refreshInfoAlert();
                    }

                    if (json.ok && (form.dataset.asyncAction || '') === 'quitar_curso_tarde') {
                        const row = form.closest('tr');
                        const tbody = row ? row.parentElement : null;

                        if (form.dataset.removeOnSuccess === 'tr') {
                            if (row) row.remove();
                        }

                        if (json.curso) {
                            const select = document.querySelector('select[name="id_curso"]');
                            if (select && !select.querySelector(`option[value="${json.curso.id_curso}"]`)) {
                                const opt = document.createElement('option');
                                opt.value = String(json.curso.id_curso);
                                opt.textContent = json.curso.label;
                                select.appendChild(opt);
                            }
                        }

                        ensureNoDataRow(tbody, 3, 'Sin cursos con turno de tarde.');
                        refreshInfoAlert();
                    }

                    if (json.ok && (form.dataset.asyncAction || '') === 'crear_horario_global' && json.horario) {
                        const card = document.querySelector('.card-header strong');
                        const tableBody = Array.from(document.querySelectorAll('table tbody')).find((tb) => tb.closest('.card').querySelector('.card-header') && tb.closest('.card').querySelector('.card-header').textContent.includes('Horarios globales configurados'));
                        if (tableBody) {
                            const maybeEmpty = tableBody.querySelector('td[colspan="8"]');
                            if (maybeEmpty) maybeEmpty.parentElement.remove();
                            tableBody.insertAdjacentHTML('afterbegin', renderHorarioGlobalRow(json.horario));
                        }
                        form.reset();
                    }

                    if (json.ok && (form.dataset.asyncAction || '') === 'toggle_estado_global') {
                        updateToggleFormState(form, Number(json.estado_nuevo));
                    }
                } catch (err) {
                    showResult(false, 'No se pudo procesar la solicitud. Verifique la conexion e intente nuevamente.');
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = prevText;
                    }
                }
            }

            document.addEventListener('submit', function (e) {
                const form = e.target;
                if (!form || !form.matches('.js-async-form')) {
                    return;
                }
                    e.preventDefault();
                    handleAsyncSubmit(form);
            });
        })();
    </script>
</body>
</html>
