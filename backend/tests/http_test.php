<?php
// ============================================================
//  PRUEBAS — recorrido HTTP funcional completo
//  Archivo: backend/tests/http_test.php
//
//      php backend/tests/http_test.php
//
//  Levanta el servidor de desarrollo (php -S + dev-router.php),
//  recorre los flujos principales con curl y limpia todo lo que
//  crea. Si no hay curl o no hay base de datos, se omite con OK.
//
//  Cubre: páginas públicas, panel, login (bien y mal), CSRF,
//  creación de noticia, subida de archivo (válida y rechazada),
//  entrega por media.php, logout y modo mantenimiento.
//
//  Código de salida: 0 = todo OK, 1 = hay fallas.
// ============================================================

declare(strict_types=1);

$raiz = dirname(__DIR__, 2);

if (!trim((string) shell_exec('command -v curl 2>/dev/null'))) {
    echo "· Recorrido HTTP: OMITIDA (no hay curl)\n";
    exit(0);
}

// Mismos valores por defecto que dev-server.sh (en XAMPP se pueden
// definir DB_USER=root y DB_PASS vacía en el entorno).
putenv('DB_HOST=' . (getenv('DB_HOST') ?: '127.0.0.1'));
putenv('DB_USER=' . (getenv('DB_USER') ?: 'telecom_wiki'));
putenv('DB_PASS=' . (getenv('DB_PASS') ?: 'telecom2026'));
putenv('DB_NAME=' . (getenv('DB_NAME') ?: 'telecom_wiki'));

require_once $raiz . '/backend/config/config.php';
require_once $raiz . '/backend/config/db.php';

try {
    getDB()->query('SELECT 1 FROM usuarios LIMIT 1');
} catch (Throwable $e) {
    echo "· Recorrido HTTP: OMITIDA (sin base de datos — importá database.sql)\n";
    exit(0);
}

$fallas = 0;
function falla(string $m): void {
    global $fallas;
    $fallas++;
    echo "  FALLA: $m\n";
}
function ok(string $m): void {
    echo "  ok: $m\n";
}

// ── Arranque del servidor ───────────────────────────────────
$puerto = 0;
$proceso = null;
$tmp     = sys_get_temp_dir() . '/tw_http_' . getmypid();
@mkdir($tmp, 0777, true);
$jar     = $tmp . '/cookies.txt';
$cuerpoA = $tmp . '/body.txt';

$base = '';
for ($intento = 0; $intento < 8; $intento++) {
    $puerto = 8200 + random_int(0, 99);
    $base   = "http://127.0.0.1:$puerto";
    $descriptors = [1 => ['file', $tmp . '/server.log', 'a'], 2 => ['file', $tmp . '/server.log', 'a']];
    $env = array_merge(getenv(), [
        'DB_USER' => getenv('DB_USER') ?: 'telecom_wiki',
        'DB_PASS' => getenv('DB_PASS') ?: 'telecom2026',
        'DB_NAME' => getenv('DB_NAME') ?: 'telecom_wiki',
    ]);
    $proceso = proc_open(
        ['php', '-S', "127.0.0.1:$puerto", '-t', $raiz, $raiz . '/dev-router.php'],
        [1 => ['file', $tmp . '/server.log', 'a'], 2 => ['file', $tmp . '/server.log', 'a']],
        $pipes, $raiz, $env
    );
    if (!is_resource($proceso)) {
        continue;
    }
    // Espera a que responda
    for ($i = 0; $i < 40; $i++) {
        usleep(150000);
        $salida = [];
        $codigo = 0;
        exec('curl -s -o /dev/null -w "%{http_code}" ' . escapeshellarg("$base/frontend/login.php") . ' 2>/dev/null', $salida, $codigo);
        if (($salida[0] ?? '') === '200') {
            break 2;
        }
    }
    proc_terminate($proceso);
    proc_close($proceso);
    $proceso = null;
}

if ($proceso === null) {
    echo "· Recorrido HTTP: OMITIDA (no se pudo levantar el servidor en ningún puerto)\n";
    exit(0);
}

