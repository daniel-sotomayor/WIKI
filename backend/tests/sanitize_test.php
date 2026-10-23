<?php
// ============================================================
//  PRUEBAS — saneador de HTML (backend/tests/sanitize_test.php)
//
//      php backend/tests/sanitize_test.php
//
//  Verifica las dos barreras contra XSS almacenado:
//    · sanearHTML()      → lo que se guarda y se muestra
//    · filtrarSinDOM()   → ruta de respaldo sin extensión DOM
//    · filtrarDOM()      → ruta principal (sólo si ext. dom instalada)
//
//  Sale con código 0 si todo pasa y 1 si hay alguna falla,
//  para poder usarlo en un script de integración continua.
// ============================================================

declare(strict_types=1);

// Shims mínimos: si falta mbstring, el saneador se puede pro igual.
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, string $e = 'UTF-8'): int { return strlen($s); }
    function mb_substr(string $s, int $i, ?int $l = null, string $e = 'UTF-8'): string {
        return $l === null ? substr($s, $i) : substr($s, $i, $l);
    }
    function mb_strtolower(string $s, string $e = 'UTF-8'): string { return strtolower($s); }
    function mb_strtoupper(string $s, string $e = 'UTF-8'): string { return strtoupper($s); }
}

require_once __DIR__ . '/../lib/sanitize.php';

$fallas = 0;
$pruebas = 0;

/**
 * Devuelve las violaciones de seguridad detectadas en una salida.
 */
function violaciones(string $html): array {
    $mal = [];
    if (preg_match('#<\s*script\b#i', $html))                    $mal[] = '<script>';
    if (preg_match('/\son[a-z]+\s*=/i', $html))                  $mal[] = 'atributo on*';
    if (preg_match('/javascript\s*:/i', $html))                  $mal[] = 'javascript:';
    if (preg_match('#<\s*(iframe|svg|style|form|input|object|embed|video|audio|template)\b#i', $html)) {
        $mal[] = 'etiqueta prohibida';
    }
    if (preg_match('/\sstyle\s*=/i', $html))                     $mal[] = 'atributo style';
    if (preg_match('/\shref\s*=\s*"\s*(vbscript|data|file):/i', $html)) $mal[] = 'URL peligrosa';
    return $mal;
}

function verificar(string $ruta, string $entrada, string $esperado): void {
    global $fallas, $pruebas;
    $pruebas++;

    $salida = $ruta === 'sanearHTML'
        ? sanearHTML($entrada)
        : ($ruta === 'filtrarSinDOM' ? filtrarSinDOM($entrada) : filtrarDOM($entrada));

    $mal = violaciones($salida);
    if ($esperado !== '') {
        foreach (explode('|', $esperado) as $fragmento) {
            if (!str_contains($salida, $fragmento)) {
                $mal[] = 'falta «' . $fragmento . '»';
            }
        }
    }

    if ($mal) {
        $fallas++;
        printf("  FALLA [%s] %s\n        entrada : %s\n        salida  : %s\n",
            $ruta, implode(', ', $mal), $entrada, $salida);
    }
}

// ── 1. Ataques: ninguno puede sobrevivir ────────────────────
$ataques = [
    '<script>alert(1)</script><p>hola</p>',
    '<p onclick="alert(1)">texto</p>',
    '<img src=x onerror="alert(1)">',
    '<a href="javascript:alert(1)">click</a>',
    '<a href="JaVaScRiPt:alert(1)">click</a>',
    '<a href="&#106;avascript:alert(1)">click</a>',
    '<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>',
    '<iframe src="https://evil.test"></iframe>',
    '<div style="background:url(javascript:alert(1))">t</div>',
    '<svg/onload=alert(1)>',
    '<p>ok</p></div><script>alert(2)</script>',
    '<form action="/x"><input name="u"></form>',
    '<video src="a.mp4" onerror="alert(1)"></video>',
    '<a href="vbscript:msgbox(1)">x</a>',
    '<img src="x" onmouseover="alert(1)" alt="a">',
    '<style>body{display:none}</style><p>t</p>',
];

// ── 2. Contenido legítimo: debe sobrevivir ──────────────────
$legitimos = [
    '<h2>Título</h2><ul><li>uno</li><li>dos</li></ul>' => '<h2>Título</h2>|<li>uno</li>',
    '<a href="/wiki.php?tag=x" class="chip chip--tag is-active" title="t">etiqueta</a>'
        => 'href="/wiki.php?tag=x"|class="chip chip--tag is-active"',
    '<table><thead><tr><th scope="col">A</th></tr></thead>'
        . '<tbody><tr><td colspan="2">x</td></tr></tbody></table>'
        => '<th scope="col">A</th>|<td colspan="2">x</td>',
    '<blockquote><p>cita</p></blockquote><pre><code>var x = 1;</code></pre>'
        => '<blockquote>|<pre><code>',
    // «open» es un atributo booleano: DOM lo serializa como open=""
    // y la ruta sin DOM deja open"> — ambos son válidos.
    '<details open><summary>ver</summary><p>oculto</p></details>' => '<details open|<summary>',
    '<img src="media.php?f=abc.jpg" alt="foto" width="300">' => 'alt="foto"|width="300"',
    '<p class="article-body--compact">ok</p>' => 'class="article-body--compact"',
];

$rutas = ['sanearHTML', 'filtrarSinDOM'];
if (class_exists('DOMDocument')) {
    $rutas[] = 'filtrarDOM';
}

echo "· Pruebas de saneamiento (" . implode(', ', $rutas) . ")\n";
echo "  " . count($ataques) . " ataques + " . count($legitimos) . " casos legítimos\n";

foreach ($rutas as $ruta) {
    foreach ($ataques as $a) {
        verificar($ruta, $a, '');
    }
    foreach ($legitimos as $entrada => $esperado) {
        verificar($ruta, $entrada, $esperado);
    }
}

// ── 3. Conversión a texto plano ─────────────────────────────
$pruebas++;
$texto = htmlATexto('<h2>Título</h2><p>Texto &amp; más</p>');
if (!str_contains($texto, 'Título') || !str_contains($texto, 'más')
    || str_contains($texto, '<') || str_contains($texto, '&amp;')) {
    $fallas++;
    echo "  FALLA htmlATexto: " . var_export($texto, true) . "\n";
}

// ── Resumen ─────────────────────────────────────────────────
$extDom = class_exists('DOMDocument') ? 'sí' : 'no';
printf("\n%d pruebas, %d fallas · extensión DOM: %s\n", $pruebas, $fallas, $extDom);
if (!class_exists('DOMDocument')) {
    echo "Nota: sin la extensión DOM sólo se ejercitó la ruta de respaldo;\n";
    echo "      instalá php-xml y volvé a correr para cubrir filtrarDOM().\n";
}

exit($fallas === 0 ? 0 : 1);
