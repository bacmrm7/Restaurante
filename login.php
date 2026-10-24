<?php
session_start();
require_once __DIR__ . '/config/conexion.php';

$error = $_SESSION['error_login'] ?? '';
unset($_SESSION['error_login']); // Limpiar el error después de leerlo

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($usuario) && !empty($password)) {
        $sql = "SELECT * FROM usuarios WHERE username = :usuario";
        $stmt = $conexion_seguridad->prepare($sql);
        $stmt->execute(['usuario' => $usuario]);
        $user = $stmt->fetch();

        // Verificación de credenciales
        if ($user && ($password === $user['password'] || password_verify($password, $user['password']))) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['nombre_completo'] = $user['nombre_completo'] ?? $user['nombre'] ?? $user['username'];
            $_SESSION['usuario'] = $user['username'];
            $_SESSION['rol_id'] = $user['rol_id'];

            if (function_exists('registrarLog')) {
                registrarLog($conexion_seguridad, $user['id'], 'Inicio de sesión', 'Inicio de sesión exitoso al sistema');
            }

            // Redirección inteligente según el rol del usuario
            if ($_SESSION['rol_id'] == 2) {
                header("Location: ventas.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $_SESSION['error_login'] = "Usuario o contraseña incorrectos.";
        }
    } else {
        $_SESSION['error_login'] = "Por favor, completa todos los campos.";
    }
    
    // REDIRECCIÓN OBLIGATORIA (Evita el reenvío de formulario al recargar)
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Hijos del Rey C.A.</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { text-align: center; color: #333; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 6px; color: #555; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background-color: #007bff; border: none; border-radius: 4px; color: white; font-size: 16px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 16px; text-align: center; font-size: 14px; }
    </style>
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app-ui.js" defer></script>
</head>
<body>
    <div class="login-card">
        <h1 class="page-title">Iniciar sesión</h1>
        <p class="page-subtitle">Accede a la gestión de inventario y operaciones.</p>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="usuario">Usuario:</label>
                <input type="text" id="usuario" name="usuario" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>