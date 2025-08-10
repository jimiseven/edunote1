<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2])) {
    header('Location: ../index.php');
    exit();
}

// Obtener ID del curso
if (!isset($_GET['id_curso'])) {
    header('Location: dashboard.php?error=curso_no_especificado');
    exit();
}

$id_curso = intval($_GET['id_curso']);

$database = new Database();
$conn = $database->connect();

// Obtener información del curso
$stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
$stmt_curso->execute([$id_curso]);

if ($stmt_curso->rowCount() == 0) {
    header('Location: dashboard.php?error=curso_no_encontrado');
    exit();
}
$curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);
$nombre_curso = "{$curso_info['nivel']} {$curso_info['curso']} \"{$curso_info['paralelo']}\"";

// Obtener estudiantes
$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, apellido_paterno, apellido_materno, nombres 
    FROM estudiantes 
    WHERE id_curso = ?
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// Obtener materias
$stmt_materias = $conn->prepare("
    SELECT m.id_materia, m.nombre_materia, m.es_extra, m.es_submateria, m.materia_padre_id
    FROM cursos_materias cm 
    JOIN materias m ON cm.id_materia = m.id_materia 
    WHERE cm.id_curso = ? 
    ORDER BY m.nombre_materia
");
$stmt_materias->execute([$id_curso]);
$todas_materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// Reorganizar materias (igual que en ver_curso.php)
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

// Asociar hijas con padres
foreach ($materias_hijas as $hija) {
    if (isset($materias_padre[$hija['materia_padre_id']])) {
        $materias_padre[$hija['materia_padre_id']]['hijas'][] = $hija;
    }
}

// Separar padres simples y con hijas
$materias_padre_simples = [];
$materias_padre_con_hijas = [];
foreach ($materias_padre as $padre) {
    if (empty($padre['hijas'])) {
        $materias_padre_simples[] = $padre;
    } else {
        $materias_padre_con_hijas[] = $padre;
    }
}

// Orden final
$materias = array_merge(
    $materias_padre_simples,
    $materias_extra,
    $materias_padre_con_hijas
);

// Añadir hijas después de sus padres
foreach ($materias_padre_con_hijas as $padre) {
    $materias = array_merge($materias, $padre['hijas']);
}

// Obtener calificaciones
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

// Calcular promedios
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

// Incluir PHPExcel
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Crear nuevo documento Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Configurar propiedades del documento
$spreadsheet->getProperties()
    ->setCreator("Sistema Edunote")
    ->setTitle("Reporte de Notas - $nombre_curso")
    ->setSubject("Reporte de Notas");

// Establecer estilos
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
];

$subHeaderStyle = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
];

$dataStyle = [
    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
];

// Escribir encabezados
$sheet->setCellValue('A1', '#');
$sheet->setCellValue('B1', 'Pos.');
$sheet->setCellValue('C1', 'Estudiante');

    $col = 4;
    foreach ($materias as $materia) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $endColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 3);
        $sheet->setCellValue($colLetter.'1', $materia['nombre_materia']);
        $sheet->mergeCells($colLetter.'1:'.$endColLetter.'1');
        $col += 4;
    }
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $sheet->setCellValue($colLetter.'1', 'P. General');
    $sheet->mergeCells($colLetter.'1:'.$colLetter.'2');

// Ajustar altura de filas y aplicar estilos
$sheet->getRowDimension(1)->setRowHeight(30); // Altura para nombres de materias
$sheet->getRowDimension(2)->setRowHeight(20); // Altura para subencabezados

// Aplicar estilo a encabezado principal
$lastColHeader = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
$sheet->getStyle('A1:'.$lastColHeader.'1')->applyFromArray($headerStyle);

// Escribir subencabezados
$sheet->setCellValue('A2', '#');
$sheet->setCellValue('B2', 'Pos.');
$sheet->setCellValue('C2', 'Estudiante');

    $col = 4;
    foreach ($materias as $materia) {
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).'2', 'T1');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1).'2', 'T2');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 2).'2', 'T3');
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 3).'2', 'P');
        $col += 4;
    }

// Aplicar estilo a subencabezados
$lastColSubheader = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
$sheet->getStyle('A2:'.$lastColSubheader.'2')->applyFromArray($subHeaderStyle);

// Escribir datos de estudiantes
$row = 3;
    foreach ($estudiantes as $estudiante) {
        $sheet->setCellValue('A'.$row, $row - 2);
        $sheet->setCellValue('B'.$row, $row - 2); // Posición temporal, se puede mejorar
        $sheet->setCellValue('C'.$row, strtoupper("{$estudiante['apellido_paterno']} {$estudiante['apellido_materno']}, {$estudiante['nombres']}"));
        
        $col = 4;
        foreach ($materias as $materia) {
            $n1 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][1] ?? '';
            $n2 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][2] ?? '';
            $n3 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][3] ?? '';
            $pm = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
            
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).$row, $n1);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1).$row, $n2);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 2).$row, $n3);
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 3).$row, $pm);
            
            $col += 4;
        }
        
        // Calcular promedios para materias padre con hijas y guardarlos
        foreach ($materias_padre_con_hijas as $padre) {
            $suma_hijas = 0;
            $contador_hijas = 0;
            
            foreach ($padre['hijas'] as $hija) {
                $promedio_hija = $promedios_materias[$estudiante['id_estudiante']][$hija['id_materia']] ?? '';
                if ($promedio_hija !== '') {
                    $suma_hijas += floatval($promedio_hija);
                    $contador_hijas++;
                }
            }
            
            if ($contador_hijas > 0) {
                $promedio_padre = $suma_hijas / $contador_hijas;
                $promedios_materias[$estudiante['id_estudiante']][$padre['id_materia']] = number_format($promedio_padre, 2);
            }
        }

        // Calcular promedio general
        $suma = 0;
        $contador = 0;
        
        // 1. Materias padre simples
        foreach ($materias_padre_simples as $materia) {
            $promedio = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
            if ($promedio !== '') {
                $suma += floatval($promedio);
                $contador++;
            }
        }
        
        // 2. Materias padre con hijas (ya calculadas)
        foreach ($materias_padre_con_hijas as $padre) {
            $promedio = $promedios_materias[$estudiante['id_estudiante']][$padre['id_materia']] ?? '';
            if ($promedio !== '') {
                $suma += floatval($promedio);
                $contador++;
            }
        }
        
        // Excluir materias extra y sub-materias
        
        $promedio_general = ($contador > 0) ? number_format($suma / $contador, 2) : '-';
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).$row, $promedio_general);
        
        $row++;
    }

// Aplicar estilo a datos
$lastColData = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
$sheet->getStyle('A3:'.$lastColData.($row - 1))->applyFromArray($dataStyle);

// Ajustar ancho de columnas
$sheet->getColumnDimension('A')->setWidth(5);  // Columna #
$sheet->getColumnDimension('B')->setWidth(5);  // Columna Pos.
$sheet->getColumnDimension('C')->setWidth(30); // Columna Estudiante

// Columnas de notas (ancho fijo para 3 números)
$col = 4;
foreach ($materias as $materia) {
    for ($i = 0; $i < 4; $i++) { // 4 columnas por materia (T1,T2,T3,P)
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + $i);
        $sheet->getColumnDimension($colLetter)->setWidth(8);
    }
    $col += 4;
}
// Columna promedio general
$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
$sheet->getColumnDimension($lastCol)->setWidth(10);

// Configurar encabezados para descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Reporte_Notas_'.str_replace(' ', '_', $nombre_curso).'.xlsx"');
header('Cache-Control: max-age=0');

// Generar y enviar archivo
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
