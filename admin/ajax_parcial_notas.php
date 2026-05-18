<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? null, [1, 2], true)) {
    json_response(['success' => false, 'message' => 'No autorizado.'], 403);
}

$database = new Database();
$conn = $database->connect();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$gestionActual = null;
$gestionAlternativa = null;
try {
    $stmtGestion = $conn->query("SELECT anio_escolar FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
    $gestionConfigurada = $stmtGestion->fetchColumn();
    $gestionConfigurada = $gestionConfigurada ? trim((string)$gestionConfigurada) : '';
    $gestionActual = $gestionConfigurada !== '' ? $gestionConfigurada : date('Y');
    if (preg_match('/\b(20\d{2})\b/', $gestionActual, $matches)) {
        $gestionAlternativa = $matches[1] ?? null;
    }
} catch (PDOException $e) {
    $gestionActual = date('Y');
}

function sanitize_int($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        return null;
    }
    return (int)$value;
}

function obtener_gestion_prioritaria(string $gestionActual, ?string $gestionAlternativa): array
{
    $gestiones = [$gestionActual];
    if ($gestionAlternativa !== null && $gestionAlternativa !== $gestionActual) {
        $gestiones[] = $gestionAlternativa;
    }
    return $gestiones;
}

function obtener_nota_trimestral(PDO $conn, int $idEstudiante, int $idMateria, int $trimestre, string $gestionActual, ?string $gestionAlternativa): array
{
    $resultado = ['autoevaluacion' => null, 'nota_extra' => null];
    $gestiones = obtener_gestion_prioritaria($gestionActual, $gestionAlternativa);
    if (empty($gestiones)) {
        return $resultado;
    }

    $placeholders = implode(',', array_fill(0, count($gestiones), '?'));
    $sql = "SELECT autoevaluacion, nota_extra, gestion
            FROM calificaciones_trimestrales
            WHERE id_estudiante = ?
              AND id_materia = ?
              AND trimestre = ?
              AND gestion IN ($placeholders)
            ORDER BY CASE WHEN gestion = ? THEN 1 ELSE 2 END";
    $params = array_merge([$idEstudiante, $idMateria, $trimestre], $gestiones, [$gestionActual]);

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fila) {
            $resultado['autoevaluacion'] = ($fila['autoevaluacion'] !== null && $fila['autoevaluacion'] !== '') ? (float)$fila['autoevaluacion'] : null;
            $resultado['nota_extra'] = ($fila['nota_extra'] !== null && $fila['nota_extra'] !== '') ? (float)$fila['nota_extra'] : null;
        }
    } catch (PDOException $e) {
        // Ignorar si la tabla no existe o no hay permisos.
    }

    return $resultado;
}

function cargar_detalle_parcial(PDO $conn, int $idCalificacionParcial): array
{
    $detalle = [
        'SER' => array_fill(1, 4, null),
        'SABER' => array_fill(1, 8, null),
        'HACER' => array_fill(1, 8, null),
    ];

    try {
        $stmt = $conn->prepare('SELECT area, indice, nota FROM calificaciones_parciales_detalle WHERE id_calificacion_parcial = ?');
        $stmt->execute([$idCalificacionParcial]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $area = strtoupper((string)$row['area']);
            $indice = (int)$row['indice'];
            if (isset($detalle[$area][$indice])) {
                $detalle[$area][$indice] = ($row['nota'] !== null && $row['nota'] !== '') ? (float)$row['nota'] : null;
            }
        }
    } catch (PDOException $e) {
        // Si la tabla no existe, devolvemos los arrays vacíos.
    }

    return $detalle;
}

function preparar_area_inputs(array $fuente, int $max): array
{
    $salida = [];
    for ($i = 1; $i <= $max; $i++) {
        $salida[$i] = null;
        if (isset($fuente[$i])) {
            $valor = $fuente[$i];
            if ($valor === '' || $valor === null) {
                continue;
            }
            $valor = str_replace(',', '.', (string)$valor);
            if (!is_numeric($valor)) {
                throw new InvalidArgumentException('Los valores deben ser numéricos.');
            }
            $salida[$i] = round((float)$valor, 2);
        }
    }
    return $salida;
}

function contar_valores_no_nulos(array $valores): int
{
    return count(array_filter($valores, static function ($v) {
        return $v !== null && $v !== '';
    }));
}

