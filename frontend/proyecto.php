<?php
// ============================================================
//  Ficha de proyecto — público
//  Archivo: frontend/proyecto.php
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

$slug = saneaGET('slug', 240);
if ($slug === '') {
    paginaNoEncontrada('proyecto');
}

$proyecto = dbUnica(
    'SELECT p.*, c.nombre AS categoria, c.slug AS categoria_slug,
            u.nombre AS responsable_nombre, u.apellido AS responsable_apellido
       FROM proyectos p
       JOIN categorias c ON c.id = p.categoria_id
  LEFT JOIN usuarios  u ON u.id = p.responsable_id
      WHERE p.slug = ?
      LIMIT 1',
    [$slug]
);

if (!$proyecto) {
    paginaNoEncontrada('proyecto');
}

$idProyecto = (int) $proyecto['id'];
$db = getDB();

// Artículos publicados agrupados por tipo
$st = $db->prepare(
    'SELECT id, titulo, slug, resumen, contenido, tipo, actualizado
       FROM articulos WHERE proyecto_id = ? AND publicado = 1
   ORDER BY tipo, titulo'
);
$st->execute([$idProyecto]);
$articulos = $st->fetchAll();

// Hitos ordenados cronológicamente
$st = $db->prepare(
    'SELECT h.*, u.nombre AS autor_nombre
       FROM hitos h
  LEFT JOIN usuarios u ON u.id = h.creado_por
      WHERE h.proyecto_id = ?
   ORDER BY h.fecha ASC, h.id ASC'
);
$st->execute([$idProyecto]);
$hitos = $st->fetchAll();

$adjuntos     = listarMedia('proyecto', $idProyecto);
$portadaMedia = mediaPrincipal('proyecto', $idProyecto);
$avance       = $hitos ? (int) end($hitos)['porcentaje_avance'] : null;
$puedeEditar  = puede('proyecto.editar');

// Agrupa los artículos por tipo para mostrar secciones
$porTipo = [];
foreach ($articulos as $a) {
    $porTipo[$a['tipo']][] = $a;
}

$pageTitle = $proyecto['titulo'];
$descripcion = $proyecto['descripcion_corta']
             ?: 'Ficha técnica del proyecto ' . $proyecto['titulo'] . '.';
$jsExtra = '';
abrirPagina();
?>

