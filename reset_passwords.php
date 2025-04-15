<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->connect();

// Generar hash para "uesimonb"
$nueva_contrasena = 'uesimonb';
$hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);

// Actualizar todas las contraseñas
$stmt = $conn->prepare("UPDATE personal SET password = ?");
$stmt->execute([$hash]);

echo "¡Contraseñas restablecidas correctamente!";
?>
