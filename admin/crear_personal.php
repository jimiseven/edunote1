<?php
session_start();
require_once '../config/database.php';

// Verificar solo para administrador
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 4], true)) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos requeridos
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $carnet_identidad = trim($_POST['carnet_identidad'] ?? '');
        $celular = trim($_POST['celular'] ?? '');
        $id_rol = (int)($_POST['id_rol'] ?? 0);

        if (empty($nombres) || empty($apellidos) || empty($carnet_identidad) || $id_rol <= 0) {
            throw new RuntimeException('Todos los campos obligatorios deben ser completados.');
        }

        // Verificar si el carnet ya existe
        $stmt_check = $conn->prepare("SELECT id_personal FROM personal WHERE carnet_identidad = ?");
        $stmt_check->execute([$carnet_identidad]);
        if ($stmt_check->fetch()) {
            throw new RuntimeException('Ya existe un personal con ese carnet de identidad.');
        }

        // Generar password por defecto (hash del carnet de identidad)
        $password_default = password_hash($carnet_identidad, PASSWORD_DEFAULT);

        // Insertar nuevo personal
        $stmt = $conn->prepare("
            INSERT INTO personal (nombres, apellidos, celular, carnet_identidad, id_rol, password, estado)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");

        $celular = empty($celular) ? null : $celular;
        $stmt->execute([$nombres, $apellidos, $celular, $carnet_identidad, $id_rol, $password_default]);

        $_SESSION['success_message'] = 'Personal creado exitosamente';
        header('Location: personal.php');
        exit();
    } catch (RuntimeException $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: personal.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error de base de datos: ' . $e->getMessage();
        header('Location: personal.php');
        exit();
    }
} else {
    header('Location: personal.php');
    exit();
}