# Guía del desarrollador

Cómo trabajar sobre el código: convenciones, recetas paso a paso y
checklist previo al commit. Antes de leer ésta, tené a mano
[`ARQUITECTURA.md`](ARQUITECTURA.md) (capas y módulos) y
[`CODIGO.md`](CODIGO.md) (catálogo de helpers).

## 1. Punto de entrada y ciclo de vida de una pantalla

Toda pantalla empieza igual:

```php
<?php require_once __DIR__ . '/../backend/lib/bootstrap.php';
```

`bootstrap.php` carga configuración, sesión, todas las librerías de
`backend/lib/` y aplica el modo mantenimiento. Después, el patrón
estándar es:

```php
// 1) Guardas (antes que nada)
requierePermiso('articulo.crear');

// 2) Entrada saneada
$q    = saneaGET('q');
$datos = ['titulo' => saneaPOST('titulo')];

// 3) POST: validar → actuar → redirigir (Post/Redirect/Get)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $errores = validar($_POST, [...]);
    if (!$errores) { /* SQL con prepare() */ redirigir('admin/articulos.php', 'ok', 'Guardado'); }
}

// 4) Lectura para la vista
$pagina = paginar(...);

// 5) Render
$pagina = 'Título'; $cssExtra = 'admin.css'; $jsExtra = 'editor.js';
abrirPagina();
// … HTML con e($valor) en todo dato dinámico …
cerrarPagina();
```

Flash + redirección: `redirigir($ruta, 'ok'|'info'|'error', 'mensaje')`
y en la destino `obtenerFlash()` ya lo imprime el layout.

## 2. Convenciones (obligatorias)

- **CSP estricta**: cero `style="…"`, cero `onclick="…"`, cero
  `<style>`/`<script>` inline. El JS se recarga desde `frontend/js/`
  con nonce; los datos para JS van en `<input type="hidden">`.
- **Escapar toda salida**: `e($dato)` en cualquier interpolación de
  usuario dentro del HTML.
- **SQL**: siempre `prepare()` + `execute()`. `LIKE` con
  `escaparLike()` y **sin** cláusula `ESCAPE`. `LIMIT`/`OFFSET`
  **sólo** desde `paginar()` (interpolación de enteros saneados).
- **Permisos por capacidad, nunca por rol**:
  `requierePermiso('x')` / `puede('x')`. Agregar la capacidad al
  array `CAPACIDADES` de `permisos.php` (y a la tabla de LEEME §7).
- **Nombres**: funciones `camelCase`, clases CSS `kebab-case` con
  bloque (`tarjeta-proyecto`), constantes `UPPER_SNAKE`. Slugs con
  `slugify()` + `slugUnico()`.
- **Sin `?>` de cierre** en archivos PHP. Cuidado: un literal `?>`
  dentro de un comentario `//` cierra el modo PHP.
- **Sin dependencias nuevas** (sin composer require, sin npm, sin
  librerías de frontend): el proyecto debe correr copiando la carpeta.
- Comentarios en español, sólo donde aportan el «por qué».

## 3. Recetas

### 3.1 Agregar una pantalla nueva

1. Crear `frontend/<lugar>/<pantalla>.php` con el patrón de §1.
2. Si es del panel: `frontend/admin/…` y empezar con
   `requierePermiso('capacidad')`.
3. Enlazar con `url('ruta.php?…')`; si es POST, `campoCSRF()`.
4. Si necesita CSS propio: clase en `frontend/css/pages.css`
   (o `admin.css`) — el test de cobertura falla si usás una clase
   inexistente.
5. Correr `php backend/tests/estructura_test.php` (verifica sintaxis,
   CSRF, `<h1>`, alt, enlaces).

### 3.2 Agregar una capacidad (permiso)

1. Agregar `'dominio.accion' => 'rol_minimo'` en `CAPACIDADES`
   (`backend/lib/permisos.php`).
2. Usar `requierePermiso()` / `puede()` en la pantalla.
3. Documentar la fila en la tabla de `LEEME.md` §7 y, si afecta el
   modelo, en `SEGURIDAD.md`.

### 3.3 Cambiar el esquema de la base

1. **Nunca** editar `database.sql` en producción: crear
   `backend/database/migraciones/NNNN_descripcion.sql` con DDL
   idempotente (`IF NOT EXISTS`, `ALTER` tolerante).
