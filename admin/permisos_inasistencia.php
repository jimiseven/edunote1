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

if (!asistencia_auth_puede_gestionar_permisos($userRole, $lectorInfo)) {
    http_response_code(403);
    echo '<h3>Acceso denegado</h3><p>No tienes permisos para gestionar permisos de inasistencia.</p>';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'guardar_permiso') {
            $idEstudiante = (int)($_POST['id_estudiante'] ?? 0);
            $fecha = trim((string)($_POST['fecha'] ?? ''));
            $motivo = trim((string)($_POST['motivo'] ?? ''));
            $detalle = trim((string)($_POST['detalle'] ?? ''));

            if ($idEstudiante <= 0) {
                throw new RuntimeException('Debes seleccionar un estudiante.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                throw new RuntimeException('Fecha invalida.');
            }
            if ($motivo === '') {
                throw new RuntimeException('Debes ingresar un motivo.');
            }

            $stmt = $conn->prepare("INSERT INTO asistencia_permisos
                (id_estudiante, fecha, motivo, detalle, estado, registrado_por)
                VALUES (?, ?, ?, ?, 'APROBADO', ?)
                ON DUPLICATE KEY UPDATE
                    motivo = VALUES(motivo),
                    detalle = VALUES(detalle),
                    estado = 'APROBADO',
                    registrado_por = VALUES(registrado_por),
                    fecha_registro = CURRENT_TIMESTAMP");
            $stmt->execute([$idEstudiante, $fecha, $motivo, $detalle !== '' ? $detalle : null, $userId]);

            $_SESSION['permisos_inasistencia_flash'] = [
                'type' => 'success',
                'message' => 'Permiso guardado correctamente.'
            ];
        }

        if ($action === 'eliminar_permiso') {
            $idPermiso = (int)($_POST['id_permiso'] ?? 0);
            if ($idPermiso <= 0) {
                throw new RuntimeException('Permiso invalido.');
            }

            $stmt = $conn->prepare("DELETE FROM asistencia_permisos WHERE id_permiso = ?");
            $stmt->execute([$idPermiso]);

            $_SESSION['permisos_inasistencia_flash'] = [
                'type' => 'success',
                'message' => 'Permiso eliminado.'
            ];
        }
    } catch (Throwable $e) {
        $_SESSION['permisos_inasistencia_flash'] = [
            'type' => 'danger',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }

    header('Location: permisos_inasistencia.php');
    exit();
}

$fechaFiltro = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFiltro)) {
    $fechaFiltro = date('Y-m-d');
}

$busqueda = trim((string)($_GET['q'] ?? ''));

