<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header('Location: ../index.php');
    exit();
}

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

$nombreArchivo = 'Parcial_T' . $trimestre . '_P' . $parcial . '_' .
    preg_replace('/[^a-zA-Z0-9_]/', '_', $curso['curso_nombre']) . '_' .
    preg_replace('/[^a-zA-Z0-9_]/', '_', $curso['nombre_materia']) . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');
echo "\xEF\xBB\xBF";
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<style>
    th { border: 1px solid #999; padding: 4px 6px; font-size: 11px; text-align: center; font-weight: 700; }
    td { border: 1px solid #ccc; padding: 3px 5px; font-size: 11px; text-align: center; }
    .nombre { text-align: left; min-width: 220px; }
    .th-ser { background:#dcfce7; color:#166534; }
    .th-saber { background:#dbeafe; color:#1e40af; }
    .th-hacer { background:#ffedd5; color:#9a3412; }
    .th-total { background:#f3e8ff; color:#6b21a8; }
    .num { width: 36px; }
</style>
</head>
<body>
<table>
    <tr><td colspan="27" style="font-size:14px;font-weight:bold;text-align:left;"><?php echo htmlspecialchars($curso['curso_nombre']); ?> - <?php echo htmlspecialchars($curso['nombre_materia']); ?></td></tr>
    <tr><td colspan="27" style="font-size:12px;text-align:left;">Gestion <?php echo htmlspecialchars($gestion); ?> - Trimestre <?php echo (int)$trimestre; ?> Parcial <?php echo (int)$parcial; ?></td></tr>
    <tr><td colspan="27"></td></tr>
    <tr>
        <th rowspan="2">#</th>
        <th rowspan="2">Estudiante</th>
        <th class="th-ser" colspan="5">SER</th>
        <th class="th-saber" colspan="9">SABER</th>
        <th class="th-hacer" colspan="9">HACER</th>
        <th class="th-total" rowspan="2">TOTAL 95</th>
    </tr>
    <tr>
        <th class="th-ser">1</th><th class="th-ser">2</th><th class="th-ser">3</th><th class="th-ser">4</th><th class="th-ser">Prom</th>
        <th class="th-saber">1</th><th class="th-saber">2</th><th class="th-saber">3</th><th class="th-saber">4</th><th class="th-saber">5</th><th class="th-saber">6</th><th class="th-saber">7</th><th class="th-saber">8</th><th class="th-saber">Prom</th>
        <th class="th-hacer">1</th><th class="th-hacer">2</th><th class="th-hacer">3</th><th class="th-hacer">4</th><th class="th-hacer">5</th><th class="th-hacer">6</th><th class="th-hacer">7</th><th class="th-hacer">8</th><th class="th-hacer">Prom</th>
    </tr>
    <?php $n = 1; foreach ($estudiantes as $est):
        $idEst = (int)$est['id_estudiante'];
        $det = $detalleNotas[$idEst] ?? [];
        $tot = $totales[$idEst] ?? ['ser_total' => 0, 'saber_total' => 0, 'hacer_total' => 0, 'calificacion' => 0];
    ?>
    <tr>
        <td class="num"><?php echo $n++; ?></td>
        <td class="nombre"><?php echo htmlspecialchars($est['nombre']); ?></td>
        <?php for ($i=1;$i<=4;$i++): ?><td><?php echo isset($det['SER'][$i]) ? number_format((float)$det['SER'][$i], 2) : ''; ?></td><?php endfor; ?>
        <td><?php echo number_format((float)$tot['ser_total'], 2); ?></td>
        <?php for ($i=1;$i<=8;$i++): ?><td><?php echo isset($det['SABER'][$i]) ? number_format((float)$det['SABER'][$i], 2) : ''; ?></td><?php endfor; ?>
        <td><?php echo number_format((float)$tot['saber_total'], 2); ?></td>
        <?php for ($i=1;$i<=8;$i++): ?><td><?php echo isset($det['HACER'][$i]) ? number_format((float)$det['HACER'][$i], 2) : ''; ?></td><?php endfor; ?>
        <td><?php echo number_format((float)$tot['hacer_total'], 2); ?></td>
        <td style="font-weight:bold"><?php echo number_format((float)$tot['calificacion'], 2); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
