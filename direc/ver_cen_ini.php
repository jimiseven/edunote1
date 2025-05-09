<?php
session_start();
require_once '../config/database.php';

// SOLO permitir acceso al rol 3 (Directora_SV)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header('Location: ../index.php');
    exit();
}

// Obtener ID del curso actual
$id_curso = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_curso <= 0) {
    header('Location: iniv.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

// Obtener lista de cursos de Inicial ordenados
$stmt_cursos = $conn->query("SELECT id_curso, curso, paralelo FROM cursos WHERE nivel = 'Inicial' ORDER BY curso, paralelo");
$cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);

// Encontrar el índice del curso actual y los ids de anterior y siguiente
$curso_ids = array_column($cursos, 'id_curso');
$index_actual = array_search($id_curso, $curso_ids);

$id_anterior = $id_siguiente = null;
if ($index_actual !== false) {
    if ($index_actual > 0) {
        $id_anterior = $curso_ids[$index_actual - 1];
    }
    if ($index_actual < count($curso_ids) - 1) {
        $id_siguiente = $curso_ids[$index_actual + 1];
    }
}

// Obtener información del curso actual
$stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ? AND nivel = 'Inicial'");
$stmt_curso->execute([$id_curso]);
$curso = $stmt_curso->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
    header('Location: iniv.php');
    exit();
}

// Obtener estudiantes
$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, nombres, apellido_paterno, apellido_materno
    FROM estudiantes 
    WHERE id_curso = ? 
    ORDER BY apellido_paterno, apellido_materno, nombres
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// Obtener materias
$stmt_materias = $conn->prepare("
    SELECT m.id_materia, m.nombre_materia
    FROM cursos_materias cm
    JOIN materias m ON cm.id_materia = m.id_materia
    WHERE cm.id_curso = ?
");
$stmt_materias->execute([$id_curso]);
$materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// Obtener comentarios para los trimestres
$comentarios = [];
foreach ($estudiantes as $est) {
    foreach ($materias as $mat) {
        for ($trim=1; $trim<=3; $trim++) {
            $stmt = $conn->prepare("
                SELECT comentario 
                FROM calificaciones 
                WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
            ");
            $stmt->execute([$est['id_estudiante'], $mat['id_materia'], $trim]);
            $comentarios[$est['id_estudiante']][$mat['id_materia']][$trim] = $stmt->fetchColumn() ?: '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Centralizador: <?= htmlspecialchars($curso['nivel'] . ' ' . $curso['curso'] . ' "' . $curso['paralelo'] . '"') ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f8f9fa; }
        .main-content { margin-left: 250px; padding: 32px 24px; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: #212c3a; color: #fff; z-index: 1000; }
        .table-centralizador th, .table-centralizador td { 
            text-align: center; 
            vertical-align: middle; 
            font-size: 0.9rem; 
            padding: 8px;
        }
        .table-centralizador thead th { 
            background-color: #eaf5ed !important; 
            color: #3a5e3a;
            position: sticky;
            top: 0;
        }
        .table-centralizador tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .nav-cursos {
            gap: 8px;
        }
        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 18px 4px; }
            .sidebar { position: static; width: 100%; height: auto; }
            .d-flex.justify-content-between { flex-direction: column; gap: 1rem; }
            .nav-cursos { justify-content: flex-start !important; }
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
            <a href="iniv.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-bar-left"></i> Volver
            </a>
            <div class="flex-grow-1 text-center">
                <h2 class="mb-0">Centralizador: <?= htmlspecialchars($curso['nivel'] . ' ' . $curso['curso'] . ' "' . $curso['paralelo'] . '"') ?></h2>
            </div>
            <div class="d-flex nav-cursos justify-content-end">
                <?php if ($id_anterior): ?>
                    <a href="ver_cen_ini.php?id=<?= $id_anterior ?>" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left"></i> Anterior
                    </a>
                <?php endif; ?>
                <?php if ($id_siguiente): ?>
                    <a href="ver_cen_ini.php?id=<?= $id_siguiente ?>" class="btn btn-outline-primary">
                        Siguiente <i class="bi bi-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="table-responsive" style="max-height: 80vh;">
            <table class="table table-bordered table-centralizador">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th style="width: 280px;">Estudiante</th>
                        <?php foreach ($materias as $materia): ?>
                            <th colspan="3"><?= htmlspecialchars($materia['nombre_materia']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th></th>
                        <th></th>
                        <?php foreach ($materias as $materia): ?>
                            <th>Trim 1</th>
                            <th>Trim 2</th>
                            <th>Trim 3</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $contador = 1; ?>
                    <?php foreach ($estudiantes as $estudiante): ?>
                        <tr>
                            <td><?= $contador++ ?></td>
                            <td class="text-start">
                                <?= htmlspecialchars(strtoupper($estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno'] . ', ' . $estudiante['nombres'])) ?>
                            </td>
                            <?php foreach ($materias as $materia): ?>
                                <td><?= htmlspecialchars($comentarios[$estudiante['id_estudiante']][$materia['id_materia']][1] ?? '') ?></td>
                                <td><?= htmlspecialchars($comentarios[$estudiante['id_estudiante']][$materia['id_materia']][2] ?? '') ?></td>
                                <td><?= htmlspecialchars($comentarios[$estudiante['id_estudiante']][$materia['id_materia']][3] ?? '') ?></td>
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