register_shutdown_function(function () use ($proceso, $tmp) {
    if (is_resource($proceso)) {
        proc_terminate($proceso);
        proc_close($proceso);
    }
    // Limpieza de archivos temporales
    foreach (glob($tmp . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tmp);
});

/** GET/POST con curl. Devuelve [código, url de redirección, cuerpo]. */
function peticion(string $metodo, string $ruta, array $datos = [], array $archivos = []): array {
    global $base, $jar, $cuerpoA;
    $cmd = 'curl -s -b ' . escapeshellarg($jar) . ' -c ' . escapeshellarg($jar)
         . ' -o ' . escapeshellarg($cuerpoA)
         . ' -w ' . escapeshellarg('%{http_code} %{redirect_url}');
    if ($metodo === 'POST') {
        $cmd .= ' -X POST';
        // curl no admite mezclar -d/-F: con archivos todo va en
        // multipart (-F); sin archivos, urlencoded (--data-urlencode).
        foreach ($datos as $k => $v) {
            $cmd .= $archivos !== []
                ? ' -F ' . escapeshellarg($k . '=' . $v)
                : ' --data-urlencode ' . escapeshellarg($k . '=' . $v);
        }
        foreach ($archivos as $k => $rutaArchivo) {
            $cmd .= ' -F ' . escapeshellarg($k . '=@' . $rutaArchivo);
        }
    }
    $cmd .= ' ' . escapeshellarg($base . $ruta);
    $salida = [];
    exec($cmd . ' 2>/dev/null', $salida);
    $linea  = trim((string) ($salida[0] ?? '0 0'));
    [$codigo, $redir] = array_pad(explode(' ', $linea, 2), 2, '');
    return [(int) $codigo, $redir, (string) @file_get_contents($cuerpoA)];
}

function extraerToken(string $html): string {
    return preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $m) ? $m[1] : '';
}

function db(): PDO {
    return getDB();
}

echo "· Recorrido HTTP funcional (puerto " . preg_replace('/.*:/', '', $base) . ")\n";

// ── 1. Páginas públicas ─────────────────────────────────────
[$c] = peticion('GET', '/');
if ($c !== 302) falla("GET / → $c (esperado 302)"); else ok('raíz redirige a frontend/');

foreach (['/frontend/index.php' => 'TelecomWiki', '/frontend/wiki.php' => '<h1',
          '/frontend/proyectos.php' => '<h1', '/frontend/noticias.php' => '<h1',
          '/frontend/login.php' => 'csrf_token'] as $ruta => $esperado) {
    [$c, , $html] = peticion('GET', $ruta);
    if ($c !== 200) falla("GET $ruta → $c");
    elseif (!str_contains($html, $esperado)) falla("GET $ruta no contiene «$esperado»");
}
ok('páginas públicas responden 200');

// Alternador de tema presente y meta de esquema correcta
[$c, , $html] = peticion('GET', '/frontend/index.php');
if ($c !== 200 || !str_contains($html, 'data-tema-toggle')) {
    falla('la portada no muestra el alternador de tema');
} elseif (!str_contains($html, 'color-scheme" content="light dark')) {
    falla('la meta color-scheme no permite ambos temas');
} elseif (!str_contains($html, 'localStorage.getItem(\'tw-tema\')')) {
    falla('falta el script anti-flash del tema en el <head>');
} else {
    ok('alternador de tema, meta light dark y script anti-flash');
}

[$c, $redir] = peticion('GET', '/frontend/admin/index.php');
if ($c !== 302 || !str_contains($redir, 'login.php')) {
    falla("panel sin sesión → $c $redir (esperado 302 a login)");
} else {
    ok('el panel exige sesión');
}

// ── 2. Login ────────────────────────────────────────────────
[, , $html] = peticion('GET', '/frontend/login.php');
$token = extraerToken($html);
if ($token === '') {
    falla('no se pudo leer el token CSRF del login');
    exit(1);
}

[$c, , $html] = peticion('POST', '/frontend/login.php',
    ['usuario' => 'admin', 'password' => 'clave-que-no-existe', 'csrf_token' => $token]);
