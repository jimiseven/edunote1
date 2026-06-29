<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

require_once '../config/database.php';
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill, Color};
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2, 4], true)) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}

$id_curso = (int)($_GET['id'] ?? 0);
if ($id_curso <= 0) {
    exit('ID de curso invalido.');
}

$modoV2 = ($_GET['modo_calculo'] ?? '') === 'v2';

if (!function_exists('valor_excel_entero')) {
    function valor_excel_entero(float $valor, bool $truncar = false): int
    {
        return $truncar ? (int)$valor : (int)round($valor);
    }
}

if (!function_exists('determinar_prioridad_gestion')) {
    function determinar_prioridad_gestion(?string $gestionValor, string $gestionActual, ?string $gestionAlternativa): int
    {
        if ($gestionValor === null) {
            return 2;
        }
        $gestionLimpia = trim($gestionValor);
        if ($gestionLimpia === $gestionActual) {
            return 4;
        }
        if ($gestionAlternativa !== null && $gestionLimpia === $gestionAlternativa) {
            return 3;
        }
        if ($gestionLimpia === '') {
            return 2;
        }
        return 1;
    }
}

function calcular_datos_trimestre_excel(
    PDO $conn,
    int $idCurso,
    int $trimestre,
    array $estudiantes,
    array $todasMaterias,
    array $materiasPadreConHijas,
    array $materias,
    string $gestionActual,
    ?string $gestionAlternativa,
    bool $modoV2 = false
): array {
    $idsMaterias = array_map('intval', array_column($todasMaterias, 'id_materia'));
    $calificacionesParciales = [];
    $promediosMateriaTrimestre = [];

    foreach ($estudiantes as $estudiante) {
        $idEstudiante = (int)$estudiante['id_estudiante'];
        foreach ($todasMaterias as $materia) {
            $idMateria = (int)$materia['id_materia'];
            for ($parcial = 1; $parcial <= 3; $parcial++) {
                $calificacionesParciales[$idEstudiante][$idMateria][$parcial] = '';
            }
            $promediosMateriaTrimestre[$idEstudiante][$idMateria] = '';
        }
    }

    $datosTrimestrales = [];
    $prioridadTrimestral = [];
    if (!empty($idsMaterias)) {
        $placeholders = implode(',', array_fill(0, count($idsMaterias), '?'));
        $sqlTrimestral = "SELECT id_estudiante, id_materia, autoevaluacion, nota_extra, gestion
                          FROM calificaciones_trimestrales
                          WHERE trimestre = ?
                            AND id_materia IN ($placeholders)";
        $stmtTrimestral = $conn->prepare($sqlTrimestral);
        $stmtTrimestral->execute(array_merge([$trimestre], $idsMaterias));

        foreach ($stmtTrimestral->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $idEstudiante = (int)$fila['id_estudiante'];
            $idMateria = (int)$fila['id_materia'];
            $prioridad = determinar_prioridad_gestion($fila['gestion'] ?? null, $gestionActual, $gestionAlternativa);

            if (!isset($prioridadTrimestral[$idEstudiante][$idMateria]) || $prioridad > $prioridadTrimestral[$idEstudiante][$idMateria]) {
                $prioridadTrimestral[$idEstudiante][$idMateria] = $prioridad;
                $autoevaluacion = $fila['autoevaluacion'];
                $extra = $fila['nota_extra'];
                $datosTrimestrales[$idEstudiante][$idMateria] = [
                    'autoevaluacion' => ($autoevaluacion !== null && $autoevaluacion !== '') ? (float)$autoevaluacion : null,
                    'nota_extra' => ($extra !== null && $extra !== '') ? (float)$extra : null,
                ];
            }
        }

        $sqlCalificaciones = "SELECT cp.id_estudiante, cp.id_materia, pe.parcial, cp.calificacion, pe.gestion
                              FROM calificaciones_parciales cp
                              INNER JOIN periodos_evaluacion pe ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
                              INNER JOIN estudiantes e ON e.id_estudiante = cp.id_estudiante
                              INNER JOIN cursos_materias cm ON cm.id_materia = cp.id_materia
                              WHERE e.id_curso = ?
                                AND cm.id_curso = ?
                                AND pe.trimestre = ?
                                AND cp.id_materia IN ($placeholders)
                                AND (pe.gestion = ?";
        $paramsCalificaciones = array_merge([$idCurso, $idCurso, $trimestre], $idsMaterias, [$gestionActual]);
        if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
            $sqlCalificaciones .= ' OR pe.gestion = ?';
            $paramsCalificaciones[] = $gestionAlternativa;
        }
        $sqlCalificaciones .= ')';

        $stmtCalificaciones = $conn->prepare($sqlCalificaciones);
        $stmtCalificaciones->execute($paramsCalificaciones);
        $prioridadParcial = [];

        foreach ($stmtCalificaciones->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            if ($fila['calificacion'] === null || $fila['calificacion'] === '') {
                continue;
            }

            $idEstudiante = (int)$fila['id_estudiante'];
            $idMateria = (int)$fila['id_materia'];
            $parcial = (int)$fila['parcial'];
            if ($parcial < 1 || $parcial > 3) {
                continue;
            }

            $prioridad = determinar_prioridad_gestion($fila['gestion'] ?? null, $gestionActual, $gestionAlternativa);
            if (!isset($prioridadParcial[$idEstudiante][$idMateria][$parcial]) || $prioridad > $prioridadParcial[$idEstudiante][$idMateria][$parcial]) {
                $prioridadParcial[$idEstudiante][$idMateria][$parcial] = $prioridad;
                $notaParcial = (float)$fila['calificacion'];
                $calificacionesParciales[$idEstudiante][$idMateria][$parcial] = $modoV2
                    ? ($notaParcial * 31.6) / 95
                    : $notaParcial;
            }
        }
    }

    foreach ($estudiantes as $estudiante) {
        $idEstudiante = (int)$estudiante['id_estudiante'];
        foreach ($todasMaterias as $materia) {
            $idMateria = (int)$materia['id_materia'];
            $parcialesMateria = $calificacionesParciales[$idEstudiante][$idMateria] ?? [];
            $parcialesValidos = array_filter($parcialesMateria, function ($valor) {
                return $valor !== '' && $valor !== null;
            });

            if (!empty($parcialesValidos)) {
                if ($modoV2) {
                    $promediosMateriaTrimestre[$idEstudiante][$idMateria] = array_sum(array_map('floatval', $parcialesValidos));
                } else {
                    $promediosMateriaTrimestre[$idEstudiante][$idMateria] = round(array_sum(array_map('floatval', $parcialesValidos)) / count($parcialesValidos));
                }
            }
        }
    }

    foreach ($estudiantes as $estudiante) {
        $idEstudiante = (int)$estudiante['id_estudiante'];
        foreach ($todasMaterias as $materia) {
            $idMateria = (int)$materia['id_materia'];
            $promedioBase = $promediosMateriaTrimestre[$idEstudiante][$idMateria] ?? '';
            $datosTri = $datosTrimestrales[$idEstudiante][$idMateria] ?? null;
            $autoVal = $datosTri['autoevaluacion'] ?? null;
            $extraVal = $datosTri['nota_extra'] ?? null;

            $tieneBase = ($promedioBase !== '' && $promedioBase !== null);
            $tieneAuto = ($autoVal !== null);
            $tieneExtra = ($extraVal !== null);

            if ($tieneBase || $tieneAuto || $tieneExtra) {
                $baseNum = $tieneBase ? (float)$promedioBase : 0.0;
                $autoNum = $tieneAuto ? (float)$autoVal : 0.0;
                $extraNum = $tieneExtra ? (float)$extraVal : 0.0;
                $promediosMateriaTrimestre[$idEstudiante][$idMateria] = $modoV2
                    ? ($baseNum + $autoNum + $extraNum)
                    : round($baseNum + $autoNum + $extraNum);
            } else {
                $promediosMateriaTrimestre[$idEstudiante][$idMateria] = '';
            }
        }
    }

    foreach ($estudiantes as $estudiante) {
        $idEstudiante = (int)$estudiante['id_estudiante'];
        foreach ($materiasPadreConHijas as $padre) {
            $idPadre = (int)$padre['id_materia'];

            for ($parcial = 1; $parcial <= 3; $parcial++) {
                $suma = 0;
                $contador = 0;
                foreach ($padre['hijas'] as $hija) {
                    $nota = $calificacionesParciales[$idEstudiante][(int)$hija['id_materia']][$parcial] ?? '';
                    if ($nota !== '' && $nota !== null) {
                        $suma += (float)$nota;
                        $contador++;
                    }
                }
                if ($contador > 0) {
                    $calificacionesParciales[$idEstudiante][$idPadre][$parcial] = $modoV2 ? ($suma / $contador) : round($suma / $contador);
                } else {
                    $calificacionesParciales[$idEstudiante][$idPadre][$parcial] = '';
                }
            }

            $sumatoriaPromediosHijas = 0;
            $contadorHijas = 0;
            foreach ($padre['hijas'] as $hija) {
                $notaHija = $promediosMateriaTrimestre[$idEstudiante][(int)$hija['id_materia']] ?? '';
                if ($notaHija !== '' && $notaHija !== null) {
                    $sumatoriaPromediosHijas += (float)$notaHija;
                    $contadorHijas++;
                }
            }
            if ($contadorHijas > 0) {
                $promediosMateriaTrimestre[$idEstudiante][$idPadre] = $modoV2
                    ? ($sumatoriaPromediosHijas / $contadorHijas)
                    : round($sumatoriaPromediosHijas / $contadorHijas);
            } else {
                $promediosMateriaTrimestre[$idEstudiante][$idPadre] = '';
            }
        }
    }

    $promediosTrimestre = [];
    foreach ($estudiantes as $estudiante) {
        $idEstudiante = (int)$estudiante['id_estudiante'];
        $suma = 0;
        $contador = 0;
        foreach ($materias as $materia) {
            if ((int)$materia['es_extra'] === 1 || isset($materia['materia_padre_id'])) {
                continue;
            }
            $nota = $promediosMateriaTrimestre[$idEstudiante][(int)$materia['id_materia']] ?? '';
            if ($nota !== '' && $nota !== null) {
                $suma += (float)$nota;
                $contador++;
            }
        }
        if ($contador > 0) {
            $promediosTrimestre[$idEstudiante] = $modoV2 ? ($suma / $contador) : round($suma / $contador);
        } else {
            $promediosTrimestre[$idEstudiante] = '';
        }
    }

    return [
        'parciales' => $calificacionesParciales,
        'promedios_materia' => $promediosMateriaTrimestre,
        'promedios_trimestre' => $promediosTrimestre,
    ];
}

