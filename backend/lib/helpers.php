<?php
// ============================================================
//  FUNCIONES AUXILIARES GENERALES
//  Archivo: backend/lib/helpers.php
// ============================================================

require_once __DIR__ . '/../config/config.php';

/**
 * Escapa texto para imprimirlo dentro de HTML.
 * Toda salida que provenga de la base de datos o del usuario DEBE
 * pasar por esta función salvo que sea HTML ya saneado.
 */
function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Convierte un texto en un slug amigable para URL.
 */
function slugify(string $text): string {
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = strtr($text, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u','ç'=>'c',
    ]);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $slug  = trim($text, '-');
    return $slug === '' ? 'sin-titulo' : $slug;
}

/**
 * Genera un slug único dentro de una tabla.
 * Si el slug ya existe le agrega -2, -3, … hasta encontrar lugar.
 */
function slugUnico(string $tabla, string $columna, string $base, ?int $excluirId = null): string {
    $db     = getDB();
    $base   = substr(slugify($base), 0, 240);
    $candidato = $base;
    $i = 1;

    while (true) {
        $sql = "SELECT COUNT(*) FROM `$tabla` WHERE `$columna` = ?";
        $params = [$candidato];

        if ($excluirId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excluirId;
        }

        $st = $db->prepare($sql);
        $st->execute($params);

        if ((int) $st->fetchColumn() === 0) {
            return $candidato;
        }

        $i++;
        $candidato = substr($base, 0, 235) . '-' . $i;

        if ($i > 500) {                     // red de seguridad
            return $base . '-' . bin2hex(random_bytes(4));
        }
    }
}

/**
 * URL absoluta dentro del sitio.
 * url('wiki.php') → http://…/frontend/wiki.php
 */
function url(string $ruta = ''): string {
    return SITE_URL . '/' . ltrim($ruta, '/');
}

/**
 * URL de un archivo de media servido por frontend/media.php
 */
function urlMedia(string $nombreGuardado): string {
    return url('media.php?f=' . rawurlencode($nombreGuardado));
}

// ── Formateo ────────────────────────────────────────────────

function fecha(?string $fecha, string $formato = 'd/m/Y'): string {
    if (!$fecha) return '—';
    $ts = strtotime($fecha);
    return $ts ? date($formato, $ts) : '—';
}

function bytes(int $b): string {
    if ($b < 1024) return $b . ' B';
    if ($b < 1048576) return round($b / 1024, 1) . ' KB';
    if ($b < 1073741824) return round($b / 1048576, 1) . ' MB';
    return round($b / 1073741824, 2) . ' GB';
}

/**
 * Recorta texto plano conservando palabras completas.
 * Se usa para resúmenes de tarjetas (el contenido HTML ya debe
 * venir convertido a texto antes de llamar a esta función).
 */
function resumir(?string $texto, int $largo = 140): string {
    $texto = trim(strip_tags($texto ?? ''));
    if (mb_strlen($texto) <= $largo) {
        return $texto;
    }
    $corte = mb_substr($texto, 0, $largo);
    $espacio = mb_strrpos($corte, ' ');
    if ($espacio !== false && $espacio > $largo * 0.6) {
        $corte = mb_substr($corte, 0, $espacio);
    }
    return rtrim($corte, '.,;:') . '…';
}

/**
 * Etiqueta legible de los estados de proyecto / noticias.
 */
