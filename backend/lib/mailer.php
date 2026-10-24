<?php
// ============================================================
//  ENVÍO DE CORREO — PHPMailer + SMTP (o archivo en desarrollo)
//  Archivo: backend/lib/mailer.php
//  Módulo 7 — Contacto por correo
// ============================================================
//
//  Transportes (MAIL_TRANSPORT en backend/config/mail_config.php):
//
//    smtp    → envía por el servidor SMTP configurado (producción)
//    archivo → guarda cada correo como .eml en
//              backend/storage/mail/  (desarrollo local y pruebas:
//              no hace falta ningún servidor de correo)
//
//  Si el archivo vendor/autoload.php no existe, las funciones
//  devuelven false en vez de romper la página — el mensaje de
//  contacto igual queda guardado en la tabla mensajes_contacto
//  (ver acerca.php), así que no se pierde información.
// ============================================================

require_once __DIR__ . '/../config/mail_config.php';

$__phpmailer_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($__phpmailer_autoload)) {
    require_once $__phpmailer_autoload;
}

/** Carpeta donde el transporte "archivo" deja los .eml. */
function carpetaCorreos(): string {
    $ruta = BACKEND . '/storage/mail';
    if (!is_dir($ruta)) {
        @mkdir($ruta, 0775, true);
    }
    return $ruta;
}

/** Último archivo generado por el transporte "archivo" (para pruebas). */
function ultimoCorreoArchivo(): ?string {
    $archivos = glob(carpetaCorreos() . '/*.eml') ?: [];
    if (!$archivos) {
        return null;
    }
    usort($archivos, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
    return $archivos[0];
}

/**
 * Transporte de archivo: escribe el correo en disco con cabeceras
 * estándar RFC 5322, suficiente para abrirlo con cualquier cliente
 * y para probar el pipeline completo sin servidor SMTP.
 */
function guardarCorreoEnArchivo(string $para, string $paraNombre, string $asunto,
                               string $cuerpo, ?string $replyTo = null,
                               ?string $replyToNombre = null): bool {
    $nombre = 'correo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.eml';
    $ruta   = carpetaCorreos() . '/' . $nombre;

    $cabeceras  = 'From: =?UTF-8?B?' . base64_encode(CONTACT_FROM_NAME) . '?= <'
                . CONTACT_FROM_EMAIL . ">\r\n";
    $cabeceras .= 'To: =?UTF-8?B?' . base64_encode($paraNombre) . '?= <' . $para . ">\r\n";
    if ($replyTo !== null) {
        $cabeceras .= 'Reply-To: =?UTF-8?B?' . base64_encode((string) $replyToNombre)
                    . '?= <' . $replyTo . ">\r\n";
    }
    $cabeceras .= 'Subject: =?UTF-8?B?' . base64_encode($asunto) . "?=\r\n";
    $cabeceras .= 'Date: ' . date('r') . "\r\n";
    $cabeceras .= "MIME-Version: 1.0\r\n";
    $cabeceras .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $cabeceras .= "Content-Transfer-Encoding: 8bit\r\n";
    $cabeceras .= "X-Mailer: TelecomWiki (transporte archivo)\r\n\r\n";

    $ok = @file_put_contents($ruta, $cabeceras . $cuerpo) !== false;
    if (!$ok) {
        error_log('[mailer.php] No se pudo escribir el correo en ' . $ruta);
    }
    return $ok;
}

/**
 * Envío común: usa el transporte configurado (smtp o archivo).
 *
 * @return bool true si el transporte confirmó la entrega
 */
function enviarMail(string $para, string $paraNombre, string $asunto, string $cuerpo,
                    ?string $replyTo = null, ?string $replyToNombre = null): bool {
    if (MAIL_TRANSPORT === 'archivo') {
        return guardarCorreoEnArchivo($para, $paraNombre, $asunto, $cuerpo,
                                      $replyTo, $replyToNombre);
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log('[mailer.php] PHPMailer no está instalado (falta vendor/autoload.php). '
                . 'Ejecuta: composer require phpmailer/phpmailer');
        return false;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(CONTACT_FROM_EMAIL, CONTACT_FROM_NAME);
        $mail->addAddress($para, $paraNombre);
        if ($replyTo !== null) {
            $mail->addReplyTo($replyTo, (string) $replyToNombre);
        }

        $mail->Subject = $asunto;
        $mail->isHTML(false);
        $mail->Body = $cuerpo;

        $mail->send();
        return true;

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('[mailer.php] Error enviando correo: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envía el mensaje del formulario de contacto al correo institucional
 * configurado en CONTACT_TO_EMAIL, con copia de respuesta al autor.
 *
 * @return bool true si el transporte confirmó el envío, false si falló
 */
function enviarCorreoContacto(string $nombre, string $emailRemitente, string $mensaje): bool {
    $cuerpo = "Nuevo mensaje desde el formulario de contacto de TelecomWiki.\n\n"
            . "Nombre: {$nombre}\n"
            . "Correo: {$emailRemitente}\n\n"
            . "Mensaje:\n{$mensaje}";

    return enviarMail(CONTACT_TO_EMAIL, CONTACT_TO_NAME,
        'Mensaje desde TelecomWiki — ' . $nombre, $cuerpo,
        $emailRemitente, $nombre);
}

/**
 * Envía un correo de confirmación automático a quien escribió el
 * formulario de contacto. Es opcional (ver ENVIAR_CONFIRMACION_CONTACTO
 * en backend/config/mail_config.php) y su fallo nunca debe bloquear el
 * flujo principal: se ejecuta después de guardar el mensaje.
 */
function enviarConfirmacionContacto(string $nombre, string $emailDestino): bool {
    if (!ENVIAR_CONFIRMACION_CONTACTO) {
        return false;
    }

    $cuerpo = "Hola {$nombre},\n\n"
            . "Gracias por escribirnos a través de TelecomWiki. Hemos recibido tu mensaje "
            . "y el equipo del Departamento de Desarrollo de Telecom Venezuela, C.A. te "
            . "responderá a la brevedad posible.\n\n"
            . "Este es un correo automático, no es necesario que lo respondas.\n\n"
            . "— TelecomWiki";

    return enviarMail($emailDestino, $nombre,
        'Hemos recibido tu mensaje — TelecomWiki', $cuerpo);
}
