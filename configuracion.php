<?php
require_once __DIR__ . '/includes/seguridad.php';
requerirRol([1]); // Solo Administrador
include __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/configuracion_sistema.php';

function dividirScriptSql($script) {
    $sentencias = [];
    $sentencia = '';
    $comilla = null;
    $longitud = strlen($script);

    for ($i = 0; $i < $longitud; $i++) {
        $caracter = $script[$i];
        $siguiente = $script[$i + 1] ?? '';

        if ($comilla !== null) {
            $sentencia .= $caracter;
            if ($caracter === '\\' && $siguiente !== '') {
                $sentencia .= $siguiente;
                $i++;
            } elseif ($caracter === $comilla) {
                $comilla = null;
            }
            continue;
        }

        if (($caracter === '-' && $siguiente === '-') || $caracter === '#') {
            while ($i < $longitud && $script[$i] !== "\n") {
                $sentencia .= $script[$i++];
            }
            $sentencia .= "\n";
            continue;
        }

        if ($caracter === '/' && $siguiente === '*') {
            $finComentario = strpos($script, '*/', $i + 2);
            if ($finComentario === false) {
                throw new RuntimeException('El archivo SQL contiene un comentario incompleto.');
            }
            $sentencia .= substr($script, $i, $finComentario + 2 - $i);
            $i = $finComentario + 1;
            continue;
        }

        if ($caracter === "'" || $caracter === '"' || $caracter === '`') {
            $comilla = $caracter;
        } elseif ($caracter === ';') {
            if (trim($sentencia) !== '') {
                $sentencias[] = trim($sentencia);
            }
            $sentencia = '';
            continue;
        }
        $sentencia .= $caracter;
    }

    if ($comilla !== null) {
        throw new RuntimeException('El archivo SQL contiene una cadena incompleta.');
    }
    if (trim($sentencia) !== '') {
        $sentencias[] = trim($sentencia);
    }
    return $sentencias;
}

function responderJson($success, $message, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

obtenerConfiguracionSistema($conexion);

if (empty($_SESSION['csrf_configuracion'])) {
    $_SESSION['csrf_configuracion'] = bin2hex(random_bytes(32));
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_configuracion'], $_POST['csrf_token'] ?? '')) {
        $error = 'La sesión del formulario venció. Recarga la página e inténtalo de nuevo.';
    } else {
        $accion = $_POST['accion'] ?? '';
        try {
            if ($accion === 'guardar_perfil') {
                $nombre = trim($_POST['nombre_comercial'] ?? '');
                $direccion = trim($_POST['direccion'] ?? '');
                $rif = trim($_POST['rif'] ?? '');
                $telefono = trim($_POST['telefono'] ?? '');
                $tasa = filter_var($_POST['tasa_bcv'] ?? null, FILTER_VALIDATE_FLOAT);
                if ($nombre === '' || $tasa === false || $tasa === null || $tasa < 0) {
                    throw new RuntimeException('Indica un nombre comercial y una tasa BCV válida, igual o mayor que cero.');
                }
                $stmt = $conexion->prepare('UPDATE configuracion_sistema SET nombre_comercial = ?, direccion = ?, rif = ?, telefono = ?, tasa_bcv = ? WHERE id = 1');
                $stmt->execute([$nombre, $direccion, $rif, $telefono, $tasa]);
                header('Location: configuracion.php?estado=perfil_guardado');
                exit;
            }

            if ($accion === 'guardar_ticket') {
                $stmt = $conexion->prepare('UPDATE configuracion_sistema SET ticket_encabezado = ?, ticket_pie = ? WHERE id = 1');
                $stmt->execute([trim($_POST['ticket_encabezado'] ?? ''), trim($_POST['ticket_pie'] ?? '')]);
                header('Location: configuracion.php?estado=ticket_guardado');
                exit;
            }

            if ($accion === 'descargar_respaldo') {
                $conexion->exec('UPDATE configuracion_sistema SET ultimo_respaldo = NOW() WHERE id = 1');
                $tablas = $conexion->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                $contenidoSql = "-- Respaldo SQL - Hijos del Rey C.A.\n-- Fecha: " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n";
                foreach ($tablas as $tabla) {
                    $tablaSql = '`' . str_replace('`', '``', $tabla) . '`';
                    $ddl = $conexion->query("SHOW CREATE TABLE $tablaSql")->fetch(PDO::FETCH_ASSOC);
                    $contenidoSql .= "DROP TABLE IF EXISTS $tablaSql;\n" . $ddl['Create Table'] . ";\n";
                    $stmt = $conexion->query("SELECT * FROM $tablaSql");
                    while ($fila = $stmt->fetch(PDO::FETCH_NUM)) {
                        $valores = array_map(static fn($valor) => $valor === null ? 'NULL' : $conexion->quote((string) $valor), $fila);
                        $contenidoSql .= "INSERT INTO $tablaSql VALUES(" . implode(',', $valores) . ");\n";
                    }
                    $contenidoSql .= "\n";
                }
                $contenidoSql .= "SET FOREIGN_KEY_CHECKS=1;\n";
                $nombreArchivo = 'respaldo_hijos_del_rey_' . date('Y-m-d_H-i-s') . '.sql';
                header('Content-Type: application/sql; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
                header('Content-Length: ' . strlen($contenidoSql));
                echo $contenidoSql;
                exit;
            }

            if ($accion === 'restaurar_respaldo') {
                $archivo = $_FILES['respaldo_sql'] ?? null;
                if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK || strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) !== 'sql') {
                    throw new RuntimeException('Selecciona un archivo .sql válido para restaurar.');
                }
                if ($archivo['size'] > 32 * 1024 * 1024) {
                    throw new RuntimeException('El respaldo supera el límite de 32 MB permitido por la aplicación.');
                }
                $script = file_get_contents($archivo['tmp_name']);
                if ($script === false || trim($script) === '') {
                    throw new RuntimeException('No se pudo leer el archivo SQL seleccionado.');
                }
                $conexion->exec('SET FOREIGN_KEY_CHECKS=0');
                try {
                    foreach (dividirScriptSql($script) as $sentencia) {
                        $conexion->exec($sentencia);
                    }
                } finally {
                    $conexion->exec('SET FOREIGN_KEY_CHECKS=1');
                }
                    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
                        responderJson(true, 'El respaldo fue restaurado correctamente.');
                    }
                header('Location: configuracion.php?estado=respaldo_restaurado');
                exit;
            }
        } catch (Throwable $e) {
            if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
                responderJson(false, 'No se pudo completar la operación: ' . $e->getMessage(), 422);
            }
            $error = 'No se pudo completar la operación: ' . $e->getMessage();
        }
    }
}

