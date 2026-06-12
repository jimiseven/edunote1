<?php
session_start();
require_once '../config/database.php';
require_once '../includes/asistencia_auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();
$userId = (int)$_SESSION['user_id'];
$userRole = (int)($_SESSION['user_role'] ?? 0);
$isAdminAsistencia = $userRole === 1;

function escaneo_get_lector(PDO $conn, $userId)
{
    $stmt = $conn->prepare("SELECT id_lector, alcance, tipo_lector FROM asistencia_lectores WHERE id_personal = ? AND estado = 1 LIMIT 1");
    $stmt->execute([(int)$userId]);
    $lector = $stmt->fetch(PDO::FETCH_ASSOC);
    return $lector ?: null;
}

function escaneo_lector_curso_habilitado(PDO $conn, $idLector, $idCurso)
{
    if ((int)$idLector <= 0 || (int)$idCurso <= 0) {
        return false;
    }
    $stmt = $conn->prepare("SELECT 1 FROM asistencia_lectores_cursos WHERE id_lector = ? AND id_curso = ? AND estado = 1 LIMIT 1");
    $stmt->execute([(int)$idLector, (int)$idCurso]);
    return (bool)$stmt->fetchColumn();
}

function escaneo_usuario_puede_registrar(PDO $conn, $isAdminAsistencia, $lectorInfo, $idCurso)
{
    if ($isAdminAsistencia) {
        return true;
    }
    if (!$lectorInfo || (int)$idCurso <= 0) {
        return false;
    }
    if (($lectorInfo['alcance'] ?? '') === 'GLOBAL') {
        return true;
    }
    if (($lectorInfo['alcance'] ?? '') === 'POR_CURSO') {
        return escaneo_lector_curso_habilitado($conn, (int)$lectorInfo['id_lector'], (int)$idCurso);
    }
    return false;
}

