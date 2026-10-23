<?php
// ============================================================
//  Seguimiento de proyecto — hitos y avances
//  Archivo: frontend/admin/hito_form.php
//  Requiere: capacidad `hito.gestionar` (editor o superior)
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';

requierePermiso('hito.gestionar');

$proyectoId = entero($_GET['proyecto_id'] ?? $_POST['proyecto_id'] ?? '', 0);
$proyecto   = dbUnica('SELECT id, titulo, slug FROM proyectos WHERE id = ? LIMIT 1', [$proyectoId]);

if (!$proyecto) {
    paginaNoEncontrada('proyecto');
}

$yo     = currentUser();
$errores = [];

// ── Eliminar hito ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    verifyCsrf();

    $hitoId = entero($_POST['hito_id'] ?? '', 0);

    // El WHERE siempre incluye proyecto_id: un id ajeno a este proyecto
    // no puede borrarse manipulando el formulario.
    $st = getDB()->prepare('DELETE FROM hitos WHERE id = ? AND proyecto_id = ?');
    $st->execute([$hitoId, $proyectoId]);

    logSeguridad('hito_eliminado', (string) $yo['id'], 'hito #' . $hitoId);

    redirigir("admin/hito_form.php?proyecto_id=$proyectoId", 'success',
        $st->rowCount() ? 'Hito eliminado.' : 'El hito no existe o no pertenece a este proyecto.');
}

// ── Crear / editar hito ──────────────────────────────────────
$editar = null;
if (isset($_GET['id'])) {
    $editar = dbUnica(
        'SELECT * FROM hitos WHERE id = ? AND proyecto_id = ? LIMIT 1',
        [entero($_GET['id'], 0), $proyectoId]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? 'guardar') === 'guardar') {
    verifyCsrf();

    $hitoId    = entero($_POST['hito_id'] ?? '', 0);
    $titulo    = saneaPOST('titulo', 200);
    $descripcion = saneaPOST('descripcion', 2000);
    $porcentaje = entero($_POST['porcentaje_avance'] ?? '', 0);
    $fecha      = saneaPOST('fecha', 10);

    $errores = validar($_POST, [
        'titulo'    => ['requerido' => true, 'min' => 3, 'max' => 200],
        'fecha'     => ['requerido' => true, 'fecha' => true],
        'descripcion' => ['max' => 2000],
    ], ['titulo' => 'Título', 'fecha' => 'Fecha', 'descripcion' => 'Descripción']);

    if (!$errores) {
        $porcentaje = max(0, min(100, $porcentaje));

        try {
            $db = getDB();

            if ($hitoId > 0) {
                $db->prepare(
                    'UPDATE hitos
                        SET titulo=?, descripcion=?, porcentaje_avance=?, fecha=?
                      WHERE id=? AND proyecto_id=?'
                )->execute([$titulo, $descripcion, $porcentaje, $fecha, $hitoId, $proyectoId]);
                logSeguridad('hito_editado', (string) $yo['id'], 'hito #' . $hitoId);
            } else {
                $db->prepare(
                    'INSERT INTO hitos
                        (proyecto_id, titulo, descripcion, porcentaje_avance, fecha, creado_por)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$proyectoId, $titulo, $descripcion, $porcentaje, $fecha, $yo['id']]);
                logSeguridad('hito_creado', (string) $yo['id'],
                    'proyecto #' . $proyectoId . ' → ' . $titulo);
            }

            redirigir("admin/hito_form.php?proyecto_id=$proyectoId", 'success',
                $hitoId > 0 ? 'Hito actualizado.' : 'Hito agregado.');
        } catch (Throwable $e) {
            error_log('[hito_form] ' . $e->getMessage());
            $errores['titulo'] = 'No se pudo guardar el hito.';
        }
    }
}

// ── Listado ──────────────────────────────────────────────────
$st = getDB()->prepare(
    'SELECT h.id, h.titulo, h.descripcion, h.porcentaje_avance, h.fecha,
            h.creado_en, u.nombre AS creador
       FROM hitos h
  LEFT JOIN usuarios u ON u.id = h.creado_por
      WHERE h.proyecto_id = ?
   ORDER BY h.fecha DESC'
);
$st->execute([$proyectoId]);
$hitos = $st->fetchAll();

// Avance global: promedio de los hitos
$avanceGlobal = $hitos
    ? (int) round(array_sum(array_column($hitos, 'porcentaje_avance')) / count($hitos))
    : 0;

$pageTitle = 'Seguimiento — ' . $proyecto['titulo'];
$cssExtra  = 'admin.css';
$jsExtra   = '';
abrirPagina();
?>

