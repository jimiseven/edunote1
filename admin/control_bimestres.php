<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->query("SELECT anio_escolar FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
$gestionConfigurada = $stmt->fetchColumn();
$gestionConfigurada = $gestionConfigurada ? trim((string)$gestionConfigurada) : '';
$gestionActual = $gestionConfigurada !== '' ? $gestionConfigurada : date('Y');
$gestionAlternativa = null;
if (preg_match('/\b(20\d{2})\b/', $gestionActual, $matches)) {
    $gestionAlternativa = $matches[1];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodosPost = $_POST['periodos'] ?? [];

    try {
        $conn->beginTransaction();

        foreach ($periodosPost as $idPeriodo => $datosPeriodo) {
            $idPeriodo = (int)$idPeriodo;
            $estaActivo = isset($datosPeriodo['esta_activo']) ? 1 : 0;
            $fechaInicio = !empty($datosPeriodo['fecha_inicio']) ? $datosPeriodo['fecha_inicio'] : null;
            $fechaFin = !empty($datosPeriodo['fecha_fin']) ? $datosPeriodo['fecha_fin'] : null;

            if ($fechaInicio && $fechaFin && $fechaInicio > $fechaFin) {
                throw new Exception("La fecha de inicio no puede ser mayor a la fecha fin.");
            }

            $stmt = $conn->prepare("UPDATE periodos_evaluacion
                                    SET esta_activo = ?, fecha_inicio = ?, fecha_fin = ?
                                    WHERE id_periodo_evaluacion = ?");
            $stmt->execute([$estaActivo, $fechaInicio, $fechaFin, $idPeriodo]);
        }

        $conn->commit();
        $success = "Configuración de parciales actualizada correctamente";
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
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

if (!empty($periodos) && $gestionAlternativa !== null) {
    $gestionActual = $periodos[0]['gestion'];
}

$periodosPorTrimestre = [];
$periodosActivos = [];
$hoy = date('Y-m-d');
foreach ($periodos as $periodo) {
    $periodosPorTrimestre[(int)$periodo['trimestre']][] = $periodo;
    $dentroRango = (empty($periodo['fecha_inicio']) || $hoy >= $periodo['fecha_inicio']) &&
                   (empty($periodo['fecha_fin']) || $hoy <= $periodo['fecha_fin']);
    if ((int)$periodo['esta_activo'] === 1 && $dentroRango) {
        $periodosActivos[] = $periodo;
    }
}
$primerTrimestre = !empty($periodosPorTrimestre) ? array_key_first($periodosPorTrimestre) : null;

$resumenTrimestres = [];
foreach ($periodosPorTrimestre as $trimestre => $periodosTrimestre) {
    $activos = 0;
    $enRango = 0;
    foreach ($periodosTrimestre as $periodo) {
        if ((int)$periodo['esta_activo'] === 1) {
            $activos++;
        }
        $dentroRango = (empty($periodo['fecha_inicio']) || $hoy >= $periodo['fecha_inicio']) &&
                       (empty($periodo['fecha_fin']) || $hoy <= $periodo['fecha_fin']);
        if ((int)$periodo['esta_activo'] === 1 && $dentroRango) {
            $enRango++;
        }
    }
    $resumenTrimestres[$trimestre] = [
        'total' => count($periodosTrimestre),
        'activos' => $activos,
        'en_rango' => $enRango
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Control de Parciales</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        body, html {
            height: 100%;
            background: #f4f8fa;
            overflow-x: hidden;
        }
        .container-fluid, .row {
            height: 100%;
        }
        .sidebar {
            background: #19202a;
            height: 100vh;
            position: sticky;
            top: 0;
        }
        main {
            background: #fff;
            height: 100vh;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
        }
        .main-title {
            font-weight: bold;
            color: #11305e;
            margin-bottom: 1rem;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
        }
        .card-header {
            background-color: #4682B4;
            color: white;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
            padding: 0.75rem 1.25rem;
        }
        .card-body {
            padding: 1.25rem;
        }
        .btn-primary {
            background: #4682B4;
            border-color: #4682B4;
            font-weight: 500;
        }
        .btn-primary:hover {
            background: #11305e;
            border-color: #11305e;
        }
        .alert {
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .summary-card {
            border-radius: 10px;
            padding: 1rem;
            background: linear-gradient(135deg, #eff6ff, #ffffff);
            border: 1px solid #dbeafe;
            box-shadow: 0 4px 6px rgba(0,0,0,0.04);
        }
        .page-intro {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem 1.25rem;
            border: 1px solid #dbeafe;
            border-radius: 12px;
            background: linear-gradient(135deg, #ffffff, #f8fbff);
        }
        .page-intro-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #11305e;
            margin-bottom: 0.35rem;
        }
        .page-intro-text {
            color: #475569;
            margin: 0;
            font-size: 0.92rem;
        }
        .page-intro-badge {
            white-space: nowrap;
            background: #11305e;
            color: #fff;
            border-radius: 999px;
            padding: 0.45rem 0.85rem;
            font-size: 0.82rem;
            font-weight: 600;
        }
        .summary-title {
            color: #11305e;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }
        .summary-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }
        .summary-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #4682B4;
        }
        .trimestre-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .accordion-item {
            border: 1px solid #dbeafe;
            border-radius: 10px !important;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.04);
        }
        .accordion-button {
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 700;
            box-shadow: none !important;
            padding: 1rem 1.2rem;
        }
        .accordion-button:not(.collapsed) {
            background: #dbeafe;
            color: #11305e;
        }
        .accordion-button::after {
            margin-left: auto;
        }
        .accordion-button:focus {
            border-color: #93c5fd;
        }
        .accordion-title-block {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .accordion-subtitle {
            font-size: 0.84rem;
            font-weight: 500;
            color: #475569;
        }
        .trimestre-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-left: 1rem;
        }
        .periodo-table-wrapper {
            padding: 1rem;
            background: #fff;
        }
        .periodo-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.75rem;
        }
        .periodo-table th {
            font-size: 0.85rem;
            color: #475569;
            font-weight: 700;
            padding: 0 0.75rem 0.35rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .periodo-table td {
            vertical-align: middle;
        }
        .periodo-row {
            background: #fff5f5;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }
        .periodo-row td {
            padding: 0.85rem 0.75rem;
            border-top: 1px solid #fee2e2;
            border-bottom: 1px solid #fee2e2;
        }
        .periodo-row td:first-child {
            border-left: 4px solid #dc3545;
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }
        .periodo-row td:last-child {
            border-right: 1px solid #fee2e2;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }
        .periodo-row.periodo-active {
            background: #f0fdf4;
        }
        .periodo-row.periodo-active td {
            border-top-color: #dcfce7;
            border-bottom-color: #dcfce7;
        }
        .periodo-row.periodo-active td:first-child {
            border-left-color: #28a745;
        }
        .periodo-nombre {
            font-weight: 700;
            color: #11305e;
        }
        .periodo-subtitulo {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.2rem;
        }
        .periodo-meta {
            margin-top: 0.4rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }
        .meta-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.5rem;
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
            font-size: 0.76rem;
            font-weight: 600;
        }
        .toggle-cell {
            min-width: 180px;
        }
        .fechas-cell {
            min-width: 320px;
        }
        .form-check-label {
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        .form-check-input:checked {
            background-color: #4682B4;
            border-color: #4682B4;
        }
        .fecha-container {
            display: flex;
            gap: 0.5rem;
        }
        .fecha-container .form-control {
            font-size: 0.9rem;
            padding: 0.4rem 0.6rem;
            height: auto;
        }
        .fecha-container label {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }
        .badge {
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        .helper-text {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 0.35rem;
        }
        .content-wrapper {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 2rem);
        }
        .cards-container {
            display: flex;
            gap: 1rem;
            flex: 1;
        }
        .card-config {
            flex: 2;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .card-status {
            flex: 1;
        }
        .card-status .card-body {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .card-body-scroll {
            overflow-y: auto;
            flex: 1;
            padding: 1.25rem;
        }
        .btn-container {
            padding: 1rem;
            border-top: 1px solid #eee;
            background: #f8f9fa;
            text-align: right;
        }
        .estado-fecha {
            font-size: 0.85rem;
            color: #4b5563;
            margin-top: 0.2rem;
        }
        .empty-state {
            padding: 1rem;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            color: #475569;
        }
        .config-helper {
            padding: 0.9rem 1rem;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            background: #f8fbff;
            color: #475569;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        .status-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .status-summary-card {
            padding: 0.9rem;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .status-summary-card strong {
            display: block;
            font-size: 1.1rem;
            color: #11305e;
        }
        .status-summary-card span {
            color: #64748b;
            font-size: 0.85rem;
        }
        .status-list-title {
            font-weight: 700;
            color: #11305e;
            margin-bottom: 0;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.6rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pill-active {
            background: #dcfce7;
            color: #166534;
        }
        .status-pill-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-pill-range {
            background: #dbeafe;
            color: #1d4ed8;
        }
        @media (max-width: 992px) {
            .cards-container {
                flex-direction: column;
            }
            .page-intro {
                flex-direction: column;
                align-items: flex-start;
            }
            .accordion-button {
                align-items: flex-start;
            }
            .trimestre-badges {
                margin-left: 0;
                margin-top: 0.4rem;
            }
            .periodo-table,
            .periodo-table thead,
            .periodo-table tbody,
            .periodo-table th,
            .periodo-table td,
            .periodo-table tr {
                display: block;
            }
            .periodo-table thead {
                display: none;
            }
            .periodo-row {
                margin-bottom: 1rem;
            }
            .periodo-row td {
                border-right: 1px solid #e5e7eb !important;
                border-left-width: 1px !important;
                border-radius: 0 !important;
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
            }
            .periodo-row td:first-child {
                border-top-left-radius: 10px !important;
                border-top-right-radius: 10px !important;
            }
            .periodo-row td:last-child {
                border-bottom-left-radius: 10px !important;
                border-bottom-right-radius: 10px !important;
            }
            .toggle-cell,
            .fechas-cell {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid g-0">
        <div class="row g-0">
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-wrapper">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                        <h1 class="main-title">Control de Parciales</h1>
                    </div>

                    <div class="page-intro">
                        <div>
                            <div class="page-intro-title">Administra la habilitación de carga por trimestre y parcial</div>
                            <p class="page-intro-text">
                                Activa solo los parciales que corresponden al periodo vigente y define con claridad su rango de fechas para evitar bloqueos en la carga de notas.
                            </p>
                        </div>
                        <div class="page-intro-badge">Gestión <?php echo htmlspecialchars($gestionActual); ?></div>
                    </div>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php elseif (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if (!empty($resumenTrimestres)): ?>
                        <div class="summary-grid">
                            <?php foreach ($resumenTrimestres as $trimestre => $resumen): ?>
                                <div class="summary-card">
                                    <div class="summary-title">Trimestre <?php echo $trimestre; ?></div>
                                    <div class="summary-value"><?php echo $resumen['activos']; ?>/<?php echo $resumen['total']; ?> parciales activos</div>
                                    <div class="summary-meta mt-2">
                                        <span class="badge bg-success">Activos: <?php echo $resumen['activos']; ?></span>
                                        <span class="badge bg-primary">En rango: <?php echo $resumen['en_rango']; ?></span>
                                        <span class="badge bg-secondary">Total: <?php echo $resumen['total']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="cards-container">
                        <div class="card card-config">
                            <div class="card-header">
                                Configuración de Parciales - Gestión <?php echo htmlspecialchars($gestionActual); ?>
                            </div>
                            <form method="post" action="" class="d-flex flex-column flex-grow-1">
                                <div class="card-body-scroll">
                                    <div class="config-helper">
                                        Selecciona un trimestre, revisa sus parciales y ajusta su habilitación junto con el rango de fechas de carga.
                                    </div>
                                    <?php if (empty($periodosPorTrimestre)): ?>
                                        <div class="empty-state">
                                            No existen periodos de evaluación configurados para la gestión actual.
                                        </div>
                                    <?php else: ?>
                                        <div class="accordion trimestre-grid" id="accordionTrimestres">
                                            <?php foreach ($periodosPorTrimestre as $trimestre => $periodosTrimestre): ?>
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header" id="headingTrimestre<?php echo $trimestre; ?>">
                                                        <button class="accordion-button <?php echo $trimestre !== $primerTrimestre ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTrimestre<?php echo $trimestre; ?>" aria-expanded="<?php echo $trimestre === $primerTrimestre ? 'true' : 'false'; ?>" aria-controls="collapseTrimestre<?php echo $trimestre; ?>">
                                                            <span class="accordion-title-block">
                                                                <span>Trimestre <?php echo $trimestre; ?></span>
                                                                <span class="accordion-subtitle">Gestiona aquí los parciales y sus fechas de carga</span>
                                                            </span>
                                                            <div class="trimestre-badges">
                                                                <span class="badge bg-success">
                                                                    Activos: <?php echo $resumenTrimestres[$trimestre]['activos'] ?? 0; ?>
                                                                </span>
                                                                <span class="badge bg-primary">
                                                                    En rango: <?php echo $resumenTrimestres[$trimestre]['en_rango'] ?? 0; ?>
                                                                </span>
                                                            </div>
                                                        </button>
                                                    </h2>
                                                    <div id="collapseTrimestre<?php echo $trimestre; ?>" class="accordion-collapse collapse <?php echo $trimestre === $primerTrimestre ? 'show' : ''; ?>" aria-labelledby="headingTrimestre<?php echo $trimestre; ?>" data-bs-parent="#accordionTrimestres">
                                                        <div class="periodo-table-wrapper">
                                                            <table class="periodo-table">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Parcial</th>
                                                                        <th>Habilitación</th>
                                                                        <th>Fechas de carga</th>
                                                                        <th>Estado actual</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($periodosTrimestre as $periodo): ?>
                                                                        <?php
                                                                        $editableAhora = (int)$periodo['esta_activo'] === 1 &&
                                                                            (empty($periodo['fecha_inicio']) || $hoy >= $periodo['fecha_inicio']) &&
                                                                            (empty($periodo['fecha_fin']) || $hoy <= $periodo['fecha_fin']);
                                                                        ?>
                                                                        <tr class="periodo-row <?php echo (int)$periodo['esta_activo'] === 1 ? 'periodo-active' : ''; ?>">
                                                                            <td>
                                                                                <div class="periodo-nombre">Parcial <?php echo (int)$periodo['parcial']; ?></div>
                                                                                <div class="periodo-subtitulo"><?php echo htmlspecialchars($periodo['nombre']); ?></div>
                                                                                <div class="periodo-meta">
                                                                                    <span class="meta-chip">Trimestre <?php echo (int)$periodo['trimestre']; ?></span>
                                                                                    <span class="meta-chip">Parcial <?php echo (int)$periodo['parcial']; ?></span>
                                                                                </div>
                                                                            </td>
                                                                            <td class="toggle-cell">
                                                                                <div class="form-check form-switch">
                                                                                    <input class="form-check-input" type="checkbox"
                                                                                        name="periodos[<?php echo $periodo['id_periodo_evaluacion']; ?>][esta_activo]"
                                                                                        id="periodo<?php echo $periodo['id_periodo_evaluacion']; ?>"
                                                                                        <?php echo (int)$periodo['esta_activo'] === 1 ? 'checked' : ''; ?>>
                                                                                    <label class="form-check-label" for="periodo<?php echo $periodo['id_periodo_evaluacion']; ?>">
                                                                                        <?php echo (int)$periodo['esta_activo'] === 1 ? 'Habilitado' : 'Deshabilitado'; ?>
                                                                                    </label>
                                                                                </div>
                                                                                <div class="helper-text">Usa este control para permitir o bloquear la carga de notas.</div>
                                                                            </td>
                                                                            <td class="fechas-cell">
                                                                                <div class="fecha-container">
                                                                                    <div class="flex-fill">
                                                                                        <label class="form-label">Inicio</label>
                                                                                        <input type="date" class="form-control"
                                                                                            name="periodos[<?php echo $periodo['id_periodo_evaluacion']; ?>][fecha_inicio]"
                                                                                            value="<?php echo htmlspecialchars((string)$periodo['fecha_inicio']); ?>">
                                                                                    </div>
                                                                                    <div class="flex-fill">
                                                                                        <label class="form-label">Fin</label>
                                                                                        <input type="date" class="form-control"
                                                                                            name="periodos[<?php echo $periodo['id_periodo_evaluacion']; ?>][fecha_fin]"
                                                                                            value="<?php echo htmlspecialchars((string)$periodo['fecha_fin']); ?>">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="helper-text">
                                                                                    <?php if (!empty($periodo['fecha_inicio']) || !empty($periodo['fecha_fin'])): ?>
                                                                                        Ventana actual:
                                                                                        <?php echo !empty($periodo['fecha_inicio']) ? date('d/m/Y', strtotime($periodo['fecha_inicio'])) : 'Sin inicio'; ?>
                                                                                        -
                                                                                        <?php echo !empty($periodo['fecha_fin']) ? date('d/m/Y', strtotime($periodo['fecha_fin'])) : 'Sin fin'; ?>
                                                                                    <?php else: ?>
                                                                                        Define fechas para controlar exactamente cuándo se podrá cargar este parcial.
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </td>
                                                                            <td>
                                                                                <div class="d-flex flex-column gap-2">
                                                                                    <span class="status-pill <?php echo (int)$periodo['esta_activo'] === 1 ? 'status-pill-active' : 'status-pill-inactive'; ?>">
                                                                                        <?php echo (int)$periodo['esta_activo'] === 1 ? 'Activo' : 'Inactivo'; ?>
                                                                                    </span>
                                                                                    <?php if ($editableAhora): ?>
                                                                                        <span class="status-pill status-pill-range">En rango hoy</span>
                                                                                    <?php else: ?>
                                                                                        <span class="estado-fecha">
                                                                                            <?php if (empty($periodo['fecha_inicio']) && empty($periodo['fecha_fin'])): ?>
                                                                                                Sin rango definido
                                                                                            <?php else: ?>
                                                                                                Fuera de rango hoy
                                                                                            <?php endif; ?>
                                                                                        </span>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-container">
                                    <button type="submit" class="btn btn-primary">
                                        Guardar Configuración
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="card card-status">
                            <div class="card-header">
                                Estado Actual del Sistema
                            </div>
                            <div class="card-body">
                                <div class="status-summary">
                                    <div class="status-summary-card">
                                        <strong><?php echo count($periodosActivos); ?></strong>
                                        <span>Parciales activos en rango hoy</span>
                                    </div>
                                    <div class="status-summary-card">
                                        <strong><?php echo count($periodos); ?></strong>
                                        <span>Parciales configurados</span>
                                    </div>
                                </div>
                                <h6 class="status-list-title">Parciales habilitados en este momento</h6>
                                <ul class="list-group">
                                    <?php if (!empty($periodosActivos)): ?>
                                        <?php foreach ($periodosActivos as $periodoActivo): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Trimestre <?php echo (int)$periodoActivo['trimestre']; ?> - Parcial <?php echo (int)$periodoActivo['parcial']; ?>
                                                <span class="badge bg-success rounded-pill">Activo</span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="list-group-item text-danger">
                                            No hay parciales activos en rango. Los profesores no podrán cargar notas.
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        if (window.feather) {
            feather.replace();
        }
        
        document.querySelectorAll('.form-check-input').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const row = this.closest('.periodo-row');
                const label = this.closest('.form-check').querySelector('.form-check-label');
                const statusPill = row ? row.querySelector('.status-pill') : null;
                if (row) {
                    if (this.checked) {
                        row.classList.add('periodo-active');
                    } else {
                        row.classList.remove('periodo-active');
                    }
                }
                if (label) {
                    label.textContent = this.checked ? 'Habilitado' : 'Deshabilitado';
                }
                if (statusPill) {
                    statusPill.textContent = this.checked ? 'Activo' : 'Inactivo';
                    statusPill.classList.toggle('status-pill-active', this.checked);
                    statusPill.classList.toggle('status-pill-inactive', !this.checked);
                }
            });
        });
    </script>
</body>
</html>
