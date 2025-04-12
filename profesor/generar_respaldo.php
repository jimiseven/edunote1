<?php
session_start();
require_once '../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header('Location: ../index.php');
    exit();
}

// Obtener ID del profesor
$profesor_id = $_SESSION['user_id'];
$profesor_nombre = $_SESSION['user_name'];

// Conexión a la base de datos
$database = new Database();
$conn = $database->connect();

// Obtener todos los cursos asignados al profesor
$query_cursos = "
    SELECT pmc.id_curso_materia, pmc.estado, c.nivel, c.curso, c.paralelo, 
           m.nombre_materia, m.id_materia, c.id_curso
    FROM profesores_materias_cursos pmc
    INNER JOIN cursos_materias cm ON pmc.id_curso_materia = cm.id_curso_materia
    INNER JOIN cursos c ON cm.id_curso = c.id_curso
    INNER JOIN materias m ON cm.id_materia = m.id_materia
    WHERE pmc.id_personal = :profesor_id
    ORDER BY c.nivel, c.curso, c.paralelo, m.nombre_materia
";
$stmt_cursos = $conn->prepare($query_cursos);
$stmt_cursos->bindParam(':profesor_id', $profesor_id, PDO::PARAM_INT);
$stmt_cursos->execute();
$cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);

// Organizar cursos por estado
$cursos_cargados = [];
$cursos_pendientes = [];

foreach ($cursos as $curso) {
    if ($curso['estado'] == 'CARGADO') {
        $cursos_cargados[] = $curso;
    } else {
        $cursos_pendientes[] = $curso;
    }
}

// Función para obtener estudiantes y sus notas
function obtenerEstudiantesNotas($conn, $id_curso, $id_materia) {
    $query = "
        SELECT e.id_estudiante, CONCAT(e.nombres, ' ', e.apellido_paterno, ' ', e.apellido_materno) AS nombre_completo
        FROM estudiantes e
        WHERE e.id_curso = :id_curso
        ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres
    ";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
    $stmt->execute();
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener notas para cada estudiante
    foreach ($estudiantes as &$estudiante) {
        $query_notas = "
            SELECT bimestre, calificacion
            FROM calificaciones
            WHERE id_estudiante = :id_estudiante AND id_materia = :id_materia
            ORDER BY bimestre
        ";
        $stmt_notas = $conn->prepare($query_notas);
        $stmt_notas->bindParam(':id_estudiante', $estudiante['id_estudiante'], PDO::PARAM_INT);
        $stmt_notas->bindParam(':id_materia', $id_materia, PDO::PARAM_INT);
        $stmt_notas->execute();
        
        $notas = [];
        while ($nota = $stmt_notas->fetch(PDO::FETCH_ASSOC)) {
            $notas[$nota['bimestre']] = $nota['calificacion'];
        }
        
        $estudiante['notas'] = $notas;
        
        // Calcular promedio si hay notas
        if (!empty($notas)) {
            $suma = 0;
            $count = 0;
            foreach ($notas as $calificacion) {
                $suma += floatval($calificacion);
                $count++;
            }
            $estudiante['promedio'] = $count > 0 ? number_format($suma / $count, 2) : 'N/A';
        } else {
            $estudiante['promedio'] = 'N/A';
        }
    }
    
    return $estudiantes;
}

// Datos para cada curso
$detalles_cursos = [];
foreach ($cursos as $curso) {
    $curso['estudiantes'] = obtenerEstudiantesNotas($conn, $curso['id_curso'], $curso['id_materia']);
    $detalles_cursos[] = $curso;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respaldo de Calificaciones - EduNote</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        @media print {
            body {
                font-size: 12pt;
                color: #000;
                background-color: #fff;
            }
            .no-print {
                display: none !important;
            }
            .container {
                width: 100%;
                max-width: 100%;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
            }
            .table th, .table td {
                border: 1px solid #000;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
            }
            .page-break {
                page-break-before: always;
            }
            .badge-success, .badge-danger {
                color: #000 !important;
                background-color: transparent !important;
            }
            .badge-success {
                border: 1px solid #28a745;
            }
            .badge-danger {
                border: 1px solid #dc3545;
            }
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-top: 20px;
        }
        .subheader {
            margin-top: 30px;
            margin-bottom: 15px;
        }
        .curso-header {
            background-color: #f0f0f0;
            padding: 10px;
            margin-top: 30px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Botones solo visibles antes de imprimir -->
        <div class="row mb-4 no-print">
            <div class="col">
                <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
                <button onclick="window.print()" class="btn btn-primary ms-2">Imprimir Respaldo</button>
            </div>
        </div>

        <!-- Encabezado general -->
        <div class="header">
            <h1>Respaldo de Calificaciones</h1>
            <h3>Profesor: <?php echo htmlspecialchars($profesor_nombre); ?></h3>
            <p>Fecha: <?php echo date('d/m/Y'); ?></p>
        </div>

        <!-- Resumen de cursos por estado -->
        <h2 class="subheader">Resumen de Estado de Cursos</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Nivel</th>
                    <th>Curso</th>
                    <th>Materia</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cursos as $curso): ?>
                <tr>
                    <td><?php echo htmlspecialchars($curso['nivel']); ?></td>
                    <td><?php echo htmlspecialchars($curso['curso']) . ' "' . htmlspecialchars($curso['paralelo']) . '"'; ?></td>
                    <td><?php echo htmlspecialchars($curso['nombre_materia']); ?></td>
                    <td>
                        <?php if ($curso['estado'] == 'CARGADO'): ?>
                            <span class="badge bg-success">CARGADO</span>
                        <?php else: ?>
                            <span class="badge bg-danger">FALTA</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Detalle de calificaciones por curso -->
        <h2 class="subheader page-break">Detalle de Calificaciones por Curso</h2>
        
        <?php foreach ($detalles_cursos as $index => $curso): ?>
            <?php if ($index > 0): ?>
                <div class="page-break"></div>
            <?php endif; ?>
            
            <div class="curso-header">
                <h3><?php echo htmlspecialchars($curso['nivel']) . ' ' . htmlspecialchars($curso['curso']) . ' "' . htmlspecialchars($curso['paralelo']) . '" - ' . htmlspecialchars($curso['nombre_materia']); ?></h3>
                <p>Estado: 
                    <?php if ($curso['estado'] == 'CARGADO'): ?>
                        <span class="badge bg-success">CARGADO</span>
                    <?php else: ?>
                        <span class="badge bg-danger">FALTA</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <?php if (empty($curso['estudiantes'])): ?>
                <p class="text-center">No hay estudiantes asignados a este curso.</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 40%">Nombre del Estudiante</th>
                            <th style="width: 15%">Nota 1</th>
                            <th style="width: 15%">Nota 2</th>
                            <th style="width: 15%">Nota 3</th>
                            <th style="width: 15%">Promedio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($curso['estudiantes'] as $estudiante): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($estudiante['nombre_completo']); ?></td>
                                <td><?php echo isset($estudiante['notas'][1]) ? $estudiante['notas'][1] : 'N/A'; ?></td>
                                <td><?php echo isset($estudiante['notas'][2]) ? $estudiante['notas'][2] : 'N/A'; ?></td>
                                <td><?php echo isset($estudiante['notas'][3]) ? $estudiante['notas'][3] : 'N/A'; ?></td>
                                <td><?php echo $estudiante['promedio']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
