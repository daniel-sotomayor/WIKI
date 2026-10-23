<?php
// ============================================================
//  Noticia — vista pública
//  Archivo: frontend/noticia.php
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

$slug = saneaGET('slug', 300);
if ($slug === '') {
    paginaNoEncontrada('noticia');
}

// La única condición de visibilidad es estado = 'publicada'.
$noticia = dbUnica(
    "SELECT n.*, u.nombre AS autor_nombre, u.apellido AS autor_apellido
       FROM noticias n
  LEFT JOIN usuarios u ON u.id = n.autor_id
      WHERE n.slug = ? AND n.estado = 'publicada'
      LIMIT 1",
    [$slug]
);

if (!$noticia) {
    paginaNoEncontrada('noticia');
}

$idNoticia  = (int) $noticia['id'];
$adjuntos   = listarMedia('noticia', $idNoticia);
$portada    = mediaPrincipal('noticia', $idNoticia);

$pageTitle   = $noticia['titulo'];
$descripcion = $noticia['resumen'] ?: resumir($noticia['contenido'], 160);
$jsExtra     = '';
abrirPagina();
?>

<div class="container">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Noticias' => 'noticias.php'],
        [$noticia['titulo'] => null],
    ]) ?>

    <article class="detalle">

        <header class="detalle__cabecera">
            <div class="detalle__titulo-bloque">
                <div class="detalle__chips">
                    <?php if (!empty($noticia['categoria'])): ?>
                        <span class="chip chip--cat"><?= e($noticia['categoria']) ?></span>
                    <?php endif; ?>
                    <span class="card__fecha">
                        <?= e(fecha($noticia['fecha_publicacion'] ?? $noticia['creado_en'], 'd \d\e F \d\e Y')) ?>
                    </span>
                </div>
                <h1><?= e($noticia['titulo']) ?></h1>
                <?php if (!empty($noticia['resumen'])): ?>
                    <p class="detalle__entradilla"><?= e($noticia['resumen']) ?></p>
                <?php endif; ?>
            </div>

            <?php if (puede('noticia.editar') && puedeEditarPropio($noticia)): ?>
                <div class="detalle__acciones">
                    <a class="btn btn-outline"
                       href="<?= e(url('admin/noticia_form.php?id=' . $idNoticia)) ?>">
                        <?= icono('edit', 16) ?> Editar
                    </a>
                </div>
            <?php endif; ?>
        </header>

        <?php if ($portada): ?>
            <figure class="detalle__portada">
                <img src="<?= e(urlMedia($portada)) ?>" alt="<?= e($noticia['titulo']) ?>">
            </figure>
        <?php endif; ?>

        <div class="article-body">
            <?= sanearHTML($noticia['contenido']) ?>
        </div>

        <?php if ($adjuntos): ?>
            <?= galeriaMedia($adjuntos) ?>
        <?php endif; ?>

        <footer class="detalle__pie">
            <?php if (!empty($noticia['autor_nombre'])): ?>
                <span class="muted">Publicado por
                    <?= e(trim($noticia['autor_nombre'] . ' ' . $noticia['autor_apellido'])) ?>
                </span>
            <?php endif; ?>
            <a class="btn btn-ghost" href="<?= e(url('noticias.php')) ?>">← Todas las noticias</a>
        </footer>

    </article>
</div>

<?php cerrarPagina(); ?>
