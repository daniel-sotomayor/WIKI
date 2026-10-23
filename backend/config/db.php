<?php
// ============================================================
//  CONEXIÓN A LA BASE DE DATOS (MySQL / MariaDB)
//  Archivo: backend/config/db.php
//
//  En producción definí los valores como variables de entorno del
//  servidor (nunca los dejes hardcodeados en un repositorio
//  público). En XAMPP local los valores por defecto de abajo son
//  suficientes para desarrollar.
// ============================================================

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'telecom_wiki');

// ── Conexión PDO ────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        // charset=utf8mb4 es imprescindible para no perder tildes, ñ y emojis.
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
             . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // prepares nativos
            ]);
        } catch (PDOException $e) {
            // El detalle real SOLO va al log del servidor (puede incluir
            // host/usuario/estructura); nunca a la respuesta pública.
            error_log('[db.php] Error de conexión a MySQL: ' . $e->getMessage());

            http_response_code(503);
            // Sin estilos en línea: la CSP del sitio (style-src 'self')
            // los bloquearía de todos modos.
            die('<div>'
              . '<strong>El sitio no está disponible en este momento.</strong><br>'
              . '<small>Intenta nuevamente en unos minutos. Si el problema persiste, '
              . 'verificá que MySQL esté iniciado (XAMPP → pestaña MySQL) y que exista '
              . 'la base de datos <em>telecom_wiki</em>.</small></div>');
        }
    }

    return $pdo;
}

// ── Atajo para consultas de una sola fila / un solo valor ───
function dbUnica(string $sql, array $params = []) {
    $st = getDB()->prepare($sql);
    $st->execute($params);
    return $st->fetch();
}

function dbValor(string $sql, array $params = []) {
    $st = getDB()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}