function escaneo_curso_doble_turno(PDO $conn, int $idCurso): bool
{
    if ($idCurso <= 0) {
        return false;
    }
    $stmt = $conn->prepare("SELECT doble_turno
        FROM asistencia_cursos_turnos
        WHERE id_curso = ? AND estado = 1
        LIMIT 1");
    $stmt->execute([$idCurso]);
    return (int)$stmt->fetchColumn() === 1;
}

function escaneo_curso_tarde_habilitado_fecha(PDO $conn, int $idCurso, string $fecha): bool
{
    if ($idCurso <= 0 || $fecha === '') {
        return false;
    }

    static $tablaExiste = null;
    if ($tablaExiste === null) {
        $stmtTbl = $conn->prepare("SHOW TABLES LIKE 'asistencia_curso_turno_dias'");
        $stmtTbl->execute();
        $tablaExiste = (bool)$stmtTbl->fetchColumn();
    }

    if (!$tablaExiste) {
        return true;
    }

    $diaSemana = (int)date('N', strtotime($fecha));

    $stmt = $conn->prepare("SELECT 1
        FROM asistencia_curso_turno_dias
        WHERE id_curso = ?
          AND turno = 'TARDE'
          AND estado = 1
          AND dia_semana = ?
          AND (fecha_inicio IS NULL OR fecha_inicio <= ?)
          AND (fecha_fin IS NULL OR fecha_fin >= ?)
        LIMIT 1");
    $stmt->execute([$idCurso, $diaSemana, $fecha, $fecha]);
    return (bool)$stmt->fetchColumn();
}

function escaneo_resolver_turno_y_puntualidad(PDO $conn, int $idCurso, int $idEstudiante, string $fecha, string $horaActual, string $turnoForzado = ''): array
{
    $obtenerHorarioGlobalTurno = static function (string $turno) use ($conn, $fecha): ?array {
        $stmt = $conn->prepare("SELECT hora_ingreso, tolerancia_min
            FROM asistencia_horarios_turno_global
            WHERE estado = 1 AND turno = ? AND ? BETWEEN fecha_inicio AND fecha_fin
            ORDER BY fecha_inicio DESC, id_horario_global DESC
            LIMIT 1");
        $stmt->execute([$turno, $fecha]);
        $h = $stmt->fetch(PDO::FETCH_ASSOC);
        return $h ?: null;
    };

    $esDoble = escaneo_curso_doble_turno($conn, $idCurso);

    $turnoForzado = strtoupper(trim($turnoForzado));
    if (!in_array($turnoForzado, ['MANANA', 'TARDE'], true)) {
        $turnoForzado = '';
    }

    if (!$esDoble) {
        if ($turnoForzado === 'TARDE') {
            throw new RuntimeException('Este curso no tiene turno TARDE habilitado.');
        }
        static $cache = [];
        $cacheKey = (string)$fecha;
        if (isset($cache[$cacheKey])) {
            $horario = $cache[$cacheKey];
        } else {
            $horario = $obtenerHorarioGlobalTurno('MANANA');
            if (!$horario) {
                $stmt = $conn->prepare("SELECT hora_ingreso, tolerancia_min
                    FROM asistencia_horarios_ingreso
                    WHERE estado = 1 AND ? BETWEEN fecha_inicio AND fecha_fin
                    ORDER BY fecha_inicio DESC, id_horario DESC
                    LIMIT 1");
                $stmt->execute([$fecha]);
                $horario = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            $cache[$cacheKey] = $horario ?: null;
        }

        if (!$horario) {
            return [
                'turno' => 'MANANA',
                'estado_puntualidad' => null,
                'hora_ingreso_programada' => null,
                'tolerancia_min' => null,
            ];
        }

        $toleranciaMin = max((int)($horario['tolerancia_min'] ?? 0), 0);
        $horaIngreso = (string)$horario['hora_ingreso'];
        $limite = date('H:i:s', strtotime($fecha . ' ' . $horaIngreso . ' +' . $toleranciaMin . ' minutes'));

        return [
            'turno' => 'MANANA',
            'estado_puntualidad' => ($horaActual <= $limite) ? 'TEMPRANO' : 'TARDE',
            'hora_ingreso_programada' => $horaIngreso,
            'tolerancia_min' => $toleranciaMin,
        ];
    }

    $horarios = [];
    foreach (['MANANA', 'TARDE'] as $turnoCfg) {
        $row = $obtenerHorarioGlobalTurno($turnoCfg);
        if ($row) {
            $horarios[$turnoCfg] = [
                'hora_ingreso' => (string)($row['hora_ingreso'] ?? ''),
                'tolerancia_min' => max((int)($row['tolerancia_min'] ?? 0), 0),
            ];
        }
    }

    if (empty($horarios)) {
        throw new RuntimeException('Curso doble turno sin horarios activos para hoy.');
    }

    if ($turnoForzado !== '') {
        if ($turnoForzado === 'TARDE' && !escaneo_curso_tarde_habilitado_fecha($conn, $idCurso, $fecha)) {
            return [
                'turno' => 'SIN_TARDE_HOY',
                'estado_puntualidad' => null,
                'hora_ingreso_programada' => null,
                'tolerancia_min' => null,
            ];
        }
        if (!isset($horarios[$turnoForzado])) {
            throw new RuntimeException('No hay horario global activo para el turno ' . $turnoForzado . ' en la fecha de hoy.');
        }

        $horaIngreso = $horarios[$turnoForzado]['hora_ingreso'];
        $toleranciaMin = $horarios[$turnoForzado]['tolerancia_min'];
        $limite = date('H:i:s', strtotime($fecha . ' ' . $horaIngreso . ' +' . $toleranciaMin . ' minutes'));

        return [
            'turno' => $turnoForzado,
            'estado_puntualidad' => ($horaActual <= $limite) ? 'TEMPRANO' : 'TARDE',
            'hora_ingreso_programada' => $horaIngreso,
            'tolerancia_min' => $toleranciaMin,
        ];
    }

    $tardeHabilitadaHoy = escaneo_curso_tarde_habilitado_fecha($conn, $idCurso, $fecha);
    $horaCorte = '12:00:00';

    if ($horaActual < $horaCorte) {
        $turnoAsignado = 'MANANA';
    } else {
        if (!$tardeHabilitadaHoy) {
            return [
                'turno' => 'SIN_TARDE_HOY',
                'estado_puntualidad' => null,
                'hora_ingreso_programada' => null,
                'tolerancia_min' => null,
            ];
        }
        $turnoAsignado = 'TARDE';
    }

    if (!isset($horarios[$turnoAsignado])) {
        throw new RuntimeException('No hay horario global activo para el turno ' . $turnoAsignado . ' en la fecha de hoy.');
    }

    $horaIngreso = $horarios[$turnoAsignado]['hora_ingreso'];
    $toleranciaMin = $horarios[$turnoAsignado]['tolerancia_min'];
    $limite = date('H:i:s', strtotime($fecha . ' ' . $horaIngreso . ' +' . $toleranciaMin . ' minutes'));

    return [
        'turno' => $turnoAsignado,
        'estado_puntualidad' => ($horaActual <= $limite) ? 'TEMPRANO' : 'TARDE',
        'hora_ingreso_programada' => $horaIngreso,
        'tolerancia_min' => $toleranciaMin,
    ];
}

$lectorInfo = $isAdminAsistencia ? null : escaneo_get_lector($conn, $userId);
if (!$isAdminAsistencia && !$lectorInfo) {
    http_response_code(403);
    echo '<h3>Acceso denegado</h3><p>Tu usuario no está habilitado para registrar asistencia.</p>';
    exit();
}

// Procesar registro de asistencia (cuando se escanea un QR)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    $raw_qr = trim((string)$_POST['qr_data']);
    $turnoManual = strtoupper(trim((string)($_POST['turno_manual'] ?? 'AUTO')));
    if (!in_array($turnoManual, ['AUTO', 'MANANA', 'TARDE'], true)) {
        $turnoManual = 'AUTO';
    }
    $turnoForzado = $turnoManual === 'AUTO' ? '' : $turnoManual;
    $id_estudiante = null;

    $qr_data = json_decode($raw_qr, true);
    if (is_array($qr_data) && isset($qr_data['id_estudiante'])) {
        $id_estudiante = (int)$qr_data['id_estudiante'];
    } elseif (preg_match('/^EST:(\d+)$/', $raw_qr, $m)) {
        $id_estudiante = (int)$m[1];
    }

    if ($id_estudiante > 0) {
        $stmtEstudiante = $conn->prepare("SELECT id_curso, nombres, apellido_paterno, apellido_materno FROM estudiantes WHERE id_estudiante = ? LIMIT 1");
        $stmtEstudiante->execute([$id_estudiante]);
        $estudiante = $stmtEstudiante->fetch(PDO::FETCH_ASSOC);
        $idCursoEscaneado = (int)($estudiante['id_curso'] ?? 0);

        if (!$estudiante) {
            echo json_encode([
                'success' => false,
                'message' => 'Estudiante no encontrado'
            ]);
            exit();
        }

        if (!escaneo_usuario_puede_registrar($conn, $isAdminAsistencia, $lectorInfo, $idCursoEscaneado)) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para registrar asistencia en este curso.'
            ]);
            exit();
        }

        $hoy = date('Y-m-d');
        $hora_actual = date('H:i:s');
        
        try {
            $puntualidad = escaneo_resolver_turno_y_puntualidad($conn, $idCursoEscaneado, $id_estudiante, $hoy, $hora_actual, $turnoForzado);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit();
        }

        if (($puntualidad['turno'] ?? '') === 'SIN_TARDE_HOY') {
            echo json_encode([
                'success' => false,
                'message' => 'Este curso no tiene clases en turno TARDE para hoy. No se permite un segundo registro.'
            ]);
            exit();
        }

        $stmt_check = $conn->prepare("SELECT id_asistencia, hora_entrada FROM asistencia WHERE id_estudiante = ? AND fecha = ? AND turno = ?");
        $stmt_check->execute([$id_estudiante, $hoy, $puntualidad['turno']]);
        $existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            $mensaje = 'El estudiante ya registró el turno ' . $puntualidad['turno'] . ' hoy a las ' . $existente['hora_entrada'];
            if ($puntualidad['turno'] === 'MANANA' && $idCursoEscaneado > 0) {
                $validacionTarde = asistencia_auth_turno_habilitado_para_fecha($conn, $idCursoEscaneado, 'TARDE', $hoy);
                if ($validacionTarde['habilitado']) {
                    $mensaje = 'Ya registraste MANANA hoy. Vuelve despues de las 12:00 para registrar TARDE.';
                } else {
                    $mensaje = 'Este curso no tiene turno TARDE. Ya registraste MANANA hoy.';
                }
            }
            echo json_encode([
                'success' => false,
                'message' => $mensaje
            ]);
        } else {

            // Registrar asistencia
            $stmt = $conn->prepare("INSERT INTO asistencia
                (id_estudiante, fecha, turno, hora_entrada, tipo_registro, estado_puntualidad, hora_ingreso_programada, tolerancia_min)
                VALUES (?, ?, ?, ?, 'QR', ?, ?, ?)");
            try {
                $stmt->execute([
                    $id_estudiante,
                    $hoy,
                    $puntualidad['turno'],
                    $hora_actual,
                    $puntualidad['estado_puntualidad'],
                    $puntualidad['hora_ingreso_programada'],
                    $puntualidad['tolerancia_min']
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Asistencia registrada correctamente',
                    'turno' => $puntualidad['turno'],
                    'estudiante' => $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno'] . ', ' . $estudiante['nombres'],
                    'nombres' => $estudiante['nombres'],
                    'apellido_paterno' => $estudiante['apellido_paterno'],
                    'apellido_materno' => $estudiante['apellido_materno'],
                    'hora' => $hora_actual,
                    'puntualidad' => $puntualidad['estado_puntualidad']
                ]);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    echo json_encode([
                        'success' => false,
                        'message' => 'El estudiante ya registró asistencia hoy.'
                    ]);
                } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al registrar la asistencia'
                ]);
                }
            }
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'QR inválido'
        ]);
    }
    exit();
}

