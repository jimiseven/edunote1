<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduNote - Login</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="container-fluid p-0">
        <div class="login-header">
            <div class="container">
                <h3 class="text-white py-2">Inicio de Sesión</h3>
            </div>
        </div>
        
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card login-card shadow">
                        <div class="card-body p-0">
                            <div class="row g-0">
                                <!-- Logo -->
                                <div class="col-md-6 d-flex align-items-center justify-content-center p-5">
                                    <img src="assets/img/info.png" class="img-fluid" style="max-width: 200px;" alt="EduNote Logo">
                                </div>
                                <!-- Formulario -->
                                <div class="col-md-6 bg-light p-4 rounded-end d-flex flex-column justify-content-center">
                                    <h2 class="text-center mb-4">Bienvenido</h2>
                                    <?php
                                    if(isset($_GET['error'])) {
                                        $errorMessages = [
                                            'wrong_password' => 'Contraseña incorrecta',
                                            'user_not_found' => 'Usuario no encontrado',
                                            'inhabilitado' => 'Su usuario está inhabilitado. Contacte al administrador.',
                                            'empty' => 'Complete todos los campos'
                                        ];
                                        $errorType = $_GET['error'];
                                        $alertClass = $errorType == 'empty' ? 'alert-warning' : 'alert-danger';
                                        
                                        if(array_key_exists($errorType, $errorMessages)) {
                                            echo '<div class="alert '.$alertClass.'" role="alert">'.$errorMessages[$errorType].'</div>';
                                        } else {
                                            echo '<div class="alert alert-danger" role="alert">Error de autenticación</div>';
                                        }
                                    }
                                    ?>
                                    <form action="login.php" method="POST">
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="usuario" placeholder="Usuario" required>
                                        </div>
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="contrasena" name="contrasena" placeholder="Contraseña" required>
                                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                    Ver
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mb-3 form-check">
                                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                            <label class="form-check-label" for="remember">Recordar mi usuario</label>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary">Ingresar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
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
                this.textContent = 'Ocultar';
            } else {
                passwordInput.type = 'password';
                this.textContent = 'Ver';
            }
        });

        // Recordar usuario
        document.addEventListener('DOMContentLoaded', function() {
            const rememberedUser = localStorage.getItem('rememberedUser');
            if (rememberedUser) {
                document.querySelector('input[name="usuario"]').value = rememberedUser;
                document.getElementById('remember').checked = true;
            }
        });

        document.querySelector('form').addEventListener('submit', function() {
            if (document.getElementById('remember').checked) {
                localStorage.setItem('rememberedUser', document.querySelector('input[name="usuario"]').value);
            } else {
                localStorage.removeItem('rememberedUser');
            }
        });
    </script>
</body>
</html>
<!-- modificaciones de 6 marzo 10:30 -->
