# TelecomWiki — Historial de desarrollo

Registro de lo que se hizo al reconstruir el sistema, para revisión del
tutor y para que cualquiera pueda retomar el trabajo. La documentación de
*uso* está en [`LEEME.md`](LEEME.md) y la de *seguridad* en
[`SEGURIDAD.md`](SEGURIDAD.md).

Código fuente de referencia (sin tocar): `telecom_wiki_postgresql2.0/`.
Código nuevo: `telecom_wiki/`.

---

## Decisiones tomadas con el cliente

| Tema | Decisión |
|---|---|
| Estructura | Reescritura profunda con una capa de helpers (`backend/lib/`), no parches sobre el original |
| Base de datos | Se revirtió el port a PostgreSQL: se vuelve a **MySQL/MariaDB** |
| Archivos subidos | En el servidor, en `backend/storage/uploads/` (fuera del alcance web) |
| Editor | `<textarea>` + barra de herramientas propia en JS puro + saneado en el servidor |
| URLs | Planas con parámetros (`.php?slug=`), sin `mod_rewrite` |
| Carpeta | Todo en `telecom_wiki/`, dejando el original intacto |
| Dependencias | Ninguna de frontend; en backend sólo PHPMailer (ya vendorizado) |

---

## Fases

### Fase 0 — Estructura y seguridad base
Árbol de carpetas, `.htaccess` en raíz/`backend/`/`partials/`/`storage/`,
`index.php` que redirige al frontend, `dev-router.php` para el servidor de
desarrollo, `database.sql` (12 tablas + datos de ejemplo) y el núcleo:
`config.php`, `db.php`, `security.php` (CSP con nonce, sesión endurecida,
bitácora), `auth.php` (login con límite de intentos, CSRF, flash),
`permisos.php`, `helpers.php`, `validate.php`, `paginate.php`,
`sanitize.php`, `render.php`, `upload.php`, `tags.php`, `mailer.php`.

### Fase 1 — Núcleo
`bootstrap.php` con `abrirPagina()` / `cerrarPagina()` (las variables de
presentación se leen con `global`: un include dentro de una función no ve
el ámbito global de la pantalla).

### Fase 3 — Pantallas públicas
Portada, proyectos, proyecto, wiki, artículo, noticias, noticia, «quiénes
somos» (con formulario de contacto: honeypot + límite de envíos),
`login.php`, `logout.php` (POST + CSRF), `media.php` (validación de
nombre/MIME y soporte de `Range` para vídeo), crear noticia y moderar
noticias.

### Fase 4 — Panel de administración
Nueve pantallas: resumen, usuarios, formulario de usuario, proyecto,
hito, artículo, historial de versiones, noticia y mensajes de contacto.
Todas con `requierePermiso()` por **capacidad**, no por lista de roles.

### Fase 5 — Utilidades
`plantillas_articulos.php` (plantillas de contenido por tipo de artículo)
y `tags.php` (etiquetas normalizadas, sin duplicados).

### Fase 6 — Interfaz (CSS/JS)
Cinco hojas de estilo en vez de un monolito:

| Archivo | Contenido |
|---|---|
| `base.css` | variables, reset, tipografía, alertas, badges, rejillas, utilidades |
| `layout.css` | barra institucional, cabecera, navegación (incluye el menú móvil), pie |
| `components.css` | botones, formularios, tablas, tarjetas, paneles, galerías, editor |
| `pages.css` | portada, listados, fichas, detalle de contenido, «quiénes somos», acceso |
| `admin.css` | resumen, colas de moderación, hitos, versiones, bitácora |

JavaScript sin librerías: `main.js` (menú móvil, aparición en scroll,
confirmaciones `data-confirm-submit`, alertas que se cierran solas),
`editor.js` (barra de herramientas que sólo inserta etiquetas permitidas)
y `articulo_form.js` (guía de plantillas).

