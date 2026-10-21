<?php
$host = '127.0.0.1';
$username = 'root';
$password = '';

try {
    // 1. Conexión a la base de datos operativa (Inventario, Ventas, Compras)
    $conexion = new PDO("mysql:host=$host;dbname=erp_inventario;charset=utf8", $username, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Conexión a la base de datos de Seguridad (Usuarios, Roles, Auditoría)
    $conexion_seguridad = new PDO("mysql:host=$host;dbname=erp_seguridad;charset=utf8", $username, $password);
    $conexion_seguridad->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Función para auditoría de eventos (Ahora apunta a erp_seguridad)
function registrarLog($conexion_seguridad, $usuario_id, $accion, $detalles = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $conexion_seguridad->prepare("INSERT INTO logs_auditoria (usuario_id, accion, detalles, ip_origen) VALUES (?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $accion, $detalles, $ip]);
    } catch (Exception $e) {
        // Manejo silencioso o log de errores
    }
}
?>