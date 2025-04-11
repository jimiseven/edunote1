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
    SELECT c.nivel, c.curso, c.paralelo, m.nombre_materia,
           pmc.estado
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduNote - Dashboard Profesor</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Menú lateral -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky">
                    <div class="sidebar-heading px-3 mt-3 fw-bold">EduNote</div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                Cursos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                Otros
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="btn btn-danger btn-sm w-100" href="../includes/logout.php">Cerrar Sesión</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Contenido principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <header class="d-flex justify-content-between align-items-center py-3">
                    <h1 class="h3">Cursos</h1>
                    <div>
                        <span class="badge bg-secondary">
                            Profesor: <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </span>
                    </div>
                </header>

                <!-- Tabla de cursos asignados -->
                <div class="card shadow">
                    <div class="card-header">
                        <h4 class="mb-0">Cursos Asignados</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nivel</th>
                                        <th>Curso</th>
                                        <th>Materia</th>
                                        <th>Acción</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($cursos) > 0): ?>
                                        <?php foreach ($cursos as $curso): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($curso['nivel']); ?></td>
                                                <td><?php echo htmlspecialchars($curso['curso']) . ' ' . htmlspecialchars($curso['paralelo']); ?></td>
                                                <td><?php echo htmlspecialchars($curso['nombre_materia']); ?></td>
                                                <td>
                                                    <a href="cargar_notas.php?curso_materia=1" class="btn btn-primary btn-sm">Cargar</a>

                                                </td>
                                                <td>
                                                    <?php if ($curso['estado'] == 'FALTA'): ?>
                                                        <span class="badge bg-danger">FALTA</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">CARGADO</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No tienes cursos asignados actualmente.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <button class="btn btn-secondary">Respaldo</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>

</html>