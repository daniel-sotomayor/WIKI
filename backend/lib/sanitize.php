<?php
// ============================================================
//  SANITIZACIÓN DE HTML EN RICH TEXT
//  Archivo: backend/lib/sanitize.php
//
//  Los campos de contenido (artículos, noticias, descripción de
//  proyectos) se guardan y se muestran como HTML. El código
//  original los imprimía tal cual, lo que permitía XSS almacenado.
//
//  Estrategia de doble barrera:
//    1. Se sana AL GUARDAR  → la base nunca contiene HTML peligroso.
//    2. Se sana AL MOSTRAR  → aunque alguien manipule la base,
//       el HTML que llega al navegador ya viene filtrado.
//
//  La lista de etiquetas y atributos es cerrada (whitelist).
// ============================================================

require_once __DIR__ . '/helpers.php';

/** Etiquetas permitidas (minúsculas). */
const TAGS_PERMITIDAS = [
    'p','br','hr',
    'h2','h3','h4','h5',
    'strong','b','em','i','u','s','small','mark','sub','sup',
    'ul','ol','li','dl','dt','dd',
    'blockquote','cite','q','abbr',
    'code','pre','kbd','samp','var',
    'a','img',
    'table','thead','tbody','tfoot','tr','th','td','caption','colgroup','col',
    'figure','figcaption','span','div','details','summary',
];

/** Etiquetas que se eliminan por completo, junto con su contenido. */
const TAGS_ELIMINAR = [
    'script','style','iframe','object','embed','applet','form','input',
    'button','select','option','textarea','meta','link','base','noscript',
    'template','svg','math','frame','frameset','audio','video','source',
    'canvas','map','area','param','object','marquee','blink','xmp','plaintext',
];

/** Atributos permitidos por etiqueta (además de los comunes). */
const ATRIBUTOS_PERMITIDOS = [
    'a'      => ['href', 'title'],
    'img'    => ['src', 'alt', 'title', 'width', 'height'],
    'td'     => ['colspan', 'rowspan'],
    'th'     => ['colspan', 'rowspan', 'scope'],
    'col'    => ['span'],
    'colgroup' => ['span'],
    'ol'     => ['start', 'type'],
    'abbr'   => ['title'],
    'q'      => ['cite'],
    'blockquote' => ['cite'],
    'details' => ['open'],
];

/** Atributos comunes permitidos en cualquier etiqueta permitida. */
const ATRIBUTOS_COMUNES = ['class', 'id', 'title', 'lang', 'dir'];

/**
 * Sanea un fragmento HTML y devuelve únicamente el HTML permitido.
 */
function sanearHTML(?string $html): string {
    if ($html === null) {
        return '';
    }
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    // 1) Elimina por completo las etiquetas peligrosas con su contenido.
    //    Se hacen varias pasadas porque pueden estar anidadas.
    for ($i = 0; $i < 4; $i++) {
        $antes = $html;
        foreach (TAGS_ELIMINAR as $tag) {
            $html = preg_replace(
                '#<\s*' . $tag . '\b[^>]*>.*?<\s*/\s*' . $tag . '\s*>#is',
                '',
                $html
            );
            // Etiqueta sin cierre
            $html = preg_replace('#<\s*' . $tag . '\b[^>]*/?>#is', '', $html);
        }
        if ($html === $antes) {
            break;
        }
    }

    // 2) Elimina etiquetas que no están en la lista blanca conservando
    //    su contenido (unwrap), para no perder el texto.
    $permitidas = implode('|', TAGS_PERMITIDAS);
    $html = preg_replace_callback(
        '#<\s*/?\s*([a-zA-Z][a-zA-Z0-9]*)\b([^>]*)>#s',
        static function (array $m) use ($permitidas) {
            $tag = strtolower($m[1]);
            if (preg_match('#^(' . $permitidas . ')$#', $tag)) {
                return $m[0];
            }
            // Cierre de etiqueta permitida → conservar
            if (str_starts_with($m[0], '</')) {
                return $m[0];
            }
            // Atributos sospechosos (onclick, onerror, style…) → quitar
            if (preg_match('#\son[a-z]+\s*=#i', $m[2]) || stripos($m[2], 'style=') !== false) {
                return '';
            }
            return '';   // abre y cierra se eliminan; el contenido queda
        },
        $html
    );
    // Sólo quedan etiquetas de la lista blanca o texto.

    // 3) Recorrido con DOM para filtrar atributos y protocolos.
    return filtrarDOM($html);
}

function filtrarDOM(string $html): string {
    if (!class_exists('DOMDocument')) {
        return filtrarSinDOM($html);
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="UTF-8"><div id="raiz-sanitizada">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
    );
    libxml_clear_errors();

    $raiz = null;
    foreach ($doc->getElementsByTagName('div') as $el) {
        if ($el->getAttribute('id') === 'raiz-sanitizada') {
            $raiz = $el;
            break;
        }
    }
    if (!$raiz) {
        return '';
    }

    filtrarNodos($raiz, $doc);

    $salida = '';
    if ($raiz->hasChildNodes()) {
        foreach (iterator_to_array($raiz->childNodes) as $hijo) {
            $salida .= $doc->saveHTML($hijo);
        }
    }

    return trim($salida);
}

