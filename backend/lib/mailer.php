<?php
// ============================================================
//  ENVÍO DE CORREO — PHPMailer + SMTP
//  Archivo: backend/lib/mailer.php
//  Módulo 7 — Contacto por correo
// ============================================================
//
//  Requiere PHPMailer instalado vía Composer:
//
//      composer require phpmailer/phpmailer
//
//  Esto crea la carpeta vendor/ en la raíz del proyecto con
//  vendor/autoload.php. Si esa carpeta no existe todavía, las
//  funciones de este archivo devuelven false en vez de romper la
//  página — el mensaje de contacto igual queda guardado en la
//  tabla mensajes_contacto (ver acerca.php), así que no se pierde
//  información aunque el correo no se haya podido enviar.
// ============================================================

require_once __DIR__ . '/../config/mail_config.php';

$__phpmailer_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($__phpmailer_autoload)) {
    require_once $__phpmailer_autoload;
}

/**
 * Envía el mensaje del formulario de contacto al correo
 * institucional configurado en CONTACT_TO_EMAIL, vía SMTP.
 *
 * @return bool true si PHPMailer confirmó el envío, false si falló
 *              o si PHPMailer no está instalado.
 */
function enviarCorreoContacto(string $nombre, string $emailRemitente, string $mensaje): bool {
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log('[mailer.php] PHPMailer no está instalado (falta vendor/autoload.php). Ejecuta: composer require phpmailer/phpmailer');
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
        $mail->addAddress(CONTACT_TO_EMAIL, CONTACT_TO_NAME);
        $mail->addReplyTo($emailRemitente, $nombre);

        $mail->Subject = 'Mensaje desde TelecomWiki — ' . $nombre;
        $mail->isHTML(false);
        $mail->Body = "Nuevo mensaje desde el formulario de contacto de TelecomWiki.\n\n"
                    . "Nombre: {$nombre}\n"
                    . "Correo: {$emailRemitente}\n\n"
                    . "Mensaje:\n{$mensaje}";

        $mail->send();
        return true;

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('[mailer.php] Error enviando correo de contacto: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envía un correo de confirmación automático a quien escribió el
 * formulario de contacto. Es opcional (ver ENVIAR_CONFIRMACION_CONTACTO
 * en backend/config/mail_config.php) y su fallo nunca debe bloquear el
 * flujo principal: se ejecuta después de guardar el mensaje.
 */
function enviarConfirmacionContacto(string $nombre, string $emailDestino): bool {
    if (!ENVIAR_CONFIRMACION_CONTACTO) return false;
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) return false;

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
        $mail->addAddress($emailDestino, $nombre);

        $mail->Subject = 'Hemos recibido tu mensaje — TelecomWiki';
        $mail->isHTML(false);
        $mail->Body = "Hola {$nombre},\n\n"
                    . "Gracias por escribirnos a través de TelecomWiki. Hemos recibido tu mensaje "
                    . "y el equipo del Departamento de Desarrollo de Telecom Venezuela, C.A. te "
                    . "responderá a la brevedad posible.\n\n"
                    . "Este es un correo automático, no es necesario que lo respondas.\n\n"
                    . "— TelecomWiki";

        $mail->send();
        return true;

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('[mailer.php] Error enviando confirmación de contacto: ' . $mail->ErrorInfo);
        return false;
    }
}
