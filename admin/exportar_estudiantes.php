<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 4], true)) {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$conn = $database->connect();

$stmt_estudiantes = $conn->query("
    SELECT
        e.apellido_paterno,
        e.apellido_materno,
        e.nombres,
        e.carnet_identidad AS ci,
        e.genero,
        e.rude,
        e.fecha_nacimiento,
        CONCAT(c.nivel, ' ', c.curso, '° ', c.paralelo) AS nombre_curso
    FROM estudiantes e
    LEFT JOIN cursos c ON e.id_curso = c.id_curso
    ORDER BY e.apellido_paterno ASC, e.apellido_materno ASC, e.nombres ASC
");
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
    ->setTitle('Listado de Estudiantes')
    ->setSubject('Listado de Estudiantes');

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

$sheet->mergeCells('A1:H1');
$sheet->setCellValue('A1', 'LISTADO GENERAL DE ESTUDIANTES');
$sheet->getStyle('A1')->applyFromArray($titleStyle);
$sheet->getRowDimension(1)->setRowHeight(24);

$sheet->setCellValue('A3', 'N°');
$sheet->setCellValue('B3', 'Apellido Paterno');
$sheet->setCellValue('C3', 'Apellido Materno');
$sheet->setCellValue('D3', 'Nombres');
$sheet->setCellValue('E3', 'CI');
$sheet->setCellValue('F3', 'Género');
$sheet->setCellValue('G3', 'RUDE');
$sheet->setCellValue('H3', 'Curso');

$sheet->getStyle('A3:H3')->applyFromArray($headerStyle);
$sheet->getRowDimension(3)->setRowHeight(20);

$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(25);
$sheet->getColumnDimension('E')->setWidth(15);
$sheet->getColumnDimension('F')->setWidth(12);
$sheet->getColumnDimension('G')->setWidth(20);
$sheet->getColumnDimension('H')->setWidth(24);

$row = 4;
$contador = 1;
foreach ($estudiantes as $estudiante) {
    $sheet->setCellValue('A' . $row, $contador);
    $sheet->setCellValue('B' . $row, strtoupper((string)($estudiante['apellido_paterno'] ?? '')));
    $sheet->setCellValue('C' . $row, strtoupper((string)($estudiante['apellido_materno'] ?? '')));
    $sheet->setCellValue('D' . $row, strtoupper((string)($estudiante['nombres'] ?? '')));
    $sheet->setCellValue('E' . $row, (string)($estudiante['ci'] ?? ''));
    $sheet->setCellValue('F' . $row, (string)($estudiante['genero'] ?? ''));
    $sheet->setCellValue('G' . $row, (string)($estudiante['rude'] ?? ''));
    $sheet->setCellValue('H' . $row, (string)($estudiante['nombre_curso'] ?? ''));

    $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($cellStyle);
    $sheet->getStyle('B' . $row . ':D' . $row)->applyFromArray($leftCellStyle);
    $sheet->getStyle('H' . $row)->applyFromArray($leftCellStyle);

    $sheet->getRowDimension($row)->setRowHeight(18);

    $contador++;
    $row++;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Listado_Estudiantes.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
