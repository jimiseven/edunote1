<!-- - funcionalidad para vista anual
- mateerias extra
- materias padres e hijas
- FALTA LA VISTA -->
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

// Obtener materias del curso con información de parentesco
$stmt_materias = $conn->prepare("
    SELECT m.id_materia, m.nombre_materia, m.es_extra, m.es_submateria, m.materia_padre_id
    FROM cursos_materias cm 
    JOIN materias m ON cm.id_materia = m.id_materia 
    WHERE cm.id_curso = ? 
    ORDER BY m.nombre_materia
");
$stmt_materias->execute([$id_curso]);
$todas_materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// Reorganizar materias: padres, extras, y luego hijas agrupadas por padre
$materias_padre = [];
$materias_extra = [];
$materias_hijas = [];

// Separar por tipo
foreach ($todas_materias as $materia) {
    if ($materia['es_extra'] == 1) {
        $materias_extra[] = $materia;
    } 
    elseif ($materia['es_submateria'] == 0) {
        $materia['hijas'] = []; // Inicializar array para hijas
        $materias_padre[$materia['id_materia']] = $materia;
    }
    else {
        $materias_hijas[] = $materia;
    }
}

// Asociar hijas con sus padres
foreach ($materias_hijas as $hija) {
    if (isset($materias_padre[$hija['materia_padre_id']])) {
        $materias_padre[$hija['materia_padre_id']]['hijas'][] = $hija;
    }
}

// Crear array ordenado para visualización
$materias = array_merge(
    array_values($materias_padre), 
    $materias_extra, 
    $materias_hijas
);

// Obtener todas las calificaciones
$calificaciones = [];
foreach ($estudiantes as $estudiante) {
    foreach ($todas_materias as $materia) {
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
// Calcular notas automáticas para materias padre (promedio de hijas)
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
                    $calificaciones[$estudiante['id_estudiante']][$padre['id_materia']][$t] = 
                        number_format($suma / $contador, 2);
                }
            }
        }
    }
}

