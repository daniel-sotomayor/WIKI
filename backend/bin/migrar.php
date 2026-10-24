<?php
// ============================================================
//  UTILERÍA DE MIGRACIONES DE ESQUEMA
//  Archivo: backend/bin/migrar.php
//
//  Uso (sólo línea de comandos):
//
//      php backend/bin/migrar.php --estado    # qué hay aplicado y qué falta
//      php backend/bin/migrar.php --aplicar   # aplica las pendientes
//
//  Las migraciones viven en backend/database/migraciones/NNNN_*.sql y
//  se aplican en orden alfabético. Cada archivo deja una fila en la
//  tabla `migraciones` con su checksum SHA-256: si alguien edita un
//  archivo ya aplicado, el comando lo avisa (no lo vuelve a ejecutar).
//
//  Nota: los archivos deben usar sintaxis idempotente (IF NOT EXISTS,
//  CREATE OR REPLACE, INSERT … WHERE NOT EXISTS) porque MySQL no
//  envuelve el DDL en transacciones.
// ============================================================

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Este script se ejecuta desde la línea de comandos.\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

const CARPETA_MIGRACIONES = __DIR__ . '/../database/migraciones';

$aplicar = in_array('--aplicar', $argv, true);
$estado  = in_array('--estado', $argv, true) || !$aplicar;

// ── Conexión y tabla de registro ────────────────────────────
try {
    $db = getDB();
} catch (Throwable $e) {
    exit("No se pudo conectar a la base de datos: " . $e->getMessage() . "\n"
       . "Revisá DB_HOST/DB_USER/DB_PASS/DB_NAME en el entorno (ver docs/OPERACION.md).\n");
}

$db->exec(
    'CREATE TABLE IF NOT EXISTS `migraciones` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `archivo`     VARCHAR(120) NOT NULL,
        `checksum`    CHAR(64)     NOT NULL,
        `aplicado_en` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_migraciones_archivo` (`archivo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$aplicadas = [];
foreach ($db->query('SELECT archivo, checksum, aplicado_en FROM migraciones')->fetchAll() as $f) {
    $aplicadas[$f['archivo']] = $f;
}

$archivos = glob(CARPETA_MIGRACIONES . '/*.sql') ?: [];
sort($archivos);

if (!$archivos) {
    exit("No hay archivos de migración en " . CARPETA_MIGRACIONES . "\n");
}

/**
 * Parte un archivo .sql en sentencias individuales.
 * Los archivos de este proyecto no usan procedimientos almacenados,
 * así que basta con cortar en cada ';' que cierra sentencia.
 */
function partirSQL(string $sql): array {
    $limpio = '';
    foreach (explode("\n", $sql) as $linea) {
        if (preg_match('/^\s*--/', $linea)) {
            continue;                      // comentario de línea
        }
        $limpio .= $linea . "\n";
    }
    $sentencias = [];
    foreach (explode(';', $limpio) as $trozo) {
        $trozo = trim($trozo);
        if ($trozo !== '') {
            $sentencias[] = $trozo;
        }
    }
    return $sentencias;
}

// ── Estado ──────────────────────────────────────────────────
if ($estado) {
    echo "Base de datos: " . DB_NAME . " @ " . DB_HOST . "\n\n";
    printf("%-8s %-40s %s\n", 'ESTADO', 'ARCHIVO', 'APLICADO');
    printf("%s\n", str_repeat('-', 78));
    $pendientes = 0;
    foreach ($archivos as $ruta) {
        $nombre = basename($ruta);
        $hash   = hash_file('sha256', $ruta);
        if (isset($aplicadas[$nombre])) {
            $alerta = $aplicadas[$nombre]['aplicado_en'];
            if (!hash_equals($aplicadas[$nombre]['checksum'], $hash)) {
                $alerta .= '  ** MODIFICADO DESPUÉS DE APLICAR **';
            }
            printf("%-8s %-40s %s\n", 'OK', $nombre, $alerta);
        } else {
            $pendientes++;
            printf("%-8s %-40s %s\n", 'PENDIENTE', $nombre, '—');
        }
    }
    echo "\n" . ($pendientes === 0
        ? "La base está al día.\n"
        : "$pendientes migración(es) pendiente(s): corré --aplicar\n");
    exit(0);
}

// ── Aplicación ──────────────────────────────────────────────
$aplicadasAhora = 0;
foreach ($archivos as $ruta) {
    $nombre = basename($ruta);
    $hash   = hash_file('sha256', $ruta);

    if (isset($aplicadas[$nombre])) {
        if (!hash_equals($aplicadas[$nombre]['checksum'], $hash)) {
            echo "!! $nombre quedó MODIFICADA después de aplicarse; no se vuelve a ejecutar.\n";
        }
        continue;
    }

    echo "→ Aplicando $nombre … ";
    $sentencias = partirSQL((string) file_get_contents($ruta));

    try {
        foreach ($sentencias as $sql) {
            $db->exec($sql);
        }
        $db->prepare('INSERT INTO migraciones (archivo, checksum) VALUES (?, ?)')
           ->execute([$nombre, $hash]);
        echo "OK (" . count($sentencias) . " sentencias)\n";
        $aplicadasAhora++;
    } catch (Throwable $e) {
        echo "ERROR\n";
        exit("Falló la migración $nombre: " . $e->getMessage() . "\n"
           . "La migración no quedó registrada; corregí el archivo y volvé a correr.\n");
    }
}

echo $aplicadasAhora === 0
    ? "No había migraciones pendientes.\n"
    : "Se aplicaron $aplicadasAhora migración(es).\n";
exit(0);
