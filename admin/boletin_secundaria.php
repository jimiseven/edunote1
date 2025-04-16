<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

// Detectar curso desde GET
$id_curso = $_GET['id_curso'] ?? 0;
if (!$id_curso) {
    header('Location: dashboard_secundaria.php');
    exit();
}

// Determinar vista
$vista = $_GET['vista'] ?? 'trimestral';
$trimestre = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : 1;

// Info curso
$stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
$stmt_curso->execute([$id_curso]);
$curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);
if (!$curso_info) {
    echo "<div class='alert alert-danger'>Curso no encontrado</div>";
    exit();
}
$nombre_curso = $curso_info['nivel'] . ' ' . $curso_info['curso'] . ' "' . $curso_info['paralelo'] . '"';

// Abreviatura automática
function generarAbreviatura($nombre) {
    $palabras = explode(' ', $nombre);
    $abreviatura = '';
    foreach ($palabras as $palabra) {
        if (strlen($palabra) > 3) $abreviatura .= strtoupper(substr($palabra, 0, 3));
        else $abreviatura .= strtoupper($palabra);
    }
    return substr($abreviatura, 0, 6);
}

// Materias
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

$materias_padre = [];
$materias_hijas = [];
$materias_individuales = [];
foreach ($todas_materias as $materia) {
    $materia['abreviatura'] = generarAbreviatura($materia['nombre_materia']);
    if ($materia['materia_padre_id'] === null) {
        if ($materia['tiene_hijas'] > 0) {
            $materias_padre[$materia['id_materia']] = $materia;
        } else {
            $materias_individuales[] = $materia;
        }
    } else {
        $materias_hijas[$materia['materia_padre_id']][] = $materia;
    }
}
foreach ($materias_padre as &$padre) {
    $padre['hijas'] = $materias_hijas[$padre['id_materia']] ?? [];
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

// Promedios generales
$promedios = [];
foreach ($estudiantes as $est) {
    $total_notas = 0;
    $contador_notas = 0;
    foreach ($materias_individuales as $mat) {
        if ($vista == 'trimestral') {
            $nota = $calificaciones[$est['id_estudiante']][$mat['id_materia']][$trimestre] ?? '-';
            if (is_numeric($nota)) {
                $total_notas += $nota;
                $contador_notas++;
            }
        } else {
            $suma_trim = 0;
            $count_trim = 0;
            for ($t = 1; $t <= 3; $t++) {
                $nota_t = $calificaciones[$est['id_estudiante']][$mat['id_materia']][$t] ?? '-';
                if (is_numeric($nota_t)) {
                    $suma_trim += $nota_t;
                    $count_trim++;
                }
            }
            if ($count_trim > 0) {
                $total_notas += ($suma_trim / $count_trim);
                $contador_notas++;
            }
        }
    }
    foreach ($materias_padre as $padre) {
        foreach ($padre['hijas'] as $hija) {
            if ($vista == 'trimestral') {
                $nota = $calificaciones[$est['id_estudiante']][$hija['id_materia']][$trimestre] ?? '-';
                if (is_numeric($nota)) {
                    $total_notas += $nota;
                    $contador_notas++;
                }
            } else {
                $suma_trim = 0;
                $count_trim = 0;
                for ($t = 1; $t <= 3; $t++) {
                    $nota_t = $calificaciones[$est['id_estudiante']][$hija['id_materia']][$t] ?? '-';
                    if (is_numeric($nota_t)) {
                        $suma_trim += $nota_t;
                        $count_trim++;
                    }
                }
                if ($count_trim > 0) {
                    $total_notas += ($suma_trim / $count_trim);
                    $contador_notas++;
                }
            }
        }
    }
    $promedios[$est['id_estudiante']] = $contador_notas > 0 ? 
        number_format($total_notas / $contador_notas, 2) : '-';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boletín <?= htmlspecialchars($nombre_curso) ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 0.9rem;
        }
        .boletin-table {
            width: 100%;
            border-collapse: collapse;
        }
        .boletin-table th, .boletin-table td {
            border: 1px solid #dee2e6;
            padding: 6px;
            text-align: center;
            vertical-align: middle;
        }
        .encabezado-principal {
            background-color: #2f75b5;
            color: white;
            font-weight: bold;
            font-size: 1.1em;
            border-top: 2px solid #1c4481;
            border-bottom: 2px solid #1c4481;
        }
        .encabezado-grupo {
            background-color: #d9e1f2;
            font-weight: bold;
            color: #305496;
        }
        .encabezado-submateria {
            background-color: #e7edf7;
            font-weight: 600;
        }
        .tr-striped td {
            background-color: #f8f9fa;
        }
        .nombre-estudiante {
            text-align: left;
            font-weight: 500;
            position: sticky;
            left: 2.5rem;
            background: white;
            z-index: 2;
            min-width: 220px;
        }
        .numero-estudiante {
            position: sticky;
            left: 0;
            background: white;
            z-index: 3;
            min-width: 2.5rem;
            border-right: 2px solid #dee2e6;
        }
        .promedio-general {
            background-color: #ffedea;
            color: #d72c16;
            font-weight: bold;
        }
        .selector-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        .vista-actual {
            background-color: #e3f2fd;
            padding: 5px 15px;
            border-radius: 4px;
            font-weight: bold;
            color: #0d47a1;
        }
        .curso-titulo {
            flex-grow: 1;
            text-align: center;
        }
        @media print {
            .no-print { display: none !important; }
            .boletin-table th, .boletin-table td { font-size: 9pt; }
            .nombre-estudiante, .numero-estudiante { background-color: white !important; }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-3">
        <!-- Cabecera con título centrado y botones a los lados -->
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <a href="dashboard_secundaria.php" class="btn btn-secondary">
                <i class="ri-arrow-left-line"></i> Atrás
            </a>
            <h1 class="h3 text-primary curso-titulo"><?= htmlspecialchars($nombre_curso) ?></h1>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="ri-printer-line"></i> Imprimir
            </button>
        </div>
        
        <!-- Selectores de vista -->
        <div class="selector-container no-print">
            <form method="GET" action="" id="vista-form">
                <input type="hidden" name="id_curso" value="<?= $id_curso ?>">
                <div class="input-group">
                    <label class="input-group-text" for="vista">Vista</label>
                    <select class="form-select" name="vista" id="vista" onchange="document.getElementById('vista-form').submit()">
                        <option value="trimestral" <?= ($vista == 'trimestral') ? 'selected' : '' ?>>Trimestral</option>
                        <option value="anual" <?= ($vista == 'anual') ? 'selected' : '' ?>>Anual</option>
                    </select>
                </div>
            </form>
            
            <?php if ($vista == 'trimestral'): ?>
                <form method="GET" action="">
                    <input type="hidden" name="id_curso" value="<?= $id_curso ?>">
                    <input type="hidden" name="vista" value="trimestral">
                    <div class="input-group">
                        <label class="input-group-text" for="trimestre">Trimestre</label>
                        <select class="form-select" name="trimestre" id="trimestre" onchange="this.form.submit()">
                            <option value="1" <?= ($trimestre == 1) ? 'selected' : '' ?>>Primer trimestre</option>
                            <option value="2" <?= ($trimestre == 2) ? 'selected' : '' ?>>Segundo trimestre</option>
                            <option value="3" <?= ($trimestre == 3) ? 'selected' : '' ?>>Tercer trimestre</option>
                        </select>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="d-flex align-items-center">
                <div class="vista-actual">
                    <?= $vista == 'trimestral' ? 'Trimestre ' . $trimestre : 'Vista Anual' ?>
                </div>
            </div>
        </div>

        <?php if (empty($todas_materias)): ?>
            <div class="alert alert-warning">
                No hay materias asignadas a este curso.
            </div>
        <?php elseif (empty($estudiantes)): ?>
            <div class="alert alert-warning">
                No hay estudiantes matriculados en este curso.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="boletin-table">
                    <?php if ($vista == 'trimestral'): ?>
                        <!-- VISTA TRIMESTRAL -->
                        <thead>
                            <tr>
                                <th rowspan="2" class="numero-estudiante">#</th>
                                <th rowspan="2" class="nombre-estudiante">Estudiante</th>
                                
                                <!-- PRIMERO: Materias individuales -->
                                <?php if (!empty($materias_individuales)): ?>
                                    <th colspan="<?= count($materias_individuales) ?>" class="encabezado-principal"></th>
                                <?php endif; ?>
                                
                                <!-- DESPUÉS: Materias padre y sus hijas -->
                                <?php foreach ($materias_padre as $padre): ?>
                                    <th colspan="<?= count($padre['hijas']) ?>" class="encabezado-grupo">
                                        <?= htmlspecialchars($padre['nombre_materia']) ?>
                                    </th>
                                <?php endforeach; ?>
                                
                                <!-- Promedio general -->
                                <th rowspan="2" class="promedio-general">PROM. GRAL</th>
                            </tr>
                            <tr>
                                <!-- Nombres de materias individuales PRIMERO -->
                                <?php foreach ($materias_individuales as $mat): ?>
                                    <th class="encabezado-submateria">
                                        <?= htmlspecialchars($mat['abreviatura']) ?>
                                    </th>
                                <?php endforeach; ?>
                                
                                <!-- Submaterias bajo cada materia padre DESPUÉS -->
                                <?php foreach ($materias_padre as $padre): ?>
                                    <?php foreach ($padre['hijas'] as $hija): ?>
                                        <th class="encabezado-submateria">
                                            <?= htmlspecialchars($hija['abreviatura']) ?>
                                        </th>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $contador = 1; foreach ($estudiantes as $est): ?>
                                <tr class="<?= $contador % 2 == 0 ? 'tr-striped' : '' ?>">
                                    <td class="numero-estudiante"><?= $contador++ ?></td>
                                    <td class="nombre-estudiante">
                                        <?= htmlspecialchars(
                                            $est['apellido_paterno'] . ' ' . 
                                            $est['apellido_materno'] . ', ' . 
                                            $est['nombres']
                                        ) ?>
                                    </td>
                                    
                                    <!-- PRIMERO: Notas de materias individuales -->
                                    <?php foreach ($materias_individuales as $mat): ?>
                                        <td>
                                            <?= $calificaciones[$est['id_estudiante']][$mat['id_materia']][$trimestre] ?? '-' ?>
                                        </td>
                                    <?php endforeach; ?>
                                    
                                    <!-- DESPUÉS: Notas de submaterias -->
                                    <?php foreach ($materias_padre as $padre): ?>
                                        <?php foreach ($padre['hijas'] as $hija): ?>
                                            <td>
                                                <?= $calificaciones[$est['id_estudiante']][$hija['id_materia']][$trimestre] ?? '-' ?>
                                            </td>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                    
                                    <!-- Promedio general -->
                                    <td class="promedio-general">
                                        <?= $promedios[$est['id_estudiante']] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    <?php else: ?>
                        <!-- VISTA ANUAL -->
                        <thead>
                            <tr>
                                <th rowspan="2" class="numero-estudiante">#</th>
                                <th rowspan="2" class="nombre-estudiante">Estudiante</th>
                                
                                <!-- PRIMERO: Materias individuales -->
                                <?php if (!empty($materias_individuales)): ?>
                                    <th colspan="<?= count($materias_individuales) * 3 ?>" class="encabezado-principal"></th>
                                <?php endif; ?>
                                
                                <!-- DESPUÉS: Materias padre -->
                                <?php foreach ($materias_padre as $padre): ?>
                                    <th colspan="<?= count($padre['hijas']) * 3 ?>" class="encabezado-grupo">
                                        <?= htmlspecialchars($padre['nombre_materia']) ?>
                                    </th>
                                <?php endforeach; ?>
                                
                                <!-- Promedio general -->
                                <th rowspan="2" class="promedio-general">PROM. GRAL</th>
                            </tr>
                            <tr>
                                <!-- PRIMERO: Materias individuales con tres trimestres cada una -->
                                <?php foreach ($materias_individuales as $mat): ?>
                                    <th class="encabezado-submateria"><?= $mat['abreviatura'] ?> T1</th>
                                    <th class="encabezado-submateria"><?= $mat['abreviatura'] ?> T2</th>
                                    <th class="encabezado-submateria"><?= $mat['abreviatura'] ?> T3</th>
                                <?php endforeach; ?>
                                
                                <!-- DESPUÉS: Para cada submateria, mostrar 3 columnas (Trim 1, 2, 3) -->
                                <?php foreach ($materias_padre as $padre): ?>
                                    <?php foreach ($padre['hijas'] as $hija): ?>
                                        <th class="encabezado-submateria"><?= $hija['abreviatura'] ?> T1</th>
                                        <th class="encabezado-submateria"><?= $hija['abreviatura'] ?> T2</th>
                                        <th class="encabezado-submateria"><?= $hija['abreviatura'] ?> T3</th>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $contador = 1; foreach ($estudiantes as $est): ?>
                                <tr class="<?= $contador % 2 == 0 ? 'tr-striped' : '' ?>">
                                    <td class="numero-estudiante"><?= $contador++ ?></td>
                                    <td class="nombre-estudiante">
                                        <?= htmlspecialchars(
                                            $est['apellido_paterno'] . ' ' . 
                                            $est['apellido_materno'] . ', ' . 
                                            $est['nombres']
                                        ) ?>
                                    </td>
                                    
                                    <!-- PRIMERO: Notas de materias individuales (3 trimestres) -->
                                    <?php foreach ($materias_individuales as $mat): ?>
                                        <?php for ($t = 1; $t <= 3; $t++): ?>
                                            <td>
                                                <?= $calificaciones[$est['id_estudiante']][$mat['id_materia']][$t] ?? '-' ?>
                                            </td>
                                        <?php endfor; ?>
                                    <?php endforeach; ?>
                                    
                                    <!-- DESPUÉS: Notas de submaterias (3 trimestres) -->
                                    <?php foreach ($materias_padre as $padre): ?>
                                        <?php foreach ($padre['hijas'] as $hija): ?>
                                            <?php for ($t = 1; $t <= 3; $t++): ?>
                                                <td>
                                                    <?= $calificaciones[$est['id_estudiante']][$hija['id_materia']][$t] ?? '-' ?>
                                                </td>
                                            <?php endfor; ?>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                    
                                    <!-- Promedio general -->
                                    <td class="promedio-general">
                                        <?= $promedios[$est['id_estudiante']] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
