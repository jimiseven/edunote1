<?php
$role = $_SESSION['user_role'] ?? null;
$current = basename($_SERVER['PHP_SELF']);
function active($str, $current)
{
    if (isset($_SESSION['force_active']) && $str === $_SESSION['force_active']) {
        return 'active';
    }
    return (strpos($current, $str) !== false) ? 'active' : '';
}
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse shadow" style="background:#181f2c; min-height:100vh;">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');

        #sidebarMenu {
            font-family: 'Inter', Arial, sans-serif !important;
            letter-spacing: 0.01em;
            display: flex;
            flex-direction: column;
        }

        /* Tamaños de fuente reducidos */
        .sidebar-brand {
            font-size: 0.95rem;
        }

        .sidebar-brand span {
            font-size: 0.93rem;
        }

        .sidebar-search-input {
            font-size: 0.82rem;
        }

        .sidebar-section-title {
            font-size: 0.8rem;
        }

        .nav-link {
            font-size: 0.83rem;
        }

        .sidebar-logout .nav-link {
            font-size: 0.85rem;
        }

        .sidebar-user {
            font-size: 0.85rem;
            color: #ffffff !important;
            /* Texto blanco */
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: auto;
            /* Lo coloca al final */
            border-top: 1px solid #2a3547;
        }

        .logo-icon {
            font-size: 1rem !important;
        }

        /* Resto de estilos (manteniendo tu diseño original) */
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 1rem 1.2rem 0.8rem;
            font-weight: 600;
            color: #4abff9;
            border-bottom: 2px solid #202f47;
            margin-bottom: 1rem;
        }

        .sidebar-brand .logo-icon {
            width: 34px;
            height: 34px;
            background: linear-gradient(135deg, #388cff 40%, #4abff9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            box-shadow: 0 2px 9px #3685bd24;
        }

        .sidebar-brand span {
            color: #fff;
            font-weight: 600;
            letter-spacing: .03em;
        }

        .sidebar-search-box {
            padding: 0 1.2rem 0.9rem;
        }

        .sidebar-search-input {
            background: #1e2638;
            border: 1.5px solid #24304a;
            border-radius: 8px;
            color: #a8b5cc;
            width: 100%;
            padding: 7px 14px;
            transition: border .15s;
        }

        .sidebar-search-input:focus {
            border-color: #4abff9;
            outline: none;
        }

        .sidebar-section-title {
            padding: 0.45rem 1.2rem 0.3rem 1.2rem;
            font-weight: 600;
            color: #77cfff;
            margin-top: 15px;
            margin-bottom: 2px;
            border-left: 3px solid #4abff9;
        }

        .nav-link {
            color: #cfd6ee !important;
            font-weight: 500;
            padding: 0.55rem 1rem 0.55rem 1.8rem;
            border-radius: 6px;
            margin: 1px 0;
            border-left: 2px solid transparent;
            transition: all .15s;
        }

        .nav-link.active,
        .nav-link:hover {
            color: #fff !important;
            background: #242f49;
            border-left: 2px solid #49f0bd;
            font-weight: 500;
        }

        .nav-link .feather {
            margin-right: 0.7rem;
            opacity: .83;
            width: 16px;
            height: 16px;
        }

        .sidebar-bottom {
            padding: 1rem 0 0.4rem;
            margin-top: auto;
            /* Empuja todo hacia arriba */
        }

        .sidebar-logout .nav-link {
            background: linear-gradient(90deg, #0ba360 0%, #3cba92 100%);
            color: #fff !important;
            font-weight: 500;
            border-radius: 6px;
            padding: 0.55rem;
            width: 72%;
            font-size: 0.82rem;
        }

        /* Ajustes responsive adicionales */
        @media (max-width: 1200px) {
            .nav-link {
                padding-left: 1.6rem;
            }
        }

        @media (max-width: 991px) {
            .sidebar-brand {
                padding: 0.8rem 1rem 0.6rem;
            }

            .sidebar-section-title {
                padding-left: 1rem;
            }

            .nav-link {
                padding-left: 1.4rem;
            }

            .sidebar-search-box {
                padding: 0 1rem 0.8rem;
            }
        }
    </style>
    <div class="position-sticky pt-0" style="height: 100vh; display: flex; flex-direction: column;">
        <!-- Header -->
        <div class="sidebar-brand">
            <span class="logo-icon">E</span>
            <span>EDUNOTE</span>
        </div>

        <!-- Contenido principal del sidebar -->
        <div style="flex: 1; overflow-y: auto;">
            <?php if ($role == 1): // Admin 
            ?>
                <div class="sidebar-section-title">CLASES Y CURSOS</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('dash_iniciales', $current); ?>" href="dash_iniciales.php">
                            <span data-feather="user"></span>
                            Inicial
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('dashboard_primaria', $current); ?>" href="dashboard_primaria.php">
                            <span data-feather="book"></span>
                            Primaria
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('dashboard_secundaria', $current); ?>" href="dashboard_secundaria.php">
                            <span data-feather="layers"></span>
                            Secundaria
                        </a>
                    </li>
                </ul>

                <div class="sidebar-section-title">PANEL DE CONTROL</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('personal', $current); ?>" href="personal.php">
                            <span data-feather="users"></span>
                            Personal
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('control_bimestres', $current); ?>" href="control_bimestres.php">
                            <span data-feather="calendar"></span>
                            Trimestres
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('monitor', $current); ?>" href="monitor.php">
                            <span data-feather="monitor"></span>
                            Monitor
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('tablon', $current); ?>" href="anuncios.php">
                            <span data-feather="calendar"></span>
                            Tablon
                        </a>
                    </li>
                </ul>
                <!-- asignacion de cursos -->
                <div class="sidebar-section-title">ASIGNACION DE PROFESORES</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('asig_ini', $current); ?>" href="asig_ini.php">
                            <span data-feather="users"></span>
                            Inicial
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('asig_pri', $current); ?>" href="asig_pri.php">
                            <span data-feather="calendar"></span>
                            Primaria
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('asig_sec', $current); ?>" href="asig_sec.php">
                            <span data-feather="calendar"></span>
                            Secundaria
                        </a>
                    </li>
                </ul>

                <!-- Informacion de estudiantes -->
                <div class="sidebar-section-title">INFORMACION DE ESTUDIANTES</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('estudiantes', $current); ?>" href="estudiantes.php">
                            <span data-feather="info"></span>
                            Estudiantes
                        </a>
                    </li>
                </ul>

            <?php elseif ($role == 2): // Profesor 
            ?>
                <div class="sidebar-section-title">MIS CURSOS</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('dashboard', $current); ?>" href="dashboard.php">
                            <span data-feather="book-open"></span>
                            Ver Cursos
                        </a>
                    </li>
                </ul>
            <?php elseif ($role == 3): // Directora 
            ?>
                <div class="sidebar-section-title">Centralizadores</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('iniv.php', $current); ?>" href="iniv.php">
                            <i class="bi bi-person-circle me-2"></i>
                            Inicial
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('priv.php', $current); ?>" href="priv.php">
                            <i class="bi bi-book me-2"></i>
                            Primaria
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('secv.php', $current); ?>" href="secv.php">
                            <i class="bi bi-layers me-2"></i>
                            Secundaria
                        </a>
                    </li>
                </ul>
            <?php endif; ?>

        </div>

        <!-- Pie de sidebar -->
        <div class="sidebar-bottom">
            <?php if (isset($_SESSION['user_name'])): ?>
                <div class="sidebar-user">
                    <span data-feather="user"></span>
                    <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
            <?php endif; ?>
            <div class="sidebar-logout">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="../includes/logout.php">
                            <span data-feather="log-out"></span>
                            Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <script>
        if (window.feather) feather.replace();
    </script>
</nav>