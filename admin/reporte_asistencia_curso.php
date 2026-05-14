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

if ($nivel !== '') {
    $stmtCursos = $conn->prepare("SELECT id_curso, nivel, curso, paralelo FROM cursos WHERE nivel = ? ORDER BY curso, paralelo");
    $stmtCursos->execute([$nivel]);
} else {
    $stmtCursos = $conn->query("SELECT id_curso, nivel, curso, paralelo FROM cursos ORDER BY nivel, curso, paralelo");
}
$cursos = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);

$idCurso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
$fecha = $_GET['fecha'] ?? date('Y-m-d');

$registros = [];
if ($idCurso > 0) {
    $stmt = $conn->prepare("SELECT a.fecha, a.hora_entrada, e.apellido_paterno, e.apellido_materno, e.nombres
        FROM asistencia a
        INNER JOIN estudiantes e ON e.id_estudiante = a.id_estudiante
        WHERE e.id_curso = ? AND a.fecha = ?
        ORDER BY a.hora_entrada ASC");
    $stmt->execute([$idCurso, $fecha]);
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
                        <form class="row g-3" method="GET" action="">
                            <div class="col-md-4">
                                <label class="form-label">Nivel</label>
                                <select class="form-select" id="nivel" name="nivel" onchange="changeLevel(this)">
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
                                <select class="form-select" name="id_curso" id="id_curso" required <?= $nivel === '' ? 'disabled' : '' ?>>
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
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Ver reporte</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($idCurso > 0): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Registros encontrados: <?= count($registros) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Estudiante</th>
                                            <th>Fecha</th>
                                            <th>Hora de entrada</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($registros)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4">No hay registros para los filtros seleccionados.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($registros as $index => $row): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($row['apellido_paterno'] . ' ' . $row['apellido_materno'] . ', ' . $row['nombres']) ?></td>
                                                    <td><?= htmlspecialchars($row['fecha']) ?></td>
                                                    <td><?= htmlspecialchars($row['hora_entrada']) ?></td>
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

        function changeLevel(select) {
            const form = select.form;
            const course = document.getElementById('id_curso');
            if (course) {
                course.value = '';
            }
            form.submit();
        }
    </script>
</body>
</html>
