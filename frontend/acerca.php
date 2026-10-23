<?php
// ============================================================
//  Quiénes somos — institución, roles y contacto
//  Archivo: frontend/acerca.php
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';
require_once BACKEND . '/lib/mailer.php';   // PHPMailer + SMTP (carga tolerante a fallos)

// ── Procesamiento del formulario de contacto ────────────────
$enviado = false;
$errores = [];
$valores = ['nombre' => '', 'email' => '', 'mensaje' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $valores['nombre']  = saneaPOST('nombre', 150);
    $valores['email']   = saneaPOST('email', 150);
    $valores['mensaje'] = saneaPOST('mensaje', 5000);
    $honeypot = saneaPOST('sitio_web', 100);   // campo trampa: debe llegar vacío

    // Trampa anti-spam: si el honeypot viene relleno es un bot.
    // Se responde como si hubiese salido bien, sin guardar nada,
    // para no delatar el filtro.
    if ($honeypot !== '') {
        $enviado = true;
    } elseif (contarEventosRecientes('contacto_enviado', null, CONTACTO_VENTANA_MIN)
              >= CONTACTO_MAX_POR_IP) {
        $errores[] = 'Enviaste varios mensajes en poco tiempo. '
                   . 'Esperá unos minutos antes de intentarlo de nuevo.';
    } else {
        $reglas = [
            'nombre'  => ['requerido' => true, 'max' => 150],
            'email'   => ['requerido' => true, 'email' => true, 'max' => 150],
            'mensaje' => ['requerido' => true, 'min' => 10, 'max' => 5000],
        ];
        $errores = validar($_POST, $reglas, [
            'nombre' => 'Nombre completo', 'email' => 'Correo electrónico', 'mensaje' => 'Mensaje',
        ]);

        if (!$errores) {
            try {
                $db = getDB();
                $db->prepare(
                    'INSERT INTO mensajes_contacto (nombre, email, mensaje, ip, enviado_ok)
                     VALUES (?, ?, ?, ?, 0)'
                )->execute([
                    $valores['nombre'], $valores['email'], $valores['mensaje'], ipCliente(),
                ]);
                $mensajeId = $db->lastInsertId();
            } catch (Throwable $e) {
                error_log('[acerca] No se pudo guardar el mensaje: ' . $e->getMessage());
                $errores[] = 'No se pudo procesar el mensaje en este momento. Intentá más tarde.';
                $mensajeId = null;
            }

            if ($mensajeId !== null) {
                logSeguridad('contacto_enviado', null, 'mensaje #' . $mensajeId);

                if (enviarCorreoContacto($valores['nombre'], $valores['email'], $valores['mensaje'])) {
                    getDB()->prepare('UPDATE mensajes_contacto SET enviado_ok = 1 WHERE id = ?')
                           ->execute([$mensajeId]);
                    enviarConfirmacionContacto($valores['nombre'], $valores['email']);
                }

                // El mensaje quedó guardado y visible en el panel: se confirma
                // al usuario aunque el SMTP no esté configurado todavía.
                $enviado = true;
                $valores = ['nombre' => '', 'email' => '', 'mensaje' => ''];
            }
        }
    }
}

$roles = [
    ['admin', 'Administrador', 'Control total del sistema: usuarios, categorías, proyectos, mensajes y configuración general.'],
    ['supervisor', 'Supervisor', 'Modera el contenido institucional: aprueba o rechaza las noticias antes de que se publiquen.'],
    ['editor', 'Editor', 'Crea y edita fichas de proyecto, artículos de la wiki, hitos de seguimiento y borradores de noticias.'],
    ['lector', 'Lector', 'Cuenta asignada al personal de Telecom Venezuela. Identifica al trabajador dentro del sistema con acceso de lectura.'],
];

$problems = [
    ['file', 'Conocimiento disperso', 'Documentos repartidos en correos y carpetas locales, sin orden ni versión.'],
    ['clock', 'Sin continuidad', 'Al rotar el personal, el conocimiento se iba con él y había que empezar de cero.'],
    ['shield', 'Sin estándar', 'Cada quien documentaba a su manera —o no documentaba— y no había forma de comparar.'],
    ['chart', 'Sin seguimiento', 'Los proyectos pausados o archivados no tenían registro de estado, responsable ni avance.'],
];

