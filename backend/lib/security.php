<?php
// ============================================================
//  SEGURIDAD — cabeceras HTTP, sesión endurecida y bitácora
//  Archivo: backend/lib/security.php
//
//  Se incluye una sola vez, muy al inicio de cada petición
//  (desde bootstrap.php), ANTES de cualquier salida HTML:
//  tanto session_start() como header() deben ejecutarse antes
//  de imprimir nada.
// ============================================================

require_once __DIR__ . '/../config/config.php';

// ── 1. Errores según entorno ────────────────────────────────
if (APP_IS_PROD) {
    // Producción: nunca mostrar errores/stack traces al visitante;
    // sí registrarlos en el log de PHP para que el admin los vea.
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
} else {
    // Desarrollo (XAMPP local): mostrar todo, para depurar cómodo.
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Oculta la versión de PHP en las respuestas (reduce fingerprinting)
if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}

// Nota: upload_max_filesize / post_max_size son PHP_INI_PERDIR y NO se
// pueden cambiar en tiempo de ejecución. Si subís archivos grandes,
// ajustalos en el php.ini de XAMPP (ver docs/LEEME.md).
ini_set('max_execution_time', '60');

// ── 2. Cabeceras de seguridad HTTP ──────────────────────────
$httpsActivo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

// Nonce único por request: permite SOLO los <script> propios que lo
// llevan explícitamente (evita 'unsafe-inline' en script-src).
if (!isset($GLOBALS['CSP_NONCE'])) {
    $GLOBALS['CSP_NONCE'] = base64_encode(random_bytes(16));
}
$cspNonce = $GLOBALS['CSP_NONCE'];

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');                    // anti-clickjacking
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

    // Content-Security-Policy:
    //  - script-src 'self' + nonce  → sólo nuestros JS y los inline con nonce
    //  - style-src 'self'           → sin 'unsafe-inline': todo el CSS vive en archivos
    //                                 propios y los estilos inline fueron eliminados
    //  - media-src 'self'           → imágenes/videos/documentos subidos (media.php)
    //  - frame-ancestors/object-src → blindaje anti-clickjacking y plugins
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'nonce-$cspNonce'; "
         . "style-src 'self'; "
         . "font-src 'self'; "
         . "img-src 'self' data:; "
         . "media-src 'self'; "
         . "connect-src 'self'; "
         . "base-uri 'self'; "
         . "form-action 'self'; "
         . "frame-ancestors 'none'; "
         . "object-src 'none'");

    if ($httpsActivo) {
        // Sólo si ya se sirve por HTTPS real; en HTTP local (XAMPP sin
        // certificado) se omite para no romper el acceso.
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ── 3. Sesión endurecida ────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('TWSESSID');   // nombre propio en vez del genérico PHPSESSID

    session_set_cookie_params([
        'lifetime' => 0,            // cookie de sesión (expira al cerrar el navegador)
        'path'     => '/',
        'domain'   => '',
        'secure'   => $httpsActivo, // sólo viaja por HTTPS si el sitio ya usa HTTPS
        'httponly' => true,         // inaccesible desde JavaScript (mitiga robo por XSS)
        'samesite' => 'Lax',        // mitiga CSRF en navegación cross-site
    ]);

    ini_set('session.use_strict_mode', '1');    // rechaza IDs de sesión inventados
    ini_set('session.use_only_cookies', '1');   // nunca vía ?PHPSESSID= en la URL

    session_start();

    // Tiempo máximo de inactividad
    if (isset($_SESSION['ultima_actividad'])
        && (time() - $_SESSION['ultima_actividad']) > SESION_INACTIVIDAD_SEG) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
    $_SESSION['ultima_actividad'] = time();

    // Duración máxima absoluta de la sesión, aunque haya actividad
    if (!isset($_SESSION['inicio_sesion'])) {
        $_SESSION['inicio_sesion'] = time();
    } elseif ((time() - $_SESSION['inicio_sesion']) > SESION_DURACION_MAX_SEG) {
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['inicio_sesion'] = time();
    }

    // Regenera el ID de sesión periódicamente para reducir la ventana
    // de uso de un ID robado.
    if (!isset($_SESSION['regenerado_en'])) {
        $_SESSION['regenerado_en'] = time();
    } elseif ((time() - $_SESSION['regenerado_en']) > SESION_REGENERAR_SEG) {
        session_regenerate_id(true);
        $_SESSION['regenerado_en'] = time();
    }
}

// ── 4. IP real del visitante ────────────────────────────────
// NOTA: X-Forwarded-For puede ser falsificado por el propio cliente si
// no hay un proxy de confianza en el medio. Sólo se usa como referencia.
function ipCliente(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ── 5. Bitácora de eventos de seguridad ─────────────────────
function logSeguridad(string $tipo, ?string $usuario = null, ?string $detalle = null): void {
    try {
        $st = getDB()->prepare(
            'INSERT INTO eventos_seguridad (tipo, usuario, ip, detalle) VALUES (?, ?, ?, ?)'
        );
        $st->execute([$tipo, $usuario, ipCliente(), $detalle]);
    } catch (Throwable $e) {
        error_log('[logSeguridad] No se pudo registrar el evento: ' . $e->getMessage());
    }
}

// Cuenta los eventos de un tipo ocurridos desde una IP (y opcionalmente
// con un usuario) en los últimos $minutos. Sostiene el límite de intentos
// de login, el rate-limit del contacto y el de subida de archivos.
function contarEventosRecientes(string $tipo, ?string $usuario, int $minutos): int {
    try {
        // La fecha se calcula en PHP: es portable entre motores y no
        // depende de sintaxis de intervalos de ningún SGBD concreto.
        $desde = date('Y-m-d H:i:s', time() - ($minutos * 60));

        if ($usuario !== null) {
            $st = getDB()->prepare(
                'SELECT COUNT(*) FROM eventos_seguridad
                  WHERE tipo = ? AND (ip = ? OR usuario = ?) AND creado_en > ?'
            );
            $st->execute([$tipo, ipCliente(), $usuario, $desde]);
        } else {
            $st = getDB()->prepare(
                'SELECT COUNT(*) FROM eventos_seguridad
                  WHERE tipo = ? AND ip = ? AND creado_en > ?'
            );
            $st->execute([$tipo, ipCliente(), $desde]);
        }
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        error_log('[contarEventosRecientes] ' . $e->getMessage());
        return 0;   // ante un fallo no bloqueamos al usuario legítimo
    }
}
