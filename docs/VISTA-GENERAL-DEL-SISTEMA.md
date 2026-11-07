# TelecomWiki — El sistema a fondo

Documento único para conocer el proyecto de punta a punta: qué resuelve,
con qué está hecho, cómo está organizado, cómo se configura y cómo se
extiende. Pensado para que un integrante nuevo del equipo pueda
entienderlo en una sola lectura y saber dónde buscar después.

**Autores y desarrolladores:** Samuel Ramirez · Elisabeth Perez ·
Juan Briceño.

| Si querés… | Andá a |
|---|---|
| Instalarlo hoy | [`LEEME.md`](LEEME.md) |
| Usarlo como usuario | [`MANUAL-DE-USUARIO.md`](MANUAL-DE-USUARIO.md) |
| Programar sobre él | [`GUIA-DESARROLLADOR.md`](GUIA-DESARROLLADOR.md) |
| El detalle profundo | Este documento + los enlaces de cada sección |

---

## 1. Qué es y para qué

**TelecomWiki** es el *Sistema de Gestión y Estandarización de
Documentación Técnica* de los proyectos de **Telecom Venezuela, C.A.**

**El problema que resuelve**: los proyectos de la empresa generan
documentación dispersa (guiones, arquitectura, mecánicas, niveles,
notas técnicas) sin versión, sin estándar y sin lugar único de
consulta. TelecomWiki concentra esa documentación en un wiki con:

- **Proyectos** con ficha estandarizada, hitos y % de avance.
- **Artículos** por proyecto con 7 tipos documentales, control de
  versiones (historial + restauración) y etiquetas navegables.
- **Noticias** institucionales con cola de moderación.
- **Multimedia** adjunta (imagen/video/documento) a cualquier entidad.
- **Panel de administración** (usuarios, contenido, mensajes,
  bitácora, diagnóstico) con roles jerárquicos.
- **Seguridad** seria: CSP estricta, CSRF, saneado de HTML, límite de
  intentos de acceso, auditoría de eventos.

**Alcance**: aplicación web interna, desplegable en XAMPP (escenario
académico/prototipo) o en Apache+MySQL de producción. Proyecto
académico — TSU en Informática · UPTC «Mariscal Sucre» · Tutor:
Alfredo Olivero.

---

## 2. Stack tecnológico

### Lenguajes y versiones (soportado y probado)

| Tecnología | Versión | Rol |
|---|---|---|
| **PHP** | **8.1 – 8.4** (probado en 8.3.6 y 8.4.27) | Todo el backend y las vistas |
| **MySQL / MariaDB** | 5.7+ / 10.2+ (probado en MariaDB 10.11) | Persistencia (InnoDB, utf8mb4) |
| **HTML5** | — | Vistas (PHP embebido) |
| **CSS3** | — | 5 hojas propias, variables CSS, grid/flex, sin preprocesador |
| **JavaScript** | ES5+/vanilla (sin framework) | `main.js`, `editor.js`, `articulo_form.js` |

Extensiones PHP obligatorias: `pdo_mysql` (conexión), `mbstring`
(texto UTF-8), `dom`/`xml` (saneado de HTML), `fileinfo` (MIME real de
subidas). Opcionales: `openssl`/`curl` para SMTP.

### Dependencias (mínimas a propósito)

| Dependencia | Versión | Uso |
|---|---|---|
| **PHPMailer** | v7.1.1 (vendored en `backend/vendor/`) | Envío SMTP de correo de contacto |
| *(composer)* | `php >=8.1`, `platform.php = 8.1.0` | Sólo para mantener el vendor |

**No hay frameworks** (ni Laravel/Symfony ni Bootstrap/Tailwind ni
React/Vue): es un requisito del proyecto correr copiando la carpeta a
`htdocs/`, sin build ni cadena de dependencias. Toda la infraestructura
(routing, sesiones, CSRF, ORM-lite, tests) es **código propio** en
`backend/lib/` — ver [`ARQUITECTURA.md`](ARQUITECTURA.md).

### Servidor web

- **Producción/académico**: Apache 2.4 (XAMPP) con `mod_rewrite`,
  `mod_headers`, `mod_authz_core`; protección por `.htaccess` ×4.
