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

function asistencia_get_lector(PDO $conn, $userId)
{
    $stmt = $conn->prepare("SELECT id_lector, id_personal, alcance, tipo_lector, estado FROM asistencia_lectores WHERE id_personal = ? AND estado = 1 LIMIT 1");
    $stmt->execute([(int)$userId]);
    $lector = $stmt->fetch(PDO::FETCH_ASSOC);
    return $lector ?: null;
}

function asistencia_lector_curso_habilitado(PDO $conn, $idLector, $idCurso)
{
    if ((int)$idLector <= 0 || (int)$idCurso <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT 1 FROM asistencia_lectores_cursos WHERE id_lector = ? AND id_curso = ? AND estado = 1 LIMIT 1");
    $stmt->execute([(int)$idLector, (int)$idCurso]);
    return (bool)$stmt->fetchColumn();
}

function asistencia_usuario_puede_registrar(PDO $conn, $isAdminAsistencia, $lectorInfo, $idCurso)
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
        return asistencia_lector_curso_habilitado($conn, (int)$lectorInfo['id_lector'], (int)$idCurso);
    }

    return false;
}

function asistencia_curso_doble_turno(PDO $conn, int $idCurso): bool
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

function asistencia_curso_tarde_habilitado_fecha(PDO $conn, int $idCurso, string $fecha): bool
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

function asistencia_resolver_turno_y_puntualidad(PDO $conn, int $idCurso, int $idEstudiante, string $fecha, string $horaActual, string $turnoForzado = ''): array
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

    $turnoForzado = strtoupper(trim($turnoForzado));
    if (!in_array($turnoForzado, ['MANANA', 'TARDE'], true)) {
        $turnoForzado = '';
    }

    $esDoble = asistencia_curso_doble_turno($conn, $idCurso);

    if (!$esDoble) {
        if ($turnoForzado === 'TARDE') {
            throw new RuntimeException('Este curso no tiene turno TARDE habilitado.');
        }
        $horario = $obtenerHorarioGlobalTurno('MANANA');

        if (!$horario) {
            $stmtLegacy = $conn->prepare("SELECT hora_ingreso, tolerancia_min
                FROM asistencia_horarios_ingreso
                WHERE estado = 1 AND ? BETWEEN fecha_inicio AND fecha_fin
                ORDER BY fecha_inicio DESC, id_horario DESC
                LIMIT 1");
            $stmtLegacy->execute([$fecha]);
            $horario = $stmtLegacy->fetch(PDO::FETCH_ASSOC) ?: null;
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
        throw new RuntimeException('El curso esta configurado en doble turno, pero no tiene horarios por turno activos para hoy.');
    }

    if ($turnoForzado !== '') {
        if ($turnoForzado === 'TARDE' && !asistencia_curso_tarde_habilitado_fecha($conn, $idCurso, $fecha)) {
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

    $stmtReg = $conn->prepare("SELECT turno FROM asistencia WHERE id_estudiante = ? AND fecha = ?");
    $stmtReg->execute([$idEstudiante, $fecha]);
    $registros = $stmtReg->fetchAll(PDO::FETCH_COLUMN);
    $registrados = [];
    foreach ($registros as $t) {
        $turnoReg = strtoupper((string)$t);
        if ($turnoReg !== '') {
            $registrados[$turnoReg] = true;
        }
    }

    $yaManana = isset($registrados['MANANA']);
    $yaTarde = isset($registrados['TARDE']);
    $tardeHabilitadaHoy = asistencia_curso_tarde_habilitado_fecha($conn, $idCurso, $fecha);

    if (!$yaManana) {
        $turnoAsignado = 'MANANA';
    } elseif (!$yaTarde) {
        if (!$tardeHabilitadaHoy) {
            return [
                'turno' => 'SIN_TARDE_HOY',
                'estado_puntualidad' => null,
                'hora_ingreso_programada' => null,
                'tolerancia_min' => null,
            ];
        }
        $turnoAsignado = 'TARDE';
    } else {
        return [
            'turno' => 'COMPLETO',
            'estado_puntualidad' => null,
            'hora_ingreso_programada' => null,
            'tolerancia_min' => null,
        ];
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

$lectorInfo = $isAdminAsistencia ? null : asistencia_get_lector($conn, $userId);
if (!$isAdminAsistencia && !$lectorInfo) {
    http_response_code(403);
    echo '<h3>Acceso denegado</h3><p>Tu usuario no está habilitado para registrar asistencia.</p>';
    exit();
}

function sanitize_file_part($value)
{
    $value = trim((string)$value);
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value);
    $value = trim($value, '_');
    return $value !== '' ? $value : 'sin_nombre';
}

function fetch_remote_binary($url)
{
    for ($i = 0; $i < 2; $i++) {
        $data = @file_get_contents($url);
        if ($data !== false && strlen($data) > 100) {
            return $data;
        }
    }

    if (function_exists('curl_init')) {
        for ($i = 0; $i < 3; $i++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'Edunote/1.0',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && $statusCode >= 200 && $statusCode < 300 && strlen($response) > 100) {
                return $response;
            }
        }
    }

    return false;
}

function fetch_qr_binary_with_fallbacks($qrData, $size = 420)
{
    $encoded = urlencode((string)$qrData);
    $size = max(180, min((int)$size, 900));

    $urls = [
        'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . $encoded,
        'https://quickchart.io/qr?size=' . $size . '&text=' . $encoded,
        'https://chart.googleapis.com/chart?chs=' . $size . 'x' . $size . '&cht=qr&chl=' . $encoded,
    ];

    foreach ($urls as $url) {
        $binary = fetch_remote_binary($url);
        if ($binary !== false && strlen($binary) > 100) {
            return $binary;
        }
    }

    return false;
}

function build_simple_pdf_from_jpegs(array $jpegPages)
{
    if (empty($jpegPages)) {
        return false;
    }

    $objects = [];
    $kids = [];
    $pageObjectIds = [];
    $currentId = 3;

    foreach ($jpegPages as $page) {
        if (!isset($page['bytes'], $page['width'], $page['height'])) {
            continue;
        }
        $imgBytes = $page['bytes'];
        $w = max(1, (int)$page['width']);
        $h = max(1, (int)$page['height']);

        $imgId = $currentId++;
        $contentId = $currentId++;
        $pageId = $currentId++;

        $imgObj = "<< /Type /XObject /Subtype /Image /Width {$w} /Height {$h} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($imgBytes) . " >>\nstream\n" . $imgBytes . "\nendstream";
        $contentStream = "q\n{$w} 0 0 {$h} 0 0 cm\n/Im0 Do\nQ\n";
        $contentObj = "<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "endstream";
        $pageObj = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$w} {$h}] /Resources << /XObject << /Im0 {$imgId} 0 R >> >> /Contents {$contentId} 0 R >>";

        $objects[$imgId] = $imgObj;
        $objects[$contentId] = $contentObj;
        $objects[$pageId] = $pageObj;
        $pageObjectIds[] = $pageId;
        $kids[] = $pageId . ' 0 R';
    }

    if (empty($kids)) {
        return false;
    }

    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';

    ksort($objects, SORT_NUMERIC);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
    }

    $xrefStart = strlen($pdf);
    $maxId = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $maxId; $i++) {
        $off = isset($offsets[$i]) ? $offsets[$i] : 0;
        $pdf .= str_pad((string)$off, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }

    $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefStart}\n%%EOF";

    return $pdf;
}

