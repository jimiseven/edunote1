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
        $_SESSION['error_message'] = implode("<br>", $errores);
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
    <title>Editar Notas: <?= htmlspecialchars($nombre_curso) ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        .nota-input { width: 70px; text-align: center; }
        .table th { background-color: #f1f8ff; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="container mt-4">
            <h2>Editar Notas: <?= htmlspecialchars($nombre_curso) ?></h2>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <form method="post">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <?php foreach ($materias as $materia): ?>
                                    <th colspan="3"><?= htmlspecialchars($materia['nombre_materia']) ?></th>
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
                                    <td><?= htmlspecialchars($est['apellido_paterno'] . ' ' . $est['apellido_materno'] . ', ' . $est['nombres']) ?></td>
                                    <?php foreach ($materias as $materia): ?>
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <td>
                                                <input type="text" 
                                                       class="form-control nota-input" 
                                                       name="notas[<?= $est['id_estudiante'] ?>][<?= $materia['id_materia'] ?>][<?= $i ?>]" 
                                                       value="<?= $calificaciones[$est['id_estudiante']][$materia['id_materia']][$i] ?? '' ?>">
                                            </td>
                                        <?php endfor; ?>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <button type="submit" name="guardar_notas" class="btn btn-primary">Guardar Cambios</button>
                    <a href="ver_curso.php?id=<?= $id_curso ?>" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