- **Desarrollo**: `php -S 127.0.0.1:8000` con `dev-router.php`
  (replica las reglas de los `.htaccess` + aliases de URL) vía
  `dev-server.sh`.

---

## 3. Arquitectura

### 3.1 Capas

```
frontend/*.php + admin/*.php   →  Pantallas (una por URL, sólo orquestan)
frontend/partials/             →  header.php / footer.php (layout común)
frontend/css|js|assets         →  Estilo y comportamiento propios
backend/lib/                   →  TODA la lógica reutilizable (14 módulos)
backend/config/                →  config.php | db.php | mail_config.php
backend/database/              →  database.sql + migraciones/ versionadas
backend/bin/                   →  migrar.php y respaldo.sh (CLI)
backend/storage/               →  uploads/ mail/ respaldos/ (FUERA de la web)
backend/tests/                 →  4 suites + corredor run.php
```

Regla de oro: **las pantallas no contienen lógica de negocio**; si dos
pantallas necesitan lo mismo, es una función en `lib/`.

### 3.2 Flujo de una petición (paso a paso)

1. Apache (o `php -S`) recibe la URL → archivo `frontend/<pantalla>.php`.
2. La pantalla hace `require bootstrap.php`:
   `config.php` (constantes/env) → `db.php` (PDO perezoso `getDB()`)
   → librerías (`security`, `helpers`, `auth`, `permisos`, `validate`,
   `paginate`, `sanitize`, `render`, `upload`, `tags`, …) → sesión con
   cookie `HttpOnly`+`SameSite` → **cabeceras de seguridad** (CSP con
   nonce, X-Frame-Options, etc.) → gate de **modo mantenimiento** (503).
3. Si es POST: `verifyCsrf()` (403 si falla) + rate-limits.
4. Guardas: `requierePermiso('capacidad')` / `requireLogin()`.
5. Entrada por `saneaGET()`/`saneaPOST()` → `validar()` con reglas.
6. Acción (SQL con `prepare()`) → `redirigir()` (patrón Post/Redirect/Get
   con mensaje *flash*).
7. Lectura para la vista (a menudo sobre **vistas SQL**) + `paginar()`.
8. `abrirPagina()` imprime `partials/header.php` (con el script
   anti-flash del tema) → contenido con `e()` → `cerrarPagina()`.

Detalle completo: [`ARQUITECTURA.md`](ARQUITECTURA.md).

### 3.3 Módulos de `backend/lib/`

| Módulo | Responsabilidad clave |
|---|---|
| `bootstrap.php` | Cableado único + mantenimiento |
| `config_sitio.php` | Ajustes clave/valor en BD con caché |
| `db.php` | `getDB()` PDO con manejo de error |
| `auth.php` | Login/logout/sesión/roles/guards |
| `permisos.php` | Matriz única `CAPACIDADES` + `puede()` |
| `security.php` | CSP+nonce, CSRF, flash, `logSeguridad`, intentos |
| `sanitize.php` | `sanearHTML()` DOM allowlist (anti-XSS) |
| `validate.php` | `saneaGET/POST`, `validar()`, `escaparLike` |
| `upload.php` | Subidas con MIME real + `media` polimórfico |
| `mailer.php` | SMTP (PHPMailer) o archivo `.eml` |
| `paginate.php` | `paginar()` / `htmlPaginacion()` |
| `render.php` | Layout, tarjetas, badges, campos de formulario |
| `helpers.php` | `e()`, `slugify()`, `url()`, fechas, iconos |
| `tags.php` · `plantillas_articulos.php` | Etiquetas N:M · plantillas por tipo |

Catálogo completo con firmas: [`CODIGO.md`](CODIGO.md).

### 3.4 URLs

Planas con parámetros (sin `mod_rewrite` obligatorio):
`proyecto.php?slug=…`, `wiki.php?tag=…&q=…`, `media.php?f=…`.
Enlaces siempre con `url('ruta')` (prefija `SITE_URL`) y
`urlMedia()` para archivos.

---

## 4. Configuración

### 4.1 Dónde se configura todo

