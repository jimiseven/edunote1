<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2])) {
    header('Location: ../index.php');
    exit();
}

// Mostrar mensajes de éxito/error
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Obtener ID del curso
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: dashboard.php?error=curso_no_especificado');
    exit();
}

$id_curso = intval($_GET['id']);
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'anual';
$trimestre = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : 1;

$database = new Database();
$conn = $database->connect();

$stmt_gestion = $conn->query("SELECT anio_escolar FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
$gestionConfigurada = $stmt_gestion->fetchColumn();
$gestionConfigurada = $gestionConfigurada ? trim((string)$gestionConfigurada) : '';
$gestionActual = $gestionConfigurada !== '' ? $gestionConfigurada : date('Y');
$gestionAlternativa = null;
if (preg_match('/\b(20\d{2})\b/', $gestionActual, $matches)) {
    $gestionAlternativa = $matches[1];
}

$stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
$stmt_curso->execute([$id_curso]);

if ($stmt_curso->rowCount() == 0) {
    header('Location: dashboard.php?error=curso_no_encontrado');
    exit();
}
$curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);
$nombre_curso = "{$curso_info['nivel']} {$curso_info['curso']} \"{$curso_info['paralelo']}\"";

// Obtener lista ordenada de cursos para navegación Anterior/Siguiente
$stmt_all_cursos = $conn->prepare("SELECT id_curso, nivel, curso, paralelo FROM cursos ORDER BY nivel, curso, paralelo");
$stmt_all_cursos->execute();
$lista_cursos = $stmt_all_cursos->fetchAll(PDO::FETCH_ASSOC);

$prev_curso_id = null;
$next_curso_id = null;
if (!empty($lista_cursos)) {
    foreach ($lista_cursos as $index => $curso_row) {
        if (intval($curso_row['id_curso']) === $id_curso) {
            if ($index > 0) {
                $prev_curso_id = intval($lista_cursos[$index - 1]['id_curso']);
            }
            if ($index < count($lista_cursos) - 1) {
                $next_curso_id = intval($lista_cursos[$index + 1]['id_curso']);
            }
            break;
        }
    }
}

$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, apellido_paterno, apellido_materno, nombres 
    FROM estudiantes 
    WHERE id_curso = ? 
    ORDER BY apellido_paterno, apellido_materno, nombres
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

$stmt_materias = $conn->prepare("
    SELECT m.id_materia, m.nombre_materia, m.es_extra, m.es_submateria, m.materia_padre_id
    FROM cursos_materias cm 
    JOIN materias m ON cm.id_materia = m.id_materia 
    WHERE cm.id_curso = ? 
    ORDER BY m.nombre_materia
");
$stmt_materias->execute([$id_curso]);
$todas_materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// Reorganiza materias: padres simples, extras, luego padres con hijas + hijas
$materias_padre = [];
$materias_extra = [];
$materias_hijas = [];
foreach ($todas_materias as $materia) {
    if ($materia['es_extra'] == 1) {
        $materias_extra[] = $materia;
    } elseif ($materia['es_submateria'] == 0) {
        $materia['hijas'] = [];
        $materias_padre[$materia['id_materia']] = $materia;
    } else {
        $materias_hijas[] = $materia;
    }
}

// Asocia hijas con sus padres
foreach ($materias_hijas as $hija) {
    if (isset($materias_padre[$hija['materia_padre_id']])) {
        $materias_padre[$hija['materia_padre_id']]['hijas'][] = $hija;
    }
}

// Separa padres simples y padres con hijas
$materias_padre_simples = [];
$materias_padre_con_hijas = [];
foreach ($materias_padre as $padre) {
    if (empty($padre['hijas'])) {
        $materias_padre_simples[] = $padre;
    } else {
        $materias_padre_con_hijas[] = $padre;
    }
}

// Orden final: padres simples > extras > padres con hijas (y sus hijas)
$materias = array_merge(
    $materias_padre_simples,  // 1. Padres sin hijas
    $materias_extra,           // 2. Materias extras
    $materias_padre_con_hijas  // 3. Padres con hijas (se mostrarán al final)
);

// Añade las hijas después de cada padre correspondiente
foreach ($materias_padre_con_hijas as $padre) {
    $materias = array_merge($materias, $padre['hijas']);
}

// Calificaciones
$calificaciones = [];
foreach ($estudiantes as $estudiante) {
    foreach ($todas_materias as $materia) {
        for ($i = 1; $i <= 3; $i++) {
            $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$i] = '';
        }
    }
}

$sqlCalificaciones = "SELECT cp.id_estudiante, cp.id_materia, pe.trimestre, cp.calificacion
                      FROM calificaciones_parciales cp
                      INNER JOIN periodos_evaluacion pe ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
                      INNER JOIN estudiantes e ON e.id_estudiante = cp.id_estudiante
                      INNER JOIN cursos_materias cm ON cm.id_materia = cp.id_materia
                      WHERE e.id_curso = ?
                        AND cm.id_curso = ?
                        AND (pe.gestion = ?";
$paramsCalificaciones = [$id_curso, $id_curso, $gestionActual];
if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
    $sqlCalificaciones .= " OR pe.gestion = ?";
    $paramsCalificaciones[] = $gestionAlternativa;
}
$sqlCalificaciones .= ")";

$stmt_calificaciones = $conn->prepare($sqlCalificaciones);
$stmt_calificaciones->execute($paramsCalificaciones);

$parcialesPorTrimestre = [];
foreach ($stmt_calificaciones->fetchAll(PDO::FETCH_ASSOC) as $filaCalificacion) {
    if ($filaCalificacion['calificacion'] === null || $filaCalificacion['calificacion'] === '') {
        continue;
    }
    $idEstudiante = (int)$filaCalificacion['id_estudiante'];
    $idMateria = (int)$filaCalificacion['id_materia'];
    $trimestreFila = (int)$filaCalificacion['trimestre'];
    $parcialesPorTrimestre[$idEstudiante][$idMateria][$trimestreFila][] = (float)$filaCalificacion['calificacion'];
}

foreach ($parcialesPorTrimestre as $idEstudiante => $materiasParciales) {
    foreach ($materiasParciales as $idMateria => $trimestresParciales) {
        foreach ($trimestresParciales as $numeroTrimestre => $notasParciales) {
            if (!empty($notasParciales)) {
                $calificaciones[$idEstudiante][$idMateria][$numeroTrimestre] = number_format(array_sum($notasParciales) / count($notasParciales), 2);
            }
        }
    }
}

$materiasPadreConHijasIds = [];
foreach ($materias_padre as $idPadreTmp => $padreTmp) {
    if (!empty($padreTmp['hijas'])) {
        $materiasPadreConHijasIds[$idPadreTmp] = true;
    }
}

$notasTrimestralesExtras = [];
$notasTrimestralesPadres = [];

$sqlTrimestrales = "SELECT ct.id_estudiante, ct.id_materia, ct.trimestre, ct.autoevaluacion, ct.nota_extra\n                      FROM calificaciones_trimestrales ct\n                      INNER JOIN estudiantes e ON e.id_estudiante = ct.id_estudiante\n                      INNER JOIN cursos_materias cm ON cm.id_materia = ct.id_materia\n                      WHERE e.id_curso = ?\n                        AND cm.id_curso = ?\n                        AND (ct.gestion = ?";
$paramsTrimestrales = [$id_curso, $id_curso, $gestionActual];
if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
    $sqlTrimestrales .= " OR ct.gestion = ?";
    $paramsTrimestrales[] = $gestionAlternativa;
}
$sqlTrimestrales .= ")";

