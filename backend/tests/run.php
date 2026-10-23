<?php
// ============================================================
//  EJECUTOR DE PRUEBAS — backend/tests/run.php
//
//      php backend/tests/run.php
//
//  Corre todas las suites del directorio tests/ y devuelve un
//  código de salida único (0 = OK) para poder usarlo en CI o
//  como paso previo a una entrega.
// ============================================================

declare(strict_types=1);

$suites = glob(__DIR__ . '/*_test.php') ?: [];
sort($suites);

if (!$suites) {
    echo "No se encontraron suites (*_test.php).\n";
    exit(1);
}

$fallas = 0;

foreach ($suites as $suite) {
    $nombre = basename($suite);
    echo "══ " . $nombre . " " . str_repeat('═', max(1, 50 - strlen($nombre))) . "\n";

    $salida = [];
    $codigo = 0;
    exec('php ' . escapeshellarg($suite) . ' 2>&1', $salida, $codigo);
    echo implode("\n", $salida) . "\n\n";

    if ($codigo !== 0) {
        $fallas++;
    }
}

echo str_repeat('─', 52) . "\n";
echo $fallas === 0
    ? "TODAS LAS SUITES OK (" . count($suites) . ")\n"
    : "$fallas suite(s) con fallas de " . count($suites) . "\n";

exit($fallas === 0 ? 0 : 1);
