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

// Calcular promedios generales
$promedios_generales = [];
$promedios_trimestre = [];

foreach ($estudiantes as $estudiante) {
    // Vista anual
    $suma_promedios = 0;
    $contador = 0;
    foreach ($materias as $materia) {
        $promedio_materia = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
        if ($promedio_materia !== '' && $materia['es_extra'] == 0) {
            $suma_promedios += floatval($promedio_materia);
            $contador++;
        }
    }
    $promedios_generales[$estudiante['id_estudiante']] = $contador > 0 ? number_format($suma_promedios / $contador, 2) : 0;

    // Vista trimestral
    $suma_trimestre = 0;
    $contador_trimestre = 0;
    foreach ($materias as $materia) {
        $nota_trimestre = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$trimestre] ?? '';
        if ($nota_trimestre !== '' && $materia['es_extra'] == 0) {
            $suma_trimestre += floatval($nota_trimestre);
            $contador_trimestre++;
        }
    }
    $promedios_trimestre[$estudiante['id_estudiante']] = $contador_trimestre > 0 ? number_format($suma_trimestre / $contador_trimestre, 2) : '';
}

// Determinar posiciones
$promedios_ordenados = $promedios_generales;
arsort($promedios_ordenados);
$posiciones = [];
$posicion_actual = 1;
$promedio_anterior = null;

foreach ($promedios_ordenados as $id_est => $promedio) {
    if ($promedio_anterior !== null && $promedio < $promedio_anterior) $posicion_actual++;
    $posiciones[$id_est] = $posicion_actual;
    $promedio_anterior = $promedio;
}

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
        /* Estilos anteriores se mantienen igual */
        .trimestre-header { background-color: #f8f9fa !important; }
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
                                    <option value="trimestral" <?= ($vista == 'trimestral') ? 'selected' : '' ?>>Trimestral</option>
                                    <option value="anual" <?= ($vista == 'anual') ? 'selected' : '' ?>>Anual</option>
                                </select>
                            </form>

                            <?php if ($vista == 'trimestral'): ?>
                                <form method="GET" action="">
                                    <input type="hidden" name="id" value="<?php echo $id_curso; ?>">
                                    <input type="hidden" name="vista" value="trimestral">
                                    <select class="form-select" name="trimestre" onchange="this.form.submit()">
                                        <?php for ($i=1; $i<=3; $i++): ?>
                                            <option value="<?= $i ?>" <?= ($trimestre == $i) ? 'selected' : '' ?>>Trimestre <?= $i ?></option>
                                        <?php endfor; ?>
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
                                        <?php if ($vista == 'anual'): ?>
                                            <th colspan="4" <?= $materia['es_extra'] ? 'class="materia-extra"' : '' ?>>
                                                <?= htmlspecialchars($materia['nombre_materia']) ?>
                                                <?= $materia['es_extra'] ? ' <small>(Extra)</small>' : '' ?>
                                            </th>
                                        <?php else: ?>
                                            <th <?= $materia['es_extra'] ? 'class="materia-extra"' : '' ?>>
                                                <?= htmlspecialchars($materia['nombre_materia']) ?>
                                                <?= $materia['es_extra'] ? ' <small>(Extra)</small>' : '' ?>
                                            </th>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <th rowspan="2">PROM. <?= $vista == 'anual' ? 'GENERAL' : 'TRIMESTRE' ?></th>
                                </tr>
                                <?php if ($vista == 'anual'): ?>
                                <tr>
                                    <?php foreach ($materias as $materia): ?>
                                        <th<?= $materia['es_extra'] ? ' class="materia-extra"' : '' ?>>T1</th>
                                        <th<?= $materia['es_extra'] ? ' class="materia-extra"' : '' ?>>T2</th>
                                        <th<?= $materia['es_extra'] ? ' class="materia-extra"' : '' ?>>T3</th>
                                        <th<?= $materia['es_extra'] ? ' class="materia-extra"' : '' ?>>Prom</th>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endif; ?>
                            </thead>
                            <tbody>
                                <?php $contador = 1; ?>
                                <?php foreach ($estudiantes_ordenados as $estudiante): ?>
                                    <tr>
                                        <td class="number-cell"><?= $contador++ ?></td>
                                        <td class="position-cell"><?= $posiciones[$estudiante['id_estudiante']] ?></td>
                                        <td class="student-name"><?= htmlspecialchars(strtoupper($estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno'] . ', ' . $estudiante['nombres'])) ?></td>

                                        <?php foreach ($materias as $materia): ?>
                                            <?php
                                            $es_extra = $materia['es_extra'];
                                            $clase_extra = $es_extra ? 'materia-extra' : '';
                                            $nota = ($vista == 'anual') 
                                                ? $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] 
                                                : $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$trimestre];
                                            ?>

                                            <?php if ($vista == 'anual'): ?>
                                                <?php $notas = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']]; ?>
                                                <td class="<?= $clase_extra ?>" <?= ($notas[1] < 50) ? 'style="color:#d81b1b;font-weight:bold"' : '' ?>><?= $notas[1] ?></td>
                                                <td class="<?= $clase_extra ?>" <?= ($notas[2] < 50) ? 'style="color:#d81b1b;font-weight:bold"' : '' ?>><?= $notas[2] ?></td>
                                                <td class="<?= $clase_extra ?>" <?= ($notas[3] < 50) ? 'style="color:#d81b1b;font-weight:bold"' : '' ?>><?= $notas[3] ?></td>
                                                <td class="average-cell <?= $clase_extra ?>" <?= ($nota < 50) ? 'style="color:#d81b1b;font-weight:bold"' : '' ?>><?= $nota ?></td>
                                            <?php else: ?>
                                                <td class="<?= $clase_extra ?>" <?= ($nota < 50) ? 'style="color:#d81b1b;font-weight:bold"' : '' ?>><?= $nota ?></td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>

                                        <td class="final-average">
                                            <?= ($vista == 'anual') 
                                                ? $promedios_generales[$estudiante['id_estudiante']] 
                                                : $promedios_trimestre[$estudiante['id_estudiante']] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between mt-4 no-print">
                        <?php
                        $nivel = $curso_info['nivel'];
                        $volver_url = 'dashboard.php';
                        switch ($nivel) {
                            case 'Inicial': $volver_url = 'dash_iniciales.php'; break;
                            case 'Primaria': $volver_url = 'dashboard_primaria.php'; break;
                            case 'Secundaria': $volver_url = 'dashboard_secundaria.php'; break;
                        }
                        ?>
                        <a href="<?= $volver_url ?>" class="btn btn-secondary">Volver</a>
                        <div>
                            <a href="editar_notas.php?id=<?= $id_curso ?>" class="btn btn-warning">Editar Notas</a>
                            <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>