<?php
// TelecomWiki — punto de entrada.
// Apache no permite redirigir desde .htaccess sin mod_rewrite, así que
// este archivo hace la salta a la carpeta pública.
header('Location: frontend/index.php', true, 302);
exit;
