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

if (!function_exists('obtener_sigla_materia')) {
    function obtener_sigla_materia(string $nombre): string
    {
        $recortado = trim($nombre);
        if ($recortado === '') {
            return 'CB';
        }
        $sigla = mb_strtoupper(mb_substr($recortado, 0, 2));
        $sigla = preg_replace('/[^A-Z0-9]/u', '', $sigla);
        return $sigla !== '' ? $sigla : 'CB';
    }
}

if (!function_exists('determinar_prioridad_gestion')) {
    function determinar_prioridad_gestion(?string $gestionValor, string $gestionActual, ?string $gestionAlternativa): int
    {
        if ($gestionValor === null) {
            return 2;
        }
        $gestionLimpia = trim($gestionValor);
        if ($gestionLimpia === $gestionActual) {
            return 4;
        }
        if ($gestionAlternativa !== null && $gestionLimpia === $gestionAlternativa) {
            return 3;
        }
        if ($gestionLimpia === '') {
            return 2;
        }
        return 1;
    }
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

$gestionesConsulta = [$gestionActual];
if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
    $gestionesConsulta[] = $gestionAlternativa;
}

$periodosIdsTrimestre = [1 => null, 2 => null, 3 => null];
if (!empty($gestionesConsulta)) {
    $placeholdersPeriodos = implode(',', array_fill(0, count($gestionesConsulta), '?'));
    $sqlPeriodosTrimestre = "SELECT id_periodo_evaluacion, parcial, gestion
                              FROM periodos_evaluacion
                              WHERE trimestre = ?
                                AND gestion IN ($placeholdersPeriodos)";
    $paramsPeriodosTrimestre = array_merge([(int)$trimestre], $gestionesConsulta);
    try {
        $stmtPeriodosTrimestre = $conn->prepare($sqlPeriodosTrimestre);
        $stmtPeriodosTrimestre->execute($paramsPeriodosTrimestre);
        foreach ($stmtPeriodosTrimestre->fetchAll(PDO::FETCH_ASSOC) as $filaPeriodo) {
            $parcialFila = isset($filaPeriodo['parcial']) ? (int)$filaPeriodo['parcial'] : 0;
            if ($parcialFila < 1 || $parcialFila > 3) {
                continue;
            }
            $idPeriodoFila = isset($filaPeriodo['id_periodo_evaluacion']) ? (int)$filaPeriodo['id_periodo_evaluacion'] : null;
            if ($idPeriodoFila === null) {
                continue;
            }
            $gestionFila = isset($filaPeriodo['gestion']) ? trim((string)$filaPeriodo['gestion']) : '';
            if ($gestionFila === $gestionActual) {
                $periodosIdsTrimestre[$parcialFila] = $idPeriodoFila;
            } elseif ($periodosIdsTrimestre[$parcialFila] === null) {
                $periodosIdsTrimestre[$parcialFila] = $idPeriodoFila;
            }
        }
    } catch (PDOException $e) {
        $periodosIdsTrimestre = [1 => null, 2 => null, 3 => null];
    }
}

$puedeEditarParciales = isset($_SESSION['user_role']) && (int)$_SESSION['user_role'] === 1;

$materiasPorId = [];
foreach ($todas_materias as $materia) {
    $materiasPorId[(int)$materia['id_materia']] = $materia;
}

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

$materiasBonusInfo = [];
$idsMaterias = array_column($todas_materias, 'id_materia');

if (!empty($idsMaterias)) {
    $placeholders = implode(',', array_fill(0, count($idsMaterias), '?'));
    $sqlComplementarias = "SELECT id_materia_principal, id_materia_complementaria, porcentaje_transferencia, gestion
                            FROM materias_complementarias
                            WHERE id_materia_principal IN ($placeholders)";
    $paramsComplementarias = array_map('intval', $idsMaterias);

    $stmtComplementarias = $conn->prepare($sqlComplementarias);
    $stmtComplementarias->execute($paramsComplementarias);

    foreach ($stmtComplementarias->fetchAll(PDO::FETCH_ASSOC) as $relacion) {
        $idPrincipal = (int)$relacion['id_materia_principal'];
        $idComplementaria = (int)$relacion['id_materia_complementaria'];
        $prioridad = determinar_prioridad_gestion($relacion['gestion'] ?? null, $gestionActual, $gestionAlternativa);
        if ($prioridad <= 0) {
            continue;
        }

        if (!isset($materiasBonusInfo[$idPrincipal]) || $prioridad > $materiasBonusInfo[$idPrincipal]['prioridad']) {
            $nombreComplementaria = $materiasPorId[$idComplementaria]['nombre_materia'] ?? '';
            $sigla = $nombreComplementaria !== '' ? obtener_sigla_materia($nombreComplementaria) : 'CB';
            $materiasBonusInfo[$idPrincipal] = [
                'id_complementaria' => $idComplementaria,
                'porcentaje' => (float)$relacion['porcentaje_transferencia'],
                'label' => 'P-' . $sigla,
                'nombre_complementaria' => $nombreComplementaria
            ];
        }
    }
}

