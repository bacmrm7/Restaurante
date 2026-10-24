<?php
require_once __DIR__ . '/includes/seguridad.php';
requerirRol([1, 2, 3]); // Administrador, Cajero y Supervisor
include __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/configuracion_sistema.php';
$config_sistema = obtenerConfiguracionSistema($conexion);
$tasa_bcv = (float) $config_sistema['tasa_bcv'];

// Obtener combos y productos directos disponibles para venta.
try {
    $combos = $conexion->query("SELECT id, codigo, nombre, precio_venta FROM combos WHERE precio_venta > 0 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $productos = $conexion->query("SELECT id, codigo, nombre, precio_venta FROM productos WHERE precio_venta > 0 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $combos = [];
    $productos = [];
}

$rol_actual = $_SESSION['rol_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta | Hijos del Rey C.A.</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; padding: 20px; margin: 0; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #28a745; padding-bottom: 10px; margin-top: 0; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #495057; font-size: 14px; }
        select, input, button { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 14px; }
        button { background: #28a745; color: white; font-weight: bold; cursor: pointer; border: none; margin-top: 15px; font-size: 16px; padding: 12px; }
        button:hover { background: #218838; }
        .box { background: #f8f9fa; padding: 20px; border-radius: 6px; border: 1px solid #dee2e6; margin-bottom: 20px; }
        .box h4 { margin-top: 0; color: #333; font-size: 16px; }
        
        /* Estilos de la Barra Superior de Navegación Operativa */
        .btn-nav { text-decoration: none; padding: 7px 12px; border-radius: 4px; font-weight: bold; font-size: 13px; color: white; display: inline-block; }
        .btn-cierre { background-color: #007bff; }
        .btn-cierre:hover { background-color: #0056b3; }
        .btn-reporte { background-color: #6c757d; }
        .btn-reporte:hover { background-color: #5a6268; }
        .btn-salir { background-color: #dc3545; }
        .btn-salir:hover { background-color: #c82333; }

        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>

<?php include __DIR__ . '/includes/navegacion.php'; ?>
<div class="container">

    <header class="module-heading">
        <div class="module-heading-copy">
            <h1 class="page-title">Punto de Venta</h1>
            <p class="page-subtitle">Selecciona productos, calcula el importe y registra el pago de la venta.</p>
        </div>
    </header>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'venta_exitosa'): ?>
        <div class="alert-success">✅ Venta procesada correctamente. Se han descontado los insumos de la receta del inventario.</div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert-danger">⚠️ <?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <form action="controllers/procesar_venta.php" method="POST">
        <div class="box">
            <h4>1. Selección de Producto / Combo</h4>
            <div class="form-group">
                <label for="producto_select">Seleccionar producto o combo:</label>
                <select id="producto_select" required onchange="actualizarPrecio()">
                    <option value="" data-precio="0">-- Seleccione un producto --</option>
                    <?php foreach ($combos as $combo): ?>
                        <option value="combo:<?= (int) $combo['id'] ?>" data-tipo="combo" data-precio="<?= htmlspecialchars($combo['precio_venta']) ?>">
                            [Combo] [<?= htmlspecialchars($combo['codigo']) ?>] <?= htmlspecialchars($combo['nombre']) ?> ($<?= number_format($combo['precio_venta'], 2) ?>)
                        </option>
                    <?php endforeach; ?>
                    <?php foreach ($productos as $p): ?>
                        <option value="producto:<?= (int) $p['id'] ?>" data-tipo="producto" data-precio="<?= htmlspecialchars($p['precio_venta']) ?>">
                            [Producto] [<?= htmlspecialchars($p['codigo']) ?>] <?= htmlspecialchars($p['nombre']) ?> ($<?= number_format($p['precio_venta'], 2) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="items[0][tipo]" id="item_tipo_input">
                <input type="hidden" name="items[0][combo_id]" id="item_combo_id_input">
                <input type="hidden" name="items[0][producto_id]" id="item_producto_id_input">
            </div>
            
            <div class="form-group">
                <label for="cantidad_input">Cantidad a Vender:</label>
                <input type="number" name="items[0][cantidad]" id="cantidad_input" value="1" min="1" required oninput="actualizarPrecio()">
                
                <!-- Valores ocultos para envío -->
                <input type="hidden" name="items[0][precio_unitario]" id="precio_unitario_input" value="0.00">
                <input type="hidden" name="total_venta" id="total_venta_input" value="0.00">
            </div>
            
            <div style="font-weight: bold; color: #28a745; margin-top: 10px; font-size: 18px;">
                Total Calculado: $<span id="lbl_total">0.00</span>
            </div>
        </div>

        <div class="box">
            <h4>2. Registro del Método de Pago</h4>
            <div class="form-group">
                <label for="metodo_pago_select">Método de Pago:</label>
                <select name="pagos[0][metodo]" id="metodo_pago_select" onchange="actualizarMonedaPago()">
                    <option value="Efectivo USD">Efectivo USD ($)</option>
                    <option value="Efectivo Bs">Efectivo Bolívares (Bs)</option>
                    <option value="Pago Movil">Pago Móvil (Bs)</option>
                    <option value="Punto">Punto de Venta (Bs)</option>
                    <option value="Zelle">Zelle ($)</option>
                </select>
            </div>
            <div class="form-group">
                <label id="lbl_monto_pagado" for="monto_pagado_input">Monto Pagado ($):</label>
                <input type="text" name="pagos[0][monto]" id="monto_pagado_input" value="0.00" required>
                <?php if ($tasa_bcv > 0): ?><small class="settings-help">Tasa BCV activa: Bs. <?= number_format($tasa_bcv, 4, ',', '.') ?> por dólar.</small><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="referencia_input">Número de Referencia (Opcional):</label>
                <input type="text" name="pagos[0][referencia]" id="referencia_input" placeholder="Ej: 987654 (Dejar vacío en efectivo)">
            </div>
        </div>

        <button type="submit" title="Procesar la venta y descontar los ingredientes del inventario">
            ✅ Procesar Venta y Descontar Receta de Inventario
        </button>

    </form>
</div>

<script>
function actualizarPrecio() {
    const select = document.getElementById('producto_select');
    const selectedOption = select.options[select.selectedIndex];
    const precioUnitario = parseFloat(selectedOption.getAttribute('data-precio')) || 0;
    const [tipo, id] = select.value.split(':');

    document.getElementById('item_tipo_input').value = tipo || '';
    document.getElementById('item_combo_id_input').value = tipo === 'combo' ? id : '';
    document.getElementById('item_producto_id_input').value = tipo === 'producto' ? id : '';
    
    const cantidad = parseInt(document.getElementById('cantidad_input').value) || 1;
    const total = precioUnitario * cantidad;

    document.getElementById('precio_unitario_input').value = precioUnitario.toFixed(2);
    document.getElementById('total_venta_input').value = total.toFixed(2);
    document.getElementById('lbl_total').innerText = total.toFixed(2);
    
    actualizarMonedaPago();
}

function actualizarMonedaPago() {
    const metodo = document.getElementById('metodo_pago_select').value;
    const lblMonto = document.getElementById('lbl_monto_pagado');
    const totalDolares = parseFloat(document.getElementById('total_venta_input').value) || 0;
    const montoInput = document.getElementById('monto_pagado_input');

    if (metodo === 'Efectivo Bs' || metodo === 'Pago Movil' || metodo === 'Punto') {
        lblMonto.innerText = 'Monto Pagado (Bs):';
        const tasaBcv = <?= json_encode($tasa_bcv) ?>;
        montoInput.value = tasaBcv > 0 ? (totalDolares * tasaBcv).toFixed(2) : '';
        montoInput.placeholder = tasaBcv > 0 ? 'Monto convertido a bolívares' : 'Indica primero la tasa BCV en Configuración';
    } else {
        lblMonto.innerText = 'Monto Pagado ($):';
        montoInput.value = totalDolares.toFixed(2);
        montoInput.placeholder = '';
    }
}
</script>

</body>
</html>