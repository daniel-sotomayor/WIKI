<?php
// ============================================================
//  TelecomWiki — DESTRUCCIÓN DE EMERGENCIA (remota)
//  Archivo: .ds.php  (en la RAÍZ del proyecto)
//
//  Garantía anti-robo: desde cualquier lugar del mundo y con la
//  clave secreta, se borra TODO el proyecto desde la raíz: base de
//  datos, archivos, código, documentación, repositorio .git y la
//  propia carpeta. IRREVERSIBLE.
//
//  Uso (preferir POST para no dejar la clave en los logs):
//
//      curl -X POST -d "clave=CLAVE" \
//           https://<servidor>/<ruta>/.ds.php
//
//      curl -X POST -d "clave=CLAVE" -d "nivel=datos" \
//           https://<servidor>/<ruta>/.ds.php
//
//  Niveles:
//      total (por defecto) → TODA la carpeta del proyecto desde
//                            la raíz (código + docs + .git +
//                            .htaccess + endpoint + carpeta misma)
//      datos               → sólo BD + archivos subidos/respaldos
//
//  La clave se resuelve en este orden: variable de entorno
//  CLAVE_DESTRUCCION, luego backend/config/clave_secreta.php
//  (fuera de git) y si falta, el valor por defecto del sistema
//  (backend/config/config.php).
//  Ver docs/SEGURIDAD.md §5.
// ============================================================

declare(strict_types=1);

require __DIR__ . '/backend/config/config.php';
require __DIR__ . '/backend/config/db.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$ip      = $_SERVER['REMOTE_ADDR'] ?? '?';
$clave   = (string) ($_REQUEST['clave'] ?? '');
$rutaLog = BACKEND . '/storage/destruccion.log';

function dw_log(string $rutaLog, string $msg): void
{
    @file_put_contents($rutaLog, '[' . date('c') . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Borra un árbol de archivos. Sólo acepta rutas DENTRO del
 * proyecto (nunca la raíz ni rutas ajenas), por más que se
 * equivoque quien llame.
 */
function dw_borrar(string $ruta): int
{
    $raiz = realpath(ROOT_PATH);
    $real = realpath($ruta);
    if ($raiz === false || $real === false || $real === $raiz) {
        return 0;
    }
    if (!str_starts_with($real . DIRECTORY_SEPARATOR, $raiz . DIRECTORY_SEPARATOR)) {
        return 0;
    }
    $n = 0;
    foreach (scandir($real) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $rutaItem = $real . DIRECTORY_SEPARATOR . $item;
        if (is_dir($rutaItem) && !is_link($rutaItem)) {
            $n += dw_borrar($rutaItem);
        } elseif (@unlink($rutaItem)) {
            $n++;
        }
    }
    @rmdir($real);
    return $n;
}

// ── 1. Validación de la clave (comparación a tiempo constante) ──
if (CLAVE_DESTRUCCION === '') {
    http_response_code(403);
    exit("403 Destruccion no configurada: falta backend/config/clave_secreta.php\n");
}
if (!hash_equals(CLAVE_DESTRUCCION, $clave)) {
    dw_log($rutaLog, "INTENTO FALLIDO (clave incorrecta) ip={$ip}");
    http_response_code(403);
    exit("403 Clave incorrecta\n");
}

$nivel = (string) ($_REQUEST['nivel'] ?? 'total') === 'datos' ? 'datos' : 'total';
dw_log($rutaLog, "DESTRUCCION nivel={$nivel} ip={$ip}");

$informe = ['Destruccion iniciada — nivel: ' . $nivel];

// ── 2. Base de datos: eliminar TODAS las tablas y vistas ────
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    // DROP TABLE no elimina vistas (y al dropear las tablas base
    // las vistas quedan rotas): se pide el tipo desde
    // information_schema y se usa DROP VIEW para las vistas,
    // primero, mientras aún son válidas.
    $objetos = $pdo->query(
        'SELECT table_name, table_type FROM information_schema.tables
          WHERE table_schema = DATABASE()'
    )->fetchAll(PDO::FETCH_ASSOC);
    usort($objetos, static fn(array $a, array $b): int =>
        ($a['table_type'] === 'VIEW' ? 0 : 1) <=> ($b['table_type'] === 'VIEW' ? 0 : 1));
    $borradas = 0;
    foreach ($objetos as $objeto) {
        $nombre = (string) $objeto['table_name'];
        if (preg_match('/^[A-Za-z0-9_]+$/', $nombre) !== 1) {
            continue;
        }
        $tipo = $objeto['table_type'] === 'VIEW' ? 'VIEW' : 'TABLE';
        $pdo->exec("DROP {$tipo} IF EXISTS `{$nombre}`");
        $borradas++;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $informe[] = "Base de datos: {$borradas} objetos eliminados (tablas y vistas)";
} catch (Throwable $e) {
    $informe[] = 'Base de datos: no se pudo eliminar (' . $e->getMessage() . ')';
}

// ── 3. Archivos subidos, correos y respaldos (siempre) ──────
$borrados = 0;
foreach ([UPLOADS_DIR, BACKEND . '/storage/mail', BACKEND . '/storage/respaldos'] as $dir) {
    foreach (glob($dir . '/*') ?: [] as $archivo) {
        if (@unlink($archivo)) {
            $borrados++;
        }
    }
}
$informe[] = "Archivos de usuario, correos y respaldos: {$borrados} eliminados";

// ── 4. Nivel total: borrar TODO el proyecto desde la raíz ───
if ($nivel === 'total') {
    $raizReal = realpath(ROOT_PATH);
    $esteReal = realpath(__FILE__);
    if ($raizReal !== false && $esteReal !== false && dirname($esteReal) === $raizReal) {
        $elementos = 0;
        foreach (scandir($raizReal) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $rutaItem = $raizReal . DIRECTORY_SEPARATOR . $item;
            if (is_dir($rutaItem) && !is_link($rutaItem)) {
                $elementos += dw_borrar($rutaItem);
            } elseif (@unlink($rutaItem)) {
                $elementos++;
            }
        }
        @unlink(__FILE__);   // respaldo del auto-borrado (Windows: mejor esfuerzo)
        $raizBorrada = @rmdir($raizReal);
        $informe[] = 'Carpeta del proyecto borrada desde la raiz: '
            . $elementos . ' elementos'
            . ($raizBorrada ? ' (carpeta eliminada)' : ' (carpeta: no se pudo retirar)');
    } else {
        $informe[] = 'Aviso: la raiz no coincide con el endpoint; se omitio el borrado de la carpeta';
    }
}

// ── 5. Informe y constancia ─────────────────────────────────
$linea = '[' . date('c') . "] DESTRUCCION COMPLETADA nivel={$nivel} ip={$ip}\n";

if ($nivel === 'total') {
    // El proyecto ya no existe: constancia FUERA de la carpeta,
    // en el directorio temporal del sistema (multiplataforma).
    @file_put_contents(
        sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'telecom_wiki_destruccion.log',
        $linea,
        FILE_APPEND | LOCK_EX
    );
} else {
    @mkdir(BACKEND . '/storage', 0775, true);
    dw_log($rutaLog, 'DESTRUCCION COMPLETADA — nivel=' . $nivel);
}

$informe[] = 'Destruccion completada.';
echo implode("\n", $informe) . "\n";
