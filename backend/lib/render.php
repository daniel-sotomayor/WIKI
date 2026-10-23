<?php
// ============================================================
//  RENDERIZADO DE COMPONENTES REUTILIZABLES
//  Archivo: backend/lib/render.php
//
//  El código original repetía el mismo HTML de tarjetas en
//  index.php, proyectos.php, wiki.php, noticias.php y
//  proyecto.php (cada uno con pequeñas diferencias). Acá vive
//  una única versión de cada componente, usada por todas las
//  pantallas.
// ============================================================

require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/paginate.php';

// ── Miga de pan ─────────────────────────────────────────────

/**
 * $items = [['Inicio' => 'index.php'], ['Proyectos' => 'proyectos.php'], ['Batalla' => null]]
 * El último elemento no lleva enlace (es la página actual).
 */
function migaDePan(array $items): string {
    if (!$items) {
        return '';
    }
    $html = '<nav class="breadcrumb" aria-label="Miga de pan"><ol>';
    $ultimo = count($items) - 1;

    foreach ($items as $i => $item) {
        $nombre = array_key_first($item);
        $ruta   = $item[$nombre];
        $sep    = $i > 0 ? '<span class="breadcrumb__sep" aria-hidden="true">/</span>' : '';

        if ($i === $ultimo || $ruta === null) {
            $html .= $sep . '<li class="breadcrumb__actual" aria-current="page">' . e($nombre) . '</li>';
        } else {
            $html .= $sep . '<li><a href="' . e(url($ruta)) . '">' . e($nombre) . '</a></li>';
        }
    }

    return $html . '</ol></nav>';
}

// ── Insignias ───────────────────────────────────────────────

function badgeEstado(string $estado): string {
    return '<span class="status-badge status-' . e($estado) . '">'
         . e(etiquetaEstado($estado)) . '</span>';
}

function badgeRol(string $rol): string {
    return '<span class="role-tag role-' . e($rol) . '">' . e(etiquetaRol($rol)) . '</span>';
}

function badgeTipo(string $tipo): string {
    return '<span class="chip chip--tipo">' . e(etiquetaTipoArticulo($tipo)) . '</span>';
}

// ── Tarjeta de proyecto ─────────────────────────────────────

function tarjetaProyecto(array $p): string {
    $avance = isset($p['avance']) ? (int) $p['avance'] : null;

    $html = '<article class="card card--proyecto reveal" data-estado="' . e($p['estado']) . '">';
    $html .= '<a class="card__link" href="' . e(url('proyecto.php?slug=' . rawurlencode($p['slug']))) . '">';
    $html .= '<div class="card__cabeza">';
    $html .= '<span class="chip chip--cat">' . e($p['categoria'] ?? 'Proyecto') . '</span>';
    $html .= badgeEstado($p['estado']);
    $html .= '</div>';
    $html .= '<h3 class="card__titulo">' . e($p['titulo']) . '</h3>';

    if (!empty($p['descripcion_corta'])) {
        $html .= '<p class="card__texto">' . e(resumir($p['descripcion_corta'], 130)) . '</p>';
    }

    $html .= '<dl class="card__meta">';
    if (!empty($p['tecnologias'])) {
        $html .= '<div><dt>Tecnologías</dt><dd>' . e($p['tecnologias']) . '</dd></div>';
    }
    if (!empty($p['version_actual'])) {
        $html .= '<div><dt>Versión</dt><dd>' . e($p['version_actual']) . '</dd></div>';
    }
    if ($avance !== null) {
        $html .= '<div><dt>Avance</dt><dd>' . $avance . '&nbsp;%</dd></div>';
    }
    $html .= '</dl>';

    if ($avance !== null) {
        // Se usa una clase (paso de 5) en vez de style="width:…%" porque
        // la CSP del sitio no permite estilos inline.
        $paso = (int) round($avance / 5) * 5;
        $html .= '<div class="progress" role="progressbar" aria-valuenow="' . $avance
               . '" aria-valuemin="0" aria-valuemax="100" aria-label="Avance del proyecto">'
               . '<span class="progress__bar progress__bar--p' . $paso . '"></span></div>';
    }

    $html .= '</a></article>';
    return $html;
}

// ── Tarjeta de artículo (wiki) ──────────────────────────────

