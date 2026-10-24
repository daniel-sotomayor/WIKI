<?php
// ============================================================
//  CONFIGURACIÓN DE CORREO (SMTP / PHPMailer)
//  Archivo: backend/config/mail_config.php
//  Módulo 7 — Contacto por correo
//
//  IMPORTANTE — SEGURIDAD:
//  Nunca escribas la contraseña o "contraseña de aplicación" del
//  correo directamente en este archivo. Definila como variable de
//  entorno del servidor (SMTP_PASS=...). Este archivo NO debe
//  contener credenciales reales, especialmente si el proyecto se
//  llega a compartir, subir a un repositorio o entregar a un
//  tercero.
//
//  En XAMPP local, podés definir variables de entorno agregando
//  líneas "SetEnv" en tu httpd-vhosts.conf, o simplemente
//  reemplazando temporalmente los getenv(...) de abajo por tus
//  valores reales SOLO en tu máquina de desarrollo — pero jamás
//  subas ese archivo así a ningún lado.
// ============================================================

define('SMTP_HOST',   getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT',   (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');   // 'tls' o 'ssl'
define('SMTP_USER',   getenv('SMTP_USER') ?: 'correo@ejemplo.com');
define('SMTP_PASS',   getenv('SMTP_PASS') ?: '');        // contraseña de aplicación — SOLO por variable de entorno

// Transporte de correo:
//   'smtp'    → envía por el servidor SMTP (necesita SMTP_PASS)
//   'archivo' → guarda cada correo en backend/storage/mail/*.eml
//               (ideal en XAMPP local y en las pruebas automatizadas)
// Si no se define nada, se elige 'archivo' cuando no hay contraseña.
define('MAIL_TRANSPORT', getenv('MAIL_TRANSPORT') ?: (SMTP_PASS !== '' ? 'smtp' : 'archivo'));

define('CONTACT_FROM_EMAIL', SMTP_USER);
define('CONTACT_FROM_NAME',  'TelecomWiki');

// Correo institucional que recibe los mensajes del formulario de contacto
define('CONTACT_TO_EMAIL', getenv('CONTACT_TO_EMAIL') ?: SMTP_USER);
define('CONTACT_TO_NAME',  'Departamento de Desarrollo — Telecom Venezuela');

// Si está en true, además del correo al departamento se envía un
// correo de confirmación automático a la persona que escribió.
define('ENVIAR_CONFIRMACION_CONTACTO', true);
