<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header('Location: ../index.php');
    exit();
}

$profesor_id = $_SESSION['user_id'];
$id_curso_materia = isset($_GET['curso_materia']) ? (int)$_GET['curso_materia'] : 0;
$trimestreExportar = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : 0;

if ($id_curso_materia <= 0) {
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

// Periodos
$sqlPeriodos = "SELECT trimestre, parcial FROM periodos_evaluacion WHERE gestion = ?";
$paramsPeriodos = [$gestionActual];
if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
    $sqlPeriodos .= " OR gestion = ?";
    $paramsPeriodos[] = $gestionAlternativa;
}
$sqlPeriodos .= " ORDER BY trimestre, parcial";
$stmt = $conn->prepare($sqlPeriodos);
$stmt->execute($paramsPeriodos);
$periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estructura de trimestres y parciales disponibles
$trimestresDisponibles = [];
foreach ($periodos as $p) {
    $t = (int)$p['trimestre'];
    $par = (int)$p['parcial'];
    $trimestresDisponibles[$t][$par] = true;
}

// Filtrar trimestres a exportar
if ($trimestreExportar > 0) {
    $trimestresAExportar = [$trimestreExportar => $trimestresDisponibles[$trimestreExportar] ?? []];
} else {
    $trimestresAExportar = $trimestresDisponibles;
}

// Notas parciales
$stmt = $conn->prepare("SELECT cp.id_estudiante, pe.trimestre, pe.parcial, cp.$campo AS valor
                        FROM calificaciones_parciales cp
                        INNER JOIN periodos_evaluacion pe ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
                        WHERE cp.id_materia = ?
                          AND pe.gestion = ?");
$stmt->execute([$curso['id_materia'], $gestionActual]);
$notas = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $notas[(int)$row['id_estudiante']][(int)$row['trimestre']][(int)$row['parcial']] = $row['valor'];
}

