<?php
session_start();
require_once '../config/database.php';

// Verificar solo para administrador
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 4], true)) {
    header('Location: ../index.php');
    exit();
}

$puedeModificarEstudiantes = (int)$_SESSION['user_role'] === 1;

if (isset($_GET['action']) && $_GET['action'] === 'buscar_responsable') {
    header('Content-Type: application/json; charset=utf-8');

    $ci = isset($_GET['ci']) ? trim($_GET['ci']) : '';
    if ($ci === '') {
        echo json_encode(['found' => false]);
        exit();
    }

    try {
        $db = new Database();
        $conn = $db->connect();

        $stmt = $conn->prepare('SELECT id_responsable, nombre, apellido, carnet_identidad, telefono FROM responsables WHERE carnet_identidad = :ci LIMIT 1');
        $stmt->bindParam(':ci', $ci);
        $stmt->execute();
        $responsable = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($responsable) {
            echo json_encode(['found' => true, 'responsable' => $responsable]);
            exit();
        }

        echo json_encode(['found' => false]);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['found' => false, 'error' => 'Error al buscar responsable']);
        exit();
    }
}

$db = new Database();
$conn = $db->connect();

// Obtener estudiantes con filtro de búsqueda si existe
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "
    SELECT
        e.id_estudiante,
        e.nombres,
        e.apellido_paterno,
        e.apellido_materno,
        e.carnet_identidad AS ci,
        e.genero,
        e.rude,
        e.fecha_nacimiento,
        c.nivel,
        c.curso,
        c.paralelo,
        CONCAT(c.nivel, ' ', c.curso, '° ', c.paralelo) AS nombre_curso
    FROM estudiantes e
    LEFT JOIN cursos c ON e.id_curso = c.id_curso
";

if (!empty($search)) {
    $sql .= " WHERE e.carnet_identidad LIKE :search
              OR e.nombres LIKE :search
              OR e.apellido_paterno LIKE :search
              OR e.apellido_materno LIKE :search";
}

$sql .= " ORDER BY e.apellido_paterno ASC, e.apellido_materno ASC";
$stmt = $conn->prepare($sql);

if (!empty($search)) {
    $searchTerm = '%' . $search . '%';
    $stmt->bindParam(':search', $searchTerm);
}

