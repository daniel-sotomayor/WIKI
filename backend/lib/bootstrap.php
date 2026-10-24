<?php
// ============================================================
//  PUNTO ÚNICO DE ARRANQUE
//  Archivo: backend/lib/bootstrap.php
//
//  Cada pantalla PHP empieza con:
//
//      <?php require_once __DIR__ . '/../backend/lib/bootstrap.php';
//
//  y a partir de ahí tiene disponibles getDB(), e(), puede(),
//  sanearHTML(), paginar(), campo(), etc. Antes era preciso
//  recordar una lista distinta de require en cada archivo.
//
//  IMPORTANTE: el orden importa. security.php envía las cabeceras
//  HTTP e inicia la sesión, así que debe correr ANTES de cualquier
//  salida de HTML.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/security.php';        // cabeceras + sesión (primero)
require_once __DIR__ . '/../config/db.php';    // getDB()
require_once __DIR__ . '/helpers.php';         // e(), slugify(), icono()…
require_once __DIR__ . '/auth.php';            // login, CSRF, permisos de sesión
require_once __DIR__ . '/permisos.php';        // matriz de roles
require_once __DIR__ . '/validate.php';        // saneo de entrada
require_once __DIR__ . '/paginate.php';        // listados paginados
require_once __DIR__ . '/sanitize.php';        // limpieza de HTML
require_once __DIR__ . '/render.php';          // tarjetas, campos, miga de pan
require_once __DIR__ . '/upload.php';          // multimedia
require_once __DIR__ . '/tags.php';            // etiquetas de artículos
require_once __DIR__ . '/config_sitio.php';    // ajustes en BD (mantenimiento…)

// ── Modo mantenimiento ──────────────────────────────────────
// Si está activo (Panel → Diagnóstico), el público ve un aviso 503
// y sólo entran los usuarios con sesión. El acceso, el cierre de
// sesión y la entrega de archivos siguen habilitados.
if (modoMantenimiento() && !isLoggedIn()) {
    $rutaActual = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $permitidas = ['login.php', 'logout.php', 'media.php'];

    if (!in_array($rutaActual, $permitidas, true)) {
        http_response_code(503);
        header('Retry-After: 300');
        header('Content-Type: text/html; charset=utf-8');
        $sitio = e(SITE_NAME);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>Sitio en mantenimiento · ' . $sitio . '</title>'
           . '<link rel="stylesheet" href="' . e(url('css/base.css')) . '">'
           . '<link rel="stylesheet" href="' . e(url('css/layout.css')) . '">'
           . '<link rel="stylesheet" href="' . e(url('css/pages.css')) . '">'
           . '</head><body class="pagina-mantenimiento"><main class="container">'
           . '<h1>Sitio en mantenimiento</h1>'
           . '<p>Estamos realizando tareas de mantenimiento de '
           . '<strong>' . $sitio . '</strong>. Volvé a intentar en unos minutos.</p>'
           . '<p><a class="btn btn-primary" href="' . e(url('login.php')) . '">'
           . 'Iniciar sesión (personal autorizado)</a></p>'
           . '</main></body></html>';
        exit;
    }
}

/**
 * Cabecera estándar de toda pantalla.
 *
 * Las variables de presentación ($pageTitle, $cssExtra, $descripcion)
 * se definen en el ámbito global de la pantalla ANTES de llamar a esta
 * función; aquí se importan con `global` para que los partials puedan
 * leerlas (un include dentro de una función NO ve el ámbito global).
 *
 * @param string $titulo  si no está vacío, prevalece sobre $pageTitle
 */
function abrirPagina(string $titulo = ''): void {
    global $pageTitle, $cssExtra, $descripcion;

    if ($titulo !== '') {
        $pageTitle = $titulo;
    }
    include FRONTEND . '/partials/header.php';
}

/**
 * Pie estándar de toda pantalla.
 * Lee $jsExtra (scripts adicionales) del ámbito global.
 */
function cerrarPagina(): void {
    global $jsExtra;
    include FRONTEND . '/partials/footer.php';
}
