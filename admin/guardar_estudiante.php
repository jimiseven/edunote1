<?php
session_start();
require_once '../config/database.php';

// Solo para administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres = trim($_POST['nombres'] ?? '');
    $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
    $apellido_materno = trim($_POST['apellido_materno'] ?? '');
    $genero = trim($_POST['genero'] ?? '');
    $rude = trim($_POST['rude'] ?? '');
    $carnet_identidad = trim($_POST['ci'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? null);
    $id_curso = intval($_POST['curso'] ?? 0);

    // Validaciones básicas
    if (
        $nombres === '' ||
        $apellido_paterno === '' ||
        $rude === '' ||
        $carnet_identidad === '' ||
        !$id_curso
    ) {
        $_SESSION['error'] = "Por favor, complete todos los campos obligatorios.";
        header('Location: estudiantes.php');
        exit();
    }

    try {
        $db = new Database();
        $conn = $db->connect();

        $sql = "INSERT INTO estudiantes 
            (nombres, apellido_paterno, apellido_materno, genero, rude, carnet_identidad, fecha_nacimiento, id_curso)
            VALUES
            (:nombres, :apellido_paterno, :apellido_materno, :genero, :rude, :carnet_identidad, :fecha_nacimiento, :id_curso)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':nombres', $nombres);
        $stmt->bindParam(':apellido_paterno', $apellido_paterno);
        $stmt->bindParam(':apellido_materno', $apellido_materno);
        $stmt->bindParam(':genero', $genero);
        $stmt->bindParam(':rude', $rude);
        $stmt->bindParam(':carnet_identidad', $carnet_identidad);
        $stmt->bindParam(':fecha_nacimiento', $fecha_nacimiento);
        $stmt->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);

        $stmt->execute();

        $_SESSION['success'] = "Estudiante registrado correctamente.";
        header('Location: estudiantes.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al guardar el estudiante: " . $e->getMessage();
        header('Location: estudiantes.php');
        exit();
    }
} else {
    header('Location: estudiantes.php');
    exit();
}
?>