// Notas trimestrales (autoevaluación + extra)
$notasTrimestrales = [];
try {
    $stmt = $conn->prepare("SELECT id_estudiante, trimestre, autoevaluacion, nota_extra
                            FROM calificaciones_trimestrales
                            WHERE id_materia = ? AND gestion = ?");
    $stmt->execute([$curso['id_materia'], $gestionActual]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $notasTrimestrales[(int)$row['id_estudiante']][(int)$row['trimestre']] = [
            'autoevaluacion' => $row['autoevaluacion'],
            'nota_extra' => $row['nota_extra']
        ];
    }
} catch (PDOException $e) {
    // Table may not exist yet
}

// Generar nombre de archivo
$nombreArchivo = 'Notas_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $curso['curso_nombre']) . '_' .
                 preg_replace('/[^a-zA-Z0-9_]/', '_', $curso['nombre_materia']) . '_' .
                 $gestionActual;
if ($trimestreExportar > 0) {
    $nombreArchivo .= '_T' . $trimestreExportar;
}
$nombreArchivo .= '.xls';

// Headers para descarga Excel
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

echo "\xEF\xBB\xBF"; // BOM UTF-8
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<style>
    td, th { mso-number-format:\@; }
    th { background: #dbeafe; font-weight: bold; text-align: center; border: 1px solid #999; padding: 4px 6px; font-size: 11px; }
    td { border: 1px solid #ccc; padding: 3px 5px; font-size: 11px; }
    .th-trim { background: #e0e7ff; }
    .th-prom { background: #f3e8ff; font-weight: bold; }
    .th-auto { background: #fef3c7; }
    .th-extra { background: #e0e7ff; }
    .th-total { background: #dcfce7; font-weight: bold; }
    .num { text-align: center; }
    .nombre { text-align: left; min-width: 200px; }
    .nota { text-align: center; mso-number-format:"0.00"; }
    .titulo { font-size: 14px; font-weight: bold; }
    .subtitulo { font-size: 12px; color: #444; }
</style>
</head>
<body>
<table>
    <tr>
        <td class="titulo" colspan="3"><?php echo htmlspecialchars($curso['curso_nombre']); ?> — <?php echo htmlspecialchars($curso['nombre_materia']); ?></td>
    </tr>
    <tr>
        <td class="subtitulo" colspan="3">Gestión <?php echo htmlspecialchars($gestionActual); ?><?php echo $trimestreExportar > 0 ? ' — Trimestre ' . $trimestreExportar : ' — Todos los trimestres'; ?></td>
    </tr>
    <tr><td colspan="3"></td></tr>
    <?php if ($es_inicial): ?>
        <!-- Modo inicial: comentarios -->
        <tr>
            <th>#</th>
            <th>Estudiante</th>
            <?php foreach ($trimestresAExportar as $t => $parciales): ?>
                <?php foreach ($parciales as $p => $v): ?>
                    <th class="th-trim">T<?php echo $t; ?>-P<?php echo $p; ?></th>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tr>
        <?php $n = 1; ?>
        <?php foreach ($estudiantes as $est): ?>
            <?php $idEst = (int)$est['id_estudiante']; ?>
            <tr>
                <td class="num"><?php echo $n++; ?></td>
                <td class="nombre"><?php echo htmlspecialchars($est['nombre']); ?></td>
                <?php foreach ($trimestresAExportar as $t => $parciales): ?>
                    <?php foreach ($parciales as $p => $v): ?>
                        <td><?php echo htmlspecialchars($notas[$idEst][$t][$p] ?? ''); ?></td>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <!-- Modo numérico: notas parciales + promedios + auto + extra + total -->
        <tr>
            <th rowspan="2">#</th>
            <th rowspan="2">Estudiante</th>
            <?php foreach ($trimestresAExportar as $t => $parciales): ?>
                <?php
                $colCount = count($parciales); // parciales
                if (!$es_inicial) $colCount += 4; // Prom95 + Auto + Extra + Total
                ?>
                <th class="th-trim" colspan="<?php echo $colCount; ?>">Trimestre <?php echo $t; ?></th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($trimestresAExportar as $t => $parciales): ?>
                <?php foreach ($parciales as $p => $v): ?>
                    <th>P<?php echo $p; ?></th>
                <?php endforeach; ?>
                <th class="th-prom">Prom 95</th>
                <th class="th-auto">Auto (5)</th>
                <th class="th-extra">Extra</th>
                <th class="th-total">TOTAL</th>
            <?php endforeach; ?>
        </tr>
        <?php $n = 1; ?>
        <?php foreach ($estudiantes as $est): ?>
            <?php $idEst = (int)$est['id_estudiante']; ?>
            <tr>
                <td class="num"><?php echo $n++; ?></td>
                <td class="nombre"><?php echo htmlspecialchars($est['nombre']); ?></td>
                <?php foreach ($trimestresAExportar as $t => $parciales): ?>
                    <?php
                    $parcialesVals = [];
                    foreach ($parciales as $p => $v) {
                        $val = $notas[$idEst][$t][$p] ?? null;
                        $parcialesVals[$p] = ($val !== null && is_numeric($val)) ? (float)$val : null;
                    }
                    $valsNoNull = array_filter($parcialesVals, fn($v) => $v !== null);
                    $prom95 = count($valsNoNull) > 0 ? array_sum($valsNoNull) / count($valsNoNull) : null;

                    $trimData = $notasTrimestrales[$idEst][$t] ?? [];
                    $autoVal = isset($trimData['autoevaluacion']) && $trimData['autoevaluacion'] !== null ? (float)$trimData['autoevaluacion'] : null;
                    $extraVal = isset($trimData['nota_extra']) && $trimData['nota_extra'] !== null ? (float)$trimData['nota_extra'] : null;

                    $total = ($prom95 ?? 0) + ($autoVal ?? 0) + ($extraVal ?? 0);
                    $hasAnyData = $prom95 !== null || $autoVal !== null || $extraVal !== null;
                    ?>
                    <?php foreach ($parciales as $p => $v): ?>
                        <td class="nota"><?php echo $parcialesVals[$p] !== null ? number_format($parcialesVals[$p], 2) : ''; ?></td>
                    <?php endforeach; ?>
                    <td class="nota"><?php echo $prom95 !== null ? number_format($prom95, 2) : ''; ?></td>
                    <td class="nota"><?php echo $autoVal !== null ? number_format($autoVal, 2) : ''; ?></td>
                    <td class="nota"><?php echo $extraVal !== null ? number_format($extraVal, 2) : ''; ?></td>
                    <td class="nota" style="font-weight:bold"><?php echo $hasAnyData ? number_format($total, 2) : ''; ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
</body>
</html>
