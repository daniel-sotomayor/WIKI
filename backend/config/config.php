<?php
// ============================================================
//  CONFIGURACIÓN GENERAL DEL SISTEMA
//  Archivo: backend/config/config.php
//
//  Todo lo que puede variar entre una instalación y otra vive
//  acá. Si necesitás mover el proyecto de carpeta, cambiar el
//  nombre de la base o ajustar límites de subida, es el único
//  archivo que tenés que tocar (más db.php para las credenciales).
// ============================================================

// ── Entorno de ejecución ────────────────────────────────────
// APP_ENV=production  → oculta los errores al visitante (van al log).
// APP_ENV=development → muestra los errores (para programar en XAMPP).
// Si no está definida, se infiere: sólo se muestra en desarrollo
// cuando el sitio se está sirviendo desde la propia máquina.
define('APP_ENV', getenv('APP_ENV') ?: (
    in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '[::1]', '::1'], true)
        ? 'development'
        : 'production'
));
define('APP_IS_PROD', APP_ENV === 'production');

// ── Rutas del sistema (se calculan solas, no hace falta tocar) ──
define('ROOT_PATH',  dirname(__DIR__, 2));                 // raíz del proyecto
define('BACKEND',    ROOT_PATH . '/backend');
define('FRONTEND',   ROOT_PATH . '/frontend');
define('UPLOADS_DIR', BACKEND . '/storage/uploads');        // FUERA del alcance web

// ── Sitio ───────────────────────────────────────────────────
// SITE_URL debe terminar en /frontend. Si cambiás el nombre de la
// carpeta del proyecto en htdocs, actualizá este valor (o definí la
// variable de entorno SITE_URL del servidor).
define('SITE_URL',  getenv('SITE_URL')  ?: 'http://localhost/telecom_wiki/frontend');
define('SITE_NAME', getenv('SITE_NAME') ?: 'TelecomWiki');
define('SITE_TAGLINE', 'Gestión y Estandarización de Documentación Técnica');

// ── Límites de subida de archivos ───────────────────────────
define('UPLOAD_MAX_FILESIZE', 50 * 1024 * 1024);   // 50 MB (también se ajusta en php.ini)
define('UPLOAD_LIMITS', [
    'imagen'    => 5  * 1024 * 1024,   // 5 MB
    'video'     => 50 * 1024 * 1024,   // 50 MB
    'documento' => 15 * 1024 * 1024,   // 15 MB
]);

// ── Lista blanca de tipos de archivo permitidos ─────────────
// extensión (minúsculas) => [tipo lógico, MIME real aceptado]
define('UPLOAD_MIME', [
    'jpg'  => ['imagen',    ['image/jpeg']],
    'jpeg' => ['imagen',    ['image/jpeg']],
    'png'  => ['imagen',    ['image/png']],
    'gif'  => ['imagen',    ['image/gif']],
    'webp' => ['imagen',    ['image/webp']],
    'mp4'  => ['video',     ['video/mp4', 'video/quicktime']],
    'webm' => ['video',     ['video/webm']],
    'pdf'  => ['documento', ['application/pdf']],
    'doc'  => ['documento', ['application/msword',
                             'application/vnd.openxmlformats-officedocument.wordprocessingml.document']],
    'docx' => ['documento', ['application/msword',
                             'application/vnd.openxmlformats-officedocument.wordprocessingml.document']],
    'odt'  => ['documento', ['application/vnd.oasis.opendocument.text']],
    'txt'  => ['documento', ['text/plain']],
    'csv'  => ['documento', ['text/csv', 'text/plain', 'application/csv']],
    'zip'  => ['documento', ['application/zip', 'application/x-zip-compressed']],
    'rar'  => ['documento', ['application/vnd.rar', 'application/x-rar-compressed']],
]);

// ── Comportamiento de los listados ──────────────────────────
define('POR_PAGINA', 12);
define('POR_PAGINA_ADMIN', 20);

// ── Seguridad: límites de intentos ──────────────────────────
define('LOGIN_MAX_POR_USUARIO', 5);      // intentos fallidos...
define('LOGIN_MAX_POR_IP', 10);          // ...por usuario / por IP
define('LOGIN_VENTANA_MIN', 10);         // ...en esta ventana (minutos)
define('CONTACTO_MAX_POR_IP', 5);        // envíos de contacto
define('CONTACTO_VENTANA_MIN', 15);
define('SUBIDA_MAX_POR_IP', 30);         // subidas de archivo
define('SUBIDA_VENTANA_MIN', 10);

// ── Listas cerradas ─────────────────────────────────────────
// La columna `noticias.categoria` es VARCHAR libre; ésta lista
// blanca evita que se llene de valores arbitrarios desde el form.
define('NOTICIA_CATEGORIAS', [
    'institucional' => 'Institucional',
    'proyectos'     => 'Proyectos',
    'eventos'       => 'Eventos',
    'tecnologia'    => 'Tecnología',
    'convocatoria'  => 'Convocatorias',
    'otro'          => 'Otro',
]);

// ── Sesión ──────────────────────────────────────────────────
define('SESION_INACTIVIDAD_SEG', 30 * 60);   // 30 minutos
define('SESION_DURACION_MAX_SEG', 8 * 3600); // 8 horas
define('SESION_REGENERAR_SEG', 15 * 60);     // regenerar ID cada 15 min

// ── Colores de alerta reutilizados por todas las pantallas ──
define('ALERT_CLASES', [
    'success' => 'alert alert-success',
    'danger'  => 'alert alert-danger',
    'info'    => 'alert alert-info',
    'warning' => 'alert alert-warning',
]);