$datosTrimestrales = [];
$prioridadTrimestral = [];
if (!empty($idsMaterias)) {
    $placeholdersTrimestrales = implode(',', array_fill(0, count($idsMaterias), '?'));
    $sqlTrimestral = "SELECT id_estudiante, id_materia, autoevaluacion, nota_extra, gestion
                      FROM calificaciones_trimestrales
                      WHERE trimestre = ?
                        AND id_materia IN ($placeholdersTrimestrales)";
    $paramsTrimestral = array_merge([(int)$trimestre], array_map('intval', $idsMaterias));

    $stmtTrimestral = $conn->prepare($sqlTrimestral);
    $stmtTrimestral->execute($paramsTrimestral);

    foreach ($stmtTrimestral->fetchAll(PDO::FETCH_ASSOC) as $filaTrimestral) {
        $idEstTr = (int)$filaTrimestral['id_estudiante'];
        $idMatTr = (int)$filaTrimestral['id_materia'];
        $prioridad = determinar_prioridad_gestion($filaTrimestral['gestion'] ?? null, $gestionActual, $gestionAlternativa);

        if (!isset($prioridadTrimestral[$idEstTr][$idMatTr]) || $prioridad > $prioridadTrimestral[$idEstTr][$idMatTr]) {
            $prioridadTrimestral[$idEstTr][$idMatTr] = $prioridad;
            $autoeval = $filaTrimestral['autoevaluacion'];
            $extra = $filaTrimestral['nota_extra'];
            $datosTrimestrales[$idEstTr][$idMatTr] = [
                'autoevaluacion' => ($autoeval !== null && $autoeval !== '') ? (float)$autoeval : null,
                'nota_extra' => ($extra !== null && $extra !== '') ? (float)$extra : null
            ];
        }
    }
}

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

$bonusComplementarios = [];
foreach ($estudiantes as $estudiante) {
    $idEstudiante = (int)$estudiante['id_estudiante'];
    foreach ($todas_materias as $materia) {
        $idMateria = (int)$materia['id_materia'];
        $promedioBase = $promediosMateriaTrimestre[$idEstudiante][$idMateria] ?? '';
        $datosTri = $datosTrimestrales[$idEstudiante][$idMateria] ?? null;
        $autoVal = $datosTri['autoevaluacion'] ?? null;
        $extraVal = $datosTri['nota_extra'] ?? null;

        $tieneBase = ($promedioBase !== '' && $promedioBase !== null);
        $tieneAuto = ($autoVal !== null);
        $tieneExtra = ($extraVal !== null);

        if ($tieneBase || $tieneAuto || $tieneExtra) {
            $baseNum = $tieneBase ? (float)$promedioBase : 0.0;
            $autoNum = $tieneAuto ? (float)$autoVal : 0.0;
            $extraNum = $tieneExtra ? (float)$extraVal : 0.0;
            $promediosMateriaTrimestre[$idEstudiante][$idMateria] = number_format($baseNum + $autoNum + $extraNum, 2);
        } else {
            $promediosMateriaTrimestre[$idEstudiante][$idMateria] = '';
        }

        if (isset($materiasBonusInfo[$idMateria])) {
            $bonusComplementarios[$idEstudiante][$idMateria] = $tieneExtra ? number_format((float)$extraVal, 2) : '';
        }
    }
}

foreach ($estudiantes as $estudiante) {
    foreach ($materias_padre_con_hijas as $padre) {
        $idPadre = (int)$padre['id_materia'];
        $sumatoriaPromediosHijas = 0;
        $contadorHijas = 0;

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
            $calificacionesParciales[$estudiante['id_estudiante']][$idPadre][$parcial] = $cont > 0 ? number_format($suma / $cont, 2) : '';
        }

        foreach ($padre['hijas'] as $hija) {
            $notaHija = $promediosMateriaTrimestre[$estudiante['id_estudiante']][$hija['id_materia']] ?? '';
            if ($notaHija !== '' && $notaHija !== null) {
                $sumatoriaPromediosHijas += (float)$notaHija;
                $contadorHijas++;
            }
        }

        if ($contadorHijas > 0) {
            $promediosMateriaTrimestre[$estudiante['id_estudiante']][$idPadre] = number_format($sumatoriaPromediosHijas / $contadorHijas, 2);
        } else {
            $promediosMateriaTrimestre[$estudiante['id_estudiante']][$idPadre] = '';
        }
    }
}

