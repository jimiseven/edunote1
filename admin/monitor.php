<?php
session_start();
require_once '../config/database.php';

// Verificar si el usuario está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$conn = $database->connect();

// Obtener el bimestre seleccionado o el activo por defecto
$bimestre_seleccionado = $_GET['bimestre'] ?? 1;
$stmt = $conn->prepare("SELECT bimestre_actual FROM configuracion_sistema LIMIT 1");
$stmt->execute();
if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $bimestre_activo = $result['bimestre_actual'];
    if (!isset($_GET['bimestre'])) {
        $bimestre_seleccionado = $bimestre_activo;
    }
}

// Obtener estadísticas por curso para Primaria
// Ahora contamos materias cargadas basándose en la existencia real de notas en la base de datos
$stmt = $conn->prepare("
    SELECT 
        c.curso,
        c.paralelo,
        COUNT(DISTINCT cm.id_curso_materia) as total_materias,
        COUNT(DISTINCT CASE WHEN EXISTS (
            SELECT 1 FROM calificaciones cal 
            WHERE cal.id_materia = cm.id_materia 
            AND cal.bimestre = ? 
            AND cal.id_estudiante IN (
                SELECT e.id_estudiante FROM estudiantes e WHERE e.id_curso = c.id_curso
            )
        ) THEN cm.id_curso_materia END) as materias_cargadas,
        COUNT(DISTINCT CASE WHEN NOT EXISTS (
            SELECT 1 FROM calificaciones cal 
            WHERE cal.id_materia = cm.id_materia 
            AND cal.bimestre = ? 
            AND cal.id_estudiante IN (
                SELECT e.id_estudiante FROM estudiantes e WHERE e.id_curso = c.id_curso
            )
        ) THEN cm.id_curso_materia END) as materias_pendientes
    FROM cursos_materias cm
    JOIN cursos c ON cm.id_curso = c.id_curso
    WHERE c.nivel = 'Primaria'
    GROUP BY c.curso, c.paralelo
    ORDER BY c.curso, c.paralelo
");
$stmt->execute([$bimestre_seleccionado, $bimestre_seleccionado]);
$primaria_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas por curso para Secundaria
$stmt = $conn->prepare("
    SELECT 
        c.curso,
        c.paralelo,
        COUNT(DISTINCT cm.id_curso_materia) as total_materias,
        COUNT(DISTINCT CASE WHEN EXISTS (
            SELECT 1 FROM calificaciones cal 
            WHERE cal.id_materia = cm.id_materia 
            AND cal.bimestre = ? 
            AND cal.id_estudiante IN (
                SELECT e.id_estudiante FROM estudiantes e WHERE e.id_curso = c.id_curso
            )
        ) THEN cm.id_curso_materia END) as materias_cargadas,
        COUNT(DISTINCT CASE WHEN NOT EXISTS (
            SELECT 1 FROM calificaciones cal 
            WHERE cal.id_materia = cm.id_materia 
            AND cal.bimestre = ? 
            AND cal.id_estudiante IN (
                SELECT e.id_estudiante FROM estudiantes e WHERE e.id_curso = c.id_curso
            )
        ) THEN cm.id_curso_materia END) as materias_pendientes
    FROM cursos_materias cm
    JOIN cursos c ON cm.id_curso = c.id_curso
    WHERE c.nivel = 'Secundaria'
    GROUP BY c.curso, c.paralelo
    ORDER BY c.curso, c.paralelo
");
$stmt->execute([$bimestre_seleccionado, $bimestre_seleccionado]);
$secundaria_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas generales por nivel
$stmt = $conn->prepare("
    SELECT 
        c.nivel,
        COUNT(DISTINCT cm.id_curso_materia) as total_materias,
        COUNT(DISTINCT CASE WHEN EXISTS (
            SELECT 1 FROM calificaciones cal 
            WHERE cal.id_materia = cm.id_materia 
            AND cal.bimestre = ? 
            AND cal.id_estudiante IN (
                SELECT e.id_estudiante FROM estudiantes e WHERE e.id_curso = c.id_curso
            )
        ) THEN cm.id_curso_materia END) as materias_cargadas,
        COUNT(DISTINCT CASE WHEN NOT EXISTS (
            SELECT 1 FROM calificaciones cal 
            WHERE cal.id_materia = cm.id_materia 
            AND cal.bimestre = ? 
            AND cal.id_estudiante IN (
                SELECT e.id_estudiante FROM estudiantes e WHERE e.id_curso = c.id_curso
            )
        ) THEN cm.id_curso_materia END) as materias_pendientes
    FROM cursos_materias cm
    JOIN cursos c ON cm.id_curso = c.id_curso
    WHERE c.nivel IN ('Primaria', 'Secundaria')
    GROUP BY c.nivel
    ORDER BY c.nivel
");
$stmt->execute([$bimestre_seleccionado, $bimestre_seleccionado]);
$niveles_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener totales generales
$total_materias = 0;
$total_cargadas = 0;
$total_pendientes = 0;

foreach ($niveles_stats as $nivel) {
    $total_materias += $nivel['total_materias'];
    $total_cargadas += $nivel['materias_cargadas'];
    $total_pendientes += $nivel['materias_pendientes'];
}

$porcentaje_completado = $total_materias > 0 ? round(($total_cargadas / $total_materias) * 100, 1) : 0;

// Obtener materias pendientes detalladas para Primaria
$stmt = $conn->prepare("
    SELECT 
        c.curso,
        c.paralelo,
        m.nombre_materia,
        p.nombres,
        p.apellidos
    FROM cursos_materias cm
    JOIN cursos c ON cm.id_curso = c.id_curso
    JOIN materias m ON cm.id_materia = m.id_materia
    LEFT JOIN profesores_materias_cursos pmc ON cm.id_curso_materia = pmc.id_curso_materia
    LEFT JOIN personal p ON pmc.id_personal = p.id_personal
    WHERE c.nivel = 'Primaria'
    AND NOT EXISTS (
        SELECT 1 FROM calificaciones cal 
        WHERE cal.id_materia = cm.id_materia 
        AND cal.bimestre = ? 
        AND cal.id_estudiante IN (
            SELECT e.id_estudiante FROM estudiantes e WHERE e.id_curso = c.id_curso
        )
    )
    ORDER BY c.curso, c.paralelo, m.nombre_materia
");
$stmt->execute([$bimestre_seleccionado]);
$primaria_materias_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener materias pendientes detalladas para Secundaria
$stmt = $conn->prepare("
    SELECT 
        c.curso,
        c.paralelo,
        m.nombre_materia,
        p.nombres,
        p.apellidos
    FROM cursos_materias cm
    JOIN cursos c ON cm.id_curso = c.id_curso
    JOIN materias m ON cm.id_materia = m.id_materia
    LEFT JOIN profesores_materias_cursos pmc ON cm.id_curso_materia = pmc.id_curso_materia
    LEFT JOIN personal p ON pmc.id_personal = p.id_personal
    WHERE c.nivel = 'Secundaria'
    AND NOT EXISTS (
        SELECT 1 FROM calificaciones cal 
        WHERE cal.id_materia = cm.id_materia 
        AND cal.bimestre = ? 
        AND cal.id_estudiante IN (
            SELECT e.id_estudiante FROM estudiantes e WHERE e.id_curso = c.id_curso
        )
    )
    ORDER BY c.curso, c.paralelo, m.nombre_materia
");
$stmt->execute([$bimestre_seleccionado]);
$secundaria_materias_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor - EDUNOTE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.css" rel="stylesheet">
    <link href="../css/styles.css" rel="stylesheet">
    <style>
        /* Estilos para fijar el sidebar */
        .sidebar {
            position: fixed !important;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        /* Ajustar el contenido principal para compensar el sidebar fijo */
        main {
            margin-left: 16.666667%; /* col-md-3 */
        }
        
        @media (min-width: 992px) {
            main {
                margin-left: 16.666667%; /* col-lg-2 */
            }
        }
        
        @media (max-width: 767.98px) {
            .sidebar {
                position: static !important;
                height: auto;
            }
            main {
                margin-left: 0;
            }
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .progress-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .nivel-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #4abff9;
        }
        .nivel-card.primaria {
            border-left-color: #28a745;
        }
        .nivel-card.secundaria {
            border-left-color: #17a2b8;
        }
        .progress {
            height: 25px;
            border-radius: 12px;
        }
        .progress-bar {
            background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
        }
        .curso-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .trimestre-selector {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .btn-trimestre {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            color: #6c757d;
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .btn-trimestre:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        .btn-trimestre.active {
            background: #4abff9;
            border-color: #4abff9;
            color: white;
        }
        .estado-cargado {
            color: #28a745;
            font-weight: 600;
        }
        .estado-pendiente {
            color: #dc3545;
            font-weight: 600;
        }
        .btn-info-materias {
            background: #17a2b8;
            border: none;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        .btn-info-materias:hover {
            background: #138496;
            color: white;
            transform: scale(1.05);
        }
        .materia-pendiente-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-left: 3px solid #ffc107;
        }
        .profesor-info {
            color: #6c757d;
            font-size: 0.9rem;
            font-style: italic;
        }
        
        /* Estilos adicionales para el sidebar */
        #sidebarMenu {
            position: fixed !important;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: #4abff9 #181f2c;
        }
        
        /* Personalizar scrollbar del sidebar */
        #sidebarMenu::-webkit-scrollbar {
            width: 6px;
        }
        
        #sidebarMenu::-webkit-scrollbar-track {
            background: #181f2c;
        }
        
        #sidebarMenu::-webkit-scrollbar-thumb {
            background: #4abff9;
            border-radius: 3px;
        }
        
        #sidebarMenu::-webkit-scrollbar-thumb:hover {
            background: #3a9fd8;
        }
        
        /* Asegurar que el contenido principal no interfiera con el sidebar */
        .container-fluid {
            padding-left: 0;
        }
        
        .row {
            margin-left: 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <span data-feather="monitor" class="me-2"></span>
                        Monitor de Carga de Notas
                    </h1>
                </div>

                <!-- Selector de Trimestre -->
                <div class="trimestre-selector">
                    <h5 class="mb-3">Seleccionar Trimestre</h5>
                    <div class="d-flex flex-wrap">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <a href="?bimestre=<?php echo $i; ?>" 
                               class="btn btn-trimestre <?php echo $bimestre_seleccionado == $i ? 'active' : ''; ?>">
                                Trimestre <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Estadísticas Generales -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card text-center">
                            <h3 class="mb-2"><?php echo $total_materias; ?></h3>
                            <p class="mb-0">Total Materias</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                            <h3 class="mb-2"><?php echo $total_cargadas; ?></h3>
                            <p class="mb-0">Materias Cargadas</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                            <h3 class="mb-2"><?php echo $total_pendientes; ?></h3>
                            <p class="mb-0">Materias Pendientes</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card text-center" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                            <h3 class="mb-2"><?php echo $porcentaje_completado; ?>%</h3>
                            <p class="mb-0">Completado</p>
                        </div>
                    </div>
                </div>

                <!-- Barra de Progreso General -->
                <div class="progress-card">
                    <h5 class="mb-3">Progreso General del Trimestre <?php echo $bimestre_seleccionado; ?></h5>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" 
                             style="width: <?php echo $porcentaje_completado; ?>%" 
                             aria-valuenow="<?php echo $porcentaje_completado; ?>" 
                             aria-valuemin="0" aria-valuemax="100">
                            <?php echo $porcentaje_completado; ?>%
                        </div>
                    </div>
                </div>

                <!-- PRIMARIA -->
                <div class="nivel-card primaria">
                    <h4 class="mb-3 text-success">
                        <span data-feather="book" class="me-2"></span>
                        PRIMARIA
                    </h4>
                    
                    <?php if (empty($primaria_stats)): ?>
                        <div class="alert alert-info">
                            No hay cursos asignados para Primaria en este trimestre.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Curso</th>
                                        <th>Total Materias</th>
                                        <th>Materias Cargadas</th>
                                        <th>Materias Pendientes</th>
                                        <th>Progreso</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($primaria_stats as $curso): ?>
                                        <?php 
                                        $porcentaje_curso = $curso['total_materias'] > 0 ? 
                                            round(($curso['materias_cargadas'] / $curso['total_materias']) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="curso-badge">
                                                    <?php echo htmlspecialchars($curso['curso'] . $curso['paralelo']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $curso['total_materias']; ?></td>
                                            <td class="estado-cargado"><?php echo $curso['materias_cargadas']; ?></td>
                                            <td class="estado-pendiente"><?php echo $curso['materias_pendientes']; ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $porcentaje_curso; ?>%" 
                                                         aria-valuenow="<?php echo $porcentaje_curso; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $porcentaje_curso; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($curso['materias_pendientes'] > 0): ?>
                                                    <button type="button" class="btn btn-info-materias" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modalPrimaria<?php echo $curso['curso'] . $curso['paralelo']; ?>">
                                                        Info
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SECUNDARIA -->
                <div class="nivel-card secundaria">
                    <h4 class="mb-3 text-info">
                        <span data-feather="layers" class="me-2"></span>
                        SECUNDARIA
                    </h4>
                    
                    <?php if (empty($secundaria_stats)): ?>
                        <div class="alert alert-info">
                            No hay cursos asignados para Secundaria en este trimestre.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Curso</th>
                                        <th>Total Materias</th>
                                        <th>Materias Cargadas</th>
                                        <th>Materias Pendientes</th>
                                        <th>Progreso</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($secundaria_stats as $curso): ?>
                                        <?php 
                                        $porcentaje_curso = $curso['total_materias'] > 0 ? 
                                            round(($curso['materias_cargadas'] / $curso['total_materias']) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="curso-badge">
                                                    <?php echo htmlspecialchars($curso['curso'] . $curso['paralelo']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $curso['total_materias']; ?></td>
                                            <td class="estado-cargado"><?php echo $curso['materias_cargadas']; ?></td>
                                            <td class="estado-pendiente"><?php echo $curso['materias_pendientes']; ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $porcentaje_curso; ?>%" 
                                                         aria-valuenow="<?php echo $porcentaje_curso; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $porcentaje_curso; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($curso['materias_pendientes'] > 0): ?>
                                                    <button type="button" class="btn btn-info-materias" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modalSecundaria<?php echo $curso['curso'] . $curso['paralelo']; ?>">
                                                        Info
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modales para Primaria -->
    <?php foreach ($primaria_stats as $curso): ?>
        <?php if ($curso['materias_pendientes'] > 0): ?>
            <!-- Filtrar materias pendientes para este curso específico -->
            <?php 
            $materias_curso = array_filter($primaria_materias_pendientes, function($materia) use ($curso) {
                return $materia['curso'] == $curso['curso'] && $materia['paralelo'] == $curso['paralelo'];
            });
            ?>
            <div class="modal fade" id="modalPrimaria<?php echo $curso['curso'] . $curso['paralelo']; ?>" tabindex="-1" aria-labelledby="modalLabel<?php echo $curso['curso'] . $curso['paralelo']; ?>" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel<?php echo $curso['curso'] . $curso['paralelo']; ?>">
                                Materias Pendientes - Primaria <?php echo $curso['curso'] . $curso['paralelo']; ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <strong>Trimestre <?php echo $bimestre_seleccionado; ?>:</strong> 
                                Faltan <?php echo $curso['materias_pendientes']; ?> materias por cargar notas.
                            </div>
                            <?php foreach ($materias_curso as $materia): ?>
                                <div class="materia-pendiente-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($materia['nombre_materia']); ?></strong>
                                        </div>
                                        <?php if ($materia['nombres'] && $materia['apellidos']): ?>
                                            <div class="profesor-info">
                                                Prof: <?php echo htmlspecialchars($materia['nombres'] . ' ' . $materia['apellidos']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Modales para Secundaria -->
    <?php foreach ($secundaria_stats as $curso): ?>
        <?php if ($curso['materias_pendientes'] > 0): ?>
            <!-- Filtrar materias pendientes para este curso específico -->
            <?php 
            $materias_curso = array_filter($secundaria_materias_pendientes, function($materia) use ($curso) {
                return $materia['curso'] == $curso['curso'] && $materia['paralelo'] == $curso['paralelo'];
            });
            ?>
            <div class="modal fade" id="modalSecundaria<?php echo $curso['curso'] . $curso['paralelo']; ?>" tabindex="-1" aria-labelledby="modalLabel<?php echo $curso['curso'] . $curso['paralelo']; ?>" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel<?php echo $curso['curso'] . $curso['paralelo']; ?>">
                                Materias Pendientes - Secundaria <?php echo $curso['curso'] . $curso['paralelo']; ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <strong>Trimestre <?php echo $bimestre_seleccionado; ?>:</strong> 
                                Faltan <?php echo $curso['materias_pendientes']; ?> materias por cargar notas.
                            </div>
                            <?php foreach ($materias_curso as $materia): ?>
                                <div class="materia-pendiente-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($materia['nombre_materia']); ?></strong>
                                        </div>
                                        <?php if ($materia['nombres'] && $materia['apellidos']): ?>
                                            <div class="profesor-info">
                                                Prof: <?php echo htmlspecialchars($materia['nombres'] . ' ' . $materia['apellidos']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script>
        // Inicializar Feather Icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // Función para fijar el sidebar
        function fixSidebar() {
            const sidebar = document.getElementById('sidebarMenu');
            const main = document.querySelector('main');
            
            if (sidebar && main) {
                // Asegurar que el sidebar esté fijo
                sidebar.style.position = 'fixed';
                sidebar.style.top = '0';
                sidebar.style.left = '0';
                sidebar.style.height = '100vh';
                sidebar.style.zIndex = '1000';
                sidebar.style.overflowY = 'auto';
                sidebar.style.overflowX = 'hidden';
                
                // Ajustar el margen del contenido principal
                const sidebarWidth = sidebar.offsetWidth;
                main.style.marginLeft = sidebarWidth + 'px';
            }
        }

        // Aplicar cuando se carga la página
        document.addEventListener('DOMContentLoaded', function() {
            fixSidebar();
            
            // Aplicar también cuando se redimensiona la ventana
            window.addEventListener('resize', fixSidebar);
        });

        // Actualizar la página cada 5 minutos para mantener la información actualizada
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutos
    </script>
</body>
</html>
