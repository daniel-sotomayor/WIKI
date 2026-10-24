<?php
// ============================================================
//  CONFIGURACIÓN DEL SITIO GUARDADA EN BASE DE DATOS
//  Archivo: backend/lib/config_sitio.php
//
//  Ajustes que se cambian desde el panel (sin tocar código):
//  por ejemplo el modo mantenimiento. Se guardan en la tabla
//  config_sitio (clave/valor) y se cachean por petición.
//
//  Todas las funciones toleran que la tabla no exista todavía
//  (instalaciones viejas): devuelven el defecto en vez de romper
//  la página.
// ============================================================

/**
 * Lee un valor de configuración. Si la clave no está, devuelve $defecto.
 */
function configObtener(string $clave, string $defecto = ''): string {
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        try {
            $filas = getDB()->query('SELECT clave, valor FROM config_sitio')->fetchAll();
            foreach ($filas as $f) {
                $cache[$f['clave']] = (string) $f['valor'];
            }
        } catch (Throwable $e) {
            error_log('[config_sitio] No se pudo leer config_sitio: ' . $e->getMessage());
        }
    }

    return $cache[$clave] ?? $defecto;
}

/**
 * Guarda (o reemplaza) un valor de configuración.
 *
 * @return bool true si quedó grabado
 */
function configGuardar(string $clave, string $valor): bool {
    if (!preg_match('/^[a-z0-9_.-]{1,60}$/', $clave)) {
        return false;
    }
    try {
        getDB()->prepare(
            'INSERT INTO config_sitio (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        )->execute([$clave, $valor]);
        return true;
    } catch (Throwable $e) {
        error_log('[config_sitio] No se pudo guardar ' . $clave . ': ' . $e->getMessage());
        return false;
    }
}

/** ¿Está activo el modo mantenimiento? (sólo los usuarios con sesión entran) */
function modoMantenimiento(): bool {
    return configObtener('mantenimiento', '0') === '1';
}
