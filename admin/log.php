<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array((int)($_SESSION['user_role'] ?? 0), [1, 4], true)) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de usuarios activos</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        body { background: #f6f8fb; }
        .main-content { padding: 1.25rem; }
        .log-card { border: 0; border-radius: 14px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        .badge-online { background: #198754; }
        .small-muted { color: #6c757d; font-size: 0.86rem; }
        @media (max-width: 768px) { .main-content { padding: 0.75rem; } }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="container-fluid">
            <div class="card log-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h4 class="mb-0">Log</h4>
                            <div class="small-muted">Estado actual de los usuarios (activo/inactivo) y última vez que ingresaron</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-online" id="activeCount">0 registros</span>
                            <button class="btn btn-sm btn-outline-secondary" id="refreshBtn" type="button">Actualizar</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead class="table-light" id="tableHead">
                                <tr>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>CI</th>
                                    <th>Estado</th>
                                    <th>Última vez que ingresó</th>
                                </tr>
                            </thead>
                            <tbody id="logTableBody">
                                <tr><td colspan="5" class="text-center text-muted py-3">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="small-muted" id="lastSync">Sin sincronizar</div>
                </div>
            </div>
        </div>
    </main>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        const tableBody = document.getElementById('logTableBody');
        const activeCount = document.getElementById('activeCount');
        const lastSync = document.getElementById('lastSync');
        const refreshBtn = document.getElementById('refreshBtn');

        function esc(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        async function cargarLog() {
            refreshBtn.disabled = true;
            try {
                const res = await fetch('log_data.php', { cache: 'no-store' });
                const data = await res.json();
                const colSpan = 5;

                if (!data.ok) {
                    tableBody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center text-danger py-3">No se pudo cargar el log.</td></tr>';
                    activeCount.textContent = '0 registros';
                    return;
                }

                activeCount.textContent = data.count + (data.count === 1 ? ' registro' : ' registros');

                if (!Array.isArray(data.items) || data.items.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center text-muted py-3">No hay registros para mostrar.</td></tr>';
                } else {
                    tableBody.innerHTML = data.items.map(function(item) {
                        const estado = String(item.activo) === '1' ? 'Activo' : 'Inactivo';
                        return '<tr>' +
                            '<td>' + esc(item.nombre_usuario || ('ID ' + item.id_personal)) + '</td>' +
                            '<td>' + esc(item.nombre_rol || '-') + '</td>' +
                            '<td>' + esc(item.carnet_identidad || '-') + '</td>' +
                            '<td>' + esc(estado) + '</td>' +
                            '<td>' + esc(item.ultima_vez_ingreso || 'Sin registro') + '</td>' +
                        '</tr>';
                    }).join('');
                }

                lastSync.textContent = 'Actualizado: ' + (data.server_time || new Date().toLocaleString());
            } catch (e) {
                tableBody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center text-danger py-3">Error de conexión al cargar el log.</td></tr>';
                activeCount.textContent = '0 registros';
            } finally {
                refreshBtn.disabled = false;
            }
        }

        refreshBtn.addEventListener('click', cargarLog);
        cargarLog();
        setInterval(cargarLog, 15000);
    </script>
</body>
</html>
