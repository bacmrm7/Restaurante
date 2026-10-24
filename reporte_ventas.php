<?php
require_once __DIR__ . '/includes/seguridad.php';
requerirRol([1, 2]);
require_once __DIR__ . '/config/conexion.php';

// Asegurar compatibilidad con la variable de conexión existente
if (!isset($pdo)) {
    if (isset($conexion)) { $pdo = $conexion; }
    elseif (isset($conn)) { $pdo = $conn; }
    elseif (isset($db)) { $pdo = $db; }
}

$error_db = "";
$ventas = [];

// Consultar ventas con su detalle, fecha y el método de pago registrado
try {
    $query = "
        SELECT 
            v.id AS venta_id,
            v.total AS total_venta,
            v.fecha_venta,
            COALESCE(c.nombre, p.nombre, 'Producto / Combo General') AS producto_nombre,
            COALESCE(dv.cantidad, 1) AS cantidad,
            COALESCE(dv.precio_unitario, v.total) AS precio_unitario,
            COALESCE(dv.subtotal, COALESCE(dv.cantidad, 1) * COALESCE(dv.precio_unitario, v.total)) AS subtotal_item,
            COALESCE(pv.metodo_pago, 'Efectivo USD') AS metodo_pago,
            COALESCE(pv.monto, v.total) AS monto_pagado,
            COALESCE(pv.referencia, 'S/R') AS referencia
        FROM ventas v
        LEFT JOIN detalle_ventas dv ON v.id = dv.venta_id
        LEFT JOIN productos p ON dv.producto_id = p.id
        LEFT JOIN combos c ON dv.combo_id = c.id
        LEFT JOIN pagos_ventas pv ON v.id = pv.venta_id
        ORDER BY v.id DESC
    ";

    $stmt = $pdo->query($query);
    $ventas = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $error_db = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas - ERP Hijos del Rey</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; padding: 20px; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; margin-top: 0; }
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; border: 1px solid #dee2e6; text-align: left; font-size: 14px; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        .footer-nav { margin-top: 25px; text-align: center; display: flex; justify-content: center; gap: 20px; }
        .btn { text-decoration: none; padding: 10px 18px; border-radius: 5px; font-weight: bold; font-size: 14px; display: inline-block; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-danger:hover { background-color: #c82333; }
    </style>
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/table-search.js" defer></script>
</head>
<body>

<?php include __DIR__ . '/includes/navegacion.php'; ?>
<div class="container">
    <h2>
        📊 Reporte General de Ventas
        <span style="font-size: 14px; font-weight: normal; color: #555;">ERP Hijos del Rey</span>
    </h2>

    <?php if ($error_db): ?>
        <div class="alert-danger">Error de Base de Datos: <?= htmlspecialchars($error_db) ?></div>
    <?php endif; ?>

    <div class="table-tools">
        <label class="table-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5"/></svg>
            <input type="search" data-table-search="#sales-table" placeholder="Buscar en reporte de ventas..." aria-label="Buscar en reporte de ventas">
        </label>
        <span class="table-search-status" aria-live="polite"></span>
    </div>
    <table id="sales-table">
        <thead>
            <tr>
                <th>ID Venta</th>
                <th>Fecha y Hora</th>
                <th>Producto / Combo</th>
                <th>Cant.</th>
                <th>Subtotal</th>
                <th>Método de Pago</th>
                <th>Monto Pagado</th>
                <th>Referencia</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($ventas) > 0): ?>
                <?php foreach ($ventas as $v): ?>
                    <tr>
                        <td><strong>#<?= $v['venta_id'] ?></strong></td>
                        <td><?= !empty($v['fecha_venta']) ? date('d/m/Y H:i', strtotime($v['fecha_venta'])) : 'N/A' ?></td>
                        <td><?= htmlspecialchars($v['producto_nombre']) ?></td>
                        <td><?= $v['cantidad'] ?></td>
                        <td style="font-weight: bold; color: #28a745;">$<?= number_format($v['subtotal_item'], 2) ?></td>
                        <td><strong><?= htmlspecialchars($v['metodo_pago'] ?? 'Efectivo USD') ?></strong></td>
                        <td>
                            <?php 
                                $metodo = $v['metodo_pago'] ?? '';
                                if (stripos($metodo, 'bs') !== false || stripos($metodo, 'movil') !== false || stripos($metodo, 'punto') !== false) {
                                    echo 'Bs. ' . number_format($v['monto_pagado'] ?? 0, 2);
                                } else {
                                    echo '$' . number_format($v['monto_pagado'] ?? 0, 2);
                                }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($v['referencia'] ?? 'S/R') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 3h14v18H5zM8 8h8M8 12h8M8 16h4"/><path d="M8 3v2m8-2v2"/></svg>
                        <strong>Aún no hay ventas registradas</strong>
                        <span>Las ventas procesadas en el punto de venta aparecerán en este reporte.</span>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer-nav">
        <a href="index.php" class="btn btn-primary" title="Volver al inventario general">← Volver al Inventario General</a>
        <a href="controllers/logout.php" class="btn btn-danger" title="Cerrar la sesión actual">🚪 Cerrar Sesión</a>
    </div>
</div>

</body>
</html>