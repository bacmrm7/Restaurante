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
    <link rel="stylesheet" href="assets/app.css">
    <style>
        body.login-page {
            --login-forest: #18392d;
            --login-forest-deep: #10291f;
            --login-gold: #e4ad27;
            min-height: 100vh;
            display: grid;
            place-items: safe center;
            padding: 28px 18px !important;
            overflow-x: hidden;
            background-color: var(--login-forest-deep) !important;
            background-image: linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px), radial-gradient(ellipse at 50% 0%, #315744 0%, var(--login-forest) 43%, var(--login-forest-deep) 100%) !important;
            background-size: 44px 44px, 44px 44px, cover !important;
            color: #202a24;
        }
        .login-layout { width: min(100%, 440px); animation: login-enter .55s ease both; }
        .login-card {
            width: 100% !important;
            padding: clamp(26px, 7vw, 42px) !important;
            border: 1px solid rgba(255,255,255,.68) !important;
            border-radius: 14px !important;
            background: rgba(255,255,255,.97) !important;
            box-shadow: 0 28px 80px rgba(5,20,13,.38), 0 2px 8px rgba(5,20,13,.12) !important;
        }
        .login-brand { display: grid; justify-items: center; margin-bottom: 26px; }
        .login-logo-frame { display: grid; width: min(250px, 100%); min-height: 146px; place-items: center; padding: 10px 24px; border-radius: 10px; background: #0f2f22; }
        .login-logo { display: block; width: min(190px, 65%); height: 126px; object-fit: contain; }
        .login-brand-name { margin: 14px 0 0; color: var(--login-forest) !important; font-size: 14px; font-weight: 750; line-height: 1.4; text-align: center; }
        .login-brand-caption { margin: 4px 0 0; color: #758078; font-size: 10px; font-weight: 700; letter-spacing: 1.2px; text-align: center; text-transform: uppercase; }
        .login-heading { margin: 0 !important; color: #202a24 !important; font-size: 25px !important; font-weight: 750 !important; line-height: 1.2 !important; text-align: center; }
        .login-description { margin: 8px 0 25px !important; color: #748078 !important; font-size: 13px !important; line-height: 1.55 !important; text-align: center; }
        .login-form { display: grid; gap: 18px; }
        .login-field label { display: block; margin: 0 0 7px; color: #39483e !important; font-size: 12px !important; font-weight: 700 !important; }
        .login-input-wrap { position: relative; width: 100%; }
        .login-input-icon { position: absolute; z-index: 1; top: 50%; left: 14px; width: 17px; height: 17px; color: #829087; transform: translateY(-50%); pointer-events: none; transition: color .18s ease; }
        .login-card .login-input-wrap input[type="text"], .login-card .login-input-wrap input[type="password"] {
            width: 100%;
            min-height: 49px;
            padding: 12px 14px 12px 52px !important;
            border: 1px solid #dce3dd !important;
            border-radius: 7px !important;
            background: #fbfcfa !important;
            color: #202a24 !important;
            font-size: 14px !important;
            transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
        }
        .login-card #usuario, .login-card #password { padding-left: 52px !important; padding-inline-start: 52px !important; text-indent: 0 !important; }
        .login-input-wrap:hover input { border-color: #aab9ad !important; }
        .login-input-wrap:focus-within .login-input-icon { color: #28714d; }
        .login-card .login-input-wrap input:focus, .login-card .login-input-wrap input:focus-visible {
            border-color: #28714d !important;
            background: #fff !important;
            box-shadow: 0 0 0 3px rgba(40,113,77,.13) !important;
            outline: none !important;
        }
        .login-error { margin: 0; padding: 11px 13px; border: 1px solid #f1d0cb; border-radius: 6px; background: #fff2ef; color: #a33d32; font-size: 12px; line-height: 1.45; }
        .login-card .login-submit {
            display: flex;
            width: 100%;
            min-height: 49px;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 3px;
            border: 1px solid var(--login-forest) !important;
            border-radius: 7px !important;
            background: var(--login-forest) !important;
            color: #fff !important;
            font-size: 13px !important;
            font-weight: 750 !important;
            cursor: pointer;
            transition: background-color .18s ease, border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }
        .login-card .login-submit:hover { border-color: #286044 !important; background: #286044 !important; box-shadow: 0 8px 18px rgba(24,57,45,.2); transform: translateY(-1px); }
        .login-card .login-submit:active { transform: translateY(0); }
        .login-card .login-submit:focus-visible { outline: 3px solid rgba(228,173,39,.62) !important; outline-offset: 3px; }
        .login-submit svg { width: 17px; height: 17px; }
        @keyframes login-enter { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 480px) {
            body.login-page { padding: 18px 14px !important; }
            .login-logo-frame { min-height: 128px; }
            .login-logo { height: 108px; }
            .login-brand { margin-bottom: 22px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .login-layout { animation: none; }
            .login-input-icon, .login-card input, .login-card .login-submit { transition: none; }
        }
    </style>
</head>
<body class="login-page">
    <main class="login-layout">
      <section class="login-card" aria-labelledby="login-heading">
        <div class="login-brand">
            <div class="login-logo-frame">
                <img class="login-logo" src="assets/img/logo.png" alt="Hijos del Rey C.A. Restaurante">
            </div>
            <p class="login-brand-name">Hijos del Rey C.A.</p>
            <p class="login-brand-caption">Sistema de gestión</p>
        </div>
        <h1 class="login-heading" id="login-heading">Iniciar sesión</h1>
        <p class="login-description">Accede a la gestión de inventario y operaciones.</p>
        <?php if (!empty($error)): ?>
            <div class="login-error" role="alert" aria-live="polite"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form class="login-form" action="login.php" method="POST">
            <div class="login-field">
                <label for="usuario">Usuario</label>
                <div class="login-input-wrap">
                    <svg class="login-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg>
                    <input type="text" id="usuario" name="usuario" required autocomplete="username">
                </div>
            </div>
            <div class="login-field">
                <label for="password">Contraseña</label>
                <div class="login-input-wrap">
                    <svg class="login-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/><path d="M12 14v3"/></svg>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
            </div>
            <button class="login-submit" type="submit">
                Ingresar
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </button>
        </form>
      </section>
    </main>
</body>
</html>