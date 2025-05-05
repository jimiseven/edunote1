<?php
session_start();
require_once '../config/database.php';

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

$profesor_id = $_SESSION['user_id'];
$id_curso_materia = $_GET['curso_materia'] ?? header('Location: dashboard.php?error=params');

$conn = (new Database())->connect();

// Obtener bimestres activos como enteros
$stmt = $conn->query("SELECT numero_bimestre FROM bimestres_activos WHERE esta_activo = 1");
$bimestres_activos = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

$stmt = $conn->query("SELECT cantidad_bimestres FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
$cantidad_bimestres = $stmt->fetchColumn() ?: 3;

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

$es_inicial = ($curso['nivel'] == 'Inicial');

$stmt = $conn->prepare("SELECT id_estudiante, 
                        CASE
                            WHEN (apellido_paterno IS NULL OR apellido_paterno = '') AND (apellido_materno IS NOT NULL AND apellido_materno != '')
                            THEN CONCAT(apellido_materno, ' ', nombres)
                            ELSE CONCAT(apellido_paterno, ' ', apellido_materno, ' ', nombres)
                        END AS nombre
                        FROM estudiantes
                        WHERE id_curso = ?
                        ORDER BY
                        CASE
                            WHEN apellido_paterno IS NULL OR apellido_paterno = '' THEN 0
                            ELSE 1
                        END,
                        CASE
                            WHEN apellido_paterno IS NULL OR apellido_paterno = '' THEN apellido_materno
                            ELSE apellido_paterno
                        END,
                        apellido_materno,
                        nombres");
$stmt->execute([$curso['id_curso']]);
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$notas = [];
$campo = $es_inicial ? 'comentario' : 'calificacion';
$stmt = $conn->prepare("SELECT id_estudiante, bimestre, $campo 
                        FROM calificaciones 
                        WHERE id_materia = ?");
$stmt->execute([$curso['id_materia']]);
foreach ($stmt->fetchAll() as $row) {
    $notas[$row['id_estudiante']][$row['bimestre']] = $row[$campo];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        if (empty($bimestres_activos)) {
            throw new Exception("No hay bimestres habilitados para carga de notas. Contacte al administrador.");
        }

        if (isset($_POST['guardar_notas'])) {
            foreach ($_POST['notas'] as $id_est => $bimestres) {
                foreach ($bimestres as $bim => $valor) {
                    $bim_int = (int)$bim;
                    if (!in_array($bim_int, $bimestres_activos)) {
                        continue;
                    }
                    
                    $valor = trim($valor);

                    if ($es_inicial) {
                        if ($valor === '') {
                            $conn->prepare("DELETE FROM calificaciones 
                                            WHERE id_estudiante = ? 
                                            AND id_materia = ? 
                                            AND bimestre = ?")
                                 ->execute([$id_est, $curso['id_materia'], $bim_int]);
                            continue;
                        }

                        $conn->prepare("INSERT INTO calificaciones 
                                        (id_estudiante, id_materia, bimestre, comentario)
                                        VALUES (?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE comentario = ?")
                             ->execute([$id_est, $curso['id_materia'], $bim_int, $valor, $valor]);
                    } else {
                        if ($valor === '') {
                            $conn->prepare("DELETE FROM calificaciones 
                                            WHERE id_estudiante = ? 
                                            AND id_materia = ? 
                                            AND bimestre = ?")
                                 ->execute([$id_est, $curso['id_materia'], $bim_int]);
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
                             ->execute([$id_est, $curso['id_materia'], $bim_int, $nota_valor, $nota_valor]);
                    }
                }
            }
        }

        if (isset($_POST['guardar_excel'])) {
            $bimestre_excel = (int)$_POST['bimestre_excel'];
            if (!in_array($bimestre_excel, $bimestres_activos)) {
                throw new Exception("El bimestre $bimestre_excel no está habilitado para carga de notas");
            }

            $datos_excel = explode("\n", trim($_POST['datos_excel']));

            if (count($datos_excel) !== count($estudiantes)) {
                throw new Exception("La cantidad de " . ($es_inicial ? "comentarios" : "notas") . " no coincide con el número de estudiantes.");
            }

            foreach ($estudiantes as $index => $est) {
                $valor = trim($datos_excel[$index]);

                if ($es_inicial) {
                    $conn->prepare("INSERT INTO calificaciones 
                                    (id_estudiante, id_materia, bimestre, comentario)
                                    VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE comentario = ?")
                         ->execute([$est['id_estudiante'], $curso['id_materia'], $bimestre_excel, $valor, $valor]);
                } else {
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
        if (strpos($error, 'no está habilitado') !== false) {
            $error .= ". Contacte al administrador del sistema.";
        }
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
        .nota-disabled,
        .coment-disabled {
            background: #f2f2f2 !important;
            border-color: #d1d5db !important;
            color: #888 !important;
            cursor: not-allowed;
        }
        .bim-inactivo-th {
            background: #f8f8f8 !important;
            color: #999 !important;
            font-weight: 400;
        }
        .bim-activo-th {
            background: #e8f4ff !important;
            color: #244876 !important;
            font-weight: 600;
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
        
        /* Nuevos estilos para la tabla con scroll */
        .table-container {
            max-height: 70vh;
            overflow: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .table-container table {
            margin-bottom: 0;
        }
        .table-container thead th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 10;
        }
        .table-container tbody td:first-child,
        .table-container thead th:first-child {
            position: sticky;
            left: 0;
            background-color: white;
            z-index: 5;
        }
        .table-container tbody td:nth-child(2),
        .table-container thead th:nth-child(2) {
            position: sticky;
            left: 40px; /* Aprox el ancho de la columna # */
            background-color: white;
            z-index: 5;
        }
        .table-container thead th:first-child,
        .table-container thead th:nth-child(2) {
            z-index: 15;
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
                        <div class="alert alert-success">¡Notas cargadas correctamente!</div>
                    <?php endif; ?>

                    <!-- Modal para pegar notas desde Excel (Solo para niveles no inicial) -->
                    <?php if (!$es_inicial): ?>
                        <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#modalExcel">
                            Cargar desde Excel
                        </button>

                        <div class="modal fade" id="modalExcel" tabindex="-1" aria-labelledby="modalExcelLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="modalExcelLabel">Cargar Notas desde Excel</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="post">
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label>Seleccione el Trimestre:</label>
                                                <select name="bimestre_excel" class="form-select mb-3">
                                                    <?php for ($i = 1; $i <= $cantidad_bimestres; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo in_array($i, $bimestres_activos) ? "" : "disabled"; ?>>
                                                            Trimestre <?php echo $i; ?><?php echo in_array($i, $bimestres_activos) ? "" : " (no habilitado)"; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                                <label>Pegue aquí la columna de notas:</label>
                                                <textarea name="datos_excel" class="form-control" placeholder="Pegue aquí SOLO la columna de notas desde Excel"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="submit" name="guardar_excel" class="btn btn-primary">Cargar Notas</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Formulario regular -->
                    <form method="post">
                        <div class="alert alert-warning mb-3">
                            <strong>Importante:</strong> Siempre revisar el orden de los estudiantes en la lista para que coincida con su lista propia
                        </div>
                        <div class="table-container"> <!-- Contenedor con scroll -->
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Estudiante</th>
                                        <?php for ($i = 1; $i <= $cantidad_bimestres; $i++): ?>
                                            <th class="text-center <?php echo in_array($i, $bimestres_activos) ? 'bim-activo-th' : 'bim-inactivo-th'; ?>">
                                                Trimestre <?php echo $i; ?>
                                                <?php if (!in_array($i, $bimestres_activos)): ?>
                                                    <span class="badge bg-secondary ms-1">No habilitado</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success ms-1">Activo</span>
                                                <?php endif; ?>
                                            </th>
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
                                                        class="coment-textarea <?php echo !in_array($i, $bimestres_activos) ? 'coment-disabled' : ''; ?>"
                                                        placeholder="<?php echo in_array($i, $bimestres_activos) ? 'Comentario bimestre '.$i : 'No habilitado'; ?>"
                                                        <?php echo !in_array($i, $bimestres_activos) ? 'readonly disabled' : ''; ?>
                                                    ><?php echo htmlspecialchars($notas[$est['id_estudiante']][$i] ?? '') ?></textarea>
                                                <?php else: ?>
                                                    <!-- Otros niveles: notas numéricas -->
                                                    <input
                                                        type="number"
                                                        name="notas[<?php echo $est['id_estudiante']; ?>][<?php echo $i; ?>]"
                                                        class="form-control nota-input <?php echo !in_array($i, $bimestres_activos) ? 'nota-disabled' : ''; ?>"
                                                        value="<?php echo $notas[$est['id_estudiante']][$i] ?? ''; ?>"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                        <?php echo !in_array($i, $bimestres_activos) ? 'readonly disabled' : ''; ?>
                                                        oninput="highlightLowGrades(this)"
                                                        <?php
                                                        $nota_style = '';
                                                        if (isset($notas[$est['id_estudiante']][$i])) {
                                                            $nota_style = $notas[$est['id_estudiante']][$i] < 51 ? 'color: #dc3545' : '';
                                                        }
                                                        ?>
                                                        style="<?php echo $nota_style; ?>"
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
                            <button type="submit" name="guardar_notas" class="btn btn-primary">Guardar Notas</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        function highlightLowGrades(input) {
            input.style.color = input.value && parseFloat(input.value) < 51 ? '#dc3545' : '';
        }
        
        // Aplicar resaltado inicial al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.nota-input').forEach(input => {
                if (input.value && parseFloat(input.value) < 51) {
                    input.style.color = '#dc3545';
                }
            });
        });
    </script>
</body>
</html>