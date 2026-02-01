<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2])) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id_curso'])) {
    header('Location: dashboard.php?error=curso_no_especificado');
    exit();
}

$id_curso = intval($_GET['id_curso']);

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

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$spreadsheet->getProperties()
    ->setCreator('Sistema Edunote')
    ->setTitle('Nómina - ' . $nombre_curso)
    ->setSubject('Nómina');

$titleStyle = [
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
    ],
];

$cellStyle = [
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
    ],
];

$leftCellStyle = $cellStyle;
$leftCellStyle['alignment']['horizontal'] = Alignment::HORIZONTAL_LEFT;

$sheet->mergeCells('A1:D1');
$sheet->setCellValue('A1', 'NÓMINA - ' . $nombre_curso);
$sheet->getStyle('A1')->applyFromArray($titleStyle);
$sheet->getRowDimension(1)->setRowHeight(24);

$sheet->setCellValue('A3', 'N°');
$sheet->setCellValue('B3', 'Apellido Paterno');
$sheet->setCellValue('C3', 'Apellido Materno');
$sheet->setCellValue('D3', 'Nombres');

$sheet->getStyle('A3:D3')->applyFromArray($headerStyle);
$sheet->getRowDimension(3)->setRowHeight(20);

$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(25);

$row = 4;
$contador = 1;
foreach ($estudiantes as $estudiante) {
    $sheet->setCellValue('A' . $row, $contador);
    $sheet->setCellValue('B' . $row, strtoupper((string)($estudiante['apellido_paterno'] ?? '')));
    $sheet->setCellValue('C' . $row, strtoupper((string)($estudiante['apellido_materno'] ?? '')));
    $sheet->setCellValue('D' . $row, strtoupper((string)($estudiante['nombres'] ?? '')));

    $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($cellStyle);
    $sheet->getStyle('B' . $row . ':D' . $row)->applyFromArray($leftCellStyle);

    $sheet->getRowDimension($row)->setRowHeight(18);

    $contador++;
    $row++;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Nomina_' . str_replace(' ', '_', $nombre_curso) . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
