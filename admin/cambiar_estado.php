<?php
session_start();
require_once '../config/database.php';

// Verificar solo para administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['estado'])) {
    $_SESSION['error_message'] = 'Parámetros inválidos';
    header("Location: personal.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

try {
    // Verificar si el personal existe
    $stmt = $conn->prepare("SELECT id_personal FROM personal WHERE id_personal = :id");
    $stmt->execute([':id' => $_GET['id']]);
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['error_message'] = 'El personal no existe';
        header("Location: personal.php");
        exit();
    }

    // Cambiar el estado
    $nuevo_estado = $_GET['estado'] ? 1 : 0;
    $stmt = $conn->prepare("UPDATE personal SET estado = :estado WHERE id_personal = :id");
    $stmt->execute([
        ':estado' => $nuevo_estado,
        ':id' => $_GET['id']
    ]);

    $_SESSION['success_message'] = 'Estado actualizado exitosamente';
    header("Location: personal.php");
    exit();
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error al cambiar estado: ' . $e->getMessage();
    header("Location: personal.php");
    exit();
}