2. Aplicar con `php backend/bin/migrar.php --aplicar` (registra archivo
   y SHA-256 en la tabla `migraciones`).
3. Reflejar el cambio en `backend/database/database.sql` (para
   instalaciones nuevas) y en `MODELO-DE-DATOS.md` + diagrama ER de
   `DIAGRAMAS.md` si cambian tablas o relaciones.
4. Pasar la suite: `php backend/tests/run.php`.

### 3.4 Agregar o modificar una tabla

- PK `id INT UNSIGNED AUTO_INCREMENT`, `slug VARCHAR` único si tiene
  URL, fechas `DATETIME DEFAULT CURRENT_TIMESTAMP`, charset `utf8mb4`.
- `ON DELETE CASCADE` sólo cuando el hijo no existe sin el padre
  (artículos→proyecto); `SET NULL` para referencias a `usuarios`;
  nada de FK hacia `media`/`eventos_seguridad` (polimórfico/auditoría).
- Agregar índices para cada columna de filtro frecuente y documentar
  la tabla nueva en `MODELO-DE-DATOS.md`.

### 3.5 Subir archivos desde un formulario nuevo

```php
// vista: enctype multipart + campo de archivo + campoCSRF()
$errores = subirAdjuntos('noticia', $id, $_FILES['archivos'] ?? []);
// al eliminar la entidad:
eliminarMediaDe('noticia', $id);   // media no tiene FK
```

`normalizarFiles()` unifica archivo único y múltiple; la validación es
por MIME real (`finfo`), no por extensión.

### 3.6 Agregar una prueba

1. Crear `backend/tests/<nombre>_test.php` con el molde de
   [`PRUEBAS.md`](PRUEBAS.md): `$fallas`, `falla()`, salida
   `"Todo OK"`, `exit(0|1)`.
2. `run.php` la detecta sola por el glob `*_test.php`.
3. Si necesita base/servidor, degradar a *omitida con OK* cuando falte
   el requisito (como hace `http_test.php`).

### 3.7 Cambiar el correo

`backend/lib/mailer.php` + `backend/config/mail_config.php`: SMTP sólo
si existe `SMTP_PASS`; si no, escribe `.eml` en `backend/storage/mail/`.
Probar sin servidor: `MAIL_TRANSPORT=archivo php backend/tests/correo_test.php`.

## 4. Flujo de trabajo con git

```bash
git status                  # antes de todo
git add <archivos>          # nunca add -a a ciegas
git diff --cached           # revisar
git commit -m "tipo: qué cambia"
git push origin main
```

- Un commit = una idea (feat/fix/docs/test separados).
- **Antes de commitear**: `php backend/tests/run.php` en verde.
- No commitear: `backend/storage/{mail,respaldos,uploads}` (está en
  `.gitignore`), `.env`, `node_modules`, ni credenciales nuevas.

## 5. Checklist antes de dar por terminada una tarea

- [ ] `php backend/tests/run.php` → 4 suites, 0 fallas.
- [ ] La pantalla nueva funciona con sesión **y** redirige sin sesión.
- [ ] Todo POST tiene `verifyCsrf()` + `campoCSRF()`.
- [ ] Permisos por capacidad (`puede()`), no por nombre de rol.
- [ ] Salida escapada con `e()`; HTML rico pasa por `sanearHTML()`.
- [ ] Sin estilos/handlers inline (CSP).
- [ ] Si cambió la BD: migración + `database.sql` + `MODELO-DE-DATOS.md`.
- [ ] Si cambió el comportamiento: `CAMBIOS.md` con fecha.
- [ ] Enlaces con `url()` apuntan a archivos que existen.

## 6. Dónde mirar primero

| Sospecha | Archivo |
|---|---|
| «No tengo permiso» | `backend/lib/permisos.php` (`CAPACIDADES`) |
| Algo del flujo HTTP | `backend/lib/bootstrap.php` |
| Fallo de conexión/SQL | `backend/config/db.php`, `backend/lib/*.php` |
| XSS / HTML que desaparece | `backend/lib/sanitize.php` |
| Subida rechazada | `backend/lib/upload.php` + `UPLOAD_MIME` en `config.php` |
| CSP bloquea algo | `backend/lib/security.php` |
| Error 503 permanente | `config_sitio.mantenimiento` + `config_sitio.php` |
| URL que no existe | `frontend/` vs `url()`; en dev, `dev-router.php` |