$stmt->execute();
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Estudiantes</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        html, body { height: 100%; }
        body { background: #f8f9fa; margin: 0; padding: 0; overflow: hidden; }

        .main {
            display: flex;
            flex-direction: column;
            height: 100vh;
            height: 100dvh;
            padding: 1rem 1.5rem 1.25rem;
            min-height: 0;
        }

        .main-title { margin: 0; font-weight: 700; color: #11305e; font-size: 1.4rem; }
        .main-subtitle { color: #6c757d; font-size: 0.9rem; margin: 0; }

        .header-section { flex-shrink: 0; }

        .btn-nuevo {
            background-color: #28a745; color: #fff; border: none;
            padding: 8px 16px; border-radius: 6px; font-weight: 500;
            display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        }
        .btn-nuevo:hover { background-color: #218838; color: #fff; }

        .btn-exportar {
            background-color: #11305e; color: #fff; border: none;
            padding: 8px 16px; border-radius: 6px; font-weight: 500;
            display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        }
        .btn-exportar:hover { background-color: #0c2342; color: #fff; }

        .tabla-box {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: #fff; border: 1px solid #eef2f7;
            border-radius: 10px; box-shadow: 0 2px 12px rgba(16, 48, 94, 0.06);
            padding: 1rem;
        }

        .tabla-header {
            flex-shrink: 0;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 0.25rem 0.25rem 0.9rem; flex-wrap: wrap;
        }

        .result-count { color: #6c757d; font-size: 0.85rem; white-space: nowrap; }

        .table-responsive {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
        }

        .table-estudiantes { margin: 0; font-size: 0.92rem; }
        .table-estudiantes th {
            background-color: #11305e; color: #fff; font-weight: 500;
            position: sticky; top: 0; z-index: 1; white-space: nowrap;
        }
        .table-estudiantes td { vertical-align: middle; }
        .table-estudiantes tbody tr:nth-child(even) { background-color: #f4f8fb; }
        .table-estudiantes tbody tr:hover { background-color: #e9f5ff; }

        .badge-genero {
            display: inline-block; padding: 4px 10px; border-radius: 999px;
            font-size: 0.78rem; font-weight: 500; white-space: nowrap;
        }
        .badge-genero.masculino { background-color: #e7f1ff; color: #1d5fa8; }
        .badge-genero.femenino { background-color: #fde8ef; color: #b03060; }
        .badge-genero.vacio { background-color: #eef2f7; color: #6c757d; }

        .curso-cell { color: #11305e; font-weight: 500; white-space: nowrap; }
        .muted-cell { color: #8a94a6; }

        .acciones-cell { display: flex; gap: 6px; }
        .btn-accion {
            width: 30px; height: 30px; padding: 0; border-radius: 6px;
            font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center;
            border: none; transition: background-color .15s ease;
        }
        .btn-editar { background-color: #17a2b8; color: #fff; }
        .btn-editar:hover { background-color: #138496; color: #fff; }
        .btn-eliminar { background-color: #dc3545; color: #fff; }
        .btn-eliminar:hover { background-color: #c82333; color: #fff; }

        .modal-content { border: none; border-radius: 12px; overflow: hidden; }
        .modal-header { background: #11305e; color: #fff; border-bottom: none; }
        .modal-title { font-size: 1.05rem; font-weight: 600; }
        .modal-footer { border-top: 1px solid #eef2f7; }

        .form-section-title {
            font-size: 0.8rem; font-weight: 700; letter-spacing: 0.04em;
            text-transform: uppercase; color: #11305e; margin: 0 0 0.5rem;
        }
        .form-divider { border-top: 1px solid #eef2f7; margin: 0.75rem 0; }

        .form-label { font-size: 0.9rem; font-weight: 500; color: #33475b; }
        .form-control, .form-select { font-size: 0.95rem; }
        .required::after { content: " *"; color: #dc3545; }

        @media (max-width: 991px) {
            html, body { height: auto; overflow: auto; }
            .main { height: auto; min-height: 100vh; padding: 1rem; }
            .tabla-box { min-height: 55vh; }
            .table-responsive { max-height: 55vh; }
            .header-section { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid g-0">
        <div class="row g-0">
            <?php include '../includes/sidebar.php'; ?>
            <main class="w-100 main">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3 header-section">
                    <div>
                        <h1 class="main-title">Listado de Estudiantes</h1>
                        <p class="main-subtitle">Administra la información y los responsables de cada estudiante.</p>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <form class="d-flex" method="get" action="estudiantes.php">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text"
                                       name="search"
                                       id="searchInput"
                                       class="form-control border-start-0 ps-0"
                                       placeholder="Buscar por CI, nombre o apellido"
                                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                                       oninput="filtrarEstudiantes()">
                                <?php if (!empty($search)): ?>
                                <button id="clearSearch" class="btn btn-outline-secondary border-start-0" type="button" onclick="window.location.href='estudiantes.php'">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </form>
                        <a href="exportar_estudiantes.php" class="btn-exportar">
                            <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                        </a>
                        <?php if ($puedeModificarEstudiantes): ?>
                            <button type="button" class="btn-nuevo" data-bs-toggle="modal" data-bs-target="#modalNuevoEstudiante">
                                <i class="bi bi-plus-lg"></i> Nuevo Estudiante
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tabla-box">
                    <div class="tabla-header">
                        <span class="result-count">
                            <strong><?php echo count($estudiantes); ?></strong>
                            estudiante<?php echo count($estudiantes) === 1 ? '' : 's'; ?>
                            <?php if (!empty($search)): ?>
                                para «<?php echo htmlspecialchars($search); ?>»
                            <?php endif; ?>
                        </span>
                        <span class="text-muted small d-none d-md-inline">
                            <i class="bi bi-info-circle"></i> Haz clic en editar para modificar los datos.
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-estudiantes align-middle w-100">
                            <thead>
                                <tr>
                                    <th>Ap. Paterno</th>
                                    <th>Ap. Materno</th>
                                    <th>Nombres</th>
                                    <th>CI</th>
                                    <th>Curso</th>
                                    <th>Género</th>
                                    <th>RUDE</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $estudiante):
                                    $genero = strtolower((string)$estudiante['genero']);
                                    $generoClass = $genero === 'masculino' ? 'masculino' : ($genero === 'femenino' ? 'femenino' : 'vacio');
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($estudiante['apellido_paterno']); ?></td>
                                    <td><?php echo htmlspecialchars($estudiante['apellido_materno']); ?></td>
                                    <td><?php echo htmlspecialchars($estudiante['nombres']); ?></td>
                                    <td class="muted-cell"><?php echo htmlspecialchars($estudiante['ci']); ?></td>
                                    <td class="curso-cell"><?php echo htmlspecialchars($estudiante['nombre_curso'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge-genero <?php echo $generoClass; ?>">
                                            <?php echo htmlspecialchars($estudiante['genero'] ?: '—'); ?>
                                        </span>
                                    </td>
                                    <td class="muted-cell"><?php echo htmlspecialchars($estudiante['rude'] ?: '—'); ?></td>
                                    <td>
                                        <div class="acciones-cell">
                                            <?php if ($puedeModificarEstudiantes): ?>
                                                <a href="editar_estudiante.php?id=<?php echo $estudiante['id_estudiante']; ?>"
                                                   class="btn-accion btn-editar" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="eliminar_estudiante.php?id=<?php echo $estudiante['id_estudiante']; ?>"
                                                   class="btn-accion btn-eliminar"
                                                   onclick="return confirm('¿Está seguro de eliminar este estudiante?')" title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">Solo lectura</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($estudiantes)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox d-block fs-3 mb-2"></i>
                                        No se encontraron estudiantes.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php if ($puedeModificarEstudiantes): ?>
    <!-- Modal Nuevo Estudiante -->
    <div class="modal fade" id="modalNuevoEstudiante" tabindex="-1" aria-labelledby="modalNuevoEstudianteLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title mb-0" id="modalNuevoEstudianteLabel">Registro de Estudiante</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <form id="formNuevoEstudiante" action="guardar_estudiante.php" method="POST">
                        <h6 class="form-section-title">Datos del estudiante</h6>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label for="nombres" class="form-label required">Nombres</label>
                                <input type="text" class="form-control" id="nombres" name="nombres" required>
                            </div>
                            <div class="col-md-4">
                                <label for="apellido_paterno" class="form-label required">Ap. Paterno</label>
                                <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" required>
                            </div>
                            <div class="col-md-4">
                                <label for="apellido_materno" class="form-label">Ap. Materno</label>
                                <input type="text" class="form-control" id="apellido_materno" name="apellido_materno">
                            </div>
                            <div class="col-md-3">
                                <label for="rude" class="form-label">RUDE</label>
                                <input type="text" class="form-control" id="rude" name="rude">
                            </div>
                            <div class="col-md-3">
                                <label for="ci" class="form-label">CI</label>
                                <input type="text" class="form-control" id="ci" name="ci">
                            </div>
                            <div class="col-md-3">
                                <label for="fecha_nacimiento" class="form-label">F. Nacimiento</label>
                                <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento">
                            </div>
                            <div class="col-md-3">
                                <label for="genero" class="form-label">Género</label>
                                <select class="form-select" id="genero" name="genero">
                                    <option value="">-</option>
                                    <option value="Masculino">Masculino</option>
                                    <option value="Femenino">Femenino</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="curso" class="form-label required">Curso</label>
                                <select class="form-select" id="curso" name="curso" required>
                                    <option value="">Seleccionar</option>
                                    <?php
                                    $sqlCursos = "SELECT id_curso, CONCAT(nivel, ' ', curso, '° ', paralelo) AS nombre FROM cursos ORDER BY nivel, curso, paralelo";
                                    $stmtCursos = $conn->query($sqlCursos);
                                    while ($curso = $stmtCursos->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="'.$curso['id_curso'].'">'.$curso['nombre'].'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <hr class="form-divider">

                        <h6 class="form-section-title">Responsable 1 <span class="text-muted fw-normal">(opcional)</span></h6>
                        <input type="hidden" id="id_responsable_1" name="id_responsable_1" value="">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label for="responsable_ci_1" class="form-label">CI</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="responsable_ci_1" name="responsable_ci_1">
                                    <button class="btn btn-outline-secondary" type="button" id="btnBuscarResponsable_1">Buscar</button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="responsable_nombre_1" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="responsable_nombre_1" name="responsable_nombre_1">
                            </div>
                            <div class="col-md-4">
                                <label for="responsable_apellido_1" class="form-label">Apellido</label>
                                <input type="text" class="form-control" id="responsable_apellido_1" name="responsable_apellido_1">
                            </div>
                            <div class="col-md-4">
                                <label for="responsable_telefono_1" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" id="responsable_telefono_1" name="responsable_telefono_1">
                            </div>
                            <div class="col-md-4">
                                <label for="tipo_responsable_1" class="form-label">Tipo</label>
                                <select class="form-select" id="tipo_responsable_1" name="tipo_responsable_1">
                                    <option value="">-</option>
                                    <option value="PADRE">Padre</option>
                                    <option value="MADRE">Madre</option>
                                    <option value="TUTOR">Tutor</option>
                                </select>
                            </div>
                        </div>

                        <hr class="form-divider">

                        <h6 class="form-section-title">Responsable 2 <span class="text-muted fw-normal">(opcional)</span></h6>
                        <input type="hidden" id="id_responsable_2" name="id_responsable_2" value="">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label for="responsable_ci_2" class="form-label">CI</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="responsable_ci_2" name="responsable_ci_2">
                                    <button class="btn btn-outline-secondary" type="button" id="btnBuscarResponsable_2">Buscar</button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="responsable_nombre_2" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="responsable_nombre_2" name="responsable_nombre_2">
                            </div>
                            <div class="col-md-4">
                                <label for="responsable_apellido_2" class="form-label">Apellido</label>
                                <input type="text" class="form-control" id="responsable_apellido_2" name="responsable_apellido_2">
                            </div>
                            <div class="col-md-4">
                                <label for="responsable_telefono_2" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" id="responsable_telefono_2" name="responsable_telefono_2">
                            </div>
                            <div class="col-md-4">
                                <label for="tipo_responsable_2" class="form-label">Tipo</label>
                                <select class="form-select" id="tipo_responsable_2" name="tipo_responsable_2">
                                    <option value="">-</option>
                                    <option value="PADRE">Padre</option>
                                    <option value="MADRE">Madre</option>
                                    <option value="TUTOR">Tutor</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formNuevoEstudiante" class="btn btn-sm btn-primary">Guardar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        function filtrarEstudiantes() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.querySelector('.table-estudiantes');
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        }
    </script>
    <?php if ($puedeModificarEstudiantes): ?>
    <script>
        function getResponsableElements(idx) {
            return {
                btnBuscar: document.getElementById(`btnBuscarResponsable_${idx}`),
                ci: document.getElementById(`responsable_ci_${idx}`),
                id: document.getElementById(`id_responsable_${idx}`),
                nombre: document.getElementById(`responsable_nombre_${idx}`),
                apellido: document.getElementById(`responsable_apellido_${idx}`),
                telefono: document.getElementById(`responsable_telefono_${idx}`)
            };
        }

        async function buscarResponsablePorCi(idx) {
            const refs = getResponsableElements(idx);
            const ci = (refs.ci.value || '').trim();
            if (ci === '') {
                return;
            }

            refs.btnBuscar.disabled = true;
            try {
                const res = await fetch(`estudiantes.php?action=buscar_responsable&ci=${encodeURIComponent(ci)}`);
                const data = await res.json();

                if (data && data.found && data.responsable) {
                    refs.id.value = data.responsable.id_responsable || '';
                    refs.nombre.value = data.responsable.nombre || '';
                    refs.apellido.value = data.responsable.apellido || '';
                    refs.telefono.value = data.responsable.telefono || '';
                } else {
                    refs.id.value = '';
                    refs.nombre.value = '';
                    refs.apellido.value = '';
                    refs.telefono.value = '';
                }
            } catch (e) {
                refs.id.value = '';
            } finally {
                refs.btnBuscar.disabled = false;
            }
        }

        [1, 2].forEach((idx) => {
            const refs = getResponsableElements(idx);
            refs.btnBuscar.addEventListener('click', () => buscarResponsablePorCi(idx));
            refs.ci.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarResponsablePorCi(idx);
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
