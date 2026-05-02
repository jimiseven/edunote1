<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || (int)($_SESSION['user_role'] ?? 0) !== 1) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'No autorizado']);
    exit();
}

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Sin conexión']);
    exit();
}

$conn->prepare("DELETE FROM usuarios_activos WHERE ultimo_ping < (NOW() - INTERVAL 5 MINUTE)")->execute();

$sql = "SELECT p.id_personal,
               CONCAT(p.nombres, ' ', p.apellidos) AS nombre_usuario,
               p.carnet_identidad,
               r.nombre_rol,
               CASE WHEN ua.id_personal IS NULL THEN 0 ELSE 1 END AS activo,
               DATE_FORMAT(ul.ultima_vez_ingreso, '%Y-%m-%d %H:%i:%s') AS ultima_vez_ingreso
        FROM personal p
        LEFT JOIN roles r ON r.id_rol = p.id_rol
        LEFT JOIN (
            SELECT id_personal, MAX(ultimo_ping) AS ultimo_ping
            FROM usuarios_activos
            WHERE ultimo_ping >= (NOW() - INTERVAL 5 MINUTE)
            GROUP BY id_personal
        ) ua ON ua.id_personal = p.id_personal
        LEFT JOIN (
            SELECT id_personal, MAX(fecha_ingreso) AS ultima_vez_ingreso
            FROM usuarios_ingresos
            GROUP BY id_personal
        ) ul ON ul.id_personal = p.id_personal
        ORDER BY activo DESC, p.apellidos ASC, p.nombres ASC";

$stmt = $conn->query($sql);
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'server_time' => date('Y-m-d H:i:s'),
    'count' => count($rows),
    'items' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
