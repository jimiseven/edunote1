<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

// Obtener ID del curso desde parámetro GET
$id_curso = $_GET['id_curso'] ?? 0;

if (!$id_curso) {
    header('Location: dashboard_primaria.php');
    exit();
}

// Determinar el modo de visualización (trimestral/anual)
$vista = $_GET['vista'] ?? 'trimestral';
$trimestre = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : 1;

// Obtener información del curso
$stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
$stmt_curso->execute([$id_curso]);
$curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);

if (!$curso_info) {
    echo "<div class='alert alert-danger'>Curso no encontrado</div>";
    exit();
}

$nombre_curso = $curso_info['nivel'] . ' ' . $curso_info['curso'] . ' "' . $curso_info['paralelo'] . '"';

// Función para generar abreviaturas automáticas
function generarAbreviatura($nombre)
{
    $palabras = explode(' ', $nombre);
    $abreviatura = '';

    foreach ($palabras as $palabra) {
        if (strlen($palabra) > 3) {
            $abreviatura .= strtoupper(substr($palabra, 0, 3));
        } else {
            $abreviatura .= strtoupper($palabra);
        }
    }

    return substr($abreviatura, 0, 6);
}

// Obtener todas las materias del curso
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

if (empty($todas_materias)) {
    $todas_materias = []; // Asegurar que sea un array vacío
}

// Organizar materias
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

// Vincular hijas con padres
foreach ($materias_padre as &$padre) {
    $padre['hijas'] = $materias_hijas[$padre['id_materia']] ?? [];
}
unset($padre);

// Obtener estudiantes
$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, apellido_paterno, apellido_materno, nombres 
    FROM estudiantes 
    WHERE id_curso = ?
    ORDER BY apellido_paterno, apellido_materno, nombres
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

if (empty($estudiantes)) {
    $estudiantes = []; // Asegurar que sea un array vacío
}

// Obtener calificaciones
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

