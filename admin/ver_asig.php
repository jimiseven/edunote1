<?php
session_start();
require_once '../config/database.php';

// Verificar acceso
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2])) {
    header('Location: ../index.php');
    exit();
}

// Obtener parámetros
$nivel = $_GET['nivel'] ?? '';
$curso = $_GET['curso'] ?? '';
$paralelo = $_GET['paralelo'] ?? '';

if (empty($nivel) || empty($curso) || empty($paralelo)) {
    header('Location: asig_prim.php');
    exit();
}

// Conectar a la base de datos
$database = new Database();
$conn = $database->connect();

// Obtener el id_curso basado en nivel, curso y paralelo
$stmt_curso = $conn->prepare("SELECT id_curso FROM cursos WHERE nivel = ? AND curso = ? AND paralelo = ?");
$stmt_curso->execute([$nivel, $curso, $paralelo]);
$id_curso = $stmt_curso->fetchColumn();

if (!$id_curso) {
    header('Location: asig_ini.php?error=curso_no_encontrado');
    exit();
}


// Obtener materias asignadas al curso
$query = "
    SELECT 
        m.id_materia,
        m.nombre_materia,
        CASE 
            WHEN m.es_extra = 1 THEN 'Extra'
            WHEN m.es_submateria = 1 THEN 'Hija'
            ELSE 'Padre'
        END AS tipo_materia,
        p.id_personal,
        CONCAT(p.nombres, ' ', p.apellidos) AS nombre_profesor,
        pmc.id_profesor_materia_curso
    FROM 
        cursos_materias cm
    JOIN 
        materias m ON cm.id_materia = m.id_materia
    LEFT JOIN 
        profesores_materias_cursos pmc ON cm.id_curso_materia = pmc.id_curso_materia
    LEFT JOIN 
        personal p ON pmc.id_personal = p.id_personal
    WHERE 
        cm.id_curso = ?
    ORDER BY 
        m.es_submateria ASC, m.nombre_materia ASC
";

