<?php
function obtenerConfiguracionSistema(PDO $conexion) {
    $conexion->exec("CREATE TABLE IF NOT EXISTS configuracion_sistema (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        nombre_comercial VARCHAR(150) NOT NULL DEFAULT 'Hijos del Rey C.A.',
        direccion VARCHAR(255) NOT NULL DEFAULT '',
        rif VARCHAR(30) NOT NULL DEFAULT '',
        telefono VARCHAR(40) NOT NULL DEFAULT '',
        tasa_bcv DECIMAL(12,4) NOT NULL DEFAULT 0,
        ticket_encabezado VARCHAR(255) NOT NULL DEFAULT '',
        ticket_pie TEXT NOT NULL,
        ultimo_respaldo DATETIME NULL,
        actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $stmt = $conexion->prepare('INSERT IGNORE INTO configuracion_sistema (id, ticket_pie) VALUES (1, ?)');
    $stmt->execute(['']);
    $conexion->exec("UPDATE configuracion_sistema SET nombre_comercial = 'Hijos del Rey C.A.' WHERE nombre_comercial IN ('Restaurante Hijos del Rey', 'Los Hijos del Rey', 'Hijos del Rey')");
    return $conexion->query('SELECT * FROM configuracion_sistema WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
}