| Archivo | Contenido |
|---|---|
| `backend/config/config.php` | Rutas, `SITE_URL`, límites de subida (`UPLOAD_MIME`, 50 MB), paginación, límites de intentos, categorías de noticia, tiempos de sesión |
| `backend/config/db.php` | Conexión: `DB_HOST/PORT/USER/PASS/NAME` (env con defaults XAMPP `root`/vacía) |
| `backend/config/mail_config.php` | SMTP + transporte: `MAIL_TRANSPORT` = `smtp` si hay `SMTP_PASS`, si no `archivo` |
| `.htaccess` (raíz, `partials/`, `backend/`, `storage/`) | Bloqueo de `.sql/.md/.env`, backend, docs, ejecución de PHP en uploads |
| `backend/storage/mail/*.eml` | Salida real del correo en modo archivo (auditable) |

### 4.2 Variables de entorno (todas opcionales con defaults)

| Variable | Defecto | Efecto |
|---|---|---|
| `APP_ENV` | inferido (`development` en localhost) | `production` oculta errores al visitante |
| `SITE_URL` | `http://localhost/telecom_wiki/frontend` | Base de todos los enlaces |
| `SITE_NAME` | `TelecomWiki` | Nombre del sitio |
| `DB_HOST/PORT/USER/PASS/NAME` | `127.0.0.1/3306/root/—/telecom_wiki` | Base de datos |
| `SMTP_HOST/PORT/SECURE/USER/PASS` | gmail/587/tls/… | Correo saliente |
| `MAIL_TRANSPORT` | auto (`smtp` si hay pass) | Forzar transporte |
| `CONTACT_TO_EMAIL` | igual al remitente | Destinatario del contacto |

### 4.3 Configuración "viva" (en la BD, no en archivos)

Tabla `config_sitio` (caché por petición, editable desde
**Panel → Diagnóstico**): `mantenimiento` (1 = 503 al público),
`titulo_portada`, `contacto_institucional`. La capa es
`backend/lib/config_sitio.php` (`configObtener`/`configGuardar`).

### 4.4 Seguridad en la configuración

- **CSP estricta** enviada por `security.php`: `script-src 'self'` +
  nonce, `style-src 'self'` **sin** `unsafe-inline` → prohibido todo
  estilo/handler inline; los datos para JS van en `<input type="hidden">`.
- Cabeceras de respaldo en `.htaccess` para estáticos
  (`X-Content-Type-Options`, `X-Frame-Options DENY`, `Referrer-Policy`).
- Subidas: lista blanca extensión→MIME real (`finfo`), límites 5/15/50 MB
  por tipo, nombres aleatorios de 32 hex, servidas por `media.php`
  (valida nombre+MIME y admite `Range` para video).
- Sesión: 30 min inactividad, 8 h máximo, regeneración de ID cada 15 min.
- Límites: 5 intentos de login/usuario y 10/IP por 10 min; 5 contactos
  y 30 subidas/IP por ventana.

---

## 5. Base de datos

**14 tablas InnoDB + 4 vistas**, utf8mb4. Importable de cero con
`backend/database/database.sql` (incluye datos de ejemplo y las 4
cuentas). Resumen:

| Bloque | Tablas | Notas |
|---|---|---|
| Identidad | `usuarios` | 4 roles ENUM jerárquicos; únicos `usuario`/`email` |
| Proyectos | `categorias`, `proyectos`, `hitos` | Categoría protegida (`RESTRICT`); hitos con % que alimenta `avance_medio` |
| Wiki | `articulos`, `historial_versiones`, `tags`, `articulo_tags` | Borrado en cascada desde proyecto; versión incrementa en cada guardado |
| Portal | `noticias` | Estado ENUM es la única fuente de verdad (borrador/pendiente/publicada/rechazada) |
| Adjuntos | `media` | **Polimórfico sin FK** (`entidad_tipo`+`entidad_id`) → `eliminarMediaDe()` al borrar |
| Operación | `mensajes_contacto`, `eventos_seguridad`, `migraciones`, `config_sitio` | Auditoría, límites de intentos, control de esquema, ajustes |

**Vistas**: `v_proyectos_resumen` (ficha + conteos + avance medio),
`v_articulos_publicados`, `v_noticias_publicadas`,
`v_noticias_moderacion`.

