<?php
// ============================================================
//  Plantilla: pie de página común a todas las pantallas
//  Archivo: frontend/partials/footer.php
// ============================================================

require_once dirname(__DIR__, 2) . '/backend/lib/bootstrap.php';

$anio = date('Y');
?>
</main>

<footer class="site-footer">
    <div class="footer-grid container">

        <div class="footer-brand">
            <span class="logo-icon" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H10l1.6 2H18a1.5 1.5 0 0 1 1.5 1.5V17A1.5 1.5 0 0 1 18 18.5H5.5A1.5 1.5 0 0 1 4 17z"/>
                    <path d="M8 11h8M8 14h5"/>
                </svg>
            </span>
            <p class="footer-brand__nombre"><?= e(SITE_NAME) ?></p>
            <p>Sistema de gestión y estandarización de la documentación técnica de los proyectos del
               Departamento de Desarrollo.</p>
        </div>

        <nav class="footer-col" aria-labelledby="foot-doc">
            <h2 id="foot-doc">Documentación</h2>
            <ul>
                <li><a href="<?= e(url('proyectos.php')) ?>">Proyectos</a></li>
                <li><a href="<?= e(url('wiki.php')) ?>">Wiki técnica</a></li>
                <li><a href="<?= e(url('noticias.php')) ?>">Noticias</a></li>
            </ul>
        </nav>

        <nav class="footer-col" aria-labelledby="foot-inst">
            <h2 id="foot-inst">Institución</h2>
            <ul>
                <li><a href="<?= e(url('acerca.php')) ?>">Quiénes somos</a></li>
                <li><a href="https://mincyt.gob.ve/" target="_blank" rel="noopener noreferrer">MINCYT</a></li>
                <li><a href="https://telecom.gob.ve" target="_blank" rel="noopener noreferrer">Telecom Venezuela</a></li>
            </ul>
        </nav>

        <div class="footer-col">
            
        </div>

    </div>

    <div class="footer-bottom">
        <div class="container">
            <p>© <?= $anio ?> Telecom Venezuela, C.A. — Av. Andrés Bello, Torre Fondo Común, Caracas.
               Todos los derechos reservados.</p>
        </div>
    </div>
</footer>

<script src="<?= e(url('js/main.js')) ?>" nonce="<?= e($GLOBALS['CSP_NONCE'] ?? '') ?>"></script>
<?php
// $jsExtra admite una lista separada por comas: 'articulo_form.js, editor.js'
$jsExtraListado = array_filter(array_map('trim', explode(',', (string) ($jsExtra ?? ''))));
foreach ($jsExtraListado as $jsArchivo):
    if (!preg_match('/^[a-z0-9_]+\.js$/', $jsArchivo)) { continue; }
?>
<script src="<?= e(url('js/' . $jsArchivo)) ?>" nonce="<?= e($GLOBALS['CSP_NONCE'] ?? '') ?>"></script>
<?php endforeach; ?>
</body>
</html>
