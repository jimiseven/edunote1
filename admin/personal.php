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
    <style>
        html, body {
            height: 100%;
        }
        body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            background: #fafbfc;
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
            padding-top: 32px;
        }
        .main-title {
            margin-top: 0.5rem;
            margin-bottom: 1.3rem;
            font-weight: bold;
            color: #11305e;
        }
        .tabla-box {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            margin-bottom: 0;
        }
        .table-responsive {
            flex: 1 1 auto;
            max-height: 75vh;
            min-height: 300px;
            overflow-y: auto;
        }
        .table-personal {
            margin-bottom: 0;
        }
        .table-personal th {
            background-color: #e9ecef;
            color: #495057;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .btn-editar {
            background-color: #4682B4;
            color: #fff;
            font-weight: 500;
            padding: 0.25rem 0.7rem;
            font-size: 0.92rem;
            border-radius: 5px;
            box-shadow: 0 1px 2px #0001;
            transition: background 0.17s;
        }
        .btn-editar:hover {
            background-color: #11305e;
            color: #fff;
        }
        /* Oculta el scroll de tabla en móviles pero mantiene funcionalidad */
        @media (max-width: 991px) {
            .container-fluid, .row, .sidebar, main {
                min-height: unset !important;
                height: auto !important;
            }
            .tabla-box, .table-responsive {
                max-height: 55vh;
            }
            .main-title {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid g-0">
        <div class="row g-0">
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <h1 class="main-title">Listado de Personal</h1>
                <div class="tabla-box">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-personal">
                            <thead>
                                <tr>
                                    <th>Nombre Completo</th>
                                    <th>Celular</th>
                                    <th>Carnet</th>
                                    <th>Rol</th>
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
                                        <a href="editar_personal.php?id=<?php echo $miembro['id_personal']; ?>" 
                                           class="btn btn-editar btn-sm">
                                           <span data-feather="edit"></span> Editar
                                        </a>
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
        if (window.feather) {
            feather.replace();
        }
    </script>
</body>
</html>
