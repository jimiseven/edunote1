<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$id_curso = isset($_GET['id_curso']) ? intval($_GET['id_curso']) : 0;
if (!$id_curso) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Curso no especificado']);
    exit();
}

// Conectar a la base de datos
$database = new Database();
$conn = $database->connect();

// Obtener datos necesarios para el centralizador
// (estudiantes, materias, calificaciones, promedios)

// 1. Obtener estudiantes
$stmt_estudiantes = $conn->prepare("
    SELECT id_estudiante, apellido_paterno, apellido_materno, nombres 
    FROM estudiantes 
    WHERE id_curso = ? 
    ORDER BY apellido_paterno, apellido_materno, nombres
");
$stmt_estudiantes->execute([$id_curso]);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// 2. Obtener materias
$stmt_materias = $conn->prepare("
    SELECT m.id_materia, m.nombre_materia, m.es_extra, m.es_submateria, m.materia_padre_id
    FROM cursos_materias cm 
    JOIN materias m ON cm.id_materia = m.id_materia 
    WHERE cm.id_curso = ?
");
$stmt_materias->execute([$id_curso]);
$materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

// 3. Organizar materias como en ver_curso.php
// [código de organización como en el original]

// 4. Obtener calificaciones
$calificaciones = [];
foreach ($estudiantes as $estudiante) {
    foreach ($materias as $materia) {
        for ($i = 1; $i <= 3; $i++) {
            $stmt = $conn->prepare("
                SELECT calificacion 
                FROM calificaciones 
                WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
            ");
            $stmt->execute([$estudiante['id_estudiante'], $materia['id_materia'], $i]);
            $nota = $stmt->fetchColumn();
            $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$i] = $nota !== false ? $nota : '';
        }
    }
}

// 5. Calcular promedios
// [código de cálculo como en el original]

// 6. Devolver datos JSON
header('Content-Type: application/json');
echo json_encode([
    'estudiantes' => $estudiantes,
    'materias' => $materias_ordenadas,
    'calificaciones' => $calificaciones,
    'promedios_materias' => $promedios_materias,
    'promedios_generales' => $promedios_generales,
    'posiciones' => $posiciones
]);
exit();
