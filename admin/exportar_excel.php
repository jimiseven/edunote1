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

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
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

    // Agregar hijas como submaterias
    foreach ($hijas_por_padre as $id_padre => $hijas) {
        if (isset($materias[$id_padre])) {
            $materias[$id_padre]['hijas'] = $hijas;
        } else {
            // Si el padre no está en materias principales, añadimos las hijas como materias normales
            foreach ($hijas as $hija) {
                if (!isset($materias[$hija['id_materia']])) {
                    $materias[$hija['id_materia']] = $hija;
                }
            }
        }
    }

    // Crear Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Estilos
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_BOTTOM],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];
    $cellStyle = [
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];
    $lowGradeStyle = [
        'font' => ['color' => ['rgb' => 'FF0000']],
    ];
    $extraSubjectStyle = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
    ];
    $parentSubjectStyle = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '92D050']],
    ];
    $childSubjectStyle = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B7DEE8']],
    ];

    // Título centrado
    $sheet->mergeCells('A1:Z1');
    $sheet->setCellValue('A1', "CENTRALIZADOR - $nombre_curso - Trimestre $trimestre");
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Encabezados
    $sheet->setCellValue('A2', 'N°')->getStyle('A2')->applyFromArray($headerStyle);
    $sheet->setCellValue('B2', 'Estudiante')->getStyle('B2')->applyFromArray($headerStyle);
    $sheet->getColumnDimension('A')->setWidth(5);
    $sheet->getColumnDimension('B')->setWidth(40);

    $colIndex = 3;
    $columnasMaterias = []; // clave: columna => info materia

    // Primero procesamos materias padres y sus hijas
    foreach ($materias as $mat) {
        if (!empty($mat['hijas'])) {
            // Materia padre
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $cell = $colLetter . '2';
            $sheet->setCellValue($cell, $mat['nombre_materia']);
            $sheet->getStyle($cell)->applyFromArray(array_merge($headerStyle, $parentSubjectStyle));
            $sheet->getStyle($cell)->getAlignment()->setTextRotation(90);
            $sheet->getColumnDimension($colLetter)->setWidth(6);
            $columnasMaterias[$colIndex] = ['tipo' => 'padre', 'materia' => $mat];
            $colIndex++;

            // Materias hijas
            foreach ($mat['hijas'] as $hija) {
                $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                $cell = $colLetter . '2';
                $sheet->setCellValue($cell, $hija['nombre_materia']);
                $style = $headerStyle;
                if ($hija['es_extra']) {
                    $style = array_merge($headerStyle, $extraSubjectStyle);
                } else {
                    $style = array_merge($headerStyle, $childSubjectStyle);
                }
                $sheet->getStyle($cell)->applyFromArray($style);
                $sheet->getStyle($cell)->getAlignment()->setTextRotation(90);
                $sheet->getColumnDimension($colLetter)->setWidth(6);
                $columnasMaterias[$colIndex] = ['tipo' => 'hija', 'materia' => $hija];
                $colIndex++;
            }
        } elseif (!isset($hijas_por_padre[$mat['id_materia']])) {
            // Materias normales (sin hijas y que no son hijas de otra)
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $cell = $colLetter . '2';
            $sheet->setCellValue($cell, $mat['nombre_materia']);
            $style = $headerStyle;
            if ($mat['es_extra']) {
                $style = array_merge($headerStyle, $extraSubjectStyle);
            }
            $sheet->getStyle($cell)->applyFromArray($style);
            $sheet->getStyle($cell)->getAlignment()->setTextRotation(90);
            $sheet->getColumnDimension($colLetter)->setWidth(6);
            $columnasMaterias[$colIndex] = ['tipo' => 'normal', 'materia' => $mat];
            $colIndex++;
        }
    }

    // Agregar columna de promedio final
    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
    $cell = $colLetter . '2';
    $sheet->setCellValue($cell, 'Promedio');
    $sheet->getStyle($cell)->applyFromArray($headerStyle);
    $sheet->getColumnDimension($colLetter)->setWidth(10);
    $colPromedioFinal = $colIndex;

    // Llenar datos
    $row = 3;
    foreach ($estudiantes as $i => $est) {
        $sheet->setCellValue("A$row", $i + 1);
        $sheet->setCellValue("B$row", strtoupper($est['nombre_completo']));
        $sheet->getStyle("A$row:B$row")->applyFromArray($cellStyle);
        $sheet->getStyle("B$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sum = 0;
        $count = 0;

        foreach ($columnasMaterias as $col => $info) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $cell = $colLetter . $row;
            
            if ($info['tipo'] === 'padre') {
                // Calcular promedio de materias hijas
                $sumHijas = 0;
                $countHijas = 0;
                foreach ($info['materia']['hijas'] as $hija) {
                    $stmt = $conn->prepare("
                        SELECT calificacion FROM calificaciones 
                        WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
                    ");
                    $stmt->execute([$est['id_estudiante'], $hija['id_materia'], $trimestre]);
                    $notaHija = $stmt->fetchColumn();
                    if (is_numeric($notaHija)) {
                        $sumHijas += $notaHija;
                        $countHijas++;
                    }
                }
                $nota = $countHijas > 0 ? round($sumHijas / $countHijas, 2) : '';
                $sheet->setCellValue($cell, $nota);
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('92D050');
            } else {
                // Materias normales o hijas
                $id_materia = $info['materia']['id_materia'];
                $stmt = $conn->prepare("
                    SELECT calificacion FROM calificaciones 
                    WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
                ");
                $stmt->execute([$est['id_estudiante'], $id_materia, $trimestre]);
                $nota = $stmt->fetchColumn();
                $sheet->setCellValue($cell, $nota);
                
                if ($info['tipo'] === 'hija') {
                    if ($info['materia']['es_extra']) {
                        $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
                    } else {
                        $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B7DEE8');
                    }
                } elseif ($info['materia']['es_extra']) {
                    $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
                }

            if (is_numeric($nota) && $nota < 51) {
                $sheet->getStyle($cell)->applyFromArray($lowGradeStyle);
            }

            // Solo sumar al promedio si no es extra (incluye hijas y materias normales, excluye padres)
            if (is_numeric($nota) && !$info['materia']['es_extra'] && $info['tipo'] !== 'padre') {
                $sum += $nota;
                $count++;
            }

            $sheet->getStyle($cell)->applyFromArray($cellStyle);
            }
        }

        // Calcular promedio final (excluyendo materias extras y padres)
        $promedioFinal = $count > 0 ? round($sum / $count, 2) : '';
        $colLetter = Coordinate::stringFromColumnIndex($colPromedioFinal);
        $cell = $colLetter . $row;
        $sheet->setCellValue($cell, $promedioFinal);
        $sheet->getStyle($cell)->applyFromArray($cellStyle);
        $row++;
    }

    // Opción para generar PDF
    if (isset($_GET['pdf'])) {
        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment;filename=\"Centralizador_{$nombre_curso}_T{$trimestre}.pdf\"");
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf($spreadsheet);
        $writer->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
        $writer->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $writer->save('php://output');
    } else {
        // Descargar Excel
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"Centralizador_{$nombre_curso}_T{$trimestre}.xlsx\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }
    exit;

} catch (Exception $e) {
    http_response_code(500);
    exit("Error al generar Excel: " . $e->getMessage());
}
