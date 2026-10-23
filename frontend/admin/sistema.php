<?php
// ============================================================
//  Diagnóstico del sistema — comprobación del entorno
//  Archivo: frontend/admin/sistema.php
//  Requiere: `panel.acceder`
//
//  Sirve para verificar en 30 segundos que una instalación nueva
//  (XAMPP u otro Apache) tiene todo lo necesario: PHP, extensiones,
//  permisos de la carpeta de archivos, base de datos y configuración.
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';

requierePermiso('panel.acceder');

// ── Recopilación de comprobaciones ──────────────────────────
$chequeos = [];

$agregar = function (string $grupo, string $nombre, bool $ok, string $detalle,
                     bool $advertencia = false) use (&$chequeos) {
    $chequeos[$grupo][] = [
        'nombre'   => $nombre,
        'ok'       => $ok,
        'detalle'  => $detalle,
        'advert'   => $advertencia,
    ];
};

// PHP
$versionPhp   = PHP_VERSION;
$versionOk    = version_compare(PHP_VERSION, '8.1.0', '>=');
$agregar('Entorno', 'Versión de PHP', $versionOk,
    $versionPhp . ($versionOk
        ? ' (requerido: 8.1 o superior)'
        : ' — este sistema necesita PHP 8.1 o superior.'),
    !$versionOk);

foreach ([
    'pdo_mysql' => 'Conexión a MySQL (pdo_mysql)',
    'mbstring'  => 'Procesamiento de texto (mbstring)',
    'dom'       => 'Saneado de HTML (dom / xml)',
    'fileinfo'  => 'Detección de tipos MIME (fileinfo)',
    'session'   => 'Sesiones (session)',
    'json'      => 'JSON (json)',
    'filter'    => 'Filtrado de entrada (filter)',
] as $ext => $desc) {
    $agregar('Entorno', $desc, extension_loaded($ext),
        $ext . (extension_loaded($ext) ? ' disponible' : ' FALTA — reinstalá PHP con esa extensión.'));
}

// Límites de subida
$tamanoLegible = function (int $bytes) {
    return $bytes >= 1048576
        ? round($bytes / 1048576, 1) . ' MB'
        : ($bytes > 0 ? $bytes . ' B' : 'sin límite');
};
$subidaMax = (int) ini_get('upload_max_filesize') ?: 0;
$postMax   = (int) ini_get('post_max_size') ?: 0;
$agregar('Archivos', 'Límite por archivo (upload_max_filesize)',
    $subidaMax >= UPLOAD_MAX_FILESIZE,
    'php.ini: ' . $tamanoLegible($subidaMax)
    . ' · el sistema acepta hasta ' . $tamanoLegible(UPLOAD_MAX_FILESIZE) . '.',
    $subidaMax < UPLOAD_MAX_FILESIZE);
$agregar('Archivos', 'Límite del formulario (post_max_size)',
    $postMax >= UPLOAD_MAX_FILESIZE,
    'php.ini: ' . $tamanoLegible($postMax) . '.',
    $postMax < UPLOAD_MAX_FILESIZE);

// Carpetas de archivos
$uploadsOk = false;
if (!is_dir(UPLOADS_DIR)) {
    @mkdir(UPLOADS_DIR, 0775, true);
}
$uploadsOk = is_dir(UPLOADS_DIR) && is_writable(UPLOADS_DIR);
$agregar('Archivos', 'Carpeta de subidas', $uploadsOk,
    UPLOADS_DIR . ($uploadsOk ? ' (creada y escribible)' : ' — no se puede escribir en ella.'),
    !$uploadsOk);

$agregar('Archivos', 'Carpeta backend/ fuera de la web', true,
    'Los archivos viven en backend/storage, protegido por .htaccess y por dev-router.');

// Base de datos
try {
    $db  = getDB();
    $ver = (string) $db->query('SELECT VERSION()')->fetchColumn();
    $tablas = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = '" . DB_NAME . "'"
    )->fetchColumn();
    $usuarios = (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $agregar('Base de datos', 'Conexión', true,
        'MySQL/MariaDB ' . $ver . ' · base ' . DB_NAME . ' · ' . $tablas . ' tablas · '
        . $usuarios . ' usuarios.');
    $agregar('Base de datos', 'Datos iniciales', $tablas >= 12 && $usuarios >= 1,
        $tablas >= 12 && $usuarios >= 1
            ? 'El esquema y los usuarios de prueba están cargados.'
            : 'Faltan tablas o usuarios: importá backend/database/database.sql.',
        !($tablas >= 12 && $usuarios >= 1));
} catch (Throwable $e) {
    $agregar('Base de datos', 'Conexión', false,
        'No se pudo conectar: ' . $e->getMessage());
}

