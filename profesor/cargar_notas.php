<?php
session_start();
require_once '../config/database.php';

function calcularPromedioTrimestre($notasTrimestre, $esInicial = false) {
    if ($esInicial) {
        return '--';
    }
    if (empty($notasTrimestre)) {
        return 'N/A';
    }
    $valores = [];
    foreach ($notasTrimestre as $valor) {
        if ($valor !== null && $valor !== '' && is_numeric($valor)) {
            $valores[] = (float)$valor;
        }
    }
    if (empty($valores)) {
        return 'N/A';
    }
    return number_format(array_sum($valores) / count($valores), 2);
}

function construirUrlPeriodo($idCursoMateria, $trimestre, $parcial, $extra = []) {
    $params = array_merge([
        'curso_materia' => $idCursoMateria,
        'trimestre' => $trimestre,
        'parcial' => $parcial
    ], $extra);
    return 'cargar_notas.php?' . http_build_query($params);
}

function obtenerModalidadCargaValida($valor) {
    return $valor === 'trimestres' ? 'trimestres' : 'parciales';
}

function tablaDetalleCalificacionesDisponible(PDO $conn) {
    try {
        $conn->query('SELECT 1 FROM calificaciones_parciales_detalle LIMIT 0');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function asegurarTablaEtiquetasActividades(PDO $conn): void {
    $conn->exec("CREATE TABLE IF NOT EXISTS `parciales_etiquetas_actividades` (
        `id_materia` int(11) NOT NULL,
        `id_periodo_evaluacion` int(11) NOT NULL,
        `area` enum('SER','SABER','HACER') NOT NULL,
        `indice` tinyint unsigned NOT NULL,
        `etiqueta` varchar(120) NOT NULL DEFAULT '',
        `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id_materia`,`id_periodo_evaluacion`,`area`,`indice`),
        KEY `idx_periodo_materia` (`id_materia`,`id_periodo_evaluacion`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

/**
 * @return array{SER: array<int, string>, SABER: array<int, string>, HACER: array<int, string>}
 */
function cargarEtiquetasActividades(PDO $conn, int $idMateria, int $idPeriodo): array {
    $default = ['SER' => [], 'SABER' => [], 'HACER' => []];
    for ($i = 1; $i <= 4; $i++) {
        $default['SER'][$i] = '';
    }
    for ($i = 1; $i <= 8; $i++) {
        $default['SABER'][$i] = '';
        $default['HACER'][$i] = '';
    }
    $stmt = $conn->prepare('SELECT area, indice, etiqueta FROM parciales_etiquetas_actividades
        WHERE id_materia = ? AND id_periodo_evaluacion = ?');
    $stmt->execute([$idMateria, $idPeriodo]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $a = $row['area'];
        $idx = (int)$row['indice'];
        if (isset($default[$a][$idx])) {
            $default[$a][$idx] = (string)$row['etiqueta'];
        }
    }
    return $default;
}

function guardarEtiquetasActividades(PDO $conn, int $idMateria, int $idPeriodo, array $postEtiquetas): void {
    // No llamar a asegurarTablaEtiquetasActividades() aquí: CREATE TABLE hace COMMIT implícito en MySQL y rompe la transacción activa.
    $stmtDel = $conn->prepare('DELETE FROM parciales_etiquetas_actividades WHERE id_materia = ? AND id_periodo_evaluacion = ?');
    $stmtDel->execute([$idMateria, $idPeriodo]);
    $stmtIns = $conn->prepare('INSERT INTO parciales_etiquetas_actividades
        (id_materia, id_periodo_evaluacion, area, indice, etiqueta) VALUES (?, ?, ?, ?, ?)');
    $areas = ['SER' => 4, 'SABER' => 8, 'HACER' => 8];
    foreach ($areas as $area => $max) {
        $bloque = $postEtiquetas[$area] ?? null;
        if (!is_array($bloque)) {
            continue;
        }
        for ($i = 1; $i <= $max; $i++) {
            $texto = isset($bloque[$i]) ? trim((string)$bloque[$i]) : '';
            $texto = mb_substr($texto, 0, 120);
            if ($texto !== '') {
                $stmtIns->execute([$idMateria, $idPeriodo, $area, $i, $texto]);
            }
        }
    }
}

function buscarRelacionComplementaria(PDO $conn, int $materiaId, string $gestionActual, bool $comoPrincipal): ?array {
    $columnaFiltro = $comoPrincipal ? 'id_materia_principal' : 'id_materia_complementaria';
    $columnaRelacion = $comoPrincipal ? 'id_materia_complementaria' : 'id_materia_principal';
    $sql = "SELECT $columnaRelacion AS materia_relacionada, porcentaje_transferencia, gestion
            FROM materias_complementarias
            WHERE $columnaFiltro = ?
              AND (gestion = '' OR gestion = ?)
            ORDER BY CASE WHEN gestion = ? THEN 2 WHEN gestion = '' THEN 1 ELSE 0 END DESC
            LIMIT 1";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$materiaId, $gestionActual, $gestionActual]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }
        $fila['materia_relacionada'] = (int)$fila['materia_relacionada'];
        $fila['porcentaje_transferencia'] = (float)$fila['porcentaje_transferencia'];
        return $fila;
    } catch (PDOException $e) {
        return null;
    }
}

function aplicarBonusComplementario(
    PDO $conn,
    int $idCurso,
    int $materiaPrincipalId,
    int $materiaComplementariaId,
    string $gestion,
    int $trimestre,
    float $porcentajeTransferencia
): void {
    if ($materiaPrincipalId <= 0 || $materiaComplementariaId <= 0 || $porcentajeTransferencia <= 0) {
        return;
    }

    $stmtEst = $conn->prepare('SELECT id_estudiante FROM estudiantes WHERE id_curso = ?');
    $stmtEst->execute([$idCurso]);
    $listaEstudiantes = $stmtEst->fetchAll(PDO::FETCH_COLUMN, 0);
    if (empty($listaEstudiantes)) {
        return;
    }

    $stmtPromParciales = $conn->prepare(
        "SELECT e.id_estudiante,
                AVG(CASE WHEN pe.id_periodo_evaluacion IS NOT NULL THEN cp.calificacion END) AS promedio_parciales
         FROM estudiantes e
         LEFT JOIN calificaciones_parciales cp
                ON cp.id_estudiante = e.id_estudiante
               AND cp.id_materia = ?
         LEFT JOIN periodos_evaluacion pe
                ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
               AND pe.gestion = ?
               AND pe.trimestre = ?
         WHERE e.id_curso = ?
         GROUP BY e.id_estudiante"
    );
    $stmtPromParciales->execute([$materiaComplementariaId, $gestion, $trimestre, $idCurso]);
    $promParciales = [];
    foreach ($stmtPromParciales->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $promParciales[(int)$row['id_estudiante']] = $row['promedio_parciales'] !== null ? (float)$row['promedio_parciales'] : null;
    }

    $stmtTrimestralComp = $conn->prepare(
        "SELECT e.id_estudiante, ct.autoevaluacion, ct.nota_extra
         FROM estudiantes e
         LEFT JOIN calificaciones_trimestrales ct
                ON ct.id_estudiante = e.id_estudiante
               AND ct.id_materia = ?
               AND ct.gestion = ?
               AND ct.trimestre = ?
         WHERE e.id_curso = ?"
    );
    $stmtTrimestralComp->execute([$materiaComplementariaId, $gestion, $trimestre, $idCurso]);
    $trimestralComp = [];
    foreach ($stmtTrimestralComp->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $trimestralComp[(int)$row['id_estudiante']] = [
            'autoevaluacion' => $row['autoevaluacion'] !== null ? (float)$row['autoevaluacion'] : null,
            'nota_extra' => $row['nota_extra'] !== null ? (float)$row['nota_extra'] : null,
        ];
    }

    $stmtTrimestralPrincipal = $conn->prepare(
        "SELECT id_estudiante, autoevaluacion, nota_extra, id_profesor
         FROM calificaciones_trimestrales
         WHERE id_materia = ? AND gestion = ? AND trimestre = ? AND id_estudiante IN (
             SELECT id_estudiante FROM estudiantes WHERE id_curso = ?
         )"
    );
    $stmtTrimestralPrincipal->execute([$materiaPrincipalId, $gestion, $trimestre, $idCurso]);
    $principalExistente = [];
    foreach ($stmtTrimestralPrincipal->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $principalExistente[(int)$row['id_estudiante']] = $row;
    }

    $stmtUpdate = $conn->prepare(
        'UPDATE calificaciones_trimestrales
         SET nota_extra = ?
         WHERE id_estudiante = ? AND id_materia = ? AND gestion = ? AND trimestre = ?'
    );
    $stmtInsert = $conn->prepare(
        'INSERT INTO calificaciones_trimestrales
            (id_estudiante, id_materia, gestion, trimestre, autoevaluacion, nota_extra, id_profesor)
         VALUES (?, ?, ?, ?, NULL, ?, NULL)'
    );

    foreach ($listaEstudiantes as $idEst) {
        $idEst = (int)$idEst;
        $parcial = $promParciales[$idEst] ?? null;
        $parcial = $parcial !== null ? min(max((float)$parcial, 0.0), 95.0) : 0.0;

        $compData = $trimestralComp[$idEst] ?? ['autoevaluacion' => null, 'nota_extra' => null];
        $autoComp = $compData['autoevaluacion'] !== null ? max(min((float)$compData['autoevaluacion'], 5.0), 0.0) : 0.0;
        $extraComp = $compData['nota_extra'] !== null ? max((float)$compData['nota_extra'], 0.0) : 0.0;

        $notaFinalComplementaria = $parcial + $autoComp + $extraComp;
        if ($notaFinalComplementaria > 100) {
            $notaFinalComplementaria = 100;
        }

        $bonus = round(($notaFinalComplementaria / 100.0) * $porcentajeTransferencia, 2);
        if ($bonus > $porcentajeTransferencia) {
            $bonus = $porcentajeTransferencia;
        }
        if ($bonus < 0) {
            $bonus = 0.0;
        }

        if (isset($principalExistente[$idEst])) {
            $stmtUpdate->execute([$bonus, $idEst, $materiaPrincipalId, $gestion, $trimestre]);
        } elseif ($bonus > 0) {
            $stmtInsert->execute([$idEst, $materiaPrincipalId, $gestion, $trimestre, $bonus]);
        }
    }
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header('Location: ../index.php');
    exit();
}

$profesor_id = $_SESSION['user_id'];
$id_curso_materia = isset($_GET['curso_materia']) ? (int)$_GET['curso_materia'] : 0;
if ($id_curso_materia <= 0) {
    header('Location: dashboard.php?error=params');
    exit();
}

$conn = (new Database())->connect();
$tieneDetalleCalificaciones = tablaDetalleCalificacionesDisponible($conn);

$stmt = $conn->query("SELECT anio_escolar, modalidad_carga_notas FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
$configuracionSistema = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$gestionConfigurada = isset($configuracionSistema['anio_escolar']) ? trim((string)$configuracionSistema['anio_escolar']) : '';
$gestionActual = $gestionConfigurada !== '' ? $gestionConfigurada : date('Y');
$modalidadCarga = obtenerModalidadCargaValida($configuracionSistema['modalidad_carga_notas'] ?? 'parciales');
$gestionAlternativa = null;
if (preg_match('/\b(20\d{2})\b/', $gestionActual, $matches)) {
    $gestionAlternativa = $matches[1];
}

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
    header('Location: dashboard.php?error=notfound');
    exit();
}

$es_inicial = ($curso['nivel'] == 'Inicial');
$es_primaria_basica = ($curso['nivel'] == 'Primaria' && isset($curso['curso']) && (int)$curso['curso'] >= 1 && (int)$curso['curso'] <= 6);
$relacionComplementariaPrincipal = $es_primaria_basica ? buscarRelacionComplementaria($conn, (int)$curso['id_materia'], $gestionActual, true) : null;
$relacionComplementariaComoSub = $es_primaria_basica ? buscarRelacionComplementaria($conn, (int)$curso['id_materia'], $gestionActual, false) : null;
$es_materia_principal_complementada = $relacionComplementariaPrincipal !== null;
$es_materia_complementaria = $relacionComplementariaComoSub !== null;
$materiaComplementariaId = $es_materia_principal_complementada ? (int)$relacionComplementariaPrincipal['materia_relacionada'] : 0;
$materiaPrincipalDesdeComplementariaId = $es_materia_complementaria ? (int)$relacionComplementariaComoSub['materia_relacionada'] : 0;
$porcentajeTransferenciaPrincipal = $es_materia_principal_complementada ? (float)$relacionComplementariaPrincipal['porcentaje_transferencia'] : 0.0;
$porcentajeTransferenciaComoComplementaria = $es_materia_complementaria ? (float)$relacionComplementariaComoSub['porcentaje_transferencia'] : 0.0;
$campo = $es_inicial ? 'comentario' : 'calificacion';
if ($es_inicial) {
    $modalidadCarga = 'trimestres';
}

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

$estudiantesPorId = [];
foreach ($estudiantes as $estudiante) {
    $estudiantesPorId[$estudiante['id_estudiante']] = $estudiante;
}

$sqlPeriodos = "SELECT id_periodo_evaluacion, gestion, trimestre, parcial, nombre, fecha_inicio, fecha_fin, esta_activo
                FROM periodos_evaluacion
                WHERE gestion = ?";
$paramsPeriodos = [$gestionActual];
if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
    $sqlPeriodos .= " OR gestion = ?";
    $paramsPeriodos[] = $gestionAlternativa;
}
$sqlPeriodos .= " ORDER BY trimestre, parcial";
$stmt = $conn->prepare($sqlPeriodos);
$stmt->execute($paramsPeriodos);
$periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($periodos)) {
    die('No existen periodos de evaluación configurados para la gestión actual (' . htmlspecialchars($gestionActual) . ').');
}

if ($gestionAlternativa !== null) {
    foreach ($periodos as $periodoEncontrado) {
        $gestionActual = $periodoEncontrado['gestion'];
        break;
    }
}

$periodosPorTrimestre = [];
$periodosActivos = [];
foreach ($periodos as $periodo) {
    $trimestre = (int)$periodo['trimestre'];
    $parcial = (int)$periodo['parcial'];
    $periodosPorTrimestre[$trimestre][$parcial] = $periodo;

    $hoy = date('Y-m-d');
    $dentroRango = (empty($periodo['fecha_inicio']) || $hoy >= $periodo['fecha_inicio']) &&
                   (empty($periodo['fecha_fin']) || $hoy <= $periodo['fecha_fin']);
    if ((int)$periodo['esta_activo'] === 1 && $dentroRango) {
        $periodosActivos[] = $periodo;
    }
}

$trimestreSeleccionado = isset($_REQUEST['trimestre']) ? (int)$_REQUEST['trimestre'] : 0;
$parcialSeleccionado = isset($_REQUEST['parcial']) ? (int)$_REQUEST['parcial'] : 0;
$periodoConfirmado = isset($_GET['confirmar']) && $_GET['confirmar'] === '1';
$vistaActual = isset($_REQUEST['vista']) && $_REQUEST['vista'] === 'trimestral' ? 'trimestral' : 'parcial';

if ($modalidadCarga === 'trimestres') {
    $parcialSeleccionado = 1;
}

if ($trimestreSeleccionado <= 0 || $parcialSeleccionado <= 0 || !isset($periodosPorTrimestre[$trimestreSeleccionado][$parcialSeleccionado])) {
    if (!empty($periodosActivos)) {
        $trimestreSeleccionado = (int)$periodosActivos[0]['trimestre'];
        $parcialSeleccionado = $modalidadCarga === 'trimestres' ? 1 : (int)$periodosActivos[0]['parcial'];
    } else {
        $primerPeriodo = $periodos[0];
        $trimestreSeleccionado = (int)$primerPeriodo['trimestre'];
        $parcialSeleccionado = $modalidadCarga === 'trimestres' ? 1 : (int)$primerPeriodo['parcial'];
    }
    $periodoConfirmado = false;
}

if ($modalidadCarga === 'trimestres' && !isset($periodosPorTrimestre[$trimestreSeleccionado][1])) {
    $primerParcialDisponible = array_key_first($periodosPorTrimestre[$trimestreSeleccionado] ?? []);
    if ($primerParcialDisponible !== null) {
        $parcialSeleccionado = (int)$primerParcialDisponible;
    }
}

$periodoSeleccionado = $periodosPorTrimestre[$trimestreSeleccionado][$parcialSeleccionado];
$idPeriodoSeleccionado = (int)$periodoSeleccionado['id_periodo_evaluacion'];
$hoy = date('Y-m-d');
$periodoEditable = (int)$periodoSeleccionado['esta_activo'] === 1 &&
                   (empty($periodoSeleccionado['fecha_inicio']) || $hoy >= $periodoSeleccionado['fecha_inicio']) &&
                   (empty($periodoSeleccionado['fecha_fin']) || $hoy <= $periodoSeleccionado['fecha_fin']);

$stmt = $conn->prepare("SELECT cp.id_estudiante, pe.trimestre, pe.parcial, cp.$campo AS valor
                        FROM calificaciones_parciales cp
                        INNER JOIN periodos_evaluacion pe ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
                        WHERE cp.id_materia = ?
                          AND pe.gestion = ?");
$stmt->execute([$curso['id_materia'], $gestionActual]);
$notas = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $notas[$row['id_estudiante']][(int)$row['trimestre']][(int)$row['parcial']] = $row['valor'];
}

$detalleNotas = [];
$totalesAreasPorEstudiante = [];
if (!$es_inicial) {
    $hasAreaCols = false;
    try {
        $conn->query('SELECT ser_total FROM calificaciones_parciales LIMIT 0');
        $hasAreaCols = true;
    } catch (PDOException $e) {
        $hasAreaCols = false;
    }
    if ($hasAreaCols && $tieneDetalleCalificaciones) {
        $stmt = $conn->prepare("SELECT cp.id_estudiante, cp.ser_total, cp.saber_total, cp.hacer_total, cp.calificacion,
                                       cpd.area, cpd.indice, cpd.nota
                                FROM calificaciones_parciales cp
                                LEFT JOIN calificaciones_parciales_detalle cpd
                                    ON cpd.id_calificacion_parcial = cp.id_calificacion_parcial
                                WHERE cp.id_materia = ? AND cp.id_periodo_evaluacion = ?");
        $stmt->execute([$curso['id_materia'], $idPeriodoSeleccionado]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $idEst = (int)$row['id_estudiante'];
            if (!isset($totalesAreasPorEstudiante[$idEst])) {
                $totalesAreasPorEstudiante[$idEst] = [
                    'ser_total' => (float)($row['ser_total'] ?? 0),
                    'saber_total' => (float)($row['saber_total'] ?? 0),
                    'hacer_total' => (float)($row['hacer_total'] ?? 0),
                    'calificacion' => (float)($row['calificacion'] ?? 0)
                ];
            }
            if ($row['area'] !== null && $row['area'] !== '' && $row['indice'] !== null && $row['nota'] !== null) {
                $detalleNotas[$idEst][$row['area']][(int)$row['indice']] = (float)$row['nota'];
            }
        }
    } elseif ($hasAreaCols) {
        $stmt = $conn->prepare("SELECT id_estudiante, ser_total, saber_total, hacer_total, calificacion
                                FROM calificaciones_parciales
                                WHERE id_materia = ? AND id_periodo_evaluacion = ?");
        $stmt->execute([$curso['id_materia'], $idPeriodoSeleccionado]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $idEst = (int)$row['id_estudiante'];
            $totalesAreasPorEstudiante[$idEst] = [
                'ser_total' => (float)($row['ser_total'] ?? 0),
                'saber_total' => (float)($row['saber_total'] ?? 0),
                'hacer_total' => (float)($row['hacer_total'] ?? 0),
                'calificacion' => (float)($row['calificacion'] ?? 0)
            ];
        }
    }
}

$etiquetasActividades = [
    'SER' => [1 => '', 2 => '', 3 => '', 4 => ''],
    'SABER' => array_fill(1, 8, ''),
    'HACER' => array_fill(1, 8, ''),
];
if (!$es_inicial) {
    try {
        asegurarTablaEtiquetasActividades($conn);
        $etiquetasActividades = cargarEtiquetasActividades($conn, (int)$curso['id_materia'], $idPeriodoSeleccionado);
    } catch (PDOException $e) {
        // Sin tabla o sin permisos: solo placeholders en la vista
    }
}

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
    // Table may not exist yet — ignore
}

$trimestreEditableParaVistaTrimestral = false;
if ($vistaActual === 'trimestral') {
    $parcialesTrim = $periodosPorTrimestre[$trimestreSeleccionado] ?? [];
    foreach ($parcialesTrim as $p) {
        $activo = (int)$p['esta_activo'] === 1 &&
                  (empty($p['fecha_inicio']) || $hoy >= $p['fecha_inicio']) &&
                  (empty($p['fecha_fin']) || $hoy <= $p['fecha_fin']);
        if ($activo) { $trimestreEditableParaVistaTrimestral = true; break; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("SELECT id_periodo_evaluacion, fecha_inicio, fecha_fin, esta_activo
                                FROM periodos_evaluacion
                                WHERE gestion = ?
                                  AND trimestre = ?
                                  AND parcial = ?
                                LIMIT 1");
        $stmt->execute([$gestionActual, $trimestreSeleccionado, $parcialSeleccionado]);
        $periodoValidado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$periodoValidado) {
            throw new Exception("El periodo seleccionado no existe.");
        }

        $periodoEditable = (int)$periodoValidado['esta_activo'] === 1 &&
                           (empty($periodoValidado['fecha_inicio']) || $hoy >= $periodoValidado['fecha_inicio']) &&
                           (empty($periodoValidado['fecha_fin']) || $hoy <= $periodoValidado['fecha_fin']);

        if (!$periodoEditable) {
            if ($modalidadCarga === 'trimestres') {
                throw new Exception("El trimestre $trimestreSeleccionado no está habilitado para carga de notas");
            }
            throw new Exception("El trimestre $trimestreSeleccionado - parcial $parcialSeleccionado no está habilitado para carga de notas");
        }

        $idPeriodoSeleccionado = (int)$periodoValidado['id_periodo_evaluacion'];
        // DDL fuera de la transacción: CREATE TABLE provoca COMMIT implícito y deja "There is no active transaction" al hacer commit().
        try {
            asegurarTablaEtiquetasActividades($conn);
        } catch (PDOException $e) {
            // Sin permisos o error de esquema: las etiquetas pueden no guardarse; las notas siguen igual.
        }
        $conn->beginTransaction();

        if (isset($_POST['guardar_notas'])) {
            $notasPost = $_POST['notas'] ?? [];
            if (is_array($notasPost)) {
                $notasNorm = [];
                foreach ($notasPost as $claveEst => $bloque) {
                    if (is_numeric($claveEst)) {
                        $notasNorm[(int)$claveEst] = $bloque;
                    }
                }
                $notasPost = $notasNorm;
            }

            if ($es_inicial) {
                $comentariosValidar = [];
                foreach ($estudiantes as $estVal) {
                    $idEstVal = (int)$estVal['id_estudiante'];
                    $datosVal = $notasPost[$idEstVal] ?? null;
                    $valorVal = is_string($datosVal) ? trim($datosVal) : '';
                    if ($valorVal === '') continue;
                    if (mb_strlen($valorVal) > 250) {
                        throw new Exception("El comentario de " . $estVal['nombre'] . " excede los 250 caracteres (" . mb_strlen($valorVal) . " caracteres).");
                    }
                    $comentariosValidar[] = mb_strtolower(trim($valorVal));
                }
                $frecuenciasComentarios = array_count_values($comentariosValidar);
                foreach ($frecuenciasComentarios as $comTexto => $comCuenta) {
                    if ($comCuenta > 3) {
                        $previewTexto = mb_substr($comTexto, 0, 50) . (mb_strlen($comTexto) > 50 ? '...' : '');
                        throw new Exception("Un comentario se repite en $comCuenta estudiantes (máx. 3): \"" . htmlspecialchars($previewTexto) . "\"");
                    }
                }
            }

            if (!$es_inicial) {
                $parseNotaValidacion = static function ($v) {
                    if ($v === null || $v === '') {
                        return null;
                    }
                    $v = str_replace(',', '.', trim((string)$v));
                    return is_numeric($v) ? (float)$v : null;
                };
                $conteosPorEstudiante = [];
                foreach ($estudiantes as $estVal) {
                    $idEstVal = (int)$estVal['id_estudiante'];
                    $datosVal = $notasPost[$idEstVal] ?? null;
                    if (!is_array($datosVal)) {
                        continue;
                    }
                    $nSer = 0;
                    for ($i = 1; $i <= 4; $i++) {
                        if ($parseNotaValidacion($datosVal['SER'][$i] ?? '') !== null) {
                            $nSer++;
                        }
                    }
                    $nSaber = 0;
                    for ($i = 1; $i <= 8; $i++) {
                        if ($parseNotaValidacion($datosVal['SABER'][$i] ?? '') !== null) {
                            $nSaber++;
                        }
                    }
                    $nHacer = 0;
                    for ($i = 1; $i <= 8; $i++) {
                        if ($parseNotaValidacion($datosVal['HACER'][$i] ?? '') !== null) {
                            $nHacer++;
                        }
                    }
                    if ($nSer === 0 && $nSaber === 0 && $nHacer === 0) {
                        continue;
                    }
                    if ($nSer !== $nSaber || $nSaber !== $nHacer) {
                        throw new Exception(
                            'En ' . $estVal['nombre'] . ': SER, SABER y HACER deben tener la misma cantidad de actividades cargadas '
                            . "(SER: $nSer, SABER: $nSaber, HACER: $nHacer)."
                        );
                    }
                    $conteosPorEstudiante[$idEstVal] = $nSer;
                }
                if (count($conteosPorEstudiante) > 1) {
                    $valoresUnicos = array_unique(array_values($conteosPorEstudiante));
                    if (count($valoresUnicos) > 1) {
                        $detalle = [];
                        foreach ($conteosPorEstudiante as $idE => $cant) {
                            foreach ($estudiantes as $e) {
                                if ((int)$e['id_estudiante'] === $idE) {
                                    $detalle[] = $e['nombre'] . ": $cant actividades por área";
                                    break;
                                }
                            }
                        }
                        throw new Exception(
                            'Todos los estudiantes con notas deben tener la misma cantidad de actividades en SER, SABER y HACER. '
                            . implode('; ', $detalle) . '.'
                        );
                    }
                }
            }

            if (!$es_inicial && !empty($periodoEditable) && isset($_POST['guardar_notas'])) {
                $etiPost = $_POST['etiquetas_actividades'] ?? [];
                if (is_array($etiPost)) {
                    guardarEtiquetasActividades($conn, (int)$curso['id_materia'], $idPeriodoSeleccionado, $etiPost);
                }
            }

            $stmtPrevCalif = $conn->prepare("SELECT ser_total, saber_total, hacer_total
                                             FROM calificaciones_parciales
                                             WHERE id_estudiante = ? AND id_materia = ? AND id_periodo_evaluacion = ?
                                             LIMIT 1");

            $stmtGetCalifId = null;
            $stmtUpsertDetalle = null;
            $stmtDeleteDetalle = null;
            $stmtPurgeDetalleCalif = null;
            if ($tieneDetalleCalificaciones) {
                $stmtGetCalifId = $conn->prepare('SELECT id_calificacion_parcial FROM calificaciones_parciales
                    WHERE id_estudiante = ? AND id_materia = ? AND id_periodo_evaluacion = ? LIMIT 1');
                $stmtUpsertDetalle = $conn->prepare('INSERT INTO calificaciones_parciales_detalle
                    (id_calificacion_parcial, area, indice, nota, creado_por) VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE nota = VALUES(nota), creado_por = VALUES(creado_por)');
                $stmtDeleteDetalle = $conn->prepare('DELETE FROM calificaciones_parciales_detalle
                    WHERE id_calificacion_parcial = ? AND area = ? AND indice = ?');
                $stmtPurgeDetalleCalif = $conn->prepare('DELETE FROM calificaciones_parciales_detalle WHERE id_calificacion_parcial = ?');
            }

            foreach ($estudiantes as $estudiante) {
                $idEstudiante = (int)$estudiante['id_estudiante'];
                $datosEstudiante = $notasPost[$idEstudiante] ?? null;

                if ($es_inicial) {
                    $valor = is_string($datosEstudiante) ? trim($datosEstudiante) : '';
                    if ($valor === '') {
                        $conn->prepare("DELETE FROM calificaciones_parciales
                                        WHERE id_estudiante = ?
                                          AND id_materia = ?
                                          AND id_periodo_evaluacion = ?")
                             ->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado]);
                        continue;
                    }

                    $conn->prepare("INSERT INTO calificaciones_parciales
                                    (id_estudiante, id_materia, id_periodo_evaluacion, comentario)
                                    VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE comentario = VALUES(comentario)")
                         ->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado, $valor]);
                } else {
                    if (!is_array($datosEstudiante)) {
                        continue;
                    }

                    $parseNota = function($v) {
                        if ($v === null || $v === '') return null;
                        $v = str_replace(',', '.', trim($v));
                        return is_numeric($v) ? (float)$v : null;
                    };

                    $serVals = [];
                    for ($i = 1; $i <= 4; $i++) {
                        $n = $parseNota($datosEstudiante['SER'][$i] ?? '');
                        if ($n !== null) {
                            if ($n < 0 || $n > 10) {
                                throw new Exception('Nota SER fuera de rango (0–10): ' . $estudiante['nombre'] . " (casilla $i)");
                            }
                            $serVals[] = $n;
                        }
                    }
                    $saberVals = [];
                    for ($i = 1; $i <= 8; $i++) {
                        $n = $parseNota($datosEstudiante['SABER'][$i] ?? '');
                        if ($n !== null) {
                            if ($n < 0 || $n > 45) {
                                throw new Exception('Nota SABER fuera de rango (0–45): ' . $estudiante['nombre'] . " (casilla $i)");
                            }
                            $saberVals[] = $n;
                        }
                    }
                    $hacerVals = [];
                    for ($i = 1; $i <= 8; $i++) {
                        $n = $parseNota($datosEstudiante['HACER'][$i] ?? '');
                        if ($n !== null) {
                            if ($n < 0 || $n > 40) {
                                throw new Exception('Nota HACER fuera de rango (0–40): ' . $estudiante['nombre'] . " (casilla $i)");
                            }
                            $hacerVals[] = $n;
                        }
                    }

                    if (empty($serVals) && empty($saberVals) && empty($hacerVals)) {
                        if ($tieneDetalleCalificaciones && $stmtGetCalifId && $stmtPurgeDetalleCalif) {
                            $stmtGetCalifId->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado]);
                            $idPurge = (int)$stmtGetCalifId->fetchColumn();
                            if ($idPurge > 0) {
                                $stmtPurgeDetalleCalif->execute([$idPurge]);
                            }
                        }
                        $conn->prepare("DELETE FROM calificaciones_parciales
                                        WHERE id_estudiante = ?
                                          AND id_materia = ?
                                          AND id_periodo_evaluacion = ?")
                             ->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado]);
                        continue;
                    }

                    $stmtPrevCalif->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado]);
                    $filaPrev = $stmtPrevCalif->fetch(PDO::FETCH_ASSOC) ?: null;

                    $serProm = !empty($serVals)
                        ? array_sum($serVals) / count($serVals)
                        : ($filaPrev !== null ? (float)$filaPrev['ser_total'] : 0);
                    $saberProm = !empty($saberVals)
                        ? array_sum($saberVals) / count($saberVals)
                        : ($filaPrev !== null ? (float)$filaPrev['saber_total'] : 0);
                    $hacerProm = !empty($hacerVals)
                        ? array_sum($hacerVals) / count($hacerVals)
                        : ($filaPrev !== null ? (float)$filaPrev['hacer_total'] : 0);

                    // Por área: promedio de las notas cargadas (SER 0–10, SABER 0–45, HACER 0–40 por casilla). Total: suma de promedios (máx. 95).
                    $serTotal = round($serProm, 2);
                    $saberTotal = round($saberProm, 2);
                    $hacerTotal = round($hacerProm, 2);
                    $calificacion = round($serProm + $saberProm + $hacerProm, 2);

                    $conn->prepare("INSERT INTO calificaciones_parciales
                                    (id_estudiante, id_materia, id_periodo_evaluacion, calificacion, ser_total, saber_total, hacer_total)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion),
                                                            ser_total = VALUES(ser_total),
                                                            saber_total = VALUES(saber_total),
                                                            hacer_total = VALUES(hacer_total)")
                         ->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado,
                                    $calificacion, $serTotal, $saberTotal, $hacerTotal]);

                    if ($tieneDetalleCalificaciones && $stmtGetCalifId && $stmtUpsertDetalle && $stmtDeleteDetalle) {
                        $stmtGetCalifId->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado]);
                        $idCalif = (int)$stmtGetCalifId->fetchColumn();
                        if ($idCalif > 0) {
                            if (!empty($serVals)) {
                                for ($i = 1; $i <= 4; $i++) {
                                    $n = $parseNota($datosEstudiante['SER'][$i] ?? '');
                                    if ($n !== null) {
                                        $stmtUpsertDetalle->execute([$idCalif, 'SER', $i, $n, $profesor_id]);
                                    } else {
                                        $stmtDeleteDetalle->execute([$idCalif, 'SER', $i]);
                                    }
                                }
                            } elseif ($filaPrev === null) {
                                for ($i = 1; $i <= 4; $i++) {
                                    $stmtDeleteDetalle->execute([$idCalif, 'SER', $i]);
                                }
                            }

                            if (!empty($saberVals)) {
                                for ($i = 1; $i <= 8; $i++) {
                                    $n = $parseNota($datosEstudiante['SABER'][$i] ?? '');
                                    if ($n !== null) {
                                        $stmtUpsertDetalle->execute([$idCalif, 'SABER', $i, $n, $profesor_id]);
                                    } else {
                                        $stmtDeleteDetalle->execute([$idCalif, 'SABER', $i]);
                                    }
                                }
                            } elseif ($filaPrev === null) {
                                for ($i = 1; $i <= 8; $i++) {
                                    $stmtDeleteDetalle->execute([$idCalif, 'SABER', $i]);
                                }
                            }

                            if (!empty($hacerVals)) {
                                for ($i = 1; $i <= 8; $i++) {
                                    $n = $parseNota($datosEstudiante['HACER'][$i] ?? '');
                                    if ($n !== null) {
                                        $stmtUpsertDetalle->execute([$idCalif, 'HACER', $i, $n, $profesor_id]);
                                    } else {
                                        $stmtDeleteDetalle->execute([$idCalif, 'HACER', $i]);
                                    }
                                }
                            } elseif ($filaPrev === null) {
                                for ($i = 1; $i <= 8; $i++) {
                                    $stmtDeleteDetalle->execute([$idCalif, 'HACER', $i]);
                                }
                            }
                        }
                    }
                }
            }
        }

        if (isset($_POST['guardar_excel'])) {
            $datosExcelTexto = isset($_POST['datos_excel']) ? trim($_POST['datos_excel']) : '';
            $lineas = $datosExcelTexto === '' ? [] : preg_split('/\r\n|\r|\n/', $datosExcelTexto);

            if (count($lineas) !== count($estudiantes)) {
                throw new Exception("La cantidad de " . ($es_inicial ? "comentarios" : "notas") . " no coincide con el número de estudiantes.");
            }

            foreach ($estudiantes as $index => $estudiante) {
                $valor = trim($lineas[$index]);

                if ($es_inicial) {
                    if ($valor === '') {
                        $conn->prepare("DELETE FROM calificaciones_parciales
                                        WHERE id_estudiante = ?
                                          AND id_materia = ?
                                          AND id_periodo_evaluacion = ?")
                             ->execute([$estudiante['id_estudiante'], $curso['id_materia'], $idPeriodoSeleccionado]);
                        continue;
                    }

                    $conn->prepare("INSERT INTO calificaciones_parciales
                                    (id_estudiante, id_materia, id_periodo_evaluacion, comentario)
                                    VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE comentario = VALUES(comentario)")
                         ->execute([$estudiante['id_estudiante'], $curso['id_materia'], $idPeriodoSeleccionado, $valor]);
                } else {
                    if ($valor === '') {
                        $conn->prepare("DELETE FROM calificaciones_parciales
                                        WHERE id_estudiante = ?
                                          AND id_materia = ?
                                          AND id_periodo_evaluacion = ?")
                             ->execute([$estudiante['id_estudiante'], $curso['id_materia'], $idPeriodoSeleccionado]);
                        continue;
                    }

                    if (!is_numeric(str_replace(',', '.', $valor))) {
                        throw new Exception("Nota inválida en la línea " . ($index + 1));
                    }

                    $notaValor = (float)str_replace(',', '.', $valor);
                    $conn->prepare("INSERT INTO calificaciones_parciales
                                    (id_estudiante, id_materia, id_periodo_evaluacion, calificacion)
                                    VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion)")
                         ->execute([$estudiante['id_estudiante'], $curso['id_materia'], $idPeriodoSeleccionado, $notaValor]);
                }
            }
        }

        if (isset($_POST['guardar_trimestral'])) {
            $promedioParcialesTrimestre = static function (int $idEst) use ($notas, $trimestreSeleccionado): ?float {
                $parciales95 = [];
                for ($px = 1; $px <= 3; $px++) {
                    $v = $notas[$idEst][$trimestreSeleccionado][$px] ?? null;
                    $parciales95[$px] = ($v !== null && $v !== '' && is_numeric($v)) ? (float)$v : null;
                }
                $vals = array_filter($parciales95, static fn($x) => $x !== null);
                return count($vals) > 0 ? array_sum($vals) / count($vals) : null;
            };

            $trimestralAGuardar = [];
            foreach ($estudiantes as $estudiante) {
                $idEst = (int)$estudiante['id_estudiante'];
                $autoVal = isset($_POST['auto'][$idEst]) ? trim((string)$_POST['auto'][$idEst]) : '';
                $extraVal = isset($_POST['extra'][$idEst]) ? trim((string)$_POST['extra'][$idEst]) : '';

                $autoNum = ($autoVal !== '' && is_numeric(str_replace(',', '.', $autoVal)))
                    ? (float)str_replace(',', '.', $autoVal) : null;
                $extraNum = ($extraVal !== '' && is_numeric(str_replace(',', '.', $extraVal)))
                    ? (float)str_replace(',', '.', $extraVal) : null;

                if ($es_materia_principal_complementada && ($extraVal === '' || $extraNum === null)) {
                    $exPrev = $notasTrimestrales[$idEst][$trimestreSeleccionado]['nota_extra'] ?? null;
                    if ($exPrev !== null && $exPrev !== '' && is_numeric($exPrev)) {
                        $extraNum = (float)$exPrev;
                    }
                }

                if ($autoNum !== null) {
                    if ($autoNum < 0 || $autoNum > 5) {
                        throw new Exception('La autoevaluación debe estar entre 0 y 5 puntos: ' . $estudiante['nombre']);
                    }
                }
                if ($extraNum !== null) {
                    if ($extraNum < 0 || $extraNum > 5) {
                        throw new Exception('El puntaje extra debe estar entre 0 y 5 puntos: ' . $estudiante['nombre']);
                    }
                }

                $prom95 = $promedioParcialesTrimestre($idEst);
                $promPart = $prom95 !== null ? $prom95 : 0.0;
                $totalTrim = $promPart + ($autoNum ?? 0) + ($extraNum ?? 0);
                if ($totalTrim > 100.00001) {
                    throw new Exception(
                        'La nota trimestral total no puede superar 100 puntos: ' . $estudiante['nombre']
                        . ' (promedio parciales ' . number_format($promPart, 2)
                        . ' + auto ' . number_format((float)($autoNum ?? 0), 2)
                        . ' + extra ' . number_format((float)($extraNum ?? 0), 2)
                        . ' = ' . number_format($totalTrim, 2) . ').'
                    );
                }

                $trimestralAGuardar[$idEst] = ['auto' => $autoNum, 'extra' => $extraNum];
            }

            foreach ($estudiantes as $estudiante) {
                $idEst = (int)$estudiante['id_estudiante'];
                $autoNum = $trimestralAGuardar[$idEst]['auto'];
                $extraNum = $trimestralAGuardar[$idEst]['extra'];

                if ($autoNum === null && $extraNum === null) {
                    $conn->prepare("DELETE FROM calificaciones_trimestrales
                                    WHERE id_estudiante = ? AND id_materia = ? AND gestion = ? AND trimestre = ?")
                         ->execute([$idEst, $curso['id_materia'], $gestionActual, $trimestreSeleccionado]);
                } else {
                    $conn->prepare("INSERT INTO calificaciones_trimestrales
                                    (id_estudiante, id_materia, gestion, trimestre, autoevaluacion, nota_extra, id_profesor)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE autoevaluacion = VALUES(autoevaluacion),
                                                            nota_extra = VALUES(nota_extra),
                                                            id_profesor = VALUES(id_profesor)")
                         ->execute([$idEst, $curso['id_materia'], $gestionActual, $trimestreSeleccionado, $autoNum, $extraNum, $profesor_id]);
                }
            }
        }

        if (!$es_inicial && $es_primaria_basica) {
            if ($es_materia_complementaria && $porcentajeTransferenciaComoComplementaria > 0) {
                aplicarBonusComplementario(
                    $conn,
                    (int)$curso['id_curso'],
                    $materiaPrincipalDesdeComplementariaId,
                    (int)$curso['id_materia'],
                    $gestionActual,
                    $trimestreSeleccionado,
                    $porcentajeTransferenciaComoComplementaria
                );
            }
            if ($es_materia_principal_complementada && $porcentajeTransferenciaPrincipal > 0) {
                aplicarBonusComplementario(
                    $conn,
                    (int)$curso['id_curso'],
                    (int)$curso['id_materia'],
                    $materiaComplementariaId,
                    $gestionActual,
                    $trimestreSeleccionado,
                    $porcentajeTransferenciaPrincipal
                );
            }
        }

        if ($conn->inTransaction()) {
            $conn->commit();
        }
        $redirectExtra = ['success' => 1, 'confirmar' => 1];
        if ($vistaActual === 'trimestral') {
            $redirectExtra['vista'] = 'trimestral';
        }

        header('Location: ' . construirUrlPeriodo($id_curso_materia, $trimestreSeleccionado, $parcialSeleccionado, $redirectExtra));
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
        if (strpos($error, 'no está habilitado') !== false) {
            $error .= ". Contacte al administrador del sistema.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>EduNote - Cargar Notas</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        html, body {
            height: 100%;
        }
        body {
            background: #f4f8fa;
            overflow: hidden;
        }
        .page-shell {
            height: 100vh;
            overflow: hidden;
        }
        .page-shell > .row {
            height: 100%;
        }
        .content-panel {
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            padding-top: 0.5rem;
            padding-bottom: 1.5rem;
            scroll-behavior: smooth;
        }
        .container-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 18px 20px;
            margin: 10px 0;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 0.75rem;
        }
        .page-header h3 {
            color: #11305e;
            font-weight: 700;
            margin: 0;
        }
        .page-header h4 {
            color: #4682B4;
            font-weight: 600;
            margin: 0;
        }
        .top-row {
            display: flex; gap: 0.75rem; margin-bottom: 0.75rem; align-items: stretch;
        }
        .intro-block {
            background: linear-gradient(135deg, #ffffff, #f8fbff);
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            flex: 0 0 220px;
            display: flex; flex-direction: column; justify-content: center;
        }
        .intro-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #11305e;
            margin-bottom: 0.3rem;
        }
        .intro-text {
            font-size: 0.8rem;
            color: #475569;
            margin: 0;
            line-height: 1.35;
        }
        .periodo-toolbar {
            background: #ffffff;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            flex: 1 1 0%;
            min-width: 0;
        }
        .periodo-toolbar-title {
            font-size: 0.82rem;
            font-weight: 700;
            color: #11305e;
            margin-bottom: 0.6rem;
        }
        .periodo-rows { display: flex; flex-direction: column; gap: 0.4rem; }
        .trim-row {
            display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;
        }
        .trim-label {
            font-size: 0.78rem; font-weight: 700; color: #11305e;
            min-width: 22px; text-align: center;
        }
        .pill-btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0.25rem 0.55rem; border-radius: 6px; font-size: 0.74rem;
            font-weight: 600; border: 1px solid #cbd5e1; background: #fff;
            color: #475569; cursor: pointer; transition: all 0.15s ease;
            text-decoration: none; line-height: 1.2;
        }
        .pill-btn:hover { border-color: #93c5fd; background: #f0f7ff; color: #1e40af; }
        .pill-btn.active { border-color: #2563eb; background: #dbeafe; color: #1e40af; font-weight: 700; }
        .pill-btn.pill-enabled { border-color: #86efac; }
        .pill-btn.pill-enabled::before {
            content: ''; display: inline-block; width: 5px; height: 5px;
            border-radius: 50%; background: #22c55e; margin-right: 4px;
        }
        .pill-btn.pill-disabled { border-color: #fca5a5; color: #94a3b8; }
        .pill-btn.pill-disabled::before {
            content: ''; display: inline-block; width: 5px; height: 5px;
            border-radius: 50%; background: #ef4444; margin-right: 4px;
        }
        .pill-sep {
            width: 1px; height: 18px; background: #e2e8f0; margin: 0 2px;
        }
        .pill-btn.pill-trim {
            background: #faf5ff; border-color: #c4b5fd; color: #7c3aed;
        }
        .pill-btn.pill-trim:hover { background: #ede9fe; border-color: #a78bfa; }
        .pill-btn.pill-trim.active { background: #ddd6fe; border-color: #8b5cf6; font-weight: 700; }
        .periodo-info {
            display: flex; flex-wrap: wrap; gap: 0.4rem;
            margin-top: 0.6rem; padding-top: 0.6rem;
            border-top: 1px solid #e5e7eb;
        }
        .periodo-badge {
            font-size: 0.78rem; padding: 0.25rem 0.55rem; font-weight: 600;
        }
        .status-badge-enabled {
            background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;
        }
        .status-badge-disabled {
            background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;
        }
        .form-select, .btn {
            border-radius: 8px;
        }
        .form-label {
            font-weight: 600;
            color: #475569;
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
        }
        .nota-input {
            width: 38px;
            height: 24px;
            padding: 1px 2px;
            margin: 0 auto;
            text-align: center;
            font-weight: 600;
            border-radius: 4px;
            font-size: 0.78rem;
            border: 1px solid #cbd5e1;
        }
        .nota-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59,130,246,.18);
            outline: none;
        }
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
            appearance: textfield;
        }
        .nota-disabled,
        .coment-disabled {
            background: #f1f5f9 !important;
            border-color: #e2e8f0 !important;
            color: #94a3b8 !important;
            cursor: not-allowed;
        }
        .periodo-inactivo-th {
            background: #f1f5f9 !important;
            color: #64748b !important;
            font-weight: 500;
        }
        .periodo-activo-th {
            background: #dbeafe !important;
            color: #1d4ed8 !important;
            font-weight: 700;
        }
        .coment-textarea {
            width: 100%;
            height: 100px;
            resize: none;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        /* ---- Nivel Inicial: cards ---- */
        .inicial-rules-bar{background:linear-gradient(135deg,#eff6ff,#f0f9ff);border:1px solid #bfdbfe;border-radius:10px;padding:10px 16px;font-size:.84rem;color:#1e40af;display:flex;align-items:center;flex-wrap:wrap;gap:6px 18px;margin-bottom:12px}
        .inicial-container{max-height:68vh;overflow-y:auto;padding-right:4px;margin-bottom:8px}
        .inicial-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;margin-bottom:10px;transition:box-shadow .2s,border-color .2s}
        .inicial-card:hover{box-shadow:0 2px 10px rgba(0,0,0,.06)}
        .inicial-card:focus-within{border-color:#93c5fd;box-shadow:0 0 0 3px rgba(59,130,246,.08)}
        .inicial-card-header{display:flex;align-items:center;margin-bottom:8px}
        .inicial-card-num{width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:#eff6ff;color:#2563eb;font-weight:700;font-size:.75rem;flex-shrink:0}
        .inicial-card-name{font-weight:600;font-size:.88rem;color:#1e293b;margin-left:10px;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .inicial-word-count{font-size:.72rem;font-weight:500;color:#94a3b8;flex-shrink:0;margin-left:8px}
        .inicial-word-count.warn{color:#f59e0b;font-weight:600}
        .inicial-word-count.over{color:#ef4444;font-weight:700}
        .inicial-textarea{width:100%;min-height:80px;max-height:200px;resize:vertical;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;font-size:.85rem;line-height:1.55;color:#334155;transition:border-color .2s,box-shadow .2s}
        .inicial-textarea:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12);outline:none}
        .inicial-textarea.coment-disabled{background:#f8fafc;color:#94a3b8;cursor:not-allowed}
        .inicial-dup-badge{display:none;font-size:.7rem;padding:2px 8px;border-radius:12px;background:#fef3c7;color:#92400e;font-weight:600;margin-left:8px}
        .inicial-dup-badge.visible{display:inline-block}
        .inicial-dup-badge.over-limit{background:#fee2e2;color:#991b1b}
        /* ---- Tabla ultra-compacta ---- */
        .table-container {
            max-height: 74vh;
            overflow: auto;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            margin-bottom: 14px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .table-container table {
            margin-bottom: 0;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        .table-container thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 4px 3px;
            text-align: center;
            vertical-align: middle;
            border-bottom: 2px solid #94a3b8;
        }
        .table-container thead tr:first-child th { top: 0; }
        .table-container thead tr:nth-child(2) th { top: 32px; }
        .table-container thead tr:nth-child(3) th { top: 124px; }
        .table-container thead th,
        .table-container tbody td {
            padding: 3px 2px;
            white-space: nowrap;
        }
        .table-container tbody td {
            vertical-align: middle;
            text-align: center;
        }
        .col-num {
            width: 28px; min-width: 28px; max-width: 28px;
            text-align: center; font-size: 0.72rem; color: #94a3b8;
        }
        .col-nombre {
            min-width: 140px; max-width: 180px;
            text-align: left !important;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            font-weight: 500; font-size: 0.76rem; padding-left: 6px !important;
        }
        .table-container tbody td:first-child,
        .table-container thead th:first-child {
            position: sticky; left: 0; background-color: #fff; z-index: 5;
        }
        .table-container tbody td:nth-child(2),
        .table-container thead th:nth-child(2) {
            position: sticky; left: 28px; background-color: #fff; z-index: 5;
            border-right: 2px solid #cbd5e1;
        }
        .table-container thead th:first-child,
        .table-container thead th:nth-child(2) {
            z-index: 15; background-color: #f1f5f9;
        }
        /* Bandas de color por area */
        .th-ser   { background: #dcfce7 !important; color: #166534 !important; }
        .th-saber { background: #dbeafe !important; color: #1e40af !important; }
        .th-hacer { background: #ffedd5 !important; color: #9a3412 !important; }
        .th-total { background: #f3e8ff !important; color: #6b21a8 !important; }
        .th-sub-ser   { background: #f0fdf4 !important; font-size: 0.7rem; }
        .th-sub-saber { background: #eff6ff !important; font-size: 0.7rem; }
        .th-sub-hacer { background: #fff7ed !important; font-size: 0.7rem; }
        .th-sub-total { background: #faf5ff !important; font-size: 0.7rem; font-weight: 700; }
        .th-etiqueta {
            vertical-align: bottom !important;
            padding: 3px 2px !important;
            max-width: 36px;
        }
        .actividad-etiqueta-input {
            display: block;
            width: 100%;
            max-width: 30px;
            min-height: 76px;
            max-height: 120px;
            margin: 0 auto;
            font-size: 0.62rem;
            line-height: 1.15;
            padding: 4px 2px;
            resize: vertical;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            text-orientation: mixed;
            white-space: pre-wrap;
            word-break: break-word;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
        }
        .th-etiqueta-ser .actividad-etiqueta-input { background: #f0fdf4; border-color: #bbf7d0; }
        .th-etiqueta-saber .actividad-etiqueta-input { background: #eff6ff; border-color: #bfdbfe; }
        .th-etiqueta-hacer .actividad-etiqueta-input { background: #fff7ed; border-color: #fed7aa; }
        .th-etiqueta-prom {
            background: #f8fafc !important;
            min-width: 40px;
        }
        .th-etiqueta-total {
            background: #faf5ff !important;
            min-width: 36px;
        }
        .paste-col-btn {
            border: none;
            background: transparent;
            color: #2563eb;
            padding: 0;
            margin-left: 4px;
            cursor: pointer;
            line-height: 1;
        }
        .paste-col-btn:hover,
        .paste-col-btn:focus {
            color: #1d4ed8;
            outline: none;
        }
        .paste-col-btn svg {
            pointer-events: none;
        }
        /* Celdas de promedio */
        .nota-ref {
            font-weight: 700; text-align: center; font-size: 0.76rem; min-width: 40px;
        }
        .ser-total   { background: #f0fdf4; color: #15803d; }
        .saber-total { background: #eff6ff; color: #1d4ed8; }
        .hacer-total { background: #fff7ed; color: #c2410c; }
        .total-95    { background: #faf5ff; color: #7c3aed; font-weight: 800; font-size: 0.82rem; }
        /* Filas zebra y hover */
        .table-container tbody tr:nth-child(even) td { background-color: #fafbfc; }
        .table-container tbody tr:nth-child(even) td:first-child,
        .table-container tbody tr:nth-child(even) td:nth-child(2) { background-color: #fafbfc; }
        .table-container tbody tr:hover td { background-color: #eef2ff !important; }
        body.sidebar-collapsed main.content-panel {
            flex: 0 0 calc(100% - 60px) !important;
            max-width: calc(100% - 60px) !important;
        }
        .helper-alert {
            background: #f8fbff;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            padding: 0.85rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #475569;
        }
        .helper-alert strong {
            color: #11305e;
        }
        .action-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid #e5e7eb;
        }
        .preview-card {
            background: linear-gradient(135deg, #ffffff, #f8fbff);
            border: 1px solid #dbeafe;
            border-radius: 12px;
            padding: 2.5rem;
            text-align: center;
            margin-top: 2rem;
        }
        .preview-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: #eff6ff;
            border-radius: 50%;
            margin-bottom: 1.5rem;
            color: #4682B4;
        }
        .preview-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #11305e;
            margin-bottom: 0.75rem;
        }
        .preview-text {
            font-size: 1rem;
            color: #475569;
            margin-bottom: 2rem;
        }
        .preview-status {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 600px;
            margin: 0 auto 2rem;
            text-align: left;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            color: #475569;
        }
        .status-item svg {
            flex-shrink: 0;
            color: #4682B4;
        }
        .status-item.status-success {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }
        .status-item.status-success svg {
            color: #16a34a;
        }
        .status-item.status-warning {
            background: #fef3c7;
            border-color: #fde68a;
        }
        .status-item.status-warning svg {
            color: #d97706;
        }
        .preview-action {
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        .preview-hint {
            font-size: 0.95rem;
            color: #64748b;
            margin: 0;
        }
        @media (max-width: 768px) {
            body {
                overflow: auto;
            }
            .page-shell {
                height: auto;
                overflow: visible;
            }
            .page-shell > .row {
                height: auto;
            }
            .content-panel {
                height: auto;
                overflow: visible;
                padding-top: 0;
                padding-bottom: 1rem;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .preview-card {
                padding: 1.5rem;
            }
            .preview-icon {
                width: 64px;
                height: 64px;
            }
            .top-row {
                flex-direction: column;
            }
            .intro-block {
                flex: none;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid page-shell">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-panel">
                <div class="container-card mt-4">
                    <div class="page-header">
                        <h3><?php echo $curso['curso_nombre']; ?></h3>
                        <h4><?php echo $curso['nombre_materia']; ?></h4>
                    </div>

                    <?php if (isset($error)): ?>
                            <div class="alert alert-danger mb-2"><?php echo $error; ?></div>
                        <?php elseif (isset($_GET['success'])): ?>
                            <div class="alert alert-success mb-2">¡Notas cargadas correctamente!</div>
                        <?php endif; ?>
                        <div class="top-row">
                            <div class="intro-block">
                                <div class="intro-title"><?php echo $modalidadCarga === 'trimestres' ? 'Carga por trimestre' : 'Carga por parcial'; ?></div>
                                <p class="intro-text">
                                    <?php if ($modalidadCarga === 'trimestres'): ?>
                                        Selecciona el trimestre, verifica que esté habilitado y carga las notas.
                                    <?php else: ?>
                                        Selecciona trimestre y parcial, verifica que esté habilitado y carga las notas.
                                    <?php endif; ?>
                                    <?php if (!$es_inicial && $es_materia_principal_complementada): ?>
                                    <br><strong>Bonus automático:</strong> Hasta <?php echo $porcentajeTransferenciaPrincipal; ?> puntos provienen de Inglés. Los docentes no editan ese valor aquí.
                                    <?php elseif (!$es_inicial && $es_materia_complementaria): ?>
                                    <br><strong>Importante:</strong> La nota final de esta materia transfiere hasta <?php echo $porcentajeTransferenciaComoComplementaria; ?> puntos a Lenguaje.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="periodo-toolbar">
                                <div class="periodo-toolbar-title">Selección de periodo</div>
                                <div class="periodo-rows">
                                    <?php foreach ($periodosPorTrimestre as $trimestre => $parciales): ?>
                                        <div class="trim-row">
                                            <span class="trim-label">T<?php echo (int)$trimestre; ?></span>
                                            <?php if ($es_inicial): ?>
                                                <?php
                                                $primerParcialIni = array_key_first($parciales);
                                                $periodoBotonIni = $parciales[$primerParcialIni];
                                                $periodoBotonEditableIni = (int)$periodoBotonIni['esta_activo'] === 1 &&
                                                    (empty($periodoBotonIni['fecha_inicio']) || $hoy >= $periodoBotonIni['fecha_inicio']) &&
                                                    (empty($periodoBotonIni['fecha_fin']) || $hoy <= $periodoBotonIni['fecha_fin']);
                                                $esTrimActualIni = (int)$trimestre === (int)$trimestreSeleccionado && $periodoConfirmado;
                                                $pillClassesIni = 'pill-btn' . ($esTrimActualIni ? ' active' : '') . ($periodoBotonEditableIni ? ' pill-enabled' : ' pill-disabled');
                                                ?>
                                                <a href="<?php echo htmlspecialchars(construirUrlPeriodo($id_curso_materia, (int)$trimestre, (int)$primerParcialIni, ['confirmar' => 1])); ?>"
                                                   class="<?php echo $pillClassesIni; ?>"
                                                   title="Trimestre <?php echo (int)$trimestre; ?> — Comentario">
                                                    Trimestre <?php echo (int)$trimestre; ?>
                                                </a>
                                            <?php else: ?>
                                                <?php foreach ($parciales as $parcial => $periodoBoton): ?>
                                                    <?php
                                                    $periodoBotonEditable = (int)$periodoBoton['esta_activo'] === 1 &&
                                                        (empty($periodoBoton['fecha_inicio']) || $hoy >= $periodoBoton['fecha_inicio']) &&
                                                        (empty($periodoBoton['fecha_fin']) || $hoy <= $periodoBoton['fecha_fin']);
                                                    $esPeriodoActual = $vistaActual === 'parcial' && (int)$trimestre === (int)$trimestreSeleccionado && (int)$parcial === (int)$parcialSeleccionado && $periodoConfirmado;
                                                    $pillClasses = 'pill-btn' . ($esPeriodoActual ? ' active' : '') . ($periodoBotonEditable ? ' pill-enabled' : ' pill-disabled');
                                                    ?>
                                                    <a href="<?php echo htmlspecialchars(construirUrlPeriodo($id_curso_materia, (int)$trimestre, (int)$parcial, ['confirmar' => 1])); ?>"
                                                       class="<?php echo $pillClasses; ?>"
                                                       title="<?php echo htmlspecialchars($periodoBoton['nombre']); ?>">
                                                        P<?php echo (int)$parcial; ?>
                                                    </a>
                                                <?php endforeach; ?>
                                                <div class="pill-sep"></div>
                                                <?php
                                                $esTrimActual = $vistaActual === 'trimestral' && (int)$trimestre === (int)$trimestreSeleccionado && $periodoConfirmado;
                                                $primerParcialTrim = array_key_first($parciales);
                                                ?>
                                                <a href="<?php echo htmlspecialchars(construirUrlPeriodo($id_curso_materia, (int)$trimestre, (int)$primerParcialTrim, ['confirmar' => 1, 'vista' => 'trimestral'])); ?>"
                                                   class="pill-btn pill-trim<?php echo $esTrimActual ? ' active' : ''; ?>"
                                                   title="Vista trimestral: autoevaluación y nota extra">
                                                    Trim
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="periodo-info">
                                    <?php if ($vistaActual === 'trimestral'): ?>
                                        <span class="badge periodo-badge <?php echo $trimestreEditableParaVistaTrimestral ? 'status-badge-enabled' : 'status-badge-disabled'; ?>">
                                            <?php echo $trimestreEditableParaVistaTrimestral ? '✓ Habilitado' : '✗ No habilitado'; ?>
                                        </span>
                                        <span class="badge bg-light text-dark border periodo-badge">Vista trimestral — T<?php echo $trimestreSeleccionado; ?></span>
                                    <?php else: ?>
                                        <span class="badge periodo-badge <?php echo $periodoEditable ? 'status-badge-enabled' : 'status-badge-disabled'; ?>">
                                            <?php echo $periodoEditable ? '✓ Habilitado' : '✗ No habilitado'; ?>
                                        </span>
                                        <span class="badge bg-light text-dark border periodo-badge">
                                            T<?php echo $trimestreSeleccionado; ?><?php echo !$es_inicial ? ' - P' . $parcialSeleccionado : ''; ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge bg-light text-dark border periodo-badge">
                                        Gestión <?php echo htmlspecialchars($gestionActual); ?>
                                    </span>
                                    <?php if (!$es_inicial && $es_materia_principal_complementada && $periodoEditable): ?>
                                        <span class="badge bg-warning text-dark border" style="font-size:0.75rem;">
                                            Bonus en sincronización…
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    <?php if ($periodoConfirmado): ?>
                        <?php if ($vistaActual === 'trimestral' && !$es_inicial): ?>
                            <form method="post">
                                <input type="hidden" name="trimestre" value="<?php echo $trimestreSeleccionado; ?>">
                                <input type="hidden" name="parcial" value="<?php echo $parcialSeleccionado; ?>">
                                <input type="hidden" name="vista" value="trimestral">

                                <?php if (!$trimestreEditableParaVistaTrimestral): ?>
                                    <div class="alert alert-warning">
                                        <strong>Modo consulta:</strong> Ningún parcial de este trimestre está habilitado.
                                    </div>
                                <?php endif; ?>
                                <div class="table-container">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="col-num">#</th>
                                                <th class="col-nombre">Estudiante</th>
                                                <th class="th-sub-total" style="min-width:50px">P1</th>
                                                <th class="th-sub-total" style="min-width:50px">P2</th>
                                                <th class="th-sub-total" style="min-width:50px">P3</th>
                                                <th class="th-total" style="min-width:55px">Promedio</th>
                                                <th style="background:#fef3c7!important;color:#92400e!important;min-width:55px">Auto (5)</th>
                                                <?php if ($es_materia_principal_complementada): ?>
                                                <th style="background:#e0e7ff!important;color:#3730a3!important;min-width:55px">Bonus Inglés (<?php echo $porcentajeTransferenciaPrincipal; ?>)</th>
                                                <?php else: ?>
                                                <th style="background:#e0e7ff!important;color:#3730a3!important;min-width:55px">Extra</th>
                                                <?php endif; ?>
                                                <th class="th-total" style="min-width:55px">TOTAL</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $contador = 1; ?>
                                            <?php foreach ($estudiantes as $est): ?>
                                                <?php
                                                $idEst = (int)$est['id_estudiante'];
                                                $trimData = $notasTrimestrales[$idEst][$trimestreSeleccionado] ?? [];
                                                $autoVal = $trimData['autoevaluacion'] ?? '';
                                                $extraVal = $trimData['nota_extra'] ?? '';
                                                $parciales95 = [];
                                                for ($px = 1; $px <= 3; $px++) {
                                                    $parciales95[$px] = isset($notas[$idEst][$trimestreSeleccionado][$px]) && is_numeric($notas[$idEst][$trimestreSeleccionado][$px])
                                                        ? (float)$notas[$idEst][$trimestreSeleccionado][$px] : null;
                                                }

                                                $vals95 = array_filter($parciales95, fn($v) => $v !== null);
                                                $prom95 = count($vals95) ? array_sum($vals95) / count($vals95) : null;
                                                $autoNum = ($autoVal !== '' && $autoVal !== null) ? (float)$autoVal : null;
                                                $extraNum = ($extraVal !== '' && $extraVal !== null) ? (float)$extraVal : null;
                                                $totalFinal = ($prom95 !== null ? $prom95 : 0) + ($autoNum ?? 0) + ($extraNum ?? 0);
                                                ?>
                                                <tr>
                                                    <td class="col-num"><?php echo $contador++; ?></td>
                                                    <td class="col-nombre" title="<?php echo htmlspecialchars($est['nombre']); ?>"><?php echo htmlspecialchars($est['nombre']); ?></td>
                                                    <?php for ($px = 1; $px <= 3; $px++): ?>
                                                        <td class="nota-ref"><?php echo $parciales95[$px] !== null ? number_format($parciales95[$px], 2) : '--'; ?></td>
                                                    <?php endfor; ?>
                                                    <td class="nota-ref total-95"><?php echo $prom95 !== null ? number_format($prom95, 2) : '--'; ?></td>
                                                    <td>
                                                        <input type="number" name="auto[<?php echo $idEst; ?>]"
                                                               class="form-control nota-input area-auto <?php echo !$trimestreEditableParaVistaTrimestral ? 'nota-disabled' : ''; ?>"
                                                               value="<?php echo htmlspecialchars($autoVal === null ? '' : $autoVal); ?>"
                                                               step="0.01" min="0" max="5"
                                                               <?php echo !$trimestreEditableParaVistaTrimestral ? 'readonly disabled' : ''; ?>>
                                                    </td>
                                                    <td>
                                                        <?php if ($es_materia_principal_complementada): ?>
                                                            <div class="form-control nota-input nota-disabled text-center" style="width:auto;min-width:55px;">
                                                                <?php echo $extraVal !== null && $extraVal !== '' ? number_format((float)$extraVal, 2) : '0.00'; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <input type="number" name="extra[<?php echo $idEst; ?>]"
                                                                   class="form-control nota-input area-extra <?php echo !$trimestreEditableParaVistaTrimestral ? 'nota-disabled' : ''; ?>"
                                                                   value="<?php echo htmlspecialchars($extraVal === null ? '' : $extraVal); ?>"
                                                                   step="0.01" min="0" max="5"
                                                                   <?php echo !$trimestreEditableParaVistaTrimestral ? 'readonly disabled' : ''; ?>>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="nota-ref total-final" data-prom95="<?php echo $prom95 !== null ? number_format($prom95, 2) : '0'; ?>" data-bonus="<?php echo $extraNum !== null ? number_format($extraNum, 2) : '0'; ?>">
                                                        <?php echo number_format($totalFinal, 2); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="action-buttons">
                                    <a href="dashboard.php" class="btn btn-outline-secondary">← Volver al panel</a>
                                    <div class="d-flex gap-2">
                                        <a href="exportar_registro.php?curso_materia=<?php echo $id_curso_materia; ?>&trimestre=<?php echo $trimestreSeleccionado; ?>"
                                           class="btn btn-outline-primary px-3" title="Registro: desglose por parcial + resumen trimestral">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><path d="M14 2H6a2 2 0 012 2v16a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>Registro
                                        </a>
                                        <a href="exportar_notas_excel.php?curso_materia=<?php echo $id_curso_materia; ?>"
                                           class="btn btn-outline-success px-3" title="Exportar todas las notas a Excel">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Excel Todo
                                        </a>
                                        <button type="submit" name="guardar_trimestral" class="btn btn-primary px-4" <?php echo !$trimestreEditableParaVistaTrimestral ? 'disabled' : ''; ?>>
                                            <?php echo $trimestreEditableParaVistaTrimestral ? 'Guardar trimestral' : 'No disponible'; ?>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="trimestre" value="<?php echo $trimestreSeleccionado; ?>">
                                <input type="hidden" name="parcial" value="<?php echo $parcialSeleccionado; ?>">
                                <?php if ($es_inicial): ?>
                                <div class="inicial-rules-bar">
                                    <span>&#128203; Máximo <strong>250 caracteres</strong> por comentario</span>
                                    <span>&#128260; Un mismo comentario puede repetirse en máximo <strong>3 estudiantes</strong></span>
                                </div>
                                <?php if (!$periodoEditable): ?>
                                    <div class="alert alert-warning mb-2">
                                        <strong>Modo consulta:</strong> Este periodo no está habilitado para edición.
                                    </div>
                                <?php endif; ?>
                                <div class="inicial-container" id="inicialContainer">
                                    <?php $contadorIni = 1; ?>
                                    <?php foreach ($estudiantes as $estIni): ?>
                                        <?php
                                        $idEstIni = (int)$estIni['id_estudiante'];
                                        $notaIni = $notas[$idEstIni][$trimestreSeleccionado][$parcialSeleccionado] ?? '';
                                        ?>
                                        <div class="inicial-card">
                                            <div class="inicial-card-header">
                                                <span class="inicial-card-num"><?php echo $contadorIni++; ?></span>
                                                <span class="inicial-card-name" title="<?php echo htmlspecialchars($estIni['nombre']); ?>"><?php echo htmlspecialchars($estIni['nombre']); ?></span>
                                                <span class="inicial-dup-badge" id="dup-<?php echo $idEstIni; ?>"></span>
                                                <span class="inicial-word-count" id="wc-<?php echo $idEstIni; ?>">0 / 250 car.</span>
                                            </div>
                                            <textarea
                                                name="notas[<?php echo $idEstIni; ?>]"
                                                class="inicial-textarea <?php echo !$periodoEditable ? 'coment-disabled' : ''; ?>"
                                                data-student-id="<?php echo $idEstIni; ?>"
                                                placeholder="<?php echo $periodoEditable ? 'Escribe el comentario (máx. 250 caracteres)...' : 'No habilitado'; ?>"
                                                maxlength="250"
                                                <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>
                                            ><?php echo htmlspecialchars($notaIni); ?></textarea>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="action-buttons">
                                    <a href="dashboard.php" class="btn btn-outline-secondary">&larr; Volver al panel</a>
                                    <div class="d-flex gap-2 align-items-center">
                                        <span id="inicialDupSummary" class="text-muted" style="font-size:0.82rem;"></span>
                                        <button type="submit" name="guardar_notas" class="btn btn-primary px-4" id="btnGuardarComentarios" <?php echo !$periodoEditable ? 'disabled' : ''; ?>>
                                            <?php echo $periodoEditable ? 'Guardar comentarios' : 'No disponible'; ?>
                                        </button>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="helper-alert">
                                    <strong>Importante:</strong> Verifica que el orden de estudiantes coincida con tu lista antes de cargar notas.
                                    <span class="d-block mt-1" style="font-size:0.85rem;">Rangos por casilla: <strong>SER</strong> 0–10, <strong>SABER</strong> 0–45, <strong>HACER</strong> 0–40. Puedes guardar solo algunos alumnos o todos; las áreas que dejes vacías en el formulario conservan el valor ya guardado en el periodo (si existía). En la fila superior de cada columna puedes escribir el nombre de la actividad (texto vertical); si la dejas vacía se muestra «Actividad N».</span>
                                </div>
                                <?php if (!$periodoEditable): ?>
                                    <div class="alert alert-warning">
                                        <strong>Modo consulta:</strong> Este periodo no está habilitado para edición. Contacta al administrador si necesitas cargar o modificar notas.
                                    </div>
                                <?php endif; ?>
                                <div class="table-container">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="col-num"<?php echo !$es_inicial ? ' rowspan="3"' : ''; ?>>#</th>
                                                <th class="col-nombre"<?php echo !$es_inicial ? ' rowspan="3"' : ''; ?>>Estudiante</th>
                                                <?php if ($es_inicial): ?>
                                                    <th class="text-center <?php echo $periodoEditable ? 'periodo-activo-th' : 'periodo-inactivo-th'; ?>">
                                                        <?php echo $modalidadCarga === 'trimestres' ? 'Comentario trimestral' : 'Comentario parcial'; ?>
                                                    </th>
                                                <?php else: ?>
                                                    <th class="th-ser" colspan="5">SER (10)</th>
                                                    <th class="th-saber" colspan="9">SABER (45)</th>
                                                    <th class="th-hacer" colspan="9">HACER (40)</th>
                                                    <th class="th-total">TOTAL</th>
                                                <?php endif; ?>
                                            </tr>
                                            <?php if (!$es_inicial): ?>
                                            <tr>
                                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                                    <?php
                                                    $etSer = trim((string)($etiquetasActividades['SER'][$i] ?? ''));
                                                    $phSer = 'Actividad ' . $i;
                                                    ?>
                                                    <th class="th-etiqueta th-etiqueta-ser">
                                                        <label class="visually-hidden" for="eti_ser_<?php echo $i; ?>">Nombre actividad SER <?php echo $i; ?></label>
                                                        <textarea id="eti_ser_<?php echo $i; ?>"
                                                                  name="etiquetas_actividades[SER][<?php echo $i; ?>]"
                                                                  class="form-control actividad-etiqueta-input"
                                                                  maxlength="120"
                                                                  rows="4"
                                                                  autocomplete="off"
                                                                  placeholder="<?php echo htmlspecialchars($phSer); ?>"
                                                                  <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>><?php echo htmlspecialchars($etSer); ?></textarea>
                                                    </th>
                                                <?php endfor; ?>
                                                <th class="th-etiqueta th-etiqueta-prom th-etiqueta-ser"></th>
                                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                                    <?php
                                                    $etSab = trim((string)($etiquetasActividades['SABER'][$i] ?? ''));
                                                    $phSab = 'Actividad ' . $i;
                                                    ?>
                                                    <th class="th-etiqueta th-etiqueta-saber">
                                                        <label class="visually-hidden" for="eti_saber_<?php echo $i; ?>">Nombre actividad SABER <?php echo $i; ?></label>
                                                        <textarea id="eti_saber_<?php echo $i; ?>"
                                                                  name="etiquetas_actividades[SABER][<?php echo $i; ?>]"
                                                                  class="form-control actividad-etiqueta-input"
                                                                  maxlength="120"
                                                                  rows="4"
                                                                  autocomplete="off"
                                                                  placeholder="<?php echo htmlspecialchars($phSab); ?>"
                                                                  <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>><?php echo htmlspecialchars($etSab); ?></textarea>
                                                    </th>
                                                <?php endfor; ?>
                                                <th class="th-etiqueta th-etiqueta-prom th-etiqueta-saber"></th>
                                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                                    <?php
                                                    $etHac = trim((string)($etiquetasActividades['HACER'][$i] ?? ''));
                                                    $phHac = 'Actividad ' . $i;
                                                    ?>
                                                    <th class="th-etiqueta th-etiqueta-hacer">
                                                        <label class="visually-hidden" for="eti_hacer_<?php echo $i; ?>">Nombre actividad HACER <?php echo $i; ?></label>
                                                        <textarea id="eti_hacer_<?php echo $i; ?>"
                                                                  name="etiquetas_actividades[HACER][<?php echo $i; ?>]"
                                                                  class="form-control actividad-etiqueta-input"
                                                                  maxlength="120"
                                                                  rows="4"
                                                                  autocomplete="off"
                                                                  placeholder="<?php echo htmlspecialchars($phHac); ?>"
                                                                  <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>><?php echo htmlspecialchars($etHac); ?></textarea>
                                                    </th>
                                                <?php endfor; ?>
                                                <th class="th-etiqueta th-etiqueta-prom th-etiqueta-hacer"></th>
                                                <th class="th-etiqueta th-etiqueta-total"></th>
                                            </tr>
                                            <tr>
                                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                                    <th class="th-sub-ser">
                                                        <?php echo $i; ?>
                                                        <?php if ($periodoEditable): ?>
                                                            <button type="button"
                                                                    class="paste-col-btn btn-paste-column"
                                                                    data-area="SER"
                                                                    data-index="<?php echo $i; ?>"
                                                                    data-min="0"
                                                                    data-max="10"
                                                                    title="Pegar notas SER <?php echo $i; ?>">
                                                                <span class="visually-hidden">Pegar notas SER columna <?php echo $i; ?></span>
                                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <rect x="7" y="3" width="10" height="14" rx="2" ry="2"></rect>
                                                                    <path d="M9 3V2a1 1 0 011-1h4a1 1 0 011 1v1"></path>
                                                                    <path d="M11 7h4"></path>
                                                                </svg>
                                                            </button>
                                                        <?php endif; ?>
                                                    </th>
                                                <?php endfor; ?>
                                                <th class="th-sub-ser" style="font-weight:700">Prom</th>
                                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                                    <th class="th-sub-saber">
                                                        <?php echo $i; ?>
                                                        <?php if ($periodoEditable): ?>
                                                            <button type="button"
                                                                    class="paste-col-btn btn-paste-column"
                                                                    data-area="SABER"
                                                                    data-index="<?php echo $i; ?>"
                                                                    data-min="0"
                                                                    data-max="45"
                                                                    title="Pegar notas SABER <?php echo $i; ?>">
                                                                <span class="visually-hidden">Pegar notas SABER columna <?php echo $i; ?></span>
                                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <rect x="7" y="3" width="10" height="14" rx="2" ry="2"></rect>
                                                                    <path d="M9 3V2a1 1 0 011-1h4a1 1 0 011 1v1"></path>
                                                                    <path d="M11 7h4"></path>
                                                                </svg>
                                                            </button>
                                                        <?php endif; ?>
                                                    </th>
                                                <?php endfor; ?>
                                                <th class="th-sub-saber" style="font-weight:700">Prom</th>
                                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                                    <th class="th-sub-hacer">
                                                        <?php echo $i; ?>
                                                        <?php if ($periodoEditable): ?>
                                                            <button type="button"
                                                                    class="paste-col-btn btn-paste-column"
                                                                    data-area="HACER"
                                                                    data-index="<?php echo $i; ?>"
                                                                    data-min="0"
                                                                    data-max="40"
                                                                    title="Pegar notas HACER <?php echo $i; ?>">
                                                                <span class="visually-hidden">Pegar notas HACER columna <?php echo $i; ?></span>
                                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <rect x="7" y="3" width="10" height="14" rx="2" ry="2"></rect>
                                                                    <path d="M9 3V2a1 1 0 011-1h4a1 1 0 011 1v1"></path>
                                                                    <path d="M11 7h4"></path>
                                                                </svg>
                                                            </button>
                                                        <?php endif; ?>
                                                    </th>
                                                <?php endfor; ?>
                                                <th class="th-sub-hacer" style="font-weight:700">Prom</th>
                                                <th class="th-sub-total" title="Suma de promedios (máx. 95)">95</th>
                                            </tr>
                                            <?php endif; ?>
                                            <?php $contador = 1; ?>
                                            <?php foreach ($estudiantes as $est): ?>
                                                <?php
                                                $idEstudianteFila = (int)$est['id_estudiante'];
                                                $notaActual = $notas[$idEstudianteFila] ?? '';
                                                ?>
                                                <?php if ($es_inicial): ?>
                                                    <tr>
                                                        <td class="col-num"><?php echo $contador++; ?></td>
                                                        <td class="col-nombre" title="<?php echo htmlspecialchars($est['nombre']); ?>"><?php echo htmlspecialchars($est['nombre']); ?></td>
                                                        <td>
                                                            <textarea
                                                                name="notas[<?php echo $idEstudianteFila; ?>]"
                                                                class="coment-textarea <?php echo !$periodoEditable ? 'coment-disabled' : ''; ?>"
                                                                placeholder="<?php echo $periodoEditable ? ($modalidadCarga === 'trimestres' ? 'Comentario trimestral' : 'Comentario parcial ' . $parcialSeleccionado) : 'No habilitado'; ?>"
                                                                <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>
                                                            ><?php echo htmlspecialchars($notaActual); ?></textarea>
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <tr>
                                                        <td class="col-num"><?php echo $contador++; ?></td>
                                                        <td class="col-nombre" title="<?php echo htmlspecialchars($est['nombre']); ?>"><?php echo htmlspecialchars($est['nombre']); ?></td>
                                                        <?php
                                                        $detalleFila = $detalleNotas[$idEstudianteFila] ?? [];
                                                        $totalesFila = $totalesAreasPorEstudiante[$idEstudianteFila] ?? ['ser_total' => 0, 'saber_total' => 0, 'hacer_total' => 0, 'calificacion' => 0];
                                                        ?>
                                                        <?php for ($i = 1; $i <= 4; $i++): ?>
                                                            <?php $valor = $detalleFila['SER'][$i] ?? ''; ?>
                                                            <td>
                                                                <input type="number"
                                                                       name="notas[<?php echo $idEstudianteFila; ?>][SER][<?php echo $i; ?>]"
                                                                       class="form-control nota-input area-ser <?php echo !$periodoEditable ? 'nota-disabled' : ''; ?>"
                                                                       data-area="SER"
                                                                       data-index="<?php echo $i; ?>"
                                                                       value="<?php echo htmlspecialchars($valor === null ? '' : $valor); ?>"
                                                                       step="0.01" min="0" max="10" title="SER: 0 a 10"
                                                                       <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>
                                                                >
                                                            </td>
                                                        <?php endfor; ?>
                                                        <td class="nota-ref ser-total" data-valor="<?php echo htmlspecialchars($totalesFila['ser_total']); ?>">
                                                            <?php echo number_format((float)$totalesFila['ser_total'], 2); ?>
                                                        </td>
                                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                                            <?php $valor = $detalleFila['SABER'][$i] ?? ''; ?>
                                                            <td>
                                                                <input type="number"
                                                                       name="notas[<?php echo $idEstudianteFila; ?>][SABER][<?php echo $i; ?>]"
                                                                       class="form-control nota-input area-saber <?php echo !$periodoEditable ? 'nota-disabled' : ''; ?>"
                                                                       data-area="SABER"
                                                                       data-index="<?php echo $i; ?>"
                                                                       value="<?php echo htmlspecialchars($valor === null ? '' : $valor); ?>"
                                                                       step="0.01" min="0" max="45" title="SABER: 0 a 45"
                                                                       <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>
                                                                >
                                                            </td>
                                                        <?php endfor; ?>
                                                        <td class="nota-ref saber-total" data-valor="<?php echo htmlspecialchars($totalesFila['saber_total']); ?>">
                                                            <?php echo number_format((float)$totalesFila['saber_total'], 2); ?>
                                                        </td>
                                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                                            <?php $valor = $detalleFila['HACER'][$i] ?? ''; ?>
                                                            <td>
                                                                <input type="number"
                                                                       name="notas[<?php echo $idEstudianteFila; ?>][HACER][<?php echo $i; ?>]"
                                                                       class="form-control nota-input area-hacer <?php echo !$periodoEditable ? 'nota-disabled' : ''; ?>"
                                                                       data-area="HACER"
                                                                       data-index="<?php echo $i; ?>"
                                                                       value="<?php echo htmlspecialchars($valor === null ? '' : $valor); ?>"
                                                                       step="0.01" min="0" max="40" title="HACER: 0 a 40"
                                                                       <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>
                                                                >
                                                            </td>
                                                        <?php endfor; ?>
                                                        <td class="nota-ref hacer-total" data-valor="<?php echo htmlspecialchars($totalesFila['hacer_total']); ?>">
                                                            <?php echo number_format((float)$totalesFila['hacer_total'], 2); ?>
                                                        </td>
                                                        <td class="nota-ref total-95" data-valor="<?php echo htmlspecialchars($totalesFila['calificacion']); ?>">
                                                            <?php echo number_format((float)$totalesFila['calificacion'], 2); ?>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="action-buttons">
                                    <a href="dashboard.php" class="btn btn-outline-secondary">← Volver al panel</a>
                                    <button type="submit" name="guardar_notas" class="btn btn-primary px-4" <?php echo !$periodoEditable ? 'disabled' : ''; ?>>
                                        <?php echo $periodoEditable ? 'Guardar notas' : 'No disponible'; ?>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="preview-card">
                            <div class="preview-icon">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 11l3 3L22 4"></path>
                                    <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                                </svg>
                            </div>
                            <h5 class="preview-title">Periodo seleccionado</h5>
                            <p class="preview-text">
                                Has seleccionado <strong><?php echo $modalidadCarga === 'trimestres' ? 'Trimestre ' . $trimestreSeleccionado : 'Trimestre ' . $trimestreSeleccionado . ' - Parcial ' . $parcialSeleccionado; ?></strong>.
                            </p>
                            <div class="preview-status">
                                <?php if ($periodoEditable): ?>
                                    <div class="status-item status-success">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                        <span>Periodo habilitado para carga de notas</span>
                                    </div>
                                    <div class="status-item">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        <span>
                                            <?php if ($periodoSeleccionado['fecha_inicio'] && $periodoSeleccionado['fecha_fin']): ?>
                                                Desde <?php echo date('d/m/Y', strtotime($periodoSeleccionado['fecha_inicio'])); ?>
                                                hasta <?php echo date('d/m/Y', strtotime($periodoSeleccionado['fecha_fin'])); ?>
                                            <?php else: ?>
                                                Sin rango de fechas definido
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="status-item">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M23 21v-2a4 4 0 00-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 010 7.75"></path>
                                        </svg>
                                        <span><?php echo count($estudiantes); ?> estudiantes en este curso</span>
                                    </div>
                                <?php else: ?>
                                    <div class="status-item status-warning">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="12" y1="8" x2="12" y2="12"></line>
                                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                        </svg>
                                        <span>Este periodo no está habilitado para edición</span>
                                    </div>
                                    <div class="status-item">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        <span>Podrás consultar las notas en modo solo lectura</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="preview-action">
                                <p class="preview-hint">Haz clic en "Cargar periodo" arriba para <?php echo $periodoEditable ? 'comenzar a cargar notas' : 'ver las notas cargadas'; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <?php if (!$es_inicial && $periodoEditable): ?>
    <div class="modal fade" id="pasteColumnModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="pasteColumnForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Pegar notas en <span id="pasteColumnTarget">—</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2" style="font-size:0.9rem; color:#475569;">Pega la columna copiada desde Excel (una nota por fila). Las notas se asignarán en el orden actual de los estudiantes.</p>
                        <textarea id="pasteColumnTextarea" class="form-control" rows="10" placeholder="Pega aquí las notas..." required></textarea>
                        <input type="hidden" id="pasteColumnArea">
                        <input type="hidden" id="pasteColumnIndex">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Pegar notas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        function parseNumber(value) {
            if (value === null || value === undefined) return null;
            const v = String(value).trim().replace(',', '.');
            if (v === '') return null;
            const n = Number(v);
            return Number.isFinite(n) ? n : null;
        }

        function avg(values) {
            const nums = values.filter(v => v !== null && Number.isFinite(v));
            if (!nums.length) return null;
            return nums.reduce((a, b) => a + b, 0) / nums.length;
        }

        function clampNotaInput(input, min, max) {
            if (input.readOnly || input.disabled) return;
            const n = parseNumber(input.value);
            if (n === null) return;
            let v = n;
            if (v < min) v = min;
            if (v > max) v = max;
            if (v !== n) input.value = String(v);
        }

        function updateRowTotals(row) {
            const serInputs = Array.from(row.querySelectorAll('input.area-ser'));
            const saberInputs = Array.from(row.querySelectorAll('input.area-saber'));
            const hacerInputs = Array.from(row.querySelectorAll('input.area-hacer'));

            if (!serInputs.length && !saberInputs.length && !hacerInputs.length) return;

            const serAvg = avg(serInputs.map(i => parseNumber(i.value)));
            const saberAvg = avg(saberInputs.map(i => parseNumber(i.value)));
            const hacerAvg = avg(hacerInputs.map(i => parseNumber(i.value)));

            const serProm = serAvg === null ? 0 : +serAvg.toFixed(2);
            const saberProm = saberAvg === null ? 0 : +saberAvg.toFixed(2);
            const hacerProm = hacerAvg === null ? 0 : +hacerAvg.toFixed(2);
            const total95 = +(serProm + saberProm + hacerProm).toFixed(2);

            const serCell = row.querySelector('.ser-total');
            const saberCell = row.querySelector('.saber-total');
            const hacerCell = row.querySelector('.hacer-total');
            const totalCell = row.querySelector('.total-95');

            if (serCell) serCell.textContent = serProm.toFixed(2);
            if (saberCell) saberCell.textContent = saberProm.toFixed(2);
            if (hacerCell) hacerCell.textContent = hacerProm.toFixed(2);
            if (totalCell) totalCell.textContent = total95.toFixed(2);
        }

        function updateTrimestralRow(row) {
            const autoInput = row.querySelector('input.area-auto');
            const extraInput = row.querySelector('input.area-extra');
            const totalCell = row.querySelector('.total-final');
            if (!totalCell) return;
            const prom95 = parseFloat(totalCell.dataset.prom95) || 0;
            const autoVal = autoInput ? (parseNumber(autoInput.value) ?? 0) : 0;
            let extraVal = 0;
            if (extraInput) {
                extraVal = parseNumber(extraInput.value) ?? 0;
                totalCell.dataset.bonus = extraVal.toFixed(2);
            } else if (totalCell.dataset.bonus !== undefined) {
                extraVal = parseNumber(totalCell.dataset.bonus) ?? 0;
            }
            totalCell.textContent = (prom95 + autoVal + extraVal).toFixed(2);
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('tbody tr').forEach(tr => {
                updateRowTotals(tr);
                updateTrimestralRow(tr);
            });

            document.querySelectorAll('input.area-ser, input.area-saber, input.area-hacer').forEach(input => {
                input.addEventListener('input', function() {
                    updateRowTotals(this.closest('tr'));
                });
                input.addEventListener('blur', function() {
                    if (this.classList.contains('area-ser')) clampNotaInput(this, 0, 10);
                    else if (this.classList.contains('area-saber')) clampNotaInput(this, 0, 45);
                    else if (this.classList.contains('area-hacer')) clampNotaInput(this, 0, 40);
                    updateRowTotals(this.closest('tr'));
                });
            });
            document.querySelectorAll('input.area-auto, input.area-extra').forEach(input => {
                input.addEventListener('input', function() {
                    updateTrimestralRow(this.closest('tr'));
                });
            });

            <?php if ($es_inicial && $periodoEditable): ?>
            (function(){
                var tas = document.querySelectorAll('.inicial-textarea');
                if (!tas.length) return;
                function cw(t){ return t.length; }
                function uwc(ta){
                    var id=ta.dataset.studentId, el=document.getElementById('wc-'+id);
                    if(!el)return;
                    var c=cw(ta.value);
                    el.textContent=c+' / 250 car.';
                    el.className='inicial-word-count'+(c>250?' over':c>200?' warn':'');
                }
                function chkDup(){
                    var map={};
                    tas.forEach(function(ta){
                        var t=ta.value.trim().toLowerCase();
                        if(!t)return;
                        if(!map[t])map[t]=[];
                        map[t].push(ta.dataset.studentId);
                    });
                    document.querySelectorAll('.inicial-dup-badge').forEach(function(b){
                        b.className='inicial-dup-badge'; b.textContent='';
                    });
                    var td=0;
                    for(var t in map){
                        if(map[t].length>1){
                            td+=map[t].length;
                            var ov=map[t].length>3;
                            map[t].forEach(function(sid){
                                var b=document.getElementById('dup-'+sid);
                                if(b){
                                    b.textContent=map[t].length+'x repetido';
                                    b.className='inicial-dup-badge visible'+(ov?' over-limit':'');
                                }
                            });
                        }
                    }
                    var s=document.getElementById('inicialDupSummary');
                    if(s) s.textContent=td>0?td+' comentarios duplicados':'';
                }
                tas.forEach(function(ta){
                    uwc(ta);
                    ta.addEventListener('input',function(){uwc(this);chkDup();});
                });
                chkDup();
                var frm=document.querySelector('form[method="post"]');
                if(frm){
                    frm.addEventListener('submit',function(e){
                        var err=false;
                        tas.forEach(function(ta){
                            if(cw(ta.value)>250){err=true;ta.style.borderColor='#ef4444';}
                            else{ta.style.borderColor='';}
                        });
                        if(err){e.preventDefault();alert('Uno o más comentarios exceden los 250 caracteres. Corrige antes de guardar.');}
                    });
                }
            })();
            <?php endif; ?>

            <?php if (!$es_inicial && $periodoEditable): ?>
            const pasteModalEl = document.getElementById('pasteColumnModal');
            const pasteTextarea = document.getElementById('pasteColumnTextarea');
            const pasteTargetLabel = document.getElementById('pasteColumnTarget');
            const pasteForm = document.getElementById('pasteColumnForm');
            let pasteContext = null;

            if (pasteModalEl && pasteTextarea && pasteForm) {
                const pasteModal = new bootstrap.Modal(pasteModalEl);

                document.querySelectorAll('.btn-paste-column').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const area = this.dataset.area;
                        const index = this.dataset.index;
                        pasteContext = {
                            area,
                            index,
                            min: parseFloat(this.dataset.min),
                            max: parseFloat(this.dataset.max),
                            inputs: Array.from(document.querySelectorAll(`input[data-area="${area}"][data-index="${index}"]`))
                        };

                        pasteTargetLabel.textContent = `${area} ${index}`;
                        pasteTextarea.value = '';
                        pasteModal.show();
                        setTimeout(() => pasteTextarea.focus(), 150);
                    });
                });

                pasteForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    if (!pasteContext) {
                        pasteModal.hide();
                        return;
                    }

                    const rawLines = pasteTextarea.value.replace(/\r\n/g, '\n').split('\n');
                    pasteContext.inputs.forEach((input, idx) => {
                        const line = rawLines[idx] ?? '';
                        let value = line.split('\t')[0] ?? '';
                        value = value.trim();

                        input.value = value;
                        if (value !== '') {
                            clampNotaInput(input, pasteContext.min, pasteContext.max);
                        }
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    });

                    pasteModal.hide();
                    pasteContext = null;
                });

                pasteModalEl.addEventListener('hidden.bs.modal', function() {
                    pasteTextarea.value = '';
                    pasteContext = null;
                });
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>