<?php
session_start();
require_once '../config/database.php';

// Solo administradores pueden modificar estudiantes.
if (!isset($_SESSION['user_id']) || (int)($_SESSION['user_role'] ?? 0) !== 1) {
    if (isset($_SESSION['user_id']) && (int)($_SESSION['user_role'] ?? 0) === 4) {
        $_SESSION['error'] = 'El usuario invitado no puede editar estudiantes.';
        header('Location: estudiantes.php');
        exit();
    }
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

$allowedReturns = [
    'dashboard_primaria.php',
    'dashboard_secundaria.php',
    'estudiantes.php'
];

$returnParam = $_GET['return'] ?? ($_POST['return'] ?? null);
$returnUrl = null;
if (is_string($returnParam) && in_array($returnParam, $allowedReturns, true)) {
    $returnUrl = $returnParam;
}

// Obtener ID del estudiante
$id_estudiante = $_GET['id'] ?? null;
if (!$id_estudiante) {
    header('Location: estudiantes.php');
    exit();
}

// Obtener datos del estudiante
$sql = "
    SELECT e.*
    FROM estudiantes e
    WHERE e.id_estudiante = ?
";
$stmt = $conn->prepare($sql);
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    header('Location: estudiantes.php');
    exit();
}

$responsablesEstudiante = [];

