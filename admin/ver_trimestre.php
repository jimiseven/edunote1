<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2])) {
    header('Location: ../index.php');
    exit();
}

$id_curso = $_GET['id_curso'] ?? 0;
$trimestre = $_GET['trimestre'] ?? 1;

$conn = (new Database())->connect();

// 1. Obtener información del curso
$stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
$stmt_curso->execute([$id_curso]);
$curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);
$nombre_curso = $curso_info['nivel'] . ' ' . $curso_info['curso'] . ' "' . $curso_info['paralelo'] . '"';

// 2. Obtener estudiantes ordenados alfabéticamente
$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, apellido_paterno, apellido_materno, nombres 
    FROM estudiantes 
    WHERE id_curso = ? 
    ORDER BY apellido_paterno, apellido_materno, nombres
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// 3. Clasificación de materias
$stmt_materias = $conn->prepare("
    SELECT m.id_materia, m.nombre_materia, m.es_extra, m.es_submateria, m.materia_padre_id
    FROM cursos_materias cm 
    JOIN materias m ON cm.id_materia = m.id_materia 
    WHERE cm.id_curso = ?
");
$stmt_materias->execute([$id_curso]);
$todas_materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

$materias_padre = $materias_extra = $materias_hijas = [];
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

$materias_padre_simples = [];
$materias_padre_con_hijas = [];
foreach ($materias_padre as $padre) {
    empty($padre['hijas']) ? $materias_padre_simples[] = $padre : $materias_padre_con_hijas[] = $padre;
}

// 4. Orden final de visualización
$materias = array_merge(
    $materias_padre_simples,
    $materias_extra,
    array_reduce($materias_padre_con_hijas, function ($carry, $padre) {
        return array_merge($carry, [$padre], $padre['hijas']);
    }, [])
);

// 5. Calificaciones y promedios
$calificaciones = [];
foreach ($estudiantes as $estudiante) {
    foreach ($todas_materias as $materia) {
        $stmt = $conn->prepare("
            SELECT calificacion 
            FROM calificaciones 
            WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
        ");
        $stmt->execute([$estudiante['id_estudiante'], $materia['id_materia'], $trimestre]);
        $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']] = $stmt->fetchColumn() ?? '';
    }
}

foreach ($estudiantes as $estudiante) {
    foreach ($materias_padre_con_hijas as $padre) {
        $suma = $cont = 0;
        foreach ($padre['hijas'] as $hija) {
            $nota = $calificaciones[$estudiante['id_estudiante']][$hija['id_materia']] ?? '';
            if ($nota !== '') {
                $suma += floatval($nota);
                $cont++;
            }
        }
        $calificaciones[$estudiante['id_estudiante']][$padre['id_materia']] = $cont > 0 ? number_format($suma / $cont, 2) : '';
    }
}

$promedios_trimestre = [];
foreach ($estudiantes as $estudiante) {
    $suma = $contador = 0;
    foreach ($materias as $mat) {
        if ($mat['es_extra'] == 1 || isset($mat['materia_padre_id']))
            continue;
        $nota = $calificaciones[$estudiante['id_estudiante']][$mat['id_materia']] ?? '';
        if ($nota !== '') {
            $suma += floatval($nota);
            $contador++;
        }
    }
    $promedios_trimestre[$estudiante['id_estudiante']] = $contador > 0 ? number_format($suma / $contador, 2) : '-';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista Trimestral - <?= htmlspecialchars($nombre_curso) ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
        }

        body {
            background: #f8f9fa;
            margin-left: var(--sidebar-width);
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #2c3e50;
            padding: 20px;
            z-index: 1000;
        }

        .main-content {
            padding: 20px;
        }

        .student-name {
            min-width: 220px;
            background: #fff;
            position: sticky;
            left: 0;
            z-index: 2;
        }

        .table-responsive {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }

        .padre-th {
            background: #e9ecef !important;
            color: #2c3e50 !important;
            font-weight: 600;
        }

        .hija-th {
            background: #f8f9fa !important;
            color: #6c757d !important;
            font-style: italic;
        }

        .extra-th {
            background: #e6f4ff !important;
            color: #0d6efd !important;
        }

        .table td.nota-baja {
            color: #dc3545 !important;
            font-weight: 600 !important;
        }

        @media print {

            .sidebar,
            .no-print {
                display: none !important;
            }

            body {
                margin-left: 0 !important;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('pdfBtn').addEventListener('click', function() {
                // Verificar que jspdf esté cargado
                if (typeof jspdf === 'undefined') {
                    console.error('jsPDF no está cargado');
                    return;
                }

                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({
                    orientation: 'landscape',
                    unit: 'mm',
                    format: 'letter'
                });

                // Encabezado
                pdf.setFontSize(14);
                pdf.setTextColor(44, 62, 80);
                pdf.text('U.E. SIMÓN BOLÍVAR', pdf.internal.pageSize.getWidth()/2, 15, {align: 'center'});
                
                pdf.setFontSize(12);
                pdf.setTextColor(30, 61, 115);
                pdf.text('<?= htmlspecialchars($nombre_curso) ?>', pdf.internal.pageSize.getWidth()/2, 20, {align: 'center'});
                
                pdf.setFontSize(10);
                pdf.setTextColor(102, 102, 102);
                pdf.text(`Trimestre <?= $trimestre ?> - ${new Date().getFullYear()}`, pdf.internal.pageSize.getWidth()/2, 25, {align: 'center'});

                // Preparar datos para la tabla
                const headers = [
                    {title: '#', dataKey: 'index'},
                    {title: 'Estudiante', dataKey: 'estudiante'}
                ];

                // Agregar encabezados de materias
                <?php foreach($materias as $mat): ?>
                    headers.push({
                        title: '<?= addslashes($mat['nombre_materia']) ?>',
                        dataKey: 'materia_<?= $mat['id_materia'] ?>'
                    });
                <?php endforeach; ?>
                
                headers.push({title: 'Promedio', dataKey: 'promedio'});

                // Preparar datos de estudiantes
                const body = [];
                <?php foreach($estudiantes as $i => $est): ?>
                    const rowData = {
                        index: <?= $i + 1 ?>,
                        estudiante: '<?= addslashes(strtoupper($est['apellido_paterno'] . ' ' . $est['apellido_materno'] . ', ' . $est['nombres'])) ?>'
                    };
                    
                    <?php foreach($materias as $mat): ?>
                        rowData['materia_<?= $mat['id_materia'] ?>'] = '<?= $calificaciones[$est['id_estudiante']][$mat['id_materia']] ?? '' ?>';
                    <?php endforeach; ?>
                    
                    rowData['promedio'] = '<?= $promedios_trimestre[$est['id_estudiante']] ?>';
                    body.push(rowData);
                <?php endforeach; ?>

                // Configuración de la tabla
                pdf.autoTable({
                    head: [headers.map(h => h.title)],
                    body: body.map(row => headers.map(h => row[h.dataKey])),
                    startY: 30,
                    styles: {
                        fontSize: 8,
                        cellPadding: 1,
                        overflow: 'linebreak'
                    },
                    columnStyles: {
                        0: {cellWidth: 8}, // Columna #
                        1: {cellWidth: 40}, // Columna Estudiante
                        [headers.length - 1]: {cellWidth: 15} // Columna Promedio
                    },
                    didParseCell: (data) => {
                        // Rotar encabezados de materias
                        if (data.section === 'head' && data.column.index > 1 && data.column.index < headers.length - 1) {
                            data.cell.styles.fontStyle = 'bold';
                            data.cell.styles.textColor = [44, 62, 80];
                            data.cell.styles.halign = 'center';
                            data.cell.styles.valign = 'middle';
                            data.cell.text = [data.cell.text[0].split('').join('\n')];
                            data.cell.styles.cellWidth = 8;
                        }
                        
                        // Resaltar notas bajas
                        if (data.section === 'body' && parseFloat(data.cell.text[0]) < 51) {
                            data.cell.styles.textColor = [220, 53, 69];
                            data.cell.styles.fontStyle = 'bold';
                        }
                    }
                });

                pdf.save(`Centralizador-<?= htmlspecialchars($nombre_curso) ?>-T<?= $trimestre ?>.pdf`);
            });
        });
    </script>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar text-white no-print">
        <?php include '../includes/sidebar.php'; ?>
    </div>

    <!-- Contenido Principal -->
    <div class="main-content">
        <!-- Header -->
        <div class="header-controls d-flex justify-content-between align-items-center mb-4 no-print">
            <div class="d-flex align-items-center gap-3">
                <a href="ver_curso.php?id=<?= $id_curso ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <h3 class="mb-0"><?= htmlspecialchars($nombre_curso) ?></h3>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
                <button id="pdfBtn" class="btn btn-primary btn-sm">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </button>
                <a href="exportar_excel.php?id=<?= $id_curso ?>&trimestre=<?= $trimestre ?>" class="btn btn-success">
                    <i class="bi bi-file-excel"></i> Excel
                </a>
            </div>
        </div>

        <!-- Selector de Trimestre -->
        <div class="card mb-4 shadow-sm no-print">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <span class="fw-bold">Trimestre:</span>
                    <div class="btn-group">
                        <?php for ($t = 1; $t <= 3; $t++): ?>
                            <a href="?id_curso=<?= $id_curso ?>&trimestre=<?= $t ?>"
                                class="btn <?= $t == $trimestre ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm">
                                <?= $t ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <span class="badge bg-primary">Trimestre <?= $trimestre ?></span>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th class="student-name">Estudiante</th>
                        <?php foreach ($materias as $mat): ?>
                            <?php
                            $clase = '';
                            if ($mat['es_extra'] == 1)
                                $clase = 'extra-th';
                            elseif (isset($mat['materia_padre_id']))
                                $clase = 'hija-th';
                            elseif (!empty($mat['hijas']))
                                $clase = 'padre-th';
                            ?>
                            <th class="<?= $clase ?>">
                                <?= htmlspecialchars($mat['nombre_materia']) ?>
                                <?= $mat['es_extra'] ? '<small>(Extra)</small>' : '' ?>
                            </th>
                        <?php endforeach; ?>
                        <th>Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $contador = 1; ?>
                    <?php foreach ($estudiantes as $estudiante): ?>
                        <tr>
                            <td><?= $contador++ ?></td>
                            <td class="student-name">
                                <?= htmlspecialchars(strtoupper(
                                    $estudiante['apellido_paterno'] . ' ' .
                                    $estudiante['apellido_materno'] . ', ' .
                                    $estudiante['nombres']
                                )) ?>
                            </td>
                            <?php foreach ($materias as $mat): ?>
                                <?php
                                $nota = $calificaciones[$estudiante['id_estudiante']][$mat['id_materia']] ?? '';
                                $clase = '';
                                if ($mat['es_extra'] == 1)
                                    $clase = 'extra-td';
                                elseif (isset($mat['materia_padre_id']))
                                    $clase = 'hija-td';
                                $clase .= (is_numeric($nota) && floatval($nota) < 51) ? ' nota-baja' : '';
                                ?>
                                <td class="<?= $clase ?>"><?= $nota ?></td>
                            <?php endforeach; ?>
                            <td class="fw-bold"><?= $promedios_trimestre[$estudiante['id_estudiante']] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
