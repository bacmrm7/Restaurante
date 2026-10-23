<?php
require_once __DIR__ . '/../includes/seguridad.php';
requerirRol([1]); // Solo Administrador
include __DIR__ . '/../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    try {
        $conexion->beginTransaction();

        // 1. Desactivar temporalmente las restricciones de clave foránea en MySQL
        $conexion->exec("SET FOREIGN_KEY_CHECKS = 0");

        // 2. Eliminar registros asociados en el Kardex (movimientos)
        $stmt1 = $conexion->prepare("DELETE FROM movimientos WHERE producto_id = :id");
        $stmt1->execute([':id' => $id]);

        // 3. Eliminar el producto de la tabla principal
        $stmt2 = $conexion->prepare("DELETE FROM productos WHERE id = :id");
        $stmt2->execute([':id' => $id]);

        // 4. Reactivar las restricciones de clave foránea
        $conexion->exec("SET FOREIGN_KEY_CHECKS = 1");

        $conexion->commit();
        header("Location: ../index.php?msg=eliminado");
        exit();

    } catch (Exception $e) {
        // Asegurarnos de reactivar las llaves foráneas si ocurre algún fallo
        $conexion->exec("SET FOREIGN_KEY_CHECKS = 1");
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        
        echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; background: #f8d7da; color: #721c24; border-radius: 6px;'>";
        echo "<h3>❌ Error al eliminar el producto:</h3>";
        echo "<p style='font-size: 14px;'>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<a href='../index.php' style='display: inline-block; background: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 15px;'>← Regresar al Inventario</a>";
        echo "</div>";
        exit();
    }
} else {
    header("Location: ../index.php");
    exit();
}
?>