<?php
require_once __DIR__ . '/includes/seguridad.php';
requerirRol([1]); // Solo Administrador
include __DIR__ . '/config/conexion.php';

$mensaje = '';
$error = '';

// 1. Determinar si se edita un combo o un producto directo.
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: " . (($_GET['tipo'] ?? '') === 'combo' ? 'menu.php' : 'index.php'));
    exit();
}

$es_combo = ($_GET['tipo'] ?? '') === 'combo';
$id_producto = (int) $_GET['id'];

// 2. Consultar el registro correspondiente para rellenar el formulario.
$sql = $es_combo
    ? "SELECT *, 1 AS es_combo, 0 AS stock_actual, 0 AS stock_minimo, 0 AS precio_compra FROM combos WHERE id = :id"
    : "SELECT * FROM productos WHERE id = :id";
$stmt = $conexion->prepare($sql);
$stmt->execute([':id' => $id_producto]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    header("Location: " . ($es_combo ? 'menu.php' : 'index.php'));
    exit();
}

// 3. Procesar el formulario cuando el usuario guarda cambios (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo']);
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $es_combo = (int) $p['es_combo'];

    if ($es_combo == 1) {
        // Valores predeterminados para Combos/Platos
        $precio_compra = 0.00;
        $precio_venta = floatval($_POST['precio_venta']);
        $stock_minimo = 0;
        $stock_adicional = 0;
    } else {
        // Valores para Insumos/Materia Prima
        $precio_compra = floatval($_POST['precio_compra']);
        $precio_venta = 0.00;
        $stock_minimo = intval($_POST['stock_minimo']);
        $stock_adicional = isset($_POST['stock_adicional']) ? intval($_POST['stock_adicional']) : 0;
    }

    try {
        // Iniciar transacción en base de datos
        $conexion->beginTransaction();

        if ($es_combo === 1) {
            $sql_update = "UPDATE combos SET codigo = :codigo, nombre = :nombre, descripcion = :descripcion,
                           precio_venta = :precio_venta, categoria_id = :categoria_id WHERE id = :id";
            $stmt = $conexion->prepare($sql_update);
            $stmt->execute([
                ':codigo' => $codigo,
                ':nombre' => $nombre,
                ':descripcion' => $descripcion,
                ':precio_venta' => $precio_venta,
                ':categoria_id' => $_POST['categoria_id'] ?? $p['categoria_id'],
                ':id' => $id_producto
            ]);
        } else {
            $stock_anterior = intval($p['stock_actual']);
            $stock_nuevo = $stock_anterior + $stock_adicional;
            $sql_update = "UPDATE productos SET codigo = :codigo, nombre = :nombre, descripcion = :descripcion,
                           precio_compra = :precio_compra, precio_venta = :precio_venta, stock_actual = :stock_nuevo,
                           stock_minimo = :stock_minimo WHERE id = :id";
            $stmt = $conexion->prepare($sql_update);
            $stmt->execute([
                ':codigo' => $codigo,
                ':nombre' => $nombre,
                ':descripcion' => $descripcion,
                ':precio_compra' => $precio_compra,
                ':precio_venta' => $precio_venta,
                ':stock_nuevo' => $stock_nuevo,
                ':stock_minimo' => $stock_minimo,
                ':id' => $id_producto
            ]);
        }

        // Registrar entrada en el Kardex si es un Insumo y hubo reabastecimiento
        if ($es_combo == 0 && $stock_adicional > 0) {
            $id_usuario = $_SESSION['usuario_id'] ?? 1;
            $sql_kardex = "INSERT INTO movimientos (producto_id, tipo, cantidad, stock_anterior, stock_nuevo, motivo, usuario_id) 
                           VALUES (:producto_id, 'Entrada', :cantidad, :stock_anterior, :stock_nuevo, 'Reabastecimiento de inventario', :usuario_id)";
            $stmt_kardex = $conexion->prepare($sql_kardex);
            $stmt_kardex->execute([
                ':producto_id' => $id_producto,
                ':cantidad' => $stock_adicional,
                ':stock_anterior' => $stock_anterior,
                ':stock_nuevo' => $stock_nuevo,
                ':usuario_id' => $id_usuario
            ]);
        }

        $conexion->commit();

        // Redirección inteligente según el tipo de producto
        if ($es_combo === 1) {
            header("Location: menu.php?msg=actualizado");
        } else {
            header("Location: index.php?msg=actualizado");
        }
        exit();

    } catch (Exception $e) {
        $conexion->rollBack();
        $error = "Error al actualizar el producto: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar / Reabastecer Producto - ERP Hijos del Rey</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 600px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: auto; }
        h2 { color: #333; text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="number"], textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        .badge-tipo { background: #e9ecef; padding: 12px; border-radius: 6px; border-left: 5px solid #007bff; margin-bottom: 20px; font-weight: bold; color: #0056b3; }
        .reabastecer-box { background-color: #e9ecef; padding: 15px; border-radius: 6px; border-left: 4px solid #28a745; margin-bottom: 15px; }
        .btn-container { display: flex; gap: 10px; justify-content: space-between; align-items: center; margin-top: 25px; border-top: 1px solid #eee; padding-top: 15px; }
        .btn-group-right { display: flex; gap: 10px; }
        .btn { padding: 10px 18px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 14px; display: inline-block; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-danger:hover { background-color: #bd2130; }
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>

<?php include __DIR__ . '/includes/navegacion.php'; ?>
<div class="container">
    <h2><?= $es_combo ? 'Editar Combo / Plato' : 'Editar / Reabastecer Producto' ?></h2>

    <?php if (!empty($error)): ?>
        <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="badge-tipo">
        Tipo de Registro: <?= $p['es_combo'] == 1 ? '🍗 Combo / Plato Final (Menú)' : '📦 Insumo / Materia Prima (Almacén)' ?>
    </div>

    <form id="delete-product-form" method="POST" action="controllers/eliminar_producto.php" data-confirm="¿Deseas eliminar este registro? Esta acción no se puede deshacer.">
        <input type="hidden" name="id" value="<?= (int) $id_producto ?>">
        <input type="hidden" name="tipo" value="<?= $es_combo ? 'combo' : 'producto' ?>">
    </form>
    <form action="editar_producto.php?<?= $es_combo ? 'tipo=combo&' : '' ?>id=<?= $id_producto ?>" method="POST">
        
        <div class="form-group">
            <label>Código del Producto:</label>
            <input type="text" name="codigo" value="<?= htmlspecialchars($p['codigo']) ?>" required>
        </div>

        <div class="form-group">
            <label>Nombre del Producto / Insumo:</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($p['nombre']) ?>" required>
        </div>

        <div class="form-group">
            <label>Descripción:</label>
            <textarea name="descripcion" rows="2"><?= htmlspecialchars($p['descripcion']) ?></textarea>
        </div>

        <?php if ($es_combo): ?>
            <div class="form-group">
                <label for="categoria_id">Categoría:</label>
                <select name="categoria_id" id="categoria_id" required>
                    <?php foreach ($conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC) as $categoria): ?>
                        <option value="<?= (int) $categoria['id'] ?>" <?= (int) $categoria['id'] === (int) $p['categoria_id'] ? 'selected' : '' ?>><?= htmlspecialchars($categoria['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <!-- FORMULARIO EXCLUSIVO PARA INSUMOS DE ALMACÉN -->
        <?php if ($p['es_combo'] == 0): ?>
            <div class="form-group">
                <label>Precio Compra ($):</label>
                <input type="number" step="0.01" name="precio_compra" value="<?= number_format($p['precio_compra'], 2, '.', '') ?>" required>
            </div>

            <div class="reabastecer-box">
                <div class="form-group" style="margin-bottom: 10px;">
                    <label>Stock Actual en Sistema:</label>
                    <input type="text" value="<?= $p['stock_actual'] ?> unidades" disabled style="font-weight: bold; background-color: #fff;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="color: #155724;">➕ Reabastecer (Ingresar cantidad recibida del proveedor):</label>
                    <input type="number" name="stock_adicional" value="0" min="0" placeholder="Ej. 20 (se sumará al stock actual)">
                    <small style="color: #555;">Deja en 0 si solo deseas modificar precios o datos del producto.</small>
                </div>
            </div>

            <div class="form-group">
                <label>Stock Mínimo Alerta:</label>
                <input type="number" name="stock_minimo" value="<?= $p['stock_minimo'] ?>" required>
            </div>
        <?php else: ?>
            <!-- FORMULARIO EXCLUSIVO PARA COMBOS DEL MENÚ -->
            <div class="form-group">
                <label>Precio de Venta al Público ($):</label>
                <input type="number" step="0.01" name="precio_venta" value="<?= number_format($p['precio_venta'], 2, '.', '') ?>" required>
                <small style="color: #666;">* El costo de producción del combo se calcula automáticamente en la Receta (BOM).</small>
            </div>
        <?php endif; ?>

        <div class="btn-container">
            <button type="submit" form="delete-product-form" class="btn btn-danger" title="Eliminar este producto del inventario">🗑️ Eliminar</button>
            <div class="btn-group-right">
                <a href="<?= $es_combo ? 'menu.php' : 'index.php' ?>" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-success">💾 Guardar Cambios</button>
            </div>
        </div>
    </form>
</div>

</body>
</html>