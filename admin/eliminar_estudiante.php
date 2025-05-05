<?php
session_start();
require_once '../config/database.php';

// Verificar permisos de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

// Obtener ID del estudiante a eliminar
$id_estudiante = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_estudiante <= 0) {
    $_SESSION['error'] = "ID de estudiante no válido";
    header('Location: estudiantes.php');
    exit();
}

// Verificar si se confirmó la eliminación
if (isset($_POST['confirmar'])) {
    try {
        // Verificar que el estudiante existe
        $check = $conn->prepare("SELECT id_estudiante FROM estudiantes WHERE id_estudiante = ?");
        $check->execute([$id_estudiante]);
        if (!$check->fetch()) {
            $_SESSION['error'] = "El estudiante ya no existe en la base de datos";
            header('Location: estudiantes.php');
            exit();
        }
        
        // Eliminar estudiante
        $sql = "DELETE FROM estudiantes WHERE id_estudiante = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id_estudiante]);
        
        $_SESSION['success'] = "Estudiante eliminado correctamente";
        header('Location: estudiantes.php');
        exit();
    } catch (PDOException $e) {
        // Capturar error de clave foránea
        if ($e->getCode() == '23000') {
            $_SESSION['error'] = "No se puede eliminar el estudiante porque tiene registros relacionados";
        } else {
            $_SESSION['error'] = "Error al eliminar: " . $e->getMessage();
        }
        header('Location: estudiantes.php');
        exit();
    }
}

// Obtener información del estudiante
$sql = "SELECT nombres, apellido_paterno, apellido_materno FROM estudiantes WHERE id_estudiante = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    $_SESSION['error'] = "Estudiante no encontrado";
    header('Location: estudiantes.php');
    exit();
}

$nombre_completo = trim($estudiante['apellido_paterno'] . ' ' . 
                       $estudiante['apellido_materno'] . ', ' . 
                       $estudiante['nombres']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Estudiante</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f8f9fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
        }
        
        .sidebar {
            position: fixed;
            width: 250px;
            height: 100vh;
            overflow-y: auto;
            background: #212c3a;
            color: white;
            z-index: 100;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px; /* Ancho del sidebar */
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .confirmation-box {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 30px;
            width: 100%;
            max-width: 500px;
        }
        
        .btn-eliminar {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .btn-eliminar:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>

    <div class="main-content">
        <div class="confirmation-box">
            <h3 class="text-center mb-4">Confirmar Eliminación</h3>
            
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                ¿Está seguro que desea eliminar al estudiante:
                <strong><?= htmlspecialchars($nombre_completo) ?></strong>?
            </div>
            
            <p class="text-muted small">Esta acción no se puede deshacer. El estudiante será eliminado permanentemente del sistema.</p>
            
            <form method="POST" class="mt-4 d-flex justify-content-center gap-3">
                <button type="submit" name="confirmar" value="1" class="btn btn-eliminar">
                    <i class="bi bi-trash me-1"></i> Sí, Eliminar
                </button>
                <a href="estudiantes.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle me-1"></i> Cancelar
                </a>
            </form>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
