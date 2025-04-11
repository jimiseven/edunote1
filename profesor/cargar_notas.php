<?php
session_start();
require_once '../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header('Location: ../index.php');
    exit();
}

// Obtener ID del profesor
$profesor_id = $_SESSION['user_id'];

// Verificar parámetros de la URL
if (!isset($_GET['curso_materia']) || empty($_GET['curso_materia'])) {
    header('Location: dashboard.php?error=params');
    exit();
}

$id_curso_materia = $_GET['curso_materia'];

// Conexión a la base de datos
$database = new Database();
$conn = $database->connect();

// Obtener información del curso y materia
$query_info = "
    SELECT c.nivel, c.curso, c.paralelo, m.nombre_materia, c.id_curso, m.id_materia
    FROM cursos_materias cm
    INNER JOIN cursos c ON cm.id_curso = c.id_curso
    INNER JOIN materias m ON cm.id_materia = m.id_materia
    WHERE cm.id_curso_materia = :id_curso_materia
";
$stmt_info = $conn->prepare($query_info);
$stmt_info->bindParam(':id_curso_materia', $id_curso_materia, PDO::PARAM_INT);
$stmt_info->execute();

if ($stmt_info->rowCount() == 0) {
    header('Location: dashboard.php?error=notfound');
    exit();
}

$info = $stmt_info->fetch(PDO::FETCH_ASSOC);
$id_curso = $info['id_curso'];
$id_materia = $info['id_materia'];
$curso_nombre = $info['nivel'] . ' ' . $info['curso'] . ' "' . $info['paralelo'] . '"';
$materia_nombre = $info['nombre_materia'];

// Obtener lista de estudiantes del curso
$query_estudiantes = "
    SELECT id_estudiante, CONCAT(nombres, ' ', apellido_paterno, ' ', apellido_materno) AS nombre_completo
    FROM estudiantes
    WHERE id_curso = :id_curso
    ORDER BY apellido_paterno, apellido_materno, nombres
";
$stmt_estudiantes = $conn->prepare($query_estudiantes);
$stmt_estudiantes->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
$stmt_estudiantes->execute();
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// Verificar si hay notas existentes
$notas_existentes = [];
$query_notas = "
    SELECT id_estudiante, bimestre, calificacion
    FROM calificaciones
    WHERE id_materia = :id_materia AND id_estudiante IN (
        SELECT id_estudiante FROM estudiantes WHERE id_curso = :id_curso
    )
";
$stmt_notas = $conn->prepare($query_notas);
$stmt_notas->bindParam(':id_materia', $id_materia, PDO::PARAM_INT);
$stmt_notas->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
$stmt_notas->execute();

