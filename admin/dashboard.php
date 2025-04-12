<?php
session_start();
require_once '../config/database.php';

// Verificar autenticación del administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

// Conexión a la base de datos
$database = new Database();
$conn = $database->connect();

// Obtener listado de cursos por nivel
try {
    $query_cursos = "
        SELECT id_curso, nivel, curso, paralelo
        FROM cursos
        ORDER BY nivel, CAST(curso AS UNSIGNED), paralelo
    ";
    $stmt = $conn->prepare($query_cursos);
    $stmt->execute();
    $todos_cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar cursos por nivel (CASE INSENSITIVE)
    $cursos_por_nivel = [
        'INICIAL' => [],
        'PRIMARIA' => [],
        'SECUNDARIA' => []
    ];
    
    foreach ($todos_cursos as $curso) {
        $nivel_upper = strtoupper(trim($curso['nivel']));
        if ($nivel_upper == 'INICIAL') {
            $cursos_por_nivel['INICIAL'][] = $curso;
        } elseif ($nivel_upper == 'PRIMARIA') {
            $cursos_por_nivel['PRIMARIA'][] = $curso;
        } elseif ($nivel_upper == 'SECUNDARIA') {
            $cursos_por_nivel['SECUNDARIA'][] = $curso;
        }
    }
    
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduNote - Dashboard Administrador</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            background-color: #212529;
            color: white;
            min-height: 100vh;
        }
        .nav-link {
            color: white;
            padding: 10px 20px;
        }
        .nav-link.active {
            background-color: #007bff;
        }
        .main-title {
            color: #007bff;
            font-weight: bold;
            margin: 20px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .level-title {
            text-align: center;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .tabla-cursos {
            margin-bottom: 30px;
            border: 1px solid #dee2e6;
        }
        .tabla-cursos th {
            background-color: #f8f9fa;
        }
        .btn-ver {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>

            <!-- Contenido principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <h1 class="main-title">Cursos</h1>

                <div class="row">
                    <?php foreach (['INICIAL', 'PRIMARIA', 'SECUNDARIA'] as $nivel): ?>
                        <div class="col-md-4">
                            <h2 class="level-title"><?php echo $nivel; ?></h2>
                            <div class="table-responsive tabla-cursos">
                                <table class="table table-hover text-center mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Curso</th>
                                            <th>Paralelo</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($cursos_por_nivel[$nivel])): ?>
                                            <?php $contador = 1; ?>
                                            <?php foreach ($cursos_por_nivel[$nivel] as $curso): ?>
                                                <tr>
                                                    <td><?php echo $contador++; ?></td>
                                                    <td><?php echo htmlspecialchars($curso['curso']); ?></td>
                                                    <td><?php echo htmlspecialchars($curso['paralelo']); ?></td>
                                                    <td>
                                                        <a href="ver_curso.php?id=<?php echo $curso['id_curso']; ?>" class="btn-ver">Ver</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4">No hay cursos disponibles.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
