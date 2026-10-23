<?php
// ============================================================
//  Alta / edición de usuarios
//  Archivo: frontend/admin/usuario_form.php
//  Requiere: capacidad `usuario.gestionar` (administrador)
//
//  Protecciones:
//   · un admin no puede cambiar su propio rol ni desactivarse
//     (se evitaría el "único administrador" o se auto-bloquearía)
//   · no se puede dejar el sistema sin al menos un admin activo
//   · la contraseña se guarda con password_hash() (bcrypt/argon2)
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';

requierePermiso('usuario.gestionar');

const ROLES_USUARIO = [
    'lector'     => 'Lector (sólo lectura)',
    'editor'     => 'Editor (crea proyectos y artículos)',
    'supervisor' => 'Supervisor (modera noticias)',
    'admin'      => 'Administrador (control total)',
];

$yo          = currentUser();
$usuarioId   = entero($_GET['id'] ?? $_POST['usuario_id'] ?? '', 0);
$esPropia    = $usuarioId > 0 && $usuarioId === (int) $yo['id'];
$objetivo    = $usuarioId > 0
    ? dbUnica('SELECT * FROM usuarios WHERE id = ? LIMIT 1', [$usuarioId])
    : null;

if ($usuarioId > 0 && !$objetivo) {
    paginaNoEncontrada('usuario');
}

$errores = [];
$valores = [
    'nombre'   => $objetivo['nombre']   ?? '',
    'apellido' => $objetivo['apellido'] ?? '',
    'usuario'  => $objetivo['usuario']  ?? '',
    'email'    => $objetivo['email']    ?? '',
    'rol'      => $objetivo['rol']      ?? 'lector',
    'activo'   => isset($objetivo['activo']) ? (int) $objetivo['activo'] : 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $valores['nombre']   = saneaPOST('nombre', 100);
    $valores['apellido'] = saneaPOST('apellido', 100);
    $valores['usuario']  = saneaPOST('usuario', 50);
    $valores['email']    = saneaPOST('email', 150);
    $password            = (string) ($_POST['password'] ?? '');
    if (is_array($password)) $password = '';
    $valores['rol']    = array_key_exists($_POST['rol'] ?? '', ROLES_USUARIO)
        ? $_POST['rol'] : 'lector';
    $valores['activo'] = isset($_POST['activo']) ? 1 : 0;

    // En la cuenta propia el rol y el estado quedan fijos
    if ($esPropia) {
        $valores['rol']    = $objetivo['rol'];
        $valores['activo'] = (int) $objetivo['activo'];
    }

    $errores = validar($_POST, [
        'nombre'   => ['requerido' => true, 'min' => 2, 'max' => 100],
        'apellido' => ['requerido' => true, 'min' => 2, 'max' => 100],
        'usuario'  => ['requerido' => true, 'min' => 4, 'max' => 50, 'letrasnum' => true],
        'email'    => ['requerido' => true, 'email' => true, 'max' => 150],
    ], [
        'nombre' => 'Nombre', 'apellido' => 'Apellido',
        'usuario' => 'Usuario (login)', 'email' => 'Correo',
    ]);

    // Contraseña: obligatoria al crear, opcional al editar
    if ($password !== '' && !passwordValida($password)) {
        $errores['password'] = 'La contraseña debe tener al menos 10 caracteres, '
            . 'con al menos una letra y un número.';
    }
    if ($objetivo === null && $password === '') {
        $errores['password'] = 'Definí una contraseña para la nueva cuenta.';
    }

    if (!$errores) {
        $db = getDB();

        // Unicidad (excluyendo el propio registro)
        $dup = $db->prepare('SELECT id FROM usuarios WHERE (usuario = ? OR email = ?) AND id <> ? LIMIT 1');
        $dup->execute([$valores['usuario'], $valores['email'], $usuarioId]);

        if ($dup->fetch()) {
            $errores['usuario'] = 'Ese nombre de usuario o correo ya está en uso.';
        } elseif ($objetivo && !$esPropia && $objetivo['rol'] === 'admin'
                  && ($valores['rol'] !== 'admin' || !$valores['activo'])
                  && contarAdminsActivos((int) $objetivo['id']) === 0) {
            $errores['rol'] = 'No se puede quitar el rol ni desactivar al único '
                            . 'administrador activo del sistema.';
        } else {
            $hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

            try {
                if ($objetivo) {
                    if ($hash !== null) {
                        $st = $db->prepare(
                            'UPDATE usuarios
                                SET nombre=?, apellido=?, usuario=?, email=?,
                                    password=?, rol=?, activo=?
                              WHERE id=?'
                        );
                        $st->execute([
                            $valores['nombre'], $valores['apellido'], $valores['usuario'],
                            $valores['email'], $hash, $valores['rol'], $valores['activo'],
                            $objetivo['id'],
                        ]);
                    } else {
                        $st = $db->prepare(
                            'UPDATE usuarios
                                SET nombre=?, apellido=?, usuario=?, email=?, rol=?, activo=?
                              WHERE id=?'
                        );
                        $st->execute([
                            $valores['nombre'], $valores['apellido'], $valores['usuario'],
                            $valores['email'], $valores['rol'], $valores['activo'],
                            $objetivo['id'],
                        ]);
                    }
                    logSeguridad('usuario_editado', (string) $yo['id'], 'usuario #' . $objetivo['id']);
                    $texto = 'Usuario actualizado correctamente.';
                } else {
                    $st = $db->prepare(
                        'INSERT INTO usuarios
                            (nombre, apellido, usuario, email, password, rol, activo)
                         VALUES (?,?,?,?,?,?,?)'
                    );
                    $st->execute([
                        $valores['nombre'], $valores['apellido'], $valores['usuario'],
                        $valores['email'], $hash, $valores['rol'], $valores['activo'],
                    ]);
                    logSeguridad('usuario_creado', (string) $yo['id'],
                        'usuario #' . $db->lastInsertId() . ' (' . $valores['rol'] . ')');
                    $texto = 'Usuario creado correctamente.';
                }

                redirigir('admin/usuarios.php', 'success', $texto);
            } catch (Throwable $e) {
                error_log('[usuario_form] ' . $e->getMessage());
                $errores['nombre'] = 'No se pudo guardar el usuario (posible duplicado).';
            }
        }
    }
}