// Calcular promedios
$promedios = [];
foreach ($estudiantes as $est) {
    $total_notas = 0;
    $contador_notas = 0;

    // Incluir materias sin categoría
    foreach ($materias_individuales as $mat) {
        if ($vista == 'trimestral') {
            $nota = $calificaciones[$est['id_estudiante']][$mat['id_materia']][$trimestre] ?? '-';
            if (is_numeric($nota)) {
                $total_notas += $nota;
                $contador_notas++;
            }
        } else {
            // En vista anual, promediamos los tres trimestres
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

    // Incluir submaterias (no las materias padre)
    foreach ($materias_padre as $padre) {
        foreach ($padre['hijas'] as $hija) {
            if ($vista == 'trimestral') {
                $nota = $calificaciones[$est['id_estudiante']][$hija['id_materia']][$trimestre] ?? '-';
                if (is_numeric($nota)) {
                    $total_notas += $nota;
                    $contador_notas++;
                }
            } else {
                // En vista anual, promediamos los tres trimestres
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

        .boletin-table th,
        .boletin-table td {
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
            .no-print {
                display: none !important;
            }

            .boletin-table th,
            .boletin-table td {
                font-size: 9pt;
            }

            .nombre-estudiante,
            .numero-estudiante {
                background-color: white !important;
            }
        }
    </style>
    <!-- Librerías para PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

</head>

<body>
    <div class="container-fluid py-3">
        <!-- Cabecera con título centrado y botones a los lados -->
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <a href="dashboard_primaria.php" class="btn btn-secondary">
                <i class="ri-arrow-left-line"></i> Atrás
            </a>
            <h1 class="h3 text-primary curso-titulo"><?= htmlspecialchars($nombre_curso) ?></h1>
            <button onclick="generarBoletinesPDF()" class="btn btn-primary">
                <i class="ri-file-pdf-line"></i> Boletines PDF
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
                                    <th colspan="<?= count($materias_individuales) ?>" class="encabezado-principal">
                                        <!-- Espacio en blanco como solicitado -->
                                    </th>
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
                            <?php $contador = 1;
                            foreach ($estudiantes as $est): ?>
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
                                    <th colspan="<?= count($materias_individuales) * 3 ?>" class="encabezado-principal">
                                        <!-- Espacio en blanco como solicitado -->
                                    </th>
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
                            <?php $contador = 1;
                            foreach ($estudiantes as $est): ?>
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
    <script>
        // Función para generar boletines PDF para primaria
        function generarBoletinesPDF() {
            // Obtener datos del sistema
            const estudiantes = <?= json_encode($estudiantes) ?>;
            const materiasIndividuales = <?= json_encode($materias_individuales) ?>;

            // Corregir el problema con materiasPadre - convertirlo explícitamente a array
            const materiasPadreObj = <?= json_encode($materias_padre) ?>;
            const materiasPadre = Object.keys(materiasPadreObj).map(key => materiasPadreObj[key]);

            const calificaciones = <?= json_encode($calificaciones) ?>;
            const nombreCurso = "<?= htmlspecialchars($nombre_curso) ?>";
            const trimestre = <?= $vista == 'trimestral' ? $trimestre : 1 ?>;
            const textoTrimestre = ["", "PRIMER", "SEGUNDO", "TERCER"][trimestre];

            // Crear nueva instancia PDF
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();

            // Verificar si hay estudiantes
            if (!Array.isArray(estudiantes) || estudiantes.length === 0) {
                alert('No hay estudiantes para generar boletines');
                return;
            }

            // Para cada estudiante generar un boletín
            estudiantes.forEach((estudiante, index) => {
                // Agregar nueva página si no es el primer estudiante
                if (index > 0) {
                    doc.addPage();
                }

                // Título del boletín
                doc.setFontSize(14);
                doc.setFont('helvetica', 'bold');
                doc.text(`BOLETÍN DE NOTAS - ${textoTrimestre} TRIMESTRE`, 105, 20, {
                    align: 'center'
                });

                // Información del encabezado
                doc.setFontSize(10);
                doc.setFont('helvetica', 'bold');
                doc.text("UNIDAD EDUCATIVA", 25, 35);
                doc.text(":", 85, 35);
                doc.setFont('helvetica', 'normal');
                doc.text("Colegio San Agustín", 90, 35);

                doc.setFont('helvetica', 'bold');
                doc.text("DOCENTE", 25, 42);
                doc.text(":", 85, 42);

                doc.setFont('helvetica', 'bold');
                doc.text("CURSO", 25, 49);
                doc.text(":", 85, 49);
                doc.setFont('helvetica', 'normal');
                doc.text(nombreCurso, 90, 49);

                doc.setFont('helvetica', 'bold');
                doc.text("ESTUDIANTE", 25, 56);
                doc.text(":", 85, 56);
                doc.setFont('helvetica', 'normal');
                doc.text(`${estudiante.apellido_paterno} ${estudiante.apellido_materno}, ${estudiante.nombres}`, 90, 56);

                // Línea separadora
                doc.line(25, 60, 185, 60);

                // Crear lista de materias para mostrar
                let todasMaterias = [];

                // Primero materias individuales
                if (Array.isArray(materiasIndividuales)) {
                    materiasIndividuales.forEach(materia => {
                        todasMaterias.push({
                            id: materia.id_materia,
                            nombre: materia.nombre_materia,
                            tipo: 'individual'
                        });
                    });
                }

                // Luego materias hijas - con verificación de array
                if (Array.isArray(materiasPadre)) {
                    materiasPadre.forEach(padre => {
                        if (padre && padre.hijas && Array.isArray(padre.hijas)) {
                            padre.hijas.forEach(hija => {
                                if (hija && hija.id_materia) {
                                    todasMaterias.push({
                                        id: hija.id_materia,
                                        nombre: hija.nombre_materia,
                                        padre: padre.nombre_materia,
                                        tipo: 'hija'
                                    });
                                }
                            });
                        }
                    });
                }

                // Cabeceras para la tabla
                const headers = [
                    [{
                            content: 'Campos De Saberes',
                            rowSpan: 2
                        },
                        {
                            content: 'ÁREA',
                            rowSpan: 1
                        },
                        {
                            content: '1er Trim.',
                            rowSpan: 1
                        },
                        {
                            content: '2do Trim.',
                            rowSpan: 1
                        },
                        {
                            content: '3er Trim.',
                            rowSpan: 1
                        },
                        {
                            content: 'PROMEDIO',
                            rowSpan: 1
                        }
                    ]
                ];

                // Crear filas para la tabla
                const rows = [];

                todasMaterias.forEach(materia => {
                    // Obtener calificaciones - con verificación de existencia
                    let nota1 = '-',
                        nota2 = '-',
                        nota3 = '-';

                    try {
                        if (calificaciones &&
                            calificaciones[estudiante.id_estudiante] &&
                            calificaciones[estudiante.id_estudiante][materia.id]) {

                            nota1 = calificaciones[estudiante.id_estudiante][materia.id][1] || '-';
                            nota2 = calificaciones[estudiante.id_estudiante][materia.id][2] || '-';
                            nota3 = calificaciones[estudiante.id_estudiante][materia.id][3] || '-';
                        }
                    } catch (e) {
                        console.error("Error al obtener calificaciones:", e);
                    }

                    // Calcular promedio
                    let promedio = '-';
                    try {
                        const notasValidas = [nota1, nota2, nota3].filter(n => !isNaN(parseFloat(n)));
                        if (notasValidas.length > 0) {
                            const suma = notasValidas.reduce((acc, val) => acc + parseFloat(val), 0);
                            promedio = (suma / notasValidas.length).toFixed(2);
                        }
                    } catch (e) {
                        console.error("Error al calcular promedio:", e);
                    }

                    // Añadir fila
                    rows.push([
                        '', // Campo de Saberes - se añadirá luego
                        materia.nombre,
                        nota1,
                        nota2,
                        nota3,
                        promedio
                    ]);
                });

                // Si no hay materias, mostrar mensaje
                if (rows.length === 0) {
                    rows.push(['', 'No hay materias asignadas', '-', '-', '-', '-']);
                }

                // Crear tabla
                doc.autoTable({
                    startY: 65,
                    head: headers,
                    body: rows,
                    theme: 'grid',
                    styles: {
                        lineColor: [0, 0, 0],
                        lineWidth: 0.1,
                        fontSize: 9
                    },
                    headStyles: {
                        fillColor: [245, 245, 245],
                        textColor: [0, 0, 0],
                        halign: 'center',
                        valign: 'middle',
                        fontStyle: 'bold'
                    },
                    columnStyles: {
                        0: {
                            cellWidth: 35,
                            halign: 'center'
                        },
                        1: {
                            cellWidth: 55
                        },
                        2: {
                            cellWidth: 18,
                            halign: 'center'
                        },
                        3: {
                            cellWidth: 18,
                            halign: 'center'
                        },
                        4: {
                            cellWidth: 18,
                            halign: 'center'
                        },
                        5: {
                            cellWidth: 25,
                            halign: 'center'
                        }
                    },
                    // Método seguro para dibujar "Campos de Saberes y Conocimientos"
                    didDrawPage: function() {
                        try {
                            // Dibujar "Campos de Saberes" manualmente con posición exacta
                            const firstCellX = 25;
                            const firstCellWidth = 35;
                            const tableStartY = 65;
                            const tableEndY = doc.autoTable.previous.finalY;

                            // Coordenadas seguras
                            const textX = firstCellX + firstCellWidth / 2;
                            const textY = tableStartY + (tableEndY - tableStartY) / 2;

                            doc.saveGraphicsState();
                            doc.setFont('helvetica', 'bold');
                            doc.setFontSize(9);
                            doc.text('Campos De Saberes y Conocimientos', textX, textY, {
                                align: 'center',
                                maxWidth: firstCellWidth - 5
                            });
                            doc.restoreGraphicsState();
                        } catch (e) {
                            console.error("Error al dibujar texto:", e);
                        }
                    }
                });

                // Espacio para firmas
                const finalY = doc.autoTable.previous.finalY + 30;
                doc.setFont('helvetica', 'bold');
                doc.text("FIRMA MAESTRO/A", 55, finalY, {
                    align: 'center'
                });
                doc.text("DIRECCIÓN", 150, finalY, {
                    align: 'center'
                });
            });

            // Guardar el PDF
            doc.save(`Boletines_${nombreCurso.replace(/\s+/g, '_')}.pdf`);
        }
    </script>


</body>

</html>