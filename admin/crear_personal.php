<?php
session_start();
require_once '../config/database.php';

// Verificar solo para administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("
            INSERT INTO personal (nombres, apellidos, celular, carnet_identidad, id_rol, estado)
            VALUES (:nombres, :apellidos, :celular, :carnet_identidad, :id_rol, 1)
        ");
        
        $stmt->execute([
            ':nombres' => $_POST['nombres'],
            ':apellidos' => $_POST['apellidos'],
            ':celular' => $_POST['celular'],
            ':carnet_identidad' => $_POST['carnet_identidad'],
            ':id_rol' => $_POST['id_rol']
        ]);

        $_SESSION['success_message'] = 'Personal creado exitosamente';
        header('Location: personal.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error al crear personal: ' . $e->getMessage();
        header('Location: personal.php');
        exit();
    }
} else {
    header('Location: personal.php');
    exit();
}