function build_gafetes_pdf_binary_for_course(array $students, $courseLabel)
{
    if (empty($students)) {
        return false;
    }

    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        return false;
    }

    $pages = [];

    $pageW = 612;
    $pageH = 792;
    $cardW = (int)round((6 * 72) / 2.54);
    $cardH = (int)round((10 * 72) / 2.54);
    $qrSize = (int)round((4.5 * 72) / 2.54);
    $cols = 3;
    $rows = 2;
    $cardsPerPage = $cols * $rows;
    $gapX = (int)round(($pageW - ($cols * $cardW)) / ($cols + 1));
    $gapY = (int)round(($pageH - ($rows * $cardH)) / ($rows + 1));

    $total = count($students);
    for ($i = 0; $i < $total; $i += $cardsPerPage) {
        $sheet = imagecreatetruecolor($pageW, $pageH);
        if (!$sheet) {
            continue;
        }

        $white = imagecolorallocate($sheet, 255, 255, 255);
        $black = imagecolorallocate($sheet, 15, 15, 15);
        $dark = imagecolorallocate($sheet, 25, 25, 25);
        $gray = imagecolorallocate($sheet, 120, 120, 120);
        $border = imagecolorallocate($sheet, 170, 170, 170);
        $light = imagecolorallocate($sheet, 245, 245, 245);
        imagefill($sheet, 0, 0, $white);

        $title = 'Curso: ' . $courseLabel;
        imagestring($sheet, 5, max(10, (int)(($pageW - (strlen($title) * 9)) / 2)), 10, $title, $dark);

        for ($slot = 0; $slot < $cardsPerPage; $slot++) {
            $idx = $i + $slot;
            if ($idx >= $total) {
                break;
            }
            $student = $students[$idx];
            $row = (int)floor($slot / $cols);
            $col = $slot % $cols;

            $x = $gapX + $col * ($cardW + $gapX);
            $y = $gapY + $row * ($cardH + $gapY);

            imagefilledrectangle($sheet, $x, $y, $x + $cardW, $y + $cardH, $white);
            imagerectangle($sheet, $x, $y, $x + $cardW, $y + $cardH, $border);

            imagefilledrectangle($sheet, $x, $y, $x + $cardW, $y + 13, $black);
            imagestring($sheet, 2, $x + (int)(($cardW - (strlen('GAFETE ESTUDIANTIL') * 6)) / 2), $y + 3, 'GAFETE ESTUDIANTIL', $white);
            imagestring($sheet, 2, $x + (int)(($cardW - (strlen('Unidad Educativa Simon Bolivar') * 6)) / 2), $y + 16, 'Unidad Educativa Simon Bolivar', $dark);

            $qrData = 'EST:' . (int)$student['id_estudiante'];
            $qrBinary = fetch_qr_binary_with_fallbacks($qrData, 600);
            $qrImg = ($qrBinary !== false) ? @imagecreatefromstring($qrBinary) : false;
            $qrX = $x + (int)(($cardW - $qrSize) / 2);
            $qrY = $y + 42;
            if ($qrImg) {
                imagecopyresampled($sheet, $qrImg, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qrImg), imagesy($qrImg));
                imagedestroy($qrImg);
            } else {
                imagefilledrectangle($sheet, $qrX, $qrY, $qrX + $qrSize, $qrY + $qrSize, $light);
                imagerectangle($sheet, $qrX, $qrY, $qrX + $qrSize, $qrY + $qrSize, $border);
                imagestring($sheet, 2, $qrX + 24, $qrY + (int)($qrSize / 2), 'QR no disponible', $gray);
            }

            $idY = $qrY + $qrSize + 12;
            imagestring($sheet, 2, $x + (int)(($cardW - (strlen('Id estudiante: ' . (int)$student['id_estudiante']) * 6)) / 2), $idY, 'Id estudiante: ' . (int)$student['id_estudiante'], $dark);

            $fullName = strtoupper(trim(($student['apellido_paterno'] ?? '') . ' ' . ($student['apellido_materno'] ?? '') . ', ' . ($student['nombres'] ?? '')));
            $nameLines = explode("\n", wordwrap($fullName, 26, "\n", true));
            if (count($nameLines) > 2) {
                $nameLines = array_slice($nameLines, 0, 2);
                $last = $nameLines[1];
                $nameLines[1] = (strlen($last) > 3) ? (substr($last, 0, -3) . '...') : $last;
            }
            $nameY = $idY + 14;
            foreach ($nameLines as $line) {
                imagestring($sheet, 3, $x + (int)(($cardW - (strlen($line) * 7)) / 2), $nameY, $line, $black);
                $nameY += 12;
            }

            $courseText = trim(($student['nivel'] ?? '') . ' ' . ($student['curso'] ?? '') . ' "' . ($student['paralelo'] ?? '') . '"');
            $courseLines = explode("\n", wordwrap($courseText, 28, "\n", true));
            if (count($courseLines) > 2) {
                $courseLines = array_slice($courseLines, 0, 2);
            }
            $footY = $y + $cardH - 20;
            foreach ($courseLines as $line) {
                imagestring($sheet, 2, $x + (int)(($cardW - (strlen($line) * 6)) / 2), $footY, $line, $dark);
                $footY += 10;
            }
        }

        ob_start();
        imagejpeg($sheet, null, 88);
        $jpeg = ob_get_clean();
        imagedestroy($sheet);

        if ($jpeg !== false && $jpeg !== '') {
            $pages[] = [
                'bytes' => $jpeg,
                'width' => $pageW,
                'height' => $pageH,
            ];
        }
    }

    return build_simple_pdf_from_jpegs($pages);
}

function create_gafete_png_binary($qrBinary, array $student)
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $qrImg = false;
    if (is_string($qrBinary) && $qrBinary !== '') {
        $qrImg = @imagecreatefromstring($qrBinary);
    }

    $width = 1063;
    $height = 638;
    $canvas = imagecreatetruecolor($width, $height);

    $white = imagecolorallocate($canvas, 255, 255, 255);
    $primary = imagecolorallocate($canvas, 20, 20, 20);
    $dark = imagecolorallocate($canvas, 25, 25, 25);
    $gray = imagecolorallocate($canvas, 90, 90, 90);
    $border = imagecolorallocate($canvas, 210, 210, 210);

    imagefill($canvas, 0, 0, $white);
    imagerectangle($canvas, 0, 0, $width - 1, $height - 1, $border);

    imagefilledrectangle($canvas, 0, 0, $width, 90, $primary);
    imagestring($canvas, 5, 35, 34, utf8_decode('GAFETE ESTUDIANTIL - EDUNOTE'), imagecolorallocate($canvas, 255, 255, 255));

    $qrSize = 410;
    $qrX = 60;
    $qrY = 190;
    if ($qrImg) {
        imagecopyresampled($canvas, $qrImg, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qrImg), imagesy($qrImg));
        imagerectangle($canvas, $qrX, $qrY, $qrX + $qrSize, $qrY + $qrSize, $border);
    } else {
        imagefilledrectangle($canvas, $qrX, $qrY, $qrX + $qrSize, $qrY + $qrSize, imagecolorallocate($canvas, 245, 245, 245));
        imagerectangle($canvas, $qrX, $qrY, $qrX + $qrSize, $qrY + $qrSize, $border);
        imagestring($canvas, 5, $qrX + 120, $qrY + 190, 'QR NO DISPONIBLE', $gray);
    }

    $fullName = trim(($student['apellido_paterno'] ?? '') . ' ' . ($student['apellido_materno'] ?? '') . ', ' . ($student['nombres'] ?? ''));
    $curso = trim(($student['nivel'] ?? '') . ' ' . ($student['curso'] ?? '') . ' "' . ($student['paralelo'] ?? '') . '"');
    $idText = 'ID: ' . (int)($student['id_estudiante'] ?? 0);

    $x = 520;
    imagestring($canvas, 5, $x, 150, utf8_decode('ESTUDIANTE'), $primary);

    $nameLines = explode("\n", wordwrap($fullName, 28, "\n", true));
    $nameY = 195;
    foreach ($nameLines as $line) {
        imagestring($canvas, 5, $x, $nameY, utf8_decode($line), $dark);
        $nameY += 30;
    }

    imagestring($canvas, 5, $x, 350, utf8_decode($curso), $dark);

    imagestring($canvas, 5, $x, 440, utf8_decode($idText), $gray);
    imagestring($canvas, 3, $x, 500, utf8_decode('Uso institucional - Control de asistencia QR'), $gray);

    ob_start();
    imagepng($canvas, null, 9);
    $binary = ob_get_clean();

    if ($qrImg) {
        imagedestroy($qrImg);
    }
    imagedestroy($canvas);

    return $binary !== false ? $binary : false;
}

