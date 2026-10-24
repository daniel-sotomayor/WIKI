# Pruebas

Corren con el PHP del sistema (no requieren framework ni composer para
ejecutarse; el vendor de `polyfills` sólo aporta funciones faltantes).

## Cómo correr todo

```bash
cd telecom_wiki
php backend/tests/run.php
```

El ejecutor corre todas las suites `*_test.php` en orden alfabético y
devuelve código de salida **0** si todo pasó (útil para CI o como paso
previo a una entrega).

## Suites

| Suite | Qué cubre | Requisitos |
|---|---|---|
| `correo_test.php` | Transporte de correo por archivo: `.eml` con cabeceras, contacto y confirmación | Ninguno |
| `estructura_test.php` | Estática de todo el código: sintaxis PHP, CSP (sin estilos/handlers inline), cobertura de clases CSS, referencias CSS/JS, archivos clave, accesibilidad (`alt`, `<h1>`), CSRF en todo POST, enlaces `url()` existentes | Ninguno |
| `http_test.php` | Funcional end-to-end: levanta `php -S` + `dev-router.php`, recorre públicas, panel, login (bien/mal), CSRF 403, creación de noticia saneada, subida de imagen + rechazo de `.php`, entrega por `media.php`, logout y mantenimiento 503. Limpia BD y disco | curl + base de datos importada |
| `sanitize_test.php` | `sanearHTML`/`filtrarDOM`/`filtrarSinDOM`: 16 ataques XSS + 7 casos legítimos | Extensión DOM (se omite con aviso si falta) |

Cada suite también se puede correr sola:

```bash
php backend/tests/sanitize_test.php
php backend/tests/http_test.php
```

`http_test.php` usa las credenciales del entorno (`DB_USER`, …) con
los mismos valores por defecto que `dev-server.sh`
(`telecom_wiki`/`telecom2026`); en XAMPP se puede correr con
`DB_USER=root DB_PASS= php backend/tests/http_test.php`. Si no hay
base de datos o curl, se **omite con OK** (no cuenta como falla).

## Cómo está escrito un test

Las suites son PHP puro con la misma estructura:

```php
$fallas = 0;
function falla(string $m): void { /* imprime y suma */ }
// … asserts manuales con if …
echo $fallas === 0 ? "Todo OK\n" : "$fallas falla(s)\n";
exit($fallas === 0 ? 0 : 1);
```

Las funciones de `backend/lib/` se prueban importando sólo los archivos
necesarios (sin bootstrap completo cuando no se necesita sesión):

```php
require_once dirname(__DIR__) . '/backend/config/config.php';
require_once dirname(__DIR__) . '/backend/lib/sanitize.php';
```

## Qué se prueba y qué no

- **Sí**: helpers puros, saneamiento, estructura del código y los
  flujos HTTP críticos (auth, CSRF, subidas, moderación, mantenimiento).
- **No**: rendimiento, compatibilidad entre navegadores y el detalle
  visual del CSS (se audita a mano con las guías de
  [`DISENO.md`](DISENO.md)).

## Reglas para mantener la suite verde

1. Toda clase CSS usada en el HTML debe existir en `frontend/css/`.
2. Todo `<form method="post">` debe llevar `campoCSRF()` (o el nombre
   `csrf_token` en un tramo cercano).
3. Todo `<img>` necesita `alt`; toda pantalla con `abrirPagina()` necesita `<h1>`.
4. Nada de estilos ni handlers inline (CSP estricta).
5. Si agregás una pantalla nueva con `url('ruta.php')`, el archivo debe existir.
