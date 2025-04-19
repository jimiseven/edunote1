<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2])) {
    header('Location: ../index.php');
    exit();
}

// Obtener ID del curso
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: dashboard.php?error=curso_no_especificado');
    exit();
}

$id_curso = intval($_GET['id']);
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'anual';
$trimestre = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : 1;

$database = new Database();
$conn = $database->connect();

$stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
$stmt_curso->execute([$id_curso]);

if ($stmt_curso->rowCount() == 0) {
    header('Location: dashboard.php?error=curso_no_encontrado');
    exit();
}
$curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);
$nombre_curso = "{$curso_info['nivel']} {$curso_info['curso']} \"{$curso_info['paralelo']}\"";

$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, apellido_paterno, apellido_materno, nombres 
    FROM estudiantes 
    WHERE id_curso = ? 
    ORDER BY apellido_paterno, apellido_materno, nombres
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

$stmt_materias = $conn->prepare("
    SELECT m.id_materia, m.nombre_materia, m.es_extra, m.es_submateria, m.materia_padre_id
    FROM cursos_materias cm 
    JOIN materias m ON cm.id_materia = m.id_materia 
    WHERE cm.id_curso = ? 
    ORDER BY m.nombre_materia
");
$stmt_materias->execute([$id_curso]);
$todas_materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// Reorganiza materias: padres, extras, luego hijas
$materias_padre = [];
$materias_extra = [];
$materias_hijas = [];
foreach ($todas_materias as $materia) {
    if ($materia['es_extra'] == 1) {
        $materias_extra[] = $materia;
    } elseif ($materia['es_submateria'] == 0) {
        $materia['hijas'] = [];
        $materias_padre[$materia['id_materia']] = $materia;
    } else {
        $materias_hijas[] = $materia;
    }
}
foreach ($materias_hijas as $hija) {
    if (isset($materias_padre[$hija['materia_padre_id']])) {
        $materias_padre[$hija['materia_padre_id']]['hijas'][] = $hija;
    }
}
$materias = array_merge(array_values($materias_padre), $materias_extra, $materias_hijas);

// Calificaciones
$calificaciones = [];
foreach ($estudiantes as $estudiante) {
    foreach ($todas_materias as $materia) {
        for ($i = 1; $i <= 3; $i++) {
            $stmt = $conn->prepare("
                SELECT calificacion 
                FROM calificaciones 
                WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
            ");
            $stmt->execute([$estudiante['id_estudiante'], $materia['id_materia'], $i]);
            $nota = $stmt->fetchColumn();
            $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$i] = $nota !== false ? $nota : '';
        }
    }
}
// NOTA AUTOMÁTICA para materias padre (promedio de hijas)
foreach ($estudiantes as $estudiante) {
    foreach ($materias_padre as $padre) {
        if (!empty($padre['hijas'])) {
            for ($t = 1; $t <= 3; $t++) {
                $suma = 0;
                $contador = 0;
                foreach ($padre['hijas'] as $hija) {
                    $nota_hija = $calificaciones[$estudiante['id_estudiante']][$hija['id_materia']][$t] ?? '';
                    if ($nota_hija !== '') {
                        $suma += floatval($nota_hija);
                        $contador++;
                    }
                }
                if ($contador > 0) {
                    $calificaciones[$estudiante['id_estudiante']][$padre['id_materia']][$t] = number_format($suma / $contador, 2);
                }
            }
        }
    }
}

// PROMEDIOS
$promedios_materias = [];
foreach ($estudiantes as $estudiante) {
    foreach ($todas_materias as $materia) {
        $notas = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']] ?? [];
        $notas_validas = array_filter($notas, function ($v) {
            return $v !== '' && $v !== null;
        });
        $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] =
            (count($notas_validas) > 0) ? number_format(array_sum($notas_validas) / count($notas_validas), 2) : '';
    }
}