$pageTitle = 'Quiénes somos';
$descripcion = 'Misión, visión, roles y marco institucional de TelecomWiki, la plataforma de '
             . 'documentación técnica de Telecom Venezuela, C.A.';
$cssExtra = '';
abrirPagina();
?>

<div class="container container--prosa">

    <?= migaDePan([['Inicio' => 'index.php'], ['Quiénes somos' => null]]) ?>

    <section class="hero-institucional">
        <p class="hero-institucional__eyebrow">Telecom Venezuela, C.A. · Departamento de Desarrollo</p>
        <h1>¿Qué es <span class="destacado">TelecomWiki</span>?</h1>
        <p>Una plataforma para centralizar, estandarizar y compartir la documentación técnica de
           los proyectos de software recreativo y de telecomunicaciones del departamento de
           desarrollo. Abierta a todo el personal que quiera aprender, contribuir o documentar.</p>
    </section>

    <section class="panel">
        <h2 class="panel__titulo"><span class="panel__icono" aria-hidden="true">01</span>
            El problema que resolvemos</h2>
        <p class="panel__texto">Antes de TelecomWiki, la documentación técnica de los proyectos
            estaba dispersa en correos, carpetas locales y documentos sin formato estándar. Eso
            generaba pérdida de conocimiento, dificultad para dar continuidad y ningún punto de
            acceso común para los colaboradores nuevos.</p>

        <ul class="rejilla rejilla--4">
            <?php foreach ($problems as [$ico, $titulo, $texto]): ?>
            <li class="tarjeta-info">
                <span class="tarjeta-info__icono" aria-hidden="true"><?= icono($ico, 20) ?></span>
                <strong><?= e($titulo) ?></strong>
                <p><?= e($texto) ?></p>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="rejilla rejilla--2">
        <div class="panel">
            <h2 class="panel__titulo"><span class="panel__icono" aria-hidden="true">02</span> Misión</h2>
            <p class="panel__texto">Centralizar y preservar el conocimiento técnico y creativo de
                los proyectos de software recreativo de Telecom Venezuela, C.A., mediante una
                plataforma colaborativa construida sobre principios de soberanía tecnológica y
                software libre.</p>
        </div>
        <div class="panel">
            <h2 class="panel__titulo"><span class="panel__icono" aria-hidden="true">03</span> Visión</h2>
            <p class="panel__texto">Ser la plataforma de referencia para la documentación de
                proyectos de software recreativo nacional, fomentando una comunidad activa de
                colaboradores que contribuyan al fortalecimiento del conocimiento tecnológico
                venezolano.</p>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel__titulo"><span class="panel__icono" aria-hidden="true">04</span> Nuestros valores</h2>
        <ul class="rejilla rejilla--4">
            <li class="tarjeta-info">
                <span class="tarjeta-info__icono" aria-hidden="true"><?= icono('shield', 20) ?></span>
                <strong>Soberanía tecnológica</strong>
                <p>Desarrollado con software libre, alineado con la Ley de Infogobierno y el
                   Decreto 3.390 sobre software público.</p>
            </li>
            <li class="tarjeta-info">
                <span class="tarjeta-info__icono" aria-hidden="true"><?= icono('users', 20) ?></span>
                <strong>Colaboración abierta</strong>
                <p>El personal puede solicitar acceso para documentar sus proyectos y contribuir
                   con artículos técnicos.</p>
            </li>
            <li class="tarjeta-info">
                <span class="tarjeta-info__icono" aria-hidden="true"><?= icono('book', 20) ?></span>
                <strong>Gestión del conocimiento</strong>
                <p>La información se organiza, versiona y preserva: nada se pierde cuando alguien
                   se va del equipo.</p>
            </li>
            <li class="tarjeta-info">
                <span class="tarjeta-info__icono" aria-hidden="true"><?= icono('star', 20) ?></span>
                <strong>Impacto social</strong>
                <p>Proyectos orientados a la educación, la cultura y el desarrollo tecnológico
                   nacional.</p>
            </li>
        </ul>
    </section>

    <section class="panel">
        <h2 class="panel__titulo"><span class="panel__icono" aria-hidden="true">05</span> ¿Cómo funciona?</h2>
        <p class="panel__texto">Cualquier visitante puede explorar todo el contenido libremente,
            sin necesidad de cuenta. Las cuentas son para el personal de Telecom Venezuela: si
            trabajás en la institución, el administrador te asignará una cuenta. Si querés
            documentar un proyecto propio, escribinos por el formulario solicitando permisos de
            Editor.</p>

        <p class="panel__texto"><strong>Se aplica el principio de mínimo privilegio
            (NIST SP 800-53):</strong> cada rol recibe únicamente los permisos necesarios para
            su tarea, y la separación de funciones reduce el riesgo de errores en la publicación.</p>

        <ol class="lista-roles">
            <?php foreach ($roles as [$rol, $titulo, $texto]): ?>
            <li class="lista-roles__item lista-roles__item--<?= e($rol) ?>">
                <?= badgeRol($rol) ?>
                <div>
                    <strong><?= e($titulo) ?></strong>
                    <p><?= e($texto) ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="panel panel--oscuro">
        <h2 class="panel__titulo">La institución detrás</h2>
        <p class="panel__texto"><strong>Telecom Venezuela, C.A.</strong> es una empresa del Estado
            venezolano adscrita al Ministerio del Poder Popular para la Ciencia, Tecnología e
            Innovación, con sede en la Avenida Andrés Bello, Torre Fondo Común, Caracas. Su
            Departamento de Desarrollo es responsable del diseño, programación y validación de
            proyectos de infraestructura tecnológica y de software recreativo de carácter nacional.</p>

        <ul class="stats-institucion">
            <li><strong>2009</strong><span>Año de fundación</span></li>
            <li><strong>+15</strong><span>Años de desarrollo soberano</span></li>
            <li><strong>100 %</strong><span>Software libre</span></li>
        </ul>
    </section>

    <section class="panel" id="contacto">
        <h2 class="panel__titulo"><span class="panel__icono" aria-hidden="true">06</span> Contáctanos</h2>
        <p class="panel__texto">¿Tenés preguntas, sugerencias, o sos parte del equipo y querés que
            te demos acceso para documentar un proyecto? Contanos qué te gustaría subir y te
            responderemos con los siguientes pasos.</p>