$estados = [
    'perfil_guardado' => 'Perfil y parámetros financieros guardados.',
    'ticket_guardado' => 'Ajustes de comanda y ticket guardados.',
    'respaldo_restaurado' => 'El respaldo fue restaurado correctamente.',
    'mantenimiento_ventas' => 'Se eliminaron las ventas y cierres de caja. Catálogo e inventario se conservaron.',
    'mantenimiento_stock' => 'Las existencias se restablecieron a cero sin eliminar productos.',
    'mantenimiento_general' => 'Reinicio general completado: transacciones limpiadas y existencias en cero.'
];
$mensaje = $estados[$_GET['estado'] ?? ''] ?? '';
$config = obtenerConfiguracionSistema($conexion);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del establecimiento | Hijos del Rey C.A.</title>
    <style>
        .settings-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; margin-bottom: 24px; }
        .settings-header h2 { margin: 0 0 6px; }
        .settings-header p, .settings-help { margin: 0; color: var(--muted); font-size: 13px; line-height: 1.5; }
        .settings-stack { display: grid; gap: 16px; }
        .settings-card { padding: 22px !important; background: var(--paper) !important; }
        .settings-card h3 { margin: 0 0 5px; color: var(--forest) !important; font-size: 16px !important; }
        .settings-card > p { margin: 0 0 18px; color: var(--muted); font-size: 12px; line-height: 1.5; }
        .settings-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px; }
        .settings-fields .field-wide { grid-column: 1 / -1; }
        .settings-fields label { display: block; margin-bottom: 6px; color: var(--ink) !important; }
        .settings-fields input, .settings-fields textarea { width: 100%; }
        .settings-fields textarea { min-height: 88px; resize: vertical; }
        .settings-actions { display: flex; align-items: center; justify-content: flex-end; gap: 9px; margin-top: 18px; }
        .settings-actions .btn { width: auto; margin: 0; padding: 10px 15px !important; }
        .backup-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
        .backup-meta { color: var(--muted); font-size: 12px; }
        .backup-meta strong { display: block; margin-top: 4px; color: var(--ink); font-size: 13px; }
        .restore-form { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: 12px; margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--line); }
        .restore-form label { display: block; margin-bottom: 6px; }
        .restore-form input { width: 100%; }
        .maintenance-card { border-color: #e9c7c3 !important; }
        .maintenance-actions { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .maintenance-action { display: flex; min-height: 148px; flex-direction: column; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 16px; border: 1px solid var(--line); border-radius: 7px; background: #fff; }
        .maintenance-action strong { display: block; margin-bottom: 6px; color: var(--ink); font-size: 13px; }
        .maintenance-action span { display: block; color: var(--muted); font-size: 11px; line-height: 1.5; }
        .maintenance-action .btn { width: 100%; margin: 0; padding: 10px 12px !important; white-space: normal; }
        .maintenance-action.critical { border-color: #e7b7b1; background: #fff9f8; }
        .feedback { margin-bottom: 16px; padding: 12px 14px; border: 1px solid #cfe3d3; border-radius: 6px; background: #edf7ef; color: #245d39; font-size: 13px; font-weight: 650; }
        .feedback.error { border-color: #f0d8d3; background: var(--danger-bg); color: var(--danger); }
        @media (max-width: 760px) {
            .settings-header { align-items: flex-start; flex-direction: column; }
            .maintenance-actions { grid-template-columns: 1fr; }
            .maintenance-action { min-height: auto; }
        }
        @media (max-width: 540px) {
            .settings-fields, .restore-form { grid-template-columns: 1fr; }
            .settings-fields .field-wide { grid-column: auto; }
            .settings-card { padding: 17px !important; }
            .backup-row { align-items: flex-start; flex-direction: column; }
            .settings-actions { justify-content: stretch; }
            .settings-actions .btn { width: 100%; }
        }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>

<?php include __DIR__ . '/includes/navegacion.php'; ?>
<div class="container">
    <header class="settings-header">
        <div>
            <h1 class="page-title">Configuración del establecimiento</h1>
            <p class="page-subtitle">Administra la identidad comercial, los tickets y las operaciones de respaldo.</p>
        </div>
    </header>

    <?php if ($mensaje): ?><div class="feedback" role="status"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="feedback error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="settings-stack">
        <section class="card-section settings-card" aria-labelledby="profile-title">
            <h3 id="profile-title">Perfil del establecimiento y parámetros financieros</h3>
            <p>Estos datos identifican al negocio y mantienen disponible la tasa de cambio vigente.</p>
            <form method="POST" action="configuracion.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_configuracion']) ?>">
                <input type="hidden" name="accion" value="guardar_perfil">
                <div class="settings-fields">
                    <div>
                        <label for="nombre_comercial">Nombre comercial</label>
                        <input id="nombre_comercial" name="nombre_comercial" maxlength="150" value="<?= htmlspecialchars($config['nombre_comercial']) ?>" required>
                    </div>
                    <div>
                        <label for="rif">RIF</label>
                        <input id="rif" name="rif" maxlength="30" value="<?= htmlspecialchars($config['rif']) ?>" placeholder="J-00000000-0">
                    </div>
                    <div class="field-wide">
                        <label for="direccion">Dirección</label>
                        <input id="direccion" name="direccion" maxlength="255" value="<?= htmlspecialchars($config['direccion']) ?>">
                    </div>
                    <div>
                        <label for="telefono">Teléfono</label>
                        <input id="telefono" name="telefono" maxlength="40" value="<?= htmlspecialchars($config['telefono']) ?>">
                    </div>
                    <div>
                        <label for="tasa_bcv">Tasa BCV ($ / Bs)</label>
                        <input id="tasa_bcv" name="tasa_bcv" type="number" min="0" step="0.0001" value="<?= htmlspecialchars($config['tasa_bcv']) ?>" required>
                    </div>
                </div>
                <div class="settings-actions"><button class="btn btn-primary" type="submit">Guardar perfil</button></div>
            </form>
        </section>

        <section class="card-section settings-card" aria-labelledby="receipt-title">
            <h3 id="receipt-title">Ajustes de comanda y ticket de venta</h3>
            <p>El encabezado y el mensaje al pie se mostrarán en la confirmación de venta.</p>
            <form method="POST" action="configuracion.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_configuracion']) ?>">
                <input type="hidden" name="accion" value="guardar_ticket">
                <div class="settings-fields">
                    <div class="field-wide">
                        <label for="ticket_encabezado">Encabezado del ticket</label>
                        <input id="ticket_encabezado" name="ticket_encabezado" maxlength="255" value="<?= htmlspecialchars($config['ticket_encabezado']) ?>" placeholder="Gracias por su compra">
                    </div>
                    <div class="field-wide">
                        <label for="ticket_pie">Mensaje al pie de página</label>
                        <textarea id="ticket_pie" name="ticket_pie" maxlength="1000" placeholder="Vuelva pronto"><?= htmlspecialchars($config['ticket_pie']) ?></textarea>
                    </div>
                </div>
                <div class="settings-actions"><button class="btn btn-primary" type="submit">Guardar ajustes de ticket</button></div>
            </form>
        </section>

        <section class="card-section settings-card" aria-labelledby="backup-title">
            <h3 id="backup-title">Respaldo y restauración de base de datos</h3>
            <p>Descarga el contenido de la base de datos operativa o restaura un archivo SQL guardado previamente.</p>
            <div class="backup-row">
                <form method="POST" action="configuracion.php" data-native-submit="true">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_configuracion']) ?>">
                    <input type="hidden" name="accion" value="descargar_respaldo">
                    <button class="btn btn-primary" type="submit">Descargar copia de seguridad (.sql)</button>
                </form>
                <div class="backup-meta">Último respaldo<strong><?= $config['ultimo_respaldo'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($config['ultimo_respaldo']))) : 'Sin respaldos registrados' ?></strong></div>
            </div>
            <form class="restore-form" method="POST" action="configuracion.php" enctype="multipart/form-data" data-ajax="true" data-confirm="La restauración reemplazará las tablas y los datos actuales. Se recomienda descargar un respaldo antes de continuar.">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_configuracion']) ?>">
                <input type="hidden" name="accion" value="restaurar_respaldo">
                <div>
                    <label for="respaldo_sql">Archivo de respaldo (.sql)</label>
                    <input id="respaldo_sql" name="respaldo_sql" type="file" accept=".sql,application/sql" required>
                </div>
                <button class="btn btn-danger" type="submit">Cargar y restaurar</button>
            </form>
        </section>

        <section class="card-section settings-card maintenance-card" aria-labelledby="maintenance-title">
            <h3 id="maintenance-title">Mantenimiento avanzado</h3>
            <p>Operaciones independientes para pruebas. Cada opción solicita confirmación antes de modificar datos.</p>
            <div class="maintenance-actions">
                <form class="maintenance-action" method="POST" action="controllers/vaciar_historial.php" data-ajax="true" data-confirm="Se eliminarán las ventas, sus detalles y pagos asociados, además de los cierres de caja. El catálogo, las existencias y el Kardex se conservarán.">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_configuracion']) ?>">
                    <input type="hidden" name="accion" value="vaciar_ventas_caja">
                    <div><strong>Vaciar Historial de Ventas y Caja</strong><span>Elimina ventas, detalles, pagos relacionados y cierres. Conserva productos y existencias.</span></div>
                    <button class="btn btn-danger" type="submit">Vaciar ventas y caja</button>
                </form>
                <form class="maintenance-action" method="POST" action="controllers/vaciar_historial.php" data-ajax="true" data-confirm="Se pondrá a cero el stock de todos los productos. No se eliminarán productos ni movimientos del historial.">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_configuracion']) ?>">
                    <input type="hidden" name="accion" value="resetear_stocks">
                    <div><strong>Resetear Stocks a 0</strong><span>Actualiza las existencias sin borrar ningún producto del catálogo.</span></div>
                    <button class="btn btn-danger" type="submit">Resetear stocks</button>
                </form>
                <form class="maintenance-action critical" method="POST" action="controllers/vaciar_historial.php" data-ajax="true" data-confirm="Se limpiarán las ventas, pagos, cierres, movimientos, compras y gastos; además, todos los stocks quedarán en cero. Esta acción no se puede deshacer.">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_configuracion']) ?>">
                    <input type="hidden" name="accion" value="reinicio_general">
                    <div><strong>Reinicio General de Pruebas</strong><span>Limpia las transacciones operativas y restablece las existencias. Mantiene el catálogo.</span></div>
                    <button class="btn btn-danger" type="submit">Reiniciar entorno de pruebas</button>
                </form>
            </div>
        </section>
    </div>
</div>

</body>
</html>