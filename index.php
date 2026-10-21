<?php
require_once 'seguridad.php';
requerirRol([1]); // 1 = Solo Administrador
include 'conexion.php';

// Consultar únicamente los insumos y materias primas (es_combo = 0) uniendo la tabla categorías
$sql = "SELECT p.*, c.nombre AS categoria_nombre 
        FROM productos p 
        INNER JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.es_combo = 0 
        ORDER BY p.id DESC";
$stmt = $conexion->query($sql);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario General - ERP Hijos del Rey</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1100px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: auto; }
        h2 { color: #333; text-align: center; margin-bottom: 20px; }
        .nav-links { display: flex; gap: 10px; margin-bottom: 20px; justify-content: flex-end; align-items: center; flex-wrap: wrap; }
        .btn { background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; display: inline-block; }
        .btn:hover { background-color: #0056b3; }
        .btn-success { background-color: #28a745; }
        .btn-success:hover { background-color: #218838; }
        .btn-info { background-color: #17a2b8; }
        .btn-info:hover { background-color: #138496; }
        .btn-danger { background-color: #dc3545; }
        .btn-danger:hover { background-color: #bd2130; }
        .btn-primary { background-color: #007bff; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-sm { padding: 5px 10px; font-size: 12px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 14px; vertical-align: middle; }
        th { background-color: #343a40; color: white; }
        tr:hover { background-color: #f1f1f1; }
        .badge-danger { background-color: #dc3545; color: white; padding: 5px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .badge-success { background-color: #28a745; color: white; padding: 5px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .text-center { text-align: center; }
    </style>
</head>
<body>

<div class="container" style="max-width: 1200px; margin: auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
    
    <!-- TÍTULO PRINCIPAL -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e9ecef; padding-bottom: 15px;">
        <h2 style="margin: 0; color: #333;">Inventario General (Insumos y Almacén) - Hijos del Rey</h2>
        <span style="font-size: 14px; color: #666; font-weight: bold;">Rol: Administrador</span>
    </div>

    <!-- BOTONERA SUPERIOR -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <!-- Bloque Izquierdo: Operaciones Principales -->
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="registrar_producto.php" class="btn" style="background-color: #28a745; color: white; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: bold; font-size: 14px;">
                + Nuevo Insumo
            </a>
            <a href="recetas.php" class="btn" style="background-color: #17a2b8; color: white; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: bold; font-size: 14px;">
                📖 Recetas (BOM)
            </a>
            <a href="menu.php" class="btn" style="background-color: #6f42c1; color: white; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: bold; font-size: 14px;">
            🍗 Menú / Combos
            </a>
            <a href="ventas.php" class="btn" style="background-color: #28a745; color: white; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: bold; font-size: 14px;">
                🛒 Registrar Venta (POS)
            </a>
            <a href="gastos.php" class="btn" style="background-color: #dc3545; color: white; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: bold; font-size: 14px;">
            💸 Gastos Operativos
            </a>
            <a href="cierre_caja.php" class="btn" style="background-color: #17a2b8; color: white; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: bold; font-size: 14px;">
            📊 Cierre de Caja y Ganancias
            </a>
            <a href="reporte_ventas.php" class="btn" style="background-color: #17a2b8; color: white; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: bold; font-size: 14px;">
                📊 Ver Reporte de Ventas
            </a>
        </div>

        <!-- Bloque Derecho: Esquina Profesional -->
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="configuracion.php" style="background-color: #495057; color: white; text-decoration: none; padding: 10px 15px; border-radius: 6px; font-weight: bold; font-size: 14px; display: inline-flex; align-items: center;">
                ⚙️ Configuración
            </a>
            <a href="logout.php" class="btn" style="background-color: #dc3545; color: white; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: bold; font-size: 14px;">
                Cerrar Sesión
            </a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Insumo / Materia Prima</th>
                <th>Categoría</th>
                <th>P. Compra</th>
                <th>Stock Actual</th>
                <th>Stock Mín.</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($productos) > 0): ?>
                <?php foreach ($productos as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['codigo']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($p['nombre']) ?></strong><br>
                            <small style="color: #666;"><?= htmlspecialchars($p['descripcion']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($p['categoria_nombre']) ?></td>
                        <td>$<?= number_format($p['precio_compra'], 2) ?></td>
                        <td><strong><?= $p['stock_actual'] ?></strong></td>
                        <td><?= $p['stock_minimo'] ?></td>
                        <td>
                            <?php if ($p['stock_actual'] <= $p['stock_minimo']): ?>
                                <span class="badge-danger">Stock Bajo</span>
                            <?php else: ?>
                                <span class="badge-success">Óptimo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="editar_producto.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">
                                ✏️ Editar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px; color: #777;">No hay insumos registrados en el inventario general todavía.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>