**Integridad**: cascadas donde el hijo no existe solo (`proyectos →
articulos → historial/tags`); `SET NULL` para autorías de `usuarios`;
sin FK en `media`/`eventos_seguridad` por diseño.

Tablas y columnas a detalle: [`MODELO-DE-DATOS.md`](MODELO-DE-DATOS.md);
diagramas ER/casos de uso/secuencias: [`DIAGRAMAS.md`](DIAGRAMAS.md)
(+11 SVG en `docs/diagramas/`).

---

## 6. Personas, roles y capacidades

Roles jerárquicos: **lector (1) < editor (2) < supervisor (3) < admin (4)**
(`nivelRol()`); un rol superior hereda todo lo de los inferiores.

La única fuente de verdad es la matriz `CAPACIDADES` en
`backend/lib/permisos.php`:

| Capacidad | mín. rol |
|---|---|
| `proyecto.crear/editar`, `articulo.*`, `hito.gestionar`, `media.*`, `noticia.crear/editar` | editor |
| `noticia.moderar` (aprobar/rechazar) | supervisor |
| `usuario.gestionar`, `categoria.gestionar`, `mensaje.gestionar`, `bitacora.ver`, `proyecto.eliminar`, `noticia.eliminar` | admin |
| `panel.acceder` | editor |
| `leer` | lector (público) |

Reglas: en código **siempre** `puede('capacidad')` /
`requierePermiso('capacidad')` — **nunca** comparar el nombre del rol a
mano; y `puedeEditarPropio()` para que un editor sólo toque lo suyo.

Usuarios seed (demo): `admin`/`Admin2026!`; `supervisor`, `editor`,
`lector`/`Demo2026!` — **cambiar en cualquier despliegue real**.

---

## 7. Seguridad — modelo resumido

