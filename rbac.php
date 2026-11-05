<?php
require_once __DIR__ . '/includes/seguridad.php';
requerirRol([1]);
require_once __DIR__ . '/config/conexion.php';

$esAdministrador = (int) ($_SESSION['rol_id'] ?? 0) === 1;
$csrfToken = $_SESSION['csrf_rbac'] ??= bin2hex(random_bytes(32));
$modulosBase = [
    ['Inventario', 'Consulta y administración de existencias.'],
    ['Punto de Venta', 'Registro y consulta de ventas.'],
    ['Recetas', 'Consulta y administración de recetas.'],
    ['Menú y Combos', 'Consulta y administración del menú del restaurante y sus combos.'],
    ['Cierre de Caja', 'Consulta y registro de cierres de caja.'],
    ['Reportes de Ventas', 'Consulta de reportes de ventas.'],
    ['Gastos operativos', 'Consulta y administración de gastos operativos.'],
    ['Configuración', 'Configuración general y administración del sistema.'],
];

$asegurarColumna = static function (PDO $db, string $tabla, string $columna, string $definicion): void {
    $consulta = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $consulta->execute(['erp_seguridad', $tabla, $columna]);
    if ((int) $consulta->fetchColumn() === 0) {
        $db->exec("ALTER TABLE erp_seguridad.`$tabla` ADD COLUMN `$columna` $definicion");
    }
};

$asegurarColumna($conexion_seguridad, 'usuarios', 'nombre_completo', 'VARCHAR(120) NULL AFTER `username`');
$asegurarColumna($conexion_seguridad, 'roles', 'nivel_acceso', 'VARCHAR(120) NULL AFTER `descripcion`');
$asegurarColumna($conexion_seguridad, 'roles', 'estado', "ENUM('activo','inactivo') NOT NULL DEFAULT 'activo' AFTER `nivel_acceso`");

