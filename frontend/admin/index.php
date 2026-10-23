<?php
// ============================================================
//  Panel de administración — resumen y accesos rápidos
//  Archivo: frontend/admin/index.php
//  Requiere: capacidad `panel.acceder` (editor o superior)
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';

requierePermiso('panel.acceder');

$usuario = currentUser();
$esAdmin = puede('usuario.gestionar');

// ── Contadores ──────────────────────────────────────────────
$db        = getDB();
$contar    = fn(string $sql): int => (int) $db->query($sql)->fetchColumn();

$numProyectos     = $contar('SELECT COUNT(*) FROM proyectos');
$numArticulos     = $contar('SELECT COUNT(*) FROM articulos');
$numNoticiasPend  = $contar("SELECT COUNT(*) FROM noticias WHERE estado = 'pendiente'");
$numUsuarios      = $contar('SELECT COUNT(*) FROM usuarios WHERE activo = 1');
$numMensajesNew   = $contar('SELECT COUNT(*) FROM mensajes_contacto WHERE leido = 0');

// ── Contenido reciente ──────────────────────────────────────
$proyectos = $db->query(
    'SELECT p.id, p.titulo, p.slug, p.estado, p.version_actual, p.creado_en,
            c.nombre AS categoria
       FROM proyectos p
  LEFT JOIN categorias c ON c.id = p.categoria_id
   ORDER BY p.creado_en DESC
      LIMIT 8'
)->fetchAll();

$articulos = $db->query(
    'SELECT a.id, a.titulo, a.slug, a.tipo, a.publicado, a.version, a.actualizado,
            p.titulo AS proyecto
       FROM articulos a
       JOIN proyectos p ON p.id = a.proyecto_id
   ORDER BY a.actualizado DESC
      LIMIT 8'
)->fetchAll();

$noticias = $db->query(
    'SELECT n.id, n.titulo, n.slug, n.estado, n.destacada, n.creado_en,
            u.nombre AS autor_nombre
       FROM noticias n
  LEFT JOIN usuarios u ON u.id = n.autor_id
   ORDER BY n.creado_en DESC
      LIMIT 8'
)->fetchAll();

$pageTitle = 'Panel de administración';
$cssExtra  = 'admin.css';
abrirPagina();
?>

