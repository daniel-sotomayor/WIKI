<?php
// ============================================================
//  Alta / edición de proyectos
//  Archivo: frontend/admin/proyecto_form.php
//  Requiere: `proyecto.crear` (alta) / `proyecto.editar` (edición)
//            `proyecto.eliminar` (sólo administrador, borrado físico)
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';

$proyectoId = entero($_GET['id'] ?? $_POST['proyecto_id'] ?? '', 0);
$esAlta     = $proyectoId === 0;

requierePermiso($esAlta ? 'proyecto.crear' : 'proyecto.editar');

const ESTADOS_PROYECTO = [
    'activo'        => 'Activo',
    'en_desarrollo' => 'En desarrollo',
    'pausado'       => 'Pausado',
    'archivado'     => 'Archivado',
];

$yo        = currentUser();
$proyecto  = $esAlta ? null : dbUnica('SELECT * FROM proyectos WHERE id = ? LIMIT 1', [$proyectoId]);

if (!$esAlta && !$proyecto) {
    paginaNoEncontrada('proyecto');
}

$categorias = getDB()->query(
    'SELECT id, nombre FROM categorias ORDER BY nombre'
)->fetchAll();
$usuarios = getDB()->query(
    'SELECT id, nombre, apellido FROM usuarios WHERE activo = 1 ORDER BY apellido, nombre'
)->fetchAll();

// Opciones del <select> de responsable: id => "Nombre Apellido"
$opcionesResponsable = [];
foreach ($usuarios as $u) {
    $opcionesResponsable[(int) $u['id']] = nombreCompleto($u);
}

$errores = [];
$valores = [
    'titulo'            => $proyecto['titulo'] ?? '',
    'categoria_id'      => $proyecto['categoria_id'] ?? '',
    'descripcion_corta' => $proyecto['descripcion_corta'] ?? '',
    'descripcion'       => $proyecto['descripcion'] ?? '',
    'tecnologias'       => $proyecto['tecnologias'] ?? '',
    'version_actual'    => $proyecto['version_actual'] ?? '',
    'estado'            => $proyecto['estado'] ?? 'en_desarrollo',
    'responsable_id'    => $proyecto['responsable_id'] ?? '',
    'fecha_inicio'      => $proyecto['fecha_inicio'] ?? '',
];

// ── Eliminar (sólo administrador) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    verifyCsrf();
    requierePermiso('proyecto.eliminar');

    if (!$proyecto) {
        paginaNoEncontrada('proyecto');
    }

    try {
        $db = getDB();

        // Los archivos asociados viven en disco fuera de la BD: se retiran
        // a mano (media no tiene FK hacia proyectos por diseño polimórfico).
        eliminarMediaDe('proyecto', $proyecto['id']);

        // artículos e hitos caen en cascata (ON DELETE CASCADE)
        $db->prepare('DELETE FROM proyectos WHERE id = ?')->execute([$proyecto['id']]);

        logSeguridad('proyecto_eliminado', (string) $yo['id'],
            'proyecto #' . $proyecto['id'] . ' «' . $proyecto['titulo'] . '»');

        redirigir('proyectos.php', 'success',
            'Proyecto eliminado junto con sus artículos, hitos y archivos.');
    } catch (Throwable $e) {
        error_log('[proyecto_form/eliminar] ' . $e->getMessage());
        $errores['titulo'] = 'No se pudo eliminar el proyecto (¿tiene referencias pendientes?).';
    }
}

// ── Eliminar un adjunto ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_media') {
    verifyCsrf();
    $idMedia = entero($_POST['media_id'] ?? '', 0);
    $media = dbUnica(
        "SELECT * FROM media WHERE id = ? AND entidad_tipo = 'proyecto' AND entidad_id = ?",
        [$idMedia, $proyectoId]
    );
    if ($media && puede('media.eliminar')) {
        eliminarMedia((int) $media['id']);
        logSeguridad('media_eliminada', (string) $yo['id'], 'media #' . $idMedia);
        redirigir('admin/proyecto_form.php?id=' . $proyectoId, 'success', 'Archivo eliminado.');
    }
    redirigir('admin/proyecto_form.php?id=' . $proyectoId, 'danger', 'No se pudo eliminar el archivo.');
}

