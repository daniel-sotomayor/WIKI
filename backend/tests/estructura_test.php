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
    '.htaccess', 'index.php',     'dev-router.php', 'dev-server.sh',
    'backend/.htaccess', 'backend/config/config.php', 'backend/config/db.php',
    'backend/database/database.sql', 'backend/storage/.htaccess',
    'frontend/partials/header.php', 'frontend/partials/footer.php',
    'frontend/partials/.htaccess', 'frontend/assets/img/favicon.svg',
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

// ── Resumen ─────────────────────────────────────────────────
echo "\n" . ($fallas === 0 ? "Todo OK\n" : "$fallas falla(s)\n");
exit($fallas === 0 ? 0 : 1);