function etiquetaEstado(string $estado): string {
    $mapa = [
        'activo'      => 'Activo',
        'en_desarrollo' => 'En desarrollo',
        'pausado'     => 'Pausado',
        'archivado'   => 'Archivado',
        'borrador'    => 'Borrador',
        'pendiente'   => 'Pendiente',
        'publicada'   => 'Publicada',
        'rechazada'   => 'Rechazada',
    ];
    return $mapa[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
}

function etiquetaTipoArticulo(string $tipo): string {
    $mapa = [
        'guion'        => 'Guión',
        'arquitectura' => 'Arquitectura',
        'mecanica'     => 'Mecánica',
        'personaje'    => 'Personaje',
        'nivel'        => 'Nivel',
        'tecnico'      => 'Técnico',
        'otro'         => 'Otro',
    ];
    return $mapa[$tipo] ?? ucfirst($tipo);
}

function etiquetaRol(string $rol): string {
    $mapa = ['lector' => 'Lector', 'editor' => 'Editor',
             'supervisor' => 'Supervisor', 'admin' => 'Administrador'];
    return $mapa[$rol] ?? ucfirst($rol);
}

/**
 * Icono SVG inline (una sola fuente de iconografía, sin librerías
 * externas ni imágenes sueltas). Se imprime con e() implícito porque
 * la lista de nombres es cerrada.
 */
function icono(string $nombre, int $size = 18): string {
    $paths = [
        'home'     => '<path d="M3 9.5 10 3l7 6.5V17a1 1 0 0 1-1 1h-4v-5H8v5H4a1 1 0 0 1-1-1z"/>',
        'folder'   => '<path d="M2 5a1 1 0 0 1 1-1h4l2 2h7a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1z"/>',
        'book'     => '<path d="M4 3h9a3 3 0 0 1 3 3v11H7a3 3 0 0 0-3 3z" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4 3v14"/>',
        'news'     => '<rect x="3" y="4" width="14" height="12" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M6 8h8M6 11h8M6 14h5"/>',
        'users'    => '<circle cx="7.5" cy="7" r="2.6"/><path d="M3 16c0-2.5 2-4 4.5-4s4.5 1.5 4.5 4"/><circle cx="14" cy="8" r="2"/><path d="M12.5 12.6c2 .3 3.5 1.7 3.5 3.4"/>',
        'info'     => '<circle cx="10" cy="10" r="7.2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M10 9v5M10 6.6v.6"/>',
        'search'   => '<circle cx="9" cy="9" r="5.2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m13 13 4 4"/>',
        'plus'     => '<path d="M10 4v12M4 10h12"/>',
        'edit'     => '<path d="M14.2 3.8a1.7 1.7 0 0 1 2.4 2.4L7.6 15.2 4 16l.8-3.6z"/>',
        'trash'    => '<path d="M4 6h12M8 6V4h4v2M6 6l.8 10h6.4L14 6"/>',
        'upload'   => '<path d="M10 14V5M6.5 8.5 10 5l3.5 3.5M4 15v1.5A1.5 1.5 0 0 0 5.5 18h9a1.5 1.5 0 0 0 1.5-1.5V15"/>',
        'image'    => '<rect x="3" y="4.5" width="14" height="11" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="7.5" cy="8.5" r="1.3"/><path d="m4 14 4-3.5 3 2.5 2.5-2 3 3"/>',
        'video'    => '<rect x="3" y="5.5" width="10" height="9" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m13 9 4-2.5v7L13 11z"/>',
        'file'     => '<path d="M5 3h6l4 4v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M11 3v4h4"/>',
        'check'    => '<path d="m4.5 10.5 3.5 3.5 7.5-8"/>',
        'clock'    => '<circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M10 6v4.3l2.8 1.7"/>',
        'shield'   => '<path d="M10 3 4.5 5.2v4.3c0 3.3 2.3 6.3 5.5 7.3 3.2-1 5.5-4 5.5-7.3V5.2z" fill="none" stroke="currentColor" stroke-width="1.6"/>',
        'menu'     => '<path d="M3 6h14M3 10h14M3 14h14"/>',
        'close'    => '<path d="m5 5 10 10M15 5 5 15"/>',
        'arrow'    => '<path d="M4 10h11M11 6l4 4-4 4"/>',
        'download' => '<path d="M10 4v9M6.5 9.5 10 13l3.5-3.5M4 16h12"/>',
        'link'     => '<path d="M8.5 11.5a3 3 0 0 0 4.2 0l2.3-2.3a3 3 0 0 0-4.2-4.2l-1 1"/><path d="M11.5 8.5a3 3 0 0 0-4.2 0L5 10.8a3 3 0 0 0 4.2 4.2l1-1"/>',
        'gamepad'  => '<rect x="2.5" y="6.5" width="15" height="8" rx="3.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M6 9v3M4.5 10.5h3M13 10h.01M15 12h.01"/>',
        'cpu'      => '<rect x="6" y="6" width="8" height="8" rx="1" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 3v3M12 3v3M8 14v3M12 14v3M3 8h3M3 12h3M14 8h3M14 12h3"/>',
        'mobile'   => '<rect x="6.5" y="3" width="7" height="14" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M9 15h2"/>',
        'server'   => '<rect x="3.5" y="4" width="13" height="5" rx="1" fill="none" stroke="currentColor" stroke-width="1.6"/><rect x="3.5" y="11" width="13" height="5" rx="1" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M6.5 6.5h.01M6.5 13.5h.01"/>',
        'signal'   => '<path d="M4 15v-3M8 15V9M12 15V6M16 15V3"/>',
        'chart'    => '<path d="M3 17V8M8 17V4M13 17v-6M18 17V9" stroke-width="2"/>',
        'mail'     => '<rect x="3" y="5" width="14" height="10" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m3.5 6 6.5 5 6.5-5"/>',
        'login'    => '<path d="M8 4H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3M12 7l3 3-3 3M15 10H8"/>',
        'star'     => '<path d="m10 3 2.2 4.5 5 .7-3.6 3.5.9 5-4.5-2.4L5.5 16.7l.9-5L2.8 8.2l5-.7z"/>',
    ];

    $d = $paths[$nombre] ?? $paths['folder'];
    return '<svg class="icon" width="' . $size . '" height="' . $size
         . '" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" focusable="false">'
         . $d . '</svg>';
}
