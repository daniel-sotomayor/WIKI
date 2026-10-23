<?php
// ============================================================
//  Historial de versiones de un artículo + reversión
//  Archivo: frontend/admin/articulo_historial.php
//  Requiere: capacidad `articulo.historial`
//
//  Cada vez que se edita el contenido, la versión anterior queda
//  archivada en `historial_versiones`. Revertir guarda primero el
//  estado actual, de modo que nunca se pierde trabajo.
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';

requierePermiso('articulo.historial');

$yo        = currentUser();
$articuloId = entero($_GET['id'] ?? $_POST['articulo_id'] ?? '', 0);

$articulo = dbUnica(
    'SELECT a.*, p.titulo AS proyecto_titulo, p.slug AS proyecto_slug
       FROM articulos a
       JOIN proyectos p ON p.id = a.proyecto_id
      WHERE a.id = ? LIMIT 1',
    [$articuloId]
);

if (!$articulo) {
    paginaNoEncontrada('articulo');
}

// ── Revertir a una versión anterior ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'revertir') {
    verifyCsrf();

    $historialId = entero($_POST['historial_id'] ?? '', 0);

    $version = dbUnica(
        'SELECT * FROM historial_versiones WHERE id = ? AND articulo_id = ? LIMIT 1',
        [$historialId, $articuloId]
    );

    if (!$version) {
        redirigir('admin/articulo_historial.php?id=' . $articuloId, 'danger',
                  'La versión indicada no existe.');
    }

    try {
        $db = getDB();

        // Primero se archiva el estado actual (nunca se pierde)
        $db->prepare(
            'INSERT INTO historial_versiones
                (articulo_id, contenido, version, editado_por)
             VALUES (?,?,?,?)'
        )->execute([$articulo['id'], $articulo['contenido'], $articulo['version'], $yo['id']]);

        $nuevaVersion = (int) $articulo['version'] + 1;

        $db->prepare('UPDATE articulos SET contenido=?, version=? WHERE id=?')
           ->execute([$version['contenido'], $nuevaVersion, $articulo['id']]);

        logSeguridad('articulo_revertido', (string) $yo['id'],
            'articulo #' . $articulo['id'] . ' → v' . $version['version'] . ' (ahora v' . $nuevaVersion . ')');

        redirigir('admin/articulo_historial.php?id=' . $articuloId, 'success',
            'Se restauró la versión v' . $version['version'] . ' (ahora es la v' . $nuevaVersion . ').');
    } catch (Throwable $e) {
        error_log('[articulo_historial] ' . $e->getMessage());
        redirigir('admin/articulo_historial.php?id=' . $articuloId, 'danger',
                  'No se pudo revertir la versión.');
    }
}

$st = getDB()->prepare(
    'SELECT h.id, h.contenido, h.version, h.editado_en, u.nombre AS editor
       FROM historial_versiones h
  LEFT JOIN usuarios u ON u.id = h.editado_por
      WHERE h.articulo_id = ?
   ORDER BY h.version DESC'
);
$st->execute([$articuloId]);
$versiones = $st->fetchAll();

$pageTitle = 'Historial — ' . $articulo['titulo'];
$cssExtra  = 'admin.css';
abrirPagina();
?>

<div class="container container--panel">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        [$articulo['proyecto_titulo'] => 'proyecto.php?slug=' . urlencode($articulo['proyecto_slug'])],
        [$articulo['titulo'] => 'articulo.php?slug=' . urlencode($articulo['slug'])],
        ['Historial' => null],
    ]) ?>

    <div class="seccion-cabecera">
        <div>
            <h1>Historial de versiones</h1>
            <p class="muted"><?= e($articulo['titulo']) ?> ·
                Versión actual: <strong>v<?= (int) $articulo['version'] ?></strong></p>
        </div>
        <?php if (puede('articulo.editar')): ?>
            <a class="btn btn-outline btn-sm"
               href="<?= e(url('admin/articulo_form.php?id=' . $articuloId)) ?>">
                ← Volver a editar
            </a>
        <?php endif; ?>
    </div>

    <!-- Versión actual -->
    <section class="panel panel--version-actual">
        <header class="panel__cabecera">
            <h2 class="panel__titulo">★ Versión actual — v<?= (int) $articulo['version'] ?></h2>
            <span class="muted">Actualizado <?= e(fecha($articulo['actualizado'], 'd/m/Y H:i')) ?></span>
        </header>
        <details class="detalle-version">
            <summary>Ver contenido completo</summary>
            <div class="article-body"><?= sanearHTML($articulo['contenido']) ?></div>
        </details>
    </section>

    <section class="panel">
        <header class="panel__cabecera">
            <h2 class="panel__titulo">Versiones anteriores</h2>
            <span class="contador"><?= count($versiones) ?></span>
        </header>

        <?php if (!$versiones): ?>
            <p class="vacio">Este artículo todavía no tiene versiones anteriores. Se generará una
                automáticamente la próxima vez que edites el contenido.</p>
        <?php else: ?>
            <ul class="lista-versiones">
                <?php foreach ($versiones as $v): ?>
                <li class="tarjeta-version">
                    <div class="tarjeta-version__cabecera">
                        <div>
                            <span class="chip chip--version">v<?= (int) $v['version'] ?></span>
                            <p class="muted">
                                Editado por <strong><?= e($v['editor'] ?? 'Desconocido') ?></strong>
                                · <?= e(fecha($v['editado_en'], 'd/m/Y H:i')) ?>
                            </p>
                        </div>

                        <?php if (puede('articulo.editar')): ?>
                        <form method="post"
                              action="<?= e(url('admin/articulo_historial.php?id=' . $articuloId)) ?>"
                              data-confirm-submit="¿Restaurar el contenido de la v<?= (int) $v['version'] ?>? La versión actual quedará archivada antes de sobreescribirse.">
                            <?= campoCSRF() ?>
                            <input type="hidden" name="accion" value="revertir">
                            <input type="hidden" name="articulo_id" value="<?= $articuloId ?>">
                            <input type="hidden" name="historial_id" value="<?= (int) $v['id'] ?>">
                            <button class="btn btn-sm btn-outline" type="submit">
                                Revertir a esta versión
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <details class="detalle-version">
                        <summary>Ver contenido de esta versión</summary>
                        <div class="article-body article-body--compact">
                            <?= sanearHTML($v['contenido']) ?>
                        </div>
                    </details>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</div>

<?php cerrarPagina(); ?>
