<?php
session_start();
require_once '../config/database.php';

// Verificar solo para administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->connect();

$id_personal = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success = $error = '';

if ($id_personal <= 0) {
    header('Location: personal.php');
    exit();
}

// Obtener roles para el select
$roles = $conn->query("SELECT id_rol, nombre_rol FROM roles ORDER BY nombre_rol")->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres  = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $celular  = trim($_POST['celular'] ?? '');
    $carnet   = trim($_POST['carnet'] ?? '');
    $id_rol   = intval($_POST['id_rol'] ?? 0);
    $nueva_password = $_POST['nueva_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';

    // Validaciones
    if (empty($nombres) || empty($apellidos) || empty($carnet) || $id_rol <= 0) {
        $error = "Todos los campos marcados con * son obligatorios";
    } elseif (!empty($nueva_password) || !empty($confirmar_password)) {
        if ($nueva_password !== $confirmar_password) {
            $error = "Las contraseñas no coinciden";
        } elseif (strlen($nueva_password) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres";
        }
    }

    if (empty($error)) {
        try {
            if (!empty($nueva_password)) {
                $hash_nueva = password_hash($nueva_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE personal 
                    SET nombres = ?, apellidos = ?, celular = ?, carnet_identidad = ?, id_rol = ?, password = ?
                    WHERE id_personal = ?
                ");
                $stmt->execute([
                    $nombres,
                    $apellidos,
                    $celular,
                    $carnet,
                    $id_rol,
                    $hash_nueva,
                    $id_personal
                ]);
            } else {
                $stmt = $conn->prepare("
                    UPDATE personal 
                    SET nombres = ?, apellidos = ?, celular = ?, carnet_identidad = ?, id_rol = ?
                    WHERE id_personal = ?
                ");
                $stmt->execute([
                    $nombres,
                    $apellidos,
                    $celular,
                    $carnet,
                    $id_rol,
                    $id_personal
                ]);
            }
            $success = "Información actualizada correctamente";
            // header("refresh:1;url=personal.php"); // opcional, redirigir tras 1 seg.
        } catch (PDOException $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Obtener datos actuales del personal
$stmt = $conn->prepare("
    SELECT id_personal, nombres, apellidos, celular, carnet_identidad, id_rol
    FROM personal WHERE id_personal = ?
");
$stmt->execute([$id_personal]);
$personal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personal) {
    header('Location: personal.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Editar Personal</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        body {
            background: #f4f8fa;
            min-height: 100vh;
        }

        .main-content {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .form-editar-box {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 6px 32px 0 rgba(40, 60, 100, .10);
            padding: 36px 32px 30px 32px;
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }

        .form-editar-box h1 {
            font-size: 2.1rem;
            color: #11305e;
            font-weight: 700;
            margin-bottom: 2.3rem;
            text-align: center;
        }

        .form-label {
            font-weight: 600;
            color: #11305e;
            font-size: .97rem;
            margin-bottom: 0.3rem;
        }

        .form-control,
        .form-select {
            background: #f6fcff;
            border: 1.5px solid #b1c8e9;
            border-radius: 7px;
            font-size: 1rem;
            min-height: 40px;
            margin-bottom: 1.2rem;
            transition: border-color .15s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #4682B4;
            box-shadow: 0 0 0 2px rgba(70, 130, 180, .10);
        }

        .btn-primary {
            background: #4682B4;
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 5px;
            padding: 10px 28px;
            font-size: 1.06rem;
            letter-spacing: .5px;
            box-shadow: 0 1px 3px rgba(70, 130, 180, .11);
            transition: background .18s;
        }

        .btn-primary:hover {
            background: #244876;
        }

        .btn-secondary {
            background: #f5f7fa;
            color: #4682B4;
            font-weight: 600;
            border: 1.5px solid #b1c8e9;
            border-radius: 5px;
            padding: 10px 22px;
            font-size: 1.06rem;
            margin-right: 8px;
            transition: background .16s;
        }

        .btn-secondary:hover {
            background: #eaf1fa;
            color: #244876;
        }

        .volver-listado {
            position: absolute;
            left: 0;
            top: 0;
            margin: 24px 0 0 35px;
        }

        @media (max-width: 600px) {
            .form-editar-box {
                padding: 16px 4px;
            }

            .form-editar-box h1 {
                font-size: 1.43rem;
            }

            .main-content {
                padding-left: 3px;
                padding-right: 3px;
            }

            .volver-listado {
                margin-left: 4px;
                margin-top: 9px;
            }
        }

        /* Opcional: para mejorar aún más la alineación en responsivo */
        @media (max-width: 600px) {
            .row .d-flex.gap-2.mt-3 {
                flex-direction: column;
                gap: 8px !important;
                align-items: stretch;
            }

            .row .d-flex.gap-2.mt-3 .btn {
                width: 100%;
                min-width: 0 !important;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid g-0">
        <div class="row g-0">
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 position-relative">
                <div class="main-content position-relative">

                    <div class="form-editar-box">
                        <h1>Editar Personal</h1>
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-12 col-md-6">
                                    <label for="nombres" class="form-label">Nombres *</label>
                                    <input type="text" class="form-control" id="nombres" name="nombres"
                                        value="<?php echo htmlspecialchars($personal['nombres']); ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="apellidos" class="form-label">Apellidos *</label>
                                    <input type="text" class="form-control" id="apellidos" name="apellidos"
                                        value="<?php echo htmlspecialchars($personal['apellidos']); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-md-6">
                                    <label for="celular" class="form-label">Celular</label>
                                    <input type="text" class="form-control" id="celular" name="celular"
                                        value="<?php echo htmlspecialchars($personal['celular']); ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="carnet" class="form-label">Carnet de Identidad *</label>
                                    <input type="text" class="form-control" id="carnet" name="carnet"
                                        value="<?php echo htmlspecialchars($personal['carnet_identidad']); ?>" required>
                                </div>
                            </div>
                            <div>
                                <label for="id_rol" class="form-label">Rol *</label>
                                <select class="form-select" id="id_rol" name="id_rol" required>
                                    <option value="">Seleccione un rol</option>
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?php echo $rol['id_rol']; ?>"
                                            <?php echo ($personal['id_rol'] == $rol['id_rol']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- CAMBIO DE CONTRASEÑA -->
                            <div class="row">
                                <div class="col-12 col-md-6">
                                    <label for="nueva_password" class="form-label">Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="nueva_password" name="nueva_password" autocomplete="new-password">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="confirmar_password" class="form-label">Confirmar Contraseña</label>
                                    <input type="password" class="form-control" id="confirmar_password" name="confirmar_password" autocomplete="new-password">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                                    <a href="personal.php" class="btn btn-outline-primary" style="min-width: 160px;">
                                        <span data-feather="arrow-left"></span> Volver al Listado
                                    </a>
                                    <button type="submit" class="btn btn-primary" style="min-width: 160px;">
                                        Guardar Cambios
                                    </button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        if (window.feather) {
            feather.replace();
        }
    </script>
</body>

</html>