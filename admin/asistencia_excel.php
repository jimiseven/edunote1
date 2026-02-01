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
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

$casillas = 20;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$spreadsheet->getProperties()
    ->setCreator('Sistema Edunote')
    ->setTitle('Asistencia - ' . $nombre_curso)
    ->setSubject('Asistencia');

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

$lastColIndex = 2 + $casillas;
$lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);

$sheet->mergeCells('A1:' . $lastColLetter . '1');
$sheet->setCellValue('A1', 'ASISTENCIA - ' . $nombre_curso);
$sheet->getStyle('A1')->applyFromArray($titleStyle);
$sheet->getRowDimension(1)->setRowHeight(24);

$sheet->setCellValue('A3', 'N°');
$sheet->setCellValue('B3', 'Estudiante');

for ($i = 1; $i <= $casillas; $i++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $i);
    $sheet->setCellValue($colLetter . '3', (string)$i);
}

$sheet->getStyle('A3:' . $lastColLetter . '3')->applyFromArray($headerStyle);
$sheet->getRowDimension(3)->setRowHeight(20);

$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(40);

for ($i = 1; $i <= $casillas; $i++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $i);
    $sheet->getColumnDimension($colLetter)->setWidth(2.3);
}

$row = 4;
$contador = 1;
foreach ($estudiantes as $estudiante) {
    $sheet->setCellValue('A' . $row, $contador);

    $apellido_paterno = trim((string)($estudiante['apellido_paterno'] ?? ''));
    $apellido_materno = trim((string)($estudiante['apellido_materno'] ?? ''));
    $nombres = trim((string)($estudiante['nombres'] ?? ''));
    $nombre_completo = trim($apellido_paterno . ' ' . $apellido_materno);
    if ($nombres !== '') {
        $nombre_completo = trim($nombre_completo . ', ' . $nombres);
    }
    $sheet->setCellValue('B' . $row, strtoupper($nombre_completo));

    for ($i = 1; $i <= $casillas; $i++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $i);
        $sheet->setCellValue($colLetter . $row, '');
    }

    $sheet->getStyle('A' . $row . ':' . $lastColLetter . $row)->applyFromArray($cellStyle);
    $sheet->getStyle('B' . $row)->applyFromArray($leftCellStyle);

    $sheet->getRowDimension($row)->setRowHeight(18);

    $contador++;
    $row++;
}

$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Asistencia_' . str_replace(' ', '_', $nombre_curso) . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
