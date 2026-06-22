<?php
session_start();
require_once '../config/database.php';

// Verificar que sea administrador
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 4], true)) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

$esAdmin = (int)$_SESSION['user_role'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_curso') {
    $idEstudiante = isset($_POST['id_estudiante']) ? (int)$_POST['id_estudiante'] : 0;
    $nuevoCurso = isset($_POST['id_curso']) ? (int)$_POST['id_curso'] : 0;

    $toastMsg = null;

    if ($idEstudiante > 0 && $nuevoCurso > 0) {
        try {
            $check = $conn->prepare('SELECT id_estudiante FROM estudiantes WHERE id_estudiante = ?');
            $check->execute([$idEstudiante]);
            if ($check->fetch(PDO::FETCH_ASSOC)) {
                $checkCurso = $conn->prepare('SELECT id_curso FROM cursos WHERE id_curso = ?');
                $checkCurso->execute([$nuevoCurso]);
                if ($checkCurso->fetch(PDO::FETCH_ASSOC)) {
                    $upd = $conn->prepare('UPDATE estudiantes SET id_curso = ? WHERE id_estudiante = ?');
                    $upd->execute([$nuevoCurso, $idEstudiante]);

                    $stmtEstNombre = $conn->prepare("SELECT TRIM(CONCAT(COALESCE(apellido_paterno,''), ' ', COALESCE(apellido_materno,''), ' ', COALESCE(nombres,''))) AS estudiante FROM estudiantes WHERE id_estudiante = ? LIMIT 1");
                    $stmtEstNombre->execute([$idEstudiante]);
                    $rowEst = $stmtEstNombre->fetch(PDO::FETCH_ASSOC);

                    $stmtCursoNombre = $conn->prepare("SELECT CONCAT(nivel, ' ', curso, '° ', paralelo) AS curso FROM cursos WHERE id_curso = ? LIMIT 1");
                    $stmtCursoNombre->execute([$nuevoCurso]);
                    $rowCurso = $stmtCursoNombre->fetch(PDO::FETCH_ASSOC);

                    $nombreEst = $rowEst['estudiante'] ?? '';
                    $nombreCurso = $rowCurso['curso'] ?? '';
                    if ($nombreEst !== '' && $nombreCurso !== '') {
                        $toastMsg = 'Se cambió al estudiante "' . $nombreEst . '" al curso "' . $nombreCurso . '"';
                    }
                }
            }
        } catch (PDOException $e) {
        }
    }

    if ($toastMsg) {
        $_SESSION['toast_message'] = $toastMsg;
    }

    header('Location: dashboard_primaria.php');
    exit();
}

