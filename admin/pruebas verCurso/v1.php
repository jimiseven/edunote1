<!-- Version que funciona el anual pero no trimestral  -->
<?php
session_start();
require_once '../config/database.php';

// Verificar autenticación (admin o profesor)
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

// Conexión a la base de datos
$database = new Database();
$conn = $database->connect();

// Obtener información del curso
$stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
$stmt_curso->execute([$id_curso]);

if ($stmt_curso->rowCount() == 0) {
    header('Location: dashboard.php?error=curso_no_encontrado');
    exit();
}

$curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);
$nombre_curso = $curso_info['nivel'] . ' ' . $curso_info['curso'] . ' "' . $curso_info['paralelo'] . '"';

// Obtener estudiantes ordenados alfabéticamente
$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, apellido_paterno, apellido_materno, nombres 
    FROM estudiantes 
    WHERE id_curso = ? 
    ORDER BY apellido_paterno, apellido_materno, nombres
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// Obtener materias del curso
$stmt_materias = $conn->prepare("
    SELECT m.id_materia, m.nombre_materia, m.es_extra 
    FROM cursos_materias cm 
    JOIN materias m ON cm.id_materia = m.id_materia 
    WHERE cm.id_curso = ? 
    ORDER BY m.nombre_materia
");
$stmt_materias->execute([$id_curso]);
$materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las calificaciones
$calificaciones = [];
foreach ($estudiantes as $estudiante) {
    foreach ($materias as $materia) {
        for ($i = 1; $i <= 3; $i++) {
            $stmt = $conn->prepare("
                SELECT calificacion 
                FROM calificaciones 
                WHERE id_estudiante = ? 
                AND id_materia = ? 
                AND bimestre = ?
            ");
            $stmt->execute([$estudiante['id_estudiante'], $materia['id_materia'], $i]);
            $nota = $stmt->fetchColumn();
            $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$i] = $nota !== false ? $nota : '';
        }
    }
}

// Calcular promedios por materia (excluyendo materias extra)
$promedios_materias = [];
foreach ($estudiantes as $estudiante) {
    foreach ($materias as $materia) {
        // Saltar materias marcadas como extra
        if ($materia['es_extra'] == 1) {
            continue;
        }

        $notas = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']] ?? [];
        $notas_validas = array_filter($notas, function ($v) {
            return $v !== '' && $v !== null;
        });

        if (count($notas_validas) > 0) {
            $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] =
                number_format(array_sum($notas_validas) / count($notas_validas), 2);
        } else {
            $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] = '';
        }
    }
}


// Calcular promedios generales (para ordenación y columna final)
$promedios_generales = [];
$promedios_trimestre = []; // Para vista trimestral

foreach ($estudiantes as $estudiante) {
    // Para vista anual - promedio de los promedios de materias
    $suma_promedios = 0;
    $contador = 0;

    foreach ($materias as $materia) {
        $promedio_materia = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
        if ($promedio_materia !== '') {
            $suma_promedios += floatval($promedio_materia);
            $contador++;
        }
    }

    if ($contador > 0) {
        $promedios_generales[$estudiante['id_estudiante']] = number_format($suma_promedios / $contador, 2);
    } else {
        $promedios_generales[$estudiante['id_estudiante']] = 0;
    }

    // Para vista trimestral - promedio de las notas del trimestre seleccionado
    $suma_trimestre = 0;
    $contador_trimestre = 0;

    foreach ($materias as $materia) {
        $nota_trimestre = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$trimestre] ?? '';
        if ($nota_trimestre !== '') {
            $suma_trimestre += floatval($nota_trimestre);
            $contador_trimestre++;
        }
    }

    if ($contador_trimestre > 0) {
        $promedios_trimestre[$estudiante['id_estudiante']] = number_format($suma_trimestre / $contador_trimestre, 2);
    } else {
        $promedios_trimestre[$estudiante['id_estudiante']] = '';
    }
}

// SOLUCIÓN: Usar copia de promedios para calcular posiciones sin alterar el orden alfabético
$promedios_ordenados = $promedios_generales;
arsort($promedios_ordenados);

// Determinar posiciones considerando empates
$posiciones = [];
$posicion_actual = 1;
$promedio_anterior = null;

