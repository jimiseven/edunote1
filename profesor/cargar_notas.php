<?php
session_start();
require_once '../config/database.php';

function calcularPromedioTrimestre($notasTrimestre, $esInicial = false) {
    if ($esInicial) {
        return '--';
    }
    if (empty($notasTrimestre)) {
        return 'N/A';
    }
    $valores = [];
    foreach ($notasTrimestre as $valor) {
        if ($valor !== null && $valor !== '' && is_numeric($valor)) {
            $valores[] = (float)$valor;
        }
    }
    if (empty($valores)) {
        return 'N/A';
    }
    return number_format(array_sum($valores) / count($valores), 2);
}

function construirUrlPeriodo($idCursoMateria, $trimestre, $parcial, $extra = []) {
    $params = array_merge([
        'curso_materia' => $idCursoMateria,
        'trimestre' => $trimestre,
        'parcial' => $parcial
    ], $extra);
    return 'cargar_notas.php?' . http_build_query($params);
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 2) {
    header('Location: ../index.php');
    exit();
}

$profesor_id = $_SESSION['user_id'];
$id_curso_materia = isset($_GET['curso_materia']) ? (int)$_GET['curso_materia'] : 0;
if ($id_curso_materia <= 0) {
    header('Location: dashboard.php?error=params');
    exit();
}

$conn = (new Database())->connect();

$stmt = $conn->query("SELECT anio_escolar FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
$gestionConfigurada = $stmt->fetchColumn();
$gestionConfigurada = $gestionConfigurada ? trim((string)$gestionConfigurada) : '';
$gestionActual = $gestionConfigurada !== '' ? $gestionConfigurada : date('Y');
$gestionAlternativa = null;
if (preg_match('/\b(20\d{2})\b/', $gestionActual, $matches)) {
    $gestionAlternativa = $matches[1];
}

$stmt = $conn->prepare("SELECT c.id_curso, c.nivel, m.id_materia,
                        CONCAT(c.nivel, ' ', c.curso, ' \"', c.paralelo, '\"') AS curso_nombre,
                        m.nombre_materia
                        FROM cursos_materias cm
                        JOIN cursos c ON cm.id_curso = c.id_curso
                        JOIN materias m ON cm.id_materia = m.id_materia
                        WHERE cm.id_curso_materia = ?");
$stmt->execute([$id_curso_materia]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
    header('Location: dashboard.php?error=notfound');
    exit();
}

$es_inicial = ($curso['nivel'] == 'Inicial');
$campo = $es_inicial ? 'comentario' : 'calificacion';

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

$estudiantesPorId = [];
foreach ($estudiantes as $estudiante) {
    $estudiantesPorId[$estudiante['id_estudiante']] = $estudiante;
}

$sqlPeriodos = "SELECT id_periodo_evaluacion, gestion, trimestre, parcial, nombre, fecha_inicio, fecha_fin, esta_activo
                FROM periodos_evaluacion
                WHERE gestion = ?";
$paramsPeriodos = [$gestionActual];
if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
    $sqlPeriodos .= " OR gestion = ?";
    $paramsPeriodos[] = $gestionAlternativa;
}
$sqlPeriodos .= " ORDER BY trimestre, parcial";
$stmt = $conn->prepare($sqlPeriodos);
$stmt->execute($paramsPeriodos);
$periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($periodos)) {
    die('No existen periodos de evaluación configurados para la gestión actual (' . htmlspecialchars($gestionActual) . ').');
}

if ($gestionAlternativa !== null) {
    foreach ($periodos as $periodoEncontrado) {
        $gestionActual = $periodoEncontrado['gestion'];
        break;
    }
}

$periodosPorTrimestre = [];
$periodosActivos = [];
foreach ($periodos as $periodo) {
    $trimestre = (int)$periodo['trimestre'];
    $parcial = (int)$periodo['parcial'];
    $periodosPorTrimestre[$trimestre][$parcial] = $periodo;

    $hoy = date('Y-m-d');
    $dentroRango = (empty($periodo['fecha_inicio']) || $hoy >= $periodo['fecha_inicio']) &&
                   (empty($periodo['fecha_fin']) || $hoy <= $periodo['fecha_fin']);
    if ((int)$periodo['esta_activo'] === 1 && $dentroRango) {
        $periodosActivos[] = $periodo;
    }
}

