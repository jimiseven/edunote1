<?php
session_start();
require_once '../config/database.php';

// Verificar que sea administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

// Procesar cambios si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bimestres_activos = isset($_POST['bimestres_activos']) ? $_POST['bimestres_activos'] : [];
    
    // Primero desactivar todos
    $stmt = $conn->prepare("UPDATE bimestres_activos SET esta_activo = FALSE");
    $stmt->execute();
    
    // Luego activar los seleccionados
    if (!empty($bimestres_activos)) {
        $placeholders = implode(',', array_fill(0, count($bimestres_activos), '?'));
        $stmt = $conn->prepare("UPDATE bimestres_activos SET esta_activo = TRUE WHERE numero_bimestre IN ($placeholders)");
        $stmt->execute($bimestres_activos);
    }
    
    // Guardar fechas si se proporcionaron
    foreach ([1, 2, 3] as $bimestre) {
        $fecha_inicio = !empty($_POST["fecha_inicio_$bimestre"]) ? $_POST["fecha_inicio_$bimestre"] : null;
        $fecha_fin = !empty($_POST["fecha_fin_$bimestre"]) ? $_POST["fecha_fin_$bimestre"] : null;
        
        if ($fecha_inicio || $fecha_fin) {
            $stmt = $conn->prepare("UPDATE bimestres_activos SET fecha_inicio = ?, fecha_fin = ? WHERE numero_bimestre = ?");
            $stmt->execute([$fecha_inicio, $fecha_fin, $bimestre]);
        }
    }
    
    $success = "Configuración de bimestres actualizada correctamente";
}

// Obtener estado actual de bimestres
$stmt = $conn->query("SELECT * FROM bimestres_activos ORDER BY numero_bimestre");
$bimestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Control de Trimestres</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        body, html {
            height: 100%;
            background: #f4f8fa;
            overflow-x: hidden;
        }
        .container-fluid, .row {
            height: 100%;
        }
        .sidebar {
            background: #19202a;
            height: 100vh;
            position: sticky;
            top: 0;
        }
        main {
            background: #fff;
            height: 100vh;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
        }
        .main-title {
            font-weight: bold;
            color: #11305e;
            margin-bottom: 1rem;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
        }
        .card-header {
            background-color: #4682B4;
            color: white;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
            padding: 0.75rem 1.25rem;
        }
        .card-body {
            padding: 1.25rem;
        }
        .btn-primary {
            background: #4682B4;
            border-color: #4682B4;
            font-weight: 500;
        }
        .btn-primary:hover {
            background: #11305e;
            border-color: #11305e;
        }
        .alert {
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        .bimestre-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 0;
        }
        .bimestre-card {
            flex: 1;
            min-width: 220px;
            border-radius: 8px;
            padding: 1rem;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .bimestre-active {
            background-color: #f0fdf4;
            border-left: 4px solid #28a745;
        }
        .bimestre-inactive {
            background-color: #fff5f5;
            border-left: 4px solid #dc3545;
        }
        .form-check-label {
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        .form-check-input:checked {
            background-color: #4682B4;
            border-color: #4682B4;
        }
        .fecha-container {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        .fecha-container .form-control {
            font-size: 0.9rem;
            padding: 0.4rem 0.6rem;
            height: auto;
        }
        .fecha-container label {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }
        .badge {
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        .content-wrapper {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 2rem);
        }
        .cards-container {
            display: flex;
            gap: 1rem;
            flex: 1;
        }
        .card-config {
            flex: 2;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .card-status {
            flex: 1;
        }
        .card-body-scroll {
            overflow-y: auto;
            flex: 1;
            padding: 1.25rem;
        }
        .btn-container {
            padding: 1rem;
            border-top: 1px solid #eee;
            background: #f8f9fa;
            text-align: right;
        }
        @media (max-width: 992px) {
            .cards-container {
                flex-direction: column;
            }
            .bimestre-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid g-0">
        <div class="row g-0">
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-wrapper">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                        <h1 class="main-title">Control de Bimestres</h1>
                    </div>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <div class="cards-container">
                        <div class="card card-config">
                            <div class="card-header">
                                Configuración de Trimestres Activos
                            </div>
                            <form method="post" action="" class="d-flex flex-column flex-grow-1">
                                <div class="card-body-scroll">
                                    <div class="bimestre-row">
                                        <?php foreach ($bimestres as $bimestre): ?>
                                            <div class="bimestre-card <?php echo $bimestre['esta_activo'] ? 'bimestre-active' : 'bimestre-inactive'; ?>">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" 
                                                        name="bimestres_activos[]" 
                                                        id="bimestre<?php echo $bimestre['numero_bimestre']; ?>" 
                                                        value="<?php echo $bimestre['numero_bimestre']; ?>"
                                                        <?php echo $bimestre['esta_activo'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="bimestre<?php echo $bimestre['numero_bimestre']; ?>">
                                                        Trimestre <?php echo $bimestre['numero_bimestre']; ?>
                                                        <span class="badge <?php echo $bimestre['esta_activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?php echo $bimestre['esta_activo'] ? 'Activo' : 'Inactivo'; ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                
                                                <div class="fecha-container">
                                                    <div class="flex-fill">
                                                        <label class="form-label">Inicio:</label>
                                                        <input type="date" class="form-control" 
                                                            name="fecha_inicio_<?php echo $bimestre['numero_bimestre']; ?>"
                                                            value="<?php echo $bimestre['fecha_inicio']; ?>">
                                                    </div>
                                                    <div class="flex-fill">
                                                        <label class="form-label">Fin:</label>
                                                        <input type="date" class="form-control" 
                                                            name="fecha_fin_<?php echo $bimestre['numero_bimestre']; ?>"
                                                            value="<?php echo $bimestre['fecha_fin']; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="btn-container">
                                    <button type="submit" class="btn btn-primary">
                                        Guardar Configuración
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="card card-status">
                            <div class="card-header">
                                Estado Actual del Sistema
                            </div>
                            <div class="card-body">
                                <h6 class="mb-3">Trimestres habilitados:</h6>
                                <ul class="list-group">
                                    <?php
                                    $hay_activos = false;
                                    foreach ($bimestres as $bimestre):
                                        if ($bimestre['esta_activo']):
                                            $hay_activos = true;
                                    ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Trimestre <?php echo $bimestre['numero_bimestre']; ?>
                                            <span class="badge bg-success rounded-pill">Activo</span>
                                        </li>
                                    <?php
                                        endif;
                                    endforeach;
                                    
                                    if (!$hay_activos):
                                    ?>
                                        <li class="list-group-item text-danger">
                                            No hay trimestres activos. Los profesores no podrán cargar notas.
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
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
        
        // Cambiar color de fondo de la tarjeta cuando se marca/desmarca el checkbox
        document.querySelectorAll('.form-check-input').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const card = this.closest('.bimestre-card');
                if (this.checked) {
                    card.classList.remove('bimestre-inactive');
                    card.classList.add('bimestre-active');
                } else {
                    card.classList.remove('bimestre-active');
                    card.classList.add('bimestre-inactive');
                }
            });
        });
    </script>
</body>
</html>
