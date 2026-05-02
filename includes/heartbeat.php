<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false]);
    exit();
}

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false]);
    exit();
}

$idPersonal = (int)$_SESSION['user_id'];
$idRol = (int)$_SESSION['user_role'];
$nombreUsuario = trim((string)($_SESSION['user_name'] ?? ''));
$sessionId = session_id();

$rutaActual = isset($_POST['path']) ? trim((string)$_POST['path']) : '';
if ($rutaActual === '' && isset($_SERVER['HTTP_REFERER'])) {
    $ref = (string)$_SERVER['HTTP_REFERER'];
    $rutaActual = parse_url($ref, PHP_URL_PATH) ?: '';
}
if (strlen($rutaActual) > 255) {
    $rutaActual = substr($rutaActual, 0, 255);
}

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if (strlen($ip) > 45) {
    $ip = substr($ip, 0, 45);
}

$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
if (strlen($ua) > 255) {
    $ua = substr($ua, 0, 255);
}

$sql = "INSERT INTO usuarios_activos
        (id_personal, session_id, nombre_usuario, id_rol, ruta_actual, ip_address, user_agent, ultimo_ping)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            id_personal = VALUES(id_personal),
            nombre_usuario = VALUES(nombre_usuario),
            id_rol = VALUES(id_rol),
            ruta_actual = VALUES(ruta_actual),
            ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent),
            ultimo_ping = NOW()";

$stmt = $conn->prepare($sql);
$stmt->execute([$idPersonal, $sessionId, $nombreUsuario, $idRol, $rutaActual, $ip, $ua]);

$conn->prepare("DELETE FROM usuarios_activos WHERE ultimo_ping < (NOW() - INTERVAL 5 MINUTE)")->execute();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
