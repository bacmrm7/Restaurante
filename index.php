<?php
require_once 'seguridad.php';
requerirRol([1]); // 1 = Solo Administrador
include 'conexion.php';

// Consultar insumos y materias primas para el inventario general.
$sql = "SELECT p.*, c.nombre AS categoria_nombre 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.es_combo = 0 
        ORDER BY p.id DESC";
$stmt = $conexion->query($sql);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_productos = count($productos);
$productos_bajos = 0;
$valor_inventario = 0;

foreach ($productos as $producto) {
    if ((int) $producto['stock_actual'] <= (int) $producto['stock_minimo']) {
        $productos_bajos++;
    }
    $valor_inventario += (float) $producto['precio_compra'] * (int) $producto['stock_actual'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | ERP Hijos del Rey</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #202a24;
            --muted: #748078;
            --canvas: #f3f5f1;
            --paper: #ffffff;
            --line: #e6eae4;
            --forest: #18392d;
            --forest-light: #254b3b;
            --lime: #d5f36a;
            --danger: #b94238;
            --danger-bg: #fff0ed;
            --green: #2d7950;
            --green-bg: #edf7ef;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--canvas); color: var(--ink); font-family: "Avenir Next", Avenir, "Segoe UI", sans-serif; font-size: 14px; }
        a { color: inherit; }
        .app-shell { min-height: 100vh; }
        .sidebar { position: fixed; inset: 0 auto 0 0; z-index: 5; display: flex; width: 254px; flex-direction: column; padding: 28px 18px 18px; background: var(--forest); color: #f5f7f3; }
        .brand { display: flex; align-items: center; gap: 12px; padding: 0 10px 30px; }
        .brand-mark { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 11px; background: var(--lime); color: var(--forest); font-size: 16px; font-weight: 900; }
        .brand-name { font-size: 14px; font-weight: 750; line-height: 1.2; }
        .brand-subtitle { display: block; margin-top: 4px; color: #a9b9ae; font-size: 10px; font-weight: 600; }
        .nav-label { padding: 0 11px; margin: 13px 0 8px; color: #91a59a; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .side-nav { display: grid; gap: 4px; }
        .side-link { display: flex; min-height: 42px; align-items: center; gap: 12px; padding: 0 11px; border-radius: 7px; color: #d2ddd5; font-size: 13px; font-weight: 600; text-decoration: none; transition: background-color .16s ease, color .16s ease; }
        .side-link:hover { background: var(--forest-light); color: #fff; }
        .side-link.active { background: var(--lime); color: var(--forest); }
        .nav-mark { display: grid; width: 23px; height: 23px; place-items: center; border: 1px solid currentColor; border-radius: 6px; font-size: 9px; font-weight: 800; }
        .sidebar-bottom { margin-top: auto; padding-top: 16px; border-top: 1px solid rgba(255,255,255,.13); }
        .side-link.logout { color: #ffc2b9; }
        .main { min-height: 100vh; margin-left: 254px; padding: 34px clamp(22px, 4vw, 58px) 48px; }
        .content { width: min(100%, 1320px); margin: 0 auto; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 34px; }
        .eyebrow { margin: 0 0 7px; color: var(--muted); font-size: 11px; font-weight: 750; text-transform: uppercase; }
        h1 { margin: 0; font-size: clamp(25px, 3vw, 34px); font-weight: 750; line-height: 1.15; }
        .topbar-meta { display: flex; align-items: center; gap: 12px; }
        .today { color: var(--muted); font-size: 12px; }
        .avatar { display: grid; width: 38px; height: 38px; place-items: center; border: 1px solid #d9e2d8; border-radius: 50%; background: #e5eee2; color: var(--forest); font-size: 12px; font-weight: 800; }
        .welcome { display: flex; min-height: 148px; align-items: center; justify-content: space-between; gap: 22px; overflow: hidden; padding: 27px 32px; border-radius: 8px; background: var(--forest); color: #fff; }
        .welcome-copy { position: relative; z-index: 1; }
        .welcome-kicker { margin: 0 0 8px; color: var(--lime); font-size: 11px; font-weight: 750; text-transform: uppercase; }
        .welcome h2 { margin: 0; font-size: 22px; font-weight: 700; }
        .welcome p { margin: 8px 0 0; color: #c2d0c6; font-size: 13px; }
        .primary-action { flex: 0 0 auto; padding: 12px 16px; border: 0; border-radius: 6px; background: var(--lime); color: var(--forest); font-size: 12px; font-weight: 800; text-decoration: none; transition: background-color .16s ease, transform .16s ease; }
        .primary-action:hover { transform: translateY(-1px); background: #e2ff85; }
        .section-heading { display: flex; align-items: end; justify-content: space-between; gap: 12px; margin: 31px 0 14px; }
        .section-heading h2 { margin: 0; font-size: 17px; font-weight: 750; }
        .section-heading p { margin: 5px 0 0; color: var(--muted); font-size: 12px; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .summary-card { min-height: 126px; padding: 19px 20px; border: 1px solid var(--line); border-radius: 7px; background: var(--paper); }
        .summary-label { display: flex; align-items: center; justify-content: space-between; gap: 8px; color: var(--muted); font-size: 12px; font-weight: 650; }
        .summary-symbol { display: grid; width: 29px; height: 29px; place-items: center; border-radius: 7px; background: #eef3e8; color: var(--forest); font-size: 11px; font-weight: 850; }
        .summary-symbol.alert { background: var(--danger-bg); color: var(--danger); }
        .summary-value { display: block; margin-top: 12px; font-size: 27px; font-weight: 750; line-height: 1; }
        .summary-note { display: block; margin-top: 7px; color: var(--muted); font-size: 11px; }
        .inventory-section { margin-top: 32px; }
        .inventory-section .section-heading { margin-top: 0; }
        .table-panel { overflow: hidden; border: 1px solid var(--line); border-radius: 7px; background: var(--paper); }
        .table-scroll { overflow-x: auto; }
        table { width: 100%; min-width: 810px; border-collapse: collapse; }
        th, td { padding: 13px 15px; border-bottom: 1px solid #edf0ec; text-align: left; vertical-align: middle; }
        th { background: #f8faf7; color: #6e7a70; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        td { color: #39443c; font-size: 12px; }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #fbfcfa; }
        .product-name { color: var(--ink); font-size: 12px; font-weight: 750; }
        .product-description { display: block; max-width: 220px; margin-top: 4px; overflow: hidden; color: var(--muted); font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
        .stock-value { font-weight: 750; }
        .status { display: inline-flex; align-items: center; gap: 6px; padding: 5px 8px; border-radius: 4px; font-size: 10px; font-weight: 750; white-space: nowrap; }
        .status::before { width: 6px; height: 6px; border-radius: 50%; background: currentColor; content: ""; }
        .status.low { background: var(--danger-bg); color: var(--danger); }
        .status.ok { background: var(--green-bg); color: var(--green); }
        .edit-link { color: var(--forest-light); font-size: 11px; font-weight: 750; text-decoration: none; }
        .edit-link:hover { text-decoration: underline; }
        .empty-state { padding: 38px 20px; color: var(--muted); text-align: center; }
        .empty-state strong { display: block; margin-bottom: 5px; color: var(--ink); }
        @media (max-width: 980px) {
            .sidebar { width: 220px; }
            .main { margin-left: 220px; padding-right: 25px; padding-left: 25px; }
            .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .summary-card:last-child { grid-column: 1 / -1; }
        }
        @media (max-width: 700px) {
            .sidebar { position: static; width: 100%; padding: 15px 16px 12px; }
            .brand { padding: 0 3px 14px; }
            .nav-label, .sidebar-bottom { display: none; }
            .side-nav { display: flex; overflow-x: auto; gap: 5px; padding-bottom: 2px; }
            .side-link { flex: 0 0 auto; min-height: 38px; gap: 8px; padding: 0 10px; font-size: 12px; }
            .nav-mark { width: 20px; height: 20px; }
            .main { min-height: auto; margin-left: 0; padding: 25px 16px 36px; }
            .topbar { margin-bottom: 23px; }
            .today { display: none; }
            .welcome { min-height: 164px; align-items: flex-start; flex-direction: column; padding: 22px; }
            .welcome h2 { font-size: 19px; }
            .summary-grid { gap: 9px; }
            .summary-card { min-height: 116px; padding: 15px; }
            .summary-value { font-size: 23px; }
            .section-heading { margin-top: 25px; }
        }
        @media (max-width: 390px) {
            .summary-grid { grid-template-columns: 1fr; }
            .summary-card:last-child { grid-column: auto; }
            .topbar h1 { font-size: 24px; }
        }
    </style>
    <link rel="stylesheet" href="app.css">
    <script src="app-ui.js" defer></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" aria-label="Navegación principal">
        <div class="brand">
            <span class="brand-mark">HR</span>
            <span class="brand-name">Hijos del Rey<span class="brand-subtitle">GESTIÓN DE NEGOCIO</span></span>
        </div>

        <span class="nav-label">Panel</span>
        <nav class="side-nav">
            <a class="side-link active" href="index.php" aria-current="page"><span class="nav-mark">IN</span>Inventario</a>
            <a class="side-link" href="registrar_producto.php"><span class="nav-mark">+</span>Nuevo insumo</a>
            <a class="side-link" href="recetas.php"><span class="nav-mark">RE</span>Recetas</a>
            <a class="side-link" href="menu.php"><span class="nav-mark">ME</span>Menú y combos</a>
        </nav>

        <span class="nav-label">Operaciones</span>
        <nav class="side-nav">
            <a class="side-link" href="ventas.php"><span class="nav-mark">PV</span>Punto de venta</a>
            <a class="side-link" href="gastos.php"><span class="nav-mark">GA</span>Gastos operativos</a>
            <a class="side-link" href="cierre_caja.php"><span class="nav-mark">CJ</span>Cierre de caja</a>
            <a class="side-link" href="reporte_ventas.php"><span class="nav-mark">RV</span>Reporte de ventas</a>
        </nav>

        <div class="sidebar-bottom">
            <nav class="side-nav">
                <a class="side-link" href="configuracion.php"><span class="nav-mark">CO</span>Configuración</a>
                <a class="side-link logout" href="logout.php"><span class="nav-mark">↗</span>Cerrar sesión</a>
            </nav>
        </div>
    </aside>

    <main class="main">
        <div class="content">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Administración</p>
                    <h1>Panel de control</h1>
                </div>
                <div class="topbar-meta">
                    <span class="today"><?= htmlspecialchars(date('d/m/Y')) ?></span>
                    <span class="avatar" aria-label="Administrador">AD</span>
                </div>
            </header>

            <section class="welcome" aria-labelledby="welcome-title">
                <div class="welcome-copy">
                    <p class="welcome-kicker">Hijos del Rey · Inventario</p>
                    <h2 id="welcome-title">Todo en orden, desde un solo lugar.</h2>
                    <p>Consulta existencias y mantén tus insumos al día.</p>
                </div>
                <a class="primary-action" href="registrar_producto.php">+ Registrar insumo</a>
            </section>

            <section aria-labelledby="summary-title">
                <div class="section-heading">
                    <div>
                        <h2 id="summary-title">Resumen de inventario</h2>
                        <p>Estado actual de insumos y materias primas</p>
                    </div>
                </div>
                <div class="summary-grid">
                    <article class="summary-card">
                        <div class="summary-label">Insumos registrados <span class="summary-symbol">IN</span></div>
                        <strong class="summary-value"><?= number_format($total_productos) ?></strong>
                        <span class="summary-note">Productos en inventario general</span>
                    </article>
                    <article class="summary-card">
                        <div class="summary-label">Requieren atención <span class="summary-symbol alert">!</span></div>
                        <strong class="summary-value"><?= number_format($productos_bajos) ?></strong>
                        <span class="summary-note">En el mínimo o por debajo del mínimo</span>
                    </article>
                    <article class="summary-card">
                        <div class="summary-label">Valor de existencias <span class="summary-symbol">$</span></div>
                        <strong class="summary-value">$<?= number_format($valor_inventario, 2) ?></strong>
                        <span class="summary-note">Calculado al precio de compra</span>
                    </article>
                </div>
            </section>

            <section class="inventory-section" id="inventario" aria-labelledby="inventory-title">
                <div class="section-heading">
                    <div>
                        <h2 id="inventory-title">Inventario general</h2>
                        <p>Insumos y materias primas registrados</p>
                    </div>
                    <a class="edit-link" href="registrar_producto.php">+ Agregar producto</a>
                </div>

                <div class="table-panel">
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Insumo / Materia prima</th>
                                    <th>Categoría</th>
                                    <th>Precio compra</th>
                                    <th>Stock actual</th>
                                    <th>Stock mín.</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($total_productos > 0): ?>
                                    <?php foreach ($productos as $p): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($p['codigo']) ?></td>
                                            <td>
                                                <span class="product-name"><?= htmlspecialchars($p['nombre']) ?></span>
                                                <span class="product-description"><?= htmlspecialchars($p['descripcion'] ?? '') ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin categoría') ?></td>
                                            <td>$<?= number_format((float) $p['precio_compra'], 2) ?></td>
                                            <td class="stock-value"><?= (int) $p['stock_actual'] ?></td>
                                            <td><?= (int) $p['stock_minimo'] ?></td>
                                            <td>
                                                <?php if ((int) $p['stock_actual'] <= (int) $p['stock_minimo']): ?>
                                                    <span class="status low">Stock bajo</span>
                                                <?php else: ?>
                                                    <span class="status ok">Óptimo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><a class="edit-link" href="editar_producto.php?id=<?= (int) $p['id'] ?>">Editar</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td class="empty-state" colspan="8">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5zM4 7.5l8 4.7 8-4.7M12 12.2V21"/><path d="M8 5.2l8 4.6"/></svg>
                                            <strong>El inventario está vacío</strong>
                                            <span>Registra un insumo o materia prima para comenzar a controlar existencias.</span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>