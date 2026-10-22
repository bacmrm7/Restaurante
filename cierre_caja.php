<?php
require_once 'seguridad.php';
requerirRol([1, 2]); // Permitir Administrador (1) y Cajero (2)
include 'conexion.php';

$mensaje = "";
$error = "";

// Determinar si el usuario actual es Administrador
$es_admin = isset($_SESSION['rol_id']) && $_SESSION['rol_id'] == 1;

// Modo de vista: Si es admin respeta el GET; si es cajero se le fuerza estrictamente a 'dia'
$modo = $es_admin ? (isset($_GET['modo']) ? $_GET['modo'] : 'dia') : 'dia';

$fecha_seleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Rango para la vista semanal (Solo accesible para Admin)
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-d', strtotime('-3 days'));
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');

// Ajustar recepción de mes consolidado (Solo accesible para Admin)
if (isset($_GET['mes_num']) && isset($_GET['anio_num'])) {
    $mes_seleccionado = $_GET['anio_num'] . '-' . $_GET['mes_num'];
} else {
    $mes_seleccionado = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');
}

// Procesar Guardado del Cierre Diario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_cierre'])) {
    $efectivo_usd_real = floatval($_POST['efectivo_usd_real']);
    $efectivo_bs_real = floatval($_POST['efectivo_bs_real'] ?? 0);
    $total_ventas = floatval($_POST['total_ventas']);
    $costo_bom = floatval($_POST['costo_bom']);
    $gastos_dia = floatval($_POST['gastos_dia']);
    $ganancia_neta = floatval($_POST['ganancia_neta']);
    $efectivo_usd_esperado = floatval($_POST['efectivo_usd_esperado']);
    
    $diferencia = $efectivo_usd_real - $efectivo_usd_esperado;
    $usuario_id = $_SESSION['usuario_id'] ?? 1;

    try {
        $stmtCheck = $conexion->prepare("SELECT id FROM cierre_caja WHERE fecha = ?");
        $stmtCheck->execute([$fecha_seleccionada]);
        
        if ($stmtCheck->fetch()) {
            $stmtUp = $conexion->prepare("UPDATE cierre_caja SET total_ventas = ?, costo_bom = ?, gastos_dia = ?, ganancia_neta = ?, efectivo_esperado = ?, efectivo_real = ?, diferencia = ?, usuario_id = ? WHERE fecha = ?");
            $stmtUp->execute([$total_ventas, $costo_bom, $gastos_dia, $ganancia_neta, $efectivo_usd_esperado, $efectivo_usd_real, $diferencia, $usuario_id, $fecha_seleccionada]);
        } else {
            $stmtIns = $conexion->prepare("INSERT INTO cierre_caja (fecha, total_ventas, costo_bom, gastos_dia, ganancia_neta, efectivo_esperado, efectivo_real, diferencia, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtIns->execute([$fecha_seleccionada, $total_ventas, $costo_bom, $gastos_dia, $ganancia_neta, $efectivo_usd_esperado, $efectivo_usd_real, $diferencia, $usuario_id]);
        }
        $mensaje = "¡Cierre de caja para la fecha {$fecha_seleccionada} guardado con éxito!";
    } catch (PDOException $e) {
        $error = "Error al guardar el cierre de caja: " . $e->getMessage();
    }
}

// ==========================================
// CÁLCULO BASE DE GASTOS OPERATIVOS (POR DÍA)
// ==========================================
$stmtGastos = $conexion->query("SELECT monto, frecuencia FROM gastos");
$gastos = $stmtGastos ? $stmtGastos->fetchAll(PDO::FETCH_ASSOC) : [];

$gasto_diario_base = 0;
foreach ($gastos as $g) {
    $monto = floatval($g['monto']);
    switch ($g['frecuencia']) {
        case 'semanal': $gasto_diario_base += ($monto / 4); break; // 4 días de trabajo por semana
        case 'mensual': $gasto_diario_base += ($monto / 16); break; // 16 días de trabajo al mes
        case 'diario':  $gasto_diario_base += $monto; break;
        case 'unico':   $gasto_diario_base += ($monto / 16); break;
    }
}

// ==========================================
// CÁLCULOS SEGÚN EL MODO DE VISTA
// ==========================================

