<?php
session_start();
require_once '../config/database.php';
require_once '../includes/asistencia_auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();
$userRole = (int)($_SESSION['user_role'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$lectorInfo = asistencia_auth_get_lector($conn, $userId);

if (!asistencia_auth_puede_ver_reportes($userRole, $lectorInfo)) {
    http_response_code(403);
    echo '<h3>Acceso denegado</h3><p>No tienes permisos para ver reportes de asistencia.</p>';
    exit();
}

$nivelesPermitidos = ['Inicial', 'Primaria', 'Secundaria'];
$stmtCatalogoCursos = $conn->query("SELECT
        c.id_curso,
        c.nivel,
        c.curso,
        c.paralelo,
        CASE
            WHEN COALESCE(act.estado, 0) = 1 AND COALESCE(act.doble_turno, 0) = 1 THEN 1
            ELSE 0
        END AS disponible_tarde
    FROM cursos c
    LEFT JOIN asistencia_cursos_turnos act ON act.id_curso = c.id_curso
    ORDER BY c.nivel, c.curso, c.paralelo");
$catalogoCursos = $stmtCatalogoCursos->fetchAll(PDO::FETCH_ASSOC);

$nivel = $_GET['nivel'] ?? '';
if (!in_array($nivel, $nivelesPermitidos, true)) {
    $nivel = '';
}

$turno = strtoupper((string)($_GET['turno'] ?? 'MANANA'));
if (!in_array($turno, ['MANANA', 'TARDE'], true)) {
    $turno = 'MANANA';
}

$idCurso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
$fecha = $_GET['fecha'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $stmtFecha = $conn->prepare("SELECT MAX(fecha) AS ultima_fecha FROM asistencia WHERE turno = ?");
    $stmtFecha->execute([$turno]);
    $ultimaFecha = (string)($stmtFecha->fetchColumn() ?: '');
    if ($ultimaFecha === '') {
        $stmtFechaGlobal = $conn->query("SELECT MAX(fecha) AS ultima_fecha FROM asistencia");
        $ultimaFecha = (string)($stmtFechaGlobal->fetchColumn() ?: '');
    }
    $fecha = $ultimaFecha !== '' ? $ultimaFecha : date('Y-m-d');
}
$tipoReporte = $_GET['tipo_reporte'] ?? 'llegada';
if (!in_array($tipoReporte, ['llegada', 'puntualidad'], true)) {
    $tipoReporte = 'llegada';
}
$accion = $_GET['accion'] ?? 'ver_reporte';
$mostrarReporteGlobal = $accion === 'reporte_global';

$stmtTurnosActivos = $conn->prepare("SELECT turno
    FROM asistencia_horarios_turno_global
    WHERE estado = 1 AND ? BETWEEN fecha_inicio AND fecha_fin");
$stmtTurnosActivos->execute([$fecha]);
$turnosActivos = [];
foreach ($stmtTurnosActivos->fetchAll(PDO::FETCH_COLUMN) as $turnoRow) {
    $turnoNorm = strtoupper((string)$turnoRow);
    if ($turnoNorm === 'MANANA' || $turnoNorm === 'TARDE') {
        $turnosActivos[$turnoNorm] = true;
    }
}
$turnoHabilitadoPorHorario = isset($turnosActivos[$turno]);

$stmtHorariosGlobales = $conn->prepare("SELECT turno, hora_ingreso, tolerancia_min
    FROM asistencia_horarios_turno_global
    WHERE estado = 1 AND ? BETWEEN fecha_inicio AND fecha_fin
    ORDER BY fecha_inicio DESC, id_horario_global DESC");
$stmtHorariosGlobales->execute([$fecha]);
$horariosGlobales = [
    'MANANA' => '',
    'TARDE' => '',
];
$toleranciasGlobales = [
    'MANANA' => 0,
    'TARDE' => 0,
];
foreach ($stmtHorariosGlobales->fetchAll(PDO::FETCH_ASSOC) as $hRow) {
    $hTurno = strtoupper((string)($hRow['turno'] ?? ''));
    if (($hTurno === 'MANANA' || $hTurno === 'TARDE') && $horariosGlobales[$hTurno] === '') {
        $horariosGlobales[$hTurno] = (string)($hRow['hora_ingreso'] ?? '');
        $toleranciasGlobales[$hTurno] = max((int)($hRow['tolerancia_min'] ?? 0), 0);
    }
}

$usarTurnoInferidoPorHora = $horariosGlobales['MANANA'] !== '' && $horariosGlobales['TARDE'] !== '';
$turnoFiltroSql = "UPPER(COALESCE(a.turno, 'MANANA')) = ?";
$turnoFiltroParams = [$turno];
if ($usarTurnoInferidoPorHora) {
    $turnoFiltroSql = "CASE
            WHEN a.hora_entrada IS NULL THEN UPPER(COALESCE(a.turno, 'MANANA'))
            WHEN ABS(TIME_TO_SEC(TIMEDIFF(a.hora_entrada, ?))) <= ABS(TIME_TO_SEC(TIMEDIFF(a.hora_entrada, ?))) THEN 'MANANA'
            ELSE 'TARDE'
        END = ?";
    $turnoFiltroParams = [$horariosGlobales['MANANA'], $horariosGlobales['TARDE'], $turno];
}

$usarPuntualidadPorHorario = $horariosGlobales[$turno] !== '';
$puntualidadTempranoSql = "COUNT(DISTINCT CASE WHEN a.id_asistencia IS NOT NULL AND UPPER(COALESCE(a.estado_puntualidad, '')) <> 'TARDE' THEN a.id_estudiante END) AS temprano";
$puntualidadRetrasoSql = "COUNT(DISTINCT CASE WHEN UPPER(COALESCE(a.estado_puntualidad, '')) = 'TARDE' THEN a.id_estudiante END) AS retrasados";
$puntualidadDetalleSql = "CASE
        WHEN a.id_asistencia IS NULL THEN NULL
        WHEN UPPER(COALESCE(a.estado_puntualidad, '')) = 'TARDE' THEN 'RETRASADO'
        ELSE 'TEMPRANO'
    END AS puntualidad_calculada";
$paramsPuntualidadResumen = [];
$paramsPuntualidadDetalle = [];

if ($usarPuntualidadPorHorario) {
    $horaTurno = $horariosGlobales[$turno];
    $toleranciaTurno = $toleranciasGlobales[$turno];
    $puntualidadTempranoSql = "COUNT(DISTINCT CASE
            WHEN a.id_asistencia IS NOT NULL
             AND a.hora_entrada <= ADDTIME(?, SEC_TO_TIME(? * 60))
            THEN a.id_estudiante
        END) AS temprano";
    $puntualidadRetrasoSql = "COUNT(DISTINCT CASE
            WHEN a.id_asistencia IS NOT NULL
             AND a.hora_entrada > ADDTIME(?, SEC_TO_TIME(? * 60))
            THEN a.id_estudiante
        END) AS retrasados";
    $puntualidadDetalleSql = "CASE
            WHEN a.id_asistencia IS NULL THEN NULL
            WHEN a.hora_entrada <= ADDTIME(?, SEC_TO_TIME(? * 60)) THEN 'TEMPRANO'
            ELSE 'RETRASADO'
        END AS puntualidad_calculada";
    $paramsPuntualidadResumen = [$horaTurno, $toleranciaTurno, $horaTurno, $toleranciaTurno];
    $paramsPuntualidadDetalle = [$horaTurno, $toleranciaTurno];
}

if ($nivel !== '') {
    if ($turno === 'TARDE') {
        $stmtCursos = $conn->prepare("SELECT c.id_curso, c.nivel, c.curso, c.paralelo
            FROM cursos c
            INNER JOIN asistencia_cursos_turnos act ON act.id_curso = c.id_curso AND act.estado = 1 AND act.doble_turno = 1
            WHERE c.nivel = ?
            ORDER BY c.curso, c.paralelo");
        $stmtCursos->execute([$nivel]);
    } else {
        $stmtCursos = $conn->prepare("SELECT id_curso, nivel, curso, paralelo FROM cursos WHERE nivel = ? ORDER BY curso, paralelo");
        $stmtCursos->execute([$nivel]);
    }
} else {
    if ($turno === 'TARDE') {
        $stmtCursos = $conn->query("SELECT c.id_curso, c.nivel, c.curso, c.paralelo
            FROM cursos c
            INNER JOIN asistencia_cursos_turnos act ON act.id_curso = c.id_curso AND act.estado = 1 AND act.doble_turno = 1
            ORDER BY c.nivel, c.curso, c.paralelo");
    } else {
        $stmtCursos = $conn->query("SELECT id_curso, nivel, curso, paralelo FROM cursos ORDER BY nivel, curso, paralelo");
    }
}
$cursos = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);
$idsCursosDisponibles = [];
foreach ($cursos as $cursoTmp) {
    $idsCursosDisponibles[(int)$cursoTmp['id_curso']] = true;
}
if ($idCurso > 0 && !isset($idsCursosDisponibles[$idCurso])) {
    $idCurso = 0;
}

$resumenCursos = [];
if ($nivel !== '') {
    $stmtResumen = $conn->prepare("SELECT
            c.id_curso,
            c.nivel,
            c.curso,
            c.paralelo,
            COUNT(DISTINCT e.id_estudiante) AS total_estudiantes,
            COUNT(DISTINCT a.id_estudiante) AS llegaron,
            " . $puntualidadTempranoSql . ",
            " . $puntualidadRetrasoSql . "
        FROM cursos c
        LEFT JOIN estudiantes e ON e.id_curso = c.id_curso
        LEFT JOIN asistencia a ON a.id_estudiante = e.id_estudiante AND a.fecha = ? AND " . $turnoFiltroSql . "
        LEFT JOIN asistencia_cursos_turnos act ON act.id_curso = c.id_curso
        WHERE c.nivel = ?
          AND (? <> 'TARDE' OR (act.estado = 1 AND act.doble_turno = 1))
        GROUP BY c.id_curso, c.nivel, c.curso, c.paralelo
        ORDER BY c.curso ASC, c.paralelo ASC");
    $paramsResumen = array_merge($paramsPuntualidadResumen, [$fecha], $turnoFiltroParams, [$nivel, $turno]);
    $stmtResumen->execute($paramsResumen);
} else {
    $stmtResumen = $conn->prepare("SELECT
            c.id_curso,
            c.nivel,
            c.curso,
            c.paralelo,
            COUNT(DISTINCT e.id_estudiante) AS total_estudiantes,
            COUNT(DISTINCT a.id_estudiante) AS llegaron,
            " . $puntualidadTempranoSql . ",
            " . $puntualidadRetrasoSql . "
        FROM cursos c
        LEFT JOIN estudiantes e ON e.id_curso = c.id_curso
        LEFT JOIN asistencia a ON a.id_estudiante = e.id_estudiante AND a.fecha = ? AND " . $turnoFiltroSql . "
        LEFT JOIN asistencia_cursos_turnos act ON act.id_curso = c.id_curso
        WHERE (? <> 'TARDE' OR (act.estado = 1 AND act.doble_turno = 1))
        GROUP BY c.id_curso, c.nivel, c.curso, c.paralelo
        ORDER BY c.nivel ASC, c.curso ASC, c.paralelo ASC");
    $paramsResumen = array_merge($paramsPuntualidadResumen, [$fecha], $turnoFiltroParams, [$turno]);
    $stmtResumen->execute($paramsResumen);
}
$resumenCursos = $stmtResumen->fetchAll(PDO::FETCH_ASSOC);

$ausentesPorCurso = [];
$resumenPorCurso = [];
$totalesColegio = [
    'total' => 0,
    'llegaron' => 0,
    'ausentes' => 0,
];

if ($mostrarReporteGlobal) {
    foreach ($resumenCursos as $rc) {
        $cursoId = (int)($rc['id_curso'] ?? 0);
        $totalEst = (int)($rc['total_estudiantes'] ?? 0);
        $llegaron = (int)($rc['llegaron'] ?? 0);
        $ausentes = max($totalEst - $llegaron, 0);
        $nombreCurso = trim((string)($rc['nivel'] ?? '') . ' ' . (string)($rc['curso'] ?? '') . ' "' . (string)($rc['paralelo'] ?? '') . '"');

        $resumenPorCurso[$cursoId] = [
            'nombre' => $nombreCurso,
            'total' => $totalEst,
            'llegaron' => $llegaron,
            'ausentes' => $ausentes,
        ];
        $ausentesPorCurso[$cursoId] = [];
        $totalesColegio['total'] += $totalEst;
        $totalesColegio['llegaron'] += $llegaron;
        $totalesColegio['ausentes'] += $ausentes;
    }
}

if ($mostrarReporteGlobal && !empty($resumenCursos)) {
    if ($nivel !== '') {
        $stmtAusentes = $conn->prepare("SELECT
                c.id_curso,
                e.apellido_paterno,
                e.apellido_materno,
                e.nombres
            FROM cursos c
            INNER JOIN estudiantes e ON e.id_curso = c.id_curso
            LEFT JOIN asistencia a ON a.id_estudiante = e.id_estudiante AND a.fecha = ? AND " . $turnoFiltroSql . "
            LEFT JOIN asistencia_cursos_turnos act ON act.id_curso = c.id_curso
            WHERE c.nivel = ?
              AND (? <> 'TARDE' OR (act.estado = 1 AND act.doble_turno = 1))
              AND a.id_asistencia IS NULL
            ORDER BY c.nivel ASC, c.curso ASC, c.paralelo ASC, e.apellido_paterno ASC, e.apellido_materno ASC, e.nombres ASC");
        $paramsAusentes = array_merge([$fecha], $turnoFiltroParams, [$nivel, $turno]);
        $stmtAusentes->execute($paramsAusentes);
    } else {
        $stmtAusentes = $conn->prepare("SELECT
                c.id_curso,
                e.apellido_paterno,
                e.apellido_materno,
                e.nombres
            FROM cursos c
            INNER JOIN estudiantes e ON e.id_curso = c.id_curso
            LEFT JOIN asistencia a ON a.id_estudiante = e.id_estudiante AND a.fecha = ? AND " . $turnoFiltroSql . "
            LEFT JOIN asistencia_cursos_turnos act ON act.id_curso = c.id_curso
            WHERE (? <> 'TARDE' OR (act.estado = 1 AND act.doble_turno = 1))
              AND a.id_asistencia IS NULL
            ORDER BY c.nivel ASC, c.curso ASC, c.paralelo ASC, e.apellido_paterno ASC, e.apellido_materno ASC, e.nombres ASC");
        $paramsAusentes = array_merge([$fecha], $turnoFiltroParams, [$turno]);
        $stmtAusentes->execute($paramsAusentes);
    }

    foreach ($stmtAusentes->fetchAll(PDO::FETCH_ASSOC) as $aRow) {
        $cursoId = (int)($aRow['id_curso'] ?? 0);
        if (!array_key_exists($cursoId, $ausentesPorCurso)) {
            continue;
        }
        $nombreCompleto = trim((string)($aRow['apellido_paterno'] ?? '') . ' ' . (string)($aRow['apellido_materno'] ?? '') . ', ' . (string)($aRow['nombres'] ?? ''));
        if ($nombreCompleto !== '') {
            $ausentesPorCurso[$cursoId][] = $nombreCompleto;
        }
    }
}

if ($mostrarReporteGlobal) {
    require_once '../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheetResumen = $spreadsheet->getActiveSheet();
    $sheetResumen->setTitle('Consolidado');
    $fechaGeneracion = date('d/m/Y H:i');
    $turnoTexto = $turno === 'TARDE' ? 'TARDE' : 'MANANA';

    $sheetResumen->setCellValue('A1', 'REPORTE DE ASISTENCIA - UE SIMON BOLIVAR 2026');
    $sheetResumen->setCellValue('A2', 'Fecha de generacion: ' . $fechaGeneracion . ' | Fecha de reporte: ' . $fecha . ' | Turno: ' . $turnoTexto);
    $sheetResumen->setCellValue('A4', 'Curso');
    $sheetResumen->setCellValue('B4', 'Total');
    $sheetResumen->setCellValue('C4', 'Llegaron');
    $sheetResumen->setCellValue('D4', 'Ausentes');
    $sheetResumen->setCellValue('E4', '% Asistencia');

    $fila = 5;
    foreach ($resumenCursos as $rc) {
        $cursoId = (int)($rc['id_curso'] ?? 0);
        $cursoNom = (string)($resumenPorCurso[$cursoId]['nombre'] ?? '');
        $totalEst = (int)($resumenPorCurso[$cursoId]['total'] ?? 0);
        $llegaron = (int)($resumenPorCurso[$cursoId]['llegaron'] ?? 0);
        $ausentes = (int)($resumenPorCurso[$cursoId]['ausentes'] ?? 0);
        $pct = $totalEst > 0 ? round(($llegaron * 100) / $totalEst, 2) : 0;

        $sheetResumen->setCellValue('A' . $fila, $cursoNom);
        $sheetResumen->setCellValue('B' . $fila, $totalEst);
        $sheetResumen->setCellValue('C' . $fila, $llegaron);
        $sheetResumen->setCellValue('D' . $fila, $ausentes);
        $sheetResumen->setCellValue('E' . $fila, $pct / 100);
        $fila++;
    }

    $sheetResumen->setCellValue('A' . $fila, 'TOTAL GENERAL');
    $sheetResumen->setCellValue('B' . $fila, (int)$totalesColegio['total']);
    $sheetResumen->setCellValue('C' . $fila, (int)$totalesColegio['llegaron']);
    $sheetResumen->setCellValue('D' . $fila, (int)$totalesColegio['ausentes']);
    $pctGeneral = (int)$totalesColegio['total'] > 0 ? round(((int)$totalesColegio['llegaron'] * 100) / (int)$totalesColegio['total'], 2) : 0;
    $sheetResumen->setCellValue('E' . $fila, $pctGeneral / 100);

    $sheetResumen->mergeCells('A1:E1');
    $sheetResumen->mergeCells('A2:E2');
    $sheetResumen->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    $sheetResumen->getStyle('A1:A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheetResumen->getStyle('A1:E2')->getFont()->getColor()->setRGB('000000');
    $sheetResumen->getStyle('A4:E4')->getFont()->setBold(true);
    $sheetResumen->getStyle('A4:E4')->getFont()->getColor()->setRGB('000000');
    $sheetResumen->getStyle('A' . $fila . ':E' . $fila)->getFont()->setBold(true);
    $sheetResumen->getStyle('E5:E' . $fila)->getNumberFormat()->setFormatCode('0.00%');
    $sheetResumen->getStyle('A4:E' . $fila)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    $sheetResumen->getStyle('A4:E' . $fila)->getFont()->setSize(9);
    $sheetResumen->getStyle('B5:E' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheetResumen->getStyle('A4:E4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheetResumen->getStyle('A1:E' . $fila)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE);
    $sheetResumen->getStyle('A1:E' . $fila)->getFont()->getColor()->setRGB('000000');
    $sheetResumen->getColumnDimension('A')->setWidth(30);
    $sheetResumen->getColumnDimension('B')->setWidth(10);
    $sheetResumen->getColumnDimension('C')->setWidth(11);
    $sheetResumen->getColumnDimension('D')->setWidth(11);
    $sheetResumen->getColumnDimension('E')->setWidth(12);
    for ($filaResumen = 4; $filaResumen <= $fila; $filaResumen++) {
        $sheetResumen->getRowDimension($filaResumen)->setRowHeight(20);
    }
    $sheetResumen->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
    $sheetResumen->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
    $sheetResumen->getPageSetup()->setFitToWidth(1);
    $sheetResumen->getPageSetup()->setFitToHeight(0);
    $sheetResumen->getPageMargins()->setTop(0.3);
    $sheetResumen->getPageMargins()->setBottom(0.3);
    $sheetResumen->getPageMargins()->setLeft(0.25);
    $sheetResumen->getPageMargins()->setRight(0.25);
    $sheetResumen->freezePane('A5');

    $sheetAusentes = $spreadsheet->createSheet();
    $sheetAusentes->setTitle('Ausentes por curso');
    $sheetAusentes->setCellValue('A1', 'REPORTE DE ASISTENCIA - UE SIMON BOLIVAR 2026');
    $sheetAusentes->setCellValue('A2', 'Fecha de generacion: ' . $fechaGeneracion . ' | Fecha de reporte: ' . $fecha . ' | Turno: ' . $turnoTexto);
    $sheetAusentes->setCellValue('A3', 'Curso');
    $sheetAusentes->setCellValue('B3', 'Ausentes');
    $sheetAusentes->mergeCells('A1:B1');
    $sheetAusentes->mergeCells('A2:B2');
    $sheetAusentes->getStyle('A1')->getFont()->setBold(true)->setSize(12);
    $sheetAusentes->getStyle('A1:B2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheetAusentes->getStyle('A3:B3')->getFont()->setBold(true);
    $sheetAusentes->getStyle('A3:B3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $filaAus = 4;
    foreach ($resumenCursos as $rc) {
        $cursoId = (int)($rc['id_curso'] ?? 0);
        $cursoNom = (string)($resumenPorCurso[$cursoId]['nombre'] ?? '');
        $totalEst = (int)($resumenPorCurso[$cursoId]['total'] ?? 0);
        $llegaron = (int)($resumenPorCurso[$cursoId]['llegaron'] ?? 0);
        $ausentes = (int)($resumenPorCurso[$cursoId]['ausentes'] ?? 0);
        $puntuales = (int)($rc['temprano'] ?? 0);
        $retrasados = (int)($rc['retrasados'] ?? 0);
        $listaAusentes = $ausentesPorCurso[$cursoId] ?? [];

        $detalleCurso = $cursoNom
            . "\nTotal: " . $totalEst
            . " | Llegaron: " . $llegaron
            . " (Puntuales: " . $puntuales . " , Retrasados: " . $retrasados . ")"
            . "\nFaltan: " . $ausentes;
        $sheetAusentes->setCellValue('A' . $filaAus, $detalleCurso);

        if (empty($listaAusentes)) {
            $sheetAusentes->setCellValue('B' . $filaAus, 'SIN AUSENTES');
        } else {
            $lineas = ['', '', ''];
            $totalNombres = count($listaAusentes);
            $tamBloque = (int)ceil($totalNombres / 3);
            for ($i = 0; $i < 3; $i++) {
                $inicio = $i * $tamBloque;
                if ($inicio >= $totalNombres) {
                    $lineas[$i] = '-';
                    continue;
                }
                $bloque = array_slice($listaAusentes, $inicio, $tamBloque);
                $lineas[$i] = implode(' | ', array_map('strtoupper', $bloque));
            }
            $sheetAusentes->setCellValue('B' . $filaAus, implode("\n", $lineas));
        }

        $sheetAusentes->getRowDimension($filaAus)->setRowHeight(54);
        $filaAus++;
    }
    $ultimaFilaAusentes = max($filaAus - 1, 4);
    $sheetAusentes->getStyle('A3:B' . $ultimaFilaAusentes)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    $sheetAusentes->getStyle('A3:B' . $ultimaFilaAusentes)->getFont()->setSize(9);
    $sheetAusentes->getStyle('A4:A' . $ultimaFilaAusentes)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)->setWrapText(true);
    $sheetAusentes->getStyle('B4:B' . $ultimaFilaAusentes)->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    $sheetAusentes->getStyle('A4:A' . $ultimaFilaAusentes)->getFont()->setBold(true);
    $sheetAusentes->getStyle('A1:B' . $ultimaFilaAusentes)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE);
    $sheetAusentes->getStyle('A1:B' . $ultimaFilaAusentes)->getFont()->getColor()->setRGB('000000');
    $sheetAusentes->getColumnDimension('A')->setWidth(28);
    $sheetAusentes->getColumnDimension('B')->setWidth(100);
    $sheetAusentes->getRowDimension(3)->setRowHeight(22);
    for ($filaDetalle = 4; $filaDetalle <= $ultimaFilaAusentes; $filaDetalle++) {
        if ($sheetAusentes->getRowDimension($filaDetalle)->getRowHeight() < 58) {
            $sheetAusentes->getRowDimension($filaDetalle)->setRowHeight(58);
        }
    }
    $sheetAusentes->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheetAusentes->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
    $sheetAusentes->getPageSetup()->setFitToWidth(1);
    $sheetAusentes->getPageSetup()->setFitToHeight(0);
    $sheetAusentes->getPageMargins()->setTop(0.25);
    $sheetAusentes->getPageMargins()->setBottom(0.25);
    $sheetAusentes->getPageMargins()->setLeft(0.2);
    $sheetAusentes->getPageMargins()->setRight(0.2);

    $nombreArchivo = 'reporte_global_asistencia_' . $fecha . '_' . strtolower($turno) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

$registros = [];
if ($idCurso > 0) {
    $stmt = $conn->prepare("SELECT e.apellido_paterno, e.apellido_materno, e.nombres,
            a.fecha, a.hora_entrada, a.estado_puntualidad,
            " . $puntualidadDetalleSql . ",
            CASE WHEN a.id_asistencia IS NULL THEN 0 ELSE 1 END AS llego
        FROM estudiantes e
        LEFT JOIN asistencia a ON a.id_estudiante = e.id_estudiante AND a.fecha = ? AND " . $turnoFiltroSql . "
        WHERE e.id_curso = ?
        ORDER BY llego DESC, a.hora_entrada ASC, e.apellido_paterno ASC, e.apellido_materno ASC, e.nombres ASC");
    $paramsDetalle = array_merge($paramsPuntualidadDetalle, [$fecha], $turnoFiltroParams, [$idCurso]);
    $stmt->execute($paramsDetalle);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Asistencia por Curso</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
</head>
<body>
    <style>
        .reporte-compacto {
            font-family: Consolas, "Courier New", monospace;
            font-size: 12px;
            line-height: 1.35;
            white-space: pre-wrap;
            margin: 0;
        }

        .reporte-columnas {
            column-count: 2;
            column-gap: 24px;
        }

        .bloque-curso {
            break-inside: avoid;
            margin-bottom: 12px;
        }

        @media print {
            .reporte-columnas {
                column-count: 2;
                column-gap: 18px;
            }
        }
    </style>
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <main class="w-100 px-md-4 position-relative py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Reporte de Asistencia por Curso</h1>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <?php if (!$turnoHabilitadoPorHorario): ?>
                            <div class="alert alert-info mb-3">
                                Para la fecha <strong><?= htmlspecialchars($fecha) ?></strong> no hay horario global activo en
                                <strong><?= htmlspecialchars($turno) ?></strong>. Se muestran igualmente los registros guardados en asistencia.
                            </div>
                        <?php endif; ?>
                        <?php if ($usarTurnoInferidoPorHora): ?>
                            <div class="alert alert-secondary mb-3">
                                Clasificacion de turno por hora activa para <?= htmlspecialchars($fecha) ?>:
                                MANANA <?= htmlspecialchars($horariosGlobales['MANANA']) ?> y TARDE <?= htmlspecialchars($horariosGlobales['TARDE']) ?>.
                            </div>
                        <?php endif; ?>
                        <?php if ($tipoReporte === 'puntualidad' && !$usarPuntualidadPorHorario): ?>
                            <div class="alert alert-warning mb-3">
                                No hay horario global del turno <?= htmlspecialchars($turno) ?> para <?= htmlspecialchars($fecha) ?>.
                                La puntualidad se calcula usando el estado guardado en cada registro.
                            </div>
                        <?php endif; ?>
                        <form class="row g-3" method="GET" action="" id="filtrosReporteForm">
                            <input type="hidden" name="accion" id="accion_form" value="ver_reporte">
                            <div class="col-md-4">
                                <label class="form-label">Nivel</label>
                                <select class="form-select" id="nivel" name="nivel">
                                    <option value="">Seleccione un nivel</option>
                                    <?php foreach ($nivelesPermitidos as $nivelOpt): ?>
                                        <option value="<?= htmlspecialchars($nivelOpt) ?>" <?= $nivel === $nivelOpt ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($nivelOpt) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Curso</label>
                                <select class="form-select" name="id_curso" id="id_curso">
                                    <option value="">Seleccione un curso</option>
                                    <?php foreach ($catalogoCursos as $curso): ?>
                                        <option
                                            value="<?= (int)$curso['id_curso'] ?>"
                                            data-curso-id="<?= (int)$curso['id_curso'] ?>"
                                            data-nivel="<?= htmlspecialchars((string)$curso['nivel']) ?>"
                                            data-disponible-tarde="<?= (int)($curso['disponible_tarde'] ?? 0) ?>"
                                            <?= $idCurso === (int)$curso['id_curso'] ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($curso['nivel'] . ' ' . $curso['curso'] . ' "' . $curso['paralelo'] . '"') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" name="fecha" value="<?= htmlspecialchars($fecha) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Turno</label>
                                <select class="form-select" name="turno">
                                    <option value="MANANA" <?= $turno === 'MANANA' ? 'selected' : '' ?>>Manana</option>
                                    <option value="TARDE" <?= $turno === 'TARDE' ? 'selected' : '' ?>>Tarde</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo de reporte</label>
                                <select class="form-select" name="tipo_reporte">
                                    <option value="llegada" <?= $tipoReporte === 'llegada' ? 'selected' : '' ?>>Llegada (llegaron vs no llegaron)</option>
                                    <option value="puntualidad" <?= $tipoReporte === 'puntualidad' ? 'selected' : '' ?>>Puntualidad (temprano vs retrasados)</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100" id="btnVerReporte">Ver reporte</button>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-dark w-100" id="btnReporteGlobal">Reporte global (Excel)</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="modal fade" id="modalTurnoReporteGlobal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Generar reporte global</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                Selecciona el turno para generar el Excel:
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-primary" id="btnTurnoManana">Turno manana</button>
                                <button type="button" class="btn btn-outline-secondary" id="btnTurnoTarde">Turno tarde</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                        <div class="card-header">
                        Resumen por curso (<?= htmlspecialchars($fecha) ?> - <?= htmlspecialchars($turno) ?>)
                        - <?= $tipoReporte === 'puntualidad' ? 'Puntualidad' : 'Llegada' ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Curso</th>
                                        <th>Total estudiantes</th>
                                        <?php if ($tipoReporte === 'puntualidad'): ?>
                                            <th>Temprano</th>
                                            <th>Retrasados</th>
                                        <?php else: ?>
                                            <th>Llegaron</th>
                                            <th>No llegaron</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($resumenCursos)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">No hay cursos para los filtros seleccionados.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($resumenCursos as $i => $rc): ?>
                                            <?php
                                                $totalEst = (int)($rc['total_estudiantes'] ?? 0);
                                                $llegaron = (int)($rc['llegaron'] ?? 0);
                                                $noLlegaron = max($totalEst - $llegaron, 0);
                                            ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars(($rc['nivel'] ?? '') . ' ' . ($rc['curso'] ?? '') . ' "' . ($rc['paralelo'] ?? '') . '"') ?></td>
                                                <td><?= $totalEst ?></td>
                                                <?php if ($tipoReporte === 'puntualidad'): ?>
                                                    <td class="text-primary"><?= (int)($rc['temprano'] ?? 0) ?></td>
                                                    <td class="text-warning"><?= (int)($rc['retrasados'] ?? 0) ?></td>
                                                <?php else: ?>
                                                    <td class="text-success"><?= $llegaron ?></td>
                                                    <td class="text-danger"><?= $noLlegaron ?></td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if ($idCurso > 0): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Reporte del curso seleccionado (<?= htmlspecialchars($fecha) ?> - <?= htmlspecialchars($turno) ?>)</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Estudiante</th>
                                            <th>Llegada</th>
                                            <th>Hora de entrada</th>
                                            <th>Puntualidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($registros)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">No hay registros para los filtros seleccionados.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($registros as $index => $row): ?>
                                                <?php $llego = (int)($row['llego'] ?? 0) === 1; ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($row['apellido_paterno'] . ' ' . $row['apellido_materno'] . ', ' . $row['nombres']) ?></td>
                                                    <td class="<?= $llego ? 'text-success' : 'text-danger' ?>"><?= $llego ? 'LLEGO' : 'NO LLEGO' ?></td>
                                                    <td><?= htmlspecialchars($llego ? (string)($row['hora_entrada'] ?? '-') : '-') ?></td>
                                                    <td>
                                                        <?php if (!$llego): ?>
                                                            -
                                                        <?php else: ?>
                                                            <?= htmlspecialchars((string)($row['puntualidad_calculada'] ?? '-')) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.0/dist/feather.min.js"></script>
    <script>
        feather.replace();

        (function () {
            var formFiltros = document.getElementById('filtrosReporteForm');
            var nivelSelect = document.getElementById('nivel');
            var turnoSelect = document.querySelector('select[name="turno"]');
            var cursoSelect = document.getElementById('id_curso');
            var accionInput = document.getElementById('accion_form');
            var btnVerReporte = document.getElementById('btnVerReporte');
            var btnReporteGlobal = document.getElementById('btnReporteGlobal');
            var btnTurnoManana = document.getElementById('btnTurnoManana');
            var btnTurnoTarde = document.getElementById('btnTurnoTarde');
            var modalElement = document.getElementById('modalTurnoReporteGlobal');
            var modalTurno = modalElement ? new bootstrap.Modal(modalElement) : null;

            if (!formFiltros || !nivelSelect || !turnoSelect || !cursoSelect || !accionInput) {
                return;
            }

            var opcionesCurso = Array.prototype.slice.call(cursoSelect.querySelectorAll('option[data-curso-id]'));

            function filtrarCursos() {
                var nivel = nivelSelect.value;
                var turno = turnoSelect.value;
                var valorSeleccionado = cursoSelect.value;

                for (var i = 0; i < opcionesCurso.length; i++) {
                    var opcion = opcionesCurso[i];
                    var nivelCurso = opcion.getAttribute('data-nivel') || '';
                    var disponibleTarde = opcion.getAttribute('data-disponible-tarde') === '1';
                    var visible = true;

                    if (nivel !== '' && nivelCurso !== nivel) {
                        visible = false;
                    }

                    if (turno === 'TARDE' && !disponibleTarde) {
                        visible = false;
                    }

                    opcion.hidden = !visible;
                    opcion.disabled = !visible;
                }

                if (valorSeleccionado !== '') {
                    var opcionSeleccionada = cursoSelect.querySelector('option[value="' + valorSeleccionado + '"]');
                    if (!opcionSeleccionada || opcionSeleccionada.hidden) {
                        cursoSelect.value = '';
                    }
                }
            }

            nivelSelect.addEventListener('change', filtrarCursos);
            turnoSelect.addEventListener('change', filtrarCursos);
            filtrarCursos();

            if (btnVerReporte) {
                btnVerReporte.addEventListener('click', function () {
                    accionInput.value = 'ver_reporte';
                });
            }

            if (btnReporteGlobal && modalTurno) {
                btnReporteGlobal.addEventListener('click', function () {
                    modalTurno.show();
                });
            }

            function enviarReporteGlobal(turno) {
                turnoSelect.value = turno;
                filtrarCursos();
                accionInput.value = 'reporte_global';
                if (modalTurno) {
                    modalTurno.hide();
                }
                formFiltros.submit();
            }

            if (btnTurnoManana) {
                btnTurnoManana.addEventListener('click', function () {
                    enviarReporteGlobal('MANANA');
                });
            }

            if (btnTurnoTarde) {
                btnTurnoTarde.addEventListener('click', function () {
                    enviarReporteGlobal('TARDE');
                });
            }
        })();
    </script>
</body>
</html>
