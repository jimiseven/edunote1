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
        if ($action === 'save_lector') {
            $idPersonal = (int)($_POST['id_personal'] ?? 0);
            $alcance = ($_POST['alcance'] ?? '') === 'POR_CURSO' ? 'POR_CURSO' : 'GLOBAL';

            if ($idPersonal <= 0) {
                throw new RuntimeException('Debes seleccionar un usuario de personal.');
            }

            $stmt = $conn->prepare("INSERT INTO asistencia_lectores (id_personal, alcance, estado)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE alcance = VALUES(alcance), estado = 1");
            $stmt->execute([$idPersonal, $alcance]);

            $stmtId = $conn->prepare("SELECT id_lector FROM asistencia_lectores WHERE id_personal = ? LIMIT 1");
            $stmtId->execute([$idPersonal]);
            $idLector = (int)$stmtId->fetchColumn();

            if ($alcance === 'GLOBAL' && $idLector > 0) {
                $stmtDel = $conn->prepare("DELETE FROM asistencia_lectores_cursos WHERE id_lector = ?");
                $stmtDel->execute([$idLector]);
            }

            $_SESSION['asistencia_lectores_flash'] = [
                'type' => 'success',
                'message' => 'Lector de asistencia guardado correctamente.'
            ];
        }

        if ($action === 'toggle_lector') {
            $idLector = (int)($_POST['id_lector'] ?? 0);
            $estado = (int)($_POST['estado'] ?? 0) === 1 ? 1 : 0;

            if ($idLector <= 0) {
                throw new RuntimeException('Lector inválido.');
            }

            $stmt = $conn->prepare("UPDATE asistencia_lectores SET estado = ? WHERE id_lector = ?");
            $stmt->execute([$estado, $idLector]);

            $_SESSION['asistencia_lectores_flash'] = [
                'type' => 'success',
                'message' => 'Estado del lector actualizado.'
            ];
        }

        if ($action === 'save_cursos') {
            $idLector = (int)($_POST['id_lector'] ?? 0);
            $cursos = $_POST['cursos'] ?? [];
            $cursos = is_array($cursos) ? array_map('intval', $cursos) : [];
            $cursos = array_values(array_unique(array_filter($cursos, function ($v) {
                return $v > 0;
            })));

            if ($idLector <= 0) {
                throw new RuntimeException('Lector inválido para asignar cursos.');
            }

            $conn->beginTransaction();

            $stmtAlcance = $conn->prepare("UPDATE asistencia_lectores SET alcance = 'POR_CURSO', estado = 1 WHERE id_lector = ?");
            $stmtAlcance->execute([$idLector]);

            $stmtDel = $conn->prepare("DELETE FROM asistencia_lectores_cursos WHERE id_lector = ?");
            $stmtDel->execute([$idLector]);

            if (!empty($cursos)) {
                $stmtIns = $conn->prepare("INSERT INTO asistencia_lectores_cursos (id_lector, id_curso, estado) VALUES (?, ?, 1)");
                foreach ($cursos as $idCurso) {
                    $stmtIns->execute([$idLector, $idCurso]);
                }
            }

            $conn->commit();

            $_SESSION['asistencia_lectores_flash'] = [
                'type' => 'success',
                'message' => 'Cursos asignados correctamente.'
            ];
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['asistencia_lectores_flash'] = [
            'type' => 'danger',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }

    header('Location: lectores_asistencia.php');
    exit();
}

