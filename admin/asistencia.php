<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

// Procesar registro de asistencia (cuando se escanea un QR desde el modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan_qr' && isset($_POST['qr_data'])) {
    $qr_data = json_decode($_POST['qr_data'], true);
    
    if ($qr_data && isset($qr_data['id_estudiante'])) {
        $id_estudiante = $qr_data['id_estudiante'];
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

// Obtener cursos
$stmt_cursos = $conn->query("SELECT id_curso, nivel, curso, paralelo FROM cursos ORDER BY nivel, curso, paralelo");
$cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);

// Curso seleccionado
$id_curso = $_GET['id_curso'] ?? null;
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
            padding: 20px;
            text-align: center;
            margin: 10px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .qr-card img {
            margin: 0 auto;
            display: block;
            width: 150px;
            height: 150px;
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
            }
            .scan-fab {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <?php include '../includes/sidebar.php'; ?>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
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
                                <i class="ri-printer-line"></i> Imprimir QRs
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
                                    <label for="id_curso" class="form-label">Seleccionar Curso</label>
                                    <select class="form-select" id="id_curso" name="id_curso" onchange="this.form.submit()">
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

                    <div class="row" id="qr-container">
                        <?php foreach ($estudiantes as $est): ?>
                            <?php
                                $qrData = json_encode([
                                    'id_estudiante' => $est['id_estudiante'],
                                    'nombres' => $est['nombres'],
                                    'apellido_paterno' => $est['apellido_paterno'],
                                    'apellido_materno' => $est['apellido_materno']
                                ]);
                                $qrDataEncoded = urlencode($qrData);
                                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . $qrDataEncoded;
                            ?>
                            <div class="col-md-3 col-sm-4 col-6">
                                <div class="qr-card">
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

        let html5QrcodeScanner = null;
        let isScanning = false;

        function openScanner() {
            const modal = new bootstrap.Modal(document.getElementById('scannerModal'));
            modal.show();
            
            // Iniciar el escáner después de que el modal se muestre
            setTimeout(startScanner, 500);
        }

        function startScanner() {
            if (isScanning) return;
            
            const readerElement = document.getElementById('reader');
            if (!readerElement) return;

            html5QrcodeScanner = new Html5QrcodeScanner(
                "reader",
                {
                    fps: 20,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                },
                false
            );
            
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
            isScanning = true;
        }

        function stopScanner() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
                html5QrcodeScanner = null;
            }
            isScanning = false;
        }

        function onScanSuccess(decodedText, decodedResult) {
            // Detener el escaneo temporalmente
            if (html5QrcodeScanner) {
                html5QrcodeScanner.pause();
            }

            // Enviar datos al servidor
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
                        if (html5QrcodeScanner) {
                            html5QrcodeScanner.resume();
                        }
                    }, 2000);
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
                        if (html5QrcodeScanner) {
                            html5QrcodeScanner.resume();
                        }
                    }, 2000);
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
                    if (html5QrcodeScanner) {
                        html5QrcodeScanner.resume();
                    }
                }, 2000);
            });
        }

        function onScanFailure(error) {
            // No hacer nada, es normal cuando no hay QR en la cámara
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
        document.getElementById('scannerModal').addEventListener('hidden.bs.modal', function () {
            stopScanner();
        });
    </script>
</body>
</html>
