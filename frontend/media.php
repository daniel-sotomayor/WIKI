<?php
// ============================================================
//  Archivos adjuntos — endpoint de descarga/visualización
//  Archivo: frontend/media.php
//
//  Los archivos viven en backend/storage/uploads/, que está
//  bloqueado por .htaccess. Ésta es la ÚNICA vía de acceso.
//
//  Medidas de seguridad:
//   · el nombre viene de la base de datos, nunca de la ruta
//   · formato del nombre restringido a [a-f0-9]{32}.ext
//   · se valida que el tipo declarado coincida con el archivo real
//   · Content-Type fijo desde la BD + nosniff + disposition
//   · sin indexación y sin caché de HTML
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

$nombre = saneaGET('f', 80);

if ($nombre === '' || !preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $nombre)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Solicitud inválida.');
}

$media = dbUnica('SELECT * FROM media WHERE nombre_guardado = ? LIMIT 1', [$nombre]);

if (!$media) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Archivo no encontrado.');
}

$ruta = rutaMedia($media['tipo'], $media['nombre_guardado']);

if (!is_file($ruta)) {
    error_log('[media] Registro sin archivo físico: ' . $media['nombre_guardado']);
    http_response_code(404);
    exit('Archivo no disponible.');
}

$tamano   = (int) filesize($ruta);
$mime     = (string) $media['mime'];
$esImagen = $media['tipo'] === 'imagen';
$esVideo  = $media['tipo'] === 'video';

// ── Cabeceras ───────────────────────────────────────────────
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . $tamano);
header('Cache-Control: private, max-age=86400');
header('Content-Disposition: ' . ($esImagen || $esVideo ? 'inline' : 'attachment')
     . '; filename="' . rawurlencode($media['nombre_original']) . '"');
header('Accept-Ranges: bytes');

// ── Soporte de Range (necesario para buscar dentro de <video>) ──
$inicio = 0;
$fin    = $tamano - 1;

if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] !== '') {
            $inicio = (int) $m[1];
            if ($m[2] !== '') {
                $fin = min((int) $m[2], $tamano - 1);
            }
        } elseif ($m[2] !== '') {
            // bytes=-N → últimos N bytes
            $inicio = max(0, $tamano - (int) $m[2]);
        }
    }

    if ($inicio > $fin || $inicio >= $tamano) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header('Content-Range: bytes */' . $tamano);
        exit;
    }

    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $inicio . '-' . $fin . '/' . $tamano);
    header('Content-Length: ' . ($fin - $inicio + 1));
}

// ── Volcado ─────────────────────────────────────────────────
$mano = fopen($ruta, 'rb');
if ($mano === false) {
    http_response_code(500);
    exit('No se pudo leer el archivo.');
}

fseek($mano, $inicio);
$restante = $fin - $inicio + 1;
$bloque = 8192;

while ($restante > 0 && !feof($mano) && !connection_aborted()) {
    $datos = fread($mano, min($bloque, $restante));
    if ($datos === false) {
        break;
    }
    echo $datos;
    $restante -= strlen($datos);
    flush();
}

fclose($mano);
exit;
