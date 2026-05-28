<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

function splitNombreApellido(string $nombreCompleto): array
{
    $nombreCompleto = trim(preg_replace('/\s+/', ' ', $nombreCompleto));
    if ($nombreCompleto === '') {
        return ['', ''];
    }

    $partes = explode(' ', $nombreCompleto);
    if (count($partes) === 1) {
        return [$partes[0], '-'];
    }

    $apellido = array_pop($partes);
    $nombre = trim(implode(' ', $partes));
    return [$nombre, $apellido];
}

function getOrCreateResponsable(PDO $conn, string $nombreCompleto, string $telefono): int
{
    $nombreCompleto = trim(preg_replace('/\s+/', ' ', $nombreCompleto));
    $telefono = trim($telefono);

    $stmt = $conn->prepare("SELECT id_responsable FROM responsables WHERE TRIM(CONCAT(nombre, ' ', apellido)) = :nombre_completo LIMIT 1");
    $stmt->bindValue(':nombre_completo', $nombreCompleto);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $idResponsable = (int)$row['id_responsable'];
        if ($telefono !== '') {
            $stmtUpd = $conn->prepare('UPDATE responsables SET telefono = :telefono WHERE id_responsable = :id_responsable');
            $stmtUpd->bindValue(':telefono', $telefono);
            $stmtUpd->bindValue(':id_responsable', $idResponsable, PDO::PARAM_INT);
            $stmtUpd->execute();
        }
        return $idResponsable;
    }

    [$nombre, $apellido] = splitNombreApellido($nombreCompleto);
    $ciTemporal = 'AUTO' . strtoupper(bin2hex(random_bytes(8)));

    $stmtIns = $conn->prepare('INSERT INTO responsables (nombre, apellido, carnet_identidad, telefono) VALUES (:nombre, :apellido, :ci, :telefono)');
    $stmtIns->bindValue(':nombre', $nombre);
    $stmtIns->bindValue(':apellido', $apellido);
    $stmtIns->bindValue(':ci', $ciTemporal);
    $stmtIns->bindValue(':telefono', $telefono === '' ? null : $telefono, $telefono === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmtIns->execute();

    return (int)$conn->lastInsertId();
}

$tablaEstRespDisponible = false;
$avisoMigracion = '';
try {
    $stmtTabla = $conn->query("SHOW TABLES LIKE 'estudiantes_responsables'");
    $tablaEstRespDisponible = (bool)$stmtTabla->fetchColumn();
} catch (Throwable $e) {
    $tablaEstRespDisponible = false;
}

if (!$tablaEstRespDisponible) {
    $avisoMigracion = 'La tabla estudiantes_responsables no existe en esta base de datos. Ejecute la migracion bds/27 may/migracion_dos_responsables.txt para habilitar 2 responsables.';
}

$nivelesValidos = [];
$stmtNiveles = $conn->query("SELECT DISTINCT nivel FROM cursos WHERE nivel IS NOT NULL AND nivel <> '' ORDER BY FIELD(nivel, 'Inicial', 'Primaria', 'Secundaria'), nivel");
$nivelesValidos = $stmtNiveles->fetchAll(PDO::FETCH_COLUMN);

$nivelSeleccionado = isset($_GET['nivel']) ? trim($_GET['nivel']) : '';
if ($nivelSeleccionado === '' && !empty($nivelesValidos)) {
    $nivelSeleccionado = $nivelesValidos[0];
}

if ($nivelSeleccionado !== '' && !in_array($nivelSeleccionado, $nivelesValidos, true)) {
    $nivelSeleccionado = $nivelesValidos[0] ?? '';
}

$cursosNivel = [];
if ($nivelSeleccionado !== '') {
    $stmtCursosNivel = $conn->prepare("SELECT id_curso, CONCAT(nivel, ' ', curso, '° ', paralelo) AS nombre FROM cursos WHERE nivel = :nivel ORDER BY curso ASC, paralelo ASC");
    $stmtCursosNivel->bindValue(':nivel', $nivelSeleccionado);
    $stmtCursosNivel->execute();
    $cursosNivel = $stmtCursosNivel->fetchAll(PDO::FETCH_ASSOC);
}

$cursoSeleccionado = isset($_GET['curso']) ? (int)$_GET['curso'] : 0;
$idsCursosNivel = array_map(static function ($item) {
    return (int)$item['id_curso'];
}, $cursosNivel);

