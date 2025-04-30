<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Forzar activación del sidebar para asignación de primaria
$_SESSION['force_active'] = 'asig_pri';

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
    WHERE c.nivel = 'Primaria'
    ORDER BY c.curso, c.paralelo, m.nombre_materia";

$stmt = $conn->query($reporte_query);
$reporte_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar datos y contar materias
$cursos_agrupados = [];
$total_materias = 0;

foreach ($reporte_data as $row) {
    $key = "{$row['curso']}° - Paralelo {$row['paralelo']}";
    if (!isset($cursos_agrupados[$key])) {
        $cursos_agrupados[$key] = [
            'materias' => [],
            'count' => 0
        ];
    }
    $cursos_agrupados[$key]['materias'][] = $row;
    $cursos_agrupados[$key]['count']++;
    $total_materias++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Asignaciones - Primaria</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --color-primario: #2c3e50;
            --color-secundario: #34495e;
            --color-fondo: #f8f9fa;
        }

        /* Diseño principal */
        body {
            display: flex;
            min-height: 100vh;
            background: var(--color-fondo);
            font-family: 'Segoe UI', system-ui;
            margin: 0;
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: #212c3a;
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            overflow-y: auto;
        }

        /* Estilo mejorado para cuadros uniformes */
        .print-container {
            width: 100%;
            padding: 0 0.5rem;
        }

        .print-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: space-between;
        }

        .print-curso {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            width: calc(25% - 0.75rem);
            min-width: 0;
            height: 280px;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
            overflow: hidden;
        }

        @media (max-width: 1200px) {
            .print-curso {
                width: calc(33.33% - 0.75rem);
            }
        }

        @media (max-width: 992px) {
            .print-curso {
                width: calc(50% - 0.75rem);
            }
        }

        @media (max-width: 768px) {
            .print-curso {
                width: 100%;
            }
        }

        @media print {
            .print-curso {
                width: calc(25% - 0.5cm);
                height: auto;
                min-height: 5.5cm;
            }
        }

        .print-header {
            background: var(--color-primario);
            color: white;
            padding: 0.4rem 0.6rem;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
        }

        .print-body {
            padding: 0.4rem;
            flex-grow: 1;
            overflow: hidden;
            font-size: 0.8rem;
        }

        .print-row {
            display: flex;
            justify-content: space-between;
            padding: 0.2rem 0;
            border-bottom: 1px dotted #eee;
        }

        .print-row:last-child {
            border-bottom: none;
        }

        .print-materia {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .print-profesor {
            color: var(--color-primario);
            margin-left: 0.5rem;
            text-align: right;
            min-width: 40%;
        }

        .sin-asignar {
            color: #e74c3c;
            font-style: italic;
        }

        /* Barra de acciones */
        .actions-bar {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Estilos para impresión */
        @media print {
            body {
                margin: 0 !important;
                padding: 0.5cm !important;
            }

            .sidebar, .no-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .report-grid {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 0.5cm !important;
            }

            .report-card {
                border: 1pt solid #ddd !important;
                page-break-inside: avoid;
                box-shadow: none !important;
            }

            .card-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                padding: 0.5cm !important;
            }

            .materia-item {
                padding: 0.3cm 0 !important;
                font-size: 9pt !important;
            }
        }

        @media (max-width: 900px) {
            body {
                flex-direction: column;
            }

            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>

    <!-- Main content -->
    <div class="main-content">
        <div class="actions-bar no-print">
            <div>
                <h1 class="mb-0" style="color: var(--color-primario);">
                    <i class="bi bi-file-earmark-text"></i> Reporte de Asignaciones
                </h1>
                <small class="text-muted">Nivel Primaria - <?= date('d/m/Y') ?></small>
            </div>
            <div class="d-flex gap-2">
                <a href="asig_pri.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
        </div>

        <!-- Contenido del reporte optimizado -->
        <div class="print-container">
            <div class="print-grid">
            <?php foreach ($cursos_agrupados as $curso => $data): ?>
                <div class="print-curso">
                    <div class="print-header">
                        <span><?= $curso ?></span>
                        <span><?= $data['count'] ?> mat.</span>
                    </div>
                    <div class="print-body">
                        <?php foreach ($data['materias'] as $materia): ?>
                            <div class="print-row">
                                <span class="print-materia"><?= htmlspecialchars($materia['nombre_materia']) ?></span>
                                <span class="print-profesor">
                                    <?= $materia['profesor_nombre'] ?
                                        htmlspecialchars($materia['profesor_nombre']) :
                                        '<span class="sin-asignar">S/A</span>' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Resumen general -->
            <div class="resumen-general no-print mt-4 p-3 bg-white rounded shadow-sm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="fw-bold text-primary">Total de cursos:</div>
                        <div class="h2"><?= count($cursos_agrupados) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-bold text-primary">Total de materias:</div>
                        <div class="h2"><?= $total_materias ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
