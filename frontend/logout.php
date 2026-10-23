<?php
// ============================================================
//  Cierre de sesión — sólo por POST con token CSRF
//  Archivo: frontend/logout.php
//
//  Antes era un enlace GET sin token, lo que permitía cerrar la
//  sesión de alguien con un simple <img src="…/logout.php">.
// ============================================================

require_once dirname(__DIR__) . '/backend/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // GET → volvemos al inicio sin tocar la sesión
    redirigir('index.php');
}

verifyCsrf();
logout();