foreach ($promedios_ordenados as $id_est => $promedio) {
    if ($promedio_anterior !== null && $promedio < $promedio_anterior) {
        $posicion_actual++;
    }
    $posiciones[$id_est] = $posicion_actual;
    $promedio_anterior = $promedio;
}

// Estudiantes ya están ordenados alfabéticamente, no necesitan reordenarse
$estudiantes_ordenados = $estudiantes;
?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centralizador de Notas - <?php echo htmlspecialchars($nombre_curso); ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .content-wrapper {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin: 20px;
        }

        .course-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .table-container {
            overflow-x: auto;
        }

        .table {
            border-collapse: collapse;
            width: 100%;
        }

        .table th,
        .table td {
            text-align: center;
            padding: 10px;
            font-size: 0.9rem;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .table th {
            background-color: #f1f8ff;
            color: #345995;
            font-weight: 600;
            white-space: nowrap;
        }

        .table th.main-header {
            background-color: #e9ecef;
            font-weight: 700;
        }

        .table th.subject-header {
            background-color: #e9ecef;
            border-bottom: 2px solid #dee2e6;
        }

        .table th.trimester-header {
            background-color: #f8f9fa;
        }

        .table td.student-name {
            text-align: left;
            font-weight: 500;
            white-space: nowrap;
        }

        .table td.position-cell {
            font-weight: 700;
            color: #2c3e50;
        }

        .table td.number-cell {
            color: #6c757d;
        }

        .table td.average-cell {
            font-weight: 600;
            background-color: #f8f9fa;
        }

        .table td.final-average {
            font-weight: 700;
            background-color: #e3f2fd;
            color: #0d47a1;
        }

        .selector-container {
            display: flex;
            gap: 10px;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .content-wrapper {
                box-shadow: none;
                margin: 0;
                padding: 10px;
            }
        }

        .materia-extra {
            background-color: #f8f9fa;
            color: #6c757d;
            font-style: italic;
        }

        .materia-extra td {
            border-right: 1px solid #dee2e6 !important;
            /* Mantiene bordes visibles */
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-wrapper">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="course-title"><?php echo htmlspecialchars($nombre_curso); ?></h1>
                        <div class="selector-container no-print">
                            <form method="GET" action="" id="vista-form">
                                <input type="hidden" name="id" value="<?php echo $id_curso; ?>">
                                <select class="form-select" name="vista" onchange="document.getElementById('vista-form').submit()">
                                    <option value="trimestral" <?php echo ($vista == 'trimestral') ? 'selected' : ''; ?>>Trimestral</option>
                                    <option value="anual" <?php echo ($vista == 'anual') ? 'selected' : ''; ?>>Anual</option>
                                </select>
                            </form>

                            <?php if ($vista == 'trimestral'): ?>
                                <form method="GET" action="">
                                    <input type="hidden" name="id" value="<?php echo $id_curso; ?>">
                                    <input type="hidden" name="vista" value="trimestral">
                                    <select class="form-select" name="trimestre" onchange="this.form.submit()">
                                        <option value="1" <?php echo ($trimestre == 1) ? 'selected' : ''; ?>>Trimestre 1</option>
                                        <option value="2" <?php echo ($trimestre == 2) ? 'selected' : ''; ?>>Trimestre 2</option>
                                        <option value="3" <?php echo ($trimestre == 3) ? 'selected' : ''; ?>>Trimestre 3</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="table">
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2">#</th>
                                    <th rowspan="2">Pos.</th>
                                    <th rowspan="2">Estudiante</th>
                                    <?php foreach ($materias as $materia): ?>
                                        <th colspan="4" <?= $materia['es_extra'] ? 'style="background:#f8f9fa; color:#6c757d;"' : '' ?>>
                                            <?= htmlspecialchars($materia['nombre_materia']) ?>
                                            <?= $materia['es_extra'] ? ' <small>(Extra)</small>' : '' ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th rowspan="2">PROM. GENERAL</th>
                                </tr>
                                <tr>
                                    <?php foreach ($materias as $materia): ?>
                                        <th<?= $materia['es_extra'] ? ' style="background:#f8f9fa; color:#6c757d;"' : '' ?>>T1</th>
                                            <th<?= $materia['es_extra'] ? ' style="background:#f8f9fa; color:#6c757d;"' : '' ?>>T2</th>
                                                <th<?= $materia['es_extra'] ? ' style="background:#f8f9fa; color:#6c757d;"' : '' ?>>T3</th>
                                                    <th<?= $materia['es_extra'] ? ' style="background:#f8f9fa; color:#6c757d;"' : '' ?>>Prom</th>
                                                    <?php endforeach; ?>
                                </tr>
                            </thead>

                            <tbody>
                                <?php $contador = 1; ?>
                                <?php foreach ($estudiantes_ordenados as $estudiante): ?>
                                    <tr>
                                        <td class="number-cell"><?php echo $contador++; ?></td>
                                        <td class="position-cell"><?php echo $posiciones[$estudiante['id_estudiante']]; ?></td>
                                        <td class="student-name">
                                            <?php echo htmlspecialchars(strtoupper($estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno'] . ', ' . $estudiante['nombres'])); ?>
                                        </td>

                                        <?php if ($vista == 'anual'): ?>
                                            <?php foreach ($materias as $materia): ?>
                                                <?php
                                                // Verificar si es materia extra
                                                $es_extra = $materia['es_extra'] ?? 0;
                                                $clase_extra = $es_extra ? 'materia-extra' : '';

                                                // Obtener notas
                                                $n1 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][1] ?? '';
                                                $n2 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][2] ?? '';
                                                $n3 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][3] ?? '';
                                                $pm = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
                                                ?>
                                                <!-- Aplicar estilo a todas las celdas de la materia -->
                                                <td class="<?= $clase_extra ?>" <?= (is_numeric($n1) && $n1 < 50) ? ' style="color:#d81b1b;font-weight:bold"' : '' ?>><?= $n1 ?></td>
                                                <td class="<?= $clase_extra ?>" <?= (is_numeric($n2) && $n2 < 50) ? ' style="color:#d81b1b;font-weight:bold"' : '' ?>><?= $n2 ?></td>
                                                <td class="<?= $clase_extra ?>" <?= (is_numeric($n3) && $n3 < 50) ? ' style="color:#d81b1b;font-weight:bold"' : '' ?>><?= $n3 ?></td>
                                                <td class="average-cell <?= $clase_extra ?>" <?= (is_numeric($pm) && $pm < 50) ? ' style="color:#d81b1b;font-weight:bold"' : '' ?>><?= $pm ?></td>
                                            <?php endforeach; ?>

                                            <!-- Promedio general (ya excluye materias extra) -->
                                            <td class="final-average"><?php echo $promedios_generales[$estudiante['id_estudiante']]; ?></td>
                                        <?php else: ?>
                                            <?php foreach ($materias as $materia): ?>
                                                <?php
                                                $es_extra = $materia['es_extra'] ?? 0;
                                                $clase_extra = $es_extra ? 'materia-extra' : '';
                                                $nt = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$trimestre] ?? '';
                                                ?>
                                                <td class="<?= $clase_extra ?>" <?= (is_numeric($nt) && $nt < 50) ? ' style="color:#d81b1b;font-weight:bold"' : '' ?>>
                                                    <?php echo $nt; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="final-average"><?php echo $promedios_trimestre[$estudiante['id_estudiante']]; ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>


                        </table>
                    </div>

                    <div class="d-flex justify-content-between mt-4 no-print">
                        <?php
                        // Asegúrate de tener la variable $nivel o $curso_info['nivel'] disponible
                        $nivel = isset($curso_info['nivel']) ? $curso_info['nivel'] : '';
                        $volver_url = 'dashboard.php';
                        if ($nivel == 'Inicial') {
                            $volver_url = 'dash_iniciales.php';
                        } elseif ($nivel == 'Primaria') {
                            $volver_url = 'dashboard_primaria.php';
                        } elseif ($nivel == 'Secundaria') {
                            $volver_url = 'dashboard_secundaria.php';
                        }
                        ?>
                        <a href="<?php echo $volver_url; ?>" class="btn btn-secondary">Volver</a>
                        <div>
                            <a href="editar_notas.php?id=<?php echo $id_curso; ?>" class="btn btn-warning">Editar Notas</a>
                            <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>
</body>

</html>