<?php
session_start();
require_once '../config/database.php';

try {
    if (isset($_SESSION['user_id'])) {
        $db = new Database();
        $conn = $db->connect();
        if ($conn) {
            $stmt = $conn->prepare("DELETE FROM usuarios_activos WHERE session_id = ? OR id_personal = ?");
            $stmt->execute([session_id(), (int)$_SESSION['user_id']]);
        }
    }
} catch (Throwable $e) {
}

session_unset(); // Elimina todas las variables de sesión
session_destroy(); // Destruye la sesión
header("Location: ../index.php"); // Redirige al usuario a la página de inicio de sesión
exit();
?>
