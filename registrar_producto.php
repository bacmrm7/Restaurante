<?php
require_once 'seguridad.php';
requerirRol([1]); // Solo Administrador
include 'conexion.php';

// Si se envió el formulario para guardar el producto
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $categoria_id = $_POST['categoria_id'];
    $codigo = trim($_POST['codigo']);
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $es_combo = intval($_POST['es_combo']); 

    // Asignar valores según el tipo para evitar incongruencias contables
    if ($es_combo == 1) {
        $precio_compra = 0.00; // El costo del combo viene del BOM (Recetas)
        $precio_venta = floatval($_POST['precio_venta']);
        $stock_actual = 0;     // El stock de los combos depende de los insumos
        $stock_minimo = 0;
    } else {
        $precio_compra = floatval($_POST['precio_compra']);
        $precio_venta = 0.00;  // Los insumos no se venden por separado en el POS
        $stock_actual = intval($_POST['stock_actual']);
        $stock_minimo = intval($_POST['stock_minimo']);
    }

    try {
        $sql = "INSERT INTO productos (categoria_id, codigo, nombre, descripcion, precio_compra, precio_venta, stock_actual, stock_minimo, es_combo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$categoria_id, $codigo, $nombre, $descripcion, $precio_compra, $precio_venta, $stock_actual, $stock_minimo, $es_combo]);

        if ($es_combo == 1) {
            header('Location: menu.php?msg=combo_creado');
        } else {
            header('Location: index.php?msg=producto_creado');
        }
        exit();
    } catch (PDOException $e) {
        $error_db = "Error al guardar el producto: " . $e->getMessage();
    }
}

// Obtener las categorías únicas
try {
    $categorias = $conexion->query("SELECT MIN(id) as id, nombre FROM categorias GROUP BY nombre ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categorias = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Producto - ERP Hijos del Rey</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 600px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: auto; }
        h2 { color: #333; text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        textarea { resize: vertical; height: 80px; }
        .row { display: flex; gap: 15px; }
        .row .form-group { flex: 1; }
        button { background-color: #28a745; color: white; border: none; padding: 12px; width: 100%; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        button:hover { background-color: #218838; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #007bff; text-decoration: none; font-weight: bold; }
        .back-link:hover { text-decoration: underline; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb; font-size: 14px; }
        .tipo-box { background-color: #e9ecef; padding: 15px; border-radius: 6px; border-left: 5px solid #007bff; margin-bottom: 15px; }
        .oculto { display: none; }
    </style>
    <link rel="stylesheet" href="app.css">
    <script>
        function alternarCampos() {
            var tipo = document.getElementById('es_combo').value;
            var seccionInsumo = document.getElementById('seccion_insumo');
            var seccionCombo = document.getElementById('seccion_combo');

            if (tipo == "1") {
                seccionInsumo.style.display = "none";
                seccionCombo.style.display = "block";
            } else {
                seccionInsumo.style.display = "block";
                seccionCombo.style.display = "none";
            }
        }
    </script>
</head>
<body onload="alternarCampos()">

<?php include 'navegacion.php'; ?>
<div class="container">
    <h2>Registrar Nuevo Producto</h2>

    <?php if (isset($error_db)): ?>
        <div class="alert-error"><?= htmlspecialchars($error_db) ?></div>
    <?php endif; ?>

    <form action="registrar_producto.php" method="POST">
        
        <!-- Selector de Tipo de Registro -->
        <div class="tipo-box">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="es_combo" style="color: #0056b3;">📌 Tipo de Registro:</label>
                <select name="es_combo" id="es_combo" onchange="alternarCampos()" required>
                    <option value="0">📦 Insumo / Materia Prima (Almacén)</option>
                    <option value="1">🍗 Combo / Plato Final (Menú de Venta)</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="categoria_id">Categoría:</label>
            <select name="categoria_id" id="categoria_id" required>
                <option value="">-- Seleccione una categoría --</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row">
            <div class="form-group">
                <label for="codigo">Código:</label>
                <input type="text" name="codigo" id="codigo" placeholder="Ej. INS-010 o CMB-003" required>
            </div>
            <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" name="nombre" id="nombre" placeholder="Ej. Pollo o Combo Especial" required>
            </div>
        </div>

        <div class="form-group">
            <label for="descripcion">Descripción:</label>
            <textarea name="descripcion" id="descripcion" placeholder="Detalles del producto..."></textarea>
        </div>

        <!-- Campos exclusivos para INSUMOS / MATERIA PRIMA -->
        <div id="seccion_insumo">
            <div class="row">
                <div class="form-group">
                    <label for="precio_compra">Precio de Compra ($):</label>
                    <input type="number" step="0.01" name="precio_compra" id="precio_compra" value="0.00">
                </div>
                <div class="form-group">
                    <label for="stock_actual">Stock Inicial:</label>
                    <input type="number" name="stock_actual" id="stock_actual" value="0">
                </div>
            </div>
            <div class="form-group">
                <label for="stock_minimo">Stock Mínimo de Alerta:</label>
                <input type="number" name="stock_minimo" id="stock_minimo" value="5">
            </div>
        </div>

        <!-- Campos exclusivos para COMBOS / PLATOS DEL MENÚ -->
        <div id="seccion_combo" class="oculto">
            <div class="form-group">
                <label for="precio_venta">Precio de Venta al Público ($):</label>
                <input type="number" step="0.01" name="precio_venta" id="precio_venta" value="0.00">
                <small style="color: #666;">* El costo de producción de este combo se calculará automáticamente mediante su Ficha Técnica (Receta BOM).</small>
            </div>
        </div>

        <button type="submit">Guardar Producto</button>
    </form>

    <a href="index.php" class="back-link">← Volver al Inventario General</a>
</div>

</body>
</html>