// PROMEDIO GENERAL: Solo materias padre
$promedios_generales = [];
$promedios_trimestre = [];
foreach ($estudiantes as $estudiante) {
    $suma_promedios = 0;
    $contador = 0;
    foreach ($todas_materias as $materia) {
        if ($materia['es_extra'] == 1 || $materia['es_submateria'] == 1) continue;
        $promedio = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
        if ($promedio !== '') {
            $suma_promedios += floatval($promedio);
            $contador++;
        }
    }
    $promedios_generales[$estudiante['id_estudiante']] = ($contador > 0)
        ? number_format($suma_promedios / $contador, 2) : '-';

    $suma_trimestre = 0;
    $contador_trimestre = 0;
    foreach ($todas_materias as $materia) {
        if ($materia['es_extra'] == 1 || $materia['es_submateria'] == 1) continue;
        $nota = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$trimestre] ?? '';
        if ($nota !== '') {
            $suma_trimestre += floatval($nota);
            $contador_trimestre++;
        }
    }
    $promedios_trimestre[$estudiante['id_estudiante']] = ($contador_trimestre > 0)
        ? number_format($suma_trimestre / $contador_trimestre, 2) : '-';
}
// Posiciones
$promedios_ordenados = $promedios_generales;
arsort($promedios_ordenados);
$posiciones = [];
$pos_actual = 1;
$prom_anterior = null;
foreach ($promedios_ordenados as $id_est => $prom) {
    if ($prom_anterior !== null && $prom < $prom_anterior) $pos_actual++;
    $posiciones[$id_est] = $pos_actual;
    $prom_anterior = $prom;
}
$estudiantes_ordenados = $estudiantes;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Centralizador - <?= htmlspecialchars($nombre_curso) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap & Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f5f6fa; }
        .content-section { background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 16px 8px; margin: 24px 0 12px 0;}
        .centralizador-table th, .centralizador-table td { vertical-align: middle; padding: 0.34rem 0.44rem;font-size: 0.94rem;}
        .centralizador-table thead th { background: #e9ecef; color: #222; font-weight: 600;}
        .centralizador-table th.extra-materia, .centralizador-table .materia-extra { background: #f6f7fa !important; color: #607080 !important; font-style: italic;}
        .centralizador-table .nota-baja { background: #fff5f6; color: #c01a30 !important; font-weight: 600;}
        .centralizador-table .average-cell { background: #f1f1f7 !important; font-weight: 500;}
        .centralizador-table .final-average { background: #ececec !important; font-weight: 650; color: #3a3a95;}
        .badge-extra { font-size: .78em; background: #838897 !important; color: #fff !important; }
        .position-cell, .number-cell { background: #f5f7fa; font-weight: 600; color: #5472a1;}
        .student-name { min-width: 150px; white-space: nowrap;}
        .header-controls { background: #fff; border-bottom: 1px solid #e2e4e7; position: sticky; top: 0; z-index: 101;}
        @media (max-width: 768px) {.centralizador-table th, .centralizador-table td { font-size: .83rem !important; padding: .2rem !important; }.content-section {padding:10px 4px;}}
        @media print {.header-controls, .btn, .no-print {display:none !important;}body {background:#fff !important;}}
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Incluye tu sidebar real aquí, sin rehacerlo -->
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4">
            <!-- Header con título y botones -->
            <div class="header-controls d-flex flex-wrap justify-content-between align-items-center py-2 mb-3 no-print">
                <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="history.back();">
                        <i class="bi bi-arrow-left"></i> Volver
                    </button>
                    <span class="fs-5 fw-bold text-primary"><?= htmlspecialchars($nombre_curso) ?></span>
                </div>
                <div class="d-flex gap-2">
                    <a href="editar_notas.php?id=<?= $id_curso ?>" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-pencil"></i> Editar
                    </a>
                    <button onclick="window.print()" class="btn btn-primary btn-sm">
                        <i class="bi bi-printer"></i> Imprimir
                    </button>
                </div>
            </div>

            <section class="content-section">
                <div class="table-responsive">
                    <table class="table centralizador-table table-bordered align-middle table-sm mb-0">
                        <thead>
                            <tr>
                                <th rowspan="2" class="align-middle">#</th>
                                <th rowspan="2" class="align-middle">Pos.</th>
                                <th rowspan="2" class="align-middle text-start">Estudiante</th>
                                <?php foreach ($materias as $materia): ?>
                                    <th colspan="4" class="align-middle <?= $materia['es_extra'] ? 'extra-materia' : '' ?>">
                                        <?= htmlspecialchars($materia['nombre_materia']) ?>
                                        <?php if (!empty($materia['es_extra'])): ?>
                                            <span class="badge badge-extra ms-1">Extra</span>
                                        <?php endif; ?>
                                    </th>
                                <?php endforeach; ?>
                                <th rowspan="2" class="align-middle">Prom. General</th>
                            </tr>
                            <tr>
                                <?php foreach ($materias as $materia): ?>
                                    <th<?= $materia['es_extra'] ? ' class="extra-materia"' : '' ?>>T1</th>
                                    <th<?= $materia['es_extra'] ? ' class="extra-materia"' : '' ?>>T2</th>
                                    <th<?= $materia['es_extra'] ? ' class="extra-materia"' : '' ?>>T3</th>
                                    <th<?= $materia['es_extra'] ? ' class="extra-materia"' : '' ?>>Prom</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $contador = 1; ?>
                            <?php foreach ($estudiantes_ordenados as $estudiante): ?>
                                <tr>
                                    <td class="number-cell"><?= $contador++ ?></td>
                                    <td class="position-cell"><?= $posiciones[$estudiante['id_estudiante']] ?></td>
                                    <td class="student-name"><?= htmlspecialchars(strtoupper("{$estudiante['apellido_paterno']} {$estudiante['apellido_materno']}, {$estudiante['nombres']}")) ?></td>
                                    <?php foreach ($materias as $materia): ?>
                                        <?php
                                        $clase_extra = !empty($materia['es_extra']) ? 'materia-extra' : '';
                                        $n1 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][1] ?? '';
                                        $n2 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][2] ?? '';
                                        $n3 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][3] ?? '';
                                        $pm = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
                                        ?>
                                        <td class="<?= $clase_extra ?> <?= (is_numeric($n1) && $n1 < 50) ? 'nota-baja' : '' ?>"><?= $n1 ?></td>
                                        <td class="<?= $clase_extra ?> <?= (is_numeric($n2) && $n2 < 50) ? 'nota-baja' : '' ?>"><?= $n2 ?></td>
                                        <td class="<?= $clase_extra ?> <?= (is_numeric($n3) && $n3 < 50) ? 'nota-baja' : '' ?>"><?= $n3 ?></td>
                                        <td class="average-cell <?= $clase_extra ?> <?= (is_numeric($pm) && $pm < 50) ? 'nota-baja' : '' ?>"><?= $pm ?></td>
                                    <?php endforeach; ?>
                                    <td class="final-average"><?= $promedios_generales[$estudiante['id_estudiante']] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
