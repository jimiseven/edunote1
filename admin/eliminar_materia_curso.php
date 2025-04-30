<?php
session_start();
require_once '../config/database.php';

// Verificar acceso y método
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1])) {
    http_response_code(403);
    exit();
}

// Obtener parámetros
$id_materia = $_POST['id_materia'] ?? null;
$id_curso = $_POST['id_curso'] ?? null;
$nivel = $_POST['nivel'] ?? null;
$curso = $_POST['curso'] ?? null;
$paralelo = $_POST['paralelo'] ?? null;

if (!$id_materia || !$id_curso || !$nivel || !$curso || !$paralelo) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos incompletos']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Iniciar transacción
    $conn->beginTransaction();

    // 1. Eliminar asignaciones de profesores primero
    $stmt_delete_asignaciones = $conn->prepare("
        DELETE pmc FROM profesores_materias_cursos pmc
        JOIN cursos_materias cm ON pmc.id_curso_materia = cm.id_curso_materia
        WHERE cm.id_curso = ? AND cm.id_materia = ?
    ");
    $stmt_delete_asignaciones->execute([$id_curso, $id_materia]);

    // 2. Eliminar la relación curso-materia
    $stmt_delete_relacion = $conn->prepare("
        DELETE FROM cursos_materias 
        WHERE id_curso = ? AND id_materia = ?
    ");
    $stmt_delete_relacion->execute([$id_curso, $id_materia]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'redirect' => "ver_asig.php?nivel=$nivel&curso=$curso&paralelo=$paralelo"
    ]);
} catch (PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}