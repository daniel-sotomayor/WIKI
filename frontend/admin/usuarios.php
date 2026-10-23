<?php
// ============================================================
//  Listado de usuarios
//  Archivo: frontend/admin/usuarios.php
//  Requiere: capacidad `usuario.gestionar` (administrador)
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';

requierePermiso('usuario.gestionar');

$yo = currentUser();

// ── Activar / desactivar desde el listado (POST + PRG) ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'toggle_activo') {
    verifyCsrf();

    $objetivoId = entero($_POST['usuario_id'] ?? '', 0);
    $mensaje    = null;

    if ($objetivoId === (int) $yo['id']) {
        $mensaje = ['danger', 'No podés activar o desactivar tu propia cuenta desde aquí.'];
    } else {
        $objetivo = dbUnica('SELECT id, nombre, rol, activo FROM usuarios WHERE id = ? LIMIT 1',
                            [$objetivoId]);

        if (!$objetivo) {
            $mensaje = ['danger', 'Usuario no encontrado.'];
        } else {
            $nuevoEstado = $objetivo['activo'] ? 0 : 1;

            if (!$nuevoEstado && $objetivo['rol'] === 'admin'
                && contarAdminsActivos($objetivoId) === 0) {
                $mensaje = ['danger', 'No se puede desactivar al único administrador activo.'];
            } else {
                getDB()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')
                       ->execute([$nuevoEstado, $objetivoId]);
                logSeguridad('usuario_cambio_estado', (string) $yo['id'],
                    'usuario #' . $objetivoId . ' → ' . ($nuevoEstado ? 'activo' : 'inactivo'));
                $mensaje = ['success', $nuevoEstado
                    ? 'Usuario «' . $objetivo['nombre'] . '» activado.'
                    : 'Usuario «' . $objetivo['nombre'] . '» desactivado.'];
            }
        }
    }

    redirigir('admin/usuarios.php', $mensaje[0], $mensaje[1]);
}

$usuarios = getDB()->query(
    'SELECT id, nombre, apellido, usuario, email, rol, activo, ultimo_acceso, creado_en
       FROM usuarios ORDER BY activo DESC, apellido, nombre'
)->fetchAll();

$pageTitle = 'Gestión de usuarios';
$cssExtra  = 'admin.css';
abrirPagina();
?>

<div class="container container--panel">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Panel' => 'admin/index.php'],
        ['Usuarios' => null],
    ]) ?>

    <div class="seccion-cabecera">
        <div>
            <h1>Gestión de usuarios</h1>
            <p class="muted">Creá, editá, cambiá roles y activá o desactivá cuentas.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('admin/usuario_form.php')) ?>">
            <?= icono('plus', 16) ?> Nuevo usuario
        </a>
    </div>

    <section class="panel panel--sin-titulo">
        <div class="tabla-envoltura">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Usuario</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Último acceso</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): $esYo = (int) $u['id'] === (int) $yo['id']; ?>
                    <tr>
                        <td>
                            <strong><?= e(nombreCompleto($u)) ?></strong>
                            <?php if ($esYo): ?><small class="bloque">(tu cuenta)</small><?php endif; ?>
                        </td>
                        <td><?= e($u['usuario']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= badgeRol($u['rol']) ?></td>
                        <td><?= badgeEstado($u['activo'] ? 'activo' : 'inactivo') ?></td>
                        <td class="muted"><?= $u['ultimo_acceso']
                            ? e(fecha($u['ultimo_acceso'], 'd/m/Y H:i'))
                            : 'nunca' ?></td>
                        <td class="celda-acciones">
                            <a class="btn btn-sm btn-outline"
                               href="<?= e(url('admin/usuario_form.php?id=' . (int) $u['id'])) ?>">
                                <?= icono('edit', 14) ?> Editar
                            </a>

                            <?php if (!$esYo): ?>
                            <form method="post" action="<?= e(url('admin/usuarios.php')) ?>"
                                  data-confirm-submit="<?= e(($u['activo'] ? 'Desactivar' : 'Activar')
                                      . ' a ' . nombreCompleto($u) . '?') ?>">
                                <?= campoCSRF() ?>
                                <input type="hidden" name="accion" value="toggle_activo">
                                <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-sm <?= $u['activo'] ? 'btn-danger' : 'btn-outline' ?>"
                                        type="submit">
                                    <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<?php cerrarPagina(); ?>