function tarjetaArticulo(array $a): string {
    $html = '<article class="card card--articulo reveal">';
    $html .= '<a class="card__link" href="' . e(url('articulo.php?slug=' . rawurlencode($a['slug']))) . '">';
    $html .= '<div class="card__cabeza">'
           . badgeTipo($a['tipo'] ?? 'tecnico')
           . '<span class="card__fecha">' . e(fecha($a['actualizado'] ?? $a['creado_en'] ?? null)) . '</span>'
           . '</div>';
    $html .= '<h3 class="card__titulo">' . e($a['titulo']) . '</h3>';

    if (!empty($a['resumen'])) {
        $html .= '<p class="card__texto">' . e(resumir($a['resumen'], 130)) . '</p>';
    } elseif (!empty($a['contenido'])) {
        $html .= '<p class="card__texto">' . e(resumir($a['contenido'], 130)) . '</p>';
    }

    if (!empty($a['proyecto_titulo'])) {
        $html .= '<p class="card__proyecto">' . icono('folder', 14)
               . '<span>' . e($a['proyecto_titulo']) . '</span></p>';
    }

    if (!empty($a['tags_nombres'])) {
        $html .= '<ul class="chips">';
        foreach (array_slice(explode('||', $a['tags_nombres']), 0, 4) as $i => $t) {
            if ($t === '') continue;
            $slug = explode('||', $a['tags_slugs'] ?? '')[$i] ?? slugify($t);
            $html .= '<li><a class="chip chip--tag" href="'
                   . e(url('wiki.php?tag=' . rawurlencode($slug))) . '">' . e($t) . '</a></li>';
        }
        $html .= '</ul>';
    }

    $html .= '</a></article>';
    return $html;
}

// ── Tarjeta de noticia ──────────────────────────────────────

function tarjetaNoticia(array $n, bool $destacada = false): string {
    $clase = $destacada ? 'card card--noticia card--destacada reveal' : 'card card--noticia reveal';

    $html  = '<article class="' . $clase . '">';
    $html .= '<a class="card__link" href="'
           . e(url('noticia.php?slug=' . rawurlencode($n['slug']))) . '">';

    // Imagen principal si existe; si no, superficie de color con gradiente
    if (!empty($n['imagen_principal'])) {
        $html .= '<div class="card__banner card__banner--foto">'
               . '<img src="' . e(urlMedia($n['imagen_principal'])) . '" alt="" loading="lazy">'
               . '</div>';
    } else {
        $html .= '<div class="card__banner" aria-hidden="true"></div>';
    }

    $html .= '<div class="card__cuerpo">';
    $html .= '<div class="card__cabeza">';
    if (!empty($n['categoria'])) {
        $html .= '<span class="chip chip--cat">' . e($n['categoria']) . '</span>';
    }
    $html .= '<span class="card__fecha">'
           . e(fecha($n['fecha_publicacion'] ?? $n['creado_en'] ?? null)) . '</span>';
    $html .= '</div>';
    $html .= '<h3 class="card__titulo">' . e($n['titulo']) . '</h3>';

    if (!empty($n['resumen'])) {
        $html .= '<p class="card__texto">' . e(resumir($n['resumen'], $destacada ? 220 : 130)) . '</p>';
    }

    $html .= '</div></a></article>';
    return $html;
}

// ── Galería de archivos adjuntos ────────────────────────────

function galeriaMedia(array $adjuntos): string {
    $imagenes = array_values(array_filter($adjuntos, fn($m) => $m['tipo'] === 'imagen'));
    $videos   = array_values(array_filter($adjuntos, fn($m) => $m['tipo'] === 'video'));
    $docs     = array_values(array_filter($adjuntos, fn($m) => $m['tipo'] === 'documento'));

    if (!$adjuntos) {
        return '';
    }

    $html = '<section class="adjuntos" aria-labelledby="tit-adjuntos">';
    $html .= '<h2 id="tit-adjuntos" class="adjuntos__titulo">' . icono('image')
           . ' Recursos adjuntos <span class="muted">(' . count($adjuntos) . ')</span></h2>';

    if ($imagenes) {
        $html .= '<ul class="galeria">';
        foreach ($imagenes as $m) {
            $html .= '<li class="galeria__item">'
                   . '<a href="' . e(urlMedia($m['nombre_guardado'])) . '" target="_blank" rel="noopener">'
                   . '<img src="' . e(urlMedia($m['nombre_guardado'])) . '" alt="'
                   . e($m['titulo'] ?: $m['nombre_original']) . '" loading="lazy">'
                   . '</a><span class="galeria__pie">'
                   . e($m['titulo'] ?: resumir($m['nombre_original'], 40)) . '</span></li>';
        }
        $html .= '</ul>';
    }

    if ($videos) {
        $html .= '<ul class="lista-adjuntos">';
        foreach ($videos as $m) {
            $html .= '<li class="adjunto adjunto--video">'
                   . '<video controls preload="metadata" src="' . e(urlMedia($m['nombre_guardado'])) . '"></video>'
                   . '<div class="adjunto__meta"><strong>' . e($m['titulo'] ?: $m['nombre_original']) . '</strong>'
                   . '<span>' . e(bytes((int) $m['tamano'])) . '</span></div></li>';
        }
        $html .= '</ul>';
    }

    if ($docs) {
        $html .= '<ul class="lista-adjuntos">';
        foreach ($docs as $m) {
            $html .= '<li class="adjunto">'
                   . '<span class="adjunto__icono">' . icono('file', 22) . '</span>'
                   . '<div class="adjunto__meta"><strong>'
                   . e($m['titulo'] ?: $m['nombre_original']) . '</strong>'
                   . '<span>' . e($m['mime']) . ' · ' . e(bytes((int) $m['tamano'])) . '</span></div>'
                   . '<a class="btn btn-outline btn-sm" href="' . e(urlMedia($m['nombre_guardado']))
                   . '" download>' . icono('download', 15) . ' Descargar</a></li>';
        }
        $html .= '</ul>';
    }

    return $html . '</section>';
}

