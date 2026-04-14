<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

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

function create_gafete_png($qrBinary, $destPath, array $student)
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

    $ok = imagepng($canvas, $destPath, 9);

    imagedestroy($qrImg);
    imagedestroy($canvas);

    return (bool)$ok;
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
    }

    if ($id_estudiante > 0) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download_qr_zip') {
    $relativePathPost = trim((string)($_POST['relative_path'] ?? ''));
    $relativePathPost = str_replace('\\', '/', $relativePathPost);
    $relativePathPost = ltrim($relativePathPost, '/');

    if ($relativePathPost === '' || strpos($relativePathPost, 'uploads/qr_asistencia/') !== 0) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'Ruta de carpeta inválida para generar ZIP.'];
        header('Location: asistencia.php');
        exit();
    }

    $projectRoot = realpath(__DIR__ . '/../');
    $sourceDir = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePathPost));

    if (!$sourceDir || !is_dir($sourceDir) || strpos(str_replace('\\', '/', $sourceDir), str_replace('\\', '/', $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'qr_asistencia')) !== 0) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'No se encontró la carpeta de gafetes para comprimir.'];
        header('Location: asistencia.php');
        exit();
    }

    if (!class_exists('ZipArchive')) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'ZipArchive no está habilitado en este servidor.'];
        header('Location: asistencia.php');
        exit();
    }

    $zipName = 'gafetes_' . date('Ymd_His') . '.zip';
    $tempZip = tempnam(sys_get_temp_dir(), 'gafetes_zip_');
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

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $filePath = $file->getRealPath();
        $localName = substr($filePath, strlen($sourceDir) + 1);
        $zip->addFile($filePath, $localName);
    }

    $zip->close();

    if (!is_file($zipPath)) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'No se pudo generar el ZIP final.'];
        header('Location: asistencia.php');
        exit();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_qr_folder') {
    $idCursoPost = isset($_POST['id_curso']) ? (int)$_POST['id_curso'] : 0;
    $idEstudiantePost = isset($_POST['id_estudiante']) ? (int)$_POST['id_estudiante'] : 0;
    $nivelRedirect = $_POST['nivel'] ?? '';

    if ($idCursoPost <= 0) {
        $_SESSION['asistencia_flash'] = ['type' => 'danger', 'message' => 'Debes seleccionar un curso para generar la carpeta de QRs.'];
        header('Location: asistencia.php');
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
        $_SESSION['asistencia_flash'] = ['type' => 'warning', 'message' => 'No se encontraron estudiantes para generar QRs.'];
        header('Location: asistencia.php?nivel=' . urlencode($nivelRedirect) . '&id_curso=' . $idCursoPost);
        exit();
    }

    $courseInfo = $studentsQr[0];
    $baseDir = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'qr_asistencia';
    $folderPath = $baseDir . DIRECTORY_SEPARATOR
        . sanitize_file_part($courseInfo['nivel']) . DIRECTORY_SEPARATOR
        . sanitize_file_part($courseInfo['curso'] . '_' . $courseInfo['paralelo']) . DIRECTORY_SEPARATOR
        . date('Y-m-d');

    if (!is_dir($folderPath)) {
        mkdir($folderPath, 0777, true);
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
        $destPath = $folderPath . DIRECTORY_SEPARATOR . $fileName;
        $saved = create_gafete_png($binary, $destPath, $studentQr);

        if (!$saved) {
            $fallback = @file_put_contents($destPath, $binary);
            $saved = $fallback !== false;
        }

        if (!$saved) {
            $fail++;
        } else {
            $ok++;
        }
    }

    $relativePath = 'uploads/qr_asistencia/'
        . sanitize_file_part($courseInfo['nivel']) . '/'
        . sanitize_file_part($courseInfo['curso'] . '_' . $courseInfo['paralelo']) . '/'
        . date('Y-m-d');

    $absolutePath = $folderPath;
    $webRoot = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['PHP_SELF']))), '/');
    $webPath = $webRoot . '/' . $relativePath;

    $_SESSION['asistencia_flash'] = [
        'type' => $ok > 0 ? 'success' : 'danger',
        'message' => "Carpeta generada: {$relativePath}. Gafetes creados: {$ok}. Fallidos: {$fail}.",
        'absolute_path' => $absolutePath,
        'web_path' => $webPath,
        'relative_path' => $relativePath
    ];

    header('Location: asistencia.php?nivel=' . urlencode($nivelRedirect) . '&id_curso=' . $idCursoPost);
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