try {
    $stmtResp = $conn->prepare("SELECT er.id_responsable, er.tipo_responsable, er.es_principal, r.nombre, r.apellido, r.carnet_identidad, r.telefono
        FROM estudiantes_responsables er
        INNER JOIN responsables r ON er.id_responsable = r.id_responsable
        WHERE er.id_estudiante = ?
        ORDER BY er.es_principal DESC, er.id_estudiante_responsable ASC
        LIMIT 2");
    $stmtResp->execute([$id_estudiante]);
    $responsablesEstudiante = $stmtResp->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $responsablesEstudiante = [];
}

if (empty($responsablesEstudiante) && !empty($estudiante['id_responsable'])) {
    $stmtRespLegacy = $conn->prepare('SELECT id_responsable, nombre, apellido, carnet_identidad, telefono FROM responsables WHERE id_responsable = ? LIMIT 1');
    $stmtRespLegacy->execute([(int)$estudiante['id_responsable']]);
    $legacy = $stmtRespLegacy->fetch(PDO::FETCH_ASSOC);
    if ($legacy) {
        $legacy['tipo_responsable'] = null;
        $legacy['es_principal'] = 1;
        $responsablesEstudiante[] = $legacy;
    }
}

for ($i = count($responsablesEstudiante); $i < 2; $i++) {
    $responsablesEstudiante[] = [
        'id_responsable' => '',
        'tipo_responsable' => '',
        'es_principal' => ($i === 0 ? 1 : 0),
        'nombre' => '',
        'apellido' => '',
        'carnet_identidad' => '',
        'telefono' => ''
    ];
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'eliminar_estudiante') {
            $idEliminar = isset($_POST['id_estudiante']) ? (int)$_POST['id_estudiante'] : 0;
            if ($idEliminar > 0) {
                $stmtNombre = $conn->prepare("SELECT TRIM(CONCAT(COALESCE(apellido_paterno,''), ' ', COALESCE(apellido_materno,''), ' ', COALESCE(nombres,''))) AS nombre FROM estudiantes WHERE id_estudiante = ? LIMIT 1");
                $stmtNombre->execute([$idEliminar]);
                $rowNombre = $stmtNombre->fetch(PDO::FETCH_ASSOC);
                $nombreEliminar = $rowNombre['nombre'] ?? '';

                $stmtDel = $conn->prepare('DELETE FROM estudiantes WHERE id_estudiante = ?');
                $stmtDel->execute([$idEliminar]);

                $msg = 'Se eliminó al estudiante "' . $nombreEliminar . '"';
                $_SESSION['success'] = $msg;
                $_SESSION['toast_message'] = $msg;
            }

            header('Location: ' . ($returnUrl ?? 'estudiantes.php'));
            exit();
        }

        $nombres = trim($_POST['nombres']);
        $apellido_paterno = trim($_POST['apellido_paterno']);
        $apellido_materno = trim($_POST['apellido_materno']);
        $ci = trim($_POST['ci']);
        $genero = $_POST['genero'];
        $rude = trim($_POST['rude']);
        $fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : null;
        $id_curso = $_POST['curso'];
        $responsablesInput = [];
        for ($i = 1; $i <= 2; $i++) {
            $responsablesInput[] = [
                'id_responsable' => trim($_POST['id_responsable_' . $i] ?? ''),
                'ci' => trim($_POST['responsable_ci_' . $i] ?? ''),
                'nombre' => trim($_POST['responsable_nombre_' . $i] ?? ''),
                'apellido' => trim($_POST['responsable_apellido_' . $i] ?? ''),
                'telefono' => trim($_POST['responsable_telefono_' . $i] ?? ''),
                'tipo_responsable' => trim($_POST['tipo_responsable_' . $i] ?? '')
            ];
        }
        $estado_1 = isset($_POST['estado_1']) ? trim($_POST['estado_1']) : '';
        $estado_2 = isset($_POST['estado_2']) ? trim($_POST['estado_2']) : '';

        $nombres = ($nombres === '') ? null : $nombres;
        $apellido_paterno = ($apellido_paterno === '') ? null : $apellido_paterno;
        $apellido_materno = ($apellido_materno === '') ? null : $apellido_materno;
        $ci = ($ci === '') ? null : $ci;
        $rude = ($rude === '') ? null : $rude;

        $fecha_nacimiento = ($fecha_nacimiento === '') ? null : $fecha_nacimiento;
        $estado_1 = ($estado_1 === '') ? null : $estado_1;
        $estado_2 = ($estado_2 === '') ? null : $estado_2;

        foreach ($responsablesInput as $index => $responsable) {
            $slot = $index + 1;
            $tieneDatos = ($responsable['ci'] !== '' || $responsable['nombre'] !== '' || $responsable['apellido'] !== '' || $responsable['telefono'] !== '');

            if (!$tieneDatos) {
                continue;
            }

            if ($responsable['ci'] === '') {
                throw new PDOException("Si registra el responsable {$slot}, debe completar su CI.");
            }

            if ($responsable['id_responsable'] === '' && ($responsable['nombre'] === '' || $responsable['apellido'] === '')) {
                throw new PDOException("Si registra el responsable {$slot}, complete Nombre y Apellido.");
            }
        }

        $conn->beginTransaction();

        $responsablesFinales = [];
        foreach ($responsablesInput as $index => $responsable) {
            $tieneDatos = ($responsable['ci'] !== '' || $responsable['nombre'] !== '' || $responsable['apellido'] !== '' || $responsable['telefono'] !== '');
            if (!$tieneDatos && $responsable['id_responsable'] === '') {
                continue;
            }

            $finalIdResponsable = null;
            if ($responsable['id_responsable'] !== '') {
                $finalIdResponsable = (int)$responsable['id_responsable'];
            } else {
                $stmtFind = $conn->prepare('SELECT id_responsable FROM responsables WHERE carnet_identidad = :ci LIMIT 1');
                $stmtFind->bindParam(':ci', $responsable['ci']);
                $stmtFind->execute();
                $existente = $stmtFind->fetch(PDO::FETCH_ASSOC);

                if ($existente) {
                    $finalIdResponsable = (int)$existente['id_responsable'];
                } else {
                    $stmtNew = $conn->prepare('INSERT INTO responsables (nombre, apellido, carnet_identidad, telefono) VALUES (:nombre, :apellido, :ci, :telefono)');
                    $stmtNew->bindParam(':nombre', $responsable['nombre']);
                    $stmtNew->bindParam(':apellido', $responsable['apellido']);
                    $stmtNew->bindParam(':ci', $responsable['ci']);
                    $stmtNew->bindParam(':telefono', $responsable['telefono']);
                    $stmtNew->execute();
                    $finalIdResponsable = (int)$conn->lastInsertId();
                }
            }

            if ($finalIdResponsable !== null && !isset($responsablesFinales[$finalIdResponsable])) {
                $tipo = $responsable['tipo_responsable'];
                if (!in_array($tipo, ['PADRE', 'MADRE', 'TUTOR'], true)) {
                    $tipo = null;
                }

                $responsablesFinales[$finalIdResponsable] = [
                    'id_responsable' => $finalIdResponsable,
                    'tipo_responsable' => $tipo,
                    'es_principal' => ($index === 0) ? 1 : 0
                ];
            }
        }

        $responsablesFinales = array_values($responsablesFinales);
        $idResponsableLegacy = isset($responsablesFinales[0]) ? (int)$responsablesFinales[0]['id_responsable'] : null;

        $sql = "UPDATE estudiantes SET 
                nombres = ?, 
                apellido_paterno = ?, 
                apellido_materno = ?, 
                carnet_identidad = ?, 
                genero = ?, 
                rude = ?, 
                fecha_nacimiento = ?,
                id_curso = ?, 
                id_responsable = ? 
                , estado_1 = ?
                , estado_2 = ?
                WHERE id_estudiante = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $nombres,
            $apellido_paterno,
            $apellido_materno,
            $ci,
            $genero,
            $rude,
            $fecha_nacimiento,
            $id_curso,
            $idResponsableLegacy,
            $estado_1,
            $estado_2,
            $id_estudiante
        ]);

        $stmtDelRel = $conn->prepare('DELETE FROM estudiantes_responsables WHERE id_estudiante = ?');
        $stmtDelRel->execute([$id_estudiante]);

        if (!empty($responsablesFinales)) {
            $stmtRel = $conn->prepare('INSERT INTO estudiantes_responsables (id_estudiante, id_responsable, tipo_responsable, es_principal) VALUES (?, ?, ?, ?)');
            foreach ($responsablesFinales as $rel) {
                $stmtRel->execute([
                    (int)$id_estudiante,
                    (int)$rel['id_responsable'],
                    $rel['tipo_responsable'],
                    (int)$rel['es_principal']
                ]);
            }
        }

        $conn->commit();

        $_SESSION['success'] = 'Estudiante actualizado correctamente';
        header('Location: ' . ($returnUrl ?? 'estudiantes.php'));
        exit();
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = 'Error al actualizar: ' . $e->getMessage();
    }
}

