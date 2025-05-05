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
$sql = "SELECT * FROM estudiantes WHERE id_estudiante = ?";
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
        $id_curso = $_POST['curso'];

        $sql = "UPDATE estudiantes SET 
                nombres = ?, 
                apellido_paterno = ?, 
                apellido_materno = ?, 
                carnet_identidad = ?, 
                genero = ?, 
                rude = ?, 
                id_curso = ? 
                WHERE id_estudiante = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $nombres,
            $apellido_paterno,
            $apellido_materno,
            $ci,
            $genero,
            $rude,
            $id_curso,
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
                        <label for="apellido_paterno" class="form-label">Apellido Paterno*</label>
                        <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno"
                               value="<?php echo htmlspecialchars($estudiante['apellido_paterno']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="apellido_materno" class="form-label">Apellido Materno</label>
                        <input type="text" class="form-control" id="apellido_materno" name="apellido_materno"
                               value="<?php echo htmlspecialchars($estudiante['apellido_materno']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="nombres" class="form-label">Nombres*</label>
                        <input type="text" class="form-control" id="nombres" name="nombres"
                               value="<?php echo htmlspecialchars($estudiante['nombres']); ?>" required>
                    </div>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="ci" class="form-label">Carnet de Identidad*</label>
                        <input type="text" class="form-control" id="ci" name="ci"
                               value="<?php echo htmlspecialchars($estudiante['carnet_identidad']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="genero" class="form-label">Género</label>
                        <select class="form-select" id="genero" name="genero">
                            <option value="">-</option>
                            <option value="Masculino" <?php echo $estudiante['genero'] === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="Femenino" <?php echo $estudiante['genero'] === 'Femenino' ? 'selected' : ''; ?>>Femenino</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="rude" class="form-label">RUDE</label>
                        <input type="text" class="form-control" id="rude" name="rude"
                               value="<?php echo htmlspecialchars($estudiante['rude']); ?>">
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
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
