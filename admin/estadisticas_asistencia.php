<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();
$fecha = $_GET['fecha'] ?? date('Y-m-d');

$stmtTotal = $conn->prepare("SELECT COUNT(*) FROM asistencia WHERE fecha = ?");
$stmtTotal->execute([$fecha]);
$totalAsistencias = (int)$stmtTotal->fetchColumn();

$stmtTotalEst = $conn->query("SELECT COUNT(*) FROM estudiantes");
$totalEstudiantesColegio = (int)$stmtTotalEst->fetchColumn();

$stmtLlegaronUnicos = $conn->prepare("SELECT COUNT(DISTINCT id_estudiante) FROM asistencia WHERE fecha = ?");
$stmtLlegaronUnicos->execute([$fecha]);
$totalLlegaronColegio = (int)$stmtLlegaronUnicos->fetchColumn();
$totalFaltanColegio = max($totalEstudiantesColegio - $totalLlegaronColegio, 0);

$stmtNiveles = $conn->prepare("SELECT
        c.nivel,
        COUNT(e.id_estudiante) AS total_estudiantes,
        COUNT(DISTINCT CASE WHEN a.id_asistencia IS NOT NULL THEN e.id_estudiante END) AS llegaron
    FROM cursos c
    LEFT JOIN estudiantes e ON e.id_curso = c.id_curso
    LEFT JOIN asistencia a ON a.id_estudiante = e.id_estudiante AND a.fecha = ?
    GROUP BY c.nivel");
$stmtNiveles->execute([$fecha]);
$rowsNiveles = $stmtNiveles->fetchAll(PDO::FETCH_ASSOC);

$nivelesBase = ['Inicial', 'Primaria', 'Secundaria'];
$nivelesStats = [];
foreach ($nivelesBase as $nivelBase) {
    $nivelesStats[$nivelBase] = ['llegaron' => 0, 'faltan' => 0, 'total' => 0];
}

foreach ($rowsNiveles as $rowNivel) {
    $nivel = (string)($rowNivel['nivel'] ?? '');
    if (!isset($nivelesStats[$nivel])) {
        continue;
    }
    $totalNivel = (int)$rowNivel['total_estudiantes'];
    $llegaronNivel = (int)$rowNivel['llegaron'];
    $nivelesStats[$nivel]['llegaron'] = $llegaronNivel;
    $nivelesStats[$nivel]['faltan'] = max($totalNivel - $llegaronNivel, 0);
    $nivelesStats[$nivel]['total'] = $totalNivel;
}

$porcentajeLlegadaColegio = $totalEstudiantesColegio > 0
    ? round(($totalLlegaronColegio * 100) / $totalEstudiantesColegio, 1)
    : 0;

$stmtCursos = $conn->prepare("SELECT COUNT(DISTINCT e.id_curso)
    FROM asistencia a
    INNER JOIN estudiantes e ON e.id_estudiante = a.id_estudiante
    WHERE a.fecha = ?");
$stmtCursos->execute([$fecha]);
$totalCursosConAsistencia = (int)$stmtCursos->fetchColumn();

$stmtDetalle = $conn->prepare("SELECT
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
$stmtDetalle->execute([$fecha]);
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

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 position-relative py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Estadísticas de Asistencia</h1>
                    <form method="GET" class="d-flex gap-2">
                        <input type="date" class="form-control" name="fecha" value="<?= htmlspecialchars($fecha) ?>">
                        <button class="btn btn-primary" type="submit">Actualizar</button>
                    </form>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <p class="text-muted mb-1">Asistencias registradas</p>
                                <h2 class="mb-0"><?= $totalAsistencias ?></h2>
                                <small class="text-muted">Fecha: <?= htmlspecialchars($fecha) ?></small>
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
                                <small class="text-muted">Llegaron: <?= $totalLlegaronColegio ?> / <?= $totalEstudiantesColegio ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header">Torta general: llegaron vs faltan</div>
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
                        Resumen por curso (fecha seleccionada)
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

        const chartColegioCtx = document.getElementById('chartColegio');
        if (chartColegioCtx) {
            new Chart(chartColegioCtx, {
                type: 'pie',
                data: {
                    labels: ['Llegaron', 'Faltan'],
                    datasets: [{
                        data: [<?= $totalLlegaronColegio ?>, <?= $totalFaltanColegio ?>],
                        backgroundColor: ['#1f9d55', '#dc3545']
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
                    labels: ['Llegaron', 'Faltan'],
                    datasets: [{
                        data: [nivelesStats[nivel].llegaron, nivelesStats[nivel].faltan],
                        backgroundColor: ['#0d6efd', '#ffc107']
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
