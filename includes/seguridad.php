<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ruta_base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if (basename($ruta_base) === 'controllers') {
    $ruta_base = dirname($ruta_base);
}

// 1. Verifica que el usuario haya iniciado sesión
function requerirAutenticacion() {
    // Acepta tanto 'usuario_id' como 'user_id' por compatibilidad
    if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['user_id'])) {
        global $ruta_base;
        header('Location: ' . $ruta_base . '/login.php');
        exit(); // Detiene la ejecución inmediatamente
    }
}

function tiene_permiso(string $nombreModulo): bool {
    $rolesUsuario = array_values(array_unique(array_map('intval', array_merge($_SESSION['roles_ids'] ?? [], [(int) ($_SESSION['rol_id'] ?? 0)]))));
    $rolesUsuario = array_values(array_filter($rolesUsuario, static fn (int $rolId): bool => $rolId > 0));
    if (!$rolesUsuario) {
        return false;
    }
    if (in_array(1, $rolesUsuario, true)) {
        return true;
    }

    $db = $GLOBALS['conexion_seguridad'] ?? null;
    if (!$db instanceof PDO) {
        require_once dirname(__DIR__) . '/config/conexion.php';
        $db = $GLOBALS['conexion_seguridad'] ?? null;
    }
    if (!$db instanceof PDO) {
        return false;
    }

    $marcadores = implode(',', array_fill(0, count($rolesUsuario), '?'));
        $consultaAcceso = $db->prepare("SELECT COALESCE(MAX(accesos.habilitado), 0) FROM erp_seguridad.modulos AS modulos LEFT JOIN erp_seguridad.r_modulo_rol AS accesos ON accesos.modulo_id = modulos.id AND accesos.rol_id IN ($marcadores) WHERE modulos.nombre = ?");
    $consultaAcceso->execute(array_merge($rolesUsuario, [$nombreModulo]));
    return (bool) $consultaAcceso->fetchColumn();
}

// 2. Verifica si el rol del usuario tiene permiso para acceder
function requerirRol($roles_permitidos = []) {
    global $ruta_base;
    requerirAutenticacion();
    global $conexion, $conexion_seguridad;

    $roles_usuario = array_values(array_unique(array_map('intval', array_merge($_SESSION['roles_ids'] ?? [], [(int) ($_SESSION['rol_id'] ?? 0)]))));
    $roles_permitidos = array_map('intval', (array) $roles_permitidos);
    $autorizado = (bool) array_intersect($roles_usuario, $roles_permitidos);
    $modulos_por_ruta = [
        'index.php' => 'Inventario',
        'registrar_producto.php' => 'Inventario',
        'editar_producto.php' => 'Inventario',
        'eliminar_producto.php' => 'Inventario',
        'ventas.php' => 'Punto de Venta',
        'procesar_venta.php' => 'Punto de Venta',
        'recetas.php' => 'Recetas',
        'menu.php' => 'Menú y Combos',
        'cierre_caja.php' => 'Cierre de Caja',
        'reporte_ventas.php' => 'Reportes de Ventas',
        'gastos.php' => 'Gastos operativos',
        'configuracion.php' => 'Configuración',
    ];
    $ruta_actual = basename($_SERVER['PHP_SELF'] ?? '');

    if (isset($modulos_por_ruta[$ruta_actual])) {
        try {
            $permisoModulo = tiene_permiso($modulos_por_ruta[$ruta_actual]);
            $autorizado = in_array(1, $roles_usuario, true) || $permisoModulo;
        } catch (Throwable $exception) {
            $autorizado = false;
        }
    }

    if (!$autorizado) {
        http_response_code(403);
        echo "<!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Acceso Restringido | Hijos del Rey C.A.</title>
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
            <link rel='stylesheet' href='{$ruta_base}/assets/app.css'>
            <script src='{$ruta_base}/assets/app-ui.js' defer></script>
        </head>
        <body>
            <div class='card'>
                <h1 class='page-title'>403 - Acceso Restringido</h1>
                <p class='page-subtitle'>Tu rol no tiene permisos para acceder a este módulo.</p>
                <div class='actions'>
                    <a href='ventas.php' class='btn btn-primary'>💳 Ir al Módulo POS</a>
                    <a href='{$ruta_base}/controllers/logout.php' class='btn btn-secondary'>🚪 Cerrar Sesión</a>
                </div>
            </div>
        </body>
        </html>";
        exit();
    }
}
?>