$stmtTodosCursos = $conn->query("SELECT id_curso, CONCAT(nivel, ' ', curso, '° ', paralelo) AS nombre FROM cursos ORDER BY nivel, curso, paralelo");
$todosCursos = $stmtTodosCursos->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los cursos de primaria
$stmt = $conn->query("
    SELECT 
        c.id_curso,
        c.curso,
        c.paralelo,
        COALESCE(SUM(CASE WHEN e.genero = 'Masculino' THEN 1 ELSE 0 END), 0) AS total_masculino,
        COALESCE(SUM(CASE WHEN e.genero = 'Femenino' THEN 1 ELSE 0 END), 0) AS total_femenino,
        COALESCE(COUNT(e.id_estudiante), 0) AS total_estudiantes,

        COALESCE(SUM(CASE WHEN e.estado_1 = 'EFECTIVO' THEN 1 ELSE 0 END), 0) AS total_efectivo,
        COALESCE(SUM(CASE WHEN e.estado_1 = 'NO_EFECTIVO' THEN 1 ELSE 0 END), 0) AS total_no_efectivo,

        COALESCE(SUM(CASE WHEN e.estado_1 = 'EFECTIVO' AND e.estado_2 = 'APROBADO' THEN 1 ELSE 0 END), 0) AS efectivo_aprobado,
        COALESCE(SUM(CASE WHEN e.estado_1 = 'EFECTIVO' AND e.estado_2 = 'REPROBADO' THEN 1 ELSE 0 END), 0) AS efectivo_reprobado,
        COALESCE(SUM(CASE WHEN e.estado_1 = 'EFECTIVO' AND e.estado_2 IS NULL THEN 1 ELSE 0 END), 0) AS efectivo_sin_estado2,

        COALESCE(SUM(CASE WHEN e.estado_1 = 'NO_EFECTIVO' AND e.estado_2 = 'NO_INCORPORADO' THEN 1 ELSE 0 END), 0) AS no_efectivo_no_incorporado,
        COALESCE(SUM(CASE WHEN e.estado_1 = 'NO_EFECTIVO' AND e.estado_2 = 'RETIRO_ABANDONO' THEN 1 ELSE 0 END), 0) AS no_efectivo_retiro_abandono,
        COALESCE(SUM(CASE WHEN e.estado_1 = 'NO_EFECTIVO' AND e.estado_2 = 'RETIRO_TRASLADO' THEN 1 ELSE 0 END), 0) AS no_efectivo_retiro_traslado,
        COALESCE(SUM(CASE WHEN e.estado_1 = 'NO_EFECTIVO' AND e.estado_2 IS NULL THEN 1 ELSE 0 END), 0) AS no_efectivo_sin_estado2,

        COALESCE(SUM(CASE WHEN e.estado_1 IS NULL THEN 1 ELSE 0 END), 0) AS sin_estado1,
        COALESCE(SUM(CASE WHEN e.estado_1 IS NULL AND e.estado_2 = 'APROBADO' THEN 1 ELSE 0 END), 0) AS sin_estado1_aprobado,
        COALESCE(SUM(CASE WHEN e.estado_1 IS NULL AND e.estado_2 = 'REPROBADO' THEN 1 ELSE 0 END), 0) AS sin_estado1_reprobado,
        COALESCE(SUM(CASE WHEN e.estado_1 IS NULL AND e.estado_2 = 'NO_INCORPORADO' THEN 1 ELSE 0 END), 0) AS sin_estado1_no_incorporado,
        COALESCE(SUM(CASE WHEN e.estado_1 IS NULL AND e.estado_2 = 'RETIRO_ABANDONO' THEN 1 ELSE 0 END), 0) AS sin_estado1_retiro_abandono,
        COALESCE(SUM(CASE WHEN e.estado_1 IS NULL AND e.estado_2 = 'RETIRO_TRASLADO' THEN 1 ELSE 0 END), 0) AS sin_estado1_retiro_traslado,
        COALESCE(SUM(CASE WHEN e.estado_1 IS NULL AND e.estado_2 IS NULL THEN 1 ELSE 0 END), 0) AS sin_estado1_sin_estado2
    FROM cursos c
    LEFT JOIN estudiantes e ON e.id_curso = c.id_curso
    WHERE c.nivel = 'Primaria'
    GROUP BY c.id_curso, c.curso, c.paralelo
    ORDER BY c.curso, c.paralelo
");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$estudiantesPorCurso = [];
if (!empty($cursos)) {
    $idsCursos = array_map(static function ($c) {
        return (int)$c['id_curso'];
    }, $cursos);

    $placeholders = implode(',', array_fill(0, count($idsCursos), '?'));
    $stmtEst = $conn->prepare("
        SELECT 
            id_estudiante,
            id_curso,
            nombres,
            apellido_paterno,
            apellido_materno,
            carnet_identidad,
            genero,
            estado_1
        FROM estudiantes
        WHERE id_curso IN ($placeholders)
        ORDER BY apellido_paterno, apellido_materno, nombres
    ");
    $stmtEst->execute($idsCursos);
    $rowsEst = $stmtEst->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rowsEst as $row) {
        $idCurso = (int)$row['id_curso'];
        if (!isset($estudiantesPorCurso[$idCurso])) {
            $estudiantesPorCurso[$idCurso] = [];
        }
        $estudiantesPorCurso[$idCurso][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Cursos de Primaria</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link id="bootstrap-css" rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        body {
            background-color: #121212;
            color: #eaeaea;
        }

        .container-fluid { min-height: 100dvh; }

        main {
            display: flex;
            flex-direction: column;
            min-height: 100dvh;
            width: 100%;
        }

        .content-wrapper {
            background: var(--content-bg, #1f1f1f);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            margin-top: 25px;
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .table-responsive.js-dashboard-table {
            flex: 1;
            min-height: 0;
            overflow: auto;
        }

        .table-cursos {
            background: var(--table-bg, #1a1a1a);
        }

        .table-cursos th {
            background: var(--th-bg, #232323);
            color: #99b898;
            text-align: center;
            font-size: 1rem;
        }

        .table-cursos td {
            text-align: center;
            vertical-align: middle;
        }

        .table-cursos tr:hover {
            background: var(--tr-hover, #282828);
        }

        .btn-centralizador {
            background: #99b898;
            color: #222;
            border: none;
            font-weight: 600;
            border-radius: 5px;
            transition: background 0.2s, transform 0.2s;
        }

        .btn-centralizador:hover {
            background: #4c5c68;
            color: #fff;
            transform: scale(1.05);
        }

        .title-box {
            border-left: 6px solid #99b898;
            padding-left: 1rem;
            margin-bottom: 2rem;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 7px;
            position: absolute;
            right: 32px;
            top: 32px;
        }

        .toggle-switch label {
            font-size: .95rem;
            font-weight: 600;
            color: #99b898;
            cursor: pointer;
        }

        .toggle-switch input[type="checkbox"] {
            width: 28px;
            height: 16px;
            position: relative;
            appearance: none;
            background: #aaa;
            outline: none;
            border-radius: 20px;
            transition: background 0.2s;
        }

        .toggle-switch input[type="checkbox"]:checked {
            background: #99b898;
        }

        .toggle-switch input[type="checkbox"]::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 12px;
            height: 12px;
            background: #fff;
            border-radius: 50%;
            transition: left 0.2s;
        }

        .toggle-switch input[type="checkbox"]:checked::after {
            left: 14px;
        }

        .student-stats {
            display: inline-flex;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            font-weight: 600;
        }

        .student-stats .stat-badge {
            padding: 0.35rem 0.55rem;
            border-radius: 999px;
            font-size: 0.85rem;
            line-height: 1;
            border: 1px solid rgba(0, 0, 0, 0.12);
            background: rgba(255, 255, 255, 0.08);
        }

        body:not(.dark-mode) .student-stats .stat-badge {
            background: rgba(17, 48, 94, 0.06);
            border-color: rgba(17, 48, 94, 0.14);
        }

        .student-stats .stat-m {
            color: #0dcaf0;
        }

        .student-stats .stat-f {
            color: #d63384;
        }

        .student-stats .stat-t {
            color: #99b898;
        }

        body:not(.dark-mode) {
            --content-bg: #f8f9fa;
            --table-bg: #fff;
            --th-bg: #e9ecef;
            --tr-hover: #e0eafc;
        }

        body.dark-mode {
            --content-bg: #1f1f1f;
            --table-bg: #1a1a1a;
            --th-bg: #232323;
            --tr-hover: #282828;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <main class="w-100 px-md-4 position-relative">

                <!-- Toggle Modo Claro/Oscuro -->
                <div class="toggle-switch">

                    <label for="toggleMode">☀️/🌙</label>
                    <input type="checkbox" id="toggleMode" <?php if (isset($_COOKIE['darkmode']) && $_COOKIE['darkmode'] == 'on') echo "checked"; ?>>
                </div>
                <div class="content-wrapper">
                    <!-- Título Principal -->
                    <div class="title-box mb-4">
                        <h2 class="mb-0" style="color:#99b898;">Cursos de Primaria</h2>
                        <small class="text-secondary">Seleccione el curso que desea visualizar:</small>
                        <?php if ($esAdmin): ?>
                        <div class="mt-3">
                            <button type="button" id="btnDescargarZipPrimaria" class="btn btn-danger btn-sm">
                                <i class="ri-file-zip-line"></i> Descargar ZIP Boletines Primaria
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tabla de Cursos -->
                    <div class="table-responsive js-dashboard-table">
                        <table class="table table-cursos table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">#</th>
                                    <th>Curso</th>
                                    <th>Estudiantes</th>
                                    <th>Centralizador</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cursos)): ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="alert alert-warning mb-0">
                                                No hay cursos de primaria registrados.
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $n = 1;
                                    foreach ($cursos as $curso): ?>
                                        <tr>
                                            <td><?php echo $n++; ?></td>
                                            <td><?php echo htmlspecialchars("{$curso['curso']} {$curso['paralelo']}"); ?></td>
                                            <td>
                                                <?php
                                                $m = (int)($curso['total_masculino'] ?? 0);
                                                $f = (int)($curso['total_femenino'] ?? 0);
                                                $t = (int)($curso['total_estudiantes'] ?? 0);
                                                echo '<div class="student-stats">'
                                                    . '<span class="stat-badge stat-m">M: ' . $m . '</span>'
                                                    . '<span class="stat-badge stat-f">F: ' . $f . '</span>'
                                                    . '<span class="stat-badge stat-t">T: ' . $t . '</span>'
                                                    . '</div>';
                                                ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2 justify-content-center flex-wrap">
                                                    <a href="ver_curso.php?id=<?php echo $curso['id_curso']; ?>" class="btn btn-centralizador">
                                                        Ver Centralizador
                                                    </a>
                                                    <?php if ($esAdmin): ?>
                                                    <a href="boletin_primaria.php?id_curso=<?= $curso['id_curso'] ?>"
                                                        class="btn btn-success btn-action">
                                                        <i class="ri-printer-line"></i> Boletín
                                                    </a>
                                                    <a href="asistencia_excel.php?id_curso=<?= $curso['id_curso'] ?>" class="btn btn-info btn-action">
                                                        Asistencia
                                                    </a>
                                                    <a href="nomina_excel.php?id_curso=<?= $curso['id_curso'] ?>" class="btn btn-warning btn-action">
                                                        Nómina
                                                    </a>

                                                    <?php $modalInfoId = 'modalInfoCurso' . (int)$curso['id_curso']; ?>
                                                    <button type="button" class="btn btn-secondary btn-action" data-bs-toggle="modal" data-bs-target="#<?php echo $modalInfoId; ?>">
                                                        Info
                                                    </button>

                                                    <div class="modal fade" id="<?php echo $modalInfoId; ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">
                                                                        Estudiantes - <?php echo htmlspecialchars("{$curso['curso']} {$curso['paralelo']}"); ?>
                                                                    </h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-bordered align-middle mb-0">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th style="width: 70px;">#</th>
                                                                                    <th>Estudiante</th>
                                                                                    <th style="width: 160px;">Estado</th>
                                                                                    <th style="width: 140px;">Género</th>
                                                                                    <th style="width: 200px;">Acción</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php
                                                                                $idCursoActual = (int)$curso['id_curso'];
                                                                                $lista = $estudiantesPorCurso[$idCursoActual] ?? [];
                                                                                if (empty($lista)):
                                                                                ?>
                                                                                    <tr>
                                                                                        <td colspan="5" class="text-center text-muted">Sin estudiantes registrados</td>
                                                                                    </tr>
                                                                                <?php else:
                                                                                    $i = 1;
                                                                                    foreach ($lista as $est):
                                                                                        $nombreCompleto = trim(($est['apellido_paterno'] ?? '') . ' ' . ($est['apellido_materno'] ?? '') . ' ' . ($est['nombres'] ?? ''));
                                                                                ?>
                                                                                        <tr>
                                                                                            <td class="text-center"><?php echo $i++; ?></td>
                                                                                            <td><?php echo htmlspecialchars($nombreCompleto); ?></td>
                                                                                            <td class="text-center"><?php echo htmlspecialchars($est['estado_1'] ?? '-'); ?></td>
                                                                                            <td class="text-center"><?php echo htmlspecialchars($est['genero'] ?? ''); ?></td>
                                                                                            <td class="text-center">
                                                                                                <div class="d-flex gap-2 justify-content-center flex-wrap">
                                                                                                    <a class="btn btn-sm btn-primary" href="editar_estudiante.php?id=<?php echo (int)$est['id_estudiante']; ?>&return=dashboard_primaria.php">
                                                                                                        Ver
                                                                                                    </a>

                                                                                                    <button
                                                                                                        type="button"
                                                                                                        class="btn btn-sm btn-warning"
                                                                                                        data-bs-toggle="modal"
                                                                                                        data-bs-target="#modalCambiarCursoGlobal"
                                                                                                        data-estudiante-id="<?php echo (int)$est['id_estudiante']; ?>"
                                                                                                        data-estudiante-nombre="<?php echo htmlspecialchars($nombreCompleto); ?>"
                                                                                                        data-estudiante-curso="<?php echo (int)$est['id_curso']; ?>">
                                                                                                        Cambiar curso
                                                                                                    </button>
                                                                                                </div>
                                                                                            </td>
                                                                                        </tr>
                                                                                    <?php endforeach; endif; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php if (!empty($_SESSION['toast_message'])): ?>
        <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
            <div id="toastCambioCurso" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3500">
                <div class="d-flex">
                    <div class="toast-body">
                        <?php echo htmlspecialchars($_SESSION['toast_message']); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['toast_message']); ?>
    <?php endif; ?>

    <div class="modal fade" id="modalCambiarCursoGlobal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar curso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cambiar_curso">
                        <input type="hidden" name="id_estudiante" id="cambiarCursoIdEstudiante" value="">

                        <div class="mb-2 text-start">
                            <div class="fw-semibold">Estudiante</div>
                            <div class="text-muted small" id="cambiarCursoNombreEstudiante"></div>
                        </div>

                        <div class="mb-2 text-start">
                            <label class="form-label">Nuevo curso</label>
                            <select class="form-select" name="id_curso" id="cambiarCursoSelect" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($todosCursos as $cOpt): ?>
                                    <option value="<?php echo (int)$cOpt['id_curso']; ?>">
                                        <?php echo htmlspecialchars($cOpt['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        // Modo claro/oscuro con persistencia en cookie
        const toggle = document.getElementById('toggleMode');
        const tableViewport = document.querySelector('.table-responsive.js-dashboard-table');

        function ajustarAltoTabla() {
            if (!tableViewport) return;
            const top = tableViewport.getBoundingClientRect().top;
            const alto = Math.max(260, Math.floor(window.innerHeight - top - 12));
            tableViewport.style.height = alto + 'px';
            tableViewport.style.maxHeight = alto + 'px';
        }

        function setMode(dark) {
            if (dark) {
                document.body.classList.add('dark-mode');
                document.cookie = "darkmode=on;path=/;max-age=31536000";
            } else {
                document.body.classList.remove('dark-mode');
                document.cookie = "darkmode=off;path=/;max-age=31536000";
            }
        }
        toggle.addEventListener('change', function() {
            setMode(this.checked);
            requestAnimationFrame(ajustarAltoTabla);
        });
        // Estado inicial al cargar
        window.onload = function() {
            if (document.cookie.indexOf('darkmode=on') !== -1) {
                document.body.classList.add('dark-mode');
                toggle.checked = true;
            }
            ajustarAltoTabla();
        }
        window.addEventListener('resize', ajustarAltoTabla);
        window.addEventListener('orientationchange', ajustarAltoTabla);
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script>
        const cursosZipPrimaria = <?php echo json_encode(array_map(static function ($c) {
            return [
                'id_curso' => (int)$c['id_curso'],
                'curso' => (string)($c['curso'] ?? ''),
                'paralelo' => (string)($c['paralelo'] ?? '')
            ];
        }, $cursos), JSON_UNESCAPED_UNICODE); ?>;

        function sanitizarNombreArchivo(nombre) {
            return String(nombre || 'archivo').replace(/[^a-z0-9-_ ]/gi, '_').replace(/\s+/g, '_');
        }

        function obtenerPdfBoletinPrimaria(idCurso) {
            return new Promise((resolve, reject) => {
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = `boletin_primaria.php?id_curso=${encodeURIComponent(idCurso)}`;

                const limpiar = () => {
                    if (iframe.parentNode) {
                        iframe.parentNode.removeChild(iframe);
                    }
                };

                const timeoutId = window.setTimeout(() => {
                    limpiar();
                    reject(new Error('Tiempo de espera agotado al generar PDF de primaria'));
                }, 90000);

                iframe.onload = () => {
                    try {
                        const win = iframe.contentWindow;
                        if (!win || typeof win.generarBoletinesPDF !== 'function') {
                            throw new Error('No se encontró generador de boletines en primaria');
                        }
                        const blob = win.generarBoletinesPDF({ download: false });
                        const esBlobValido = blob && typeof blob === 'object' && typeof blob.size === 'number' && typeof blob.type === 'string';
                        if (!esBlobValido) {
                            throw new Error('No se pudo obtener el PDF del curso');
                        }
                        window.clearTimeout(timeoutId);
                        limpiar();
                        resolve(blob);
                    } catch (err) {
                        window.clearTimeout(timeoutId);
                        limpiar();
                        reject(err);
                    }
                };

                document.body.appendChild(iframe);
            });
        }

        async function descargarZipBoletinesPrimaria() {
            if (typeof JSZip === 'undefined') {
                alert('No se pudo cargar la librería para generar el ZIP.');
                return;
            }

            if (!Array.isArray(cursosZipPrimaria) || cursosZipPrimaria.length === 0) {
                alert('No hay cursos de primaria para exportar.');
                return;
            }

            const btn = document.getElementById('btnDescargarZipPrimaria');
            const textoOriginal = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Generando ZIP...';
            }

            try {
                const zip = new JSZip();
                const raiz = zip.folder('Boletines_Primaria');
                for (const curso of cursosZipPrimaria) {
                    const nombreCurso = sanitizarNombreArchivo(`${curso.curso}_${curso.paralelo}`);
                    const pdfBlob = await obtenerPdfBoletinPrimaria(curso.id_curso);
                    const carpetaCurso = raiz.folder(nombreCurso);
                    carpetaCurso.file(`Boletines_${nombreCurso}.pdf`, pdfBlob);
                }

                const zipBlob = await zip.generateAsync({ type: 'blob' });
                const enlace = document.createElement('a');
                const url = URL.createObjectURL(zipBlob);
                enlace.href = url;
                enlace.download = `Boletines_Primaria_${new Date().getFullYear()}.zip`;
                document.body.appendChild(enlace);
                enlace.click();
                enlace.remove();
                URL.revokeObjectURL(url);
            } catch (error) {
                alert(`Error al generar ZIP de primaria: ${error.message}`);
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = textoOriginal;
                }
            }
        }

        document.getElementById('btnDescargarZipPrimaria')?.addEventListener('click', descargarZipBoletinesPrimaria);
    </script>
    <script>
        const modalCambiarCursoGlobal = document.getElementById('modalCambiarCursoGlobal');
        let lastInfoModalId = null;

        modalCambiarCursoGlobal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const idEst = button.getAttribute('data-estudiante-id') || '';
            const nombre = button.getAttribute('data-estudiante-nombre') || '';
            const idCurso = button.getAttribute('data-estudiante-curso') || '';

            const parentModal = button.closest('.modal');
            lastInfoModalId = parentModal ? parentModal.id : null;

            const inputId = document.getElementById('cambiarCursoIdEstudiante');
            const lblNombre = document.getElementById('cambiarCursoNombreEstudiante');
            const selectCurso = document.getElementById('cambiarCursoSelect');

            inputId.value = idEst;
            lblNombre.textContent = nombre;
            selectCurso.value = idCurso;
        });

        modalCambiarCursoGlobal.addEventListener('hidden.bs.modal', function() {
            if (!lastInfoModalId) {
                return;
            }

            const infoEl = document.getElementById(lastInfoModalId);
            if (!infoEl) {
                return;
            }

            const infoModal = bootstrap.Modal.getOrCreateInstance(infoEl);
            infoModal.show();
        });
    </script>
    <script>
        const toastEl = document.getElementById('toastCambioCurso');
        if (toastEl) {
            const toast = bootstrap.Toast.getOrCreateInstance(toastEl);
            toast.show();
        }
    </script>
</body>

</html>