if ($c !== 200 || !str_contains($html, 'Usuario o contraseña incorrectos')) {
    falla('el login inválido no muestra el mensaje esperado (código ' . $c . ')');
} else {
    ok('login con contraseña incorrecta → mensaje genérico');
}

[, , $html] = peticion('GET', '/frontend/login.php');
$token = extraerToken($html);
[$c, $redir] = peticion('POST', '/frontend/login.php',
    ['usuario' => 'admin', 'password' => 'Admin2026!', 'csrf_token' => $token]);
if ($c !== 302) falla("login correcto → $c (esperado 302)"); else ok('login con sesión de administrador');

[$c, , $html] = peticion('GET', '/frontend/admin/index.php');
if ($c !== 200 || !str_contains($html, 'Panel de administración')) {
    falla("panel con sesión → $c");
} else {
    ok('el panel responde 200 con sesión');
}

foreach (['/frontend/admin/sistema.php' => 'Diagnóstico del sistema',
          '/frontend/cuenta.php' => 'Mi cuenta',
          '/frontend/admin/usuarios.php' => 'Gestión de usuarios'] as $ruta => $esperado) {
    [$c, , $html] = peticion('GET', $ruta);
    if ($c !== 200 || !str_contains($html, $esperado)) falla("GET $ruta → $c");
}
ok('pantallas del panel (diagnóstico, cuenta, usuarios)');

// ── 3. CSRF ─────────────────────────────────────────────────
[$c] = peticion('POST', '/frontend/noticias_moderar.php',
    ['noticia_id' => '1', 'accion' => 'aprobar']);
if ($c !== 403) falla("POST sin CSRF → $c (esperado 403)"); else ok('POST sin token CSRF → 403');

// ── 4. Noticia completa ─────────────────────────────────────
$tituloNoticia = 'Noticia de prueba HTTP ' . date('Ymd_His');
[, , $html] = peticion('GET', '/frontend/admin/noticia_form.php');
$token = extraerToken($html);
[$c, $redir] = peticion('POST', '/frontend/admin/noticia_form.php', [
    'csrf_token' => $token, 'accion' => 'guardar',
    'titulo' => $tituloNoticia,
    'resumen' => 'Resumen de la noticia creada por la prueba HTTP funcional.',
    'categoria' => 'Pruebas',
    'contenido' => '<p>Contenido de prueba.</p><script>alert(1)</script>',
]);
$slugNoticia = (string) db()->query(
    'SELECT slug FROM noticias WHERE titulo = ' . db()->quote($tituloNoticia) . ' LIMIT 1'
)->fetchColumn();
if ($c !== 302 || $slugNoticia === '') {
    falla("crear noticia → $c, slug «$slugNoticia»");
} else {
    ok("noticia creada (slug $slugNoticia)");
}

[, , $html] = peticion('GET', '/frontend/noticia.php?slug=' . urlencode($slugNoticia));
if (!str_contains($html, 'Contenido de prueba') || str_contains($html, 'script>alert')) {
    falla('la noticia pública no muestra el contenido saneado');
} else {
    ok('la noticia se ve publicada y el HTML quedó saneado');
}

// ── 5. Proyecto con archivo y rechazo de .php ───────────────
$png = $tmp . '/prueba.png';
file_put_contents($png, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
));
$php = $tmp . '/prueba.php';
file_put_contents($php, '<?php echo "hack";');

$tituloProyecto = 'Proyecto de prueba HTTP ' . date('His');
[, , $html] = peticion('GET', '/frontend/admin/proyecto_form.php');
$token = extraerToken($html);
[$c, $redir] = peticion('POST', '/frontend/admin/proyecto_form.php', [
    'csrf_token' => $token, 'accion' => 'guardar',
    'titulo' => $tituloProyecto, 'categoria_id' => '1', 'estado' => 'activo',
], ['archivos' => $png]);
$idProyecto = (int) db()->query(
    'SELECT id FROM proyectos WHERE titulo = ' . db()->quote($tituloProyecto) . ' LIMIT 1'
)->fetchColumn();
$media = $idProyecto ? db()->query(
    "SELECT * FROM media WHERE entidad_tipo = 'proyecto' AND entidad_id = $idProyecto LIMIT 1"
)->fetch() : false;

