<?php
session_start();
require_once '../config/database.php';

// Verificar autenticación admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$id_curso = $_GET['id'] ?? header('Location: dash_iniciales.php');
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'anual'; // Vista anual por defecto
$trimestre = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : 1; // Trimestre 1 por defecto

$conn = (new Database())->connect();

// Obtener información del curso
$stmt_curso = $conn->prepare("
    SELECT nivel, curso, paralelo 
    FROM cursos 
    WHERE id_curso = ? AND nivel = 'Inicial'
");
$stmt_curso->execute([$id_curso]);
$curso = $stmt_curso->fetch(PDO::FETCH_ASSOC);

if (!$curso) header('Location: dash_iniciales.php');

// Obtener estudiantes
$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, nombres, apellido_paterno, apellido_materno 
    FROM estudiantes 
    WHERE id_curso = ?
    ORDER BY apellido_paterno, apellido_materno, nombres
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// Obtener materias
$stmt_materias = $conn->prepare("
    SELECT m.id_materia, m.nombre_materia 
    FROM cursos_materias cm
    JOIN materias m ON cm.id_materia = m.id_materia
    WHERE cm.id_curso = ?
");
$stmt_materias->execute([$id_curso]);
$materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// Obtener comentarios
$comentarios = [];
foreach ($estudiantes as $est) {
    foreach ($materias as $mat) {
        if ($vista == 'anual') {
            for ($bim=1; $bim<=3; $bim++) {
                $stmt = $conn->prepare("
                    SELECT comentario 
                    FROM calificaciones 
                    WHERE id_estudiante = ? 
                    AND id_materia = ? 
                    AND bimestre = ?
                ");
                $stmt->execute([$est['id_estudiante'], $mat['id_materia'], $bim]);
                $comentarios[$est['id_estudiante']][$mat['id_materia']][$bim] = $stmt->fetchColumn() ?: '';
            }
        } else {
            // Solo un trimestre para vista trimestral
            $stmt = $conn->prepare("
                SELECT comentario 
                FROM calificaciones 
                WHERE id_estudiante = ? 
                AND id_materia = ? 
                AND bimestre = ?
            ");
            $stmt->execute([$est['id_estudiante'], $mat['id_materia'], $trimestre]);
            $comentarios[$est['id_estudiante']][$mat['id_materia']][$trimestre] = $stmt->fetchColumn() ?: '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centralizador Inicial <?php echo htmlspecialchars("{$curso['curso']} \"{$curso['paralelo']}\""); ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .content-wrapper {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        .table-responsive {
            margin-top: 20px;
            border-radius: 8px;
            overflow: hidden;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .table th {
            text-align: center;
            background-color: #e3f2fd;
            color: #007bff;
        }
        .table td {
            text-align: center;
            font-size: 0.9rem;
            padding: 8px;
        }
        .table td.comentario-cell {
            text-align: left;
            word-wrap: break-word;
            white-space: pre-wrap;
            max-width: 275px;
        }
        .table thead {
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .action-buttons .form-select {
            min-width: 140px;
        }
        .btn-back {
            background-color: #6c757d;
            color: white;
            border-radius: 5px;
        }
        .btn-back:hover {
            background-color: #5a6269;
        }
        .btn-print {
            background-color: #007bff;
            color: white;
            border-radius: 5px;
        }
        .btn-print:hover {
            background-color: #0056b3;
        }
        @media (max-width: 992px) {
            .action-buttons {
                flex-direction: column;
                align-items: flex-end;
                gap: 7px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-wrapper">
                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
                        <h2>Centralizador: Inicial <?php echo htmlspecialchars("{$curso['curso']} \"{$curso['paralelo']}\""); ?></h2>
                        <div class="action-buttons">
                            <form method="GET" action="" class="d-flex gap-2 align-items-center mb-0">
                                <input type="hidden" name="id" value="<?php echo $id_curso; ?>">
                                <select name="vista" class="form-select" onchange="this.form.submit()">
                                    <option value="anual" <?php echo ($vista == 'anual') ? 'selected' : ''; ?>>Vista Anual</option>
                                    <option value="trimestral" <?php echo ($vista == 'trimestral') ? 'selected' : ''; ?>>Vista Trimestral</option>
                                </select>
                                <?php if ($vista == 'trimestral'): ?>
                                    <select name="trimestre" class="form-select" onchange="this.form.submit()">
                                        <option value="1" <?php echo ($trimestre == 1) ? 'selected' : ''; ?>>Trimestre 1</option>
                                        <option value="2" <?php echo ($trimestre == 2) ? 'selected' : ''; ?>>Trimestre 2</option>
                                        <option value="3" <?php echo ($trimestre == 3) ? 'selected' : ''; ?>>Trimestre 3</option>
                                    </select>
                                <?php endif; ?>
                            </form>
                            <a href="dash_iniciales.php" class="btn btn-back">Volver</a>
                            <button onclick="window.print();" class="btn btn-print">Imprimir</button>
                        </div>
                    </div>

                    <!-- Tabla de Centralizador -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Estudiante</th>
                                    <?php foreach ($materias as $materia): ?>
                                        <?php if ($vista == 'anual'): ?>
                                            <th colspan="3"><?php echo htmlspecialchars($materia['nombre_materia']); ?></th>
                                        <?php else: ?>
                                            <th><?php echo htmlspecialchars($materia['nombre_materia']); ?></th>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                                <?php if ($vista == 'anual'): ?>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <?php foreach ($materias as $materia): ?>
                                            <th>Trim 1</th>
                                            <th>Trim 2</th>
                                            <th>Trim 3</th>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endif; ?>
                            </thead>
                            <tbody>
                                <?php $contador = 1; ?>
                                <?php foreach ($estudiantes as $est): ?>
                                    <tr>
                                        <td><?php echo $contador++; ?></td>
                                        <td><?php echo htmlspecialchars("{$est['apellido_paterno']} {$est['apellido_materno']}, {$est['nombres']}"); ?></td>
                                        <?php foreach ($materias as $mat): ?>
                                            <?php if ($vista == 'anual'): ?>
                                                <?php for ($bim=1; $bim<=3; $bim++): ?>
                                                    <td class="comentario-cell">
                                                        <?php echo htmlspecialchars($comentarios[$est['id_estudiante']][$mat['id_materia']][$bim]); ?>
                                                    </td>
                                                <?php endfor; ?>
                                            <?php else: ?>
                                                <td class="comentario-cell">
                                                    <?php echo htmlspecialchars($comentarios[$est['id_estudiante']][$mat['id_materia']][$trimestre]); ?>
                                                </td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
