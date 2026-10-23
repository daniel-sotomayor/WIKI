<?php
// ============================================================
//  Edición de noticias (panel)
//  Archivo: frontend/admin/noticia_form.php
//
//  Reglas:
//   · un editor sólo toca las SUYAS y todavía no publicadas
//   · el estado sólo lo cambia quien tiene `noticia.moderar`
//   · eliminar exige `noticia.eliminar` (administrador)
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';

$noticiaId = entero($_GET['id'] ?? $_POST['noticia_id'] ?? '', 0);
$esAlta    = $noticiaId === 0;

requierePermiso($esAlta ? 'noticia.crear' : 'noticia.editar');

$yo         = currentUser();
$puedeMod   = puede('noticia.moderar');
$puedeBorrar = puede('noticia.eliminar');
$noticia    = $esAlta ? null : dbUnica('SELECT * FROM noticias WHERE id = ? LIMIT 1', [$noticiaId]);

if (!$esAlta && !$noticia) {
    paginaNoEncontrada('noticia');
}

// Reglas de autoría para el rol editor
if ($noticia && !$puedeMod) {
    if ((int) $noticia['autor_id'] !== (int) $yo['id']) {
        accesoDenegado('Sólo podés editar tus propias noticias.');
    }
    if ($noticia['estado'] === 'publicada') {
        accesoDenegado('Esta noticia ya está publicada; pedile a un supervisor que la edite.');
    }
}