$promedios_trimestre = [];
foreach ($estudiantes as $estudiante) {
    $suma = $contador = 0;
    foreach ($materias as $mat) {
        if ($mat['es_extra'] == 1 || isset($mat['materia_padre_id'])) {
            continue;
        }
        $nota = $promediosMateriaTrimestre[$estudiante['id_estudiante']][$mat['id_materia']] ?? '';
        if ($nota !== '' && $nota !== null) {
            $suma += (float)$nota;
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
            height: 100dvh; /* Soporte para móviles */
            position: fixed;
            left: 0;
            top: 0;
            background: #2c3e50;
            padding: 20px;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.3) transparent;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
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

        #parcialTabs .nav-link {
            color: #111827;
            font-weight: 600;
            opacity: 1;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        #parcialTabs .nav-link:hover,
        #parcialTabs .nav-link:focus {
            color: #1d4ed8;
            background-color: rgba(29, 78, 216, 0.08);
        }

        #parcialTabs .nav-link.active {
            background-color: #1d4ed8;
            color: #ffffff;
            pointer-events: none;
        }

        .partial-col.js-parcial-edit {
            cursor: pointer;
            position: relative;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .partial-col.js-parcial-edit:hover {
            background: #e0f2fe !important;
            color: #0b2545 !important;
        }

        .partial-col.js-parcial-edit.parcial-loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .partial-col.js-parcial-edit::after {
            content: '';
            position: absolute;
            top: 2px;
            right: 2px;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 6px 6px 0;
            border-color: transparent #2563eb transparent transparent;
            opacity: 0;
            transition: opacity 0.15s ease;
        }

        .partial-col.js-parcial-edit:hover::after {
            opacity: 1;
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

        .bonus-col {
            min-width: 76px;
            text-align: center;
            background: #fff4e6;
            color: #b45309;
            font-weight: 600;
        }

        .bonus-col.nota-baja {
            color: #b45309 !important;
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
                    <?php if (isset($materiasBonusInfo[$mat['id_materia']])): ?>
                        headers.push({
                            title: '<?= addslashes($mat['nombre_materia']) ?> <?= addslashes($materiasBonusInfo[$mat['id_materia']]['label']) ?>',
                            dataKey: 'materia_<?= $mat['id_materia'] ?>_pin'
                        });
                    <?php endif; ?>
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
                        <?php if (isset($materiasBonusInfo[$mat['id_materia']])): ?>
                        'materia_<?= $mat['id_materia'] ?>_pin': '<?= $bonusComplementarios[$est['id_estudiante']][$mat['id_materia']] ?? '--' ?>',
                        <?php endif; ?>
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
                            $bonusInfo = $materiasBonusInfo[$mat['id_materia']] ?? null;
                            $colspan = 4 + ($bonusInfo ? 1 : 0);
                            $profesorMateria = trim((string)($mat['nombre_profesor'] ?? ''));
                            $textoProfesor = $profesorMateria !== '' ? $profesorMateria : 'Profesor no asignado';
                            ?>
                            <th colspan="<?= $colspan ?>" class="<?= $clase ?>">
                                <span class="subject-heading"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Profesor: <?= htmlspecialchars($textoProfesor, ENT_QUOTES) ?>">
                                    <span><?= htmlspecialchars($mat['nombre_materia']) ?></span>
                                    <i class="bi bi-info-circle"></i>
                                </span>
                                <?= $mat['es_extra'] ? '<small>(Extra)</small>' : '' ?>
                                <?php if ($bonusInfo && !empty($bonusInfo['nombre_complementaria'])): ?>
                                    <div><small class="text-muted">Bonus desde <?= htmlspecialchars($bonusInfo['nombre_complementaria']) ?></small></div>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                        <th rowspan="2">Promedio</th>
                    </tr>
                    <tr class="trim-header-sub">
                        <?php foreach ($materias as $mat): ?>
                            <?php $bonusInfo = $materiasBonusInfo[$mat['id_materia']] ?? null; ?>
                            <th class="partial-col">P1</th>
                            <th class="partial-col">P2</th>
                            <th class="partial-col">P3</th>
                            <?php if ($bonusInfo): ?>
                                <th class="bonus-col" data-bs-toggle="tooltip" data-bs-title="<?= htmlspecialchars('Puntos ponderados desde ' . ($bonusInfo['nombre_complementaria'] ?? 'materia complementaria'), ENT_QUOTES) ?>"><?= htmlspecialchars($bonusInfo['label']) ?></th>
                            <?php endif; ?>
                            <th class="average-col">Prom.</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $contador = 1; ?>
                    <?php foreach ($estudiantes as $estudiante): ?>
                        <tr>
                            <?php
                            $estudianteNombreMayus = strtoupper(
                                $estudiante['apellido_paterno'] . ' ' .
                                $estudiante['apellido_materno'] . ', ' .
                                $estudiante['nombres']
                            );
                            $estudianteNombreEsc = htmlspecialchars($estudianteNombreMayus, ENT_QUOTES, 'UTF-8');
                            ?>
                            <td class="number-col"><?= $contador++ ?></td>
                            <td class="student-name">
                                <?= htmlspecialchars($estudianteNombreMayus) ?>
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
                                $bonusInfo = $materiasBonusInfo[$mat['id_materia']] ?? null;
                                $bonusVal = $bonusInfo ? ($bonusComplementarios[$estudiante['id_estudiante']][$mat['id_materia']] ?? '') : '';
                                $materiaNombreEsc = htmlspecialchars($mat['nombre_materia'], ENT_QUOTES, 'UTF-8');
                                $materiaIdAttr = (int)$mat['id_materia'];
                                $estudianteIdAttr = (int)$estudiante['id_estudiante'];
                                // Materia compuesta = padre con hijas (calculada, no editable)
                                // Hijas y extras sí son editables (tienen notas directas)
                                $esMateriaCompuesta = !empty($mat['hijas']);
                                $esMateriaEditable = !$esMateriaCompuesta; // Hijas, extras y padres simples son editables
                                
                                $periodoIdP1 = $periodosIdsTrimestre[1] ?? null;
                                $periodoIdP2 = $periodosIdsTrimestre[2] ?? null;
                                $periodoIdP3 = $periodosIdsTrimestre[3] ?? null;
                                $editableP1 = $puedeEditarParciales && $esMateriaEditable && $periodoIdP1 !== null;
                                $editableP2 = $puedeEditarParciales && $esMateriaEditable && $periodoIdP2 !== null;
                                $editableP3 = $puedeEditarParciales && $esMateriaEditable && $periodoIdP3 !== null;
                                ?>
                                <td
                                    class="partial-col <?= $clase ?> <?= (is_numeric($p1) && floatval($p1) < 51) ? 'nota-baja' : '' ?><?= $editableP1 ? ' js-parcial-edit' : '' ?>"
                                    <?= $editableP1 ? 'title="Editar P1"' : '' ?>
                                    data-materia-id="<?= $materiaIdAttr ?>"
                                    data-estudiante-id="<?= $estudianteIdAttr ?>"
                                    data-parcial="1"
                                    <?= $periodoIdP1 !== null ? 'data-periodo-id="' . $periodoIdP1 . '"' : '' ?>
                                    data-trimestre="<?= (int)$trimestre ?>"
                                    data-student="<?= $estudianteNombreEsc ?>"
                                    data-materia-name="<?= $materiaNombreEsc ?>"
                                ><?= $p1 !== '' ? $p1 : '--' ?></td>
                                <td
                                    class="partial-col <?= $clase ?> <?= (is_numeric($p2) && floatval($p2) < 51) ? 'nota-baja' : '' ?><?= $editableP2 ? ' js-parcial-edit' : '' ?>"
                                    <?= $editableP2 ? 'title="Editar P2"' : '' ?>
                                    data-materia-id="<?= $materiaIdAttr ?>"
                                    data-estudiante-id="<?= $estudianteIdAttr ?>"
                                    data-parcial="2"
                                    <?= $periodoIdP2 !== null ? 'data-periodo-id="' . $periodoIdP2 . '"' : '' ?>
                                    data-trimestre="<?= (int)$trimestre ?>"
                                    data-student="<?= $estudianteNombreEsc ?>"
                                    data-materia-name="<?= $materiaNombreEsc ?>"
                                ><?= $p2 !== '' ? $p2 : '--' ?></td>
                                <td
                                    class="partial-col <?= $clase ?> <?= (is_numeric($p3) && floatval($p3) < 51) ? 'nota-baja' : '' ?><?= $editableP3 ? ' js-parcial-edit' : '' ?>"
                                    <?= $editableP3 ? 'title="Editar P3"' : '' ?>
                                    data-materia-id="<?= $materiaIdAttr ?>"
                                    data-estudiante-id="<?= $estudianteIdAttr ?>"
                                    data-parcial="3"
                                    <?= $periodoIdP3 !== null ? 'data-periodo-id="' . $periodoIdP3 . '"' : '' ?>
                                    data-trimestre="<?= (int)$trimestre ?>"
                                    data-student="<?= $estudianteNombreEsc ?>"
                                    data-materia-name="<?= $materiaNombreEsc ?>"
                                ><?= $p3 !== '' ? $p3 : '--' ?></td>
                                <?php if ($bonusInfo): ?>
                                    <td class="bonus-col" title="<?= htmlspecialchars('Puntos ponderados desde ' . ($bonusInfo['nombre_complementaria'] ?? 'materia complementaria'), ENT_QUOTES) ?>"><?= $bonusVal !== '' ? $bonusVal : '--' ?></td>
                                <?php endif; ?>
                                <td class="average-col <?= $clase ?> <?= (is_numeric($promedioMateria) && floatval($promedioMateria) < 51) ? 'nota-baja' : '' ?>"><?= $promedioMateria !== '' ? $promedioMateria : '--' ?></td>
                            <?php endforeach; ?>
                            <td class="fw-bold"><?= $promedios_trimestre[$estudiante['id_estudiante']] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($puedeEditarParciales): ?>
    <div class="modal fade" id="modalEditarParcial" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar notas del parcial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="d-flex flex-wrap gap-3">
                            <div class="flex-grow-1">
                                <strong>Estudiante:</strong>
                                <span id="modalParcialEstudiante">—</span>
                            </div>
                            <div class="flex-grow-1">
                                <strong>Materia:</strong>
                                <span id="modalParcialMateria">—</span>
                            </div>
                        </div>
                    </div>
                    <!-- Pestañas para los 3 parciales -->
                    <ul class="nav nav-tabs" id="parcialTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="parcial1-tab" data-parcial="1" data-bs-toggle="tab" data-bs-target="#parcial1-content" type="button" role="tab" aria-controls="parcial1-content" aria-selected="true">
                                <strong>Parcial 1</strong>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="parcial2-tab" data-parcial="2" data-bs-toggle="tab" data-bs-target="#parcial2-content" type="button" role="tab" aria-controls="parcial2-content" aria-selected="false">
                                <strong>Parcial 2</strong>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="parcial3-tab" data-parcial="3" data-bs-toggle="tab" data-bs-target="#parcial3-content" type="button" role="tab" aria-controls="parcial3-content" aria-selected="false">
                                <strong>Parcial 3</strong>
                            </button>
                        </li>
                    </ul>
                    <div class="mt-3" id="modalParcialNotaInfo">
                        Nota del parcial seleccionado: <strong id="modalParcialNotaActual">—</strong>
                    </div>
                    <div class="tab-content border border-top-0 p-3 rounded-bottom" id="parcialTabContent">
                        <?php for ($p = 1; $p <= 3; $p++): ?>
                        <div class="tab-pane fade <?= $p === 1 ? 'show active' : '' ?>" id="parcial<?= $p ?>-content" role="tabpanel" aria-labelledby="parcial<?= $p ?>-tab">
                            <div class="row g-3">
                                <div class="col-12 col-lg-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
                                            <span>SER (0-10)</span>
                                            <small class="text-primary" data-parcial="<?= $p ?>" data-area="SER" data-role="total-area">—</small>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-2 parcial-inputs" data-parcial="<?= $p ?>" data-area="SER"></div>
                                            <div class="mt-2 text-muted small parcial-resumen" data-parcial="<?= $p ?>" data-area="SER">Sin datos ingresados.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
                                            <span>SABER (0-45)</span>
                                            <small class="text-primary" data-parcial="<?= $p ?>" data-area="SABER" data-role="total-area">—</small>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-2 parcial-inputs" data-parcial="<?= $p ?>" data-area="SABER"></div>
                                            <div class="mt-2 text-muted small parcial-resumen" data-parcial="<?= $p ?>" data-area="SABER">Sin datos ingresados.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
                                            <span>HACER (0-40)</span>
                                            <small class="text-primary" data-parcial="<?= $p ?>" data-area="HACER" data-role="total-area">—</small>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-2 parcial-inputs" data-parcial="<?= $p ?>" data-area="HACER"></div>
                                            <div class="mt-2 text-muted small parcial-resumen" data-parcial="<?= $p ?>" data-area="HACER">Sin datos ingresados.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div class="alert alert-info mt-3" id="modalInfoTrimestral" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto text-muted small" id="modalParcialMensaje"></div>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="modalParcialGuardar">Guardar parcial actual</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <?php if ($puedeEditarParciales): ?>
    <script>
        (function() {
            console.log('[Editar Parcial] Script iniciado');
            const modalEl = document.getElementById('modalEditarParcial');
            if (!modalEl) {
                console.error('[Editar Parcial] Modal no encontrado');
                return;
            }

            const bsModal = new bootstrap.Modal(modalEl);
            const tabla = document.querySelector('.trimester-table');
            if (!tabla) {
                console.error('[Editar Parcial] Tabla no encontrada');
                return;
            }

            const estudianteEl = modalEl.querySelector('#modalParcialEstudiante');
            const materiaEl = modalEl.querySelector('#modalParcialMateria');
            const infoMsgEl = modalEl.querySelector('#modalParcialMensaje');
            const infoTrimestralEl = modalEl.querySelector('#modalInfoTrimestral');
            const btnGuardar = modalEl.querySelector('#modalParcialGuardar');
            const notaActualEl = modalEl.querySelector('#modalParcialNotaActual');

            let contextoActual = null;
            let totalesParcialesCache = null;
            const maxPorArea = { SER: 4, SABER: 8, HACER: 8 };
            const rangos = { SER: 10, SABER: 45, HACER: 40 };

            // Mapa de inputs y resúmenes por parcial y área
            const inputsMap = {};
            const resumenMap = {};
            const totalAreaMap = {};

            function crearInput(area, indice) {
                const wrapper = document.createElement('div');
                wrapper.className = 'col-6';

                const input = document.createElement('input');
                input.type = 'number';
                input.className = 'form-control form-control-sm';
                input.dataset.area = area;
                input.dataset.indice = indice;
                input.step = '0.01';
                input.min = '0';
                input.max = String(rangos[area]);
                input.placeholder = `${indice}`;

                wrapper.appendChild(input);
                return { wrapper, input };
            }

            function renderInputs(container, area) {
                container.innerHTML = '';
                const elementos = [];
                for (let i = 1; i <= maxPorArea[area]; i++) {
                    const { wrapper, input } = crearInput(area, i);
                    container.appendChild(wrapper);
                    elementos.push(input);
                }
                return elementos;
            }

            // Inicializar inputs para los 3 parciales
            for (let p = 1; p <= 3; p++) {
                inputsMap[p] = {};
                resumenMap[p] = {};
                ['SER', 'SABER', 'HACER'].forEach(area => {
                    const container = modalEl.querySelector(`.parcial-inputs[data-parcial="${p}"][data-area="${area}"]`);
                    const resumenEl = modalEl.querySelector(`.parcial-resumen[data-parcial="${p}"][data-area="${area}"]`);
                    const totalEl = modalEl.querySelector(`[data-role="total-area"][data-parcial="${p}"][data-area="${area}"]`);
                    if (container && resumenEl) {
                        inputsMap[p][area] = renderInputs(container, area);
                        resumenMap[p][area] = resumenEl;
                        if (!totalAreaMap[p]) {
                            totalAreaMap[p] = {};
                        }
                        totalAreaMap[p][area] = totalEl ?? null;
                    }
                });
            }

            function asignarValores(parcial, area, valores) {
                const inputs = inputsMap[parcial]?.[area];
                if (!inputs) return;
                inputs.forEach(input => {
                    const idx = parseInt(input.dataset.indice, 10);
                    const valor = valores[idx] ?? null;
                    input.value = valor !== null ? valor : '';
                });
            }

            function resumenArea(textEl, inputs, area) {
                const valores = inputs
                    .map(input => {
                        const v = input.value.trim();
                        if (v === '') return null;
                        const num = Number(v.replace(',', '.'));
                        return isNaN(num) ? null : num;
                    })
                    .filter(v => v !== null);

                if (valores.length === 0) {
                    textEl.textContent = 'Sin datos ingresados.';
                    return { promedio: null, cantidad: 0 };
                }
                const suma = valores.reduce((acc, v) => acc + v, 0);
                const promedio = suma / valores.length;
                const restante = rangos[area] - promedio;
                textEl.innerHTML = `Promedio: <strong>${promedio.toFixed(2)}</strong> · Restante área: ${restante.toFixed(2)}`;
                return { promedio, cantidad: valores.length };
            }

            function calcularResumenes(parcial) {
                if (!parcial) {
                    for (let p = 1; p <= 3; p++) {
                        calcularResumenes(p);
                    }
                    return;
                }
                ['SER', 'SABER', 'HACER'].forEach(area => {
                    const inputs = inputsMap[parcial]?.[area];
                    const resumenEl = resumenMap[parcial]?.[area];
                    if (inputs && resumenEl) {
                        resumenArea(resumenEl, inputs, area);
                    }
                });
            }

            function aplicarTotalesParciales(totales) {
                if (!totales) {
                    for (let p = 1; p <= 3; p++) {
                        ['SER', 'SABER', 'HACER'].forEach(area => {
                            const totalEl = totalAreaMap[p]?.[area];
                            if (totalEl) {
                                totalEl.textContent = '—';
                            }
                        });
                    }
                    return;
                }
                for (let p = 1; p <= 3; p++) {
                    const totalesParcial = totales[p];
                    if (!totalesParcial) {
                        continue;
                    }
                    ['SER', 'SABER', 'HACER'].forEach(area => {
                        const inputs = inputsMap[p]?.[area];
                        const resumenEl = resumenMap[p]?.[area];
                        const totalEl = totalAreaMap[p]?.[area];
                        if (!inputs || !resumenEl) {
                            return;
                        }
                        const tieneValores = inputs.some(input => input.value.trim() !== '');
                        if (tieneValores) {
                            if (totalEl) {
                                totalEl.textContent = '—';
                            }
                            return;
                        }
                        const claveTotal = `${area.toLowerCase()}_total`;
                        const totalArea = totalesParcial?.[claveTotal];
                        if (totalArea !== null && totalArea !== undefined) {
                            const numero = Number(totalArea);
                            if (!Number.isNaN(numero)) {
                                resumenEl.innerHTML = `Promedio registrado: <strong>${numero.toFixed(2)}</strong>`;
                                if (totalEl) {
                                    totalEl.textContent = numero.toFixed(2);
                                }
                                return;
                            }
                        }
                        resumenEl.textContent = 'Sin datos ingresados.';
                        if (totalEl) {
                            totalEl.textContent = '—';
                        }
                    });
                }
            }

            function limpiarModal() {
                contextoActual = null;
                totalesParcialesCache = null;
                infoMsgEl.textContent = '';
                infoTrimestralEl.style.display = 'none';
                estudianteEl.textContent = '—';
                materiaEl.textContent = '—';
                for (let p = 1; p <= 3; p++) {
                    ['SER', 'SABER', 'HACER'].forEach(area => {
                        const inputs = inputsMap[p]?.[area];
                        if (inputs) {
                            inputs.forEach(input => input.value = '');
                        }
                    });
                }
                calcularResumenes();
                aplicarTotalesParciales(null);
                notaActualEl.textContent = '—';
            }

            function obtenerValores(inputs, area) {
                const resultado = {};
                inputs.forEach(input => {
                    const idx = input.dataset.indice;
                    const valor = input.value.trim();
                    if (valor === '') {
                        resultado[idx] = null;
                        return;
                    }
                    const num = Number(valor.replace(',', '.'));
                    if (isNaN(num)) {
                        throw new Error(`${area} ${idx}: ingrese un número válido.`);
                    }
                    if (num < 0 || num > rangos[area]) {
                        throw new Error(`${area} ${idx}: debe estar entre 0 y ${rangos[area]}.`);
                    }
                    resultado[idx] = Number(num.toFixed(2));
                });
                return resultado;
            }

            function calcularNotaParcial(parcial) {
                const inputs = inputsMap[parcial];
                if (!inputs) return null;
                let total = 0;
                ['SER', 'SABER', 'HACER'].forEach(area => {
                    const areaInputs = inputs[area] ?? [];
                    const valores = areaInputs
                        .map(input => {
                            const v = input.value.trim();
                            if (v === '') return null;
                            const num = Number(v.replace(',', '.'));
                            return Number.isNaN(num) ? null : num;
                        })
                        .filter(v => v !== null);
                    if (valores.length > 0) {
                        total += valores.reduce((acc, val) => acc + val, 0) / valores.length;
                    }
                });
                return total;
            }

            function actualizarNotaParcialDisplay(parcial, totales) {
                let valor = calcularNotaParcial(parcial);
                if ((valor === null || Number.isNaN(valor)) && totales) {
                    const parcialTotals = totales[parcial];
                    if (parcialTotals && parcialTotals.calificacion !== null && parcialTotals.calificacion !== undefined) {
                        const cal = Number(parcialTotals.calificacion);
                        if (!Number.isNaN(cal)) {
                            valor = cal;
                        }
                    }
                }
                if (valor === null || Number.isNaN(valor)) {
                    notaActualEl.textContent = '—';
                    return;
                }
                notaActualEl.textContent = Number(valor).toFixed(2);
            }

            async function cargarDetalle(celda, contexto) {
                celda.classList.add('parcial-loading');
                try {
                    const params = new URLSearchParams({
                        id_curso: contexto.idCurso,
                        id_materia: contexto.idMateria,
                        id_estudiante: contexto.idEstudiante,
                        trimestre: contexto.trimestre,
                        id_periodo_evaluacion: contexto.idPeriodo,
                        parcial: contexto.parcial,
                    });

                    const resp = await fetch('ajax_parcial_notas.php?' + params.toString(), {
                        credentials: 'same-origin'
                    });
                    if (!resp.ok) {
                        throw new Error('No fue posible obtener los datos.');
                    }
                    const json = await resp.json();
                    if (!json.success) {
                        throw new Error(json.message || 'Error desconocido.');
                    }

                    // Cargar los 3 parciales
                    for (let p = 1; p <= 3; p++) {
                        const detalle = json.data.detalle_parciales?.[p];
                        if (detalle) {
                            asignarValores(p, 'SER', detalle.SER || []);
                            asignarValores(p, 'SABER', detalle.SABER || []);
                            asignarValores(p, 'HACER', detalle.HACER || []);
                        }
                    }
                    totalesParcialesCache = json.data.totales_parciales ?? null;
                    calcularResumenes();
                    aplicarTotalesParciales(totalesParcialesCache);
                    actualizarNotaParcialDisplay(contexto.parcial, totalesParcialesCache);

                    if (json.data.autoevaluacion !== null || json.data.nota_extra !== null) {
                        infoTrimestralEl.style.display = 'block';
                        infoTrimestralEl.innerHTML = `Autoevaluación: <strong>${json.data.autoevaluacion ?? 0}</strong> · Extra: <strong>${json.data.nota_extra ?? 0}</strong>`;
                    } else {
                        infoTrimestralEl.style.display = 'none';
                    }
                } catch (error) {
                    infoMsgEl.textContent = error.message;
                } finally {
                    celda.classList.remove('parcial-loading');
                }
            }

            tabla.addEventListener('click', async function (event) {
                const celda = event.target.closest('.js-parcial-edit');
                if (!celda) return;

                const dataset = celda.dataset;
                
                const idMateria = parseInt(dataset.materiaId, 10);
                const idEstudiante = parseInt(dataset.estudianteId, 10);
                const parcial = parseInt(dataset.parcial, 10);
                const idPeriodo = parseInt(dataset.periodoId, 10);
                const trimestre = parseInt(dataset.trimestre, 10);

                if (!idMateria || !idEstudiante || !parcial || !idPeriodo || !trimestre) {
                    infoMsgEl.textContent = 'Información incompleta para editar.';
                    return;
                }

                contextoActual = {
                    idCurso: <?= (int)$id_curso ?>,
                    idMateria,
                    idEstudiante,
                    idPeriodo,
                    trimestre,
                    parcial,
                    celda,
                };

                estudianteEl.textContent = dataset.student ?? '—';
                materiaEl.textContent = dataset.materiaName ?? '—';
                infoMsgEl.textContent = '';
                infoTrimestralEl.style.display = 'none';

                // Limpiar todos los inputs
                for (let p = 1; p <= 3; p++) {
                    ['SER', 'SABER', 'HACER'].forEach(area => {
                        const inputs = inputsMap[p]?.[area];
                        if (inputs) {
                            inputs.forEach(input => input.value = '');
                        }
                    });
                }
                calcularResumenes();

                // Activar la pestaña del parcial clickeado
                const tabEl = modalEl.querySelector(`#parcial${parcial}-tab`);
                if (tabEl) {
                    const tab = new bootstrap.Tab(tabEl);
                    tab.show();
                }

                await cargarDetalle(celda, contextoActual);

                bsModal.show();
            });

            const tabsEl = modalEl.querySelector('#parcialTabs');
            if (tabsEl) {
                tabsEl.addEventListener('shown.bs.tab', (event) => {
                    const button = event.target;
                    const parcial = parseInt(button.dataset.parcial, 10);
                    if (!Number.isNaN(parcial)) {
                        actualizarNotaParcialDisplay(parcial, totalesParcialesCache);
                    }
                });
            }

            // Eventos input para todos los inputs
            for (let p = 1; p <= 3; p++) {
                ['SER', 'SABER', 'HACER'].forEach(area => {
                    const inputs = inputsMap[p]?.[area];
                    if (inputs) {
                        inputs.forEach(input => {
                            input.addEventListener('input', () => {
                                try {
                                    calcularResumenes(p);
                                    aplicarTotalesParciales(totalesParcialesCache);
                                    actualizarNotaParcialDisplay(p, totalesParcialesCache);
                                    infoMsgEl.textContent = '';
                                } catch (error) {
                                    infoMsgEl.textContent = error.message;
                                }
                            });
                        });
                    }
                });
            }

            btnGuardar.addEventListener('click', async function () {
                if (!contextoActual) return;
                const parcial = contextoActual.parcial;
                try {
                    const ser = obtenerValores(inputsMap[parcial]['SER'], 'SER');
                    const saber = obtenerValores(inputsMap[parcial]['SABER'], 'SABER');
                    const hacer = obtenerValores(inputsMap[parcial]['HACER'], 'HACER');

                    btnGuardar.disabled = true;
                    infoMsgEl.textContent = 'Guardando...';

                    const payload = {
                        id_curso: contextoActual.idCurso,
                        id_materia: contextoActual.idMateria,
                        id_estudiante: contextoActual.idEstudiante,
                        id_periodo_evaluacion: contextoActual.idPeriodo,
                        trimestre: contextoActual.trimestre,
                        parcial: contextoActual.parcial,
                        ser,
                        saber,
                        hacer,
                    };

                    const resp = await fetch('ajax_parcial_notas.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });
                    const json = await resp.json();
                    if (!resp.ok || !json.success) {
                        throw new Error(json.message || 'No se pudo guardar.');
                    }

                    infoMsgEl.textContent = 'Cambios guardados correctamente.';

                    if (contextoActual.celda) {
                        if (json.data.parcial_formatted !== undefined) {
                            contextoActual.celda.textContent = json.data.parcial_formatted;
                        }
                        contextoActual.celda.classList.toggle('nota-baja', json.data.es_nota_baja === true);
                        notaActualEl.textContent = json.data.parcial_formatted !== '--' ? json.data.parcial_formatted : '—';
                    }

                    const fila = contextoActual.celda?.parentElement;
                    if (fila && json.data.promedio_materia_formatted !== undefined) {
                        const celdasProm = fila.querySelectorAll('.average-col');
                        if (celdasProm.length > 0) {
                            const celdaProm = celdasProm[celdasProm.length - 1];
                            if (celdaProm) {
                                celdaProm.textContent = json.data.promedio_materia_formatted ?? '--';
                                if (json.data.promedio_materia_formatted !== null) {
                                    celdaProm.classList.toggle('nota-baja', parseFloat(json.data.promedio_materia_formatted) < 51);
                                }
                            }
                        }
                    }

                    const promedioArea = valores => {
                        const arr = Object.values(valores).filter(v => v !== null && v !== undefined);
                        if (!arr.length) return null;
                        const suma = arr.reduce((acc, val) => acc + Number(val), 0);
                        return Number((suma / arr.length).toFixed(2));
                    };

                    if (!totalesParcialesCache) {
                        totalesParcialesCache = {};
                    }
                    if (typeof contextoActual.parcial === 'number') {
                        if (!totalesParcialesCache[contextoActual.parcial]) {
                            totalesParcialesCache[contextoActual.parcial] = {
                                ser_total: null,
                                saber_total: null,
                                hacer_total: null,
                                calificacion: null,
                            };
                        }
                        const serProm = promedioArea(ser);
                        const saberProm = promedioArea(saber);
                        const hacerProm = promedioArea(hacer);
                        totalesParcialesCache[contextoActual.parcial].ser_total = serProm;
                        totalesParcialesCache[contextoActual.parcial].saber_total = saberProm;
                        totalesParcialesCache[contextoActual.parcial].hacer_total = hacerProm;
                        totalesParcialesCache[contextoActual.parcial].calificacion = json.data.parcial_formatted !== '--' ? parseFloat(json.data.parcial_formatted) : null;
                        aplicarTotalesParciales(totalesParcialesCache);
                    }

                    setTimeout(() => {
                        bsModal.hide();
                    }, 600);
                } catch (error) {
                    infoMsgEl.textContent = error.message;
                } finally {
                    btnGuardar.disabled = false;
                }
            });

            modalEl.addEventListener('hidden.bs.modal', () => {
                limpiarModal();
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
