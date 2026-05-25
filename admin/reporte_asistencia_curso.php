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

    if ($mostrarReporteGlobal) {
        $totalesColegio['total'] += $totalEst;
        $totalesColegio['llegaron'] += $llegaron;
        $totalesColegio['ausentes'] += $ausentes;
    }
}

if (!empty($resumenCursos)) {
    if ($nivel !== '') {
        $stmtAusentes = $conn->prepare("SELECT
                c.id_curso,
                e.id_estudiante,
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
                e.id_estudiante,
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
        $idEstudianteAus = (int)($aRow['id_estudiante'] ?? 0);
        if ($nombreCompleto !== '') {
            $ausentesPorCurso[$cursoId][] = [
                'id_estudiante' => $idEstudianteAus,
                'nombre' => $nombreCompleto,
            ];
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
                $lineas[$i] = implode(' | ', array_map(static function ($item) {
                    return strtoupper((string)($item['nombre'] ?? ''));
                }, $bloque));
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
        .mobile-report-title {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            color: #1f3c88;
        }

        .mobile-report-subtitle {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.85rem;
        }

        .mobile-table {
            font-size: 0.9rem;
            margin-bottom: 0;
            border-radius: 0.45rem;
            overflow: hidden;
        }

        .mobile-table th,
        .mobile-table td {
            padding: 0.45rem 0.5rem;
            vertical-align: middle;
            border-color: #cfd4da;
        }

        .mobile-table thead th {
            background: #e8eef9;
            font-weight: 700;
            color: #1f3c88;
        }

        .mobile-summary-card,
        .mobile-detail-card {
            border: 1px solid #d9e3f3;
            border-radius: 0.6rem;
            box-shadow: 0 6px 14px rgba(31, 60, 136, 0.08);
        }

        .mobile-nivel-tabs {
            border-bottom: 0;
            gap: 0.4rem;
        }

        .mobile-nivel-tabs .nav-link {
            border: 1px solid transparent;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.84rem;
            padding: 0.38rem 0.7rem;
            color: #475569;
            background: #eef2f7;
        }

        .mobile-nivel-tabs .nav-link.mobile-tab-inicial.active {
            color: #ffffff;
            background: #0ea5a4;
            border-color: #0b8f8e;
        }

        .mobile-nivel-tabs .nav-link.mobile-tab-primaria.active {
            color: #ffffff;
            background: #2563eb;
            border-color: #1d4ed8;
        }

        .mobile-nivel-tabs .nav-link.mobile-tab-secundaria.active {
            color: #ffffff;
            background: #ea580c;
            border-color: #c2410c;
        }

        .mobile-nivel-tabs .nav-link.mobile-tab-inicial:not(.active) {
            background: #e6f6f5;
            color: #0f766e;
            border-color: #bae6e3;
        }

        .mobile-nivel-tabs .nav-link.mobile-tab-primaria:not(.active) {
            background: #e8f0ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .mobile-nivel-tabs .nav-link.mobile-tab-secundaria:not(.active) {
            background: #fff1e8;
            color: #c2410c;
            border-color: #fed7aa;
        }

        .mobile-nivel-tabs .nav-link:focus {
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.2);
        }

        .mobile-chip-group {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.4rem;
            margin: 0.75rem 0 0.9rem;
        }

        .mobile-chip {
            border: 1px solid #ced4da;
            border-radius: 0.4rem;
            font-size: 0.8rem;
            text-align: center;
            padding: 0.3rem 0.2rem;
            background: #fff;
        }

        .mobile-absent-list .list-group-item {
            padding: 0.55rem 0.65rem;
            font-size: 0.9rem;
        }

        .mobile-absent-name {
            font-weight: 500;
        }

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
                            <div class="alert alert-secondary mb-3 d-none d-md-block">
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
                        <form class="row g-3 d-none d-md-flex" method="GET" action="" id="filtrosReporteForm">
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
                        <div class="table-responsive d-none d-md-block">
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

                        <div class="d-md-none p-3">
                            <?php
                                $resumenNivel = [];
                                foreach ($nivelesPermitidos as $nivelBase) {
                                    $resumenNivel[$nivelBase] = ['total' => 0, 'llegaron' => 0, 'faltan' => 0];
                                }
                                foreach ($resumenCursos as $rowResumen) {
                                    $nivelKey = (string)($rowResumen['nivel'] ?? '');
                                    if (!isset($resumenNivel[$nivelKey])) {
                                        continue;
                                    }
                                    $totTmp = (int)($rowResumen['total_estudiantes'] ?? 0);
                                    $llegTmp = (int)($rowResumen['llegaron'] ?? 0);
                                    $resumenNivel[$nivelKey]['total'] += $totTmp;
                                    $resumenNivel[$nivelKey]['llegaron'] += $llegTmp;
                                    $resumenNivel[$nivelKey]['faltan'] += max($totTmp - $llegTmp, 0);
                                }
                            ?>

                            <div class="mobile-report-title">Reporte de asistencia</div>
                            <div class="mobile-report-subtitle"><?= htmlspecialchars($fecha) ?> - <?= htmlspecialchars($turno) ?></div>
                            <div class="mb-3">
                                <form method="GET" action="" class="row g-2 align-items-end">
                                    <input type="hidden" name="accion" value="ver_reporte">
                                    <input type="hidden" name="turno" value="<?= htmlspecialchars($turno) ?>">
                                    <input type="hidden" name="tipo_reporte" value="<?= htmlspecialchars($tipoReporte) ?>">
                                    <input type="hidden" name="nivel" value="">
                                    <input type="hidden" name="id_curso" value="0">
                                    <div class="col-8">
                                        <label class="form-label mb-1">Fecha</label>
                                        <input type="date" class="form-control form-control-sm" name="fecha" value="<?= htmlspecialchars($fecha) ?>" required>
                                    </div>
                                    <div class="col-4 d-grid">
                                        <button type="submit" class="btn btn-sm btn-primary">Ver</button>
                                    </div>
                                </form>
                            </div>
                            <div class="mb-3">
                                <form method="GET" action="" class="d-flex gap-2">
                                    <input type="hidden" name="accion" value="ver_reporte">
                                    <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha) ?>">
                                    <input type="hidden" name="tipo_reporte" value="<?= htmlspecialchars($tipoReporte) ?>">
                                    <input type="hidden" name="nivel" value="">
                                    <input type="hidden" name="id_curso" value="0">
                                    <button type="submit" name="turno" value="MANANA" class="btn btn-sm <?= $turno === 'MANANA' ? 'btn-dark' : 'btn-outline-dark' ?>">Manana</button>
                                    <button type="submit" name="turno" value="TARDE" class="btn btn-sm <?= $turno === 'TARDE' ? 'btn-dark' : 'btn-outline-dark' ?>">Tarde</button>
                                </form>
                            </div>

                            <div class="card mb-3 mobile-summary-card">
                                <div class="card-body p-2">
                                    <div class="table-responsive">
                                        <table class="table table-bordered mobile-table">
                                            <thead>
                                                <tr>
                                                    <th>Nivel</th>
                                                    <th class="text-center">Llegaron</th>
                                                    <th class="text-center">Faltan</th>
                                                    <th class="text-center">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($nivelesPermitidos as $nivelNom): ?>
                                                    <tr data-nivel-row="<?= htmlspecialchars($nivelNom) ?>">
                                                        <td><?= htmlspecialchars($nivelNom) ?></td>
                                                        <td class="text-center" data-field="llegaron"><?= (int)$resumenNivel[$nivelNom]['llegaron'] ?></td>
                                                        <td class="text-center" data-field="faltan"><?= (int)$resumenNivel[$nivelNom]['faltan'] ?></td>
                                                        <td class="text-center"><?= (int)$resumenNivel[$nivelNom]['total'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <ul class="nav nav-tabs mobile-nivel-tabs mb-2" id="mobileNivelTabs" role="tablist">
                                <?php foreach ($nivelesPermitidos as $idx => $nivelTab): ?>
                                    <?php
                                        $tabClass = 'mobile-tab-inicial';
                                        if ($nivelTab === 'Primaria') {
                                            $tabClass = 'mobile-tab-primaria';
                                        } elseif ($nivelTab === 'Secundaria') {
                                            $tabClass = 'mobile-tab-secundaria';
                                        }
                                    ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?= htmlspecialchars($tabClass) ?> <?= $idx === 0 ? 'active' : '' ?>" id="tab-<?= strtolower($nivelTab) ?>" data-bs-toggle="tab" data-bs-target="#pane-<?= strtolower($nivelTab) ?>" type="button" role="tab" aria-controls="pane-<?= strtolower($nivelTab) ?>" aria-selected="<?= $idx === 0 ? 'true' : 'false' ?>">
                                            <?= htmlspecialchars($nivelTab) ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="tab-content" id="mobileNivelTabsContent">
                                <?php foreach ($nivelesPermitidos as $idx => $nivelTab): ?>
                                    <div class="tab-pane fade <?= $idx === 0 ? 'show active' : '' ?>" id="pane-<?= strtolower($nivelTab) ?>" role="tabpanel" aria-labelledby="tab-<?= strtolower($nivelTab) ?>">
                                        <div class="card mobile-detail-card">
                                            <div class="card-body p-2">
                                                <div class="table-responsive">
                                                    <table class="table table-bordered mobile-table mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Curso</th>
                                                                <th class="text-center">Llegaron</th>
                                                                <th class="text-center">Faltan</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php $tieneCursosNivel = false; ?>
                                                            <?php foreach ($resumenCursos as $rc): ?>
                                                                <?php if (($rc['nivel'] ?? '') !== $nivelTab) { continue; } ?>
                                                                <?php
                                                                    $tieneCursosNivel = true;
                                                                    $totalEst = (int)($rc['total_estudiantes'] ?? 0);
                                                                    $llegaron = (int)($rc['llegaron'] ?? 0);
                                                                    $faltan = max($totalEst - $llegaron, 0);
                                                                ?>
                                                                <tr data-curso-row="<?= (int)($rc['id_curso'] ?? 0) ?>" data-nivel-row="<?= htmlspecialchars((string)($rc['nivel'] ?? '')) ?>">
                                                                    <td><?= htmlspecialchars(($rc['curso'] ?? '') . ' ' . ($rc['paralelo'] ?? '')) ?></td>
                                                                    <td class="text-center" data-field="llegaron"><?= $llegaron ?></td>
                                                                    <td class="text-center">
                                                                        <button
                                                                            type="button"
                                                                            class="btn btn-sm btn-outline-danger py-0 px-2 mobile-faltan-btn"
                                                                            data-curso-id="<?= (int)($rc['id_curso'] ?? 0) ?>"
                                                                            data-curso-nivel="<?= htmlspecialchars((string)($rc['nivel'] ?? '')) ?>"
                                                                            data-curso-nombre="<?= htmlspecialchars(($rc['nivel'] ?? '') . ' ' . ($rc['curso'] ?? '') . ' "' . ($rc['paralelo'] ?? '') . '"') ?>"
                                                                            data-faltan="<?= $faltan ?>"
                                                                        >
                                                                            <?= $faltan ?>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                            <?php if (!$tieneCursosNivel): ?>
                                                                <tr><td colspan="3" class="text-center py-3">Sin cursos en este nivel</td></tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade d-md-none" id="modalAusentesCursoMovil" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalAusentesTitulo">Ausentes</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="small text-muted mb-2" id="modalAusentesMeta"></div>
                                <ul class="list-group" id="modalAusentesLista"></ul>
                                <div class="mt-3 border-top pt-3">
                                    <label for="modalManualStudentId" class="form-label mb-1"><strong>Registrar por ID</strong></label>
                                    <div class="input-group">
                                        <input type="number" min="1" step="1" class="form-control" id="modalManualStudentId" placeholder="Ej: 154">
                                        <button type="button" class="btn btn-success" id="modalRegistrarIdBtn">Registrar</button>
                                    </div>
                                    <div class="form-text">Puedes usar ID manual o escanear QR desde la camara.</div>
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-outline-success w-100" id="modalToggleQrBtn">Abrir camara QR</button>
                                </div>
                                <div class="mt-2 d-none" id="modalQrWrapper">
                                    <div id="modalQrReader" style="width:100%;"></div>
                                </div>
                                <div class="mt-2" id="modalRegistroResultado"></div>
                            </div>
                        </div>
                    </div>
                </div>


            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.0/dist/feather.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
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
            var modalAusentesElement = document.getElementById('modalAusentesCursoMovil');
            var modalAusentes = modalAusentesElement ? new bootstrap.Modal(modalAusentesElement) : null;
            var modalAusentesTitulo = document.getElementById('modalAusentesTitulo');
            var modalAusentesMeta = document.getElementById('modalAusentesMeta');
            var modalAusentesLista = document.getElementById('modalAusentesLista');
            var modalManualStudentId = document.getElementById('modalManualStudentId');
            var modalRegistrarIdBtn = document.getElementById('modalRegistrarIdBtn');
            var modalToggleQrBtn = document.getElementById('modalToggleQrBtn');
            var modalQrWrapper = document.getElementById('modalQrWrapper');
            var modalRegistroResultado = document.getElementById('modalRegistroResultado');
            var ausentesPorCurso = <?= json_encode($ausentesPorCurso, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            var qrReader = null;
            var qrActivo = false;
            var cursoActualModal = {
                id: '0',
                nivel: '',
                nombre: '',
            };

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

            function escaparHtml(texto) {
                return String(texto)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function actualizarMetaModal() {
                if (!modalAusentesMeta) {
                    return;
                }
                var faltanActual = Array.isArray(ausentesPorCurso[cursoActualModal.id]) ? ausentesPorCurso[cursoActualModal.id].length : 0;
                modalAusentesMeta.textContent = 'Fecha: <?= htmlspecialchars($fecha) ?> | Turno: <?= htmlspecialchars($turno) ?> | Faltan: ' + faltanActual;
            }

            function renderAusentesModal() {
                if (!modalAusentesLista) {
                    return;
                }
                var lista = Array.isArray(ausentesPorCurso[cursoActualModal.id]) ? ausentesPorCurso[cursoActualModal.id] : [];
                modalAusentesLista.innerHTML = '';
                if (lista.length === 0) {
                    modalAusentesLista.innerHTML = '<li class="list-group-item text-center text-muted">Sin ausentes</li>';
                    actualizarMetaModal();
                    return;
                }

                for (var i = 0; i < lista.length; i++) {
                    var item = lista[i];
                    var li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-center';
                    li.innerHTML = '<span>' + escaparHtml(item.nombre || '') + '</span>' +
                        '<button type="button" class="btn btn-sm btn-outline-success modal-reg-item-btn" data-id-estudiante="' + escaparHtml(item.id_estudiante || 0) + '">Registrar</button>';
                    modalAusentesLista.appendChild(li);
                }
                actualizarMetaModal();
            }

            function mostrarResultadoRegistro(tipo, mensaje) {
                if (!modalRegistroResultado) {
                    return;
                }
                var clase = 'alert-info';
                if (tipo === 'ok') {
                    clase = 'alert-success';
                } else if (tipo === 'error') {
                    clase = 'alert-danger';
                } else if (tipo === 'warn') {
                    clase = 'alert-warning';
                }
                modalRegistroResultado.innerHTML = '<div class="alert ' + clase + ' py-2 mb-0">' + escaparHtml(mensaje) + '</div>';
            }

            function actualizarTablasTrasRegistro(idEstudiante) {
                var lista = Array.isArray(ausentesPorCurso[cursoActualModal.id]) ? ausentesPorCurso[cursoActualModal.id] : [];
                var nuevaLista = [];
                for (var i = 0; i < lista.length; i++) {
                    if (String(lista[i].id_estudiante) !== String(idEstudiante)) {
                        nuevaLista.push(lista[i]);
                    }
                }
                ausentesPorCurso[cursoActualModal.id] = nuevaLista;

                var faltanNuevo = nuevaLista.length;
                var cursoRow = document.querySelector('tr[data-curso-row="' + cursoActualModal.id + '"]');
                if (cursoRow) {
                    var llegoCell = cursoRow.querySelector('td[data-field="llegaron"]');
                    if (llegoCell) {
                        var llegoActual = parseInt(llegoCell.textContent, 10) || 0;
                        llegoCell.textContent = String(llegoActual + 1);
                    }
                    var faltanBtn = cursoRow.querySelector('.mobile-faltan-btn');
                    if (faltanBtn) {
                        faltanBtn.textContent = String(faltanNuevo);
                        faltanBtn.setAttribute('data-faltan', String(faltanNuevo));
                    }
                }

                var nivelRow = document.querySelector('tr[data-nivel-row="' + cursoActualModal.nivel + '"]');
                if (nivelRow) {
                    var nivelLlegoCell = nivelRow.querySelector('td[data-field="llegaron"]');
                    var nivelFaltanCell = nivelRow.querySelector('td[data-field="faltan"]');
                    if (nivelLlegoCell) {
                        var nivelLlegoActual = parseInt(nivelLlegoCell.textContent, 10) || 0;
                        nivelLlegoCell.textContent = String(nivelLlegoActual + 1);
                    }
                    if (nivelFaltanCell) {
                        var nivelFaltanActual = parseInt(nivelFaltanCell.textContent, 10) || 0;
                        nivelFaltanCell.textContent = String(Math.max(nivelFaltanActual - 1, 0));
                    }
                }

                renderAusentesModal();
            }

            function registrarAsistenciaPorId(idEstudiante) {
                var idNum = parseInt(idEstudiante, 10);
                if (!(idNum > 0)) {
                    mostrarResultadoRegistro('warn', 'Ingresa un ID valido.');
                    return;
                }

                var lista = Array.isArray(ausentesPorCurso[cursoActualModal.id]) ? ausentesPorCurso[cursoActualModal.id] : [];
                var existeEnLista = false;
                for (var i = 0; i < lista.length; i++) {
                    if (String(lista[i].id_estudiante) === String(idNum)) {
                        existeEnLista = true;
                        break;
                    }
                }
                if (!existeEnLista) {
                    mostrarResultadoRegistro('warn', 'Ese ID no corresponde a un ausente de este curso.');
                    return;
                }

                mostrarResultadoRegistro('ok', 'Registrando asistencia...');
                fetch('asistencia.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: 'action=scan_qr&qr_data=' + encodeURIComponent('EST:' + idNum)
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (data && data.success) {
                            actualizarTablasTrasRegistro(idNum);
                            mostrarResultadoRegistro('ok', data.message || 'Asistencia registrada correctamente.');
                            if (modalManualStudentId) {
                                modalManualStudentId.value = '';
                                modalManualStudentId.focus();
                            }
                        } else {
                            mostrarResultadoRegistro('error', (data && data.message) ? data.message : 'No se pudo registrar la asistencia.');
                        }
                    })
                    .catch(function () {
                        mostrarResultadoRegistro('error', 'Error de conexion al registrar asistencia.');
                    });
            }

            function iniciarQr() {
                if (qrActivo || typeof Html5Qrcode === 'undefined') {
                    return;
                }
                qrReader = new Html5Qrcode('modalQrReader');
                qrReader.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 220, height: 220 } },
                    function (decodedText) {
                        var match = String(decodedText || '').match(/^EST:(\d+)$/i);
                        if (match && match[1]) {
                            registrarAsistenciaPorId(match[1]);
                        } else {
                            mostrarResultadoRegistro('warn', 'QR invalido para registro escolar.');
                        }
                    },
                    function () {}
                ).then(function () {
                    qrActivo = true;
                    if (modalToggleQrBtn) {
                        modalToggleQrBtn.textContent = 'Cerrar camara QR';
                    }
                }).catch(function () {
                    mostrarResultadoRegistro('error', 'No se pudo iniciar la camara QR.');
                });
            }

            function detenerQr() {
                if (!qrReader || !qrActivo) {
                    return;
                }
                qrReader.stop().then(function () {
                    qrReader.clear();
                    qrActivo = false;
                    qrReader = null;
                    if (modalToggleQrBtn) {
                        modalToggleQrBtn.textContent = 'Abrir camara QR';
                    }
                }).catch(function () {});
            }

            document.addEventListener('click', function (event) {
                var target = event.target;
                if (!target || !target.classList || !target.classList.contains('mobile-faltan-btn')) {
                    return;
                }

                if (!modalAusentes || window.innerWidth >= 768) {
                    return;
                }

                var cursoId = target.getAttribute('data-curso-id') || '0';
                var cursoNivel = target.getAttribute('data-curso-nivel') || '';
                var cursoNombre = target.getAttribute('data-curso-nombre') || 'Curso';
                cursoActualModal.id = cursoId;
                cursoActualModal.nivel = cursoNivel;
                cursoActualModal.nombre = cursoNombre;

                if (modalAusentesTitulo) {
                    modalAusentesTitulo.textContent = 'Ausentes - ' + cursoNombre;
                }
                if (modalRegistroResultado) {
                    modalRegistroResultado.innerHTML = '';
                }
                renderAusentesModal();

                modalAusentes.show();
            });

            if (modalAusentesLista) {
                modalAusentesLista.addEventListener('click', function (event) {
                    var btn = event.target;
                    if (!btn || !btn.classList || !btn.classList.contains('modal-reg-item-btn')) {
                        return;
                    }
                    var idEst = btn.getAttribute('data-id-estudiante') || '0';
                    registrarAsistenciaPorId(idEst);
                });
            }

            if (modalRegistrarIdBtn) {
                modalRegistrarIdBtn.addEventListener('click', function () {
                    var idVal = modalManualStudentId ? modalManualStudentId.value : '';
                    registrarAsistenciaPorId(idVal);
                });
            }

            if (modalManualStudentId) {
                modalManualStudentId.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        registrarAsistenciaPorId(modalManualStudentId.value);
                    }
                });
            }

            if (modalToggleQrBtn) {
                modalToggleQrBtn.addEventListener('click', function () {
                    if (!modalQrWrapper) {
                        return;
                    }
                    if (modalQrWrapper.classList.contains('d-none')) {
                        modalQrWrapper.classList.remove('d-none');
                        iniciarQr();
                    } else {
                        modalQrWrapper.classList.add('d-none');
                        detenerQr();
                    }
                });
            }

            if (modalAusentesElement) {
                modalAusentesElement.addEventListener('hidden.bs.modal', function () {
                    detenerQr();
                    if (modalQrWrapper) {
                        modalQrWrapper.classList.add('d-none');
                    }
                    if (modalManualStudentId) {
                        modalManualStudentId.value = '';
                    }
                    if (modalRegistroResultado) {
                        modalRegistroResultado.innerHTML = '';
                    }
                });
            }
        })();
    </script>
</body>
</html>
