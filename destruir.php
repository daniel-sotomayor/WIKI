<?php
// ============================================================
//  TelecomWiki — DESTRUCCIÓN DE EMERGENCIA (remota)
//  Archivo: destruir.php  (en la RAÍZ del proyecto)
//
//  Garantía anti-robo: desde cualquier lugar del mundo y con la
//  clave secreta, se borra la base de datos y los archivos del
//  sistema; en nivel total se borra además el propio código, la
//  documentación y el repositorio. IRREVERSIBLE.
//
//  Uso (preferir POST para no dejar la clave en los logs):
//
//      curl -X POST -d "clave=CLAVE" \
//           https://<servidor>/<ruta>/destruir.php
//
//      curl -X POST -d "clave=CLAVE" -d "nivel=datos" \
//           https://<servidor>/<ruta>/destruir.php
//
//  Niveles:
//      total (por defecto) → BD + archivos + código + docs + .git
//      datos               → sólo BD + archivos subidos/respaldos
//
//  La clave vive en backend/config/clave_secreta.php (fuera de
//  git) o en la variable de entorno CLAVE_DESTRUCCION.
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

// ── 2. Base de datos: eliminar TODAS las tablas ─────────────
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tablas  = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $borradas = 0;
    foreach ($tablas as $tabla) {
        $tabla = (string) $tabla;
        if (preg_match('/^[A-Za-z0-9_]+$/', $tabla) === 1) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $tabla . '`');
            $borradas++;
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $informe[] = "Base de datos: {$borradas} tablas eliminadas";
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

// ── 4. Nivel total: documentación, código y repositorio ─────
if ($nivel === 'total') {
    $objetivos = [
        ROOT_PATH . '/docs',
        ROOT_PATH . '/frontend',
        ROOT_PATH . '/backend',
        ROOT_PATH . '/README.md',
        ROOT_PATH . '/index.php',
        ROOT_PATH . '/dev-router.php',
        ROOT_PATH . '/dev-server.sh',
        ROOT_PATH . '/.git',
    ];
    $elementos = 0;
    foreach ($objetivos as $objetivo) {
        if (is_dir($objetivo)) {
            $elementos += dw_borrar($objetivo);
        } elseif (is_file($objetivo) && @unlink($objetivo)) {
            $elementos++;
        }
    }
    $informe[] = "Codigo, documentacion y repositorio: {$elementos} elementos eliminados";
}

// ── 5. Informe, constancia y cierre ─────────────────────────
// Si backend/ se borró (nivel total), se recrea sólo storage/
// para dejar la constancia de la destrucción.
@mkdir(BACKEND . '/storage', 0775, true);
dw_log($rutaLog, 'DESTRUCCION COMPLETADA — nivel=' . $nivel);

$informe[] = 'Destruccion completada.';
echo implode("\n", $informe) . "\n";

if ($nivel === 'total') {
    @unlink(__FILE__);   // el endpoint se elimina a sí mismo
}
