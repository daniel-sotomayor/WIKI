# Arquitectura del sistema

TelecomWiki es una aplicación PHP "clásica" (sin framework): cada
pantalla es un archivo `.php` bajo `frontend/` que incluye un
bootstrap común, llama a helpers y renderiza HTML con vistas
parciales. No hay build, no hay dependencias de framework: se copia
la carpeta y funciona sobre Apache (XAMPP) o el servidor de desarrollo
de PHP.

## Capas

```
frontend/*.php        →  Pantallas (entry points, una por URL)
frontend/admin/*.php  →  Pantallas del panel
frontend/partials/    →  header/footer reutilizables
backend/lib/          →  Capa de helpers (toda la lógica reutilizable)
backend/config/       →  Constantes y credenciales (config, db, mail)
backend/database/     →  Esquema SQL + migraciones versionadas
backend/storage/      →  Subidas (uploads/) y correos en archivo (mail/)
```

Reglas de la arquitectura:

1. **Toda la lógica vive en `backend/lib/`.** Las pantallas sólo
   orquestan: leer entrada (`saneaGET`/`saneaPOST`), validar, llamar a
   helpers, renderizar. Si dos pantallas necesitan lo mismo, es una
   función en `lib/`.
2. **Una única puerta de entrada por petición**: `backend/lib/bootstrap.php`.
   Carga configuración, sesión, permisos, librerías y aplica el modo
   mantenimiento. Ninguna pantalla arranca sin él.
3. **SQL con PDO preparado.** Los `LIKE` usan `escaparLike()` sin cláusula
   `ESCAPE`; los `LIMIT`/`OFFSET` se interpolan sólo desde `paginar()`
   (enteros ya saneados).
4. **Salida siempre escapada** con `e()`; el HTML de contenido rico se
   sanea con `sanearHTML()` (DOM, allowlist de etiquetas).

## Flujo de una petición

```
Apache / php -S
   └─ frontend/<pantalla>.php
        ├─ require bootstrap.php
        │    ├─ config.php (constantes, modo debug)
        │    ├─ db.php (conexión PDO perezosa: getDB())
        │    ├─ librerías (auth, security, helpers, render, …)
        │    ├─ sesión + cookie segura
        │    └─ modo mantenimiento → 503 (salvo login/logout/media)
        ├─ saneaGET/saneaPOST → CSRF si es POST (verifyCsrf)
        ├─ requireLogin() / requierePermiso('capacidad')
        ├─ lógica (helpers de lib/) → SQL → redirigir() con flash
        └─ abrirPagina('Título') → partials/header.php
             … contenido …
             partials/footer.php → cerrarPagina()
```

Errores de permiso (`accesoDenegado`, 403) y de ruta (`paginaNoEncontrada`,
404) se manejan desde helpers y terminan la petición.

## Módulos (`backend/lib/`)

| Módulo | Responsabilidad |
|---|---|
| `bootstrap.php` | Cableado de todo + gate de mantenimiento |
| `config_sitio.php` | `config_sitio` (mantenimiento, correo, etc.) con caché |
| `db.php` | `getDB()`: PDO MySQL perezoso con manejo de error |
| `auth.php` | Login/logout, sesión, roles, `requireLogin`, `destruirSesion` |
| `permisos.php` | Matriz única `CAPACIDADES` y `puede()`/`requierePermiso()` |
| `security.php` | CSRF, flash, `redirigir`, `logSeguridad`, intentos de login |
| `sanitize.php` | `sanearHTML` (DOM) y `filtrarSinDOM` (fallback) |
| `validate.php` | `saneaGET`/`saneaPOST`, `validar()`, `escaparLike`, `enLista` |
| `upload.php` | Subida, validación MIME real, `media` polimórfico |
| `mailer.php` | SMTP si hay credenciales; si no, `.eml` en `storage/mail/` |
| `paginate.php` | `paginar()` + `htmlPaginacion()` |
| `render.php` | Vistas: tarjetas, badges, campos de formulario, galería |
| `helpers.php` | `e`, `slugify`, `url`, fechas, iconos SVG inline |
| `tags.php` | Tags N:M de artículos (`sincronizarTags`) |
| `plantillas_articulos.php` | Plantillas HTML de tipos de artículo |

## Modelo de datos (resumen)

14 tablas + 4 vistas. Entidades principales: `usuarios` (autoría y
permisos), `categorias`→`proyectos`, `articulos` (+`historial_versiones`,
`tags`/`articulo_tags`, `hitos`), `noticias` (flujo de moderación),
`media` (polimórfico sobre proyectos/articulos/noticias),
`mensajes_contacto`, `eventos_seguridad`, `migraciones`,
`config_sitio`. Detalle completo en [`MODELO-DE-DATOS.md`](MODELO-DE-DATOS.md)
y diagramas en [`DIAGRAMAS.md`](DIAGRAMAS.md).

`media`, `eventos_seguridad` y `historial_versiones` **no tienen FK** a
entidades por diseño (polimorfismo y auditoría): al borrar una entidad
hay que llamar `eliminarMediaDe()` a mano.

## Roles y capacidades

Roles jerárquicos: `lector`(1) < `editor`(2) < `supervisor`(3) < `admin`(4).
La matriz `CAPACIDADES` en `permisos.php` es la única fuente de verdad;
`requierePermiso()` se invoca al inicio de cada pantalla del panel y en
los POST sensibles. Un editor sólo ve/edita sus propios registros
(`puedeEditarPropio`).

## Seguridad (resumen)

Cabecera CSP estricta (`script-src 'self'` + nonce, `style-src 'self'`,
sin `unsafe-inline`): por eso **estilos y handlers inline están prohibidos**
en todo el HTML y los datos para JS viajan en `<input type="hidden">`.
Además: CSRF en todo POST, cookies `HttpOnly`+`SameSite`, contraseñas
`password_hash`, MIME real verificado en subidas, `.htaccess` que bloquea
`backend/`, `storage/` y ejecución de PHP en `uploads/`. El modelo
completo está en [`SEGURIDAD.md`](SEGURIDAD.md).

## Decisiones de diseño

- **Sin framework**: el proyecto es académico y debe correr en XAMPP
  copiando archivos; un framework impondría su curva y su árbol de
  dependencias.
- **Helpers con firmas puras y testeables**: la suite corre funciones
  de `lib/` directamente, sin servidor.
- **`media` polimórfico sin FK**: los adjuntos pueden colgar de
  proyectos, artículos y noticias sin tablas puente; el borrado
  explícito evita huérfanos.
- **Correo por archivo**: sin servidor SMTP en desarrollo, cada correo
  queda como `.eml` auditable; en producción se activa SMTP con
  variables de entorno.
- **Mantenimiento configurable en BD** (`config_sitio`): se activa sin
  tocar archivos y con controlador de acceso al diagnóstico del panel.