// Obtener cursos
$sqlCursos = "SELECT id_curso, CONCAT(nivel, ' ', curso, '° ', paralelo) AS nombre 
              FROM cursos ORDER BY nivel, curso, paralelo";
$cursos = $conn->query($sqlCursos)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Estudiante</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: #212c3a;
            color: white;
            z-index: 1000;
            overflow-y: auto;
        }
        .main-content {
            margin-left: 250px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 10px;
        }
        .form-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px 24px;
            width: 100%;
            max-width: 700px;
        }
        @media (max-width: 900px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }
            .main-content {
                margin-left: 0;
                padding: 20px 2px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>
    <div class="main-content">
        <div class="form-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="main-title">Editar Estudiante</h2>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalEliminarEstudiante">
                        Eliminar
                    </button>
                    <a href="<?php echo htmlspecialchars($returnUrl ?? 'estudiantes.php'); ?>" class="btn btn-outline-secondary">Volver</a>
                </div>
            </div>
            
            <form method="POST">
                <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl ?? ''); ?>">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="nombres" class="form-label">Nombres*</label>
                        <input type="text" class="form-control" id="nombres" name="nombres"
                               value="<?php echo htmlspecialchars($estudiante['nombres']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="apellido_paterno" class="form-label">Ap. Paterno*</label>
                        <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno"
                               value="<?php echo htmlspecialchars($estudiante['apellido_paterno']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="apellido_materno" class="form-label">Ap. Materno</label>
                        <input type="text" class="form-control" id="apellido_materno" name="apellido_materno"
                               value="<?php echo htmlspecialchars($estudiante['apellido_materno']); ?>">
                    </div>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="rude" class="form-label">RUDE*</label>
                        <input type="text" class="form-control" id="rude" name="rude"
                               value="<?php echo htmlspecialchars($estudiante['rude']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="ci" class="form-label">CI*</label>
                        <input type="text" class="form-control" id="ci" name="ci"
                               value="<?php echo htmlspecialchars($estudiante['carnet_identidad']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_nacimiento" class="form-label">F. Nacimiento</label>
                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento"
                               value="<?php echo htmlspecialchars($estudiante['fecha_nacimiento'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="genero" class="form-label">Género</label>
                        <select class="form-select" id="genero" name="genero">
                            <option value="">-</option>
                            <option value="Masculino" <?php echo $estudiante['genero'] === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="Femenino" <?php echo $estudiante['genero'] === 'Femenino' ? 'selected' : ''; ?>>Femenino</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="estado_1" class="form-label">Estado 1</label>
                        <select class="form-select" id="estado_1" name="estado_1">
                            <option value="">-</option>
                            <option value="EFECTIVO" <?php echo ($estudiante['estado_1'] ?? '') === 'EFECTIVO' ? 'selected' : ''; ?>>EFECTIVO</option>
                            <option value="NO_EFECTIVO" <?php echo ($estudiante['estado_1'] ?? '') === 'NO_EFECTIVO' ? 'selected' : ''; ?>>NO_EFECTIVO</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="estado_2" class="form-label">Estado 2</label>
                        <select class="form-select" id="estado_2" name="estado_2">
                            <option value="">-</option>
                            <option value="APROBADO" <?php echo ($estudiante['estado_2'] ?? '') === 'APROBADO' ? 'selected' : ''; ?>>APROBADO</option>
                            <option value="REPROBADO" <?php echo ($estudiante['estado_2'] ?? '') === 'REPROBADO' ? 'selected' : ''; ?>>REPROBADO</option>
                            <option value="NO_INCORPORADO" <?php echo ($estudiante['estado_2'] ?? '') === 'NO_INCORPORADO' ? 'selected' : ''; ?>>NO_INCORPORADO</option>
                            <option value="RETIRO_ABANDONO" <?php echo ($estudiante['estado_2'] ?? '') === 'RETIRO_ABANDONO' ? 'selected' : ''; ?>>RETIRO_ABANDONO</option>
                            <option value="RETIRO_TRASLADO" <?php echo ($estudiante['estado_2'] ?? '') === 'RETIRO_TRASLADO' ? 'selected' : ''; ?>>RETIRO_TRASLADO</option>
                        </select>
                    </div>
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="curso" class="form-label">Curso*</label>
                        <select class="form-select" id="curso" name="curso" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($cursos as $curso): ?>
                            <option value="<?php echo $curso['id_curso']; ?>"
                                <?php echo $curso['id_curso'] == $estudiante['id_curso'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($curso['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-2">
                    <div class="col-12">
                        <hr>
                    </div>
                    <div class="col-12">
                        <h6 class="mb-1">Responsable 1 (opcional)</h6>
                    </div>
                    <input type="hidden" id="id_responsable_1" name="id_responsable_1" value="<?php echo htmlspecialchars($responsablesEstudiante[0]['id_responsable'] ?? ''); ?>">
                    <div class="col-md-4">
                        <label for="responsable_ci_1" class="form-label">CI Responsable 1</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="responsable_ci_1" name="responsable_ci_1"
                                   value="<?php echo htmlspecialchars($responsablesEstudiante[0]['carnet_identidad'] ?? ''); ?>">
                            <button class="btn btn-outline-secondary" type="button" id="btnBuscarResponsable_1">Buscar</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="responsable_nombre_1" class="form-label">Nombre Responsable 1</label>
                        <input type="text" class="form-control" id="responsable_nombre_1" name="responsable_nombre_1"
                               value="<?php echo htmlspecialchars($responsablesEstudiante[0]['nombre'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="responsable_apellido_1" class="form-label">Apellido Responsable 1</label>
                        <input type="text" class="form-control" id="responsable_apellido_1" name="responsable_apellido_1"
                               value="<?php echo htmlspecialchars($responsablesEstudiante[0]['apellido'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="responsable_telefono_1" class="form-label">Teléfono Responsable 1</label>
                        <input type="text" class="form-control" id="responsable_telefono_1" name="responsable_telefono_1"
                               value="<?php echo htmlspecialchars($responsablesEstudiante[0]['telefono'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="tipo_responsable_1" class="form-label">Tipo Responsable 1</label>
                        <select class="form-select" id="tipo_responsable_1" name="tipo_responsable_1">
                            <option value="">-</option>
                            <option value="PADRE" <?php echo ($responsablesEstudiante[0]['tipo_responsable'] ?? '') === 'PADRE' ? 'selected' : ''; ?>>Padre</option>
                            <option value="MADRE" <?php echo ($responsablesEstudiante[0]['tipo_responsable'] ?? '') === 'MADRE' ? 'selected' : ''; ?>>Madre</option>
                            <option value="TUTOR" <?php echo ($responsablesEstudiante[0]['tipo_responsable'] ?? '') === 'TUTOR' ? 'selected' : ''; ?>>Tutor</option>
                        </select>
                    </div>
                    <div class="col-12 mt-2">
                        <hr>
                        <h6 class="mb-1">Responsable 2 (opcional)</h6>
                    </div>
                    <input type="hidden" id="id_responsable_2" name="id_responsable_2" value="<?php echo htmlspecialchars($responsablesEstudiante[1]['id_responsable'] ?? ''); ?>">
                    <div class="col-md-4">
                        <label for="responsable_ci_2" class="form-label">CI Responsable 2</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="responsable_ci_2" name="responsable_ci_2"
                                   value="<?php echo htmlspecialchars($responsablesEstudiante[1]['carnet_identidad'] ?? ''); ?>">
                            <button class="btn btn-outline-secondary" type="button" id="btnBuscarResponsable_2">Buscar</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="responsable_nombre_2" class="form-label">Nombre Responsable 2</label>
                        <input type="text" class="form-control" id="responsable_nombre_2" name="responsable_nombre_2"
                               value="<?php echo htmlspecialchars($responsablesEstudiante[1]['nombre'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="responsable_apellido_2" class="form-label">Apellido Responsable 2</label>
                        <input type="text" class="form-control" id="responsable_apellido_2" name="responsable_apellido_2"
                               value="<?php echo htmlspecialchars($responsablesEstudiante[1]['apellido'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="responsable_telefono_2" class="form-label">Teléfono Responsable 2</label>
                        <input type="text" class="form-control" id="responsable_telefono_2" name="responsable_telefono_2"
                               value="<?php echo htmlspecialchars($responsablesEstudiante[1]['telefono'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="tipo_responsable_2" class="form-label">Tipo Responsable 2</label>
                        <select class="form-select" id="tipo_responsable_2" name="tipo_responsable_2">
                            <option value="">-</option>
                            <option value="PADRE" <?php echo ($responsablesEstudiante[1]['tipo_responsable'] ?? '') === 'PADRE' ? 'selected' : ''; ?>>Padre</option>
                            <option value="MADRE" <?php echo ($responsablesEstudiante[1]['tipo_responsable'] ?? '') === 'MADRE' ? 'selected' : ''; ?>>Madre</option>
                            <option value="TUTOR" <?php echo ($responsablesEstudiante[1]['tipo_responsable'] ?? '') === 'TUTOR' ? 'selected' : ''; ?>>Tutor</option>
                        </select>
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>

            <div class="modal fade" id="modalEliminarEstudiante" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Eliminar estudiante</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger mb-0">
                                Se eliminará al estudiante irrevocablemente. Esta acción no se puede deshacer.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <form method="POST" class="m-0">
                                <input type="hidden" name="action" value="eliminar_estudiante">
                                <input type="hidden" name="id_estudiante" value="<?php echo (int)$id_estudiante; ?>">
                                <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl ?? ''); ?>">
                                <button type="submit" class="btn btn-danger">Eliminar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        function getResponsableElements(idx) {
            return {
                btnBuscar: document.getElementById(`btnBuscarResponsable_${idx}`),
                ci: document.getElementById(`responsable_ci_${idx}`),
                id: document.getElementById(`id_responsable_${idx}`),
                nombre: document.getElementById(`responsable_nombre_${idx}`),
                apellido: document.getElementById(`responsable_apellido_${idx}`),
                telefono: document.getElementById(`responsable_telefono_${idx}`)
            };
        }

        async function buscarResponsablePorCi(idx) {
            const refs = getResponsableElements(idx);
            const ci = (refs.ci.value || '').trim();
            if (ci === '') {
                return;
            }

            refs.btnBuscar.disabled = true;
            try {
                const res = await fetch(`estudiantes.php?action=buscar_responsable&ci=${encodeURIComponent(ci)}`);
                const data = await res.json();

                if (data && data.found && data.responsable) {
                    refs.id.value = data.responsable.id_responsable || '';
                    refs.nombre.value = data.responsable.nombre || '';
                    refs.apellido.value = data.responsable.apellido || '';
                    refs.telefono.value = data.responsable.telefono || '';
                } else {
                    refs.id.value = '';
                    refs.nombre.value = '';
                    refs.apellido.value = '';
                    refs.telefono.value = '';
                }
            } catch (e) {
                refs.id.value = '';
            } finally {
                refs.btnBuscar.disabled = false;
            }
        }

        [1, 2].forEach((idx) => {
            const refs = getResponsableElements(idx);
            refs.btnBuscar.addEventListener('click', () => buscarResponsablePorCi(idx));
            refs.ci.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarResponsablePorCi(idx);
                }
            });
        });
    </script>
</body>
</html>
