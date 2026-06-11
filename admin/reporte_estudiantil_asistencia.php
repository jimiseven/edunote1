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
    echo '<h3>Acceso denegado</h3><p>No tienes permisos para ver reportes estudiantiles de asistencia.</p>';
    exit();
}

$q = trim((string)($_GET['q'] ?? ''));
$idEstudiante = (int)($_GET['id_estudiante'] ?? 0);
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
    $fechaInicio = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
    $fechaFin = date('Y-m-d');
}
if ($fechaInicio > $fechaFin) {
    $tmp = $fechaInicio;
    $fechaInicio = $fechaFin;
    $fechaFin = $tmp;
}

$stmtLista = $conn->query("SELECT e.id_estudiante, e.nombres, e.apellido_paterno, e.apellido_materno, e.carnet_identidad,
        c.nivel, c.curso, c.paralelo
    FROM estudiantes e
    LEFT JOIN cursos c ON c.id_curso = e.id_curso
    ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres");
$estudiantesLista = $stmtLista->fetchAll(PDO::FETCH_ASSOC);

$estudiante = null;
$resumen = [
    'dias_lectivos' => 0,
    'vino_temprano' => 0,
    'vino_tarde' => 0,
    'no_vino_con_licencia' => 0,
    'no_vino_sin_licencia' => 0,
];
$detalleDias = [];

if ($idEstudiante > 0) {
    $stmtEst = $conn->prepare("SELECT e.id_estudiante, e.nombres, e.apellido_paterno, e.apellido_materno, e.carnet_identidad,
            c.nivel, c.curso, c.paralelo
        FROM estudiantes e
        LEFT JOIN cursos c ON c.id_curso = e.id_curso
        WHERE e.id_estudiante = ?
        LIMIT 1");
    $stmtEst->execute([$idEstudiante]);
    $estudiante = $stmtEst->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($estudiante) {
        $stmtLectivos = $conn->prepare("SELECT DISTINCT fecha
            FROM asistencia
            WHERE fecha BETWEEN ? AND ?
            ORDER BY fecha ASC");
        $stmtLectivos->execute([$fechaInicio, $fechaFin]);
        $diasLectivos = array_map(static function ($r) {
            return (string)$r['fecha'];
        }, $stmtLectivos->fetchAll(PDO::FETCH_ASSOC));

        if (empty($diasLectivos)) {
            $cursor = $fechaInicio;
            while ($cursor <= $fechaFin) {
                $diasLectivos[] = $cursor;
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }
        }

        $stmtAsis = $conn->prepare("SELECT fecha, hora_entrada, estado_puntualidad
            FROM asistencia
            WHERE id_estudiante = ?
              AND fecha BETWEEN ? AND ?
            ORDER BY fecha ASC");
        $stmtAsis->execute([$idEstudiante, $fechaInicio, $fechaFin]);
        $asistenciasRows = $stmtAsis->fetchAll(PDO::FETCH_ASSOC);
        $asistencias = [];
        foreach ($asistenciasRows as $r) {
            $asistencias[(string)$r['fecha']] = $r;
        }

        $stmtPerm = $conn->prepare("SELECT fecha, motivo, detalle
            FROM asistencia_permisos
            WHERE id_estudiante = ?
              AND estado = 'APROBADO'
              AND fecha BETWEEN ? AND ?
            ORDER BY fecha ASC");
        $stmtPerm->execute([$idEstudiante, $fechaInicio, $fechaFin]);
        $permisosRows = $stmtPerm->fetchAll(PDO::FETCH_ASSOC);
        $permisos = [];
        foreach ($permisosRows as $r) {
            $permisos[(string)$r['fecha']] = $r;
        }

        foreach ($diasLectivos as $fecha) {
            $item = [
                'fecha' => $fecha,
                'estado' => '',
                'hora_entrada' => '',
                'motivo' => '',
                'detalle' => '',
            ];

            if (isset($asistencias[$fecha])) {
                $punt = strtoupper((string)($asistencias[$fecha]['estado_puntualidad'] ?? ''));
                if ($punt === 'TARDE') {
                    $item['estado'] = 'VINO TARDE';
                    $resumen['vino_tarde']++;
                } else {
                    $item['estado'] = 'VINO TEMPRANO';
                    $resumen['vino_temprano']++;
                }
                $item['hora_entrada'] = (string)($asistencias[$fecha]['hora_entrada'] ?? '');
            } else {
                if (isset($permisos[$fecha])) {
                    $item['estado'] = 'NO VINO (CON LICENCIA)';
                    $item['motivo'] = (string)($permisos[$fecha]['motivo'] ?? '');
                    $item['detalle'] = (string)($permisos[$fecha]['detalle'] ?? '');
                    $resumen['no_vino_con_licencia']++;
                } else {
                    $item['estado'] = 'NO VINO (SIN LICENCIA)';
                    $resumen['no_vino_sin_licencia']++;
                }
            }

            $detalleDias[] = $item;
        }

        $resumen['dias_lectivos'] = count($diasLectivos);
    }
}

// Procesar registro manual de asistencia extra desde la tabla del reporte estudiantil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar_asistencia_estudiantil') {
    header('Content-Type: application/json; charset=utf-8');
    $idEstudianteReg = (int)($_POST['id_estudiante'] ?? 0);
    $fechaReg = trim((string)($_POST['fecha'] ?? ''));
    $turnoReg = strtoupper(trim((string)($_POST['turno'] ?? '')));
    $horaEntrada = trim((string)($_POST['hora_entrada'] ?? ''));

    if ($idEstudianteReg <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaReg) || !in_array($turnoReg, ['MANANA', 'TARDE'], true) || $horaEntrada === '') {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos o invalidos.']);
        exit();
    }

    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $horaEntrada)) {
        echo json_encode(['success' => false, 'message' => 'Hora de entrada invalida.']);
        exit();
    }

    if (strlen($horaEntrada) === 5) {
        $horaEntrada .= ':00';
    }

    $stmtEst = $conn->prepare("SELECT id_curso FROM estudiantes WHERE id_estudiante = ? LIMIT 1");
    $stmtEst->execute([$idEstudianteReg]);
    $idCursoEst = (int)$stmtEst->fetchColumn();

    if ($idCursoEst <= 0) {
        echo json_encode(['success' => false, 'message' => 'Estudiante no encontrado.']);
        exit();
    }

    $lectorInfoReg = $userRole === 1 ? null : asistencia_auth_get_lector($conn, $userId);
    $puede = $userRole === 1;
    if (!$puede && $lectorInfoReg) {
        if ($lectorInfoReg['alcance'] === 'GLOBAL') {
            $puede = true;
        } elseif ($lectorInfoReg['alcance'] === 'POR_CURSO') {
            $stmtLC = $conn->prepare("SELECT 1 FROM asistencia_lectores_cursos WHERE id_lector = ? AND id_curso = ? AND estado = 1 LIMIT 1");
            $stmtLC->execute([(int)$lectorInfoReg['id_lector'], $idCursoEst]);
            $puede = (bool)$stmtLC->fetchColumn();
        }
    }

    if (!$puede) {
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para registrar asistencia en este curso.']);
        exit();
    }

    $stmtCheck = $conn->prepare("SELECT 1 FROM asistencia WHERE id_estudiante = ? AND fecha = ? AND turno = ? LIMIT 1");
    $stmtCheck->execute([$idEstudianteReg, $fechaReg, $turnoReg]);
    if ($stmtCheck->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'El estudiante ya tiene asistencia registrada para el turno ' . $turnoReg . ' en la fecha ' . $fechaReg . '.']);
        exit();
    }

    $validacionTurno = asistencia_auth_turno_habilitado_para_fecha($conn, $idCursoEst, $turnoReg, $fechaReg);
    if (!$validacionTurno['habilitado']) {
        echo json_encode(['success' => false, 'message' => $validacionTurno['motivo']]);
        exit();
    }

    $estadoPuntualidad = null;
    $horaProgramada = null;
    $toleranciaMin = null;

    $stmtHor = $conn->prepare("SELECT hora_ingreso, tolerancia_min
        FROM asistencia_horarios_turno_global
        WHERE estado = 1 AND turno = ? AND ? BETWEEN fecha_inicio AND fecha_fin
        ORDER BY fecha_inicio DESC, id_horario_global DESC LIMIT 1");
    $stmtHor->execute([$turnoReg, $fechaReg]);
    $horario = $stmtHor->fetch(PDO::FETCH_ASSOC);

    if ($horario) {
        $horaProgramada = (string)$horario['hora_ingreso'];
        $toleranciaMin = max((int)$horario['tolerancia_min'], 0);
        $limite = date('H:i:s', strtotime($fechaReg . ' ' . $horaProgramada . ' +' . $toleranciaMin . ' minutes'));
        $estadoPuntualidad = ($horaEntrada <= $limite) ? 'TEMPRANO' : 'TARDE';
    }

    try {
        $stmtIns = $conn->prepare("INSERT INTO asistencia
            (id_estudiante, fecha, turno, hora_entrada, tipo_registro, estado_puntualidad, hora_ingreso_programada, tolerancia_min, registrado_por)
            VALUES (?, ?, ?, ?, 'MANUAL', ?, ?, ?, ?)");
        $stmtIns->execute([$idEstudianteReg, $fechaReg, $turnoReg, $horaEntrada, $estadoPuntualidad, $horaProgramada, $toleranciaMin, $userId]);

        echo json_encode([
            'success' => true,
            'message' => 'Asistencia registrada correctamente.',
            'id_estudiante' => $idEstudianteReg,
            'fecha' => $fechaReg,
            'turno' => $turnoReg,
            'hora_entrada' => $horaEntrada,
            'puntualidad' => $estadoPuntualidad,
            'es_tarde' => $estadoPuntualidad === 'TARDE',
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            echo json_encode(['success' => false, 'message' => 'El estudiante ya tiene asistencia registrada para esta fecha y turno.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al registrar la asistencia.']);
        }
    }
    exit();
}

$tardeHabilitadaPorFecha = [];
$diasTardeCurso = [];
$cursoEsDobleTurno = false;
$cursoEstudiante = null;

if ($estudiante) {
    $idCursoEstudiante = (int)($estudiante['id_curso'] ?? 0);
    if ($idCursoEstudiante > 0) {
        $stmtDobleCurso = $conn->prepare("SELECT doble_turno FROM asistencia_cursos_turnos WHERE id_curso = ? AND estado = 1 LIMIT 1");
        $stmtDobleCurso->execute([$idCursoEstudiante]);
        $cursoEsDobleTurno = ((int)$stmtDobleCurso->fetchColumn()) === 1;

        $stmtTblDias = $conn->prepare("SHOW TABLES LIKE 'asistencia_curso_turno_dias'");
        $stmtTblDias->execute();
        $tablaDiasExiste = (bool)$stmtTblDias->fetchColumn();

        if ($cursoEsDobleTurno && $tablaDiasExiste) {
            $stmtDiasCurso = $conn->prepare("SELECT dia_semana, fecha_inicio, fecha_fin
                FROM asistencia_curso_turno_dias
                WHERE id_curso = ? AND turno = 'TARDE' AND estado = 1");
            $stmtDiasCurso->execute([$idCursoEstudiante]);
            $diasTardeCurso = $stmtDiasCurso->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

foreach ($detalleDias as $fechaDia) {
    $fechaEval = (string)$fechaDia['fecha'];
    $habilitado = false;
    if ($cursoEsDobleTurno) {
        if (!empty($diasTardeCurso)) {
            $dw = (int)date('N', strtotime($fechaEval));
            foreach ($diasTardeCurso as $dt) {
                $fi = $dt['fecha_inicio'];
                $ff = $dt['fecha_fin'];
                if ((int)$dt['dia_semana'] === $dw
                    && ($fi === null || (string)$fi <= $fechaEval)
                    && ($ff === null || (string)$ff >= $fechaEval)) {
                    $habilitado = true;
                    break;
                }
            }
        } else {
            $habilitado = true;
        }
    }
    $tardeHabilitadaPorFecha[$fechaEval] = $habilitado;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Estudiantil de Asistencia</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <main class="w-100 px-md-4 position-relative py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Reporte Estudiantil de Asistencia</h1>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3" id="formReporteEstudiante">
                            <div class="col-md-5">
                                <label class="form-label">Buscar estudiante</label>
                                <input type="text" class="form-control mb-2" id="buscar_estudiante" value="<?= htmlspecialchars($q) ?>" placeholder="Nombre, apellido, carnet o ID">
                                <select class="form-select" id="id_estudiante" name="id_estudiante" required>
                                    <option value="">Seleccione estudiante</option>
                                    <?php foreach ($estudiantesLista as $est): ?>
                                        <?php
                                            $idOpt = (int)$est['id_estudiante'];
                                            $ciOpt = trim((string)($est['carnet_identidad'] ?? ''));
                                            $nombreOpt = trim(($est['apellido_paterno'] ?? '') . ' ' . ($est['apellido_materno'] ?? '') . ', ' . ($est['nombres'] ?? ''));
                                            $cursoOpt = trim(($est['nivel'] ?? '') . ' ' . ($est['curso'] ?? '') . ' "' . ($est['paralelo'] ?? '') . '"');
                                            $textoOpt = 'ID ' . $idOpt . ' | CI ' . ($ciOpt !== '' ? $ciOpt : 'S/N') . ' | ' . $nombreOpt . ' - ' . $cursoOpt;
                                            $searchOpt = mb_strtolower($textoOpt, 'UTF-8');
                                        ?>
                                        <option value="<?= $idOpt ?>" data-search="<?= htmlspecialchars($searchOpt) ?>" data-label="<?= htmlspecialchars($textoOpt) ?>" <?= $idEstudiante === $idOpt ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($textoOpt) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="q" id="q" value="<?= htmlspecialchars($q) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Desde</label>
                                <input type="date" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Hasta</label>
                                <input type="date" class="form-control" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Ver reporte</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($estudiante): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="mb-1"><?= htmlspecialchars(trim($estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno'] . ', ' . $estudiante['nombres'])) ?></h5>
                            <div class="text-muted">
                                ID <?= (int)$estudiante['id_estudiante'] ?> | CI <?= htmlspecialchars((string)($estudiante['carnet_identidad'] ?: 'S/N')) ?> |
                                <?= htmlspecialchars(($estudiante['nivel'] ?? '') . ' ' . ($estudiante['curso'] ?? '') . ' "' . ($estudiante['paralelo'] ?? '') . '"') ?>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">Resumen (<?= htmlspecialchars($fechaInicio) ?> a <?= htmlspecialchars($fechaFin) ?>)</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3"><div class="border rounded p-3 bg-light"><div class="small text-muted">Dias lectivos</div><div class="h4 mb-0"><?= (int)$resumen['dias_lectivos'] ?></div></div></div>
                                <div class="col-md-3"><div class="border rounded p-3 bg-light"><div class="small text-muted">Vino temprano</div><div class="h4 mb-0 text-success"><?= (int)$resumen['vino_temprano'] ?></div></div></div>
                                <div class="col-md-3"><div class="border rounded p-3 bg-light"><div class="small text-muted">Vino tarde</div><div class="h4 mb-0 text-warning"><?= (int)$resumen['vino_tarde'] ?></div></div></div>
                                <div class="col-md-3"><div class="border rounded p-3 bg-light"><div class="small text-muted">No vino con licencia</div><div class="h4 mb-0 text-primary"><?= (int)$resumen['no_vino_con_licencia'] ?></div></div></div>
                                <div class="col-md-3"><div class="border rounded p-3 bg-light"><div class="small text-muted">No vino sin licencia</div><div class="h4 mb-0 text-danger"><?= (int)$resumen['no_vino_sin_licencia'] ?></div></div></div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">Detalle diario</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                            <th>Hora entrada</th>
                                            <th>Motivo licencia</th>
                                            <th>Detalle licencia</th>
                                            <th>Registrar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($detalleDias)): ?>
                                            <tr><td colspan="7" class="text-center py-4">No hay datos para el rango seleccionado.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($detalleDias as $i => $d): ?>
                                                <?php
                                                    $estadoDia = strtoupper((string)$d['estado']);
                                                    $noHayAsistencia = ($estadoDia === 'NO VINO (CON LICENCIA)' || $estadoDia === 'NO VINO (SIN LICENCIA)');
                                                    $idEstActual = (int)($estudiante['id_estudiante'] ?? 0);
                                                ?>
                                                <tr>
                                                    <td><?= $i + 1 ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($d['fecha']) ?>
                                                        <?php if (!$tardeHabilitadaPorFecha[$d['fecha']]): ?>
                                                            <span class="badge bg-secondary ms-1" title="Este curso no tiene turno TARDE para esta fecha">Sin TARDE</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="celda-estado-est"><span class="celda-estado-texto"><?= htmlspecialchars($d['estado']) ?></span></td>
                                                    <td class="celda-hora-est"><?= htmlspecialchars($d['hora_entrada'] !== '' ? $d['hora_entrada'] : '-') ?></td>
                                                    <td><?= htmlspecialchars($d['motivo'] !== '' ? $d['motivo'] : '-') ?></td>
                                                    <td><?= htmlspecialchars($d['detalle'] !== '' ? $d['detalle'] : '-') ?></td>
                                                    <td class="celda-registro-est">
                                                        <?php
                                                            $tardePermitidaFecha = !empty($tardeHabilitadaPorFecha[$d['fecha']]);
                                                        ?>
                                                        <?php if ($noHayAsistencia && $idEstActual > 0): ?>
                                                            <form class="registro-extra-form-est d-flex align-items-center gap-1 flex-wrap" data-id="<?= $idEstActual ?>">
                                                                <select name="turno" class="form-select form-select-sm registro-extra-turno" style="width:95px">
                                                                    <option value="MANANA">MANANA</option>
                                                                    <option value="TARDE" <?= $tardePermitidaFecha ? '' : 'disabled' ?>><?= $tardePermitidaFecha ? 'TARDE' : 'TARDE (no)' ?></option>
                                                                </select>
                                                                <input type="time" name="hora_entrada" class="form-control form-control-sm registro-extra-hora" style="width:110px" value="<?= date('H:i') ?>" required>
                                                                <input type="hidden" name="fecha" value="<?= htmlspecialchars($d['fecha']) ?>">
                                                                <input type="hidden" name="id_estudiante" value="<?= $idEstActual ?>">
                                                                <button type="submit" class="btn btn-sm btn-success registro-extra-btn-est">
                                                                    <i class="ri-check-line"></i> OK
                                                                </button>
                                                            </form>
                                                            <div class="registro-extra-msg-est small mt-1"></div>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
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
    <script>
        (function() {
            const searchInput = document.getElementById('buscar_estudiante');
            const select = document.getElementById('id_estudiante');
            const qInput = document.getElementById('q');
            if (!searchInput || !select || !qInput) {
                return;
            }

            const options = Array.from(select.querySelectorAll('option')).filter(function(opt) {
                return opt.value !== '';
            });

            const selected = select.selectedOptions[0];
            if (selected && selected.value !== '') {
                searchInput.value = selected.dataset.label || selected.textContent.trim();
            }

            function filterOptions() {
                const term = searchInput.value.trim().toLowerCase();
                let firstVisible = null;

                options.forEach(function(opt) {
                    const haystack = (opt.dataset.search || '').toLowerCase();
                    const visible = term === '' || haystack.includes(term);
                    opt.hidden = !visible;
                    if (visible && !firstVisible) {
                        firstVisible = opt;
                    }
                });

                if (term !== '' && firstVisible) {
                    if (!select.value || (select.selectedOptions[0] && select.selectedOptions[0].hidden)) {
                        select.value = firstVisible.value;
                    }
                }

                qInput.value = searchInput.value;
            }

            searchInput.addEventListener('input', filterOptions);
            select.addEventListener('change', function() {
                const opt = select.selectedOptions[0];
                if (opt && opt.value !== '') {
                    searchInput.value = opt.dataset.label || opt.textContent.trim();
                    qInput.value = searchInput.value;
                }
            });

            filterOptions();
        })();

        (function() {
            function escaparHtmlEst(texto) {
                return String(texto == null ? '' : texto)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            var forms = document.querySelectorAll('.registro-extra-form-est');
            if (!forms || forms.length === 0) {
                return;
            }

            Array.prototype.forEach.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    var btn = form.querySelector('.registro-extra-btn-est');
                    var horaInput = form.querySelector('.registro-extra-hora');
                    var turnoSelect = form.querySelector('.registro-extra-turno');
                    var msgEl = form.parentElement.querySelector('.registro-extra-msg-est');

                    var horaValor = (horaInput && horaInput.value) ? horaInput.value : '';
                    var turnoValor = (turnoSelect && turnoSelect.value) ? turnoSelect.value : '';
                    if (!horaValor || !turnoValor) {
                        if (msgEl) {
                            msgEl.innerHTML = '<span class="text-danger">Complete turno y hora.</span>';
                        }
                        return;
                    }

                    if (btn) { btn.disabled = true; }
                    if (msgEl) { msgEl.innerHTML = '<span class="text-muted">Registrando...</span>'; }

                    var data = new FormData(form);
                    data.append('action', 'registrar_asistencia_estudiantil');

                    fetch('reporte_estudiantil_asistencia.php', {
                        method: 'POST',
                        body: data
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(resp) {
                        if (!resp || !resp.success) {
                            var errMsg = (resp && resp.message) ? resp.message : 'No se pudo registrar la asistencia.';
                            if (msgEl) {
                                msgEl.innerHTML = '<span class="text-danger">' + escaparHtmlEst(errMsg) + '</span>';
                            }
                            if (btn) { btn.disabled = false; }
                            return;
                        }

                        var tr = form.closest('tr');
                        if (tr) {
                            var celdaHora = tr.querySelector('.celda-hora-est');
                            if (celdaHora) {
                                celdaHora.textContent = resp.hora_entrada || '-';
                            }
                            var celdaEstadoTxt = tr.querySelector('.celda-estado-texto');
                            if (celdaEstadoTxt) {
                                var estadoNuevo = (resp.es_tarde ? 'VINO TARDE' : 'VINO TEMPRANO') + ' (' + resp.turno + ')';
                                celdaEstadoTxt.textContent = estadoNuevo;
                            }
                            var celdaEstado = tr.querySelector('.celda-estado-est');
                            if (celdaEstado) {
                                celdaEstado.className = 'celda-estado-est ' + (resp.es_tarde ? 'text-warning fw-semibold' : 'text-success fw-semibold');
                            }
                            var celdaRegistro = tr.querySelector('.celda-registro-est');
                            if (celdaRegistro) {
                                celdaRegistro.innerHTML = '<span class="text-success"><i class="ri-check-double-line"></i> Registrado</span>';
                            }
                        }
                    })
                    .catch(function() {
                        if (msgEl) {
                            msgEl.innerHTML = '<span class="text-danger">Error de conexion al registrar la asistencia.</span>';
                        }
                        if (btn) { btn.disabled = false; }
                    });
                });
            });
        })();
    </script>
</body>
</html>
