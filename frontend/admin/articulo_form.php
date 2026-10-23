<?php
// ============================================================
//  Alta / edición de artículos de la wiki
//  Archivo: frontend/admin/articulo_form.php
//  Requiere: `articulo.crear` / `articulo.editar`
//
//  Al detectar cambios en título, tipo o contenido se guarda la
//  versión anterior en `historial_versiones` y se incrementa
//  articulos.version.
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';
require_once BACKEND . '/lib/plantillas_articulos.php';

$articuloId = entero($_GET['id'] ?? $_POST['articulo_id'] ?? '', 0);
$esAlta     = $articuloId === 0;

requierePermiso($esAlta ? 'articulo.crear' : 'articulo.editar');

const TIPOS_ARTICULO = [
    'guion' => 'Guión', 'arquitectura' => 'Arquitectura', 'mecanica' => 'Mecánica',
    'personaje' => 'Personaje', 'nivel' => 'Nivel', 'tecnico' => 'Técnico',
    'otro' => 'Otro',
];

$yo       = currentUser();
$articulo = $esAlta ? null : dbUnica('SELECT * FROM articulos WHERE id = ? LIMIT 1', [$articuloId]);

if (!$esAlta && !$articulo) {
    paginaNoEncontrada('articulo');
}

$proyectoId = $articulo ? (int) $articulo['proyecto_id']
                        : entero($_GET['proyecto_id'] ?? $_POST['proyecto_id'] ?? '', 0);
$proyecto = dbUnica('SELECT id, titulo, slug FROM proyectos WHERE id = ? LIMIT 1', [$proyectoId]);

if (!$proyecto) {
    accesoDenegado('Indicá un proyecto válido para crear un artículo.');
}

$errores = [];
$valores = [
    'titulo'    => $articulo['titulo'] ?? '',
    'resumen'   => $articulo['resumen'] ?? '',
    'tipo'      => $articulo['tipo'] ?? 'tecnico',
    'contenido' => $articulo['contenido'] ?? '',
    'publicado' => isset($articulo['publicado']) ? (int) $articulo['publicado'] : 0,
    'etiquetas' => '',
];
$etiquetasFinales = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // ── Eliminar un archivo adjunto ───────────────────────────
    if (($_POST['accion'] ?? '') === 'eliminar_media') {
        $idMedia = entero($_POST['media_id'] ?? '', 0);
        $media = dbUnica(
            "SELECT * FROM media WHERE id = ? AND entidad_tipo = 'articulo' AND entidad_id = ?",
            [$idMedia, $articuloId]
        );

        if ($media && puede('media.eliminar')) {
            eliminarMedia((int) $media['id']);
            logSeguridad('media_eliminada', (string) $yo['id'], 'media #' . $idMedia);
            $mensaje = ['success', 'Archivo eliminado.'];
        } else {
            $mensaje = ['danger', 'No se pudo eliminar el archivo.'];
        }

        redirigir('admin/articulo_form.php?id=' . $articuloId, $mensaje[0], $mensaje[1]);
    }

    $valores['titulo']    = saneaPOST('titulo', 300);
    $valores['resumen']   = saneaPOST('resumen', 500);
    $valores['tipo']      = array_key_exists($_POST['tipo'] ?? '', TIPOS_ARTICULO)
        ? $_POST['tipo'] : 'tecnico';
    $valores['contenido'] = sanearHTML(saneaPOST('contenido', 200000));
    $valores['publicado'] = isset($_POST['publicado']) ? 1 : 0;
    $valores['etiquetas'] = saneaPOST('etiquetas', 500);

    $errores = validar($_POST, [
        'titulo'  => ['requerido' => true, 'min' => 3, 'max' => 300],
        'resumen' => ['max' => 500],
        'etiquetas' => ['max' => 500],
    ], ['titulo' => 'Título', 'resumen' => 'Resumen', 'etiquetas' => 'Etiquetas']);

    if (trim(strip_tags($valores['contenido'])) === '') {
        $errores['contenido'] = 'El contenido es obligatorio.';
    }

    if (!$errores) {
        try {
            $db = getDB();

            if ($articulo) {
                $hayCambios = $articulo['titulo'] !== $valores['titulo']
                           || $articulo['contenido'] !== $valores['contenido']
                           || $articulo['tipo'] !== $valores['tipo'];

                if ($hayCambios) {
                    // Se archiva la versión anterior ANTES de sobrescribir
                    $db->prepare(
                        'INSERT INTO historial_versiones
                            (articulo_id, contenido, version, editado_por)
                         VALUES (?,?,?,?)'
                    )->execute([
                        $articulo['id'], $articulo['contenido'],
                        $articulo['version'], $yo['id'],
                    ]);

                    $nuevaVersion = (int) $articulo['version'] + 1;

                    $db->prepare(
                        'UPDATE articulos
                            SET titulo=?, resumen=?, contenido=?, tipo=?,
                                publicado=?, version=?
                          WHERE id=?'
                    )->execute([
                        $valores['titulo'], $valores['resumen'], $valores['contenido'],
                        $valores['tipo'], $valores['publicado'], $nuevaVersion,
                        $articulo['id'],
                    ]);

                    $version = $nuevaVersion;
                    logSeguridad('articulo_editado', (string) $yo['id'],
                        'articulo #' . $articulo['id'] . ' → v' . $nuevaVersion);
                    $texto = 'Artículo actualizado. Se archivó la versión anterior (v'
                           . $articulo['version'] . ') en el historial.';
                } else {
                    $db->prepare('UPDATE articulos SET publicado=?, resumen=? WHERE id=?')
                       ->execute([$valores['publicado'], $valores['resumen'], $articulo['id']]);
                    $version = (int) $articulo['version'];
                    $texto = 'Artículo guardado (sin cambios de contenido).';
                }

                sincronizarTags((int) $articulo['id'], $valores['etiquetas']);
                $destinoId = (int) $articulo['id'];
            } else {
                $db->prepare(
                    'INSERT INTO articulos
                        (proyecto_id, titulo, slug, resumen, contenido, tipo,
                         autor_id, version, publicado)
                     VALUES (?,?,?,?,?,?,?,1,?)'
                )->execute([
                    $proyectoId, $valores['titulo'],
                    slugUnico('articulos', 'slug', slugify($valores['titulo'])),
                    $valores['resumen'], $valores['contenido'], $valores['tipo'],
                    $yo['id'], $valores['publicado'],
                ]);
                $destinoId = (int) $db->lastInsertId();
                sincronizarTags($destinoId, $valores['etiquetas']);
                logSeguridad('articulo_creado', (string) $yo['id'],
                    'articulo #' . $destinoId . ' en proyecto #' . $proyectoId);
                $texto = 'Artículo creado correctamente.';
            }

            // Adjuntos opcionales
            $erroresAdjunto = [];
            if (!empty($_FILES['archivos']['name'])) {
                subirAdjuntos('articulo', $destinoId, $_FILES['archivos'], $yo['id'], $erroresAdjunto);
            }
            if ($erroresAdjunto) {
                $texto .= ' Algunos adjuntos no se subieron: ' . implode(' ', $erroresAdjunto);
            }

            redirigir('admin/articulo_form.php?id=' . $destinoId, 'success', $texto);
        } catch (Throwable $e) {
            error_log('[articulo_form] ' . $e->getMessage());
            $errores['titulo'] = 'No se pudo guardar el artículo.';
        }
    }
}

