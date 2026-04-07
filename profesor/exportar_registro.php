<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header('Location: ../index.php');
    exit();
}

$profesor_id = $_SESSION['user_id'];
$id_curso_materia = isset($_GET['curso_materia']) ? (int)$_GET['curso_materia'] : 0;
$trimestreExportar = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : 1;

if ($id_curso_materia <= 0 || $trimestreExportar <= 0) {
    die('Parámetros inválidos.');
}

$conn = (new Database())->connect();

// Configuración del sistema
$stmt = $conn->query("SELECT anio_escolar, modalidad_carga_notas FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
$configuracionSistema = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$gestionConfigurada = isset($configuracionSistema['anio_escolar']) ? trim((string)$configuracionSistema['anio_escolar']) : '';
$gestionActual = $gestionConfigurada !== '' ? $gestionConfigurada : date('Y');
$gestionAlternativa = null;
if (preg_match('/\b(20\d{2})\b/', $gestionActual, $matches)) {
    $gestionAlternativa = $matches[1];
}

// Curso y materia
$stmt = $conn->prepare("SELECT c.id_curso, c.nivel, m.id_materia,
                        CONCAT(c.nivel, ' ', c.curso, ' \"', c.paralelo, '\"') AS curso_nombre,
                        m.nombre_materia
                        FROM cursos_materias cm
                        JOIN cursos c ON cm.id_curso = c.id_curso
                        JOIN materias m ON cm.id_materia = m.id_materia
                        WHERE cm.id_curso_materia = ?");
$stmt->execute([$id_curso_materia]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
    die('Curso no encontrado.');
}

$es_inicial = ($curso['nivel'] == 'Inicial');
$campo = $es_inicial ? 'comentario' : 'calificacion';

// Estudiantes
$stmt = $conn->prepare("SELECT id_estudiante,
                        CASE
                            WHEN (apellido_paterno IS NULL OR apellido_paterno = '') AND (apellido_materno IS NOT NULL AND apellido_materno != '')
                            THEN CONCAT(apellido_materno, ' ', nombres)
                            ELSE CONCAT(apellido_paterno, ' ', apellido_materno, ' ', nombres)
                        END AS nombre
                        FROM estudiantes
                        WHERE id_curso = ?
                        ORDER BY
                        CASE
                            WHEN apellido_paterno IS NULL OR apellido_paterno = '' THEN 0
                            ELSE 1
                        END,
                        CASE
                            WHEN apellido_paterno IS NULL OR apellido_paterno = '' THEN apellido_materno
                            ELSE apellido_paterno
                        END,
                        apellido_materno,
                        nombres");
$stmt->execute([$curso['id_curso']]);
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Periodos del trimestre seleccionado
$sqlPeriodos = "SELECT id_periodo_evaluacion, trimestre, parcial, nombre
                FROM periodos_evaluacion
                WHERE trimestre = ? AND (gestion = ?";
$paramsPeriodos = [$trimestreExportar, $gestionActual];
if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
    $sqlPeriodos .= " OR gestion = ?";
    $paramsPeriodos[] = $gestionAlternativa;
}
$sqlPeriodos .= ") ORDER BY parcial";
$stmt = $conn->prepare($sqlPeriodos);
$stmt->execute($paramsPeriodos);
$periodosTrim = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map parcial => id_periodo
$parcialesMap = [];
foreach ($periodosTrim as $p) {
    $parcialesMap[(int)$p['parcial']] = (int)$p['id_periodo_evaluacion'];
}

// Notas parciales para este trimestre
$stmt = $conn->prepare("SELECT cp.id_estudiante, pe.parcial, cp.$campo AS valor,
                                cp.ser_total, cp.saber_total, cp.hacer_total
                        FROM calificaciones_parciales cp
                        INNER JOIN periodos_evaluacion pe ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
                        WHERE cp.id_materia = ?
                          AND pe.gestion = ?
                          AND pe.trimestre = ?
                        ORDER BY pe.parcial");
$stmt->execute([$curso['id_materia'], $gestionActual, $trimestreExportar]);
$notasPorParcial = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $notasPorParcial[(int)$row['parcial']][(int)$row['id_estudiante']] = [
        'valor' => $row['valor'],
        'ser_total' => $row['ser_total'],
        'saber_total' => $row['saber_total'],
        'hacer_total' => $row['hacer_total']
    ];
}

// Agregados por trimestre para cálculo anual (solo numérico)
$agregadoTrimestres = [];
if (!$es_inicial) {
    $sqlPromAnual = "SELECT cp.id_estudiante, pe.trimestre, cp.$campo AS valor
                     FROM calificaciones_parciales cp
                     INNER JOIN periodos_evaluacion pe ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
                     WHERE cp.id_materia = ? AND (pe.gestion = ?";
    $paramsPromAnual = [$curso['id_materia'], $gestionActual];
    if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
        $sqlPromAnual .= " OR pe.gestion = ?";
        $paramsPromAnual[] = $gestionAlternativa;
    }
    $sqlPromAnual .= ")";
    $stmtPromAnual = $conn->prepare($sqlPromAnual);
    $stmtPromAnual->execute($paramsPromAnual);
    foreach ($stmtPromAnual->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idEst = (int)$row['id_estudiante'];
        $trim = (int)$row['trimestre'];
        if (!isset($agregadoTrimestres[$idEst][$trim])) {
            $agregadoTrimestres[$idEst][$trim] = ['suma' => 0.0, 'contador' => 0];
        }
        $valor = $row['valor'];
        if ($valor !== null && $valor !== '' && is_numeric($valor)) {
            $agregadoTrimestres[$idEst][$trim]['suma'] += (float)$valor;
            $agregadoTrimestres[$idEst][$trim]['contador']++;
        }
    }
}

// Notas trimestrales (autoevaluación + extra)
$notasTrimestrales = [];
$notasTrimestralesPorTrimestre = [];
try {
    $stmt = $conn->prepare("SELECT id_estudiante, trimestre, autoevaluacion, nota_extra
                            FROM calificaciones_trimestrales
                            WHERE id_materia = ? AND gestion = ?");
    $stmt->execute([$curso['id_materia'], $gestionActual]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idEst = (int)$row['id_estudiante'];
        $trim = (int)$row['trimestre'];
        $notasTrimestralesPorTrimestre[$idEst][$trim] = [
            'autoevaluacion' => $row['autoevaluacion'],
            'nota_extra' => $row['nota_extra']
        ];
        if ($trim === $trimestreExportar) {
            $notasTrimestrales[$idEst] = [
                'autoevaluacion' => $row['autoevaluacion'],
                'nota_extra' => $row['nota_extra']
            ];
        }
    }
} catch (PDOException $e) {
    // Table may not exist yet
}

// Check if ser_total/saber_total/hacer_total columns exist
$hasAreaColumns = false;
try {
    $checkStmt = $conn->query("SELECT ser_total FROM calificaciones_parciales LIMIT 0");
    $hasAreaColumns = true;
} catch (PDOException $e) {
    $hasAreaColumns = false;
}

// Nombre del archivo
$nombreArchivo = 'Registro_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $curso['curso_nombre']) . '_' .
                 preg_replace('/[^a-zA-Z0-9_]/', '_', $curso['nombre_materia']) . '_T' .
                 $trimestreExportar . '_' . $gestionActual . '.xls';

// Headers
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// Helper: escape XML
function xmlEsc($str) {
    return htmlspecialchars((string)$str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// Determine max parciales
$maxParcial = 3;
if (!empty($parcialesMap)) {
    $maxParcial = max(array_keys($parcialesMap));
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Font ss:FontName="Calibri" ss:Size="11"/>
  </Style>
  <Style ss:ID="titulo">
   <Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1" ss:Color="#11305E"/>
  </Style>
  <Style ss:ID="subtitulo">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#475569"/>
  </Style>
  <Style ss:ID="header">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1E3A5F"/>
   <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="hSer">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#166534"/>
   <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
   </Borders>
  </Style>
  <Style ss:ID="hSaber">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1E40AF"/>
   <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
   </Borders>
  </Style>
  <Style ss:ID="hHacer">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#9A3412"/>
   <Interior ss:Color="#FFEDD5" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
   </Borders>
  </Style>
  <Style ss:ID="hTotal">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#6B21A8"/>
   <Interior ss:Color="#F3E8FF" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
   </Borders>
  </Style>
  <Style ss:ID="hAuto">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#92400E"/>
   <Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
   </Borders>
  </Style>
  <Style ss:ID="hExtra">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#3730A3"/>
   <Interior ss:Color="#E0E7FF" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
   </Borders>
  </Style>
  <Style ss:ID="num">
   <Alignment ss:Horizontal="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#94A3B8"/>
  </Style>
  <Style ss:ID="nombre">
   <Font ss:FontName="Calibri" ss:Size="10"/>
  </Style>
  <Style ss:ID="nota">
   <Alignment ss:Horizontal="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10"/>
   <NumberFormat ss:Format="0.00"/>
  </Style>
  <Style ss:ID="notaBold">
   <Alignment ss:Horizontal="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1"/>
   <NumberFormat ss:Format="0.00"/>
  </Style>
  <Style ss:ID="notaTotal">
   <Alignment ss:Horizontal="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#6B21A8"/>
   <Interior ss:Color="#FAF5FF" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="0.00"/>
  </Style>
  <Style ss:ID="notaFinal">
   <Alignment ss:Horizontal="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#166534"/>
   <Interior ss:Color="#F0FDF4" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="0.00"/>
  </Style>
 </Styles>

<?php
// =====================================================
// HOJAS DE PARCIALES (1 por parcial)
// =====================================================
for ($px = 1; $px <= $maxParcial; $px++):
    $dataParcial = $notasPorParcial[$px] ?? [];
?>
 <Worksheet ss:Name="Parcial <?php echo $px; ?>">
  <Table>
   <Column ss:Width="30"/>
   <Column ss:Width="220"/>
<?php if ($es_inicial): ?>
   <Column ss:Width="400"/>
<?php elseif ($hasAreaColumns): ?>
   <Column ss:Width="70"/>
   <Column ss:Width="70"/>
   <Column ss:Width="70"/>
   <Column ss:Width="75"/>
<?php else: ?>
   <Column ss:Width="80"/>
<?php endif; ?>
   <Row>
    <Cell ss:StyleID="titulo"><Data ss:Type="String"><?php echo xmlEsc($curso['curso_nombre'] . ' — ' . $curso['nombre_materia']); ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="subtitulo"><Data ss:Type="String">Gestión <?php echo xmlEsc($gestionActual); ?> — Trimestre <?php echo $trimestreExportar; ?> — Parcial <?php echo $px; ?></Data></Cell>
   </Row>
   <Row/>
   <Row>
    <Cell ss:StyleID="header"><Data ss:Type="String">#</Data></Cell>
    <Cell ss:StyleID="header"><Data ss:Type="String">Estudiante</Data></Cell>
<?php if ($es_inicial): ?>
    <Cell ss:StyleID="header"><Data ss:Type="String">Comentario</Data></Cell>
<?php elseif ($hasAreaColumns): ?>
    <Cell ss:StyleID="hSer"><Data ss:Type="String">SER (10)</Data></Cell>
    <Cell ss:StyleID="hSaber"><Data ss:Type="String">SABER (45)</Data></Cell>
    <Cell ss:StyleID="hHacer"><Data ss:Type="String">HACER (40)</Data></Cell>
    <Cell ss:StyleID="hTotal"><Data ss:Type="String">TOTAL (95)</Data></Cell>
<?php else: ?>
    <Cell ss:StyleID="hTotal"><Data ss:Type="String">Nota</Data></Cell>
<?php endif; ?>
   </Row>
<?php $n = 1; ?>
<?php foreach ($estudiantes as $est): ?>
<?php
    $idEst = (int)$est['id_estudiante'];
    $data = $dataParcial[$idEst] ?? null;
?>
   <Row>
    <Cell ss:StyleID="num"><Data ss:Type="Number"><?php echo $n++; ?></Data></Cell>
    <Cell ss:StyleID="nombre"><Data ss:Type="String"><?php echo xmlEsc($est['nombre']); ?></Data></Cell>
<?php if ($es_inicial): ?>
    <Cell ss:StyleID="nombre"><Data ss:Type="String"><?php echo xmlEsc($data['valor'] ?? ''); ?></Data></Cell>
<?php elseif ($hasAreaColumns && $data): ?>
    <Cell ss:StyleID="nota"><Data ss:Type="Number"><?php echo (float)($data['ser_total'] ?? 0); ?></Data></Cell>
    <Cell ss:StyleID="nota"><Data ss:Type="Number"><?php echo (float)($data['saber_total'] ?? 0); ?></Data></Cell>
    <Cell ss:StyleID="nota"><Data ss:Type="Number"><?php echo (float)($data['hacer_total'] ?? 0); ?></Data></Cell>
    <Cell ss:StyleID="notaTotal"><Data ss:Type="Number"><?php echo (float)($data['valor'] ?? 0); ?></Data></Cell>
<?php elseif ($data && $data['valor'] !== null && $data['valor'] !== ''): ?>
    <Cell ss:StyleID="nota"><Data ss:Type="Number"><?php echo (float)$data['valor']; ?></Data></Cell>
<?php else: ?>
    <Cell ss:StyleID="nota"><Data ss:Type="String"></Data></Cell>
<?php endif; ?>
   </Row>
<?php endforeach; ?>
  </Table>
 </Worksheet>
<?php endfor; ?>

<?php
// =====================================================
// HOJA RESUMEN TRIMESTRAL
// =====================================================
?>
 <Worksheet ss:Name="Resumen T<?php echo $trimestreExportar; ?>">
  <Table>
   <Column ss:Width="30"/>
   <Column ss:Width="220"/>
<?php for ($px = 1; $px <= $maxParcial; $px++): ?>
   <Column ss:Width="70"/>
<?php endfor; ?>
   <Column ss:Width="80"/>
   <Column ss:Width="70"/>
   <Column ss:Width="70"/>
   <Column ss:Width="80"/>
   <Row>
    <Cell ss:StyleID="titulo"><Data ss:Type="String"><?php echo xmlEsc($curso['curso_nombre'] . ' — ' . $curso['nombre_materia']); ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="subtitulo"><Data ss:Type="String">Gestión <?php echo xmlEsc($gestionActual); ?> — Resumen Trimestre <?php echo $trimestreExportar; ?></Data></Cell>
   </Row>
   <Row/>
   <Row>
    <Cell ss:StyleID="header"><Data ss:Type="String">#</Data></Cell>
    <Cell ss:StyleID="header"><Data ss:Type="String">Estudiante</Data></Cell>
<?php for ($px = 1; $px <= $maxParcial; $px++): ?>
    <Cell ss:StyleID="header"><Data ss:Type="String">P<?php echo $px; ?></Data></Cell>
<?php endfor; ?>
    <Cell ss:StyleID="hTotal"><Data ss:Type="String">Prom 95</Data></Cell>
    <Cell ss:StyleID="hAuto"><Data ss:Type="String">Auto (5)</Data></Cell>
    <Cell ss:StyleID="hExtra"><Data ss:Type="String">Extra</Data></Cell>
    <Cell ss:StyleID="hTotal"><Data ss:Type="String">TOTAL</Data></Cell>
   </Row>
<?php $n = 1; ?>
<?php foreach ($estudiantes as $est): ?>
<?php
    $idEst = (int)$est['id_estudiante'];
    $parcialesVals = [];
    for ($px = 1; $px <= $maxParcial; $px++) {
        $d = $notasPorParcial[$px][$idEst] ?? null;
        $parcialesVals[$px] = ($d && $d['valor'] !== null && $d['valor'] !== '' && is_numeric($d['valor']))
            ? (float)$d['valor'] : null;
    }
    $valsNoNull = array_filter($parcialesVals, fn($v) => $v !== null);
    $prom95 = count($valsNoNull) > 0 ? array_sum($valsNoNull) / count($valsNoNull) : null;

    $trimData = $notasTrimestrales[$idEst] ?? [];
    $autoVal = isset($trimData['autoevaluacion']) && $trimData['autoevaluacion'] !== null ? (float)$trimData['autoevaluacion'] : null;
    $extraVal = isset($trimData['nota_extra']) && $trimData['nota_extra'] !== null ? (float)$trimData['nota_extra'] : null;

    $total = ($prom95 ?? 0) + ($autoVal ?? 0) + ($extraVal ?? 0);
    $hasAnyData = $prom95 !== null || $autoVal !== null || $extraVal !== null;
?>
   <Row>
    <Cell ss:StyleID="num"><Data ss:Type="Number"><?php echo $n++; ?></Data></Cell>
    <Cell ss:StyleID="nombre"><Data ss:Type="String"><?php echo xmlEsc($est['nombre']); ?></Data></Cell>
<?php for ($px = 1; $px <= $maxParcial; $px++): ?>
<?php if ($parcialesVals[$px] !== null): ?>
    <Cell ss:StyleID="nota"><Data ss:Type="Number"><?php echo $parcialesVals[$px]; ?></Data></Cell>
<?php else: ?>
    <Cell ss:StyleID="nota"><Data ss:Type="String"></Data></Cell>
<?php endif; ?>
<?php endfor; ?>
<?php if ($prom95 !== null): ?>
    <Cell ss:StyleID="notaTotal"><Data ss:Type="Number"><?php echo round($prom95, 2); ?></Data></Cell>
<?php else: ?>
    <Cell ss:StyleID="notaTotal"><Data ss:Type="String"></Data></Cell>
<?php endif; ?>
<?php if ($autoVal !== null): ?>
    <Cell ss:StyleID="nota"><Data ss:Type="Number"><?php echo round($autoVal, 2); ?></Data></Cell>
<?php else: ?>
    <Cell ss:StyleID="nota"><Data ss:Type="String"></Data></Cell>
<?php endif; ?>
<?php if ($extraVal !== null): ?>
    <Cell ss:StyleID="nota"><Data ss:Type="Number"><?php echo round($extraVal, 2); ?></Data></Cell>
<?php else: ?>
    <Cell ss:StyleID="nota"><Data ss:Type="String"></Data></Cell>
<?php endif; ?>
<?php if ($hasAnyData): ?>
    <Cell ss:StyleID="notaFinal"><Data ss:Type="Number"><?php echo round($total, 2); ?></Data></Cell>
<?php else: ?>
    <Cell ss:StyleID="notaFinal"><Data ss:Type="String"></Data></Cell>
<?php endif; ?>
   </Row>
<?php endforeach; ?>
  </Table>
 </Worksheet>
<?php if (!$es_inicial): ?>
<?php
    $trimestresAnualesMapa = [];
    foreach ($agregadoTrimestres as $porTrim) {
        foreach ($porTrim as $trim => $ign) {
            $trimestresAnualesMapa[$trim] = true;
        }
    }
    foreach ($notasTrimestralesPorTrimestre as $porTrim) {
        foreach ($porTrim as $trim => $ign) {
            $trimestresAnualesMapa[$trim] = true;
        }
    }
    if (empty($trimestresAnualesMapa)) {
        $trimestresAnualesMapa = [1 => true, 2 => true, 3 => true];
    }
    $trimestresAnuales = array_keys($trimestresAnualesMapa);
    sort($trimestresAnuales);
?>
 <Worksheet ss:Name="Promedio Anual">
  <Table>
   <Column ss:Width="30"/>
   <Column ss:Width="230"/>
<?php foreach ($trimestresAnuales as $trim): ?>
   <Column ss:Width="80"/>
<?php endforeach; ?>
   <Column ss:Width="90"/>
   <Row>
    <Cell ss:StyleID="titulo"><Data ss:Type="String"><?php echo xmlEsc($curso['curso_nombre'] . ' — ' . $curso['nombre_materia']); ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="subtitulo"><Data ss:Type="String">Gestión <?php echo xmlEsc($gestionActual); ?> — Promedio anual de calificaciones</Data></Cell>
   </Row>
   <Row/>
   <Row>
    <Cell ss:StyleID="header"><Data ss:Type="String">#</Data></Cell>
    <Cell ss:StyleID="header"><Data ss:Type="String">Estudiante</Data></Cell>
<?php foreach ($trimestresAnuales as $trim): ?>
    <Cell ss:StyleID="hTotal"><Data ss:Type="String">T<?php echo $trim; ?></Data></Cell>
<?php endforeach; ?>
    <Cell ss:StyleID="hAuto"><Data ss:Type="String">Promedio Anual</Data></Cell>
   </Row>
<?php $n = 1; ?>
<?php foreach ($estudiantes as $est): ?>
<?php
    $idEst = (int)$est['id_estudiante'];
    $totalesPorTrimestre = [];
    foreach ($trimestresAnuales as $trim) {
        $prom95 = null;
        if (isset($agregadoTrimestres[$idEst][$trim])) {
            $dataTrim = $agregadoTrimestres[$idEst][$trim];
            if ($dataTrim['contador'] > 0) {
                $prom95 = $dataTrim['suma'] / $dataTrim['contador'];
            }
        }
        $autoData = $notasTrimestralesPorTrimestre[$idEst][$trim]['autoevaluacion'] ?? null;
        $extraData = $notasTrimestralesPorTrimestre[$idEst][$trim]['nota_extra'] ?? null;
        $autoVal = ($autoData !== null && $autoData !== '' && is_numeric($autoData)) ? (float)$autoData : null;
        $extraVal = ($extraData !== null && $extraData !== '' && is_numeric($extraData)) ? (float)$extraData : null;
        $totalTrim = null;
        if ($prom95 !== null || $autoVal !== null || $extraVal !== null) {
            $totalTrim = ($prom95 ?? 0) + ($autoVal ?? 0) + ($extraVal ?? 0);
        }
        $totalesPorTrimestre[$trim] = $totalTrim;
    }
    $totalesValidos = array_filter($totalesPorTrimestre, fn($v) => $v !== null);
    $promAnual = count($totalesValidos) > 0 ? array_sum($totalesValidos) / count($totalesValidos) : null;
?>
   <Row>
    <Cell ss:StyleID="num"><Data ss:Type="Number"><?php echo $n++; ?></Data></Cell>
    <Cell ss:StyleID="nombre"><Data ss:Type="String"><?php echo xmlEsc($est['nombre']); ?></Data></Cell>
<?php foreach ($trimestresAnuales as $trim): ?>
<?php $totalTrim = $totalesPorTrimestre[$trim]; ?>
<?php if ($totalTrim !== null): ?>
    <Cell ss:StyleID="nota"><Data ss:Type="Number"><?php echo round($totalTrim, 2); ?></Data></Cell>
<?php else: ?>
    <Cell ss:StyleID="nota"><Data ss:Type="String"></Data></Cell>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($promAnual !== null): ?>
    <Cell ss:StyleID="notaFinal"><Data ss:Type="Number"><?php echo round($promAnual, 2); ?></Data></Cell>
<?php else: ?>
    <Cell ss:StyleID="notaFinal"><Data ss:Type="String"></Data></Cell>
<?php endif; ?>
   </Row>
<?php endforeach; ?>
  </Table>
 </Worksheet>
<?php endif; ?>
</Workbook>