// Procesar registro de asistencia (cuando se escanea un QR desde el modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan_qr' && isset($_POST['qr_data'])) {
    header('Content-Type: application/json; charset=utf-8');
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
    } elseif (ctype_digit($raw_qr)) {
        $id_estudiante = (int)$raw_qr;
    }

    if ($id_estudiante > 0) {
        $stmtCursoEst = $conn->prepare("SELECT id_curso FROM estudiantes WHERE id_estudiante = ? LIMIT 1");
        $stmtCursoEst->execute([$id_estudiante]);
        $idCursoEscaneado = (int)$stmtCursoEst->fetchColumn();

        if (!asistencia_usuario_puede_registrar($conn, $isAdminAsistencia, $lectorInfo, $idCursoEscaneado)) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para registrar asistencia en este curso.'
            ]);
            exit();
        }

        $hoy = date('Y-m-d');
        $hora_actual = date('H:i:s');

        try {
            $puntualidad = asistencia_resolver_turno_y_puntualidad($conn, $idCursoEscaneado, $id_estudiante, $hoy, $hora_actual, $turnoForzado);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit();
        }

        if (($puntualidad['turno'] ?? '') === 'COMPLETO') {
            echo json_encode([
                'success' => false,
                'message' => 'El estudiante ya registró asistencia en MANANA y TARDE hoy.'
            ]);
        } elseif (($puntualidad['turno'] ?? '') === 'SIN_TARDE_HOY') {
            echo json_encode([
                'success' => false,
                'message' => 'Este curso no tiene clases en turno TARDE para hoy. No se permite un segundo registro.'
            ]);
        } else {
            $stmt_check = $conn->prepare("SELECT id_asistencia, hora_entrada FROM asistencia WHERE id_estudiante = ? AND fecha = ? AND turno = ?");
            $stmt_check->execute([$id_estudiante, $hoy, $puntualidad['turno']]);
            $existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($existente) {
                echo json_encode([
                    'success' => false,
                    'message' => 'El estudiante ya registró el turno ' . $puntualidad['turno'] . ' hoy a las ' . $existente['hora_entrada']
                ]);
                exit();
            }

            // Registrar asistencia
            $stmt = $conn->prepare("INSERT INTO asistencia
                (id_estudiante, fecha, turno, hora_entrada, tipo_registro, estado_puntualidad, hora_ingreso_programada, tolerancia_min)
                VALUES (?, ?, ?, ?, 'QR', ?, ?, ?)");
            if ($stmt->execute([
                $id_estudiante,
                $hoy,
                $puntualidad['turno'],
                $hora_actual,
                $puntualidad['estado_puntualidad'],
                $puntualidad['hora_ingreso_programada'],
                $puntualidad['tolerancia_min']
            ])) {
                // Obtener información del estudiante
                $stmt_est = $conn->prepare("SELECT nombres, apellido_paterno, apellido_materno FROM estudiantes WHERE id_estudiante = ?");
                $stmt_est->execute([$id_estudiante]);
                $estudiante = $stmt_est->fetch(PDO::FETCH_ASSOC) ?: [];
                $nombreEstudiante = trim(implode(' ', array_filter([
                    (string)($estudiante['apellido_paterno'] ?? ''),
                    (string)($estudiante['apellido_materno'] ?? ''),
                    (string)($estudiante['nombres'] ?? '')
                ])));
                if ($nombreEstudiante === '') {
                    $nombreEstudiante = 'ID ' . (int)$id_estudiante;
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Asistencia registrada correctamente (' . $puntualidad['turno'] . ')',
                    'id_estudiante' => (int)$id_estudiante,
                    'turno' => $puntualidad['turno'],
                    'estudiante' => $nombreEstudiante,
                    'hora' => $hora_actual,
                    'puntualidad' => $puntualidad['estado_puntualidad']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al registrar la asistencia'
                ]);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_qr_folder') {
    if (!$isAdminAsistencia) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'Solo administradores pueden generar ZIP de gafetes.'];
        header('Location: asistencia.php');
        exit();
    }

    $idCursoPost = isset($_POST['id_curso']) ? (int)$_POST['id_curso'] : 0;
    $idEstudiantePost = isset($_POST['id_estudiante']) ? (int)$_POST['id_estudiante'] : 0;
    $nivelRedirect = $_POST['nivel'] ?? '';

    if ($idCursoPost <= 0) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'Debes seleccionar un curso para generar el ZIP de gafetes.'];
        header('Location: asistencia.php');
        exit();
    }

    if (!class_exists('ZipArchive')) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'ZipArchive no está habilitado en este servidor.'];
        header('Location: asistencia.php?nivel=' . urlencode($nivelRedirect) . '&id_curso=' . $idCursoPost);
        exit();
    }

    $sql = "SELECT e.id_estudiante, e.nombres, e.apellido_paterno, e.apellido_materno, c.nivel, c.curso, c.paralelo
            FROM estudiantes e
            INNER JOIN cursos c ON c.id_curso = e.id_curso
            WHERE e.id_curso = ?";
    $params = [$idCursoPost];

    if ($idEstudiantePost > 0) {
        $sql .= " AND e.id_estudiante = ?";
        $params[] = $idEstudiantePost;
    }

    $sql .= " ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres";

    $stmtQrStudents = $conn->prepare($sql);
    $stmtQrStudents->execute($params);
    $studentsQr = $stmtQrStudents->fetchAll(PDO::FETCH_ASSOC);

    if (empty($studentsQr)) {
        $_SESSION['asistencia_flash'] = ['type' => 'warning', 'message' => 'No se encontraron estudiantes para generar gafetes.'];
        header('Location: asistencia.php?nivel=' . urlencode($nivelRedirect) . '&id_curso=' . $idCursoPost);
        exit();
    }

    $courseInfo = $studentsQr[0];
    $tempZip = tempnam(sys_get_temp_dir(), 'gafetes_zip_');
    if ($tempZip === false) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'No se pudo crear archivo temporal ZIP.'];
        header('Location: asistencia.php?nivel=' . urlencode($nivelRedirect) . '&id_curso=' . $idCursoPost);
        exit();
    }

    $zipPath = $tempZip . '.zip';
    @unlink($tempZip);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'No se pudo crear el archivo ZIP.'];
        header('Location: asistencia.php?nivel=' . urlencode($nivelRedirect) . '&id_curso=' . $idCursoPost);
        exit();
    }

    $ok = 0;
    $fail = 0;

    @set_time_limit(0);
    @ini_set('max_execution_time', '0');

    foreach ($studentsQr as $studentQr) {
        $qrData = 'EST:' . (int)$studentQr['id_estudiante'];
        $binary = fetch_qr_binary_with_fallbacks($qrData, 420);

        if ($binary === false) {
            $fail++;
            continue;
        }

        $fileName = sanitize_file_part($studentQr['apellido_paterno'] . '_' . $studentQr['nombres'])
            . '_' . (int)$studentQr['id_estudiante'] . '.png';
        $gafeteBinary = create_gafete_png_binary($binary, $studentQr);

        if ($gafeteBinary === false) {
            $gafeteBinary = $binary;
        }

        $saved = $zip->addFromString($fileName, $gafeteBinary);

        if (!$saved) {
            $fail++;
        } else {
            $ok++;
        }
    }

    $zip->close();

    if ($ok <= 0 || !is_file($zipPath)) {
        @unlink($zipPath);
        $_SESSION['asistencia_flash'] = [
            'type' => 'danger',
            'message' => "No se pudo generar el ZIP de gafetes. Fallidos: {$fail}."
        ];
        header('Location: asistencia.php?nivel=' . urlencode($nivelRedirect) . '&id_curso=' . $idCursoPost);
        exit();
    }

    $slugCurso = sanitize_file_part($courseInfo['nivel'] . '_' . $courseInfo['curso'] . '_' . $courseInfo['paralelo']);
    $zipName = 'gafetes_' . $slugCurso . '_' . date('Ymd_His') . '.zip';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Pragma: public');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    readfile($zipPath);
    @unlink($zipPath);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_all_school_zip') {
    if (!$isAdminAsistencia) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'Solo administradores pueden descargar el ZIP global.'];
        header('Location: asistencia.php');
        exit();
    }

    if (!class_exists('ZipArchive')) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'ZipArchive no está habilitado en este servidor.'];
        header('Location: asistencia.php');
        exit();
    }

    if (!function_exists('imagecreatetruecolor') || !function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'La extension GD no esta habilitada. No se pueden generar gafetes PDF.'];
        header('Location: asistencia.php');
        exit();
    }

    $stmtAll = $conn->query("SELECT e.id_estudiante, e.nombres, e.apellido_paterno, e.apellido_materno,
            c.nivel, c.curso, c.paralelo
        FROM estudiantes e
        INNER JOIN cursos c ON c.id_curso = e.id_curso
        ORDER BY FIELD(c.nivel, 'Inicial', 'Primaria', 'Secundaria'), c.curso, c.paralelo,
            e.apellido_paterno, e.apellido_materno, e.nombres");
    $studentsAll = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    if (empty($studentsAll)) {
        $_SESSION['asistencia_flash'] = ['type' => 'warning', 'message' => 'No se encontraron estudiantes para generar gafetes.'];
        header('Location: asistencia.php');
        exit();
    }

    $tempZip = tempnam(sys_get_temp_dir(), 'gafetes_all_zip_');
    if ($tempZip === false) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'No se pudo crear archivo temporal ZIP.'];
        header('Location: asistencia.php');
        exit();
    }

    $zipPath = $tempZip . '.zip';
    @unlink($tempZip);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'No se pudo crear el archivo ZIP.'];
        header('Location: asistencia.php');
        exit();
    }

    $ok = 0;
    $fail = 0;

    $groupedByCourse = [];
    foreach ($studentsAll as $student) {
        $nivel = (string)$student['nivel'];
        $cursoFolder = sanitize_file_part($student['curso'] . '_' . $student['paralelo']);
        $courseKey = $nivel . '||' . $cursoFolder;
        if (!isset($groupedByCourse[$courseKey])) {
            $groupedByCourse[$courseKey] = [
                'nivel' => $nivel,
                'curso_folder' => $cursoFolder,
                'course_label' => trim($student['nivel'] . ' ' . $student['curso'] . ' "' . $student['paralelo'] . '"'),
                'students' => [],
            ];
        }
        $groupedByCourse[$courseKey]['students'][] = $student;
    }

    foreach (['Inicial', 'Primaria', 'Secundaria'] as $nivelBase) {
        $zip->addEmptyDir(sanitize_file_part($nivelBase));
    }

    @set_time_limit(0);
    @ini_set('max_execution_time', '0');

    foreach ($groupedByCourse as $courseData) {
        $nivelFolder = sanitize_file_part($courseData['nivel']);
        $cursoFolder = $courseData['curso_folder'];
        $baseFolder = $nivelFolder . '/' . $cursoFolder;
        $zip->addEmptyDir($baseFolder);

        $pdfBinary = build_gafetes_pdf_binary_for_course($courseData['students'], $courseData['course_label']);
        if ($pdfBinary === false || $pdfBinary === '') {
            $fail++;
            continue;
        }

        $pdfName = 'Gafetes_' . $cursoFolder . '.pdf';
        $saved = $zip->addFromString($baseFolder . '/' . $pdfName, $pdfBinary);
        if ($saved) {
            $ok++;
        } else {
            $fail++;
        }
    }

    $zip->close();

    if ($ok <= 0 || !is_file($zipPath)) {
        @unlink($zipPath);
        $_SESSION['asistencia_flash'] = [
            'type' => 'danger',
            'message' => "No se pudo generar el ZIP global de gafetes. Fallidos: {$fail}."
        ];
        header('Location: asistencia.php');
        exit();
    }

    $zipName = 'gafetes_todo_colegio_' . date('Ymd_His') . '.zip';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Pragma: public');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    readfile($zipPath);
    @unlink($zipPath);
    exit();
}

// Obtener cursos por nivel
$nivelesPermitidos = ['Inicial', 'Primaria', 'Secundaria'];
$id_curso = $_GET['id_curso'] ?? null;
$nivel = $_GET['nivel'] ?? '';
if (!in_array($nivel, $nivelesPermitidos, true)) {
    $nivel = '';
}

if ($nivel === '' && !empty($id_curso)) {
    $stmtNivel = $conn->prepare("SELECT nivel FROM cursos WHERE id_curso = ? LIMIT 1");
    $stmtNivel->execute([(int)$id_curso]);
    $nivelCurso = $stmtNivel->fetchColumn();
    if ($nivelCurso && in_array($nivelCurso, $nivelesPermitidos, true)) {
        $nivel = $nivelCurso;
    }
}

