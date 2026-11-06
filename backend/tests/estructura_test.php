<?php
// ============================================================
//  PRUEBAS — estructura y convenciones del proyecto
//  Archivo: backend/tests/estructura_test.php
//
//      php backend/tests/estructura_test.php
//
//  Comprobaciones baratas que evitan regresiones frecuentes:
//    1. sintaxis de todos los archivos PHP
//    2. ningún handler en línea ni style="…" (rompería la CSP)
//    3. cada clase usada en el HTML existe en algún CSS
//    4. los $cssExtra / $jsExtra referenciados existen
//    5. archivos clave presentes (htaccess, parciales, esquema)
//
//  Código de salida: 0 = todo OK, 1 = hay fallas.
// ============================================================

declare(strict_types=1);

$raiz = dirname(__DIR__, 2);          // telecom_wiki/
$fallas = 0;

function falla(string $mensaje): void {
    global $fallas;
    $fallas++;
    echo "  FALLA: $mensaje\n";
}

function listarPhp(string $dir, array &$salida = []): array {
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..' || $item === 'vendor') continue;
        $ruta = $dir . '/' . $item;
        if (is_dir($ruta)) {
            listarPhp($ruta, $salida);
        } elseif (str_ends_with($item, '.php')) {
            $salida[] = $ruta;
        }
    }
    return $salida;
}

$archivosPhp = listarPhp($raiz);

// ── 1. Sintaxis ─────────────────────────────────────────────
echo "· 1. Sintaxis PHP (" . count($archivosPhp) . " archivos)\n";
foreach ($archivosPhp as $ruta) {
    $salida = [];
    $codigo = 0;
    exec('php -l ' . escapeshellarg($ruta) . ' 2>&1', $salida, $codigo);
    if ($codigo !== 0) {
        falla(basename($ruta) . ' → ' . implode(' ', $salida));
    }
}

// ── 2. Nada que rompa la CSP ────────────────────────────────
echo "· 2. Estilos y handlers en línea (CSP)\n";
$patrones = [
    '/\son[a-z]+\s*=\s*["\']/i'  => 'handler en línea',
    '/\sstyle\s*=\s*"/i'         => 'style="…" en línea',
];
$excluir = ['/vendor/', '/tests/', '/backend/storage/'];
foreach ($archivosPhp as $ruta) {
    foreach ($excluir as $e) {
        if (str_contains($ruta, $e)) continue 2;
    }
    $texto = (string) file_get_contents($ruta);
    // Se ignoran los comentarios: un «style="…» citado en la documentación
    // del código no rompe la CSP.
    $texto = (string) preg_replace('#/\*.*?\*/#s', '', $texto);
    $texto = (string) preg_replace('#^\s*//.*$#m', '', $texto);
    foreach ($patrones as $regex => $que) {
        if (preg_match($regex, $texto, $m, PREG_OFFSET_CAPTURE)) {
            $linea = substr_count(substr($texto, 0, $m[0][1]), "\n") + 1;
            falla("$que en " . substr($ruta, strlen($raiz) + 1) . ":$linea");
        }
    }
}

// ── 3. Clases CSS usadas vs. definidas ──────────────────────
echo "· 3. Cobertura de clases CSS\n";

$css = '';
foreach (glob($raiz . '/frontend/css/*.css') ?: [] as $f) {
    $css .= (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($f));
}
preg_match_all('/\.([a-zA-Z][a-zA-Z0-9_-]*)/', $css, $mDef);
$definidas = array_flip($mDef[1]);

// Falsos positivos: fragmentos que quedan al recortar los bloques
// PHP embebidos en el atributo class (p. ej. progress__bar--p… ,
// lista-roles__item--… o alert-… , que se completan en tiempo de
// ejecución y por eso no están escritos en los CSS).
$ignorar = array_flip([
    'editor-ric', 'activo', 'info', 'slug', 'tipo', 'leido', 'porcentaje_avance',
]);
$prefijos = ['progress__bar--p', 'lista-roles__item--', 'alert-', 'card--pro', 'card--art', 'card--not'];