<?php if ($enviado): ?>
        <div class="alert alert-success" role="status">
            Mensaje recibido. Te responderemos a la brevedad.
            <?php if (!ENVIAR_CONFIRMACION_CONTACTO): ?>
                Si dejaste tu correo, recibirás una copia de confirmación.
            <?php endif; ?>
        </div>
<?php else: ?>
        <?= pintarErrores($errores) ?>

        <form method="post" action="<?= e(url('acerca.php#contacto')) ?>" class="formulario-contacto">
            <?= campoCSRF() ?>

            <!-- Honeypot: oculto para las personas, visible para los bots -->
            <div class="campo-trampa" aria-hidden="true">
                <label for="sitio_web">Sitio web (dejar en blanco)</label>
                <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
            </div>

            <div class="rejilla rejilla--2">
                <?= campo('nombre', 'Nombre completo', 'text', [
                    'valor' => $valores['nombre'], 'requerido' => true, 'max' => 150,
                    'placeholder' => 'Tu nombre y apellido',
                ]) ?>
                <?= campo('email', 'Correo electrónico', 'email', [
                    'valor' => $valores['email'], 'requerido' => true, 'max' => 150,
                    'placeholder' => 'tucorreo@ejemplo.com',
                ]) ?>
            </div>

            <?= campo('mensaje', 'Mensaje', 'textarea', [
                'valor' => $valores['mensaje'], 'requerido' => true, 'max' => 5000,
                'filas' => 7, 'placeholder' => 'Contanos en qué podemos ayudarte…',
                'ayuda' => 'Entre 10 y 5000 caracteres.',
            ]) ?>

            <button class="btn btn-primary btn-lg" type="submit">
                Enviar mensaje <?= icono('arrow', 16) ?>
            </button>
        </form>
<?php endif; ?>
    </section>

    <section class="proyecto-academico">
        <p>TelecomWiki — Proyecto Sociotecnológico · TSU Informática · UPTC «Mariscal Sucre»<br>
           PNF en Informática · Tutor: Alfredo Olivero</p>
    </section>

</div>

<?php cerrarPagina(); ?>
