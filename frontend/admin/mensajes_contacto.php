<?php
// ============================================================
//  Mensajes del formulario de contacto
//  Archivo: frontend/admin/mensajes_contacto.php
//  Requiere: capacidad `mensaje.gestionar` (administrador)
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';

requierePermiso('mensaje.gestionar');

$yo = currentUser();

// ── Acciones sobre un mensaje ────────────────────────────────
$acciones = [
    'toggle_leido'  => ['leido',    'Marcado como leído / no leído'],
    'toggle_enviado'=> ['enviado_ok', 'Estado de correo ajustado'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($acciones[$_POST['accion'] ?? ''])) {
    verifyCsrf();

    [$columna] = $acciones[$_POST['accion']];
    $id = entero($_POST['mensaje_id'] ?? '', 0);

    $mensaje = dbUnica("SELECT id, $columna AS valor FROM mensajes_contacto WHERE id = ? LIMIT 1", [$id]);

    if ($mensaje) {
        getDB()->prepare("UPDATE mensajes_contacto SET $columna = ? WHERE id = ?")
               ->execute([$mensaje['valor'] ? 0 : 1, $id]);
    }

    redirigir('admin/mensajes_contacto.php', 'success', 'Mensajes actualizados.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    verifyCsrf();

    $id = entero($_POST['mensaje_id'] ?? '', 0);
    getDB()->prepare('DELETE FROM mensajes_contacto WHERE id = ?')->execute([$id]);
    logSeguridad('mensaje_eliminado', (string) $yo['id'], 'mensaje #' . $id);

    redirigir('admin/mensajes_contacto.php', 'success', 'Mensaje eliminado.');
}

// ── Listado paginado (sin leer primero) ──────────────────────
$totalNoLeidos = (int) getDB()->query(
    'SELECT COUNT(*) FROM mensajes_contacto WHERE leido = 0'
)->fetchColumn();

$total = (int) getDB()->query('SELECT COUNT(*) FROM mensajes_contacto')->fetchColumn();
$pag   = paginar($total, POR_PAGINA_ADMIN);

$st = getDB()->prepare(
    'SELECT * FROM mensajes_contacto
      ORDER BY leido ASC, creado_en DESC
      LIMIT :n OFFSET :o'
);
$st->bindValue(':n', $pag['por_pagina'], PDO::PARAM_INT);
$st->bindValue(':o', $pag['offset'], PDO::PARAM_INT);
$st->execute();
$mensajes = $st->fetchAll();

$pageTitle = 'Mensajes de contacto';
$cssExtra  = 'admin.css';
abrirPagina();
?>

<div class="container container--panel">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Panel' => 'admin/index.php'],
        ['Mensajes de contacto' => null],
    ]) ?>

    <div class="seccion-cabecera">
        <div>
            <h1>Mensajes de contacto</h1>
            <p class="muted">Mensajes recibidos desde el formulario de «Quiénes somos».</p>
        </div>
        <?php if ($totalNoLeidos): ?>
            <span class="chip chip--aviso"><?= $totalNoLeidos ?> sin leer</span>
        <?php endif; ?>
    </div>

    <?php if (!$mensajes): ?>
        <div class="alert alert-info">Todavía no se recibió ningún mensaje de contacto.</div>
    <?php else: ?>

        <ul class="lista-mensajes">
            <?php foreach ($mensajes as $m): ?>
            <li class="tarjeta-mensaje<?= $m['leido'] ? '' : ' tarjeta-mensaje--nuevo' ?>">
                <header class="tarjeta-mensaje__cabecera">
                    <div>
                        <p class="tarjeta-mensaje__nombre">
                            <?= e($m['nombre']) ?>
                            <?php if (!$m['leido']): ?>
                                <span class="chip chip--aviso">Nuevo</span>
                            <?php endif; ?>
                        </p>
                        <p class="muted">
                            <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
                            · <?= e(fecha($m['creado_en'], 'd/m/Y H:i')) ?>
                            <?php if ($m['ip']): ?> · IP: <?= e($m['ip']) ?><?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <?php if ($m['enviado_ok']): ?>
                            <span class="status-badge status-activo">Correo enviado</span>
                        <?php else: ?>
                            <span class="status-badge status-rechazada">Correo no enviado</span>
                        <?php endif; ?>
                    </div>
                </header>

                <p class="tarjeta-mensaje__cuerpo"><?= e($m['mensaje']) ?></p>

                <div class="tarjeta-mensaje__acciones">
                    <form method="post" action="<?= e(url('admin/mensajes_contacto.php')) ?>">
                        <?= campoCSRF() ?>
                        <input type="hidden" name="accion" value="toggle_leido">
                        <input type="hidden" name="mensaje_id" value="<?= (int) $m['id'] ?>">
                        <button class="btn btn-sm btn-outline" type="submit">
                            <?= $m['leido'] ? 'Marcar como no leído' : 'Marcar como leído' ?>
                        </button>
                    </form>

                    <form method="post" action="<?= e(url('admin/mensajes_contacto.php')) ?>"
                          title="El estado automático del correo no siempre es exacto: corregilo según lo que veas en tu bandeja.">
                        <?= campoCSRF() ?>
                        <input type="hidden" name="accion" value="toggle_enviado">
                        <input type="hidden" name="mensaje_id" value="<?= (int) $m['id'] ?>">
                        <button class="btn btn-sm btn-outline" type="submit">
                            <?= $m['enviado_ok'] ? 'Marcar como no enviado' : 'Marcar como enviado' ?>
                        </button>
                    </form>

                    <a class="btn btn-sm btn-outline" href="mailto:<?= e($m['email']) ?>">
                        <?= icono('mail', 14) ?> Responder
                    </a>

                    <form method="post" action="<?= e(url('admin/mensajes_contacto.php')) ?>"
                          data-confirm-submit="¿Eliminar este mensaje? No se puede deshacer.">
                        <?= campoCSRF() ?>
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="mensaje_id" value="<?= (int) $m['id'] ?>">
                        <button class="btn btn-sm btn-danger" type="submit">
                            <?= icono('trash', 14) ?> Eliminar
                        </button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <p class="muted"><?= e(resumenPaginacion($pag)) ?></p>
        <?= htmlPaginacion($pag) ?>

    <?php endif; ?>

</div>

<?php cerrarPagina(); ?>
