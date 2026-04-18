<?php
session_start();
require_once '../config/database.php';

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
    $stmt = $conn->prepare("SELECT id_lector, id_personal, alcance, estado FROM asistencia_lectores WHERE id_personal = ? AND estado = 1 LIMIT 1");
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
    $data = @file_get_contents($url);
    if ($data !== false) {
        return $data;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $statusCode >= 200 && $statusCode < 300) {
            return $response;
        }
    }

    return false;
}

function create_gafete_png_binary($qrBinary, array $student)
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $qrImg = @imagecreatefromstring($qrBinary);
    if (!$qrImg) {
        return false;
    }

    $width = 1063;
    $height = 638;
    $canvas = imagecreatetruecolor($width, $height);

    $white = imagecolorallocate($canvas, 255, 255, 255);
    $primary = imagecolorallocate($canvas, 34, 80, 149);
    $dark = imagecolorallocate($canvas, 25, 25, 25);
    $gray = imagecolorallocate($canvas, 90, 90, 90);
    $border = imagecolorallocate($canvas, 210, 210, 210);

    imagefill($canvas, 0, 0, $white);
    imagerectangle($canvas, 0, 0, $width - 1, $height - 1, $border);

    imagefilledrectangle($canvas, 0, 0, $width, 90, $primary);
    imagestring($canvas, 5, 35, 34, utf8_decode('GAFETE ESTUDIANTIL - EDUNOTE'), imagecolorallocate($canvas, 255, 255, 255));

    $qrSize = 430;
    imagecopyresampled($canvas, $qrImg, 40, 140, 0, 0, $qrSize, $qrSize, imagesx($qrImg), imagesy($qrImg));
    imagerectangle($canvas, 40, 140, 40 + $qrSize, 140 + $qrSize, $border);

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

    imagestring($canvas, 5, $x, 330, utf8_decode('NIVEL Y CURSO'), $primary);
    imagestring($canvas, 5, $x, 365, utf8_decode($curso), $dark);

    imagestring($canvas, 5, $x, 440, utf8_decode($idText), $gray);
    imagestring($canvas, 3, $x, 500, utf8_decode('Uso institucional - Control de asistencia QR'), $gray);

    ob_start();
    imagepng($canvas, null, 9);
    $binary = ob_get_clean();

    imagedestroy($qrImg);
    imagedestroy($canvas);

    return $binary !== false ? $binary : false;
}

