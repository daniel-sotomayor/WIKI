<?php
// ============================================================
//  ROUTER DEL SERVIDOR DE DESARROLLO DE PHP
//  Archivo: dev-router.php  (SÓLO para probar localmente)
//
//      php -S 127.0.0.1:8000 -t telecom_wiki dev-router.php
//
//  El servidor embebido de PHP ignora los .htaccess, así que este
//  router reproduce las mismas protecciones que Apache aplica en
//  XAMPP: bloquea backend/, storage/, archivos .sql/.md/.env, el
//  listado de directorios y las plantillas parciales.
//
//  En XAMPP real no se usa: ahí manda el .htaccess de la raíz.
// ============================================================

$uri     = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$ruta    = rawurldecode($uri);
$archivo = __DIR__ . $ruta;

// ── 1. Protecciones equivalentes al .htaccess ───────────────
$bloqueadas = [
    '#^/backend(/|$)#'          => 'backend',
    '#^/docs(/|$)#'             => 'documentación',
    '#^/dev-router\.php$#'      => 'router',
    '#\.(sql|md|log|env|ini|bak|sh|json|lock|rar)$#' => 'archivo de configuración',
    '#^/frontend/partials(/|$)#'=> 'plantillas parciales',
    '#/storage(/|$)#'           => 'almacenamiento de archivos',
];

foreach ($bloqueadas as $patron => $motivo) {
    if (preg_match($patron, $ruta)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo '403 Acceso denegado (' . $motivo . ')';
        exit;
    }
}

// ── 2. Sin listado de directorios ───────────────────────────
if (is_dir($archivo)) {
    if (is_file($archivo . '/index.php')) {
        $ruta = rtrim($ruta, '/') . '/index.php';
        $archivo = __DIR__ . $ruta;
    } else {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo '403 Listado de directorios deshabilitado';
        exit;
    }
}

// ── 3. Archivo que existe: se sirve tal cual (PHP o estático) ──
if (is_file($archivo)) {
    if (str_ends_with(strtolower($archivo), '.php')) {
        chdir(dirname($archivo));
        require $archivo;
        return true;
    }
    return false;   // el servidor de desarrollo se encarga (css, js, fuentes…)
}

// ── 4. Alias cómodos: /wiki.php → /frontend/wiki.php ────────
// Permite probar con URLs cortas aunque en XAMPP el sitio viva
// bajo /telecom_wiki/frontend/. Se redirige (302) para que la
// dirección que quede en el navegador sea siempre la canónica.
$archivoFrontend = __DIR__ . '/frontend' . $ruta;
if (is_file($archivoFrontend)) {
    $destino = '/frontend' . $ruta;
    $query   = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . $destino . ($query !== '' ? '?' . $query : ''), true, 302);
    exit;
}

// ── 5. Raíz del proyecto → redirigir al frontend ────────────
if ($ruta === '/' || $ruta === '') {
    header('Location: /frontend/index.php', true, 302);
    exit;
}

// ── 6. 404 con plantilla ────────────────────────────────────
http_response_code(404);
require __DIR__ . '/frontend/../backend/lib/bootstrap.php';
paginaNoEncontrada('página');
return true;