$sinCss = [];
$fuente = array_merge($archivosPhp, glob($raiz . '/backend/lib/*.php') ?: []);
foreach ($fuente as $ruta) {
    $texto = (string) file_get_contents($ruta);
    $clases = [];

    preg_match_all('/class="([^"]*)"/s', $texto, $mClases);
    foreach ($mClases[1] as $cuerpo) {
        // cadenas simples entre comillas simples dentro de la lista
        preg_match_all("/'([a-z0-9_ -]+)'/", $cuerpo, $mC);
        foreach ($mC[1] as $c) {
            foreach (preg_split('/\s+/', $c) ?: [] as $tok) $clases[] = $tok;
        }
        // resto de la lista, recortando los trozos PHP
        $simple = (string) preg_replace('/<\?=.*?(?:\?>|$)/s', ' ', $cuerpo);
        if (str_contains($simple, '=')) continue;
        foreach (preg_split('/\s+/', trim($simple)) ?: [] as $tok) {
            if ($tok !== '' && preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $tok)) $clases[] = $tok;
        }
    }
    preg_match_all("/'class'\s*=>\s*'([^']*)'/", $texto, $mC2);
    foreach ($mC2[1] as $c) {
        foreach (preg_split('/\s+/', $c) ?: [] as $tok) $clases[] = $tok;
    }

    foreach ($clases as $clase) {
        $clase = trim($clase);
        if ($clase === '' || isset($ignorar[$clase])) continue;
        $prefijoOk = false;
        foreach ($prefijos as $p) {
            if (str_starts_with($clase, $p)) { $prefijoOk = true; break; }
        }
        if ($prefijoOk) continue;
        if (!isset($definidas[$clase])) {
            $sinCss[$clase][] = substr($ruta, strlen($raiz) + 1);
        }
    }
}
foreach ($sinCss as $clase => $sitios) {
    falla('clase «' . $clase . '» sin CSS (usada en ' . implode(', ', array_unique($sitios)) . ')');
}
if (!$sinCss) {
    echo "  ok: todas las clases del HTML existen en frontend/css/\n";
}

// ── 4. Archivos de CSS/JS referenciados ─────────────────────
echo "· 4. Referencias a CSS y JS\n";
foreach ($archivosPhp as $ruta) {
    $texto = (string) file_get_contents($ruta);

    preg_match_all("/\\\$cssExtra\s*=\s*'([^']+)'/", $texto, $mCss);
    foreach ($mCss[1] as $f) {
        if ($f !== '' && !is_file($raiz . '/frontend/css/' . $f)) {
            falla('cssExtra inexistente: ' . $f . ' (' . substr($ruta, strlen($raiz) + 1) . ')');
        }
    }
    preg_match_all("/\\\$jsExtra\s*=\s*'([^']+)'/", $texto, $mJs);
    foreach ($mJs[1] as $lista) {
        foreach (array_map('trim', explode(',', $lista)) as $f) {
            if ($f !== '' && !is_file($raiz . '/frontend/js/' . $f)) {
                falla('jsExtra inexistente: ' . $f . ' (' . substr($ruta, strlen($raiz) + 1) . ')');
            }
        }
    }
}

// ── 5. Archivos clave ───────────────────────────────────────
echo "· 5. Archivos clave\n";
$claves = [
    '.htaccess', 'index.php',     'dev-router.php', 'dev-server.sh', 'destruir.php',
    'backend/.htaccess', 'backend/config/config.php', 'backend/config/db.php',
    'backend/database/database.sql', 'backend/storage/.htaccess',
    'frontend/partials/header.php', 'frontend/partials/footer.php',
    'frontend/partials/.htaccess', 'frontend/assets/img/favicon.png',
    'frontend/css/base.css', 'frontend/css/layout.css', 'frontend/css/components.css',
    'frontend/css/pages.css', 'frontend/css/admin.css',
    'frontend/js/main.js', 'frontend/js/editor.js',
    'frontend/login.php', 'frontend/cuenta.php', 'frontend/media.php',
    'frontend/admin/index.php', 'frontend/admin/sistema.php',
    'frontend/admin/seguridad.php', 'frontend/admin/proyecto_form.php',
    'docs/LEEME.md', 'docs/SEGURIDAD.md', 'docs/CAMBIOS.md',
];
foreach ($claves as $c) {
    if (!is_file($raiz . '/' . $c)) {
        falla('falta el archivo ' . $c);
    }
}