// Procesar registro de asistencia (cuando se escanea un QR desde el modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan_qr' && isset($_POST['qr_data'])) {
    header('Content-Type: application/json; charset=utf-8');
    $raw_qr = trim((string)$_POST['qr_data']);
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
        
        // Verificar si ya tiene asistencia hoy
        $stmt_check = $conn->prepare("SELECT id_asistencia, hora_entrada FROM asistencia WHERE id_estudiante = ? AND fecha = ?");
        $stmt_check->execute([$id_estudiante, $hoy]);
        $existente = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($existente) {
            echo json_encode([
                'success' => false,
                'message' => 'El estudiante ya registró asistencia hoy a las ' . $existente['hora_entrada']
            ]);
        } else {
            // Registrar asistencia
            $stmt = $conn->prepare("INSERT INTO asistencia (id_estudiante, fecha, hora_entrada, tipo_registro) VALUES (?, ?, ?, 'QR')");
            if ($stmt->execute([$id_estudiante, $hoy, $hora_actual])) {
                // Obtener información del estudiante
                $stmt_est = $conn->prepare("SELECT nombres, apellido_paterno, apellido_materno FROM estudiantes WHERE id_estudiante = ?");
                $stmt_est->execute([$id_estudiante]);
                $estudiante = $stmt_est->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Asistencia registrada correctamente',
                    'estudiante' => $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno'] . ', ' . $estudiante['nombres'],
                    'hora' => $hora_actual
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

    foreach ($studentsQr as $studentQr) {
        $qrData = 'EST:' . (int)$studentQr['id_estudiante'];
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=945x945&data=' . urlencode($qrData);
        $binary = fetch_remote_binary($qrUrl);

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

    foreach ($studentsAll as $studentQr) {
        $qrData = 'EST:' . (int)$studentQr['id_estudiante'];
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=945x945&data=' . urlencode($qrData);
        $binary = fetch_remote_binary($qrUrl);

        if ($binary === false) {
            $fail++;
            continue;
        }

        $fileName = sanitize_file_part($studentQr['nivel']) . '__'
            . sanitize_file_part($studentQr['curso'] . '_' . $studentQr['paralelo']) . '__'
            . sanitize_file_part($studentQr['apellido_paterno'] . '_' . $studentQr['nombres'])
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
            'message' => "No se pudo generar el ZIP global de gafetes. Fallidos: {$fail}."
        ];
        header('Location: asistencia.php');
        exit();
    }

    $zipName = 'gafetes_todo_colegio_' . date('Ymd_His') . '.zip';

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
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            margin: 10px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .qr-card img {
            margin: 0 auto;
            display: block;
            width: 170px;
            height: 170px;
        }
        .badge-title {
            font-weight: 700;
            font-size: 0.78rem;
            color: #225095;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
        .student-name {
            font-weight: 600;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        .student-info {
            font-size: 0.8rem;
            color: #6c757d;
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
        }
        #reader video {
            border-radius: 10px;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .scan-modal .modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .scan-modal .modal-footer {
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        .manual-id-card {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.28);
            border-radius: 10px;
            padding: 12px;
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
        .reader-pane {
            border-radius: 10px;
            transition: all 0.25s ease;
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
        .scan-layout.manual-mode .reader-collapsed-hint {
            display: block;
        }
        .scan-result-box {
            position: sticky;
            top: 0;
            z-index: 6;
        }
        #scan-result .alert {
            margin-bottom: 0.75rem;
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
                border: 1px solid #333;
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
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 position-relative py-4 asistencia-main">
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
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="action" value="generate_all_school_zip">
                                    <button type="submit" class="btn btn-success">
                                        <i class="ri-download-cloud-2-line"></i> Descargar ZIP de todo el colegio
                                    </button>
                                </form>
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
                                        Nivel y curso: <?= htmlspecialchars($est['nivel'] . ' ' . $est['curso'] . ' "' . $est['paralelo'] . '"') ?>
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
                    <div class="scan-result-box">
                        <div id="scan-result"></div>
                    </div>
                    <div class="row g-3 align-items-start scan-layout" id="scanLayout">
                        <div class="col-lg-8">
                            <div class="reader-pane">
                                <div id="reader"></div>
                                <div id="readerCollapsedHint" class="reader-collapsed-hint" onclick="setManualMode(false)">
                                    <i class="ri-qr-scan-line"></i>
                                    Lector QR minimizado. Toca aquí para volver al tamaño grande.
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="manual-id-card">
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

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.0/dist/feather.min.js"></script>
    <script>
        feather.replace();

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
        const RESULT_DISPLAY_MS = 3000;

        function setManualMode(enabled) {
            const layout = document.getElementById('scanLayout');
            if (!layout) {
                return;
            }
            if (enabled) {
                layout.classList.add('manual-mode');
            } else {
                layout.classList.remove('manual-mode');
            }
        }

        function renderScanResult(data) {
            const resultDiv = document.getElementById('scan-result');
            const modalBody = document.querySelector('#scannerModal .modal-body');

            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="alert alert-success result-card">
                        <h5><i class="ri-check-circle-fill"></i> ¡Asistencia Registrada!</h5>
                        <p><strong>Estudiante:</strong> ${data.estudiante}</p>
                        <p><strong>Hora:</strong> ${data.hora}</p>
                    </div>
                `;
                playSuccessSound();
            } else {
                resultDiv.innerHTML = `
                    <div class="alert alert-warning result-card">
                        <h5><i class="ri-alert-fill"></i> Atención</h5>
                        <p>${data.message}</p>
                    </div>
                `;
                playErrorSound();
            }

            if (modalBody) {
                modalBody.scrollTo({ top: 0, behavior: 'smooth' });
            }
            resultDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });

            setTimeout(() => {
                resultDiv.innerHTML = '';
                isProcessingScan = false;
                if (html5QrCode) {
                    html5QrCode.resume();
                }
            }, RESULT_DISPLAY_MS);
        }

        function procesarRegistroAsistencia(payload) {
            if (isProcessingScan) {
                return;
            }

            isProcessingScan = true;

            if (html5QrCode) {
                html5QrCode.pause();
            }

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
                const resultDiv = document.getElementById('scan-result');
                resultDiv.innerHTML = `
                    <div class="alert alert-danger result-card">
                        <h5><i class="ri-error-warning-fill"></i> Error</h5>
                        <p>Error al procesar el registro</p>
                    </div>
                `;

                setTimeout(() => {
                    resultDiv.innerHTML = '';
                    isProcessingScan = false;
                    if (html5QrCode) {
                        html5QrCode.resume();
                    }
                }, RESULT_DISPLAY_MS);
            });
        }

        function openScanner() {
            const modal = new bootstrap.Modal(document.getElementById('scannerModal'));
            setManualMode(false);
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
                const resultDiv = document.getElementById('scan-result');
                resultDiv.innerHTML = `
                    <div class="alert alert-warning result-card">
                        <h5><i class="ri-alert-fill"></i> ID inválido</h5>
                        <p>Ingresa un ID numérico válido.</p>
                    </div>
                `;
                return;
            }

            procesarRegistroAsistencia('EST:' + parseInt(idValue, 10));
            input.value = '';
            input.focus();
        }

        function onScanFailure() {
        }

        function playSuccessSound() {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.3;
            
            oscillator.start();
            setTimeout(() => {
                oscillator.stop();
            }, 200);
        }

        function playErrorSound() {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 300;
            oscillator.type = 'square';
            gainNode.gain.value = 0.2;
            
            oscillator.start();
            setTimeout(() => {
                oscillator.stop();
            }, 300);
        }

        // Detener el escáner cuando se cierra el modal
        document.getElementById('scannerModal').addEventListener('hidden.bs.modal', async function () {
            setManualMode(false);
            await stopScanner();
        });

        document.addEventListener('DOMContentLoaded', function() {
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