if ($stmt_notas->rowCount() > 0) {
    $resultados = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);
    foreach ($resultados as $resultado) {
        $notas_existentes[$resultado['id_estudiante']][$resultado['bimestre']] = $resultado['calificacion'];
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_notas'])) {
    try {
        $conn->beginTransaction();
        
        foreach ($_POST['notas'] as $estudiante_id => $bimestres) {
            foreach ($bimestres as $bimestre => $nota) {
                if ($nota === '') continue; // Saltar si está vacío
                
                $nota = floatval(str_replace(',', '.', $nota)); // Asegurar formato numérico
                
                // Verificar si ya existe la nota
                $query_check = "
                    SELECT id_calificacion
                    FROM calificaciones
                    WHERE id_estudiante = :id_estudiante
                    AND id_materia = :id_materia
                    AND bimestre = :bimestre
                ";
                $stmt_check = $conn->prepare($query_check);
                $stmt_check->bindParam(':id_estudiante', $estudiante_id, PDO::PARAM_INT);
                $stmt_check->bindParam(':id_materia', $id_materia, PDO::PARAM_INT);
                $stmt_check->bindParam(':bimestre', $bimestre, PDO::PARAM_INT);
                $stmt_check->execute();
                
                if ($stmt_check->rowCount() > 0) {
                    // Actualizar nota existente
                    $query_update = "
                        UPDATE calificaciones
                        SET calificacion = :nota
                        WHERE id_estudiante = :id_estudiante
                        AND id_materia = :id_materia
                        AND bimestre = :bimestre
                    ";
                    $stmt_update = $conn->prepare($query_update);
                    $stmt_update->bindParam(':nota', $nota, PDO::PARAM_STR);
                    $stmt_update->bindParam(':id_estudiante', $estudiante_id, PDO::PARAM_INT);
                    $stmt_update->bindParam(':id_materia', $id_materia, PDO::PARAM_INT);
                    $stmt_update->bindParam(':bimestre', $bimestre, PDO::PARAM_INT);
                    $stmt_update->execute();
                } else {
                    // Insertar nueva nota
                    $query_insert = "
                        INSERT INTO calificaciones (id_estudiante, id_materia, bimestre, calificacion)
                        VALUES (:id_estudiante, :id_materia, :bimestre, :nota)
                    ";
                    $stmt_insert = $conn->prepare($query_insert);
                    $stmt_insert->bindParam(':id_estudiante', $estudiante_id, PDO::PARAM_INT);
                    $stmt_insert->bindParam(':id_materia', $id_materia, PDO::PARAM_INT);
                    $stmt_insert->bindParam(':bimestre', $bimestre, PDO::PARAM_INT);
                    $stmt_insert->bindParam(':nota', $nota, PDO::PARAM_STR);
                    $stmt_insert->execute();
                }
            }
        }
        
        // Actualizar estado a "CARGADO" en la tabla profesores_materias_cursos
        $query_update_estado = "
            UPDATE profesores_materias_cursos
            SET estado = 'CARGADO'
            WHERE id_personal = :profesor_id
            AND id_curso_materia = :id_curso_materia
        ";
        $stmt_update_estado = $conn->prepare($query_update_estado);
        $stmt_update_estado->bindParam(':profesor_id', $profesor_id, PDO::PARAM_INT);
        $stmt_update_estado->bindParam(':id_curso_materia', $id_curso_materia, PDO::PARAM_INT);
        $stmt_update_estado->execute();
        
        $conn->commit();
        
        // Recargar la página con mensaje de éxito
        header("Location: cargar_notas.php?curso_materia=$id_curso_materia&success=1");
        exit();
        
    } catch(PDOException $e) {
        $conn->rollBack();
        $error_message = "Error al guardar las notas: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduNote - Cargar Notas</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .container-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            background-color: white;
        }
        .header-section {
            background-color: #f8d7da;
            padding: 10px;
            border-radius: 5px 5px 0 0;
            margin-bottom: 20px;
        }
        .sidebar {
            min-height: 100vh;
            border-right: 1px solid #dee2e6;
            padding-top: 20px;
        }
        .main-content {
            padding: 20px;
        }
        .table input {
            width: 60px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="container">
            <h5 class="mb-0">Cargar notas al curso escogido</h5>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Menú lateral -->
            <div class="col-md-2 sidebar">
                <h3>EduNote</h3>
                <div class="nav flex-column nav-pills mt-4">
                    <a class="nav-link active" href="#">Cursos</a>
                    <a class="nav-link" href="#">Otros</a>
                </div>
            </div>
            
            <!-- Contenido principal -->
            <div class="col-md-10 main-content">
                <div class="container-card">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h2><?php echo htmlspecialchars($curso_nombre); ?></h2>
                        </div>
                        <div class="col-md-6 text-end">
                            <h2>Materia: <?php echo htmlspecialchars($materia_nombre); ?></h2>
                        </div>
                    </div>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success">Las notas se han guardado correctamente.</div>
                    <?php endif; ?>
                    
                    <form method="post" id="form-notas">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th style="width: 40%">Nombre</th>
                                        <th style="width: 15%">N1</th>
                                        <th style="width: 15%">N2</th>
                                        <th style="width: 15%">N3</th>
                                        <th style="width: 15%">PROMEDIO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($estudiantes) > 0): ?>
                                        <?php foreach ($estudiantes as $estudiante): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($estudiante['nombre_completo']); ?></td>
                                                <td>
                                                    <input type="text" class="form-control nota-input" 
                                                           name="notas[<?php echo $estudiante['id_estudiante']; ?>][1]" 
                                                           value="<?php echo isset($notas_existentes[$estudiante['id_estudiante']][1]) ? $notas_existentes[$estudiante['id_estudiante']][1] : ''; ?>"
                                                           data-row="<?php echo $estudiante['id_estudiante']; ?>">
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control nota-input" 
                                                           name="notas[<?php echo $estudiante['id_estudiante']; ?>][2]" 
                                                           value="<?php echo isset($notas_existentes[$estudiante['id_estudiante']][2]) ? $notas_existentes[$estudiante['id_estudiante']][2] : ''; ?>"
                                                           data-row="<?php echo $estudiante['id_estudiante']; ?>">
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control nota-input" 
                                                           name="notas[<?php echo $estudiante['id_estudiante']; ?>][3]" 
                                                           value="<?php echo isset($notas_existentes[$estudiante['id_estudiante']][3]) ? $notas_existentes[$estudiante['id_estudiante']][3] : ''; ?>"
                                                           data-row="<?php echo $estudiante['id_estudiante']; ?>">
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control" 
                                                           id="promedio-<?php echo $estudiante['id_estudiante']; ?>" 
                                                           readonly>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No hay estudiantes asignados a este curso.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <a href="dashboard.php" class="btn btn-secondary">Atrás</a>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="submit" name="guardar_notas" class="btn btn-primary">Guardar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Calcular promedios iniciales
            calcularTodosPromedios();
            
            // Agregar event listeners a todos los inputs de notas
            const inputsNotas = document.querySelectorAll('.nota-input');
            inputsNotas.forEach(input => {
                input.addEventListener('input', function() {
                    const row = this.dataset.row;
                    calcularPromedio(row);
                });
                
                // Validar entradas numéricas
                input.addEventListener('keypress', function(e) {
                    const charCode = e.which ? e.which : e.keyCode;
                    if (charCode == 46 || charCode == 44) {
                        // Permitir solo un punto o coma
                        if (this.value.indexOf('.') !== -1 || this.value.indexOf(',') !== -1) {
                            e.preventDefault();
                        }
                    } else if (charCode > 31 && (charCode < 48 || charCode > 57)) {
                        // Aceptar solo números
                        e.preventDefault();
                    }
                });
            });
            
            // Función para calcular promedio por estudiante
            function calcularPromedio(idEstudiante) {
                const inputs = document.querySelectorAll(`input[data-row="${idEstudiante}"]`);
                let sum = 0;
                let count = 0;
                
                inputs.forEach(input => {
                    if (input.value.trim() !== '') {
                        // Reemplazar coma por punto para el cálculo
                        const valor = parseFloat(input.value.replace(',', '.'));
                        if (!isNaN(valor)) {
                            sum += valor;
                            count++;
                        }
                    }
                });
                
                const promedio = count > 0 ? (sum / count).toFixed(2) : '';
                document.getElementById(`promedio-${idEstudiante}`).value = promedio;
            }
            
            // Calcular promedios de todos los estudiantes
            function calcularTodosPromedios() {
                const rows = new Set();
                document.querySelectorAll('.nota-input').forEach(input => {
                    rows.add(input.dataset.row);
                });
                
                rows.forEach(row => {
                    calcularPromedio(row);
                });
            }
        });
    </script>
</body>
</html>
