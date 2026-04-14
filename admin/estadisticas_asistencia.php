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
                </div>

                <div class="card">
                    <div class="card-header">
                        Resumen por curso (fecha seleccionada)
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Curso</th>
                                        <th>Llegaron</th>
                                        <th>Faltan</th>
                                        <th>Total estudiantes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($detalleCursos)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">No hay datos para la fecha seleccionada.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($detalleCursos as $i => $row): ?>
                                            <?php
                                                $totalEstudiantes = (int)$row['total_estudiantes'];
                                                $llegaron = (int)$row['llegaron'];
                                                $faltan = max($totalEstudiantes - $llegaron, 0);
                                            ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars($row['nivel'] . ' ' . $row['curso'] . ' "' . $row['paralelo'] . '"') ?></td>
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
                </div>
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
