<?php
// ============================================================
//  PRUEBAS — transporte de correo por archivo
//  Archivo: backend/tests/correo_test.php
//
//      php backend/tests/correo_test.php
//
//  Con MAIL_TRANSPORT=archivo (el valor por defecto cuando no hay
//  contraseña SMTP) cada correo debe quedar en backend/storage/mail/
//  como archivo .eml con cabeceras RFC 5322. Así se prueba todo el
//  pipeline sin depender de un servidor de correo.
//
//  Código de salida: 0 = todo OK, 1 = hay fallas.
// ============================================================

declare(strict_types=1);

putenv('MAIL_TRANSPORT=archivo');   // antes de cargar la configuración

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/backend/config/config.php';
require_once $raiz . '/backend/lib/mailer.php';

$fallas = 0;
$creados = [];

function falla(string $m): void {
    global $fallas;
    $fallas++;
    echo "  FALLA: $m\n";
}

/**
 * Devuelve el archivo .eml más reciente que no estaba en la lista
 * conocida. (filemtime tiene resolución de 1 segundo, así que no se
 * puede desempatar sólo por fecha cuando se generan varios correos
 * seguidos.)
 */
function correoNuevo(array $conocidos): ?string {
    $todos = glob(carpetaCorreos() . '/*.eml') ?: [];
    $nuevos = array_values(array_diff($todos, $conocidos));
    if (!$nuevos) {
        return null;
    }
    usort($nuevos, fn (string $a, string $b) => filemtime($b) <=> filemtime($a)
        ?: strcmp($b, $a));
    return $nuevos[0];
}

echo "· Transporte de correo (archivo)\n";

// 1. Transporte forzado a archivo
if (MAIL_TRANSPORT !== 'archivo') {
    falla('MAIL_TRANSPORT debería ser "archivo" en la prueba, es "' . MAIL_TRANSPORT . '"');
}

// 2. guardarCorreoEnArchivo escribe un .eml con cabeceras y cuerpo
$ruta = guardarCorreoEnArchivo('destino@ejemplo.com', 'Destino de Prueba',
    'Asunto de prueba', "Cuerpo del correo de prueba.\nLínea dos.\n",
    'autor@ejemplo.com', 'Autor Prueba');
if ($ruta !== true) {
    falla('guardarCorreoEnArchivo devolvió false');
} else {
    $ultimo = ultimoCorreoArchivo();
    if ($ultimo === null || !is_file($ultimo)) {
        falla('ultimoCorreoArchivo() no devuelve un archivo existente');
    } else {
        $creados[] = $ultimo;
        $texto = (string) file_get_contents($ultimo);
        // El asunto viaja codificado (RFC 2047): se comprueba en base64.
        foreach (['From: ', 'To: ', 'Subject: ', 'Reply-To: ',
                  base64_encode('Asunto de prueba'),
                  'Cuerpo del correo de prueba'] as $esperado) {
            if (!str_contains($texto, $esperado)) {
                falla('el .eml no contiene «' . $esperado . '»');
            }
        }
        echo "  ok: el .eml tiene cabeceras y cuerpo correctos\n";
    }
}

// 3. enviarCorreoContacto usa el mismo transporte
$ok = enviarCorreoContacto('Persona de Contacto', 'persona@ejemplo.com',
    'Consulta desde el formulario de contacto.');
if ($ok !== true) {
    falla('enviarCorreoContacto() devolvió false con transporte archivo');
} else {
    $ultimo = correoNuevo($creados);
    if ($ultimo === null || !is_file($ultimo)) {
        falla('enviarCorreoContacto no dejó archivo');
    } else {
        $creados[] = $ultimo;
        $texto = (string) file_get_contents($ultimo);
        // El asunto completo incluye el nombre del autor; se comprueba
        // el cuerpo y el remitente de respuesta.
        if (!str_contains($texto, 'formulario de contacto de TelecomWiki')) {
            falla('el correo de contacto no lleva el cuerpo esperado');
        }
        if (!str_contains($texto, base64_encode('Persona de Contacto'))) {
            falla('el correo de contacto no nombra al autor');
        }
        if (!str_contains($texto, 'persona@ejemplo.com')) {
            falla('el correo de contacto no responde al autor');
        }
        echo "  ok: enviarCorreoContacto genera el correo correctamente\n";
    }
}

// 4. La confirmación automática también (ENVIAR_CONFIRMACION_CONTACTO)
if (ENVIAR_CONFIRMACION_CONTACTO) {
    $ok = enviarConfirmacionContacto('Persona de Contacto', 'persona@ejemplo.com');
    if ($ok !== true) {
        falla('enviarConfirmacionContacto() devolvió false');
    } else {
        echo "  ok: confirmación automática generada\n";
        $u = correoNuevo($creados);
        if ($u) $creados[] = $u;
    }
}

// Limpieza: no dejar correos de prueba en el disco
foreach (array_unique($creados) as $f) {
    @unlink($f);
}

echo "\n" . ($fallas === 0 ? "Todo OK\n" : "$fallas falla(s)\n");
exit($fallas === 0 ? 0 : 1);