$personal = $conn->query("SELECT p.id_personal, p.nombres, p.apellidos, p.carnet_identidad, p.id_rol, p.estado, r.nombre_rol
    FROM personal p
    LEFT JOIN roles r ON r.id_rol = p.id_rol
    ORDER BY p.apellidos, p.nombres")->fetchAll(PDO::FETCH_ASSOC);

$cursos = $conn->query("SELECT id_curso, nivel, curso, paralelo
    FROM cursos
    ORDER BY FIELD(nivel, 'Inicial', 'Primaria', 'Secundaria'), curso, paralelo")->fetchAll(PDO::FETCH_ASSOC);

$lectores = $conn->query("SELECT al.id_lector, al.id_personal, al.alcance, al.estado,
        p.nombres, p.apellidos, p.carnet_identidad, r.nombre_rol,
        (SELECT COUNT(*) FROM asistencia_lectores_cursos alc WHERE alc.id_lector = al.id_lector AND alc.estado = 1) AS total_cursos
    FROM asistencia_lectores al
    INNER JOIN personal p ON p.id_personal = al.id_personal
    LEFT JOIN roles r ON r.id_rol = p.id_rol
    ORDER BY al.estado DESC, p.apellidos, p.nombres")->fetchAll(PDO::FETCH_ASSOC);

$asigRows = $conn->query("SELECT id_lector, id_curso FROM asistencia_lectores_cursos WHERE estado = 1")->fetchAll(PDO::FETCH_ASSOC);
$asignaciones = [];
foreach ($asigRows as $row) {
    $idLector = (int)$row['id_lector'];
    if (!isset($asignaciones[$idLector])) {
        $asignaciones[$idLector] = [];
    }
    $asignaciones[$idLector][] = (int)$row['id_curso'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lectores de Asistencia</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 position-relative py-4">
                <?php if (isset($_SESSION['asistencia_lectores_flash'])): ?>
                    <?php $flash = $_SESSION['asistencia_lectores_flash']; unset($_SESSION['asistencia_lectores_flash']); ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" role="alert">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Lectores de Asistencia</h1>
                </div>

                <div class="card mb-4">
                    <div class="card-header">Habilitar o actualizar lector</div>
                    <div class="card-body">
                        <form method="POST" action="" class="row g-3">
                            <input type="hidden" name="action" value="save_lector">
                            <div class="col-md-7">
                                <label class="form-label">Usuario de personal</label>
                                <select name="id_personal" class="form-select" required>
                                    <option value="">Seleccione un usuario</option>
                                    <?php foreach ($personal as $per): ?>
                                        <option value="<?= (int)$per['id_personal'] ?>">
                                            <?= htmlspecialchars($per['apellidos'] . ', ' . $per['nombres'] . ' | CI: ' . $per['carnet_identidad'] . ' | ' . ($per['nombre_rol'] ?: 'Sin rol') . ' | ' . ((int)$per['estado'] === 1 ? 'Activo' : 'Inactivo')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Alcance</label>
                                <select name="alcance" class="form-select">
                                    <option value="GLOBAL">GLOBAL</option>
                                    <option value="POR_CURSO">POR_CURSO</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Usuarios habilitados para registrar asistencia</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Rol</th>
                                        <th>Alcance</th>
                                        <th>Cursos</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lectores)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">No hay lectores registrados.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($lectores as $lector): ?>
                                            <?php $idLector = (int)$lector['id_lector']; ?>
                                            <tr>
                                                <td><?= htmlspecialchars($lector['apellidos'] . ', ' . $lector['nombres'] . ' (CI ' . $lector['carnet_identidad'] . ')') ?></td>
                                                <td><?= htmlspecialchars($lector['nombre_rol'] ?: 'Sin rol') ?></td>
                                                <td><?= htmlspecialchars($lector['alcance']) ?></td>
                                                <td><?= (int)$lector['total_cursos'] ?></td>
                                                <td><?= (int)$lector['estado'] === 1 ? 'Habilitado' : 'Inhabilitado' ?></td>
                                                <td>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_lector">
                                                        <input type="hidden" name="id_lector" value="<?= $idLector ?>">
                                                        <input type="hidden" name="estado" value="<?= (int)$lector['estado'] === 1 ? 0 : 1 ?>">
                                                        <button type="submit" class="btn btn-sm <?= (int)$lector['estado'] === 1 ? 'btn-warning' : 'btn-success' ?>">
                                                            <?= (int)$lector['estado'] === 1 ? 'Inhabilitar' : 'Habilitar' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="6" class="bg-light">
                                                    <form method="POST" action="" class="row g-2">
                                                        <input type="hidden" name="action" value="save_cursos">
                                                        <input type="hidden" name="id_lector" value="<?= $idLector ?>">
                                                        <div class="col-12">
                                                            <strong>Asignar cursos (solo aplica a POR_CURSO):</strong>
                                                        </div>
                                                        <?php foreach ($cursos as $curso): ?>
                                                            <?php
                                                                $idCurso = (int)$curso['id_curso'];
                                                                $checked = in_array($idCurso, $asignaciones[$idLector] ?? [], true) ? 'checked' : '';
                                                            ?>
                                                            <div class="col-md-3 col-sm-6">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="cursos[]" value="<?= $idCurso ?>" id="lc<?= $idLector ?>c<?= $idCurso ?>" <?= $checked ?>>
                                                                    <label class="form-check-label" for="lc<?= $idLector ?>c<?= $idCurso ?>">
                                                                        <?= htmlspecialchars($curso['nivel'] . ' ' . $curso['curso'] . ' "' . $curso['paralelo'] . '"') ?>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                        <div class="col-12 mt-2">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">Guardar cursos</button>
                                                        </div>
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
