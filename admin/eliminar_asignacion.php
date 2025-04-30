<?php
session_start();
require_once '../config/database.php';

// Verificar acceso y método
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' || !isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1])) {
    http_response_code(403);
    exit();
}

// Obtener ID de la asignación
$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de asignación no proporcionado']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Verificar si la asignación existe
    $stmt_check = $conn->prepare("SELECT id_profesor_materia_curso FROM profesores_materias_cursos WHERE id_profesor_materia_curso = ?");
    $stmt_check->execute([$id]);
    
    if (!$stmt_check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Asignación no encontrada']);
        exit();
    }

    // Eliminar la asignación
    $stmt_delete = $conn->prepare("DELETE FROM profesores_materias_cursos WHERE id_profesor_materia_curso = ?");
    $stmt_delete->execute([$id]);

    http_response_code(200);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}