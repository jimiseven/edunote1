<?php
session_start();
require_once '../config/database.php';

/**
 * Calcula el promedio de notas (solo aplica para niveles no iniciales)
 */
function calcularPromedio($notas) {
    if (empty($notas)) return 'N/A';
    $suma = array_sum(array_filter($notas, 'is_numeric'));
    $count = count(array_filter($notas, 'is_numeric'));
    return $count ? number_format($suma / $count, 2) : 'N/A';
}

// Verificar autenticación
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header('Location: ../index.php');
    exit();
}

// Datos del profesor
$profesor_id = $_SESSION['user_id'];
$id_curso_materia = $_GET['curso_materia'] ?? header('Location: dashboard.php?error=params');

// Conexión a la base de datos
$conn = (new Database())->connect();

// Configuración de bimestres
$stmt = $conn->query("SELECT cantidad_bimestres FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
$cantidad_bimestres = $stmt->fetchColumn() ?: 3;

// Información del curso
$stmt = $conn->prepare("SELECT c.id_curso, c.nivel, m.id_materia, 
                        CONCAT(c.nivel, ' ', c.curso, ' \"', c.paralelo, '\"') AS curso_nombre,
                        m.nombre_materia
                        FROM cursos_materias cm
                        JOIN cursos c ON cm.id_curso = c.id_curso
                        JOIN materias m ON cm.id_materia = m.id_materia
                        WHERE cm.id_curso_materia = ?");
$stmt->execute([$id_curso_materia]);
$curso = $stmt->fetch();

if (!$curso) header('Location: dashboard.php?error=notfound');

// Determinar si es nivel inicial
$es_inicial = ($curso['nivel'] == 'Inicial');

// Estudiantes ordenados alfabéticamente
$stmt = $conn->prepare("SELECT id_estudiante, 
                        CONCAT_WS(' ', nombres, apellido_paterno, apellido_materno) AS nombre 
                        FROM estudiantes 
                        WHERE id_curso = ? 
                        ORDER BY apellido_paterno, apellido_materno, nombres");
$stmt->execute([$curso['id_curso']]);
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Notas existentes
$notas = [];
$campo = $es_inicial ? 'comentario' : 'calificacion'; // Campo dinámico según nivel
$stmt = $conn->prepare("SELECT id_estudiante, bimestre, $campo 
                        FROM calificaciones 
                        WHERE id_materia = ?");
$stmt->execute([$curso['id_materia']]);
foreach ($stmt->fetchAll() as $row) {
    $notas[$row['id_estudiante']][$row['bimestre']] = $row[$campo];
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        if (isset($_POST['guardar_notas'])) {
            foreach ($_POST['notas'] as $id_est => $bimestres) {
                foreach ($bimestres as $bim => $valor) {
                    $valor = trim($valor);

                    if ($es_inicial) {
                        // Procesar comentarios para nivel inicial
                        if ($valor === '') {
                            $conn->prepare("DELETE FROM calificaciones 
                                            WHERE id_estudiante = ? 
                                            AND id_materia = ? 
                                            AND bimestre = ?")
                                 ->execute([$id_est, $curso['id_materia'], $bim]);
                            continue;
                        }

                        $conn->prepare("INSERT INTO calificaciones 
                                        (id_estudiante, id_materia, bimestre, comentario)
                                        VALUES (?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE comentario = ?")
                             ->execute([$id_est, $curso['id_materia'], $bim, $valor, $valor]);
                    } else {
                        // Procesar notas numéricas para otros niveles
                        if ($valor === '') {
                            $conn->prepare("DELETE FROM calificaciones 
                                            WHERE id_estudiante = ? 
                                            AND id_materia = ? 
                                            AND bimestre = ?")
                                 ->execute([$id_est, $curso['id_materia'], $bim]);
                            continue;
                        }

                        if (!is_numeric(str_replace(',', '.', $valor))) {
                            throw new Exception("Nota inválida para: " . 
                                $estudiantes[array_search($id_est, array_column($estudiantes, 'id_estudiante'))]['nombre']);
                        }

                        $nota_valor = floatval(str_replace(',', '.', $valor));

                        $conn->prepare("INSERT INTO calificaciones 
                                       (id_estudiante, id_materia, bimestre, calificacion)
                                       VALUES (?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE calificacion = ?")
                             ->execute([$id_est, $curso['id_materia'], $bim, $nota_valor, $nota_valor]);
                    }
                }
            }
        }

        if (isset($_POST['guardar_excel'])) {
            $bimestre_excel = $_POST['bimestre_excel'];
            $datos_excel = explode("\n", trim($_POST['datos_excel']));

            if (count($datos_excel) !== count($estudiantes)) {
                throw new Exception("La cantidad de " . ($es_inicial ? "comentarios" : "notas") . " no coincide con el número de estudiantes.");
            }

            foreach ($estudiantes as $index => $est) {
                $valor = trim($datos_excel[$index]);

                if ($es_inicial) {
                    // Procesar comentarios desde Excel para nivel inicial
                    $conn->prepare("INSERT INTO calificaciones 
                                    (id_estudiante, id_materia, bimestre, comentario)
                                    VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE comentario = ?")
                         ->execute([$est['id_estudiante'], $curso['id_materia'], $bimestre_excel, $valor, $valor]);
                } else {
                    // Procesar notas numéricas desde Excel para otros niveles
                    if (!is_numeric(str_replace(',', '.', $valor))) {
                        throw new Exception("Nota inválida en la línea " . ($index + 1));
                    }

                    $nota_valor = floatval(str_replace(',', '.', $valor));

                    $conn->prepare("INSERT INTO calificaciones 
                                    (id_estudiante, id_materia, bimestre, calificacion)
                                    VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE calificacion = ?")
                         ->execute([$est['id_estudiante'], $curso['id_materia'], $bimestre_excel, $nota_valor, $nota_valor]);
                }
            }
        }

        // Actualizar estado del curso
        $conn->prepare("UPDATE profesores_materias_cursos
                       SET estado = 'CARGADO'
                       WHERE id_personal = ? AND id_curso_materia = ?")
             ->execute([$profesor_id, $id_curso_materia]);

        $conn->commit();
        header("Location: cargar_notas.php?curso_materia=$id_curso_materia&success=1");
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
    <title>EduNote - Cargar Notas</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        .container-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 25px;
            margin: 20px 0;
        }
        .nota-input {
            width: 80px;
            text-align: center;
        }
        .modal-body textarea {
            width: 100%;
            height: 150px;
            resize: none;
            font-family: monospace;
        }
        .coment-textarea {
            width: 100%;
            height: 100px;
            resize: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="container-card mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="text-primary"><?php echo $curso['curso_nombre']; ?></h3>
                        <h4 class="text-secondary"><?php echo $curso['nombre_materia']; ?></h4>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php elseif (isset($_GET['success'])): ?>
                        <div class="alert alert-success">¡Datos cargados correctamente!</div>
                    <?php endif; ?>

                    <!-- Modal para cargar desde Excel -->
                    <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#modalExcel">
                        <?php echo $es_inicial ? 'Cargar Comentarios desde Excel' : 'Cargar Notas desde Excel'; ?>
                    </button>

                    <div class="modal fade" id="modalExcel" tabindex="-1" aria-labelledby="modalExcelLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form method="post">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <?php echo $es_inicial ? 'Cargar Comentarios desde Excel' : 'Cargar Notas desde Excel'; ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label>Seleccione el bimestre:</label>
                                            <select name="bimestre_excel" class="form-select mb-3">
                                                <?php for ($i = 1; $i <= $cantidad_bimestres; $i++): ?>
                                                    <option value="<?php echo $i; ?>">Bimestre <?php echo $i; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <label>
                                                <?php echo $es_inicial ? 'Pega aquí los comentarios (uno por estudiante):' : 'Pega aquí las notas (una por línea):'; ?>
                                            </label>
                                            <textarea name="datos_excel" class="form-control" placeholder="<?php echo $es_inicial ? 'Comentario por línea...' : 'Nota por línea...'; ?>"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" name="guardar_excel" class="btn btn-primary">Cargar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario regular -->
                    <form method="post">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Estudiante</th>
                                        <?php for ($i = 1; $i <= $cantidad_bimestres; $i++): ?>
                                            <th class="text-center">Bimestre <?php echo $i; ?></th>
                                        <?php endfor; ?>
                                        <th>Promedio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $contador = 1; ?>
                                    <?php foreach ($estudiantes as $est): ?>
                                    <tr>
                                        <td><?php echo $contador++; ?></td>
                                        <td><?php echo htmlspecialchars($est['nombre']); ?></td>
                                        <?php for ($i = 1; $i <= $cantidad_bimestres; $i++): ?>
                                            <td>
                                                <?php if ($es_inicial): ?>
                                                    <!-- Nivel inicial: comentarios -->
                                                    <textarea 
                                                        name="notas[<?php echo $est['id_estudiante']; ?>][<?php echo $i; ?>]"
                                                        class="coment-textarea"
                                                        placeholder="Ingrese el comentario para este bimestre"><?= htmlspecialchars($notas[$est['id_estudiante']][$i] ?? '') ?></textarea>
                                                <?php else: ?>
                                                    <!-- Otros niveles: notas numéricas -->
                                                    <input 
                                                        type="number"
                                                        name="notas[<?php echo $est['id_estudiante']; ?>][<?php echo $i; ?>]"
                                                        class="form-control nota-input"
                                                        value="<?php echo $notas[$est['id_estudiante']][$i] ?? ''; ?>"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                    >
                                                <?php endif; ?>
                                            </td>
                                        <?php endfor; ?>
                                        <td class="align-middle">
                                            <?php if (!$es_inicial): ?>
                                                <span class="promedio"><?php echo calcularPromedio($notas[$est['id_estudiante']] ?? []); ?></span>
                                            <?php else: ?>
                                                -- <!-- Sin promedio para nivel inicial -->
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="dashboard.php" class="btn btn-secondary">Volver</a>
                            <button type="submit" name="guardar_notas" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