if ($cursoSeleccionado > 0 && !in_array($cursoSeleccionado, $idsCursosNivel, true)) {
    $cursoSeleccionado = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'guardar_responsables') {
    $nivelForm = trim($_POST['nivel'] ?? '');
    $cursoForm = (int)($_POST['curso'] ?? 0);
    $idsEstudiantes = $_POST['id_estudiante'] ?? [];
    $responsables1 = $_POST['responsable_1'] ?? [];
    $responsables2 = $_POST['responsable_2'] ?? [];
    $telefonos1 = $_POST['telefono_1'] ?? [];
    $telefonos2 = $_POST['telefono_2'] ?? [];

    try {
        if (!$tablaEstRespDisponible) {
            throw new RuntimeException('No existe la tabla estudiantes_responsables. Ejecute la migracion antes de guardar responsables.');
        }

        $conn->beginTransaction();

        foreach ($idsEstudiantes as $idx => $idEstudianteRaw) {
            $idEstudiante = (int)$idEstudianteRaw;
            if ($idEstudiante <= 0) {
                continue;
            }

            $resp1 = trim($responsables1[$idx] ?? '');
            $resp2 = trim($responsables2[$idx] ?? '');
            $tel1 = trim($telefonos1[$idx] ?? '');
            $tel2 = trim($telefonos2[$idx] ?? '');

            $relaciones = [];

            if ($resp1 !== '') {
                $idResp1 = getOrCreateResponsable($conn, $resp1, $tel1);
                $relaciones[] = ['id_responsable' => $idResp1, 'es_principal' => 1];
            }

            if ($resp2 !== '') {
                $idResp2 = getOrCreateResponsable($conn, $resp2, $tel2);
                if (empty($relaciones) || $relaciones[0]['id_responsable'] !== $idResp2) {
                    $relaciones[] = ['id_responsable' => $idResp2, 'es_principal' => 0];
                }
            }

            $stmtDel = $conn->prepare('DELETE FROM estudiantes_responsables WHERE id_estudiante = ?');
            $stmtDel->execute([$idEstudiante]);

            if (!empty($relaciones)) {
                $stmtInsRel = $conn->prepare('INSERT INTO estudiantes_responsables (id_estudiante, id_responsable, tipo_responsable, es_principal) VALUES (?, ?, NULL, ?)');
                foreach ($relaciones as $rel) {
                    $stmtInsRel->execute([$idEstudiante, (int)$rel['id_responsable'], (int)$rel['es_principal']]);
                }
            }

            $legacy = !empty($relaciones) ? (int)$relaciones[0]['id_responsable'] : null;
            $stmtLegacy = $conn->prepare('UPDATE estudiantes SET id_responsable = :id_responsable WHERE id_estudiante = :id_estudiante');
            $stmtLegacy->bindValue(':id_responsable', $legacy, $legacy === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmtLegacy->bindValue(':id_estudiante', $idEstudiante, PDO::PARAM_INT);
            $stmtLegacy->execute();
        }

        $conn->commit();
        $_SESSION['success'] = 'Responsables guardados correctamente.';
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = 'Error al guardar responsables: ' . $e->getMessage();
    }

    $redirCurso = $cursoForm > 0 ? '&curso=' . $cursoForm : '&curso=0';
    header('Location: responsables_por_nivel.php?nivel=' . urlencode($nivelForm) . $redirCurso);
    exit();
}

$filas = [];
if ($nivelSeleccionado !== '') {
    $whereCurso = '';
    if ($cursoSeleccionado > 0) {
        $whereCurso = ' AND c.id_curso = :id_curso ';
    }

    if ($tablaEstRespDisponible) {
        $sql = "
            SELECT
                c.id_curso,
                c.nivel,
                c.curso,
                c.paralelo,
                e.id_estudiante,
                e.apellido_paterno,
                e.apellido_materno,
                e.nombres,
                (
                    SELECT TRIM(CONCAT(r1.nombre, ' ', r1.apellido))
                    FROM estudiantes_responsables er1
                    INNER JOIN responsables r1 ON r1.id_responsable = er1.id_responsable
                    WHERE er1.id_estudiante = e.id_estudiante
                    ORDER BY er1.es_principal DESC, er1.id_estudiante_responsable ASC
                    LIMIT 1
                ) AS responsable_1,
                (
                    SELECT r1.telefono
                    FROM estudiantes_responsables er1
                    INNER JOIN responsables r1 ON r1.id_responsable = er1.id_responsable
                    WHERE er1.id_estudiante = e.id_estudiante
                    ORDER BY er1.es_principal DESC, er1.id_estudiante_responsable ASC
                    LIMIT 1
                ) AS telefono_1,
                (
                    SELECT TRIM(CONCAT(r2.nombre, ' ', r2.apellido))
                    FROM estudiantes_responsables er2
                    INNER JOIN responsables r2 ON r2.id_responsable = er2.id_responsable
                    WHERE er2.id_estudiante = e.id_estudiante
                    ORDER BY er2.es_principal DESC, er2.id_estudiante_responsable ASC
                    LIMIT 1 OFFSET 1
                ) AS responsable_2,
                (
                    SELECT r2.telefono
                    FROM estudiantes_responsables er2
                    INNER JOIN responsables r2 ON r2.id_responsable = er2.id_responsable
                    WHERE er2.id_estudiante = e.id_estudiante
                    ORDER BY er2.es_principal DESC, er2.id_estudiante_responsable ASC
                    LIMIT 1 OFFSET 1
                ) AS telefono_2
            FROM cursos c
            LEFT JOIN estudiantes e ON e.id_curso = c.id_curso
            WHERE c.nivel = :nivel
            {$whereCurso}
            ORDER BY c.curso ASC, c.paralelo ASC, e.apellido_paterno ASC, e.apellido_materno ASC, e.nombres ASC
        ";
    } else {
        $sql = "
            SELECT
                c.id_curso,
                c.nivel,
                c.curso,
                c.paralelo,
                e.id_estudiante,
                e.apellido_paterno,
                e.apellido_materno,
                e.nombres,
                TRIM(CONCAT(r.nombre, ' ', r.apellido)) AS responsable_1,
                r.telefono AS telefono_1,
                NULL AS responsable_2,
                NULL AS telefono_2
            FROM cursos c
            LEFT JOIN estudiantes e ON e.id_curso = c.id_curso
            LEFT JOIN responsables r ON r.id_responsable = e.id_responsable
            WHERE c.nivel = :nivel
            {$whereCurso}
            ORDER BY c.curso ASC, c.paralelo ASC, e.apellido_paterno ASC, e.apellido_materno ASC, e.nombres ASC
        ";
    }

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nivel', $nivelSeleccionado);
        if ($cursoSeleccionado > 0) {
            $stmt->bindValue(':id_curso', $cursoSeleccionado, PDO::PARAM_INT);
        }
        $stmt->execute();
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $_SESSION['error'] = 'No se pudo cargar la lista de responsables: ' . $e->getMessage();
        $filas = [];
    }
}

