<?php
require_once __DIR__ . '/../includes/seguridad.php';
requerirRol([1]); // Solo Administrador
include __DIR__ . '/../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $es_combo = ($_POST['tipo'] ?? '') === 'combo';
    
    try {
        $conexion->beginTransaction();

        if ($es_combo) {
            $stmt = $conexion->prepare("DELETE FROM combos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $url_retorno = '../menu.php?msg=eliminado';
        } else {
            $stmtMovimientos = $conexion->prepare("DELETE FROM movimientos WHERE producto_id = :id");
            $stmtMovimientos->execute([':id' => $id]);

            $stmtProducto = $conexion->prepare("DELETE FROM productos WHERE id = :id");
            $stmtProducto->execute([':id' => $id]);
            $url_retorno = '../index.php?msg=eliminado';
        }

        $conexion->commit();
        header("Location: {$url_retorno}");
        exit();

    } catch (Exception $e) {
        // Asegurarnos de reactivar las llaves foráneas si ocurre algún fallo
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        
        echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; background: #f8d7da; color: #721c24; border-radius: 6px;'>";
        echo "<h3>❌ Error al eliminar el producto:</h3>";
        echo "<p style='font-size: 14px;'>" . htmlspecialchars($e->getMessage()) . "</p>";
        $url_retorno = $es_combo ? '../menu.php' : '../index.php';
        echo "<a href='{$url_retorno}' style='display: inline-block; background: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 15px;'>← Regresar</a>";
        echo "</div>";
        exit();
    }
} else {
    header("Location: ../index.php");
    exit();
}
?>