try {
    $conn = (new Database())->connect();

    $stmtGestion = $conn->query('SELECT anio_escolar FROM configuracion_sistema ORDER BY id DESC LIMIT 1');
    $gestionConfigurada = $stmtGestion->fetchColumn();
    $gestionConfigurada = $gestionConfigurada ? trim((string)$gestionConfigurada) : '';
    $gestionActual = $gestionConfigurada !== '' ? $gestionConfigurada : date('Y');
    $gestionAlternativa = null;
    if (preg_match('/\b(20\d{2})\b/', $gestionActual, $matches)) {
        $gestionAlternativa = $matches[1];
    }

    $stmtCurso = $conn->prepare('SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?');
    $stmtCurso->execute([$id_curso]);
    $curso = $stmtCurso->fetch(PDO::FETCH_ASSOC);
    if (!$curso) {
        exit('Curso no encontrado.');
    }

    $nombreCurso = $curso['nivel'] . ' ' . $curso['curso'] . ' "' . $curso['paralelo'] . '"';
    $nombreArchivoCurso = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($curso['nivel'] . ' ' . $curso['curso'] . ' ' . $curso['paralelo']));
    $nombreArchivoCurso = trim($nombreArchivoCurso, '_') ?: 'curso';

    $stmtEstudiantes = $conn->prepare('
        SELECT id_estudiante, apellido_paterno, apellido_materno, nombres
        FROM estudiantes
        WHERE id_curso = ?
        ORDER BY apellido_paterno, apellido_materno, nombres
    ');
    $stmtEstudiantes->execute([$id_curso]);
    $estudiantes = $stmtEstudiantes->fetchAll(PDO::FETCH_ASSOC);

    $stmtMaterias = $conn->prepare('
        SELECT m.id_materia, m.nombre_materia, m.es_extra, m.es_submateria, m.materia_padre_id
        FROM cursos_materias cm
        JOIN materias m ON cm.id_materia = m.id_materia
        WHERE cm.id_curso = ?
    ');
    $stmtMaterias->execute([$id_curso]);
    $todasMaterias = $stmtMaterias->fetchAll(PDO::FETCH_ASSOC);

    $materiasPadre = $materiasExtra = $materiasHijas = [];
    foreach ($todasMaterias as $materia) {
        if ((int)$materia['es_extra'] === 1) {
            $materiasExtra[] = $materia;
        } elseif ((int)$materia['es_submateria'] === 0) {
            $materia['hijas'] = [];
            $materiasPadre[(int)$materia['id_materia']] = $materia;
        } else {
            $materiasHijas[] = $materia;
        }
    }

    foreach ($materiasHijas as $hija) {
        $idPadre = (int)$hija['materia_padre_id'];
        if (isset($materiasPadre[$idPadre])) {
            $materiasPadre[$idPadre]['hijas'][] = $hija;
        }
    }

    $materiasPadreSimples = [];
    $materiasPadreConHijas = [];
    foreach ($materiasPadre as $padre) {
        empty($padre['hijas']) ? $materiasPadreSimples[] = $padre : $materiasPadreConHijas[] = $padre;
    }

    $materias = array_merge(
        $materiasPadreSimples,
        $materiasExtra,
        array_reduce($materiasPadreConHijas, function ($carry, $padre) {
            return array_merge($carry, [$padre], $padre['hijas']);
        }, [])
    );

    $datosPorTrimestre = [];
    for ($trimestre = 1; $trimestre <= 3; $trimestre++) {
        $datosPorTrimestre[$trimestre] = calcular_datos_trimestre_excel(
            $conn,
            $id_curso,
            $trimestre,
            $estudiantes,
            $todasMaterias,
            $materiasPadreConHijas,
            $materias,
            $gestionActual,
            $gestionAlternativa,
            $modoV2
        );
    }

    $spreadsheet = new Spreadsheet();

    $titleStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];
    $subjectStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B4C6E7']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];
    $cellStyle = [
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];
    $lowGradeStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FF0000']],
    ];
    $averageStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2F0D9']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];

    for ($trimestre = 1; $trimestre <= 3; $trimestre++) {
        $sheet = $trimestre === 1 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
        $sheet->setTitle('Trimestre ' . $trimestre);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.3)->setRight(0.3);

        $totalColumnas = 2 + (count($materias) * 4) + 1;
        $ultimaColumna = Coordinate::stringFromColumnIndex($totalColumnas);

        $sheet->mergeCells("A1:{$ultimaColumna}1");
        $sheet->setCellValue('A1', 'UNIDAD EDUCATIVA SIMON BOLIVAR');
        $sheet->getStyle('A1')->applyFromArray($titleStyle);
        $sheet->getStyle('A1')->getFont()->setSize(16)->setColor(new Color('000000'));
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->mergeCells("A2:{$ultimaColumna}2");
        $sheet->setCellValue('A2', ($modoV2 ? 'CENTRALIZADOR V2 - TRIMESTRE ' : 'CENTRALIZADOR - TRIMESTRE ') . $trimestre);
        $sheet->getStyle('A2')->applyFromArray($titleStyle);
        $sheet->getStyle('A2')->getFont()->setSize(13)->setColor(new Color('000000'));
        $sheet->getRowDimension(2)->setRowHeight(22);

        $sheet->mergeCells("A3:{$ultimaColumna}3");
        $sheet->setCellValue('A3', 'Curso: ' . $nombreCurso);
        $sheet->getStyle('A3')->applyFromArray($titleStyle);
        $sheet->getStyle('A3')->getFont()->setSize(11)->setColor(new Color('000000'));
        $sheet->getRowDimension(3)->setRowHeight(20);

        $headerTopRow = 4;
        $headerSubRow = 5;
        $sheet->mergeCells("A{$headerTopRow}:A{$headerSubRow}");
        $sheet->setCellValue("A{$headerTopRow}", 'N°');
        $sheet->getStyle("A{$headerTopRow}:A{$headerSubRow}")->applyFromArray($headerStyle);
        $sheet->mergeCells("B{$headerTopRow}:B{$headerSubRow}");
        $sheet->setCellValue("B{$headerTopRow}", 'ESTUDIANTE');
        $sheet->getStyle("B{$headerTopRow}:B{$headerSubRow}")->applyFromArray($headerStyle);
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(38);

        $colIndex = 3;
        foreach ($materias as $materia) {
            $inicio = Coordinate::stringFromColumnIndex($colIndex);
            $fin = Coordinate::stringFromColumnIndex($colIndex + 3);
            $sheet->mergeCells("{$inicio}{$headerTopRow}:{$fin}{$headerTopRow}");
            $sheet->setCellValue("{$inicio}{$headerTopRow}", strtoupper($materia['nombre_materia']));
            $sheet->getStyle("{$inicio}{$headerTopRow}:{$fin}{$headerTopRow}")->applyFromArray($subjectStyle);

            foreach (['P1', 'P2', 'P3', 'Prom.'] as $offset => $titulo) {
                $col = Coordinate::stringFromColumnIndex($colIndex + $offset);
                $sheet->setCellValue("{$col}{$headerSubRow}", $titulo);
                $sheet->getStyle("{$col}{$headerSubRow}")->applyFromArray($offset === 3 ? $averageStyle : $headerStyle);
                $sheet->getColumnDimension($col)->setWidth($offset === 3 ? 8 : 7);
            }
            $colIndex += 4;
        }

        $colPromedio = Coordinate::stringFromColumnIndex($colIndex);
        $sheet->mergeCells("{$colPromedio}{$headerTopRow}:{$colPromedio}{$headerSubRow}");
        $sheet->setCellValue("{$colPromedio}{$headerTopRow}", 'PROMEDIO');
        $sheet->getStyle("{$colPromedio}{$headerTopRow}:{$colPromedio}{$headerSubRow}")->applyFromArray($averageStyle);
        $sheet->getColumnDimension($colPromedio)->setWidth(10);

        $datos = $datosPorTrimestre[$trimestre];
        $row = 6;
        foreach ($estudiantes as $index => $estudiante) {
            $idEstudiante = (int)$estudiante['id_estudiante'];
            $nombreCompleto = strtoupper(
                ($estudiante['apellido_paterno'] ?? '') . ' ' .
                ($estudiante['apellido_materno'] ?? '') . ', ' .
                ($estudiante['nombres'] ?? '')
            );

            $sheet->setCellValue("A{$row}", $index + 1);
            $sheet->setCellValue("B{$row}", $nombreCompleto);
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray($cellStyle);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            $colIndex = 3;
            foreach ($materias as $materia) {
                $idMateria = (int)$materia['id_materia'];
                for ($parcial = 1; $parcial <= 3; $parcial++) {
                    $col = Coordinate::stringFromColumnIndex($colIndex);
                    $cell = "{$col}{$row}";
                    $nota = $datos['parciales'][$idEstudiante][$idMateria][$parcial] ?? '';
                    if ($nota !== '' && $nota !== null) {
                        $sheet->setCellValue($cell, valor_excel_entero((float)$nota, $modoV2));
                        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('0');
                        if ((float)$nota < 51) {
                            $sheet->getStyle($cell)->applyFromArray($lowGradeStyle);
                        }
                    }
                    $sheet->getStyle($cell)->applyFromArray($cellStyle);
                    $colIndex++;
                }

                $col = Coordinate::stringFromColumnIndex($colIndex);
                $cell = "{$col}{$row}";
                $promedioMateria = $datos['promedios_materia'][$idEstudiante][$idMateria] ?? '';
                if ($promedioMateria !== '' && $promedioMateria !== null) {
                    $sheet->setCellValue($cell, valor_excel_entero((float)$promedioMateria, $modoV2));
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('0');
                    if ((float)$promedioMateria < 51) {
                        $sheet->getStyle($cell)->applyFromArray($lowGradeStyle);
                    }
                }
                $sheet->getStyle($cell)->applyFromArray($averageStyle);
                $colIndex++;
            }

            $cellPromedio = $colPromedio . $row;
            $promedioTrimestre = $datos['promedios_trimestre'][$idEstudiante] ?? '';
            if ($promedioTrimestre !== '' && $promedioTrimestre !== null) {
                $sheet->setCellValue($cellPromedio, valor_excel_entero((float)$promedioTrimestre, $modoV2));
                $sheet->getStyle($cellPromedio)->getNumberFormat()->setFormatCode('0');
            }
            $sheet->getStyle($cellPromedio)->applyFromArray($averageStyle);
            $row++;
        }

        $sheet->freezePane('C6');
        $sheet->getStyle("A{$headerTopRow}:{$ultimaColumna}" . ($row - 1))->getAlignment()->setWrapText(true);
    }

    $sheetResumen = $spreadsheet->createSheet();
    $sheetResumen->setTitle('Resumen Anual');
    $sheetResumen->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
        ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
        ->setFitToWidth(1)
        ->setFitToHeight(0);
    $sheetResumen->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.3)->setRight(0.3);

    $ultimaColumnaResumen = 'F';
    $sheetResumen->mergeCells("A1:{$ultimaColumnaResumen}1");
    $sheetResumen->setCellValue('A1', 'UNIDAD EDUCATIVA SIMON BOLIVAR');
    $sheetResumen->getStyle('A1')->applyFromArray($titleStyle);
    $sheetResumen->getStyle('A1')->getFont()->setSize(16)->setColor(new Color('000000'));
    $sheetResumen->getRowDimension(1)->setRowHeight(30);

    $sheetResumen->mergeCells("A2:{$ultimaColumnaResumen}2");
    $sheetResumen->setCellValue('A2', $modoV2 ? 'RESUMEN ANUAL V2' : 'RESUMEN ANUAL');
    $sheetResumen->getStyle('A2')->applyFromArray($titleStyle);
    $sheetResumen->getStyle('A2')->getFont()->setSize(13)->setColor(new Color('000000'));
    $sheetResumen->getRowDimension(2)->setRowHeight(22);

    $sheetResumen->mergeCells("A3:{$ultimaColumnaResumen}3");
    $sheetResumen->setCellValue('A3', 'Curso: ' . $nombreCurso);
    $sheetResumen->getStyle('A3')->applyFromArray($titleStyle);
    $sheetResumen->getStyle('A3')->getFont()->setSize(11)->setColor(new Color('000000'));
    $sheetResumen->getRowDimension(3)->setRowHeight(20);

    $headerResumenRow = 4;
    $encabezadosResumen = [
        'A' => 'N°',
        'B' => 'ESTUDIANTE',
        'C' => 'T1',
        'D' => 'T2',
        'E' => 'T3',
        'F' => 'NOTA ANUAL',
    ];
    foreach ($encabezadosResumen as $col => $titulo) {
        $sheetResumen->setCellValue($col . $headerResumenRow, $titulo);
        $sheetResumen->getStyle($col . $headerResumenRow)->applyFromArray($headerStyle);
    }
    $sheetResumen->getColumnDimension('A')->setWidth(6);
    $sheetResumen->getColumnDimension('B')->setWidth(42);
    foreach (['C', 'D', 'E', 'F'] as $col) {
        $sheetResumen->getColumnDimension($col)->setWidth(12);
    }

    $rowResumen = 5;
    foreach ($estudiantes as $index => $estudiante) {
        $idEstudiante = (int)$estudiante['id_estudiante'];
        $nombreCompleto = strtoupper(
            ($estudiante['apellido_paterno'] ?? '') . ' ' .
            ($estudiante['apellido_materno'] ?? '') . ', ' .
            ($estudiante['nombres'] ?? '')
        );

        $sheetResumen->setCellValue("A{$rowResumen}", $index + 1);
        $sheetResumen->setCellValue("B{$rowResumen}", $nombreCompleto);
        $sheetResumen->getStyle("A{$rowResumen}:B{$rowResumen}")->applyFromArray($cellStyle);
        $sheetResumen->getStyle("B{$rowResumen}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sumatoriaAnual = 0;
        $contadorAnual = 0;
        for ($trimestre = 1; $trimestre <= 3; $trimestre++) {
            $col = Coordinate::stringFromColumnIndex($trimestre + 2);
            $cell = $col . $rowResumen;
            $promedioTrimestre = $datosPorTrimestre[$trimestre]['promedios_trimestre'][$idEstudiante] ?? '';
            if ($promedioTrimestre !== '' && $promedioTrimestre !== null) {
                $notaTrim = valor_excel_entero((float)$promedioTrimestre, $modoV2);
                $sheetResumen->setCellValue($cell, $notaTrim);
                $sheetResumen->getStyle($cell)->getNumberFormat()->setFormatCode('0');
                if ($notaTrim < 51) {
                    $sheetResumen->getStyle($cell)->applyFromArray($lowGradeStyle);
                }
                $sumatoriaAnual += (float)$promedioTrimestre;
                $contadorAnual++;
            }
            $sheetResumen->getStyle($cell)->applyFromArray($cellStyle);
        }

        $cellAnual = 'F' . $rowResumen;
        if ($contadorAnual > 0) {
            $notaAnual = valor_excel_entero($sumatoriaAnual / $contadorAnual, $modoV2);
            $sheetResumen->setCellValue($cellAnual, $notaAnual);
            $sheetResumen->getStyle($cellAnual)->getNumberFormat()->setFormatCode('0');
            if ($notaAnual < 51) {
                $sheetResumen->getStyle($cellAnual)->applyFromArray($lowGradeStyle);
            }
        }
        $sheetResumen->getStyle($cellAnual)->applyFromArray($averageStyle);
        $rowResumen++;
    }

    $sheetResumen->freezePane('C5');

    $spreadsheet->setActiveSheetIndex(0);

    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $sufijoArchivo = $modoV2 ? '3_Trimestres_V2' : '3_Trimestres';
    header("Content-Disposition: attachment;filename=\"Centralizador_{$nombreArchivoCurso}_{$sufijoArchivo}.xlsx\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (Exception $e) {
    http_response_code(500);
    exit('Error al generar Excel: ' . $e->getMessage());
}