$stmt = $conn->prepare($query);
$stmt->execute([$id_curso]);
$materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener profesores para el modal de asignación
$stmt_profesores = $conn->prepare("
    SELECT 
        id_personal, 
        nombres, 
        apellidos,
        carnet_identidad
    FROM 
        personal 
    WHERE 
        id_rol = 2 
    ORDER BY 
        apellidos, nombres
");
$stmt_profesores->execute();
$profesores = $stmt_profesores->fetchAll(PDO::FETCH_ASSOC);

// Procesar asignación si hay POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asignar'])) {
    $id_materia = $_POST['id_materia'];
    $id_profesor = $_POST['id_profesor'];

    // Obtener el id_curso_materia correspondiente
    $stmt_curso_materia = $conn->prepare("
        SELECT id_curso_materia 
        FROM cursos_materias 
        WHERE id_curso = ? AND id_materia = ?
    ");
    $stmt_curso_materia->execute([$id_curso, $id_materia]);
    $id_curso_materia = $stmt_curso_materia->fetchColumn();

    if (!$id_curso_materia) {
        // Si no existe la relación curso-materia, crearla
        $stmt_insert_cm = $conn->prepare("
            INSERT INTO cursos_materias (id_curso, id_materia) 
            VALUES (?, ?)
        ");
        $stmt_insert_cm->execute([$id_curso, $id_materia]);
        $id_curso_materia = $conn->lastInsertId();
    }

    // Verificar si ya existe una asignación
    $stmt_check = $conn->prepare("
        SELECT id_profesor_materia_curso 
        FROM profesores_materias_cursos 
        WHERE id_curso_materia = ?
    ");
    $stmt_check->execute([$id_curso_materia]);
    $asignacion_existente = $stmt_check->fetchColumn();

    if ($asignacion_existente) {
        // Actualizar asignación existente
        $stmt_update = $conn->prepare("
            UPDATE profesores_materias_cursos 
            SET id_personal = ?, estado = 'CARGADO' 
            WHERE id_profesor_materia_curso = ?
        ");
        $stmt_update->execute([$id_profesor, $asignacion_existente]);
    } else {
        // Crear nueva asignación
        $stmt_insert = $conn->prepare("
            INSERT INTO profesores_materias_cursos (id_curso_materia, id_personal, estado) 
            VALUES (?, ?, 'CARGADO')
        ");
        $stmt_insert->execute([$id_curso_materia, $id_profesor]);
    }

    // Redireccionar para evitar reenvío del formulario
    header("Location: ver_asig.php?nivel=$nivel&curso=$curso&paralelo=$paralelo&success=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignación de Profesores - <?= ucfirst($nivel) ?> <?= $curso ?> "<?= $paralelo ?>"</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
        }

        body {
            background: #f5f6fa;
            margin-left: var(--sidebar-width);
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #212c3a;
            padding: 20px;
            z-index: 1000;
        }

        .table th {
            background: #f0f4f8;
        }

        .tipo-padre {
            background-color: #e3ecfa !important;
        }

        .tipo-hija {
            background-color: #f1f5fa !important;
        }

        .tipo-extra {
            background-color: #e6f4ff !important;
            font-style: italic;
        }

        .sin-asignar {
            color: #dc3545;
            font-style: italic;
        }

        .modal-header {
            background: #e9f0f8;
        }

        .search-container {
            position: relative;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            z-index: 1050;
        }

        .search-item {
            padding: 8px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }

        .search-item:hover {
            background: #f5f5f5;
        }

        @media print {

            .sidebar,
            .no-print {
                display: none !important;
            }

            body {
                margin-left: 0 !important;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar text-white">
        <?php include '../includes/sidebar.php'; ?>
    </div>

    <!-- Contenido Principal -->
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Asignación de Profesores</h2>
            <a href="#" id="btnVolver" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h3 class="card-title">
                    <?= ucfirst($nivel) ?> <?= $curso ?> "<?= $paralelo ?>"
                </h3>
                <p class="text-muted">
                    Asignación de profesores a materias para el presente curso
                </p>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>¡Éxito!</strong> La asignación se ha realizado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Materia</th>
                                <th>Profesor Asignado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materias as $materia): ?>
                                <tr class="tipo-<?= strtolower($materia['tipo_materia']) ?>">
                                    <td><?= $materia['tipo_materia'] ?></td>
                                    <td><?= htmlspecialchars($materia['nombre_materia']) ?></td>
                                    <td>
                                        <?php if (!empty($materia['nombre_profesor'])): ?>
                                            <?= htmlspecialchars($materia['nombre_profesor']) ?>
                                        <?php else: ?>
                                            <span class="sin-asignar">Sin profesor asignado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <!-- Botón Asignar -->
                                            <button type="button"
                                                class="btn btn-success btn-sm d-flex align-items-center"
                                                data-bs-toggle="modal"
                                                data-bs-target="#asignarModal"
                                                data-id="<?= $materia['id_materia'] ?>"
                                                data-materia="<?= htmlspecialchars($materia['nombre_materia']) ?>"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="Asignar profesor">
                                                <i class="bi bi-person-plus me-1"></i>
                                                <span class="d-none d-md-inline">Asignar</span>
                                            </button>

                                            <!-- Botón Quitar Profesor -->
                                            <?php if (!empty($materia['id_profesor_materia_curso'])): ?>
                                            <button type="button"
                                                class="btn btn-danger btn-sm d-flex align-items-center btn-eliminar"
                                                data-id="<?= $materia['id_profesor_materia_curso'] ?>"
                                                data-materia="<?= htmlspecialchars($materia['nombre_materia']) ?>"
                                                data-profesor="<?= htmlspecialchars($materia['nombre_profesor'] ?? '') ?>"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="Quitar profesor asignado">
                                                <i class="bi bi-person-x me-1"></i>
                                                <span class="d-none d-md-inline">Quitar Prof.</span>
                                            </button>
                                            <?php endif; ?>

                                            <!-- Botón Quitar Materia -->
                                            <button type="button"
                                                class="btn btn-outline-danger btn-sm d-flex align-items-center btn-eliminar-materia"
                                                data-id="<?= $materia['id_materia'] ?>"
                                                data-materia="<?= htmlspecialchars($materia['nombre_materia']) ?>"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="Eliminar materia del curso">
                                                <i class="bi bi-journal-x me-1"></i>
                                                <span class="d-none d-md-inline">Quitar Mat.</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Asignación -->
    <div class="modal fade" id="asignarModal" tabindex="-1" aria-labelledby="asignarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="asignarModalLabel">Asignar Profesor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="asignarForm" method="post">
                        <input type="hidden" name="id_materia" id="id_materia">
                        <input type="hidden" name="id_profesor" id="id_profesor">
                        <input type="hidden" name="asignar" value="1">

                        <div class="mb-3">
                            <label class="form-label">Materia:</label>
                            <h5 id="nombre_materia"></h5>
                        </div>

                        <div class="mb-3">
                            <label for="buscar_profesor" class="form-label">Buscar Profesor:</label>
                            <div class="search-container">
                                <input type="text" class="form-control" id="buscar_profesor" placeholder="Nombre o carnet del profesor...">
                                <div class="search-results d-none" id="resultados_profesores"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Profesor Seleccionado:</label>
                            <h5 id="profesor_seleccionado" class="text-primary">Ninguno seleccionado</h5>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btn_guardar_asignacion" disabled>Guardar Asignación</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        // Inicializar tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Efectos hover para botones
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('mouseenter', () => {
                btn.style.transform = 'translateY(-2px)';
                btn.style.transition = 'all 0.2s ease';
            });
            btn.addEventListener('mouseleave', () => {
                btn.style.transform = '';
            });
        });

        // Manejar eliminación de materia del curso
        document.querySelectorAll('.btn-eliminar-materia').forEach(btn => {
            btn.addEventListener('click', function() {
                const materia = this.getAttribute('data-materia');
                const idMateria = this.getAttribute('data-id');
                
                // Mostrar modal de confirmación con estilo
                const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
                document.getElementById('confirmMessage').innerHTML = `
                    <div class="alert alert-warning">
                        <h5><i class="bi bi-exclamation-triangle-fill"></i> ¿Eliminar materia?</h5>
                        <p>¿Está seguro de eliminar la materia <strong>${materia}</strong> de este curso?</p>
                        <p class="text-danger"><small>Esta acción no se puede deshacer.</small></p>
                    </div>
                `;
                
                document.getElementById('confirmButton').onclick = () => {
                    const formData = new FormData();
                    formData.append('id_materia', idMateria);
                    formData.append('id_curso', <?= $id_curso ?>);
                    formData.append('nivel', '<?= $nivel ?>');
                    formData.append('curso', '<?= $curso ?>');
                    formData.append('paralelo', '<?= $paralelo ?>');

                    // Mostrar spinner durante la carga
                    const spinner = document.createElement('span');
                    spinner.className = 'spinner-border spinner-border-sm me-2';
                    document.getElementById('confirmButton').prepend(spinner);
                    document.getElementById('confirmButton').disabled = true;

                    fetch('eliminar_materia_curso.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Mostrar notificación de éxito
                            const toast = new bootstrap.Toast(document.getElementById('successToast'));
                            document.getElementById('toastMessage').textContent = `Materia ${materia} eliminada correctamente`;
                            toast.show();
                            
                            // Redireccionar después de 1.5 segundos
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 1500);
                        } else {
                            modal.hide();
                            alert('Error al eliminar la materia: ' + (data.error || 'Error desconocido'));
                        }
                    })
                    .catch(error => {
                        modal.hide();
                        console.error('Error:', error);
                        alert('Error al eliminar la materia');
                    });
                };

                modal.show();
            });
        });
    </script>

    <!-- Modal de confirmación -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">Confirmar acción</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmMessage">
                    <!-- Mensaje dinámico -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmButton">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast de éxito -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="successToast" class="toast align-items-center text-white bg-success" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <script>
        // Manejar botón volver basado en el nivel actual
        document.getElementById('btnVolver').addEventListener('click', function(e) {
            e.preventDefault();
            
            // Determinar página de retorno según el nivel
            let paginaRetorno;
            switch('<?= $nivel ?>') {
                case 'inicial':
                    paginaRetorno = 'asig_ini.php';
                    break;
                case 'primaria':
                    paginaRetorno = 'asig_pri.php';
                    break;
                case 'secundaria':
                default:
                    paginaRetorno = 'asig_sec.php';
            }
            
            // Redirigir a la página correspondiente
            window.location.href = paginaRetorno;
        });
        // Manejar eliminación de materia del curso
        document.querySelectorAll('.btn-eliminar-materia').forEach(btn => {
            btn.addEventListener('click', function() {
                const materia = this.getAttribute('data-materia');
                const idMateria = this.getAttribute('data-id');
                
                if (confirm(`¿Está seguro de eliminar la materia ${materia} de este curso? Esta acción no se puede deshacer.`)) {
                    const formData = new FormData();
                    formData.append('id_materia', idMateria);
                    formData.append('id_curso', <?= $id_curso ?>);
                    formData.append('nivel', '<?= $nivel ?>');
                    formData.append('curso', '<?= $curso ?>');
                    formData.append('paralelo', '<?= $paralelo ?>');

                    fetch('eliminar_materia_curso.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = data.redirect;
                        } else {
                            alert('Error al eliminar la materia: ' + (data.error || 'Error desconocido'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al eliminar la materia');
                    });
                }
            });
        });
    </script>
    <script>
        // Lista de profesores para búsqueda
        const profesores = <?= json_encode($profesores) ?>;

        // Configuración del modal
        const asignarModal = document.getElementById('asignarModal');
        asignarModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const idMateria = button.getAttribute('data-id');
            const nombreMateria = button.getAttribute('data-materia');

            document.getElementById('id_materia').value = idMateria;
            document.getElementById('nombre_materia').textContent = nombreMateria;
            document.getElementById('profesor_seleccionado').textContent = 'Ninguno seleccionado';
            document.getElementById('id_profesor').value = '';
            document.getElementById('buscar_profesor').value = '';
            document.getElementById('btn_guardar_asignacion').disabled = true;
        });

        // Búsqueda de profesores
        const buscador = document.getElementById('buscar_profesor');
        const resultados = document.getElementById('resultados_profesores');

        buscador.addEventListener('input', () => {
            const texto = buscador.value.toLowerCase().trim();
            if (texto.length < 2) {
                resultados.innerHTML = '';
                resultados.classList.add('d-none');
                return;
            }

            const filtrados = profesores.filter(p => {
                const nombreCompleto = `${p.nombres} ${p.apellidos}`.toLowerCase();
                const carnet = p.carnet_identidad ? p.carnet_identidad.toLowerCase() : '';
                return nombreCompleto.includes(texto) || carnet.includes(texto);
            });

            resultados.innerHTML = '';

            if (filtrados.length === 0) {
                resultados.innerHTML = '<div class="search-item">No se encontraron profesores</div>';
            } else {
                filtrados.forEach(p => {
                    const item = document.createElement('div');
                    item.className = 'search-item';
                    item.innerHTML = `
                        <strong>${p.apellidos}, ${p.nombres}</strong>
                        ${p.carnet_identidad ? `<small class="text-muted"> (${p.carnet_identidad})</small>` : ''}
                    `;
                    item.dataset.id = p.id_personal;
                    item.dataset.nombre = `${p.apellidos}, ${p.nombres}`;

                    item.addEventListener('click', () => {
                        document.getElementById('id_profesor').value = p.id_personal;
                        document.getElementById('profesor_seleccionado').textContent = item.dataset.nombre;
                        document.getElementById('btn_guardar_asignacion').disabled = false;
                        buscador.value = item.dataset.nombre;
                        resultados.classList.add('d-none');
                    });

                    resultados.appendChild(item);
                });
            }

            resultados.classList.remove('d-none');
        });

        // Cerrar resultados al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!buscador.contains(e.target) && !resultados.contains(e.target)) {
                resultados.classList.add('d-none');
            }
        });

        // Enviar formulario al guardar
        document.getElementById('btn_guardar_asignacion').addEventListener('click', () => {
            document.getElementById('asignarForm').submit();
        });

        // Manejar eliminación de asignaciones
        document.querySelectorAll('.btn-eliminar').forEach(btn => {
            btn.addEventListener('click', function() {
                const materia = this.getAttribute('data-materia');
                const profesor = this.getAttribute('data-profesor');
                const idAsignacion = this.getAttribute('data-id');
                
                if (confirm(`¿Está seguro de eliminar la asignación de ${profesor} a ${materia}?`)) {
                    fetch(`eliminar_asignacion.php?id=${idAsignacion}`, {
                        method: 'DELETE'
                    })
                    .then(response => {
                        if (response.ok) {
                            location.reload();
                        } else {
                            alert('Error al eliminar la asignación');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al eliminar la asignación');
                    });
                }
            });
        });
    </script>
</body>

</html>