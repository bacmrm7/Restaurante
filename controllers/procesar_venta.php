<?php
require_once __DIR__ . '/../includes/seguridad.php';
requerirRol([1, 2, 3]); // Administrador, Cajero y Supervisor
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/configuracion_sistema.php';
$config_sistema = obtenerConfiguracionSistema($conexion);

// Asegurar compatibilidad con la variable de conexión existente
if (!isset($pdo)) {
    if (isset($conexion)) { $pdo = $conexion; }
    elseif (isset($conn)) { $pdo = $conn; }
    elseif (isset($db)) { $pdo = $db; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Normalizamos la variable de sesión para evitar conflictos ('user_id' o 'usuario_id')
    $usuario_id = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? 1;
    $items = $_POST['items'] ?? []; 
    $metodos_pago = $_POST['pagos'] ?? []; 
    $total_venta = $_POST['total_venta'] ?? 0;
    
    $codigo_factura = 'VTA-' . date('Ymd') . '-' . rand(1000, 9999);

    try {
        $pdo->beginTransaction();

        // Preparar consultas optimizadas para el ciclo
        $stmtDetalleProducto = $pdo->prepare("INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmtDetalleCombo = $pdo->prepare("INSERT INTO detalle_ventas (venta_id, combo_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmtProducto = $pdo->prepare("SELECT id, stock_actual, precio_venta FROM productos WHERE id = ? FOR UPDATE");
        $stmtCombo = $pdo->prepare("SELECT id, precio_venta FROM combos WHERE id = ? FOR UPDATE");
        $stmtReceta = $pdo->prepare("SELECT p.id AS producto_id, r.cantidad FROM recetas r INNER JOIN productos p ON p.id = r.insumo_id WHERE r.combo_id = ?");
        $stmtStockActual = $pdo->prepare("SELECT stock_actual FROM productos WHERE id = ? FOR UPDATE");
        $stmtDescuento = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?");

        // Registro en Kardex (Movimientos)
        $stmtKardex = $pdo->prepare("INSERT INTO movimientos (producto_id, tipo, cantidad, stock_anterior, stock_nuevo, motivo, usuario_id) VALUES (?, 'Salida', ?, ?, ?, ?, ?)");

        // Validar entidades y calcular importes usando precios almacenados en la base de datos.
        $total_venta = 0.0;
        foreach ($items as &$item) {
            $tipo_item = $item['tipo'] ?? '';
            $id_item = filter_var(
                $tipo_item === 'combo' ? ($item['combo_id'] ?? null) : ($item['producto_id'] ?? null),
                FILTER_VALIDATE_INT
            );
            if (!in_array($tipo_item, ['combo', 'producto'], true) || $id_item === false || $id_item <= 0) {
                throw new RuntimeException('El producto o combo seleccionado no es válido.');
            }

            $cantidad = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT);
            if ($cantidad === false || $cantidad <= 0) {
                throw new RuntimeException('La cantidad vendida debe ser un entero mayor que cero.');
            }

            if ($tipo_item === 'combo') {
                $stmtCombo->execute([$id_item]);
                $entidad = $stmtCombo->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmtProducto->execute([$id_item]);
                $entidad = $stmtProducto->fetch(PDO::FETCH_ASSOC);
            }
            if (!$entidad) {
                throw new RuntimeException('El producto o combo seleccionado ya no existe.');
            }

            $item['precio_unitario'] = (float) $entidad['precio_venta'];
            $total_venta += $cantidad * $item['precio_unitario'];
        }
        unset($item);

        // 1. Registrar la Cabecera de la Venta con el total calculado desde la base de datos.
        $stmtVenta = $pdo->prepare("INSERT INTO ventas (codigo_factura, usuario_id, total) VALUES (?, ?, ?)");
        $stmtVenta->execute([$codigo_factura, $usuario_id, $total_venta]);
        $venta_id = (int) $conexion->lastInsertId();

        // 2. Registrar Métodos de Pago
        $stmtPago = $pdo->prepare("INSERT INTO pagos_ventas (venta_id, metodo_pago, monto, referencia) VALUES (?, ?, ?, ?)");
        foreach ($metodos_pago as $pago) {
            $stmtPago->execute([$venta_id, $pago['metodo'], $pago['monto'], $pago['referencia'] ?? null]);
        }

        // 3. Procesar Productos, Combos e Inventario en Tiempo Real
        foreach ($items as $item) {
            $tipo_item = $item['tipo'] ?? '';
            $id_item = filter_var(
                $tipo_item === 'combo' ? ($item['combo_id'] ?? null) : ($item['producto_id'] ?? null),
                FILTER_VALIDATE_INT
            );
            if (!in_array($tipo_item, ['combo', 'producto'], true) || $id_item === false || $id_item <= 0) {
                throw new RuntimeException('El producto o combo seleccionado no es válido.');
            }
            $cant_vendida = $item['cantidad'];
            $precio_unitario = $item['precio_unitario'];
            
            // CORREGIDO: Operador de multiplicación aplicado correctamente
            $subtotal = $cant_vendida * $precio_unitario;

            if ($tipo_item === 'combo') {
                $stmtCombo->execute([$id_item]);
                $combo_id = $stmtCombo->fetchColumn();
                if ($combo_id === false) {
                    throw new RuntimeException('El combo seleccionado ya no existe.');
                }
                $combo_id = (int) $combo_id;

                $stmtReceta->execute([$combo_id]);
                $ingredientes = $stmtReceta->fetchAll(PDO::FETCH_ASSOC);
                if (!$ingredientes) {
                    throw new RuntimeException('No se puede procesar este combo porque no tiene insumos configurados en su receta.');
                }

                $stmtDetalleCombo->execute([$venta_id, $combo_id, $cant_vendida, $precio_unitario, $subtotal]);

                // Descontar materias primas e insumos del BOM.
                foreach ($ingredientes as $ing) {
                    $insumo_id = (int) $ing['producto_id'];
                    $cant_a_descontar = $ing['cantidad'] * $cant_vendida;

                    // Consultar stock previo del insumo
                    $stmtStockActual->execute([$insumo_id]);
                    $stock_anterior = $stmtStockActual->fetchColumn();
                    if ($stock_anterior === false) {
                        throw new RuntimeException('Un insumo de la receta ya no existe en el inventario.');
                    }
                    $stock_anterior = (float) $stock_anterior;
                    
                    // CORREGIDO: Operador de resta aplicado correctamente
                    $stock_nuevo = $stock_anterior - $cant_a_descontar;

                    // Restar del stock de inventario
                    $stmtDescuento->execute([$cant_a_descontar, $insumo_id]);

                    // Registrar salida en Kardex para auditoría
                    $motivo = "Descuento por venta de Combo (Factura: {$codigo_factura})";
                    $stmtKardex->execute([$insumo_id, $cant_a_descontar, $stock_anterior, $stock_nuevo, $motivo, $usuario_id]);
                }
            } else {
                $stmtProducto->execute([$id_item]);
                $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);
                if (!$producto) {
                    throw new RuntimeException('El producto seleccionado ya no existe en el inventario.');
                }
                $producto_id = (int) $producto['id'];
                $stock_anterior = (float) $producto['stock_actual'];

                $stmtDetalleProducto->execute([$venta_id, $producto_id, $cant_vendida, $precio_unitario, $subtotal]);
                
                // Producto o insumo directo: descontar su propio stock.
                $stock_nuevo = $stock_anterior - $cant_vendida;

                // Restar del stock
                $stmtDescuento->execute([$cant_vendida, $producto_id]);

                // Registrar salida en Kardex
                $motivo = "Venta directa (Factura: {$codigo_factura})";
                $stmtKardex->execute([$producto_id, $cant_vendida, $stock_anterior, $stock_nuevo, $motivo, $usuario_id]);
            }
        }

        $stmtTicketVenta = $conexion->prepare("SELECT id, codigo_factura, fecha_venta, total FROM ventas WHERE id = ?");
        $stmtTicketVenta->execute([$venta_id]);
        $venta_ticket = $stmtTicketVenta->fetch(PDO::FETCH_ASSOC);
        if (!$venta_ticket) {
            throw new RuntimeException('No se pudo consultar la venta recién registrada.');
        }

        $stmtTicketDetalle = $conexion->prepare("SELECT COALESCE(c.nombre, p.nombre, 'Artículo') AS descripcion, dv.cantidad, dv.precio_unitario, dv.subtotal FROM detalle_ventas dv LEFT JOIN combos c ON c.id = dv.combo_id LEFT JOIN productos p ON p.id = dv.producto_id WHERE dv.venta_id = ? ORDER BY dv.id ASC");
        $stmtTicketDetalle->execute([$venta_id]);
        $detalles_ticket = $stmtTicketDetalle->fetchAll(PDO::FETCH_ASSOC);

        $tasa_interna = 1000.0;
        $total_usd = (float) $venta_ticket['total'];
        $total_bolivares = $total_usd * $tasa_interna;
        $cajero = trim((string) ($_SESSION['nombre_completo'] ?? ''));
        if ($cajero === '') {
            $cajero = (string) ($_SESSION['usuario'] ?? 'Cajero');
        }

        $pdo->commit();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Ticket <?= htmlspecialchars($venta_ticket['codigo_factura'], ENT_QUOTES, 'UTF-8') ?> | Hijos del Rey C.A.</title>
            <style>
                * { box-sizing: border-box; }
                body { min-height: 100vh; margin: 0; padding: 28px 16px; background: #eef1ed; color: #172019; font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace; }
                .ticket-shell { width: min(100%, 430px); margin: 0 auto; }
                .ticket-paper { width: 100%; padding: 24px; background: #fff; box-shadow: 0 12px 36px rgba(16, 41, 31, .12); }
                .ticket-header { text-align: center; }
                .ticket-header h1 { margin: 0; color: #173d2b; font-size: 18px; line-height: 1.3; }
                .ticket-header p { margin: 5px 0; font-size: 11px; line-height: 1.45; }
                .ticket-header .ticket-title { margin-top: 12px; font-weight: 700; }
                .ticket-rule { margin: 13px 0; border: 0; border-top: 1px dashed #647168; }
                .ticket-meta { display: grid; gap: 5px; font-size: 11px; line-height: 1.4; }
                .ticket-meta div { display: flex; justify-content: space-between; gap: 12px; }
                .ticket-meta strong { text-align: right; overflow-wrap: anywhere; }
                .ticket-items { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10px; }
                .ticket-items th, .ticket-items td { padding: 6px 2px; border-bottom: 1px dotted #b7c0b9; text-align: right; vertical-align: top; }
                .ticket-items th { color: #3d4a40; font-size: 9px; }
                .ticket-items th:first-child, .ticket-items td:first-child { width: 10%; text-align: left; }
                .ticket-items th:nth-child(2), .ticket-items td:nth-child(2) { width: 38%; text-align: left; overflow-wrap: anywhere; }
                .ticket-items th:nth-child(3), .ticket-items td:nth-child(3) { width: 24%; }
                .ticket-items th:nth-child(4), .ticket-items td:nth-child(4) { width: 28%; }
                .ticket-total { display: flex; justify-content: space-between; gap: 12px; margin-top: 7px; font-size: 12px; font-weight: 700; }
                .ticket-total-grand { color: #173d2b; font-size: 15px; }
                .ticket-footer { margin: 14px 0 0; text-align: center; font-size: 10px; line-height: 1.5; }
                .ticket-actions { display: flex; justify-content: center; gap: 10px; margin: 18px 0 0; }
                .ticket-actions button, .ticket-actions a { display: inline-flex; min-height: 40px; align-items: center; justify-content: center; padding: 9px 14px; border: 0; border-radius: 5px; background: #18392d; color: #fff; font-family: inherit; font-size: 12px; font-weight: 700; text-decoration: none; cursor: pointer; }
                .ticket-actions a { background: #fff; color: #18392d; outline: 1px solid #cbd5ce; }
                @media print {
                    @page { size: 80mm auto; margin: 4mm; }
                    html, body { width: 100%; min-height: 0; margin: 0 !important; padding: 0 !important; background: #fff !important; }
                    .sidebar, .side-nav, nav, .navbar, .top-nav, .ticket-actions { display: none !important; }
                    .ticket-shell, .ticket-paper { width: 100%; max-width: none; margin: 0; padding: 0; box-shadow: none; }
                    .ticket-paper { color: #000; }
                    .ticket-header h1, .ticket-total-grand { color: #000; }
                    .ticket-items { font-size: 9px; }
                    .ticket-items th { font-size: 8px; }
                    .ticket-rule { border-color: #555; }
                    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                }
            </style>
        </head>
        <body>
            <main class="ticket-shell">
                <article class="ticket-paper" aria-label="Ticket de venta">
                    <header class="ticket-header">
                        <img src='../assets/img/logo.png' alt='Hijos del Rey C.A.' style='max-width: 100px; height: auto; filter: brightness(0) contrast(200%); display: block; margin: 0 auto 8px auto;'>
                        <?php if (!empty($config_sistema['ticket_encabezado'])): ?><p><?= nl2br(htmlspecialchars($config_sistema['ticket_encabezado'], ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
                        <?php if (!empty($config_sistema['rif'])): ?><p>RIF: <?= htmlspecialchars($config_sistema['rif'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                        <?php if (!empty($config_sistema['direccion'])): ?><p><?= htmlspecialchars($config_sistema['direccion'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                        <?php if (!empty($config_sistema['telefono'])): ?><p>Tel.: <?= htmlspecialchars($config_sistema['telefono'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                        <p class="ticket-title">COMPROBANTE DE VENTA</p>
                    </header>
                    <hr class="ticket-rule">
                    <div class="ticket-meta">
                        <div><span>Factura</span><strong><?= htmlspecialchars($venta_ticket['codigo_factura'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                        <div><span>Fecha</span><strong><?= htmlspecialchars(date('d/m/Y H:i', strtotime($venta_ticket['fecha_venta'])), ENT_QUOTES, 'UTF-8') ?></strong></div>
                        <div><span>Cajero</span><strong><?= htmlspecialchars($cajero, ENT_QUOTES, 'UTF-8') ?></strong></div>
                    </div>
                    <hr class="ticket-rule">
                    <table class="ticket-items">
                        <thead><tr><th>Cant.</th><th>Descripción</th><th>P. unit.</th><th>Subtotal</th></tr></thead>
                        <tbody>
                            <?php foreach ($detalles_ticket as $detalle): ?>
                                <tr>
                                    <td><?= (int) $detalle['cantidad'] ?></td>
                                    <td><?= htmlspecialchars($detalle['descripcion'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>$<?= number_format((float) $detalle['precio_unitario'], 2, '.', ',') ?></td>
                                    <td>$<?= number_format((float) $detalle['subtotal'], 2, '.', ',') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <hr class="ticket-rule">
                    <div class="ticket-total ticket-total-grand"><span>TOTAL USD</span><span>$<?= number_format($total_usd, 2, '.', ',') ?></span></div>
                    <div class="ticket-total"><span>Tasa aplicada</span><span>1,000 Bs/USD</span></div>
                    <div class="ticket-total"><span>TOTAL Bs.</span><span>Bs. <?= number_format($total_bolivares, 2, '.', ',') ?></span></div>
                    <?php if (!empty($config_sistema['ticket_pie'])): ?><p class="ticket-footer"><?= nl2br(htmlspecialchars($config_sistema['ticket_pie'], ENT_QUOTES, 'UTF-8')) ?></p><?php else: ?><p class="ticket-footer">Gracias por su compra.</p><?php endif; ?>
                </article>
                <div class="ticket-actions">
                    <button type="button" onclick="window.print()">Imprimir ticket</button>
                    <a href="../ventas.php">Nueva venta</a>
                </div>
            </main>
        </body>
        </html>
        <?php

    } catch (Exception $e) {
        // Si ocurre un error, revertimos la transacción y mostramos un mensaje amigable
        $pdo->rollBack();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error en Venta | Hijos del Rey C.A.</title>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; }
                h2 { color: #dc3545; margin-bottom: 10px; }
                p { color: #555; font-size: 14px; margin-bottom: 20px; }
                .btn { display: inline-block; background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; }
            </style>
            <link rel="stylesheet" href="../assets/app.css">
        </head>
        <body>
            <div class="card">
                <h1 class="page-title">No se pudo procesar la venta</h1>
                <p class="page-subtitle">La operación no se completó. Revisa el detalle antes de volver a intentarlo.</p>
                <p><?= htmlspecialchars($e->getMessage()) ?></p>
                <a href="../ventas.php" class="btn">Regresar al POS</a>
            </div>
            <script src="../assets/app-ui.js" defer></script>
        </body>
        </html>
        <?php
    }
}