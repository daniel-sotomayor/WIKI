<?php
// ============================================================
//  Inicio — resumen del sistema
//  Archivo: frontend/index.php
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

$db = getDB();

// Contadores globales (índices, consultas baratas)
$numProyectos  = (int) dbValor('SELECT COUNT(*) FROM proyectos');
$numArticulos  = (int) dbValor('SELECT COUNT(*) FROM articulos WHERE publicado = 1');
$numUsuarios   = (int) dbValor('SELECT COUNT(*) FROM usuarios WHERE activo = 1');
$numNoticias   = (int) dbValor("SELECT COUNT(*) FROM noticias WHERE estado = 'publicada'");

// Últimos proyectos con su porcentaje de avance (último hito cargado)
$st = $db->prepare(
    "SELECT p.id, p.titulo, p.slug, p.descripcion_corta, p.tecnologias,
            p.version_actual, p.estado, c.nombre AS categoria,
            (SELECT h.porcentaje_avance FROM hitos h
              WHERE h.proyecto_id = p.id
              ORDER BY h.fecha DESC, h.id DESC LIMIT 1) AS avance
       FROM proyectos p
       JOIN categorias c ON c.id = p.categoria_id
   ORDER BY p.fecha_actualizacion DESC
      LIMIT 6"
);
$st->execute();
$proyectos = $st->fetchAll();

// Últimos artículos publicados
$st = $db->prepare(
    "SELECT a.id, a.titulo, a.slug, a.resumen, a.contenido, a.tipo, a.actualizado,
            p.titulo AS proyecto_titulo
       FROM articulos a
       JOIN proyectos p ON p.id = a.proyecto_id
      WHERE a.publicado = 1
   ORDER BY a.actualizado DESC
      LIMIT 4"
);
$st->execute();
$articulos = $st->fetchAll();

// Noticias destacadas / publicadas
$st = $db->prepare(
    "SELECT id, titulo, slug, resumen, categoria, fecha_publicacion, creado_en
       FROM noticias
      WHERE estado = 'publicada'
   ORDER BY destacada DESC, fecha_publicacion DESC
      LIMIT 3"
);
$st->execute();
$noticias = $st->fetchAll();

$portadas = portadasMedia('noticia', array_column($noticias, 'id'));
foreach ($noticias as $i => $n) {
    $noticias[$i]['imagen_principal'] = $portadas[$n['id']] ?? null;
}

$pageTitle = 'Inicio';
$descripcion = 'Portal de gestión y estandarización de la documentación técnica de los '
             . 'proyectos de Telecom Venezuela, C.A.: fichas técnicas, wiki, noticias y multimedia.';
abrirPagina();
?>

<section class="hero">
    <div class="hero__inner container">
        <p class="hero__eyebrow">Telecom Venezuela, C.A. · Departamento de Desarrollo</p>
        <h1 class="hero__titulo">Documentación técnica <span class="destacado">estandarizada</span>
            para todo el portafolio de proyectos</h1>
        <p class="hero__texto">
            Un único lugar para registrar fichas técnicas, publicar la documentación de cada
            proyecto con control de versiones, dar seguimiento a los hitos y difundir las
            noticias institucionales.
        </p>

        <div class="hero-actions">
            <a class="btn btn-gold btn-lg" href="<?= e(url('proyectos.php')) ?>">
                Ver proyectos
            </a>
            <a class="btn btn-outline btn-lg" href="<?= e(url('wiki.php')) ?>">
                Consultar la wiki
            </a>
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <span class="hero-stat__num" data-count="<?= $numProyectos ?>">0</span>
                <span class="hero-stat__label">Proyectos registrados</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat__num" data-count="<?= $numArticulos ?>">0</span>
                <span class="hero-stat__label">Artículos publicados</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat__num" data-count="<?= $numNoticias ?>">0</span>
                <span class="hero-stat__label">Noticias</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat__num" data-count="<?= $numUsuarios ?>">0</span>
                <span class="hero-stat__label">Usuarios activos</span>
            </div>
        </div>
    </div>
</section>

<?php if ($proyectos): ?>
<section class="section">
    <div class="container">
        <div class="section-header section-header--flex">
            <div>
                <h2>Proyectos recientes</h2>
                <p class="muted">Fichas técnicas con estado, responsable y avance.</p>
            </div>
            <a class="btn btn-outline" href="<?= e(url('proyectos.php')) ?>">
                Ver todos <?= icono('arrow', 15) ?>
            </a>
        </div>
        <div class="grid grid--proyectos">
            <?php foreach ($proyectos as $p) echo tarjetaProyecto($p); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($articulos): ?>
<section class="section section--alt">
    <div class="container">
        <div class="section-header section-header--flex">
            <div>
                <h2>Últimos artículos de la wiki</h2>
                <p class="muted">Documentación técnica revisada y publicada por el equipo.</p>
            </div>
            <a class="btn btn-outline" href="<?= e(url('wiki.php')) ?>">
                Ir a la wiki <?= icono('arrow', 15) ?>
            </a>
        </div>
        <div class="grid grid--articulos">
            <?php foreach ($articulos as $a) echo tarjetaArticulo($a); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($noticias): ?>
<section class="section">
    <div class="container">
        <div class="section-header section-header--flex">
            <div>
                <h2>Noticias destacadas</h2>
                <p class="muted">Actualidad institucional y avances del departamento.</p>
            </div>
            <a class="btn btn-outline" href="<?= e(url('noticias.php')) ?>">
                Todas las noticias <?= icono('arrow', 15) ?>
            </a>
        </div>
        <div class="grid grid--noticias">
            <?php foreach ($noticias as $n) echo tarjetaNoticia($n, true); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section section--cta">
    <div class="container cta-box">
        <div>
            <h2>¿Querés saber más sobre la empresa?</h2>
            <p>Conocé la misión, la visión y el marco de trabajo del Departamento de Desarrollo,
               o escribinos directamente desde el formulario de contacto.</p>
        </div>
        <a class="btn btn-gold btn-lg" href="<?= e(url('acerca.php')) ?>">Quiénes somos</a>
    </div>
</section>

<?php cerrarPagina(); ?>
