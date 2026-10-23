<?php
// ============================================================
//  Moderación de noticias — aprueba o rechaza borradores
//  Archivo: frontend/noticias_moderar.php
//  Requiere: rol supervisor o administrador
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

requireLogin();
requierePermiso('noticia.moderar');

$usuario = currentUser();
$errores = [];

// ── Acciones sobre una noticia pendiente ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $id     = entero($_POST['noticia_id'] ?? '', 0);
    $accion = (string) ($_POST['accion'] ?? '');
    $razon  = saneaPOST('razon_rechazo', 500);

    if ($id <= 0 || !in_array($accion, ['aprobar', 'rechazar'], true)) {
        $errores[] = 'Solicitud inválida.';
    } else {
        // Se selecciona por id + estado pendiente: un atacante no puede
        // "re-aprobar" algo ya publicado ni operar sobre notas ajenas.
        $noticia = dbUnica(
            "SELECT id, titulo FROM noticias WHERE id = ? AND estado = 'pendiente' LIMIT 1",
            [$id]
        );

        if (!$noticia) {
            $errores[] = 'La noticia ya fue revisada o no existe.';
        } elseif ($accion === 'aprobar') {
            getDB()->prepare(
                "UPDATE noticias
                    SET estado = 'publicada', aprobado_por = ?, fecha_publicacion = NOW()
                  WHERE id = ? AND estado = 'pendiente'"
            )->execute([$usuario['id'], $id]);
            logSeguridad('noticia_aprobada', (string) $usuario['id'], 'noticia #' . $id);
            redirigir('noticias_moderar.php', 'success', 'Noticia aprobada y publicada.');
        } else {
            getDB()->prepare(
                "UPDATE noticias
                    SET estado = 'rechazada', aprobado_por = ?, razon_rechazo = ?
                  WHERE id = ? AND estado = 'pendiente'"
            )->execute([$usuario['id'], $razon !== '' ? $razon : null, $id]);
            logSeguridad('noticia_rechazada', (string) $usuario['id'], 'noticia #' . $id);
            redirigir('noticias_moderar.php', 'success', 'Noticia rechazada.');
        }
    }
}

// ── Cola de revisión ────────────────────────────────────────
$pendientes = getDB()->query(
    "SELECT n.id, n.titulo, n.slug, n.resumen, n.categoria, n.creado_en,
            u.nombre AS autor_nombre, u.apellido AS autor_apellido
       FROM noticias n
  LEFT JOIN usuarios u ON u.id = n.autor_id
      WHERE n.estado = 'pendiente'
   ORDER BY n.creado_en ASC"
)->fetchAll();

// ── Historial reciente de revisiones ────────────────────────
$historial = getDB()->query(
    "SELECT n.id, n.titulo, n.slug, n.estado, n.razon_rechazo,
            n.fecha_publicacion, n.creado_en,
            a.nombre AS autor_nombre,
            r.nombre AS revisor_nombre, r.apellido AS revisor_apellido
       FROM noticias n
  LEFT JOIN usuarios a ON a.id = n.autor_id
  LEFT JOIN usuarios r ON r.id = n.aprobado_por
      WHERE n.estado IN ('publicada','rechazada')
   ORDER BY n.fecha_publicacion DESC, n.creado_en DESC
      LIMIT 10"
)->fetchAll();

$pageTitle = 'Moderación de noticias';
$cssExtra  = '';
abrirPagina();
?>

