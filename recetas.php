<?php
require_once __DIR__ . '/includes/seguridad.php';
requerirRol([1, 2, 3]); // Administrador, Cajero y Supervisor
include __DIR__ . '/config/conexion.php';

$mensaje = '';
$error = '';

// 1. Guardar o eliminar ingrediente de la receta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
        $combo_id = intval($_POST['combo_id']);
        $insumo_id = intval($_POST['insumo_id']);
        $cantidad = floatval($_POST['cantidad']);

        if ($combo_id > 0 && $insumo_id > 0 && $cantidad > 0) {
            try {
                $stmtCombo = $conexion->prepare("SELECT id FROM combos WHERE id = ?");
                $stmtCombo->execute([$combo_id]);
                $combo_id_validado = $stmtCombo->fetchColumn();

                if ($combo_id_validado === false) {
                    $error = "El combo seleccionado ya no existe.";
                } else {
                    $combo_id = (int) $combo_id_validado;

                    // Verificar si el insumo ya está en la receta del combo
                    $stmtCheck = $conexion->prepare("SELECT id FROM recetas WHERE combo_id = ? AND insumo_id = ?");
                    $stmtCheck->execute([$combo_id, $insumo_id]);

                    if ($stmtCheck->rowCount() > 0) {
                        $stmtUp = $conexion->prepare("UPDATE recetas SET cantidad = cantidad + ? WHERE combo_id = ? AND insumo_id = ?");
                        $stmtUp->execute([$cantidad, $combo_id, $insumo_id]);
                    } else {
                        $stmtIns = $conexion->prepare("INSERT INTO recetas (combo_id, insumo_id, cantidad) VALUES (?, ?, ?)");
                        $stmtIns->execute([$combo_id, $insumo_id, $cantidad]);
                    }
                    $mensaje = "Ingrediente agregado correctamente a la receta.";
                }
            } catch (PDOException $e) {
                $error = "Error al guardar el ingrediente: " . $e->getMessage();
            }
        } else {
            $error = "Por favor selecciona un combo, un insumo y una cantidad válida.";
        }
    } elseif (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
        $receta_id = intval($_POST['receta_id']);
        try {
            $stmtDel = $conexion->prepare("DELETE FROM recetas WHERE id = ?");
            $stmtDel->execute([$receta_id]);
            $mensaje = "Ingrediente eliminado de la receta.";
        } catch (PDOException $e) {
            $error = "Error al eliminar ingrediente: " . $e->getMessage();
        }
    }
}

// 2. Obtener lista de Combos / Platos Principales
try {
    $stmtCombos = $conexion->query("SELECT * FROM combos ORDER BY id DESC");
    $combos = $stmtCombos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $combos = [];
}

