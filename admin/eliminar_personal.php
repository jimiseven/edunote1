<?php
session_start();
require_once '../config/database.php';

// Verificar solo para administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'ID de personal inválido';
    header('Location: personal.php');
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
        header('Location: personal.php');
        exit();
    }

    // Eliminar el registro
    $stmt = $conn->prepare("DELETE FROM personal WHERE id_personal = :id");
    $stmt->execute([':id' => $_GET['id']]);

    $_SESSION['success_message'] = 'Personal eliminado exitosamente';
    header('Location: personal.php');
    exit();
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error al eliminar personal: ' . $e->getMessage();
    header('Location: personal.php');
    exit();
}