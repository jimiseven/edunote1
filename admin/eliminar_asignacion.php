<?php
session_start();
require_once '../config/database.php';

// Verificar acceso
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2])) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    exit('Método no permitido');
}

// Obtener ID de asignación
$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    exit('ID de asignación no proporcionado');
}

// Conectar a la base de datos
$database = new Database();
$conn = $database->connect();

try {
    // Eliminar asignación
    $stmt = $conn->prepare("DELETE FROM profesores_materias_cursos WHERE id_profesor_materia_curso = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        exit('Asignación eliminada correctamente');
    } else {
        http_response_code(404);
        exit('Asignación no encontrada');
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Error al eliminar asignación: ' . $e->getMessage());
}