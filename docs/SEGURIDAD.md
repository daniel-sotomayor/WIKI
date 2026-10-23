# TelecomWiki — Notas de seguridad

Documento técnico del proyecto académico: describe **qué amenazas se
consideraron, con qué contramedidas se cubrieron y qué debe respetar quien
extienda el sistema**.

> Alcance: aplicación web (PHP + MySQL). No incluye la seguridad del
> sistema operativo ni la del servidor Apache, que queda a cargo de quien
> despliegue.

---

## 1. Modelo de amenazas

| # | Amenaza | Impacto si no se mitiga |
|---|---|---|
| A1 | XSS almacenado (contenido con `<script>`/`onerror`) | robo de sesión, defacement, redirección de visitantes |
| A2 | XSS reflejado (parámetros en la salida) | lo mismo, vía un enlace manipulado |
| A3 | Inyección SQL en búsquedas/filtros | lectura o borrado de toda la base |
| A4 | CSRF en formularios autenticados | un tercero publica/borra contenido en nombre de la víctima |
| A5 | Fuerza bruta sobre `login.php` | cuenta administradora comprometida |
| A6 | Subida de archivos peligrosos (`.php`, `.phtml`, polyglots) | ejecución de código en el servidor (RCE) |
| A7 | Escalada de privilegios entre roles | un lector edita o administra |
| A8 | Robo/fijación de sesión | suplantación de usuarios |
| A9 | Clickjacking, sniffing de contenido, descarga de ficheros internos | exposición indirecta |
| A10 | Enumeración / fingerprinting | facilita los ataques anteriores |

---

## 2. Respuestas por amenaza

### A1/A2 · XSS — doble barrera (`backend/lib/sanitize.php`)

- **Al guardar**: `sanearHTML()` limpia el HTML de los campos enriquecidos
  (artículos, noticias, descripciones).
- **Al mostrar**: se vuelve a sanear antes de imprimir; aunque alguien
  manipule la base, lo que llega al navegador ya viene filtrado.
- Mecanismo: eliminación de etiquetas peligrosas *con su contenido*
  (`script`, `style`, `iframe`, `svg`, `form`, `input`, `video`…),
  **lista blanca** de etiquetas permitidas, y filtrado de atributos por
  etiqueta (`href`, `src`, `cite` → sólo `http/https/mailto/tel`, rutas
  relativas y anclas; `javascript:`, `data:`, `vbscript:` → descartados,
  incluida su escritura con entidades HTML o con mayúsculas mezcladas).
- Atributos `on*` y `style` se borran siempre; `class`/`id` se limitan a
  identificadores simples (evita romper la CSS del sitio).
- Salida: `e()` = `htmlspecialchars(…, ENT_QUOTES, 'UTF-8')` en **toda**
  interpolación de datos en HTML (títulos, atributos, listados…).
- Las plantillas no usan `innerHTML` con datos del usuario.

### A3 · SQLi — consultas preparadas

- Todas las consultas pasan por PDO con *prepared statements* y
  `ATTR_EMULATE_PREPARES => false` (parámetros reales, no concatenados).
