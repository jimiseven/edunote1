<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/nota_extra_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header('Location: ../index.php');
    exit();
}

$profesor_nombre = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Profesor';
$id_curso_materia = isset($_GET['curso_materia']) ? (int)$_GET['curso_materia'] : 0;
$trimestre = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : 0;
$parcial = isset($_GET['parcial']) ? (int)$_GET['parcial'] : 0;

if ($id_curso_materia <= 0 || $trimestre <= 0 || $parcial <= 0) {
    die('Parametros invalidos.');
}

$conn = (new Database())->connect();

$stmt = $conn->query("SELECT anio_escolar FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
$gestion = trim((string)$stmt->fetchColumn());
if ($gestion === '') {
    $gestion = date('Y');
}
$gestionAlternativa = null;
if (preg_match('/\b(20\d{2})\b/', $gestion, $mGestion)) {
    $gestionAlternativa = $mGestion[1];
}

$stmtCurso = $conn->prepare("SELECT c.id_curso, c.nivel, m.id_materia,
    CONCAT(c.nivel, ' ', c.curso, ' \"', c.paralelo, '\"') AS curso_nombre,
    m.nombre_materia
    FROM cursos_materias cm
    JOIN cursos c ON cm.id_curso = c.id_curso
    JOIN materias m ON cm.id_materia = m.id_materia
    WHERE cm.id_curso_materia = ?");
$stmtCurso->execute([$id_curso_materia]);
$curso = $stmtCurso->fetch(PDO::FETCH_ASSOC);
if (!$curso) {
    die('Curso/materia no encontrado.');
}

$stmtPeriodo = $conn->prepare("SELECT id_periodo_evaluacion
    FROM periodos_evaluacion
    WHERE trimestre = ? AND parcial = ? AND (gestion = ?" . ($gestionAlternativa !== null && $gestionAlternativa !== $gestion ? " OR gestion = ?" : "") . ")
    ORDER BY CASE WHEN gestion = ? THEN 0 ELSE 1 END
    LIMIT 1");
$paramsPeriodo = [$trimestre, $parcial, $gestion];
if ($gestionAlternativa !== null && $gestionAlternativa !== $gestion) {
    $paramsPeriodo[] = $gestionAlternativa;
}
$paramsPeriodo[] = $gestion;
$stmtPeriodo->execute($paramsPeriodo);
$idPeriodo = (int)$stmtPeriodo->fetchColumn();
if ($idPeriodo <= 0) {
    die('Periodo no encontrado.');
}

// Cargar etiquetas de actividades
$etiquetasActividades = ['SER' => [], 'SABER' => [], 'HACER' => []];
for ($i = 1; $i <= 4; $i++) {
    $etiquetasActividades['SER'][$i] = 'SER ' . $i;
}
for ($i = 1; $i <= 8; $i++) {
    $etiquetasActividades['SABER'][$i] = 'SABER ' . $i;
    $etiquetasActividades['HACER'][$i] = 'HACER ' . $i;
}

$stmtEtiquetas = $conn->prepare('SELECT area, indice, etiqueta
    FROM parciales_etiquetas_actividades
    WHERE id_curso = ? AND id_materia = ? AND id_periodo_evaluacion = ?');
$stmtEtiquetas->execute([(int)$curso['id_curso'], (int)$curso['id_materia'], $idPeriodo]);
foreach ($stmtEtiquetas->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $area = $row['area'];
    $idx = (int)$row['indice'];
    if (!empty($row['etiqueta'])) {
        $etiquetasActividades[$area][$idx] = $row['etiqueta'];
    }
}

$stmtEst = $conn->prepare("SELECT id_estudiante,
    CASE
        WHEN (apellido_paterno IS NULL OR apellido_paterno = '') AND (apellido_materno IS NOT NULL AND apellido_materno != '')
        THEN CONCAT(apellido_materno, ' ', nombres)
        ELSE CONCAT(apellido_paterno, ' ', apellido_materno, ' ', nombres)
    END AS nombre
    FROM estudiantes
    WHERE id_curso = ?
    ORDER BY
    CASE WHEN apellido_paterno IS NULL OR apellido_paterno = '' THEN 0 ELSE 1 END,
    CASE WHEN apellido_paterno IS NULL OR apellido_paterno = '' THEN apellido_materno ELSE apellido_paterno END,
    apellido_materno, nombres");
$stmtEst->execute([(int)$curso['id_curso']]);
$estudiantes = $stmtEst->fetchAll(PDO::FETCH_ASSOC);

$detalleNotas = [];
$totales = [];

$hasDetalle = false;
try {
    $conn->query('SELECT 1 FROM calificaciones_parciales_detalle LIMIT 1');
    $hasDetalle = true;
} catch (PDOException $e) {
    $hasDetalle = false;
}

if ($hasDetalle) {
    $stmtDet = $conn->prepare("SELECT cp.id_estudiante, cpd.area, cpd.indice, cpd.nota,
        cp.ser_total, cp.saber_total, cp.hacer_total, cp.calificacion
        FROM calificaciones_parciales cp
        LEFT JOIN calificaciones_parciales_detalle cpd ON cpd.id_calificacion_parcial = cp.id_calificacion_parcial
        WHERE cp.id_materia = ? AND cp.id_periodo_evaluacion = ?");
    $stmtDet->execute([(int)$curso['id_materia'], $idPeriodo]);
    foreach ($stmtDet->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idEst = (int)$row['id_estudiante'];
        if (!isset($totales[$idEst])) {
            $totales[$idEst] = [
                'ser_total' => (float)($row['ser_total'] ?? 0),
                'saber_total' => (float)($row['saber_total'] ?? 0),
                'hacer_total' => (float)($row['hacer_total'] ?? 0),
                'calificacion' => (float)($row['calificacion'] ?? 0),
            ];
        }
        if (!empty($row['area']) && $row['indice'] !== null && $row['nota'] !== null) {
            $detalleNotas[$idEst][$row['area']][(int)$row['indice']] = (float)$row['nota'];
        }
    }
} else {
    $stmtTot = $conn->prepare("SELECT id_estudiante, ser_total, saber_total, hacer_total, calificacion
        FROM calificaciones_parciales
        WHERE id_materia = ? AND id_periodo_evaluacion = ?");
    $stmtTot->execute([(int)$curso['id_materia'], $idPeriodo]);
    foreach ($stmtTot->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idEst = (int)$row['id_estudiante'];
        $totales[$idEst] = [
            'ser_total' => (float)($row['ser_total'] ?? 0),
            'saber_total' => (float)($row['saber_total'] ?? 0),
            'hacer_total' => (float)($row['hacer_total'] ?? 0),
            'calificacion' => (float)($row['calificacion'] ?? 0),
        ];
    }
}

$detalleNotasCal = [];
$stmtCalPorTrimestre = $conn->prepare("SELECT cp.id_estudiante, pe.parcial, cp.calificacion
    FROM calificaciones_parciales cp
    INNER JOIN periodos_evaluacion pe ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
    WHERE cp.id_materia = ? AND pe.trimestre = ? AND (pe.gestion = ?" . ($gestionAlternativa !== null && $gestionAlternativa !== $gestion ? " OR pe.gestion = ?" : "") . ")");
$paramsCal = [(int)$curso['id_materia'], $trimestre, $gestion];
if ($gestionAlternativa !== null && $gestionAlternativa !== $gestion) {
    $paramsCal[] = $gestionAlternativa;
}
$stmtCalPorTrimestre->execute($paramsCal);
foreach ($stmtCalPorTrimestre->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $detalleNotasCal[(int)$row['id_estudiante']][(int)$row['parcial']] = $row['calificacion'];
}

$notaExtraTrimestre = [];
try {
    $stmtExtra = $conn->prepare("SELECT id_estudiante, nota_extra
        FROM calificaciones_trimestrales
        WHERE id_materia = ? AND gestion = ? AND trimestre = ?");
    $stmtExtra->execute([(int)$curso['id_materia'], $gestion, $trimestre]);
    foreach ($stmtExtra->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $notaExtraTrimestre[(int)$row['id_estudiante']] = (float)$row['nota_extra'];
    }
} catch (PDOException $e) {
}

$nombreArchivo = 'Parcial_T' . $trimestre . '_P' . $parcial . '_' .
    preg_replace('/[^a-zA-Z0-9_]/', '_', $curso['curso_nombre']) . '_' .
    preg_replace('/[^a-zA-Z0-9_]/', '_', $curso['nombre_materia']) . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

function xmlEscape($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// SpreadsheetML expresa ss:Width en puntos, no en cantidad de caracteres.
// El ancho se calcula desde el nombre más largo y conserva margen para evitar solapamiento.
$maxNombreLen = 0;
foreach ($estudiantes as $est) {
    $nombreLen = mb_strlen(trim((string)$est['nombre']), 'UTF-8');
    if ($nombreLen > $maxNombreLen) {
        $maxNombreLen = $nombreLen;
    }
}
$wNombreParcial = max(220, ($maxNombreLen + 4) * 7.2);

$wNum = 24;
$wAreaDet = 18;
$wProm = 22;
$wTotal = 26;

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
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#000000"/>
   <Alignment ss:Vertical="Bottom"/>
  </Style>
  <Style ss:ID="num">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="9" ss:Color="#000000"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="nombre">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:Indent="1"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#000000"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="nota">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#000000"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="notaTotal">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#000000"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="notaFinal">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#000000"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="header">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#000000"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="unidadEducativa">
   <Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1" ss:Color="#000000"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="infoHeader">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#000000"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="titulo">
   <Font ss:FontName="Calibri" ss:Size="13" ss:Bold="1" ss:Color="#000000"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="subtitulo">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#000000"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="hSer">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#166534"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="hSaber">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1E40AF"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="hHacer">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#9A3412"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Interior ss:Color="#FFEDD5" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="hTotal">
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#6B21A8"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Interior ss:Color="#F3E8FF" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="hSerVert">
   <Font ss:FontName="Calibri" ss:Size="8" ss:Bold="1" ss:Color="#166534"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Bottom" ss:Rotate="90" ss:WrapText="1"/>
   <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="hSaberVert">
   <Font ss:FontName="Calibri" ss:Size="8" ss:Bold="1" ss:Color="#1E40AF"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Bottom" ss:Rotate="90" ss:WrapText="1"/>
   <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="hHacerVert">
   <Font ss:FontName="Calibri" ss:Size="8" ss:Bold="1" ss:Color="#9A3412"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Bottom" ss:Rotate="90" ss:WrapText="1"/>
   <Interior ss:Color="#FFEDD5" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="hSerPromVert">
   <Font ss:FontName="Calibri" ss:Size="8" ss:Bold="1" ss:Color="#166534"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Bottom" ss:Rotate="90"/>
   <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="hSaberPromVert">
   <Font ss:FontName="Calibri" ss:Size="8" ss:Bold="1" ss:Color="#1E40AF"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Bottom" ss:Rotate="90"/>
   <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="hHacerPromVert">
   <Font ss:FontName="Calibri" ss:Size="8" ss:Bold="1" ss:Color="#9A3412"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Bottom" ss:Rotate="90"/>
   <Interior ss:Color="#FFEDD5" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
  <Style ss:ID="hTotalVert">
   <Font ss:FontName="Calibri" ss:Size="8" ss:Bold="1" ss:Color="#6B21A8"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Bottom" ss:Rotate="90"/>
   <Interior ss:Color="#F3E8FF" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>
   </Borders>
  </Style>
 </Styles>
 <Worksheet ss:Name="Parcial <?php echo (int)$parcial; ?>">
  <Table>
   <Column ss:Width="<?php echo $wNum; ?>"/>
   <Column ss:Width="<?php echo $wNombreParcial; ?>"/>
<?php for ($px = 1; $px <= 4; $px++): ?>
   <Column ss:Width="<?php echo $wAreaDet; ?>"/>
<?php endfor; ?>
   <Column ss:Width="<?php echo $wProm; ?>"/>
<?php for ($px = 1; $px <= 8; $px++): ?>
   <Column ss:Width="<?php echo $wAreaDet; ?>"/>
<?php endfor; ?>
   <Column ss:Width="<?php echo $wProm; ?>"/>
<?php for ($px = 1; $px <= 8; $px++): ?>
   <Column ss:Width="<?php echo $wAreaDet; ?>"/>
<?php endfor; ?>
   <Column ss:Width="<?php echo $wProm; ?>"/>
   <Column ss:Width="<?php echo $wTotal; ?>"/>
   <Row>
    <Cell ss:StyleID="unidadEducativa" ss:MergeAcross="25"><Data ss:Type="String">Unidad Educativa "Simón Bolívar"</Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="infoHeader" ss:MergeAcross="25"><Data ss:Type="String">Nombre del profesor/a: <?php echo xmlEscape($profesor_nombre); ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="infoHeader" ss:MergeAcross="25"><Data ss:Type="String">Nombre de la directora: Lic. NORKA MALDONADO ROCHA</Data></Cell>
   </Row>
   <Row/>
   <Row>
    <Cell ss:StyleID="titulo" ss:MergeAcross="25"><Data ss:Type="String"><?php echo xmlEscape($curso['curso_nombre'] . ' — ' . $curso['nombre_materia']); ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="subtitulo" ss:MergeAcross="25"><Data ss:Type="String">Gestión <?php echo xmlEscape($gestion); ?> — Trimestre <?php echo (int)$trimestre; ?> — Parcial <?php echo (int)$parcial; ?></Data></Cell>
   </Row>
   <Row/>
   <Row>
    <Cell ss:StyleID="header" ss:Index="3" ss:MergeAcross="4"><Data ss:Type="String">SER</Data></Cell>
    <Cell ss:StyleID="header" ss:MergeAcross="8"><Data ss:Type="String">SABER</Data></Cell>
    <Cell ss:StyleID="header" ss:MergeAcross="8"><Data ss:Type="String">HACER</Data></Cell>
   </Row>
   <Row ss:Height="100">
    <Cell ss:StyleID="header"><Data ss:Type="String">#</Data></Cell>
    <Cell ss:StyleID="header"><Data ss:Type="String">Estudiante</Data></Cell>
<?php for ($px = 1; $px <= 4; $px++): ?>
    <Cell ss:StyleID="hSerVert"><Data ss:Type="String"><?php echo xmlEscape($etiquetasActividades['SER'][$px]); ?></Data></Cell>
<?php endfor; ?>
    <Cell ss:StyleID="hSerPromVert"><Data ss:Type="String">Prom</Data></Cell>
<?php for ($px = 1; $px <= 8; $px++): ?>
    <Cell ss:StyleID="hSaberVert"><Data ss:Type="String"><?php echo xmlEscape($etiquetasActividades['SABER'][$px]); ?></Data></Cell>
<?php endfor; ?>
    <Cell ss:StyleID="hSaberPromVert"><Data ss:Type="String">Prom</Data></Cell>
<?php for ($px = 1; $px <= 8; $px++): ?>
    <Cell ss:StyleID="hHacerVert"><Data ss:Type="String"><?php echo xmlEscape($etiquetasActividades['HACER'][$px]); ?></Data></Cell>
<?php endfor; ?>
    <Cell ss:StyleID="hHacerPromVert"><Data ss:Type="String">Prom</Data></Cell>
    <Cell ss:StyleID="hTotalVert"><Data ss:Type="String">TOTAL 95</Data></Cell>
   </Row>
<?php $n = 1; foreach ($estudiantes as $est):
    $idEst = (int)$est['id_estudiante'];
    $det = $detalleNotas[$idEst] ?? [];
    $tot = $totales[$idEst] ?? ['ser_total' => 0, 'saber_total' => 0, 'hacer_total' => 0, 'calificacion' => 0];
    $p1 = $detalleNotasCal[$idEst][1] ?? null;
    $p2 = $detalleNotasCal[$idEst][2] ?? null;
    $p3 = $detalleNotasCal[$idEst][3] ?? null;
    $extraE = $notaExtraTrimestre[$idEst] ?? 0.0;
    $rep = repartirNotaExtra([
        1 => ($p1 !== null && $p1 !== '' && is_numeric($p1)) ? (float)$p1 : null,
        2 => ($p2 !== null && $p2 !== '' && is_numeric($p2)) ? (float)$p2 : null,
        3 => ($p3 !== null && $p3 !== '' && is_numeric($p3)) ? (float)$p3 : null,
    ], (float)$extraE);
    $valsRep = array_filter($rep, fn($v) => $v !== null);
    $promConBono = !empty($valsRep) ? array_sum($valsRep) / count($valsRep) : (float)$tot['calificacion'];
?>
   <Row>
    <Cell ss:StyleID="num"><Data ss:Type="Number"><?php echo $n++; ?></Data></Cell>
    <Cell ss:StyleID="nombre"><Data ss:Type="String"><?php echo xmlEscape($est['nombre']); ?></Data></Cell>
<?php for ($i = 1; $i <= 4; $i++): ?>
    <Cell ss:StyleID="nota"><?php if (isset($det['SER'][$i])): ?><Data ss:Type="Number"><?php echo (int)round((float)$det['SER'][$i]); ?></Data><?php else: ?><Data ss:Type="String"></Data><?php endif; ?></Cell>
<?php endfor; ?>
    <Cell ss:StyleID="notaTotal"><Data ss:Type="Number"><?php echo (int)round((float)$tot['ser_total']); ?></Data></Cell>
<?php for ($i = 1; $i <= 8; $i++): ?>
    <Cell ss:StyleID="nota"><?php if (isset($det['SABER'][$i])): ?><Data ss:Type="Number"><?php echo (int)round((float)$det['SABER'][$i]); ?></Data><?php else: ?><Data ss:Type="String"></Data><?php endif; ?></Cell>
<?php endfor; ?>
    <Cell ss:StyleID="notaTotal"><Data ss:Type="Number"><?php echo (int)round((float)$tot['saber_total']); ?></Data></Cell>
<?php for ($i = 1; $i <= 8; $i++): ?>
    <Cell ss:StyleID="nota"><?php if (isset($det['HACER'][$i])): ?><Data ss:Type="Number"><?php echo (int)round((float)$det['HACER'][$i]); ?></Data><?php else: ?><Data ss:Type="String"></Data><?php endif; ?></Cell>
<?php endfor; ?>
    <Cell ss:StyleID="notaTotal"><Data ss:Type="Number"><?php echo (int)round((float)$tot['hacer_total']); ?></Data></Cell>
    <Cell ss:StyleID="notaFinal"><Data ss:Type="Number"><?php echo (int)round($promConBono); ?></Data></Cell>
   </Row>
<?php endforeach; ?>
  </Table>
  <PageSetup ss:Orientation="Landscape" ss:PaperSize="1" ss:FitToWidth="1" ss:FitToHeight="0"/>
  <PrintOptions ss:FitToPage="1"/>
 </Worksheet>
</Workbook>
