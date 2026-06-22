<?php
$role = $_SESSION['user_role'] ?? null;
$sidebarPuedeRegistrarAsistencia = ((int)$role === 1);
$sidebarPuedeVerReportesAsistencia = ((int)$role === 1);

if (isset($_SESSION['user_id']) && (int)$role !== 1) {
    try {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/asistencia_auth.php';

        $sidebarConn = (new Database())->connect();
        $sidebarLectorInfo = asistencia_auth_get_lector($sidebarConn, (int)$_SESSION['user_id']);
        $sidebarPuedeRegistrarAsistencia = $sidebarLectorInfo !== null;
        $sidebarPuedeVerReportesAsistencia = asistencia_auth_puede_ver_reportes((int)$role, $sidebarLectorInfo);
    } catch (Throwable $e) {
    }
}

$current = basename($_SERVER['PHP_SELF']);
function active($str, $current)
{
    if (isset($_SESSION['force_active']) && $str === $_SESSION['force_active']) {
        return 'active';
    }
    return (strpos($current, $str) !== false) ? 'active' : '';
}
?>
<button type="button" class="sidebar-mobile-open-btn" id="sidebarOpenGlobal" aria-label="Mostrar menú">☰</button>
<div class="sidebar-mobile-backdrop" id="sidebarMobileBackdrop"></div>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse shadow" style="background:#181f2c; min-height:100vh;">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');

        #sidebarMenu {
            font-family: 'Inter', Arial, sans-serif !important;
            letter-spacing: 0.01em;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            background: #181f2c !important;
            color: #ffffff;
        }

        body.has-unified-sidebar {
            --edunote-sidebar-width: 250px;
            --edunote-sidebar-collapsed-width: 60px;
            margin-left: 0 !important;
            padding-left: var(--edunote-sidebar-width) !important;
            transition: padding-left .25s ease;
        }

        body.has-unified-sidebar.sidebar-collapsed {
            padding-left: var(--edunote-sidebar-collapsed-width) !important;
        }

        body.has-unified-sidebar .sidebar {
            position: fixed !important;
            left: 0 !important;
            top: 0 !important;
            width: var(--edunote-sidebar-width) !important;
            max-width: var(--edunote-sidebar-width) !important;
            min-width: var(--edunote-sidebar-width) !important;
            height: 100vh !important;
            height: 100dvh !important;
            min-height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
            z-index: 1050 !important;
            overflow: hidden !important;
        }

        body.has-unified-sidebar #sidebarMenu {
            background: #181f2c !important;
            box-shadow: 4px 0 18px rgba(15, 23, 42, 0.18);
        }

        body.has-unified-sidebar.sidebar-collapsed .sidebar {
            width: var(--edunote-sidebar-collapsed-width) !important;
            max-width: var(--edunote-sidebar-collapsed-width) !important;
            min-width: var(--edunote-sidebar-collapsed-width) !important;
        }

        body.has-unified-sidebar .main-content,
        body.has-unified-sidebar main,
        body.has-unified-sidebar .content,
        body.has-unified-sidebar .content-panel,
        body.has-unified-sidebar .container-fluid,
        body.has-unified-sidebar .dashboard-content {
            margin-left: 0 !important;
        }

        body.has-unified-sidebar main,
        body.has-unified-sidebar .main-content,
        body.has-unified-sidebar .content-panel,
        body.has-unified-sidebar .dashboard-content {
            width: 100% !important;
            max-width: none !important;
        }

        .sidebar-toggle {
            margin-left: auto;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid #2a3547;
            background: #1e2638;
            color: #cfd6ee;
            font-weight: 600;
            cursor: pointer;
            flex-shrink: 0;
            font-size: 0.85rem;
            transition: background 0.2s, color 0.2s;
        }

        body.sidebar-collapsed .sidebar-toggle {
            transform: rotate(180deg);
        }

        .sidebar-toggle:hover {
            background: #242f49;
            color: #ffffff;
        }

        .sidebar-mobile-open-btn {
            display: none;
            position: fixed;
            top: 10px;
            left: 10px;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 1px solid #2a3547;
            background: #1e2638;
            color: #ffffff;
            z-index: 1055;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.25);
        }

        .sidebar-mobile-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 1040;
        }

        body.sidebar-mobile-open .sidebar-mobile-open-btn {
            display: none !important;
        }

        body.sidebar-collapsed #sidebarMenu {
            flex: 0 0 60px !important;
            max-width: 60px !important;
            width: 60px !important;
            overflow: hidden;
        }

        body.sidebar-collapsed #sidebarMenu .brand-label,
        body.sidebar-collapsed #sidebarMenu .nav-label,
        body.sidebar-collapsed #sidebarMenu .sidebar-section-title,
        body.sidebar-collapsed #sidebarMenu .sidebar-search-box,
        body.sidebar-collapsed #sidebarMenu .sidebar-user-name {
            display: none !important;
        }

        body.sidebar-collapsed #sidebarMenu .sidebar-brand {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }

        body.sidebar-collapsed #sidebarMenu .sidebar-toggle {
            margin-left: 0;
            width: 28px;
            height: 28px;
        }

        body.sidebar-collapsed #sidebarMenu .sidebar-brand {
            flex-direction: column;
            gap: 0.45rem;
        }

        body.sidebar-collapsed #sidebarMenu .nav-link {
            justify-content: center;
            padding: 0.65rem 0;
            margin: 3px 8px;
            border-left: 0;
        }

        body.sidebar-collapsed #sidebarMenu .nav-link .feather {
            margin-right: 0;
        }

        body.sidebar-collapsed #sidebarMenu .sidebar-user {
            justify-content: center;
            padding: 0.75rem 0;
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
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .nav-link.active,
        .nav-link:hover {
            color: #fff !important;
            background: #242f49;
            border-left: 2px solid #49f0bd;
            font-weight: 500;
        }

        .nav-link .feather {
            opacity: .83;
            width: 16px;
            height: 16px;
            flex: 0 0 16px;
        }

        .nav-label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sidebar-bottom {
            padding: 1rem 0 0.4rem;
            margin-top: auto;
            /* Empuja todo hacia arriba */
            flex-shrink: 0;
            background: #181f2c;
            border-top: 1px solid #24304a;
        }

        .sidebar-logout .nav-link {
            background: linear-gradient(135deg, #2563eb 0%, #0891b2 100%);
            color: #fff !important;
            font-weight: 500;
            border-radius: 6px;
            padding: 0.55rem;
            width: 72%;
            font-size: 0.82rem;
            justify-content: center;
        }

        /* Ajustes responsive adicionales */
        @media (max-width: 1200px) {
            .nav-link {
                padding-left: 1.6rem;
            }
        }

        @media (max-width: 991px) {
            body.has-unified-sidebar {
                padding-left: 0 !important;
            }

            body.has-unified-sidebar .sidebar {
                width: 270px !important;
                max-width: 270px !important;
                min-width: 270px !important;
                left: -285px !important;
            }

            .sidebar-mobile-open-btn {
                display: inline-flex;
            }

            #sidebarMenu {
                position: fixed;
                top: 0;
                left: -285px !important;
                width: 270px !important;
                max-width: 270px !important;
                min-height: 100vh !important;
                height: 100vh;
                z-index: 1050;
                transition: left .25s ease;
                overflow: hidden;
            }

            #sidebarMenu .position-sticky {
                height: 100vh !important;
                min-height: 100vh !important;
            }

            #sidebarMenu .position-sticky > div[style*='overflow-y: auto'] {
                overflow-y: auto !important;
                min-height: 0;
                padding-bottom: 10px;
            }

            body.sidebar-mobile-open #sidebarMenu {
                left: 0 !important;
            }

            body.sidebar-mobile-open .sidebar-mobile-backdrop {
                display: block;
            }

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

            .sidebar-bottom {
                padding-bottom: env(safe-area-inset-bottom, 10px);
            }

            .sidebar-logout .nav-link {
                width: calc(100% - 1.6rem);
                margin: 0 0.8rem;
            }
        }
    </style>
    <div class="position-sticky pt-0" style="height: 100vh; display: flex; flex-direction: column;">
        <!-- Header -->
        <div class="sidebar-brand">
            <span class="logo-icon">E</span>
            <span class="brand-label">EDUNOTE</span>
            <button type="button" class="sidebar-toggle" id="sidebarToggleGlobal" aria-label="Contraer/expandir menú">☰</button>
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
                            <span class="nav-label">Inicial</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('dashboard_primaria', $current); ?>" href="dashboard_primaria.php">
                            <span data-feather="book"></span>
                            <span class="nav-label">Primaria</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('dashboard_secundaria', $current); ?>" href="dashboard_secundaria.php">
                            <span data-feather="layers"></span>
                            <span class="nav-label">Secundaria</span>
                        </a>
                    </li>
                </ul>

                <div class="sidebar-section-title">PANEL DE CONTROL</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('personal', $current); ?>" href="personal.php">
                            <span data-feather="users"></span>
                            <span class="nav-label">Personal</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('control_bimestres', $current); ?>" href="control_bimestres.php">
                            <span data-feather="calendar"></span>
                            <span class="nav-label">Trimestres</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('monitor', $current); ?>" href="monitor.php">
                            <span data-feather="monitor"></span>
                            <span class="nav-label">Monitor</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('tablon', $current); ?>" href="anuncios.php">
                            <span data-feather="message-square"></span>
                            <span class="nav-label">Tablón</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('log.php', $current); ?>" href="log.php">
                            <span data-feather="activity"></span>
                            <span class="nav-label">Log</span>
                        </a>
                    </li>
                </ul>
                <!-- asignacion de cursos -->
                <div class="sidebar-section-title">ASIGNACIÓN DE PROFESORES</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('asig_ini', $current); ?>" href="asig_ini.php">
                            <span data-feather="user-plus"></span>
                            <span class="nav-label">Inicial</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('asig_pri', $current); ?>" href="asig_pri.php">
                            <span data-feather="user-check"></span>
                            <span class="nav-label">Primaria</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('asig_sec', $current); ?>" href="asig_sec.php">
                            <span data-feather="user-check"></span>
                            <span class="nav-label">Secundaria</span>
                        </a>
                    </li>
                </ul>

                <!-- Informacion de estudiantes -->
                <div class="sidebar-section-title">INFORMACIÓN DE ESTUDIANTES</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('estudiantes', $current); ?>" href="estudiantes.php">
                            <span data-feather="users"></span>
                            <span class="nav-label">Estudiantes</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('responsables_por_nivel.php', $current); ?>" href="responsables_por_nivel.php">
                            <span data-feather="list"></span>
                            <span class="nav-label">Responsables por nivel</span>
                        </a>
                    </li>
                </ul>

                <!-- Asistencia -->
                <div class="sidebar-section-title">ASISTENCIA</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('asistencia.php', $current); ?>" href="asistencia.php">
                            <span data-feather="check-circle"></span>
                            <span class="nav-label">Ver QR</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('lectores_asistencia.php', $current); ?>" href="lectores_asistencia.php">
                            <span data-feather="smartphone"></span>
                            <span class="nav-label">Lectores de asistencia</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('ajustes_asistencia.php', $current); ?>" href="ajustes_asistencia.php">
                            <span data-feather="settings"></span>
                            <span class="nav-label">Ajustes</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('reporte_asistencia_curso.php', $current); ?>" href="reporte_asistencia_curso.php">
                            <span data-feather="file-text"></span>
                            <span class="nav-label">Reporte por curso</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('estadisticas_asistencia.php', $current); ?>" href="estadisticas_asistencia.php">
                            <span data-feather="bar-chart-2"></span>
                            <span class="nav-label">Estadísticas</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('reporte_desayuno_escolar.php', $current); ?>" href="reporte_desayuno_escolar.php">
                            <span data-feather="clipboard"></span>
                            <span class="nav-label">Desayuno escolar</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('permisos_inasistencia.php', $current); ?>" href="permisos_inasistencia.php">
                            <span data-feather="file-plus"></span>
                            <span class="nav-label">Permisos inasistencia</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('reporte_estudiantil_asistencia.php', $current); ?>" href="reporte_estudiantil_asistencia.php">
                            <span data-feather="user-check"></span>
                            <span class="nav-label">Reporte estudiantil</span>
                        </a>
                    </li>
                </ul>

            <?php elseif ($role == 2): // Profesor 
            ?>
                <div class="sidebar-section-title">MIS CURSOS</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('dashboard', $current); ?>" href="../profesor/dashboard.php">
                            <span data-feather="book-open"></span>
                            <span class="nav-label">Ver Cursos</span>
                        </a>
                    </li>
                    <?php if ($sidebarPuedeRegistrarAsistencia): ?>
                        <li>
                            <a class="nav-link <?php echo active('asistencia.php', $current); ?>" href="../admin/asistencia.php">
                                <span data-feather="check-square"></span>
                                <span class="nav-label">Registrar Asistencia</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($sidebarPuedeVerReportesAsistencia): ?>
                        <li>
                            <a class="nav-link <?php echo active('reporte_asistencia_curso.php', $current); ?>" href="../admin/reporte_asistencia_curso.php">
                                <span data-feather="file-text"></span>
                                <span class="nav-label">Reporte por curso</span>
                            </a>
                        </li>
                        <li>
                            <a class="nav-link <?php echo active('estadisticas_asistencia.php', $current); ?>" href="../admin/estadisticas_asistencia.php">
                                <span data-feather="bar-chart-2"></span>
                                <span class="nav-label">Estadísticas</span>
                            </a>
                        </li>
                        <li>
                            <a class="nav-link <?php echo active('reporte_desayuno_escolar.php', $current); ?>" href="../admin/reporte_desayuno_escolar.php">
                                <span data-feather="clipboard"></span>
                                <span class="nav-label">Desayuno escolar</span>
                            </a>
                        </li>
                        <li>
                            <a class="nav-link <?php echo active('permisos_inasistencia.php', $current); ?>" href="../admin/permisos_inasistencia.php">
                                <span data-feather="file-plus"></span>
                                <span class="nav-label">Permisos inasistencia</span>
                            </a>
                        </li>
                        <li>
                            <a class="nav-link <?php echo active('reporte_estudiantil_asistencia.php', $current); ?>" href="../admin/reporte_estudiantil_asistencia.php">
                                <span data-feather="user-check"></span>
                                <span class="nav-label">Reporte estudiantil</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            <?php elseif ($role == 3): // Directora 
            ?>
                <div class="sidebar-section-title">CENTRALIZADORES</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('iniv.php', $current); ?>" href="iniv.php">
                            <span data-feather="user"></span>
                            <span class="nav-label">Inicial</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('priv.php', $current); ?>" href="priv.php">
                            <span data-feather="book"></span>
                            <span class="nav-label">Primaria</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('secv.php', $current); ?>" href="secv.php">
                            <span data-feather="layers"></span>
                            <span class="nav-label">Secundaria</span>
                        </a>
                    </li>
                </ul>
                                    <?php elseif ($role == 4): // Invitado (mismas vistas que admin, se restringirá gradualmente)
            ?>
                <div class="sidebar-section-title">CLASES Y CURSOS</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('dash_iniciales', $current); ?>" href="dash_iniciales.php">
                            <span data-feather="user"></span>
                            <span class="nav-label">Inicial</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('dashboard_primaria', $current); ?>" href="dashboard_primaria.php">
                            <span data-feather="book"></span>
                            <span class="nav-label">Primaria</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('dashboard_secundaria', $current); ?>" href="dashboard_secundaria.php">
                            <span data-feather="layers"></span>
                            <span class="nav-label">Secundaria</span>
                        </a>
                    </li>
                </ul>

                <!-- Informacion de estudiantes -->
                <div class="sidebar-section-title">INFORMACIÓN DE ESTUDIANTES</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('estudiantes', $current); ?>" href="estudiantes.php">
                            <span data-feather="users"></span>
                            <span class="nav-label">Estudiantes</span>
                        </a>
                    </li>
                </ul>

                <!-- Asistencia -->
                <div class="sidebar-section-title">ASISTENCIA</div>
                <ul class="nav flex-column sidebar-group-list">
                    <li>
                        <a class="nav-link <?php echo active('reporte_asistencia_curso.php', $current); ?>" href="reporte_asistencia_curso.php">
                            <span data-feather="file-text"></span>
                            <span class="nav-label">Reporte por curso</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-link <?php echo active('estadisticas_asistencia.php', $current); ?>" href="estadisticas_asistencia.php">
                            <span data-feather="bar-chart-2"></span>
                            <span class="nav-label">Estadísticas</span>
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
                    <span class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                </div>
            <?php endif; ?>
            <div class="sidebar-logout">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="../includes/logout.php">
                            <span data-feather="log-out"></span>
                            <span class="nav-label">Cerrar Sesión</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <script>
        (function() {
            document.body.classList.add('has-unified-sidebar');

            try {
                const collapsed = localStorage.getItem('edunote_sidebar_collapsed') === '1';
                if (collapsed) {
                    document.body.classList.add('sidebar-collapsed');
                }
            } catch (e) {}

            const btn = document.getElementById('sidebarToggleGlobal');
            const openBtn = document.getElementById('sidebarOpenGlobal');
            const backdrop = document.getElementById('sidebarMobileBackdrop');

            const isMobile = () => window.matchMedia('(max-width: 991px)').matches;

            const closeMobileSidebar = () => {
                document.body.classList.remove('sidebar-mobile-open');
            };

            if (btn) {
                btn.addEventListener('click', function() {
                    if (isMobile()) {
                        closeMobileSidebar();
                        return;
                    }

                    document.body.classList.toggle('sidebar-collapsed');
                    try {
                        localStorage.setItem('edunote_sidebar_collapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
                    } catch (e) {}
                });
            }

            if (openBtn) {
                openBtn.addEventListener('click', function() {
                    document.body.classList.toggle('sidebar-mobile-open');
                });
            }

            if (backdrop) {
                backdrop.addEventListener('click', closeMobileSidebar);
            }

            const heartbeatUrl = '../includes/heartbeat.php';
            const enviarHeartbeat = function() {
                try {
                    fetch(heartbeatUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                        },
                        body: 'path=' + encodeURIComponent(window.location.pathname)
                    });
                } catch (e) {}
            };

            enviarHeartbeat();
            setInterval(enviarHeartbeat, 30000);

            document.querySelectorAll('#sidebarMenu .nav-link').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (isMobile()) {
                        closeMobileSidebar();
                    }
                });
            });

            window.addEventListener('resize', function() {
                if (!isMobile()) {
                    closeMobileSidebar();
                }
            });
        })();
        function renderSidebarIcons() {
            if (window.feather) {
                window.feather.replace();
            }
        }

        if (window.feather) {
            renderSidebarIcons();
        } else {
            const featherScript = document.createElement('script');
            featherScript.src = 'https://cdn.jsdelivr.net/npm/feather-icons@4.29.0/dist/feather.min.js';
            featherScript.onload = renderSidebarIcons;
            document.head.appendChild(featherScript);
        }
    </script>
</nav>
