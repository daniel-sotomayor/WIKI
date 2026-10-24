# Catálogo de helpers (`backend/lib/`)

Toda la lógica reutilizable vive acá. Las pantallas sólo orquestan.
Firmas exactas en cada archivo; este catálogo es el mapa de navegación.

## Seguridad y sesión

| Función | Archivo | Qué hace |
|---|---|---|
| `csrfToken()` / `verifyCsrf()` | `security.php` | Token por sesión; `verifyCsrf()` aborta 403 si falta o no coincide (todo POST) |
| `flash()` / `obtenerFlash()` | `security.php` | Mensaje de una sola lectura para la siguiente página |
| `redirigir()` | `security.php` | `Location` + flash en una sola llamada |
| `ipCliente()` | `security.php` | IP real (soporta proxies X-Forwarded-For) |
| `logSeguridad()` | `security.php` | Escribe en `eventos_seguridad` |
| `contarEventosRecientes()` | `security.php` | Ventana deslizante para límite de intentos de login |
| `login()` / `logout()` | `auth.php` | Autentica (hash + intentos + `ultimo_acceso`) o destruye la sesión |
| `isLoggedIn()` / `currentUser()` | `auth.php` | Estado de sesión |
| `requireLogin()` / `requireRole()` | `auth.php` | Guard de pantalla: redirige o 403 |
| `accesoDenegado()` / `paginaNoEncontrada()` | `auth.php` | Respuestas 403 / 404 y terminan |
| `destruirSesion()` | `auth.php` | Regenera ID y limpia datos |
| `passwordValida()` | `auth.php` | ≥10 caracteres con letra y dígito |
| `contarAdminsActivos()` | `auth.php` | Evita quedarse sin administradores |

## Permisos

| Función | Qué hace |
|---|---|
| `nivelRol()` | Número 1–4 del rol (lector→admin) |
| `puede($capacidad)` | Consulta la matriz única `CAPACIDADES` |
| `requierePermiso($capacidad)` | Guard: 403 + log si no puede |
| `puedeEditarPropio($registro)` | Dueño o rol ≥ supervisor |

## Entrada y validación (`validate.php`)

| Función | Qué hace |
|---|---|
| `saneaGET()` / `saneaPOST()` | Lee y recorta entrada (2.º parámetro = longitud máxima, defecto 300) |
| `entrada()` | Origen genérico $_GET/$_POST |
| `entero()` | Entero con defecto seguro |
| `escaparLike()` | Escapa `%`, `_` y `\` para LIKE **sin** cláusula ESCAPE |
| `enLista()` | Whitelist de valores (estados, roles…) con defecto |
| `esFechaValida()` | Fecha `dd/mm/aaaa` o ISO real |
| `validar()` | Motor de reglas: `requerido`, `email`, `min`, `max`, `fecha`, `unico`… |
| `pintarErrores()` | Bloque HTML de errores del formulario |
| `valorPOST()` | Valor con defecto para rellenar formularios |

## Saneamiento de HTML (`sanitize.php`)

| Función | Qué hace |
|---|---|
| `sanearHTML()` | Limpieza vía DOMDocument con allowlist de etiquetas/atributos (XSS) |
| `filtrarDOM()` / `filtrarNodos()` | Núcleo del filtrado recursivo |
| `filtrarAtributo()` / `filtrarURL()` | Valida `href`, `src`, `style`, etc. (bloquea `javascript:`) |
| `filtrarSinDOM()` | Fallback regex cuando no hay extensión DOM |
| `htmlATexto()` | Convierte HTML a texto plano (resúmenes, asuntos de correo) |

## Render (`render.php`)

| Función | Qué hace |
|---|---|
| `abrirPagina()` / `cerrarPagina()` | Layout con `partials/header.php` y `footer.php` |
| `migaDePan()` | Breadcrumbs |
| `badgeEstado()` / `badgeRol()` / `badgeTipo()` | Chips de estado |
| `tarjetaProyecto()` / `tarjetaArticulo()` / `tarjetaNoticia()` | Tarjetas de los listados |
| `galeriaMedia()` | Miniaturas de los adjuntos |
| `botonSiPoe()` → `botonSiPuede()` | Botón de acción sólo si hay capacidad |
| `campo()` / `campoOculto()` / `campoCSRF()` | Formularios (label+input+errores; ocultos; token) |

## Utilidades (`helpers.php`)

| Función | Qué hace |
|---|---|
| `e()` | Escapado HTML de salida (siempre) |
| `slugify()` / `slugUnico()` | Slugs ASCII únicos para URLs |
| `url()` / `urlMedia()` | URLs del sitio / de `media.php` |
| `fecha()` | Formatea fechas legibles |
| `bytes()` | 1536 → "1.5 KB" |
| `resumir()` | Recorta con puntos suspensivos |
| `icono()` | SVG inline (sin librerías externas) |

## Archivos (`upload.php`)

| Función | Qué hace |
|---|---|
| `infoArchivo()` | MIME real (finfo), tamaño, extensión |
| `validarSubida()` | Extensiones/MIME permitidos, límites de tamaño |
| `guardarArchivo()` | Mueve a `storage/uploads/<tipo>/` con nombre aleatorio |
| `adjuntarMedia()` | Crea la fila en `media` |
| `subirAdjuntos()` | Pipeline completo proyecto/noticia/artículo (`normalizarFiles` unifica múltiple y simple) |
| `listarMedia()` / `mediaPrincipal()` / `portadasMedia()` | Consultas de adjuntos |
| `eliminarMedia()` / `eliminarMediaDe()` | Borra fila **y** archivo del disco (obligatorio al borrar entidades) |

## Correo (`mailer.php`)

| Función | Qué hace |
|---|---|
| `enviarMail()` | SMTP si hay `SMTP_PASS`; si no, `.eml` en `storage/mail/` |
| `guardarCorreoEnArchivo()` | Escribe el correo con cabeceras RFC 5322 |
| `ultimoCorreoArchivo()` | Último `.eml` generado (diagnóstico/pruebas) |
| `enviarCorreoContacto()` / `enviarConfirmacionContacto()` | Flujo del formulario de contacto |

## Paginación (`paginate.php`)

| Función | Qué hace |
|---|---|
| `paginar()` | Calcula páginas/offsets; **devuelve enteros ya saneados** para LIMIT |
| `htmlPaginacion()` | Bloque de enlaces con el estado actual |
| `resumenPaginacion()` | "Mostrando X–Y de Z" |

## Sitio y plantillas

- `config_sitio.php`: `configObtener()`, `configGuardar()`,
  `modoMantenimiento()` (caché por petición, tolerante a tabla ausente).
- `plantillas_articulos.php`: `plantillasArticulo()`,
  `plantillaHTML($tipo)` — esqueleto HTML por tipo documental.
- `tags.php`: `sincronizarTags()`, `tagsDeArticulo()`.