$errores = [];
$valores = [
    'titulo'        => $noticia['titulo'] ?? '',
    'resumen'       => $noticia['resumen'] ?? '',
    'categoria'     => $noticia['categoria'] ?? '',
    'contenido'     => $noticia['contenido'] ?? '',
    'destacada'     => isset($noticia['destacada']) ? (int) $noticia['destacada'] : 0,
    'estado'        => $noticia['estado'] ?? 'pendiente',
    'razon_rechazo' => $noticia['razon_rechazo'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = $_POST['accion'] ?? 'guardar';

    // ── Eliminar (sólo administrador) ─────────────────────────
    if ($accion === 'eliminar') {
        requierePermiso('noticia.eliminar');
        if (!$noticia) {
            paginaNoEncontrada('noticia');
        }

        eliminarMediaDe('noticia', $noticia['id']);
        getDB()->prepare('DELETE FROM noticias WHERE id = ?')->execute([$noticia['id']]);
        logSeguridad('noticia_eliminada', (string) $yo['id'],
            'noticia #' . $noticia['id'] . ' «' . $noticia['titulo'] . '»');

        redirigir('noticias.php', 'success', 'Noticia eliminada.');
    }

    // ── Eliminar un adjunto ──────────────────────────────────
    if ($accion === 'eliminar_media') {
        $idMedia = entero($_POST['media_id'] ?? '', 0);
        $media = dbUnica(
            "SELECT * FROM media WHERE id = ? AND entidad_tipo = 'noticia' AND entidad_id = ?",
            [$idMedia, $noticiaId]
        );
        if ($media && puede('media.eliminar')) {
            eliminarMedia((int) $media['id']);
            logSeguridad('media_eliminada', (string) $yo['id'], 'media #' . $idMedia);
            redirigir('admin/noticia_form.php?id=' . $noticiaId, 'success', 'Archivo eliminado.');
        }
        redirigir('admin/noticia_form.php?id=' . $noticiaId, 'danger', 'No se pudo eliminar el archivo.');
    }

    // ── Guardar ──────────────────────────────────────────────
    $valores['titulo']        = saneaPOST('titulo', 300);
    $valores['resumen']       = saneaPOST('resumen', 600);
    $valores['categoria']     = saneaPOST('categoria', 100);
    $valores['contenido']     = sanearHTML(saneaPOST('contenido', 60000));
    $valores['destacada']     = isset($_POST['destacada']) ? 1 : 0;
    $valores['razon_rechazo'] = saneaPOST('razon_rechazo', 500);

    $errores = validar($_POST, [
        'titulo'  => ['requerido' => true, 'min' => 5, 'max' => 300],
        'resumen' => ['requerido' => true, 'min' => 10, 'max' => 600],
        'categoria' => ['max' => 100],
    ], ['titulo' => 'Título', 'resumen' => 'Resumen', 'categoria' => 'Categoría']);

    if (trim(strip_tags($valores['contenido'])) === '') {
        $errores['contenido'] = 'El contenido es obligatorio.';
    }

    // El estado sólo lo manipula quien modera, y sólo con valores válidos
    $estadoPost = $noticia['estado'] ?? 'pendiente';
    if ($puedeMod && $noticia) {
        $permitidos = ['pendiente', 'publicada', 'rechazada', 'borrador'];
        if (in_array($_POST['estado'] ?? '', $permitidos, true)) {
            $estadoPost = $_POST['estado'];
        }
    }
    $valores['estado'] = $estadoPost;

    if (!$errores) {
        try {
            $db = getDB();

            if ($noticia) {
                $fechaPub = $noticia['fecha_publicacion'];
                if ($valores['estado'] === 'publicada' && !$fechaPub) {
                    $fechaPub = date('Y-m-d H:i:s');
                }
                $aprobador = $valores['estado'] !== 'pendiente'
                    ? ($puedeMod ? $yo['id'] : $noticia['aprobado_por'])
                    : $noticia['aprobado_por'];

                if ($puedeMod) {
                    $db->prepare(
                        'UPDATE noticias
                            SET titulo=?, resumen=?, contenido=?, categoria=?, destacada=?,
                                estado=?, razon_rechazo=?, fecha_publicacion=?, aprobado_por=?
                          WHERE id=?'
                    )->execute([
                        $valores['titulo'], $valores['resumen'], $valores['contenido'],
                        $valores['categoria'], $valores['destacada'], $valores['estado'],
                        $valores['estado'] === 'rechazada' ? $valores['razon_rechazo'] : null,
                        $fechaPub, $aprobador, $noticia['id'],
                    ]);
                } else {
                    // Editor: sólo contenido; el estado queda como estaba
                    $db->prepare(
                        'UPDATE noticias
                            SET titulo=?, resumen=?, contenido=?, categoria=?, destacada=?
                          WHERE id=?'
                    )->execute([
                        $valores['titulo'], $valores['resumen'], $valores['contenido'],
                        $valores['categoria'], $valores['destacada'], $noticia['id'],
                    ]);
                }

                $destinoId = (int) $noticia['id'];
                $texto = 'Noticia actualizada.';
                logSeguridad('noticia_editada', (string) $yo['id'], 'noticia #' . $destinoId);
            } else {
                $estado = $puedeMod ? 'publicada' : 'pendiente';
                $db->prepare(
                    'INSERT INTO noticias
                        (titulo, slug, resumen, contenido, categoria, autor_id,
                         estado, destacada, aprobado_por, fecha_publicacion)
                     VALUES (?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $valores['titulo'],
                    slugUnico('noticias', 'slug', slugify($valores['titulo'])),
                    $valores['resumen'], $valores['contenido'], $valores['categoria'],
                    $yo['id'], $estado, $valores['destacada'],
                    $puedeMod ? $yo['id'] : null,
                    $puedeMod ? date('Y-m-d H:i:s') : null,
                ]);
                $destinoId = (int) $db->lastInsertId();
                logSeguridad('noticia_creada', (string) $yo['id'], 'noticia #' . $destinoId);
                $texto = $puedeMod
                    ? 'Noticia creada y publicada.'
                    : 'Noticia creada. Queda pendiente de aprobación.';
            }

            $erroresAdjunto = [];
            if (!empty($_FILES['archivos']['name'])) {
                subirAdjuntos('noticia', $destinoId, $_FILES['archivos'], $yo['id'], $erroresAdjunto);
            }
            if ($erroresAdjunto) {
                $texto .= ' Adjuntos: ' . implode(' ', $erroresAdjunto);
            }

            redirigir('admin/noticia_form.php?id=' . $destinoId, 'success', $texto);
        } catch (Throwable $e) {
            error_log('[noticia_form] ' . $e->getMessage());
            $errores['titulo'] = 'No se pudo guardar la noticia.';
        }
    }
}

$adjuntos = $noticia ? listarMedia('noticia', $noticiaId) : [];

$pageTitle = $noticia ? 'Editar noticia' : 'Nueva noticia';
$cssExtra  = 'admin.css';
$jsExtra   = 'editor.js';
abrirPagina();
?>

<div class="container container--form">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Noticias' => 'noticias.php'],
        ['Panel' => 'admin/index.php'],
        [$noticia ? 'Editar noticia' : 'Nueva noticia' => null],
    ]) ?>

    <div class="seccion-cabecera">
        <div>
            <h1><?= $noticia ? 'Editar noticia' : 'Nueva noticia' ?></h1>
            <?php if ($noticia): ?>
                <p class="muted">Estado actual: <?= badgeEstado($noticia['estado']) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($noticia && $noticia['estado'] === 'publicada'): ?>
            <a class="btn btn-outline btn-sm"
               href="<?= e(url('noticia.php?slug=' . urlencode($noticia['slug']))) ?>">
                Ver noticia →</a>
        <?php endif; ?>
    </div>

    <div class="panel panel--form">
        <?= pintarErrores($errores) ?>

        <form method="post" enctype="multipart/form-data" class="formulario"
              action="<?= e(url('admin/noticia_form.php')) ?>">
            <?= campoCSRF() ?>
            <input type="hidden" name="accion" value="guardar">
            <?php if ($noticia): ?>
                <input type="hidden" name="noticia_id" value="<?= (int) $noticia['id'] ?>">
            <?php endif; ?>

            <?= campo('titulo', 'Título', 'text', [
                'valor' => $valores['titulo'], 'requerido' => true, 'max' => 300,
            ]) ?>

            <?= campo('resumen', 'Resumen', 'text', [
                'valor' => $valores['resumen'], 'requerido' => true, 'max' => 600,
                'ayuda' => 'Entre 10 y 600 caracteres; se muestra en la lista de noticias.',
            ]) ?>

            <div class="rejilla rejilla--2">
                <?= campo('categoria', 'Categoría', 'select', [
                    'valor' => $valores['categoria'], 'opciones' => NOTICIA_CATEGORIAS,
                    'vacio' => '— Sin categoría —',
                ]) ?>

                <div class="form-group">
                    <label class="casilla" for="f_destacada">
                        <input type="checkbox" id="f_destacada" name="destacada" value="1"
                            <?= $valores['destacada'] ? 'checked' : '' ?>>
                        <span>Destacada (aparece primero en la portada)</span>
                    </label>
                </div>
            </div>

            <?php if ($puedeMod && $noticia): ?>
            <fieldset class="caja-moderacion">
                <legend>Moderación</legend>
                <div class="rejilla rejilla--2">
                    <?= campo('estado', 'Estado', 'select', [
                        'valor' => $valores['estado'],
                        'opciones' => [
                            'borrador' => 'Borrador', 'pendiente' => 'Pendiente',
                            'publicada' => 'Publicada', 'rechazada' => 'Rechazada',
                        ],
                    ]) ?>
                    <?= campo('razon_rechazo', 'Razón de rechazo', 'text', [
                        'valor' => $valores['razon_rechazo'], 'max' => 500,
                        'ayuda' => 'Sólo se guarda si el estado es «Rechazada».',
                    ]) ?>
                </div>
            </fieldset>
            <?php endif; ?>

            <?= campo('contenido', 'Contenido', 'textarea', [
                'valor' => $valores['contenido'], 'requerido' => true, 'filas' => 14,
                'max' => 60000, 'class' => 'editor-ric',
                'ayuda' => 'HTML básico admitido; se limpia al guardar.',
            ]) ?>

            <?php if (!$noticia && !$puedeMod): ?>
                <div class="alert alert-info">
                    Como editor, tu noticia quedará <strong>pendiente de aprobación</strong>.
                </div>
            <?php endif; ?>

            <?= campo('archivos', 'Imágenes, videos o documentos', 'file', [
                'multiple' => true, 'max' => 6,
            ]) ?>

            <div class="acciones-formulario">
                <button class="btn btn-primary btn-lg" type="submit">
                    <?= $noticia ? 'Guardar cambios' : 'Crear noticia' ?>
                </button>
                <a class="btn btn-ghost btn-lg" href="<?= e(url('noticias.php')) ?>">Cancelar</a>
            </div>
        </form>
    </div>

<?php if ($noticia && $adjuntos): ?>
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
                <form method="post" action="<?= e(url('admin/noticia_form.php')) ?>"
                      data-confirm-submit="¿Eliminar «<?= e($a['nombre_original']) ?>»?">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="eliminar_media">
                    <input type="hidden" name="noticia_id" value="<?= (int) $noticia['id'] ?>">
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

<?php if ($noticia && $puedeBorrar): ?>
    <div class="panel panel--peligro">
        <h2 class="panel__titulo">Zona de peligro</h2>
        <p>Eliminar esta noticia y sus archivos no se puede deshacer.</p>
        <form method="post" action="<?= e(url('admin/noticia_form.php')) ?>"
              data-confirm-submit="<?= e('¿Eliminar la noticia «' . $noticia['titulo'] . '»? No se puede deshacer.') ?>">
            <?= campoCSRF() ?>
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="noticia_id" value="<?= (int) $noticia['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit">
                <?= icono('trash', 15) ?> Eliminar noticia
            </button>
        </form>
    </div>
<?php endif; ?>

</div>

<?php cerrarPagina(); ?>
