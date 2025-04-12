<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header('Location: ../index.php');
    exit();
}

// Conexión a la base de datos
$database = new Database();
$conn = $database->connect();

// Obtener datos del profesor y sus materias/cursos asignados
$profesor_id = $_SESSION['user_id'];

$query = "
    SELECT pmc.id_curso_materia, c.nivel, c.curso, c.paralelo, m.nombre_materia, pmc.estado
    FROM profesores_materias_cursos pmc
    INNER JOIN cursos_materias cm ON pmc.id_curso_materia = cm.id_curso_materia
    INNER JOIN cursos c ON cm.id_curso = c.id_curso
    INNER JOIN materias m ON cm.id_materia = m.id_materia
    WHERE pmc.id_personal = :profesor_id
";
$stmt = $conn->prepare($query);
$stmt->bindParam(':profesor_id', $profesor_id, PDO::PARAM_INT);
$stmt->execute();
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>EduNote - Dashboard Profesor</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        .sidebar {
            background-color: #f8f8f8;
            min-height: 100vh;
            padding: 1rem;
        }
        
        .main-content {
            padding: 1.5rem;
            overflow-x: auto;
        }
        
        .table-responsive {
            min-width: 600px;
        }
        
        .status-badge {
            font-size: 0.9rem;
            padding: 0.4em 0.8em;
        }
        
        .btn-action {
            white-space: nowrap;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                padding: 1rem 0;
            }
            
            .header-title {
                font-size: 1.2rem;
            }
            
            .user-badge {
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 576px) {
            .table th, .table td {
                padding: 0.75rem 0.5rem;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>

            <!-- Contenido principal -->
            <main class="col-md-9 col-lg-10 px-0 main-content">
                <!-- Encabezado -->
                <header class="d-flex flex-column flex-md-row justify-content-between align-items-center p-3 bg-light border-bottom">
                    <h1 class="header-title h5 mb-3 mb-md-0 text-primary fw-bold">Cursos Asignados</h1>
                    <span class="user-badge badge bg-secondary">
                        Profesor: <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </span>
                </header>

                <!-- Contenido -->
                <div class="container-fluid p-3">
                    <div class="card shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">Nivel</th>
                                            <th scope="col">Curso</th>
                                            <th scope="col">Materia</th>
                                            <th scope="col" class="text-center">Acción</th>
                                            <th scope="col" class="text-center">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($cursos)): ?>
                                            <?php foreach ($cursos as $curso): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($curso['nivel']); ?></td>
                                                    <td><?php echo htmlspecialchars($curso['curso']) . ' ' . htmlspecialchars($curso['paralelo']); ?></td>
                                                    <td><?php echo htmlspecialchars($curso['nombre_materia']); ?></td>
                                                    <td class="text-center">
                                                        <a href="cargar_notas.php?curso_materia=<?php echo htmlspecialchars($curso['id_curso_materia']); ?>" 
                                                           class="btn btn-primary btn-sm btn-action">
                                                            Cargar
                                                        </a>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($curso['estado'] == 'CARGADO'): ?>
                                                            <span class="badge bg-success status-badge">CARGADO</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger status-badge">FALTA</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">No tienes cursos asignados actualmente.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0 text-center text-md-end">
                            <a href="generar_respaldo.php" class="btn btn-secondary">Generar Respaldo</a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
