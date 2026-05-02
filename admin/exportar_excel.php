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
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

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

    // GESTION
    $stmtGestion = $conn->query("SELECT anio_escolar FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
    $gestionConfigurada = $stmtGestion->fetchColumn();
    $gestionConfigurada = $gestionConfigurada ? trim((string)$gestionConfigurada) : '';
    $gestionActual = $gestionConfigurada !== '' ? $gestionConfigurada : date('Y');
    $gestionAlternativa = null;
    if (preg_match('/\b(20\d{2})\b/', $gestionActual, $matches)) {
        $gestionAlternativa = $matches[1];
    }

    // PERIODOS DE EVALUACION
    $periodosIdsTrimestre = [1 => null, 2 => null, 3 => null];
    $gestionesConsulta = [$gestionActual];
    if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
        $gestionesConsulta[] = $gestionAlternativa;
    }
    if (!empty($gestionesConsulta)) {
        $placeholdersPeriodos = implode(',', array_fill(0, count($gestionesConsulta), '?'));
        $sqlPeriodosTrimestre = "SELECT id_periodo_evaluacion, parcial
                                  FROM periodos_evaluacion
                                  WHERE trimestre = ? AND gestion IN ($placeholdersPeriodos)";
        $paramsPeriodosTrimestre = array_merge([(int)$trimestre], $gestionesConsulta);
        $stmtPeriodosTrimestre = $conn->prepare($sqlPeriodosTrimestre);
        $stmtPeriodosTrimestre->execute($paramsPeriodosTrimestre);
        foreach ($stmtPeriodosTrimestre->fetchAll(PDO::FETCH_ASSOC) as $filaPeriodo) {
            $parcialFila = isset($filaPeriodo['parcial']) ? (int)$filaPeriodo['parcial'] : 0;
            if ($parcialFila < 1 || $parcialFila > 3) continue;
            $idPeriodoFila = isset($filaPeriodo['id_periodo_evaluacion']) ? (int)$filaPeriodo['id_periodo_evaluacion'] : null;
            if ($idPeriodoFila === null) continue;
            $periodosIdsTrimestre[$parcialFila] = $idPeriodoFila;
        }
    }

    // DATOS DEL CURSO
    $stmt = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
    $stmt->execute([$id_curso]);
    $curso = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$curso) exit("Curso no encontrado.");
    $nombre_curso = "{$curso['nivel']} {$curso['curso']} \"{$curso['paralelo']}\"";

    // ESTUDIANTES
    $stmt = $conn->prepare("
        SELECT id_estudiante, apellido_paterno, apellido_materno, nombres
        FROM estudiantes
        WHERE id_curso = ?
        ORDER BY apellido_paterno, apellido_materno, nombres
    ");
    $stmt->execute([$id_curso]);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // MATERIAS (misma clasificacion que ver_trimestre.php)
    $stmt = $conn->prepare("
        SELECT m.id_materia, m.nombre_materia, m.es_extra, m.es_submateria, m.materia_padre_id
        FROM cursos_materias cm
        JOIN materias m ON cm.id_materia = m.id_materia
        WHERE cm.id_curso = ?
    ");
    $stmt->execute([$id_curso]);
    $todas_materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $materiasPorId = [];
    foreach ($todas_materias as $materia) {
        $materiasPorId[(int)$materia['id_materia']] = $materia;
    }

    $materias_padre = $materias_extra = $materias_hijas = [];
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

    foreach ($materias_hijas as $hija) {
        if (isset($materias_padre[$hija['materia_padre_id']])) {
            $materias_padre[$hija['materia_padre_id']]['hijas'][] = $hija;
        }
    }

    $materias_padre_simples = [];
    $materias_padre_con_hijas = [];
    foreach ($materias_padre as $padre) {
        empty($padre['hijas']) ? $materias_padre_simples[] = $padre : $materias_padre_con_hijas[] = $padre;
    }

    $materias = array_merge(
        $materias_padre_simples,
        $materias_extra,
        array_reduce($materias_padre_con_hijas, function ($carry, $padre) {
            return array_merge($carry, [$padre], $padre['hijas']);
        }, [])
    );

    // CALIFICACIONES TRIMESTRALES (autoevaluacion + nota_extra)
    $datosTrimestrales = [];
    $prioridadTrimestral = [];
    $idsMaterias = array_column($todas_materias, 'id_materia');

    if (!function_exists('determinar_prioridad_gestion')) {
        function determinar_prioridad_gestion($gestionValor, $gestionActual, $gestionAlternativa) {
            if ($gestionValor === null) return 2;
            $gestionLimpia = trim($gestionValor);
            if ($gestionLimpia === $gestionActual) return 4;
            if ($gestionAlternativa !== null && $gestionLimpia === $gestionAlternativa) return 3;
            if ($gestionLimpia === '') return 2;
            return 1;
        }
    }

    if (!empty($idsMaterias)) {
        $placeholdersTrimestrales = implode(',', array_fill(0, count($idsMaterias), '?'));
        $sqlTrimestral = "SELECT id_estudiante, id_materia, autoevaluacion, nota_extra, gestion
                          FROM calificaciones_trimestrales
                          WHERE trimestre = ? AND id_materia IN ($placeholdersTrimestrales)";
        $paramsTrimestral = array_merge([(int)$trimestre], array_map('intval', $idsMaterias));

        $stmtTrimestral = $conn->prepare($sqlTrimestral);
        $stmtTrimestral->execute($paramsTrimestral);

        foreach ($stmtTrimestral->fetchAll(PDO::FETCH_ASSOC) as $filaTrimestral) {
            $idEstTr = (int)$filaTrimestral['id_estudiante'];
            $idMatTr = (int)$filaTrimestral['id_materia'];
            $prioridad = determinar_prioridad_gestion($filaTrimestral['gestion'] ?? null, $gestionActual, $gestionAlternativa);

            if (!isset($prioridadTrimestral[$idEstTr][$idMatTr]) || $prioridad > $prioridadTrimestral[$idEstTr][$idMatTr]) {
                $prioridadTrimestral[$idEstTr][$idMatTr] = $prioridad;
                $autoeval = $filaTrimestral['autoevaluacion'];
                $extra = $filaTrimestral['nota_extra'];
                $datosTrimestrales[$idEstTr][$idMatTr] = [
                    'autoevaluacion' => ($autoeval !== null && $autoeval !== '') ? (float)$autoeval : null,
                    'nota_extra' => ($extra !== null && $extra !== '') ? (float)$extra : null
                ];
            }
        }
    }

    // CALIFICACIONES PARCIALES
    $calificacionesParciales = [];
    $promediosMateriaTrimestre = [];
    foreach ($estudiantes as $estudiante) {
        foreach ($todas_materias as $materia) {
            for ($parcial = 1; $parcial <= 3; $parcial++) {
                $calificacionesParciales[$estudiante['id_estudiante']][$materia['id_materia']][$parcial] = '';
            }
            $promediosMateriaTrimestre[$estudiante['id_estudiante']][$materia['id_materia']] = '';
        }
    }

    $sqlCalificaciones = "SELECT cp.id_estudiante, cp.id_materia, pe.parcial, cp.calificacion
                          FROM calificaciones_parciales cp
                          INNER JOIN periodos_evaluacion pe ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
                          INNER JOIN estudiantes e ON e.id_estudiante = cp.id_estudiante
                          INNER JOIN cursos_materias cm ON cm.id_materia = cp.id_materia
                          WHERE e.id_curso = ?
                            AND cm.id_curso = ?
                            AND pe.trimestre = ?
                            AND (pe.gestion = ?";
    $paramsCalificaciones = [$id_curso, $id_curso, $trimestre, $gestionActual];
    if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
        $sqlCalificaciones .= " OR pe.gestion = ?";
        $paramsCalificaciones[] = $gestionAlternativa;
    }
    $sqlCalificaciones .= ")";

    $stmt_calificaciones = $conn->prepare($sqlCalificaciones);
    $stmt_calificaciones->execute($paramsCalificaciones);
    foreach ($stmt_calificaciones->fetchAll(PDO::FETCH_ASSOC) as $filaCalificacion) {
        if ($filaCalificacion['calificacion'] === null || $filaCalificacion['calificacion'] === '') continue;
        $idEstudiante = (int)$filaCalificacion['id_estudiante'];
        $idMateria = (int)$filaCalificacion['id_materia'];
        $parcial = (int)$filaCalificacion['parcial'];
        $calificacionesParciales[$idEstudiante][$idMateria][$parcial] = (float)$filaCalificacion['calificacion'];
    }

    // Promedio base (promedio de parciales)
    foreach ($estudiantes as $estudiante) {
        foreach ($todas_materias as $materia) {
            $parcialesMateria = $calificacionesParciales[$estudiante['id_estudiante']][$materia['id_materia']] ?? [];
            $parcialesValidos = array_filter($parcialesMateria, function ($valor) {
                return $valor !== '' && $valor !== null;
            });
            if (!empty($parcialesValidos)) {
                $promediosMateriaTrimestre[$estudiante['id_estudiante']][$materia['id_materia']] = array_sum(array_map('floatval', $parcialesValidos)) / count($parcialesValidos);
            }
        }
    }

    // Aplicar autoevaluacion + extra
    foreach ($estudiantes as $estudiante) {
        $idEst = (int)$estudiante['id_estudiante'];
        foreach ($todas_materias as $materia) {
            $idMat = (int)$materia['id_materia'];
            $promedioBase = $promediosMateriaTrimestre[$idEst][$idMat] ?? '';
            $datosTri = $datosTrimestrales[$idEst][$idMat] ?? null;
            $autoVal = $datosTri['autoevaluacion'] ?? null;
            $extraVal = $datosTri['nota_extra'] ?? null;

            $tieneBase = ($promedioBase !== '' && $promedioBase !== null);
            $tieneAuto = ($autoVal !== null);
            $tieneExtra = ($extraVal !== null);

            if ($tieneBase || $tieneAuto || $tieneExtra) {
                $baseNum = $tieneBase ? (float)$promedioBase : 0.0;
                $autoNum = $tieneAuto ? (float)$autoVal : 0.0;
                $extraNum = $tieneExtra ? (float)$extraVal : 0.0;
                $promediosMateriaTrimestre[$idEst][$idMat] = $baseNum + $autoNum + $extraNum;
            } else {
                $promediosMateriaTrimestre[$idEst][$idMat] = '';
            }
        }
    }

    // Promedios de materias padre (promedio de hijas)
    foreach ($estudiantes as $estudiante) {
        $idEst = (int)$estudiante['id_estudiante'];
        foreach ($materias_padre_con_hijas as $padre) {
            $idPadre = (int)$padre['id_materia'];
            $sumatoria = 0;
            $contador = 0;
            foreach ($padre['hijas'] as $hija) {
                $notaHija = $promediosMateriaTrimestre[$idEst][(int)$hija['id_materia']] ?? '';
                if ($notaHija !== '' && $notaHija !== null) {
                    $sumatoria += (float)$notaHija;
                    $contador++;
                }
            }
            $promediosMateriaTrimestre[$idEst][$idPadre] = $contador > 0 ? round($sumatoria / $contador, 2) : '';
        }
    }

    // CREAR EXCEL
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
        ->setFitToWidth(1)
        ->setFitToHeight(0);
    $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.3)->setRight(0.3);

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
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

    $totalColumnas = 2 + count($materias) + 1;
    $ultimaColumna = Coordinate::stringFromColumnIndex($totalColumnas);

    // Fila 1: Unidad Educativa
    $sheet->mergeCells("A1:{$ultimaColumna}1");
    $sheet->setCellValue('A1', 'UNIDAD EDUCATIVA SIMON BOLIVAR');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new Color('000000'));
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // Fila 2: Centralizador
    $sheet->mergeCells("A2:{$ultimaColumna}2");
    $sheet->setCellValue('A2', "CENTRALIZADOR - TRIMESTRE $trimestre");
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13)->setColor(new Color('000000'));
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(22);

    // Fila 3: Curso
    $sheet->mergeCells("A3:{$ultimaColumna}3");
    $sheet->setCellValue('A3', "Curso: $nombre_curso");
    $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11)->setColor(new Color('000000'));
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(3)->setRowHeight(20);

    // Fila 4: Encabezados
    $headerRow = 4;
    $sheet->setCellValue("A$headerRow", 'N°')->getStyle("A$headerRow")->applyFromArray($headerStyle);
    $sheet->setCellValue("B$headerRow", 'ESTUDIANTE')->getStyle("B$headerRow")->applyFromArray($headerStyle);
    $sheet->getColumnDimension('A')->setWidth(5);
    $sheet->getColumnDimension('B')->setWidth(40);

    $sheet->getRowDimension($headerRow)->setRowHeight(45);

    $colIndex = 3;
    $columnasMaterias = [];

    foreach ($materias as $mat) {
        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
        $cell = $colLetter . $headerRow;
        $sheet->setCellValue($cell, strtoupper($mat['nombre_materia']));
        $sheet->getStyle($cell)->applyFromArray($headerStyle);
        $sheet->getStyle($cell)->getAlignment()->setTextRotation(90);
        $sheet->getStyle($cell)->getFont()->setSize(8);
        $sheet->getColumnDimension($colLetter)->setWidth(5);
        $columnasMaterias[$colIndex] = $mat;
        $colIndex++;
    }

    // Columna promedio final
    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
    $cell = $colLetter . $headerRow;
    $sheet->setCellValue($cell, 'PROMEDIO');
    $sheet->getStyle($cell)->applyFromArray($headerStyle);
    $sheet->getStyle($cell)->getFont()->setSize(9);
    $sheet->getColumnDimension($colLetter)->setWidth(10);
    $colPromedioFinal = $colIndex;

    // LLENAR DATOS
    $row = $headerRow + 1;
    foreach ($estudiantes as $i => $est) {
        $idEst = (int)$est['id_estudiante'];
        $nombreCompletoMayus = strtoupper(
            ($est['apellido_paterno'] ?? '') . ' ' .
            ($est['apellido_materno'] ?? '') . ', ' .
            ($est['nombres'] ?? '')
        );

        $sheet->setCellValue("A$row", $i + 1);
        $sheet->setCellValue("B$row", $nombreCompletoMayus);
        $sheet->getStyle("A$row:B$row")->applyFromArray($cellStyle);
        $sheet->getStyle("B$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sum = 0;
        $count = 0;

        foreach ($columnasMaterias as $col => $matInfo) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $cell = $colLetter . $row;
            $idMateria = (int)$matInfo['id_materia'];

            $nota = $promediosMateriaTrimestre[$idEst][$idMateria] ?? '';
            $nota = (is_numeric($nota)) ? round((float)$nota, 2) : '';

            if ($nota !== '') {
                $sheet->setCellValue($cell, $nota);
            }

            if (is_numeric($nota) && !$matInfo['es_extra']) {
                $sum += (float)$nota;
                $count++;
            }

            if (is_numeric($nota) && (float)$nota < 51) {
                $sheet->getStyle($cell)->applyFromArray($lowGradeStyle);
            }

            $sheet->getStyle($cell)->applyFromArray($cellStyle);
        }

        $promedioFinal = $count > 0 ? round($sum / $count, 2) : '';
        $colLetter = Coordinate::stringFromColumnIndex($colPromedioFinal);
        $cell = $colLetter . $row;
        if ($promedioFinal !== '') {
            $sheet->setCellValue($cell, $promedioFinal);
        }
        $sheet->getStyle($cell)->applyFromArray($cellStyle);
        $row++;
    }

    if (isset($_GET['pdf'])) {
        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment;filename=\"Centralizador_{$nombre_curso}_T{$trimestre}.pdf\"");
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Tcpdf($spreadsheet);
        $writer->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
        $writer->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $writer->save('php://output');
    } else {
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
