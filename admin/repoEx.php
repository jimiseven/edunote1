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

// Función auxiliar para normalizar strings en español
function normalizeSpanishString($str) {
    // Convertir a minúsculas para comparación
    $str = mb_strtolower($str, 'UTF-8');
    
    // Reemplazar caracteres especiales del español
    $replacements = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u'
    ];
    
    return strtr($str, $replacements);
}

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

// NOTA AUTOMÁTICA para materias padre (promedio de hijas por trimestre)
foreach ($estudiantes as $estudiante) {
    foreach ($materias_padre as $padre) {
        if (!empty($padre['hijas'])) {
            for ($t = 1; $t <= 3; $t++) {
                $suma = 0;
                $contador = 0;
                foreach ($padre['hijas'] as $hija) {
                    $nota_hija = $calificaciones[$estudiante['id_estudiante']][$hija['id_materia']][$t] ?? '';
                    if ($nota_hija !== '') {
                        $suma += floatval($nota_hija);
                        $contador++;
                    }
                }
                if ($contador > 0) {
                    $calificaciones[$estudiante['id_estudiante']][$padre['id_materia']][$t] = number_format($suma / $contador, 2);
                }
            }
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

// Establecer estilos optimizados para impresión en blanco y negro
$headerStyle = [
    'font' => [
        'bold' => true, 
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 10
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 
        'startColor' => ['rgb' => '000000']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
    ]
];

$subHeaderStyle = [
    'font' => [
        'bold' => true,
        'size' => 9,
        'color' => ['rgb' => '000000']
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 
        'startColor' => ['rgb' => 'CCCCCC']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
    ]
];

$dataStyle = [
    'font' => [
        'size' => 8,
        'color' => ['rgb' => '000000']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => '666666']
        ]
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
    ]
];

$positionStyle = [
    'font' => [
        'bold' => true,
        'size' => 8,
        'color' => ['rgb' => '000000']
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 
        'startColor' => ['rgb' => 'E6E6E6']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
    ]
];

$numberStyle = [
    'font' => [
        'bold' => true,
        'size' => 8,
        'color' => ['rgb' => '000000']
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 
        'startColor' => ['rgb' => 'F2F2F2']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
    ]
];

$studentNameStyle = [
    'font' => [
        'bold' => true,
        'size' => 8,
        'color' => ['rgb' => '000000']
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 
        'startColor' => ['rgb' => 'FFFFFF']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
    ]
];

$averageStyle = [
    'font' => [
        'bold' => true,
        'size' => 8,
        'color' => ['rgb' => '000000']
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 
        'startColor' => ['rgb' => 'D9D9D9']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
    ]
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
        
        // Aplicar estilo especial para materias extras en el encabezado (gris oscuro para B&N)
        if ($materia['es_extra'] == 1) {
            $sheet->getStyle($colLetter.'1:'.$endColLetter.'1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('808080'));
        }
        
        $col += 4;
    }
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $sheet->setCellValue($colLetter.'1', 'P. General');
    $sheet->mergeCells($colLetter.'1:'.$colLetter.'2');

// Ajustar altura de filas para diseño compacto
$sheet->getRowDimension(1)->setRowHeight(18); // Altura para nombres de materias
$sheet->getRowDimension(2)->setRowHeight(14); // Altura para subencabezados

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

// PRIMERO: Calcular promedios para materias padre con hijas ANTES de calcular promedios generales
foreach ($estudiantes as $estudiante) {
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
}

// SEGUNDO: Calcular promedios generales para todos los estudiantes
$promedios_generales = [];
foreach ($estudiantes as $estudiante) {
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
    
    // 2. Materias padre con hijas (ya calculadas arriba)
    foreach ($materias_padre_con_hijas as $padre) {
        $promedio = $promedios_materias[$estudiante['id_estudiante']][$padre['id_materia']] ?? '';
        if ($promedio !== '') {
            $suma += floatval($promedio);
            $contador++;
        }
    }
    
    $promedios_generales[$estudiante['id_estudiante']] = ($contador > 0) ? number_format($suma / $contador, 2) : '-';
}

// TERCERO: Calcular posiciones según promedio general
$promedios_ordenados = $promedios_generales;
arsort($promedios_ordenados);
$posiciones = [];
$pos_actual = 1;
$prom_anterior = null;

foreach ($promedios_ordenados as $id_est => $prom) {
    if ($prom_anterior !== null && $prom < $prom_anterior) {
        $pos_actual++;
    }
    $posiciones[$id_est] = $pos_actual;
    $prom_anterior = $prom;
}

// CUARTO: Ordenar estudiantes alfabéticamente considerando caracteres españoles
$estudiantes_ordenados = $estudiantes;
usort($estudiantes_ordenados, function($a, $b) {
    // Normalizar caracteres para comparación correcta en español
    $a_paterno = normalizeSpanishString($a['apellido_paterno']);
    $b_paterno = normalizeSpanishString($b['apellido_paterno']);
    $a_materno = normalizeSpanishString($a['apellido_materno']);
    $b_materno = normalizeSpanishString($b['apellido_materno']);
    $a_nombres = normalizeSpanishString($a['nombres']);
    $b_nombres = normalizeSpanishString($b['nombres']);
    
    // Comparar primero por apellido paterno
    $cmp_apellido = strcoll($a_paterno, $b_paterno);
    if ($cmp_apellido !== 0) {
        return $cmp_apellido;
    }
    
    // Si apellido paterno es igual, comparar por apellido materno
    $cmp_materno = strcoll($a_materno, $b_materno);
    if ($cmp_materno !== 0) {
        return $cmp_materno;
    }
    
    // Si ambos apellidos son iguales, comparar por nombres
    return strcoll($a_nombres, $b_nombres);
});



// Escribir datos de estudiantes con estilos específicos
$row = 3;
$contador = 1;
foreach ($estudiantes_ordenados as $estudiante) {
    // Columna # (número secuencial)
    $sheet->setCellValue('A'.$row, $contador);
    $sheet->getStyle('A'.$row)->applyFromArray($numberStyle);
    
    // Columna Pos. (posición por promedio)
    $sheet->setCellValue('B'.$row, $posiciones[$estudiante['id_estudiante']]);
    $sheet->getStyle('B'.$row)->applyFromArray($positionStyle);
    
    // Columna Estudiante (nombre)
    $sheet->setCellValue('C'.$row, strtoupper("{$estudiante['apellido_paterno']} {$estudiante['apellido_materno']}, {$estudiante['nombres']}"));
    $sheet->getStyle('C'.$row)->applyFromArray($studentNameStyle);
    
    $col = 4;
    foreach ($materias as $materia) {
        $n1 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][1] ?? '';
        $n2 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][2] ?? '';
        $n3 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][3] ?? '';
        $pm = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
        
        // Aplicar estilos específicos según el tipo de dato
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).$row, $n1);
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1).$row, $n2);
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 2).$row, $n3);
        
        // Columna de promedio (P) con estilo especial
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 3).$row, $pm);
        $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 3).$row)->applyFromArray($averageStyle);
        
        // Aplicar estilo especial para materias extras (gris claro para B&N)
        if ($materia['es_extra'] == 1) {
            for ($i = 0; $i < 4; $i++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + $i);
                $sheet->getStyle($colLetter.$row)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('F0F0F0'));
            }
        }
        
        $col += 4;
    }
    
    // Promedio general con estilo destacado
    $promedio_general = $promedios_generales[$estudiante['id_estudiante']];
    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).$row, $promedio_general);
    $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).$row)->applyFromArray($averageStyle);
    
    $contador++;
    $row++;
}