<div class="container">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Noticias' => 'noticias.php'],
        ['Moderación' => null],
    ]) ?>

    <div class="seccion-cabecera">
        <div>
            <h1>Moderación de noticias</h1>
            <p class="muted">Revisá y aprobá o rechazá las noticias enviadas por los editores.</p>
        </div>
        <?php if (puede('noticia.crear')): ?>
            <a class="btn btn-primary" href="<?= e(url('noticias_crear.php')) ?>">
                <?= icono('plus', 16) ?> Nueva noticia
            </a>
        <?php endif; ?>
    </div>

    <?= pintarErrores($errores) ?>

    <section class="panel">
        <header class="panel__cabecera">
            <h2 class="panel__titulo">Pendientes de revisión</h2>
            <span class="contador" aria-label="<?= count($pendientes) ?> pendientes">
                <?= count($pendientes) ?>
            </span>
        </header>

        <?php if (!$pendientes): ?>
            <p class="vacio">No hay noticias pendientes por revisar.</p>
        <?php else: ?>
            <ul class="lista-moderacion">
                <?php foreach ($pendientes as $n): ?>
                <li class="tarjeta-moderacion">
                    <div class="tarjeta-moderacion__cabecera">
                        <div>
                            <h3><?= e($n['titulo']) ?></h3>
                            <p class="muted">
                                Por <strong><?= e(trim(($n['autor_nombre'] ?? '') . ' ' . ($n['autor_apellido'] ?? ''))) ?: 'Desconocido' ?></strong>
                                · <?= e(fecha($n['creado_en'], 'd/m/Y H:i')) ?>
                                <?php if ($n['categoria']): ?>
                                    · <span class="chip chip--cat"><?= e($n['categoria']) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?= badgeEstado('pendiente') ?>
                    </div>

                    <?php if ($n['resumen']): ?>
                        <p class="tarjeta-moderacion__resumen"><?= e($n['resumen']) ?></p>
                    <?php endif; ?>

                    <div class="tarjeta-moderacion__acciones">
                        <form method="post" action="<?= e(url('noticias_moderar.php')) ?>"
                              data-confirm-submit="¿Aprobar y publicar esta noticia?">
                            <?= campoCSRF() ?>
                            <input type="hidden" name="noticia_id" value="<?= (int) $n['id'] ?>">
                            <input type="hidden" name="accion" value="aprobar">
                            <button class="btn btn-primary btn-sm" type="submit">
                                <?= icono('check', 15) ?> Aprobar
                            </button>
                        </form>

                        <form method="post" action="<?= e(url('noticias_moderar.php')) ?>"
                              class="formulario-rechazo"
                              data-confirm-submit="¿Rechazar esta noticia?">
                            <?= campoCSRF() ?>
                            <input type="hidden" name="noticia_id" value="<?= (int) $n['id'] ?>">
                            <input type="hidden" name="accion" value="rechazar">
                            <label class="sr-only" for="razon_<?= (int) $n['id'] ?>">
                                Razón del rechazo
                            </label>
                            <input class="form-control form-control--sm" id="razon_<?= (int) $n['id'] ?>"
                                   type="text" name="razon_rechazo" maxlength="500"
                                   placeholder="Razón del rechazo (opcional)…">
                            <button class="btn btn-danger btn-sm" type="submit">
                                <?= icono('close', 15) ?> Rechazar
                            </button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <header class="panel__cabecera">
            <h2 class="panel__titulo">Historial reciente</h2>
        </header>

        <?php if (!$historial): ?>
            <p class="vacio">Todavía no hay noticias revisadas.</p>
        <?php else: ?>
            <div class="tabla-envoltura">
                <table class="tabla-datos">
                    <thead>
                        <tr>
                            <th scope="col">Título</th>
                            <th scope="col">Autor</th>
                            <th scope="col">Revisado por</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $h): ?>
                        <tr>
                            <td>
                                <?php if ($h['estado'] === 'publicada'): ?>
                                    <a href="<?= e(url('noticia.php?slug=' . urlencode($h['slug']))) ?>">
                                        <?= e($h['titulo']) ?>
                                    </a>
                                <?php else: ?>
                                    <strong><?= e($h['titulo']) ?></strong>
                                <?php endif; ?>
                                <?php if ($h['razon_rechazo']): ?>
                                    <small class="bloque">
                                        <?= e($h['razon_rechazo']) ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?= e(trim(($h['autor_nombre'] ?? '') . ' ' . ($h['autor_apellido'] ?? ''))) ?: '—' ?></td>
                            <td><?= e(trim(($h['revisor_nombre'] ?? '') . ' ' . ($h['revisor_apellido'] ?? ''))) ?: '—' ?></td>
                            <td><?= badgeEstado($h['estado']) ?></td>
                            <td class="muted"><?= $h['fecha_publicacion']
                                ? e(fecha($h['fecha_publicacion'], 'd/m/Y H:i'))
                                : e(fecha($h['creado_en'], 'd/m/Y H:i')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>

<?php cerrarPagina(); ?>