| Amenaza | Medida |
|---|---|
| XSS (script inyectado) | `e()` en toda salida + `sanearHTML()` DOM allowlist al guardar y al mostrar + CSP sin `unsafe-inline` |
| CSRF | Token por sesión en todo POST (`verifyCsrf` → 403) |
| Fuerza bruta | 5 intentos/usuario, 10/IP por 10 min (`eventos_seguridad`) |
| Subida maliciosa | MIME real por `finfo`, whitelist, nombre aleatorio, fuera de la web, servido por `media.php` |
| Escalada de rol | Matriz única de capacidades + guards al inicio de cada pantalla |
| Sesión fijada | `session_regenerate_id` al login, al cambiar contraseña y cada 15 min |
| Exposición de archivos | `.htaccess` ×4 + `dev-router.php` en desarrollo: backend, docs, SQL, MD, storage |
| Clickjacking / MIME sniffing | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy` |
| Registro | `logSeguridad()` en `eventos_seguridad` (login, fallos, moderación, subidas…) |

Modelo completo y matriz de amenazas: [`SEGURIDAD.md`](SEGURIDAD.md).

---

## 8. Experiencia y diseño

- **Sistema de diseño propio**: tokens CSS en `:root` (colores,
  radios, sombras, escala tipográfica Inter auto-hospedada,
  degradados, transiciones), 5 hojas
  (`base`, `layout`, `components`, `pages`, `admin`), componentes
  generados por helpers (tarjetas, badges, chips, campos, galería,
  paginación), iconos SVG inline. Responsive en 600/700/960 px,
  accesibilidad (h1 único, alt, focus visible, `prefers-reduced-motion`).
- **Modo oscuro/claro**: capa de tokens semánticos
  (`--bg`, `--surface`, `--heading`, `--border`, roles, estados) con
  **bloque oscuro duplicado e idéntico** — `[data-tema='oscuro']` (JS)
  y `@media (prefers-color-scheme: dark)` (sin JS). El tema se resuelve
  en el `<head>` con nonce (cero parpadeos), se alterna con el botón
  sol/luna de la cabecera y persiste en `localStorage['tw-tema']`
  (`auto|claro|oscuro`). Contraste AA verificado.
- **Editor enriquecido propio** (`editor.js`): sólo inserta etiquetas
  permitidas (Ctrl+B/I), sin librerías externas.

Detalle: [`DISENO.md`](DISENO.md).

---

## 9. Flujos principales (cómo funcionan por dentro)

1. **Acceso**: `login.php` valida con `password_verify`, registra
   `login_exitoso`/`login_fallido`, regenera la sesión, actualiza
   `ultimo_acceso` y redirige al panel (guardado por capacidad).
2. **Artículo**: editor → validación → `sanearHTML()` → INSERT +
   copia en `historial_versiones` con `version+1` → `sincronizarTags()`
   → redirect con flash. Restaurar = copiar una versión al contenido.
3. **Noticia**: editor crea (`pendiente`) → supervisor aprueba
   (`publicada` + `fecha_publicacion`) o rechaza (motivo obligatorio)
   → el público sólo ve `publicada` (vista `v_noticias_publicadas`).
4. **Subida**: `normalizarFiles()` → validación MIME/límites → archivo
   a `storage/uploads/<tipo>/<32hex>` → fila en `media` → se muestra en
   la galería pública vía `media.php?f=`.
5. **Contacto**: INSERT en `mensajes_contacto` + correo al admin +
   confirmación al autor (transporte smtp o archivo).
6. **Mantenimiento**: `config_sitio.mantenimiento=1` → 503 con
   `Retry-After` para el público; login/logout/media y panel siguen
   accesibles para diagnosticar.

---

## 10. Pruebas y calidad

```bash
php backend/tests/run.php        # 4 suites, código de salida 0 = OK
```

| Suite | Cubre | Requisitos |
|---|---|---|
| `sanitize_test` | 70 pruebas: 16 payloads XSS + HTML legítimo (DOM y fallback) | — |
| `estructura_test` | Sintaxis (52 archivos), CSP, clases CSS, accesibilidad, CSRF, enlaces, **sección 9: bloques de tema idénticos y tokens definidos** | — |
| `correo_test` | `.eml` con cabeceras RFC, contacto y confirmación | — |
| `http_test` | End-to-end con `php -S`+curl: login, CSRF 403, subidas, media, moderación, mantenimiento 503, alternador de tema | curl + BD |

Convención: `exit 0` si todo OK; si falta un requisito la suite se
**omite con OK**. Detalle y molde para nuevas pruebas:
[`PRUEBAS.md`](PRUEBAS.md).

---

## 11. Operación

- **Instalar (XAMPP)**: copiar a `htdocs/` → importar `database.sql` →
  ajustar `db.php` si hace falta → Panel → Diagnóstico en verde.
- **Desarrollar (Linux)**: usuario MySQL propio + `./dev-server.sh`
  (exporta `DB_*` y sirve en `:8000`).
- **Respaldos**: `backend/bin/respaldo.sh crear` (mysqldump gzip +
  tar de uploads) / `--listar` / `--restaurar`.
- **Cambios de esquema**: `backend/database/migraciones/NNNN_*.sql`
  idempotentes → `php backend/bin/migrar.php --aplicar` (registra
  SHA-256 en `migraciones`) → reflejar en `database.sql` y docs.
- **Correo**: SMTP real sólo con `SMTP_PASS` en el entorno; sin eso,
  `.eml` en `storage/mail/` (Panel → Diagnóstico → "correo de prueba").
- **php.ini sugerido**: `upload_max_filesize=16M+`, `post_max_size`,
  `memory_limit=256M`, `date.timezone=America/Caracas`.

Pasos completos y troubleshooting: [`OPERACION.md`](OPERACION.md).

---

## 12. Convenciones que el equipo debe conocer

1. **CSP**: cero `style=`/`onclick=` inline; JS sólo desde
   `frontend/js/` con nonce; datos para JS en inputs ocultos.
2. **Salida escapada** con `e()`; HTML rico con `sanearHTML()`.
3. **SQL**: `prepare()` siempre; `LIKE` con `escaparLike()` y sin
   `ESCAPE`; `LIMIT` sólo desde `paginar()`.
4. **Permisos por capacidad**, nunca por nombre de rol.
5. **Sin `?>` de cierre** en PHP (y cuidado con `?>` dentro de
   comentarios `//`).
6. **Sin dependencias nuevas**: el proyecto debe correr copiando.
7. **Colores**: sólo tokens semánticos; si es nuevo, definirlo en
   `:root` **y** en los dos bloques oscuros idénticos (lo verifica el
   test).
