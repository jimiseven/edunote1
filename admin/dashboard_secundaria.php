<?php
session_start();
require_once '../config/database.php';

// Verificar administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

// Obtener cursos de secundaria
$stmt = $conn->query("
    SELECT c.id_curso, c.curso, c.paralelo 
    FROM cursos c
    WHERE c.nivel = 'Secundaria'
    ORDER BY c.curso, c.paralelo
");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Cursos de Secundaria</title>
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
                    <div class="title-box mb-4">
                        <h2 class="mb-0" style="color:#99b898;">Cursos de Secundaria</h2>
                        <small class="text-secondary">Seleccione el curso que desea visualizar:</small>
                    </div>
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
                                                No hay cursos de secundaria registrados.
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
                                                <a href="ver_curso.php?id=<?php echo $curso['id_curso']; ?>" class="btn btn-centralizador">
                                                    Ver Centralizador
                                                </a>
                                                <a href="boletin_secundaria.php?id_curso=<?= $curso['id_curso'] ?>"
                                                    class="btn btn-success btn-action">
                                                    <i class="ri-printer-line"></i> Boletín
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