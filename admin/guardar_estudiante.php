<?php
session_start();
require_once '../config/database.php';

// Solo para administrador
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 4], true)) {
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

    $responsablesInput = [];
    for ($i = 1; $i <= 2; $i++) {
        $responsablesInput[] = [
            'id_responsable' => trim($_POST['id_responsable_' . $i] ?? ''),
            'ci' => trim($_POST['responsable_ci_' . $i] ?? ''),
            'nombre' => trim($_POST['responsable_nombre_' . $i] ?? ''),
            'apellido' => trim($_POST['responsable_apellido_' . $i] ?? ''),
            'telefono' => trim($_POST['responsable_telefono_' . $i] ?? ''),
            'tipo_responsable' => trim($_POST['tipo_responsable_' . $i] ?? '')
        ];
    }

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

    foreach ($responsablesInput as $index => $responsable) {
        $slot = $index + 1;
        $tieneDatos = ($responsable['ci'] !== '' || $responsable['nombre'] !== '' || $responsable['apellido'] !== '' || $responsable['telefono'] !== '');

        if (!$tieneDatos) {
            continue;
        }

        if ($responsable['ci'] === '') {
            $_SESSION['error'] = "Si registra el responsable {$slot}, debe completar su CI.";
            header('Location: estudiantes.php');
            exit();
        }

        if ($responsable['id_responsable'] === '' && ($responsable['nombre'] === '' || $responsable['apellido'] === '')) {
            $_SESSION['error'] = "Si registra el responsable {$slot}, complete Nombre y Apellido.";
            header('Location: estudiantes.php');
            exit();
        }
    }

    try {
        $db = new Database();
        $conn = $db->connect();

        $rude = $rude === '' ? null : $rude;
        $carnet_identidad = $carnet_identidad === '' ? null : $carnet_identidad;

        $conn->beginTransaction();

        $responsablesFinales = [];
        foreach ($responsablesInput as $index => $responsable) {
            $tieneDatos = ($responsable['ci'] !== '' || $responsable['nombre'] !== '' || $responsable['apellido'] !== '' || $responsable['telefono'] !== '');
            if (!$tieneDatos && $responsable['id_responsable'] === '') {
                continue;
            }

            $finalIdResponsable = null;
            if ($responsable['id_responsable'] !== '') {
                $finalIdResponsable = (int)$responsable['id_responsable'];
            } else {
                $stmt = $conn->prepare('SELECT id_responsable FROM responsables WHERE carnet_identidad = :ci LIMIT 1');
                $stmt->bindParam(':ci', $responsable['ci']);
                $stmt->execute();
                $existente = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existente) {
                    $finalIdResponsable = (int)$existente['id_responsable'];
                } else {
                    $stmt = $conn->prepare('INSERT INTO responsables (nombre, apellido, carnet_identidad, telefono) VALUES (:nombre, :apellido, :ci, :telefono)');
                    $stmt->bindParam(':nombre', $responsable['nombre']);
                    $stmt->bindParam(':apellido', $responsable['apellido']);
                    $stmt->bindParam(':ci', $responsable['ci']);
                    $stmt->bindParam(':telefono', $responsable['telefono']);
                    $stmt->execute();
                    $finalIdResponsable = (int)$conn->lastInsertId();
                }
            }

            if ($finalIdResponsable !== null && !isset($responsablesFinales[$finalIdResponsable])) {
                $tipo = $responsable['tipo_responsable'];
                if (!in_array($tipo, ['PADRE', 'MADRE', 'TUTOR'], true)) {
                    $tipo = null;
                }
                $responsablesFinales[$finalIdResponsable] = [
                    'id_responsable' => $finalIdResponsable,
                    'tipo_responsable' => $tipo,
                    'es_principal' => ($index === 0) ? 1 : 0
                ];
            }
        }

        $responsablesFinales = array_values($responsablesFinales);
        $responsableLegacy = isset($responsablesFinales[0]) ? (int)$responsablesFinales[0]['id_responsable'] : null;

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
        $stmt->bindValue(':id_responsable', $responsableLegacy, $responsableLegacy === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':estado_1', 'EFECTIVO');
        $stmt->bindValue(':estado_2', null, PDO::PARAM_NULL);

        $stmt->execute();

        $idEstudianteNuevo = (int)$conn->lastInsertId();

        if (!empty($responsablesFinales)) {
            $stmtRel = $conn->prepare('INSERT INTO estudiantes_responsables (id_estudiante, id_responsable, tipo_responsable, es_principal) VALUES (:id_estudiante, :id_responsable, :tipo_responsable, :es_principal)');
            foreach ($responsablesFinales as $item) {
                $stmtRel->bindValue(':id_estudiante', $idEstudianteNuevo, PDO::PARAM_INT);
                $stmtRel->bindValue(':id_responsable', $item['id_responsable'], PDO::PARAM_INT);
                $stmtRel->bindValue(':tipo_responsable', $item['tipo_responsable'], $item['tipo_responsable'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmtRel->bindValue(':es_principal', $item['es_principal'], PDO::PARAM_INT);
                $stmtRel->execute();
            }
        }

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
