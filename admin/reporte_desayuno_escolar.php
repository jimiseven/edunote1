<?php
session_start();
require_once '../config/database.php';
require_once '../includes/asistencia_auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();
$userRole = (int)($_SESSION['user_role'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$lectorInfo = asistencia_auth_get_lector($conn, $userId);

if (!asistencia_auth_puede_ver_reportes($userRole, $lectorInfo)) {
    http_response_code(403);
    echo '<h3>Acceso denegado</h3><p>No tienes permisos para ver el reporte de desayuno escolar.</p>';
    exit();
}

$nivelesPermitidos = ['Inicial', 'Primaria', 'Secundaria'];
$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $fecha = date('Y-m-d');
}

$sql = "SELECT
        c.id_curso,
        c.nivel,
        c.curso,
        c.paralelo,
        COUNT(e.id_estudiante) AS total_estudiantes,
        COUNT(DISTINCT CASE WHEN a.id_asistencia IS NOT NULL THEN e.id_estudiante END) AS llegaron,
        COUNT(DISTINCT CASE WHEN a.id_asistencia IS NULL AND ap.id_permiso IS NOT NULL THEN e.id_estudiante END) AS faltaron_con_permiso
    FROM cursos c
    LEFT JOIN estudiantes e ON e.id_curso = c.id_curso
    LEFT JOIN asistencia a ON a.id_estudiante = e.id_estudiante AND a.fecha = ?
    LEFT JOIN asistencia_permisos ap ON ap.id_estudiante = e.id_estudiante AND ap.fecha = ? AND ap.estado = 'APROBADO'
    GROUP BY c.id_curso, c.nivel, c.curso, c.paralelo
    ORDER BY FIELD(c.nivel, 'Inicial', 'Primaria', 'Secundaria'), c.curso, c.paralelo";

$stmt = $conn->prepare($sql);
$stmt->execute([$fecha, $fecha]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtPermisosDetalle = $conn->prepare("SELECT
        ap.id_permiso,
        ap.fecha,
        ap.motivo,
        ap.detalle,
        e.id_estudiante,
        e.nombres,
        e.apellido_paterno,
        e.apellido_materno,
        c.nivel,
        c.curso,
        c.paralelo
    FROM asistencia_permisos ap
    INNER JOIN estudiantes e ON e.id_estudiante = ap.id_estudiante
    INNER JOIN cursos c ON c.id_curso = e.id_curso
    WHERE ap.fecha = ?
      AND ap.estado = 'APROBADO'
    ORDER BY FIELD(c.nivel, 'Inicial', 'Primaria', 'Secundaria'), c.curso, c.paralelo, e.apellido_paterno, e.apellido_materno, e.nombres");
$stmtPermisosDetalle->execute([$fecha]);
$permisosDetalle = $stmtPermisosDetalle->fetchAll(PDO::FETCH_ASSOC);

$totales = [
    'estudiantes' => 0,
    'llegaron' => 0,
    'faltaron' => 0,
    'faltaron_con_permiso' => 0,
    'faltaron_sin_permiso' => 0,
];

foreach ($rows as &$row) {
    $row['total_estudiantes'] = (int)$row['total_estudiantes'];
    $row['llegaron'] = (int)$row['llegaron'];
    $row['faltaron_con_permiso'] = (int)$row['faltaron_con_permiso'];
    $row['faltaron'] = max($row['total_estudiantes'] - $row['llegaron'], 0);
    $row['faltaron_con_permiso'] = min($row['faltaron_con_permiso'], $row['faltaron']);
    $row['faltaron_sin_permiso'] = max($row['faltaron'] - $row['faltaron_con_permiso'], 0);

    $totales['estudiantes'] += $row['total_estudiantes'];
    $totales['llegaron'] += $row['llegaron'];
    $totales['faltaron'] += $row['faltaron'];
    $totales['faltaron_con_permiso'] += $row['faltaron_con_permiso'];
    $totales['faltaron_sin_permiso'] += $row['faltaron_sin_permiso'];
}
unset($row);

if (($_GET['export'] ?? '') === 'excel') {
    require_once '../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Desayuno Escolar');

    $sheetPermisos = $spreadsheet->createSheet();
    $sheetPermisos->setTitle('Permisos Inasistencia');

    $spreadsheet->getProperties()
        ->setCreator('Sistema Edunote')
        ->setTitle('Reporte Desayuno Escolar')
        ->setSubject('Asistencia y desayuno');

    $sheet->mergeCells('A1:G1');
    $sheet->setCellValue('A1', 'REPORTE DE DESAYUNO ESCOLAR - ' . $fecha);
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 14],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);

    $sheet->mergeCells('A2:G2');
    $sheet->setCellValue('A2', 'Totales: Estudiantes ' . $totales['estudiantes'] . ' | Llegaron ' . $totales['llegaron'] . ' | Faltaron ' . $totales['faltaron'] . ' | Con permiso ' . $totales['faltaron_con_permiso'] . ' | Sin permiso ' . $totales['faltaron_sin_permiso']);
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    ];

    $cellStyle = [
        'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    ];

    $nivelStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => '1F4E78']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCE6F1']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    ];

    $sheet->fromArray(['#', 'Curso', 'Total estudiantes', 'Llegaron', 'Faltaron', 'Faltaron con permiso', 'Faltaron sin permiso'], null, 'A4');
    $sheet->getStyle('A4:G4')->applyFromArray($headerStyle);

    $rowNum = 5;
    $contador = 1;
    $actualNivel = '';

    foreach ($rows as $row) {
        if ($actualNivel !== $row['nivel']) {
            $actualNivel = $row['nivel'];
            $sheet->mergeCells('A' . $rowNum . ':G' . $rowNum);
            $sheet->setCellValue('A' . $rowNum, 'NIVEL: ' . strtoupper($actualNivel));
            $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->applyFromArray($nivelStyle);
            $rowNum++;
        }

        $sheet->setCellValue('A' . $rowNum, $contador);
        $sheet->setCellValue('B' . $rowNum, $row['nivel'] . ' ' . $row['curso'] . ' "' . $row['paralelo'] . '"');
        $sheet->setCellValue('C' . $rowNum, (int)$row['total_estudiantes']);
        $sheet->setCellValue('D' . $rowNum, (int)$row['llegaron']);
        $sheet->setCellValue('E' . $rowNum, (int)$row['faltaron']);
        $sheet->setCellValue('F' . $rowNum, (int)$row['faltaron_con_permiso']);
        $sheet->setCellValue('G' . $rowNum, (int)$row['faltaron_sin_permiso']);
        $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->applyFromArray($cellStyle);
        $sheet->getStyle('C' . $rowNum . ':G' . $rowNum)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

        $contador++;
        $rowNum++;
    }

    $sheet->setCellValue('A' . $rowNum, 'TOTAL');
    $sheet->mergeCells('A' . $rowNum . ':B' . $rowNum);
    $sheet->setCellValue('C' . $rowNum, (int)$totales['estudiantes']);
    $sheet->setCellValue('D' . $rowNum, (int)$totales['llegaron']);
    $sheet->setCellValue('E' . $rowNum, (int)$totales['faltaron']);
    $sheet->setCellValue('F' . $rowNum, (int)$totales['faltaron_con_permiso']);
    $sheet->setCellValue('G' . $rowNum, (int)$totales['faltaron_sin_permiso']);
    $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->applyFromArray($headerStyle);

    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(12);
    $sheet->getColumnDimension('E')->setWidth(12);
    $sheet->getColumnDimension('F')->setWidth(20);
    $sheet->getColumnDimension('G')->setWidth(20);

    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
    $sheet->getPageSetup()->setFitToWidth(1);
    $sheet->getPageSetup()->setFitToHeight(0);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Desayuno_Escolar_' . $fecha . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

    $sheetPermisos->mergeCells('A1:E1');
    $sheetPermisos->setCellValue('A1', 'ESTUDIANTES CON PERMISO DE INASISTENCIA - ' . $fecha);
    $sheetPermisos->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 13],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);
    $sheetPermisos->fromArray(['#', 'Curso', 'Estudiante', 'Motivo', 'Detalle'], null, 'A3');
    $sheetPermisos->getStyle('A3:E3')->applyFromArray($headerStyle);

    $permRow = 4;
    foreach ($permisosDetalle as $idx => $perm) {
        $sheetPermisos->setCellValue('A' . $permRow, $idx + 1);
        $sheetPermisos->setCellValue('B' . $permRow, $perm['nivel'] . ' ' . $perm['curso'] . ' "' . $perm['paralelo'] . '"');
        $sheetPermisos->setCellValue('C' . $permRow, trim($perm['apellido_paterno'] . ' ' . $perm['apellido_materno'] . ', ' . $perm['nombres']) . ' (ID ' . (int)$perm['id_estudiante'] . ')');
        $sheetPermisos->setCellValue('D' . $permRow, (string)$perm['motivo']);
        $sheetPermisos->setCellValue('E' . $permRow, (string)($perm['detalle'] ?? ''));
        $sheetPermisos->getStyle('A' . $permRow . ':E' . $permRow)->applyFromArray($cellStyle);
        $permRow++;
    }

    if (empty($permisosDetalle)) {
        $sheetPermisos->mergeCells('A4:E4');
        $sheetPermisos->setCellValue('A4', 'No hay estudiantes con permiso para la fecha seleccionada.');
        $sheetPermisos->getStyle('A4:E4')->applyFromArray($cellStyle);
    }

    $sheetPermisos->getColumnDimension('A')->setWidth(6);
    $sheetPermisos->getColumnDimension('B')->setWidth(24);
    $sheetPermisos->getColumnDimension('C')->setWidth(42);
    $sheetPermisos->getColumnDimension('D')->setWidth(24);
    $sheetPermisos->getColumnDimension('E')->setWidth(42);
    $sheetPermisos->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheetPermisos->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
    $sheetPermisos->getPageSetup()->setFitToWidth(1);
    $sheetPermisos->getPageSetup()->setFitToHeight(0);

    $spreadsheet->setActiveSheetIndex(0);
    $writer->save('php://output');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Desayuno Escolar</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            main {
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <main class="w-100 px-md-4 position-relative py-4">
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h1 class="h3 mb-0">Reporte de Desayuno Escolar</h1>
                    <a class="btn btn-success" href="?fecha=<?= urlencode($fecha) ?>&export=excel">
                        Descargar Excel
                    </a>
                </div>

                <div class="card mb-4 no-print">
                    <div class="card-body">
                        <form class="row g-3" method="GET" action="">
                            <div class="col-md-4">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" name="fecha" value="<?= htmlspecialchars($fecha) ?>" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Generar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        Resumen general - <?= htmlspecialchars($fecha) ?>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-light">
                                    <div class="text-muted small">Total estudiantes</div>
                                    <div class="h4 mb-0"><?= (int)$totales['estudiantes'] ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-light">
                                    <div class="text-muted small">Llegaron</div>
                                    <div class="h4 mb-0 text-success"><?= (int)$totales['llegaron'] ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-light">
                                    <div class="text-muted small">Faltaron</div>
                                    <div class="h4 mb-0 text-danger"><?= (int)$totales['faltaron'] ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-light">
                                    <div class="text-muted small">Faltaron con permiso</div>
                                    <div class="h4 mb-0 text-primary"><?= (int)$totales['faltaron_con_permiso'] ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-light">
                                    <div class="text-muted small">Faltaron sin permiso</div>
                                    <div class="h4 mb-0 text-danger"><?= (int)$totales['faltaron_sin_permiso'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Detalle por curso</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Curso</th>
                                        <th class="text-end">Total estudiantes</th>
                                        <th class="text-end">Llegaron</th>
                                        <th class="text-end">Faltaron</th>
                                        <th class="text-end">Faltaron con permiso</th>
                                        <th class="text-end">Faltaron sin permiso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No hay cursos para el filtro seleccionado.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $i => $row): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars($row['nivel'] . ' ' . $row['curso'] . ' "' . $row['paralelo'] . '"') ?></td>
                                                <td class="text-end"><?= (int)$row['total_estudiantes'] ?></td>
                                                <td class="text-end text-success fw-semibold"><?= (int)$row['llegaron'] ?></td>
                                                <td class="text-end text-danger fw-semibold"><?= (int)$row['faltaron'] ?></td>
                                                <td class="text-end text-primary fw-semibold"><?= (int)$row['faltaron_con_permiso'] ?></td>
                                                <td class="text-end text-danger fw-semibold"><?= (int)$row['faltaron_sin_permiso'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="table-light fw-semibold">
                                    <tr>
                                        <td colspan="2">TOTAL</td>
                                        <td class="text-end"><?= (int)$totales['estudiantes'] ?></td>
                                        <td class="text-end text-success"><?= (int)$totales['llegaron'] ?></td>
                                        <td class="text-end text-danger"><?= (int)$totales['faltaron'] ?></td>
                                        <td class="text-end text-primary"><?= (int)$totales['faltaron_con_permiso'] ?></td>
                                        <td class="text-end text-danger"><?= (int)$totales['faltaron_sin_permiso'] ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">Estudiantes con permiso de inasistencia</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Curso</th>
                                        <th>Estudiante</th>
                                        <th>Motivo</th>
                                        <th>Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($permisosDetalle)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">No hay estudiantes con permiso para la fecha seleccionada.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($permisosDetalle as $i => $perm): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars($perm['nivel'] . ' ' . $perm['curso'] . ' "' . $perm['paralelo'] . '"') ?></td>
                                                <td><?= htmlspecialchars(trim($perm['apellido_paterno'] . ' ' . $perm['apellido_materno'] . ', ' . $perm['nombres']) . ' (ID ' . (int)$perm['id_estudiante'] . ')') ?></td>
                                                <td><?= htmlspecialchars((string)$perm['motivo']) ?></td>
                                                <td><?= htmlspecialchars((string)($perm['detalle'] ?? '')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
