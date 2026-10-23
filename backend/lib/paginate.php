<?php
// ============================================================
//  PAGINACIÓN DE LISTADOS
//  Archivo: backend/lib/paginate.php
//
//  El código original cargaba TODOS los resultados de wiki,
//  proyectos y noticias de una sola vez (sin LIMIT), lo que
//  provoca consultas de escaneo completo y respuestas sin fin.
//  Todas las vistas de listado usan estas funciones.
// ============================================================

require_once __DIR__ . '/validate.php';

/**
 * Calcula el offset de la página actual y limita el rango.
 *
 * @return array{pagina:int, por_pagina:int, offset:int, total_paginas:int, total:int}
 */
function paginar(int $total, int $porPagina = 0): array {
    $porPagina = $porPagina > 0 ? $porPagina : POR_PAGINA;
    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    $pagina = entero($_GET['pagina'] ?? 1, 1);
    $pagina = max(1, min($pagina, $totalPaginas));

    return [
        'pagina'        => $pagina,
        'por_pagina'    => $porPagina,
        'offset'        => ($pagina - 1) * $porPagina,
        'total_paginas' => $totalPaginas,
        'total'         => $total,
    ];
}

/**
 * HTML de los controles de paginación.
 * Conserva todos los filtros actuales de la URL y sólo reemplaza
 * el parámetro "pagina".
 *
 * Se muestra sólo si hay más de una página; nunca se emiten
 * controles muertos.
 */
function htmlPaginacion(array $pag, array $params = []): string {
    if ($pag['total_paginas'] <= 1) {
        return '';
    }

    $actual = $pag['pagina'];
    $ultima = $pag['total_paginas'];

    $href = function (int $p) use ($params): string {
        $q = array_merge($_GET, $params, ['pagina' => $p]);
        $q = array_filter($q, fn($v) => !is_array($v));
        return '?' . http_build_query($q);
    };

    $salida = '<nav class="paginacion" aria-label="Paginación">';

    if ($actual > 1) {
        $salida .= '<a class="paginacion__btn" href="' . e($href($actual - 1))
                 . '" rel="prev">← Anterior</a>';
    }

    $salida .= '<ul class="paginacion__lista">';

    // Ventana compacta: … 4 5 [6] 7 8 …
    $inicio = max(1, $actual - 2);
    $fin    = min($ultima, $actual + 2);

    if ($inicio > 1) {
        $salida .= '<li><a class="paginacion__num" href="' . e($href(1)) . '">1</a></li>';
        if ($inicio > 2) $salida .= '<li><span class="paginacion__puntos">…</span></li>';
    }

    for ($i = $inicio; $i <= $fin; $i++) {
        if ($i === $actual) {
            $salida .= '<li><span class="paginacion__num is-active" aria-current="page">'
                     . $i . '</span></li>';
        } else {
            $salida .= '<li><a class="paginacion__num" href="' . e($href($i)) . '">' . $i . '</a></li>';
        }
    }

    if ($fin < $ultima) {
        if ($fin < $ultima - 1) $salida .= '<li><span class="paginacion__puntos">…</span></li>';
        $salida .= '<li><a class="paginacion__num" href="' . e($href($ultima)) . '">' . $ultima . '</a></li>';
    }

    $salida .= '</ul>';

    if ($actual < $ultima) {
        $salida .= '<a class="paginacion__btn" href="' . e($href($actual + 1))
                 . '" rel="next">Siguiente →</a>';
    }

    return $salida . '</nav>';
}

/**
 * Texto "Mostrando X–Y de Z" para dar contexto al listado.
 */
function resumenPaginacion(array $pag): string {
    if ($pag['total'] === 0) {
        return 'Sin resultados.';
    }
    $desde = $pag['offset'] + 1;
    $hasta = min($pag['offset'] + $pag['por_pagina'], $pag['total']);
    return 'Mostrando ' . $desde . '–' . $hasta . ' de ' . $pag['total'] . ' resultados';
}