> La CSP no permite `unsafe-inline`, por lo que **no existen** handlers ni
> estilos en línea: los datos para JS viajan en `<input type="hidden">`
> y las clases de color/medida se usan en lugar de `style="…"`.

### Fase 7 — Documentación y verificación
- `docs/LEEME.md`, `docs/SEGURIDAD.md` y este archivo.
- Corredor de pruebas propio sin dependencias: `backend/tests/run.php`.
- `favicon.svg` propio (no había estáticos en el proyecto original).

### Fase 8 — PHP moderno, XAMPP y cuentas (2026-10-23)
- **PHP 8.4.27** instalado y verificado sobre el proyecto (la suite
  corre igual en 8.3 y 8.4); `composer.json` ahora declara
  `"php": ">=8.1"` y `platform.php = 8.1.0`.
- **`frontend/cuenta.php`** — «Mi cuenta»: cualquier rol edita sus
  datos y cambia su contraseña (pide la actual, fuerza fortaleza,
  regenera el ID de sesión y lo registra en la bitácora).
- **`frontend/admin/sistema.php`** — diagnóstico del entorno: versión
  de PHP, las 7 extensiones, límites de subida del `php.ini`, permisos
  de `backend/storage/uploads`, conexión y carga de la base, `.htaccess`
  y vendor. Se abre desde el panel y sirve para validar una instalación
  nueva en XAMPP en 30 segundos.
- **Subidas en proyectos**: `proyecto_form.php` ahora admite adjuntos
  (igual que noticias y artículos) con galería y borrado.
- **Bug corregido**: `subirAdjuntos()` rompía con **un solo archivo**
  (el navegador envía escalares, no arrays, y el código lea `[0]` como
  offset de cadena → «No se seleccionó ningún archivo»). Ahora
  `normalizarFiles()` unifica ambos casos.
- `docs/LEEME.md`: requisitos PHP 8.1–8.4, `php.ini` sugerido para
  XAMPP y nota de «Mi cuenta».

---

## Bugs del código original detectados y corregidos

| Problema original | Corrección |
|---|---|
| `INSERT` de proyectos sin campo `slug` (los enlaces salían rotos) | se genera con `slugify()` en alta y edición |
| `contarAdminsActivos()` declarada en dos archivos (fatal: *Cannot redeclare*) | una única versión en `auth.php` |
| Imágenes enlazadas con rutas abreviadas `~` que no existen en el servidor | helpers `url()` y `urlMedia()` |
| `logout` por GET sin token (un `<img>` podía cerrar sesiones) | `logout.php` por POST con CSRF |
| Borrados/ediciones sin validar el ID recibido | `WHERE id = ?` acotado a la entidad y al registro actual |
| HTML de artículos/noticias impreso tal cual (**XSS almacenado**) | `sanearHTML()` al guardar y al mostrar, lista blanca de etiquetas |
| Listados sin `LIMIT` (escaneo completo, respuesta sin fin) | `paginar()` + `htmlPaginacion()` en todas las vistas |
| Consultas concatenadas | PDO con *prepared statements* y `ATTR_EMULATE_PREPARES => false` |
| Hash de la contraseña de `admin` en `database.sql` que no validaba ninguna clave | regenerado con `password_hash('Admin2026!')` y verificado con `password_verify()` |

---

## Verificación (2026-10-23)

Con PHP 8.4.27 y la base ya importada:

- `php backend/tests/run.php` → 2 suites, **70 pruebas, 0 fallas**
  (48 archivos PHP, sin estilos/handlers en línea, todas las clases CSS
  existen).
- «Mi cuenta»: cambio de contraseña con la actual incorrecta → error;
  con la correcta → 302, login con la clave nueva, restauración y
  `password_verify()` OK; la bitácora registra `password_cambiada`.
- Subidas en proyectos: PNG subido → fila en `media`, archivo con
  nombre aleatorio en `backend/storage/uploads/imagen/`, visible en la
  ficha pública y servido por `media.php` como `image/png`; un `.php`
  se rechaza con «Tipo de archivo no permitido»; el borrado del adjunto
  y del proyecto limpian disco y base.
