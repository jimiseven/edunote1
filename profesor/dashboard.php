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

// Obtener cantidad de bimestres configurados
$stmt = $conn->query("SELECT cantidad_bimestres FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
$cantidad_bimestres = $stmt->fetchColumn() ?: 3;

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
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>

            <!-- Contenido principal -->
            <main class="col-md-9 col-lg-10 px-0 main-content">
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

                <!-- Contenido -->
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
                                                    <td><?php echo htmlspecialchars($curso['nivel']); ?></td>
                                                    <td><?php echo htmlspecialchars($curso['curso']) . ' ' . htmlspecialchars($curso['paralelo']); ?></td>
                                                    <td><?php echo htmlspecialchars($curso['nombre_materia']); ?></td>
                                                    <td class="text-center">
                                                        <a href="cargar_notas.php?curso_materia=<?php echo htmlspecialchars($curso['id_curso_materia']); ?>"
                                                            class="btn btn-primary btn-sm btn-action">
                                                            Cargar
                                                        </a>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex flex-wrap gap-1 justify-content-center">
                                                            <?php
                                                            $bimestres_cargados = $curso['bimestres_cargados'] ? explode(',', $curso['bimestres_cargados']) : [];
                                                            for ($i = 1; $i <= $cantidad_bimestres; $i++): ?>
                                                                <span class="badge <?= in_array($i, $bimestres_cargados) ? 'bg-success' : 'bg-secondary' ?> status-badge">
                                                                    B<?= $i ?>
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
                        <div class="card-footer bg-white border-top-0 text-center text-md-end">
                            <a href="generar_respaldo.php" class="btn btn-secondary">Generar Respaldo</a>
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
</body>

</html>