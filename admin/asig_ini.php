<?php
session_start();
require_once '../config/database.php';

// Verificar acceso
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Solo mostramos Kinder 1 y 2 con paralelos A y B
$cursos_inicial = [
    [
        'curso' => 1,
        'paralelos' => [
            ['paralelo' => 'A'],
            ['paralelo' => 'B'],
        ]
    ],
    [
        'curso' => 2,
        'paralelos' => [
            ['paralelo' => 'A'],
            ['paralelo' => 'B'],
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignación de Profesores - Inicial</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f5f6fa; }
        .main-content { margin-left: 250px; padding: 32px 24px; }
        .sidebar { 
            position: fixed; left: 0; top: 0; 
            width: 250px; height: 100vh; 
            background: #212c3a; color: #fff; 
            z-index: 1000; 
        }
        .table-asig th { background: #e6f0fa; color: #335177; }
        .btn-asig { min-width: 110px; }
        
        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 18px 4px; }
            .sidebar { position: static; width: 100%; height: auto; }
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
        <div class="d-flex justify-content-between align-items-center">
            <div class="bg-white rounded shadow-sm p-4 mb-4">
                <h2 class="mb-0" style="color:#5177b8;">
                    <i class="bi bi-person-gear"></i> Asignaciones - Nivel Inicial
                </h2>
                <small class="text-muted">Gestión de asignaciones por paralelo</small>
            </div>
            
            <!-- Botón de reporte (NUEVO) -->
            <a href="reporte_inicial.php" class="btn btn-info btn-sm mb-4">
                <i class="bi bi-printer"></i> Ver Reporte
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-asig align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:90px;">Curso</th>
                        <th>Paralelo A</th>
                        <th>Paralelo B</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cursos_inicial as $curso): ?>
                        <tr>
                            <td class="fw-bold">Kínder <?= $curso['curso'] ?></td>
                            <?php foreach ($curso['paralelos'] as $paralelo): ?>
                                <td>
                                    <div class="d-flex flex-column align-items-center gap-2">
                                        <span class="fw-semibold fs-5"><?= $paralelo['paralelo'] ?></span>
                                        <a href="ver_asig.php?nivel=inicial&curso=<?= $curso['curso'] ?>&paralelo=<?= $paralelo['paralelo'] ?>" 
                                           class="btn btn-primary btn-sm btn-asig">
                                            <i class="bi bi-people-fill"></i> Gestionar
                                        </a>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