<div class="container">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Proyectos' => 'proyectos.php'],
        [($proyecto['categoria'] ?? 'Proyecto') => 'proyectos.php?cat=' . rawurlencode($proyecto['categoria_slug'] ?? '')],
        [$proyecto['titulo'] => null],
    ]) ?>

    <article class="detalle">

        <header class="detalle__cabecera">
            <div class="detalle__titulo-bloque">
                <div class="detalle__chips">
                    <span class="chip chip--cat"><?= e($proyecto['categoria']) ?></span>
                    <?= badgeEstado($proyecto['estado']) ?>
                </div>
                <h1><?= e($proyecto['titulo']) ?></h1>
                <?php if (!empty($proyecto['descripcion_corta'])): ?>
                    <p class="detalle__entradilla"><?= e($proyecto['descripcion_corta']) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($puedeEditar): ?>
                <div class="detalle__acciones">
                    <a class="btn btn-outline" href="<?= e(url('admin/proyecto_form.php?id=' . $idProyecto)) ?>">
                        <?= icono('edit', 16) ?> Editar
                    </a>
                    <a class="btn btn-outline" href="<?= e(url('admin/hito_form.php?proyecto_id=' . $idProyecto)) ?>">
                        <?= icono('chart', 16) ?> Hitos
                    </a>
                    <a class="btn btn-primary" href="<?= e(url('admin/articulo_form.php?proyecto_id=' . $idProyecto)) ?>">
                        <?= icono('plus', 16) ?> Nuevo artículo
                    </a>
                </div>
            <?php endif; ?>
        </header>

        <dl class="ficha">
            <div><dt>Estado</dt><dd><?= badgeEstado($proyecto['estado']) ?></dd></div>
            <?php if (!empty($proyecto['version_actual'])): ?>
                <div><dt>Versión actual</dt><dd><?= e($proyecto['version_actual']) ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($proyecto['responsable_nombre'])): ?>
                <div><dt>Responsable</dt>
                     <dd><?= e(trim($proyecto['responsable_nombre'] . ' ' . $proyecto['responsable_apellido'])) ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($proyecto['fecha_inicio'])): ?>
                <div><dt>Fecha de inicio</dt><dd><?= e(fecha($proyecto['fecha_inicio'])) ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($proyecto['tecnologias'])): ?>
                <div><dt>Tecnologías</dt><dd><?= e($proyecto['tecnologias']) ?></dd></div>
            <?php endif; ?>
            <div><dt>Última actualización</dt><dd><?= e(fecha($proyecto['fecha_actualizacion'])) ?></dd></div>
        </dl>

        <?php if ($avance !== null): ?>
            <div class="avance-bloque">
                <div class="avance-bloque__texto">
                    <strong>Avance global</strong>
                    <span><?= $avance ?> %</span>
                </div>
                <div class="progress progress--lg" role="progressbar" aria-valuenow="<?= $avance ?>"
                     aria-valuemin="0" aria-valuemax="100" aria-label="Avance global del proyecto">
                    <span class="progress__bar progress__bar--p<?= (int) round($avance / 5) * 5 ?>"></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($proyecto['descripcion'])): ?>
            <section class="detalle__seccion">
                <h2>Descripción</h2>
                <div class="article-body"><?= sanearHTML($proyecto['descripcion']) ?></div>
            </section>
        <?php endif; ?>

        <?php if ($portadaMedia): ?>
        <figure class="detalle__portada">
            <img src="<?= e(urlMedia($portadaMedia)) ?>" alt="Imagen principal de <?= e($proyecto['titulo']) ?>">
        </figure>
        <?php endif; ?>

        <?php if ($hitos): ?>
        <section class="detalle__seccion">
            <div class="section-header section-header--flex">
                <h2>Seguimiento por hitos</h2>
                <?php if ($puedeEditar): ?>
                    <a class="btn btn-outline btn-sm" href="<?= e(url('admin/hito_form.php?proyecto_id=' . $idProyecto)) ?>">
                        <?= icono('plus', 15) ?> Gestionar hitos
                    </a>
                <?php endif; ?>
            </div>

            <ol class="timeline">
                <?php foreach ($hitos as $h): ?>
                <li class="timeline__item">
                    <div class="timeline__fecha"><?= e(fecha($h['fecha'])) ?></div>
                    <div class="timeline__cuerpo">
                        <h3><?= e($h['titulo']) ?></h3>
                        <?php if (!empty($h['descripcion'])): ?>
                            <p><?= nl2br(e($h['descripcion'])) ?></p>
                        <?php endif; ?>
                        <div class="timeline__meta">
                            <span class="chip"><?= (int) $h['porcentaje_avance'] ?> % de avance</span>
                            <?php if (!empty($h['autor_nombre'])): ?>
                                <span class="muted">Registrado por <?= e($h['autor_nombre']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="progress" role="progressbar"
                             aria-valuenow="<?= (int) $h['porcentaje_avance'] ?>"
                             aria-valuemin="0" aria-valuemax="100">
                            <span class="progress__bar progress__bar--p<?= (int) round((int) $h['porcentaje_avance'] / 5) * 5 ?>"></span>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php endif; ?>

        <section class="detalle__seccion">
            <div class="section-header section-header--flex">
                <h2>Documentación <span class="muted">(<?= count($articulos) ?> artículos)</span></h2>
                <?php if (puede('articulo.crear')): ?>
                    <a class="btn btn-outline btn-sm" href="<?= e(url('admin/articulo_form.php?proyecto_id=' . $idProyecto)) ?>">
                        <?= icono('plus', 15) ?> Nuevo artículo
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($articulos): ?>
                <?php foreach ($porTipo as $tipo => $lista): ?>
                    <h3 class="detalle__subtitulo"><?= e(etiquetaTipoArticulo($tipo)) ?></h3>
                    <div class="grid grid--articulos">
                        <?php foreach ($lista as $a) echo tarjetaArticulo($a + ['proyecto_titulo' => null]); ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    Este proyecto todavía no tiene documentación publicada.
                    <?php if (puede('articulo.crear')): ?>
                        <a href="<?= e(url('admin/articulo_form.php?proyecto_id=' . $idProyecto)) ?>">Crear el primer artículo</a>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($adjuntos): ?>
            <?= galeriaMedia($adjuntos) ?>
        <?php endif; ?>

    </article>
</div>

<?php cerrarPagina(); ?>
