<?php
require_once __DIR__ . '/../includes/seguridad.php';
requerirRol([1]);
require_once __DIR__ . '/../config/conexion.php';

$esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$responderJson = static function ($success, $message, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($esAjax) {
        $responderJson(false, 'La ruta requiere una solicitud POST.', 405);
    }
    header('Location: ../configuracion.php');
    exit;
}

if (empty($_SESSION['csrf_configuracion']) || !hash_equals($_SESSION['csrf_configuracion'], $_POST['csrf_token'] ?? '')) {
    if ($esAjax) {
        $responderJson(false, 'La sesión del formulario venció. Recarga la página e inténtalo de nuevo.', 403);
    }
    http_response_code(403);
    exit('La solicitud venció. Regresa a configuración e inténtalo de nuevo.');
}

$accion = $_POST['accion'] ?? '';
$accionesPermitidas = ['vaciar_ventas_caja', 'resetear_stocks', 'reinicio_general'];
if (!in_array($accion, $accionesPermitidas, true)) {
    if ($esAjax) {
        $responderJson(false, 'La acción de mantenimiento solicitada no es válida.', 400);
    }
    http_response_code(400);
    exit('La acción de mantenimiento solicitada no es válida.');
}

try {
    $conexion->beginTransaction();

    if ($accion === 'vaciar_ventas_caja' || $accion === 'reinicio_general') {
        $conexion->exec('DELETE FROM pagos_ventas');
        $conexion->exec('DELETE FROM detalle_ventas');
        $conexion->exec('DELETE FROM ventas');
        $conexion->exec('DELETE FROM cierre_caja');
    }

    if ($accion === 'reinicio_general') {
        $conexion->exec('DELETE FROM detalle_compras');
        $conexion->exec('DELETE FROM compras');
        $conexion->exec('DELETE FROM movimientos');
        $conexion->exec('DELETE FROM gastos');
    }

    if ($accion === 'resetear_stocks' || $accion === 'reinicio_general') {
        $conexion->exec('UPDATE productos SET stock_actual = 0');
    }

    $conexion->commit();
    $estado = [
        'vaciar_ventas_caja' => 'mantenimiento_ventas',
        'resetear_stocks' => 'mantenimiento_stock',
        'reinicio_general' => 'mantenimiento_general'
    ][$accion];
    if ($esAjax) {
        $mensajes = [
            'vaciar_ventas_caja' => 'Ventas y cierres de caja eliminados. El inventario se conservó.',
            'resetear_stocks' => 'Los stocks se restablecieron a cero.',
            'reinicio_general' => 'Reinicio general completado y stocks restablecidos a cero.'
        ];
        $responderJson(true, $mensajes[$accion]);
    }
    header('Location: ../configuracion.php?estado=' . $estado);
    exit;
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    if ($esAjax) {
        $responderJson(false, 'No se pudo completar el mantenimiento: ' . $e->getMessage(), 500);
    }
    http_response_code(500);
    echo 'No se pudo completar el mantenimiento. ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}