<?php
session_start();
require_once '../config/database.php';

// Verificar que sea administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

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

        .content-wrapper {
            background: var(--content-bg, #1f1f1f);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            margin-top: 25px;
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

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 position-relative">

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
                    </div>

                    <!-- Tabla de Cursos -->
                    <div class="table-responsive">
                        <table class="table table-cursos table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">#</th>
                                    <th>Curso</th>
                                    <th>Estudiantes</th>
                                    <th>Estados</th>
                                    <th>Centralizador</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cursos)): ?>
                                    <tr>
                                        <td colspan="5">
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
                                                <?php
                                                $modalId = 'modalEstadosCurso' . (int)$curso['id_curso'];

                                                $totalEfectivo = (int)($curso['total_efectivo'] ?? 0);
                                                $totalNoEfectivo = (int)($curso['total_no_efectivo'] ?? 0);

                                                $efectivoAprobado = (int)($curso['efectivo_aprobado'] ?? 0);
                                                $efectivoReprobado = (int)($curso['efectivo_reprobado'] ?? 0);
                                                $efectivoSinEstado2 = (int)($curso['efectivo_sin_estado2'] ?? 0);

                                                $noEfectivoNoIncorporado = (int)($curso['no_efectivo_no_incorporado'] ?? 0);
                                                $noEfectivoRetiroAbandono = (int)($curso['no_efectivo_retiro_abandono'] ?? 0);
                                                $noEfectivoRetiroTraslado = (int)($curso['no_efectivo_retiro_traslado'] ?? 0);
                                                $noEfectivoSinEstado2 = (int)($curso['no_efectivo_sin_estado2'] ?? 0);

                                                $sinEstado1 = (int)($curso['sin_estado1'] ?? 0);
                                                $sinEstado1Aprobado = (int)($curso['sin_estado1_aprobado'] ?? 0);
                                                $sinEstado1Reprobado = (int)($curso['sin_estado1_reprobado'] ?? 0);
                                                $sinEstado1NoIncorporado = (int)($curso['sin_estado1_no_incorporado'] ?? 0);
                                                $sinEstado1RetiroAbandono = (int)($curso['sin_estado1_retiro_abandono'] ?? 0);
                                                $sinEstado1RetiroTraslado = (int)($curso['sin_estado1_retiro_traslado'] ?? 0);
                                                $sinEstado1SinEstado2 = (int)($curso['sin_estado1_sin_estado2'] ?? 0);

                                                $totalEstudiantes = (int)($curso['total_estudiantes'] ?? 0);
                                                ?>

                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>">
                                                    Ver
                                                </button>

                                                <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">
                                                                    Estados - <?php echo htmlspecialchars("{$curso['curso']} {$curso['paralelo']}"); ?>
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row g-3">
                                                                    <div class="col-md-6">
                                                                        <div class="border rounded p-3">
                                                                            <h6 class="mb-3">EFECTIVO</h6>
                                                                            <div class="d-flex justify-content-between">
                                                                                <span>APROBADO</span>
                                                                                <span class="fw-bold"><?php echo $efectivoAprobado; ?></span>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between">
                                                                                <span>REPROBADO</span>
                                                                                <span class="fw-bold"><?php echo $efectivoReprobado; ?></span>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between">
                                                                                <span>SIN ESTADO 2</span>
                                                                                <span class="fw-bold"><?php echo $efectivoSinEstado2; ?></span>
                                                                            </div>
                                                                            <hr class="my-2">
                                                                            <div class="d-flex justify-content-between">
                                                                                <span class="fw-semibold">Subtotal EFECTIVO</span>
                                                                                <span class="fw-bold"><?php echo $totalEfectivo; ?></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="border rounded p-3">
                                                                            <h6 class="mb-3">NO_EFECTIVO</h6>
                                                                            <div class="d-flex justify-content-between">
                                                                                <span>NO_INCORPORADO</span>
                                                                                <span class="fw-bold"><?php echo $noEfectivoNoIncorporado; ?></span>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between">
                                                                                <span>RETIRO_ABANDONO</span>
                                                                                <span class="fw-bold"><?php echo $noEfectivoRetiroAbandono; ?></span>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between">
                                                                                <span>RETIRO_TRASLADO</span>
                                                                                <span class="fw-bold"><?php echo $noEfectivoRetiroTraslado; ?></span>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between">
                                                                                <span>SIN ESTADO 2</span>
                                                                                <span class="fw-bold"><?php echo $noEfectivoSinEstado2; ?></span>
                                                                            </div>
                                                                            <hr class="my-2">
                                                                            <div class="d-flex justify-content-between">
                                                                                <span class="fw-semibold">Subtotal NO_EFECTIVO</span>
                                                                                <span class="fw-bold"><?php echo $totalNoEfectivo; ?></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <div class="border rounded p-3 d-flex justify-content-between align-items-center">
                                                                            <span class="fw-semibold">Total estudiantes</span>
                                                                            <span class="fw-bold fs-5"><?php echo $totalEstudiantes; ?></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <div class="border rounded p-3">
                                                                            <h6 class="mb-3">SIN ESTADO 1</h6>
                                                                            <div class="row g-2">
                                                                                <div class="col-md-6">
                                                                                    <div class="d-flex justify-content-between">
                                                                                        <span>APROBADO</span>
                                                                                        <span class="fw-bold"><?php echo $sinEstado1Aprobado; ?></span>
                                                                                    </div>
                                                                                    <div class="d-flex justify-content-between">
                                                                                        <span>REPROBADO</span>
                                                                                        <span class="fw-bold"><?php echo $sinEstado1Reprobado; ?></span>
                                                                                    </div>
                                                                                    <div class="d-flex justify-content-between">
                                                                                        <span>SIN ESTADO 2</span>
                                                                                        <span class="fw-bold"><?php echo $sinEstado1SinEstado2; ?></span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="d-flex justify-content-between">
                                                                                        <span>NO_INCORPORADO</span>
                                                                                        <span class="fw-bold"><?php echo $sinEstado1NoIncorporado; ?></span>
                                                                                    </div>
                                                                                    <div class="d-flex justify-content-between">
                                                                                        <span>RETIRO_ABANDONO</span>
                                                                                        <span class="fw-bold"><?php echo $sinEstado1RetiroAbandono; ?></span>
                                                                                    </div>
                                                                                    <div class="d-flex justify-content-between">
                                                                                        <span>RETIRO_TRASLADO</span>
                                                                                        <span class="fw-bold"><?php echo $sinEstado1RetiroTraslado; ?></span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <hr class="my-2">
                                                                            <div class="d-flex justify-content-between">
                                                                                <span class="fw-semibold">Subtotal SIN ESTADO 1</span>
                                                                                <span class="fw-bold"><?php echo $sinEstado1; ?></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2 justify-content-center flex-wrap">
                                                    <a href="ver_curso.php?id=<?php echo $curso['id_curso']; ?>" class="btn btn-centralizador">
                                                        Ver Centralizador
                                                    </a>
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
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        // Modo claro/oscuro con persistencia en cookie
        const toggle = document.getElementById('toggleMode');

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
        });
        // Estado inicial al cargar
        window.onload = function() {
            if (document.cookie.indexOf('darkmode=on') !== -1) {
                document.body.classList.add('dark-mode');
                toggle.checked = true;
            }
        }
    </script>
</body>

</html>