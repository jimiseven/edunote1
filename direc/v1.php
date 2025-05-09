<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 3) {
    header('Location: ../index.php');
    exit();
}

$id_curso = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$trimestre = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : 1;
if ($trimestre < 1 || $trimestre > 3) $trimestre = 1;

if ($id_curso <= 0) {
    header('Location: priv.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

// Materias
$stmt_materias = $conn->prepare("
    SELECT 
        m.id_materia, 
        m.nombre_materia, 
        m.es_extra,
        m.materia_padre_id
    FROM cursos_materias cm
    JOIN materias m ON cm.id_materia = m.id_materia
    WHERE cm.id_curso = ?
    ORDER BY m.materia_padre_id, m.nombre_materia
");
$stmt_materias->execute([$id_curso]);
$materias_raw = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// Organizar materias: padres e hijas
$materias_padres = [];
$materias_hijas = [];
foreach ($materias_raw as $mat) {
    if ($mat['materia_padre_id']) {
        $materias_hijas[$mat['materia_padre_id']][] = $mat;
    } else {
        $materias_padres[$mat['id_materia']] = $mat;
    }
}

// Encabezado para la tabla
$columnas = [];
foreach ($materias_padres as $id_padre => $padre) {
    $columnas[] = [
        'tipo' => 'padre',
        'datos' => $padre
    ];
    foreach ($materias_hijas[$id_padre] ?? [] as $hija) {
        $columnas[] = [
            'tipo' => 'hija',
            'datos' => $hija
        ];
    }
}

// Estudiantes
$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, nombres, apellido_paterno, apellido_materno
    FROM estudiantes 
    WHERE id_curso = ? 
    ORDER BY apellido_paterno, apellido_materno, nombres
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// Notas
function obtenerNotaTrimestre($conn, $id_estudiante, $id_materia, $trim)
{
    $stmt = $conn->prepare("
        SELECT calificacion 
        FROM calificaciones 
        WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
    ");
    $stmt->execute([$id_estudiante, $id_materia, $trim]);
    $nota = $stmt->fetchColumn();
    return ($nota !== false && $nota !== null) ? $nota : '';
}

// Compacta el nombre de la materia: solo la primera palabra y la inicial de la segunda, todo mayúsculas
function compactarMateria($nombre)
{
    $nombre = strtoupper($nombre);
    $palabras = explode(' ', $nombre);
    if (count($palabras) == 1) {
        return $palabras[0];
    }
    return $palabras[0] . ' ' . substr($palabras[1], 0, 1) . '.';
}

// Prepara datos de notas
$notas = [];
foreach ($estudiantes as $est) {
    foreach ($columnas as $col) {
        $materia_id = $col['datos']['id_materia'];
        if ($col['tipo'] == 'hija') {
            $notas[$est['id_estudiante']][$materia_id] = obtenerNotaTrimestre($conn, $est['id_estudiante'], $materia_id, $trimestre);
        }
    }
}
// Calcula promedios de padres
foreach ($estudiantes as $est) {
    foreach ($materias_padres as $id_padre => $padre) {
        if (!empty($materias_hijas[$id_padre])) {
            $suma = 0;
            $cuenta = 0;
            foreach ($materias_hijas[$id_padre] as $hija) {
                $nota = $notas[$est['id_estudiante']][$hija['id_materia']] ?? '';
                if ($nota !== '' && is_numeric($nota)) {
                    $suma += $nota;
                    $cuenta++;
                }
            }
            $notas[$est['id_estudiante']][$id_padre] = ($cuenta > 0) ? round($suma / $cuenta) : '';
        } else {
            // Padre sin hijas: buscar su propia nota
            $nota = obtenerNotaTrimestre($conn, $est['id_estudiante'], $id_padre, $trimestre);
            $notas[$est['id_estudiante']][$id_padre] = ($nota !== '' && is_numeric($nota)) ? round($nota) : '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Centralizador: Trimestre <?= $trimestre ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: 250px;
            padding: 24px;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #212c3a;
            color: #fff;
            z-index: 1000;
            overflow-y: auto;
        }

        .header-container {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .table-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 15px;
            overflow-x: auto;
        }

        .tabla-centralizador {
            border-collapse: collapse;
            width: 100%;
            background-color: #fff;
            table-layout: auto;
            border: 1px solid #e0e0e0;
            font-size: 13px;
        }

        .tabla-centralizador th,
        .tabla-centralizador td {
            border: 1px solid #e0e0e0;
            padding: 6px 4px;
            text-align: center;
            vertical-align: middle;
        }

        .tabla-centralizador th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .col-estudiante {
            width: 260px !important;
            min-width: 260px !important;
            max-width: 260px !important;
            text-align: left !important;
            background-color: #f8f9fa;
            font-weight: 600;
            padding-left: 12px !important;
            position: sticky;
            left: 0;
            z-index: 20;
            border-right: 2px solid #dee2e6 !important;
            vertical-align: middle !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 15px !important;
        }

        .encabezado-materia {
            height: 120px;
            min-width: 35px;
            max-width: 35px;
            padding: 0;
            position: relative;
            background-color: #f8f9fa;
            border-left: 1px solid #e0e0e0;
        }

        .materia-padre {
            background-color: #e9ecef !important;
            font-weight: 700;
            border-bottom: 2px solid #ced4da !important;
        }

        .materia-hija {
            background-color: #f8f9fa !important;
        }

        .texto-vertical {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            transform: translate(-50%, -50%) rotate(-90deg);
            transform-origin: center center;
            white-space: nowrap;
            font-size: 10px;
            font-weight: 500;
            color: #212529;
            text-align: center;
            line-height: 1.2;
            padding: 5px 0;
        }

        .texto-vertical .nombre-materia {
            display: inline-block;
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nota-baja {
            color: #dc3545;
            font-weight: bold;
            background-color: #fff5f5 !important;
        }

        .nota-alta {
            color: #28a745;
            font-weight: bold;
        }

        tr:nth-child(even) td:not(.col-estudiante) {
            background-color: #fdfdfd;
        }

        tr:nth-child(odd) td:not(.col-estudiante) {
            background-color: #ffffff;
        }

        tr:hover td {
            background-color: #f1f3f5 !important;
        }

        .tabla-centralizador td:not(.col-estudiante) {
            min-width: 35px !important;
            max-width: 35px !important;
            width: 35px !important;
            padding: 4px 2px !important;
            font-size: 12px;
        }

        .form-select {
            font-size: 14px;
            width: 150px;
        }

        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }

        .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: white;
        }

        .trimestre-selector {
            min-width: 180px;
        }

        .badge-trimestre {
            font-size: 14px;
            padding: 6px 10px;
            background-color: #6c757d;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <div class="sidebar d-flex flex-column">
        <?php include '../includes/sidebar.php'; ?>
    </div>

    <div class="main-content">
        <div class="header-container d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <a href="ver_cursov.php?id=<?= $id_curso ?>" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Volver al curso
            </a>
            
            <div class="d-flex align-items-center gap-3">
                <span class="badge badge-trimestre rounded-pill bg-secondary">Trimestre <?= $trimestre ?></span>
                
                <form method="get" class="trimestre-selector">
                    <input type="hidden" name="id" value="<?= $id_curso ?>">
                    <select name="trimestre" class="form-select" onchange="this.form.submit()">
                        <option value="1" <?= $trimestre == 1 ? 'selected' : '' ?>>Trimestre 1</option>
                        <option value="2" <?= $trimestre == 2 ? 'selected' : '' ?>>Trimestre 2</option>
                        <option value="3" <?= $trimestre == 3 ? 'selected' : '' ?>>Trimestre 3</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="tabla-centralizador">
                    <thead>
                        <tr>
                            <th class="col-estudiante">Estudiante</th>
                            <?php foreach ($columnas as $col): ?>
                                <th class="encabezado-materia <?= $col['tipo'] == 'padre' ? 'materia-padre' : 'materia-hija' ?>">
                                    <div class="texto-vertical">
                                        <span class="nombre-materia">
                                            <?= compactarMateria($col['datos']['nombre_materia']) ?>
                                            <?php if ($col['tipo'] == 'padre'): ?>
                                                <i class="bi bi-collection" style="margin-left: 3px;"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantes as $est): ?>
                            <tr>
                                <td class="col-estudiante">
                                    <?= htmlspecialchars(strtoupper(
                                        $est['apellido_paterno'] . ' ' . $est['apellido_materno'] . ', ' . $est['nombres']
                                    )) ?>
                                </td>
                                <?php foreach ($columnas as $col): ?>
                                    <?php
                                        $materia_id = $col['datos']['id_materia'];
                                        $nota = $notas[$est['id_estudiante']][$materia_id] ?? '';
                                        $clase = '';
                                        if (is_numeric($nota)) {
                                            $clase = ($nota < 51) ? 'nota-baja' : (($nota > 89) ? 'nota-alta' : '');
                                        }
                                    ?>
                                    <td class="<?= $clase ?>">
                                        <?= ($nota !== '' && is_numeric($nota)) ? intval($nota) : '-' ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.tabla-centralizador tbody tr');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f1f3f5';
                });
                row.addEventListener('mouseleave', function() {
                    const isEven = this.rowIndex % 2 === 0;
                    this.style.backgroundColor = isEven ? '#fdfdfd' : '#ffffff';
                });
            });

            const adjustHeaderHeights = () => {
                const headers = document.querySelectorAll('.encabezado-materia');
                let maxHeight = 0;
                
                headers.forEach(header => {
                    const textElement = header.querySelector('.texto-vertical');
                    if (textElement) {
                        const rotatedHeight = textElement.getBoundingClientRect().width;
                        const currentHeight = header.style.height || '120px';
                        const numericHeight = parseInt(currentHeight);
                        
                        if (rotatedHeight > numericHeight) {
                            maxHeight = Math.max(maxHeight, rotatedHeight + 20);
                        }
                    }
                });

                if (maxHeight > 0) {
                    headers.forEach(header => {
                        header.style.height = `${maxHeight}px`;
                    });
                }
            };

            adjustHeaderHeights();
            window.addEventListener('resize', adjustHeaderHeights);
        });
    </script>
</body>
</html>