function filtrarNodos(DOMNode $nodo, DOMDocument $doc): void {
    foreach (iterator_to_array($nodo->childNodes ?? []) as $hijo) {
        if ($hijo->nodeType === XML_ELEMENT_NODE) {
            /** @var DOMElement $hijo */
            $tag = strtolower($hijo->tagName);

            // a) Etiquetas prohibidas con contenido → se eliminan por completo
            if (in_array($tag, TAGS_ELIMINAR, true)) {
                $nodo->removeChild($hijo);
                continue;
            }

            // b) Etiquetas fuera de la lista blanca → se desempaquetan
            if (!in_array($tag, TAGS_PERMITIDAS, true)) {
                while ($hijo->firstChild) {
                    $nodo->insertBefore($hijo->firstChild, $hijo);
                }
                $nodo->removeChild($hijo);
                continue;   // los hijos ya fueron movidos al nivel actual
            }

            // c) Atributos: se borra todo y se reinsertan los permitidos
            $permitidos = array_merge(
                ATRIBUTOS_COMUNES,
                ATRIBUTOS_PERMITIDOS[$tag] ?? []
            );

            $guardar = [];
            foreach (iterator_to_array($hijo->attributes) as $attr) {
                $nombre = strtolower($attr->name);
                $valor  = (string) $attr->value;

                // Nunca atributos de evento ni style
                if (str_starts_with($nombre, 'on') || $nombre === 'style') {
                    continue;
                }
                if (!in_array($nombre, $permitidos, true)) {
                    continue;
                }

                $valor = filtrarAtributo($tag, $nombre, $valor);
                if ($valor !== null) {
                    $guardar[$nombre] = $valor;
                }
            }

            foreach (iterator_to_array($hijo->attributes) as $attr) {
                $hijo->removeAttribute($attr->name);
            }
            foreach ($guardar as $nombre => $valor) {
                $hijo->setAttribute($nombre, $valor);
            }

            // Los <img> deben tener alt (accesibilidad)
            if ($tag === 'img' && !$hijo->hasAttribute('alt')) {
                $hijo->setAttribute('alt', '');
            }

            filtrarNodos($hijo, $doc);
        } elseif ($hijo->nodeType === XML_COMMENT_NODE
               || $hijo->nodeType === XML_PI_NODE
               || $hijo->nodeType === XML_DOCUMENT_TYPE_NODE) {
            $nodo->removeChild($hijo);
        }
    }
}

/**
 * Valida el valor de un atributo según su tipo.
 * Devuelve null si el valor debe descartarse.
 */
function filtrarAtributo(string $tag, string $attr, string $valor): ?string {
    $valor = trim($valor);

    if ($attr === 'class' || $attr === 'id') {
        // Sólo identificadores simples: evita romper la CSS o inyectar URLs.
        $valor = preg_replace('/[^A-Za-z0-9_\- ]/', '', $valor);
        $valor = trim(preg_replace('/\s+/', ' ', $valor));
        return $valor === '' ? null : substr($valor, 0, 100);
    }

    if ($attr === 'lang' || $attr === 'dir') {
        return preg_match('/^[a-zA-Z\-]{2,10}$/', $valor) ? $valor : null;
    }

    if (in_array($attr, ['colspan', 'rowspan', 'span', 'start', 'width', 'height'], true)) {
        $n = (int) $valor;
        return $n > 0 ? (string) $n : null;
    }

    if ($attr === 'scope') {
        return in_array($valor, ['row', 'col', 'rowgroup', 'colgroup'], true) ? $valor : null;
    }

    if ($attr === 'type' && $tag === 'ol') {
        return in_array($valor, ['1', 'a', 'A', 'i', 'I'], true) ? $valor : null;
    }

    if ($attr === 'open') {
        return '';
    }

    if (in_array($attr, ['href', 'src', 'cite'], true)) {
        return filtrarURL($valor);
    }

    // title, alt y el resto: sólo texto plano, sin etiquetas
    $valor = trim(strip_tags($valor));
    return $valor === '' ? null : substr($valor, 0, 300);
}

/**
 * Bloquea javascript:, data: y demás esquemas peligrosos.
 * Se admiten: http, https, mailto, tel, fragmentos, rutas relativas
 * y el endpoint propio de media.
 */