if ($isAdminAsistencia || (($lectorInfo['alcance'] ?? '') === 'GLOBAL')) {
    if ($nivel !== '') {
        $stmt_cursos = $conn->prepare("SELECT id_curso, nivel, curso, paralelo FROM cursos WHERE nivel = ? ORDER BY curso, paralelo");
        $stmt_cursos->execute([$nivel]);
    } else {
        $stmt_cursos = $conn->query("SELECT id_curso, nivel, curso, paralelo FROM cursos ORDER BY nivel, curso, paralelo");
    }
} else {
    if ($nivel !== '') {
        $stmt_cursos = $conn->prepare("SELECT c.id_curso, c.nivel, c.curso, c.paralelo
            FROM cursos c
            INNER JOIN asistencia_lectores_cursos alc ON alc.id_curso = c.id_curso
            WHERE alc.id_lector = ? AND alc.estado = 1 AND c.nivel = ?
            ORDER BY c.curso, c.paralelo");
        $stmt_cursos->execute([(int)$lectorInfo['id_lector'], $nivel]);
    } else {
        $stmt_cursos = $conn->prepare("SELECT c.id_curso, c.nivel, c.curso, c.paralelo
            FROM cursos c
            INNER JOIN asistencia_lectores_cursos alc ON alc.id_curso = c.id_curso
            WHERE alc.id_lector = ? AND alc.estado = 1
            ORDER BY FIELD(c.nivel, 'Inicial', 'Primaria', 'Secundaria'), c.curso, c.paralelo");
        $stmt_cursos->execute([(int)$lectorInfo['id_lector']]);
    }
}
$cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);

$cursoIdsPermitidos = array_map('intval', array_column($cursos, 'id_curso'));
if ($id_curso && !$isAdminAsistencia && !in_array((int)$id_curso, $cursoIdsPermitidos, true)) {
    $_SESSION['asistencia_flash'] = ['type' => 'warning', 'message' => 'No tienes permiso para acceder a ese curso.'];
    $id_curso = null;
}

// Curso seleccionado
$estudiantes = [];

if ($id_curso) {
    $stmt_est = $conn->prepare("
        SELECT e.id_estudiante, e.nombres, e.apellido_paterno, e.apellido_materno, 
               c.nivel, c.curso, c.paralelo
        FROM estudiantes e
        JOIN cursos c ON e.id_curso = c.id_curso
        WHERE e.id_curso = ?
        ORDER BY e.apellido_paterno, e.apellido_materno, e.nombres
    ");
    $stmt_est->execute([$id_curso]);
    $estudiantes = $stmt_est->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistencia - Generación de QR</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .qr-card {
            border: 1px solid #b8b8b8;
            border-radius: 16px;
            padding: 12px 12px 14px;
            text-align: center;
            margin: 10px;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.1);
        }
        .qr-card img {
            margin: 0.35rem auto 0;
            display: block;
            width: 170px;
            height: 170px;
            border-radius: 10px;
            border: 1px solid #b8b8b8;
            background: #ffffff;
            padding: 6px;
        }
        .badge-title {
            font-weight: 700;
            font-size: 0.74rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #ffffff;
            background: #111111;
            border-radius: 999px;
            padding: 6px 10px;
            margin-bottom: 8px;
            display: inline-block;
        }
        .student-name {
            font-weight: 700;
            margin-top: 10px;
            font-size: 0.92rem;
            color: #111111;
            line-height: 1.2;
            text-align: center;
        }
        .student-info {
            font-size: 0.8rem;
            color: #222222;
            line-height: 1.2;
            text-align: center;
        }
        .qr-placeholder {
            width: 150px;
            height: 150px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: #6c757d;
            font-size: 0.8rem;
        }
        .scan-fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.5);
            cursor: pointer;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .scan-fab:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
        }
        #reader {
            border-radius: 10px;
            overflow: hidden;
            height: 360px;
            background: rgba(0, 0, 0, 0.25);
        }
        #reader video {
            border-radius: 10px;
            width: 100% !important;
            height: 100% !important;
            object-fit: cover;
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
        .scan-modal .modal-content {
            background: linear-gradient(165deg, #0f2742 0%, #19436f 55%, #1f5f8b 100%);
            color: #f4f8fc;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 14px;
        }
        .scan-modal .modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .scan-modal .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        .pdf-loading-modal .modal-content {
            border-radius: 14px;
            border: 0;
        }
        .pdf-loading-modal .modal-body {
            padding: 1.5rem;
            text-align: center;
        }
        .pdf-loading-modal .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        .pdf-progress-wrap {
            margin-top: 12px;
            text-align: left;
        }
        .pdf-progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 6px;
        }
        .pdf-progress-track {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: #e9ecef;
            overflow: hidden;
        }
        .pdf-progress-fill {
            height: 100%;
            width: 1%;
            background: linear-gradient(90deg, #198754 0%, #20c997 100%);
            transition: width 0.25s ease;
        }
        .pdf-progress-eta {
            margin-top: 6px;
            font-size: 0.8rem;
            color: #6c757d;
            min-height: 1.1rem;
        }
        .manual-id-card {
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.24);
            border-radius: 12px;
            padding: 14px;
        }
        .manual-id-input {
            background: rgba(255,255,255,0.95);
            border: 0;
        }
        .manual-id-help {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-top: 6px;
        }
        .scan-mode-toggle {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }
        .scan-mode-btn {
            border: 1px solid rgba(255,255,255,0.34);
            background: rgba(255,255,255,0.09);
            color: #e8f3ff;
            border-radius: 10px;
            padding: 8px 10px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .scan-mode-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .scan-mode-btn.is-active {
            background: #e9f4ff;
            color: #17416a;
            border-color: #e9f4ff;
            box-shadow: 0 6px 20px rgba(8, 28, 48, 0.28);
        }
        .scan-kpi-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .scan-kpi {
            flex: 1 1 140px;
            border: 1px solid rgba(255,255,255,0.24);
            background: rgba(255,255,255,0.09);
            border-radius: 10px;
            padding: 8px 10px;
            line-height: 1.15;
        }
        .scan-kpi-label {
            font-size: 0.74rem;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .scan-kpi-value {
            font-size: 1.1rem;
            font-weight: 700;
        }
        .speed-tip {
            margin-top: 8px;
            font-size: 0.8rem;
            opacity: 0.95;
        }
        .reader-pane {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            min-height: 360px;
            transition: all 0.25s ease;
            border: 1px solid rgba(255,255,255,0.20);
            background: rgba(0,0,0,0.15);
        }
        .scan-result-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            z-index: 30;
            pointer-events: none;
        }
        .reader-collapsed-hint {
            display: none;
            padding: 16px;
            border-radius: 10px;
            border: 2px dashed rgba(255,255,255,0.6);
            background: rgba(255,255,255,0.15);
            text-align: center;
            cursor: pointer;
        }
        .scan-layout.manual-mode #reader {
            display: none;
        }
        .scan-layout.manual-mode .scan-result-overlay {
            display: none;
        }
        .scan-layout.manual-mode .reader-collapsed-hint {
            display: block;
        }
        .scan-layout.manual-mode .col-lg-8 {
            order: 2;
        }
        .scan-layout.manual-mode .col-lg-4 {
            order: 1;
        }
        #scan-result .alert,
        #scan-result-manual .alert {
            margin-bottom: 0;
            min-height: 96px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.28);
            border-radius: 10px;
        }
        #scan-result-manual {
            min-height: 90px;
        }
        #scan-result .alert-success,
        #scan-result-manual .alert-success {
            border: 2px solid #198754;
        }
        #scan-result .alert-warning,
        #scan-result-manual .alert-warning {
            border: 2px solid #ffc107;
        }
        #scan-result .alert-danger,
        #scan-result-manual .alert-danger {
            border: 2px solid #dc3545;
        }
        @media (max-width: 991.98px) {
            .asistencia-main {
                padding-top: 3.2rem !important;
            }
            .d-flex.justify-content-between.align-items-center.mb-4.no-print {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px;
                padding-left: 56px;
            }
            .d-flex.justify-content-between.align-items-center.mb-4.no-print > div {
                width: 100%;
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            .d-flex.justify-content-between.align-items-center.mb-4.no-print > div .btn {
                flex: 1 1 auto;
            }
            .scan-fab {
                width: 58px;
                height: 58px;
                right: 16px;
                bottom: 16px;
                font-size: 24px;
            }
            #reader {
                height: 300px;
            }
            .reader-pane {
                min-height: 300px;
            }
            .scan-modal .modal-body {
                padding-bottom: calc(1rem + env(safe-area-inset-bottom));
            }
            .scan-layout.manual-mode .reader-pane {
                min-height: 140px;
            }
            .scan-layout.manual-mode #scan-result-manual {
                position: sticky;
                bottom: 0;
                z-index: 5;
                padding-bottom: 4px;
            }
            .qr-card {
                margin: 6px;
                padding: 10px;
            }
            .qr-card img {
                width: 140px;
                height: 140px;
            }
        }
        @media (max-width: 767.98px) {
            .asistencia-main {
                padding-top: 0.75rem !important;
            }
            .asistencia-main .card {
                border-radius: 12px;
            }
            .asistencia-main .h3 {
                font-size: 1.2rem;
            }
            .scan-mode-btn {
                font-size: 0.85rem;
                padding: 8px;
            }
            .scan-kpi-value {
                font-size: 1.02rem;
            }
            .manual-id-card {
                padding: 12px;
            }
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .qr-card {
                break-inside: avoid;
                page-break-inside: avoid;
                width: 9cm;
                min-height: 5.8cm;
                margin: 0.2cm auto;
                box-shadow: none;
                background: #fff;
                border: 1px solid #24456d;
                border-radius: 0;
                padding: 0.25cm;
            }
            .qr-card img {
                width: 3.2cm !important;
                height: 3.2cm !important;
                margin: 0.05cm auto 0.1cm;
            }
            .badge-title {
                font-size: 10pt;
                margin-bottom: 0.15cm;
            }
            .student-name {
                font-size: 10pt;
                margin-top: 0;
            }
            .student-info {
                font-size: 9pt;
            }
            .scan-fab {
                display: none !important;
            }
            .no-print-student {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <!-- Main content -->
            <main class="w-100 px-md-4 position-relative py-4 asistencia-main">
                <?php if (isset($_SESSION['asistencia_flash'])): ?>
                    <?php $flash = $_SESSION['asistencia_flash']; unset($_SESSION['asistencia_flash']); ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> no-print" role="alert">
                        <div><?= htmlspecialchars($flash['message']) ?></div>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h1 class="h3">
                        <i class="ri-qr-code-line"></i> Asistencia - Generación de QR
                    </h1>
                    <div>
                        <?php if ($id_curso && !empty($estudiantes) && $isAdminAsistencia): ?>
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="ri-printer-line"></i> Imprimir curso seleccionado
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$isAdminAsistencia): ?>
                    <div class="card mb-4 no-print">
                        <div class="card-body">
                            <h5 class="mb-3"><i class="ri-shield-check-line"></i> Permisos de lecturado</h5>
                            <?php if (($lectorInfo['alcance'] ?? '') === 'GLOBAL'): ?>
                                <div class="alert alert-success mb-0">
                                    Tienes acceso para lecturar <strong>todos los cursos</strong>.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-3">
                                    Cursos habilitados para tu usuario:
                                </div>
                                <?php if (!empty($cursos)): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($cursos as $curso): ?>
                                            <span class="badge bg-primary">
                                                <?= htmlspecialchars($curso['nivel'] . ' ' . $curso['curso'] . ' "' . $curso['paralelo'] . '"') ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0">
                                        No tienes cursos habilitados actualmente. Contacta al administrador.
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <div class="mt-3">
                                <span class="badge bg-dark">Tipo: <?= htmlspecialchars(($lectorInfo['tipo_lector'] ?? 'LECTURADOR')) ?></span>
                            </div>

                            <div class="mt-3">
                                <button type="button" class="btn btn-success" onclick="openScanner()">
                                    <i class="ri-qr-scan-line"></i> Habilitar pantalla de lecturado
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Selector de curso -->
                <?php if ($isAdminAsistencia): ?>
                    <div class="card mb-4 no-print">
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="row align-items-end">
                                    <div class="col-md-4">
                                        <label for="nivel" class="form-label">Seleccionar Nivel</label>
                                        <select class="form-select" id="nivel" name="nivel" onchange="changeLevel(this)">
                                            <option value="">-- Seleccione un nivel --</option>
                                            <?php foreach ($nivelesPermitidos as $nivelOpt): ?>
                                                <option value="<?= htmlspecialchars($nivelOpt) ?>" <?= ($nivel === $nivelOpt) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($nivelOpt) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="id_curso" class="form-label">Seleccionar Curso</label>
                                        <select class="form-select" id="id_curso" name="id_curso" onchange="this.form.submit()" <?= $nivel === '' ? 'disabled' : '' ?>>
                                            <option value="">-- Seleccione un curso --</option>
                                            <?php foreach ($cursos as $curso): ?>
                                                <option value="<?= $curso['id_curso'] ?>" <?= ($id_curso == $curso['id_curso']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($curso['nivel'] . ' ' . $curso['curso'] . ' "' . $curso['paralelo'] . '"') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </form>

                            <div class="mt-3">
                                <button type="button" class="btn btn-success" id="btnZipTodoColegio" onclick="generarZipTodoColegio()">
                                    <i class="ri-download-cloud-2-line"></i> Descargar ZIP de todo el colegio
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Lista de estudiantes con QR -->
                <?php if ($id_curso && !empty($estudiantes) && $isAdminAsistencia): ?>
                    <div class="alert alert-info no-print">
                        <i class="ri-information-line"></i> 
                        Se generará un código QR único para cada estudiante. Escanee el QR con el celular para registrar la asistencia.
                    </div>

                    <div class="mb-3 d-flex gap-2 no-print">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="generate_qr_folder">
                            <input type="hidden" name="id_curso" value="<?= (int)$id_curso ?>">
                            <input type="hidden" name="nivel" value="<?= htmlspecialchars($nivel) ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="ri-download-cloud-2-line"></i> Descargar ZIP de gafetes (curso)
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger" id="btnGenerarPdf" onclick="generarPdfGafetes()">
                            <i class="ri-file-pdf-2-line"></i> Generar PDF
                        </button>
                    </div>

                    <div class="row" id="qr-container">
                        <?php foreach ($estudiantes as $est): ?>
                            <?php
                                $qrData = 'EST:' . $est['id_estudiante'];
                                $qrDataEncoded = urlencode($qrData);
                                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . $qrDataEncoded;
                            ?>
                            <div class="col-md-3 col-sm-4 col-6 qr-card-wrapper" id="student-card-<?= (int)$est['id_estudiante'] ?>">
                                <div class="qr-card">
                                    <div class="badge-title">GAFETE ESTUDIANTIL</div>
                                    <img src="<?= $qrUrl ?>" alt="QR <?= htmlspecialchars($est['apellido_paterno'] . ' ' . $est['apellido_materno']) ?>" 
                                         onerror="this.onerror=null; this.src='https://via.placeholder.com/150?text=Error+QR';">
                                    <div class="student-name">
                                        <?= htmlspecialchars($est['apellido_paterno'] . ' ' . $est['apellido_materno'] . ', ' . $est['nombres']) ?>
                                    </div>
                                    <div class="student-info">
                                        <?= htmlspecialchars($est['nivel'] . ' ' . $est['curso'] . ' "' . $est['paralelo'] . '"') ?>
                                    </div>
                                    <div class="student-info mt-1">
                                        ID: <?= $est['id_estudiante'] ?>
                                    </div>
                                    <div class="mt-2 no-print d-grid gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="printStudent(<?= (int)$est['id_estudiante'] ?>)">
                                            <i class="ri-printer-line"></i> Imprimir este estudiante
                                        </button>
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="generate_qr_folder">
                                            <input type="hidden" name="id_curso" value="<?= (int)$id_curso ?>">
                                            <input type="hidden" name="id_estudiante" value="<?= (int)$est['id_estudiante'] ?>">
                                            <input type="hidden" name="nivel" value="<?= htmlspecialchars($nivel) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                                <i class="ri-download-2-line"></i> Descargar ZIP (este estudiante)
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($id_curso): ?>
                    <div class="alert alert-warning">
                        <i class="ri-alert-line"></i> <?= $isAdminAsistencia ? 'No hay estudiantes matriculados en este curso.' : 'No hay estudiantes disponibles o no tienes acceso a este curso.' ?>
                    </div>
                <?php endif; ?>

                <!-- Botón para ir al escáner -->
                <?php if ($id_curso && $isAdminAsistencia): ?>
                    <div class="mt-4 no-print">
                        <a href="escanear_asistencia.php<?= $id_curso ? ('?id_curso=' . (int)$id_curso) : '' ?>" class="btn btn-success btn-lg">
                            <i class="ri-qr-scan-line"></i> Escanear Asistencia (Página completa)
                        </a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Botón flotante para escanear -->
    <?php if ($id_curso && $isAdminAsistencia): ?>
        <button class="scan-fab no-print" onclick="openScanner()" title="Escanear QR">
            <i class="ri-qr-scan-2-line"></i>
        </button>
    <?php endif; ?>

    <!-- Modal para escanear QR -->
    <div class="modal fade scan-modal" id="scannerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ri-qr-scan-line"></i> Escanear Asistencia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 align-items-start scan-layout" id="scanLayout">
                        <div class="col-lg-8">
                            <div class="reader-pane">
                                <div id="reader"></div>
                                <div class="scan-result-overlay">
                                    <div id="scan-result"></div>
                                </div>
                                <div id="readerCollapsedHint" class="reader-collapsed-hint" onclick="setManualMode(false)">
                                    <i class="ri-qr-scan-line"></i>
                                    Lector QR minimizado. Toca aquí para volver al tamaño grande.
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="manual-id-card">
                                <div class="scan-kpi-bar">
                                    <div class="scan-kpi">
                                        <div class="scan-kpi-label">Registros en sesion</div>
                                        <div class="scan-kpi-value" id="kpiSuccessCount">0</div>
                                    </div>
                                    <div class="scan-kpi">
                                        <div class="scan-kpi-label">Ultimo ID</div>
                                        <div class="scan-kpi-value" id="kpiLastId">-</div>
                                    </div>
                                </div>
                                <div class="scan-mode-toggle">
                                    <button type="button" class="scan-mode-btn is-active" id="modeQrBtn" onclick="setManualMode(false)">
                                        <i class="ri-qr-scan-2-line"></i> Escanear QR
                                    </button>
                                    <button type="button" class="scan-mode-btn" id="modeManualBtn" onclick="setManualMode(true)">
                                        <i class="ri-keyboard-box-line"></i> Registro manual
                                    </button>
                                </div>
                                <div class="alert alert-secondary mb-2">
                                    <strong>Turno automatico:</strong> el sistema define MANANA o TARDE segun configuracion del curso y del dia.
                                </div>
                                <label for="manualStudentId" class="form-label mb-1"><strong>Registrar por ID</strong></label>
                                <div class="input-group">
                                    <input type="number" min="1" step="1" id="manualStudentId" class="form-control manual-id-input" placeholder="Ej: 154">
                                    <button class="btn btn-light" type="button" onclick="registerById()">
                                        <i class="ri-user-add-line"></i> Registrar
                                    </button>
                                </div>
                                <div class="manual-id-help">
                                    Usar cuando el estudiante no tenga su QR. Solo ingrese el ID del estudiante.
                                </div>
                                <div class="speed-tip" id="scanSpeedTip">
                                    Flujo rapido: escriba ID -> Enter -> siguiente ID.
                                </div>
                                <div id="scan-result-manual" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade pdf-loading-modal" id="pdfLoadingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                    <h6 class="mt-3 mb-1">Generando PDF</h6>
                    <p class="text-muted mb-0">Por favor espere. No cierre esta ventana.</p>
                    <div class="pdf-progress-wrap">
                        <div class="pdf-progress-label">
                            <span id="pdfProgressText">Preparando...</span>
                            <span id="pdfProgressPercent">1%</span>
                        </div>
                        <div class="pdf-progress-track">
                            <div class="pdf-progress-fill" id="pdfProgressFill"></div>
                        </div>
                        <div class="pdf-progress-eta" id="pdfProgressEta"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.0/dist/feather.min.js"></script>
    <script>
        feather.replace();

        const datosGafetesPdf = <?php
            $datosPdf = [];
            if ($id_curso && !empty($estudiantes) && $isAdminAsistencia) {
                foreach ($estudiantes as $estPdf) {
                    $nombreMayus = strtoupper(trim(($estPdf['apellido_paterno'] ?? '') . ' ' . ($estPdf['apellido_materno'] ?? '') . ', ' . ($estPdf['nombres'] ?? '')));
                    $qrDataPdf = 'EST:' . (int)$estPdf['id_estudiante'];
                    $datosPdf[] = [
                        'id_estudiante' => (int)$estPdf['id_estudiante'],
                        'nombre' => $nombreMayus,
                        'curso_texto' => trim(($estPdf['nivel'] ?? '') . ' ' . ($estPdf['curso'] ?? '') . ' "' . ($estPdf['paralelo'] ?? '') . '"'),
                        'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=' . urlencode($qrDataPdf),
                    ];
                }
            }
            echo json_encode($datosPdf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>;

        const datosGafetesZip = <?php
            $datosZip = [];
            if ($isAdminAsistencia) {
                try {
                    $stmtZip = $conn->query("SELECT e.id_estudiante, e.nombres, e.apellido_paterno, e.apellido_materno, c.nivel, c.curso, c.paralelo
                        FROM estudiantes e
                        INNER JOIN cursos c ON c.id_curso = e.id_curso
                        ORDER BY FIELD(c.nivel, 'Inicial', 'Primaria', 'Secundaria'), c.curso, c.paralelo, e.apellido_paterno, e.apellido_materno, e.nombres");
                    $rowsZip = $stmtZip ? $stmtZip->fetchAll(PDO::FETCH_ASSOC) : [];
                    foreach ($rowsZip as $rowZip) {
                        $qrDataZip = 'EST:' . (int)$rowZip['id_estudiante'];
                        $datosZip[] = [
                            'id_estudiante' => (int)$rowZip['id_estudiante'],
                            'nombre' => strtoupper(trim(($rowZip['apellido_paterno'] ?? '') . ' ' . ($rowZip['apellido_materno'] ?? '') . ', ' . ($rowZip['nombres'] ?? ''))),
                            'nivel' => (string)($rowZip['nivel'] ?? ''),
                            'curso' => (string)($rowZip['curso'] ?? ''),
                            'paralelo' => (string)($rowZip['paralelo'] ?? ''),
                            'curso_texto' => trim(($rowZip['nivel'] ?? '') . ' ' . ($rowZip['curso'] ?? '') . ' "' . ($rowZip['paralelo'] ?? '') . '"'),
                            'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=' . urlencode($qrDataZip),
                        ];
                    }
                } catch (Throwable $e) {
                    $datosZip = [];
                }
            }
            echo json_encode($datosZip, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>;

        function cmToPt(cm) {
            return (cm * 72) / 2.54;
        }

        let isGeneratingPdf = false;

        function obtenerModalPdfCarga() {
            const modalEl = document.getElementById('pdfLoadingModal');
            if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return null;
            }
            return bootstrap.Modal.getOrCreateInstance(modalEl);
        }

        function mostrarCargaPdf() {
            const botonPdf = document.getElementById('btnGenerarPdf');
            if (botonPdf) {
                botonPdf.disabled = true;
            }
            const modal = obtenerModalPdfCarga();
            if (modal) {
                modal.show();
            }
        }

        function actualizarProgresoPdf(percent, text, etaText) {
            const fill = document.getElementById('pdfProgressFill');
            const pct = document.getElementById('pdfProgressPercent');
            const lbl = document.getElementById('pdfProgressText');
            const eta = document.getElementById('pdfProgressEta');

            const safe = Math.max(1, Math.min(100, Math.round(percent)));
            if (fill) fill.style.width = safe + '%';
            if (pct) pct.textContent = safe + '%';
            if (lbl && text) lbl.textContent = text;
            if (eta) eta.textContent = etaText || '';
        }

        function formatearSegundos(segundos) {
            const s = Math.max(0, Math.round(segundos));
            const m = Math.floor(s / 60);
            const r = s % 60;
            if (m <= 0) return `${r}s`;
            return `${m}m ${r}s`;
        }

        function ocultarCargaPdf() {
            const botonPdf = document.getElementById('btnGenerarPdf');
            if (botonPdf) {
                botonPdf.disabled = false;
            }
            const modal = obtenerModalPdfCarga();
            if (modal) {
                modal.hide();
            }
        }

        async function cargarImagenComoDataUrl(url) {
            try {
                const response = await fetch(url, { mode: 'cors' });
                if (!response.ok) {
                    throw new Error('No se pudo cargar el QR');
                }
                const blob = await response.blob();
                return await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onloadend = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);
                });
            } catch (e) {
                return null;
            }
        }

        async function generarPdfGafetes() {
            if (isGeneratingPdf) {
                return;
            }

            if (!Array.isArray(datosGafetesPdf) || datosGafetesPdf.length === 0) {
                alert('No hay estudiantes para generar el PDF.');
                return;
            }

            isGeneratingPdf = true;
            mostrarCargaPdf();
            actualizarProgresoPdf(1, 'Generando PDF del curso', '');

            try {
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'letter' });

                const pageW = 612;
                const pageH = 792;
                const cardW = cmToPt(6);
                const cardH = cmToPt(10);
                const qrSize = cmToPt(4.5);
                const cardsPerPage = 6;
                const cursoPagina = (datosGafetesPdf[0] && datosGafetesPdf[0].curso_texto) ? datosGafetesPdf[0].curso_texto : '';

                const cols = 3;
                const rows = 2;
                const gapX = (pageW - (cols * cardW)) / (cols + 1);
                const gapY = (pageH - (rows * cardH)) / (rows + 1);

                const slots = [];
                for (let row = 0; row < rows; row++) {
                    for (let col = 0; col < cols; col++) {
                        slots.push({
                            x: gapX + col * (cardW + gapX),
                            y: gapY + row * (cardH + gapY)
                        });
                    }
                }

                for (let i = 0; i < datosGafetesPdf.length; i++) {
                    const pct = 1 + Math.floor(((i + 1) / datosGafetesPdf.length) * 99);
                    actualizarProgresoPdf(pct, 'Procesando gafetes del curso', '');
                    if (i > 0 && i % cardsPerPage === 0) {
                        pdf.addPage('letter', 'portrait');
                    }

                    const est = datosGafetesPdf[i];
                    const slot = slots[i % cardsPerPage];
                    const x = slot.x;
                    const y = slot.y;

                    if (i % cardsPerPage === 0) {
                        pdf.setFont('helvetica', 'bold');
                        pdf.setFontSize(11);
                        pdf.setTextColor(25, 25, 25);
                        pdf.text('Curso: ' + cursoPagina, pageW / 2, 28, { align: 'center' });
                    }

                    pdf.setFillColor(255, 255, 255);
                    pdf.setDrawColor(170, 170, 170);
                    pdf.setLineWidth(0.5);
                    pdf.rect(x, y, cardW, cardH, 'FD');

                    pdf.setFillColor(15, 15, 15);
                    pdf.rect(x, y, cardW, 13, 'F');

                    pdf.setTextColor(0, 0, 0);
                    pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(8);
                    pdf.setTextColor(255, 255, 255);
                    pdf.text('GAFETE ESTUDIANTIL', x + (cardW / 2), y + 9, { align: 'center' });
                    pdf.setTextColor(20, 20, 20);
                    pdf.setFontSize(7.5);
                    pdf.text('Unidad Educativa Simon Bolivar', x + (cardW / 2), y + 20, { align: 'center' });

                    const qrX = x + (cardW - qrSize) / 2;
                    const qrY = y + 42;
                    const qrDataUrl = await cargarImagenComoDataUrl(est.qr_url);
                    if (qrDataUrl) {
                        pdf.addImage(qrDataUrl, 'PNG', qrX, qrY, qrSize, qrSize);
                    } else {
                        pdf.setDrawColor(160, 160, 160);
                        pdf.rect(qrX, qrY, qrSize, qrSize);
                        pdf.setFont('helvetica', 'normal');
                        pdf.setFontSize(8);
                        pdf.text('QR no disponible', x + (cardW / 2), qrY + (qrSize / 2), { align: 'center' });
                    }

                    const idY = qrY + qrSize + 11;
                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(8);
                    pdf.text('Id estudiante: ' + est.id_estudiante, x + (cardW / 2), idY, { align: 'center' });

                    pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(10);
                    const nombreBase = (est.nombre || '').toUpperCase();
                    let nombreLineas = pdf.splitTextToSize(nombreBase, cardW - 12);
                    if (nombreLineas.length > 2) {
                        nombreLineas = nombreLineas.slice(0, 2);
                        nombreLineas[1] = nombreLineas[1].slice(0, Math.max(nombreLineas[1].length - 3, 0)) + '...';
                    }
                    pdf.text(nombreLineas, x + (cardW / 2), idY + 14, { align: 'center', maxWidth: cardW - 12 });

                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(8);
                    const pieTexto = est.curso_texto || '';
                    let pieLineas = pdf.splitTextToSize(pieTexto, cardW - 12);
                    if (pieLineas.length > 2) {
                        pieLineas = pieLineas.slice(0, 2);
                        pieLineas[1] = pieLineas[1].slice(0, Math.max(pieLineas[1].length - 3, 0)) + '...';
                    }
                    pdf.text(pieLineas, x + (cardW / 2), y + cardH - 20, { align: 'center', maxWidth: cardW - 12 });
                }

                const nombreCursoArchivo = <?= json_encode($id_curso ? ('curso_' . (int)$id_curso) : 'curso') ?>;
                pdf.save('gafetes_pdf_' + nombreCursoArchivo + '.pdf');
                actualizarProgresoPdf(100, 'PDF completado', '');
            } finally {
                isGeneratingPdf = false;
                ocultarCargaPdf();
            }
        }

        function changeLevel(select) {
            const form = select.form;
            const courseSelect = document.getElementById('id_curso');
            if (courseSelect) {
                courseSelect.value = '';
            }
            form.submit();
        }

        function printStudent(studentId) {
            const wrappers = document.querySelectorAll('.qr-card-wrapper');
            wrappers.forEach(function(el) {
                el.classList.add('no-print-student');
            });

            const selected = document.getElementById('student-card-' + studentId);
            if (selected) {
                selected.classList.remove('no-print-student');
            }

            window.print();

            wrappers.forEach(function(el) {
                el.classList.remove('no-print-student');
            });
        }

        let html5QrCode = null;
        let isScanning = false;
        let isProcessingScan = false;
        const RESULT_DISPLAY_MS = 1300;
        let beepAudioContext = null;
        let successCount = 0;
        const isMobileScreen = window.matchMedia('(max-width: 991.98px)').matches;

        function ensureBeepContext() {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) {
                return null;
            }
            if (!beepAudioContext) {
                beepAudioContext = new AudioCtx();
            }
            if (beepAudioContext.state === 'suspended') {
                beepAudioContext.resume().catch(() => {});
            }
            return beepAudioContext;
        }

        function sanitizeFolderName(value) {
            return String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^A-Za-z0-9_-]+/g, '_')
                .replace(/^_+|_+$/g, '') || 'sin_nombre';
        }

        async function crearPdfGafetesCursoBlob(estudiantesCurso, cursoPagina) {
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'letter' });

            const pageW = 612;
            const pageH = 792;
            const cardW = cmToPt(6);
            const cardH = cmToPt(10);
            const qrSize = cmToPt(4.5);
            const cardsPerPage = 6;

            const cols = 3;
            const rows = 2;
            const gapX = (pageW - (cols * cardW)) / (cols + 1);
            const gapY = (pageH - (rows * cardH)) / (rows + 1);

            const slots = [];
            for (let row = 0; row < rows; row++) {
                for (let col = 0; col < cols; col++) {
                    slots.push({
                        x: gapX + col * (cardW + gapX),
                        y: gapY + row * (cardH + gapY)
                    });
                }
            }

            for (let i = 0; i < estudiantesCurso.length; i++) {
                if (i > 0 && i % cardsPerPage === 0) {
                    pdf.addPage('letter', 'portrait');
                }

                const est = estudiantesCurso[i];
                const slot = slots[i % cardsPerPage];
                const x = slot.x;
                const y = slot.y;

                if (i % cardsPerPage === 0) {
                    pdf.setFont('helvetica', 'bold');
                    pdf.setFontSize(11);
                    pdf.setTextColor(25, 25, 25);
                    pdf.text('Curso: ' + cursoPagina, pageW / 2, 28, { align: 'center' });
                }

                pdf.setFillColor(255, 255, 255);
                pdf.setDrawColor(170, 170, 170);
                pdf.setLineWidth(0.5);
                pdf.rect(x, y, cardW, cardH, 'FD');

                pdf.setFillColor(15, 15, 15);
                pdf.rect(x, y, cardW, 13, 'F');

                pdf.setTextColor(255, 255, 255);
                pdf.setFont('helvetica', 'bold');
                pdf.setFontSize(8);
                pdf.text('GAFETE ESTUDIANTIL', x + (cardW / 2), y + 9, { align: 'center' });
                pdf.setTextColor(20, 20, 20);
                pdf.setFontSize(7.5);
                pdf.text('Unidad Educativa Simon Bolivar', x + (cardW / 2), y + 20, { align: 'center' });

                const qrX = x + (cardW - qrSize) / 2;
                const qrY = y + 42;
                const qrDataUrl = await cargarImagenComoDataUrl(est.qr_url);
                if (qrDataUrl) {
                    pdf.addImage(qrDataUrl, 'PNG', qrX, qrY, qrSize, qrSize);
                } else {
                    pdf.setDrawColor(160, 160, 160);
                    pdf.rect(qrX, qrY, qrSize, qrSize);
                    pdf.setFont('helvetica', 'normal');
                    pdf.setFontSize(8);
                    pdf.text('QR no disponible', x + (cardW / 2), qrY + (qrSize / 2), { align: 'center' });
                }

                const idY = qrY + qrSize + 11;
                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(8);
                pdf.text('Id estudiante: ' + est.id_estudiante, x + (cardW / 2), idY, { align: 'center' });

                pdf.setFont('helvetica', 'bold');
                pdf.setFontSize(10);
                const nombreBase = (est.nombre || '').toUpperCase();
                let nombreLineas = pdf.splitTextToSize(nombreBase, cardW - 12);
                if (nombreLineas.length > 2) {
                    nombreLineas = nombreLineas.slice(0, 2);
                    nombreLineas[1] = nombreLineas[1].slice(0, Math.max(nombreLineas[1].length - 3, 0)) + '...';
                }
                pdf.text(nombreLineas, x + (cardW / 2), idY + 14, { align: 'center', maxWidth: cardW - 12 });

                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(8);
                const pieTexto = est.curso_texto || '';
                let pieLineas = pdf.splitTextToSize(pieTexto, cardW - 12);
                if (pieLineas.length > 2) {
                    pieLineas = pieLineas.slice(0, 2);
                    pieLineas[1] = pieLineas[1].slice(0, Math.max(pieLineas[1].length - 3, 0)) + '...';
                }
                pdf.text(pieLineas, x + (cardW / 2), y + cardH - 20, { align: 'center', maxWidth: cardW - 12 });
            }

            return pdf.output('blob');
        }

        async function generarZipTodoColegio() {
            if (!Array.isArray(datosGafetesZip) || datosGafetesZip.length === 0) {
                alert('No hay estudiantes para generar el ZIP global.');
                return;
            }
            if (typeof JSZip === 'undefined') {
                alert('No se pudo cargar JSZip. Recarga la pagina e intenta nuevamente.');
                return;
            }

            const btn = document.getElementById('btnZipTodoColegio');
            if (btn) {
                btn.disabled = true;
            }
            mostrarCargaPdf();
            actualizarProgresoPdf(1, 'Preparando cursos', 'Calculando tiempo estimado...');

            try {
                const zip = new JSZip();
                const byCourse = new Map();

                for (const st of datosGafetesZip) {
                    const key = `${st.nivel}||${st.curso}||${st.paralelo}`;
                    if (!byCourse.has(key)) {
                        byCourse.set(key, {
                            nivel: st.nivel,
                            curso: st.curso,
                            paralelo: st.paralelo,
                            curso_texto: st.curso_texto,
                            estudiantes: []
                        });
                    }
                    byCourse.get(key).estudiantes.push(st);
                }

                ['Inicial', 'Primaria', 'Secundaria'].forEach(n => zip.folder(sanitizeFolderName(n)));

                const courseEntries = Array.from(byCourse.values());
                const totalCursos = courseEntries.length;
                const startTs = Date.now();

                for (let idx = 0; idx < totalCursos; idx++) {
                    const cursoData = courseEntries[idx];
                    const nivelFolder = sanitizeFolderName(cursoData.nivel);
                    const cursoFolder = sanitizeFolderName(`${cursoData.curso}_${cursoData.paralelo}`);
                    const carpetaCurso = zip.folder(`${nivelFolder}/${cursoFolder}`);

                    const avance = idx / Math.max(totalCursos, 1);
                    const pctPrevio = 1 + Math.floor(avance * 94);
                    const elapsedSec = (Date.now() - startTs) / 1000;
                    const cursosTerminados = idx;
                    const avgSec = cursosTerminados > 0 ? (elapsedSec / cursosTerminados) : 0;
                    const restantes = Math.max(totalCursos - cursosTerminados, 0);
                    const etaSec = avgSec * restantes;
                    actualizarProgresoPdf(
                        pctPrevio,
                        `Generando PDF ${idx + 1}/${totalCursos}: ${cursoData.curso_texto}`,
                        cursosTerminados > 0 ? `Tiempo estimado restante: ${formatearSegundos(etaSec)}` : 'Calculando tiempo estimado...'
                    );

                    const pdfBlob = await crearPdfGafetesCursoBlob(cursoData.estudiantes, cursoData.curso_texto);
                    carpetaCurso.file(`Gafetes_${cursoFolder}.pdf`, pdfBlob);
                }

                actualizarProgresoPdf(96, 'Empaquetando ZIP', '');
                const zipBlob = await zip.generateAsync({ type: 'blob', compression: 'DEFLATE', compressionOptions: { level: 6 } });
                const ts = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                const name = `gafetes_todo_colegio_${ts.getFullYear()}${pad(ts.getMonth()+1)}${pad(ts.getDate())}_${pad(ts.getHours())}${pad(ts.getMinutes())}${pad(ts.getSeconds())}.zip`;

                const url = URL.createObjectURL(zipBlob);
                const a = document.createElement('a');
                a.href = url;
                a.download = name;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                actualizarProgresoPdf(100, 'ZIP completado', 'Descarga iniciada');
            } catch (err) {
                console.error(err);
                alert('No se pudo generar el ZIP global. Intenta nuevamente.');
            } finally {
                if (btn) {
                    btn.disabled = false;
                }
                ocultarCargaPdf();
            }
        }

        function setManualMode(enabled) {
            const layout = document.getElementById('scanLayout');
            if (!layout) {
                return;
            }

            const qrBtn = document.getElementById('modeQrBtn');
            const manualBtn = document.getElementById('modeManualBtn');
            if (enabled) {
                layout.classList.add('manual-mode');
                if (manualBtn) {
                    manualBtn.classList.add('is-active');
                }
                if (qrBtn) {
                    qrBtn.classList.remove('is-active');
                }
            } else {
                layout.classList.remove('manual-mode');
                if (qrBtn) {
                    qrBtn.classList.add('is-active');
                }
                if (manualBtn) {
                    manualBtn.classList.remove('is-active');
                }
            }
            clearScanResults();
        }

        function getScanResultContainer() {
            const layout = document.getElementById('scanLayout');
            const inManualMode = layout && layout.classList.contains('manual-mode');
            if (inManualMode) {
                return document.getElementById('scan-result-manual');
            }
            return document.getElementById('scan-result');
        }

        function clearScanResults() {
            const overlayResult = document.getElementById('scan-result');
            const manualResult = document.getElementById('scan-result-manual');
            if (overlayResult) {
                overlayResult.innerHTML = '';
            }
            if (manualResult) {
                manualResult.innerHTML = '';
            }
        }

        function updateKpis(data) {
            const successNode = document.getElementById('kpiSuccessCount');
            const lastIdNode = document.getElementById('kpiLastId');
            if (data && data.success) {
                successCount += 1;
            }
            if (successNode) {
                successNode.textContent = String(successCount);
            }
            if (lastIdNode && data && data.id_estudiante) {
                lastIdNode.textContent = String(data.id_estudiante);
            }
        }

        function renderScanResult(data) {
            const resultDiv = getScanResultContainer();
            if (!resultDiv) {
                isProcessingScan = false;
                return;
            }

            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="alert alert-success result-card">
                        <h5><i class="ri-check-circle-fill"></i> ¡Asistencia Registrada!</h5>
                        ${data.id_estudiante ? `<p><strong>ID:</strong> ${data.id_estudiante}</p>` : ''}
                        ${data.turno ? `<p><strong>Turno:</strong> ${data.turno}</p>` : ''}
                        <p><strong>Estudiante:</strong> ${data.estudiante}</p>
                        <p><strong>Hora:</strong> ${data.hora}</p>
                        ${data.puntualidad ? `<p><strong>Puntualidad:</strong> ${data.puntualidad}</p>` : ''}
                    </div>
                `;
                playSuccessSound();
                if (navigator.vibrate) {
                    navigator.vibrate(70);
                }
            } else {
                resultDiv.innerHTML = `
                    <div class="alert alert-warning result-card">
                        <h5><i class="ri-alert-fill"></i> Atención</h5>
                        <p>${data.message}</p>
                    </div>
                `;
                playErrorSound();
            }

            updateKpis(data);

            if (resultDiv.id === 'scan-result-manual') {
                resultDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            setTimeout(() => {
                clearScanResults();
                isProcessingScan = false;
            }, RESULT_DISPLAY_MS);
        }

        function procesarRegistroAsistencia(payload) {
            if (isProcessingScan) {
                return;
            }

            isProcessingScan = true;

            fetch('asistencia.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=scan_qr&qr_data=' + encodeURIComponent(payload)
            })
            .then(response => response.json())
            .then(renderScanResult)
            .catch(error => {
                console.error('Error:', error);
                const resultDiv = getScanResultContainer();
                if (!resultDiv) {
                    isProcessingScan = false;
                    return;
                }
                resultDiv.innerHTML = `
                    <div class="alert alert-danger result-card">
                        <h5><i class="ri-error-warning-fill"></i> Error</h5>
                        <p>Error al procesar el registro</p>
                    </div>
                `;

                setTimeout(() => {
                    clearScanResults();
                    isProcessingScan = false;
                }, RESULT_DISPLAY_MS);
            });
        }

        function openScanner() {
            const modal = new bootstrap.Modal(document.getElementById('scannerModal'));
            setManualMode(!isMobileScreen);
            ensureBeepContext();
            modal.show();
            
            // Iniciar el escáner después de que el modal se muestre
            setTimeout(startScanner, 500);
        }

        async function startScanner() {
            if (isScanning) return;
            
            const readerElement = document.getElementById('reader');
            if (!readerElement) return;

            readerElement.innerHTML = '';

            const isLocalhost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
            if (!window.isSecureContext && !isLocalhost) {
                const resultDiv = document.getElementById('scan-result');
                resultDiv.innerHTML = `
                    <div class="alert alert-warning result-card">
                        <h5><i class="ri-lock-2-line"></i> Cámara bloqueada por el navegador</h5>
                        <p>Para usar la cámara en celular debes abrir el sistema con <strong>HTTPS</strong> (o localhost).</p>
                    </div>
                `;
                isScanning = false;
                return;
            }

            try {
                html5QrCode = new Html5Qrcode('reader');

                const scanConfig = {
                    fps: 25,
                    qrbox: { width: 260, height: 260 },
                    aspectRatio: 1.0,
                    formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                    disableFlip: true
                };

                const tryStart = async (cameraConfig) => {
                    await html5QrCode.start(cameraConfig, scanConfig, onScanSuccess, onScanFailure);
                };

                let started = false;

                try {
                    await tryStart({ facingMode: { exact: 'environment' } });
                    started = true;
                } catch (e1) {
                    try {
                        await tryStart({ facingMode: 'environment' });
                        started = true;
                    } catch (e2) {
                        const cameras = await Html5Qrcode.getCameras();
                        if (cameras && cameras.length > 0) {
                            const backCam = cameras.find(c => /back|rear|trasera|environment/i.test(c.label || ''));
                            const selectedCam = backCam ? backCam.id : cameras[0].id;
                            await tryStart({ deviceId: { exact: selectedCam } });
                            started = true;
                        }
                    }
                }

                if (!started) {
                    throw new Error('No se encontró una cámara disponible');
                }

                isScanning = true;
            } catch (error) {
                const resultDiv = document.getElementById('scan-result');
                resultDiv.innerHTML = `
                    <div class="alert alert-danger result-card">
                        <h5><i class="ri-error-warning-fill"></i> Error de cámara</h5>
                        <p>No se pudo abrir la cámara. Revisa permisos del navegador y vuelve a intentar.</p>
                    </div>
                `;
                console.error('No se pudo iniciar cámara:', error);
                isScanning = false;
            }
        }

        async function stopScanner() {
            if (html5QrCode) {
                try {
                    await html5QrCode.stop();
                    await html5QrCode.clear();
                } catch (error) {
                    console.error('Error al detener cámara:', error);
                }
                html5QrCode = null;
            }
            isScanning = false;
            isProcessingScan = false;
        }

        function onScanSuccess(decodedText) {
            procesarRegistroAsistencia(decodedText);
        }

        function registerById() {
            const input = document.getElementById('manualStudentId');
            if (!input) {
                return;
            }

            setManualMode(true);

            const idValue = (input.value || '').trim();
            if (!/^\d+$/.test(idValue) || parseInt(idValue, 10) <= 0) {
                const resultDiv = getScanResultContainer();
                if (!resultDiv) {
                    return;
                }
                resultDiv.innerHTML = `
                    <div class="alert alert-warning result-card">
                        <h5><i class="ri-alert-fill"></i> ID inválido</h5>
                        <p>Ingresa un ID numérico válido.</p>
                    </div>
                `;
                return;
            }

            setManualMode(true);
            procesarRegistroAsistencia('EST:' + parseInt(idValue, 10));
            input.value = '';
            input.focus();
        }

        function onScanFailure() {
        }

        function playSuccessSound() {
            const audioContext = ensureBeepContext();
            if (!audioContext) {
                return;
            }

            const now = audioContext.currentTime;
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.setValueAtTime(920, now);
            oscillator.type = 'sine';
            gainNode.gain.setValueAtTime(0.0001, now);
            gainNode.gain.exponentialRampToValueAtTime(0.45, now + 0.01);
            gainNode.gain.exponentialRampToValueAtTime(0.0001, now + 0.20);

            oscillator.start(now);
            oscillator.stop(now + 0.21);
        }

        function playErrorSound() {
            const audioContext = ensureBeepContext();
            if (!audioContext) {
                return;
            }

            const now = audioContext.currentTime;
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.setValueAtTime(320, now);
            oscillator.type = 'square';
            gainNode.gain.setValueAtTime(0.0001, now);
            gainNode.gain.exponentialRampToValueAtTime(0.38, now + 0.01);
            gainNode.gain.exponentialRampToValueAtTime(0.0001, now + 0.28);

            oscillator.start(now);
            oscillator.stop(now + 0.29);
        }

        // Detener el escáner cuando se cierra el modal
        document.getElementById('scannerModal').addEventListener('hidden.bs.modal', async function () {
            setManualMode(false);
            await stopScanner();
        });

        document.addEventListener('DOMContentLoaded', function() {
            const qrBtn = document.getElementById('modeQrBtn');
            const speedTip = document.getElementById('scanSpeedTip');
            if (!isMobileScreen) {
                setManualMode(true);
                if (qrBtn) {
                    qrBtn.disabled = true;
                    qrBtn.title = 'Escaneo QR habilitado para celular';
                }
                if (speedTip) {
                    speedTip.textContent = 'PC: priorice registro manual continuo con Enter.';
                }
            } else {
                setManualMode(false);
                if (speedTip) {
                    speedTip.textContent = 'Celular: use QR; manual solo para excepciones.';
                }
            }
            const activateAudio = () => ensureBeepContext();
            document.addEventListener('click', activateAudio, { once: true });
            document.addEventListener('touchstart', activateAudio, { once: true });

            const input = document.getElementById('manualStudentId');
            if (input) {
                input.addEventListener('focus', function() {
                    setManualMode(true);
                });
                input.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        registerById();
                    }
                });
            }
        });
    </script>
</body>
</html>
