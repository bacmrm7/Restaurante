<?php
$pagina_actual = basename($_SERVER['PHP_SELF']);
$rol_actual = $_SESSION['rol_id'] ?? null;
$es_admin = (int) $rol_actual === 1;
?>
<aside class="sidebar" aria-label="Navegación principal">
    <div class="brand">
        <span class="brand-mark">HR</span>
        <span class="brand-name">Hijos del Rey<span class="brand-subtitle">GESTIÓN DE NEGOCIO</span></span>
    </div>

    <?php if ($es_admin): ?>
        <span class="nav-label">Panel</span>
        <nav class="side-nav">
            <a class="side-link <?= $pagina_actual === 'index.php' ? 'active' : '' ?>" href="index.php" title="Consultar el inventario general"><span class="nav-mark">IN</span>Inventario</a>
            <a class="side-link <?= $pagina_actual === 'registrar_producto.php' ? 'active' : '' ?>" href="registrar_producto.php" title="Registrar un nuevo insumo"><span class="nav-mark">+</span>Nuevo insumo</a>
            <a class="side-link <?= $pagina_actual === 'recetas.php' ? 'active' : '' ?>" href="recetas.php" title="Consultar y gestionar recetas"><span class="nav-mark">RE</span>Recetas</a>
            <a class="side-link <?= $pagina_actual === 'menu.php' ? 'active' : '' ?>" href="menu.php" title="Ver el menú y administrar combos"><span class="nav-mark">ME</span>Menú y combos</a>
        </nav>
    <?php else: ?>
        <span class="nav-label">Producción</span>
        <nav class="side-nav">
            <?php if ((int) $rol_actual === 3): ?>
                <a class="side-link <?= $pagina_actual === 'index.php' ? 'active' : '' ?>" href="index.php" title="Consultar el inventario general"><span class="nav-mark">IN</span>Inventario</a>
            <?php endif; ?>
            <a class="side-link <?= $pagina_actual === 'recetas.php' ? 'active' : '' ?>" href="recetas.php" title="Consultar las recetas disponibles"><span class="nav-mark">RE</span>Recetas</a>
        </nav>
    <?php endif; ?>

    <span class="nav-label">Operaciones</span>
    <nav class="side-nav">
        <a class="side-link <?= $pagina_actual === 'ventas.php' ? 'active' : '' ?>" href="ventas.php" title="Registrar ventas en el punto de venta"><span class="nav-mark">PV</span>Punto de venta</a>
        <?php if ($es_admin): ?>
            <a class="side-link <?= $pagina_actual === 'gastos.php' ? 'active' : '' ?>" href="gastos.php" title="Consultar y registrar gastos operativos"><span class="nav-mark">GA</span>Gastos operativos</a>
        <?php endif; ?>
        <a class="side-link <?= $pagina_actual === 'cierre_caja.php' ? 'active' : '' ?>" href="cierre_caja.php" title="Consultar y consolidar cierres de caja"><span class="nav-mark">CJ</span>Cierre de caja</a>
        <a class="side-link <?= $pagina_actual === 'reporte_ventas.php' ? 'active' : '' ?>" href="reporte_ventas.php" title="Consultar el historial de ventas"><span class="nav-mark">RV</span>Reporte de ventas</a>
    </nav>

    <div class="sidebar-bottom">
        <nav class="side-nav">
            <?php if ($es_admin): ?>
                <a class="side-link <?= $pagina_actual === 'configuracion.php' ? 'active' : '' ?>" href="configuracion.php" title="Administrar configuración y respaldos"><span class="nav-mark">CO</span>Configuración</a>
                <a class="side-link <?= in_array($pagina_actual, ['rbac.php', 'control_accesos.php'], true) ? 'active' : '' ?>" href="control_accesos.php" title="Consultar roles y permisos"><span class="nav-mark">RB</span>Control de Accesos</a>
            <?php endif; ?>
            <a class="side-link logout" href="controllers/logout.php" title="Cerrar la sesión actual"><span class="nav-mark">↗</span>Cerrar sesión</a>
        </nav>
    </div>
</aside>
<script src="assets/app-ui.js" defer></script>