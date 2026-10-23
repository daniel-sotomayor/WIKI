<?php
// ============================================================
//  AUTENTICACIÓN, SESIONES Y CSRF
//  Archivo: backend/lib/auth.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/security.php';   // cabeceras + sesión endurecida (inicia la sesión)

// ── Estado de la sesión ─────────────────────────────────────

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Devuelve el usuario actual SIEMPRE leyendo de la base de datos.
 *
 * Motivo: si un admin cambia el rol o desactiva una cuenta, el cambio
 * debe surtir efecto de inmediato. Guardar el rol sólo al hacer login
 * dejaba privilegios obsoletos vigentes hasta 8 horas.
 * El resultado se cachea durante la petición para no repetir consultas.
 */
function currentUser(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $id = (int) $_SESSION['user_id'];
    $row = dbUnica(
        'SELECT id, nombre, apellido, usuario, email, rol, activo
           FROM usuarios WHERE id = ? LIMIT 1',
        [$id]
    );

    if (!$row || !(int) $row['activo']) {
        // Cuenta eliminada o desactivada mientras estaba con sesión abierta.
        destruirSesion();
        return null;
    }

    // Sincroniza la sesión con lo que dice la BD (rol pudo haber cambiado).
    $_SESSION['user_id']     = (int) $row['id'];
    $_SESSION['user_nombre'] = $row['nombre'];
    $_SESSION['user_rol']    = $row['rol'];

    $cache = [
        'id'       => (int) $row['id'],
        'nombre'   => $row['nombre'],
        'apellido' => $row['apellido'],
        'usuario'  => $row['usuario'],
        'email'    => $row['email'],
        'rol'      => $row['rol'],
    ];

    return $cache;
}

