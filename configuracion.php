<?php
require_once __DIR__ . '/includes/seguridad.php';
requerirRol([1]); // Solo Administrador
include __DIR__ . '/config/conexion.php';

$mensaje = "";
$error = "";

// Lógica opcional para exportar respaldo de base de datos (Backup SQL)
if (isset($_GET['accion']) && $_GET['accion'] === 'backup') {$nombre_archivo = "respaldo_hijos_del_rey_" . date('Y-m-d_H-i-s') . ".sql";
    
    // Obtener todas las tablas
    $tablas = [];
    $stmt =$conexion->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {$tablas[] = $row[0]; }$contenido_sql = "-- Respaldo de Base de Datos - Sistema Hijos del Rey\n";
    $contenido_sql .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($tablas as$tabla) {
        $stmt =$conexion->query("SELECT * FROM `$tabla`");
        $num_campos = $stmt->columnCount();$contenido_sql .= "DROP TABLE IF EXISTS `$tabla`;\n";
        $row2 =$conexion->query("SHOW CREATE TABLE `$tabla`")->fetch(PDO::FETCH_ASSOC);
        $contenido_sql .=$row2['Create Table'] . ";\n\n";
        
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {$contenido_sql .= "INSERT INTO `$tabla` VALUES(";
            for ($i = 0; $i < $num_campos; $i++) {
                if (!isset($row[$i])) {$contenido_sql .= "NULL";
                } else {
                    $row[$i] = addslashes($row[$i]);$row[$i] = str_replace("\n", "\\n", $row[$i]);$contenido_sql .= '"' . $row[$i] . '"';
                }
                if ($i < ($num_campos - 1)) {$contenido_sql .= ',';
                }
            }
            $contenido_sql .= ");\n";
        }
        $contenido_sql .= "\n\n";
    }
    
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"" . $nombre_archivo . "\"");
    echo $contenido_sql;
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración y Mantenimiento - Hijos del Rey</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 900px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e9ecef; padding-bottom: 15px; margin-bottom: 25px; }
        h2 { margin: 0; color: #333; }
        .btn { background-color: #6c757d; color: white; padding: 10px 18px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; border: none; cursor: pointer; display: inline-block; }
        .btn:hover { background-color: #5a6268; }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-danger:hover { background-color: #c82333; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0056b3; }
        .card-section { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin-bottom: 20px; }
        .card-section h3 { margin-top: 0; color: #007bff; font-size: 18px; border-bottom: 1px solid #ced4da; padding-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 10px; }
        .info-item label { display: block; font-size: 13px; color: #6c757d; font-weight: bold; margin-bottom: 3px; }
        .info-item span { font-size: 15px; color: #212529; font-weight: bold; }
        .danger-zone { background: #fff5f5; border: 1px solid #feb2b2; }
        .danger-zone h3 { color: #c53030; border-color: #fed7d7; }
        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; font-weight: bold; }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>

<?php include __DIR__ . '/includes/navegacion.php'; ?>
<div class="container">
    <div class="header">
        <h2>⚙️ Configuración y Mantenimiento del Sistema</h2>
        <a href="index.php" class="btn" title="Volver al inventario general">← Regresar al Inventario</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <!-- 1. INFORMACIÓN DEL ESTABLECIMIENTO -->
    <div class="card-section">
        <h3>🏢 Información del Establecimiento y Modelo Operativo</h3>
        <div class="info-grid">
            <div class="info-item">
                <label>Nombre Comercial:</label>
                <span>Restaurante Hijos del Rey</span>
            </div>
            <div class="info-item">
                <label>Ubicación:</label>
                <span>Maracaibo, Estado Zulia, Venezuela</span>
            </div>
            <div class="info-item">
                <label>Modelo Operativo:</label>
                <span>4 días por semana (Viernes a Lunes)</span>
            </div>
            <div class="info-item">
                <label>Jornada Mensual Estándar:</label>
                <span>16 días laborables / mes (Prorrateo de Gastos)</span>
            </div>
            <div class="info-item">
                <label>Arquitectura del Software:</label>
                <span>PHP con PDO / MySQL / Arquitectura Modular</span>
            </div>
            <div class="info-item">
                <label>Nivel de Acceso Activo:</label>
                <span style="color: #28a745;">Administrador General (Rol 1)</span>
            </div>
        </div>
    </div>

    <!-- 2. GESTIÓN Y RESPALDO DE DATOS -->
    <div class="card-section">
        <h3>💾 Respaldo y Seguridad de Base de Datos</h3>
        <p style="color: #555; font-size: 14px; margin-top: 0;">
            Descarga un archivo de respaldo SQL con toda la información actual del inventario, ventas, recetas y cierres de caja registrados en el sistema.
        </p>
        <a href="configuracion.php?action=backup" class="btn btn-primary" title="Descargar un respaldo SQL de las bases de datos" onclick="return confirm('¿Deseas descargar el respaldo SQL de la base de datos ahora?');">
            📥 Descargar Respaldo de Base de Datos (.SQL)
        </a>
    </div>

    <!-- 3. ZONA DE MANTENIMIENTO Y PRUEBAS -->
    <div class="card-section danger-zone">
        <h3>⚠️ Zona de Mantenimiento y Pruebas</h3>
        <p style="color: #718096; font-size: 14px; margin-top: 0;">
            Esta opción elimina de forma permanente todas las transacciones registradas (entradas/salidas del Kardex, historial de ventas, pagos y cierres de caja). Los productos y recetas registrados permanecerán en el catálogo con stock inicial en 0. Ideal para reiniciar pruebas antes de la defensa.
        </p>
        <form method="POST" action="controllers/vaciar_historial.php" data-confirm="¿Deseas vaciar todo el historial y reiniciar los stocks? Esta acción no se puede deshacer.">
            <button type="submit" class="btn btn-danger" title="Eliminar el historial de transacciones y reiniciar existencias">🗑️ Vaciar Historial y Reiniciar Stocks a 0</button>
        </form>
    </div>
</div>

</body>
</html>