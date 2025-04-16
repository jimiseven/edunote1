<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();
$id_curso = 1; // Ajusta si corresponde

// Info del curso
$stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
$stmt_curso->execute([$id_curso]);
$curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);
$nombre_curso = $curso_info['nivel'] . ' ' . $curso_info['curso'] . ' "' . $curso_info['paralelo'] . '"';

// Todas las materias y abreviaturas
$abreviaturas = [
    'Biología' => 'BIO',
    'Física' => 'FIS',
    'Química' => 'QUI',
    'Ciencias Naturales' => 'C.NAT',
    // Añade más si tienes otras materias con nombres largos
];

// Obtener todas las materias
$stmt_materias = $conn->prepare("
    SELECT 
        m.id_materia, 
        m.nombre_materia,
        m.materia_padre_id,
        (SELECT COUNT(*) FROM materias WHERE materia_padre_id = m.id_materia) AS tiene_hijas
    FROM cursos_materias cm
    JOIN materias m ON cm.id_materia = m.id_materia
    WHERE cm.id_curso = ?
    ORDER BY m.materia_padre_id, m.id_materia
");
$stmt_materias->execute([$id_curso]);
$todas_materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// Construir estructura: padres e hijas
$materias_padre = [];
$materias_hijas = [];
$materias_individuales = [];
foreach ($todas_materias as $mat) {
    if ($mat['materia_padre_id'] === null && $mat['tiene_hijas'] > 0) {
        $materias_padre[$mat['id_materia']] = [
            'nombre' => $mat['nombre_materia'],
            'abreviatura' => $abreviaturas[$mat['nombre_materia']] ?? $mat['nombre_materia'],
            'hijas' => []
        ];
    } elseif ($mat['materia_padre_id']) {
        $materias_hijas[$mat['materia_padre_id']][] = [
            'id_materia' => $mat['id_materia'],
            'nombre_materia' => $mat['nombre_materia'],
            'abreviatura' => $abreviaturas[$mat['nombre_materia']] ?? $mat['nombre_materia']
        ];
    } elseif ($mat['materia_padre_id'] === null && $mat['tiene_hijas'] == 0) {
        $materias_individuales[] = [
            'id_materia' => $mat['id_materia'],
            'nombre_materia' => $mat['nombre_materia'],
            'abreviatura' => $abreviaturas[$mat['nombre_materia']] ?? $mat['nombre_materia']
        ];
    }
}
// Asignar hijas a su padre
foreach ($materias_padre as $padre_id => &$padre) {
    $padre['hijas'] = $materias_hijas[$padre_id] ?? [];
}
unset($padre);

// Estudiantes
$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, apellido_paterno, apellido_materno, nombres 
    FROM estudiantes 
    WHERE id_curso = ?
    ORDER BY apellido_paterno, apellido_materno, nombres
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// Calificaciones
$calificaciones = [];
foreach ($estudiantes as $est) {
    foreach ($todas_materias as $mat) {
        $stmt = $conn->prepare("
            SELECT bimestre, calificacion 
            FROM calificaciones 
            WHERE id_estudiante = ? AND id_materia = ?
        ");
        $stmt->execute([$est['id_estudiante'], $mat['id_materia']]);
        $notas = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        for ($bim = 1; $bim <= 3; $bim++) {
            $calificaciones[$est['id_estudiante']][$mat['id_materia']][$bim] = $notas[$bim] ?? '-';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Horizontal Primaria 1A</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        .table-horz {
            font-size: 0.92rem;
            border: 2px solid #dee2e6;
            min-width: 1300px;
        }
        .table-horz th, .table-horz td { text-align: center; vertical-align: middle;}
        .mat-hija { background: #f2f6fa; }
        .mat-padre { background: #ddebf7; font-weight: 700; }
        .prom-final { background: #cfe2ff; font-weight: 700; }
        .mat-indiv { background: #e5fbe5; font-weight: 700; }
        .student-cell { text-align: left; min-width: 170px; font-weight: 500;}
        @media print {
            .no-print { display: none !important; }
            body { padding: 15px !important;}
            .table-horz th, .table-horz td { font-size: 0.87rem; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="text-primary"><?= htmlspecialchars($nombre_curso) ?></h2>
        <button onclick="window.print()" class="btn btn-primary no-print">
            <i class="ri-printer-line"></i> Imprimir
        </button>
    </div>
    <!-- Tabla horizontal combinada -->
    <div class="table-responsive">
        <table class="table table-bordered table-horz">
            <thead>
            <tr>
                <th rowspan="2" class="align-middle">#</th>
                <th rowspan="2" class="align-middle">Estudiante</th>
                <!-- Materias padre + hijas -->
                <?php foreach ($materias_padre as $padre): ?>
                    <th colspan="<?= count($padre['hijas']) ?>" class="mat-padre"><?= $padre['abreviatura'] ?></th>
                    <th rowspan="2" class="prom-final" title="Promedio de <?= $padre['abreviatura'] ?>">P-<?= $padre['abreviatura'] ?></th>
                <?php endforeach; ?>
                <!-- Materias individuales -->
                <?php foreach ($materias_individuales as $mat): ?>
                    <th rowspan="2" class="mat-indiv"><?= $mat['abreviatura'] ?></th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach ($materias_padre as $padre): ?>
                    <?php foreach ($padre['hijas'] as $hija): ?>
                        <th class="mat-hija"><?= $hija['abreviatura'] ?></th>
                    <?php endforeach; ?>
                    <!-- Después del foreach, el promedio ya está en la fila 1 (por rowspan) -->
                <?php endforeach; ?>
                <!-- Materias individuales no necesitan columna hija en la fila 2 -->
            </tr>
            </thead>
            <tbody>
            <?php $i = 0; foreach ($estudiantes as $est): $i++; ?>
                <tr>
                    <td><?= $i ?></td>
                    <td class="student-cell"><?= 
                        htmlspecialchars($est['apellido_paterno'].' '.$est['apellido_materno'].', '.$est['nombres']); ?></td>
                    <!-- Notas de materias compuestas -->
                    <?php foreach ($materias_padre as $padre_id => $padre): 
                        $sum_p = 0; $cant_p = 0;
                        foreach ($padre['hijas'] as $hija):
                            $nota = $calificaciones[$est['id_estudiante']][$hija['id_materia']][1] ?? '-';
                            if (is_numeric($nota)) { $sum_p += $nota; $cant_p++; }
                    ?>
                            <td class="mat-hija"><?= $nota ?></td>
                    <?php endforeach; ?>
                        <td class="prom-final"><?= ($cant_p>0) ? number_format($sum_p/$cant_p,2) : '-' ?></td>
                    <?php endforeach; ?>
                    <!-- Materias individuales -->
                    <?php foreach ($materias_individuales as $mat): ?>
                        <td class="mat-indiv">
                            <?= $calificaciones[$est['id_estudiante']][$mat['id_materia']][1] ?? '-' ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