<div class="container container--panel">

    <div class="seccion-cabecera">
        <div>
            <h1>Panel de administración</h1>
            <p class="muted">Gestión de contenido: proyectos, artículos, hitos y noticias.</p>
        </div>
        <?= badgeRol($usuario['rol']) ?>
    </div>

    <!-- ── Contadores ── -->
    <ul class="rejilla rejilla--5 lista-stats">
        <li class="tarjeta-stat"><strong><?= $numProyectos ?></strong><span>Proyectos</span></li>
        <li class="tarjeta-stat"><strong><?= $numArticulos ?></strong><span>Artículos (todos)</span></li>
        <li class="tarjeta-stat<?= $numNoticiasPend ? ' tarjeta-stat--aviso' : '' ?>">
            <strong><?= $numNoticiasPend ?></strong><span>Noticias pendientes</span></li>
        <li class="tarjeta-stat">
            <strong><?= $numUsuarios ?></strong><span>Usuarios activos</span></li>
        <?php if ($esAdmin): ?>
        <li class="tarjeta-stat tarjeta-stat--enlace">
            <a href="<?= e(url('admin/usuarios.php')) ?>">
                <strong><?= $numUsuarios ?></strong><span>Gestionar usuarios →</span>
            </a>
        </li>
        <li class="tarjeta-stat tarjeta-stat--enlace<?= $numMensajesNew ? ' tarjeta-stat--aviso' : '' ?>">
            <a href="<?= e(url('admin/mensajes_contacto.php')) ?>">
                <strong><?= $numMensajesNew ?></strong><span>Mensajes sin leer →</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <!-- ── Accesos rápidos ── -->
    <nav class="acciones-rapidas" aria-label="Acciones rápidas">
        <?php if (puede('proyecto.crear')): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(url('admin/proyecto_form.php')) ?>">
                <?= icono('plus', 15) ?> Nuevo proyecto</a>
        <?php endif; ?>
        <?php if (puede('articulo.crear')): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(url('admin/articulo_form.php')) ?>">
                <?= icono('plus', 15) ?> Nuevo artículo</a>
        <?php endif; ?>
        <?php if (puede('noticia.crear')): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(url('noticias_crear.php')) ?>">
                <?= icono('plus', 15) ?> Nueva noticia</a>
        <?php endif; ?>
        <?php if (puede('noticia.moderar')): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('noticias_moderar.php')) ?>">
                <?= icono('news', 15) ?> Moderar noticias
                <?= $numNoticiasPend ? "($numNoticiasPend)" : '' ?></a>
        <?php endif; ?>
        <?php if ($esAdmin): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('admin/usuario_form.php')) ?>">
                <?= icono('users', 15) ?> Nuevo usuario</a>
            <a class="btn btn-outline btn-sm" href="<?= e(url('admin/mensajes_contacto.php')) ?>">
                <?= icono('mail', 15) ?> Mensajes de contacto
                <?= $numMensajesNew ? "($numMensajesNew)" : '' ?></a>
            <?php if (puede('bitacora.ver')): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('admin/seguridad.php')) ?>">
                    <?= icono('shield', 15) ?> Bitácora de seguridad</a>
            <?php endif; ?>
        <?php endif; ?>
        <a class="btn btn-outline btn-sm" href="<?= e(url('admin/sistema.php')) ?>">
            <?= icono('server', 15) ?> Diagnóstico del sistema</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('cuenta.php')) ?>">Mi cuenta</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('proyectos.php')) ?>">Ver proyectos (público)</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('wiki.php')) ?>">Ver wiki (pública)</a>
    </nav>

    <!-- ── Proyectos ── -->
    <section class="panel">
        <header class="panel__cabecera">
            <h2 class="panel__titulo">Proyectos recientes</h2>
            <a class="panel__enlace" href="<?= e(url('proyectos.php')) ?>">Ver todos →</a>
        </header>
        <?php if (!$proyectos): ?>
            <p class="vacio">No hay proyectos registrados todavía.</p>
        <?php else: ?>
        <div class="tabla-envoltura">
            <table class="tabla-datos">
                <thead><tr>
                    <th scope="col">Título</th><th scope="col">Categoría</th>
                    <th scope="col">Estado</th><th scope="col">Versión</th>
                    <th scope="col">Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($proyectos as $p): ?>
                    <tr>
                        <td><a href="<?= e(url('proyecto.php?slug=' . urlencode($p['slug']))) ?>">
                            <?= e($p['titulo']) ?></a></td>
                        <td><?= e($p['categoria'] ?? '—') ?></td>
                        <td><?= badgeEstado($p['estado']) ?></td>
                        <td><?= $p['version_actual'] ? 'v' . e($p['version_actual']) : '—' ?></td>
                        <td class="celda-acciones">
                            <?php if (puede('proyecto.editar')): ?>
                                <a class="btn btn-sm btn-outline"
                                   href="<?= e(url('admin/proyecto_form.php?id=' . (int) $p['id'])) ?>">Editar</a>
                            <?php endif; ?>
                            <?php if (puede('hito.gestionar')): ?>
                                <a class="btn btn-sm btn-ghost"
                                   href="<?= e(url('admin/hito_form.php?proyecto_id=' . (int) $p['id'])) ?>">Seguimiento</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- ── Artículos ── -->
    <section class="panel">
        <header class="panel__cabecera">
            <h2 class="panel__titulo">Artículos recientes</h2>
            <a class="panel__enlace" href="<?= e(url('wiki.php')) ?>">Ver todos →</a>
        </header>
        <?php if (!$articulos): ?>
            <p class="vacio">No hay artículos registrados todavía.</p>
        <?php else: ?>
        <div class="tabla-envoltura">
            <table class="tabla-datos">
                <thead><tr>
                    <th scope="col">Título</th><th scope="col">Proyecto</th>
                    <th scope="col">Tipo</th><th scope="col">Estado</th>
                    <th scope="col">Versión</th><th scope="col">Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($articulos as $a): ?>
                    <tr>
                        <td><strong><?= e($a['titulo']) ?></strong></td>
                        <td><?= e($a['proyecto']) ?></td>
                        <td><?= e(etiquetaTipoArticulo($a['tipo'])) ?></td>
                        <td><?= $a['publicado'] ? badgeEstado('publicada') : badgeEstado('borrador') ?></td>
                        <td>v<?= (int) $a['version'] ?></td>
                        <td class="celda-acciones">
                            <?php if (puede('articulo.editar')): ?>
                                <a class="btn btn-sm btn-outline"
                                   href="<?= e(url('admin/articulo_form.php?id=' . (int) $a['id'])) ?>">Editar</a>
                            <?php endif; ?>
                            <?php if (puede('articulo.historial')): ?>
                                <a class="btn btn-sm btn-ghost"
                                   href="<?= e(url('admin/articulo_historial.php?id=' . (int) $a['id'])) ?>">Historial</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- ── Noticias ── -->
    <section class="panel">
        <header class="panel__cabecera">
            <h2 class="panel__titulo">Noticias recientes</h2>
            <a class="panel__enlace" href="<?= e(url('noticias.php')) ?>">Ver todas →</a>
        </header>
        <?php if (!$noticias): ?>
            <p class="vacio">No hay noticias registradas todavía.</p>
        <?php else: ?>
        <div class="tabla-envoltura">
            <table class="tabla-datos">
                <thead><tr>
                    <th scope="col">Título</th><th scope="col">Autor</th>
                    <th scope="col">Estado</th><th scope="col">Fecha</th>
                    <th scope="col">Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($noticias as $n): ?>
                    <tr>
                        <td>
                            <?= e($n['titulo']) ?>
                            <?php if ($n['destacada']): ?>
                                <span class="chip chip--destacada" title="Destacada">★</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($n['autor_nombre'] ?? '—') ?></td>
                        <td><?= badgeEstado($n['estado']) ?></td>
                        <td class="muted"><?= e(fecha($n['creado_en'], 'd/m/Y')) ?></td>
                        <td class="celda-acciones">
                            <?php if (puede('noticia.editar')): ?>
                                <a class="btn btn-sm btn-outline"
                                   href="<?= e(url('admin/noticia_form.php?id=' . (int) $n['id'])) ?>">Editar</a>
                            <?php endif; ?>
                            <?php if ($n['estado'] === 'pendiente' && puede('noticia.moderar')): ?>
                                <a class="btn btn-sm btn-ghost"
                                   href="<?= e(url('noticias_moderar.php')) ?>">Moderar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

</div>

<?php cerrarPagina(); ?>