// Calcular promedios por materia (incluye todas, pero solo se usan padres en el promedio general)
$promedios_materias = [];
foreach ($estudiantes as $estudiante) {
    foreach ($todas_materias as $materia) {
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

// Calcular promedios generales (SOLO materias padre)
$promedios_generales = [];
$promedios_trimestre = [];

foreach ($estudiantes as $estudiante) {
    $suma_promedios = 0;
    $contador = 0;
    
    // Solo materias padre (no extra, no hijas)
    foreach ($todas_materias as $materia) {
        if (
            $materia['es_extra'] == 1 || 
            $materia['es_submateria'] == 1 ||
            isset($materia['materia_padre_id']) // Excluir hijas
        ) {
            continue;
        }
        
        $promedio = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
        if ($promedio !== '') {
            $suma_promedios += floatval($promedio);
            $contador++;
        }
    }
    
    $promedios_generales[$estudiante['id_estudiante']] = $contador > 0 
        ? number_format($suma_promedios / $contador, 2) 
        : '-';

    // Vista trimestral (solo materias padre)
    $suma_trimestre = 0;
    $contador_trimestre = 0;
    foreach ($todas_materias as $materia) {
        if (
            $materia['es_extra'] == 1 || 
            $materia['es_submateria'] == 1 ||
            isset($materia['materia_padre_id'])
        ) {
            continue;
        }
        
        $nota = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$trimestre] ?? '';
        if ($nota !== '') {
            $suma_trimestre += floatval($nota);
            $contador_trimestre++;
        }
    }
    
    $promedios_trimestre[$estudiante['id_estudiante']] = $contador_trimestre > 0 
        ? number_format($suma_trimestre / $contador_trimestre, 2) 
        : '-';
}

// Calcular posiciones (según promedio general)
$promedios_ordenados = $promedios_generales;
arsort($promedios_ordenados);
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

// Mantener orden alfabético de estudiantes
$estudiantes_ordenados = $estudiantes;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centralizador - <?= htmlspecialchars($nombre_curso) ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        .materia-extra { background-color: #f8f9fa; color: #6c757d; font-style: italic; }
        .nota-baja { color: #dc3545; font-weight: bold; }
        .table th, .table td { vertical-align: middle; text-align: center; }
        .student-name { text-align: left; min-width: 250px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-wrapper mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1><?= htmlspecialchars($nombre_curso) ?></h1>
                        <div class="btn-group">
                            <a href="dashboard_primaria.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                        </div>
                    </div>

                    <!-- Selectores de vista -->
                    <div class="mb-3">
                        <a href="?id=<?= $id_curso ?>&vista=anual" 
                           class="btn <?= $vista == 'anual' ? 'btn-primary' : 'btn-outline-primary' ?>">
                            Vista Anual
                        </a>
                        <a href="?id=<?= $id_curso ?>&vista=trimestral" 
                           class="btn <?= $vista == 'trimestral' ? 'btn-primary' : 'btn-outline-primary' ?>">
                            Vista Trimestral
                        </a>
                    </div>

                    <!-- Tabla de calificaciones -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2">#</th>
                                    <th rowspan="2">Pos.</th>
                                    <th rowspan="2">Estudiante</th>
                                    <?php foreach ($materias as $materia): ?>
                                        <th colspan="4" class="<?= $materia['es_extra'] ? 'materia-extra' : '' ?>">
                                            <?= htmlspecialchars($materia['nombre_materia']) ?>
                                            <?= $materia['es_extra'] ? ' <small>(Extra)</small>' : '' ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th rowspan="2">PROM. GENERAL</th>
                                </tr>
                                <tr>
                                    <?php foreach ($materias as $materia): ?>
                                        <th class="<?= $materia['es_extra'] ? 'materia-extra' : '' ?>">T1</th>
                                        <th class="<?= $materia['es_extra'] ? 'materia-extra' : '' ?>">T2</th>
                                        <th class="<?= $materia['es_extra'] ? 'materia-extra' : '' ?>">T3</th>
                                        <th class="<?= $materia['es_extra'] ? 'materia-extra' : '' ?>">Prom</th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $contador = 1; ?>
                                <?php foreach ($estudiantes_ordenados as $estudiante): ?>
                                    <tr>
                                        <td><?= $contador++ ?></td>
                                        <td><?= $posiciones[$estudiante['id_estudiante']] ?></td>
                                        <td class="student-name">
                                            <?= htmlspecialchars(strtoupper(
                                                $estudiante['apellido_paterno'] . ' ' . 
                                                $estudiante['apellido_materno'] . ', ' . 
                                                $estudiante['nombres']
                                            )) ?>
                                        </td>
                                        
                                        <?php foreach ($materias as $materia): ?>
                                            <?php
                                            $es_extra = $materia['es_extra'] ?? 0;
                                            $clase_extra = $es_extra ? 'materia-extra' : '';
                                            $n1 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][1] ?? '';
                                            $n2 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][2] ?? '';
                                            $n3 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][3] ?? '';
                                            $pm = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
                                            ?>
                                            <td class="<?= $clase_extra ?>" <?= (is_numeric($n1) && $n1 < 50) ? 'style="color: #dc3545; font-weight: bold;"' : '' ?>>
                                                <?= $n1 ?>
                                            </td>
                                            <td class="<?= $clase_extra ?>" <?= (is_numeric($n2) && $n2 < 50) ? 'style="color: #dc3545; font-weight: bold;"' : '' ?>>
                                                <?= $n2 ?>
                                            </td>
                                            <td class="<?= $clase_extra ?>" <?= (is_numeric($n3) && $n3 < 50) ? 'style="color: #dc3545; font-weight: bold;"' : '' ?>>
                                                <?= $n3 ?>
                                            </td>
                                            <td class="average-cell <?= $clase_extra ?>" <?= (is_numeric($pm) && $pm < 50) ? 'style="color: #dc3545; font-weight: bold;"' : '' ?>>
                                                <?= $pm ?>
                                            </td>
                                        <?php endforeach; ?>
                                        
                                        <td class="final-average fw-bold">
                                            <?= $promedios_generales[$estudiante['id_estudiante']] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