$stmtEst = $conn->query("SELECT e.id_estudiante, e.nombres, e.apellido_paterno, e.apellido_materno, e.carnet_identidad,
        c.nivel, c.curso, c.paralelo
    FROM estudiantes e
    INNER JOIN cursos c ON c.id_curso = e.id_curso
    ORDER BY FIELD(c.nivel, 'Inicial', 'Primaria', 'Secundaria'), c.curso, c.paralelo, e.apellido_paterno, e.apellido_materno, e.nombres");
$estudiantes = $stmtEst->fetchAll(PDO::FETCH_ASSOC);

$sqlPermisos = "SELECT ap.id_permiso, ap.id_estudiante, ap.fecha, ap.motivo, ap.detalle, ap.fecha_registro,
        e.nombres, e.apellido_paterno, e.apellido_materno,
        c.nivel, c.curso, c.paralelo,
        p.nombres AS reg_nombres, p.apellidos AS reg_apellidos
    FROM asistencia_permisos ap
    INNER JOIN estudiantes e ON e.id_estudiante = ap.id_estudiante
    INNER JOIN cursos c ON c.id_curso = e.id_curso
    LEFT JOIN personal p ON p.id_personal = ap.registrado_por
    WHERE ap.fecha = ?";

$params = [$fechaFiltro];
if ($busqueda !== '') {
    $sqlPermisos .= " AND (
        e.nombres LIKE ? OR
        e.apellido_paterno LIKE ? OR
        e.apellido_materno LIKE ? OR
        c.nivel LIKE ?
    )";
    $like = '%' . $busqueda . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sqlPermisos .= " ORDER BY FIELD(c.nivel, 'Inicial', 'Primaria', 'Secundaria'), c.curso, c.paralelo, e.apellido_paterno, e.apellido_materno, e.nombres";

$stmtPerm = $conn->prepare($sqlPermisos);
$stmtPerm->execute($params);
$permisos = $stmtPerm->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permisos de Inasistencia</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <main class="w-100 px-md-4 position-relative py-4">
                <?php if (isset($_SESSION['permisos_inasistencia_flash'])): ?>
                    <?php $flash = $_SESSION['permisos_inasistencia_flash']; unset($_SESSION['permisos_inasistencia_flash']); ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" role="alert">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Permisos por Inasistencia</h1>
                </div>

                <div class="card mb-4">
                    <div class="card-header">Registrar permiso</div>
                    <div class="card-body">
                        <form method="POST" action="" class="row g-3">
                            <input type="hidden" name="action" value="guardar_permiso">
                            <div class="col-md-5">
                                <label class="form-label">Estudiante</label>
                                <input type="text" id="buscar_estudiante" class="form-control mb-2" placeholder="Buscar por nombre, carnet o ID...">
                                <select name="id_estudiante" class="form-select" required>
                                    <option value="">Seleccione estudiante</option>
                                    <?php foreach ($estudiantes as $est): ?>
                                        <?php
                                            $nombreComp = trim(($est['apellido_paterno'] ?? '') . ' ' . ($est['apellido_materno'] ?? '') . ', ' . ($est['nombres'] ?? ''));
                                            $cursoComp = trim(($est['nivel'] ?? '') . ' ' . ($est['curso'] ?? '') . ' "' . ($est['paralelo'] ?? '') . '"');
                                            $ciComp = trim((string)($est['carnet_identidad'] ?? ''));
                                            $searchComp = mb_strtolower($nombreComp . ' ' . $cursoComp . ' ' . $ciComp . ' ' . (int)$est['id_estudiante'], 'UTF-8');
                                        ?>
                                        <option value="<?= (int)$est['id_estudiante'] ?>" data-search="<?= htmlspecialchars($searchComp) ?>">
                                            <?= htmlspecialchars('ID ' . (int)$est['id_estudiante'] . ' | CI ' . ($ciComp !== '' ? $ciComp : 'S/N') . ' | ' . $nombreComp . ' - ' . $cursoComp) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Fecha</label>
                                <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($fechaFiltro) ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Motivo</label>
                                <input type="text" name="motivo" class="form-control" maxlength="150" placeholder="Ej: Consulta medica" required>
                            </div>
                            <div class="col-md-10">
                                <label class="form-label">Detalle (opcional)</label>
                                <textarea name="detalle" class="form-control" rows="2" maxlength="1000" placeholder="Observaciones adicionales"></textarea>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Permisos registrados</div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Fecha</label>
                                <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($fechaFiltro) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Buscar</label>
                                <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Nombre, apellido o nivel">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Estudiante</th>
                                        <th>Curso</th>
                                        <th>Fecha</th>
                                        <th>Motivo</th>
                                        <th>Registrado por</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($permisos)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No hay permisos para la fecha seleccionada.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($permisos as $i => $perm): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars($perm['apellido_paterno'] . ' ' . $perm['apellido_materno'] . ', ' . $perm['nombres']) ?></td>
                                                <td><?= htmlspecialchars($perm['nivel'] . ' ' . $perm['curso'] . ' "' . $perm['paralelo'] . '"') ?></td>
                                                <td><?= htmlspecialchars($perm['fecha']) ?></td>
                                                <td>
                                                    <div><?= htmlspecialchars($perm['motivo']) ?></div>
                                                    <?php if (!empty($perm['detalle'])): ?>
                                                        <small class="text-muted"><?= nl2br(htmlspecialchars($perm['detalle'])) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars(trim(($perm['reg_nombres'] ?? '') . ' ' . ($perm['reg_apellidos'] ?? '')) ?: 'N/D') ?></td>
                                                <td>
                                                    <form method="POST" action="" onsubmit="return confirm('¿Eliminar este permiso?');">
                                                        <input type="hidden" name="action" value="eliminar_permiso">
                                                        <input type="hidden" name="id_permiso" value="<?= (int)$perm['id_permiso'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
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
    <script>
        (function() {
            const input = document.getElementById('buscar_estudiante');
            const select = document.querySelector('select[name="id_estudiante"]');
            if (!input || !select) {
                return;
            }

            const optionDefault = select.querySelector('option[value=""]');
            const options = Array.from(select.querySelectorAll('option')).filter(opt => opt.value !== '');

            input.addEventListener('input', function() {
                const term = this.value.trim().toLowerCase();
                let firstVisible = null;

                options.forEach(function(opt) {
                    const haystack = (opt.dataset.search || '').toLowerCase();
                    const visible = term === '' || haystack.includes(term);
                    opt.hidden = !visible;
                    if (visible && !firstVisible) {
                        firstVisible = opt;
                    }
                });

                if (term !== '') {
                    if (select.selectedOptions.length === 0 || (select.selectedOptions[0] && select.selectedOptions[0].hidden)) {
                        if (firstVisible) {
                            select.value = firstVisible.value;
                        } else {
                            select.value = '';
                        }
                    }
                } else if (optionDefault) {
                    optionDefault.hidden = false;
                }
            });
        })();
    </script>
</body>
</html>