$pageTitle = $objetivo ? 'Editar usuario' : 'Nuevo usuario';
$cssExtra  = 'admin.css';
abrirPagina();
?>

<div class="container container--form">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Panel' => 'admin/index.php'],
        ['Usuarios' => 'admin/usuarios.php'],
        [$objetivo ? 'Editar usuario' : 'Nuevo usuario' => null],
    ]) ?>

    <div class="panel panel--form">
        <header class="panel__cabecera">
            <h1><?= $objetivo ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
        </header>

        <?php if ($esPropia): ?>
            <div class="alert alert-info">
                Estás editando tu propia cuenta: por seguridad, el rol y el estado
                (activo/inactivo) no se pueden cambiar desde aquí.
            </div>
        <?php endif; ?>

        <?= pintarErrores($errores) ?>

        <form method="post" class="formulario"
              action="<?= e(url('admin/usuario_form.php' . ($objetivo ? '?id=' . (int) $objetivo['id'] : ''))) ?>">
            <?= campoCSRF() ?>
            <?php if ($objetivo): ?>
                <input type="hidden" name="usuario_id" value="<?= (int) $objetivo['id'] ?>">
            <?php endif; ?>

            <div class="rejilla rejilla--2">
                <?= campo('nombre', 'Nombre', 'text', [
                    'valor' => $valores['nombre'], 'requerido' => true, 'max' => 100,
                ]) ?>
                <?= campo('apellido', 'Apellido', 'text', [
                    'valor' => $valores['apellido'], 'requerido' => true, 'max' => 100,
                ]) ?>
            </div>

            <div class="rejilla rejilla--2">
                <?= campo('usuario', 'Usuario (login)', 'text', [
                    'valor' => $valores['usuario'], 'requerido' => true, 'max' => 50,
                    'ayuda' => 'Letras, números, punto, guion y guion bajo.',
                    'extra' => 'autocomplete="username"',
                ]) ?>
                <?= campo('email', 'Correo electrónico', 'email', [
                    'valor' => $valores['email'], 'requerido' => true, 'max' => 150,
                    'extra' => 'autocomplete="email"',
                ]) ?>
            </div>

            <?= campo('password', $objetivo ? 'Nueva contraseña' : 'Contraseña', 'password', [
                'requerido' => $objetivo === null,
                'extra' => 'autocomplete="new-password" minlength="10"',
                'ayuda' => $objetivo
                    ? 'Dejalo vacío para no cambiar la contraseña actual.'
                    : 'Mínimo 10 caracteres, con letras y números.',
            ]) ?>

            <div class="rejilla rejilla--2">
                <div class="form-group">
                    <label class="form-label" for="f_rol">Rol</label>
                    <?php if ($esPropia): ?>
                        <select class="form-control" id="f_rol" name="rol" disabled>
                            <?php foreach (ROLES_USUARIO as $val => $txt): ?>
                                <option value="<?= e($val) ?>" <?= $val === $valores['rol'] ? 'selected' : '' ?>>
                                    <?= e($txt) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="rol" value="<?= e($valores['rol']) ?>">
                    <?php else: ?>
                        <select class="form-control" id="f_rol" name="rol">
                            <?php foreach (ROLES_USUARIO as $val => $txt): ?>
                                <option value="<?= e($val) ?>" <?= $val === $valores['rol'] ? 'selected' : '' ?>>
                                    <?= e($txt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <p class="form-ayuda">Aplica el principio de mínimo privilegio: cada rol sólo
                        recibe los permisos que necesita.</p>
                </div>

                <div class="form-group">
                    <label class="casilla" for="f_activo">
                        <input type="checkbox" id="f_activo" name="activo" value="1"
                            <?= $valores['activo'] ? 'checked' : '' ?>
                            <?= $esPropia ? 'disabled' : '' ?>>
                        <span>Cuenta activa (puede iniciar sesión)</span>
                    </label>
                    <?php if ($esPropia): ?>
                        <input type="hidden" name="activo" value="1">
                    <?php endif; ?>
                </div>
            </div>

            <div class="acciones-formulario">
                <button class="btn btn-primary btn-lg" type="submit">
                    <?= $objetivo ? 'Guardar cambios' : 'Crear usuario' ?>
                </button>
                <a class="btn btn-ghost btn-lg" href="<?= e(url('admin/usuarios.php')) ?>">Cancelar</a>
            </div>
        </form>
    </div>

</div>

<?php cerrarPagina(); ?>
