<?php
// ============================================================
//  Mi cuenta — datos personales y cambio de contraseña
//  Archivo: frontend/cuenta.php
//  Requiere: sesión iniciada (cualquier rol)
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

requireLogin();

$yo       = currentUser();
$errores  = [];
$valores  = [
    'nombre'  => $yo['nombre'],
    'apellido'=> $yo['apellido'],
    'email'   => $yo['email'],
];

// ── Actualizar datos personales ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'datos') {
    verifyCsrf();

    $valores['nombre']   = saneaPOST('nombre', 80);
    $valores['apellido'] = saneaPOST('apellido', 80);
    $valores['email']    = saneaPOST('email', 190);

    $errores = validar($_POST, [
        'nombre'   => ['requerido' => true, 'min' => 2, 'max' => 80],
        'apellido' => ['requerido' => true, 'min' => 2, 'max' => 80],
        'email'    => ['requerido' => true, 'email' => true, 'max' => 190],
    ], ['nombre' => 'Nombre', 'apellido' => 'Apellido', 'email' => 'Correo']);

    if (!$errores) {
        // El usuario no se puede renombrar desde acá: sólo el admin lo
        // hace desde Usuarios (evita que dos cuentas queden iguales).
        getDB()->prepare(
            'UPDATE usuarios SET nombre = ?, apellido = ?, email = ? WHERE id = ?'
        )->execute([$valores['nombre'], $valores['apellido'], $valores['email'], $yo['id']]);

        logSeguridad('cuenta_editada', (string) $yo['id'],
            $valores['nombre'] . ' ' . $valores['apellido'] . ' <' . $valores['email'] . '>');

        redirigir('cuenta.php', 'success', 'Datos actualizados correctamente.');
    }
}

// ── Cambiar contraseña ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'password') {
    verifyCsrf();

    $actual  = (string) ($_POST['password_actual'] ?? '');
    $nueva   = (string) ($_POST['password_nueva'] ?? '');
    $confirm = (string) ($_POST['password_confirmar'] ?? '');

    $fallo = false;
    if ($actual === '' || $nueva === '' || $confirm === '') {
        $errores['password_nueva'] = 'Completá los tres campos.';
        $fallo = true;
    } else {
        $hashActual = dbValor('SELECT password FROM usuarios WHERE id = ?', [$yo['id']]);
        if (!$hashActual || !password_verify($actual, (string) $hashActual)) {
            $errores['password_actual'] = 'La contraseña actual no coincide.';
            $fallo = true;
        }
        if (!passwordValida($nueva)) {
            $errores['password_nueva'] = 'La contraseña debe tener al menos 10 caracteres, '
                . 'una letra y un dígito.';
            $fallo = true;
        } elseif ($nueva !== $confirm) {
            $errores['password_confirmar'] = 'Las dos contraseñas nuevas no coinciden.';
            $fallo = true;
        } elseif (password_verify($nueva, (string) $hashActual)) {
            $errores['password_nueva'] = 'La contraseña nueva debe ser distinta de la actual.';
            $fallo = true;
        }
    }

    if (!$fallo) {
        getDB()->prepare('UPDATE usuarios SET password = ? WHERE id = ?')
            ->execute([password_hash($nueva, PASSWORD_DEFAULT), $yo['id']]);

        logSeguridad('password_cambiada', (string) $yo['id'], 'cuenta propia');

        // Nueva contraseña = nuevo ID de sesión: corta cualquier
        // identificador viejo que un atacante pudiera conservar.
        session_regenerate_id(true);

        redirigir('cuenta.php', 'success', 'Contraseña actualizada correctamente.');
    }
}

$pageTitle = 'Mi cuenta';
$cssExtra  = 'admin.css';
abrirPagina();
?>

<div class="container container--form">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Mi cuenta' => null],
    ]) ?>

    <div class="seccion-cabecera">
        <div>
            <h1>Mi cuenta</h1>
            <p class="muted">Usuario <code><?= e($yo['usuario']) ?></code> ·
                <?= badgeRol($yo['rol']) ?></p>
        </div>
    </div>

    <div class="panel panel--form">
        <header class="panel__cabecera">
            <h2 class="panel__titulo">Datos personales</h2>
        </header>

        <?= pintarErrores($errores) ?>

        <form method="post" class="formulario" action="<?= e(url('cuenta.php')) ?>">
            <?= campoCSRF() ?>
            <input type="hidden" name="accion" value="datos">

            <div class="rejilla rejilla--2">
                <?= campo('nombre', 'Nombre', 'text', [
                    'valor' => $valores['nombre'], 'requerido' => true, 'max' => 80,
                    'errores' => $errores,
                ]) ?>
                <?= campo('apellido', 'Apellido', 'text', [
                    'valor' => $valores['apellido'], 'requerido' => true, 'max' => 80,
                    'errores' => $errores,
                ]) ?>
            </div>

            <?= campo('email', 'Correo electrónico', 'email', [
                'valor' => $valores['email'], 'requerido' => true, 'max' => 190,
                'errores' => $errores,
            ]) ?>

            <p class="form-ayuda">Tu nombre de usuario (<code><?= e($yo['usuario']) ?></code>)
                lo gestiona un administrador desde el panel.</p>

            <div class="acciones-formulario">
                <button class="btn btn-primary" type="submit">Guardar datos</button>
            </div>
        </form>
    </div>

    <div class="panel panel--form">
        <header class="panel__cabecera">
            <h2 class="panel__titulo">Cambiar contraseña</h2>
        </header>

        <form method="post" class="formulario" action="<?= e(url('cuenta.php')) ?>">
            <?= campoCSRF() ?>
            <input type="hidden" name="accion" value="password">

            <?= campo('password_actual', 'Contraseña actual', 'password', [
                'requerido' => true, 'errores' => $errores,
                'extra' => 'autocomplete="current-password"',
            ]) ?>

            <div class="rejilla rejilla--2">
                <?= campo('password_nueva', 'Nueva contraseña', 'password', [
                    'requerido' => true, 'errores' => $errores,
                    'ayuda' => 'Mínimo 10 caracteres, con letra y dígito.',
                    'extra' => 'autocomplete="new-password" minlength="10"',
                ]) ?>
                <?= campo('password_confirmar', 'Repetir contraseña', 'password', [
                    'requerido' => true, 'errores' => $errores,
                    'extra' => 'autocomplete="new-password" minlength="10"',
                ]) ?>
            </div>

            <div class="acciones-formulario">
                <button class="btn btn-primary" type="submit">Cambiar contraseña</button>
            </div>
        </form>
    </div>

</div>

<?php cerrarPagina(); ?>