// ── Guardar ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? 'guardar') === 'guardar') {
    verifyCsrf();

    $valores['titulo']            = saneaPOST('titulo', 200);
    $valores['categoria_id']      = entero($_POST['categoria_id'] ?? '', 0);
    $valores['descripcion_corta'] = saneaPOST('descripcion_corta', 500);
    $valores['descripcion']       = sanearHTML(saneaPOST('descripcion', 60000));
    $valores['tecnologias']       = saneaPOST('tecnologias', 300);
    $valores['version_actual']    = saneaPOST('version_actual', 20);
    $valores['estado']            = array_key_exists($_POST['estado'] ?? '', ESTADOS_PROYECTO)
        ? $_POST['estado'] : 'en_desarrollo';
    $valores['responsable_id']    = entero($_POST['responsable_id'] ?? '', 0) ?: null;
    $valores['fecha_inicio']      = saneaPOST('fecha_inicio', 10);

    $errores = validar($_POST, [
        'titulo'            => ['requerido' => true, 'min' => 3, 'max' => 200],
        'descripcion_corta' => ['max' => 500],
        'tecnologias'       => ['max' => 300],
        'fecha_inicio'      => ['fecha' => true],
    ], ['titulo' => 'Título', 'descripcion_corta' => 'Descripción corta',
        'tecnologias' => 'Tecnologías', 'fecha_inicio' => 'Fecha de inicio']);

    $idsCategorias = array_map('intval', array_column($categorias, 'id'));
    if (!in_array((int) $valores['categoria_id'], $idsCategorias, true)) {
        $errores['categoria_id'] = 'Elegí una categoría válida.';
    }

    if ($valores['fecha_inicio'] !== '' && !esFechaValida($valores['fecha_inicio'])) {
        $errores['fecha_inicio'] = 'La fecha de inicio no es válida (usá AAAA-MM-DD).';
    }

    if (!$errores) {
        try {
            $db = getDB();

            if ($proyecto) {
                $db->prepare(
                    'UPDATE proyectos
                        SET titulo=?, categoria_id=?, descripcion_corta=?, descripcion=?,
                            tecnologias=?, version_actual=?, estado=?, responsable_id=?,
                            fecha_inicio=?
                      WHERE id=?'
                )->execute([
                    $valores['titulo'], $valores['categoria_id'], $valores['descripcion_corta'],
                    $valores['descripcion'], $valores['tecnologias'], $valores['version_actual'],
                    $valores['estado'], $valores['responsable_id'],
                    $valores['fecha_inicio'] !== '' ? $valores['fecha_inicio'] : null,
                    $proyecto['id'],
                ]);
                $destinoId = (int) $proyecto['id'];
                logSeguridad('proyecto_editado', (string) $yo['id'], 'proyecto #' . $destinoId);
                $texto = 'Proyecto actualizado correctamente.';
            } else {
                $db->prepare(
                    'INSERT INTO proyectos
                        (titulo, slug, categoria_id, descripcion_corta, descripcion,
                         tecnologias, version_actual, estado, responsable_id, fecha_inicio)
                     VALUES (?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $valores['titulo'],
                    slugUnico('proyectos', 'slug', slugify($valores['titulo'])),
                    $valores['categoria_id'], $valores['descripcion_corta'],
                    $valores['descripcion'], $valores['tecnologias'], $valores['version_actual'],
                    $valores['estado'], $valores['responsable_id'],
                    $valores['fecha_inicio'] !== '' ? $valores['fecha_inicio'] : null,
                ]);
                $destinoId = (int) $db->lastInsertId();
                logSeguridad('proyecto_creado', (string) $yo['id'], 'proyecto #' . $destinoId);
                $texto = 'Proyecto creado correctamente.';
            }

            // Archivos del proyecto (imágenes, videos o documentos)
            $erroresAdjunto = [];
            if (!empty($_FILES['archivos']['name'])) {
                subirAdjuntos('proyecto', $destinoId, $_FILES['archivos'], $yo['id'], $erroresAdjunto);
            }
            if ($erroresAdjunto) {
                $texto .= ' Adjuntos: ' . implode(' ', $erroresAdjunto);
            }

            redirigir('admin/proyecto_form.php?id=' . $destinoId, 'success', $texto);
        } catch (Throwable $e) {
            error_log('[proyecto_form] ' . $e->getMessage());
            $errores['titulo'] = 'No se pudo guardar el proyecto.';
        }
    }
}