if ($modo === 'mes' && $es_admin) {
    // --- 📈 MODO MENSUAL ---
    $stmtVentas = $conexion->prepare("SELECT SUM(total) as total_mes FROM ventas WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ?");
    $stmtVentas->execute([$mes_seleccionado]);
    $resVentas = $stmtVentas->fetch(PDO::FETCH_ASSOC);
    $total_ventas_periodo = floatval($resVentas['total_mes'] ?? 0);

    $stmtPagos = $conexion->prepare("
        SELECT pv.metodo_pago, SUM(pv.monto) as total_metodo 
        FROM pagos_ventas pv 
        JOIN ventas v ON pv.venta_id = v.id 
        WHERE DATE_FORMAT(v.fecha_venta, '%Y-%m') = ? 
        GROUP BY pv.metodo_pago
    ");
    $stmtPagos->execute([$mes_seleccionado]);
    $pagos_brutos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);

    $usd_periodo = 0; $bs_periodo = 0; $zelle_periodo = 0; $pago_movil_periodo = 0;
    foreach ($pagos_brutos as $p) {
        $metodo = strtolower($p['metodo_pago']);
        $monto = floatval($p['total_metodo']);
        if (strpos($metodo, 'efectivo usd') !== false || $metodo === 'efectivo' || strpos($metodo, 'dolar') !== false) {
            $usd_periodo += $monto;
        } elseif (strpos($metodo, 'zelle') !== false) {
            $zelle_periodo += $monto;
        } elseif (strpos($metodo, 'pago movil') !== false || strpos($metodo, 'punto') !== false) {
            $pago_movil_periodo += $monto;
        } elseif (strpos($metodo, 'bs') !== false) {
            $bs_periodo += $monto;
        } else {
            $usd_periodo += $monto;
        }
    }

    $stmtDetalles = $conexion->prepare("
        SELECT dv.producto_id, dv.cantidad as cant_vendida 
        FROM detalle_ventas dv 
        JOIN ventas v ON dv.venta_id = v.id 
        WHERE DATE_FORMAT(v.fecha_venta, '%Y-%m') = ?
    ");
    $stmtDetalles->execute([$mes_seleccionado]);
    $detalles_ventas = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

    $costo_total_bom_periodo = 0;
    foreach ($detalles_ventas as $dv) {
        $stmtReceta = $conexion->prepare("SELECT insumo_id, cantidad FROM recetas WHERE combo_id = ?");
        $stmtReceta->execute([$dv['producto_id']]);
        $ingredientes = $stmtReceta->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ingredientes as $ing) {
            $stmtIns = $conexion->prepare("SELECT precio_compra FROM productos WHERE id = ?");
            $stmtIns->execute([$ing['insumo_id']]);
            $insumo = $stmtIns->fetch(PDO::FETCH_ASSOC);
            $costo_total_bom_periodo += ($ing['cantidad'] * $dv['cant_vendida'] * floatval($insumo['precio_compra'] ?? 0));
        }
    }

    $total_gasto_operativo_periodo = $gasto_diario_base * 16;
    $ganancia_neta_periodo = $total_ventas_periodo - $costo_total_bom_periodo - $total_gasto_operativo_periodo;

} elseif ($modo === 'semana' && $es_admin) {
    // --- 🗓️ MODO SEMANAL ---
    $stmtVentas = $conexion->prepare("SELECT SUM(total) as total_sem FROM ventas WHERE DATE(fecha_venta) BETWEEN ? AND ?");
    $stmtVentas->execute([$fecha_desde, $fecha_hasta]);
    $resVentas = $stmtVentas->fetch(PDO::FETCH_ASSOC);
    $total_ventas_periodo = floatval($resVentas['total_sem'] ?? 0);

    $stmtPagos = $conexion->prepare("
        SELECT pv.metodo_pago, SUM(pv.monto) as total_metodo 
        FROM pagos_ventas pv 
        JOIN ventas v ON pv.venta_id = v.id 
        WHERE DATE(v.fecha_venta) BETWEEN ? AND ? 
        GROUP BY pv.metodo_pago
    ");
    $stmtPagos->execute([$fecha_desde, $fecha_hasta]);
    $pagos_brutos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);

    $usd_periodo = 0; $bs_periodo = 0; $zelle_periodo = 0; $pago_movil_periodo = 0;
    foreach ($pagos_brutos as $p) {
        $metodo = strtolower($p['metodo_pago']);
        $monto = floatval($p['total_metodo']);
        if (strpos($metodo, 'efectivo usd') !== false || $metodo === 'efectivo' || strpos($metodo, 'dolar') !== false) {
            $usd_periodo += $monto;
        } elseif (strpos($metodo, 'zelle') !== false) {
            $zelle_periodo += $monto;
        } elseif (strpos($metodo, 'pago movil') !== false || strpos($metodo, 'punto') !== false) {
            $pago_movil_periodo += $monto;
        } elseif (strpos($metodo, 'bs') !== false) {
            $bs_periodo += $monto;
        } else {
            $usd_periodo += $monto;
        }
    }

    $stmtDetalles = $conexion->prepare("
        SELECT dv.producto_id, dv.cantidad as cant_vendida 
        FROM detalle_ventas dv 
        JOIN ventas v ON dv.venta_id = v.id 
        WHERE DATE(v.fecha_venta) BETWEEN ? AND ?
    ");
    $stmtDetalles->execute([$fecha_desde, $fecha_hasta]);
    $detalles_ventas = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

    $costo_total_bom_periodo = 0;
    foreach ($detalles_ventas as $dv) {
        $stmtReceta = $conexion->prepare("SELECT insumo_id, cantidad FROM recetas WHERE combo_id = ?");
        $stmtReceta->execute([$dv['producto_id']]);
        $ingredientes = $stmtReceta->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ingredientes as $ing) {
            $stmtIns = $conexion->prepare("SELECT precio_compra FROM productos WHERE id = ?");
            $stmtIns->execute([$ing['insumo_id']]);
            $insumo = $stmtIns->fetch(PDO::FETCH_ASSOC);
            $costo_total_bom_periodo += ($ing['cantidad'] * $dv['cant_vendida'] * floatval($insumo['precio_compra'] ?? 0));
        }
    }

    $dias_diferencia = (strtotime($fecha_hasta) - strtotime($fecha_desde)) / (60 * 60 * 24) + 1;
    $dias_diferencia = max(1, $dias_diferencia);

    $total_gasto_operativo_periodo = $gasto_diario_base * $dias_diferencia;
    $ganancia_neta_periodo = $total_ventas_periodo - $costo_total_bom_periodo - $total_gasto_operativo_periodo;

} else {
    // --- 📅 MODO DIARIO ---
    $stmtVentas = $conexion->prepare("SELECT SUM(total) as total_dia FROM ventas WHERE DATE(fecha_venta) = ?");
    $stmtVentas->execute([$fecha_seleccionada]);
    $resVentas = $stmtVentas->fetch(PDO::FETCH_ASSOC);
    $total_ventas_dia = floatval($resVentas['total_dia'] ?? 0);

    $stmtPagos = $conexion->prepare("
        SELECT pv.metodo_pago, SUM(pv.monto) as total_metodo 
        FROM pagos_ventas pv 
        JOIN ventas v ON pv.venta_id = v.id 
        WHERE DATE(v.fecha_venta) = ? 
        GROUP BY pv.metodo_pago
    ");
    $stmtPagos->execute([$fecha_seleccionada]);
    $pagos_brutos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);

    $efectivo_usd_esperado = 0; $efectivo_bs_esperado = 0; $zelle_esperado = 0; $pago_movil_punto_esperado = 0;
    foreach ($pagos_brutos as $p) {
        $metodo = strtolower($p['metodo_pago']);
        $monto = floatval($p['total_metodo']);
        if (strpos($metodo, 'efectivo usd') !== false || $metodo === 'efectivo' || strpos($metodo, 'dolar') !== false) {
            $efectivo_usd_esperado += $monto;
        } elseif (strpos($metodo, 'zelle') !== false) {
            $zelle_esperado += $monto;
        } elseif (strpos($metodo, 'pago movil') !== false || strpos($metodo, 'punto') !== false) {
            $pago_movil_punto_esperado += $monto;
        } elseif (strpos($metodo, 'bs') !== false || strpos($metodo, 'efectivo bs') !== false) {
            $efectivo_bs_esperado += $monto;
        } else {
            $efectivo_usd_esperado += $monto;
        }
    }
    if ($efectivo_usd_esperado == 0 && $efectivo_bs_esperado == 0 && $zelle_esperado == 0 && $pago_movil_punto_esperado == 0) {
        $efectivo_usd_esperado = $total_ventas_dia;
    }

    $stmtDetalles = $conexion->prepare("
        SELECT dv.producto_id, dv.cantidad as cant_vendida 
        FROM detalle_ventas dv 
        JOIN ventas v ON dv.venta_id = v.id 
        WHERE DATE(v.fecha_venta) = ?
    ");
    $stmtDetalles->execute([$fecha_seleccionada]);
    $detalles_ventas = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

    $costo_total_bom_dia = 0;
    foreach ($detalles_ventas as $dv) {
        $stmtReceta = $conexion->prepare("SELECT insumo_id, cantidad FROM recetas WHERE combo_id = ?");
        $stmtReceta->execute([$dv['producto_id']]);
        $ingredientes = $stmtReceta->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ingredientes as $ing) {
            $stmtIns = $conexion->prepare("SELECT precio_compra FROM productos WHERE id = ?");
            $stmtIns->execute([$ing['insumo_id']]);
            $insumo = $stmtIns->fetch(PDO::FETCH_ASSOC);
            $costo_total_bom_dia += ($ing['cantidad'] * $dv['cant_vendida'] * floatval($insumo['precio_compra'] ?? 0));
        }
    }

    $gasto_operativo_diario = $gasto_diario_base;
    $ganancia_neta_dia = $total_ventas_dia - $costo_total_bom_dia - $gasto_operativo_diario;
}

