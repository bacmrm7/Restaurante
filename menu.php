<?php
require_once 'seguridad.php';
requerirRol([1]); // Administrador
include 'conexion.php';

// Consultar únicamente los productos que son combos/platos del menú (es_combo = 1)
$sql = "SELECT p.*, c.nombre AS categoria_nombre 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.es_combo = 1 
        ORDER BY p.id DESC";
$stmt = $conexion->query($sql);
$combos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú de Combos - ERP Hijos del Rey</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1100px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: auto; }
        h2 { color: #333; margin: 0; }
        .btn { background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; display: inline-block; }
        .btn:hover { background-color: #0056b3; }
        .btn-success { background-color: #28a745; }
        .btn-primary { background-color: #007bff; }
        .btn-danger { background-color: #dc3545; }
        .btn-secondary { background-color: #6c757d; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 14px; vertical-align: middle; }
        th { background-color: #343a40; color: white; }
        tr:hover { background-color: #f1f1f1; }
        .text-center { text-align: center; }
    </style>
    <link rel="stylesheet" href="app.css">
</head>
<body>

<?php include 'navegacion.php'; ?>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e9ecef; padding-bottom: 15px;">
        <h2>🍗 Menú de Combos (Venta al Público)</h2>
        <a href="index.php" class="btn btn-secondary">← Volver al Inventario</a>
    </div>

    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
        <a href="registrar_producto.php" class="btn btn-success">+ Nuevo Combo / Plato</a>
        <a href="recetas.php" class="btn" style="background-color: #17a2b8;">📖 Configurar Recetas (BOM)</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre del Combo / Plato</th>
                <th>Categoría</th>
                <th>Precio Venta</th>
                <th>Descripción</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($combos) > 0): ?>
                <?php foreach ($combos as $cb): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($cb['codigo']) ?></strong></td>
                        <td><?= htmlspecialchars($cb['nombre']) ?></td>
                        <td><?= htmlspecialchars($cb['categoria_nombre'] ?? 'Sin Categoría') ?></td>
                        <td><strong style="color: #28a745; font-size: 16px;">$<?= number_format($cb['precio_venta'], 2) ?></strong></td>
                        <td><small style="color: #666;"><?= htmlspecialchars($cb['descripcion']) ?></small></td>
                        <td class="table-actions-cell">
                            <div class="table-actions">
                                <a href="editar_producto.php?id=<?= $cb['id'] ?>" class="btn btn-sm btn-primary">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 5 4 4M4 20l4-.8L19 8a2.1 2.1 0 0 0-3-3L5 16z"/></svg>Editar
                                </a>
                                <form method="POST" action="eliminar_producto.php" data-confirm="¿Deseas eliminar este combo? Esta acción no se puede deshacer.">
                                    <input type="hidden" name="id" value="<?= (int) $cb['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m4 4v6m6-6v6"/></svg>Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5zM4 7.5l8 4.7 8-4.7M12 12.2V21"/><path d="M8 5.2l8 4.6"/></svg>
                        <strong>Aún no hay combos registrados</strong>
                        <span>Crea un combo o plato para empezar a vender desde el punto de venta.</span>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>