$adjuntos = $proyecto ? listarMedia('proyecto', $proyectoId) : [];

$pageTitle = $proyecto ? 'Editar proyecto' : 'Nuevo proyecto';
$cssExtra  = 'admin.css';
$jsExtra   = 'editor.js';
abrirPagina();
?>

<div class="container container--form">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Proyectos' => 'proyectos.php'],
        ['Panel' => 'admin/index.php'],
        [$proyecto ? 'Editar proyecto' : 'Nuevo proyecto' => null],
    ]) ?>

    <div class="seccion-cabecera">
        <div>
            <h1><?= $proyecto ? 'Editar proyecto' : 'Nuevo proyecto' ?></h1>
            <?php if ($proyecto): ?>
                <p class="muted">Slug: <code><?= e($proyecto['slug']) ?></code></p>
            <?php endif; ?>
        </div>
        <?php if ($proyecto): ?>
            <div class="seccion-cabecera__acciones">
                <a class="btn btn-outline btn-sm"
                   href="<?= e(url('proyecto.php?slug=' . urlencode($proyecto['slug']))) ?>">
                    Ver proyecto →</a>
                <?php if (puede('hito.gestionar')): ?>
                    <a class="btn btn-outline btn-sm"
                       href="<?= e(url('admin/hito_form.php?proyecto_id=' . (int) $proyecto['id'])) ?>">
                        <?= icono('chart', 15) ?> Seguimiento</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel panel--form">
        <?= pintarErrores($errores) ?>

        <form method="post" enctype="multipart/form-data" class="formulario"
              action="<?= e(url('admin/proyecto_form.php')) ?>">
            <?= campoCSRF() ?>
            <input type="hidden" name="accion" value="guardar">
            <?php if ($proyecto): ?>
                <input type="hidden" name="proyecto_id" value="<?= (int) $proyecto['id'] ?>">
            <?php endif; ?>

            <?= campo('titulo', 'Título', 'text', [
                'valor' => $valores['titulo'], 'requerido' => true, 'max' => 200,
            ]) ?>

            <div class="rejilla rejilla--2">
                <?= campo('categoria_id', 'Categoría', 'select', [
                    'valor' => $valores['categoria_id'], 'requerido' => true,
                    'opciones' => array_column($categorias, 'nombre', 'id'),
                    'vacio' => '— Seleccionar —',
                ]) ?>
                <?= campo('estado', 'Estado', 'select', [
                    'valor' => $valores['estado'],
                    'opciones' => ESTADOS_PROYECTO,
                ]) ?>
            </div>

            <?= campo('descripcion_corta', 'Descripción corta', 'text', [
                'valor' => $valores['descripcion_corta'], 'max' => 500,
                'placeholder' => 'Se muestra en las tarjetas del listado…',
            ]) ?>

            <?= campo('descripcion', 'Descripción completa', 'textarea', [
                'valor' => $valores['descripcion'], 'filas' => 12, 'max' => 60000,
                'placeholder' => 'HTML básico permitido: h2, h3, p, ul, li, strong, a…',
                'ayuda' => 'El HTML se limpia al guardar; no se admiten scripts ni estilos en línea.',
                'class' => 'editor-ric',
            ]) ?>

            <div class="rejilla rejilla--3">
                <?= campo('tecnologias', 'Tecnologías', 'text', [
                    'valor' => $valores['tecnologias'], 'max' => 300,
                    'placeholder' => 'PHP, MySQL, JavaScript…',
                    'ayuda' => 'Separadas por comas.',
                ]) ?>
                <?= campo('version_actual', 'Versión actual', 'text', [
                    'valor' => $valores['version_actual'], 'max' => 20,
                    'placeholder' => '0.8.2',
                ]) ?>
                <?= campo('fecha_inicio', 'Fecha de inicio', 'date', [
                    'valor' => $valores['fecha_inicio'],
                ]) ?>
            </div>

            <?= campo('responsable_id', 'Responsable', 'select', [
                'valor' => $valores['responsable_id'],
                'opciones' => $opcionesResponsable,
                'vacio' => '— Sin asignar —',
            ]) ?>

            <?= campo('archivos', 'Imágenes, videos o documentos', 'file', [
                'multiple' => true, 'max' => 6,
                'ayuda' => 'Fotos de la obra, diagramas, manuales o planos en PDF.',
            ]) ?>

            <div class="acciones-formulario">
                <button class="btn btn-primary btn-lg" type="submit">
                    <?= $proyecto ? 'Guardar cambios' : 'Crear proyecto' ?>
                </button>
                <a class="btn btn-ghost btn-lg"
                   href="<?= e(url($proyecto ? 'proyecto.php?slug=' . urlencode($proyecto['slug']) : 'proyectos.php')) ?>">
                    Cancelar</a>
            </div>
        </form>
    </div>

