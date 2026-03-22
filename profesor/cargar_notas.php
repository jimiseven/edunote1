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
$campo = $es_inicial ? 'comentario' : 'calificacion';

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
        $conn->beginTransaction();

        if (isset($_POST['guardar_notas'])) {
            $notasPost = $_POST['notas'] ?? [];
            foreach ($estudiantes as $estudiante) {
                $idEstudiante = (int)$estudiante['id_estudiante'];
                $valor = isset($notasPost[$idEstudiante]) ? trim($notasPost[$idEstudiante]) : '';

                if ($es_inicial) {
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
                    if ($valor === '') {
                        $conn->prepare("DELETE FROM calificaciones_parciales
                                        WHERE id_estudiante = ?
                                          AND id_materia = ?
                                          AND id_periodo_evaluacion = ?")
                             ->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado]);
                        continue;
                    }

                    if (!is_numeric(str_replace(',', '.', $valor))) {
                        throw new Exception("Nota inválida para: " . $estudiante['nombre']);
                    }

                    $notaValor = (float)str_replace(',', '.', $valor);
                    $conn->prepare("INSERT INTO calificaciones_parciales
                                    (id_estudiante, id_materia, id_periodo_evaluacion, calificacion)
                                    VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion)")
                         ->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado, $notaValor]);
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

        $conn->commit();
        header('Location: ' . construirUrlPeriodo($id_curso_materia, $trimestreSeleccionado, $parcialSeleccionado, ['success' => 1]));
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
            padding: 30px;
            margin: 20px 0;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 1.5rem;
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
        .intro-block {
            background: linear-gradient(135deg, #ffffff, #f8fbff);
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
        }
        .intro-title {
            font-size: 1rem;
            font-weight: 700;
            color: #11305e;
            margin-bottom: 0.4rem;
        }
        .intro-text {
            font-size: 0.9rem;
            color: #475569;
            margin: 0;
        }
        .periodo-toolbar {
            background: #ffffff;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        .periodo-toolbar-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #11305e;
            margin-bottom: 1rem;
        }
        .periodo-info {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        .periodo-badge {
            font-size: 0.85rem;
            padding: 0.4rem 0.75rem;
            font-weight: 600;
        }
        .status-badge-enabled {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .status-badge-disabled {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
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
            width: 85px;
            text-align: center;
            font-weight: 600;
            border-radius: 6px;
        }
        .nota-disabled,
        .coment-disabled {
            background: #f8f9fa !important;
            border-color: #d1d5db !important;
            color: #888 !important;
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
            border: 2px solid #93c5fd !important;
        }
        .modal-body textarea {
            width: 100%;
            height: 150px;
            resize: none;
            font-family: monospace;
            border-radius: 8px;
        }
        .coment-textarea {
            width: 100%;
            height: 100px;
            resize: none;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .table-container {
            max-height: 70vh;
            overflow: auto;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        .table-container table {
            margin-bottom: 0;
        }
        .table-container thead th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 10;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            padding: 0.85rem 0.75rem;
        }
        .table-container tbody td {
            vertical-align: middle;
        }
        .table-container tbody td:first-child,
        .table-container thead th:first-child {
            position: sticky;
            left: 0;
            background-color: white;
            z-index: 5;
        }
        .table-container tbody td:nth-child(2),
        .table-container thead th:nth-child(2) {
            position: sticky;
            left: 40px;
            background-color: white;
            z-index: 5;
        }
        .table-container thead th:first-child,
        .table-container thead th:nth-child(2) {
            z-index: 15;
        }
        .nota-ref {
            font-weight: 600;
            text-align: center;
            color: #475569;
        }
        .table-primary {
            background-color: #eff6ff !important;
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

                    <div class="intro-block">
                        <div class="intro-title"><?php echo $modalidadCarga === 'trimestres' ? 'Carga de notas por trimestre' : 'Carga de notas por parcial'; ?></div>
                        <p class="intro-text">
                            <?php if ($modalidadCarga === 'trimestres'): ?>
                                Selecciona el trimestre correspondiente, verifica que el periodo esté habilitado y procede a cargar la nota final trimestral de tus estudiantes.
                            <?php else: ?>
                                Selecciona el trimestre y parcial correspondiente, verifica que el periodo esté habilitado y procede a cargar las notas de tus estudiantes.
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php elseif (isset($_GET['success'])): ?>
                        <div class="alert alert-success">¡Notas cargadas correctamente!</div>
                    <?php endif; ?>
                    <div class="periodo-toolbar">
                        <div class="periodo-toolbar-title">Selección de periodo</div>
                        <form method="get" class="row g-3 align-items-end">
                            <input type="hidden" name="curso_materia" value="<?php echo $id_curso_materia; ?>">
                            <input type="hidden" name="confirmar" value="1">
                            <?php if ($modalidadCarga === 'trimestres'): ?>
                                <input type="hidden" name="parcial" value="<?php echo $parcialSeleccionado; ?>">
                            <?php endif; ?>
                            <div class="col-md-3">
                                <label class="form-label">Trimestre</label>
                                <select name="trimestre" class="form-select">
                                    <?php foreach ($periodosPorTrimestre as $trimestre => $parciales): ?>
                                        <option value="<?php echo $trimestre; ?>" <?php echo $trimestre == $trimestreSeleccionado ? 'selected' : ''; ?>>
                                            Trimestre <?php echo $trimestre; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($modalidadCarga === 'parciales'): ?>
                                <div class="col-md-3">
                                    <label class="form-label">Parcial</label>
                                    <select name="parcial" class="form-select">
                                        <?php foreach (($periodosPorTrimestre[$trimestreSeleccionado] ?? []) as $parcial => $periodo): ?>
                                            <option value="<?php echo $parcial; ?>" <?php echo $parcial == $parcialSeleccionado ? 'selected' : ''; ?>>
                                                Parcial <?php echo $parcial; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <?php echo $periodoConfirmado ? 'Cambiar periodo' : 'Cargar periodo'; ?>
                                </button>
                            </div>
                        </form>
                        <div class="periodo-info">
                            <span class="badge periodo-badge <?php echo $periodoEditable ? 'status-badge-enabled' : 'status-badge-disabled'; ?>">
                                <?php echo $periodoEditable ? '✓ Habilitado para carga' : '✗ No habilitado'; ?>
                            </span>
                            <span class="badge bg-light text-dark border periodo-badge">
                                Gestión <?php echo htmlspecialchars($gestionActual); ?>
                            </span>
                            <span class="badge bg-light text-dark border periodo-badge">
                                <?php echo $modalidadCarga === 'trimestres' ? 'Trimestre ' . $trimestreSeleccionado : 'T' . $trimestreSeleccionado . ' - P' . $parcialSeleccionado; ?>
                            </span>
                            <?php if ($periodoSeleccionado['fecha_inicio'] || $periodoSeleccionado['fecha_fin']): ?>
                                <span class="badge bg-light text-dark border periodo-badge">
                                    <?php echo $periodoSeleccionado['fecha_inicio'] ? date('d/m/Y', strtotime($periodoSeleccionado['fecha_inicio'])) : 'Sin inicio'; ?>
                                    -
                                    <?php echo $periodoSeleccionado['fecha_fin'] ? date('d/m/Y', strtotime($periodoSeleccionado['fecha_fin'])) : 'Sin fin'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($periodoConfirmado): ?>
                        <?php if (!$es_inicial): ?>
                            <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#modalExcel" <?php echo !$periodoEditable ? 'disabled' : ''; ?>>
                                Cargar desde Excel
                            </button>
                            <div class="modal fade" id="modalExcel" tabindex="-1" aria-labelledby="modalExcelLabel" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="modalExcelLabel">Cargar Notas desde Excel</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="post">
                                            <input type="hidden" name="trimestre" value="<?php echo $trimestreSeleccionado; ?>">
                                            <input type="hidden" name="parcial" value="<?php echo $parcialSeleccionado; ?>">
                                            <div class="modal-body">
                                                <div class="alert alert-info">
                                                    Cargará notas para <strong><?php echo $modalidadCarga === 'trimestres' ? 'Trimestre ' . $trimestreSeleccionado : 'Trimestre ' . $trimestreSeleccionado . ' - Parcial ' . $parcialSeleccionado; ?></strong>.
                                                </div>
                                                <div class="mb-3">
                                                    <label>Pegue aquí la columna de notas:</label>
                                                    <textarea name="datos_excel" class="form-control" placeholder="Pegue aquí SOLO la columna de notas desde Excel"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" name="guardar_excel" class="btn btn-primary" <?php echo !$periodoEditable ? 'disabled' : ''; ?>>Cargar Notas</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <form method="post">
                            <input type="hidden" name="trimestre" value="<?php echo $trimestreSeleccionado; ?>">
                            <input type="hidden" name="parcial" value="<?php echo $parcialSeleccionado; ?>">
                            <div class="helper-alert">
                                <strong>Importante:</strong> Verifica que el orden de estudiantes coincida con tu lista antes de cargar notas.
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
                                            <th>#</th>
                                            <th>Estudiante</th>
                                            <th class="text-center <?php echo $periodoEditable ? 'periodo-activo-th' : 'periodo-inactivo-th'; ?>">
                                                <?php echo $modalidadCarga === 'trimestres' ? 'Nota trimestral' : 'Parcial actual'; ?>
                                            </th>
                                            <th class="text-center">P1</th>
                                            <th class="text-center">P2</th>
                                            <th class="text-center">P3</th>
                                            <th>Promedio trimestre</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $contador = 1; ?>
                                        <?php foreach ($estudiantes as $est): ?>
                                        <tr>
                                            <td><?php echo $contador++; ?></td>
                                            <td><?php echo htmlspecialchars($est['nombre']); ?></td>
                                            <?php
                                            $notaActual = $notas[$est['id_estudiante']][$trimestreSeleccionado][$parcialSeleccionado] ?? '';
                                            $notasTrimestre = $notas[$est['id_estudiante']][$trimestreSeleccionado] ?? [];
                                            ?>
                                            <td>
                                                <?php if ($es_inicial): ?>
                                                    <textarea
                                                        name="notas[<?php echo $est['id_estudiante']; ?>]"
                                                        class="coment-textarea <?php echo !$periodoEditable ? 'coment-disabled' : ''; ?>"
                                                        placeholder="<?php echo $periodoEditable ? ($modalidadCarga === 'trimestres' ? 'Comentario trimestral' : 'Comentario parcial ' . $parcialSeleccionado) : 'No habilitado'; ?>"
                                                        <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>
                                                    ><?php echo htmlspecialchars($notaActual); ?></textarea>
                                                <?php else: ?>
                                                    <?php
                                                    $notaStyle = '';
                                                    if ($notaActual !== '' && is_numeric($notaActual) && $notaActual < 51) {
                                                        $notaStyle = 'color: #dc3545;';
                                                    }
                                                    ?>
                                                    <input
                                                        type="number"
                                                        name="notas[<?php echo $est['id_estudiante']; ?>]"
                                                        class="form-control nota-input <?php echo !$periodoEditable ? 'nota-disabled' : ''; ?>"
                                                        value="<?php echo htmlspecialchars($notaActual); ?>"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                        <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>
                                                        oninput="highlightLowGrades(this)"
                                                        style="<?php echo $notaStyle; ?>"
                                                    >
                                                <?php endif; ?>
                                            </td>
                                            <?php for ($parcialRef = 1; $parcialRef <= 3; $parcialRef++): ?>
                                                <?php $valorReferencia = $notas[$est['id_estudiante']][$trimestreSeleccionado][$parcialRef] ?? ''; ?>
                                                <td class="nota-ref <?php echo ($parcialRef === $parcialSeleccionado) ? 'table-primary' : ''; ?>">
                                                    <?php echo $valorReferencia !== '' ? htmlspecialchars($valorReferencia) : '--'; ?>
                                                </td>
                                            <?php endfor; ?>
                                            <td class="align-middle">
                                                <span class="promedio"><?php echo calcularPromedioTrimestre($notasTrimestre, $es_inicial); ?></span>
                                            </td>
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
                        </form>
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
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        function highlightLowGrades(input) {
            input.style.color = input.value && parseFloat(input.value) < 51 ? '#dc3545' : '';
        }
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.nota-input').forEach(input => {
                if (input.value && parseFloat(input.value) < 51) {
                    input.style.color = '#dc3545';
                }
            });
            const trimestreSelect = document.querySelector('select[name="trimestre"]');
            const parcialSelect = document.querySelector('select[name="parcial"]');
            const modalidadCarga = <?php echo json_encode($modalidadCarga); ?>;
            const parcialesPorTrimestre = <?php echo json_encode(array_map(function ($parciales) {
                return array_map(function ($periodo) {
                    return [
                        'parcial' => (int)$periodo['parcial'],
                        'label' => 'Parcial ' . (int)$periodo['parcial']
                    ];
                }, array_values($parciales));
            }, $periodosPorTrimestre)); ?>;
            if (modalidadCarga === 'parciales' && trimestreSelect && parcialSelect) {
                trimestreSelect.addEventListener('change', function() {
                    const trimestre = this.value;
                    const opciones = parcialesPorTrimestre[trimestre] || [];
                    parcialSelect.innerHTML = '';
                    opciones.forEach(opcion => {
                        const option = document.createElement('option');
                        option.value = opcion.parcial;
                        option.textContent = opcion.label;
                        parcialSelect.appendChild(option);
                    });
                });
            }
        });
    </script>
</body>
</html>