$trimestreSeleccionado = isset($_REQUEST['trimestre']) ? (int)$_REQUEST['trimestre'] : 0;
$parcialSeleccionado = isset($_REQUEST['parcial']) ? (int)$_REQUEST['parcial'] : 0;

if ($trimestreSeleccionado <= 0 || $parcialSeleccionado <= 0 || !isset($periodosPorTrimestre[$trimestreSeleccionado][$parcialSeleccionado])) {
    if (!empty($periodosActivos)) {
        $trimestreSeleccionado = (int)$periodosActivos[0]['trimestre'];
        $parcialSeleccionado = (int)$periodosActivos[0]['parcial'];
    } else {
        $primerPeriodo = $periodos[0];
        $trimestreSeleccionado = (int)$primerPeriodo['trimestre'];
        $parcialSeleccionado = (int)$primerPeriodo['parcial'];
    }
}

$periodoSeleccionado = $periodosPorTrimestre[$trimestreSeleccionado][$parcialSeleccionado];
$idPeriodoSeleccionado = (int)$periodoSeleccionado['id_periodo_evaluacion'];
$hoy = date('Y-m-d');
$periodoEditable = (int)$periodoSeleccionado['esta_activo'] === 1 &&
                   (empty($periodoSeleccionado['fecha_inicio']) || $hoy >= $periodoSeleccionado['fecha_inicio']) &&
                   (empty($periodoSeleccionado['fecha_fin']) || $hoy <= $periodoSeleccionado['fecha_fin']);