<div class="container container--panel">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        [$proyecto['titulo'] => 'proyecto.php?slug=' . urlencode($proyecto['slug'])],
        ['Seguimiento' => null],
    ]) ?>

    <div class="seccion-cabecera">
        <div>
            <h1>Seguimiento de proyecto</h1>
            <p class="muted">Hitos y avances de «<?= e($proyecto['titulo']) ?>»</p>
        </div>
        <a class="btn btn-outline btn-sm" href="<?= e(url('proyecto.php?slug=' . urlencode($proyecto['slug']))) ?>">
            Ver proyecto →
        </a>
    </div>

    <div class="panel panel--form">
        <header class="panel__cabecera">
            <h2 class="panel__titulo"><?= $editar ? 'Editar hito' : 'Agregar nuevo hito' ?></h2>
        </header>

        <?= pintarErrores($errores) ?>

        <form method="post" class="formulario" action="<?= e(url('admin/hito_form.php?proyecto_id=' . $proyectoId)) ?>">
            <?= campoCSRF() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="proyecto_id" value="<?= $proyectoId ?>">
            <?php if ($editar): ?>
                <input type="hidden" name="hito_id" value="<?= (int) $editar['id'] ?>">
            <?php endif; ?>

            <?= campo('titulo', 'Título', 'text', [
                'valor' => $editar['titulo'] ?? '', 'requerido' => true, 'max' => 200,
                'placeholder' => 'Ej: Prototipo jugable v0.3',
            ]) ?>

            <?= campo('descripcion', 'Descripción', 'textarea', [
                'valor' => $editar['descripcion'] ?? '', 'filas' => 4, 'max' => 2000,
                'placeholder' => 'Detalles del avance…',
            ]) ?>

            <div class="rejilla rejilla--2">
                <?= campo('fecha', 'Fecha', 'date', [
                    'valor' => $editar['fecha'] ?? date('Y-m-d'), 'requerido' => true,
                ]) ?>
                <?= campo('porcentaje_avance', 'Avance (%)', 'number', [
                    'valor' => (string) ($editar['porcentaje_avance'] ?? 0),
                    'min' => 0, 'max_valor' => 100,
                ]) ?>
            </div>

            <div class="acciones-formulario">
                <button class="btn btn-primary" type="submit">
                    <?= $editar ? 'Guardar cambios' : 'Agregar hito' ?>
                </button>
                <?php if ($editar): ?>
                    <a class="btn btn-ghost"
                       href="<?= e(url('admin/hito_form.php?proyecto_id=' . $proyectoId)) ?>">
                        Cancelar edición</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <section class="panel">
        <header class="panel__cabecera">
            <h2 class="panel__titulo">Hitos registrados</h2>
            <span class="contador"><?= count($hitos) ?></span>
        </header>

        <?php if ($hitos): ?>
            <div class="avance-global">
                <span>Avance promedio</span>
                <div class="progress" role="progressbar"
                     aria-valuenow="<?= $avanceGlobal ?>" aria-valuemin="0" aria-valuemax="100"
                     aria-label="Avance promedio del proyecto">
                    <div class="progress__bar progress__bar--p<?= floor($avanceGlobal / 5) * 5 ?>"></div>
                </div>
                <strong><?= $avanceGlobal ?> %</strong>
            </div>
        <?php endif; ?>

        <?php if (!$hitos): ?>
            <p class="vacio">Todavía no hay hitos registrados para este proyecto.</p>
        <?php else: ?>
            <ul class="lista-hitos">
                <?php foreach ($hitos as $h): ?>
                <li class="tarjeta-hito">
                    <div class="tarjeta-hito__info">
                        <p class="tarjeta-hito__meta">
                            <?= e(fecha($h['fecha'], 'd/m/Y')) ?>
                            · <?= (int) $h['porcentaje_avance'] ?> % de avance
                            <?php if ($h['creador']): ?>
                                · Registrado por <?= e($h['creador']) ?>
                            <?php endif; ?>
                        </p>
                        <h3><?= e($h['titulo']) ?></h3>
                        <?php if ($h['descripcion']): ?>
                            <p><?= e($h['descripcion']) ?></p>
                        <?php endif; ?>
                        <div class="progress progress--sm" role="progressbar"
                             aria-valuenow="<?= (int) $h['porcentaje_avance'] ?>"
                             aria-valuemin="0" aria-valuemax="100"
                             aria-label="Avance de <?= e($h['titulo']) ?>">
                            <div class="progress__bar progress__bar--p<?= floor((int) $h['porcentaje_avance'] / 5) * 5 ?>"></div>
                        </div>
                    </div>

                    <div class="tarjeta-hito__acciones">
                        <a class="btn btn-sm btn-outline"
                           href="<?= e(url('admin/hito_form.php?proyecto_id=' . $proyectoId . '&id=' . (int) $h['id'])) ?>">
                            <?= icono('edit', 14) ?> Editar
                        </a>
                        <form method="post"
                              action="<?= e(url('admin/hito_form.php?proyecto_id=' . $proyectoId)) ?>"
                              data-confirm-submit="¿Eliminar este hito? No se puede deshacer.">
                            <?= campoCSRF() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="proyecto_id" value="<?= $proyectoId ?>">
                            <input type="hidden" name="hito_id" value="<?= (int) $h['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">
                                <?= icono('trash', 14) ?> Eliminar
                            </button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</div>

<?php cerrarPagina(); ?>
