<?php
require_once 'seguridad.php';
requerirRol([1]); // Solo Administrador
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: configuracion.php");
    exit();
}

try {
    $conexion->beginTransaction();

    // 1. Desactivar temporalmente las restricciones de clave foránea
    $conexion->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 2. Limpiar las tablas de movimientos y ventas de prueba
    $conexion->exec("DELETE FROM movimientos");
    $conexion->exec("DELETE FROM detalle_ventas");
    $conexion->exec("DELETE FROM ventas");

    // 3. Reiniciar los stocks de todos los productos a 0
    $conexion->exec("UPDATE productos SET stock_actual = 0");

    // 4. Reactivar las restricciones de clave foránea
    $conexion->exec("SET FOREIGN_KEY_CHECKS = 1");

    $conexion->commit();

    header("Location: index.php?msg=historial_limpio");
    exit();

} catch (Exception $e) {
    $conexion->exec("SET FOREIGN_KEY_CHECKS = 1");
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; background: #f8d7da; color: #721c24; border-radius: 6px;'>";
    echo "<h3>❌ Error al vaciar el historial:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='index.php' style='display: inline-block; background: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; margin-top: 10px;'>← Volver al Inventario</a>";
    echo "</div>";
    exit();
}
?>