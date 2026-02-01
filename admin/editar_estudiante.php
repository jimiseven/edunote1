<?php
session_start();
require_once '../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

// Obtener ID del estudiante
$id_estudiante = $_GET['id'] ?? null;
if (!$id_estudiante) {
    header('Location: estudiantes.php');
    exit();
}

// Obtener datos del estudiante
$sql = "
    SELECT 
        e.*, 
        r.id_responsable AS resp_id_responsable,
        r.nombre AS resp_nombre,
        r.apellido AS resp_apellido,
        r.carnet_identidad AS resp_carnet_identidad,
        r.telefono AS resp_telefono
    FROM estudiantes e
    LEFT JOIN responsables r ON e.id_responsable = r.id_responsable
    WHERE e.id_estudiante = ?
";
$stmt = $conn->prepare($sql);
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    header('Location: estudiantes.php');
    exit();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombres = trim($_POST['nombres']);
        $apellido_paterno = trim($_POST['apellido_paterno']);
        $apellido_materno = trim($_POST['apellido_materno']);
        $ci = trim($_POST['ci']);
        $genero = $_POST['genero'];
        $rude = trim($_POST['rude']);
        $fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : null;
        $id_curso = $_POST['curso'];
        $id_responsable = isset($_POST['id_responsable']) ? trim($_POST['id_responsable']) : '';
        $estado_1 = isset($_POST['estado_1']) ? trim($_POST['estado_1']) : '';
        $estado_2 = isset($_POST['estado_2']) ? trim($_POST['estado_2']) : '';

        $nombres = ($nombres === '') ? null : $nombres;
        $apellido_paterno = ($apellido_paterno === '') ? null : $apellido_paterno;
        $apellido_materno = ($apellido_materno === '') ? null : $apellido_materno;
        $ci = ($ci === '') ? null : $ci;
        $rude = ($rude === '') ? null : $rude;

        $id_responsable = ($id_responsable === '') ? null : (int)$id_responsable;
        $fecha_nacimiento = ($fecha_nacimiento === '') ? null : $fecha_nacimiento;
        $estado_1 = ($estado_1 === '') ? null : $estado_1;
        $estado_2 = ($estado_2 === '') ? null : $estado_2;

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
            $id_responsable,
            $estado_1,
            $estado_2,
            $id_estudiante
        ]);

        $_SESSION['success'] = 'Estudiante actualizado correctamente';
        header('Location: estudiantes.php');
        exit();
    } catch (PDOException $e) {
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
                <a href="estudiantes.php" class="btn btn-outline-secondary">Volver</a>
            </div>
            
            <form method="POST">
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

                <input type="hidden" id="id_responsable" name="id_responsable" value="<?php echo htmlspecialchars($estudiante['resp_id_responsable'] ?? ''); ?>">

                <div class="row g-3 mb-2">
                    <div class="col-12">
                        <hr>
                    </div>
                    <div class="col-md-4">
                        <label for="responsable_ci" class="form-label">CI Responsable*</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="responsable_ci" name="responsable_ci"
                                   value="<?php echo htmlspecialchars($estudiante['resp_carnet_identidad'] ?? ''); ?>">
                            <button class="btn btn-outline-secondary" type="button" id="btnBuscarResponsable">Buscar</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="responsable_nombre" class="form-label">Nombre Responsable*</label>
                        <input type="text" class="form-control" id="responsable_nombre" name="responsable_nombre"
                               value="<?php echo htmlspecialchars($estudiante['resp_nombre'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="responsable_apellido" class="form-label">Apellido Responsable*</label>
                        <input type="text" class="form-control" id="responsable_apellido" name="responsable_apellido"
                               value="<?php echo htmlspecialchars($estudiante['resp_apellido'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="responsable_telefono" class="form-label">Teléfono Responsable</label>
                        <input type="text" class="form-control" id="responsable_telefono" name="responsable_telefono"
                               value="<?php echo htmlspecialchars($estudiante['resp_telefono'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        const btnBuscarResponsable = document.getElementById('btnBuscarResponsable');
        const responsableCi = document.getElementById('responsable_ci');
        const idResponsable = document.getElementById('id_responsable');
        const responsableNombre = document.getElementById('responsable_nombre');
        const responsableApellido = document.getElementById('responsable_apellido');
        const responsableTelefono = document.getElementById('responsable_telefono');

        async function buscarResponsablePorCi() {
            const ci = (responsableCi.value || '').trim();
            if (ci === '') {
                return;
            }

            btnBuscarResponsable.disabled = true;
            try {
                const res = await fetch(`estudiantes.php?action=buscar_responsable&ci=${encodeURIComponent(ci)}`);
                const data = await res.json();

                if (data && data.found && data.responsable) {
                    idResponsable.value = data.responsable.id_responsable || '';
                    responsableNombre.value = data.responsable.nombre || '';
                    responsableApellido.value = data.responsable.apellido || '';
                    responsableTelefono.value = data.responsable.telefono || '';
                } else {
                    idResponsable.value = '';
                    responsableNombre.value = '';
                    responsableApellido.value = '';
                    responsableTelefono.value = '';
                }
            } catch (e) {
                idResponsable.value = '';
            } finally {
                btnBuscarResponsable.disabled = false;
            }
        }

        btnBuscarResponsable.addEventListener('click', buscarResponsablePorCi);
        responsableCi.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarResponsablePorCi();
            }
        });
    </script>
</body>
</html>