- Los `LIKE` escapan `\`, `%` y `_` con `escaparLike()` para que un patrón
  de búsqueda no amplíe el resultado ni inyecte sintaxis.
- Los `ORDER BY`/límites se construyen con listas de permitidos
  (`enLista()` / valores enteros validados), nunca con texto del usuario.

### A4 · CSRF

- Token por sesión (`csrfToken()`), campo `csrf_token` en cada formulario
  y verificación con `hash_equals()` (`verifyCsrf()`) antes de procesar.
- El token se inyecta con el helper `campo()` de `render.php`; **todo
  formulario con `method="post"` debe llevarlo**.
- `logout.php` es **POST con CSRF** (antes era un GET enlaceable).
- Cookies `SameSite=Lax` como segunda barrera.
- Los intentos fallidos quedan en `eventos_seguridad`
  (tipo `csrf_invalido`).

### A5 · Fuerza bruta

Ventanas y topes (`backend/config/config.php`):

| Evento | Límite |
|---|---|
| `login_fallido` por usuario | 5 en 10 min |
| `login_fallido` por IP | 10 en 10 min |
| envíos de contacto por IP | 5 en 15 min |
| subidas por IP | 30 en 10 min |

- El bloqueo se calcula contra la tabla `eventos_seguridad`
  (`contarEventosRecientes()`), por IP **y** por usuario.
- Contraseñas: `password_hash()` / `password_verify()` (bcrypt).
- `session_regenerate_id(true)` al autenticar (A8).

### A6 · Subidas de archivo (`backend/lib/upload.php`)

1. Extensión de la lista blanca `UPLOAD_MIME` (imágenes, video, audio,
   documentos; **nunca** `.php`, `.phtml`, `.htaccess`…).
2. Límite de tamaño por tipo (`UPLOAD_LIMITS`) además del `php.ini`.
3. **MIME real** con `finfo` — no el declarado por el navegador — y que
   coincida con la extensión.
4. Imágenes: `getimagesize()` con dimensiones máximas (12000 px);
   vídeos: el MIME debe empezar por `video/`.
5. Nombre aleatorio de 32 hex + extensión: sin nombre original en disco
   (evita colisiones, rutas y *path traversal*).
6. `is_uploaded_file()` obligatorio.
7. **Almacenamiento fuera del alcance web**: `backend/storage/uploads/`,
   carpeta que Apache bloquea con `.htaccess` (`Require all denied`).
8. Entrega por `media.php?f=`: valida el nombre
   (`[a-f0-9]{32}.ext`), el MIME guardado en la tabla `media` y sirve con
   `Content-Disposition` correcto, `X-Content-Type-Options: nosniff` y
   soporte de `Range` (206) para `<video>`.

### A7 · Control de acceso

- Matriz única de capacidades en `backend/lib/permisos.php`; los roles son
  jerárquicos (lector < editor < supervisor < admin).
- Toda pantalla protegida arranca con `requireLogin()` +
  `requierePermiso('<capacidad>')`.
- Las vistas preguntan por **capacidad** (`puede()`), nunca por una lista
  de roles armada a mano: así no hay dos políticas que diverjan.
- Las operaciones destructivas acotan el `WHERE` a la entidad
  (`WHERE id = ? AND proyecto_id = ?`): ni ID arbitrario ni borrado cruzado.
- `puedeEditarPropio()` permite autor o rol ≥ supervisor.
- Protecciones anti-accidentes: no se puede dejar el sistema sin
  administradores (`contarAdminsActivos()`).

### A8 · Sesión (`backend/lib/security.php`)

| Parámetro | Valor |
|---|---|
| nombre de cookie | `TWSESSID` (no el genérico `PHPSESSID`) |
| `HttpOnly` | sí → JS no lee la cookie |
| `SameSite` | `Lax` |
| `Secure` | activo sólo si la petición ya es HTTPS |
| `use_strict_mode` | sí → rechaza IDs inventados |
| inactividad | 30 min |
| duración máxima | 8 h |
| regeneración de ID | cada 15 min |

### A9 · Cabeceras HTTP (enviadas por `security.php`)

```
Content-Security-Policy: default-src 'self';
                         script-src 'self' 'nonce-…';
                         style-src 'self';
                         font-src 'self'; img-src 'self' data:;
                         media-src 'self'; connect-src 'self';
                         base-uri 'self'; form-action 'self';
                         frame-ancestors 'none'; object-src 'none'
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()
Strict-Transport-Security (sólo cuando ya hay HTTPS real)
```

Además: `header_remove('X-Powered-By')`, `.htaccess` con `ServerSignature
Off`, bloqueo de `backend/`, `docs/`, `storage/`, parciales, `.sql`, `.md`,
`.env`, backups y `dev-router.php`; el PHP del panel no expone *stack
traces* cuando `APP_ENV=production`.

### A10 · Bitácora

`eventos_seguridad` registra `login_exitoso`, `login_fallido`,
`login_bloqueado`, `logout`, `csrf_invalido`, subidas, borrados y cambios
de rol, con usuario, IP y detalle. Se consulta en
`frontend/admin/seguridad.php` (capacidad `bitacora.ver`, sólo
administrador): filtros por tipo, usuario, IP y detalle, resumen de las
últimas 24 horas y purga de registros antiguos con confirmación.

---

## 3. Reglas para quien extienda el sistema

1. **Nada de estilos ni handlers en línea.** La CSP no tiene
   `unsafe-inline` ni en `script-src` ni en `style-src`:
   - ❌ `<div style="width:50%">` → ✅ clase `progress__bar--p50`
   - ❌ `<button onclick="…">` → ✅ `data-confirm-submit="¿Borrar?"`
     (lo procesa `frontend/js/main.js`)
   - ❌ `<script>var x = 1</script>` → ✅ archivo con `nonce` en el tag, o
     un `<input type="hidden">` con el dato
2. **Siempre** `e()` al imprimir datos; `sanearHTML()` sólo para HTML
   enriquecido.
3. **Siempre** consulta preparada; `escaparLike()` en búsquedas.
4. **Siempre** `csrf_token` en todo POST y `verifyCsrf()` al procesarlo.
5. **Siempre** `requierePermiso()` al abrir una pantalla y capacidad
   (nunca rol) en las vistas.
6. Al crear un registro con `media`, llamar `eliminarMediaDe()` en el
   borrado correspondiente.
7. No mover `backend/storage/uploads/` a `frontend/`: dejaría de estar
   protegido.
8. Al agregar una tabla, agregar también su `DROP TABLE IF EXISTS`, índices
   y claves foráneas en `backend/database/database.sql`.

---

## 4. Verificación

```bash
# Todo en un paso (devuelve 0 si no hay fallas)
php backend/tests/run.php

# Suite suelta del saneador
php backend/tests/sanitize_test.php

# Suite de estructura: sintaxis, CSP, clases CSS, referencias
php backend/tests/estructura_test.php
```

`sanitize_test.php` cubre las **dos** rutas del saneador:
`sanearHTML()` y `filtrarSinDOM()` siempre; `filtrarDOM()` cuando la
extensión DOM está instalada. `estructura_test.php` además falla si
aparece algún handler en línea (`onclick=…`), un `style="…"` o una clase
CSS que no exista en `frontend/css/` (romperían la CSP o la maquetación).

Casos que se probaron contra `sanearHTML()` (16 payloads, 0 fallos):
`<script>`, `onclick=`, `onerror=`, `javascript:` en minúsculas/mayúsculas
y con entidades, `data:text/html`, `<iframe>`, `<svg/onload>`, `style=` con
URL, cierres de etiqueta sueltos y contenido legítimo (tablas, listas,
`class`, `alt`, `width`, `<details open>`).

**Pendiente al instalar las extensiones**: repetir la misma batería con la
ruta `DOMDocument` (`filtrarDOM()`), que es la que se usa en XAMPP.
