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
require_once BACKEND . '/lib/mailer.php';   // MAIL_TRANSPORT, enviarCorreoContacto()

requierePermiso('panel.acceder');

$yo  = currentUser();
$esAdmin = nivelRol($yo['rol']) >= 4;

// ── Acciones (todas con CSRF) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = (string) ($_POST['accion'] ?? '');

    // Modo mantenimiento — sólo administrador
    if ($accion === 'mantenimiento') {
        requierePermiso('usuario.gestionar');
        $nuevo = modoMantenimiento() ? '0' : '1';
        configGuardar('mantenimiento', $nuevo);
        logSeguridad($nuevo === '1' ? 'mantenimiento_activado' : 'mantenimiento_desactivado',
            (string) $yo['id'], null);
        redirigir('admin/sistema.php', 'success',
            $nuevo === '1'
                ? 'Modo mantenimiento activado: el público ya no ve el sitio.'
                : 'Modo mantenimiento desactivado: el sitio vuelve a ser público.');
    }

    // Correo de prueba — escribe un .eml (transporte archivo) o envía por SMTP
    if ($accion === 'correo_prueba') {
        $ok = enviarCorreoContacto('Prueba de envío — TelecomWiki',
            'prueba@telecom.gob.ve',
            'Correo de prueba generado desde Panel → Diagnóstico del sistema.');
        logSeguridad('correo_prueba', (string) $yo['id'], $ok ? 'enviado' : 'falló');
        redirigir('admin/sistema.php', $ok ? 'success' : 'danger',
            $ok
                ? 'Correo de prueba generado. Con el transporte por archivo queda en '
                  . 'backend/storage/mail/ (último: ' . basename((string) ultimoCorreoArchivo()) . ').'
                : 'No se pudo generar el correo de prueba; revisá el log del servidor.');
    }
}

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
    MAIL_TRANSPORT === 'archivo'
        ? 'Transporte por ARCHIVO: cada correo queda en backend/storage/mail/ '
          . '(no hace falta servidor SMTP en desarrollo).'
        : 'Transporte SMTP hacia ' . SMTP_HOST . ':' . SMTP_PORT . '.',
    MAIL_TRANSPORT !== 'archivo');

$mantenimiento = modoMantenimiento();
$agregar('Configuración', 'Modo mantenimiento', !$mantenimiento,
    $mantenimiento
        ? 'ACTIVO: el público ve un aviso 503 y sólo entra el personal con sesión.'
        : 'Desactivado: el sitio es público.',
    $mantenimiento);

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

    <section class="panel">
        <header class="panel__cabecera">
            <h2 class="panel__titulo">Acciones de mantenimiento</h2>
        </header>

        <form method="post" class="formulario" action="<?= e(url('admin/sistema.php')) ?>">
            <?= campoCSRF() ?>
            <input type="hidden" name="accion" value="correo_prueba">
            <p class="muted">Genera un correo al departamento
                (<?= e(CONTACT_TO_EMAIL) ?>) usando el transporte
                <strong><?= e(MAIL_TRANSPORT) ?></strong>.</p>
            <div class="acciones-formulario">
                <button class="btn btn-outline" type="submit">
                    <?= icono('mail', 15) ?> Enviar correo de prueba
                </button>
            </div>
        </form>

        <?php if ($esAdmin): ?>
        <hr>
        <form method="post" class="formulario" action="<?= e(url('admin/sistema.php')) ?>"
              data-confirm-submit="<?= $mantenimiento
                  ? '¿Desactivar el modo mantenimiento y volver a publicar el sitio?'
                  : '¿Activar el modo mantenimiento? El público verá un aviso y sólo entrarán los usuarios con sesión.' ?>">
            <?= campoCSRF() ?>
            <input type="hidden" name="accion" value="mantenimiento">
            <p class="muted">Con el mantenimiento activo, el público ve una página de
                aviso (HTTP 503) y sólo el personal con sesión puede entrar.</p>
            <div class="acciones-formulario">
                <button class="btn <?= $mantenimiento ? 'btn-primary' : 'btn-danger' ?>" type="submit">
                    <?= icono('shield', 15) ?>
                    <?= $mantenimiento ? 'Desactivar mantenimiento' : 'Activar mantenimiento' ?>
                </button>
            </div>
        </form>
        <?php endif; ?>
    </section>

    <p class="muted">Si algo cambia en el servidor (versión de PHP, permisos, base de datos),
        volvé a abrir esta página: se recalcula en cada petición.</p>

</div>

<?php cerrarPagina(); ?>
