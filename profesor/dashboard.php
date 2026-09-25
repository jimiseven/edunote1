<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header('Location: ../index.php');
    exit();
}

// Conexión a la base de datos
$database = new Database();
$conn = $database->connect();

// Obtener datos del profesor y sus materias/cursos asignados
$profesor_id = $_SESSION['user_id'];

// Obtener configuración del sistema
$stmt = $conn->query("SELECT cantidad_bimestres, anio_escolar FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
$confRow = $stmt->fetch(PDO::FETCH_ASSOC);
$cantidad_bimestres = $confRow ? (int)$confRow['cantidad_bimestres'] : 3;
$gestionActual = ($confRow && trim($confRow['anio_escolar']) !== '') ? trim($confRow['anio_escolar']) : date('Y');

// Determinar qué trimestres tienen al menos un parcial habilitado
$hoy = date('Y-m-d');
$stmtPeriodos = $conn->prepare("SELECT DISTINCT trimestre FROM periodos_evaluacion
    WHERE gestion = ? AND esta_activo = 1
    AND (fecha_inicio IS NULL OR fecha_inicio <= ?)
    AND (fecha_fin IS NULL OR fecha_fin >= ?)");
$stmtPeriodos->execute([$gestionActual, $hoy, $hoy]);
$trimestresHabilitados = array_column($stmtPeriodos->fetchAll(PDO::FETCH_ASSOC), 'trimestre');
$trimestresHabilitados = array_map('intval', $trimestresHabilitados);

$query = "
    SELECT
        pmc.id_curso_materia,
        c.nivel,
        c.curso,
        c.paralelo,
        m.nombre_materia,
        pmc.estado,
        GROUP_CONCAT(DISTINCT cal.bimestre) AS bimestres_cargados
    FROM profesores_materias_cursos pmc
    INNER JOIN cursos_materias cm ON pmc.id_curso_materia = cm.id_curso_materia
    INNER JOIN cursos c ON cm.id_curso = c.id_curso
    INNER JOIN materias m ON cm.id_materia = m.id_materia
    LEFT JOIN calificaciones cal ON cal.id_materia = m.id_materia
        AND EXISTS (
            SELECT 1 FROM estudiantes e
            WHERE e.id_curso = c.id_curso
            AND e.id_estudiante = cal.id_estudiante
        )
    WHERE pmc.id_personal = :profesor_id
    GROUP BY pmc.id_curso_materia
";
$stmt = $conn->prepare($query);
$stmt->bindParam(':profesor_id', $profesor_id, PDO::PARAM_INT);
$stmt->execute();
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Reporte: estudiantes con parciales faltantes ---
// Solo parciales activos o que ya tengan al menos un registro guardado
$stmtPer = $conn->prepare("
    SELECT pe.id_periodo_evaluacion, pe.trimestre, pe.parcial
    FROM periodos_evaluacion pe
    WHERE pe.gestion = ?
    AND (
        pe.esta_activo = 1
        OR EXISTS (SELECT 1 FROM calificaciones_parciales cp2 WHERE cp2.id_periodo_evaluacion = pe.id_periodo_evaluacion)
    )
    ORDER BY pe.trimestre, pe.parcial");
$stmtPer->execute([$gestionActual]);
$periodos = $stmtPer->fetchAll(PDO::FETCH_ASSOC);

$trimestresCols = [];
$periodoIds = [];
foreach ($periodos as $p) {
    $t = (int)$p['trimestre'];
    $pr = (int)$p['parcial'];
    $periodoIds[] = (int)$p['id_periodo_evaluacion'];
    if (!isset($trimestresCols[$t])) $trimestresCols[$t] = [];
    $trimestresCols[$t][] = $pr;
}
ksort($trimestresCols);

$reporteData = [];
if (!empty($periodoIds)) {
    $inList = implode(',', $periodoIds);
    $sqlReporte = "
        SELECT e.id_estudiante,
            e.nombres, e.apellido_paterno, e.apellido_materno,
            c.nivel, c.curso, c.paralelo,
            m.nombre_materia,
            pmc.id_curso_materia,
            pe.trimestre, pe.parcial,
            cp.id_calificacion_parcial IS NOT NULL AS tiene_registro,
            COALESCE(cp.calificacion, 0) AS calificacion,
            cp.comentario
        FROM profesores_materias_cursos pmc
        INNER JOIN cursos_materias cm ON pmc.id_curso_materia = cm.id_curso_materia
        INNER JOIN cursos c ON cm.id_curso = c.id_curso
        INNER JOIN materias m ON cm.id_materia = m.id_materia
        INNER JOIN estudiantes e ON e.id_curso = c.id_curso
        CROSS JOIN periodos_evaluacion pe
            ON pe.gestion = :gestion AND pe.id_periodo_evaluacion IN ($inList)
        LEFT JOIN calificaciones_parciales cp
            ON cp.id_estudiante = e.id_estudiante
            AND cp.id_materia = m.id_materia
            AND cp.id_periodo_evaluacion = pe.id_periodo_evaluacion
        WHERE pmc.id_personal = :profesor
        ORDER BY c.nivel, c.curso, c.paralelo, m.nombre_materia,
                 e.apellido_paterno, e.apellido_materno, e.nombres,
                 pe.trimestre, pe.parcial";
    $stmtRep = $conn->prepare($sqlReporte);
    $stmtRep->execute([':gestion' => $gestionActual, ':profesor' => $profesor_id]);
    $rowsReporte = $stmtRep->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rowsReporte as $row) {
        $key = $row['id_estudiante'] . '|' . $row['nombre_materia']
             . '|' . $row['nivel'] . '|' . $row['curso'] . '|' . $row['paralelo'];
        if (!isset($reporteData[$key])) {
            $reporteData[$key] = [
                'estudiante' => trim(($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ', ' . $row['nombres']),
                'nivel'    => $row['nivel'],
                'curso'    => $row['curso'],
                'paralelo' => $row['paralelo'],
                'materia'  => $row['nombre_materia'],
                'id_curso_materia' => (int)$row['id_curso_materia'],
                'parciales'=> [],
                'faltantes'=> 0,
            ];
        }
        $tieneNota = false;
        if ($row['tiene_registro']) {
            if ((float)$row['calificacion'] > 0) $tieneNota = true;
            elseif (!empty($row['comentario'])) $tieneNota = true;
        }
        $reporteData[$key]['parciales'][(int)$row['trimestre'] . '_' . (int)$row['parcial']] = $tieneNota;
        if (!$tieneNota) $reporteData[$key]['faltantes']++;
    }
}
$reporteFiltrado = array_values(array_filter($reporteData, function($r) { return $r['faltantes'] > 0; }));

// Obtener TODOS los anuncios activos con sus IDs
$conn = (new Database())->connect();
$hoy = date('Y-m-d');
$stmt = $conn->prepare("SELECT id, mensaje FROM anuncios WHERE fecha_inicio <= ? AND fecha_fin >= ? ORDER BY id DESC");
$stmt->execute([$hoy, $hoy]);
$anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>EduNote - Dashboard Profesor</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        .sidebar {
            background-color: #f8f8f8;
            min-height: 100vh;
            padding: 1rem;
        }

        .main-content {
            padding: 1.5rem;
            overflow-x: auto;
        }

        .table-responsive {
            min-width: 600px;
        }

        .status-badge {
            font-size: 0.9rem;
            padding: 0.4em 0.8em;
        }

        .btn-action {
            white-space: nowrap;
        }

        /* Estilos base para el banner de anuncios */
        .announcement-banner {
            border-left: 4px solid;
            border-radius: 0 8px 8px 0;
            padding: 1rem 1.5rem;
            margin: 0 -1.5rem 1.5rem -1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .announcement-banner::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 0h20L0 20z' fill='%23e3f2fd' fill-opacity='0.2' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.6;
            z-index: 0;
        }

        /* Colores alternados para los anuncios */
        .announcement-color-0 {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left-color: #2196f3;
        }

        .announcement-color-1 {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border-left-color: #4caf50;
        }

        .announcement-color-2 {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            border-left-color: #ff9800;
        }

        .announcement-color-3 {
            background: linear-gradient(135deg, #fce4ec 0%, #f8bbd0 100%);
            border-left-color: #e91e63;
        }

        .announcement-icon {
            font-size: 1.8rem;
            margin-right: 1rem;
            flex-shrink: 0;
            z-index: 1;
        }

        .announcement-icon-0 {
            color: #2196f3;
        }

        .announcement-icon-1 {
            color: #4caf50;
        }

        .announcement-icon-2 {
            color: #ff9800;
        }

        .announcement-icon-3 {
            color: #e91e63;
        }

        .announcement-text {
            font-size: 1.05rem;
            font-weight: 500;
            margin: 0;
            z-index: 1;
            line-height: 1.4;
            flex: 1;
        }

        .announcement-text-0 {
            color: #0d47a1;
        }

        .announcement-text-1 {
            color: #2e7d32;
        }

        .announcement-text-2 {
            color: #e65100;
        }

        .announcement-text-3 {
            color: #ad1457;
        }

        .announcement-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            z-index: 1;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .announcement-close-0 {
            color: #2196f3;
        }

        .announcement-close-1 {
            color: #4caf50;
        }

        .announcement-close-2 {
            color: #ff9800;
        }

        .announcement-close-3 {
            color: #e91e63;
        }

        .announcement-close:hover {
            opacity: 1;
        }

        /* Animaciones para los anuncios */
        .announcement-slide-enter {
            opacity: 0;
            transform: translateY(20px);
        }

        .announcement-slide-enter-active {
            opacity: 1;
            transform: translateY(0);
            transition: all 0.5s ease-out;
        }

        .announcement-slide-exit {
            opacity: 1;
            transform: translateY(0);
        }

        .announcement-slide-exit-active {
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.5s ease-out;
        }

        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                padding: 1rem 0;
            }

            .header-title {
                font-size: 1.2rem;
            }

            .user-badge {
                font-size: 0.9rem;
            }

            .announcement-banner {
                flex-direction: column;
                text-align: center;
                padding: 1rem;
            }

            .announcement-icon {
                margin-right: 0;
                margin-bottom: 0.5rem;
            }

            .announcement-close {
                margin-top: 0.5rem;
                margin-left: 0;
            }
        }

        @media (max-width: 576px) {

            .main-content {
                padding: 0.75rem;
            }

            .container-fluid.p-3 {
                padding: 0 !important;
            }

            .card {
                border: none;
                box-shadow: none !important;
                background: transparent;
            }

            .table-responsive {
                min-width: 100%;
                overflow: visible;
            }

            .table thead {
                display: none;
            }

            .table,
            .table tbody,
            .table tr,
            .table td {
                display: block;
                width: 100%;
            }

            .table tr {
                background-color: #fff;
                border: 1px solid #e9ecef;
                border-radius: 0.75rem;
                margin-bottom: 0.75rem;
                padding: 0.35rem 0.5rem;
            }

            .table td {
                border: 0;
                border-bottom: 1px dashed #e9ecef;
                padding: 0.55rem 0.4rem;
                text-align: left !important;
            }

            .table td:last-child {
                border-bottom: 0;
            }

            .table td::before {
                content: attr(data-label);
                display: block;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                color: #6c757d;
                margin-bottom: 0.2rem;
            }

            .btn-action {
                width: 100%;
            }

            .status-wrapper {
                justify-content: flex-start !important;
            }

            .table th,
            .table td {
                padding: 0.75rem 0.5rem;
            }

            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }

            .announcement-text {
                font-size: 0.95rem;
            }
        }

        /* Pestañas del dashboard */
        #dashboardTabs {
            border-bottom: 2px solid #dee2e6;
        }

        #dashboardTabs .nav-link {
            color: #000 !important;
            background-color: #e9ecef;
            border: 1px solid #dee2e6;
            border-bottom: none;
            border-radius: 0.375rem 0.375rem 0 0;
            padding: 0.5rem 1.2rem;
            font-weight: 500;
            transition: color 0.15s, background-color 0.15s;
        }

        #dashboardTabs .nav-link:hover {
            color: #0a58ca;
            background-color: #d6e4ff;
        }

        #dashboardTabs .nav-link.active {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd #0d6efd #fff;
        }

        /* Reporte: tabla de notas faltantes */
        .falta-nota {
            color: #dc3545;
            font-weight: bold;
            font-size: 1.3em;
            line-height: 1;
        }

        .report-table th {
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .report-table td {
            vertical-align: middle;
        }

        .report-filtro {
            max-width: 320px;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>

            <!-- Contenido principal -->
            <main class="w-100 px-0 main-content">
                <!-- Encabezado -->
                <header class="d-flex flex-column flex-md-row justify-content-between align-items-center p-3 bg-light border-bottom">
                    <h1 class="header-title h5 mb-3 mb-md-0 text-primary fw-bold">Cursos Asignados</h1>
                    <span class="user-badge badge bg-secondary">
                        Profesor: <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </span>
                </header>

                <!-- Banner de anuncios -->
                <?php if (!empty($anuncios)): ?>
                    <div id="announcement-container" class="announcement-banner announcement-color-<?php echo 0 % 4; ?>">
                        <i class="ri-megaphone-fill announcement-icon announcement-icon-<?php echo 0 % 4; ?>"></i>
                        <div id="announcement-text" class="announcement-text announcement-text-<?php echo 0 % 4; ?>">
                            <?php echo htmlspecialchars($anuncios[0]['mensaje']); ?>
                        </div>
                        <button class="announcement-close announcement-close-<?php echo 0 % 4; ?>" onclick="document.getElementById('announcement-container').style.display='none'">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Tabs de navegación -->
                <ul class="nav nav-tabs px-3 pt-2" id="dashboardTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tabCursos">Cursos Asignados</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tabReportes">Reportes</a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab: Cursos Asignados -->
                    <div class="tab-pane fade show active" id="tabCursos">
                        <div class="container-fluid p-3">
                            <div class="card shadow-sm">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th scope="col">Nivel</th>
                                                    <th scope="col">Curso</th>
                                                    <th scope="col">Materia</th>
                                                    <th scope="col" class="text-center">Acción</th>
                                                    <th scope="col" class="text-center">Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($cursos)): ?>
                                                    <?php foreach ($cursos as $curso): ?>
                                                        <tr>
                                                            <td data-label="Nivel"><?php echo htmlspecialchars($curso['nivel']); ?></td>
                                                            <td data-label="Curso"><?php echo htmlspecialchars($curso['curso']) . ' ' . htmlspecialchars($curso['paralelo']); ?></td>
                                                            <td data-label="Materia"><?php echo htmlspecialchars($curso['nombre_materia']); ?></td>
                                                            <td data-label="Accion" class="text-center">
                                                                <a href="cargar_notas.php?curso_materia=<?php echo htmlspecialchars($curso['id_curso_materia']); ?>"
                                                                    class="btn btn-primary btn-sm btn-action">
                                                                    Cargar
                                                                </a>
                                                            </td>
                                                            <td data-label="Estado" class="text-center">
                                                                <div class="d-flex flex-wrap gap-1 justify-content-center status-wrapper">
                                                                    <?php for ($i = 1; $i <= $cantidad_bimestres; $i++): ?>
                                                                        <span class="badge <?= in_array($i, $trimestresHabilitados) ? 'bg-success' : 'bg-secondary' ?> status-badge">
                                                                            T<?= $i ?>
                                                                        </span>
                                                                    <?php endfor; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center py-4">No tienes cursos asignados actualmente.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Reportes -->
                    <div class="tab-pane fade" id="tabReportes">
                        <div class="container-fluid p-3">
                            <h6 class="fw-bold text-muted mb-1">Notas Faltantes por Parcial</h6>
                            <p class="text-muted small mb-3">
                                Solo se evalúan parciales habilitados o que ya tengan registros guardados.
                            </p>

                            <?php if (empty($reporteFiltrado)): ?>
                                <div class="alert alert-success mb-0">
                                    <i class="ri-check-line"></i> Todos los estudiantes tienen sus notas completas en los parciales habilitados.
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                    <span class="badge bg-danger"><?php echo count($reporteFiltrado); ?> estudiante(s) con notas faltantes</span>
                                    <select id="filtroCurso" class="form-select form-select-sm report-filtro">
                                        <option value="">Todos los cursos</option>
                                        <?php
                                        $cursosUnicos = [];
                                        foreach ($reporteFiltrado as $r) {
                                            $label = $r['nivel'] . ' ' . $r['curso'] . ' ' . $r['paralelo'] . ' – ' . $r['materia'];
                                            $cursosUnicos[$label] = true;
                                        }
                                        foreach (array_keys($cursosUnicos) as $label): ?>
                                            <option value="<?php echo htmlspecialchars($label); ?>"><?php echo htmlspecialchars($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm report-table mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th rowspan="2" class="align-bottom">#</th>
                                                <th rowspan="2" class="align-bottom">Estudiante</th>
                                                <th rowspan="2" class="align-bottom">Nivel</th>
                                                <th rowspan="2" class="align-bottom">Curso</th>
                                                <th rowspan="2" class="align-bottom">Materia</th>
                                                <?php foreach ($trimestresCols as $t => $parciales): ?>
                                                    <th colspan="<?php echo count($parciales); ?>" class="text-center border-start">T<?php echo $t; ?></th>
                                                <?php endforeach; ?>
                                                <th rowspan="2" class="align-bottom text-center">Acción</th>
                                            </tr>
                                            <tr>
                                                <?php foreach ($trimestresCols as $t => $parciales): ?>
                                                    <?php foreach ($parciales as $p): ?>
                                                        <th class="text-center border-start" style="font-size:0.8rem">P<?php echo $p; ?></th>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $n = 1; foreach ($reporteFiltrado as $r):
                                                $cursoLabel = $r['nivel'] . ' ' . $r['curso'] . ' ' . $r['paralelo'] . ' – ' . $r['materia'];
                                            ?>
                                                <tr data-curso="<?php echo htmlspecialchars($cursoLabel); ?>">
                                                    <td><?php echo $n++; ?></td>
                                                    <td><?php echo htmlspecialchars($r['estudiante']); ?></td>
                                                    <td><?php echo htmlspecialchars($r['nivel']); ?></td>
                                                    <td><?php echo htmlspecialchars($r['curso'] . ' ' . $r['paralelo']); ?></td>
                                                    <td><?php echo htmlspecialchars($r['materia']); ?></td>
                                                    <?php foreach ($trimestresCols as $t => $parciales): ?>
                                                        <?php foreach ($parciales as $p): ?>
                                                            <td class="text-center border-start">
                                                                <?php if (!($r['parciales'][$t . '_' . $p] ?? false)): ?>
                                                                    <span class="falta-nota">✕</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        <?php endforeach; ?>
                                                    <?php endforeach; ?>
                                                    <td class="text-center">
                                                        <a href="cargar_notas.php?curso_materia=<?php echo $r['id_curso_materia']; ?>"
                                                           class="btn btn-outline-primary btn-sm" title="Ir a cargar notas">
                                                            <i class="ri-edit-line"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <?php if (count($anuncios) > 1): ?>
        <script>
            // Configuración del carrusel de anuncios
            const anuncios = <?php echo json_encode($anuncios); ?>;
            let currentIndex = 0;
            const announcementContainer = document.getElementById('announcement-container');
            const announcementText = document.getElementById('announcement-text');
            const announcementIcon = document.querySelector('.announcement-icon');
            const announcementClose = document.querySelector('.announcement-close');

            function rotateAnnouncements() {
                // Animación de salida
                announcementText.classList.add('announcement-slide-exit');
                announcementText.classList.add('announcement-slide-exit-active');

                setTimeout(() => {
                    // Cambiar el mensaje y los colores
                    currentIndex = (currentIndex + 1) % anuncios.length;
                    const colorClass = currentIndex % 4;

                    // Actualizar contenido y clases de color
                    announcementText.textContent = anuncios[currentIndex].mensaje;

                    // Eliminar clases de color anteriores
                    announcementContainer.className = announcementContainer.className.replace(/\bannouncement-color-\d+\b/g, '');
                    announcementIcon.className = announcementIcon.className.replace(/\bannouncement-icon-\d+\b/g, '');
                    announcementText.className = announcementText.className.replace(/\bannouncement-text-\d+\b/g, '');
                    announcementClose.className = announcementClose.className.replace(/\bannouncement-close-\d+\b/g, '');

                    // Añadir nuevas clases de color
                    announcementContainer.classList.add(`announcement-color-${colorClass}`);
                    announcementIcon.classList.add(`announcement-icon-${colorClass}`);
                    announcementText.classList.add(`announcement-text-${colorClass}`);
                    announcementClose.classList.add(`announcement-close-${colorClass}`);

                    // Animación de entrada
                    announcementText.classList.remove('announcement-slide-exit');
                    announcementText.classList.remove('announcement-slide-exit-active');
                    announcementText.classList.add('announcement-slide-enter');

                    setTimeout(() => {
                        announcementText.classList.add('announcement-slide-enter-active');

                        setTimeout(() => {
                            announcementText.classList.remove('announcement-slide-enter');
                            announcementText.classList.remove('announcement-slide-enter-active');
                        }, 500);
                    }, 10);
                }, 500);
            }

            // Rotar anuncios cada 5 segundos
            if (anuncios.length > 1) {
                setInterval(rotateAnnouncements, 5000);
            }
        </script>
    <?php endif; ?>
    <script>
        document.getElementById('filtroCurso')?.addEventListener('change', function() {
            var val = this.value;
            document.querySelectorAll('#tabReportes tbody tr').forEach(function(tr) {
                tr.style.display = (!val || tr.dataset.curso === val) ? '' : 'none';
            });
        });
    </script>
</body>

</html>
