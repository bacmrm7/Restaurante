<?php
require_once 'seguridad.php';
requerirRol([1]); // Solo Administrador
include 'conexion.php';

$mensaje = "";
$error = "";

// Registrar un nuevo gasto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar') {
    $concepto = trim($_POST['concepto']);
    $monto = floatval($_POST['monto']);
    $frecuencia = $_POST['frecuencia'];
    $fecha = $_POST['fecha'];

    if (!empty($concepto) && $monto > 0 && !empty($fecha)) {
        try {
            $stmt = $conexion->prepare("INSERT INTO gastos (concepto, monto, frecuencia, fecha) VALUES (?, ?, ?, ?)");
            $stmt->execute([$concepto, $monto, $frecuencia, $fecha]);
            $mensaje = "Gasto registrado correctamente.";
        } catch (PDOException $e) {
            $error = "Error al registrar el gasto: " . $e->getMessage();
        }
    } else {
        $error = "Por favor completa todos los campos requeridos.";
    }
}

// Eliminar gasto
if (isset($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);
    try {
        $stmt = $conexion->prepare("DELETE FROM gastos WHERE id = ?");
        $stmt->execute([$id_eliminar]);
        header("Location: gastos.php?msg=eliminado");
        exit;
    } catch (PDOException $e) {
        $error = "Error al eliminar el gasto: " . $e->getMessage();
    }
}

// Consultar gastos registrados
$sql = "SELECT * FROM gastos ORDER BY fecha DESC, id DESC";
$gastos = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Calcular el Total de Gasto Mensual Equivalente
$total_gastos_mensuales = 0;
foreach ($gastos as $g) {
    $monto = floatval($g['monto']);
    switch ($g['frecuencia']) {
        case 'semanal':
            $total_gastos_mensuales += ($monto * 4); // 4 semanas al mes
            break;
        case 'mensual':
            $total_gastos_mensuales += $monto;
            break;
        case 'diario':
            $total_gastos_mensuales += ($monto * 30); // 30 días al mes
            break;
        case 'unico':
            $total_gastos_mensuales += $monto;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gastos Operativos - ERP Hijos del Rey</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1200px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: auto; }
        h2 { color: #333; margin: 0; }
        .btn { background-color: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; border: none; cursor: pointer; display: inline-block; }
        .btn:hover { background-color: #0056b3; }
        .btn-success { background-color: #28a745; }
        .btn-danger { background-color: #dc3545; }
        .btn-secondary { background-color: #6c757d; }
        .grid { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-top: 20px; }
        .card { background: #f8f9fa; padding: 20px; border-radius: 6px; border: 1px solid #dee2e6; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; color: #495057; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 14px; }
        th { background-color: #343a40; color: white; }
        .alert-success { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e9ecef; padding-bottom: 15px;">
        <h2>💸 Control de Gastos Operativos (Fijos / Variables)</h2>
        <a href="index.php" class="btn btn-secondary">← Volver al Inventario</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert-success" style="margin-top: 15px;"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-danger" style="margin-top: 15px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grid">
        <!-- Formulario para Registrar Gastos -->
        <div class="card">
            <h3 style="margin-top: 0; color: #333;">➕ Registrar Gasto</h3>
            <form action="gastos.php" method="POST">
                <input type="hidden" name="accion" value="guardar">
                
                <div class="form-group">
                    <label>Concepto / Descripción:</label>
                    <input type="text" name="concepto" placeholder="Ej: Sueldos de Personal, Alquiler, Wi-Fi" required>
                </div>

                <div class="form-group">
                    <label>Monto ($ USD):</label>
                    <input type="number" step="0.01" name="monto" placeholder="0.00" min="0.01" required>
                </div>

                <div class="form-group">
                    <label>Frecuencia:</label>
                    <select name="frecuencia">
                        <option value="semanal">Semanal</option>
                        <option value="mensual">Mensual</option>
                        <option value="diario">Diario</option>
                        <option value="unico">Pago Único / Ocasional</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fecha del Gasto:</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
                </div>

                <button type="submit" class="btn btn-success" style="width: 100%;">Guardar Gasto</button>
            </form>
        </div>

        <!-- Tabla e Historial de Gastos -->
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0; color: #333;">Historial de Gastos</h3>
                <span style="font-size: 15px; font-weight: bold; color: #721c24; background: #f8d7da; padding: 6px 12px; border-radius: 4px; border: 1px solid #f5c6cb;">
                    Total Gasto Mensual (Est.): $<?= number_format($total_gastos_mensuales, 2) ?>
                </span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Frecuencia</th>
                        <th>Monto Reg.</th>
                        <th>Equiv. Mensual</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($gastos) > 0): ?>
                        <?php foreach ($gastos as $gt): 
                            $monto_reg = floatval($gt['monto']);
                            $equiv_mensual = 0;
                            if ($gt['frecuencia'] == 'semanal') $equiv_mensual = $monto_reg * 4;
                            elseif ($gt['frecuencia'] == 'mensual') $equiv_mensual = $monto_reg;
                            elseif ($gt['frecuencia'] == 'diario') $equiv_mensual = $monto_reg * 30;
                            elseif ($gt['frecuencia'] == 'unico') $equiv_mensual = $monto_reg;
                        ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($gt['fecha'])) ?></td>
                                <td><strong><?= htmlspecialchars($gt['concepto']) ?></strong></td>
                                <td><small style="background: #e9ecef; padding: 3px 8px; border-radius: 4px; font-weight: bold; color: #495057;"><?= ucfirst($gt['frecuencia']) ?></small></td>
                                <td>$<?= number_format($monto_reg, 2) ?></td>
                                <td style="color: #dc3545; font-weight: bold;">$<?= number_format($equiv_mensual, 2) ?></td>
                                <td>
                                    <a href="gastos.php?eliminar=<?= $gt['id'] ?>" class="btn btn-danger" style="padding: 4px 8px; font-size: 12px;" onclick="return confirm('¿Seguro que deseas eliminar este gasto?');">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #777; padding: 20px;">No hay gastos registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>