// ── 6. Accesibilidad básica ─────────────────────────────────
echo "· 6. Accesibilidad básica\n";
// Quita comentarios y la interpolación PHP embebida (signos de
// apertura/cierre de etiqueta corta) para no confundir los patrones
// con ejemplos del propio código. OJO: un cierre de etiqueta dentro
// de un comentario // cierra el modo PHP y vuelca el archivo a la
// salida — por eso acá no se escriben literales completos.
function textoLimpio(string $texto): string {
    $texto = (string) preg_replace('#/\*.*?\*/#s', '', $texto);
    $texto = (string) preg_replace('#^\s*//.*$#m', '', $texto);
    return (string) preg_replace('/<\?=.*?(?:\?>|$)/s', 'X', $texto);
}

$publicas = glob($raiz . '/frontend/*.php') ?: [];
foreach ($publicas as $ruta) {
    $bruto = (string) file_get_contents($ruta);
    $texto = textoLimpio($bruto);
    $base  = substr($ruta, strlen($raiz) + 1);

    // <img> siempre con alt (vacío explícito si es decorativa)
    preg_match_all('/<img\b[^>]*>/i', $texto, $mImg);
    foreach ($mImg[0] as $tag) {
        if (!preg_match('/\salt\s*=\s*"/i', $tag)) {
            falla("$base: <img> sin alt → " . substr($tag, 0, 60));
        }
    }
    // Toda pantalla que usa el layout tiene un <h1> (jerarquía de títulos)
    if (str_contains($bruto, 'abrirPagina(') && !preg_match('/<h1[\s>]/i', $texto)) {
        falla("$base: no tiene <h1>");
    }
}

// Los helpers de render también emiten <img>
foreach (glob($raiz . '/backend/lib/*.php') ?: [] as $ruta) {
    $texto = textoLimpio((string) file_get_contents($ruta));
    preg_match_all('/<img\b[^>]*>/i', $texto, $mImg);
    foreach ($mImg[0] as $tag) {
        if (!preg_match('/\salt\s*=\s*"/i', $tag)) {
            falla(basename($ruta) . ": <img> sin alt → " . substr($tag, 0, 60));
        }
    }
}

// ── 7. CSRF en todo formulario POST ─────────────────────────
echo "· 7. Token CSRF en formularios\n";
foreach ($archivosPhp as $ruta) {
    $excluido = false;
    foreach (['/vendor/', '/tests/'] as $e) {
        if (str_contains($ruta, $e)) { $excluido = true; break; }
    }
    if ($excluido) continue;

    $texto = (string) file_get_contents($ruta);
    $base  = substr($ruta, strlen($raiz) + 1);
    preg_match_all('/<form\b[^>]*method="post"[^>]*>/i', $texto, $mForm);
    foreach ($mForm[0] as $i => $tag) {
        // Se mira un tramo del formulario buscando el token
        $desde  = $mForm[0][$i] === '' ? 0 : strpos($texto, $tag);
        $tramo  = $desde === false ? '' : substr($texto, $desde, 1200);
        if (!str_contains($tramo, 'campoCSRF') && !str_contains($tramo, 'csrf_token')) {
            falla("$base: formulario POST sin token CSRF → " . substr($tag, 0, 60));
        }
    }
}

// ── 8. Enlaces internos que apuntan a archivos existentes ────
echo "· 8. Enlaces internos\n";
foreach ($archivosPhp as $ruta) {
    $texto = (string) file_get_contents($ruta);
    $base  = substr($ruta, strlen($raiz) + 1);
    preg_match_all("/url\\(\\s*'([^']+)'\\s*\\)/", $texto, $mUrl);
    foreach ($mUrl[1] as $destino) {
        if (!str_ends_with($destino, '.php') && !str_contains($destino, '.php?')) {
            continue;    // sólo se verifican rutas .php
        }
        $limpio = explode('?', $destino)[0];
        if ($limpio === '' || str_starts_with($limpio, 'http')) continue;
        if (!is_file($raiz . '/frontend/' . $limpio)) {
            falla("$base: url('$destino') no existe en frontend/");
        }
    }
}
echo "  ok: rutas .php referenciadas existen\n";