$stmt_trimestrales = $conn->prepare($sqlTrimestrales);
$stmt_trimestrales->execute($paramsTrimestrales);

foreach ($stmt_trimestrales->fetchAll(PDO::FETCH_ASSOC) as $filaTrimestral) {
    $idEstTrim = (int)$filaTrimestral['id_estudiante'];
    $idMatTrim = (int)$filaTrimestral['id_materia'];
    $trimNumero = (int)$filaTrimestral['trimestre'];

    $registro = [
        'autoevaluacion' => $filaTrimestral['autoevaluacion'],
        'nota_extra' => $filaTrimestral['nota_extra']
    ];

    if (isset($materiasPadreConHijasIds[$idMatTrim])) {
        $notasTrimestralesPadres[$idEstTrim][$idMatTrim][$trimNumero] = $registro;
    } else {
        $notasTrimestralesExtras[$idEstTrim][$idMatTrim][$trimNumero] = $registro;
    }
}

// NOTA AUTOMÁTICA para materias padre (promedio de hijas)
$recalcularNotasPadre = static function (&$matrizCalificaciones) use ($estudiantes, $materias_padre) {
    foreach ($estudiantes as $estudiante) {
        foreach ($materias_padre as $padre) {
            if (empty($padre['hijas'])) {
                continue;
            }
            for ($t = 1; $t <= 3; $t++) {
                $suma = 0;
                $contador = 0;
                foreach ($padre['hijas'] as $hija) {
                    $notaHija = $matrizCalificaciones[$estudiante['id_estudiante']][$hija['id_materia']][$t] ?? '';
                    if ($notaHija !== '' && $notaHija !== null) {
                        $suma += (float)str_replace(',', '', (string)$notaHija);
                        $contador++;
                    }
                }
                if ($contador > 0) {
                    $matrizCalificaciones[$estudiante['id_estudiante']][$padre['id_materia']][$t] = number_format($suma / $contador, 2);
                }
            }
        }
    }
};
$recalcularNotasPadre($calificaciones);

$aplicarNotasTrimestrales = static function (&$matrizCalificaciones, array $datosExtras) {
    foreach ($datosExtras as $idEstudiante => $materiasExtras) {
        foreach ($materiasExtras as $idMateria => $trimestresExtras) {
            foreach ($trimestresExtras as $numeroTrimestre => $valoresExtras) {
                $baseActual = $matrizCalificaciones[$idEstudiante][$idMateria][$numeroTrimestre] ?? '';
                $baseNum = ($baseActual !== '' && $baseActual !== null)
                    ? (float)str_replace(',', '', (string)$baseActual)
                    : null;

                $autoValor = $valoresExtras['autoevaluacion'];
                $extraValor = $valoresExtras['nota_extra'];

                $autoNum = ($autoValor !== null && $autoValor !== '') ? (float)$autoValor : null;
                $extraNum = ($extraValor !== null && $extraValor !== '') ? (float)$extraValor : null;

                if ($baseNum === null && $autoNum === null && $extraNum === null) {
                    continue;
                }

                $total = ($baseNum ?? 0) + ($autoNum ?? 0) + ($extraNum ?? 0);
                $matrizCalificaciones[$idEstudiante][$idMateria][$numeroTrimestre] = number_format($total, 2);
            }
        }
    }
};

if (!empty($notasTrimestralesExtras)) {
    $aplicarNotasTrimestrales($calificaciones, $notasTrimestralesExtras);
    if (!empty($materiasPadreConHijasIds)) {
        $recalcularNotasPadre($calificaciones);
    }
}

if (!empty($notasTrimestralesPadres)) {
    $aplicarNotasTrimestrales($calificaciones, $notasTrimestralesPadres);
}

// Build data for modal: individual partial grades + trimester extras
$detalleParciales = [];
foreach ($parcialesPorTrimestre as $idEst => $materias_est) {
    foreach ($materias_est as $idMat => $trimestres) {
        foreach ($trimestres as $numTrim => $parciales) {
            $detalleParciales[$idEst][$idMat][$numTrim] = [
                'parciales' => array_values($parciales),
                'autoevaluacion' => $notasTrimestralesExtras[$idEst][$idMat][$numTrim]['autoevaluacion']
                    ?? $notasTrimestralesPadres[$idEst][$idMat][$numTrim]['autoevaluacion']
                    ?? null,
                'nota_extra' => $notasTrimestralesExtras[$idEst][$idMat][$numTrim]['nota_extra']
                    ?? $notasTrimestralesPadres[$idEst][$idMat][$numTrim]['nota_extra']
                    ?? null,
            ];
        }
    }
}

// PROMEDIOS
$promedios_materias = [];
foreach ($estudiantes as $estudiante) {
    foreach ($todas_materias as $materia) {
        $notas = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']] ?? [];
        $notas_validas = array_filter($notas, function ($v) {
            return $v !== '' && $v !== null;
        });
        $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] =
            (count($notas_validas) > 0) ? number_format(array_sum($notas_validas) / count($notas_validas), 2) : '';
    }
}