function requireLogin(): void {
    if (!isLoggedIn() || currentUser() === null) {
        // Guarda a dónde quería volver para redirigirlo tras el login.
        $_SESSION['intento_url'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function destruirSesion(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── Control de acceso ───────────────────────────────────────

function accesoDenegado(string $mensaje = 'No tienes permiso para realizar esta acción.'): void {
    http_response_code(403);
    $pageTitle = 'Acceso denegado';
    include FRONTEND . '/partials/header.php';
    ?>
    <main class="main-content">
        <div class="container container--narrow">
            <div class="alert alert-danger"><?= e($mensaje) ?></div>
            <a href="<?= SITE_URL ?>/index.php" class="btn btn-outline">← Volver al inicio</a>
        </div>
    </main>
    <?php
    include FRONTEND . '/partials/footer.php';
    exit;
}

function paginaNoEncontrada(string $que = 'registro'): void {
    http_response_code(404);
    $pageTitle = 'No encontrado';
    include FRONTEND . '/partials/header.php';
    ?>
    <main class="main-content">
        <div class="container container--narrow">
            <div class="alert alert-info">El <?= e($que) ?> que buscás no existe o ya no está disponible.</div>
            <a href="<?= SITE_URL ?>/index.php" class="btn btn-outline">← Volver al inicio</a>
        </div>
    </main>
    <?php
    include FRONTEND . '/partials/footer.php';
    exit;
}

/**
 * Exige un nivel mínimo de rol. Ver backend/lib/permisos.php para la
 * jerarquía y la matriz de capacidades.
 */
function requireRole(string $rol): void {
    requireLogin();
    $user = currentUser();
    $jerarquia = ['lector' => 1, 'editor' => 2, 'supervisor' => 3, 'admin' => 4];

    if (($jerarquia[$user['rol']] ?? 0) < ($jerarquia[$rol] ?? 99)) {
        logSeguridad('acceso_denegado', $user['usuario'],
            "rol requerido: $rol, rol actual: {$user['rol']}, URI: " . ($_SERVER['REQUEST_URI'] ?? ''));
        accesoDenegado("Se requiere el rol <strong>" . e($rol) . "</strong> para acceder a esta sección.");
    }
}

// ── Login / Logout ──────────────────────────────────────────

function login(string $usuario, string $password): bool|string {
    $intentosUsuario = $usuario !== '' ? contarEventosRecientes('login_fallido', $usuario, LOGIN_VENTANA_MIN) : 0;
    $intentosIp      = contarEventosRecientes('login_fallido', null, LOGIN_VENTANA_MIN);

    if ($intentosUsuario >= LOGIN_MAX_POR_USUARIO || $intentosIp >= LOGIN_MAX_POR_IP) {
        logSeguridad('login_bloqueado', $usuario ?: null);
        return 'bloqueado';
    }

    $row = dbUnica(
        'SELECT id, nombre, password, rol, activo FROM usuarios WHERE usuario = ? LIMIT 1',
        [$usuario]
    );

    if (!$row || !(int) $row['activo'] || !password_verify($password, $row['password'])) {
        logSeguridad('login_fallido', $usuario ?: null);
        return false;
    }

    session_regenerate_id(true);        // nuevo ID de sesión tras autenticar

    $_SESSION['user_id']         = (int) $row['id'];
    $_SESSION['user_nombre']     = $row['nombre'];
    $_SESSION['user_rol']        = $row['rol'];
    $_SESSION['inicio_sesion']   = time();
    $_SESSION['ultima_actividad'] = time();
    $_SESSION['regenerado_en']   = time();
    unset($_SESSION['intento_url']);

    try {
        getDB()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')
               ->execute([(int) $row['id']]);
    } catch (Throwable $e) {
        error_log('[login] No se pudo actualizar ultimo_acceso: ' . $e->getMessage());
    }

    logSeguridad('login_exitoso', $usuario);
    return true;
}

function logout(): void {
    logSeguridad('logout', $_SESSION['user_nombre'] ?? null);
    destruirSesion();
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

// ── CSRF ────────────────────────────────────────────────────

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $u = currentUser();
        logSeguridad('csrf_invalido', $u['usuario'] ?? null, $_SERVER['REQUEST_URI'] ?? null);
        http_response_code(403);
        die('Token CSRF inválido. Volvé atrás, recargá la página e inténtalo de nuevo.');
    }
}

// ── Flash messages (avisos que sobreviven a una redirección) ─

function flash(string $tipo, string $texto): void {
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function obtenerFlash(): ?array {
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function redirigir(string $ruta, string $tipo = '', string $mensaje = ''): void {
    if ($mensaje !== '') {
        flash($tipo, $mensaje);
    }
    header('Location: ' . SITE_URL . '/' . ltrim($ruta, '/'));
    exit;
}

// ============================================================
//  Utilidades compartidas por las pantallas de gestión de usuarios
//  (antes duplicadas en usuarios.php y usuario_form.php, lo que
//  provocaba "Cannot redeclare function" al incluir ambas)
// ============================================================

/**
 * Cantidad de administradores activos, opcionalmente sin contar a uno.
 * Se usa para impedir que el sistema se quede sin ningún admin.
 */
function contarAdminsActivos(?int $excluirId = null): int {
    if ($excluirId !== null) {
        $st = getDB()->prepare(
            "SELECT COUNT(*) FROM usuarios
              WHERE rol = 'admin' AND activo = 1 AND id <> ?"
        );
        $st->execute([$excluirId]);
        return (int) $st->fetchColumn();
    }
    return (int) getDB()->query(
        "SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND activo = 1"
    )->fetchColumn();
}

/** Regla de fortaleza: ≥10 caracteres, al menos una letra y un dígito. */
function passwordValida(string $password): bool {
    return strlen($password) >= 10
        && preg_match('/[A-Za-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1;
}

/** "Nombre Apellido" ya normalizado para mostrar. */
function nombreCompleto(?array $usuario): string {
    if ($usuario === null) {
        return '';
    }
    return trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''));
}
