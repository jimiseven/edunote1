<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || (int)($_SESSION['user_role'] ?? 0) !== 1) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'crear_horario') {
            $fechaInicio = trim((string)($_POST['fecha_inicio'] ?? ''));
            $fechaFin = trim((string)($_POST['fecha_fin'] ?? ''));
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

            $stmt = $conn->prepare("INSERT INTO asistencia_horarios_ingreso
                (fecha_inicio, fecha_fin, hora_ingreso, tolerancia_min, estado, creado_por)
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fechaInicio, $fechaFin, $horaIngreso, $toleranciaMin, $estado, $creadoPor]);

            $_SESSION['ajustes_asistencia_flash'] = [
                'type' => 'success',
                'message' => 'Horario de ingreso guardado correctamente.'
            ];
        }

        if ($action === 'toggle_estado') {
            $idHorario = (int)($_POST['id_horario'] ?? 0);
            $estado = (int)($_POST['estado'] ?? 0) === 1 ? 1 : 0;

            if ($idHorario <= 0) {
                throw new RuntimeException('Horario inválido.');
            }

            $stmt = $conn->prepare("UPDATE asistencia_horarios_ingreso SET estado = ? WHERE id_horario = ?");
            $stmt->execute([$estado, $idHorario]);

            $_SESSION['ajustes_asistencia_flash'] = [
                'type' => 'success',
                'message' => 'Estado del horario actualizado.'
            ];
        }
    } catch (Throwable $e) {
        $_SESSION['ajustes_asistencia_flash'] = [
            'type' => 'danger',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }

    header('Location: ajustes_asistencia.php');
    exit();
}

$horarios = $conn->query("SELECT id_horario, fecha_inicio, fecha_fin, hora_ingreso, tolerancia_min, estado, created_at
    FROM asistencia_horarios_ingreso
    ORDER BY fecha_inicio DESC, id_horario DESC")->fetchAll(PDO::FETCH_ASSOC);

$hoy = date('Y-m-d');
$stmtVigente = $conn->prepare("SELECT fecha_inicio, fecha_fin, hora_ingreso, tolerancia_min
    FROM asistencia_horarios_ingreso
    WHERE estado = 1 AND ? BETWEEN fecha_inicio AND fecha_fin
    ORDER BY fecha_inicio DESC, id_horario DESC
    LIMIT 1");
$stmtVigente->execute([$hoy]);
$vigenteHoy = $stmtVigente->fetch(PDO::FETCH_ASSOC) ?: null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes de Asistencia</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 position-relative py-4">
                <?php if (isset($_SESSION['ajustes_asistencia_flash'])): ?>
                    <?php $flash = $_SESSION['ajustes_asistencia_flash']; unset($_SESSION['ajustes_asistencia_flash']); ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" role="alert">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Ajustes de Asistencia</h1>
                </div>

                <div class="card mb-4">
                    <div class="card-header">Horario vigente para hoy</div>
                    <div class="card-body">
                        <?php if ($vigenteHoy): ?>
                            <div class="alert alert-success mb-0">
                                <strong>Vigente hoy:</strong>
                                <?= htmlspecialchars($vigenteHoy['fecha_inicio']) ?> a <?= htmlspecialchars($vigenteHoy['fecha_fin']) ?> |
                                Ingreso: <strong><?= htmlspecialchars(substr($vigenteHoy['hora_ingreso'], 0, 5)) ?></strong> |
                                Tolerancia: <strong><?= (int)$vigenteHoy['tolerancia_min'] ?> min</strong>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                No hay un horario activo para la fecha de hoy.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">Crear horario de ingreso por rango</div>
                    <div class="card-body">
                        <form method="POST" action="" class="row g-3">
                            <input type="hidden" name="action" value="crear_horario">

                            <div class="col-md-3">
                                <label class="form-label">Fecha inicio</label>
                                <input type="date" name="fecha_inicio" class="form-control" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Fecha fin</label>
                                <input type="date" name="fecha_fin" class="form-control" required>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Hora ingreso</label>
                                <input type="time" name="hora_ingreso" class="form-control" required>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Tolerancia (min)</label>
                                <input type="number" min="0" max="120" name="tolerancia_min" class="form-control" value="0" required>
                            </div>

                            <div class="col-md-2">
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

                <div class="card">
                    <div class="card-header">Horarios configurados</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Rango</th>
                                        <th>Hora ingreso</th>
                                        <th>Tolerancia</th>
                                        <th>Estado</th>
                                        <th>Creado</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($horarios)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No hay horarios configurados.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($horarios as $idx => $h): ?>
                                            <tr>
                                                <td><?= $idx + 1 ?></td>
                                                <td><?= htmlspecialchars($h['fecha_inicio'] . ' a ' . $h['fecha_fin']) ?></td>
                                                <td><?= htmlspecialchars(substr($h['hora_ingreso'], 0, 5)) ?></td>
                                                <td><?= (int)$h['tolerancia_min'] ?> min</td>
                                                <td><?= (int)$h['estado'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                                                <td><?= htmlspecialchars($h['created_at']) ?></td>
                                                <td>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_estado">
                                                        <input type="hidden" name="id_horario" value="<?= (int)$h['id_horario'] ?>">
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
            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
