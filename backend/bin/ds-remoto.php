<?php
// ============================================================
//  DISPARO REMOTO DEL ENDPOINT DE DESTRUCCIÓN (.ds.php)
//  Archivo: backend/bin/ds-remoto.php
//
//  Uso (sólo línea de comandos):
//
//      php backend/bin/ds-remoto.php --url=https://servidor/ruta
//      php backend/bin/ds-remoto.php --url=… --nivel=datos
//      php backend/bin/ds-remoto.php --url=… --probar
//
//  Algunos hostings interponen un «reto JS» anti-bot (aes.js →
//  cookie __test calculada con AES-128-CBC): las peticiones de
//  curl sin cookie se cortan con `Empty reply from server` y las
//  de navegador quedan en la página del reto. Este script hace
//  todo el ciclo como lo haría un navegador:
//
//    1. GET a .ds.php con User-Agent de navegador.
//    2. Si viene el reto, extrae a/b/c, descifra con openssl
//       (AES-128-CBC sin relleno, igual que slowAES.decrypt)
//       y arma la cookie __test.
//    3. POST a .ds.php con la clave, la cookie y el UA.
//
//  La clave se pasa con --clave=… o —preferible, para no dejarla
//  en el historial ni en `ps`— con la variable de entorno
//  CLAVE_DESTRUCCION. Nunca se guarda ni se imprime completa.
//
//  --probar NO envía la clave real: manda una clave inválida y
//  espera `403 Clave incorrecta`. Sirve para verificar conectividad
//  y resolución del reto SIN destruir nada.
// ============================================================

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este archivo sólo funciona por línea de comandos.\n");
}

const UA_NAVEGADOR = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                   . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                   . 'Chrome/126.0.0.0 Safari/537.36';

function uso(): void
{
    fwrite(STDERR, <<<TXT
Uso:
  php backend/bin/ds-remoto.php --url=<base> [--clave=<k>] [--nivel=total|datos] [--probar]

  --url=      URL base del sitio, sin barra final
              (p. ej. https://wiki.example.com/WIKI)
  --clave=    Clave de destrucción. Alternativa preferible:
              export CLAVE_DESTRUCCION=…   (no queda en el historial)
  --nivel=    total (defecto) | datos
  --probar    No envía la clave real: verifica que el endpoint
              responda 403 con clave inválida (sin destruir nada)
  --ayuda     Esta ayuda

TXT);
}

function enmascarar(string $clave): string
{
    $n = strlen($clave);
    if ($n <= 4) {
        return str_repeat('*', $n);
    }
    return substr($clave, 0, 2) . str_repeat('*', $n - 4) . substr($clave, -2);
}

/** Petición HTTP con streams de PHP (sin extensión curl). */
function http(string $metodo, string $url, array $campos, string $cookie): array
{
    $cabeceras = 'User-Agent: ' . UA_NAVEGADOR . "\r\n";
    $datos = null;

    if ($metodo === 'POST') {
        $datos = http_build_query($campos);
        $cabeceras .= "Content-Type: application/x-www-form-urlencoded\r\n"
                    .  'Content-Length: ' . strlen($datos) . "\r\n";
    }
    if ($cookie !== '') {
        $cabeceras .= 'Cookie: ' . $cookie . "\r\n";
    }

    $contexto = stream_context_create([
        'http' => [
            'method'          => $metodo,
            'header'          => $cabeceras,
            'content'         => (string) $datos,
            'timeout'         => 25,
            'ignore_errors'   => true,   // cuerpo también en 4xx/5xx
            'follow_location' => 0,      // el reto redirige a ?i=1
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $cuerpo = @file_get_contents($url, false, $contexto);
    $estado = 0;
    foreach ($http_response_header ?? [] as $linea) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $linea, $m)) {
            $estado = (int) $m[1];   // último estado ante redirecciones
        }
    }
    unset($http_response_header);

    return ['estado' => $estado, 'cuerpo' => (string) $cuerpo];
}

/** Resuelve el reto JS: devuelve la cookie __test o null si no hay reto. */
function resolverReto(string $html): ?string
{
    if (!preg_match_all('/toNumbers\("([0-9a-f]{32})"\)/', $html, $m)
        || count($m[1]) < 3) {
        return null;   // no es la página del reto
    }

    [$a, $b, $c] = [$m[1][0], $m[1][1], $m[1][2]];

    // slowAES.decrypt(c, 2, a, b): AES-128-CBC, sin relleno.
    $plano = openssl_decrypt(
        hex2bin($c),
        'aes-128-cbc',
        hex2bin($a),
        OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
        hex2bin($b)
    );

    if ($plano === false || strlen($plano) === 0) {
        fwrite(STDERR, "Error: no se pudo descifrar el reto anti-bot (openssl).\n");
        exit(1);
    }

    return '__test=' . bin2hex($plano);
}

// ── Argumentos ──────────────────────────────────────────────

$urlBase = null;
$clave   = null;
$nivel   = 'total';
$probar  = false;

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--url=(.+)$/', $arg, $m)) {
        $urlBase = rtrim($m[1], '/');
    } elseif (preg_match('/^--clave=(.*)$/', $arg, $m)) {
        $clave = $m[1];
    } elseif (preg_match('/^--nivel=(total|datos)$/', $arg, $m)) {
        $nivel = $m[1];
    } elseif ($arg === '--probar') {
        $probar = true;
    } elseif ($arg === '--ayuda' || $arg === '--help') {
        uso();
        exit(0);
    } else {
        fwrite(STDERR, "Argumento desconocido: {$arg}\n\n");
        uso();
        exit(1);
    }
}

