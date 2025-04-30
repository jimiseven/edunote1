<?php
session_start();
require_once '../config/database.php';

// Verificar acceso
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Obtener datos para el reporte
$database = new Database();
$conn = $database->connect();

$reporte_query = "
    SELECT 
        c.curso,
        c.paralelo,
        m.nombre_materia,
        CONCAT(p.nombres, ' ', p.apellidos) AS profesor_nombre
    FROM cursos c
    JOIN cursos_materias cm ON c.id_curso = cm.id_curso
    JOIN materias m ON cm.id_materia = m.id_materia
    LEFT JOIN profesores_materias_cursos pmc ON cm.id_curso_materia = pmc.id_curso_materia
    LEFT JOIN personal p ON pmc.id_personal = p.id_personal
    WHERE c.nivel = 'Inicial'
    ORDER BY c.curso, c.paralelo, m.nombre_materia";

$stmt = $conn->query($reporte_query);
$reporte_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por curso y paralelo
$cursos_agrupados = [];
foreach ($reporte_data as $row) {
    $key = "Kínder {$row['curso']} \"{$row['paralelo']}\"";
    if (!isset($cursos_agrupados[$key])) {
        $cursos_agrupados[$key] = [];
    }
    $cursos_agrupados[$key][] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Asignaciones - Nivel Inicial</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: 'Segoe UI', system-ui, -apple-system;
        }
        .main-content { 
            margin-left: 250px; 
            padding: 32px 24px; 
        }
        .sidebar { 
            position: fixed; 
            left: 0; 
            top: 0; 
            width: 250px; 
            height: 100vh; 
            background: #212c3a; 
            color: #fff; 
            z-index: 1000; 
        }
        
        /* Diseño mejorado */
        .report-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 20px;
        }
        .report-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: transform 0.2s;
        }
        .report-card:hover {
            transform: translateY(-2px);
        }
        .report-header {
            background: linear-gradient(135deg, #5177b8 0%, #3a5f9a 100%);
            color: white;
            padding: 12px 15px;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .report-body {
            padding: 12px 15px;
        }
        .materia-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
        }
        .materia-row:last-child {
            border-bottom: none;
        }
        .materia-nombre {
            color: #444;
            width: 60%;
        }
        .profesor-nombre {
            color: #222;
            width: 40%;
            text-align: right;
            font-weight: 500;
        }
        .sin-asignar {
            color: #dc3545;
            font-style: italic;
            font-size: 0.85rem;
        }
        
        /* Barra de acciones mejorada */
        .actions-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-accion {
            padding: 8px 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-accion:hover {
            transform: translateY(-1px);
        }
        
        /* Estilos para impresión */
        @media print {
            body { 
                background: white;
                margin: 0;
                padding: 5mm;
                font-size: 10pt;
            }
            .sidebar, .actions-bar {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .report-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 5mm;
            }
            .report-card {
                box-shadow: none;
                border: 1px solid #ddd;
                page-break-inside: avoid;
            }
            .report-header {
                background: #5177b8 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                padding: 3mm;
                font-size: 10pt;
            }
            .materia-row {
                padding: 2mm 0;
                font-size: 9pt;
            }
            .materia-nombre {
                width: 55%;
            }
            .profesor-nombre {
                width: 45%;
            }
        }
        
        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 18px 4px; }
            .sidebar { position: static; width: 100%; height: auto; }
            .report-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column">
        <?php include '../includes/sidebar.php'; ?>
    </div>

    <!-- Main content -->
    <div class="main-content">
        <div class="actions-bar">
            <div>
                <h4 class="mb-1">Reporte de Asignaciones</h4>
                <small class="text-muted">Nivel Inicial - Visualización compacta</small>
            </div>
            <div class="d-flex gap-2">
                <a href="asig_ini.php" class="btn-accion btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <button class="btn-accion btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
        </div>

        <!-- Contenido del reporte -->
        <div id="reporte-container">
            <div class="d-none d-print-block text-center mb-4">
                <h5 class="mb-1">EDUNOTE - REPORTE OFICIAL</h5>
                <p class="small mb-0 text-muted">Generado el <?= date('d/m/Y H:i') ?></p>
            </div>
            
            <div class="report-grid">
                <?php foreach ($cursos_agrupados as $nombre_curso => $materias): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <?= $nombre_curso ?>
                        </div>
                        <div class="report-body">
                            <?php foreach ($materias as $materia): ?>
                                <div class="materia-row">
                                    <div class="materia-nombre">
                                        <?= htmlspecialchars($materia['nombre_materia']) ?>
                                    </div>
                                    <div class="profesor-nombre">
                                        <?php if (!empty($materia['profesor_nombre'])): ?>
                                            <?= htmlspecialchars($materia['profesor_nombre']) ?>
                                        <?php else: ?>
                                            <span class="sin-asignar">Sin asignar</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4 d-print-none">
                <small class="text-muted">Total de cursos: <?= count($cursos_agrupados) ?></small>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
