<?php
// ============================================================
//  Plantilla: cabecera HTML común a todas las pantallas
//  Archivo: frontend/partials/header.php
//
//  Variables opcionales que puede definir la página antes de
//  incluirlo:  $pageTitle (string), $cssExtra (string), $descripcion
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';

$paginaActual = basename($_SERVER['PHP_SELF'] ?? '', '.php');

// Secciones que resaltan en el menú (agrupa detalle y listado)
$seccionDe = [
    'index'              => 'index',
    'proyectos'          => 'proyectos',
    'proyecto'           => 'proyectos',
    'wiki'               => 'wiki',
    'articulo'           => 'wiki',
    'noticias'           => 'noticias',
    'noticia'            => 'noticias',
    'acerca'             => 'acerca',
];
$seccionActiva = $seccionDe[$paginaActual] ?? '';

$enlaces = [
    'index'     => ['Inicio',     'index.php'],
    'proyectos' => ['Proyectos',  'proyectos.php'],
    'wiki'      => ['Wiki',       'wiki.php'],
    'noticias'  => ['Noticias',   'noticias.php'],
    'acerca'    => ['Acerca de',  'acerca.php'],
];

$usuario    = currentUser();
$flash      = obtenerFlash();
$descripcion = $descripcion ?? 'Sistema de Gestión y Estandarización de Documentación Técnica '
                            . 'de los proyectos de Telecom Venezuela, C.A.';
$cssExtra   = $cssExtra ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($descripcion) ?>">
    <meta name="author" content="Telecom Venezuela, C.A. — Departamento de Desarrollo">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#0B1F3A">
    <title><?= e($pageTitle ?? SITE_NAME) ?> · <?= e(SITE_NAME) ?></title>

    <link rel="icon" href="<?= e(url('assets/img/favicon.png')) ?>" type="image/png">
    <link rel="stylesheet" href="<?= e(url('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('css/pages.css')) ?>">
<?php if ($cssExtra !== ''): ?>
    <link rel="stylesheet" href="<?= e(url('css/' . $cssExtra)) ?>">
<?php endif; ?>

    <!-- Marca <html class="js"> sólo si hay JavaScript: así las
         animaciones de aparición se activan sin parpadeo previo
         y sin romper la CSP (el script lleva nonce).
         Aplica además el tema guardado ANTES del primer pintado
         para evitar el destello blanco en modo oscuro: resuelve la
         preferencia de localStorage ('auto'|'claro'|'oscuro') con
         prefers-color-scheme como valor por defecto. -->
    <script nonce="<?= e($GLOBALS['CSP_NONCE'] ?? '') ?>">
        document.documentElement.classList.add('js');
        // Primero se resuelve el tema (sin depender de localStorage
        // para el resultado); después se intenta leer la preferencia.
        var pref = 'auto';
        try { pref = localStorage.getItem('tw-tema') || 'auto'; } catch (e) { /* privado */ }
        var oscuro = pref === 'auto'
            ? window.matchMedia('(prefers-color-scheme: dark)').matches
            : pref === 'oscuro';
        document.documentElement.setAttribute('data-tema', oscuro ? 'oscuro' : 'claro');
        document.documentElement.setAttribute('data-tema-pref', pref);
    </script>
</head>
<body>

<a class="skip-link" href="#contenido">Saltar al contenido principal</a>

<div class="bar-top">
    <div class="bar-top__inner container">
        <span>República Bolivariana de Venezuela</span>
        <span class="bar-top__sep" aria-hidden="true">·</span>
        <span>Telecom Venezuela, C.A.</span>
        <span class="bar-top__derecha"><?= e(SITE_TAGLINE) ?></span>
    </div>
</div>

<header class="site-header">
    <div class="site-header__inner container">

        <a class="site-logo" href="<?= e(url('index.php')) ?>">
            <span class="logo-icon" aria-hidden="true">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H10l1.6 2H18a1.5 1.5 0 0 1 1.5 1.5V17A1.5 1.5 0 0 1 18 18.5H5.5A1.5 1.5 0 0 1 4 17z"/>
                    <path d="M8 11h8M8 14h5"/>
                </svg>
            </span>
            <span class="logo-text">
                <span class="logo-title"><?= e(SITE_NAME) ?></span>
                <span class="logo-sub">Documentación técnica</span>
            </span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false"
                aria-controls="nav-principal" aria-label="Abrir menú de navegación">
            <svg width="24" height="24" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M3 6h14M3 10h14M3 14h14"/>
            </svg>
        </button>

        <nav id="nav-principal" class="site-nav" aria-label="Navegación principal">
            <ul>
<?php foreach ($enlaces as $clave => [$texto, $ruta]): ?>
                <li>
                    <a href="<?= e(url($ruta)) ?>"
                       <?= $seccionActiva === $clave ? 'class="is-active" aria-current="page"' : '' ?>>
                        <?= e($texto) ?>
                    </a>
                </li>
<?php endforeach; ?>
            </ul>
        </nav>

        <div class="site-header__actions">

            <!-- Modo oscuro/claro: sin JS no se muestra (la clase
                 .js la pone el script del <head>). Lo maneja
                 main.js → módulo del tema. -->
            <button class="theme-toggle" type="button" data-tema-toggle
                    aria-pressed="false"
                    aria-label="Cambiar entre modo claro y modo oscuro">
                <svg class="theme-toggle__icon theme-toggle__icon--sol"
                     width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                     aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                </svg>
                <svg class="theme-toggle__icon theme-toggle__icon--luna"
                     width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
                     aria-hidden="true">
                    <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
                </svg>
            </button>

<?php if ($usuario === null): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('login.php')) ?>">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M8 4H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3M12 7l3 3-3 3M15 10H8"/>
                </svg>
                Iniciar sesión
            </a>
<?php else: ?>
            <span class="user-badge">
                <span class="user-dot" aria-hidden="true"></span>
                <span class="user-badge__nombre"><?= e($usuario['nombre']) ?></span>
                <?= badgeRol($usuario['rol']) ?>
            </span>

            <a class="btn btn-ghost btn-sm" href="<?= e(url('cuenta.php')) ?>">
                Mi cuenta
            </a>

            <?php if (puede('panel.acceder')): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('admin/index.php')) ?>">Panel</a>
            <?php endif; ?>

            <form class="form-salir" method="post" action="<?= e(url('logout.php')) ?>">
                <?= campoCSRF() ?>
                <button class="btn btn-ghost btn-sm" type="submit">Salir</button>
            </form>
<?php endif; ?>
        </div>

    </div>
</header>

<?php if ($flash !== null): ?>
<div class="container">
    <div class="alert alert-<?= e($flash['tipo'] ?? 'info') ?>" role="status">
        <?= e($flash['texto'] ?? '') ?>
    </div>
</div>
<?php endif; ?>

<main id="contenido" class="main-content">
