<?php
session_start();
require_once '../config/database.php';

// Verificar administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

// Obtener cursos iniciales
$stmt = $conn->query("
    SELECT c.id_curso, c.curso, c.paralelo
    FROM cursos c
    WHERE c.nivel = 'Inicial'
    ORDER BY c.curso, c.paralelo
");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cursos de Inicial</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link id="bootstrap-css" rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        /* ---- DARK MODE ---- */
        body { background-color: #181a1b; color: #eaeaea; }
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
            color: #c0d3f7;
            text-align: center;
            font-size: 1rem;
        }
        .table-cursos td {
            text-align: center;
            vertical-align: middle;
        }
        .table-cursos tr:hover {
            background: var(--tr-hover, #e3f2fd1a);
        }
        .btn-centralizador {
            background: #4682B4;
            color: #fff;
            border: none;
            font-weight: 600;
            border-radius: 5px;
            transition: background 0.2s, transform 0.2s;
        }
        .btn-centralizador:hover {
            background: #0099e6;
            color: #fff;
            transform: scale(1.05);
        }
        .title-box {
            border-left: 6px solid #4682B4;
            padding-left: 1rem;
            margin-bottom: 2rem;
        }
        .toggle-switch {
            display: flex; align-items: center; gap:7px;
            position: absolute; right: 32px; top: 32px;
        }
        .toggle-switch label {
            font-size: .95rem; font-weight: 600; color: #4682B4; cursor:pointer;
        }
        .toggle-switch input[type="checkbox"] {
            width: 28px; height: 16px; position: relative; appearance: none;
            background: #aaa; outline: none; border-radius: 20px; transition: background 0.2s;
        }
        .toggle-switch input[type="checkbox"]:checked { background: #4682B4; }
        .toggle-switch input[type="checkbox"]::after {
            content: '';
            position: absolute; top: 2px; left: 2px; width: 12px; height: 12px;
            background: #fff; border-radius: 50%; transition: left 0.2s;
        }
        .toggle-switch input[type="checkbox"]:checked::after { left: 14px; }

        /* ---- LIGHT MODE ---- */
        body:not(.dark-mode) {
            --content-bg: #fff;
            --table-bg: #f7fbff;
            --th-bg: #eaf6fb;
            --tr-hover: #e3f2fd;
        }
        body:not(.dark-mode) .table-cursos th {
            color: #4682B4;
            background: var(--th-bg);
            border-bottom: 2px solid #b9d6f2;
        }
        body:not(.dark-mode) .btn-centralizador {
            background: #1877c9;
            color: #fff;
        }
        body:not(.dark-mode) .btn-centralizador:hover {
            background: #0056b3;
            color: #e3f2fd;
        }
        body:not(.dark-mode) .title-box {
            border-left: 6px solid #1877c9;
        }
        body:not(.dark-mode) .toggle-switch label { color: #1877c9; }
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
                    <input type="checkbox" id="toggleMode" <?php if(isset($_COOKIE['darkmode']) && $_COOKIE['darkmode']=='on') echo "checked"; ?>>
                </div>
                <div class="content-wrapper">
                    <!-- Título Principal -->
                    <div class="title-box mb-4">
                        <h2 class="mb-0" style="color:#4682B4;">Cursos de Nivel Inicial</h2>
                        <small class="text-secondary">Seleccione el curso que desea visualizar:</small>
                    </div>

                    <!-- Tabla de Cursos -->
                    <div class="table-responsive">
                        <table class="table table-cursos table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">#</th>
                                    <th>Curso</th>
                                    <th>Centralizador</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cursos)): ?>
                                    <tr>
                                        <td colspan="3">
                                            <div class="alert alert-warning mb-0">
                                                No hay cursos de nivel inicial registrados.
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $n = 1; foreach ($cursos as $curso): ?>
                                    <tr>
                                        <td><?php echo $n++; ?></td>
                                        <td><?php echo htmlspecialchars("{$curso['curso']} {$curso['paralelo']}"); ?></td>
                                        <td>
                                            <a href="ver_c_inicial.php?id=<?php echo $curso['id_curso']; ?>" class="btn btn-centralizador">
                                                Ver Centralizador
                                            </a>
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
    <script>
        // Modo claro/oscuro con persistencia en cookie
        const toggle = document.getElementById('toggleMode');
        function setMode(dark) {
            if(dark) {
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
            if(document.cookie.indexOf('darkmode=on')!==-1) {
                document.body.classList.add('dark-mode');
                toggle.checked = true;
            }
        }
    </script>
</body>
</html>