// ── Botón condicional por permiso ───────────────────────────

function botonSiPuede(string $capacidad, string $ruta, string $texto, string $iconoNombre = 'plus'): string {
    if (!puede($capacidad)) {
        return '';
    }
    return '<a class="btn btn-primary" href="' . e(url($ruta)) . '">'
         . icono($iconoNombre, 16) . ' ' . e($texto) . '</a>';
}

// ── Campo de formulario reutilizable ────────────────────────

/**
 * $tipo: text|email|password|date|number|select|textarea|url
 */
function campo(string $nombre, string $etiqueta, string $tipo = 'text', array $op = []): string {
    $valor     = $op['valor'] ?? '';
    $requerido = !empty($op['requerido']);
    $max       = $op['max'] ?? null;
    $ayuda     = $op['ayuda'] ?? '';
    $extra     = $op['extra'] ?? '';
    $id        = 'f_' . $nombre;
    $errores   = $op['errores'] ?? [];

    $atributos = 'id="' . e($id) . '" name="' . e($nombre) . '"';
    if ($requerido) $atributos .= ' required';
    if ($max !== null && $tipo !== 'file') {
        $atributos .= ' maxlength="' . (int) $max . '"';
    }
    if ($tipo === 'number') {
        $atributos .= ' min="' . (int) ($op['min'] ?? 0) . '"';
        if (isset($op['max_valor'])) $atributos .= ' max="' . (int) $op['max_valor'] . '"';
    }
    if (!empty($op['placeholder']) && $tipo !== 'file') {
        $atributos .= ' placeholder="' . e($op['placeholder']) . '"';
    }
    if (!empty($op['multiple']) && $tipo === 'file') {
        $atributos .= ' multiple';
    }
    $atributos .= ' ' . trim($extra);

    // Clase extra (p. ej. editor-ric) sumada a la base form-control
    $clase = 'form-control' . (!empty($op['class']) ? ' ' . $op['class'] : '');

    $html = '<div class="form-group' . (isset($errores[$nombre]) ? ' form-group--error' : '') . '">';
    $html .= '<label class="form-label" for="' . e($id) . '">' . e($etiqueta)
           . ($requerido ? ' <span class="obligatorio" aria-hidden="true">*</span>' : '') . '</label>';

    if ($tipo === 'select') {
        $html .= '<select class="' . $clase . '" ' . $atributos . '>';
        if (isset($op['vacio'])) {
            $vacio = (string) $op['vacio'];
            $html .= '<option value=""'
                   . ((string) $valor === '' ? ' selected' : '') . '>' . e($vacio) . '</option>';
        }
        foreach ($op['opciones'] ?? [] as $k => $v) {
            $sel = ((string) $k === (string) $valor) ? ' selected' : '';
            $html .= '<option value="' . e((string) $k) . '"' . $sel . '>' . e($v) . '</option>';
        }
        $html .= '</select>';
    } elseif ($tipo === 'textarea') {
        $html .= '<textarea class="' . $clase . '" rows="' . (int) ($op['filas'] ?? 6) . '" '
               . $atributos . '>' . e($valor) . '</textarea>';
    } elseif ($tipo === 'file') {
        // Nunca se emite value en input[type=file]: el navegador lo ignora
        // y los validadores lo marcan como error de HTML.
        $html .= '<input class="' . $clase . '" type="file" ' . $atributos . '>';
    } else {
        $html .= '<input class="' . $clase . '" type="' . e($tipo) . '" value="' . e($valor) . '" '
               . $atributos . '>';
    }

    if (isset($errores[$nombre])) {
        $html .= '<p class="form-error" role="alert">' . e($errores[$nombre]) . '</p>';
    } elseif ($ayuda !== '') {
        $html .= '<p class="form-ayuda">' . e($ayuda) . '</p>';
    }

    return $html . '</div>';
}

function campoOculto(string $nombre, string $valor): string {
    return '<input type="hidden" name="' . e($nombre) . '" value="' . e($valor) . '">';
}

function campoCSRF(): string {
    return campoOculto('csrf_token', csrfToken());
}
