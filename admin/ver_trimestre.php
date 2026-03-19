<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2])) {
    header('Location: ../index.php');
    exit();
}

$id_curso = $_GET['id_curso'] ?? 0;
$trimestre = $_GET['trimestre'] ?? 1;

$conn = (new Database())->connect();

$stmt_gestion = $conn->query("SELECT anio_escolar FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
$gestionConfigurada = $stmt_gestion->fetchColumn();
$gestionConfigurada = $gestionConfigurada ? trim((string)$gestionConfigurada) : '';
$gestionActual = $gestionConfigurada !== '' ? $gestionConfigurada : date('Y');
$gestionAlternativa = null;
if (preg_match('/\b(20\d{2})\b/', $gestionActual, $matches)) {
    $gestionAlternativa = $matches[1];
}

// 1. Obtener información del curso
$stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
$stmt_curso->execute([$id_curso]);
$curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);
$nombre_curso = $curso_info['nivel'] . ' ' . $curso_info['curso'] . ' "' . $curso_info['paralelo'] . '"';

// 2. Obtener estudiantes ordenados alfabéticamente
$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, apellido_paterno, apellido_materno, nombres 
    FROM estudiantes 
    WHERE id_curso = ? 
    ORDER BY apellido_paterno, apellido_materno, nombres
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// 3. Clasificación de materias
$stmt_materias = $conn->prepare("
    SELECT 
        m.id_materia,
        m.nombre_materia,
        m.es_extra,
        m.es_submateria,
        m.materia_padre_id,
        CONCAT(COALESCE(p.nombres, ''), CASE WHEN p.apellidos IS NOT NULL AND p.apellidos <> '' THEN ' ' ELSE '' END, COALESCE(p.apellidos, '')) AS nombre_profesor
    FROM cursos_materias cm 
    JOIN materias m ON cm.id_materia = m.id_materia 
    LEFT JOIN profesores_materias_cursos pmc ON cm.id_curso_materia = pmc.id_curso_materia
    LEFT JOIN personal p ON pmc.id_personal = p.id_personal
    WHERE cm.id_curso = ?
");
$stmt_materias->execute([$id_curso]);
$todas_materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

$materias_padre = $materias_extra = $materias_hijas = [];
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

foreach ($materias_hijas as $hija) {
    if (isset($materias_padre[$hija['materia_padre_id']])) {
        $materias_padre[$hija['materia_padre_id']]['hijas'][] = $hija;
    }
}

$materias_padre_simples = [];
$materias_padre_con_hijas = [];
foreach ($materias_padre as $padre) {
    empty($padre['hijas']) ? $materias_padre_simples[] = $padre : $materias_padre_con_hijas[] = $padre;
}

// 4. Orden final de visualización
$materias = array_merge(
    $materias_padre_simples,
    $materias_extra,
    array_reduce($materias_padre_con_hijas, function ($carry, $padre) {
        return array_merge($carry, [$padre], $padre['hijas']);
    }, [])
);

// 5. Calificaciones parciales y promedios del trimestre
$calificacionesParciales = [];
$promediosMateriaTrimestre = [];
foreach ($estudiantes as $estudiante) {
    foreach ($todas_materias as $materia) {
        for ($parcial = 1; $parcial <= 3; $parcial++) {
            $calificacionesParciales[$estudiante['id_estudiante']][$materia['id_materia']][$parcial] = '';
        }
        $promediosMateriaTrimestre[$estudiante['id_estudiante']][$materia['id_materia']] = '';
    }
}

$sqlCalificaciones = "SELECT cp.id_estudiante, cp.id_materia, pe.parcial, cp.calificacion
                      FROM calificaciones_parciales cp
                      INNER JOIN periodos_evaluacion pe ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
                      INNER JOIN estudiantes e ON e.id_estudiante = cp.id_estudiante
                      INNER JOIN cursos_materias cm ON cm.id_materia = cp.id_materia
                      WHERE e.id_curso = ?
                        AND cm.id_curso = ?
                        AND pe.trimestre = ?
                        AND (pe.gestion = ?";
$paramsCalificaciones = [$id_curso, $id_curso, $trimestre, $gestionActual];
if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
    $sqlCalificaciones .= " OR pe.gestion = ?";
    $paramsCalificaciones[] = $gestionAlternativa;
}
$sqlCalificaciones .= ")";

$stmt_calificaciones = $conn->prepare($sqlCalificaciones);
$stmt_calificaciones->execute($paramsCalificaciones);
foreach ($stmt_calificaciones->fetchAll(PDO::FETCH_ASSOC) as $filaCalificacion) {
    if ($filaCalificacion['calificacion'] === null || $filaCalificacion['calificacion'] === '') {
        continue;
    }
    $idEstudiante = (int)$filaCalificacion['id_estudiante'];
    $idMateria = (int)$filaCalificacion['id_materia'];
    $parcial = (int)$filaCalificacion['parcial'];
    $calificacionesParciales[$idEstudiante][$idMateria][$parcial] = number_format((float)$filaCalificacion['calificacion'], 2);
}

foreach ($estudiantes as $estudiante) {
    foreach ($todas_materias as $materia) {
        $parcialesMateria = $calificacionesParciales[$estudiante['id_estudiante']][$materia['id_materia']] ?? [];
        $parcialesValidos = array_filter($parcialesMateria, function ($valor) {
            return $valor !== '' && $valor !== null;
        });
        if (!empty($parcialesValidos)) {
            $promediosMateriaTrimestre[$estudiante['id_estudiante']][$materia['id_materia']] = number_format(array_sum(array_map('floatval', $parcialesValidos)) / count($parcialesValidos), 2);
        }
    }
}

foreach ($estudiantes as $estudiante) {
    foreach ($materias_padre_con_hijas as $padre) {
        for ($parcial = 1; $parcial <= 3; $parcial++) {
            $suma = 0;
            $cont = 0;
            foreach ($padre['hijas'] as $hija) {
                $nota = $calificacionesParciales[$estudiante['id_estudiante']][$hija['id_materia']][$parcial] ?? '';
                if ($nota !== '') {
                    $suma += floatval($nota);
                    $cont++;
                }
            }
            $calificacionesParciales[$estudiante['id_estudiante']][$padre['id_materia']][$parcial] = $cont > 0 ? number_format($suma / $cont, 2) : '';
        }

        $parcialesPadre = $calificacionesParciales[$estudiante['id_estudiante']][$padre['id_materia']] ?? [];
        $parcialesValidosPadre = array_filter($parcialesPadre, function ($valor) {
            return $valor !== '' && $valor !== null;
        });
        $promediosMateriaTrimestre[$estudiante['id_estudiante']][$padre['id_materia']] = !empty($parcialesValidosPadre)
            ? number_format(array_sum(array_map('floatval', $parcialesValidosPadre)) / count($parcialesValidosPadre), 2)
            : '';
    }
}

$promedios_trimestre = [];
foreach ($estudiantes as $estudiante) {
    $suma = $contador = 0;
    foreach ($materias as $mat) {
        if ($mat['es_extra'] == 1 || isset($mat['materia_padre_id']))
            continue;
        $nota = $promediosMateriaTrimestre[$estudiante['id_estudiante']][$mat['id_materia']] ?? '';
        if ($nota !== '') {
            $suma += floatval($nota);
            $contador++;
        }
    }
    $promedios_trimestre[$estudiante['id_estudiante']] = $contador > 0 ? number_format($suma / $contador, 2) : '-';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista Trimestral - <?= htmlspecialchars($nombre_curso) ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
        }

        body {
            background: #f8f9fa;
            margin-left: var(--sidebar-width);
            transition: background-color 0.25s ease, color 0.25s ease;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        h3,
        .fw-bold {
            font-size: 1.35rem !important;
            font-weight: 700 !important;
            letter-spacing: -0.01em;
        }

        .trimester-table th,
        .trimester-table td,
        .badge,
        .btn {
            font-size: 0.92rem;
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #2c3e50;
            padding: 20px;
            z-index: 1000;
        }

        .main-content {
            padding: 20px;
        }

        .theme-toggle-btn {
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-weight: 600;
        }

        .header-main {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.85rem 0.15rem;
            margin-bottom: 0;
        }

        .header-title-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-height: 44px;
        }

        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            justify-content: flex-end;
            max-width: 100%;
        }

        .header-controls {
            margin-bottom: 1rem;
        }

        .selector-card {
            margin-bottom: 1rem !important;
        }

        .btn {
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.01em;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
            transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.14);
        }

        .btn-outline-secondary,
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

        body.dark-mode {
            background: #0f172a;
            color: #e2e8f0;
        }

        body.dark-mode .table-responsive,
        body.dark-mode .card,
        body.dark-mode .card-body,
        body.dark-mode .header-controls {
            background: #111827 !important;
            color: #e5e7eb;
            border-color: #1f2937 !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.28);
        }

        body.dark-mode .trimester-table thead th,
        body.dark-mode .trim-header-top th,
        body.dark-mode .trim-header-sub th,
        body.dark-mode .trim-header-top .student-name,
        body.dark-mode .trim-header-sub .student-name,
        body.dark-mode .trim-header-top .number-col,
        body.dark-mode .trim-header-sub .number-col {
            background: #1f2937 !important;
            color: #e5e7eb !important;
        }

        body.dark-mode .trimester-table tbody tr:nth-child(odd) td,
        body.dark-mode .table tbody .number-col,
        body.dark-mode .table tbody .student-name {
            background: #0f172a !important;
            color: #dbeafe;
        }

        body.dark-mode .trimester-table tbody tr:nth-child(even) td,
        body.dark-mode .table tbody tr:nth-child(even) .number-col,
        body.dark-mode .table tbody tr:nth-child(even) .student-name {
            background: #111827 !important;
            color: #dbeafe;
        }

        body.dark-mode .trimester-table tbody tr:hover td {
            background: #1d4ed8 !important;
            color: #eff6ff !important;
        }

        body.dark-mode .trimester-table tbody tr:hover .number-col,
        body.dark-mode .trimester-table tbody tr:hover .student-name {
            background: #2563eb !important;
            color: #ffffff !important;
            box-shadow: 4px 0 12px rgba(37, 99, 235, 0.35);
        }

        body.dark-mode .average-col {
            background: #1e293b !important;
            color: #e2e8f0;
        }

        body.dark-mode .padre-th {
            background: #1f2937 !important;
            color: #f8fafc !important;
        }

        body.dark-mode .hija-th {
            background: #162032 !important;
            color: #94a3b8 !important;
        }

        body.dark-mode .extra-th {
            background: #172554 !important;
            color: #bfdbfe !important;
        }

        body.dark-mode .table td.nota-baja,
        body.dark-mode .table td.average-col.nota-baja {
            background: #3b0d19 !important;
            color: #fda4af !important;
        }

        body.dark-mode .student-name,
        body.dark-mode .number-col,
        body.dark-mode .trimester-table td,
        body.dark-mode .trimester-table th,
        body.dark-mode .table-responsive,
        body.dark-mode .card {
            border-color: #334155 !important;
        }

        body.dark-mode .btn-outline-secondary,
        body.dark-mode .btn-outline-primary {
            color: #dbeafe;
            border-color: #41566f;
            background: #182234 !important;
        }

        body.dark-mode .badge.bg-primary {
            background: #1d4ed8 !important;
        }

        body.dark-mode .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-color: #1e40af;
        }

        body.dark-mode .btn-success {
            background: linear-gradient(135deg, #15803d, #166534);
            border-color: #166534;
        }

        .trimester-table {
            min-width: max-content;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .trimester-table th,
        .trimester-table td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .number-col {
            min-width: 56px;
            width: 56px;
            text-align: center;
            position: sticky;
            left: 0;
            z-index: 6;
            background: #ffffff;
            box-shadow: 1px 0 0 #dee2e6;
        }

        .student-name {
            min-width: 220px;
            background: #fff;
            position: sticky;
            left: 56px;
            z-index: 6;
            box-shadow: 4px 0 12px rgba(15, 23, 42, 0.08);
        }

        .table-responsive {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            overflow: auto;
            max-height: calc(100vh - 220px);
            scrollbar-gutter: stable both-edges;
            position: relative;
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

        .trim-header-top th {
            white-space: nowrap;
            text-align: center;
            vertical-align: middle;
            position: sticky;
            top: 0;
            z-index: 4;
            background: #e9ecef !important;
        }

        .trim-header-sub th {
            white-space: nowrap;
            text-align: center;
            font-size: 0.82rem;
            position: sticky;
            top: 45px;
            z-index: 4;
            background: #f8f9fa !important;
        }

        .trim-header-top .number-col,
        .trim-header-sub .number-col {
            z-index: 8;
        }

        .trim-header-top .student-name,
        .trim-header-sub .student-name {
            z-index: 8;
            background: #e9ecef !important;
        }

        .partial-col {
            min-width: 72px;
            text-align: center;
        }

        .subject-heading {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            max-width: 100%;
            cursor: help;
        }

        .subject-heading .bi-info-circle {
            font-size: 0.82rem;
            opacity: 0.78;
        }

        .average-col {
            min-width: 82px;
            text-align: center;
            background: #f4f6fb;
            font-weight: 600;
        }

        .table td.average-col.nota-baja {
            background: #fff5f6 !important;
        }

        .padre-th {
            background: #e9ecef !important;
            color: #2c3e50 !important;
            font-weight: 600;
        }

        .hija-th {
            background: #f8f9fa !important;
            color: #6c757d !important;
            font-style: italic;
        }

        .extra-th {
            background: #e6f4ff !important;
            color: #0d6efd !important;
        }

        .table td.nota-baja {
            color: #dc3545 !important;
            font-weight: 600 !important;
        }

        .trimester-table tbody tr {
            transition: background-color 0.18s ease, box-shadow 0.18s ease;
        }

        .trimester-table tbody tr:nth-child(odd) td {
            background: #ffffff;
        }

        .trimester-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .trimester-table tbody tr {
            border-bottom: 2px solid #e5e7eb;
        }

        .trimester-table tbody tr:hover td {
            background: #bfdbfe !important;
            color: #0f172a !important;
        }

        .trimester-table tbody tr:hover .number-col,
        .trimester-table tbody tr:hover .student-name {
            background: #93c5fd !important;
            color: #0b2545 !important;
            box-shadow: 4px 0 12px rgba(59, 130, 246, 0.22);
        }

        .trimester-table tbody tr:focus,
        .trimester-table tbody tr:active,
        .trimester-table tbody tr td:focus,
        .trimester-table tbody tr td:active {
            outline: none !important;
            box-shadow: none !important;
        }

        .table tbody .number-col,
        .table tbody .student-name {
            background: #ffffff !important;
        }

        .table tbody tr:nth-child(even) .number-col,
        .table tbody tr:nth-child(even) .student-name {
            background: #f8fafc !important;
        }

        .trimester-table tbody .student-name {
            font-weight: 600;
            border-right: 2px solid #dbe4f0;
        }

        .trimester-table tbody .number-col {
            color: #475569;
            font-weight: 700;
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
        }

        body.dark-mode {
            background: #0b1220 !important;
            color: #e5eefb !important;
        }

        body.dark-mode .main-content,
        body.dark-mode .header-controls,
        body.dark-mode .card,
        body.dark-mode .card-body,
        body.dark-mode .table-responsive {
            background: #111827 !important;
            color: #e5eefb !important;
            border-color: #243244 !important;
        }

        body.dark-mode .table-responsive::after {
            background: linear-gradient(to left, rgba(17, 24, 39, 0.98), rgba(17, 24, 39, 0)) !important;
        }

        body.dark-mode .trimester-table td,
        body.dark-mode .trimester-table th,
        body.dark-mode .student-name,
        body.dark-mode .number-col {
            border-color: #243244 !important;
        }

        body.dark-mode .trim-header-top th,
        body.dark-mode .trim-header-sub th,
        body.dark-mode .trim-header-top .number-col,
        body.dark-mode .trim-header-top .student-name,
        body.dark-mode .trim-header-sub .number-col,
        body.dark-mode .trim-header-sub .student-name {
            background: #1b2535 !important;
            color: #f8fbff !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.35) !important;
        }

        body.dark-mode .trimester-table tbody tr:nth-child(odd) td,
        body.dark-mode .table tbody .number-col,
        body.dark-mode .table tbody .student-name {
            background: #0f172a !important;
            color: #dbeafe !important;
        }

        body.dark-mode .trimester-table tbody tr:nth-child(even) td,
        body.dark-mode .table tbody tr:nth-child(even) .number-col,
        body.dark-mode .table tbody tr:nth-child(even) .student-name {
            background: #131d31 !important;
            color: #dbeafe !important;
        }

        body.dark-mode .trimester-table tbody tr:hover td {
            background: #1e40af !important;
            color: #eff6ff !important;
        }

        body.dark-mode .trimester-table tbody tr:hover .number-col,
        body.dark-mode .trimester-table tbody tr:hover .student-name {
            background: #2563eb !important;
            color: #ffffff !important;
            box-shadow: 4px 0 12px rgba(37, 99, 235, 0.35) !important;
        }

        body.dark-mode .average-col {
            background: #1c2a3d !important;
            color: #e2e8f0 !important;
        }

        body.dark-mode .padre-th {
            background: #223047 !important;
            color: #f8fafc !important;
        }

        body.dark-mode .hija-th {
            background: #162235 !important;
            color: #93a8c4 !important;
        }

        body.dark-mode .extra-th {
            background: #17304f !important;
            color: #bfdbfe !important;
        }

        body.dark-mode .table td.nota-baja,
        body.dark-mode .table td.average-col.nota-baja {
            background: #3b1220 !important;
            color: #fda4af !important;
        }

        body.dark-mode .btn-outline-secondary,
        body.dark-mode .btn-outline-primary {
            color: #dbeafe !important;
            border-color: #41566f !important;
            background: #182234 !important;
        }

        body.dark-mode .badge.bg-primary {
            background: #2563eb !important;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
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

            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl, {
                    boundary: document.body,
                    html: false,
                    placement: 'top'
                });
            });

            document.getElementById('pdfBtn').addEventListener('click', function() {
                // Verificar que jspdf esté cargado
                if (typeof jspdf === 'undefined') {
                    console.error('jsPDF no está cargado');
                    return;
                }

                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({
                    orientation: 'landscape',
                    unit: 'mm',
                    format: 'letter'
                });

                // Encabezado
                pdf.setFontSize(14);
                pdf.setTextColor(44, 62, 80);
                pdf.text('U.E. SIMÓN BOLÍVAR', pdf.internal.pageSize.getWidth()/2, 15, {align: 'center'});
                
                pdf.setFontSize(12);
                pdf.setTextColor(30, 61, 115);
                pdf.text('<?= htmlspecialchars($nombre_curso) ?>', pdf.internal.pageSize.getWidth()/2, 20, {align: 'center'});
                
                pdf.setFontSize(10);
                pdf.setTextColor(102, 102, 102);
                pdf.text(`Trimestre <?= $trimestre ?> - ${new Date().getFullYear()}`, pdf.internal.pageSize.getWidth()/2, 25, {align: 'center'});

                // Preparar datos para la tabla
                const headers = [
                    {title: '#', dataKey: 'index'},
                    {title: 'Estudiante', dataKey: 'estudiante'}
                ];

                // Agregar encabezados de materias
                <?php foreach($materias as $mat): ?>
                    headers.push({
                        title: '<?= addslashes($mat['nombre_materia']) ?> P1',
                        dataKey: 'materia_<?= $mat['id_materia'] ?>_p1'
                    });
                    headers.push({
                        title: '<?= addslashes($mat['nombre_materia']) ?> P2',
                        dataKey: 'materia_<?= $mat['id_materia'] ?>_p2'
                    });
                    headers.push({
                        title: '<?= addslashes($mat['nombre_materia']) ?> P3',
                        dataKey: 'materia_<?= $mat['id_materia'] ?>_p3'
                    });
                    headers.push({
                        title: '<?= addslashes($mat['nombre_materia']) ?> Prom.',
                        dataKey: 'materia_<?= $mat['id_materia'] ?>_prom'
                    });
                <?php endforeach; ?>
                
                headers.push({title: 'Promedio', dataKey: 'promedio'});

                // Preparar datos de estudiantes
                const body = [];
                <?php foreach($estudiantes as $i => $est): ?>
                    body.push({
                        index: <?= $i + 1 ?>,
                        estudiante: '<?= addslashes(strtoupper($est['apellido_paterno'] . ' ' . $est['apellido_materno'] . ', ' . $est['nombres'])) ?>'
                    <?php foreach($materias as $mat): ?>,
                        'materia_<?= $mat['id_materia'] ?>_p1': '<?= $calificacionesParciales[$est['id_estudiante']][$mat['id_materia']][1] ?? '--' ?>',
                        'materia_<?= $mat['id_materia'] ?>_p2': '<?= $calificacionesParciales[$est['id_estudiante']][$mat['id_materia']][2] ?? '--' ?>',
                        'materia_<?= $mat['id_materia'] ?>_p3': '<?= $calificacionesParciales[$est['id_estudiante']][$mat['id_materia']][3] ?? '--' ?>',
                        'materia_<?= $mat['id_materia'] ?>_prom': '<?= $promediosMateriaTrimestre[$est['id_estudiante']][$mat['id_materia']] ?? '--' ?>'
                    <?php endforeach; ?>,
                        promedio: '<?= $promedios_trimestre[$est['id_estudiante']] ?>'
                    });
                    
                <?php endforeach; ?>

                // Configuración de la tabla
                pdf.autoTable({
                    head: [headers.map(h => h.title)],
                    body: body.map(row => headers.map(h => row[h.dataKey])),
                    startY: 30,
                    styles: {
                        fontSize: 8,
                        cellPadding: 1,
                        overflow: 'linebreak'
                    },
                    columnStyles: {
                        0: {cellWidth: 8}, // Columna #
                        1: {cellWidth: 40}, // Columna Estudiante
                        [headers.length - 1]: {cellWidth: 15} // Columna Promedio
                    },
                    didParseCell: (data) => {
                        // Rotar encabezados de materias
                        if (data.section === 'head' && data.column.index > 1 && data.column.index < headers.length - 1) {
                            data.cell.styles.fontStyle = 'bold';
                            data.cell.styles.textColor = [44, 62, 80];
                            data.cell.styles.halign = 'center';
                            data.cell.styles.valign = 'middle';
                            data.cell.text = [data.cell.text[0].split('').join('\n')];
                            data.cell.styles.cellWidth = 8;
                        }
                        
                        // Resaltar notas bajas
                        if (data.section === 'body' && !isNaN(parseFloat(data.cell.text[0])) && parseFloat(data.cell.text[0]) < 51) {
                            data.cell.styles.textColor = [220, 53, 69];
                            data.cell.styles.fontStyle = 'bold';
                        }
                    }
                });

                pdf.save(`Centralizador-<?= htmlspecialchars($nombre_curso) ?>-T<?= $trimestre ?>.pdf`);
            });
        });
    </script>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar text-white no-print">
        <?php include '../includes/sidebar.php'; ?>
    </div>

    <!-- Contenido Principal -->
    <div class="main-content">
        <!-- Header -->
        <div class="header-controls no-print">
            <div class="header-main">
                <div class="header-title-group">
                    <a href="ver_curso.php?id=<?= $id_curso ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                    <h3 class="mb-0"><?= htmlspecialchars($nombre_curso) ?></h3>
                </div>
                <div class="header-actions">
                    <button type="button" id="themeToggle" class="btn btn-outline-secondary btn-sm theme-toggle-btn">
                        <i class="bi bi-moon-stars"></i>
                        <span>Vista nocturna</span>
                    </button>
                    <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-printer"></i> Imprimir
                    </button>
                    <button id="pdfBtn" class="btn btn-primary btn-sm">
                        <i class="bi bi-file-earmark-pdf"></i> PDF
                    </button>
                    <a href="exportar_excel.php?id=<?= $id_curso ?>&trimestre=<?= $trimestre ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-file-excel"></i> Excel
                    </a>
                </div>
            </div>
        </div>

        <!-- Selector de Trimestre -->
        <div class="card mb-4 shadow-sm no-print selector-card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <span class="fw-bold">Trimestre:</span>
                    <div class="btn-group">
                        <?php for ($t = 1; $t <= 3; $t++): ?>
                            <a href="?id_curso=<?= $id_curso ?>&trimestre=<?= $t ?>"
                                class="btn <?= $t == $trimestre ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm">
                                <?= $t ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <span class="badge bg-primary">Trimestre <?= $trimestre ?></span>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="table-responsive">
            <table class="table table-bordered align-middle trimester-table">
                <thead class="table-light">
                    <tr class="trim-header-top">
                        <th rowspan="2" class="number-col">#</th>
                        <th rowspan="2" class="student-name">Estudiante</th>
                        <?php foreach ($materias as $mat): ?>
                            <?php
                            $clase = '';
                            if ($mat['es_extra'] == 1)
                                $clase = 'extra-th';
                            elseif (isset($mat['materia_padre_id']))
                                $clase = 'hija-th';
                            elseif (!empty($mat['hijas']))
                                $clase = 'padre-th';
                            $profesorMateria = trim((string)($mat['nombre_profesor'] ?? ''));
                            $textoProfesor = $profesorMateria !== '' ? $profesorMateria : 'Profesor no asignado';
                            ?>
                            <th colspan="4" class="<?= $clase ?>">
                                <span class="subject-heading"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Profesor: <?= htmlspecialchars($textoProfesor, ENT_QUOTES) ?>">
                                    <span><?= htmlspecialchars($mat['nombre_materia']) ?></span>
                                    <i class="bi bi-info-circle"></i>
                                </span>
                                <?= $mat['es_extra'] ? '<small>(Extra)</small>' : '' ?>
                            </th>
                        <?php endforeach; ?>
                        <th rowspan="2">Promedio</th>
                    </tr>
                    <tr class="trim-header-sub">
                        <?php foreach ($materias as $mat): ?>
                            <th class="partial-col">P1</th>
                            <th class="partial-col">P2</th>
                            <th class="partial-col">P3</th>
                            <th class="average-col">Prom.</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $contador = 1; ?>
                    <?php foreach ($estudiantes as $estudiante): ?>
                        <tr>
                            <td class="number-col"><?= $contador++ ?></td>
                            <td class="student-name">
                                <?= htmlspecialchars(strtoupper(
                                    $estudiante['apellido_paterno'] . ' ' .
                                    $estudiante['apellido_materno'] . ', ' .
                                    $estudiante['nombres']
                                )) ?>
                            </td>
                            <?php foreach ($materias as $mat): ?>
                                <?php
                                $clase = '';
                                if ($mat['es_extra'] == 1)
                                    $clase = 'extra-td';
                                elseif (isset($mat['materia_padre_id']))
                                    $clase = 'hija-td';
                                $p1 = $calificacionesParciales[$estudiante['id_estudiante']][$mat['id_materia']][1] ?? '';
                                $p2 = $calificacionesParciales[$estudiante['id_estudiante']][$mat['id_materia']][2] ?? '';
                                $p3 = $calificacionesParciales[$estudiante['id_estudiante']][$mat['id_materia']][3] ?? '';
                                $promedioMateria = $promediosMateriaTrimestre[$estudiante['id_estudiante']][$mat['id_materia']] ?? '';
                                ?>
                                <td class="partial-col <?= $clase ?> <?= (is_numeric($p1) && floatval($p1) < 51) ? 'nota-baja' : '' ?>"><?= $p1 !== '' ? $p1 : '--' ?></td>
                                <td class="partial-col <?= $clase ?> <?= (is_numeric($p2) && floatval($p2) < 51) ? 'nota-baja' : '' ?>"><?= $p2 !== '' ? $p2 : '--' ?></td>
                                <td class="partial-col <?= $clase ?> <?= (is_numeric($p3) && floatval($p3) < 51) ? 'nota-baja' : '' ?>"><?= $p3 !== '' ? $p3 : '--' ?></td>
                                <td class="average-col <?= $clase ?> <?= (is_numeric($promedioMateria) && floatval($promedioMateria) < 51) ? 'nota-baja' : '' ?>"><?= $promedioMateria !== '' ? $promedioMateria : '--' ?></td>
                            <?php endforeach; ?>
                            <td class="fw-bold"><?= $promedios_trimestre[$estudiante['id_estudiante']] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