$stmt = $conn->prepare("SELECT cp.id_estudiante, pe.trimestre, pe.parcial, cp.$campo AS valor
                        FROM calificaciones_parciales cp
                        INNER JOIN periodos_evaluacion pe ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
                        WHERE cp.id_materia = ?
                          AND pe.gestion = ?");
$stmt->execute([$curso['id_materia'], $gestionActual]);
$notas = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $notas[$row['id_estudiante']][(int)$row['trimestre']][(int)$row['parcial']] = $row['valor'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("SELECT id_periodo_evaluacion, fecha_inicio, fecha_fin, esta_activo
                                FROM periodos_evaluacion
                                WHERE gestion = ?
                                  AND trimestre = ?
                                  AND parcial = ?
                                LIMIT 1");
        $stmt->execute([$gestionActual, $trimestreSeleccionado, $parcialSeleccionado]);
        $periodoValidado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$periodoValidado) {
            throw new Exception("El periodo seleccionado no existe.");
        }

        $periodoEditable = (int)$periodoValidado['esta_activo'] === 1 &&
                           (empty($periodoValidado['fecha_inicio']) || $hoy >= $periodoValidado['fecha_inicio']) &&
                           (empty($periodoValidado['fecha_fin']) || $hoy <= $periodoValidado['fecha_fin']);

        if (!$periodoEditable) {
            throw new Exception("El trimestre $trimestreSeleccionado - parcial $parcialSeleccionado no está habilitado para carga de notas");
        }

        $idPeriodoSeleccionado = (int)$periodoValidado['id_periodo_evaluacion'];
        $conn->beginTransaction();

        if (isset($_POST['guardar_notas'])) {
            $notasPost = $_POST['notas'] ?? [];
            foreach ($estudiantes as $estudiante) {
                $idEstudiante = (int)$estudiante['id_estudiante'];
                $valor = isset($notasPost[$idEstudiante]) ? trim($notasPost[$idEstudiante]) : '';

                if ($es_inicial) {
                    if ($valor === '') {
                        $conn->prepare("DELETE FROM calificaciones_parciales
                                        WHERE id_estudiante = ?
                                          AND id_materia = ?
                                          AND id_periodo_evaluacion = ?")
                             ->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado]);
                        continue;
                    }

                    $conn->prepare("INSERT INTO calificaciones_parciales
                                    (id_estudiante, id_materia, id_periodo_evaluacion, comentario)
                                    VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE comentario = VALUES(comentario)")
                         ->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado, $valor]);
                } else {
                    if ($valor === '') {
                        $conn->prepare("DELETE FROM calificaciones_parciales
                                        WHERE id_estudiante = ?
                                          AND id_materia = ?
                                          AND id_periodo_evaluacion = ?")
                             ->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado]);
                        continue;
                    }

                    if (!is_numeric(str_replace(',', '.', $valor))) {
                        throw new Exception("Nota inválida para: " . $estudiante['nombre']);
                    }

                    $notaValor = (float)str_replace(',', '.', $valor);
                    $conn->prepare("INSERT INTO calificaciones_parciales
                                    (id_estudiante, id_materia, id_periodo_evaluacion, calificacion)
                                    VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion)")
                         ->execute([$idEstudiante, $curso['id_materia'], $idPeriodoSeleccionado, $notaValor]);
                }
            }
        }

        if (isset($_POST['guardar_excel'])) {
            $datosExcelTexto = isset($_POST['datos_excel']) ? trim($_POST['datos_excel']) : '';
            $lineas = $datosExcelTexto === '' ? [] : preg_split('/\r\n|\r|\n/', $datosExcelTexto);

            if (count($lineas) !== count($estudiantes)) {
                throw new Exception("La cantidad de " . ($es_inicial ? "comentarios" : "notas") . " no coincide con el número de estudiantes.");
            }

            foreach ($estudiantes as $index => $estudiante) {
                $valor = trim($lineas[$index]);

                if ($es_inicial) {
                    if ($valor === '') {
                        $conn->prepare("DELETE FROM calificaciones_parciales
                                        WHERE id_estudiante = ?
                                          AND id_materia = ?
                                          AND id_periodo_evaluacion = ?")
                             ->execute([$estudiante['id_estudiante'], $curso['id_materia'], $idPeriodoSeleccionado]);
                        continue;
                    }

                    $conn->prepare("INSERT INTO calificaciones_parciales
                                    (id_estudiante, id_materia, id_periodo_evaluacion, comentario)
                                    VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE comentario = VALUES(comentario)")
                         ->execute([$estudiante['id_estudiante'], $curso['id_materia'], $idPeriodoSeleccionado, $valor]);
                } else {
                    if ($valor === '') {
                        $conn->prepare("DELETE FROM calificaciones_parciales
                                        WHERE id_estudiante = ?
                                          AND id_materia = ?
                                          AND id_periodo_evaluacion = ?")
                             ->execute([$estudiante['id_estudiante'], $curso['id_materia'], $idPeriodoSeleccionado]);
                        continue;
                    }

                    if (!is_numeric(str_replace(',', '.', $valor))) {
                        throw new Exception("Nota inválida en la línea " . ($index + 1));
                    }

                    $notaValor = (float)str_replace(',', '.', $valor);
                    $conn->prepare("INSERT INTO calificaciones_parciales
                                    (id_estudiante, id_materia, id_periodo_evaluacion, calificacion)
                                    VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion)")
                         ->execute([$estudiante['id_estudiante'], $curso['id_materia'], $idPeriodoSeleccionado, $notaValor]);
                }
            }
        }

        $conn->prepare("UPDATE profesores_materias_cursos
                        SET estado = 'CARGADO'
                        WHERE id_personal = ? AND id_curso_materia = ?")
             ->execute([$profesor_id, $id_curso_materia]);

        $conn->commit();
        header('Location: ' . construirUrlPeriodo($id_curso_materia, $trimestreSeleccionado, $parcialSeleccionado, ['success' => 1]));
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
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
        .periodo-inactivo-th {
            background: #f8f8f8 !important;
            color: #999 !important;
            font-weight: 400;
        }
        .periodo-activo-th {
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
        .periodo-toolbar {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .periodo-badge {
            font-size: 0.9rem;
        }
        .nota-ref {
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
                    <div class="periodo-toolbar">
                        <form method="get" class="row g-3 align-items-end">
                            <input type="hidden" name="curso_materia" value="<?php echo $id_curso_materia; ?>">
                            <div class="col-md-3">
                                <label class="form-label">Trimestre</label>
                                <select name="trimestre" class="form-select">
                                    <?php foreach ($periodosPorTrimestre as $trimestre => $parciales): ?>
                                        <option value="<?php echo $trimestre; ?>" <?php echo $trimestre == $trimestreSeleccionado ? 'selected' : ''; ?>>
                                            Trimestre <?php echo $trimestre; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Parcial</label>
                                <select name="parcial" class="form-select">
                                    <?php foreach (($periodosPorTrimestre[$trimestreSeleccionado] ?? []) as $parcial => $periodo): ?>
                                        <option value="<?php echo $parcial; ?>" <?php echo $parcial == $parcialSeleccionado ? 'selected' : ''; ?>>
                                            Parcial <?php echo $parcial; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-outline-primary w-100">Ver periodo</button>
                            </div>
                        </form>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="badge <?php echo $periodoEditable ? 'bg-success' : 'bg-secondary'; ?> periodo-badge">
                                <?php echo $periodoEditable ? 'Periodo habilitado' : 'Periodo no habilitado'; ?>
                            </span>
                            <span class="badge bg-info text-dark periodo-badge">
                                Gestión <?php echo htmlspecialchars($gestionActual); ?>
                            </span>
                            <span class="badge bg-light text-dark border periodo-badge">
                                Trimestre <?php echo $trimestreSeleccionado; ?> - Parcial <?php echo $parcialSeleccionado; ?>
                            </span>
                            <span class="badge bg-light text-dark border periodo-badge">
                                Inicio: <?php echo $periodoSeleccionado['fecha_inicio'] ? htmlspecialchars($periodoSeleccionado['fecha_inicio']) : 'Sin fecha'; ?>
                            </span>
                            <span class="badge bg-light text-dark border periodo-badge">
                                Fin: <?php echo $periodoSeleccionado['fecha_fin'] ? htmlspecialchars($periodoSeleccionado['fecha_fin']) : 'Sin fecha'; ?>
                            </span>
                        </div>
                    </div>
                    <?php if (!$es_inicial): ?>
                        <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#modalExcel" <?php echo !$periodoEditable ? 'disabled' : ''; ?>>
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
                                        <input type="hidden" name="trimestre" value="<?php echo $trimestreSeleccionado; ?>">
                                        <input type="hidden" name="parcial" value="<?php echo $parcialSeleccionado; ?>">
                                        <div class="modal-body">
                                            <div class="alert alert-info">
                                                Cargará notas para <strong>Trimestre <?php echo $trimestreSeleccionado; ?> - Parcial <?php echo $parcialSeleccionado; ?></strong>.
                                            </div>
                                            <div class="mb-3">
                                                <label>Pegue aquí la columna de notas:</label>
                                                <textarea name="datos_excel" class="form-control" placeholder="Pegue aquí SOLO la columna de notas desde Excel"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="submit" name="guardar_excel" class="btn btn-primary" <?php echo !$periodoEditable ? 'disabled' : ''; ?>>Cargar Notas</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="trimestre" value="<?php echo $trimestreSeleccionado; ?>">
                        <input type="hidden" name="parcial" value="<?php echo $parcialSeleccionado; ?>">
                        <div class="alert alert-warning mb-3">
                            <strong>Importante:</strong> Siempre revisar el orden de los estudiantes en la lista para que coincida con su lista propia
                        </div>
                        <?php if (!$periodoEditable): ?>
                            <div class="alert alert-secondary">
                                El periodo seleccionado está en modo consulta. Puedes ver las notas cargadas, pero no editar hasta que el administrador lo habilite y la fecha esté dentro del rango.
                            </div>
                        <?php endif; ?>
                        <div class="table-container">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Estudiante</th>
                                        <th class="text-center <?php echo $periodoEditable ? 'periodo-activo-th' : 'periodo-inactivo-th'; ?>">
                                            Parcial actual
                                        </th>
                                        <th class="text-center">P1</th>
                                        <th class="text-center">P2</th>
                                        <th class="text-center">P3</th>
                                        <th>Promedio trimestre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $contador = 1; ?>
                                    <?php foreach ($estudiantes as $est): ?>
                                    <tr>
                                        <td><?php echo $contador++; ?></td>
                                        <td><?php echo htmlspecialchars($est['nombre']); ?></td>
                                        <?php
                                        $notaActual = $notas[$est['id_estudiante']][$trimestreSeleccionado][$parcialSeleccionado] ?? '';
                                        $notasTrimestre = $notas[$est['id_estudiante']][$trimestreSeleccionado] ?? [];
                                        ?>
                                        <td>
                                            <?php if ($es_inicial): ?>
                                                <textarea
                                                    name="notas[<?php echo $est['id_estudiante']; ?>]"
                                                    class="coment-textarea <?php echo !$periodoEditable ? 'coment-disabled' : ''; ?>"
                                                    placeholder="<?php echo $periodoEditable ? 'Comentario parcial '.$parcialSeleccionado : 'No habilitado'; ?>"
                                                    <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>
                                                ><?php echo htmlspecialchars($notaActual); ?></textarea>
                                            <?php else: ?>
                                                <?php
                                                $notaStyle = '';
                                                if ($notaActual !== '' && is_numeric($notaActual) && $notaActual < 51) {
                                                    $notaStyle = 'color: #dc3545;';
                                                }
                                                ?>
                                                <input
                                                    type="number"
                                                    name="notas[<?php echo $est['id_estudiante']; ?>]"
                                                    class="form-control nota-input <?php echo !$periodoEditable ? 'nota-disabled' : ''; ?>"
                                                    value="<?php echo htmlspecialchars($notaActual); ?>"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>
                                                    oninput="highlightLowGrades(this)"
                                                    style="<?php echo $notaStyle; ?>"
                                                >
                                            <?php endif; ?>
                                        </td>
                                        <?php for ($parcialRef = 1; $parcialRef <= 3; $parcialRef++): ?>
                                            <?php $valorReferencia = $notas[$est['id_estudiante']][$trimestreSeleccionado][$parcialRef] ?? ''; ?>
                                            <td class="nota-ref <?php echo ($parcialRef === $parcialSeleccionado) ? 'table-primary' : ''; ?>">
                                                <?php echo $valorReferencia !== '' ? htmlspecialchars($valorReferencia) : '--'; ?>
                                            </td>
                                        <?php endfor; ?>
                                        <td class="align-middle">
                                            <span class="promedio"><?php echo calcularPromedioTrimestre($notasTrimestre, $es_inicial); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <a href="dashboard.php" class="btn btn-secondary">Volver</a>
                            <button type="submit" name="guardar_notas" class="btn btn-primary" <?php echo !$periodoEditable ? 'disabled' : ''; ?>>Guardar Notas</button>
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
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.nota-input').forEach(input => {
                if (input.value && parseFloat(input.value) < 51) {
                    input.style.color = '#dc3545';
                }
            });
            const trimestreSelect = document.querySelector('select[name="trimestre"]');
            const parcialSelect = document.querySelector('select[name="parcial"]');
            const parcialesPorTrimestre = <?php echo json_encode(array_map(function ($parciales) {
                return array_map(function ($periodo) {
                    return [
                        'parcial' => (int)$periodo['parcial'],
                        'label' => 'Parcial ' . (int)$periodo['parcial']
                    ];
                }, array_values($parciales));
            }, $periodosPorTrimestre)); ?>;
            if (trimestreSelect && parcialSelect) {
                trimestreSelect.addEventListener('change', function() {
                    const trimestre = this.value;
                    const opciones = parcialesPorTrimestre[trimestre] || [];
                    parcialSelect.innerHTML = '';
                    opciones.forEach(opcion => {
                        const option = document.createElement('option');
                        option.value = opcion.parcial;
                        option.textContent = opcion.label;
                        parcialSelect.appendChild(option);
                    });
                });
            }
        });
    </script>
</body>
</html>