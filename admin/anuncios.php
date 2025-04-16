<?php
session_start();
require_once '../config/database.php';
if ($_SESSION['user_role'] != 1) { header('Location: ../index.php'); exit(); }
$conn = (new Database())->connect();

// Eliminar anuncio si se pidió
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id_borrar = intval($_GET['eliminar']);
    $conn->prepare("DELETE FROM anuncios WHERE id = ?")->execute([$id_borrar]);
    $exito = "Anuncio eliminado correctamente";
}

// Guardar anuncio nuevo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mensaje = trim($_POST['mensaje']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $conn->prepare("INSERT INTO anuncios (mensaje, fecha_inicio, fecha_fin, creado_por) VALUES (?, ?, ?, ?)")
        ->execute([$mensaje, $fecha_inicio, $fecha_fin, $_SESSION['user_id']]);
    $exito = "Anuncio creado correctamente";
}

// Traer anuncios actuales
$stmt = $conn->query("SELECT * FROM anuncios ORDER BY fecha_inicio DESC");
$anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablón de Anuncios</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css">
    <style>
        :root {
            --primary-blue: #2678bf;
            --light-blue: #f6fbff;
            --danger-red: #f44336;
            --danger-hover: #b71c1c;
            --border-color: #e0e0e0;
            --shadow-color: rgba(28, 78, 172, 0.07);
        }
        
        body { 
            background: #f3f6fb; 
            font-family: 'Inter', Arial, sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        
        .main-container {
            display: flex;
            height: calc(100vh - 56px);
            gap: 1.5rem;
            padding: 1.5rem 1.5rem 1.5rem 0;
        }
        
        .form-column {
            flex: 0 0 380px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 18px var(--shadow-color);
            padding: 1.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .history-column {
            flex: 1;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 18px var(--shadow-color);
            padding: 1.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .section-title {
            color: var(--primary-blue);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            margin-bottom: 1.5rem;
        }
        
        .form-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .date-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .submit-btn {
            margin-top: auto;
            width: 100%;
        }
        
        .announcement-list {
            flex: 1;
            overflow-y: auto;
            padding-right: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .announcement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background: var(--light-blue);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
        }
        
        .announcement-content {
            flex: 1;
        }
        
        .announcement-message {
            margin-bottom: 0.25rem;
        }
        
        .announcement-dates {
            font-size: 0.85rem;
            color: #3578b3;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .delete-btn {
            background: var(--danger-red);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.5rem;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            flex-shrink: 0;
            margin-left: 0.75rem;
        }
        
        .delete-btn:hover {
            background: var(--danger-hover);
        }
        
        .empty-state {
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 2rem 0;
        }
        
        @media (max-width: 992px) {
            .main-container {
                flex-direction: column;
                height: auto;
                padding: 1rem;
            }
            
            .form-column, .history-column {
                flex: auto;
                width: 100%;
            }
            
            .history-column {
                margin-top: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .date-inputs {
                grid-template-columns: 1fr;
            }
            
            .section-title {
                font-size: 1.1rem;
            }
            
            .announcement-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .delete-btn {
                margin-left: 0;
                margin-top: 0.5rem;
                align-self: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="main-container">
                    <!-- Columna del formulario -->
                    <div class="form-column">
                        <h2 class="section-title">
                            <i class="ri-megaphone-line"></i> Crear Anuncio
                        </h2>
                        
                        <?php if (!empty($exito)): ?>
                            <div class="alert alert-success">
                                <i class="ri-checkbox-circle-line"></i> <?php echo $exito; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" class="form-container" autocomplete="off">
                            <div class="form-group">
                                <label>Mensaje del anuncio</label>
                                <textarea 
                                    name="mensaje" 
                                    class="form-control" 
                                    rows="4" 
                                    maxlength="255" 
                                    required
                                    placeholder="Escribe el mensaje que aparecerá en el banner..."
                                ></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Período de visualización</label>
                                <div class="date-inputs">
                                    <div>
                                        <label>Fecha de inicio</label>
                                        <input 
                                            type="date" 
                                            name="fecha_inicio" 
                                            required 
                                            class="form-control"
                                        >
                                    </div>
                                    <div>
                                        <label>Fecha de fin</label>
                                        <input 
                                            type="date" 
                                            name="fecha_fin" 
                                            required 
                                            class="form-control"
                                        >
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary submit-btn">
                                <i class="ri-send-plane-line"></i> Publicar Anuncio
                            </button>
                        </form>
                    </div>
                    
                    <!-- Columna del historial -->
                    <div class="history-column">
                        <h2 class="section-title">
                            <i class="ri-archive-drawer-line"></i> Historial
                        </h2>
                        
                        <div class="announcement-list">
                            <?php if (empty($anuncios)): ?>
                                <div class="empty-state">
                                    No hay anuncios registrados
                                </div>
                            <?php else: ?>
                                <?php foreach ($anuncios as $a): ?>
                                    <div class="announcement-item">
                                        <div class="announcement-content">
                                            <div class="announcement-message">
                                                <?php echo htmlspecialchars($a['mensaje']); ?>
                                            </div>
                                            <div class="announcement-dates">
                                                <i class="ri-calendar-line"></i>
                                                <?php echo date('d/m/Y', strtotime($a['fecha_inicio'])); ?>
                                                - 
                                                <?php echo date('d/m/Y', strtotime($a['fecha_fin'])); ?>
                                            </div>
                                        </div>
                                        <form method="get" onsubmit="return confirm('¿Eliminar este anuncio?');">
                                            <input type="hidden" name="eliminar" value="<?php echo $a['id']; ?>">
                                            <button type="submit" class="delete-btn" title="Eliminar anuncio">
                                                <i class="ri-delete-bin-2-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>