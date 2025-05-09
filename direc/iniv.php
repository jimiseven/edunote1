<?php
session_start();
require_once '../config/database.php';

// SOLO permitir acceso al rol 3 (Directora_SV)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

// Obtener cursos de nivel inicial
$stmt = $conn->query("SELECT c.id_curso, c.curso, c.paralelo FROM cursos c WHERE c.nivel = 'Inicial' ORDER BY c.curso, c.paralelo");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Centralizadores Inicial</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f8f9fa; }
        .main-content { margin-left: 250px; padding: 32px 24px; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: #212c3a; color: #fff; z-index: 1000; }
        .table-centralizador th, .table-centralizador td { text-align: center; vertical-align: middle; font-size: 1.04rem; }
        .table-centralizador th { background: #eaf5ed; color: #3a5e3a; }
        .btn-centralizador { min-width: 110px; }
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
        <div class="bg-white rounded shadow-sm p-4 mb-4">
            <h2 class="mb-0" style="color:#7bb27d;">Centralizadores de Nivel Inicial</h2>
            <small class="text-muted">Seleccione el curso que desea visualizar:</small>
        </div>
        <div class="table-responsive">
            <table class="table table-centralizador align-middle">
                <thead>
                    <tr>
                        <th style="width:90px;">#</th>
                        <th>Curso</th>
                        <th>Centralizador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($cursos) > 0): ?>
                        <?php $contador = 1; ?>
                        <?php foreach ($cursos as $curso): ?>
                            <tr>
                                <td><?= $contador++ ?></td>
                                <td><?= htmlspecialchars($curso['curso']) ?> "<?= htmlspecialchars($curso['paralelo']) ?>"</td>
                                <td>
                                    <a href="ver_cen_ini.php?id=<?= $curso['id_curso'] ?>" class="btn btn-success btn-sm btn-centralizador">
                                        <i class="bi bi-eye"></i> Ver Centralizador
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">No hay cursos de nivel inicial registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
