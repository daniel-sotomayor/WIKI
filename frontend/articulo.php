<?php
// ============================================================
//  Artículo de la wiki — vista pública
//  Archivo: frontend/articulo.php
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

$slug = saneaGET('slug', 300);
if ($slug === '') {
    paginaNoEncontrada('artículo');
}

$articulo = dbUnica(
    'SELECT a.*, p.titulo AS proyecto_titulo, p.slug AS proyecto_slug,
            u.nombre AS autor_nombre, u.apellido AS autor_apellido
       FROM articulos a
       JOIN proyectos p ON p.id = a.proyecto_id
  LEFT JOIN usuarios  u ON u.id = a.autor_id
      WHERE a.slug = ? AND a.publicado = 1
      LIMIT 1',
    [$slug]
);

if (!$articulo) {
    paginaNoEncontrada('artículo');
}

$idArticulo = (int) $articulo['id'];
$db = getDB();

$st = $db->prepare(
    'SELECT t.id, t.nombre, t.slug
       FROM tags t
       JOIN articulo_tags atg ON atg.tag_id = t.id
      WHERE atg.articulo_id = ?
   ORDER BY t.nombre'
);
$st->execute([$idArticulo]);
$etiquetas = $st->fetchAll();

$adjuntos = listarMedia('articulo', $idArticulo);

// Versiones anteriores (para el enlace "Historial")
$versiones = (int) dbValor(
    'SELECT COUNT(*) FROM historial_versiones WHERE articulo_id = ?',
    [$idArticulo]
);

$puedeEditar = puede('articulo.editar');

$pageTitle    = $articulo['titulo'];
$descripcion  = $articulo['resumen'] ?: resumir($articulo['contenido'], 160);
$jsExtra      = '';
abrirPagina();
?>

<div class="container">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Wiki' => 'wiki.php'],
        [$articulo['proyecto_titulo'] => 'proyecto.php?slug=' . rawurlencode($articulo['proyecto_slug'])],
        [$articulo['titulo'] => null],
    ]) ?>

    <article class="detalle">

        <header class="detalle__cabecera">
            <div class="detalle__titulo-bloque">
                <div class="detalle__chips">
                    <?= badgeTipo($articulo['tipo']) ?>
                    <span class="chip chip--cat"><?= e($articulo['proyecto_titulo']) ?></span>
                    <span class="chip">Versión <?= (int) $articulo['version'] ?></span>
                </div>
                <h1><?= e($articulo['titulo']) ?></h1>
                <?php if (!empty($articulo['resumen'])): ?>
                    <p class="detalle__entradilla"><?= e($articulo['resumen']) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($puedeEditar): ?>
                <div class="detalle__acciones">
                    <a class="btn btn-outline"
                       href="<?= e(url('admin/articulo_form.php?id=' . $idArticulo)) ?>">
                        <?= icono('edit', 16) ?> Editar
                    </a>
                    <a class="btn btn-outline"
                       href="<?= e(url('admin/articulo_historial.php?id=' . $idArticulo)) ?>">
                        <?= icono('clock', 16) ?> Historial (<?= $versiones ?>)
                    </a>
                </div>
            <?php endif; ?>
        </header>

        <dl class="ficha ficha--compacta">
            <?php if (!empty($articulo['autor_nombre'])): ?>
                <div><dt>Autor</dt>
                     <dd><?= e(trim($articulo['autor_nombre'] . ' ' . $articulo['autor_apellido'])) ?></dd></div>
            <?php endif; ?>
            <div><dt>Publicado</dt><dd><?= e(fecha($articulo['creado_en'])) ?></dd></div>
            <div><dt>Última edición</dt><dd><?= e(fecha($articulo['actualizado'])) ?></dd></div>
        </dl>

        <div class="article-body">
            <?= sanearHTML($articulo['contenido']) ?>
        </div>

        <?php if ($etiquetas): ?>
            <ul class="chips chips--fin">
                <?php foreach ($etiquetas as $t): ?>
                    <li><a class="chip chip--tag"
                           href="<?= e(url('wiki.php?tag=' . rawurlencode($t['slug']))) ?>">
                           <?= e($t['nombre']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($adjuntos): ?>
            <?= galeriaMedia($adjuntos) ?>
        <?php endif; ?>

        <footer class="detalle__pie">
            <a class="btn btn-ghost" href="<?= e(url('proyecto.php?slug=' . rawurlencode($articulo['proyecto_slug']))) ?>">
                ← Volver a <?= e($articulo['proyecto_titulo']) ?>
            </a>
            <a class="btn btn-ghost" href="<?= e(url('wiki.php')) ?>">Toda la documentación</a>
        </footer>

    </article>
</div>

<?php cerrarPagina(); ?>