// 3. Obtener lista completa de Insumos / Materia Prima / Bebidas / Acompañantes (es_combo = 0)
try {
    $insumos = $conexion->query("SELECT id, codigo, nombre FROM productos WHERE es_combo = 0 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $insumos = [];
}

// 4. Combo seleccionado actualmente
$combo_seleccionado = isset($_GET['combo_id']) ? intval($_GET['combo_id']) : ($combos[0]['id'] ?? 0);

// 5. Obtener los ingredientes de la receta del combo seleccionado
$ingredientes_receta = [];
if ($combo_seleccionado > 0) {
    $sqlReceta = "SELECT r.id as receta_id, p.codigo, p.nombre as insumo_nombre, r.cantidad 
                  FROM recetas r 
                  JOIN productos p ON r.insumo_id = p.id 
                  WHERE r.combo_id = ? 
                  ORDER BY p.nombre ASC";
    $stmtR = $conexion->prepare($sqlReceta);
    $stmtR->execute([$combo_seleccionado]);
    $ingredientes_receta = $stmtR->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Recetas (BOM) | Hijos del Rey C.A.</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1000px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e9ecef; padding-bottom: 15px; margin-bottom: 20px; }
        h2 { margin: 0; color: #333; }
        .grid { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        .card { background: #f8f9fa; padding: 20px; border-radius: 6px; border: 1px solid #dee2e6; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #495057; font-size: 14px; }
        select, input[type="number"] { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        .btn { padding: 10px 15px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-success { background-color: #28a745; color: white; width: 100%; }
        .btn-danger { background-color: #dc3545; color: white; padding: 5px 10px; font-size: 12px; }
        .btn-secondary { background-color: #6c757d; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th, table td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; font-size: 14px; }
        table th { background-color: #e9ecef; color: #495057; }
        .alert-success { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #f5c6cb; }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>

<?php include __DIR__ . '/includes/navegacion.php'; ?>
<div class="container">
    <div class="header">
        <div>
            <h1 class="page-title">Ficha Técnica y Recetas</h1>
            <p class="page-subtitle">Administra los insumos y cantidades que componen cada plato o combo.</p>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($combos)): ?>
        <div class="empty-state empty-state-panel">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5zM4 7.5l8 4.7 8-4.7M12 12.2V21"/><path d="M8 5.2l8 4.6"/></svg>
            <strong>Aún no hay combos para configurar</strong>
            <span>Registra un combo o plato desde Menú y combos para preparar su ficha técnica.</span>
        </div>
    <?php else: ?>
        <!-- Selección del Combo a configurar -->
        <div style="margin-bottom: 20px; background: #e9ecef; padding: 15px; border-radius: 6px;">
            <form method="GET" action="recetas.php" id="formCombo">
                <label for="combo_id" style="font-size: 16px;">Selecciona el Combo / Plato a Estructurar:</label>
                <select name="combo_id" id="combo_id" onchange="document.getElementById('formCombo').submit();" style="font-size: 15px; font-weight: bold; margin-top: 5px;">
                    <?php foreach ($combos as $combo): ?>
                        <option value="<?= (int) $combo['id'] ?>" <?= (int) $combo['id'] === (int) $combo_seleccionado ? 'selected' : '' ?>>
                            <?= htmlspecialchars($combo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="grid">
            <!-- Formulario para agregar insumo a la receta -->
            <div class="card">
                <h3 style="margin-top: 0; color: #333;">➕ Añadir Ingrediente</h3>
                <form method="POST" action="recetas.php?combo_id=<?= $combo_seleccionado ?>">
                    <input type="hidden" name="accion" value="agregar">
                    <input type="hidden" name="combo_id" value="<?= $combo_seleccionado ?>">

                    <div class="form-group">
                        <label>Insumo / Materia Prima:</label>
                        <select name="insumo_id" required>
                            <option value="">Seleccione insumo...</option>
                            <?php foreach ($insumos as $ins): ?>
                                <option value="<?= $ins['id'] ?>">[<?= htmlspecialchars($ins['codigo']) ?>] <?= htmlspecialchars($ins['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cantidad a Descontar por Venta:</label>
                        <input type="number" step="0.01" name="cantidad" value="1.00" min="0.01" required>
                    </div>

                    <button type="submit" class="btn btn-success" title="Añadir el ingrediente seleccionado a la receta">Guardar Ingrediente</button>
                </form>
            </div>

            <!-- Tabla de ingredientes asociados actualmente -->
            <div class="card">
                <h3 style="margin-top: 0; color: #333;">📋 Estructura de la Receta</h3>
                <?php if (empty($ingredientes_receta)): ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6 4h12v16H6zM9 8h6M9 12h6M9 16h3"/></svg>
                        <strong>Esta receta aún no tiene ingredientes</strong>
                        <span>Agrega insumos con sus cantidades desde el formulario de la izquierda.</span>
                    </div>
                <?php else: ?>
                    <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Ingrediente / Insumo</th>
                                <th>Cant. Descuento</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ingredientes_receta as $ing): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($ing['codigo']) ?></strong></td>
                                    <td><?= htmlspecialchars($ing['insumo_nombre']) ?></td>
                                    <td><strong><?= number_format($ing['cantidad'], 2) ?></strong></td>
                                    <td class="table-actions-cell">
                                        <div class="table-actions">
                                        <form method="POST" action="recetas.php?combo_id=<?= $combo_seleccionado ?>" style="margin: 0;" data-confirm="¿Deseas quitar este ingrediente de la receta?">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="receta_id" value="<?= $ing['receta_id'] ?>">
                                            <button type="submit" class="btn btn-danger" title="Quitar este ingrediente de la receta">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m4 4v6m6-6v6"/></svg>Quitar
                                            </button>
                                        </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>