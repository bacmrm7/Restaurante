<?php
require_once 'seguridad.php';
requerirRol([1, 2]); // Admin y Cajero
require_once 'conexion.php';

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

        // 1. Registrar la Cabecera de la Venta
        $stmtVenta = $pdo->prepare("INSERT INTO ventas (codigo_factura, usuario_id, total) VALUES (?, ?, ?)");
        $stmtVenta->execute([$codigo_factura, $usuario_id, $total_venta]);
        $venta_id = $pdo->lastInsertId();

        // 2. Registrar Métodos de Pago
        $stmtPago = $pdo->prepare("INSERT INTO pagos_ventas (venta_id, metodo_pago, monto, referencia) VALUES (?, ?, ?, ?)");
        foreach ($metodos_pago as $pago) {
            $stmtPago->execute([$venta_id, $pago['metodo'], $pago['monto'], $pago['referencia'] ?? null]);
        }

        // Preparar consultas optimizadas para el ciclo
        $stmtDetalle = $pdo->prepare("INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
        // CORREGIDO: Se usa 'combo_id' en lugar de 'producto_padre_id' para sincronizar con recetas.php
        $stmtReceta = $pdo->prepare("SELECT insumo_id, cantidad FROM recetas WHERE combo_id = ?");
        $stmtStockActual = $pdo->prepare("SELECT stock_actual FROM productos WHERE id = ?");
        $stmtDescuento = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?");
        
        // Registro en Kardex (Movimientos)
        $stmtKardex = $pdo->prepare("INSERT INTO movimientos (producto_id, tipo, cantidad, stock_anterior, stock_nuevo, motivo, usuario_id) VALUES (?, 'Salida', ?, ?, ?, ?, ?)");

        // 3. Procesar Productos, Combos e Inventario en Tiempo Real
        foreach ($items as $item) {
            $producto_id = $item['producto_id'];
            $cant_vendida = $item['cantidad'];
            $precio_unitario = $item['precio_unitario'];
            
            // CORREGIDO: Operador de multiplicación aplicado correctamente
            $subtotal = $cant_vendida * $precio_unitario;

            $stmtDetalle->execute([$venta_id, $producto_id, $cant_vendida, $precio_unitario, $subtotal]);

            // Verificar si el producto tiene receta (es un Combo de Hijos del Rey)
            $stmtReceta->execute([$producto_id]);
            $ingredientes = $stmtReceta->fetchAll(PDO::FETCH_ASSOC);

            if (count($ingredientes) > 0) {
                // ES UN COMBO: Descontar materias primas / insumos asociados según el BOM
                foreach ($ingredientes as $ing) {
                    $insumo_id = $ing['insumo_id'];
                    $cant_a_descontar = $ing['cantidad'] * $cant_vendida;

                    // Consultar stock previo del insumo
                    $stmtStockActual->execute([$insumo_id]);
                    $stock_anterior = (float)$stmtStockActual->fetchColumn();
                    
                    // CORREGIDO: Operador de resta aplicado correctamente
                    $stock_nuevo = $stock_anterior - $cant_a_descontar;

                    // Restar del stock de inventario
                    $stmtDescuento->execute([$cant_a_descontar, $insumo_id]);

                    // Registrar salida en Kardex para auditoría
                    $motivo = "Descuento por venta de Combo (Factura: {$codigo_factura})";
                    $stmtKardex->execute([$insumo_id, $cant_a_descontar, $stock_anterior, $stock_nuevo, $motivo, $usuario_id]);
                }
            } else {
                // VENTA DIRECTA (Productos individuales, bebidas, etc.)
                $stmtStockActual->execute([$producto_id]);
                $stock_anterior = (float)$stmtStockActual->fetchColumn();
                
                // CORREGIDO: Operador de resta aplicado correctamente
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
            <title>Venta Exitosa - ERP Hijos del Rey</title>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; }
                h2 { color: #28a745; margin-bottom: 10px; }
                p { color: #555; font-size: 14px; margin-bottom: 20px; }
                .factura-box { background: #e2f0d9; border: 1px dashed #548235; padding: 10px; border-radius: 5px; font-weight: bold; color: #274e13; margin-bottom: 20px; }
                .btn { display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; }
                .btn:hover { background-color: #0056b3; }
            </style>
        </head>
        <body>
            <div class="card">
                <h2>¡Venta Procesada con Éxito!</h2>
                <p>El inventario y el Kardex se han actualizado automáticamente.</p>
                <div class="factura-box">
                    Nro. de Factura: <?= htmlspecialchars($codigo_factura) ?>
                </div>
                <a href="ventas.php" class="btn">🛒 Realizar Otra Venta</a>
            </div>
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
            <title>Error en Venta - ERP Hijos del Rey</title>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; }
                h2 { color: #dc3545; margin-bottom: 10px; }
                p { color: #555; font-size: 14px; margin-bottom: 20px; }
                .btn { display: inline-block; background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="card">
                <h2>¡Error al Procesar!</h2>
                <p><?= htmlspecialchars($e->getMessage()) ?></p>
                <a href="ventas.php" class="btn">Regresar al POS</a>
            </div>
        </body>
        </html>
        <?php
    }
}