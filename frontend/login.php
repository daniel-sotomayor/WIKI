<?php
// ============================================================
//  Acceso al sistema
//  Archivo: frontend/login.php
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

if (isLoggedIn() && currentUser() !== null) {
    redirigir('index.php');
}

$errores   = [];
$usuario   = '';
$bloqueado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $usuario = saneaPOST('usuario', 50);
    $password = (string) ($_POST['password'] ?? '');
    if (is_array($password)) $password = '';

    if ($usuario === '' || $password === '') {
        $errores[] = 'Ingresá tu usuario y tu contraseña.';
    } else {
        $resultado = login($usuario, $password);

        if ($resultado === true) {
            // Vuelve a la página que originó el intento de acceso
            $destino = $_SESSION['intento_url'] ?? '';
            unset($_SESSION['intento_url']);

            if ($destino !== '' && str_starts_with($destino, '/')) {
                $ruta = parse_url($destino, PHP_URL_PATH);
                $base = rtrim(parse_url(SITE_URL, PHP_URL_PATH), '/');
                if ($ruta !== null && str_starts_with($ruta, $base . '/')) {
                    $relativo = substr($ruta, strlen($base) + 1);
                    header('Location: ' . SITE_URL . '/' . ltrim($relativo, '/'));
                    exit;
                }
            }

            $rol = currentUser()['rol'] ?? 'lector';
            redirigir(puede('panel.acceder', ['rol' => $rol]) ? 'admin/index.php' : 'index.php');
        }

        if ($resultado === 'bloqueado') {
            $bloqueado = true;
        } else {
            $errores[] = 'Usuario o contraseña incorrectos.';
        }
    }
}

$pageTitle = 'Iniciar sesión';
abrirPagina();
?>

<div class="container auth-wrap">
    <div class="auth-card">

        <div class="auth-card__header">
            <h1>Acceso al sistema</h1>
            <p>Ingresá con tu cuenta institucional para gestionar documentación,
               proyectos y noticias.</p>
        </div>

        <?php if ($bloqueado): ?>
            <div class="alert alert-danger" role="alert">
                <strong>Demasiados intentos fallidos.</strong><br>
                Por seguridad el acceso quedó bloqueado temporalmente.
                Esperá unos minutos e intentá de nuevo.
            </div>
        <?php endif; ?>

        <?php if ($errores): ?>
            <div class="alert alert-danger" role="alert">
                <ul>
                    <?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login.php')) ?>" autocomplete="on" novalidate>
            <?= campoCSRF() ?>

            <?= campo('usuario', 'Usuario', 'text', [
                'valor' => $usuario,
                'requerido' => true,
                'max' => 50,
                'autocomplete' => 'username',
                'placeholder' => 'tu.usuario',
                'errores' => [],
            ]) ?>

            <?= campo('password', 'Contraseña', 'password', [
                'requerido' => true,
                'autocomplete' => 'current-password',
                'ayuda' => 'Mínimo 10 caracteres si tu cuenta fue creada desde el panel.',
            ]) ?>

            <button class="btn btn-primary btn-lg btn-block" type="submit">
                <?= icono('login', 17) ?> Ingresar
            </button>
        </form>

        <p class="auth-card__pie muted">
            ¿No tenés cuenta? Pedile acceso al administrador del sistema.
            Las contraseñas se guardan cifradas y nunca se almacenan en texto plano.
        </p>

    </div>
</div>

<?php cerrarPagina(); ?>
