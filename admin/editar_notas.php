<?php
session_start();
require_once '../config/database.php';

// Verificar autenticación de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

// Obtener ID del curso
$id_curso = isset($_GET['id']) ? intval($_GET['id']) : header('Location: dashboard.php?error=curso_no_especificado');

// Conexión a la base de datos
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

// Obtener estudiantes ordenados alfabéticamente
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
    SELECT m.id_materia, m.nombre_materia 
    FROM cursos_materias cm 
    JOIN materias m ON cm.id_materia = m.id_materia 
    WHERE cm.id_curso = ?
    ORDER BY m.nombre_materia
");
$stmt_materias->execute([$id_curso]);
$materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las calificaciones
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        if(isset($_POST['guardar_notas'])) {
            // Procesamiento de notas
            foreach ($_POST['notas'] as $id_est => $materias_data) {
                foreach ($materias_data as $id_materia => $bimestres) {
                    foreach ($bimestres as $bimestre => $valor) {
                        $valor = trim($valor);
                        
                        if ($valor === '') {
                            $conn->prepare("DELETE FROM calificaciones 
                                          WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?")
                                 ->execute([$id_est, $id_materia, $bimestre]);
                            continue;
                        }
                        
                        if (!is_numeric(str_replace(',', '.', $valor))) {
                            throw new Exception("Nota inválida para el estudiante ID: $id_est");
                        }
                        
                        $nota_valor = floatval(str_replace(',', '.', $valor));
                        
                        $conn->prepare("INSERT INTO calificaciones 
                                      (id_estudiante, id_materia, bimestre, calificacion)
                                      VALUES (?, ?, ?, ?)
                                      ON DUPLICATE KEY UPDATE calificacion = ?")
                             ->execute([$id_est, $id_materia, $bimestre, $nota_valor, $nota_valor]);
                    }
                }
            }
        }

        $conn->commit();
        header("Location: ver_curso.php?id=$id_curso&success=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Notas: <?php echo htmlspecialchars($nombre_curso); ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .content-wrapper {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 25px;
            margin: 20px;
        }
        .nota-input {
            width: 70px;
            text-align: center;
        }
        .table th {
            background-color: #f1f8ff;
            font-weight: 600;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-wrapper">
                    <h2 class="mb-4">Editar Notas: <?php echo htmlspecialchars($nombre_curso); ?></h2>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Estudiante</th>
                                        <?php foreach ($materias as $materia): ?>
                                            <th colspan="3"><?php echo htmlspecialchars($materia['nombre_materia']); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <th></th>
                                        <?php foreach ($materias as $materia): ?>
                                            <th>T1</th>
                                            <th>T2</th>
                                            <th>T3</th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estudiantes as $est): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($est['apellido_paterno'] . ' ' . $est['apellido_materno'] . ', ' . $est['nombres']); ?></td>
                                            <?php foreach ($materias as $materia): ?>
                                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                                    <td>
                                                        <input type="text" 
                                                               class="form-control nota-input" 
                                                               name="notas[<?php echo $est['id_estudiante']; ?>][<?php echo $materia['id_materia']; ?>][<?php echo $i; ?>]" 
                                                               value="<?php echo $calificaciones[$est['id_estudiante']][$materia['id_materia']][$i] ?? ''; ?>">
                                                    </td>
                                                <?php endfor; ?>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="ver_curso.php?id=<?php echo $id_curso; ?>" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" name="guardar_notas" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