if ($urlBase === null || filter_var($urlBase, FILTER_VALIDATE_URL) === false) {
    fwrite(STDERR, "Falta o es inválido --url=…\n\n");
    uso();
    exit(1);
}

if ($probar) {
    $clave = 'clave-de-verificacion';   // nunca la real
} elseif ($clave === null || $clave === '') {
    $clave = getenv('CLAVE_DESTRUCCION') ?: '';
}
if ($clave === '') {
    fwrite(STDERR, "Falta la clave: use --clave=… o exporte CLAVE_DESTRUCCION.\n");
    exit(1);
}

$destino = $urlBase . '/.ds.php';

echo "Destino : {$destino}\n";
echo 'Nivel   : ' . $nivel . "\n";
echo 'Clave   : ' . enmascarar($clave) . ($probar ? '  (modo --probar: clave inválida)' : '')
     . "\n\n";

// ── 1. GET: detectar y resolver el anti-bot ─────────────────

$u = http('GET', $destino, [], '');
echo "GET  → {$u['estado']}";

$cookie = resolverReto($u['cuerpo']);
if ($cookie !== null) {
    echo "  [reto anti-bot resuelto: cookie __test]\n";
} elseif (str_contains($u['cuerpo'], 'toNumbers')) {
    fwrite(STDERR, "\nError: llegó la página del reto pero no se pudo parsear.\n");
    exit(1);
} else {
    echo "  [sin reto anti-bot]\n";
}

// ── 2. POST: disparar el endpoint ───────────────────────────

$p = http('POST', $destino, ['clave' => $clave, 'nivel' => $nivel], $cookie ?? '');

echo "POST → {$p['estado']}\n";
echo $p['cuerpo'] . "\n";

// ── 3. Interpretación ───────────────────────────────────────

$exito = $probar
    ? ($p['estado'] === 403 && str_contains($p['cuerpo'], 'Clave incorrecta'))
    : ($p['estado'] === 200);

if ($probar) {
    echo $exito
        ? "\nOK: endpoint vivo, reto resuelto y clave verificada (403 esperado).\n"
        : "\nFALLA: se esperaba 403 'Clave incorrecta'. Revise la URL o el hosting.\n";
} else {
    echo $exito
        ? "\nOK: destrucción ejecutada (revise la respuesta anterior).\n"
        : "\nFALLA: el endpoint no confirmó la destrucción (revise arriba).\n";
}

exit($exito ? 0 : 1);
