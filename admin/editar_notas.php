<?php
session_start();
require_once '../config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Obtener el ID del curso
$id_curso = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_curso <= 0) {
    header('Location: dashboard.php?error=curso_invalido');
    exit();
}

$database = new Database();
$conn = $database->connect();

// Obtener información del curso
$stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
$stmt_curso->execute([$id_curso]);

if ($stmt_curso->rowCount() == 0) {
    header('Location: dashboard.php?error=curso_no_encontrado');
    exit();
}

$curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);
$nombre_curso = $curso_info['nivel'] . ' ' . $curso_info['curso'] . ' "' . $curso_info['paralelo'] . '"';

// Obtener estudiantes
$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, apellido_paterno, apellido_materno, nombres
    FROM estudiantes
    WHERE id_curso = ?
    ORDER BY apellido_paterno, apellido_materno, nombres
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// Obtener materias del curso
$stmt_materias = $conn->prepare("
    SELECT m.id_materia, m.nombre_materia, m.es_submateria, m.materia_padre_id
    FROM cursos_materias cm
    JOIN materias m ON cm.id_materia = m.id_materia
    WHERE cm.id_curso = ?
    ORDER BY m.nombre_materia
");
$stmt_materias->execute([$id_curso]);
$materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// Obtener calificaciones existentes
$calificaciones = [];
foreach ($estudiantes as $estudiante) {
    foreach ($materias as $materia) {
        for ($i = 1; $i <= 3; $i++) {
            $stmt = $conn->prepare("
                SELECT calificacion
                FROM calificaciones
                WHERE id_estudiante = ?
                AND id_materia = ?
                AND bimestre = ?
            ");
            $stmt->execute([$estudiante['id_estudiante'], $materia['id_materia'], $i]);
            $nota = $stmt->fetchColumn();
            $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$i] = $nota !== false ? $nota : '';
        }
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_notas'])) {
    $actualizaciones = 0;
    $errores = [];
    
    foreach ($_POST['notas'] as $id_est => $materias_data) {
        foreach ($materias_data as $id_materia => $bimestres) {
            foreach ($bimestres as $bimestre => $valor) {
                $valor = trim($valor);
                
                try {
                    // Eliminar si está vacío
                    if ($valor === '') {
                        $stmt = $conn->prepare("DELETE FROM calificaciones
                            WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?");
                        $stmt->execute([$id_est, $id_materia, $bimestre]);
                        $actualizaciones += $stmt->rowCount();
                        continue;
                    }
                    
                    // Validar nota
                    if (!is_numeric(str_replace(',', '.', $valor))) {
                        throw new Exception("Nota inválida");
                    }
                    
                    $nota_valor = floatval(str_replace(',', '.', $valor));
                    
                    // Validar rango de nota (0-100)
                    if ($nota_valor < 0 || $nota_valor > 100) {
                        throw new Exception("La nota debe estar entre 0 y 100");
                    }
                    
                    // Insertar/actualizar nota
                    $stmt = $conn->prepare("INSERT INTO calificaciones
                        (id_estudiante, id_materia, bimestre, calificacion)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE calificacion = ?");
                    $stmt->execute([$id_est, $id_materia, $bimestre, $nota_valor, $nota_valor]);
                    $actualizaciones += $stmt->rowCount();
                    
                } catch (Exception $e) {
                    $errores[] = "Error en estudiante $id_est, materia $id_materia, bimestre $bimestre: " . $e->getMessage();
                }
            }
        }
    }
    
    if (empty($errores)) {
        $_SESSION['success_message'] = "Se actualizaron $actualizaciones notas correctamente";
    } else {
        $_SESSION['error_message'] = implode("\n", $errores);
    }
    
    header("Location: ver_curso.php?id=$id_curso");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Notas - <?php echo htmlspecialchars($nombre_curso); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar-wrapper {
            width: 250px;
            flex-shrink: 0;
            background-color: #343a40;
            color: white;
        }

        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .content-area {
            padding: 20px;
            overflow-x: auto;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            color: #495057;
            border: none;
            border-radius: 0;
            padding: 15px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }

        .nav-tabs .nav-link:hover {
            border: none;
            background-color: #e9ecef;
        }

        .nav-tabs .nav-link.active:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tab-content {
            padding: 25px;
        }

        .notas-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .notas-table th {
            background-color: #007bff;
            color: white;
            text-align: center;
            vertical-align: middle;
            padding: 12px 8px;
            border: 1px solid #dee2e6;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .notas-table td {
            text-align: center;
            vertical-align: middle;
            padding: 10px 8px;
            border: 1px solid #dee2e6;
        }

        .student-name {
            background-color: #f8f9fa;
            text-align: left !important;
            font-weight: 500;
            min-width: 280px;
            max-width: 280px;
            position: sticky;
            left: 0;
            z-index: 5;
            border-right: 2px solid #007bff;
        }

        .nota-input {
            width: 80px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px;
            transition: all 0.3s ease;
        }

        .nota-input:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
            transform: scale(1.05);
        }

        .nota-input.modified {
            border-color: #28a745;
            background-color: #f8fff9;
        }

        .nota-input.error {
            border-color: #dc3545;
            background-color: #fff5f5;
        }

        .materia-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            min-width: 100px;
            font-size: 0.85rem;
        }

        .btn-guardar {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 15px 40px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            box-shadow: 0 6px 20px rgba(40,167,69,0.3);
        }

        .btn-guardar:hover {
            background: linear-gradient(45deg, #20c997, #28a745);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40,167,69,0.4);
            color: white;
        }

        .table-scroll {
            max-height: 65vh;
            overflow: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }

        .summary-stats {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .trimestre-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-left: 10px;
        }

        .trimestre-1 { background-color: #e3f2fd; color: #1976d2; }
        .trimestre-2 { background-color: #f3e5f5; color: #7b1fa2; }
        .trimestre-3 { background-color: #e8f5e8; color: #388e3c; }

        @media (max-width: 768px) {
            .layout-container {
                flex-direction: column;
            }
            
            .sidebar-wrapper {
                width: 100%;
            }
            
            .content-area {
                padding: 15px;
            }
            
            .nota-input {
                width: 70px;
            }
            
            .student-name {
                min-width: 200px;
                max-width: 200px;
            }

            .btn-guardar {
                position: relative;
                bottom: auto;
                right: auto;
                margin: 20px auto;
                display: block;
            }

            .nav-tabs .nav-link {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-wrapper">
            <div class="content-area">
                <!-- Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1><i class="fas fa-edit"></i> Editar Notas</h1>
                            <p class="mb-0">Curso: <?php echo htmlspecialchars($nombre_curso); ?></p>
                        </div>
                        <a href="ver_curso.php?id=<?php echo $id_curso; ?>" class="btn btn-light">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>  

                <!-- Mensajes de alerta -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Formulario de notas por trimestres -->
                <div class="table-container">
                    <form method="POST" id="notasForm">
                        <!-- Pestañas de trimestres -->
                        <ul class="nav nav-tabs" id="trimestreTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="trimestre1-tab" data-bs-toggle="tab" data-bs-target="#trimestre1" type="button" role="tab">
                                    <i class="fas fa-calendar-alt"></i> Primer Trimestre
                                    <span class="trimestre-badge trimestre-1">1°</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="trimestre2-tab" data-bs-toggle="tab" data-bs-target="#trimestre2" type="button" role="tab">
                                    <i class="fas fa-calendar-alt"></i> Segundo Trimestre
                                    <span class="trimestre-badge trimestre-2">2°</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="trimestre3-tab" data-bs-toggle="tab" data-bs-target="#trimestre3" type="button" role="tab">
                                    <i class="fas fa-calendar-alt"></i> Tercer Trimestre
                                    <span class="trimestre-badge trimestre-3">3°</span>
                                </button>
                            </li>
                        </ul>

                        <!-- Contenido de las pestañas -->
                        <div class="tab-content" id="trimestreTabContent">
                            <?php for ($trimestre = 1; $trimestre <= 3; $trimestre++): ?>
                                <div class="tab-pane fade <?php echo $trimestre === 1 ? 'show active' : ''; ?>" 
                                     id="trimestre<?php echo $trimestre; ?>" role="tabpanel">
                                    
                                    <div class="table-scroll">
                                        <table class="notas-table">
                                            <thead>
                                                <tr>
                                                    <th class="student-name">Estudiante</th>
                                                    <?php foreach ($materias as $materia): ?>
                                                        <th class="materia-header">
                                                            <?php echo htmlspecialchars($materia['nombre_materia']); ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($estudiantes as $index => $estudiante): ?>
                                                    <tr class="<?php echo $index % 2 === 0 ? 'table-light' : ''; ?>">
                                                        <td class="student-name">
                                                            <strong><?php echo htmlspecialchars($estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno'] . ', ' . $estudiante['nombres']); ?></strong>
                                                        </td>
                                                        <?php foreach ($materias as $materia): ?>
                                                            <td>
                                                                <input type="number" 
                                                                       class="nota-input" 
                                                                       name="notas[<?php echo $estudiante['id_estudiante']; ?>][<?php echo $materia['id_materia']; ?>][<?php echo $trimestre; ?>]" 
                                                                       value="<?php echo htmlspecialchars($calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$trimestre]); ?>" 
                                                                       min="0" 
                                                                       max="100" 
                                                                       step="0.1"
                                                                       placeholder="0-100"
                                                                       data-student="<?php echo $estudiante['id_estudiante']; ?>"
                                                                       data-materia="<?php echo $materia['id_materia']; ?>"
                                                                       data-trimestre="<?php echo $trimestre; ?>">
                                                            </td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <button type="submit" name="guardar_notas" class="btn btn-guardar">
                            <i class="fas fa-save"></i> Guardar Todas las Notas
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables para tracking de cambios
        let cambiosRealizados = false;
        let datosOriginales = {};

        document.addEventListener('DOMContentLoaded', function() {
            // Guardar datos originales para detectar cambios
            document.querySelectorAll('.nota-input').forEach(input => {
                const key = `${input.dataset.student}-${input.dataset.materia}-${input.dataset.trimestre}`;
                datosOriginales[key] = input.value;
            });

            // Validación en tiempo real
            document.querySelectorAll('.nota-input').forEach(input => {
                input.addEventListener('input', function() {
                    const value = parseFloat(this.value);
                    const key = `${this.dataset.student}-${this.dataset.materia}-${this.dataset.trimestre}`;
                    
                    // Verificar si hubo cambios
                    if (this.value !== datosOriginales[key]) {
                        cambiosRealizados = true;
                        this.classList.add('modified');
                    } else {
                        this.classList.remove('modified');
                    }

                    // Validar rango
                    if (this.value !== '' && (isNaN(value) || value < 0 || value > 100)) {
                        this.classList.add('error');
                        this.classList.remove('modified');
                    } else {
                        this.classList.remove('error');
                    }

                    // Corregir valores fuera de rango
                    if (value < 0) this.value = 0;
                    if (value > 100) this.value = 100;
                });

                // Formatear al perder el foco
                input.addEventListener('blur', function() {
                    if (this.value !== '' && !isNaN(this.value)) {
                        this.value = parseFloat(this.value).toFixed(1);
                    }
                });
            });

            // Confirmación antes de salir sin guardar
            window.addEventListener('beforeunload', function(e) {
                if (cambiosRealizados) {
                    e.preventDefault();
                    e.returnValue = '¿Estás seguro de que quieres salir sin guardar los cambios?';
                }
            });

            // Confirmación al enviar formulario
            document.getElementById('notasForm').addEventListener('submit', function(e) {
                if (cambiosRealizados) {
                    if (!confirm('¿Está seguro de que desea guardar todos los cambios realizados?')) {
                        e.preventDefault();
                        return;
                    }
                }
                cambiosRealizados = false; // Resetear flag
            });

            // Atajos de teclado para navegación entre trimestres
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey) {
                    switch(e.key) {
                        case '1':
                            e.preventDefault();
                            document.getElementById('trimestre1-tab').click();
                            break;
                        case '2':
                            e.preventDefault();
                            document.getElementById('trimestre2-tab').click();
                            break;
                        case '3':
                            e.preventDefault();
                            document.getElementById('trimestre3-tab').click();
                            break;
                    }
                }
            });
        });
    </script>
</body>
</html>
