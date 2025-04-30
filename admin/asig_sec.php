<?php
session_start();
require_once '../config/database.php';

// Verificar acceso
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Cursos de secundaria (1 a 6), paralelos A y B
$cursos_secundaria = [];
for ($i = 1; $i <= 6; $i++) {
    $cursos_secundaria[] = [
        'curso' => $i,
        'paralelos' => [
            ['paralelo' => 'A'],
            ['paralelo' => 'B'],
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignación de Profesores - Secundaria</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f5f6fa; }
        .main-content { margin-left: 250px; padding: 32px 24px; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: #212c3a; color: #fff; z-index: 1000; }
        .sidebar a, .sidebar .nav-link { color: #b8c7ce; }
        .sidebar .nav-link.active, .sidebar .nav-link:focus, .sidebar .nav-link:hover { color: #fff; background: #1b2430; }
        .sidebar-section-title { font-size: 0.95rem; font-weight: 600; margin: 24px 0 8px 18px; color: #8cb4e0; letter-spacing: 1px; }
        .table-asig th, .table-asig td { text-align: center; vertical-align: middle; font-size: 1.04rem; }
        .table-asig th { background: #eaf5ed; color: #3a5e3a; }
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="bg-white rounded shadow-sm p-4">
                <h2 class="mb-0" style="color:#7bb27d;">Asignación de Profesores - Secundaria</h2>
                <small class="text-muted">Seleccione el paralelo del curso que desea ver:</small>
            </div>
            <!-- Botón de reporte -->
            <a href="reporte_secundaria.php" class="btn btn-info btn-sm">
                <i class="bi bi-printer"></i> Ver Reporte
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-asig align-middle">
                <thead>
                    <tr>
                        <th style="width:90px;">Curso</th>
                        <th>Paralelo A</th>
                        <th>Paralelo B</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cursos_secundaria as $curso): ?>
                        <tr>
                            <td class="fw-bold"><?= $curso['curso'] ?></td>
                            <?php foreach ($curso['paralelos'] as $paralelo): ?>
                                <td>
                                    <div class="d-flex flex-column align-items-center gap-2">
                                        <span class="fw-semibold fs-5"><?= $paralelo['paralelo'] ?></span>
                                        <a href="ver_asig.php?nivel=secundaria&curso=<?= $curso['curso'] ?>&paralelo=<?= $paralelo['paralelo'] ?>" class="btn btn-success btn-sm btn-asig">
                                            <i class="bi bi-eye"></i> Ver Asignación
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
