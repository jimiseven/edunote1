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
        function generarBoletinesPDF() {
            const estudiantes = <?= json_encode($estudiantes) ?>;
            const materiasIndividuales = <?= json_encode($materias_individuales) ?>;
            const materiasPadreObj = <?= json_encode($materias_padre) ?>;
            const materiasPadre = Object.keys(materiasPadreObj).map(key => materiasPadreObj[key]);
            const calificaciones = <?= json_encode($calificaciones) ?>;
            const nombreCurso = <?= json_encode(htmlspecialchars_decode($nombre_curso)) ?>;
            const trimestre = <?= $vista == 'trimestral' ? $trimestre : 1 ?>;

            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();

            // 🔵 Pega aquí tu imagen Base64
            const LOGO_BASE64 = "data:image/png;base64,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";

            if (!Array.isArray(estudiantes) || estudiantes.length === 0) {
                alert('No hay estudiantes para generar boletines');
                return;
            }

            estudiantes.forEach((estudiante, index) => {
                if (index > 0) doc.addPage();

                doc.setTextColor(0, 0, 0); // Negro puro

                // 🔵 Insertar logo en la parte superior derecha
                doc.addImage(LOGO_BASE64, 'PNG', 170, 10, 18, 18);

                // TÍTULO
                doc.setFontSize(16);
                doc.setFont('helvetica', 'bolditalic');
                doc.text("U.E SIMON BOLIVAR", 105, 15, {
                    align: 'center'
                });

                // ENCABEZADO
                doc.setFontSize(12);
                doc.setFont('helvetica', 'bold');
                doc.text("ESTUDIANTE :", 20, 27);
                doc.setFont('helvetica', 'normal');
                doc.text(`${estudiante.apellido_paterno} ${estudiante.apellido_materno} ${estudiante.nombres}`, 60, 27);

                doc.setFont('helvetica', 'bold');
                doc.text("CURSO :", 20, 33);
                doc.setFont('helvetica', 'normal');
                doc.text(`${nombreCurso} PRIMARIA`, 60, 33);

                doc.setFont('helvetica', 'bold');
                doc.text("GESTIÓN :", 150, 33);
                doc.setFont('helvetica', 'normal');
                doc.text("2025", 180, 33);

                doc.line(20, 37, 190, 37); // Línea separadora

                // MATERIAS
                let todasMaterias = [];

                if (Array.isArray(materiasIndividuales)) {
                    materiasIndividuales.forEach(m => {
                        todasMaterias.push({
                            id: m.id_materia,
                            nombre: m.nombre_materia
                        });
                    });
                }

                if (Array.isArray(materiasPadre)) {
                    materiasPadre.forEach(p => {
                        if (p.hijas && Array.isArray(p.hijas)) {
                            p.hijas.forEach(h => {
                                todasMaterias.push({
                                    id: h.id_materia,
                                    nombre: h.nombre_materia
                                });
                            });
                        }
                    });
                }

                // ORDEN PERSONALIZADO
                const ordenDeseado = [
                    "LENGUAJE",
                    "INGLES",
                    "CIENCIAS SOCIALES",
                    "EDUCACION FISICA Y DEPORTES",
                    "EDUCACION MUSICA",
                    "ARTES PLASTICAS",
                    "MATEMATICAS",
                    "TECNICA TECNOLOGIA",
                    "CIENCIAS NATURALES",
                    "VALORES ESPIRITUALIDAD Y RELIGIONES"
                ];

                const normalizar = str => str.toUpperCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s+/g, " ").trim();

                todasMaterias.sort((a, b) => {
                    const idxA = ordenDeseado.findIndex(nombre => normalizar(nombre) === normalizar(a.nombre));
                    const idxB = ordenDeseado.findIndex(nombre => normalizar(nombre) === normalizar(b.nombre));
                    return (idxA === -1 ? 999 : idxA) - (idxB === -1 ? 999 : idxB);
                });

                // CABECERA
                const headers = [
                    [{
                            content: 'ASIGNATURA',
                            styles: {
                                halign: 'left'
                            }
                        },
                        '1TRI', '2TRI', '3TRI', 'P.ANUAL'
                    ]
                ];

                const rows = [];

                todasMaterias.forEach(materia => {
                    let nota1 = '-',
                        nota2 = '-',
                        nota3 = '-',
                        promedio = '-';

                    try {
                        const notas = calificaciones?.[estudiante.id_estudiante]?.[materia.id] || {};
                        nota1 = notas[1] ?? '-';
                        nota2 = notas[2] ?? '-';
                        nota3 = notas[3] ?? '-';

                        const validas = [nota1, nota2, nota3].map(n => parseFloat(n)).filter(n => !isNaN(n));
                        if (validas.length > 0) {
                            promedio = (validas.reduce((a, b) => a + b, 0) / validas.length).toFixed(2);
                        }
                    } catch (e) {
                        console.error(e);
                    }

                    rows.push([{
                            content: materia.nombre,
                            styles: {
                                halign: 'left'
                            }
                        },
                        nota1, nota2, nota3, promedio
                    ]);
                });

                if (rows.length === 0) {
                    rows.push(['No hay materias', '-', '-', '-', '-']);
                }

                // TABLA
                doc.autoTable({
                    head: headers,
                    body: rows,
                    startY: 40,
                    theme: 'grid',
                    headStyles: {
                        fillColor: [255, 255, 255],
                        textColor: [0, 0, 0],
                        fontStyle: 'bold',
                        halign: 'center'
                    },
                    bodyStyles: {
                        fontSize: 10,
                        textColor: [0, 0, 0],
                        halign: 'center'
                    },
                    columnStyles: {
                        0: {
                            cellWidth: 80,
                            halign: 'left'
                        },
                        1: {
                            cellWidth: 20,
                            halign: 'center'
                        },
                        2: {
                            cellWidth: 20,
                            halign: 'center'
                        },
                        3: {
                            cellWidth: 20,
                            halign: 'center'
                        },
                        4: {
                            cellWidth: 25,
                            halign: 'center'
                        }
                    },
                    tableWidth: 'auto',
                    margin: {
                        left: 20,
                        right: 20
                    }
                });

                // FIRMAS
                const yFinal = doc.lastAutoTable.finalY + 20;
                doc.setFont('helvetica', 'bold');
                doc.setTextColor(0, 0, 0);
                doc.text("FIRMA MAESTRO", 45, yFinal, {
                    align: 'center'
                });
                doc.text("DIRECCIÓN", 150, yFinal, {
                    align: 'center'
                });
            });

            doc.save(`Boletines_${nombreCurso.replace(/\s+/g, '_')}.pdf`);
        }
    </script>


</body>

</html>