function nombreEstudiante(array $row): string
{
    return trim(($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ' ' . ($row['nombres'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Responsables por nivel</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; margin: 0; padding: 0; }
        .main-title { margin: 0; font-weight: bold; color: #11305e; }
        .tabla-box { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,.05); padding: 20px; }
        .table thead th { background: #11305e; color: #fff; position: sticky; top: 0; }
        .curso-header { background: #eaf2ff; font-weight: 600; color: #0a3a7a; }
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
                <?php if ($avisoMigracion !== ''): ?>
                    <div class="alert alert-warning mt-3" role="alert">
                        <?php echo htmlspecialchars($avisoMigracion); ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
                    <h1 class="main-title">Responsables por nivel</h1>
                </div>

                <div class="tabla-box mb-3">
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label for="nivel" class="form-label">Nivel</label>
                            <select id="nivel" name="nivel" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($nivelesValidos as $nivel): ?>
                                    <option value="<?php echo htmlspecialchars($nivel); ?>" <?php echo $nivel === $nivelSeleccionado ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($nivel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="curso" class="form-label">Curso</label>
                            <select id="curso" name="curso" class="form-select" onchange="this.form.submit()">
                                <option value="0">Todos los cursos del nivel</option>
                                <?php foreach ($cursosNivel as $curso): ?>
                                    <option value="<?php echo (int)$curso['id_curso']; ?>" <?php echo $cursoSeleccionado === (int)$curso['id_curso'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($curso['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <form method="post" id="formGuardarResponsables">
                    <input type="hidden" name="action" value="guardar_responsables">
                    <input type="hidden" name="nivel" value="<?php echo htmlspecialchars($nivelSeleccionado); ?>">
                    <input type="hidden" name="curso" value="<?php echo (int)$cursoSeleccionado; ?>">

                <div class="tabla-box">
                    <div class="d-flex justify-content-end mb-2">
                        <button type="submit" class="btn btn-primary btn-sm">Guardar toda la informacion</button>
                    </div>
                    <div class="table-responsive" style="max-height:70vh;">
                        <table class="table table-hover align-middle w-100">
                            <thead>
                                <tr>
                                    <th>Nro</th>
                                    <th>Estudiante</th>
                                    <th>Responsable 1 <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 ms-1" data-columna="responsable_1" data-bs-toggle="modal" data-bs-target="#modalPegarColumna">Pegar</button></th>
                                    <th>Responsable 2 <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 ms-1" data-columna="responsable_2" data-bs-toggle="modal" data-bs-target="#modalPegarColumna">Pegar</button></th>
                                    <th>Telefono 1 <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 ms-1" data-columna="telefono_1" data-bs-toggle="modal" data-bs-target="#modalPegarColumna">Pegar</button></th>
                                    <th>Telefono 2 <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 ms-1" data-columna="telefono_2" data-bs-toggle="modal" data-bs-target="#modalPegarColumna">Pegar</button></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $cursoActual = null;
                                $hayRegistros = false;
                                $contador = 0;
                                foreach ($filas as $row):
                                    if ($cursoActual !== $row['id_curso']) {
                                        $cursoActual = $row['id_curso'];
                                        echo '<tr class="curso-header"><td colspan="6">Curso: ' . htmlspecialchars($row['nivel'] . ' ' . $row['curso'] . '° ' . $row['paralelo']) . '</td></tr>';
                                    }

                                    if (empty($row['id_estudiante'])) {
                                        echo '<tr><td colspan="6" class="text-muted">Sin estudiantes en este curso</td></tr>';
                                        continue;
                                    }

                                    $hayRegistros = true;
                                    $contador++;
                                ?>
                                    <tr>
                                        <td><?php echo $contador; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars(nombreEstudiante($row)); ?>
                                            <input type="hidden" name="id_estudiante[]" value="<?php echo (int)$row['id_estudiante']; ?>">
                                        </td>
                                        <td><input type="text" class="form-control form-control-sm" name="responsable_1[]" value="<?php echo htmlspecialchars($row['responsable_1'] ?? ''); ?>"></td>
                                        <td><input type="text" class="form-control form-control-sm" name="responsable_2[]" value="<?php echo htmlspecialchars($row['responsable_2'] ?? ''); ?>"></td>
                                        <td><input type="text" class="form-control form-control-sm" name="telefono_1[]" value="<?php echo htmlspecialchars($row['telefono_1'] ?? ''); ?>"></td>
                                        <td><input type="text" class="form-control form-control-sm" name="telefono_2[]" value="<?php echo htmlspecialchars($row['telefono_2'] ?? ''); ?>"></td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$hayRegistros && !empty($filas)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No hay estudiantes registrados en este nivel.</td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (empty($filas)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No hay datos para mostrar.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                </form>
            </main>
        </div>
    </div>

    <div class="modal fade" id="modalPegarColumna" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloModalPegar">Pegar columna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Pegue una columna desde Excel (una fila por linea).</p>
                    <textarea id="textoColumna" class="form-control" rows="10" placeholder="Dato 1&#10;Dato 2&#10;Dato 3"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnAplicarColumna">Aplicar columna</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            let columnaActual = '';
            const modal = document.getElementById('modalPegarColumna');
            const titulo = document.getElementById('tituloModalPegar');
            const txt = document.getElementById('textoColumna');
            const btnAplicar = document.getElementById('btnAplicarColumna');

            document.querySelectorAll('[data-columna]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    columnaActual = btn.getAttribute('data-columna') || '';
                    titulo.textContent = 'Pegar columna: ' + columnaActual.replace('_', ' ').replace('_', ' ');
                    txt.value = '';
                });
            });

            btnAplicar.addEventListener('click', () => {
                if (!columnaActual) {
                    return;
                }

                const lineas = txt.value.split(/\r?\n/);
                const inputs = document.querySelectorAll(`input[name="${columnaActual}[]"]`);

                inputs.forEach((input, idx) => {
                    input.value = (lineas[idx] || '').trim();
                });

                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            });
        })();
    </script>
</body>
</html>
