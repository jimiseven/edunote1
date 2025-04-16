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
                                    <?php if(isset($_GET['error'])): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <?php echo $_GET['error'] == 'invalid' ? 'Credenciales inválidas. Intente nuevamente.' : 'Error de autenticación'; ?>
                                        </div>
                                    <?php endif; ?>
                                    <form action="login.php" method="POST">
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="usuario" placeholder="Usuario" required>
                                        </div>
                                        <div class="mb-3">
                                            <input type="password" class="form-control" name="contrasena" placeholder="Contraseña" required>
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
</body>
</html>
