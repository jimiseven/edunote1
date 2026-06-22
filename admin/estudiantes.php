<?php
session_start();
require_once '../config/database.php';

// Verificar solo para administrador
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 4], true)) {
    header('Location: ../index.php');
    exit();
}

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
        body { background: #f8f9fa; margin: 0; padding: 0; }
        .main-title { margin: 0; font-weight: bold; color: #11305e; }
        .btn-nuevo { background-color: #28a745; color: white; border-radius: 5px; }
        .btn-nuevo:hover { background-color: #218838; color: white; }
        .tabla-box { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 20px; }
        .table-estudiantes th { background-color: #11305e; color: white; font-weight: 500; position: sticky; top: 0; }
        .table-estudiantes tr:nth-child(even) { background-color: #f4f8fb; }
        .table-estudiantes tr:hover { background-color: #e9f5ff; }
        .acciones-cell { display: flex; gap: 5px; }
        .btn-accion { padding: 5px 10px; border-radius: 4px; font-size: 0.85rem; display: flex; align-items: center; gap: 3px; }
        .btn-editar { background-color: #17a2b8; color: white; }
        .btn-editar:hover { background-color: #138496; color: white; }
        .btn-eliminar { background-color: #dc3545; color: white; }
        .btn-eliminar:hover { background-color: #c82333; color: white; }
        .modal-lg { max-width: 700px; }
        .modal-header { background: #11305e; color: white; }
        .modal-title { font-size: 1.1rem; }
        .form-label { font-size: 0.95rem; }
        .form-control, .form-select { font-size: 0.96rem; }
        @media (max-width: 991px) {
            .tabla-box, .table-responsive { max-height: 55vh; }
            .header-section { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid g-0">
        <div class="row g-0">
            <?php include '../includes/sidebar.php'; ?>
            <main class="w-100 px-md-4">
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

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="main-title">Listado de Estudiantes</h1>
                    <div class="d-flex gap-3 align-items-center">
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
                        <script>
                        function filtrarEstudiantes() {
                            const input = document.getElementById('searchInput');
                            const filter = input.value.toLowerCase();
                            const table = document.querySelector('.table-estudiantes');
                            const rows = table.querySelectorAll('tbody tr');
                            
                            rows.forEach(row => {
                                const text = row.textContent.toLowerCase();
                                row.style.display = text.includes(filter) ? '' : 'none';
                            });
                        }
                        </script>
                        <button type="button" class="btn-nuevo" data-bs-toggle="modal" data-bs-target="#modalNuevoEstudiante">
                            <i class="bi bi-plus-lg"></i> Nuevo Estudiante
                        </button>
                    </div>
                </div>

                <div class="tabla-box">
                    <div class="table-responsive" style="max-height:70vh;">
                        <table class="table table-hover table-estudiantes align-middle w-100">
                            <thead>
                                <tr>
                                    <th>Ap. Paterno</th>
                                    <th>Ap. Materno</th>
                                    <th>Nombres</th>
                                    <th>CI</th>
                                    <th>Género</th>
                                    <th>RUDE</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes as $estudiante): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($estudiante['apellido_paterno']); ?></td>
                                    <td><?php echo htmlspecialchars($estudiante['apellido_materno']); ?></td>
                                    <td><?php echo htmlspecialchars($estudiante['nombres']); ?></td>
                                    <td><?php echo htmlspecialchars($estudiante['ci']); ?></td>
                                    <td><?php echo htmlspecialchars($estudiante['genero']); ?></td>
                                    <td><?php echo htmlspecialchars($estudiante['rude']); ?></td>
                                    <td>
                                        <div class="acciones-cell">
                                            <a href="editar_estudiante.php?id=<?php echo $estudiante['id_estudiante']; ?>"
                                               class="btn btn-accion btn-editar" title="Editar">
                                               <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="eliminar_estudiante.php?id=<?php echo $estudiante['id_estudiante']; ?>"
                                               class="btn btn-accion btn-eliminar"
                                               onclick="return confirm('¿Está seguro de eliminar este estudiante?')" title="Eliminar">
                                               <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

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
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label for="nombres" class="form-label">Nombres*</label>
                                <input type="text" class="form-control" id="nombres" name="nombres" required>
                            </div>
                            <div class="col-md-4">
                                <label for="apellido_paterno" class="form-label">Ap. Paterno*</label>
                                <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" required>
                            </div>
                            <div class="col-md-4">
                                <label for="apellido_materno" class="form-label">Ap. Materno</label>
                                <input type="text" class="form-control" id="apellido_materno" name="apellido_materno">
                            </div>
                            <div class="col-md-3">
                                <label for="rude" class="form-label">RUDE*</label>
                                <input type="text" class="form-control" id="rude" name="rude">
                            </div>
                            <div class="col-md-3">
                                <label for="ci" class="form-label">CI*</label>
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
                                <label for="curso" class="form-label">Curso*</label>
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

                            <div class="col-12 mt-2">
                                <hr class="my-2">
                            </div>

                            <div class="col-12">
                                <h6 class="mb-1">Responsable 1 (opcional)</h6>
                                <small class="text-muted">Puede guardar 0, 1 o 2 responsables.</small>
                            </div>

                            <input type="hidden" id="id_responsable_1" name="id_responsable_1" value="">

                            <div class="col-md-4">
                                <label for="responsable_ci_1" class="form-label">CI Responsable 1</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="responsable_ci_1" name="responsable_ci_1">
                                    <button class="btn btn-outline-secondary" type="button" id="btnBuscarResponsable_1">Buscar</button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="responsable_nombre_1" class="form-label">Nombre Responsable 1</label>
                                <input type="text" class="form-control" id="responsable_nombre_1" name="responsable_nombre_1">
                            </div>
                            <div class="col-md-4">
                                <label for="responsable_apellido_1" class="form-label">Apellido Responsable 1</label>
                                <input type="text" class="form-control" id="responsable_apellido_1" name="responsable_apellido_1">
                            </div>
                            <div class="col-md-4">
                                <label for="responsable_telefono_1" class="form-label">Teléfono Responsable 1</label>
                                <input type="text" class="form-control" id="responsable_telefono_1" name="responsable_telefono_1">
                            </div>
                            <div class="col-md-4">
                                <label for="tipo_responsable_1" class="form-label">Tipo Responsable 1</label>
                                <select class="form-select" id="tipo_responsable_1" name="tipo_responsable_1">
                                    <option value="">-</option>
                                    <option value="PADRE">Padre</option>
                                    <option value="MADRE">Madre</option>
                                    <option value="TUTOR">Tutor</option>
                                </select>
                            </div>

                            <div class="col-12 mt-2">
                                <hr class="my-2">
                                <h6 class="mb-1">Responsable 2 (opcional)</h6>
                            </div>

                            <input type="hidden" id="id_responsable_2" name="id_responsable_2" value="">

                            <div class="col-md-4">
                                <label for="responsable_ci_2" class="form-label">CI Responsable 2</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="responsable_ci_2" name="responsable_ci_2">
                                    <button class="btn btn-outline-secondary" type="button" id="btnBuscarResponsable_2">Buscar</button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="responsable_nombre_2" class="form-label">Nombre Responsable 2</label>
                                <input type="text" class="form-control" id="responsable_nombre_2" name="responsable_nombre_2">
                            </div>
                            <div class="col-md-4">
                                <label for="responsable_apellido_2" class="form-label">Apellido Responsable 2</label>
                                <input type="text" class="form-control" id="responsable_apellido_2" name="responsable_apellido_2">
                            </div>
                            <div class="col-md-4">
                                <label for="responsable_telefono_2" class="form-label">Teléfono Responsable 2</label>
                                <input type="text" class="form-control" id="responsable_telefono_2" name="responsable_telefono_2">
                            </div>
                            <div class="col-md-4">
                                <label for="tipo_responsable_2" class="form-label">Tipo Responsable 2</label>
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

    <script src="../js/bootstrap.bundle.min.js"></script>
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
</body>
</html>