function resumen_area(array $valores): array
{
    $filtrados = array_values(array_filter($valores, static function ($v) {
        return $v !== null && $v !== '';
    }));
    if (empty($filtrados)) {
        return ['promedio' => null, 'suma' => 0.0, 'cantidad' => 0];
    }
    $suma = array_sum($filtrados);
    $cantidad = count($filtrados);
    $promedio = $suma / $cantidad;
    return ['promedio' => $promedio, 'suma' => $suma, 'cantidad' => $cantidad];
}

if ($method === 'GET') {
    $idCurso = sanitize_int($_GET['id_curso'] ?? null);
    $idMateria = sanitize_int($_GET['id_materia'] ?? null);
    $idEstudiante = sanitize_int($_GET['id_estudiante'] ?? null);
    $idPeriodo = sanitize_int($_GET['id_periodo_evaluacion'] ?? null);
    $trimestre = sanitize_int($_GET['trimestre'] ?? null);
    $parcialSolicitado = sanitize_int($_GET['parcial'] ?? null);
    $idPeriodoSolicitado = sanitize_int($_GET['id_periodo_evaluacion'] ?? null);

    if (in_array(null, [$idCurso, $idMateria, $idEstudiante, $trimestre], true)) {
        json_response(['success' => false, 'message' => 'Parámetros incompletos.'], 422);
    }

    try {
        $stmtCheck = $conn->prepare('SELECT 1 FROM estudiantes WHERE id_estudiante = ? AND id_curso = ? LIMIT 1');
        $stmtCheck->execute([$idEstudiante, $idCurso]);
        if ($stmtCheck->fetchColumn() === false) {
            json_response(['success' => false, 'message' => 'El estudiante no pertenece al curso.'], 404);
        }

        // Obtener los periodos de evaluación para los 3 parciales del trimestre
        $gestiones = obtener_gestion_prioritaria($gestionActual, $gestionAlternativa);
        $placeholdersGestion = implode(',', array_fill(0, count($gestiones), '?'));
        $stmtPeriodos = $conn->prepare("SELECT id_periodo_evaluacion, parcial, gestion
                                        FROM periodos_evaluacion
                                        WHERE trimestre = ?
                                          AND gestion IN ($placeholdersGestion)
                                        ORDER BY parcial ASC,
                                                 CASE WHEN gestion = ? THEN 0 ELSE 1 END ASC,
                                                 id_periodo_evaluacion DESC");
        $stmtPeriodos->execute(array_merge([$trimestre], $gestiones, [$gestionActual]));
        $periodos = [];
        foreach ($stmtPeriodos->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $parc = (int)$p['parcial'];
            if (!isset($periodos[$parc])) {
                $periodos[$parc] = (int)$p['id_periodo_evaluacion'];
            }
        }

        // Cargar detalle de los 3 parciales
        $detalleParciales = [];
        $totalesParciales = [];
        for ($p = 1; $p <= 3; $p++) {
            $idPeriodoP = $periodos[$p] ?? null;
            if ($parcialSolicitado !== null && $idPeriodoSolicitado !== null) {
                if ($p === $parcialSolicitado) {
                    $idPeriodoP = $idPeriodoSolicitado;
                }
            }
            $detalleParciales[$p] = [
                'SER' => array_fill(1, 4, null),
                'SABER' => array_fill(1, 8, null),
                'HACER' => array_fill(1, 8, null),
            ];
            $totalesParciales[$p] = [
                'ser_total' => null,
                'saber_total' => null,
                'hacer_total' => null,
                'calificacion' => null,
            ];

            if ($idPeriodoP !== null) {
                $stmtParcial = $conn->prepare('SELECT id_calificacion_parcial, calificacion, ser_total, saber_total, hacer_total
                                               FROM calificaciones_parciales
                                               WHERE id_estudiante = ? AND id_materia = ? AND id_periodo_evaluacion = ?
                                               LIMIT 1');
                $stmtParcial->execute([$idEstudiante, $idMateria, $idPeriodoP]);
                $filaParcial = $stmtParcial->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($filaParcial) {
                    $detalleParciales[$p] = cargar_detalle_parcial($conn, (int)$filaParcial['id_calificacion_parcial']);
                    $totalesParciales[$p]['ser_total'] = $filaParcial['ser_total'] !== null ? (float)$filaParcial['ser_total'] : null;
                    $totalesParciales[$p]['saber_total'] = $filaParcial['saber_total'] !== null ? (float)$filaParcial['saber_total'] : null;
                    $totalesParciales[$p]['hacer_total'] = $filaParcial['hacer_total'] !== null ? (float)$filaParcial['hacer_total'] : null;
                    $totalesParciales[$p]['calificacion'] = $filaParcial['calificacion'] !== null ? (float)$filaParcial['calificacion'] : null;
                }
            }
        }

        $notaTrimestral = obtener_nota_trimestral($conn, $idEstudiante, $idMateria, $trimestre, $gestionActual, $gestionAlternativa);

        json_response([
            'success' => true,
            'data' => [
                'detalle_parciales' => $detalleParciales,
                'totales_parciales' => $totalesParciales,
                'autoevaluacion' => $notaTrimestral['autoevaluacion'],
                'nota_extra' => $notaTrimestral['nota_extra'],
            ],
        ]);
    } catch (InvalidArgumentException $e) {
        json_response(['success' => false, 'message' => $e->getMessage()], 422);
    } catch (PDOException $e) {
        json_response(['success' => false, 'message' => 'Error al obtener la información.'], 500);
    }
}

if ($method === 'POST') {
    if (!isset($_SESSION['user_role']) || (int)$_SESSION['user_role'] !== 1) {
        json_response(['success' => false, 'message' => 'Solo administradores pueden editar.'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        json_response(['success' => false, 'message' => 'Formato de datos inválido.'], 422);
    }

    $idCurso = sanitize_int($input['id_curso'] ?? null);
    $idMateria = sanitize_int($input['id_materia'] ?? null);
    $idEstudiante = sanitize_int($input['id_estudiante'] ?? null);
    $idPeriodo = sanitize_int($input['id_periodo_evaluacion'] ?? null);
    $trimestre = sanitize_int($input['trimestre'] ?? null);
    $parcial = sanitize_int($input['parcial'] ?? null);

    if (in_array(null, [$idCurso, $idMateria, $idEstudiante, $idPeriodo, $trimestre, $parcial], true)) {
        json_response(['success' => false, 'message' => 'Parámetros incompletos.'], 422);
    }

    $areasConfig = [
        'SER' => ['max_campos' => 4, 'max_valor' => 10],
        'SABER' => ['max_campos' => 8, 'max_valor' => 45],
        'HACER' => ['max_campos' => 8, 'max_valor' => 40],
    ];

    try {
        $conn->beginTransaction();

        $stmtCheck = $conn->prepare('SELECT 1 FROM estudiantes WHERE id_estudiante = ? AND id_curso = ? LIMIT 1');
        $stmtCheck->execute([$idEstudiante, $idCurso]);
        if ($stmtCheck->fetchColumn() === false) {
            $conn->rollBack();
            json_response(['success' => false, 'message' => 'El estudiante no pertenece al curso.'], 404);
        }

        $stmtPeriodo = $conn->prepare('SELECT trimestre FROM periodos_evaluacion WHERE id_periodo_evaluacion = ? LIMIT 1');
        $stmtPeriodo->execute([$idPeriodo]);
        $filaPeriodo = $stmtPeriodo->fetch(PDO::FETCH_ASSOC);
        if (!$filaPeriodo) {
            $conn->rollBack();
            json_response(['success' => false, 'message' => 'El periodo de evaluación no existe.'], 404);
        }

        if ((int)$filaPeriodo['trimestre'] !== $trimestre) {
            $conn->rollBack();
            json_response(['success' => false, 'message' => 'El periodo no corresponde al trimestre indicado.'], 422);
        }

        $serValores = preparar_area_inputs($input['ser'] ?? [], $areasConfig['SER']['max_campos']);
        $saberValores = preparar_area_inputs($input['saber'] ?? [], $areasConfig['SABER']['max_campos']);
        $hacerValores = preparar_area_inputs($input['hacer'] ?? [], $areasConfig['HACER']['max_campos']);

        $conteoSer = contar_valores_no_nulos($serValores);
        $conteoSaber = contar_valores_no_nulos($saberValores);
        $conteoHacer = contar_valores_no_nulos($hacerValores);

        $stmtPrev = $conn->prepare('SELECT id_calificacion_parcial, ser_total, saber_total, hacer_total
            FROM calificaciones_parciales
            WHERE id_estudiante = ? AND id_materia = ? AND id_periodo_evaluacion = ?
            LIMIT 1');
        $stmtPrev->execute([$idEstudiante, $idMateria, $idPeriodo]);
        $filaPrev = $stmtPrev->fetch(PDO::FETCH_ASSOC) ?: null;
        $idCalificacionExistente = $filaPrev ? (int)$filaPrev['id_calificacion_parcial'] : null;

        if ($conteoSer === 0 && $conteoSaber === 0 && $conteoHacer === 0) {
            if ($idCalificacionExistente !== null) {
                $conn->prepare('DELETE FROM calificaciones_parciales WHERE id_calificacion_parcial = ?')
                     ->execute([$idCalificacionExistente]);
                try {
                    $conn->prepare('DELETE FROM calificaciones_parciales_detalle WHERE id_calificacion_parcial = ?')
                         ->execute([$idCalificacionExistente]);
                } catch (PDOException $e) {
                    // La tabla de detalle puede no existir.
                }
            }

            $conn->commit();
            json_response([
                'success' => true,
                'data' => [
                    'parcial_formatted' => '--',
                    'es_nota_baja' => false,
                    'promedio_materia_formatted' => null,
                ],
            ]);
        }

        $rangos = [
            'SER' => $areasConfig['SER']['max_valor'],
            'SABER' => $areasConfig['SABER']['max_valor'],
            'HACER' => $areasConfig['HACER']['max_valor'],
        ];

        foreach ($serValores as $indice => $valor) {
            if ($valor !== null && ($valor < 0 || $valor > $rangos['SER'])) {
                throw new InvalidArgumentException('SER fuera de rango (0-10).');
            }
        }
        foreach ($saberValores as $indice => $valor) {
            if ($valor !== null && ($valor < 0 || $valor > $rangos['SABER'])) {
                throw new InvalidArgumentException('SABER fuera de rango (0-45).');
            }
        }
        foreach ($hacerValores as $indice => $valor) {
            if ($valor !== null && ($valor < 0 || $valor > $rangos['HACER'])) {
                throw new InvalidArgumentException('HACER fuera de rango (0-40).');
            }
        }

        $serResumen = resumen_area($serValores);
        $saberResumen = resumen_area($saberValores);
        $hacerResumen = resumen_area($hacerValores);

        $prevSerTotal = $filaPrev && $filaPrev['ser_total'] !== null ? (float)$filaPrev['ser_total'] : 0.0;
        $prevSaberTotal = $filaPrev && $filaPrev['saber_total'] !== null ? (float)$filaPrev['saber_total'] : 0.0;
        $prevHacerTotal = $filaPrev && $filaPrev['hacer_total'] !== null ? (float)$filaPrev['hacer_total'] : 0.0;

        $serProm = $conteoSer > 0 ? ($serResumen['promedio'] ?? 0.0) : 0.0;
        $saberProm = $conteoSaber > 0 ? ($saberResumen['promedio'] ?? 0.0) : 0.0;
        $hacerProm = $conteoHacer > 0 ? ($hacerResumen['promedio'] ?? 0.0) : 0.0;

        $serTotal = round($serProm, 2);
        $saberTotal = round($saberProm, 2);
        $hacerTotal = round($hacerProm, 2);
        $calificacion = round($serProm + $saberProm + $hacerProm, 2);

        $stmtUpsert = $conn->prepare('INSERT INTO calificaciones_parciales
            (id_estudiante, id_materia, id_periodo_evaluacion, calificacion, ser_total, saber_total, hacer_total)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion), ser_total = VALUES(ser_total), saber_total = VALUES(saber_total), hacer_total = VALUES(hacer_total)');
        $stmtUpsert->execute([
            $idEstudiante,
            $idMateria,
            $idPeriodo,
            $calificacion,
            $serTotal,
            $saberTotal,
            $hacerTotal,
        ]);

        $stmtPrev->execute([$idEstudiante, $idMateria, $idPeriodo]);
        $filaPrev = $stmtPrev->fetch(PDO::FETCH_ASSOC) ?: null;
        $idCalificacionExistente = $filaPrev ? (int)$filaPrev['id_calificacion_parcial'] : null;

        if ($idCalificacionExistente !== null) {
            try {
                $stmtDetalleUpsert = $conn->prepare('INSERT INTO calificaciones_parciales_detalle
                    (id_calificacion_parcial, area, indice, nota, creado_por)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE nota = VALUES(nota), creado_por = VALUES(creado_por)');
                $stmtDetalleDelete = $conn->prepare('DELETE FROM calificaciones_parciales_detalle WHERE id_calificacion_parcial = ? AND area = ? AND indice = ?');

                foreach ($serValores as $indice => $valor) {
                    if ($valor !== null) {
                        $stmtDetalleUpsert->execute([$idCalificacionExistente, 'SER', $indice, $valor, $_SESSION['user_id']]);
                    } else {
                        $stmtDetalleDelete->execute([$idCalificacionExistente, 'SER', $indice]);
                    }
                }

                foreach ($saberValores as $indice => $valor) {
                    if ($valor !== null) {
                        $stmtDetalleUpsert->execute([$idCalificacionExistente, 'SABER', $indice, $valor, $_SESSION['user_id']]);
                    } else {
                        $stmtDetalleDelete->execute([$idCalificacionExistente, 'SABER', $indice]);
                    }
                }

                foreach ($hacerValores as $indice => $valor) {
                    if ($valor !== null) {
                        $stmtDetalleUpsert->execute([$idCalificacionExistente, 'HACER', $indice, $valor, $_SESSION['user_id']]);
                    } else {
                        $stmtDetalleDelete->execute([$idCalificacionExistente, 'HACER', $indice]);
                    }
                }
            } catch (PDOException $e) {
                // Ignorar si no existe tabla de detalle
            }
        }

        $conn->commit();

        $notaTrimestral = obtener_nota_trimestral($conn, $idEstudiante, $idMateria, $trimestre, $gestionActual, $gestionAlternativa);

        $stmtParciales = $conn->prepare('SELECT pe.parcial, cp.calificacion, pe.gestion
                                          FROM calificaciones_parciales cp
                                          INNER JOIN periodos_evaluacion pe ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
                                          WHERE cp.id_estudiante = ?
                                            AND cp.id_materia = ?
                                            AND pe.trimestre = ?
                                            AND pe.gestion IN (?, ?)
                                          ORDER BY pe.parcial ASC,
                                                   CASE WHEN pe.gestion = ? THEN 0 ELSE 1 END ASC,
                                                   cp.id_calificacion_parcial DESC;');
        $stmtParciales->execute([
            $idEstudiante,
            $idMateria,
            $trimestre,
            $gestionActual,
            $gestionAlternativa ?? $gestionActual,
            $gestionActual,
        ]);

        $parcialesValores = [];
        foreach ($stmtParciales->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $p = (int)$fila['parcial'];
            if (!isset($parcialesValores[$p]) || $parcialesValores[$p] === null) {
                $parcialesValores[$p] = $fila['calificacion'] !== null ? (float)$fila['calificacion'] : null;
            }
        }

        $calificacionParcial = $parcialesValores[$parcial] ?? null;

        $parcialesValidos = array_filter($parcialesValores, static function ($v) {
            return $v !== null && $v !== '';
        });
        $promedioMateria = null;
        if (!empty($parcialesValidos) || $notaTrimestral['autoevaluacion'] !== null || $notaTrimestral['nota_extra'] !== null) {
            $promedioParciales = !empty($parcialesValidos) ? array_sum($parcialesValidos) / count($parcialesValidos) : 0.0;
            $promedioMateria = $promedioParciales + ($notaTrimestral['autoevaluacion'] ?? 0) + ($notaTrimestral['nota_extra'] ?? 0);
            $promedioMateria = round($promedioMateria, 2);
        }

        json_response([
            'success' => true,
            'data' => [
                'parcial_formatted' => $calificacionParcial !== null ? number_format((float)$calificacionParcial, 2) : '--',
                'es_nota_baja' => $calificacionParcial !== null ? ((float)$calificacionParcial < 51.0) : false,
                'promedio_materia_formatted' => $promedioMateria !== null ? number_format($promedioMateria, 2) : null,
                'autoevaluacion' => $notaTrimestral['autoevaluacion'],
                'nota_extra' => $notaTrimestral['nota_extra'],
            ],
        ]);
    } catch (InvalidArgumentException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        json_response(['success' => false, 'message' => $e->getMessage()], 422);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        json_response(['success' => false, 'message' => 'Error al guardar la información.'], 500);
    }
}

json_response(['success' => false, 'message' => 'Método no soportado.'], 405);
