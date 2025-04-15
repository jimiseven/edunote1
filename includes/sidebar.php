<?php
$role = $_SESSION['user_role'] ?? null;
$current = basename($_SERVER['PHP_SELF']); // Para resaltar la opción activa
function active($str, $current)
{
    return (strpos($current, $str) !== false) ? 'active' : '';
}
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <style>
        .sidebar-section {
            margin-bottom: 1.7rem;
        }

        .sidebar-section-title {
            padding: .75rem 1.2rem .5rem 1.2rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: #fff;
            background: linear-gradient(90deg, #1d3557 70%, #457b9d 100%);
            border-radius: 7px 7px 0 0;
            font-size: 1.05rem;
        }

        .sidebar-group-list {
            background: #222b3a;
            border-radius: 0 0 14px 14px;
            box-shadow: 0 2px 6px #00000011;
            padding-bottom: 7px;
        }

        .nav-link {
            color: #dee2e6 !important;
            font-weight: 500;
            padding: .7rem 1.6rem .7rem 2.1rem;
            border-left: 3px solid transparent;
            display: flex;
            align-items: center;
            transition: background .16s, color .18s, border-color .2s;
        }

        .nav-link.active,
        .nav-link:hover {
            color: #fff !important;
            background: #3270b4;
            border-left: 3px solid #fbbf24;
            font-weight: 600;
        }

        .nav-link .feather {
            margin-right: .9rem;
            opacity: .7;
        }

        .sidebar-logout .nav-link {
            color: #fff !important;
            background: #ec4747;
            font-weight: 600;
            border-radius: 0 0 12px 12px;
            margin-top: .7rem;
            border-left: 3px solid #e93232;
        }

        .sidebar-logout .nav-link:hover {
            background: #c21d1d;
            color: #fff;
        }

        @media (max-width: 991px) {
            .sidebar-section-title {
                font-size: 1rem;
                padding: .7rem 1rem .4rem 1rem;
            }

            .nav-link {
                padding: .65rem 1rem .65rem 1.5rem;
            }
        }
    </style>
    <div class="position-sticky pt-3">

        <?php if ($role == 1): // Administrador 
        ?>
            <div class="sidebar-section">
                <div class="sidebar-section-title">INICIAL</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('dash_iniciales', $current); ?>" href="dash_iniciales.php">
                            <span data-feather="user"></span>
                            Cursos Iniciales
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-title">PRIMARIA</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('dashboard_primaria', $current); ?>" href="dashboard_primaria.php">
                            <span data-feather="book"></span>
                            Cursos Primaria
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-title">SECUNDARIA</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('dashboard_secundaria', $current); ?>" href="dashboard_secundaria.php">
                            <span data-feather="layers"></span>
                            Cursos Secundaria
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Panel de Control</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('personal', $current); ?>" href="personal.php">
                            <span data-feather="layers"></span>
                            Personal
                        </a>
                    </li>
                </ul>
            </div>
        <?php elseif ($role == 2): // Profesor 
        ?>
            <div class="sidebar-section">
                <div class="sidebar-section-title">CURSOS</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('dashboard', $current); ?>" href="dashboard.php">
                            <span data-feather="book-open"></span>
                            Mis Cursos
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- CERRAR SESIÓN (visible para todos) -->
        <div class="sidebar-logout">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link text-danger" href="../includes/logout.php">
                        <span data-feather="log-out"></span>
                        Cerrar Sesión
                    </a>

                </li>
            </ul>
        </div>
    </div>
    <script>
        // Activa feather icons
        if (window.feather) feather.replace();
    </script>
</nav>