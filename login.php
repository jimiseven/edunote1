<?php
session_start();
require_once 'config/database.php';

// Mensajes de error
$error = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'inhabilitado':
            $error = '<div class="alert alert-danger">Su cuenta no está habilitada. Contacte al administrador.</div>';
            break;
        case 'credenciales':
            $error = '<div class="alert alert-danger">Usuario o contraseña incorrectos</div>';
            break;
        case 'empty':
            $error = '<div class="alert alert-warning">Complete todos los campos</div>';
            break;
    }
}

// Recordar usuario
$rememberUser = isset($_COOKIE['remember_user']) ? $_COOKIE['remember_user'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $contrasena = trim($_POST['contrasena']);

if (empty($usuario) || empty($contrasena)) {
    header('Location: index.php?error=empty');
    exit();
}

$db = new Database();
$conn = $db->connect();

// 1. Modificar la consulta para incluir el campo password
$query = "SELECT id_personal, nombres, apellidos, id_rol, password, estado
          FROM personal
          WHERE carnet_identidad = :usuario AND estado = 1";
$stmt = $conn->prepare($query);
$stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Cambiar la verificación por password_verify()
    if (password_verify($contrasena, $user['password'])) {
        // Verificar si el usuario está habilitado
        if ($user['estado'] != 1) {
            header('Location: index.php?error=inhabilitado');
            exit();
        }
        $_SESSION['user_id'] = $user['id_personal'];
        $_SESSION['user_name'] = $user['nombres'] . ' ' . $user['apellidos'];
        $_SESSION['user_role'] = $user['id_rol'];

        // Redirigir según el rol (mantenemos la lógica existente)
        if ($_SESSION['user_role'] == 1) {
            header('Location: admin/dash_iniciales.php');
        } elseif ($_SESSION['user_role'] == 2) {
            header('Location: profesor/dashboard.php');
        }
        elseif ($user['id_rol'] == 3) {
            header('Location: direc/iniv.php'); // Directora_SV (solo centralizadores)
            exit();
        }
        exit();
    }
}

// 3. Mejorar mensajes de error
    // Si no encontró usuario o está inhabilitado
    if ($stmt->rowCount() > 0 && isset($user['estado']) && $user['estado'] == 0) {
        header('Location: index.php?error=inhabilitado');
    } else {
        header('Location: index.php?error=credenciales');
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EduNote</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-center mb-4">Iniciar Sesión</h3>
                        <?php echo $error; ?>
                        <form method="POST" action="login.php">
                            <div class="mb-3">
                                <label for="usuario" class="form-label">Usuario (CI)</label>
                                <input type="text" class="form-control" id="usuario" name="usuario"
                                    value="<?php echo htmlspecialchars($rememberUser); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="contrasena" class="form-label">Contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="contrasena" name="contrasena" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember"
                                    <?php echo $rememberUser ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="remember">Recordar usuario</label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar/ocultar contraseña
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('contrasena');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });

        // Validación en tiempo real
        document.querySelector('form').addEventListener('submit', function(e) {
            const usuario = document.getElementById('usuario').value.trim();
            const contrasena = document.getElementById('contrasena').value.trim();
            
            if (!usuario || !contrasena) {
                e.preventDefault();
                alert('Complete todos los campos');
            }
        });
    </script>
</body>
</html>
exit();
?>
