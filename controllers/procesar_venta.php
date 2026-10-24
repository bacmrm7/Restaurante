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
        $venta_id = $pdo->lastInsertId();

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

        $pdo->commit();
        
        // === PANTALLA DE ÉXITO PROFESIONAL PARA EL POS ===
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Venta Exitosa | Hijos del Rey C.A.</title>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; }
                h2 { color: #28a745; margin-bottom: 10px; }
                p { color: #555; font-size: 14px; margin-bottom: 20px; }
                .factura-box { background: #e2f0d9; border: 1px dashed #548235; padding: 10px; border-radius: 5px; font-weight: bold; color: #274e13; margin-bottom: 20px; }
                .btn { display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; }
                .btn:hover { background-color: #0056b3; }
            </style>
            <link rel="stylesheet" href="../assets/app.css">
        </head>
        <body data-toast-success="Venta procesada correctamente.">
            <div class="card">
                <h1 class="page-title">Venta procesada con éxito</h1>
                <p class="page-subtitle">El inventario y el Kardex se han actualizado automáticamente.</p>
                <div class="receipt-heading">
                    <strong><?= htmlspecialchars($config_sistema['nombre_comercial']) ?></strong>
                    <?php if ($config_sistema['ticket_encabezado'] !== ''): ?><span><?= nl2br(htmlspecialchars($config_sistema['ticket_encabezado'])) ?></span><?php endif; ?>
                    <?php if ($config_sistema['rif'] !== ''): ?><span>RIF: <?= htmlspecialchars($config_sistema['rif']) ?></span><?php endif; ?>
                    <?php if ($config_sistema['direccion'] !== ''): ?><span><?= htmlspecialchars($config_sistema['direccion']) ?></span><?php endif; ?>
                    <?php if ($config_sistema['telefono'] !== ''): ?><span><?= htmlspecialchars($config_sistema['telefono']) ?></span><?php endif; ?>
                </div>
                <div class="factura-box">
                    Nro. de Factura: <?= htmlspecialchars($codigo_factura) ?>
                </div>
                <?php if ($config_sistema['ticket_pie'] !== ''): ?><p class="receipt-footer"><?= nl2br(htmlspecialchars($config_sistema['ticket_pie'])) ?></p><?php endif; ?>
                <a href="../ventas.php" class="btn">🛒 Realizar Otra Venta</a>
            </div>
            <script src="../assets/app-ui.js" defer></script>
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