// ==========================================
// CONSULTAR HISTÓRICO DE CIERRES GUARDADOS
// ==========================================
$stmtHistorico = $conexion->query("SELECT * FROM cierre_caja ORDER BY fecha DESC LIMIT 30");
$cierres_guardados = $stmtHistorico ? $stmtHistorico->fetchAll(PDO::FETCH_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre y Control Financiero - Hijos del Rey</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1000px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e9ecef; padding-bottom: 15px; margin-bottom: 20px; }
        h2 { margin: 0; color: #333; }
        .btn { background-color: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; border: none; cursor: pointer; display: inline-block; }
        .btn:hover { background-color: #5a6268; }
        .btn-success { background-color: #28a745; width: 100%; font-size: 16px; padding: 12px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; color: white; }
        .btn-success:hover { background-color: #218838; }
        .mode-selector { display: flex; gap: 10px; margin-bottom: 20px; }
        .mode-btn { padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; background: #e9ecef; color: #495057; border: 1px solid #ced4da; }
        .mode-btn.active { background: #007bff; color: white; border-color: #0056b3; }
        .filter-box { background: #e9ecef; padding: 15px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .filter-box input, .filter-box select { padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; background: white; }
        .desglose-box { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #ced4da; }
        .desglose-item { text-align: center; }
        .desglose-item strong { display: block; color: #6c757d; font-size: 13px; margin-bottom: 4px; }
        .desglose-item span { font-size: 18px; font-weight: bold; color: #212529; }
        .grid-metrics { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; }
        .metric-card { background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; border-radius: 6px; border-left: 5px solid #007bff; }
        .metric-card h4 { margin: 0 0 8px 0; color: #495057; font-size: 15px; }
        .metric-card span { font-size: 24px; font-weight: bold; }
        .net-card { background: #e8f5e9; border-color: #c8e6c9; border-left-color: #2e7d32; }
        .net-card h4 { color: #2e7d32; }
        .net-card span { color: <?= ($modo === 'dia' ? $ganancia_neta_dia : $ganancia_neta_periodo) >= 0 ? '#1b5e20' : '#dc3545' ?>; }
        .cash-box { background: #eef2f7; padding: 20px; border-radius: 6px; border: 1px solid #cbd5e1; margin-top: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 15px; }
        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; font-weight: bold; }
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; font-weight: bold; }
        
        /* Tabla de Histórico */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px 12px; border: 1px solid #dee2e6; text-align: left; font-size: 13px; }
        th { background-color: #343a40; color: white; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        .tag-dif-ok { color: #28a745; font-weight: bold; }
        .tag-dif-bad { color: #dc3545; font-weight: bold; }
    </style>
    <link rel="stylesheet" href="app.css">
</head>
<body>

<?php include 'navegacion.php'; ?>
<div class="container">
    <div class="header">
        <h2>📊 Cierre de Caja y Control Financiero</h2>
        <nav class="quick-nav" aria-label="Accesos rápidos">
            <a href="reporte_ventas.php" class="btn btn-secondary">Tickets</a>
            <a href="ventas.php" class="btn btn-secondary">Punto de Venta</a>
            <a href="<?= $es_admin ? 'index.php' : 'ventas.php' ?>" class="btn btn-primary">Volver</a>
        </nav>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Selector de Modo de Vista (Solo visible para el Administrador) -->
    <?php if ($es_admin): ?>
        <div class="mode-selector">
            <a href="cierre_caja.php?modo=dia" class="mode-btn <?= $modo === 'dia' ? 'active' : '' ?>">📅 Arqueo Diario</a>
            <a href="cierre_caja.php?modo=semana" class="mode-btn <?= $modo === 'semana' ? 'active' : '' ?>">🗓️ Fin de Semana (4 Días)</a>
            <a href="cierre_caja.php?modo=mes" class="mode-btn <?= $modo === 'mes' ? 'active' : '' ?>">📈 Consolidado Mensual</a>
        </div>
    <?php else: ?>
        <div style="background: #e9ecef; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; color: #495057;">
            🔒 Módulo Operativo: Arqueo y Cierre Diario de Caja (Cajero)
        </div>
    <?php endif; ?>

    <?php if ($modo === 'mes' && $es_admin): ?>
        <!-- FILTRO MENSUAL UNIFORME -->
        <form method="GET" action="cierre_caja.php" class="filter-box">
            <input type="hidden" name="modo" value="mes">
            <label style="font-weight: bold; color: #333;">📅 Seleccionar Mes a Consolidar:</label>
            
            <?php 
                $partes_mes = explode('-', $mes_seleccionado);
                $anio_actual = $partes_mes[0] ?? date('Y');
                $num_mes_actual = $partes_mes[1] ?? date('m');
                
                $meses_nombre = [
                    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
                    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
                    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
                ];
            ?>

            <select name="mes_num" required>
                <?php foreach ($meses_nombre as $cod => $nombre): ?>
                    <option value="<?= $cod ?>" <?= $cod == $num_mes_actual ? 'selected' : '' ?>><?= $nombre ?></option>
                <?php endforeach; ?>
            </select>

            <select name="anio_num" required>
                <?php for ($a = 2024; $a <= 2030; $a++): ?>
                    <option value="<?= $a ?>" <?= $a == $anio_actual ? 'selected' : '' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>

            <button type="submit" class="btn" style="background-color: #007bff;">Consultar Mes</button>
        </form>

        <div class="desglose-box">
            <div class="desglose-item">
                <strong>💵 Efectivo USD (Mes)</strong>
                <span>$<?= number_format($usd_periodo, 2) ?></span>
            </div>
            <div class="desglose-item">
                <strong>⚡ Zelle (Mes)</strong>
                <span>$<?= number_format($zelle_periodo, 2) ?></span>
            </div>
            <div class="desglose-item">
                <strong>🇻🇪 Efectivo Bs (Mes)</strong>
                <span>Bs. <?= number_format($bs_periodo, 2) ?></span>
            </div>
            <div class="desglose-item">
                <strong>💳 P.Móvil / Punto (Mes)</strong>
                <span>Bs. <?= number_format($pago_movil_periodo, 2) ?></span>
            </div>
        </div>

        <div class="grid-metrics">
            <div class="metric-card">
                <h4>(+) Ventas Totales del Mes</h4>
                <span style="color: #007bff;">$<?= number_format($total_ventas_periodo, 2) ?></span>
            </div>
            <div class="metric-card" style="border-left-color: #dc3545;">
                <h4>(-) Costo de Ventas (BOM Acumulado)</h4>
                <span style="color: #dc3545;">$<?= number_format($costo_total_bom_periodo, 2) ?></span>
            </div>
            <div class="metric-card" style="border-left-color: #ffc107;">
                <h4>(-) Gastos Operativos (16 días laborables)</h4>
                <span style="color: #d39e00;">$<?= number_format($total_gasto_operativo_periodo, 2) ?></span>
            </div>
            <div class="metric-card net-card">
                <h4>(=) Ganancia Neta Real del Mes</h4>
                <span>$<?= number_format($ganancia_neta_periodo, 2) ?></span>
            </div>
        </div>

    <?php elseif ($modo === 'semana' && $es_admin): ?>
        <!-- FILTRO SEMANAL -->
        <form method="GET" action="cierre_caja.php" class="filter-box">
            <input type="hidden" name="modo" value="semana">
            <label style="font-weight: bold; color: #333;">📅 Desde:</label>
            <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>" required>
            <label style="font-weight: bold; color: #333;">📅 Hasta:</label>
            <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>" required>
            <button type="submit" class="btn" style="background-color: #007bff;">Consultar Ciclo</button>
        </form>

        <div class="desglose-box">
            <div class="desglose-item">
                <strong>💵 Efectivo USD</strong>
                <span>$<?= number_format($usd_periodo, 2) ?></span>
            </div>
            <div class="desglose-item">
                <strong>⚡ Zelle</strong>
                <span>$<?= number_format($zelle_periodo, 2) ?></span>
            </div>
            <div class="desglose-item">
                <strong>🇻🇪 Efectivo Bs</strong>
                <span>Bs. <?= number_format($bs_periodo, 2) ?></span>
            </div>
            <div class="desglose-item">
                <strong>💳 P.Móvil / Punto</strong>
                <span>Bs. <?= number_format($pago_movil_periodo, 2) ?></span>
            </div>
        </div>

        <div class="grid-metrics">
            <div class="metric-card">
                <h4>(+) Ventas Totales del Ciclo</h4>
                <span style="color: #007bff;">$<?= number_format($total_ventas_periodo, 2) ?></span>
            </div>
            <div class="metric-card" style="border-left-color: #dc3545;">
                <h4>(-) Costo de Ventas (BOM Ciclo)</h4>
                <span style="color: #dc3545;">$<?= number_format($costo_total_bom_periodo, 2) ?></span>
            </div>
            <div class="metric-card" style="border-left-color: #ffc107;">
                <h4>(-) Gastos Operativos (<?= $dias_diferencia ?> días)</h4>
                <span style="color: #d39e00;">$<?= number_format($total_gasto_operativo_periodo, 2) ?></span>
            </div>
            <div class="metric-card net-card">
                <h4>(=) Ganancia Neta del Ciclo</h4>
                <span>$<?= number_format($ganancia_neta_periodo, 2) ?></span>
            </div>
        </div>

    <?php else: ?>
        <!-- FILTRO DIARIO (ACCESIBLE PARA ADMIN Y CAJERO) -->
        <form method="GET" action="cierre_caja.php" class="filter-box">
            <input type="hidden" name="modo" value="dia">
            <label for="fecha" style="font-weight: bold; color: #333;">📅 Seleccionar Fecha a Auditar:</label>
            <input type="date" name="fecha" id="fecha" value="<?= htmlspecialchars($fecha_seleccionada) ?>" required>
            <button type="submit" class="btn" style="background-color: #007bff;">Consultar Día</button>
        </form>

        <div class="desglose-box">
            <div class="desglose-item">
                <strong>💵 Efectivo USD</strong>
                <span>$<?= number_format($efectivo_usd_esperado, 2) ?></span>
            </div>
            <div class="desglose-item">
                <strong>⚡ Zelle (USD)</strong>
                <span>$<?= number_format($zelle_esperado, 2) ?></span>
            </div>
            <div class="desglose-item">
                <strong>🇻🇪 Efectivo Bs</strong>
                <span>Bs. <?= number_format($efectivo_bs_esperado, 2) ?></span>
            </div>
            <div class="desglose-item">
                <strong>💳 P.Móvil / Punto</strong>
                <span>Bs. <?= number_format($pago_movil_punto_esperado, 2) ?></span>
            </div>
        </div>

        <div class="grid-metrics">
            <div class="metric-card">
                <h4>(+) Ventas Totales del Día</h4>
                <span style="color: #007bff;">$<?= number_format($total_ventas_dia, 2) ?></span>
            </div>
            <div class="metric-card" style="border-left-color: #dc3545;">
                <h4>(-) Costo de Ventas (BOM / Insumos)</h4>
                <span style="color: #dc3545;">$<?= number_format($costo_total_bom_dia, 2) ?></span>
            </div>
            <div class="metric-card" style="border-left-color: #ffc107;">
                <h4>(-) Gastos Operativos (Cuota Día)</h4>
                <span style="color: #d39e00;">$<?= number_format($gasto_operativo_diario, 2) ?></span>
            </div>
            <div class="metric-card net-card">
                <h4>(=) Ganancia Neta Real</h4>
                <span>$<?= number_format($ganancia_neta_dia, 2) ?></span>
            </div>
        </div>

        <!-- FORMULARIO DE ARQUEO FÍSICO -->
        <div class="cash-box">
            <h3 style="margin-top: 0; color: #333;">🔒 Arqueo Físico y Cierre Diario</h3>
            <form action="cierre_caja.php?fecha=<?= $fecha_seleccionada ?>&modo=dia" method="POST">
                <input type="hidden" name="total_ventas" value="<?= $total_ventas_dia ?>">
                <input type="hidden" name="costo_bom" value="<?= $costo_total_bom_dia ?>">
                <input type="hidden" name="gastos_dia" value="<?= $gasto_operativo_diario ?>">
                <input type="hidden" name="ganancia_neta" value="<?= $ganancia_neta_dia ?>">
                <input type="hidden" name="efectivo_usd_esperado" value="<?= $efectivo_usd_esperado ?>">

                <div class="form-group">
                    <label>Efectivo USD Físico Contado en Gaveta ($):</label>
                    <input type="number" step="0.01" name="efectivo_usd_real" value="<?= number_format($efectivo_usd_esperado, 2, '.', '') ?>" required>
                    <small style="color: #64748b;">Monto en billetes físicos de dólares presentes en la caja registradora.</small>
                </div>

                <div class="form-group">
                    <label>Efectivo Bolívares (Bs) Físico Contado en Gaveta (Opcional):</label>
                    <input type="number" step="0.01" name="efectivo_bs_real" value="<?= number_format($efectivo_bs_esperado, 2, '.', '') ?>">
                </div>

                <button type="submit" name="guardar_cierre" class="btn btn-success">💾 Guardar y Consolidar Cierre de Caja</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- ======================================================= -->
    <!-- HISTÓRICO DE CIERRES DIARIOS GUARDADOS EN BD           -->
    <!-- ======================================================= -->
    <div style="margin-top: 40px; border-top: 2px solid #dee2e6; padding-top: 20px;">
        <h3 style="color: #333; margin-bottom: 15px;">📜 Histórico de Cierres Auditados Guardados</h3>
        
        <?php if (count($cierres_guardados) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Ventas Totales</th>
                        <th>Costo BOM</th>
                        <th>Gastos Día</th>
                        <th>Ganancia Neta</th>
                        <th>USD Esperado</th>
                        <th>USD Físico</th>
                        <th>Diferencia</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cierres_guardados as $c): ?>
                        <tr>
                            <td><strong><?= date('d/m/Y', strtotime($c['fecha'])) ?></strong></td>
                            <td style="color: #007bff; font-weight: bold;">$<?= number_format($c['total_ventas'], 2) ?></td>
                            <td style="color: #dc3545;">$<?= number_format($c['costo_bom'], 2) ?></td>
                            <td style="color: #d39e00;">$<?= number_format($c['gastos_dia'], 2) ?></td>
                            <td style="font-weight: bold; color: <?= $c['ganancia_neta'] >= 0 ? '#28a745' : '#dc3545' ?>;">
                                $<?= number_format($c['ganancia_neta'], 2) ?>
                            </td>
                            <td>$<?= number_format($c['efectivo_esperado'], 2) ?></td>
                            <td>$<?= number_format($c['efectivo_real'], 2) ?></td>
                            <td>
                                <?php if ($c['diferencia'] == 0): ?>
                                    <span class="tag-dif-ok">✓ Cuadrado</span>
                                <?php elseif ($c['diferencia'] > 0): ?>
                                    <span class="tag-dif-ok">+$<?= number_format($c['diferencia'], 2) ?> (Sobrante)</span>
                                <?php else: ?>
                                    <span class="tag-dif-bad">-$<?= number_format(abs($c['diferencia']), 2) ?> (Faltante)</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions-cell">
                                <div class="table-actions">
                                    <a href="cierre_caja.php?modo=dia&fecha=<?= $c['fecha'] ?>" class="btn" aria-label="Ver cierre del día" title="Ver cierre del día">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>Ver Día
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state empty-state-panel">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 3h14v18H5zM8 8h8M8 12h8M8 16h4"/><path d="M8 3v2m8-2v2"/></svg>
                <strong>Aún no hay cierres registrados</strong>
                <span>Completa el arqueo diario y guarda el cierre para verlo en este historial.</span>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>