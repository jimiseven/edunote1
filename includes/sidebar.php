<?php
$role = $_SESSION['user_role'] ?? null;
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">

        <!-- INICIAL -->
        <div class="mb-4">
            <div class="px-3 pt-2 pb-1 border-start border-4" style="border-color:#3a3f51 !important; background:#23272f;">
                <span style="color:#c0d3f7; font-weight:600;">INICIAL</span>
            </div>
            <ul class="nav flex-column" style="background:#23272f;">
                <?php if ($role == 1): ?>
                <li class="nav-item">
                    <a class="nav-link text-light" href="dash_iniciales.php">
                        <span data-feather="user" class="me-2"></span>
                        Cursos Iniciales
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- PRIMARIA -->
        <div class="mb-4">
            <div class="px-3 pt-2 pb-1 border-start border-4" style="border-color:#16697a !important; background:#1a2229;">
                <span style="color:#85c7de; font-weight:600;">PRIMARIA</span>
            </div>
            <ul class="nav flex-column" style="background:#1a2229;">
                <li class="nav-item">
                    <a class="nav-link text-light" href="dashboard_primaria.php">
                        <span data-feather="book" class="me-2"></span>
                        Cursos Primaria
                    </a>
                </li>
            </ul>
        </div>

        <!-- SECUNDARIA -->
        <div class="mb-2">
            <div class="px-3 pt-2 pb-1 border-start border-4" style="border-color:#4c5c68 !important; background:#191a1e;">
                <span style="color:#99b898; font-weight:600;">SECUNDARIA</span>
            </div>
            <ul class="nav flex-column" style="background:#191a1e;">
                <li class="nav-item">
                    <a class="nav-link text-light" href="dashboard_secundaria.php">
                        <span data-feather="layers" class="me-2"></span>
                        Cursos Secundaria
                    </a>
                </li>
            </ul>
        </div>

    </div>
</nav>