// Aplicar estilos alternados para mejor legibilidad
$lastColData = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
for ($r = 3; $r < $row; $r++) {
    // Aplicar estilo base a toda la fila
    $sheet->getStyle('A'.$r.':'.$lastColData.$r)->applyFromArray($dataStyle);
    
    // Aplicar color de fondo alternado para mejor legibilidad (gris muy claro para B&N)
    if ($r % 2 == 0) {
        $sheet->getStyle('A'.$r.':'.$lastColData.$r)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FAFAFA'));
    }
}

// Ajustar ancho de columnas para diseño compacto
$sheet->getColumnDimension('A')->setWidth(4);  // Columna #
$sheet->getColumnDimension('B')->setWidth(4);  // Columna Pos.
$sheet->getColumnDimension('C')->setWidth(25); // Columna Estudiante

// Columnas de notas (ancho compacto)
$col = 4;
foreach ($materias as $materia) {
    for ($i = 0; $i < 4; $i++) { // 4 columnas por materia (T1,T2,T3,P)
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + $i);
        $sheet->getColumnDimension($colLetter)->setWidth(6);
    }
    $col += 4;
}
// Columna promedio general
$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
$sheet->getColumnDimension($lastCol)->setWidth(8);

// Configurar encabezados para descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Reporte_Notas_'.str_replace(' ', '_', $nombre_curso).'.xlsx"');
header('Cache-Control: max-age=0');

// Generar y enviar archivo
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
