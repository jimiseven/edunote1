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

    $id_responsable = isset($_POST['id_responsable']) ? trim($_POST['id_responsable']) : '';
    $responsable_ci = trim($_POST['responsable_ci'] ?? '');
    $responsable_nombre = trim($_POST['responsable_nombre'] ?? '');
    $responsable_apellido = trim($_POST['responsable_apellido'] ?? '');
    $responsable_telefono = trim($_POST['responsable_telefono'] ?? '');

    // Validaciones básicas
    if (
        $nombres === '' ||
        $apellido_paterno === '' ||
        !$id_curso
    ) {
        $_SESSION['error'] = "Por favor, complete todos los campos obligatorios.";
        header('Location: estudiantes.php');
        exit();
    }

    if ($id_responsable === '' && $responsable_ci !== '' && ($responsable_nombre === '' || $responsable_apellido === '')) {
        $_SESSION['error'] = "Si registra un responsable, complete Nombre y Apellido del responsable.";
        header('Location: estudiantes.php');
        exit();
    }

    try {
        $db = new Database();
        $conn = $db->connect();

        $rude = $rude === '' ? null : $rude;
        $carnet_identidad = $carnet_identidad === '' ? null : $carnet_identidad;

        $conn->beginTransaction();

        $final_id_responsable = null;
        if ($id_responsable !== '') {
            $final_id_responsable = (int)$id_responsable;
        } elseif ($responsable_ci !== '') {
            $stmt = $conn->prepare('SELECT id_responsable FROM responsables WHERE carnet_identidad = :ci LIMIT 1');
            $stmt->bindParam(':ci', $responsable_ci);
            $stmt->execute();
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existente) {
                $final_id_responsable = (int)$existente['id_responsable'];
            } else {
                $stmt = $conn->prepare('INSERT INTO responsables (nombre, apellido, carnet_identidad, telefono) VALUES (:nombre, :apellido, :ci, :telefono)');
                $stmt->bindParam(':nombre', $responsable_nombre);
                $stmt->bindParam(':apellido', $responsable_apellido);
                $stmt->bindParam(':ci', $responsable_ci);
                $stmt->bindParam(':telefono', $responsable_telefono);
                $stmt->execute();
                $final_id_responsable = (int)$conn->lastInsertId();
            }
        }

        $sql = "INSERT INTO estudiantes 
            (nombres, apellido_paterno, apellido_materno, genero, rude, carnet_identidad, fecha_nacimiento, id_curso, id_responsable, estado_1, estado_2)
            VALUES
            (:nombres, :apellido_paterno, :apellido_materno, :genero, :rude, :carnet_identidad, :fecha_nacimiento, :id_curso, :id_responsable, :estado_1, :estado_2)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':nombres', $nombres);
        $stmt->bindParam(':apellido_paterno', $apellido_paterno);
        $stmt->bindParam(':apellido_materno', $apellido_materno);
        $stmt->bindParam(':genero', $genero);
        $stmt->bindParam(':rude', $rude);
        $stmt->bindParam(':carnet_identidad', $carnet_identidad);
        $stmt->bindParam(':fecha_nacimiento', $fecha_nacimiento);
        $stmt->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
        $stmt->bindValue(':id_responsable', $final_id_responsable, $final_id_responsable === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':estado_1', 'EFECTIVO');
        $stmt->bindValue(':estado_2', null, PDO::PARAM_NULL);

        $stmt->execute();

        $conn->commit();

        $_SESSION['success'] = "Estudiante registrado correctamente.";
        header('Location: estudiantes.php');
        exit();
    } catch (PDOException $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "Error al guardar el estudiante: " . $e->getMessage();
        header('Location: estudiantes.php');
        exit();
    }
} else {
    header('Location: estudiantes.php');
    exit();
}
?>
