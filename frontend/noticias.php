<?php
// ============================================================
//  Noticias institucionales — listado público
//  Archivo: frontend/noticias.php
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

$q  = saneaGET('q', 80);
$cat = saneaGET('cat', 100);

// Categorías reales existentes (lista blanca contra inyección de basura)
$categorias = array_column(
    getDB()->query(
        "SELECT DISTINCT categoria FROM noticias
          WHERE estado = 'publicada' AND categoria IS NOT NULL AND categoria <> ''
          ORDER BY categoria"
    )->fetchAll(),
    'categoria'
);

// Si el parámetro no corresponde a una categoría real, se ignora.
if ($cat !== '' && !in_array($cat, $categorias, true)) {
    $cat = '';
}

$where  = ["n.estado = 'publicada'", 'n.fecha_publicacion IS NOT NULL',
           'n.fecha_publicacion <= NOW()'];
$params = [];

if ($q !== '') {
    $like = '%' . escaparLike($q) . '%';
    $where[] = '(n.titulo LIKE ? OR n.resumen LIKE ? OR n.contenido LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($cat !== '') {
    $where[] = 'n.categoria = ?';
    $params[] = $cat;
}

$condicion = implode(' AND ', $where);
$db = getDB();

$total = (int) dbValor("SELECT COUNT(*) FROM noticias n WHERE $condicion", $params);
$pag   = paginar($total, POR_PAGINA);

$st = $db->prepare(
    "SELECT n.id, n.titulo, n.slug, n.resumen, n.categoria,
            n.fecha_publicacion, n.creado_en, n.destacada,
            u.nombre AS autor_nombre, u.apellido AS autor_apellido
       FROM noticias n
  LEFT JOIN usuarios u ON u.id = n.autor_id
      WHERE $condicion
   ORDER BY n.destacada DESC, n.fecha_publicacion DESC, n.id DESC
      LIMIT {$pag['por_pagina']} OFFSET {$pag['offset']}"
);
$st->execute($params);
$noticias = $st->fetchAll();

$portadas = portadasMedia('noticia', array_column($noticias, 'id'));
foreach ($noticias as $i => $n) {
    $noticias[$i]['imagen_principal'] = $portadas[$n['id']] ?? null;
}

// La noticia destacada ocupa el ancho completo sólo en la primera página
$destacada = null;
if ($pag['pagina'] === 1 && $noticias && (int) $noticias[0]['destacada'] === 1) {
    $destacada = array_shift($noticias);
}

$pageTitle = 'Noticias';
$descripcion = 'Actualidad institucional de Telecom Venezuela, C.A. y avances de los proyectos.';
abrirPagina();
?>

<div class="container">
    <?= migaDePan([['Inicio' => 'index.php'], ['Noticias' => null]]) ?>

    <div class="page-head">
        <div>
            <h1>Noticias</h1>
            <p class="muted">Comunicados, avances de proyectos y actividades del departamento.</p>
        </div>
        <div class="page-head__acciones">
            <?php if (puede('noticia.crear')): ?>
                <a class="btn btn-outline" href="<?= e(url('noticias_crear.php')) ?>">
                    <?= icono('plus', 16) ?> Nueva noticia
                </a>
            <?php endif; ?>
            <?php if (puede('noticia.moderar')): ?>
                <a class="btn btn-primary" href="<?= e(url('noticias_moderar.php')) ?>">
                    <?= icono('shield', 16) ?> Moderar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form class="filtros" method="get" action="<?= e(url('noticias.php')) ?>">
        <div class="filtros__campo filtros__campo--grande">
            <label class="form-label" for="q">Buscar noticias</label>
            <input class="form-control" type="search" id="q" name="q" maxlength="80"
                   value="<?= e($q) ?>" placeholder="Título, resumen o contenido…">
        </div>

        <?php if ($categorias): ?>
        <div class="filtros__campo">
            <label class="form-label" for="cat">Categoría</label>
            <select class="form-control" id="cat" name="cat">
                <option value="">Todas</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= e($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="filtros__acciones">
            <button class="btn btn-primary" type="submit">Filtrar</button>
            <?php if ($q !== '' || $cat !== ''): ?>
                <a class="btn btn-ghost" href="<?= e(url('noticias.php')) ?>">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>

    <p class="resultados" role="status"><?= e(resumenPaginacion($pag)) ?></p>

    <?php if ($destacada): ?>
        <div class="grid grid--destacada">
            <?= tarjetaNoticia($destacada, true) ?>
        </div>
    <?php endif; ?>

    <?php if ($noticias): ?>
        <div class="grid grid--noticias">
            <?php foreach ($noticias as $n) echo tarjetaNoticia($n); ?>
        </div>
        <?= htmlPaginacion($pag) ?>
    <?php elseif (!$destacada): ?>
        <div class="alert alert-info">Todavía no hay noticias publicadas.</div>
    <?php endif; ?>
</div>

<?php cerrarPagina(); ?>
