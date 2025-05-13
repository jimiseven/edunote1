<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once '../config/database.php';
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill, Color};

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

    foreach ($materias_raw as $mat) {
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
            // El padre no está en materias, añadir hijas directamente
            foreach ($hijas as $hija) {
                $materias[$hija['id_materia']] = $hija;
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

    foreach ($materias as $mat) {
        if (!empty($mat['hijas'])) {
            // Calcular promedio de hijas
            $sheet->setCellValueByColumnAndRow($colIndex, 2, $mat['nombre_materia']);
            $sheet->getStyleByColumnAndRow($colIndex, 2)->applyFromArray($headerStyle);
            $sheet->getStyleByColumnAndRow($colIndex, 2)->getAlignment()->setTextRotation(90);
            $sheet->getColumnDimensionByColumn($colIndex)->setWidth(6);
            $columnasMaterias[$colIndex] = ['tipo' => 'promedio_hijas', 'padre' => $mat, 'hijas' => $mat['hijas']];
            $colIndex++;
        } else {
            // Materia sola o hija sin padre listado
            $sheet->setCellValueByColumnAndRow($colIndex, 2, $mat['nombre_materia']);
            $sheet->getStyleByColumnAndRow($colIndex, 2)->applyFromArray($headerStyle);
            $sheet->getStyleByColumnAndRow($colIndex, 2)->getAlignment()->setTextRotation(90);
            $sheet->getColumnDimensionByColumn($colIndex)->setWidth(6);
            $columnasMaterias[$colIndex] = ['tipo' => 'normal', 'materia' => $mat];
            $colIndex++;
        }
    }

    // Agregar columna de promedio final
    $sheet->setCellValueByColumnAndRow($colIndex, 2, 'Promedio');
    $sheet->getStyleByColumnAndRow($colIndex, 2)->applyFromArray($headerStyle);
    $sheet->getColumnDimensionByColumn($colIndex)->setWidth(10);
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
            if ($info['tipo'] === 'normal') {
                $id_materia = $info['materia']['id_materia'];
                $stmt = $conn->prepare("
                    SELECT calificacion FROM calificaciones 
                    WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
                ");
                $stmt->execute([$est['id_estudiante'], $id_materia, $trimestre]);
                $nota = $stmt->fetchColumn();

                if (is_numeric($nota)) {
                    $sum += (!$info['materia']['es_extra']) ? $nota : 0;
                    $count += (!$info['materia']['es_extra']) ? 1 : 0;
                }

                $sheet->setCellValueByColumnAndRow($col, $row, $nota);
                if (is_numeric($nota) && $nota < 51) {
                    $sheet->getStyleByColumnAndRow($col, $row)->applyFromArray($lowGradeStyle);
                }
            } elseif ($info['tipo'] === 'promedio_hijas') {
                $total = 0;
                $subCount = 0;
                foreach ($info['hijas'] as $hija) {
                    $stmt = $conn->prepare("
                        SELECT calificacion FROM calificaciones 
                        WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
                    ");
                    $stmt->execute([$est['id_estudiante'], $hija['id_materia'], $trimestre]);
                    $nota = $stmt->fetchColumn();
                    if (is_numeric($nota)) {
                        $total += $nota;
                        $subCount++;
                    }
                }

                $prom = $subCount > 0 ? round($total / $subCount, 2) : '';
                if (is_numeric($prom)) {
                    $sum += (!$info['padre']['es_extra']) ? $prom : 0;
                    $count += (!$info['padre']['es_extra']) ? 1 : 0;
                }

                $sheet->setCellValueByColumnAndRow($col, $row, $prom);
                if (is_numeric($prom) && $prom < 51) {
                    $sheet->getStyleByColumnAndRow($col, $row)->applyFromArray($lowGradeStyle);
                }
            }

            $sheet->getStyleByColumnAndRow($col, $row)->applyFromArray($cellStyle);
        }

        $promedioFinal = $count > 0 ? round($sum / $count, 2) : '';
        $sheet->setCellValueByColumnAndRow($colPromedioFinal, $row, $promedioFinal);
        $sheet->getStyleByColumnAndRow($colPromedioFinal, $row)->applyFromArray($cellStyle);
        $row++;
    }

    // Descargar
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