// PROMEDIO GENERAL: Solo materias padre
$promedios_generales = [];
$promedios_trimestre = [];
foreach ($estudiantes as $estudiante) {
    $suma_promedios = 0;
    $contador = 0;
    foreach ($todas_materias as $materia) {
        if ($materia['es_extra'] == 1 || $materia['es_submateria'] == 1)
            continue;
        $promedio = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
        if ($promedio !== '') {
            $suma_promedios += floatval($promedio);
            $contador++;
        }
    }
    $promedios_generales[$estudiante['id_estudiante']] = ($contador > 0)
        ? number_format($suma_promedios / $contador, 2) : '-';

    $suma_trimestre = 0;
    $contador_trimestre = 0;
    foreach ($todas_materias as $materia) {
        if ($materia['es_extra'] == 1 || $materia['es_submateria'] == 1)
            continue;
        $nota = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$trimestre] ?? '';
        if ($nota !== '') {
            $suma_trimestre += floatval($nota);
            $contador_trimestre++;
        }
    }
    $promedios_trimestre[$estudiante['id_estudiante']] = ($contador_trimestre > 0)
        ? number_format($suma_trimestre / $contador_trimestre, 2) : '-';
}
// Posiciones
$promedios_ordenados = $promedios_generales;
arsort($promedios_ordenados);
$posiciones = [];
$pos_actual = 1;
$prom_anterior = null;
foreach ($promedios_ordenados as $id_est => $prom) {
    if ($prom_anterior !== null && $prom < $prom_anterior)
        $pos_actual++;
    $posiciones[$id_est] = $pos_actual;
    $prom_anterior = $prom;
}
$estudiantes_ordenados = $estudiantes;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Centralizador - <?= htmlspecialchars($nombre_curso) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap & Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: #f5f6fa;
            height: 100vh;
            overflow: auto;
            transition: background-color 0.25s ease, color 0.25s ease;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        h3,
        .fs-5 {
            font-size: 1.35rem !important;
            font-weight: 700 !important;
            letter-spacing: -0.01em;
        }

        .centralizador-table th,
        .centralizador-table td,
        .badge,
        .btn {
            font-size: 0.92rem;
        }

        .container-fluid {
            min-height: 100vh;
        }

        main {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding-left: 0.4rem;
            padding-right: 0.4rem;
        }

        .content-section {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            padding: 0;
            margin: 0 0 8px 0;
            flex: 1;
            width: 100%;
        }

        /* Contenedor de tabla con scroll unificado */
        .table-responsive {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            overflow: auto;
            max-height: calc(100vh - 132px);
            scrollbar-gutter: stable both-edges;
            position: relative;
            width: 100%;
        }

        .table-responsive::after {
            content: '';
            position: sticky;
            right: 0;
            top: 0;
            display: block;
            width: 18px;
            height: 100%;
            float: right;
            pointer-events: none;
            background: linear-gradient(to left, rgba(248, 249, 250, 0.95), rgba(248, 249, 250, 0));
        }

        .centralizador-table {
            min-width: max-content;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .centralizador-table tbody tr {
            transition: background-color 0.18s ease, box-shadow 0.18s ease;
        }

        .centralizador-table th,
        .centralizador-table td {
            white-space: nowrap;
            vertical-align: middle;
            padding: 0.34rem 0.44rem;
            font-size: 0.94rem;
        }

        .centralizador-table tbody tr:nth-child(odd) td {
            background: #ffffff;
        }

        .centralizador-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .centralizador-table tbody tr {
            border-bottom: 2px solid #e5e7eb;
        }

        .centralizador-table tbody tr:hover td {
            background: #bfdbfe !important;
            color: #0f172a !important;
        }

        .centralizador-table td.nota-detail-trigger {
            cursor: pointer;
            position: relative;
            transition: background-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
        }

        .centralizador-table td.nota-detail-trigger:hover {
            background: #dbeafe !important;
            box-shadow: inset 0 0 0 2px rgba(59, 130, 246, 0.4);
        }

        .centralizador-table td.nota-detail-trigger:focus-visible {
            outline: 2px solid rgba(37, 99, 235, 0.6);
            outline-offset: 1px;
        }

        /* Modal detalle parciales */
        #notaDetalleModal .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,.15);
            overflow: hidden;
        }
        #notaDetalleModal .modal-header {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border-bottom: none;
            padding: 10px 16px;
        }
        #notaDetalleModal .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        #notaDetalleModal .modal-body {
            padding: 16px;
        }
        #notaDetalleModal .detalle-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            margin-bottom: 12px;
            font-size: .85rem;
        }
        #notaDetalleModal .detalle-info span {
            color: #475569;
        }
        #notaDetalleModal .detalle-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            font-size: .85rem;
        }
        #notaDetalleModal .detalle-table th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 600;
            padding: 7px 6px;
            text-align: center;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        #notaDetalleModal .detalle-table td {
            padding: 8px 6px;
            text-align: center;
            color: #1e293b;
            font-weight: 500;
            border-right: 1px solid #f1f5f9;
        }
        #notaDetalleModal .detalle-table td:last-child { border-right: none; }
        #notaDetalleModal .detalle-table th.col-prom {
            background: #fef3c7;
            color: #92400e;
        }
        #notaDetalleModal .detalle-table td.col-prom {
            background: #fffbeb;
            color: #92400e;
            font-weight: 700;
        }
        #notaDetalleModal .detalle-table th.col-auto {
            background: #e0f2fe;
            color: #075985;
        }
        #notaDetalleModal .detalle-table td.col-auto {
            background: #f0f9ff;
            color: #075985;
        }
        #notaDetalleModal .detalle-table th.col-extra {
            background: #ede9fe;
            color: #5b21b6;
        }
        #notaDetalleModal .detalle-table td.col-extra {
            background: #f5f3ff;
            color: #5b21b6;
        }
        #notaDetalleModal .detalle-table th.col-total {
            background: #d1fae5;
            color: #065f46;
        }
        #notaDetalleModal .detalle-table td.col-total {
            background: #ecfdf5;
            color: #065f46;
            font-weight: 700;
            font-size: .9rem;
        }
        #notaDetalleModal .modal-footer {
            border-top: 1px solid #f1f5f9;
            padding: 8px 16px;
        }
        .dark-mode #notaDetalleModal .modal-content {
            background: #1e293b;
        }
        .dark-mode #notaDetalleModal .modal-header {
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
        }
        .dark-mode #notaDetalleModal .detalle-info span {
            color: #94a3b8;
        }
        .dark-mode #notaDetalleModal .detalle-table th {
            background: #334155;
            color: #e2e8f0;
            border-bottom-color: #475569;
        }
        .dark-mode #notaDetalleModal .detalle-table td {
            color: #e2e8f0;
            border-right-color: #334155;
        }
        .dark-mode #notaDetalleModal .detalle-table {
            border-color: #475569;
        }
        .dark-mode #notaDetalleModal .detalle-table th.col-prom { background: #422006; color: #fde68a; }
        .dark-mode #notaDetalleModal .detalle-table td.col-prom { background: #451a03; color: #fde68a; }
        .dark-mode #notaDetalleModal .detalle-table th.col-auto { background: #0c4a6e; color: #bae6fd; }
        .dark-mode #notaDetalleModal .detalle-table td.col-auto { background: #082f49; color: #bae6fd; }
        .dark-mode #notaDetalleModal .detalle-table th.col-extra { background: #4c1d95; color: #ddd6fe; }
        .dark-mode #notaDetalleModal .detalle-table td.col-extra { background: #3b0764; color: #ddd6fe; }
        .dark-mode #notaDetalleModal .detalle-table th.col-total { background: #064e3b; color: #a7f3d0; }
        .dark-mode #notaDetalleModal .detalle-table td.col-total { background: #022c22; color: #a7f3d0; }
        .dark-mode #notaDetalleModal .modal-footer { border-top-color: #334155; }

        .centralizador-table tbody tr.top-performer-row td {
            background: #dcfce7 !important;
            color: #14532d !important;
            box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.35);
        }

        .centralizador-table tbody tr.top-performer-row td.number-cell,
        .centralizador-table tbody tr.top-performer-row td.position-cell,
        .centralizador-table tbody tr.top-performer-row td.student-name {
            background: #bbf7d0 !important;
            color: #14532d !important;
            box-shadow: 1px 0 0 rgba(34, 197, 94, 0.35);
        }

        .centralizador-table tbody tr.top-performer-row td.final-average,
        .centralizador-table tbody tr.top-performer-row td.average-cell {
            background: #bbf7d0 !important;
            color: #14532d !important;
        }

        .centralizador-table tbody tr:hover td:nth-child(1),
        .centralizador-table tbody tr:hover td:nth-child(2),
        .centralizador-table tbody tr:hover td:nth-child(3) {
            background: #93c5fd !important;
            color: #0b2545 !important;
            box-shadow: 1px 0 0 #60a5fa;
        }

        .centralizador-table thead th {
            background: #e9ecef;
            color: #222;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 20;
            box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);
        }

        .materia-head {
            min-width: 240px;
            width: 240px;
            max-width: 240px;
            text-align: center;
            vertical-align: middle;
        }

        .materia-subhead {
            min-width: 60px;
            width: 60px;
            max-width: 60px;
            text-align: center;
        }

        .nombre-materia {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
            min-height: 2.4em;
            max-width: 220px;
            margin: 0 auto;
            white-space: normal;
            word-break: break-word;
            font-size: 0.92rem;
        }

        .nombre-materia.long-name {
            font-size: 0.82rem;
        }

        .nombre-materia.extra-long-name {
            font-size: 0.74rem;
        }

        .centralizador-table .nota-baja {
            background: #fff5f6;
            color: #c01a30 !important;
            font-weight: 600;
        }

        .centralizador-table .average-cell {
            background: #f1f1f7 !important;
            font-weight: 500;
        }

        .centralizador-table .final-average {
            background: #ececec !important;
            font-weight: 650;
            color: #3a3a95;
        }

        .badge-extra {
            font-size: .78em;
            background: #838897 !important;
            color: #fff !important;
        }

        .position-cell,
        .number-cell {
            background: #f5f7fa;
            font-weight: 600;
            color: #5472a1;
        }

        .number-cell {
            min-width: 56px;
            width: 56px;
            text-align: center;
            position: sticky;
            left: 0;
            z-index: 16;
            box-shadow: 1px 0 0 #dee2e6;
        }

        .position-cell {
            min-width: 56px;
            width: 56px;
            text-align: center;
            position: sticky;
            left: 56px;
            z-index: 16;
            box-shadow: 1px 0 0 #dee2e6;
        }

        .student-name {
            min-width: 220px;
            white-space: nowrap;
            position: sticky;
            left: 112px;
            background: #fff;
            z-index: 16;
            box-shadow: 1px 0 0 #dbe4f0;
            font-weight: 600;
            border-right: 2px solid #dbe4f0;
        }

        .header-controls {
            background: #fff;
            border-bottom: 1px solid #e2e4e7;
            position: sticky;
            top: 0;
            z-index: 101;
            flex-shrink: 0;
            margin-bottom: 0.55rem;
        }

        .header-main {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            padding: 0.45rem 0.15rem;
            margin-bottom: 0;
        }

        .header-title-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-height: 36px;
        }

        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            justify-content: flex-end;
            max-width: 100%;
        }

        .theme-toggle-btn {
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-weight: 600;
        }

        .btn {
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.01em;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
            transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
            padding-top: 0.32rem;
            padding-bottom: 0.32rem;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.14);
        }

        .btn-outline-secondary,
        .btn-outline-warning,
        .btn-outline-info,
        .btn-outline-primary {
            background: #ffffff;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-color: #1d4ed8;
        }

        .btn-success {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border-color: #15803d;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border-color: #b91c1c;
        }

        .centralizador-table th.extra-materia,
        .centralizador-table .materia-extra {
            background: #e6f4ff !important;
            color: #0d6efd !important;
            font-style: italic;
        }

        body.dark-mode {
            background: #0b1220 !important;
            color: #e5eefb !important;
        }

        body.dark-mode .content-section,
        body.dark-mode .header-controls,
        body.dark-mode .table-responsive {
            background: #111827 !important;
            color: #e5eefb !important;
            border-color: #243244 !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.28);
        }

        body.dark-mode .table-responsive::after {
            background: linear-gradient(to left, rgba(17, 24, 39, 0.98), rgba(17, 24, 39, 0)) !important;
        }

        body.dark-mode .centralizador-table td,
        body.dark-mode .centralizador-table th,
        body.dark-mode .student-name,
        body.dark-mode .number-cell,
        body.dark-mode .position-cell {
            border-color: #243244 !important;
        }

        body.dark-mode .centralizador-table thead th,
        body.dark-mode .centralizador-table thead th:nth-child(1),
        body.dark-mode .centralizador-table thead th:nth-child(2),
        body.dark-mode .centralizador-table thead th:nth-child(3) {
            background: #1b2535 !important;
            color: #f8fbff !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.35) !important;
        }

        body.dark-mode .nombre-materia {
            color: #f8fbff;
        }

        body.dark-mode .centralizador-table tbody tr:nth-child(odd) td,
        body.dark-mode .centralizador-table tbody td:nth-child(1),
        body.dark-mode .centralizador-table tbody td:nth-child(2),
        body.dark-mode .centralizador-table tbody td:nth-child(3) {
            background: #0f172a !important;
            color: #dbeafe !important;
        }

        body.dark-mode .centralizador-table tbody tr:nth-child(even) td,
        body.dark-mode .centralizador-table tbody tr:nth-child(even) td:nth-child(1),
        body.dark-mode .centralizador-table tbody tr:nth-child(even) td:nth-child(2),
        body.dark-mode .centralizador-table tbody tr:nth-child(even) td:nth-child(3) {
            background: #131d31 !important;
            color: #dbeafe !important;
        }

        body.dark-mode .centralizador-table tbody tr.top-performer-row td {
            background: rgba(34, 197, 94, 0.35) !important;
            color: #ecfdf5 !important;
            box-shadow: inset 0 0 0 1px rgba(134, 239, 172, 0.5);
        }

        body.dark-mode .centralizador-table tbody tr.top-performer-row td.number-cell,
        body.dark-mode .centralizador-table tbody tr.top-performer-row td.position-cell,
        body-dark-mode .centralizador-table tbody tr.top-performer-row td.student-name {
            background: rgba(134, 239, 172, 0.55) !important;
            color: #064e3b !important;
            box-shadow: 1px 0 0 rgba(134, 239, 172, 0.4) !important;
        }

        body.dark-mode .centralizador-table tbody tr.top-performer-row td.average-cell,
        body-dark-mode .centralizador-table tbody tr.top-performer-row td.final-average {
            background: rgba(134, 239, 172, 0.55) !important;
            color: #064e3b !important;
        }

        body.dark-mode .centralizador-table tbody tr:hover td {
            background: #1e40af !important;
            color: #eff6ff !important;
        }

        body.dark-mode .centralizador-table tbody tr:hover td:nth-child(1),
        body.dark-mode .centralizador-table tbody tr:hover td:nth-child(2),
        body.dark-mode .centralizador-table tbody tr:hover td:nth-child(3) {
            background: #2563eb !important;
            color: #ffffff !important;
            box-shadow: 1px 0 0 #60a5fa !important;
        }

        body.dark-mode .centralizador-table .average-cell {
            background: #1c2a3d !important;
            color: #e2e8f0 !important;
        }

        body.dark-mode .centralizador-table .final-average {
            background: #22345a !important;
            color: #e0e7ff !important;
        }

        body.dark-mode .centralizador-table .nota-baja {
            background: #3b1220 !important;
            color: #fda4af !important;
        }

        body.dark-mode .centralizador-table th.extra-materia,
        body.dark-mode .centralizador-table .materia-extra {
            background: #17304f !important;
            color: #bfdbfe !important;
        }

        body.dark-mode .btn-outline-secondary,
        body.dark-mode .btn-outline-warning,
        body.dark-mode .btn-outline-info,
        body.dark-mode .btn-outline-primary {
            color: #dbeafe !important;
            border-color: #41566f !important;
            background: #182234 !important;
        }

        body.dark-mode .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-color: #1e40af;
        }

        body.dark-mode .btn-success {
            background: linear-gradient(135deg, #15803d, #166534);
            border-color: #166534;
        }

        body.dark-mode .btn-danger {
            background: linear-gradient(135deg, #b91c1c, #991b1b);
            border-color: #991b1b;
        }

        body.dark-mode .alert-success {
            background: #052e16;
            color: #bbf7d0;
            border-color: #166534;
        }

        /* Estilos para las columnas fijas iniciales */
        .centralizador-table th:nth-child(1),
        .centralizador-table th:nth-child(2),
        .centralizador-table th:nth-child(3),
        .centralizador-table td:nth-child(1),
        .centralizador-table td:nth-child(2),
        .centralizador-table td:nth-child(3) {
            position: sticky;
            z-index: 15;
            background: #fff;
        }

        .centralizador-table tbody td:nth-child(1),
        .centralizador-table tbody td:nth-child(2),
        .centralizador-table tbody td:nth-child(3) {
            background: #ffffff !important;
        }

        .centralizador-table tbody tr:nth-child(even) td:nth-child(1),
        .centralizador-table tbody tr:nth-child(even) td:nth-child(2),
        .centralizador-table tbody tr:nth-child(even) td:nth-child(3) {
            background: #f8fafc !important;
        }

        .centralizador-table th:nth-child(1),
        .centralizador-table td:nth-child(1) {
            left: 0;
        }

        .centralizador-table th:nth-child(2),
        .centralizador-table td:nth-child(2) {
            left: 56px;
        }

        .centralizador-table th:nth-child(3),
        .centralizador-table td:nth-child(3) {
            left: 112px;
        }

        /* Asegurar que los headers sticky mantengan su fondo */
        .centralizador-table thead th:nth-child(1),
        .centralizador-table thead th:nth-child(2),
        .centralizador-table thead th:nth-child(3) {
            background: #e9ecef !important;
            z-index: 25;
            box-shadow: 1px 0 0 #cbd5e1;
        }

        /* Responsive para diferentes tamaños de pantalla */
        @media (min-width: 1920px) {
            .table-responsive {
                max-height: calc(100vh - 120px);
            }
        }

        @media (min-width: 1440px) and (max-width: 1919px) {
            .table-responsive {
                max-height: calc(100vh - 126px);
            }
        }

        @media (min-width: 1200px) and (max-width: 1439px) {
            .table-responsive {
                max-height: calc(100vh - 132px);
            }
        }

        @media (min-width: 992px) and (max-width: 1199px) {
            .table-responsive {
                max-height: calc(100vh - 148px);
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            .centralizador-table th,
            .centralizador-table td {
                font-size: .85rem !important;
                padding: .25rem !important;
            }

            .content-section {
                padding: 12px 6px;
                margin: 16px 0 8px 0;
            }

            .table-responsive {
                max-height: calc(100vh - 168px);
            }
        }

        @media (max-width: 767px) {
            .header-main {
                align-items: flex-start;
            }

            .header-title-group,
            .header-actions {
                width: 100%;
            }

            .header-actions {
                justify-content: flex-start;
            }

            main {
                padding-left: 0.2rem;
                padding-right: 0.2rem;
            }

            .centralizador-table th,
            .centralizador-table td {
                font-size: .75rem !important;
                padding: .15rem !important;
            }

            .content-section {
                padding: 8px 4px;
                margin: 10px 0 5px 0;
            }

            .table-responsive {
                max-height: calc(100vh - 210px);
            }
        }

        @media (max-width: 480px) {
            .table-responsive {
                max-height: calc(100vh - 235px);
            }
        }

        @media print {
            .header-controls,
            .btn,
            .no-print {
                display: none !important;
            }

            body {
                background: #fff !important;
                height: auto !important;
                overflow: visible !important;
            }

            .table-responsive {
                max-height: none !important;
                height: auto !important;
                overflow: visible !important;
            }

            main {
                height: auto !important;
                overflow: visible !important;
            }

            .container-fluid,
            .row {
                height: auto !important;
            }
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .centralizador-table,
            .centralizador-table * {
                visibility: visible;
            }

            .centralizador-table {
                position: absolute;
                left: 0;
                top: 0;
                width: 100% !important;
                max-width: 100% !important;
            }
        }

        /* Mejoras para pantallas ultrawide */
        @media (min-width: 2560px) {
            .table-responsive {
                height: calc(100vh - 118px);
            }
        }

        /* Mejoras para laptops pequeños */
        @media (min-height: 600px) and (max-height: 800px) {
            .table-responsive {
                height: calc(100vh - 160px);
            }
        }

        /* Pantallas muy altas */
        @media (min-height: 1080px) {
            .table-responsive {
                height: calc(100vh - 126px);
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Incluye tu sidebar real aquí, sin rehacerlo -->
            <?php include '../includes/sidebar.php'; ?>

            <main class="col-md-10 ms-sm-auto col-lg-10 px-md-2 px-lg-2">
                <!-- Header con título y botones -->
                <div class="header-controls no-print">
                    <div class="header-main">
                    <div class="header-title-group">
                    <span class="fs-5 fw-bold text-primary"><?= htmlspecialchars($nombre_curso) ?></span>
                </div>
                <div class="header-actions">
                        <button type="button" id="themeToggle" class="btn btn-outline-secondary btn-sm theme-toggle-btn">
                            <i class="bi bi-moon-stars"></i>
                            <span>Vista nocturna</span>
                        </button>
                        <?php if ($prev_curso_id !== null): ?>
                            <a href="ver_curso.php?id=<?= $prev_curso_id ?>&vista=<?= urlencode($vista) ?>&trimestre=<?= (int)$trimestre ?>"
                               class="btn btn-outline-secondary btn-sm" title="Curso anterior">
                                <i class="bi bi-chevron-left"></i> Anterior
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-sm" title="No hay curso anterior" disabled>
                                <i class="bi bi-chevron-left"></i> Anterior
                            </button>
                        <?php endif; ?>

                        <?php if ($next_curso_id !== null): ?>
                            <a href="ver_curso.php?id=<?= $next_curso_id ?>&vista=<?= urlencode($vista) ?>&trimestre=<?= (int)$trimestre ?>"
                               class="btn btn-outline-secondary btn-sm" title="Curso siguiente">
                                Siguiente <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-sm" title="No hay curso siguiente" disabled>
                                Siguiente <i class="bi bi-chevron-right"></i>
                            </button>
                        <?php endif; ?>
                        <a href="editar_notas.php?id=<?= $id_curso ?>" class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-pencil"></i> Editar
                        </a>
                        <a href="ver_trimestre.php?id_curso=<?= $id_curso ?>" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-calendar-week"></i> Ver Trimestre
                        </a>
                        <a href="repoEx.php?id_curso=<?= $id_curso ?>" class="btn btn-success btn-sm">
                            <i class="bi bi-file-earmark-excel"></i> Reporte Excel
                        </a>
                        <a href="repoPdf.php?id_curso=<?= $id_curso ?>" class="btn btn-danger btn-sm">
                            <i class="bi bi-file-earmark-pdf"></i> Reporte PDF
                        </a>
                        <button onclick="showOrderModal()" class="btn btn-primary btn-sm">
                            <i class="bi bi-arrow-down-up"></i> Ordenar Materias
                        </button>
                    </div>
                </div>
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-3">
                        <?= $success_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <section class="content-section">
                    <div class="table-responsive">
                        <table class="table centralizador-table table-bordered align-middle table-sm mb-0">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="align-middle">#</th>
                                    <th rowspan="2" class="align-middle">Pos.</th>
                                    <th rowspan="2" class="align-middle text-start">Estudiante</th>
                                    <?php foreach ($materias as $materia): ?>
                                        <?php
                                        $longitudMateria = mb_strlen($materia['nombre_materia']);
                                        $claseNombreMateria = 'nombre-materia';
                                        if ($longitudMateria > 38) {
                                            $claseNombreMateria .= ' extra-long-name';
                                        } elseif ($longitudMateria > 24) {
                                            $claseNombreMateria .= ' long-name';
                                        }
                                        ?>
                                        <th colspan="4"
                                            class="text-center materia-head <?= $materia['es_extra'] ? 'extra-materia' : '' ?>">
                                            <span class="<?= $claseNombreMateria ?>">
                                                <?= htmlspecialchars($materia['nombre_materia']) ?>
                                            </span>
                                            <?php if (!empty($materia['es_extra'])): ?>
                                                <span class="badge badge-extra ms-1">Extra</span>
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th rowspan="2" class="text-center">P. General</th>
                                </tr>
                                <tr>
                                    <?php foreach ($materias as $materia): ?>
                                        <th class="text-center materia-subhead<?= $materia['es_extra'] ? ' extra-materia' : '' ?>">T1</th>
                                        <th class="text-center materia-subhead<?= $materia['es_extra'] ? ' extra-materia' : '' ?>">T2</th>
                                        <th class="text-center materia-subhead<?= $materia['es_extra'] ? ' extra-materia' : '' ?>">T3</th>
                                        <th class="text-center materia-subhead<?= $materia['es_extra'] ? ' extra-materia' : '' ?>">P</th>
                                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $contador = 1; ?>
                                <?php foreach ($estudiantes_ordenados as $estudiante): ?>
                                    <?php
                                    $posicionEst = $posiciones[$estudiante['id_estudiante']] ?? null;
                                    $esTopPerformer = $posicionEst !== null && $posicionEst <= 3;
                                    $nombreEstudiante = strtoupper("{$estudiante['apellido_paterno']} {$estudiante['apellido_materno']}, {$estudiante['nombres']}");
                                    ?>
                                    <tr class="<?= $esTopPerformer ? 'top-performer-row' : '' ?>">
                                        <td class="number-cell"><?= $contador++ ?></td>
                                        <td class="position-cell"><?= $posiciones[$estudiante['id_estudiante']] ?></td>
                                        <td class="student-name">
                                            <?= htmlspecialchars($nombreEstudiante) ?>
                                        </td>
                                        <?php foreach ($materias as $materia): ?>
                                            <?php
                                            $clase_extra = !empty($materia['es_extra']) ? 'materia-extra' : '';
                                            $n1 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][1] ?? '';
                                            $n2 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][2] ?? '';
                                            $n3 = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][3] ?? '';
                                            $pm = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
                                            $idEstJs = $estudiante['id_estudiante'];
                                            $idMatJs = $materia['id_materia'];
                                            $nomEstEsc = htmlspecialchars($nombreEstudiante, ENT_QUOTES, 'UTF-8');
                                            $nomMatEsc = htmlspecialchars($materia['nombre_materia'], ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <td class="nota-detail-trigger <?= $clase_extra ?> <?= (is_numeric($n1) && $n1 < 50) ? 'nota-baja' : '' ?>"
                                                data-estudiante-id="<?= $idEstJs ?>" data-materia-id="<?= $idMatJs ?>" data-trimestre="1"
                                                data-student="<?= $nomEstEsc ?>" data-materia-name="<?= $nomMatEsc ?>"><?= $n1 ?></td>
                                            <td class="nota-detail-trigger <?= $clase_extra ?> <?= (is_numeric($n2) && $n2 < 50) ? 'nota-baja' : '' ?>"
                                                data-estudiante-id="<?= $idEstJs ?>" data-materia-id="<?= $idMatJs ?>" data-trimestre="2"
                                                data-student="<?= $nomEstEsc ?>" data-materia-name="<?= $nomMatEsc ?>"><?= $n2 ?></td>
                                            <td class="nota-detail-trigger <?= $clase_extra ?> <?= (is_numeric($n3) && $n3 < 50) ? 'nota-baja' : '' ?>"
                                                data-estudiante-id="<?= $idEstJs ?>" data-materia-id="<?= $idMatJs ?>" data-trimestre="3"
                                                data-student="<?= $nomEstEsc ?>" data-materia-name="<?= $nomMatEsc ?>"><?= $n3 ?></td>
                                            <td class="average-cell <?= $clase_extra ?> <?= (is_numeric($pm) && $pm < 50) ? 'nota-baja' : '' ?>"><?= $pm ?></td>
                                        <?php endforeach; ?>
                                        <td class="final-average"><?= $promedios_generales[$estudiante['id_estudiante']] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <div class="modal fade" id="notaDetalleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title mb-0" id="notaDetalleTitulo">Detalle Trimestre</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="detalle-info">
                        <div><strong>Estudiante:</strong> <span id="detalleEstudiante">—</span></div>
                        <div><strong>Materia:</strong> <span id="detalleMateria">—</span></div>
                    </div>
                    <table class="detalle-table">
                        <thead>
                            <tr id="detalleParcialesHead"></tr>
                        </thead>
                        <tbody>
                            <tr id="detalleParcialesBody"></tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = themeToggle ? themeToggle.querySelector('i') : null;
            const themeText = themeToggle ? themeToggle.querySelector('span') : null;
            const storageKey = 'edunote-theme-dark';

            function applyTheme(isDark) {
                document.body.classList.toggle('dark-mode', isDark);
                if (themeIcon) {
                    themeIcon.className = isDark ? 'bi bi-sun' : 'bi bi-moon-stars';
                }
                if (themeText) {
                    themeText.textContent = isDark ? 'Vista diurna' : 'Vista nocturna';
                }
            }

            const savedTheme = localStorage.getItem(storageKey);
            applyTheme(savedTheme === 'true');

            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    const isDark = !document.body.classList.contains('dark-mode');
                    applyTheme(isDark);
                    localStorage.setItem(storageKey, isDark ? 'true' : 'false');
                });
            }
        });

        function showOrderModal() {
            const modalHTML = `
                <div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Ordenar Materias</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted mb-3">Arrastra las materias para cambiar su orden</p>
                                <div class="list-group" id="sortableMaterias">
                                    ${[...document.querySelectorAll('.nombre-materia')].map((el, i) => `
                                        <div class="list-group-item d-flex justify-content-between align-items-center" data-materia="${el.textContent.trim()}">
                                            <div>
                                                <i class="bi bi-grip-vertical me-2 text-muted"></i>
                                                ${el.textContent.trim()}
                                            </div>
                                            <span class="badge bg-secondary">${i + 1}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" onclick="applyNewOrder()">Aplicar Orden</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            new Sortable(document.getElementById('sortableMaterias'), {
                animation: 150,
                handle: '.bi-grip-vertical',
                ghostClass: 'list-group-item-secondary'
            });
            
            const modal = new bootstrap.Modal(document.getElementById('orderModal'));
            modal.show();
            
            document.getElementById('orderModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        function applyNewOrder() {
            const materiasOrdenadas = [...document.querySelectorAll('#sortableMaterias [data-materia]')]
                .map(el => el.dataset.materia);
            
            const table = document.querySelector('.centralizador-table');
            const headerRow = table.querySelector('thead tr:first-child');
            const subHeaderRow = table.querySelector('thead tr:last-child');
            const bodyRows = [...table.querySelectorAll('tbody tr')];
            
            // Obtener solo los headers de materias (excluyendo las primeras 3 columnas y la última)
            const originalHeaders = [...headerRow.querySelectorAll('th[colspan]')];
            const originalSubHeaders = [...subHeaderRow.querySelectorAll('th')].slice(3, -1); // Excluir columnas fijas
            
            const newHeaders = [];
            const newSubHeaders = [];
            
            materiasOrdenadas.forEach(materia => {
                const index = originalHeaders.findIndex(th =>
                    th.querySelector('.nombre-materia').textContent.trim() === materia);
                
                if (index !== -1) {
                    newHeaders.push(originalHeaders[index]);
                    // Agregar los 4 subheaders correspondientes (T1, T2, T3, P)
                    newSubHeaders.push(...originalSubHeaders.slice(index*4, index*4+4));
                }
            });
            
            // Preservar columnas fijas al inicio y final
            const fixedStartHeaders = [...headerRow.querySelectorAll('th')].slice(0, 3);
            const fixedEndHeader = [...headerRow.querySelectorAll('th')].slice(-1)[0];
            
            const fixedStartSubHeaders = [...subHeaderRow.querySelectorAll('th')].slice(0, 3);
            const fixedEndSubHeader = [...subHeaderRow.querySelectorAll('th')].slice(-1)[0];
            
            // Reconstruir headers manteniendo las columnas fijas
            headerRow.innerHTML = '';
            fixedStartHeaders.forEach(th => headerRow.appendChild(th));
            newHeaders.forEach(th => headerRow.appendChild(th));
            headerRow.appendChild(fixedEndHeader);
            
            subHeaderRow.innerHTML = '';
            fixedStartSubHeaders.forEach(th => subHeaderRow.appendChild(th));
            newSubHeaders.forEach(th => subHeaderRow.appendChild(th));
            subHeaderRow.appendChild(fixedEndSubHeader);
            
            // Reordenar celdas del cuerpo manteniendo las columnas fijas
            bodyRows.forEach(row => {
                const fixedStartCells = [...row.querySelectorAll('td')].slice(0, 3);
                const materiaCells = [...row.querySelectorAll('td')].slice(3, -1);
                const fixedEndCell = [...row.querySelectorAll('td')].slice(-1)[0];
                
                const newMateriaCells = [];
                materiasOrdenadas.forEach(materia => {
                    const index = originalHeaders.findIndex(th =>
                        th.querySelector('.nombre-materia').textContent.trim() === materia);
                    
                    if (index !== -1) {
                        newMateriaCells.push(...materiaCells.slice(index*4, index*4+4));
                    }
                });
                
                row.innerHTML = '';
                fixedStartCells.forEach(td => row.appendChild(td));
                newMateriaCells.forEach(td => row.appendChild(td));
                row.appendChild(fixedEndCell);
            });
            
            bootstrap.Modal.getInstance(document.getElementById('orderModal')).hide();
        }

        async function generatePDFPanelBlocks() {
            const nombreCurso = <?= json_encode($nombre_curso) ?>;
            const pdfContainer = document.createElement('div');
            pdfContainer.className = 'pdf-export';

            // Cabecera elegante
            const header = `
                <div style="font-family: Arial, sans-serif; text-align:center; margin-bottom:8px;">
                    <div style="font-size:16pt; font-weight:bold;">U.E. SIMÓN BOLÍVAR</div>
                    <div style="font-size:15pt; font-weight:700; color:#003366;">CENTRALIZADOR DE NOTAS</div>
                    <div style="font-size:11.5pt; margin-top:1px; margin-bottom:3px;">${nombreCurso}</div>
                    <div style="font-size:10.5pt; color:#555;">Año Escolar ${new Date().getFullYear()}</div>
                    <hr style="border-top:1.2px solid #003366; width:75%; margin:6px auto;">
                </div>
            `;

            // Clona y personaliza la tabla
            const originalTable = document.querySelector('.centralizador-table');
            const table = originalTable.cloneNode(true);

            table.style.margin = "0 auto";
            table.style.fontSize = "9pt";
            table.style.width = "99%";
            table.style.borderCollapse = "collapse";
            table.style.tableLayout = "fixed";

            // Celdas compactas y nítidas
            table.querySelectorAll('th, td').forEach(el => {
                el.style.padding = "3.3px 4.2px";
                el.style.border = "1.6px solid #bbb";
                el.style.fontSize = "9pt";
                el.style.textAlign = "center";
                el.style.wordBreak = "break-word";
            });

            // Encabezado por bloques
            table.querySelectorAll('th[colspan]').forEach(th => {
                th.style.background = "#e3ecfa";
                th.style.color = "#1e3d73";
                th.style.fontWeight = "bold";
                th.style.fontSize = "9.5pt";
                th.style.borderBottom = "2.3px solid #6699cc";
                th.style.letterSpacing = ".5px";
            });
            // Extras azul pálido
            table.querySelectorAll('.materia-extra').forEach(td => {
                td.style.background = "#e3f4ff";
                td.style.color = "#18809b";
                td.style.fontStyle = "italic";
            });
            // Promedios generales destacados
            table.querySelectorAll('.final-average').forEach(td => {
                td.style.background = "#f9edc1";
                td.style.fontWeight = "bold";
                td.style.color = "#ad5409";
                td.style.fontSize = "10pt";
            });

            // Rayado de filas
            [...table.tBodies[0].rows].forEach((row, i) => {
                if (i % 2 === 1) row.style.background = "#f7f8fd";
            });

            pdfContainer.innerHTML = header;
            pdfContainer.appendChild(table);
            document.body.appendChild(pdfContainer);

            // ---- PDF con máxima resolución ----
            const pdf = new jspdf.jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: 'a4',
                hotfixes: ["px_scaling"]
            });

            const options = {
                scale: 3, // MAX RESOLUCIÓN
                useCORS: true,
                logging: false,
                scrollY: 0,
                backgroundColor: "#fff"
            };

            const canvas = await html2canvas(pdfContainer, options);

            // Medidas ajustadas para hoja carta horizontal
            const pageWidth = 297;
            const xPosition = 7,
                yPosition = 9;
            const imgWidth = pageWidth - 2 * xPosition;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;

            pdf.addImage(canvas, 'PNG', xPosition, yPosition, imgWidth, imgHeight, undefined, 'FAST');

            document.body.removeChild(pdfContainer);
            pdf.save(`Centralizador - ${nombreCurso}.pdf`);
        }

        function showAnnualReport() {
            // Crear modal básico con spinner
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div class="modal fade" id="annualReportModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">Reporte Anual</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <p class="mt-2">Generando reporte...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal.querySelector('.modal'));
            bsModal.show();

            // Generar reporte directamente con los datos del DOM
            setTimeout(() => {
                try {
                    const reportContent = generateClientSideReport();
                    modal.querySelector('.modal-body').innerHTML = reportContent;
                } catch (error) {
                    modal.querySelector('.modal-body').innerHTML = `
                        <div class="alert alert-danger">
                            Error al generar el reporte: ${error.message}
                        </div>
                    `;
                }
            }, 500);

            // Limpiar al cerrar
            modal.addEventListener('hidden.bs.modal', () => {
                document.body.removeChild(modal);
            });
        }

        function generateClientSideReport() {
            // Clonar la tabla existente con validación
            const originalTable = document.querySelector('.centralizador-table');
            if (!originalTable) {
                throw new Error('No se encontró la tabla de datos');
            }
            const table = originalTable.cloneNode(true);
            
            
            // Aplicar estilos para el reporte y evitar solapamiento
            table.classList.add('table-bordered', 'table-sm', 'reporte-table');
            table.style.width = 'auto';
            table.style.minWidth = '100%';
            table.style.tableLayout = 'auto';
            
            // Convertir notas decimales a enteros
            table.querySelectorAll('td').forEach(td => {
                if (/^\d+\.\d+$/.test(td.textContent)) {
                    td.textContent = Math.round(parseFloat(td.textContent));
                }
            });

            // Crear contenedor para el reporte con estilos mejorados
            const container = document.createElement('div');
            container.style.overflowX = 'auto';
            container.style.maxHeight = '80vh';
            container.innerHTML = '<style>' +
                '.reporte-table {' +
                '   border-collapse: collapse;' +
                '   width: 100%;' +
                '   font-size: 8px;' +
                '   table-layout: fixed;' +
                '}' +
                '.reporte-table th[colspan] {' +
                '   max-width: 120px;' +
                '   overflow: hidden;' +
                '   text-overflow: ellipsis;' +
                '}' +
                '.nombre-materia {' +
                '   display: inline-block;' +
                '   max-width: 100%;' +
                '   white-space: nowrap;' +
                '   overflow: hidden;' +
                '   text-overflow: ellipsis;' +
                '}' +
                '.reporte-table th, .reporte-table td {' +
                '   padding: 2px 3px;' +
                '   border: 1px solid #dee2e6;' +
                '   white-space: nowrap;' +
                '   vertical-align: middle;' +
                '   text-align: center;' +
                '}' +
                '.reporte-table th {' +
                '   background-color: #f8f9fa;' +
                '   position: sticky;' +
                '   top: 0;' +
                '   z-index: 10;' +
                '   font-weight: bold;' +
                '}' +
                '.reporte-table tr:nth-child(even) {' +
                '   background-color: #f9f9f9;' +
                '}' +
                '.modal-body {' +
                '   overflow: auto;' +
                '   max-height: 80vh;' +
                '}' +
                '.table-responsive {' +
                '   width: 100%;' +
                '   overflow-x: auto;' +
                '}' +
                '@media print {' +
                '   @page { size: landscape; margin: 5mm; }' +
                '   body { margin: 0; padding: 0; }' +
                '   .split-container {' +
                '       display: flex;' +
                '       width: 100%;' +
                '   }' +
                '   .split-section {' +
                '       width: 50%;' +
                '       page-break-after: always;' +
                '   }' +
                '   .reporte-table {' +
                '       font-size: 7px !important;' +
                '       margin-bottom: 10mm;' +
                '   }' +
                '   .reporte-table th, .reporte-table td {' +
                '       padding: 1px 2px !important;' +
                '   }' +
                '   .header-section {' +
                '       text-align: center;' +
                '       margin-bottom: 5mm;' +
                '   }' +
                '   .footer-section {' +
                '       text-align: center;' +
                '       margin-top: 5mm;' +
                '       font-size: 8px;' +
                '   }' +
                '}' +
                '</style>' +
                '<h4 class="text-center mb-4">Reporte Anual</h4>' +
                '<div class="table-responsive">' +
                table.outerHTML.replace('centralizador-table', 'centralizador-table reporte-table') +
                '</div>' +
                '<div class="mt-4 text-muted text-center">' +
                'Generado el ' + new Date().toLocaleDateString() +
                '</div>';

            // Para secundaria: dividir en 2 hojas carta horizontales
            if (document.querySelector('.nivel-secundaria')) {
                const mitad = Math.ceil(materias.length / 2);
                const primeraMitad = table.rows[0].cells.slice(0, mitad * 4 + 1);
                const segundaMitad = table.rows[0].cells.slice(mitad * 4 + 1);
                
                container.innerHTML = `
                    <div class="header-section">
                        <h4>Reporte Anual - Primera Parte</h4>
                    </div>
                    <div class="table-responsive">
                        ${primeraMitad.outerHTML}
                    </div>
                    <div class="footer-section">
                        Generado el ${new Date().toLocaleDateString()}
                    </div>
                    
                    <div style="page-break-before: always;"></div>
                    
                    <div class="header-section">
                        <h4>Reporte Anual - Segunda Parte</h4>
                    </div>
                    <div class="table-responsive">
                        ${segundaMitad.outerHTML}
                    </div>
                    <div class="footer-section">
                        Generado el ${new Date().toLocaleDateString()}
                    </div>
                `;
            } else {
                container.innerHTML = `
                    <div class="header-section">
                        <h4>Reporte Anual</h4>
                    </div>
                    <div class="table-responsive">
                        ${table.outerHTML}
                    </div>
                    <div class="footer-section">
                        Generado el ${new Date().toLocaleDateString()}
                    </div>
                `;
            }
            
            return container.innerHTML;
        }

        // --- Modal detalle parciales al hacer clic en T1/T2/T3 ---
        (function() {
            var dataParciales = <?= json_encode($detalleParciales, JSON_UNESCAPED_UNICODE) ?>;
            var modalEl = document.getElementById('notaDetalleModal');
            if (!modalEl) return;
            var bsModal = new bootstrap.Modal(modalEl);
            var tableEl = document.querySelector('.centralizador-table');
            if (!tableEl) return;

            tableEl.addEventListener('click', function(e) {
                var td = e.target.closest('td.nota-detail-trigger');
                if (!td) return;

                var estId  = td.getAttribute('data-estudiante-id');
                var matId  = td.getAttribute('data-materia-id');
                var trim   = td.getAttribute('data-trimestre');
                var studentName = td.getAttribute('data-student') || '—';
                var materiaName = td.getAttribute('data-materia-name') || '—';

                document.getElementById('detalleEstudiante').textContent = studentName;
                document.getElementById('detalleMateria').textContent   = materiaName;
                document.getElementById('notaDetalleTitulo').textContent = 'Detalle Trimestre ' + trim;

                var info = dataParciales[estId] && dataParciales[estId][matId] && dataParciales[estId][matId][trim]
                         ? dataParciales[estId][matId][trim]
                         : null;

                var headRow   = document.getElementById('detalleParcialesHead');
                var bodyRow   = document.getElementById('detalleParcialesBody');
                headRow.innerHTML = '';
                bodyRow.innerHTML = '';

                if (info && info.parciales && info.parciales.length > 0) {
                    for (var i = 0; i < info.parciales.length; i++) {
                        headRow.innerHTML += '<th>P' + (i + 1) + '</th>';
                        bodyRow.innerHTML += '<td>' + parseFloat(info.parciales[i]).toFixed(0) + '</td>';
                    }
                    var sum = 0;
                    for (var j = 0; j < info.parciales.length; j++) sum += parseFloat(info.parciales[j]);
                    var avg = sum / info.parciales.length;
                    headRow.innerHTML += '<th class="col-prom">Prom</th>';
                    bodyRow.innerHTML += '<td class="col-prom">' + avg.toFixed(2) + '</td>';

                    var autoVal = (info.autoevaluacion !== null && info.autoevaluacion !== '' && info.autoevaluacion !== undefined) ? parseFloat(info.autoevaluacion) : 0;
                    var extraVal = (info.nota_extra !== null && info.nota_extra !== '' && info.nota_extra !== undefined) ? parseFloat(info.nota_extra) : 0;

                    headRow.innerHTML += '<th class="col-auto">Autoev</th>';
                    bodyRow.innerHTML += '<td class="col-auto">' + (autoVal ? autoVal : '-') + '</td>';

                    headRow.innerHTML += '<th class="col-extra">Extra</th>';
                    bodyRow.innerHTML += '<td class="col-extra">' + (extraVal ? extraVal : '-') + '</td>';

                    var total = avg + autoVal + extraVal;
                    headRow.innerHTML += '<th class="col-total">Total</th>';
                    bodyRow.innerHTML += '<td class="col-total">' + total.toFixed(2) + '</td>';
                } else {
                    headRow.innerHTML = '<th colspan="4">Sin datos</th>';
                    bodyRow.innerHTML = '<td colspan="4">—</td>';
                }

                bsModal.show();
            });
        })();
    </script>

</body>

</html>