<?php if ($proyecto && $adjuntos): ?>
    <section class="panel">
        <header class="panel__cabecera">
            <h2 class="panel__titulo">Archivos adjuntos</h2>
            <span class="contador"><?= count($adjuntos) ?></span>
        </header>
        <ul class="lista-adjuntos">
            <?php foreach ($adjuntos as $a): ?>
            <li>
                <?php if ($a['tipo'] === 'imagen'): ?>
                    <img class="lista-adjuntos__mini" src="<?= e(urlMedia($a['nombre_guardado'])) ?>"
                         alt="<?= e($a['titulo'] ?: $a['nombre_original']) ?>" loading="lazy">
                <?php else: ?>
                    <span class="lista-adjuntos__icono" aria-hidden="true">
                        <?= icono($a['tipo'] === 'video' ? 'video' : 'file', 22) ?>
                    </span>
                <?php endif; ?>
                <a href="<?= e(urlMedia($a['nombre_guardado'])) ?>" target="_blank" rel="noopener">
                    <?= e($a['nombre_original']) ?></a>
                <small class="muted"><?= bytes((int) $a['tamano']) ?></small>

                <?php if (puede('media.eliminar')): ?>
                <form method="post" action="<?= e(url('admin/proyecto_form.php')) ?>"
                      data-confirm-submit="¿Eliminar «<?= e($a['nombre_original']) ?>»?">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="eliminar_media">
                    <input type="hidden" name="proyecto_id" value="<?= (int) $proyecto['id'] ?>">
                    <input type="hidden" name="media_id" value="<?= (int) $a['id'] ?>">
                    <button class="btn btn-sm btn-danger" type="submit">
                        <?= icono('trash', 14) ?> Eliminar
                    </button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($proyecto && puede('proyecto.eliminar')): ?>
    <div class="panel panel--peligro">
        <h2 class="panel__titulo">Zona de peligro</h2>
        <p>Eliminar este proyecto también elimina <strong>todas sus artículos, historial de
            versiones, hitos y archivos adjuntos</strong>. Esta acción no se puede deshacer.</p>
        <form method="post" action="<?= e(url('admin/proyecto_form.php')) ?>"
              data-confirm-submit="<?= e('¿Eliminar «' . $proyecto['titulo']
                  . '» y todo su contenido asociado? No se puede deshacer.') ?>">
            <?= campoCSRF() ?>
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="proyecto_id" value="<?= (int) $proyecto['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit">
                <?= icono('trash', 15) ?> Eliminar proyecto
            </button>
        </form>
    </div>
<?php endif; ?>

</div>

<?php cerrarPagina(); ?>
