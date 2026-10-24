<?php
require_once __DIR__ . '/includes/seguridad.php';
requerirRol([1]); // Administrador
include __DIR__ . '/config/conexion.php';

// Consultar combos directamente desde el catálogo de menú.
$sql = "SELECT c.*, cat.nombre AS categoria
    FROM combos c
    LEFT JOIN categorias cat ON c.categoria_id = cat.id
    ORDER BY c.id DESC";
$stmt = $conexion->query($sql);
$combos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú de Combos | Hijos del Rey C.A.</title>
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
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/table-search.js" defer></script>
</head>
<body>

<?php include __DIR__ . '/includes/navegacion.php'; ?>
<div class="container">
    <header class="module-heading">
        <div class="module-heading-copy">
            <h1 class="page-title">Menú de Combos</h1>
            <p class="page-subtitle">Gestiona los platos disponibles y sus precios de venta.</p>
        </div>
    </header>

    <div class="module-actions">
        <a href="registrar_producto.php?tipo=combo" class="btn btn-success" title="Registrar un nuevo combo o plato">+ Nuevo Combo / Plato</a>
    </div>

    <div class="table-tools">
        <label class="table-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5"/></svg>
            <input type="search" data-table-search="#menu-table" placeholder="Buscar en menú y combos..." aria-label="Buscar en menú y combos">
        </label>
        <span class="table-search-status" aria-live="polite"></span>
    </div>
    <div class="table-panel table-scroll">
    <table id="menu-table">
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
                        <td><?= htmlspecialchars($cb['categoria'] ?? 'Sin Categoría') ?></td>
                        <td><strong style="color: #28a745; font-size: 16px;">$<?= number_format($cb['precio_venta'], 2) ?></strong></td>
                        <td><small style="color: #666;"><?= htmlspecialchars($cb['descripcion']) ?></small></td>
                        <td class="table-actions-cell">
                            <div class="table-actions">
                                <a href="editar_producto.php?tipo=combo&id=<?= (int) $cb['id'] ?>" class="btn btn-sm btn-primary" title="Editar este combo o plato">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 5 4 4M4 20l4-.8L19 8a2.1 2.1 0 0 0-3-3L5 16z"/></svg>Editar
                                </a>
                                <form method="POST" action="controllers/eliminar_producto.php" data-confirm="¿Deseas eliminar este combo? Esta acción no se puede deshacer.">
                                    <input type="hidden" name="id" value="<?= (int) $cb['id'] ?>">
                                    <input type="hidden" name="tipo" value="combo">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar este combo o plato">
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
</div>

</body>
</html>