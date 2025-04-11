<?php
session_start();
require_once 'config/database.php';

$usuario = trim($_POST['usuario']);
$contrasena = trim($_POST['contrasena']);

if (empty($usuario) || empty($contrasena)) {
    header('Location: index.php?error=empty');
    exit();
}

$db = new Database();
$conn = $db->connect();

$query = "SELECT id_personal, nombres, apellidos, id_rol FROM personal WHERE carnet_identidad = :usuario";
$stmt = $conn->prepare($query);
$stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($contrasena === $usuario) { // Simulando que CI es contraseña
        $_SESSION['user_id'] = $user['id_personal'];
        $_SESSION['user_name'] = $user['nombres'] . ' ' . $user['apellidos'];
        $_SESSION['user_role'] = $user['id_rol'];

        // Redirigir según el rol
        if ($_SESSION['user_role'] == 1) {
            header('Location: admin/dashboard.php');
        } elseif ($_SESSION['user_role'] == 2) {
            header('Location: profesor/dashboard.php');
        }
        exit();
    }
}
header('Location: index.php?error=invalid');
exit();
?>
