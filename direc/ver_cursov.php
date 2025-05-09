<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header('Location: ../index.php');
    exit();
}

$id_curso = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_curso <= 0) {
    header('Location: priv.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

// Obtener información del curso
$stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
$stmt_curso->execute([$id_curso]);
$curso = $stmt_curso->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
    header('Location: priv.php');
    exit();
}

// Obtener materias con jerarquía
$stmt_materias = $conn->prepare("
    SELECT 
        m.id_materia, 
        m.nombre_materia, 
        m.es_extra,
        m.materia_padre_id,
        mp.nombre_materia AS nombre_padre
    FROM cursos_materias cm
    JOIN materias m ON cm.id_materia = m.id_materia
    LEFT JOIN materias mp ON m.materia_padre_id = mp.id_materia
    WHERE cm.id_curso = ?
    ORDER BY m.materia_padre_id, m.nombre_materia
");
$stmt_materias->execute([$id_curso]);
$materias_raw = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// Organizar materias: padres e hijas
$materias_padres = [];
$materias_hijas = [];
foreach ($materias_raw as $mat) {
    if ($mat['materia_padre_id']) {
        $materias_hijas[$mat['materia_padre_id']][] = $mat;
    } else {
        $materias_padres[$mat['id_materia']] = $mat;
    }
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

// Función para obtener notas
function obtenerNotas($conn, $id_estudiante, $id_materia) {
    $notas = [];
    for ($trim = 1; $trim <= 3; $trim++) {
        $stmt = $conn->prepare("
            SELECT calificacion 
            FROM calificaciones 
            WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
        ");
        $stmt->execute([$id_estudiante, $id_materia, $trim]);
        $nota = $stmt->fetchColumn();
        $notas[$trim] = $nota !== false ? $nota : null;
    }
    return $notas;
}

// Procesar datos por estudiante
$datos = [];
foreach ($estudiantes as $est) {
    $fila = [
        'estudiante' => $est,
        'materias' => []
    ];
    foreach ($materias_padres as $id_padre => $padre) {
        // Notas de hijas
        $hijas = $materias_hijas[$id_padre] ?? [];
        $notas_hijas = [];
        foreach ($hijas as $hija) {
            $notas_hijas[$hija['id_materia']] = obtenerNotas($conn, $est['id_estudiante'], $hija['id_materia']);
        }
        // Promedio padre por trimestre
        $promedios_padre = [];
        for ($trim = 1; $trim <= 3; $trim++) {
            $notas_trim = [];
            foreach ($hijas as $hija) {
                $nota = $notas_hijas[$hija['id_materia']][$trim] ?? null;
                if ($nota !== null && $nota !== '') $notas_trim[] = $nota;
            }
            $promedios_padre[$trim] = count($notas_trim) ? round(array_sum($notas_trim) / count($notas_trim), 2) : null;
        }
        $fila['materias'][$id_padre] = [
            'tipo' => 'padre',
            'datos' => $padre,
            'notas' => $promedios_padre
        ];
        // Agregar hijas
        foreach ($hijas as $hija) {
            $fila['materias'][$hija['id_materia']] = [
                'tipo' => 'hija',
                'datos' => $hija,
                'notas' => $notas_hijas[$hija['id_materia']]
            ];
        }
    }
    // Materias sin hijas (padres "solos")
    foreach ($materias_padres as $id_padre => $padre) {
        if (empty($materias_hijas[$id_padre])) {
            $notas = obtenerNotas($conn, $est['id_estudiante'], $id_padre);
            $fila['materias'][$id_padre] = [
                'tipo' => 'padre',
                'datos' => $padre,
                'notas' => $notas
            ];
        }
    }
    $datos[] = $fila;
}

// Para el header: organizar orden de columnas
$columnas = [];
foreach ($materias_padres as $id_padre => $padre) {
    $columnas[] = [
        'tipo' => 'padre',
        'datos' => $padre
    ];
    foreach ($materias_hijas[$id_padre] ?? [] as $hija) {
        $columnas[] = [
            'tipo' => 'hija',
            'datos' => $hija
        ];
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
        .main-content { margin-left: 250px; padding: 24px 10px; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: #212c3a; color: #fff; z-index: 1000; }
        .table-centralizador th, .table-centralizador td { 
            text-align: center; 
            vertical-align: middle; 
            font-size: 0.9rem; 
            padding: 3px 5px;
            min-width: 60px;
        }
        .materia-padre { background-color: #e9f5ff; font-weight: 600; }
        .materia-hija { background-color: #f8f9fa; font-style: italic; }
        .nota-baja { color: #dc3545 !important; font-weight: 700 !important; }
        .btn-volver { min-width: 110px; }
        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 8px 2px; }
            .sidebar { position: static; width: 100%; height: auto; }
        }
    </style>
</head>
<body>
    <div class="sidebar d-flex flex-column">
        <?php include '../includes/sidebar.php'; ?>
    </div>
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Centralizador: <?= htmlspecialchars($curso['nivel'] . ' ' . $curso['curso'] . ' "' . $curso['paralelo'] . '"') ?></h2>
            <a href="<?= $curso['nivel'] == 'Primaria' ? 'priv.php' : 'secv.php' ?>" class="btn btn-outline-secondary btn-volver">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
        <div class="table-responsive" style="max-height: 80vh;">
            <table class="table table-bordered table-centralizador">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 30px;">#</th>
                        <th rowspan="2" style="min-width: 180px;">Estudiante</th>
                        <?php foreach ($columnas as $col): ?>
                            <th colspan="3" class="<?= $col['tipo'] == 'padre' ? 'materia-padre' : 'materia-hija' ?>">
                                <?= htmlspecialchars($col['datos']['nombre_materia']) ?>
                                <?php if ($col['tipo'] == 'padre' && !empty($materias_hijas[$col['datos']['id_materia']])): ?>
                                    <div class="small text-muted">(Promedio)</div>
                                <?php endif; ?>
                                <?php if ($col['tipo'] == 'hija'): ?>
                                    <div class="small text-muted">(Hija)</div>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($columnas as $col): ?>
                            <th>T1</th>
                            <th>T2</th>
                            <th>T3</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $contador = 1; ?>
                    <?php foreach ($datos as $fila): ?>
                        <tr>
                            <td><?= $contador++ ?></td>
                            <td class="text-start">
                                <?= htmlspecialchars(strtoupper(
                                    $fila['estudiante']['apellido_paterno'] . ' ' . 
                                    $fila['estudiante']['apellido_materno'] . ', ' . 
                                    $fila['estudiante']['nombres']
                                )) ?>
                            </td>
                            <?php foreach ($columnas as $col): ?>
                                <?php 
                                $materia_id = $col['datos']['id_materia'];
                                $tipo = $col['tipo'];
                                $notas = $fila['materias'][$materia_id]['notas'] ?? [null, null, null, null];
                                for ($trim = 1; $trim <= 3; $trim++):
                                    $nota = $notas[$trim] ?? null;
                                    $clase = (is_numeric($nota) && $nota !== '' && $nota < 51) ? 'nota-baja' : '';
                                ?>
                                    <td class="<?= $clase ?>"><?= ($nota !== null && $nota !== '') ? $nota : '' ?></td>
                                <?php endfor; ?>
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
