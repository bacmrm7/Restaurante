<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verifica que el usuario haya iniciado sesión
function requerirAutenticacion() {
    // Acepta tanto 'usuario_id' como 'user_id' por compatibilidad
    if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit(); // Detiene la ejecución inmediatamente
    }
}

// 2. Verifica si el rol del usuario tiene permiso para acceder
function requerirRol($roles_permitidos = []) {
    requerirAutenticacion();
    
    // Obtener el rol de la sesión actual
    $rol_actual = $_SESSION['rol_id'] ?? null;
    
    if (!in_array($rol_actual, $roles_permitidos)) {
        http_response_code(403);
        echo "<!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Acceso Restringido - ERP Hijos del Rey</title>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 480px; width: 100%; }
                h1 { color: #dc3545; margin-bottom: 10px; font-size: 24px; }
                p { color: #555; font-size: 14px; margin-bottom: 25px; line-height: 1.5; }
                .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
                .btn { display: inline-block; padding: 10px 18px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; }
                .btn-primary { background-color: #007bff; color: white; }
                .btn-primary:hover { background-color: #0056b3; }
                .btn-secondary { background-color: #6c757d; color: white; }
                .btn-secondary:hover { background-color: #5a6268; }
            </style>
            <link rel='stylesheet' href='app.css'>
            <script src='app-ui.js' defer></script>
        </head>
        <body>
            <div class='card'>
                <h1>⚠️ 403 - Acceso Restringido</h1>
                <p>Tu rol de usuario no tiene los privilegios necesarios para acceder a este módulo del sistema de <strong>Hijos del Rey</strong>.</p>
                <div class='actions'>
                    <a href='ventas.php' class='btn btn-primary'>💳 Ir al Módulo POS</a>
                    <a href='logout.php' class='btn btn-secondary'>🚪 Cerrar Sesión</a>
                </div>
            </div>
        </body>
        </html>";
        exit();
    }
}
?>