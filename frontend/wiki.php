<?php
// ============================================================
//  Wiki — buscador y listado de artículos técnicos
//  Archivo: frontend/wiki.php
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

$tipos = ['guion', 'arquitectura', 'mecanica', 'personaje', 'nivel', 'tecnico', 'otro'];

$q       = saneaGET('q', 80);
$tipo    = enLista(saneaGET('tipo', 20), $tipos, '');
$proyectoSlug = saneaGET('proyecto', 220);
$tagSlug = saneaGET('tag', 120);

$where  = ['a.publicado = 1'];
$params = [];

if ($q !== '') {
    $like = '%' . escaparLike($q) . '%';
    $where[] = '(a.titulo LIKE ? OR a.contenido LIKE ? OR a.resumen LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($tipo !== '') {
    $where[] = 'a.tipo = ?';
    $params[] = $tipo;
}
if ($proyectoSlug !== '') {
    $where[] = 'p.slug = ?';
    $params[] = $proyectoSlug;
}
if ($tagSlug !== '') {
    $where[] = 'EXISTS (SELECT 1 FROM articulo_tags atf
                          JOIN tags tf ON tf.id = atf.tag_id
                         WHERE atf.articulo_id = a.id AND tf.slug = ?)';
    $params[] = $tagSlug;
}

$condicion = implode(' AND ', $where);
$db = getDB();

$total = (int) dbValor(
    "SELECT COUNT(*) FROM articulos a
       JOIN proyectos p ON p.id = a.proyecto_id
      WHERE $condicion",
    $params
);

$pag = paginar($total, POR_PAGINA);

// Los alias at2 / atg son válidos también en MySQL (se conservan del
// código original para no introducir diferencias innecesarias).
$st = $db->prepare(
    "SELECT a.id, a.titulo, a.slug, a.resumen, a.contenido, a.tipo,
            a.actualizado, a.creado_en, p.titulo AS proyecto_titulo,
            (SELECT GROUP_CONCAT(DISTINCT t.nombre ORDER BY t.nombre SEPARATOR '||')
               FROM articulo_tags atg
               JOIN tags t ON t.id = atg.tag_id
              WHERE atg.articulo_id = a.id) AS tags_nombres,
            (SELECT GROUP_CONCAT(DISTINCT t.slug ORDER BY t.nombre SEPARATOR '||')
               FROM articulo_tags atg
               JOIN tags t ON t.id = atg.tag_id
              WHERE atg.articulo_id = a.id) AS tags_slugs
       FROM articulos a
       JOIN proyectos p ON p.id = a.proyecto_id
      WHERE $condicion
   ORDER BY a.actualizado DESC
      LIMIT {$pag['por_pagina']} OFFSET {$pag['offset']}"
);
$st->execute($params);
$articulos = $st->fetchAll();

$st = $db->query('SELECT id, titulo, slug FROM proyectos ORDER BY titulo');
$listaProyectos = $st->fetchAll();

$st = $db->query(
    'SELECT t.id, t.nombre, t.slug, COUNT(DISTINCT atg.articulo_id) AS usos
       FROM tags t
       JOIN articulo_tags atg ON atg.tag_id = t.id
       JOIN articulos a ON a.id = atg.articulo_id AND a.publicado = 1
   GROUP BY t.id, t.nombre, t.slug
   ORDER BY usos DESC, t.nombre ASC
      LIMIT 25'
);
$nubeTags = $st->fetchAll();

$hayFiltro = ($q !== '' || $tipo !== '' || $proyectoSlug !== '' || $tagSlug !== '');

$pageTitle = 'Wiki técnica';
$descripcion = 'Buscador de documentación técnica: guiones, arquitectura, mecánicas, niveles '
             . 'y fichas técnicas de los proyectos.';
abrirPagina();
?>

<div class="container">
    <?= migaDePan([['Inicio' => 'index.php'], ['Wiki técnica' => null]]) ?>

    <div class="page-head">
        <div>
            <h1>Wiki técnica</h1>
            <p class="muted">Documentación estandarizada por proyecto, con control de versiones
               y etiquetas.</p>
        </div>
        <?= botonSiPuede('articulo.crear', 'admin/articulo_form.php', 'Nuevo artículo') ?>
    </div>

    <form class="filtros filtros--wiki" method="get" action="<?= e(url('wiki.php')) ?>">
        <div class="filtros__campo filtros__campo--grande">
            <label class="form-label" for="q">Buscar en la documentación</label>
            <input class="form-control" type="search" id="q" name="q" maxlength="80"
                   value="<?= e($q) ?>" placeholder="Título, resumen o contenido…">
        </div>

        <div class="filtros__campo">
            <label class="form-label" for="tipo">Tipo de documento</label>
            <select class="form-control" id="tipo" name="tipo">
                <option value="">Todos</option>
                <?php foreach ($tipos as $t): ?>
                    <option value="<?= e($t) ?>" <?= $tipo === $t ? 'selected' : '' ?>>
                        <?= e(etiquetaTipoArticulo($t)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtros__campo">
            <label class="form-label" for="proyecto">Proyecto</label>
            <select class="form-control" id="proyecto" name="proyecto">
                <option value="">Todos</option>
                <?php foreach ($listaProyectos as $p): ?>
                    <option value="<?= e($p['slug']) ?>" <?= $proyectoSlug === $p['slug'] ? 'selected' : '' ?>>
                        <?= e($p['titulo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtros__acciones">
            <button class="btn btn-primary" type="submit">Buscar</button>
            <?php if ($hayFiltro): ?>
                <a class="btn btn-ghost" href="<?= e(url('wiki.php')) ?>">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($nubeTags): ?>
    <div class="nube-tags" aria-label="Etiquetas más usadas">
        <span class="nube-tags__label">Etiquetas:</span>
        <ul class="chips">
            <?php foreach ($nubeTags as $t): ?>
                <li>
                    <a class="chip chip--tag <?= $tagSlug === $t['slug'] ? 'is-active' : '' ?>"
                       href="<?= e(url('wiki.php?tag=' . rawurlencode($t['slug']))) ?>">
                        <?= e($t['nombre']) ?> <span class="chip__num"><?= (int) $t['usos'] ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <p class="resultados" role="status"><?= e(resumenPaginacion($pag)) ?></p>

    <?php if ($articulos): ?>
        <div class="grid grid--articulos">
            <?php foreach ($articulos as $a) echo tarjetaArticulo($a); ?>
        </div>
        <?= htmlPaginacion($pag) ?>
    <?php else: ?>
        <div class="alert alert-info">
            No hay artículos que coincidan con la búsqueda.
            <?php if (puede('articulo.crear')): ?>
                <a href="<?= e(url('admin/articulo_form.php')) ?>">Crear uno nuevo</a>.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php cerrarPagina(); ?>