$etiquetasFinales = $articulo
    ? tagsDeArticulo((int) $articulo['id'])
    : $valores['etiquetas'];

$tagsSugeridas = getDB()->query('SELECT nombre FROM tags ORDER BY nombre LIMIT 200')
                       ->fetchAll(PDO::FETCH_COLUMN);

$adjuntos = $articulo ? listarMedia('articulo', (int) $articulo['id']) : [];

// Guía de plantillas por tipo: se pasa al JS en un <input type="hidden">
// (CSP: no se inyecta <script> con contenido dinámico).
$plantillasJson = json_encode(
    plantillasArticulo(),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);

$pageTitle = $articulo ? 'Editar artículo' : 'Nuevo artículo';
$cssExtra  = 'admin.css';
$jsExtra   = 'articulo_form.js, editor.js';
abrirPagina();
?>

<div class="container container--form">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        [$proyecto['titulo'] => 'proyecto.php?slug=' . urlencode($proyecto['slug'])],
        ['Wiki' => 'wiki.php'],
        [$articulo ? 'Editar artículo' : 'Nuevo artículo' => null],
    ]) ?>

    <div class="seccion-cabecera">
        <div>
            <h1><?= $articulo ? 'Editar artículo' : 'Nuevo artículo' ?></h1>
            <p class="muted">
                Proyecto: <strong><?= e($proyecto['titulo']) ?></strong>
                <?php if ($articulo): ?> · Versión actual: v<?= (int) $articulo['version'] ?><?php endif; ?>
            </p>
        </div>
        <?php if ($articulo && puede('articulo.historial')): ?>
            <a class="btn btn-outline btn-sm"
               href="<?= e(url('admin/articulo_historial.php?id=' . (int) $articulo['id'])) ?>">
                <?= icono('clock', 15) ?> Ver historial
            </a>
        <?php endif; ?>
    </div>

    <div class="panel panel--form">
        <?= pintarErrores($errores) ?>

        <form method="post" enctype="multipart/form-data" class="formulario"
              action="<?= e(url('admin/articulo_form.php')) ?>">
            <?= campoCSRF() ?>
            <input type="hidden" name="proyecto_id" value="<?= $proyectoId ?>">
            <?php if ($articulo): ?>
                <input type="hidden" name="articulo_id" value="<?= (int) $articulo['id'] ?>">
            <?php endif; ?>

            <?= campo('titulo', 'Título', 'text', [
                'valor' => $valores['titulo'], 'requerido' => true, 'max' => 300,
            ]) ?>

            <div class="rejilla rejilla--2">
                <?= campo('tipo', 'Tipo', 'select', [
                    'valor' => $valores['tipo'], 'opciones' => TIPOS_ARTICULO,
                ]) ?>

                <div class="form-group">
                    <label class="casilla" for="f_publicado">
                        <input type="checkbox" id="f_publicado" name="publicado" value="1"
                            <?= $valores['publicado'] ? 'checked' : '' ?>>
                        <span>Publicado (visible para todos)</span>
                    </label>
                    <p class="form-ayuda">Los artículos sin publicar sólo los ven los editores
                        desde el panel.</p>
                </div>
            </div>

            <?= campo('resumen', 'Resumen', 'text', [
                'valor' => $valores['resumen'], 'max' => 500,
                'placeholder' => 'Una o dos líneas que resumen el artículo…',
            ]) ?>

            <div class="form-group">
                <label class="form-label" for="f_etiquetas">Etiquetas</label>
                <input class="form-control" type="text" id="f_etiquetas" name="etiquetas"
                       list="tags-existentes" value="<?= e($etiquetasFinales) ?>"
                       placeholder="Ej: motor de juego, pygame, mvc" maxlength="500">
                <p class="form-ayuda">Separadas por comas. Las etiquetas nuevas se crean solas
                    y se reutilizan entre artículos.</p>
                <datalist id="tags-existentes">
                    <?php foreach ($tagsSugeridas as $tg): ?>
                        <option value="<?= e((string) $tg) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <!-- Guía de estructura por tipo (Módulo 6) -->
            <div class="guia-plantilla">
                <div class="guia-plantilla__cabecera">
                    <strong id="plantilla-titulo">Estructura sugerida</strong>
                    <button type="button" id="btn-plantilla" class="btn btn-outline btn-sm">
                        <?= icono('file', 15) ?> Insertar plantilla
                    </button>
                </div>
                <ul id="plantilla-lista"></ul>
                <p class="guia-plantilla__nota">
                    Es una guía para estandarizar la documentación; el contenido final es libre.
                </p>
                <input type="hidden" id="plantillas-data" value="<?= e($plantillasJson) ?>">
            </div>

            <?= campo('contenido', 'Contenido', 'textarea', [
                'valor' => $valores['contenido'], 'requerido' => true, 'filas' => 18,
                'max' => 200000, 'class' => 'editor-ric',
                'placeholder' => 'HTML básico permitido: h2, h3, p, ul, li, strong, a…',
                'ayuda' => 'El HTML se limpia al guardar.',
            ]) ?>

            <?= campo('archivos', 'Agregar imágenes, videos o documentos', 'file', [
                'multiple' => true, 'max' => 6,
                'ayuda' => 'Se valida el contenido real (MIME) de cada archivo.',
            ]) ?>

            <div class="acciones-formulario">
                <button class="btn btn-primary btn-lg" type="submit">
                    <?= $articulo ? 'Guardar cambios' : 'Crear artículo' ?>
                </button>
                <a class="btn btn-ghost btn-lg"
                   href="<?= e(url('proyecto.php?slug=' . urlencode($proyecto['slug']))) ?>">
                    Cancelar</a>
            </div>
        </form>
    </div>

<?php if ($articulo && $adjuntos): ?>
    <!-- Los adjuntos viven en un bloque aparte: los formularios de
         eliminación no pueden anidarse dentro del form principal. -->
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
                    <?= e($a['nombre_original']) ?>
                </a>
                <small class="muted"><?= bytes((int) $a['tamano']) ?></small>

                <?php if (puede('media.eliminar')): ?>
                <form method="post" action="<?= e(url('admin/articulo_form.php')) ?>"
                      data-confirm-submit="¿Eliminar «<?= e($a['nombre_original']) ?>»? No se puede deshacer.">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="eliminar_media">
                    <input type="hidden" name="articulo_id" value="<?= (int) $articulo['id'] ?>">
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

</div>

<?php cerrarPagina(); ?>
