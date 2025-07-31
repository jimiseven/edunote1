<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once '../config/database.php';
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill, Color};
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    http_response_code(403);
    exit("Acceso no autorizado.");
}

$id_curso = (int)($_GET['id'] ?? 0);
$trimestre = (int)($_GET['trimestre'] ?? 1);
if ($id_curso <= 0) exit("ID de curso inválido.");

try {
    $db = new Database();
    $conn = $db->connect();

    // Datos del curso
    $stmt = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
    $stmt->execute([$id_curso]);
    $curso = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$curso) exit("Curso no encontrado.");
    $nombre_curso = "{$curso['nivel']} {$curso['curso']} \"{$curso['paralelo']}\"";

    // Estudiantes
    $stmt = $conn->prepare("
        SELECT id_estudiante, CONCAT(apellido_paterno, ' ', apellido_materno, ', ', nombres) AS nombre_completo
        FROM estudiantes 
        WHERE id_curso = ? 
        ORDER BY apellido_paterno, apellido_materno, nombres
    ");
    $stmt->execute([$id_curso]);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Materias
    $stmt = $conn->prepare("
        SELECT m.id_materia, m.nombre_materia, m.es_extra, m.materia_padre_id 
        FROM cursos_materias cm 
        JOIN materias m ON cm.id_materia = m.id_materia 
        WHERE cm.id_curso = ? 
        ORDER BY m.materia_padre_id, m.nombre_materia
    ");
    $stmt->execute([$id_curso]);
    $materias_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Clasificación de materias
    $materias = [];
    $hijas_por_padre = [];
    $todas_materias = [];

    foreach ($materias_raw as $mat) {
        $todas_materias[$mat['id_materia']] = $mat;
        if ($mat['materia_padre_id']) {
            $hijas_por_padre[$mat['materia_padre_id']][] = $mat;
        } else {
            $materias[$mat['id_materia']] = $mat;
        }
    }

    // Crear Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Estilos para replicar vista_tri.php
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => '212529']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];

    $parentHeaderStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'textRotation' => 90
        ],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];

    $childHeaderStyle = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'textRotation' => 90
        ],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];

    $lowGradeStyle = [
        'font' => ['color' => ['rgb' => 'DC3545'], 'bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF5F5']]
    ];

    $highGradeStyle = [
        'font' => ['color' => ['rgb' => '28A745'], 'bold' => true]
    ];

    // Configurar anchos de columnas
    $sheet->getColumnDimension('A')->setWidth(5); // Columna N°
    $sheet->getColumnDimension('B')->setWidth(30); // Columna Estudiante

    // Encabezados
    $sheet->setCellValue('A1', "CENTRALIZADOR - $nombre_curso - Trimestre $trimestre");
    $sheet->mergeCells('A1:Z1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', 'N°')->getStyle('A2')->applyFromArray($headerStyle);
    $sheet->setCellValue('B2', 'Estudiante')->getStyle('B2')->applyFromArray($headerStyle);

    $colIndex = 3;
    $columnasMaterias = [];

    // Ajustar altura de fila para encabezados
    $sheet->getRowDimension(2)->setRowHeight(100);

    // Procesar materias para encabezados
    foreach ($materias as $mat) {
        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
        
        if (!empty($mat['hijas'])) {
            // Materia padre
            $sheet->setCellValue($colLetter.'2', $mat['nombre_materia'])
                ->getStyle($colLetter.'2')->applyFromArray($parentHeaderStyle);
            $columnasMaterias[$colIndex] = ['tipo' => 'padre', 'materia' => $mat];
            $colIndex++;

            // Materias hijas
            foreach ($mat['hijas'] as $hija) {
                $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($colLetter.'2', $hija['nombre_materia'])
                    ->getStyle($colLetter.'2')->applyFromArray($childHeaderStyle);
                $columnasMaterias[$colIndex] = ['tipo' => 'hija', 'materia' => $hija];
                $colIndex++;
            }
        } else {
            // Materias sin hijas
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter.'2', $mat['nombre_materia'])
                ->getStyle($colLetter.'2')->applyFromArray($parentHeaderStyle);
            $columnasMaterias[$colIndex] = ['tipo' => 'normal', 'materia' => $mat];
            $colIndex++;
        }
    }

    // Llenar datos de estudiantes
    $row = 3;
    foreach ($estudiantes as $i => $est) {
        $sheet->setCellValue('A'.$row, $i+1)->getStyle('A'.$row)->applyFromArray($headerStyle);
        $sheet->setCellValue('B'.$row, strtoupper($est['nombre_completo']))->getStyle('B'.$row)->applyFromArray($headerStyle);

        foreach ($columnasMaterias as $col => $info) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $id_materia = $info['materia']['id_materia'];
            
            $stmt = $conn->prepare("
                SELECT calificacion FROM calificaciones 
                WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
            ");
            $stmt->execute([$est['id_estudiante'], $id_materia, $trimestre]);
            $nota = $stmt->fetchColumn();

            $cell = $colLetter.$row;
            $sheet->setCellValue($cell, is_numeric($nota) ? $nota : '-');
            
            // Aplicar estilos condicionales
            if (is_numeric($nota)) {
                if ($nota < 51) {
                    $sheet->getStyle($cell)->applyFromArray($lowGradeStyle);
                } elseif ($nota > 89) {
                    $sheet->getStyle($cell)->applyFromArray($highGradeStyle);
                }
            }
        }
        $row++;
    }

    // Descargar Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment;filename=\"Centralizador_{$nombre_curso}_T{$trimestre}.xlsx\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    http_response_code(500);
    exit("Error al generar Excel: " . $e->getMessage());
}
