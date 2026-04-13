<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

// Procesar registro de asistencia (cuando se escanea un QR)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
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

// Obtener información del curso
$id_curso = $_GET['id_curso'] ?? null;
$curso_info = null;

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
        }
        .scanner-container {
            max-width: 500px;
            margin: 0 auto;
        }
        #reader {
            border-radius: 10px;
            overflow: hidden;
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
                                <i class="ri-qr-scan-line"></i> Escanear Asistencia
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

                        <!-- Scanner -->
                        <div class="scanner-container mb-4">
                            <div id="reader"></div>
                        </div>

                        <!-- Resultado del escaneo -->
                        <div id="result" class="mb-4"></div>

                        <!-- Lista de asistencia de hoy -->
                        <?php if (!empty($asistencia_hoy)): ?>
                            <div class="card mt-4">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="ri-check-double-line"></i> Asistencia Registrada Hoy (<?= count($asistencia_hoy) ?>)
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
                                            <tbody>
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
        let html5QrcodeScanner;

        function onScanSuccess(decodedText, decodedResult) {
            // Detener el escaneo temporalmente
            if (html5QrcodeScanner) {
                html5QrcodeScanner.pause();
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
                        </div>
                    `;
                    
                    // Recargar la página después de 2 segundos para mostrar la lista actualizada
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-warning result-card">
                            <h5><i class="ri-alert-fill"></i> Atención</h5>
                            <p>${data.message}</p>
                        </div>
                    `;
                    
                    // Reanudar el escaneo después de 2 segundos
                    setTimeout(() => {
                        if (html5QrcodeScanner) {
                            html5QrcodeScanner.resume();
                        }
                        resultDiv.innerHTML = '';
                    }, 2000);
                }
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
                
                // Reanudar el escaneo
                setTimeout(() => {
                    if (html5QrcodeScanner) {
                        html5QrcodeScanner.resume();
                    }
                    resultDiv.innerHTML = '';
                }, 2000);
            });
        }

        function onScanFailure(error) {
            // Manejar errores de escaneo (opcional)
            // console.warn(`Code scan error = ${error}`);
        }

        // Iniciar el escáner
        document.addEventListener('DOMContentLoaded', function() {
            html5QrcodeScanner = new Html5QrcodeScanner(
                "reader",
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                },
                /* verbose= */ false
            );
            
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        });
    </script>
</body>
</html>
