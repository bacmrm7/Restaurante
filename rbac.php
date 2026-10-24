<?php
require_once __DIR__ . '/includes/seguridad.php';
requerirRol([1]);
include __DIR__ . '/config/conexion.php';

$roles = [
    [
        'nombre' => 'Administrador',
        'id' => 'Rol 1',
        'nivel' => 'Control total del sistema',
        'detalle' => 'Gestiona inventario, catálogo, recetas, ventas, caja y configuración.',
        'estado' => 'Activo',
        'tipo' => 'role-admin'
    ],
    [
        'nombre' => 'Supervisor',
        'id' => 'Rol 3',
        'nivel' => 'Operación y supervisión',
        'detalle' => 'Opera POS y recetas, consulta inventario, cierres diarios y reportes; no administra configuración.',
        'estado' => 'Activo',
        'tipo' => 'role-supervisor'
    ],
    [
        'nombre' => 'Cajero',
        'id' => 'Rol 2',
        'nivel' => 'Operación de ventas',
        'detalle' => 'Accede al POS, recetas y cierre diario según los guards actuales.',
        'estado' => 'Activo',
        'tipo' => 'role-operator'
    ]
];

$permisos = [
    ['modulo' => 'Inventario', 'admin' => 'allow', 'supervisor' => 'allow', 'cajero' => 'deny'],
    ['modulo' => 'Punto de Venta', 'admin' => 'allow', 'supervisor' => 'allow', 'cajero' => 'allow'],
    ['modulo' => 'Recetas', 'admin' => 'allow', 'supervisor' => 'allow', 'cajero' => 'allow'],
    ['modulo' => 'Cierre de Caja', 'admin' => 'allow', 'supervisor' => 'consultation', 'cajero' => 'limited'],
    ['modulo' => 'Reportes de Ventas', 'admin' => 'allow', 'supervisor' => 'allow', 'cajero' => 'allow'],
    ['modulo' => 'Configuración', 'admin' => 'allow', 'supervisor' => 'deny', 'cajero' => 'deny']
];

$badge = static function (string $estado): string {
    $labels = [
        'allow' => ['Permitido', 'badge-allowed', '✓'],
        'consultation' => ['Consulta', 'badge-consultation', '◉'],
        'limited' => ['Diario', 'badge-limited', '◷'],
        'deny' => ['Sin acceso', 'badge-denied', '−'],
    ];
    [$texto, $clase, $icono] = $labels[$estado];
    return '<span class="rbac-badge ' . $clase . '"><span aria-hidden="true">' . $icono . '</span>' . $texto . '</span>';
};
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
        @media (max-width: 760px) {
            .rbac-heading { align-items: flex-start; flex-direction: column; }
            .rbac-heading h1 { font-size: 22px; }
            .rbac-overview { grid-template-columns: 1fr; }
            .rbac-flow { padding: 13px 0 0; border-top: 1px solid #dce7dc; border-left: 0; }
            .rbac-section-heading { align-items: flex-start; flex-direction: column; }
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
        <span class="rbac-version">Hijos del Rey C.A. · Administración</span>
    </header>

    <section class="rbac-overview" aria-labelledby="rbac-model-title">
        <div>
            <h2 id="rbac-model-title">Acceso basado en roles</h2>
            <p>La autenticación valida las credenciales mediante PHP PDO y conserva el identificador del rol en la sesión. Antes de ejecutar cada módulo, <code>requerirRol()</code> compara ese identificador con los roles permitidos. Así, las acciones administrativas y operativas se separan en el servidor y no dependen únicamente de ocultar enlaces en pantalla.</p>
        </div>
        <div class="rbac-flow" aria-label="Flujo de autorización">
            <span>Flujo de autorización</span>
            <strong>Usuario → Sesión → Rol → Módulo</strong>
            <span>Control aplicado al acceder a cada página</span>
        </div>
    </section>

    <section class="rbac-section" aria-labelledby="roles-title">
        <div class="rbac-section-heading">
            <h2 id="roles-title">Roles del restaurante</h2>
            <p>Perfiles y alcance de acceso</p>
        </div>
        <div class="rbac-table-wrap">
            <table class="rbac-table">
                <thead>
                    <tr><th>Rol</th><th>Nivel de acceso</th><th>Descripción</th><th>Estado</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $rol): ?>
                        <tr>
                            <td><span class="rbac-role-name"><?= htmlspecialchars($rol['nombre']) ?></span><span class="rbac-role-id"><?= htmlspecialchars($rol['id']) ?></span></td>
                            <td class="rbac-level"><?= htmlspecialchars($rol['nivel']) ?></td>
                            <td class="rbac-detail"><?= htmlspecialchars($rol['detalle']) ?></td>
                            <td><span class="rbac-status <?= htmlspecialchars($rol['tipo']) ?>"><?= htmlspecialchars($rol['estado']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rbac-section" aria-labelledby="permissions-title">
        <div class="rbac-section-heading">
            <h2 id="permissions-title">Matriz de Permisos</h2>
            <p>Permisos activos por rol</p>
        </div>
        <div class="rbac-table-wrap">
            <table class="rbac-table">
                <thead>
                    <tr><th>Módulo</th><th>Administrador</th><th>Supervisor</th><th>Cajero</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($permisos as $permiso): ?>
                        <tr>
                            <td><span class="rbac-role-name"><?= htmlspecialchars($permiso['modulo']) ?></span></td>
                            <td><?= $badge($permiso['admin']) ?></td>
                            <td><?= $badge($permiso['supervisor']) ?></td>
                            <td><?= $badge($permiso['cajero']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="rbac-note">
            <span aria-hidden="true">!</span>
            <div><strong>Alcance:</strong> Supervisor (Rol 3) puede operar POS y recetas, consultar inventario, reportes y cierres diarios. La configuración permanece exclusiva del Administrador.</div>
        </div>
    </section>
</main>
</body>
</html>