- `admin/sistema.php` → 200 con todas las comprobaciones en verde.

## Verificación final (2026-10-13)

Con las extensiones de PHP y MariaDB instaladas en la máquina de
desarrollo se corrió todo el ciclo:

- Base importada (`database.sql`): 12 tablas y 4 usuarios; las cuatro
  contraseñas validan con `password_verify()`.
- `php backend/tests/run.php` → 2 suites, **70 pruebas, 0 fallas**, ya
  por la ruta `DOMDocument` del saneador.
- Recorrido con `./dev-server.sh`: portada, wiki, proyectos, noticias,
  ficha de proyecto, artículo, noticia, quiénes somos y acceso → **200**;
  `admin/` y `admin/seguridad.php` redirigen a login sin sesión.
- Flujo completo con sesión de `admin`: alta de noticia (302 y `slug`
  generado), guardado con `<script>`, `onerror` y `javascript:` en el
  HTML → **eliminados** en la base y en la vista pública; publicación,
  `POST` sin token CSRF → **403**; `logout` por POST; bitácora con
  `login_exitoso`, `noticia_creada`, `csrf_invalido` y `logout`.

Queda pendiente sólo la prueba de **subida de archivos** (imágenes,
vídeo y documentos) contra `media.php`, que requiere un caso de uso
real, y el envío de correo por SMTP con credenciales propias.

## Nota del entorno

Se agregaron **PHP 8.4.27** (repositorio ondrej/php, junto al 8.3 del
sistema) y **MariaDB 10.11** para validar el proyecto con la última
versión estable de PHP disponible para Ubuntu 24.04. También se reparó
el repositorio de AnyDesk (`apt` fallaba por la clave GPG faltante).


El paquete `virtualbox-ext-pack` de esta máquina fallaba al configurarse
con `dpkg` (post-instalación rota, preexistente y ajena al proyecto);
se reparó instalando la extensión 7.2.14 desde un mirror y quedó en
estado `ii`, sin paquetes rotos en `dpkg`.


## Fase 9 — Pruebas funcionales y documentación completa (2026-10-24)

- **Suite HTTP end-to-end** (`backend/tests/http_test.php`): levanta
  `php -S` con `dev-router.php` y recorre con curl las públicas, el
  panel, login (correcto e incorrecto), CSRF 403, creación de noticia
  con HTML saneado, subida de imagen (fila `media` + archivo en disco),
  rechazo de `.php`, entrega por `media.php`, logout y mantenimiento
  503 con restauración. Limpia BD y disco al finalizar; se omite con
  OK si no hay curl o base de datos.
- **Suite de correo** (`correo_test.php`): con `MAIL_TRANSPORT=archivo`
  valida que cada `.eml` tenga cabeceras RFC 5322, que el contacto y la
  confirmación se generen (el asunto viaja codificado RFC 2047) y
  borra los archivos de prueba.
- Correcciones halladas por las pruebas:
  - `http_test.php`: curl no admite mezclar `-d`/`--data-urlencode`
    con `-F` (salía `exit 2`); con archivos todo va en multipart.
  - `estructura_test.php`: un literal `?>` dentro de un comentario
    `//` cerraba el modo PHP y volcaba el archivo a la salida (el test
    "pasaba" sin ejecutarse). Reescrito el comentario.
  - La verificación del login inválido se hacía en un GET posterior;
    el mensaje vive en la respuesta del POST (200).
- `frontend/css/pages.css`: reglas para `.pagina-mantenimiento`
  (503 del gate de mantenimiento en `bootstrap.php`).
