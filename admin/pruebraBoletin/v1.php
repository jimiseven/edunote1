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

// Reorganiza materias: padres simples, extras, luego padres con hijas + hijas
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

// Asocia hijas con sus padres
foreach ($materias_hijas as $hija) {
    if (isset($materias_padre[$hija['materia_padre_id']])) {
        $materias_padre[$hija['materia_padre_id']]['hijas'][] = $hija;
    }
}

// Separa padres simples y padres con hijas
$materias_padre_simples = [];
$materias_padre_con_hijas = [];
foreach ($materias_padre as $padre) {
    if (empty($padre['hijas'])) {
        $materias_padre_simples[] = $padre;
    } else {
        $materias_padre_con_hijas[] = $padre;
    }
}

// Orden final: padres simples > extras > padres con hijas (y sus hijas)
$materias = array_merge(
    $materias_padre_simples,  // 1. Padres sin hijas
    $materias_extra,           // 2. Materias extras
    $materias_padre_con_hijas  // 3. Padres con hijas (se mostrarán al final)
);

// Añade las hijas después de cada padre correspondiente
foreach ($materias_padre_con_hijas as $padre) {
    $materias = array_merge($materias, $padre['hijas']);
}

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
        if ($materia['es_extra'] == 1 || $materia['es_submateria'] == 1)
            continue;
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
        if ($materia['es_extra'] == 1 || $materia['es_submateria'] == 1)
            continue;
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
    if ($prom_anterior !== null && $prom < $prom_anterior)
        $pos_actual++;
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
        body {
            background: #f5f6fa;
        }

        .content-section {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            padding: 16px 8px;
            margin: 24px 0 12px 0;
        }

        .centralizador-table th,
        .centralizador-table td {
            vertical-align: middle;
            padding: 0.34rem 0.44rem;
            font-size: 0.94rem;
        }

        .centralizador-table thead th {
            background: #e9ecef;
            color: #222;
            font-weight: 600;
        }

        .centralizador-table th.extra-materia,
        .centralizador-table .materia-extra {
            background: #f6f7fa !important;
            color: #607080 !important;
            font-style: italic;
        }

        .centralizador-table .nota-baja {
            background: #fff5f6;
            color: #c01a30 !important;
            font-weight: 600;
        }

        .centralizador-table .average-cell {
            background: #f1f1f7 !important;
            font-weight: 500;
        }

        .centralizador-table .final-average {
            background: #ececec !important;
            font-weight: 650;
            color: #3a3a95;
        }

        .badge-extra {
            font-size: .78em;
            background: #838897 !important;
            color: #fff !important;
        }

        .position-cell,
        .number-cell {
            background: #f5f7fa;
            font-weight: 600;
            color: #5472a1;
        }

        .student-name {
            min-width: 150px;
            white-space: nowrap;
        }

        .header-controls {
            background: #fff;
            border-bottom: 1px solid #e2e4e7;
            position: sticky;
            top: 0;
            z-index: 101;
        }

        @media (max-width: 768px) {

            .centralizador-table th,
            .centralizador-table td {
                font-size: .83rem !important;
                padding: .2rem !important;
            }

            .content-section {
                padding: 10px 4px;
            }
        }

        @media print {

            .header-controls,
            .btn,
            .no-print {
                display: none !important;
            }

            body {
                background: #fff !important;
            }
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .centralizador-table,
            .centralizador-table * {
                visibility: visible;
            }

            .centralizador-table {
                position: absolute;
                left: 0;
                top: 0;
                width: 100% !important;
                max-width: 100% !important;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Incluye tu sidebar real aquí, sin rehacerlo -->
            <?php include '../includes/sidebar.php'; ?>

            <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4">
                <!-- Header con título y botones -->
                <div
                    class="header-controls d-flex flex-wrap justify-content-between align-items-center py-2 mb-3 no-print">
                    <div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
                        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>

                        <span class="fs-5 fw-bold text-primary"><?= htmlspecialchars($nombre_curso) ?></span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="editar_notas.php?id=<?= $id_curso ?>" class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-pencil"></i> Editar
                        </a>
                        <a href="ver_trimestre.php?id_curso=<?= $id_curso ?>" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-calendar-week"></i> Ver Trimestre
                        </a>
                        <button onclick="showAnnualReport()" class="btn btn-secondary btn-sm">
                            Reporte
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
                                        <th colspan="4"
                                            class="text-center <?= $materia['es_extra'] ? 'extra-materia' : '' ?>">
                                            <span class="nombre-materia" style="font-size: <?= strlen($materia['nombre_materia']) > 20 ? '0.8em' : '1em' ?>">
                                                <?= htmlspecialchars($materia['nombre_materia']) ?>
                                            </span>
                                            <?php if (!empty($materia['es_extra'])): ?>
                                                <span class="badge badge-extra ms-1">Extra</span>
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th rowspan="2" class="text-center">P. General</th>
                                </tr>
                                <tr>
                                    <?php foreach ($materias as $materia): ?>
                                        <th class="text-center<?= $materia['es_extra'] ? ' extra-materia' : '' ?>">T1</th>
                                        <th class="text-center<?= $materia['es_extra'] ? ' extra-materia' : '' ?>">T2</th>
                                        <th class="text-center<?= $materia['es_extra'] ? ' extra-materia' : '' ?>">T3</th>
                                        <th class="text-center<?= $materia['es_extra'] ? ' extra-materia' : '' ?>">P</th>
                                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $contador = 1; ?>
                                <?php foreach ($estudiantes_ordenados as $estudiante): ?>
                                    <tr>
                                        <td class="number-cell"><?= $contador++ ?></td>
                                        <td class="position-cell"><?= $posiciones[$estudiante['id_estudiante']] ?></td>
                                        <td class="student-name">
                                            <?= htmlspecialchars(strtoupper("{$estudiante['apellido_paterno']} {$estudiante['apellido_materno']}, {$estudiante['nombres']}")) ?>
                                        </td>
                                        <?php foreach ($materias as $materia): ?>
                                            <?php
                                            $clase_extra = !empty($materia['es_extra']) ? 'materia-extra' : '';
                                            $n1 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][1] ?? '';
                                            $n2 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][2] ?? '';
                                            $n3 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][3] ?? '';
                                            $pm = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
                                            ?>
                                            <td
                                                class="<?= $clase_extra ?> <?= (is_numeric($n1) && $n1 < 50) ? 'nota-baja' : '' ?>">
                                                <?= $n1 ?></td>
                                            <td
                                                class="<?= $clase_extra ?> <?= (is_numeric($n2) && $n2 < 50) ? 'nota-baja' : '' ?>">
                                                <?= $n2 ?></td>
                                            <td
                                                class="<?= $clase_extra ?> <?= (is_numeric($n3) && $n3 < 50) ? 'nota-baja' : '' ?>">
                                                <?= $n3 ?></td>
                                            <td
                                                class="average-cell <?= $clase_extra ?> <?= (is_numeric($pm) && $pm < 50) ? 'nota-baja' : '' ?>">
                                                <?= $pm ?></td>
                                        <?php endforeach; ?>
                                        <td class="final-average"><?= $promedios_generales[$estudiante['id_estudiante']] ?>
                                        </td>
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


    <script>
        async function generatePDFPanelBlocks() {
            const nombreCurso = <?= json_encode($nombre_curso) ?>;
            const pdfContainer = document.createElement('div');
            pdfContainer.className = 'pdf-export';

            // Cabecera elegante
            const header = `
                <div style="font-family: Arial, sans-serif; text-align:center; margin-bottom:8px;">
                    <div style="font-size:16pt; font-weight:bold;">U.E. SIMÓN BOLÍVAR</div>
                    <div style="font-size:15pt; font-weight:700; color:#003366;">CENTRALIZADOR DE NOTAS</div>
                    <div style="font-size:11.5pt; margin-top:1px; margin-bottom:3px;">${nombreCurso}</div>
                    <div style="font-size:10.5pt; color:#555;">Año Escolar ${new Date().getFullYear()}</div>
                    <hr style="border-top:1.2px solid #003366; width:75%; margin:6px auto;">
                </div>
            `;

            // Clona y personaliza la tabla
            const originalTable = document.querySelector('.centralizador-table');
            const table = originalTable.cloneNode(true);

            table.style.margin = "0 auto";
            table.style.fontSize = "9pt";
            table.style.width = "99%";
            table.style.borderCollapse = "collapse";
            table.style.tableLayout = "fixed";

            // Celdas compactas y nítidas
            table.querySelectorAll('th, td').forEach(el => {
                el.style.padding = "3.3px 4.2px";
                el.style.border = "1.6px solid #bbb";
                el.style.fontSize = "9pt";
                el.style.textAlign = "center";
                el.style.wordBreak = "break-word";
            });

            // Encabezado por bloques
            table.querySelectorAll('th[colspan]').forEach(th => {
                th.style.background = "#e3ecfa";
                th.style.color = "#1e3d73";
                th.style.fontWeight = "bold";
                th.style.fontSize = "9.5pt";
                th.style.borderBottom = "2.3px solid #6699cc";
                th.style.letterSpacing = ".5px";
            });
            // Extras azul pálido
            table.querySelectorAll('.materia-extra').forEach(td => {
                td.style.background = "#e3f4ff";
                td.style.color = "#18809b";
                td.style.fontStyle = "italic";
            });
            // Promedios generales destacados
            table.querySelectorAll('.final-average').forEach(td => {
                td.style.background = "#f9edc1";
                td.style.fontWeight = "bold";
                td.style.color = "#ad5409";
                td.style.fontSize = "10pt";
            });

            // Rayado de filas
            [...table.tBodies[0].rows].forEach((row, i) => {
                if (i % 2 === 1) row.style.background = "#f7f8fd";
            });

            pdfContainer.innerHTML = header;
            pdfContainer.appendChild(table);
            document.body.appendChild(pdfContainer);

            // ---- PDF con máxima resolución ----
            const pdf = new jspdf.jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: 'a4',
                hotfixes: ["px_scaling"]
            });

            const options = {
                scale: 3, // MAX RESOLUCIÓN
                useCORS: true,
                logging: false,
                scrollY: 0,
                backgroundColor: "#fff"
            };

            const canvas = await html2canvas(pdfContainer, options);

            // Medidas ajustadas para hoja carta horizontal
            const pageWidth = 297;
            const xPosition = 7,
                yPosition = 9;
            const imgWidth = pageWidth - 2 * xPosition;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;

            pdf.addImage(canvas, 'PNG', xPosition, yPosition, imgWidth, imgHeight, undefined, 'FAST');

            document.body.removeChild(pdfContainer);
            pdf.save(`Centralizador - ${nombreCurso}.pdf`);
        }

        function showAnnualReport() {
            // Crear modal básico con spinner
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div class="modal fade" id="annualReportModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">Reporte Anual</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <p class="mt-2">Generando reporte...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal.querySelector('.modal'));
            bsModal.show();

            // Generar reporte directamente con los datos del DOM
            setTimeout(() => {
                try {
                    const reportContent = generateClientSideReport();
                    modal.querySelector('.modal-body').innerHTML = reportContent;
                } catch (error) {
                    modal.querySelector('.modal-body').innerHTML = `
                        <div class="alert alert-danger">
                            Error al generar el reporte: ${error.message}
                        </div>
                    `;
                }
            }, 500);

            // Limpiar al cerrar
            modal.addEventListener('hidden.bs.modal', () => {
                document.body.removeChild(modal);
            });
        }

        function generateClientSideReport() {
            // Clonar la tabla existente con validación
            const originalTable = document.querySelector('.centralizador-table');
            if (!originalTable) {
                throw new Error('No se encontró la tabla de datos');
            }
            const table = originalTable.cloneNode(true);
            
            
            // Aplicar estilos para el reporte y evitar solapamiento
            table.classList.add('table-bordered', 'table-sm', 'reporte-table');
            table.style.width = 'auto';
            table.style.minWidth = '100%';
            table.style.tableLayout = 'auto';
            
            // Convertir notas decimales a enteros
            table.querySelectorAll('td').forEach(td => {
                if (/^\d+\.\d+$/.test(td.textContent)) {
                    td.textContent = Math.round(parseFloat(td.textContent));
                }
            });

            // Crear contenedor para el reporte con estilos mejorados
            const container = document.createElement('div');
            container.style.overflowX = 'auto';
            container.style.maxHeight = '80vh';
            container.innerHTML = '<style>' +
                '.reporte-table {' +
                '   border-collapse: collapse;' +
                '   width: 100%;' +
                '   font-size: 8px;' +
                '   table-layout: fixed;' +
                '}' +
                '.reporte-table th[colspan] {' +
                '   max-width: 120px;' +
                '   overflow: hidden;' +
                '   text-overflow: ellipsis;' +
                '}' +
                '.nombre-materia {' +
                '   display: inline-block;' +
                '   max-width: 100%;' +
                '   white-space: nowrap;' +
                '   overflow: hidden;' +
                '   text-overflow: ellipsis;' +
                '}' +
                '.reporte-table th, .reporte-table td {' +
                '   padding: 2px 3px;' +
                '   border: 1px solid #dee2e6;' +
                '   white-space: nowrap;' +
                '   vertical-align: middle;' +
                '   text-align: center;' +
                '}' +
                '.reporte-table th {' +
                '   background-color: #f8f9fa;' +
                '   position: sticky;' +
                '   top: 0;' +
                '   z-index: 10;' +
                '   font-weight: bold;' +
                '}' +
                '.reporte-table tr:nth-child(even) {' +
                '   background-color: #f9f9f9;' +
                '}' +
                '.modal-body {' +
                '   overflow: auto;' +
                '   max-height: 80vh;' +
                '}' +
                '.table-responsive {' +
                '   width: 100%;' +
                '   overflow-x: auto;' +
                '}' +
                '@media print {' +
                '   @page { size: landscape; margin: 5mm; }' +
                '   body { margin: 0; padding: 0; }' +
                '   .split-container {' +
                '       display: flex;' +
                '       width: 100%;' +
                '   }' +
                '   .split-section {' +
                '       width: 50%;' +
                '       page-break-after: always;' +
                '   }' +
                '   .reporte-table {' +
                '       font-size: 7px !important;' +
                '       margin-bottom: 10mm;' +
                '   }' +
                '   .reporte-table th, .reporte-table td {' +
                '       padding: 1px 2px !important;' +
                '   }' +
                '   .header-section {' +
                '       text-align: center;' +
                '       margin-bottom: 5mm;' +
                '   }' +
                '   .footer-section {' +
                '       text-align: center;' +
                '       margin-top: 5mm;' +
                '       font-size: 8px;' +
                '   }' +
                '}' +
                '</style>' +
                '<h4 class="text-center mb-4">Reporte Anual</h4>' +
                '<div class="table-responsive">' +
                table.outerHTML.replace('centralizador-table', 'centralizador-table reporte-table') +
                '</div>' +
                '<div class="mt-4 text-muted text-center">' +
                'Generado el ' + new Date().toLocaleDateString() +
                '</div>';

            // Para secundaria: dividir en 2 hojas carta horizontales
            if (document.querySelector('.nivel-secundaria')) {
                const mitad = Math.ceil(materias.length / 2);
                const primeraMitad = table.rows[0].cells.slice(0, mitad * 4 + 1);
                const segundaMitad = table.rows[0].cells.slice(mitad * 4 + 1);
                
                container.innerHTML = `
                    <div class="header-section">
                        <h4>Reporte Anual - Primera Parte</h4>
                    </div>
                    <div class="table-responsive">
                        ${primeraMitad.outerHTML}
                    </div>
                    <div class="footer-section">
                        Generado el ${new Date().toLocaleDateString()}
                    </div>
                    
                    <div style="page-break-before: always;"></div>
                    
                    <div class="header-section">
                        <h4>Reporte Anual - Segunda Parte</h4>
                    </div>
                    <div class="table-responsive">
                        ${segundaMitad.outerHTML}
                    </div>
                    <div class="footer-section">
                        Generado el ${new Date().toLocaleDateString()}
                    </div>
                `;
            } else {
                container.innerHTML = `
                    <div class="header-section">
                        <h4>Reporte Anual</h4>
                    </div>
                    <div class="table-responsive">
                        ${table.outerHTML}
                    </div>
                    <div class="footer-section">
                        Generado el ${new Date().toLocaleDateString()}
                    </div>
                `;
            }
            
            return container.innerHTML;
        }

    </script>



</body>

</html>