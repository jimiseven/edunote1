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
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($detalleDias)): ?>
                                            <tr><td colspan="6" class="text-center py-4">No hay datos para el rango seleccionado.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($detalleDias as $i => $d): ?>
                                                <tr>
                                                    <td><?= $i + 1 ?></td>
                                                    <td><?= htmlspecialchars($d['fecha']) ?></td>
                                                    <td><?= htmlspecialchars($d['estado']) ?></td>
                                                    <td><?= htmlspecialchars($d['hora_entrada'] !== '' ? $d['hora_entrada'] : '-') ?></td>
                                                    <td><?= htmlspecialchars($d['motivo'] !== '' ? $d['motivo'] : '-') ?></td>
                                                    <td><?= htmlspecialchars($d['detalle'] !== '' ? $d['detalle'] : '-') ?></td>
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
    </script>
</body>
</html>
