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
if ($id_curso <= 0) exit("ID de curso inválido.");

try {
    $db = new Database();
    $conn = $db->connect();

    // Obtener información del curso
    $stmt = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
    $stmt->execute([$id_curso]);
    $curso = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$curso) exit("Curso no encontrado.");
    $nombre_curso = "{$curso['nivel']} {$curso['curso']} \"{$curso['paralelo']}\"";

    // Obtener materias con jerarquía
    $stmt_materias = $conn->prepare("
        SELECT 
            m.id_materia, 
            m.nombre_materia, 
            m.es_extra,
            m.materia_padre_id,
            mp.nombre_materia AS nombre_padre
        FROM cursos_materias cm
        JOIN materias m ON cm.id_materia = m.id_materia
        LEFT JOIN materias mp ON m.materia_padre_id = mp.id_materia
        WHERE cm.id_curso = ?
        ORDER BY m.materia_padre_id, m.nombre_materia
    ");
    $stmt_materias->execute([$id_curso]);
    $materias_raw = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

    // Organizar materias: padres e hijas
    $materias_padres = [];
    $materias_hijas = [];
    foreach ($materias_raw as $mat) {
        if ($mat['materia_padre_id']) {
            $materias_hijas[$mat['materia_padre_id']][] = $mat;
        } else {
            $materias_padres[$mat['id_materia']] = $mat;
        }
    }

    // Obtener estudiantes
    $stmt_estudiantes = $conn->prepare("
        SELECT id_estudiante, nombres, apellido_paterno, apellido_materno
        FROM estudiantes 
        WHERE id_curso = ? 
        ORDER BY apellido_paterno, apellido_materno, nombres
    ");
    $stmt_estudiantes->execute([$id_curso]);
    $estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

    // Función para obtener notas
    function obtenerNotas($conn, $id_estudiante, $id_materia) {
        $notas = [];
        for ($trim = 1; $trim <= 3; $trim++) {
            $stmt = $conn->prepare("
                SELECT calificacion 
                FROM calificaciones 
                WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
            ");
            $stmt->execute([$id_estudiante, $id_materia, $trim]);
            $nota = $stmt->fetchColumn();
            $notas[$trim] = $nota !== false ? $nota : null;
        }
        return $notas;
    }

    // Crear Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Estilos
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];

    $parentHeaderStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];

    $childHeaderStyle = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];

    $lowGradeStyle = [
        'font' => ['color' => ['rgb' => 'DC3545'], 'bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF5F5']]
    ];

    $anualStyle = [
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4F4E9']],
        'font' => ['bold' => true]
    ];

    // Configurar anchos de columnas
    $sheet->getColumnDimension('A')->setWidth(5); // N°
    $sheet->getColumnDimension('B')->setWidth(40); // Estudiante

    // Título
    $sheet->setCellValue('A1', "CENTRALIZADOR - $nombre_curso");
    $sheet->mergeCells('A1:Z1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Encabezados
    $sheet->setCellValue('A2', 'N°')->getStyle('A2')->applyFromArray($headerStyle);
    $sheet->setCellValue('B2', 'Estudiante')->getStyle('B2')->applyFromArray($headerStyle);

    // Organizar columnas de materias
    $columnas = [];
    foreach ($materias_padres as $id_padre => $padre) {
        $columnas[] = ['tipo' => 'padre', 'datos' => $padre];
        foreach ($materias_hijas[$id_padre] ?? [] as $hija) {
            $columnas[] = ['tipo' => 'hija', 'datos' => $hija];
        }
    }

    // Encabezados de materias
    $colIndex = 3;
    foreach ($columnas as $col) {
        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($colLetter.'2', $col['datos']['nombre_materia'])
            ->getStyle($colLetter.'2')->applyFromArray(
                $col['tipo'] == 'padre' ? $parentHeaderStyle : $childHeaderStyle
            );
        $sheet->mergeCells($colLetter.'2:'.Coordinate::stringFromColumnIndex($colIndex+3).'2');
        
        // Subencabezados (T1, T2, T3, Prom.)
        $sheet->setCellValue($colLetter.'3', 'T1')
            ->getStyle($colLetter.'3')->applyFromArray($headerStyle);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex+1).'3', 'T2')
            ->getStyle(Coordinate::stringFromColumnIndex($colIndex+1).'3')->applyFromArray($headerStyle);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex+2).'3', 'T3')
            ->getStyle(Coordinate::stringFromColumnIndex($colIndex+2).'3')->applyFromArray($headerStyle);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex+3).'3', 'Prom.')
            ->getStyle(Coordinate::stringFromColumnIndex($colIndex+3).'3')->applyFromArray($headerStyle);
        
        $colIndex += 4;
    }

    // Datos de estudiantes
    $row = 4;
    foreach ($estudiantes as $i => $est) {
        $sheet->setCellValue('A'.$row, $i+1);
        $sheet->setCellValue('B'.$row, strtoupper(
            $est['apellido_paterno'].' '.$est['apellido_materno'].', '.$est['nombres']
        ));

        $colIndex = 3;
        foreach ($columnas as $col) {
            $materia_id = $col['datos']['id_materia'];
            $notas = obtenerNotas($conn, $est['id_estudiante'], $materia_id);
            
            // Calcular promedio anual
            $sum = 0;
            $count = 0;
            for ($trim = 1; $trim <= 3; $trim++) {
                if ($notas[$trim] !== null) {
                    $sum += $notas[$trim];
                    $count++;
                }
            }
            $promedio = $count > 0 ? round($sum / $count, 2) : null;

            // Escribir notas
            for ($trim = 1; $trim <= 3; $trim++) {
                $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($colLetter.$row, $notas[$trim] !== null ? $notas[$trim] : '');
                
                // Aplicar estilo si nota baja
                if ($notas[$trim] !== null && $notas[$trim] < 51) {
                    $sheet->getStyle($colLetter.$row)->applyFromArray($lowGradeStyle);
                }
                
                $colIndex++;
            }
            
            // Escribir promedio anual
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter.$row, $promedio !== null ? $promedio : '');
            $sheet->getStyle($colLetter.$row)->applyFromArray($anualStyle);
            
            // Aplicar estilo si promedio bajo
            if ($promedio !== null && $promedio < 51) {
                $sheet->getStyle($colLetter.$row)->applyFromArray($lowGradeStyle);
            }
            
            $colIndex++;
        }
        $row++;
    }

    // Descargar Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment;filename=\"Centralizador_{$nombre_curso}.xlsx\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    http_response_code(500);
    exit("Error al generar Excel: " . $e->getMessage());
}
