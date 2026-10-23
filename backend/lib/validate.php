<?php
// ============================================================
//  VALIDACIÓN DE ENTRADA
//  Archivo: backend/lib/validate.php
//
//  Toda la entrada que llega por GET/POST DEBE pasar por acá antes
//  de usarse. Reglas aplicadas de forma sistemática en todas las
//  pantallas para evitar los problemas que tenía el código original:
//    · parámetros tipo array (?q[]=x) provocaban un TypeError fatal
//    · no se controlaba la longitud y los VARCHAR de la BD reventaban
//    · los comodines % y _ de LIKE no se escapaban
//    · los valores de estado/tipo se aceptaban sin lista blanca
// ============================================================

require_once __DIR__ . '/helpers.php';

/**
 * Lee un parámetro GET/POST garantizando que sea UNA cadena de texto.
 * Si llega un array (ataque o error del cliente) devuelve ''.
 */
function entrada(array $origen, string $clave, int $maxLen = 300): string {
    $v = $origen[$clave] ?? '';
    if (is_array($v)) {
        logSeguridad('entrada_invalida', currentUser()['usuario'] ?? null,
            "parámetro $clave recibido como array");
        return '';
    }
    $v = trim((string) $v);
    return mb_strlen($v) > $maxLen ? mb_substr($v, 0, $maxLen) : $v;
}

function saneaGET(string $clave, int $maxLen = 300): string {
    return entrada($_GET, $clave, $maxLen);
}

function saneaPOST(string $clave, int $maxLen = 300): string {
    return entrada($_POST, $clave, $maxLen);
}

/**
 * Extrae un entero seguro (IDs, páginas, porcentajes).
 */
function entero($v, int $defecto = 0): int {
    if (is_array($v) || $v === null || $v === '') {
        return $defecto;
    }
    return filter_var($v, FILTER_VALIDATE_INT) !== false ? (int) $v : $defecto;
}

/**
 * Escapa los comodines de LIKE para que el usuario pueda buscar
 * literalmente porcentajes y guiones bajos sin saturar la consulta.
 * Debe usarse junto con un ESCAPE explícito en la sentencia SQL.
 */
function escaparLike(string $texto): string {
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto);
}

/**
 * Aplica lista blanca a un valor.
 */
function enLista(string $valor, array $permitidos, string $defecto): string {
    return in_array($valor, $permitidos, true) ? $valor : $defecto;
}

function esFechaValida(string $texto): bool {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

// ── Validador declarativo ───────────────────────────────────
//
//   $errores = validar($_POST, [
//       'titulo'   => ['requerido' => true, 'max' => 200],
//       'email'    => ['requerido' => true, 'email' => true, 'max' => 150],
//       'estado'   => ['en' => ['borrador','pendiente']],
//       'fecha'    => ['requerido' => true, 'fecha' => true],
//       'web'      => ['url' => true],
//   ]);
//   if ($errores) { … mostrar $errores … }
//
function validar(array $datos, array $reglas, array $etiquetas = []): array {
    $errores = [];

    foreach ($reglas as $campo => $reglasCampo) {
        $valor = $datos[$campo] ?? '';
        if (is_array($valor)) {
            $valor = '';
        }
        $valor = is_string($valor) ? trim($valor) : '';
        $etiqueta = $etiquetas[$campo] ?? ucfirst(strtolower($campo));

        if (!empty($reglasCampo['requerido']) && $valor === '') {
            $errores[$campo] = "El campo «{$etiqueta}» es obligatorio.";
            continue;
        }

        // El resto de las reglas sólo aplican si hay valor
        if ($valor === '') {
            continue;
        }

        if (isset($reglasCampo['max']) && mb_strlen($valor) > (int) $reglasCampo['max']) {
            $errores[$campo] = "«{$etiqueta}» no puede superar los {$reglasCampo['max']} caracteres.";
            continue;
        }
        if (isset($reglasCampo['min']) && mb_strlen($valor) < (int) $reglasCampo['min']) {
            $errores[$campo] = "«{$etiqueta}» debe tener al menos {$reglasCampo['min']} caracteres.";
            continue;
        }
        if (!empty($reglasCampo['email']) && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $errores[$campo] = "«{$etiqueta}» no es un correo electrónico válido.";
            continue;
        }
        if (!empty($reglasCampo['url']) && !filter_var($valor, FILTER_VALIDATE_URL)) {
            $errores[$campo] = "«{$etiqueta}» no es una URL válida (debe incluir https://).";
            continue;
        }
        if (!empty($reglasCampo['fecha']) && !esFechaValida($valor)) {
            $errores[$campo] = "«{$etiqueta}» no es una fecha válida (use AAAA-MM-DD).";
            continue;
        }
        if (isset($reglasCampo['en']) && !in_array($valor, $reglasCampo['en'], true)) {
            $errores[$campo] = "«{$etiqueta}» tiene un valor no permitido.";
            continue;
        }
        if (isset($reglasCampo['entero_en'])) {
            $n = entero($valor, -1);
            if ($n < (int) $reglasCampo['entero_en'][0] || $n > (int) $reglasCampo['entero_en'][1]) {
                $errores[$campo] = "«{$etiqueta}» está fuera del rango permitido.";
                continue;
            }
        }
        if (!empty($reglasCampo['letrasnum']) && !preg_match('/^[A-Za-z0-9._-]+$/', $valor)) {
            $errores[$campo] = "«{$etiqueta}» sólo puede contener letras, números, punto, guion y guion bajo.";
            continue;
        }
    }

    return $errores;
}

/**
 * Muestra los errores de validación de forma uniforme.
 */
function pintarErrores(array $errores): string {
    if (!$errores) {
        return '';
    }
    $html = '<div class="alert alert-danger" role="alert"><strong>Revisá los siguientes campos:</strong><ul>';
    foreach ($errores as $texto) {
        $html .= '<li>' . e($texto) . '</li>';
    }
    return $html . '</ul></div>';
}

/**
 * Conserva el valor escrito por el usuario en el formulario.
 */
function valorPOST(string $clave, string $defecto = ''): string {
    if (isset($_POST[$clave]) && !is_array($_POST[$clave])) {
        return (string) $_POST[$clave];
    }
    return $defecto;
}