// ── 9. Sistema de temas (modo oscuro/claro) ────────────────
//  · base.css define DOS bloques oscuros con idénticas
//    declaraciones: [data-tema='oscuro'] (lo aplica JS) y
//    @media (prefers-color-scheme: dark) :root:not([data-tema])
//    (respaldo sin JS). Si difieren, un modo se desincroniza.
//  · Todo var(--token) referenciado en cualquier CSS debe estar
//    definido en :root o en los bloques oscuros (o traer valor
//    por defecto inline).
echo "· 9. Sistema de temas (oscuro/claro)\n";

$baseCss = (string) file_get_contents($raiz . '/frontend/css/base.css');

function bloqueCss(string $css, string $inicio): ?string {
    $p = strpos($css, $inicio);
    if ($p === false) return null;
    $fin = strpos($css, '}', $p);
    return $fin === false ? null : substr($css, $p, $fin - $p + 1);
}
function declaraciones(string $bloque): string {
    $interno = substr($bloque, strpos($bloque, '{') + 1, -1);
    return (string) preg_replace('/\s+/', ' ', trim($interno));
}

$bloqueAttr = bloqueCss($baseCss, ":root[data-tema='oscuro']");
$bloqueMedia = null;
if (preg_match('#@media\s*\(prefers-color-scheme:\s*dark\)#', $baseCss, $m, PREG_OFFSET_CAPTURE)) {
    $p = $m[0][1];
    $sub = substr($baseCss, $p);
    if (preg_match('#:root:not\(\[data-tema\]\)#', $sub, $m2, PREG_OFFSET_CAPTURE)) {
        $p2 = $p + $m2[0][1];
        $fin = strpos($baseCss, '}', $p2);
        $bloqueMedia = $fin === false ? null : substr($baseCss, $p2, $fin - $p2 + 1);
    }
}

if ($bloqueAttr === null) {
    falla('base.css: falta el bloque ":root[data-tema=\'oscuro\']"');
}
if ($bloqueMedia === null) {
    falla('base.css: falta el bloque @media (prefers-color-scheme: dark) '
        . ':root:not([data-tema]) — respaldo sin JavaScript');
}
if ($bloqueAttr !== null && $bloqueMedia !== null
    && declaraciones($bloqueAttr) !== declaraciones($bloqueMedia)) {
    falla('los dos bloques oscuros de base.css no coinciden: '
        . 'JS y el respaldo sin JS aplicarían temas distintos');
}
if ($bloqueAttr !== null && !str_contains($bloqueAttr, 'color-scheme: dark')) {
    falla('el bloque oscuro no declara color-scheme: dark (controles nativos)');
}

// Tokens definidos: :root principal + los dos bloques oscuros
$definidos = [];
if (preg_match('#:root\s*\{[^}]*\}#', $baseCss, $mRaiz)) {
    preg_match_all('/(--[a-z0-9-]+)\s*:/', $mRaiz[0], $mDef);
    foreach ($mDef[1] as $t) $definidos[$t] = true;
}
foreach ([$bloqueAttr, $bloqueMedia] as $b) {
    if ($b === null) continue;
    preg_match_all('/(--[a-z0-9-]+)\s*:/', $b, $mDef);
    foreach ($mDef[1] as $t) $definidos[$t] = true;
}

$sinDefinir = 0;
foreach ((glob($raiz . '/frontend/css/*.css') ?: []) as $rutaCss) {
    $css = (string) file_get_contents($rutaCss);
    // var(--token) sin valor por defecto debe existir
    preg_match_all('/var\(\s*(--[a-z0-9-]+)\s*\)/', $css, $mVar);
    foreach ($mVar[1] as $token) {
        if (!isset($definidos[$token])) {
            falla(basename($rutaCss) . ": var($token) sin definir en :root ni en el tema oscuro");
            $sinDefinir++;
        }
    }
}
if ($sinDefinir === 0) {
    echo "  ok: bloques oscuros idénticos y todos los tokens definidos\n";
}

// ── Resumen ─────────────────────────────────────────────────
echo "\n" . ($fallas === 0 ? "Todo OK\n" : "$fallas falla(s)\n");
exit($fallas === 0 ? 0 : 1);