- **Documentación completa** en `docs/`: `ARQUITECTURA.md` (capas,
  flujo de petición, módulos, decisiones), `DISENO.md` (tokens,
  componentes, responsive, accesibilidad), `MODELO-DE-DATOS.md`
  (tabla por tabla, vistas, cascadas), `CODIGO.md` (catálogo de los
  ~90 helpers de `backend/lib/`), `PRUEBAS.md` (cómo correr y
  escribir suites) y `OPERACION.md` (XAMPP, respaldos, migraciones,
  correo, mantenimiento). `LEEME.md` ya las enlaza.

## Verificación final (2026-10-24)

- `php backend/tests/run.php` → **4 suites, 0 fallas**: correo,
  estructura (52 archivos PHP: sintaxis, CSP, clases CSS, accesibilidad,
  CSRF, enlaces), recorrido HTTP (17 comprobaciones) y saneamiento
  (70 pruebas DOM).
- Recorrido manual con `./dev-server.sh`: todas las pantallas en 200
  sin avisos PHP 8.4.

## Documentación para el equipo (2026-10-24)

Se completaron los tres documentos que faltaban para que un integrante
nuevo pueda entender y trabajar sobre el proyecto sin contexto previo:

- `docs/MANUAL-DE-USUARIO.md` — qué hace cada pantalla pública y del
  panel, flujos (moderación de noticias, versiones de artículos,
  hitos/avance), recetas rápidas y reglas de usuario.
- `docs/GUIA-DESARROLLADOR.md` — patrón de pantalla, convenciones
  obligatorias (CSP, CSRF, SQL preparado, permisos por capacidad),
  recetas paso a paso (pantalla nueva, capacidad, migración, subidas,
  pruebas), flujo de git y checklist de entrega.
- `docs/GLOSARIO.md` — términos del sistema (capacidad, media
  polimórfico, slug, migración, transporte de correo, etc.).

Actualizaciones de coherencia: `LEEME.md` (14 tablas + 4 vistas, 4
suites de pruebas, índice con los documentos nuevos y punto de partida
según el rol del lector) y `README.md` (tabla de documentación).

## Fase 10 — Modo oscuro / claro (2026-10-25)

- **Capa de tokens semánticos** en `frontend/css/base.css`:
  `--bg`, `--surface`, `--surface-2`, `--border`, `--field-border`,
  `--heading`, `--link`, `--code-bg`, `--muted-strong`, `--focus-ring`,
  bordes de estado (`--*-line`), `--gold-bg/line`, `--danger-solid/deep`
  e insignias de rol (`--role-*-bg/fg`). Los ~30 colores literales
  fuera de marca (alertas, roles, errores de formulario, foco, panel de
  versión, punto de estado) ahora pasan por tokens.
- **Bloque oscuro duplicado e idéntico** en `base.css`:
  `:root[data-tema='oscuro']` (JS) y `@media (prefers-color-scheme:
  dark) :root:not([data-tema])` (respaldo sin JS), con `color-scheme`
  y contraste AA verificado (≥4.5:1 texto, ≥3:1 bordes de campo).
- **`partials/header.php`**: meta `color-scheme: light dark`; script
  con nonce que resuelve `localStorage['tw-tema']` (`auto|claro|oscuro`)
  contra `prefers-color-scheme` **antes del primer pintado** (cero
  destellos); botón `.theme-toggle` con SVG inline (sol/luna), oculto
  sin JS.
- **`js/main.js` → módulo del tema**: clic alterna y persiste;
  `matchMedia` aplica cambios del sistema en `auto`; evento `storage`
  sincroniza entre pestañas; `aria-pressed`/`aria-label` actualizados.
- **Pruebas**: `estructura_test` suma la sección 9 (los dos bloques
  oscuros deben ser idénticos, `color-scheme: dark` presente y todo
  `var(--token)` definido); `http_test` verifica alternador, meta y
  script anti-flash en la portada.
- **Docs**: `DISENO.md` (sección "Modo oscuro / claro" con reglas para
  agregar colores) y `MANUAL-DE-USUARIO.md` (cómo cambiar el tema).
