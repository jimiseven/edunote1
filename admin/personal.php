<?php
session_start();
require_once '../config/database.php';

// Verificar solo para administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

// Obtener todo el personal
$stmt = $conn->query("
    SELECT
        p.id_personal,
        p.nombres,
        p.apellidos,
        p.celular,
        p.carnet_identidad,
        p.estado,
        r.nombre_rol
    FROM personal p
    JOIN roles r ON p.id_rol = r.id_rol
    ORDER BY p.apellidos ASC
");
$personal = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Personal</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        html, body {
            height: 100%;
        }
        body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            background: #f8f9fa;
        }
        .container-fluid, .row, main, .sidebar {
            height: 100vh !important;
            min-height: 100vh !important;
        }
        .sidebar {
            background: #19202a;
            min-height: 100vh;
            height: 100vh !important;
            position: sticky;
            top: 0;
        }
        main {
            background: #fff;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            height: 100vh;
            padding: 20px;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .main-title {
            margin: 0;
            font-weight: bold;
            color: #11305e;
        }
        .btn-nuevo {
            background-color: #28a745;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .btn-nuevo:hover {
            background-color: #218838;
            color: white;
        }
        .tabla-box {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            margin-bottom: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
        }
        .table-responsive {
            flex: 1 1 auto;
            max-height: 70vh;
            min-height: 300px;
            overflow-y: auto;
        }
        .table-personal {
            margin-bottom: 0;
        }
        .table-personal th {
            background-color: #11305e;
            color: white;
            font-weight: 500;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .table-personal tr:hover {
            background-color: #f8f9fa;
        }
        .acciones-cell {
            display: flex;
            gap: 5px;
        }
        .btn-accion {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .btn-editar {
            background-color: #17a2b8;
            color: white;
        }
        .btn-editar:hover {
            background-color: #138496;
            color: white;
        }
        .btn-eliminar {
            background-color: #dc3545;
            color: white;
        }
        .btn-eliminar:hover {
            background-color: #c82333;
            color: white;
        }
        /* Formulario nuevo personal */
        .form-nuevo-personal {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        /* Responsive */
        @media (max-width: 991px) {
            .container-fluid, .row, .sidebar, main {
                min-height: unset !important;
                height: auto !important;
            }
            .tabla-box, .table-responsive {
                max-height: 55vh;
            }
            .header-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid g-0">
        <div class="row g-0">
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="header-section">
                    <h1 class="main-title">Listado de Personal</h1>
                    <button type="button" class="btn-nuevo" data-bs-toggle="modal" data-bs-target="#modalNuevoPersonal">
                        <i class="bi bi-plus-lg"></i> Nuevo Personal
                    </button>
                </div>

                <!-- Modal para nuevo personal -->
                <div class="modal fade" id="modalNuevoPersonal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Agregar Nuevo Personal</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form action="crear_personal.php" method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Nombres</label>
                                        <input type="text" class="form-control" name="nombres" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Apellidos</label>
                                        <input type="text" class="form-control" name="apellidos" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Celular</label>
                                        <input type="text" class="form-control" name="celular">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Carnet de Identidad</label>
                                        <input type="text" class="form-control" name="carnet_identidad" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Rol</label>
                                        <select class="form-select" name="id_rol" required>
                                            <?php
                                            $stmt = $conn->query("SELECT id_rol, nombre_rol FROM roles");
                                            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($roles as $rol) {
                                                echo '<option value="'.$rol['id_rol'].'">'.$rol['nombre_rol'].'</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Guardar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tabla-box">
                    <div class="table-responsive">
                        <table class="table table-hover table-personal">
                            <thead>
                                <tr>
                                    <th>Nombre Completo</th>
                                    <th>Celular</th>
                                    <th>Carnet</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($personal as $miembro): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($miembro['apellidos'] . ', ' . $miembro['nombres']); ?></td>
                                    <td><?php echo htmlspecialchars($miembro['celular']); ?></td>
                                    <td><?php echo htmlspecialchars($miembro['carnet_identidad']); ?></td>
                                    <td><?php echo htmlspecialchars($miembro['nombre_rol']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $miembro['estado'] ? 'success' : 'danger'; ?>">
                                            <?php echo $miembro['estado'] ? 'Habilitado' : 'Inhabilitado'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="acciones-cell">
                                            <a href="editar_personal.php?id=<?php echo $miembro['id_personal']; ?>"
                                               class="btn btn-accion btn-editar">
                                               <i class="bi bi-pencil"></i> Editar
                                            </a>
                                            <a href="eliminar_personal.php?id=<?php echo $miembro['id_personal']; ?>"
                                               class="btn btn-accion btn-eliminar"
                                               onclick="return confirm('¿Está seguro de eliminar este registro?')">
                                               <i class="bi bi-trash"></i> Eliminar
                                            </a>
                                            <a href="cambiar_estado.php?id=<?php echo $miembro['id_personal']; ?>&estado=<?php echo $miembro['estado'] ? 0 : 1; ?>"
                                               class="btn btn-accion btn-<?php echo $miembro['estado'] ? 'warning' : 'success'; ?>"
                                               onclick="return confirm('¿<?php echo $miembro['estado'] ? 'Inhabilitar' : 'Habilitar'; ?> este personal?')">
                                               <i class="bi bi-<?php echo $miembro['estado'] ? 'x-circle' : 'check-circle'; ?>"></i>
                                               <?php echo $miembro['estado'] ? 'Inhabilitar' : 'Habilitar'; ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        // Inicializar tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html>