if ($c !== 302 || $idProyecto === 0 || !$media) {
    falla("crear proyecto con imagen → $c, proyecto $idProyecto, media " . ($media ? 'ok' : 'no'));
} else {
    $rutaDisco = UPLOADS_DIR . '/' . $media['tipo'] . '/' . $media['nombre_guardado'];
    if (!is_file($rutaDisco)) {
        falla('el archivo no quedó en ' . $rutaDisco);
    } else {
        ok('proyecto creado con imagen adjunta (fila media + archivo en disco)');
    }

    [$c, , $html] = peticion('GET', '/frontend/media.php?f=' . urlencode($media['nombre_guardado']));
    if ($c !== 200) falla("media.php → $c"); else ok('media.php entrega el archivo');

    [$c, , $html] = peticion('GET', '/frontend/proyecto.php?slug=' . urlencode(
        (string) db()->query("SELECT slug FROM proyectos WHERE id = $idProyecto")->fetchColumn()
    ));
    if ($c !== 200 || !str_contains($html, 'media.php?f=')) {
        falla('la ficha pública del proyecto no muestra la galería');
    } else {
        ok('la galería aparece en la ficha pública');
    }
}

// Subida rechazada: el .php no pasa la validación
[, , $html] = peticion('GET', '/frontend/admin/proyecto_form.php?id=' . $idProyecto);
$token = extraerToken($html);
[$c] = peticion('POST', '/frontend/admin/proyecto_form.php', [
    'csrf_token' => $token, 'accion' => 'guardar', 'proyecto_id' => (string) $idProyecto,
    'titulo' => $tituloProyecto, 'categoria_id' => '1', 'estado' => 'activo',
], ['archivos' => $php]);
[, , $html] = peticion('GET', '/frontend/admin/proyecto_form.php?id=' . $idProyecto);
if (!str_contains($html, 'Tipo de archivo no permitido')) {
    falla('la subida de un .php no fue rechazada con el mensaje esperado');
} else {
    ok('subida de .php rechazada');
}

// ── 6. Logout ───────────────────────────────────────────────
[, , $html] = peticion('GET', '/frontend/admin/index.php');
$token = extraerToken($html);
[$c] = peticion('POST', '/frontend/logout.php', ['csrf_token' => $token]);
[$c2, $redir] = peticion('GET', '/frontend/admin/index.php');
if ($c !== 302 || $c2 !== 302) falla("logout → $c, y luego panel → $c2");
else ok('logout por POST y panel protegido otra vez');

// ── 7. Modo mantenimiento ───────────────────────────────────
db()->exec("UPDATE config_sitio SET valor = '1' WHERE clave = 'mantenimiento'");
[$c] = peticion('GET', '/frontend/index.php');
if ($c !== 503) falla("mantenimiento → $c en la portada (esperado 503)");
else ok('modo mantenimiento responde 503 al público');
db()->exec("UPDATE config_sitio SET valor = '0' WHERE clave = 'mantenimiento'");
[$c] = peticion('GET', '/frontend/index.php');
if ($c !== 200) falla("al desactivar mantenimiento → $c");
else ok('el sitio vuelve a 200 al desactivar el mantenimiento');

// ── Limpieza ────────────────────────────────────────────────
if ($idProyecto) {
    db()->prepare("DELETE FROM media WHERE entidad_tipo = 'proyecto' AND entidad_id = ?")
        ->execute([$idProyecto]);
    db()->prepare('DELETE FROM proyectos WHERE id = ?')->execute([$idProyecto]);
}
if ($slugNoticia !== '') {
    db()->prepare('DELETE FROM noticias WHERE slug = ?')->execute([$slugNoticia]);
}
if ($media && is_file(UPLOADS_DIR . '/' . $media['tipo'] . '/' . $media['nombre_guardado'])) {
    @unlink(UPLOADS_DIR . '/' . $media['tipo'] . '/' . $media['nombre_guardado']);
}

echo "\n" . ($fallas === 0 ? "Todo OK\n" : "$fallas falla(s)\n");
exit($fallas === 0 ? 0 : 1);