// Obtener información del curso
$id_curso = $_GET['id_curso'] ?? null;
$curso_info = null;

if ($id_curso && !escaneo_usuario_puede_registrar($conn, $isAdminAsistencia, $lectorInfo, (int)$id_curso)) {
    http_response_code(403);
    echo '<h3>Acceso denegado</h3><p>No tienes permiso para operar en este curso.</p>';
    exit();
}

if ($id_curso) {
    $stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
    $stmt_curso->execute([$id_curso]);
    $curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);
}

// Obtener asistencia de hoy
$hoy = date('Y-m-d');
$asistencia_hoy = [];
if ($id_curso) {
    $stmt_asist = $conn->prepare("
        SELECT a.hora_entrada, e.nombres, e.apellido_paterno, e.apellido_materno
        FROM asistencia a
        JOIN estudiantes e ON a.id_estudiante = e.id_estudiante
        WHERE a.fecha = ? AND e.id_curso = ?
        ORDER BY a.hora_entrada DESC
    ");
    $stmt_asist->execute([$hoy, $id_curso]);
    $asistencia_hoy = $stmt_asist->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escanear Asistencia</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-anchor: none;
        }
        .scanner-container {
            max-width: 500px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }
        #reader {
            border-radius: 10px;
            overflow: hidden;
            height: 340px;
        }
        #result {
            position: absolute;
            top: 10px;
            left: 12px;
            right: 12px;
            z-index: 20;
            pointer-events: none;
        }
        #result .alert {
            margin-bottom: 0;
            min-height: 110px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.28);
            border-radius: 10px;
        }
        @media (max-width: 576px) {
            #reader {
                height: 300px;
            }
        }
        .result-card {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        .asistencia-list {
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="ri-qr-scan-line"></i> Escanear Asistencias
                            </h4>
                            <a href="asistencia.php<?= $id_curso ? '?id_curso=' . $id_curso : '' ?>" class="btn btn-sm btn-light">
                                <i class="ri-arrow-left-line"></i> Volver
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($curso_info): ?>
                            <div class="alert alert-info">
                                <strong>Curso:</strong> <?= htmlspecialchars($curso_info['nivel'] . ' ' . $curso_info['curso'] . ' "' . $curso_info['paralelo'] . '"') ?>
                                <br>
                                <strong>Fecha:</strong> <?= date('d/m/Y') ?>
                            </div>
                        <?php endif; ?>

                        <div class="alert alert-secondary mb-3">
                            <strong>Turno automatico:</strong> el sistema calcula MANANA o TARDE automaticamente segun la configuracion del curso y del dia.
                        </div>

                        <!-- Scanner -->
                        <div class="scanner-container mb-4">
                            <div id="reader"></div>
                            <div id="result"></div>
                        </div>

                        <!-- Lista de asistencia de hoy -->
                        <?php if (!empty($asistencia_hoy)): ?>
                            <div class="card mt-4">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="ri-check-double-line"></i> Asistencia Registrada Hoy (<span id="asistenciaCount"><?= count($asistencia_hoy) ?></span>)
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive asistencia-list">
                                        <table class="table table-striped mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Hora</th>
                                                    <th>Estudiante</th>
                                                </tr>
                                            </thead>
                                            <tbody id="asistenciaTableBody">
                                                <?php foreach ($asistencia_hoy as $asist): ?>
                                                    <tr>
                                                        <td><?= $asist['hora_entrada'] ?></td>
                                                        <td><?= htmlspecialchars($asist['apellido_paterno'] . ' ' . $asist['apellido_materno'] . ', ' . $asist['nombres']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let html5QrcodeInstance = null;
        let isProcessingScan = false;
        let scanUnlockTimer = null;

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function prependAsistenciaRow(hora, estudianteNombre) {
            const tbody = document.getElementById('asistenciaTableBody');
            const countEl = document.getElementById('asistenciaCount');
            if (!tbody || !countEl) {
                return;
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${escapeHtml(hora)}</td><td>${escapeHtml(estudianteNombre)}</td>`;
            tbody.prepend(tr);

            const currentCount = parseInt(countEl.textContent || '0', 10);
            countEl.textContent = Number.isNaN(currentCount) ? '1' : String(currentCount + 1);
        }

        function onScanSuccess(decodedText, decodedResult) {
            if (isProcessingScan) {
                return;
            }
            isProcessingScan = true;

            if (scanUnlockTimer) {
                clearTimeout(scanUnlockTimer);
                scanUnlockTimer = null;
            }

            // Enviar datos al servidor
            fetch('escanear_asistencia.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'qr_data=' + encodeURIComponent(decodedText)
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('result');
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success result-card">
                            <h5><i class="ri-check-circle-fill"></i> ¡Asistencia Registrada!</h5>
                            <p><strong>Estudiante:</strong> ${data.estudiante}</p>
                            <p><strong>Hora:</strong> ${data.hora}</p>
                            ${data.puntualidad ? `<p><strong>Puntualidad:</strong> ${data.puntualidad}</p>` : ''}
                        </div>
                    `;

                    prependAsistenciaRow(data.hora, data.estudiante);
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-warning result-card">
                            <h5><i class="ri-alert-fill"></i> Atención</h5>
                            <p>${data.message}</p>
                        </div>
                    `;
                }

                scanUnlockTimer = setTimeout(() => {
                    resultDiv.innerHTML = '';
                    isProcessingScan = false;
                }, 1600);
            })
            .catch(error => {
                console.error('Error:', error);
                const resultDiv = document.getElementById('result');
                resultDiv.innerHTML = `
                    <div class="alert alert-danger result-card">
                        <h5><i class="ri-error-warning-fill"></i> Error</h5>
                        <p>Error al procesar el QR</p>
                    </div>
                `;

                scanUnlockTimer = setTimeout(() => {
                    resultDiv.innerHTML = '';
                    isProcessingScan = false;
                }, 1600);
            });
        }

        function onScanFailure(error) {
            // Manejar errores de escaneo (opcional)
            // console.warn(`Code scan error = ${error}`);
        }

        // Iniciar el escáner
        document.addEventListener('DOMContentLoaded', function() {
            html5QrcodeInstance = new Html5Qrcode('reader');
            html5QrcodeInstance.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0,
                    rememberLastUsedCamera: true
                },
                onScanSuccess,
                onScanFailure
            ).catch((err) => {
                const resultDiv = document.getElementById('result');
                resultDiv.innerHTML = `
                    <div class="alert alert-danger result-card">
                        <h5><i class="ri-error-warning-fill"></i> Error</h5>
                        <p>No se pudo iniciar la camara. Verifica permisos.</p>
                    </div>
                `;
                console.error(err);
            });
        });
    </script>
</body>
</html>
