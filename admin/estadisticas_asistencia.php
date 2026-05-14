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
    echo '<h3>Acceso denegado</h3><p>No tienes permisos para ver estadisticas de asistencia.</p>';
    exit();
}
$modo = ($_GET['modo'] ?? 'dia') === 'rango' ? 'rango' : 'dia';
$tipo = ($_GET['tipo'] ?? 'llegada') === 'puntualidad' ? 'puntualidad' : 'llegada';
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$fechaInicio = $_GET['fecha_inicio'] ?? $fecha;
$fechaFin = $_GET['fecha_fin'] ?? $fecha;

if ($modo === 'rango') {
    if ($fechaInicio > $fechaFin) {
        $tmp = $fechaInicio;
        $fechaInicio = $fechaFin;
        $fechaFin = $tmp;
    }
}

$rangoLabel = $modo === 'rango'
    ? (htmlspecialchars($fechaInicio) . ' a ' . htmlspecialchars($fechaFin))
    : htmlspecialchars($fecha);

$diasRango = 1;
if ($modo === 'rango') {
    try {
        $d1 = new DateTime($fechaInicio);
        $d2 = new DateTime($fechaFin);
        $diff = $d1->diff($d2);
        $diasRango = max((int)$diff->days + 1, 1);
    } catch (Throwable $e) {
        $diasRango = 1;
    }
}

$stmtTotal = $modo === 'rango'
    ? $conn->prepare("SELECT COUNT(*) FROM asistencia WHERE fecha BETWEEN ? AND ?")
    : $conn->prepare("SELECT COUNT(*) FROM asistencia WHERE fecha = ?");

if ($modo === 'rango') {
    $stmtTotal->execute([$fechaInicio, $fechaFin]);
} else {
    $stmtTotal->execute([$fecha]);
}
$totalAsistencias = (int)$stmtTotal->fetchColumn();

$stmtTotalEst = $conn->query("SELECT COUNT(*) FROM estudiantes");
$totalEstudiantesColegio = (int)$stmtTotalEst->fetchColumn();

$stmtLlegaronUnicos = $modo === 'rango'
    ? $conn->prepare("SELECT COUNT(DISTINCT id_estudiante) FROM asistencia WHERE fecha BETWEEN ? AND ?")
    : $conn->prepare("SELECT COUNT(DISTINCT id_estudiante) FROM asistencia WHERE fecha = ?");

if ($modo === 'rango') {
    $stmtLlegaronUnicos->execute([$fechaInicio, $fechaFin]);
} else {
    $stmtLlegaronUnicos->execute([$fecha]);
}
$totalLlegaronColegioUnico = (int)$stmtLlegaronUnicos->fetchColumn();

$totalLlegaronColegio = $totalLlegaronColegioUnico;
if ($modo === 'rango') {
    $stmtDaily = $conn->prepare("SELECT fecha, COUNT(DISTINCT id_estudiante) AS llegaron
        FROM asistencia
        WHERE fecha BETWEEN ? AND ?
        GROUP BY fecha");
    $stmtDaily->execute([$fechaInicio, $fechaFin]);
    $rowsDaily = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);
    $sumDaily = 0;
    foreach ($rowsDaily as $rd) {
        $sumDaily += (int)$rd['llegaron'];
    }
    $totalLlegaronColegio = (int)round($sumDaily / max($diasRango, 1));
}

$totalFaltanColegio = max($totalEstudiantesColegio - $totalLlegaronColegio, 0);