// Configuración
$agregar('Configuración', 'Entorno (APP_ENV)', true,
    APP_ENV . (APP_IS_PROD
        ? ' — los errores NO se muestran al visitante.'
        : ' — los errores se muestran en pantalla (sólo para desarrollo).'),
    APP_IS_PROD && false);

$agregar('Configuración', 'URL del sitio (SITE_URL)', str_starts_with(SITE_URL, 'http'),
    SITE_URL, false);

$faltanHtaccess = array_filter([
    ROOT_PATH . '/.htaccess',
    BACKEND . '/.htaccess',
    FRONTEND . '/partials/.htaccess',
    BACKEND . '/storage/.htaccess',
], fn (string $ruta) => !is_file($ruta));
$agregar('Configuración', 'Archivos .htaccess', $faltanHtaccess === [],
    $faltanHtaccess === []
        ? 'Los 4 archivos de protección están en su lugar.'
        : 'Faltan: ' . implode(', ', $faltanHtaccess),
    $faltanHtaccess !== []);

$vendorOk = is_file(BACKEND . '/vendor/autoload.php');
$agregar('Configuración', 'PHPMailer (vendor)', $vendorOk,
    $vendorOk ? 'Autocargador presente en backend/vendor.'
              : 'Falta backend/vendor: corré `composer install` en backend/.',
    !$vendorOk);

$agregar('Configuración', 'Correo saliente (SMTP)', true,
    'Configurado en backend/config/mail_config.php; se prueba el envío desde «Quiénes somos».');

// Resumen
$total    = 0;
$fallas   = 0;
$avisos   = 0;
foreach ($chequeos as $grupo) {
    foreach ($grupo as $c) {
        $total++;
        if (!$c['ok']) {
            $fallas++;
        } elseif ($c['advert']) {
            $avisos++;
        }
    }
}

$pageTitle = 'Diagnóstico del sistema';
$cssExtra  = 'admin.css';
abrirPagina();
?>

<div class="container">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Panel' => 'admin/index.php'],
        ['Diagnóstico' => null],
    ]) ?>

    <div class="seccion-cabecera">
        <div>
            <h1>Diagnóstico del sistema</h1>
            <p class="muted">Comprobación del entorno para una instalación con XAMPP u otro Apache.</p>
        </div>
    </div>

    <?php if ($fallas === 0 && $avisos === 0): ?>
        <div class="alert alert-success" role="status">
            Todo en orden: <?= $total ?> comprobaciones superadas.
        </div>
    <?php elseif ($fallas === 0): ?>
        <div class="alert alert-warning" role="status">
            <?= $total ?> comprobaciones, <?= $avisos ?> con advertencias (funciona, pero conviene revisar).
        </div>
    <?php else: ?>
        <div class="alert alert-danger" role="alert">
            <?= $fallas ?> de <?= $total ?> comprobaciones fallan. Revisá los puntos marcados en rojo.
        </div>
    <?php endif; ?>

    <?php foreach ($chequeos as $grupo => $items): ?>
    <section class="panel">
        <header class="panel__cabecera">
            <h2 class="panel__titulo"><?= e($grupo) ?></h2>
            <span class="contador"><?= count($items) ?></span>
        </header>

        <ul class="lista-adjuntos">
            <?php foreach ($items as $c): ?>
            <li>
                <span class="lista-adjuntos__icono" aria-hidden="true">
                    <?= icono($c['ok'] ? ($c['advert'] ? 'info' : 'check') : 'close', 20) ?>
                </span>
                <div>
                    <strong><?= e($c['nombre']) ?></strong>
                    <?php if (!$c['ok']): ?>
                        <span class="status-badge status-rechazada">Falla</span>
                    <?php elseif ($c['advert']): ?>
                        <span class="status-badge status-pendiente">Aviso</span>
                    <?php else: ?>
                        <span class="status-badge status-publicada">OK</span>
                    <?php endif; ?>
                    <p class="muted"><?= e($c['detalle']) ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endforeach; ?>

    <p class="muted">Si algo cambia en el servidor (versión de PHP, permisos, base de datos),
        volvé a abrir esta página: se recalcula en cada petición.</p>

</div>

<?php cerrarPagina(); ?>