8. **Todo POST** lleva `campoCSRF()`; toda pantalla del panel empieza
   con `requierePermiso()`.
9. **Documentar mientras se desarrolla**: cambios relevantes en
   `CAMBIOS.md` y en la doc correspondiente.
10. **Verificación previa al commit**: `php backend/tests/run.php`.

---

## 13. Mapa del código — dónde está cada cosa

| Estoy buscando… | Archivo |
|---|---|
| El arranque de cada petición | `backend/lib/bootstrap.php` |
| Constantes y límites | `backend/config/config.php` |
| Conexión a la BD | `backend/config/db.php` (`getDB()`) |
| Permisos | `backend/lib/permisos.php` |
| CSP, CSRF, log | `backend/lib/security.php` |
| Saneado de HTML | `backend/lib/sanitize.php` |
| Subidas y `media` | `backend/lib/upload.php` |
| Layout (header/footer) | `frontend/partials/header.php` · `footer.php` |
| Tokens y tema oscuro | `frontend/css/base.css` |
| Comportamiento común | `frontend/js/main.js` |
| Esquema + datos | `backend/database/database.sql` |
| Diagnóstico del entorno | `frontend/admin/sistema.php` |
| Pruebas | `backend/tests/run.php` |

---

## 14. Índice completo de documentación

| Documento | Contenido |
|---|---|
| [`LEEME.md`](LEEME.md) | Instalación, requisitos, troubleshooting |
| [`VISTA-GENERAL-DEL-SISTEMA.md`](VISTA-GENERAL-DEL-SISTEMA.md) | Este documento |
| [`MANUAL-DE-USUARIO.md`](MANUAL-DE-USUARIO.md) | Pantallas, flujos y recetas de uso |
| [`MANUAL-VISITANTE.md`](MANUAL-VISITANTE.md) | Manual por rol: consulta pública |
| [`MANUAL-EDITOR.md`](MANUAL-EDITOR.md) | Manual por rol: proyectos, artículos, versiones, moderación |
| [`MANUAL-ADMINISTRADOR.md`](MANUAL-ADMINISTRADOR.md) | Manual por rol: usuarios, bitácora, diagnóstico, mantenimiento |
| [`DEFENSA-DEL-PROYECTO.md`](DEFENSA-DEL-PROYECTO.md) | Documento de defensa: objetivos, métricas, preguntas del tribunal, demo |
| [`ARQUITECTURA.md`](ARQUITECTURA.md) | Capas, flujo de petición, módulos, decisiones |
| [`GUIA-DESARROLLADOR.md`](GUIA-DESARROLLADOR.md) | Convenciones, recetas para extender, checklist |
| [`DISENO.md`](DISENO.md) | Tokens, componentes, responsive, accesibilidad, modo oscuro |
| [`MODELO-DE-DATOS.md`](MODELO-DE-DATOS.md) | Tablas, claves, vistas, cascadas |
| [`DIAGRAMAS.md`](DIAGRAMAS.md) | ER, casos de uso, procesos, secuencias, estados (+SVG) |
| [`CODIGO.md`](CODIGO.md) | Catálogo de helpers |
| [`GLOSARIO.md`](GLOSARIO.md) | Términos del sistema |
| [`PRUEBAS.md`](PRUEBAS.md) | Cómo correr y escribir pruebas |
| [`OPERACION.md`](OPERACION.md) | Despliegue, respaldos, migraciones, correo |
| [`SEGURIDAD.md`](SEGURIDAD.md) | Modelo de amenazas y medidas |
| [`CAMBIOS.md`](CAMBIOS.md) | Historial de desarrollo y verificaciones |

**Ruta sugerida para un integrante nuevo**: 1) este documento →
2) `LEEME.md` instalando una copia → 3) tu manual por rol
(`MANUAL-VISITANTE.md` / `MANUAL-EDITOR.md` /
`MANUAL-ADMINISTRADOR.md`) usando el sistema con las 4 cuentas →
4) `GUIA-DESARROLLADOR.md` + `CODIGO.md` antes de escribir código →
5) `DIAGRAMAS.md` para el panorama completo. Para exponer el proyecto
→ `DEFENSA-DEL-PROYECTO.md`.
