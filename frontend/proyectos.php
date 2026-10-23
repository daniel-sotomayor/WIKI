<?php
// ============================================================
//  Listado de proyectos (fichas técnicas) — público
//  Archivo: frontend/proyectos.php
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

$estados = ['activo', 'en_desarrollo', 'pausado', 'archivado'];

// ── Filtros de entrada (lista blanca + límites) ─────────────
$q      = saneaGET('q', 80);
$cat    = saneaGET('cat', 120);
$estado = enLista(saneaGET('estado', 30), $estados, '');

$where  = ['1 = 1'];
$params = [];

if ($q !== '') {
    // escaparLike neutraliza % y _ para que el usuario pueda buscarlos
    // literalmente (MySQL usa \ como carácter de escape por defecto).
    $like = '%' . escaparLike($q) . '%';
    $where[] = '(p.titulo LIKE ? OR p.descripcion_corta LIKE ?)';
    $params[] = $like;
    $params[] = $like;
}
if ($cat !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $cat;
}
if ($estado !== '') {
    $where[] = 'p.estado = ?';
    $params[] = $estado;
}

$condicion = implode(' AND ', $where);
$db = getDB();

$total = (int) dbValor(
    "SELECT COUNT(*) FROM proyectos p
       JOIN categorias c ON c.id = p.categoria_id
      WHERE $condicion",
    $params
);

$pag = paginar($total, POR_PAGINA);

$st = $db->prepare(
    "SELECT p.id, p.titulo, p.slug, p.descripcion_corta, p.tecnologias,
            p.version_actual, p.estado, c.nombre AS categoria,
            (SELECT h.porcentaje_avance FROM hitos h
              WHERE h.proyecto_id = p.id
              ORDER BY h.fecha DESC, h.id DESC LIMIT 1) AS avance
       FROM proyectos p
       JOIN categorias c ON c.id = p.categoria_id
      WHERE $condicion
   ORDER BY p.fecha_actualizacion DESC
      LIMIT {$pag['por_pagina']} OFFSET {$pag['offset']}"
);
$st->execute($params);
$proyectos = $st->fetchAll();

$st = $db->query('SELECT c.slug, c.nombre, COUNT(p.id) AS total
                    FROM categorias c
                    LEFT JOIN proyectos p ON p.categoria_id = c.id
                GROUP BY c.id, c.nombre, c.slug
                ORDER BY c.nombre');
$categorias = $st->fetchAll();

$tituloPagina = 'Proyectos';
$pageTitle    = 'Proyectos';
abrirPagina();
?>

<div class="container">
    <?= migaDePan([['Inicio' => 'index.php'], ['Proyectos' => null]]) ?>

    <div class="page-head">
        <div>
            <h1>Fichas técnicas de proyectos</h1>
            <p class="muted">Registro único de los proyectos realizados y por realizar:
               estado, responsable, tecnologías y avance.</p>
        </div>
        <?= botonSiPuede('proyecto.crear', 'admin/proyecto_form.php', 'Nuevo proyecto') ?>
    </div>

    <form class="filtros" method="get" action="<?= e(url('proyectos.php')) ?>">
        <div class="filtros__campo filtros__campo--grande">
            <label class="form-label" for="q">Buscar</label>
            <input class="form-control" type="search" id="q" name="q" maxlength="80"
                   value="<?= e($q) ?>" placeholder="Título o descripción del proyecto…">
        </div>

        <div class="filtros__campo">
            <label class="form-label" for="cat">Categoría</label>
            <select class="form-control" id="cat" name="cat">
                <option value="">Todas</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= e($c['slug']) ?>" <?= $cat === $c['slug'] ? 'selected' : '' ?>>
                        <?= e($c['nombre']) ?> (<?= (int) $c['total'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtros__campo">
            <label class="form-label" for="estado">Estado</label>
            <select class="form-control" id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach ($estados as $e): ?>
                    <option value="<?= e($e) ?>" <?= $estado === $e ? 'selected' : '' ?>>
                        <?= e(etiquetaEstado($e)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtros__acciones">
            <button class="btn btn-primary" type="submit">Filtrar</button>
            <a class="btn btn-ghost" href="<?= e(url('proyectos.php')) ?>">Limpiar</a>
        </div>
    </form>

    <p class="resultados" role="status"><?= e(resumenPaginacion($pag)) ?></p>

    <?php if ($proyectos): ?>
        <div class="grid grid--proyectos">
            <?php foreach ($proyectos as $p) echo tarjetaProyecto($p); ?>
        </div>
        <?= htmlPaginacion($pag) ?>
    <?php else: ?>
        <div class="alert alert-info">
            No se encontraron proyectos con esos criterios.
            <?php if (puede('proyecto.crear')): ?>
                <a href="<?= e(url('admin/proyecto_form.php')) ?>">Crear el primero</a>.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php cerrarPagina(); ?>