$totalTempranoColegio = 0;
$totalTardeColegio = 0;
if ($tipo === 'puntualidad') {
    if ($modo === 'rango') {
        $stmtDailyP = $conn->prepare("SELECT fecha,
                COUNT(DISTINCT CASE WHEN estado_puntualidad = 'TEMPRANO' THEN id_estudiante END) AS temprano,
                COUNT(DISTINCT CASE WHEN estado_puntualidad = 'TARDE' THEN id_estudiante END) AS tarde
            FROM asistencia
            WHERE fecha BETWEEN ? AND ?
            GROUP BY fecha");
        $stmtDailyP->execute([$fechaInicio, $fechaFin]);
        $rowsDailyP = $stmtDailyP->fetchAll(PDO::FETCH_ASSOC);

        $sumTemprano = 0;
        $sumTarde = 0;
        foreach ($rowsDailyP as $rp) {
            $sumTemprano += (int)$rp['temprano'];
            $sumTarde += (int)$rp['tarde'];
        }
        $totalTempranoColegio = (int)round($sumTemprano / max($diasRango, 1));
        $totalTardeColegio = (int)round($sumTarde / max($diasRango, 1));
    } else {
        $stmtP = $conn->prepare("SELECT
                COUNT(DISTINCT CASE WHEN estado_puntualidad = 'TEMPRANO' THEN id_estudiante END) AS temprano,
                COUNT(DISTINCT CASE WHEN estado_puntualidad = 'TARDE' THEN id_estudiante END) AS tarde
            FROM asistencia
            WHERE fecha = ?");
        $stmtP->execute([$fecha]);
        $rowP = $stmtP->fetch(PDO::FETCH_ASSOC) ?: [];
        $totalTempranoColegio = (int)($rowP['temprano'] ?? 0);
        $totalTardeColegio = (int)($rowP['tarde'] ?? 0);
    }
}

$stmtNivelesTotal = $conn->query("SELECT c.nivel, COUNT(e.id_estudiante) AS total_estudiantes
    FROM cursos c
    LEFT JOIN estudiantes e ON e.id_curso = c.id_curso
    GROUP BY c.nivel");
$rowsNivelesTotal = $stmtNivelesTotal->fetchAll(PDO::FETCH_ASSOC);

$nivelesBase = ['Inicial', 'Primaria', 'Secundaria'];
$nivelesStats = [];
foreach ($nivelesBase as $nivelBase) {
    $nivelesStats[$nivelBase] = ['llegaron' => 0, 'faltan' => 0, 'total' => 0];
}

foreach ($rowsNivelesTotal as $rowNivel) {
    $nivel = (string)($rowNivel['nivel'] ?? '');
    if (!isset($nivelesStats[$nivel])) {
        continue;
    }
    $nivelesStats[$nivel]['total'] = (int)$rowNivel['total_estudiantes'];
}

$stmtLlegaronNivelUnico = $modo === 'rango'
    ? $conn->prepare("SELECT c.nivel, COUNT(DISTINCT a.id_estudiante) AS llegaron
        FROM asistencia a
        INNER JOIN estudiantes e ON e.id_estudiante = a.id_estudiante
        INNER JOIN cursos c ON c.id_curso = e.id_curso
        WHERE a.fecha BETWEEN ? AND ?
        GROUP BY c.nivel")
    : $conn->prepare("SELECT c.nivel, COUNT(DISTINCT a.id_estudiante) AS llegaron
        FROM asistencia a
        INNER JOIN estudiantes e ON e.id_estudiante = a.id_estudiante
        INNER JOIN cursos c ON c.id_curso = e.id_curso
        WHERE a.fecha = ?
        GROUP BY c.nivel");

if ($modo === 'rango') {
    $stmtLlegaronNivelUnico->execute([$fechaInicio, $fechaFin]);
} else {
    $stmtLlegaronNivelUnico->execute([$fecha]);
}
$rowsLlegaronNivelUnico = $stmtLlegaronNivelUnico->fetchAll(PDO::FETCH_ASSOC);

if ($modo === 'rango') {
    $stmtDailyNivel = $conn->prepare("SELECT a.fecha, c.nivel, COUNT(DISTINCT a.id_estudiante) AS llegaron
        FROM asistencia a
        INNER JOIN estudiantes e ON e.id_estudiante = a.id_estudiante
        INNER JOIN cursos c ON c.id_curso = e.id_curso
        WHERE a.fecha BETWEEN ? AND ?
        GROUP BY a.fecha, c.nivel");
    $stmtDailyNivel->execute([$fechaInicio, $fechaFin]);
    $rowsDailyNivel = $stmtDailyNivel->fetchAll(PDO::FETCH_ASSOC);

    $sumByNivel = [];
    foreach ($nivelesBase as $nivelBase) {
        $sumByNivel[$nivelBase] = 0;
    }
    foreach ($rowsDailyNivel as $rdn) {
        $nivel = (string)$rdn['nivel'];
        if (!isset($sumByNivel[$nivel])) {
            continue;
        }
        $sumByNivel[$nivel] += (int)$rdn['llegaron'];
    }
    foreach ($nivelesBase as $nivelBase) {
        $nivelesStats[$nivelBase]['llegaron'] = (int)round($sumByNivel[$nivelBase] / max($diasRango, 1));
        $nivelesStats[$nivelBase]['faltan'] = max($nivelesStats[$nivelBase]['total'] - $nivelesStats[$nivelBase]['llegaron'], 0);
    }
} else {
    foreach ($rowsLlegaronNivelUnico as $rowNivel) {
        $nivel = (string)($rowNivel['nivel'] ?? '');
        if (!isset($nivelesStats[$nivel])) {
            continue;
        }
        $nivelesStats[$nivel]['llegaron'] = (int)$rowNivel['llegaron'];
        $nivelesStats[$nivel]['faltan'] = max($nivelesStats[$nivel]['total'] - $nivelesStats[$nivel]['llegaron'], 0);
    }
}

if ($tipo === 'puntualidad') {
    foreach ($nivelesBase as $nivelBase) {
        $nivelesStats[$nivelBase]['temprano'] = 0;
        $nivelesStats[$nivelBase]['tarde'] = 0;
    }

    if ($modo === 'rango') {
        $stmtDailyNivelP = $conn->prepare("SELECT a.fecha, c.nivel,
                COUNT(DISTINCT CASE WHEN a.estado_puntualidad = 'TEMPRANO' THEN a.id_estudiante END) AS temprano,
                COUNT(DISTINCT CASE WHEN a.estado_puntualidad = 'TARDE' THEN a.id_estudiante END) AS tarde
            FROM asistencia a
            INNER JOIN estudiantes e ON e.id_estudiante = a.id_estudiante
            INNER JOIN cursos c ON c.id_curso = e.id_curso
            WHERE a.fecha BETWEEN ? AND ?
            GROUP BY a.fecha, c.nivel");
        $stmtDailyNivelP->execute([$fechaInicio, $fechaFin]);
        $rowsDailyNivelP = $stmtDailyNivelP->fetchAll(PDO::FETCH_ASSOC);

        $sumTempranoNivel = [];
        $sumTardeNivel = [];
        foreach ($nivelesBase as $nivelBase) {
            $sumTempranoNivel[$nivelBase] = 0;
            $sumTardeNivel[$nivelBase] = 0;
        }
        foreach ($rowsDailyNivelP as $rpn) {
            $nivel = (string)$rpn['nivel'];
            if (!isset($sumTempranoNivel[$nivel])) {
                continue;
            }
            $sumTempranoNivel[$nivel] += (int)$rpn['temprano'];
            $sumTardeNivel[$nivel] += (int)$rpn['tarde'];
        }
        foreach ($nivelesBase as $nivelBase) {
            $nivelesStats[$nivelBase]['temprano'] = (int)round($sumTempranoNivel[$nivelBase] / max($diasRango, 1));
            $nivelesStats[$nivelBase]['tarde'] = (int)round($sumTardeNivel[$nivelBase] / max($diasRango, 1));
        }
    } else {
        $stmtNivelP = $conn->prepare("SELECT c.nivel,
                COUNT(DISTINCT CASE WHEN a.estado_puntualidad = 'TEMPRANO' THEN a.id_estudiante END) AS temprano,
                COUNT(DISTINCT CASE WHEN a.estado_puntualidad = 'TARDE' THEN a.id_estudiante END) AS tarde
            FROM asistencia a
            INNER JOIN estudiantes e ON e.id_estudiante = a.id_estudiante
            INNER JOIN cursos c ON c.id_curso = e.id_curso
            WHERE a.fecha = ?
            GROUP BY c.nivel");
        $stmtNivelP->execute([$fecha]);
        $rowsNivelP = $stmtNivelP->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rowsNivelP as $rnp) {
            $nivel = (string)($rnp['nivel'] ?? '');
            if (!isset($nivelesStats[$nivel])) {
                continue;
            }
            $nivelesStats[$nivel]['temprano'] = (int)($rnp['temprano'] ?? 0);
            $nivelesStats[$nivel]['tarde'] = (int)($rnp['tarde'] ?? 0);
        }
    }
}

$porcentajeLlegadaColegio = $totalEstudiantesColegio > 0
    ? round(($totalLlegaronColegio * 100) / $totalEstudiantesColegio, 1)
    : 0;

$stmtCursos = $modo === 'rango'
    ? $conn->prepare("SELECT COUNT(DISTINCT e.id_curso)
        FROM asistencia a
        INNER JOIN estudiantes e ON e.id_estudiante = a.id_estudiante
        WHERE a.fecha BETWEEN ? AND ?")
    : $conn->prepare("SELECT COUNT(DISTINCT e.id_curso)
        FROM asistencia a
        INNER JOIN estudiantes e ON e.id_estudiante = a.id_estudiante
        WHERE a.fecha = ?");

if ($modo === 'rango') {
    $stmtCursos->execute([$fechaInicio, $fechaFin]);
} else {
    $stmtCursos->execute([$fecha]);
}
$totalCursosConAsistencia = (int)$stmtCursos->fetchColumn();

$stmtDetalle = $modo === 'rango'
    ? $conn->prepare("SELECT
            c.id_curso,
            c.nivel,
            c.curso,
            c.paralelo,
            COUNT(DISTINCT e.id_estudiante) AS total_estudiantes,
            COUNT(DISTINCT a.id_estudiante) AS llegaron
        FROM cursos c
        LEFT JOIN estudiantes e ON e.id_curso = c.id_curso
        LEFT JOIN asistencia a ON a.id_estudiante = e.id_estudiante AND a.fecha BETWEEN ? AND ?
        GROUP BY c.id_curso, c.nivel, c.curso, c.paralelo
        ORDER BY FIELD(c.nivel, 'Inicial', 'Primaria', 'Secundaria'), c.curso, c.paralelo")
    : $conn->prepare("SELECT
            c.id_curso,
            c.nivel,
            c.curso,
            c.paralelo,
            COUNT(e.id_estudiante) AS total_estudiantes,
            COALESCE(SUM(CASE WHEN a.id_asistencia IS NOT NULL THEN 1 ELSE 0 END), 0) AS llegaron
        FROM cursos c
        LEFT JOIN estudiantes e ON e.id_curso = c.id_curso
        LEFT JOIN asistencia a ON a.id_estudiante = e.id_estudiante AND a.fecha = ?
        GROUP BY c.id_curso, c.nivel, c.curso, c.paralelo
        ORDER BY FIELD(c.nivel, 'Inicial', 'Primaria', 'Secundaria'), c.curso, c.paralelo");

if ($modo === 'rango') {
    $stmtDetalle->execute([$fechaInicio, $fechaFin]);
} else {
    $stmtDetalle->execute([$fecha]);
}
$detalleCursos = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Asistencia</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <main class="w-100 px-md-4 position-relative py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Estadísticas de Asistencia</h1>
                    <form method="GET" class="d-flex gap-2 align-items-center" id="statsForm">
                        <div class="btn-group" role="group" aria-label="Tipo de estadística">
                            <input type="radio" class="btn-check" name="tipo" id="tipoLlegada" value="llegada" autocomplete="off" <?= $tipo === 'llegada' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary" for="tipoLlegada">Llegada</label>

                            <input type="radio" class="btn-check" name="tipo" id="tipoPuntualidad" value="puntualidad" autocomplete="off" <?= $tipo === 'puntualidad' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary" for="tipoPuntualidad">Puntualidad</label>
                        </div>

                        <select class="form-select" name="modo" id="modoSelect" style="max-width: 180px;">
                            <option value="dia" <?= $modo === 'dia' ? 'selected' : '' ?>>Una fecha</option>
                            <option value="rango" <?= $modo === 'rango' ? 'selected' : '' ?>>Rango</option>
                        </select>

                        <input type="date" class="form-control" name="fecha" id="inputFecha" value="<?= htmlspecialchars($fecha) ?>" <?= $modo === 'dia' ? 'required' : 'disabled hidden' ?> <?= $modo === 'dia' ? '' : 'hidden' ?>>
                        <input type="date" class="form-control" name="fecha_inicio" id="inputFechaInicio" value="<?= htmlspecialchars($fechaInicio) ?>" <?= $modo === 'rango' ? 'required' : 'disabled hidden' ?> <?= $modo === 'rango' ? '' : 'hidden' ?>>
                        <input type="date" class="form-control" name="fecha_fin" id="inputFechaFin" value="<?= htmlspecialchars($fechaFin) ?>" <?= $modo === 'rango' ? 'required' : 'disabled hidden' ?> <?= $modo === 'rango' ? '' : 'hidden' ?>>

                        <button class="btn btn-primary" type="submit">Actualizar</button>
                    </form>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <p class="text-muted mb-1">Asistencias registradas</p>
                                <h2 class="mb-0"><?= $totalAsistencias ?></h2>
                                <small class="text-muted"><?= $modo === 'rango' ? ('Rango: ' . $rangoLabel . ' | Días: ' . $diasRango) : ('Fecha: ' . $rangoLabel) ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <p class="text-muted mb-1">Cursos con asistencia</p>
                                <h2 class="mb-0"><?= $totalCursosConAsistencia ?></h2>
                                <small class="text-muted">Con al menos 1 registro</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <p class="text-muted mb-1">Llegada general del colegio</p>
                                <h2 class="mb-0"><?= $porcentajeLlegadaColegio ?>%</h2>
                                <small class="text-muted"><?= $modo === 'rango' ? 'Promedio diario (aprox.)' : 'Del día' ?> | Llegaron: <?= $totalLlegaronColegio ?> / <?= $totalEstudiantesColegio ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header">
                                <?= $tipo === 'puntualidad' ? 'Torta general: temprano vs tarde' : 'Torta general: llegaron vs faltan' ?>
                            </div>
                            <div class="card-body">
                                <div style="max-width: 420px; margin: 0 auto;">
                                    <canvas id="chartColegio"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header">Tortas por nivel</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <?php foreach ($nivelesBase as $nivelChart): ?>
                                        <div class="col-12 col-md-4">
                                            <div class="border rounded p-2 h-100">
                                                <p class="mb-2 text-center fw-semibold"><?= htmlspecialchars($nivelChart) ?></p>
                                                <canvas id="chartNivel<?= htmlspecialchars($nivelChart) ?>"></canvas>
                                                <small class="text-muted d-block text-center mt-2">
                                                    <?= $nivelesStats[$nivelChart]['llegaron'] ?> / <?= $nivelesStats[$nivelChart]['total'] ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        Resumen por curso (<?= $modo === 'rango' ? 'rango seleccionado' : 'fecha seleccionada' ?>)
                    </div>
                    <div class="card-body">
                        <?php if (empty($detalleCursos)): ?>
                            <div class="text-center py-4">No hay datos para la fecha seleccionada.</div>
                        <?php else: ?>
                            <?php
                                $cursosPorNivel = [
                                    'Inicial' => [],
                                    'Primaria' => [],
                                    'Secundaria' => []
                                ];
                                foreach ($detalleCursos as $rowCurso) {
                                    $nivelCurso = (string)($rowCurso['nivel'] ?? '');
                                    if (isset($cursosPorNivel[$nivelCurso])) {
                                        $cursosPorNivel[$nivelCurso][] = $rowCurso;
                                    }
                                }
                            ?>
                            <div class="row g-3">
                                <?php foreach (['Inicial', 'Primaria', 'Secundaria'] as $nivelCol): ?>
                                    <div class="col-12 col-lg-4">
                                        <h6 class="fw-bold mb-2"><?= htmlspecialchars($nivelCol) ?></h6>
                                        <div class="table-responsive border rounded">
                                            <table class="table table-striped mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Curso</th>
                                                        <th>Lleg.</th>
                                                        <th>Falt.</th>
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($cursosPorNivel[$nivelCol])): ?>
                                                        <tr>
                                                            <td colspan="5" class="text-center text-muted py-3">Sin cursos en este nivel</td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($cursosPorNivel[$nivelCol] as $rowIndex => $row): ?>
                                                            <?php
                                                                $totalEstudiantes = (int)$row['total_estudiantes'];
                                                                $llegaron = (int)$row['llegaron'];
                                                                $faltan = max($totalEstudiantes - $llegaron, 0);
                                                            ?>
                                                            <tr>
                                                                <td><?= $rowIndex + 1 ?></td>
                                                                <td><?= htmlspecialchars($row['curso'] . ' "' . $row['paralelo'] . '"') ?></td>
                                                                <td><?= $llegaron ?></td>
                                                                <td><?= $faltan ?></td>
                                                                <td><?= $totalEstudiantes ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.0/dist/feather.min.js"></script>
    <script>
        feather.replace();

        (function() {
            const modoSelect = document.getElementById('modoSelect');
            const inputFecha = document.getElementById('inputFecha');
            const inputFechaInicio = document.getElementById('inputFechaInicio');
            const inputFechaFin = document.getElementById('inputFechaFin');

            if (!modoSelect || !inputFecha || !inputFechaInicio || !inputFechaFin) {
                return;
            }

            const applyModo = () => {
                const modo = modoSelect.value === 'rango' ? 'rango' : 'dia';

                inputFecha.hidden = modo !== 'dia';
                inputFecha.disabled = modo !== 'dia';
                inputFecha.required = modo === 'dia';

                inputFechaInicio.hidden = modo !== 'rango';
                inputFechaFin.hidden = modo !== 'rango';
                inputFechaInicio.disabled = modo !== 'rango';
                inputFechaFin.disabled = modo !== 'rango';
                inputFechaInicio.required = modo === 'rango';
                inputFechaFin.required = modo === 'rango';
            };

            modoSelect.addEventListener('change', applyModo);
            applyModo();
        })();

        const chartColegioCtx = document.getElementById('chartColegio');
        if (chartColegioCtx) {
            new Chart(chartColegioCtx, {
                type: 'pie',
                data: {
                    labels: <?= $tipo === 'puntualidad'
                        ? "['Temprano', 'Tarde']"
                        : "['Llegaron', 'Faltan']" ?>,
                    datasets: [{
                        data: <?= $tipo === 'puntualidad'
                            ? '[' . $totalTempranoColegio . ', ' . $totalTardeColegio . ']'
                            : '[' . $totalLlegaronColegio . ', ' . $totalFaltanColegio . ']' ?>,
                        backgroundColor: <?= $tipo === 'puntualidad'
                            ? "['#0d6efd', '#dc3545']"
                            : "['#1f9d55', '#dc3545']" ?>
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        const niveles = <?= json_encode($nivelesBase, JSON_UNESCAPED_UNICODE) ?>;
        const nivelesStats = <?= json_encode($nivelesStats, JSON_UNESCAPED_UNICODE) ?>;

        niveles.forEach(function(nivel) {
            const id = 'chartNivel' + nivel;
            const el = document.getElementById(id);
            if (!el || !nivelesStats[nivel]) {
                return;
            }

            new Chart(el, {
                type: 'pie',
                data: {
                    labels: <?= $tipo === 'puntualidad'
                        ? "['Temprano', 'Tarde']"
                        : "['Llegaron', 'Faltan']" ?>,
                    datasets: [{
                        data: <?= $tipo === 'puntualidad'
                            ? '[nivelesStats[nivel].temprano || 0, nivelesStats[nivel].tarde || 0]'
                            : '[nivelesStats[nivel].llegaron || 0, nivelesStats[nivel].faltan || 0]' ?>,
                        backgroundColor: <?= $tipo === 'puntualidad'
                            ? "['#0d6efd', '#dc3545']"
                            : "['#0d6efd', '#ffc107']" ?>
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        });
    </script>
</body>
</html>
