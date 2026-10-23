<?php
// ============================================================
//  Crear noticia
//  Archivo: frontend/noticias_crear.php
//
//  Regla de aprobación: el supervisor/administrador publica
//  directamente; el editor envía a la cola `pendiente`, que se
//  revisa en noticias_moderar.php.
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

requireLogin();
requierePermiso('noticia.crear');

$usuario     = currentUser();
$publicador  = puede('noticia.moderar');   // admin / supervisor
$errores     = [];
$adjuntosErr = [];
$exito       = false;

$valores = ['titulo' => '', 'categoria' => '', 'resumen' => '', 'contenido' => '', 'destacada' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $valores['titulo']    = saneaPOST('titulo', 300);
    $valores['categoria'] = saneaPOST('categoria', 100);
    $valores['resumen']   = saneaPOST('resumen', 600);
    $valores['contenido'] = saneaPOST('contenido', 60000);
    $valores['destacada'] = isset($_POST['destacada']) ? 1 : 0;

    $errores = validar($_POST, [
        'titulo'    => ['requerido' => true, 'min' => 5, 'max' => 300],
        'categoria' => ['requerido' => true, 'en' => array_keys(NOTICIA_CATEGORIAS)],
        'resumen'   => ['requerido' => true, 'min' => 10, 'max' => 600],
        'contenido' => ['requerido' => true, 'min' => 20, 'max' => 60000],
    ], [
        'titulo' => 'Título', 'categoria' => 'Categoría',
        'resumen' => 'Resumen', 'contenido' => 'Contenido',
    ]);

    // El contenido es HTML del usuario: se purifica ANTES de persistir,
    // de modo que en base de datos nunca haya marcado no permitido.
    $valores['contenido'] = sanearHTML($valores['contenido']);

    if (!$errores) {
        $estado = $publicador ? 'publicada' : 'pendiente';

        try {
            $db = getDB();
            $db->prepare(
                'INSERT INTO noticias
                    (titulo, slug, resumen, contenido, categoria, autor_id,
                     estado, destacada, aprobado_por, fecha_publicacion)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $valores['titulo'],
                slugUnico('noticias', 'slug', slugify($valores['titulo'])),
                $valores['resumen'],
                $valores['contenido'],
                $valores['categoria'],
                $usuario['id'],
                $estado,
                $valores['destacada'],
                $publicador ? $usuario['id'] : null,
                $publicador ? date('Y-m-d H:i:s') : null,
            ]);

            $idNoticia = (int) $db->lastInsertId();

            // Adjuntos opcionales (el archivo principal queda como portada)
            if (!empty($_FILES['archivos']['name'])) {
                subirAdjuntos('noticia', $idNoticia, $_FILES['archivos'],
                              $usuario['id'], $adjuntosErr);
            }

            if ($adjuntosErr) {
                // La noticia ya existe: se avisa pero no se descarta el texto.
                flash('warning', 'Noticia guardada, pero algunos adjuntos no se subieron: '
                    . implode(' ', $adjuntosErr));
            }

            redirigir(
                'noticias.php',
                'success',
                $publicador
                    ? 'Noticia publicada correctamente.'
                    : 'Noticia enviada. Quedará visible cuando un supervisor la apruebe.'
            );
        } catch (Throwable $e) {
            error_log('[noticias_crear] ' . $e->getMessage());
            $errores[] = 'No se pudo guardar la noticia. Intentá de nuevo.';
        }
    }
}

$pageTitle = 'Crear noticia';
$jsExtra   = 'editor.js';
abrirPagina();
?>

<div class="container container--form">

    <?= migaDePan([
        ['Inicio' => 'index.php'],
        ['Noticias' => 'noticias.php'],
        ['Crear noticia' => null],
    ]) ?>

    <div class="panel panel--form">
        <header class="panel__cabecera">
            <h1>Nueva noticia</h1>
            <p class="muted">
                <?php if ($publicador): ?>
                    Como publicador, tu noticia se publicará directamente.
                <?php else: ?>
                    Tu noticia quedará <strong>pendiente de aprobación</strong> hasta que un
                    supervisor o administrador la revise.
                <?php endif; ?>
            </p>
        </header>

        <?= pintarErrores($errores) ?>

        <form method="post" enctype="multipart/form-data" class="formulario"
              action="<?= e(url('noticias_crear.php')) ?>">
            <?= campoCSRF() ?>

            <?= campo('titulo', 'Título', 'text', [
                'valor' => $valores['titulo'], 'requerido' => true, 'min' => 5, 'max' => 300,
                'placeholder' => 'Título de la noticia…',
            ]) ?>

            <?= campo('categoria', 'Categoría', 'select', [
                'valor' => $valores['categoria'], 'requerido' => true,
                'opciones' => NOTICIA_CATEGORIAS,
                'vacio' => 'Elegí una categoría',
            ]) ?>

            <?= campo('resumen', 'Resumen', 'text', [
                'valor' => $valores['resumen'], 'requerido' => true, 'min' => 10, 'max' => 600,
                'placeholder' => 'Breve descripción que se verá en la lista…',
                'ayuda' => 'Entre 10 y 600 caracteres.',
            ]) ?>

            <?= campo('contenido', 'Contenido', 'textarea', [
                'valor' => $valores['contenido'], 'requerido' => true, 'min' => 20, 'max' => 60000,
                'filas' => 14, 'placeholder' => 'Escribí el cuerpo de la noticia…',
                'ayuda' => 'Se admiten títulos, listas, enlaces, imágenes y videos embebidos. '
                         . 'El HTML se limpia al guardar.',
                'class' => 'editor-ric',
            ]) ?>

            <?= campo('archivos', 'Imágenes, videos o documentos', 'file', [
                'multiple' => true, 'max' => 6,
                'ayuda' => 'Hasta 6 archivos. Se valida el contenido real de cada uno '
                         . '(MIME) antes de aceptarlo.',
            ]) ?>

            <label class="casilla">
                <input type="checkbox" name="destacada" value="1"
                    <?= $valores['destacada'] ? 'checked' : '' ?>>
                <span>Marcar como destacada (aparece primero en la portada)</span>
            </label>

            <div class="acciones-formulario">
                <button class="btn btn-primary btn-lg" type="submit">
                    <?= $publicador ? 'Publicar noticia' : 'Enviar para aprobación' ?>
                </button>
                <a class="btn btn-ghost btn-lg" href="<?= e(url('noticias.php')) ?>">Cancelar</a>
            </div>
        </form>
    </div>

</div>

<?php cerrarPagina(); ?>