if ($nivel !== '') {
    $stmt_cursos = $conn->prepare("SELECT id_curso, nivel, curso, paralelo FROM cursos WHERE nivel = ? ORDER BY curso, paralelo");
    $stmt_cursos->execute([$nivel]);
} else {
    $stmt_cursos = $conn->query("SELECT id_curso, nivel, curso, paralelo FROM cursos ORDER BY nivel, curso, paralelo");
}
$cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);

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
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 position-relative py-4">
                <?php if (isset($_SESSION['asistencia_flash'])): ?>
                    <?php $flash = $_SESSION['asistencia_flash']; unset($_SESSION['asistencia_flash']); ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> no-print" role="alert">
                        <div><?= htmlspecialchars($flash['message']) ?></div>
                        <?php if (!empty($flash['absolute_path'])): ?>
                            <div class="mt-2">
                                <label class="form-label mb-1"><strong>Ruta de carpeta (copiable):</strong></label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="qr-folder-path" value="<?= htmlspecialchars($flash['absolute_path']) ?>" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyFolderPath()">Copiar</button>
                                    <?php if (!empty($flash['web_path'])): ?>
                                        <a class="btn btn-outline-primary" href="<?= htmlspecialchars($flash['web_path']) ?>" target="_blank" rel="noopener">Abrir enlace</a>
                                    <?php endif; ?>
                                    <?php if (!empty($flash['relative_path'])): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="action" value="download_qr_zip">
                                            <input type="hidden" name="relative_path" value="<?= htmlspecialchars($flash['relative_path']) ?>">
                                            <button class="btn btn-success" type="submit">Descargar ZIP</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h1 class="h3">
                        <i class="ri-qr-code-line"></i> Asistencia - Generación de QR
                    </h1>
                    <div>
                        <a href="dashboard_secundaria.php" class="btn btn-secondary">
                            <i class="ri-arrow-left-line"></i> Volver
                        </a>
                        <?php if ($id_curso && !empty($estudiantes)): ?>
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="ri-printer-line"></i> Imprimir todo el curso
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Selector de curso -->
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
                    </div>
                </div>

                <!-- Lista de estudiantes con QR -->
                <?php if ($id_curso && !empty($estudiantes)): ?>
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
                                <i class="ri-id-card-line"></i> Generar carpeta de gafetes (curso)
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
                                                <i class="ri-id-card-line"></i> Generar gafete PNG
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($id_curso): ?>
                    <div class="alert alert-warning">
                        <i class="ri-alert-line"></i> No hay estudiantes matriculados en este curso.
                    </div>
                <?php endif; ?>

                <!-- Botón para ir al escáner -->
                <?php if ($id_curso): ?>
                    <div class="mt-4 no-print">
                        <a href="escanear_asistencia.php?id_curso=<?= $id_curso ?>" class="btn btn-success btn-lg">
                            <i class="ri-qr-scan-line"></i> Escanear Asistencia (Página completa)
                        </a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Botón flotante para escanear -->
    <?php if ($id_curso): ?>
        <button class="scan-fab no-print" onclick="openScanner()" title="Escanear QR">
            <i class="ri-qr-scan-2-line"></i>
        </button>
    <?php endif; ?>

    <!-- Modal para escanear QR -->
    <div class="modal fade scan-modal" id="scannerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ri-qr-scan-line"></i> Escanear Asistencia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="reader"></div>
                    <div id="scan-result" class="mt-3"></div>
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

        function copyFolderPath() {
            const input = document.getElementById('qr-folder-path');
            if (!input) return;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value);
            } else {
                input.select();
                document.execCommand('copy');
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

        function openScanner() {
            const modal = new bootstrap.Modal(document.getElementById('scannerModal'));
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
                body: 'action=scan_qr&qr_data=' + encodeURIComponent(decodedText)
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('scan-result');
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success result-card">
                            <h5><i class="ri-check-circle-fill"></i> ¡Asistencia Registrada!</h5>
                            <p><strong>Estudiante:</strong> ${data.estudiante}</p>
                            <p><strong>Hora:</strong> ${data.hora}</p>
                        </div>
                    `;
                    
                    // Reproducir sonido de éxito
                    playSuccessSound();
                    
                    // Reanudar el escaneo después de 2 segundos
                    setTimeout(() => {
                        resultDiv.innerHTML = '';
                        isProcessingScan = false;
                        if (html5QrCode) {
                            html5QrCode.resume();
                        }
                    }, 1200);
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-warning result-card">
                            <h5><i class="ri-alert-fill"></i> Atención</h5>
                            <p>${data.message}</p>
                        </div>
                    `;
                    
                    // Reproducir sonido de error
                    playErrorSound();
                    
                    // Reanudar el escaneo después de 2 segundos
                    setTimeout(() => {
                        resultDiv.innerHTML = '';
                        isProcessingScan = false;
                        if (html5QrCode) {
                            html5QrCode.resume();
                        }
                    }, 1200);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const resultDiv = document.getElementById('scan-result');
                resultDiv.innerHTML = `
                    <div class="alert alert-danger result-card">
                        <h5><i class="ri-error-warning-fill"></i> Error</h5>
                        <p>Error al procesar el QR</p>
                    </div>
                `;
                
                // Reanudar el escaneo
                setTimeout(() => {
                    resultDiv.innerHTML = '';
                    isProcessingScan = false;
                    if (html5QrCode) {
                        html5QrCode.resume();
                    }
                }, 1200);
            });
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
            await stopScanner();
        });
    </script>
</body>
</html>