foreach ($modulosBase as $indice => [$nombreModulo, $descripcionModulo]) {
    $claveModulo = strtolower(str_replace(' ', '_', $nombreModulo));
    $stmt = $conexion_seguridad->prepare('INSERT INTO erp_seguridad.modulos (nombre, clave, descripcion, orden) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion), orden = VALUES(orden)');
    $stmt->execute([$nombreModulo, $claveModulo, $descripcionModulo, $indice + 1]);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('La sesión del formulario venció. Recarga la página e inténtalo de nuevo.');
        }

        $accion = (string) ($_POST['accion'] ?? '');
        $usuarioAuditoria = (int) ($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0);
        $rolesBase = [1, 2, 3];

        if ($accion === 'crear_usuario') {
            $nombre = trim((string) ($_POST['nombre_completo'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirmarPassword = (string) ($_POST['confirmar_password'] ?? '');
            $rolesSeleccionados = array_values(array_unique(array_filter(array_map('intval', $_POST['roles'] ?? []))));
            if ($nombre === '' || $username === '' || $password === '' || !$rolesSeleccionados) {
                throw new RuntimeException('Completa los datos obligatorios y asigna al menos un rol.');
            }
            if ($password !== $confirmarPassword) {
                throw new RuntimeException('La clave y su confirmación no coinciden.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Ingresa un correo electrónico válido.');
            }
            $validarRoles = $conexion_seguridad->prepare('SELECT id FROM erp_seguridad.roles WHERE id IN (' . implode(',', array_fill(0, count($rolesSeleccionados), '?')) . ')');
            $validarRoles->execute($rolesSeleccionados);
            if (count($validarRoles->fetchAll(PDO::FETCH_COLUMN)) !== count($rolesSeleccionados)) {
                throw new RuntimeException('Uno o más roles seleccionados ya no existen.');
            }
            $conexion_seguridad->beginTransaction();
            $insertarUsuario = $conexion_seguridad->prepare('INSERT INTO erp_seguridad.usuarios (username, nombre_completo, password, email, rol_id, estado) VALUES (?, ?, ?, ?, ?, ?)');
            $insertarUsuario->execute([$username, $nombre, password_hash($password, PASSWORD_DEFAULT), $email !== '' ? $email : null, $rolesSeleccionados[0], 'activo']);
            $usuarioNuevo = (int) $conexion_seguridad->lastInsertId();
            $insertarRol = $conexion_seguridad->prepare('INSERT INTO erp_seguridad.r_usuario_rol (usuario_id, rol_id) VALUES (?, ?)');
            foreach ($rolesSeleccionados as $rolId) {
                $insertarRol->execute([$usuarioNuevo, $rolId]);
            }
            $conexion_seguridad->commit();
            registrarLog($conexion_seguridad, $usuarioAuditoria, 'Crear usuario', "Se creó el usuario $username.");
            $_SESSION['flash_rbac'] = 'Usuario creado correctamente.';
        } elseif ($accion === 'actualizar_datos_usuario') {
            if ((int) ($_SESSION['rol_id'] ?? 0) !== 1) {
                http_response_code(403);
                throw new RuntimeException('Solo el Administrador puede editar los datos de usuarios.');
            }
            $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
            $nombre = trim((string) ($_POST['nombre_completo'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $nuevaClave = (string) ($_POST['password'] ?? '');
            $confirmarNuevaClave = (string) ($_POST['confirmar_password'] ?? '');
            if ($usuarioId < 1 || $nombre === '' || $username === '') {
                throw new RuntimeException('El nombre completo y el usuario de acceso son obligatorios.');
            }
            if ($nuevaClave !== $confirmarNuevaClave) {
                throw new RuntimeException('La nueva clave y su confirmación no coinciden.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Ingresa un correo electrónico válido.');
            }
            if ($nuevaClave !== '' && strlen($nuevaClave) < 8) {
                throw new RuntimeException('La nueva clave debe tener al menos 8 caracteres.');
            }
            $buscarUsuario = $conexion_seguridad->prepare('SELECT id FROM erp_seguridad.usuarios WHERE id = ?');
            $buscarUsuario->execute([$usuarioId]);
            if (!$buscarUsuario->fetchColumn()) {
                throw new RuntimeException('El usuario que intentas editar ya no existe.');
            }
            $buscarUsername = $conexion_seguridad->prepare('SELECT id FROM erp_seguridad.usuarios WHERE username = ? AND id <> ?');
            $buscarUsername->execute([$username, $usuarioId]);
            if ($buscarUsername->fetchColumn()) {
                throw new RuntimeException('Ese nombre de usuario ya está registrado.');
            }
            if ($nuevaClave === '') {
                $actualizarUsuario = $conexion_seguridad->prepare('UPDATE erp_seguridad.usuarios SET nombre_completo = ?, username = ?, email = ? WHERE id = ?');
                $actualizarUsuario->execute([$nombre, $username, $email !== '' ? $email : null, $usuarioId]);
            } else {
                $actualizarUsuario = $conexion_seguridad->prepare('UPDATE erp_seguridad.usuarios SET nombre_completo = ?, username = ?, email = ?, password = ? WHERE id = ?');
                $actualizarUsuario->execute([$nombre, $username, $email !== '' ? $email : null, password_hash($nuevaClave, PASSWORD_DEFAULT), $usuarioId]);
            }
            registrarLog($conexion_seguridad, $usuarioAuditoria, 'Editar datos de usuario', "Se actualizaron los datos del usuario $usuarioId.");
            $_SESSION['flash_rbac'] = 'Datos del usuario actualizados correctamente.';
        } elseif ($accion === 'guardar_roles_usuario') {
            if ((int) ($_SESSION['rol_id'] ?? 0) !== 1) {
                http_response_code(403);
                throw new RuntimeException('Solo el Administrador puede asignar roles o cambiar el estado de usuarios.');
            }
            $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
            $estadoUsuario = (string) ($_POST['estado_usuario'] ?? '');
            $rolesSeleccionados = array_values(array_unique(array_filter(array_map('intval', $_POST['roles'] ?? []))));
            if ($usuarioId < 1 || !$rolesSeleccionados || !in_array($estadoUsuario, ['activo', 'inactivo'], true)) {
                throw new RuntimeException('Selecciona al menos un rol y un estado válido para el usuario.');
            }
            $validarRoles = $conexion_seguridad->prepare('SELECT id FROM erp_seguridad.roles WHERE id IN (' . implode(',', array_fill(0, count($rolesSeleccionados), '?')) . ')');
            $validarRoles->execute($rolesSeleccionados);
            if (count($validarRoles->fetchAll(PDO::FETCH_COLUMN)) !== count($rolesSeleccionados)) {
                throw new RuntimeException('Uno o más roles seleccionados ya no existen.');
            }
            $conexion_seguridad->beginTransaction();
            $usuarioBloqueado = $conexion_seguridad->prepare('SELECT username FROM erp_seguridad.usuarios WHERE id = ? FOR UPDATE');
            $usuarioBloqueado->execute([$usuarioId]);
            $usernameObjetivo = $usuarioBloqueado->fetchColumn();
            if ($usernameObjetivo === false) {
                throw new RuntimeException('El usuario ya no existe.');
            }
            if ($estadoUsuario === 'inactivo' && ($usuarioId === 1 || strcasecmp(trim((string) $usernameObjetivo), 'admin') === 0)) {
                $conexion_seguridad->rollBack();
                $_SESSION['flash_rbac_error'] = 'El superadministrador está protegido y no puede desactivarse.';
                header('Location: rbac.php');
                exit();
            }
            $actualizarUsuario = $conexion_seguridad->prepare('UPDATE erp_seguridad.usuarios SET rol_id = ?, estado = ? WHERE id = ?');
            $actualizarUsuario->execute([$rolesSeleccionados[0], $estadoUsuario, $usuarioId]);
            $conexion_seguridad->prepare('DELETE FROM erp_seguridad.r_usuario_rol WHERE usuario_id = ?')->execute([$usuarioId]);
            $insertarRol = $conexion_seguridad->prepare('INSERT INTO erp_seguridad.r_usuario_rol (usuario_id, rol_id) VALUES (?, ?)');
            foreach ($rolesSeleccionados as $rolId) {
                $insertarRol->execute([$usuarioId, $rolId]);
            }
            $conexion_seguridad->commit();
            registrarLog($conexion_seguridad, $usuarioAuditoria, 'Asignar roles', "Se actualizaron los roles del usuario $usuarioId.");
            $_SESSION['flash_rbac'] = 'Roles guardados correctamente.';
        } elseif ($accion === 'eliminar_usuario') {
            $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
            $conexion_seguridad->beginTransaction();
            $usuarioBloqueado = $conexion_seguridad->prepare('SELECT username FROM erp_seguridad.usuarios WHERE id = ? FOR UPDATE');
            $usuarioBloqueado->execute([$usuarioId]);
            $usernameObjetivo = $usuarioBloqueado->fetchColumn();
            if ($usernameObjetivo === false) {
                throw new RuntimeException('El usuario ya no existe.');
            }
            if ($usuarioId === 1 || strcasecmp(trim((string) $usernameObjetivo), 'admin') === 0) {
                $conexion_seguridad->rollBack();
                $_SESSION['flash_rbac_error'] = 'El superadministrador está protegido y no puede eliminarse.';
                header('Location: rbac.php');
                exit();
            }
            $eliminar = $conexion_seguridad->prepare('DELETE FROM erp_seguridad.usuarios WHERE id = ?');
            $eliminar->execute([$usuarioId]);
            if (!$eliminar->rowCount()) {
                throw new RuntimeException('El usuario ya no existe.');
            }
            $conexion_seguridad->commit();
            $_SESSION['flash_rbac'] = 'Usuario eliminado correctamente.';
        } elseif ($accion === 'crear_rol') {
            $nombre = trim((string) ($_POST['nombre_rol'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
            $nivel = trim((string) ($_POST['nivel_acceso'] ?? ''));
            if ($nombre === '' || !in_array($nivel, ['administrativo', 'supervision', 'operativo'], true)) {
                throw new RuntimeException('Ingresa el nombre y selecciona un nivel de acceso válido.');
            }
            $insertar = $conexion_seguridad->prepare('INSERT INTO erp_seguridad.roles (nombre, descripcion, nivel_acceso) VALUES (?, ?, ?)');
            $insertar->execute([$nombre, $descripcion !== '' ? $descripcion : null, $nivel]);
            registrarLog($conexion_seguridad, $usuarioAuditoria, 'Crear rol', "Se creó el rol $nombre.");
            $_SESSION['flash_rbac'] = 'Rol creado correctamente.';
        } elseif ($accion === 'actualizar_rol') {
            if ((int) ($_SESSION['rol_id'] ?? 0) !== 1) {
                http_response_code(403);
                throw new RuntimeException('Solo el Administrador puede editar roles.');
            }
            $rolId = (int) ($_POST['rol_id'] ?? 0);
            $nombre = trim((string) ($_POST['nombre_rol'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
            $nivel = (string) ($_POST['nivel_acceso'] ?? '');
            $estadoRol = (string) ($_POST['estado_rol'] ?? '');
            if ($rolId < 1 || $nombre === '' || !in_array($nivel, ['administrativo', 'operativo', 'supervision'], true) || !in_array($estadoRol, ['activo', 'inactivo'], true)) {
                throw new RuntimeException('Completa el nombre del rol y selecciona un nivel y estado válidos.');
            }
            $actualizar = $conexion_seguridad->prepare('UPDATE erp_seguridad.roles SET nombre = ?, descripcion = ?, nivel_acceso = ?, estado = ? WHERE id = ?');
            $actualizar->execute([$nombre, $descripcion !== '' ? $descripcion : null, $nivel, $estadoRol, $rolId]);
            $_SESSION['flash_rbac'] = 'Rol actualizado correctamente.';
        } elseif ($accion === 'eliminar_rol') {
            $rolId = (int) ($_POST['rol_id'] ?? 0);
            if (in_array($rolId, $rolesBase, true)) {
                throw new RuntimeException('Los roles base del sistema no se pueden eliminar.');
            }
            $eliminar = $conexion_seguridad->prepare('DELETE FROM erp_seguridad.roles WHERE id = ?');
            $eliminar->execute([$rolId]);
            if (!$eliminar->rowCount()) {
                throw new RuntimeException('El rol personalizado ya no existe.');
            }
            $_SESSION['flash_rbac'] = 'Rol eliminado correctamente.';
        } elseif ($accion === 'guardar_permisos') {
            if (!$esAdministrador) {
                http_response_code(403);
                throw new RuntimeException('Solo el Administrador puede cambiar la matriz de permisos.');
            }
            $rolesDb = $conexion_seguridad->query('SELECT id FROM erp_seguridad.roles')->fetchAll(PDO::FETCH_COLUMN);
            $modulosDb = $conexion_seguridad->query('SELECT id FROM erp_seguridad.modulos')->fetchAll(PDO::FETCH_COLUMN);
            $permisosPost = $_POST['permisos'] ?? [];
            $guardarPermiso = $conexion_seguridad->prepare('INSERT INTO erp_seguridad.r_modulo_rol (rol_id, modulo_id, habilitado) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE habilitado = VALUES(habilitado)');
            $conexion_seguridad->beginTransaction();
            foreach ($rolesDb as $rolId) {
                foreach ($modulosDb as $moduloId) {
                    $permitido = isset($permisosPost[$rolId][$moduloId]) ? 1 : 0;
                    $guardarPermiso->execute([(int) $rolId, (int) $moduloId, $permitido]);
                }
            }
            $conexion_seguridad->commit();
            registrarLog($conexion_seguridad, $usuarioAuditoria, 'Actualizar permisos', 'Se guardó la matriz de permisos por rol.');
            $_SESSION['flash_rbac'] = 'Matriz de permisos guardada correctamente.';
        } else {
            throw new RuntimeException('La acción solicitada no es válida.');
        }

        header('Location: rbac.php');
        exit();
    } catch (Throwable $exception) {
        if ($conexion_seguridad->inTransaction()) {
            $conexion_seguridad->rollBack();
        }
        $error = $exception instanceof PDOException && (string) $exception->getCode() === '23000'
            ? 'El nombre de usuario o rol ya está registrado, o el registro aún está asignado.'
            : $exception->getMessage();
    }
}

$flash = $_SESSION['flash_rbac'] ?? '';
$flashError = $_SESSION['flash_rbac_error'] ?? '';
unset($_SESSION['flash_rbac']);
unset($_SESSION['flash_rbac_error']);
$roles = $conexion_seguridad->query('SELECT id, nombre, descripcion, nivel_acceso, estado FROM erp_seguridad.roles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$usuarios = $conexion_seguridad->query('SELECT usuarios.id, usuarios.username, usuarios.nombre_completo, usuarios.email, usuarios.rol_id, usuarios.estado, roles.nombre AS rol_principal FROM erp_seguridad.usuarios AS usuarios LEFT JOIN erp_seguridad.roles AS roles ON roles.id = usuarios.rol_id ORDER BY usuarios.id')->fetchAll(PDO::FETCH_ASSOC);
$asignaciones = [];
foreach ($conexion_seguridad->query('SELECT usuario_id, rol_id FROM erp_seguridad.r_usuario_rol') as $asignacion) {
    $asignaciones[(int) $asignacion['usuario_id']][] = (int) $asignacion['rol_id'];
}
foreach ($usuarios as &$usuario) {
    $usuarioId = (int) $usuario['id'];
    $usuario['roles_ids'] = $asignaciones[$usuarioId] ?? [(int) $usuario['rol_id']];
}
unset($usuario);
$modulos = $conexion_seguridad->query('SELECT id, nombre AS nombre_modulo, descripcion FROM erp_seguridad.modulos ORDER BY orden, id')->fetchAll(PDO::FETCH_ASSOC);
$accesos = [];
foreach ($conexion_seguridad->query('SELECT rol_id, modulo_id, habilitado FROM erp_seguridad.r_modulo_rol') as $acceso) {
    $accesos[(int) $acceso['rol_id']][(int) $acceso['modulo_id']] = (int) $acceso['habilitado'];
}
foreach ($roles as $rol) {
    foreach ($modulos as $modulo) {
        $rolId = (int) $rol['id'];
        $moduloId = (int) $modulo['id'];
        if (!isset($accesos[$rolId][$moduloId])) {
            $habilitado = $rolId === 1 || ($rolId === 3 && !in_array($modulo['nombre_modulo'], ['Configuración', 'Menú y Combos'], true)) || ($rolId === 2 && in_array($modulo['nombre_modulo'], ['Punto de Venta', 'Recetas', 'Cierre de Caja', 'Reportes de Ventas'], true));
            $insertar = $conexion_seguridad->prepare('INSERT INTO erp_seguridad.r_modulo_rol (rol_id, modulo_id, habilitado) VALUES (?, ?, ?)');
            $insertar->execute([$rolId, $moduloId, $habilitado ? 1 : 0]);
            $accesos[$rolId][$moduloId] = $habilitado ? 1 : 0;
        }
    }
}

$esc = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$usuarioEstaActivo = static fn ($estado): bool => in_array(strtolower(trim((string) $estado)), ['1', 'activo'], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Accesos y Roles | Hijos del Rey C.A.</title>
    <link rel="stylesheet" href="assets/app.css">
    <style>
        .rbac-page { max-width: 1320px; }
        .rbac-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; margin-bottom: 22px; padding-bottom: 18px; border-bottom: 1px solid var(--line); }
        .rbac-eyebrow { margin: 0 0 7px; color: var(--green); font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .rbac-heading h1 { margin: 0; font-size: 26px; }
        .rbac-heading p { margin: 8px 0 0; color: var(--muted); font-size: 13px; }
        .rbac-version { flex: 0 0 auto; color: var(--muted); font-size: 11px; }
        .rbac-overview { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(220px, .7fr); gap: 20px; margin-bottom: 25px; padding: 22px; border: 1px solid #dce7dc; border-left: 4px solid var(--green); border-radius: 7px; background: #f8fbf7; }
        .rbac-overview h2 { margin: 0 0 9px; font-size: 17px; }
        .rbac-overview p { margin: 0; color: #58665d; font-size: 13px; line-height: 1.65; }
        .rbac-flow { display: grid; align-content: center; gap: 8px; padding-left: 18px; border-left: 1px solid #dce7dc; }
        .rbac-flow span { color: var(--muted); font-size: 10px; font-weight: 750; text-transform: uppercase; }
        .rbac-flow strong { color: var(--forest); font-size: 13px; }
        .rbac-section { margin-top: 26px; }
        .rbac-section-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 11px; }
        .rbac-section-heading h2 { margin: 0; font-size: 17px; }
        .rbac-section-heading p { margin: 0; color: var(--muted); font-size: 11px; }
        .rbac-table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 7px; background: var(--paper); }
        .rbac-table { min-width: 720px; }
        .rbac-table th, .rbac-table td { padding: 13px 15px !important; }
        .rbac-table th { white-space: nowrap; }
        .rbac-role-name { display: block; color: var(--ink); font-size: 13px; font-weight: 750; }
        .rbac-role-id { display: block; margin-top: 4px; color: var(--muted); font-size: 10px; }
        .rbac-level { min-width: 150px; color: #37443a; font-weight: 650; }
        .rbac-detail { min-width: 280px; color: var(--muted); line-height: 1.5; }
        .rbac-status { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; font-size: 10px; font-weight: 750; }
        .rbac-status::before { width: 7px; height: 7px; border-radius: 50%; background: currentColor; content: ''; }
        .role-admin { color: var(--green); }
        .role-operator { color: #607e39; }
        .role-supervisor { color: var(--green); }
        .rbac-badge { display: inline-flex; min-height: 25px; align-items: center; gap: 6px; padding: 4px 8px; border: 1px solid transparent; border-radius: 5px; white-space: nowrap; font-size: 10px; font-weight: 750; }
        .rbac-badge span { display: inline-grid; min-width: 12px; place-items: center; font-size: 11px; }
        .badge-allowed { border-color: #d5e9d9; background: var(--green-bg); color: var(--green); }
        .badge-limited { border-color: #e3e9ce; background: #f5f8e9; color: #687b31; }
        .badge-consultation { border-color: #dce7dc; background: #f0f5ef; color: #47644b; }
        .badge-denied { border-color: #e8e9e5; background: #f5f6f3; color: #788078; }
        .rbac-note { display: flex; align-items: flex-start; gap: 10px; margin-top: 13px; padding: 12px 14px; border: 1px solid #dce7dc; border-radius: 6px; background: #f6faf5; color: #526257; font-size: 11px; line-height: 1.55; }
        .rbac-note strong { color: var(--forest); }
        .rbac-header-actions, .rbac-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
        .rbac-flash { margin: 0 0 18px; padding: 12px 14px; border: 1px solid #d5e9d9; border-radius: 6px; background: var(--green-bg); color: var(--green); }
        .rbac-error { border-color: #f0d8d3; background: var(--danger-bg); color: var(--danger); }
        .rbac-table { min-width: 760px; }
        .rbac-users-table { min-width: 850px; }
        .rbac-users-table .rbac-actions { justify-content: flex-end; }
        .rbac-actions { justify-content: flex-start; }
        .rbac-primary-role { display: block; margin-top: 3px; color: var(--muted); font-size: 10px; }
        .rbac-base-label { color: var(--muted); font-size: 10px; font-weight: 700; }
        .rbac-dialog { width: min(100% - 28px, 560px); max-width: 560px; max-height: min(88vh, 760px); padding: 0; overflow: auto; border: 1px solid var(--line); border-radius: 8px; background: var(--paper); color: var(--ink); box-shadow: 0 24px 70px rgba(17, 31, 22, .24); }
        .rbac-dialog::backdrop { background: rgba(19, 34, 26, .55); }
        .rbac-dialog form { display: grid; gap: 18px; padding: 22px; }
        .rbac-dialog h2 { margin: 0; font-size: 18px; }
        .rbac-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .rbac-field { display: grid; align-content: start; gap: 6px; }
        .rbac-field-wide { grid-column: 1 / -1; }
        .rbac-field input, .rbac-field textarea { width: 100%; }
        .rbac-checkbox-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .rbac-checkbox-option { display: flex; min-height: 42px; align-items: center; gap: 9px; padding: 9px 10px; border: 1px solid var(--line); border-radius: 6px; background: #fbfcfa; cursor: pointer; }
        .rbac-checkbox-option input[type="checkbox"], .rbac-permission-control input[type="checkbox"] { width: 17px; height: 17px; flex: 0 0 17px; margin: 0; appearance: auto !important; -webkit-appearance: checkbox !important; accent-color: var(--green); cursor: pointer; pointer-events: auto; }
        .rbac-checkbox-option span { font-size: 12px; font-weight: 650; }
        .rbac-dialog-actions { display: flex; justify-content: flex-end; gap: 8px; padding-top: 2px; }
        .rbac-password-wrap { display: flex; align-items: stretch; gap: 7px; }
        .rbac-password-wrap input { flex: 1; min-width: 0; }
        .rbac-password-toggle { display: inline-grid; width: 42px; min-width: 42px; min-height: 42px; place-items: center; padding: 8px !important; border: 1px solid var(--line) !important; background: #f5f7f4 !important; color: var(--forest) !important; }
        .rbac-password-toggle:hover { background: #e9eee8 !important; }
        .rbac-password-toggle svg { width: 18px; height: 18px; }
        .rbac-status-inactive { color: #788078; }
        .rbac-status-inactive::before { background: #9aa19b; }
        .rbac-permission-control { display: inline-flex; min-height: 34px; align-items: center; gap: 8px; cursor: pointer; }
        .rbac-permission-state { min-width: 82px; color: var(--muted); font-size: 10px; font-weight: 750; }
        .rbac-permission-state.is-allowed { color: var(--green); }
        .rbac-permission-save { display: flex; justify-content: flex-end; margin-top: 14px; }
        @media (max-width: 760px) {
            .rbac-heading { align-items: flex-start; flex-direction: column; }
            .rbac-heading h1 { font-size: 22px; }
            .rbac-overview { grid-template-columns: 1fr; }
            .rbac-flow { padding: 13px 0 0; border-top: 1px solid #dce7dc; border-left: 0; }
            .rbac-section-heading { align-items: flex-start; flex-direction: column; }
            .rbac-form-grid, .rbac-checkbox-list { grid-template-columns: 1fr; }
            .rbac-field-wide { grid-column: auto; }
            .rbac-dialog form { padding: 18px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/navegacion.php'; ?>
<main class="container rbac-page">
    <header class="rbac-heading">
        <div>
            <p class="rbac-eyebrow">Seguridad del sistema</p>
            <h1 class="page-title">Control de Accesos y Roles</h1>
            <p class="page-subtitle">Modelo de autorización aplicado a la operación del restaurante.</p>
        </div>
        <div class="rbac-header-actions">
            <button type="button" class="btn btn-primary" data-open-modal="nuevo-usuario-dialog">+ Nuevo usuario</button>
            <button type="button" class="btn btn-secondary" data-open-modal="nuevo-rol-dialog">+ Nuevo rol</button>
        </div>
    </header>

    <?php if ($flash !== ''): ?><div class="rbac-flash" role="status"><?= $esc($flash) ?></div><?php endif; ?>
    <?php if ($flashError !== ''): ?><div class="rbac-flash rbac-error" role="alert"><?= $esc($flashError) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="rbac-flash rbac-error" role="alert"><?= $esc($error) ?></div><?php endif; ?>

    <section class="rbac-overview" aria-labelledby="rbac-model-title">
        <div>
            <h2 id="rbac-model-title">Acceso basado en roles</h2>
            <p>La autenticación valida las credenciales mediante PHP PDO y conserva en la sesión los roles asignados al usuario. Antes de ejecutar cada módulo, <code>requerirRol()</code> compara esos identificadores con los roles permitidos. Así, las acciones administrativas y operativas se separan en el servidor y no dependen únicamente de ocultar enlaces en pantalla.</p>
        </div>
        <div class="rbac-flow" aria-label="Flujo de autorización">
            <span>Flujo de autorización</span>
            <strong>Usuario → Sesión → Rol → Módulo</strong>
            <span>Control aplicado al acceder a cada página</span>
        </div>
    </section>

    <section class="rbac-section" aria-labelledby="users-title">
        <div class="rbac-section-heading">
            <h2 id="users-title">Usuarios del sistema</h2>
            <p><?= count($usuarios) ?> usuario(s) registrado(s)</p>
        </div>
        <div class="rbac-table-wrap">
            <table class="rbac-table rbac-users-table">
                <thead><tr><th>Nombre</th><th>Usuario</th><th>Correo</th><th>Rol principal</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><span class="rbac-role-name"><?= $esc($usuario['nombre_completo'] ?: $usuario['username']) ?></span></td>
                            <td><?= $esc($usuario['username']) ?></td>
                            <td><?= $esc($usuario['email'] ?: '—') ?></td>
                            <td><?= $esc($usuario['rol_principal'] ?: 'Sin rol') ?></td>
                            <?php $activo = $usuarioEstaActivo($usuario['estado']); ?>
                            <td><span class="rbac-status <?= $activo ? 'role-admin' : 'rbac-status-inactive' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
                            <td>
                                <div class="rbac-actions">
                                    <button type="button" class="btn btn-secondary" data-open-modal="modal-editar-usuario-<?= (int) $usuario['id'] ?>">Editar datos</button>
                                    <button type="button" class="btn btn-secondary" data-open-modal="editar-usuario-<?= (int) $usuario['id'] ?>">Editar roles</button>
                                    <form method="post" onsubmit="return confirm('¿Deseas eliminar este usuario?');">
                                        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                                        <input type="hidden" name="accion" value="eliminar_usuario">
                                        <input type="hidden" name="usuario_id" value="<?= (int) $usuario['id'] ?>">
                                        <button type="submit" class="btn btn-danger">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$usuarios): ?><tr><td colspan="6">No hay usuarios registrados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php foreach ($usuarios as $usuario): $usuarioId = (int) $usuario['id']; ?>
        <dialog class="rbac-dialog" id="modal-editar-usuario-<?= $usuarioId ?>" aria-labelledby="modal-editar-usuario-title-<?= $usuarioId ?>">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                <input type="hidden" name="accion" value="actualizar_datos_usuario">
                <input type="hidden" name="usuario_id" value="<?= $usuarioId ?>">
                <h2 id="modal-editar-usuario-title-<?= $usuarioId ?>">Editar datos: <?= $esc($usuario['nombre_completo'] ?: $usuario['username']) ?></h2>
                <div class="rbac-form-grid">
                    <label class="rbac-field rbac-field-wide" for="editar-nombre-usuario-<?= $usuarioId ?>"><span>Nombre completo</span><input id="editar-nombre-usuario-<?= $usuarioId ?>" name="nombre_completo" type="text" maxlength="120" autocomplete="name" value="<?= $esc($usuario['nombre_completo']) ?>" required></label>
                    <label class="rbac-field" for="editar-username-<?= $usuarioId ?>"><span>Usuario de acceso</span><input id="editar-username-<?= $usuarioId ?>" name="username" type="text" maxlength="50" autocomplete="username" value="<?= $esc($usuario['username']) ?>" required></label>
                    <label class="rbac-field" for="editar-email-usuario-<?= $usuarioId ?>"><span>Correo electrónico</span><input id="editar-email-usuario-<?= $usuarioId ?>" name="email" type="email" maxlength="100" autocomplete="email" value="<?= $esc($usuario['email']) ?>"></label>
                    <div class="rbac-field rbac-field-wide">
                        <label for="editar-clave-usuario-<?= $usuarioId ?>"><span>Nueva clave (opcional)</span></label>
                        <div class="rbac-password-wrap"><input id="editar-clave-usuario-<?= $usuarioId ?>" name="password" type="password" minlength="8" autocomplete="new-password"><button type="button" class="rbac-password-toggle" data-toggle-password="editar-clave-usuario-<?= $usuarioId ?>" aria-label="Mostrar nueva clave" title="Mostrar u ocultar clave"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
                        <small>Déjala en blanco para conservar la clave actual.</small>
                    </div>
                    <div class="rbac-field rbac-field-wide">
                        <label for="editar-confirmar-clave-usuario-<?= $usuarioId ?>"><span>Confirmar nueva clave</span></label>
                        <div class="rbac-password-wrap"><input id="editar-confirmar-clave-usuario-<?= $usuarioId ?>" name="confirmar_password" type="password" minlength="8" autocomplete="new-password"><button type="button" class="rbac-password-toggle" data-toggle-password="editar-confirmar-clave-usuario-<?= $usuarioId ?>" aria-label="Mostrar confirmación de nueva clave" title="Mostrar u ocultar clave"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
                    </div>
                </div>
                <div class="rbac-dialog-actions"><button type="button" class="btn btn-secondary" data-close-modal>Cancelar</button><button type="submit" class="btn btn-primary">Guardar datos</button></div>
            </form>
        </dialog>
    <?php endforeach; ?>

    <dialog class="rbac-dialog" id="nuevo-usuario-dialog" aria-labelledby="nuevo-usuario-title">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
            <input type="hidden" name="accion" value="crear_usuario">
            <h2 id="nuevo-usuario-title">Nuevo usuario</h2>
            <div class="rbac-form-grid">
                <label class="rbac-field rbac-field-wide" for="nuevo-nombre"><span>Nombre completo</span><input id="nuevo-nombre" name="nombre_completo" type="text" maxlength="120" autocomplete="name" required></label>
                <label class="rbac-field" for="nuevo-username"><span>Usuario</span><input id="nuevo-username" name="username" type="text" maxlength="50" autocomplete="username" required></label>
                <label class="rbac-field" for="nuevo-email"><span>Correo</span><input id="nuevo-email" name="email" type="email" maxlength="100" autocomplete="email"></label>
                <div class="rbac-field rbac-field-wide">
                    <label for="nuevo-password"><span>Clave</span></label>
                    <div class="rbac-password-wrap"><input id="nuevo-password" name="password" type="password" minlength="8" autocomplete="new-password" required><button type="button" class="rbac-password-toggle" data-toggle-password="nuevo-password" aria-label="Mostrar clave" title="Mostrar u ocultar clave"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
                </div>
                <div class="rbac-field rbac-field-wide">
                    <label for="nuevo-confirmar-password"><span>Confirmar contraseña</span></label>
                    <div class="rbac-password-wrap"><input id="nuevo-confirmar-password" name="confirmar_password" type="password" minlength="8" autocomplete="new-password" required><button type="button" class="rbac-password-toggle" data-toggle-password="nuevo-confirmar-password" aria-label="Mostrar confirmación de contraseña" title="Mostrar u ocultar clave"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
                </div>
            </div>
            <fieldset class="rbac-field">
                <legend>Roles asignados</legend>
                <div class="rbac-checkbox-list">
                    <?php foreach ($roles as $rol): $rolId = (int) $rol['id']; ?>
                        <label class="rbac-checkbox-option" for="nuevo-rol-<?= $rolId ?>"><input id="nuevo-rol-<?= $rolId ?>" name="roles[]" type="checkbox" value="<?= $rolId ?>"><span><?= $esc($rol['nombre']) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="rbac-dialog-actions"><button type="button" class="btn btn-secondary" data-close-modal>Cancelar</button><button type="submit" class="btn btn-primary">Crear usuario</button></div>
        </form>
    </dialog>

    <?php foreach ($usuarios as $usuario): ?>
        <dialog class="rbac-dialog" id="editar-usuario-<?= (int) $usuario['id'] ?>" aria-labelledby="editar-usuario-title-<?= (int) $usuario['id'] ?>">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                <input type="hidden" name="accion" value="guardar_roles_usuario">
                <input type="hidden" name="usuario_id" value="<?= (int) $usuario['id'] ?>">
                <h2 id="editar-usuario-title-<?= (int) $usuario['id'] ?>">Asignar Roles a <?= $esc($usuario['nombre_completo'] ?: $usuario['username']) ?></h2>
                <fieldset class="rbac-field">
                    <legend>Selecciona uno o más roles</legend>
                    <div class="rbac-checkbox-list">
                        <?php foreach ($roles as $rol): $rolId = (int) $rol['id']; $checkboxId = 'usuario-' . (int) $usuario['id'] . '-rol-' . $rolId; ?>
                            <label class="rbac-checkbox-option" for="<?= $esc($checkboxId) ?>"><input id="<?= $esc($checkboxId) ?>" name="roles[]" type="checkbox" value="<?= $rolId ?>" <?= in_array($rolId, $usuario['roles_ids'], true) ? 'checked' : '' ?>><span><?= $esc($rol['nombre']) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <small class="rbac-help">Los roles seleccionados otorgan permisos acumulativos al usuario.</small>
                </fieldset>
                <label class="rbac-field" for="estado-usuario-<?= (int) $usuario['id'] ?>">
                    <span>Estado del usuario</span>
                    <select id="estado-usuario-<?= (int) $usuario['id'] ?>" name="estado_usuario" required>
                        <option value="activo" <?= $usuario['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= $usuario['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </label>
                <div class="rbac-dialog-actions"><button type="button" class="btn btn-secondary" data-close-modal>Cancelar</button><button type="submit" class="btn btn-primary">Guardar Roles</button></div>
            </form>
        </dialog>
    <?php endforeach; ?>

    <section class="rbac-section" aria-labelledby="roles-title">
        <div class="rbac-section-heading">
            <h2 id="roles-title">Roles del restaurante</h2>
            <p><?= count($roles) ?> rol(es) configurado(s)</p>
        </div>
        <div class="rbac-table-wrap">
            <table class="rbac-table">
                <thead>
                    <tr><th>Rol</th><th>Nivel de acceso</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $rol): ?>
                        <?php $rolId = (int) $rol['id']; $esRolBase = in_array($rolId, [1, 2, 3], true); ?>
                        <tr>
                            <td><span class="rbac-role-name"><?= $esc($rol['nombre']) ?></span><span class="rbac-role-id">Rol <?= $rolId ?></span></td>
                            <td class="rbac-level"><?= $esc($rol['nivel_acceso'] ?: ($rolId === 1 ? 'Control total del sistema' : 'Acceso definido por permisos')) ?></td>
                            <td class="rbac-detail"><?= $esc($rol['descripcion'] ?: 'Sin descripción') ?></td>
                            <td><span class="rbac-status <?= $rol['estado'] === 'activo' ? 'role-admin' : 'role-operator' ?>"><?= $esc(ucfirst($rol['estado'])) ?></span></td>
                            <td>
                                <div class="rbac-actions">
                                    <button type="button" class="btn btn-secondary" data-open-modal="editar-rol-<?= $rolId ?>">Editar</button>
                                    <?php if ($esRolBase): ?>
                                        <span class="rbac-base-label">Rol base</span>
                                    <?php else: ?>
                                        <form method="post" onsubmit="return confirm('¿Deseas eliminar este rol?');">
                                            <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="eliminar_rol">
                                            <input type="hidden" name="rol_id" value="<?= $rolId ?>">
                                            <button type="submit" class="btn btn-danger">Eliminar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$roles): ?><tr><td colspan="5">No hay roles configurados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <dialog class="rbac-dialog" id="nuevo-rol-dialog" aria-labelledby="nuevo-rol-title">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
            <input type="hidden" name="accion" value="crear_rol">
            <h2 id="nuevo-rol-title">Nuevo rol</h2>
            <label class="rbac-field" for="nuevo-nombre-rol"><span>Nombre del rol</span><input id="nuevo-nombre-rol" name="nombre_rol" type="text" maxlength="50" required></label>
            <label class="rbac-field" for="nuevo-nivel-rol">
                <span>Nivel de acceso</span>
                <select id="nuevo-nivel-rol" name="nivel_acceso" required>
                    <option value="administrativo">Administrativo (Control total del sistema)</option>
                    <option value="supervision">Supervisión (Supervisión de operaciones e inventario)</option>
                    <option value="operativo">Operativo (Operación de ventas y POS)</option>
                </select>
            </label>
            <label class="rbac-field" for="nuevo-descripcion-rol"><span>Descripción</span><textarea id="nuevo-descripcion-rol" name="descripcion" maxlength="255" rows="3"></textarea></label>
            <div class="rbac-dialog-actions"><button type="button" class="btn btn-secondary" data-close-modal>Cancelar</button><button type="submit" class="btn btn-primary">Crear rol</button></div>
        </form>
    </dialog>

    <?php foreach ($roles as $rol): $rolId = (int) $rol['id']; ?>
        <dialog class="rbac-dialog" id="editar-rol-<?= $rolId ?>" aria-labelledby="editar-rol-title-<?= $rolId ?>">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                <input type="hidden" name="accion" value="actualizar_rol">
                <input type="hidden" name="rol_id" value="<?= $rolId ?>">
                <h2 id="editar-rol-title-<?= $rolId ?>">Editar Rol: <?= $esc($rol['nombre']) ?></h2>
                <label class="rbac-field" for="editar-nombre-rol-<?= $rolId ?>"><span>Nombre del rol</span><input id="editar-nombre-rol-<?= $rolId ?>" name="nombre_rol" type="text" maxlength="50" value="<?= $esc($rol['nombre']) ?>" required></label>
                <label class="rbac-field" for="editar-descripcion-rol-<?= $rolId ?>"><span>Descripción</span><textarea id="editar-descripcion-rol-<?= $rolId ?>" name="descripcion" maxlength="255" rows="3"><?= $esc($rol['descripcion']) ?></textarea></label>
                <?php
                    $nivelActual = strtolower(trim((string) $rol['nivel_acceso']));
                    if (!in_array($nivelActual, ['administrativo', 'operativo', 'supervision'], true)) {
                        $nivelActual = $rolId === 1 ? 'administrativo' : ($rolId === 3 ? 'supervision' : 'operativo');
                    }
                ?>
                <label class="rbac-field" for="editar-nivel-rol-<?= $rolId ?>">
                    <span>Nivel de acceso</span>
                    <select id="editar-nivel-rol-<?= $rolId ?>" name="nivel_acceso" required>
                        <option value="administrativo" <?= $nivelActual === 'administrativo' ? 'selected' : '' ?>>Administrativo (Control total del sistema)</option>
                        <option value="supervision" <?= $nivelActual === 'supervision' ? 'selected' : '' ?>>Supervisión (Supervisión de operaciones e inventario)</option>
                        <option value="operativo" <?= $nivelActual === 'operativo' ? 'selected' : '' ?>>Operativo (Operación de ventas y POS)</option>
                    </select>
                </label>
                <label class="rbac-field" for="editar-estado-rol-<?= $rolId ?>">
                    <span>Estado del rol</span>
                    <select id="editar-estado-rol-<?= $rolId ?>" name="estado_rol" required>
                        <option value="activo" <?= $rol['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= $rol['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </label>
                <div class="rbac-dialog-actions"><button type="button" class="btn btn-secondary" data-close-modal>Cancelar</button><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
            </form>
        </dialog>
    <?php endforeach; ?>

    <section class="rbac-section" aria-labelledby="permissions-title">
        <div class="rbac-section-heading">
            <h2 id="permissions-title">Matriz de Permisos</h2>
            <p>Permisos activos por rol<?= $esAdministrador ? '' : ' (solo lectura)' ?></p>
        </div>
        <form method="post" id="matriz-permisos-form">
            <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
            <input type="hidden" name="accion" value="guardar_permisos">
            <div class="rbac-table-wrap">
                <table class="rbac-table">
                    <thead><tr><th>Módulo</th><?php foreach ($roles as $rol): ?><th><?= $esc($rol['nombre']) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                        <?php foreach ($modulos as $modulo): $moduloId = (int) $modulo['id']; ?>
                            <tr>
                                <td><span class="rbac-role-name"><?= $esc($modulo['nombre_modulo']) ?></span><span class="rbac-role-id"><?= $esc($modulo['descripcion']) ?></span></td>
                                <?php foreach ($roles as $rol): $rolId = (int) $rol['id']; $permitido = !empty($accesos[$rolId][$moduloId]); $permisoId = 'permiso-' . $rolId . '-' . $moduloId; ?>
                                    <td>
                                        <label class="rbac-permission-control" for="<?= $permisoId ?>">
                                            <input id="<?= $permisoId ?>" name="permisos[<?= $rolId ?>][<?= $moduloId ?>]" type="checkbox" value="1" <?= $permitido ? 'checked' : '' ?> <?= $esAdministrador ? '' : 'disabled' ?>>
                                            <span class="rbac-permission-state <?= $permitido ? 'is-allowed' : '' ?>"><?= $permitido ? 'Permitido' : 'Sin acceso' ?></span>
                                        </label>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($esAdministrador): ?><div class="rbac-permission-save"><button type="submit" class="btn btn-primary">Guardar permisos</button></div><?php endif; ?>
        </form>
        <div class="rbac-note">
            <span aria-hidden="true">!</span>
            <div><strong>Alcance:</strong> La matriz registra el acceso por módulo y rol. Los cambios se validan también en el servidor y solo pueden guardarse con una sesión de Administrador (rol 1).</div>
        </div>
    </section>
</main>
<script>
    document.querySelectorAll('[data-open-modal]').forEach((button) => {
        button.addEventListener('click', () => document.getElementById(button.dataset.openModal)?.showModal());
    });
    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
    document.querySelectorAll('.rbac-dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });
    document.querySelectorAll('.rbac-permission-control input').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const state = checkbox.parentElement.querySelector('.rbac-permission-state');
            state.textContent = checkbox.checked ? 'Permitido' : 'Sin acceso';
            state.classList.toggle('is-allowed', checkbox.checked);
        });
    });
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.togglePassword);
            if (!input) return;
            const mostrar = input.type === 'password';
            input.type = mostrar ? 'text' : 'password';
            button.setAttribute('aria-label', `${mostrar ? 'Ocultar' : 'Mostrar'} contraseña`);
        });
    });
</script>
</body>
</html>