function filtrarURL(string $url): ?string {
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    // Normaliza entidades que podrían evadir la detección
    $limpio = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $limpio = preg_replace('/[\x00-\x1F\x7F]/', '', $limpio);
    $limpio = str_replace([' ', "\t", "\n", "\r"], '', $limpio);

    // Esquema explícito
    if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.\-]*):#', $limpio, $m)) {
        $esquema = strtolower($m[1]);
        if (in_array($esquema, ['http', 'https', 'mailto', 'tel'], true)) {
            return $url;
        }
        return null;   // javascript:, data:, vbscript:, file:, etc.
    }

    // Relativas, absolutas de sitio y anclas
    return $url;
}

/**
 * Respaldo cuando la extensión DOM no está disponible.
 * XAMPP la trae habilitada por defecto, pero por las dudas se
 * aplica una limpieza conservadora por expresiones regulares:
 * se quitan etiquetas fuera de lista, manejadores de evento, style
 * y los esquemas de URL peligrosos.
 */
function filtrarSinDOM(string $html): string {
    // 1) Elimina por completo las etiquetas peligrosas, con su contenido.
    for ($i = 0; $i < 4; $i++) {
        $antes = $html;
        foreach (TAGS_ELIMINAR as $tag) {
            $html = preg_replace(
                '#<\s*' . $tag . '\b[^>]*>.*?<\s*/\s*' . $tag . '\s*>#is',
                '',
                $html
            );
            $html = preg_replace('#<\s*' . $tag . '\b[^>]*/?>#is', '', $html);
        }
        if ($html === $antes) break;
    }

    // 2) Recorre todas las etiquetas: conserva sólo las de la lista
    //    blanca y, dentro de ellas, sólo los atributos permitidos.
    $html = preg_replace_callback(
        '#<\s*(/?)\s*([a-zA-Z][a-zA-Z0-9]*)\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>#s',
        static function (array $m) {
            $cierra = $m[1] === '/';
            $tag    = strtolower($m[2]);

            if ($cierra) {
                return in_array($tag, TAGS_PERMITIDAS, true) ? '</' . $tag . '>' : '';
            }
            if (!in_array($tag, TAGS_PERMITIDAS, true)) {
                return '';   // se descarta la apertura; el contenido queda
            }

            $permitidos = array_merge(ATRIBUTOS_COMUNES, ATRIBUTOS_PERMITIDOS[$tag] ?? []);
            $salida = '<' . $tag;

            if (preg_match_all(
                '/([a-zA-Z][-a-zA-Z0-9]*)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))|([a-zA-Z][-a-zA-Z0-9]*)(?=[\s\/>])/',
                // El espacio final hace que también se reconozcan atributos
                // booleanos sin valor al final de la etiqueta (p. ej. "open").
                $m[3] . ' ', $coincidencias, PREG_SET_ORDER
            )) {
                foreach ($coincidencias as $c) {
                    $bruto = ($c[1] ?? '') !== '' ? $c[1] : ($c[6] ?? '');
                    $nombre = strtolower($bruto);
                    if ($nombre === '') continue;
                    if (str_starts_with($nombre, 'on') || $nombre === 'style') continue;
                    if (!in_array($nombre, $permitidos, true)) continue;

                    $valor = $c[3] ?? '';
                    if ($valor === '' && ($c[4] ?? '') !== '') $valor = $c[4];
                    if ($valor === '' && ($c[5] ?? '') !== '') $valor = $c[5];

                    if (in_array($nombre, ['href', 'src', 'cite'], true)) {
                        $valor = filtrarURL($valor);
                        if ($valor === null) continue;
                        $salida .= ' ' . $nombre . '="' . e($valor) . '"';
                        continue;
                    }
                    if (in_array($nombre, ['class', 'id'], true)) {
                        $valor = preg_replace('/[^A-Za-z0-9_\- ]/', '', $valor);
                        $valor = trim(preg_replace('/\s+/', ' ', (string) $valor));
                        if ($valor === '') continue;
                        $salida .= ' ' . $nombre . '="' . e(substr($valor, 0, 100)) . '"';
                        continue;
                    }
                    if (in_array($nombre, ['colspan', 'rowspan', 'span', 'start', 'width', 'height'], true)) {
                        $n = (int) $valor;
                        if ($n <= 0) continue;
                        $salida .= ' ' . $nombre . '="' . $n . '"';
                        continue;
                    }
                    // Atributo booleano sin valor (p. ej. <details open>)
                    if ($nombre === 'open' && $valor === '') {
                        $salida .= ' open';
                        continue;
                    }
                    $valor = trim(strip_tags($valor));
                    if ($valor === '') continue;
                    $salida .= ' ' . $nombre . '="' . e(substr($valor, 0, 300)) . '"';
                }
            }

            return $salida . '>';
        },
        $html
    );

    return trim($html);
}

/**
 * Convierte el HTML saneado en texto plano (para resúmenes/búsquedas).
 */
function htmlATexto(?string $html): string {
    $texto = trim(preg_replace('/\s+/u', ' ', strip_tags($html ?? '')));
    return html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
