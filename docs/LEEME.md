# TelecomWiki — Manual de instalación y uso

**Sistema de Gestión y Estandarización de Documentación Técnica**
para los proyectos de Telecom Venezuela, C.A.

Proyecto académico · TSU en Informática · Universidad Politécnica Territorial
«Mariscal Sucre» · Tutor: Alfredo Olivero.

---

## 1. Qué es este sistema

TelecomWiki es un wiki técnico que centraliza, versiona y estandariza la
documentación de los proyectos de la empresa:

- **Proyectos** con hitos, avance porcentual y estado.
- **Artículos** vinculados a cada proyecto, con historial de versiones y
  etiquetas (tags) navegables.
- **Noticias** institucionales con cola de moderación.
- **Multimedia** (imágenes, videos, documentos) adjunta a cualquier contenido.
- **Panel de administración** para usuarios, proyectos, contenidos y mensajes
  de contacto.
- **Registro de eventos de seguridad** y control de acceso por roles.

Stack: **PHP 8.1+ · MySQL/MariaDB · HTML5 · CSS3 · JavaScript puro**
(sin frameworks, sin librerías de frontend; PHPMailer viene incluido en
`backend/vendor/`).

### Documentación del proyecto

| Documento | Contenido |
|---|---|
| [`LEEME.md`](LEEME.md) | Este manual: instalación, uso y solución de problemas |
| [`VISTA-GENERAL-DEL-SISTEMA.md`](VISTA-GENERAL-DEL-SISTEMA.md) | El sistema a fondo: tecnologías, arquitectura, configuración, BD, seguridad y operación en un solo documento |
| [`MANUAL-DE-USUARIO.md`](MANUAL-DE-USUARIO.md) | Qué hace cada pantalla, flujos (moderación, versiones) y recetas rápidas |
| [`ARQUITECTURA.md`](ARQUITECTURA.md) | Capas, flujo de una petición, módulos y decisiones de diseño |
| [`GUIA-DESARROLLADOR.md`](GUIA-DESARROLLADOR.md) | Convenciones, recetas para extender el código y checklist de entrega |
| [`DISENO.md`](DISENO.md) | Sistema de diseño: tokens, componentes, responsive y accesibilidad |
| [`MODELO-DE-DATOS.md`](MODELO-DE-DATOS.md) | Tabla por tabla, claves, relaciones e índices |
| [`DIAGRAMAS.md`](DIAGRAMAS.md) | Entidad-relación, casos de uso, procesos, secuencia y estados (Mermaid; SVG listos en `docs/diagramas/`) |
| [`CODIGO.md`](CODIGO.md) | Catálogo de funciones de la capa de helpers |
| [`GLOSARIO.md`](GLOSARIO.md) | Términos del sistema (capacidad, media, slug, migración…) |
| [`PRUEBAS.md`](PRUEBAS.md) | Cómo correr y escribir pruebas |
| [`OPERACION.md`](OPERACION.md) | Despliegue, respaldos, migraciones y correo |
| [`SEGURIDAD.md`](SEGURIDAD.md) | Modelo de amenazas y medidas aplicadas |
| [`CAMBIOS.md`](CAMBIOS.md) | Historial de desarrollo y verificaciones |

**Por dónde empezar**: si vas a *usar* el sistema →
[`MANUAL-DE-USUARIO.md`](MANUAL-DE-USUARIO.md); si vas a *instalarlo* →
sigue éste; si vas a *programar* →
[`GUIA-DESARROLLADOR.md`](GUIA-DESARROLLADOR.md).

---


## 2. Requisitos

| Componente | Versión mínima | Notas |
|---|---|---|
| PHP | 8.1 – 8.4 | XAMPP ya la trae; probado en 8.3.6 y 8.4.27 |
| Extensión `pdo_mysql` | — | conexión a MySQL |
| Extensión `mbstring` | — | tildes, ñ, longitud de cadenas |
| Extensión `xml` (DOM) | — | saneado de HTML enriquecido |
| Extensión `fileinfo` | — | detección MIME de subidas |
| MySQL / MariaDB | 5.7 / 10.2 | XAMPP incluye MariaDB |
| Apache | 2.4 | con `mod_headers` y `mod_authz_core` |
| phpMyAdmin | — | para importar la base (opcional) |

Comprobación rápida de extensiones desde la terminal:

```bash
php -m | grep -E "pdo_mysql|mbstring|dom|fileinfo"
```

La misma comprobación (versión, extensiones, límites de subida, permisos
de la carpeta de archivos y conexión a la base) la hace el panel en
**Panel → Diagnóstico del sistema**: es lo primero que conviene abrir
tras instalar.

Si falta alguna en Ubuntu/Debian:

```bash
sudo apt update && sudo apt install -y mariadb-server \
     php8.3-mysql php8.3-mbstring php8.3-xml
sudo systemctl start mariadb
```

---

## 3. Instalación paso a paso (XAMPP)

1. **Copiá la carpeta** `telecom_wiki` dentro de `C:\xampp\htdocs\` (Windows)
   o `/opt/lampp/htdocs/` (Linux).
2. **Iniciá Apache y MySQL** desde el panel de XAMPP.
3. **Importá la base de datos**:
   - Abrí `http://localhost/phpmyadmin`
   - *Importar* → elegí `telecom_wiki/backend/database/database.sql` →
     *Continuar*.
   - El script crea la base `telecom_wiki`, las 14 tablas, las 4 vistas
     y los datos de ejemplo (no hace falta crear la base antes).
4. **Verificá la conexión**: abrí
   `http://localhost/telecom_wiki/frontend/` — debe mostrarse la portada.
5. **Ingresá** en `http://localhost/telecom_wiki/frontend/login.php`:

   | Usuario | Contraseña | Rol |
   |---|---|---|
   | `admin` | `Admin2026!` | administrador |
   | `supervisor` | `Demo2026!` | supervisor |
   | `editor` | `Demo2026!` | editor |
   | `lector` | `Demo2026!` | lector |

   > **Cambialo apenas entres** (Usuarios → Editar → contraseña). Las
   > cuentas de prueba son públicas: no se usan en un despliegue real.

6. **Entrá al panel** (`admin` / `Admin2026!`) y abrí
   **Diagnóstico del sistema**: te dice en 30 segundos si PHP, las
   extensiones, los permisos de `backend/storage` y la base de datos
   están bien. Cambiá la contraseña de prueba apenas entres.
7. Si el servidor Apache está en una ruta distinta de
   `http://localhost/telecom_wiki/`, ajustá la constante `SITE_URL` en
   `backend/config/config.php` (o exportá la variable de entorno `SITE_URL`).

### php.ini que suele hacer falta en XAMPP

`C:\xampp\php\php.ini` (revisá que la extensión esté activa quitando
el `;` del inicio de la línea y reiniciá Apache):

```ini
extension=pdo_mysql
extension=mbstring
extension=xml          ; trae DOMDocument
extension=fileinfo
upload_max_filesize = 50M
post_max_size = 60M
memory_limit = 256M
date.timezone = America/Caracas
```

Los límites de subida no pueden ser menores que `UPLOAD_MAX_FILESIZE`
(50 MB, ver `backend/config/config.php`); el diagnóstico del panel los
compara y avisa si alguno queda corto.

### Linux sin XAMPP (MariaDB de sistema)

En Ubuntu/Debian el `root` de MariaDB se autentica por *socket* y **no**
acepta conexiones TCP con contraseña vacía, así que el sitio necesita un
usuario propio de la base:

```bash
sudo apt install -y mariadb-server php8.3-mysql php8.3-mbstring php8.3-xml
sudo systemctl start mariadb

sudo mariadb -e "CREATE DATABASE IF NOT EXISTS telecom_wiki
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS 'telecom_wiki'@'localhost'
    IDENTIFIED BY 'telecom2026';
  GRANT ALL PRIVILEGES ON telecom_wiki.* TO 'telecom_wiki'@'localhost';"

mysql -u telecom_wiki -ptelecom2026 telecom_wiki \
    < telecom_wiki/backend/database/database.sql
```

O exportá esas credenciales al iniciar el servidor (ver §9) o a Apache
(`/etc/apache2/envvars`). En XAMPP no hace falta nada de esto: los
valores por defecto (`root` sin contraseña) ya apuntan a su MySQL.

Las cuatro extensiones son obligatorias: `pdo_mysql` (base de datos),
`mbstring` (`saneaGET()`), `xml`/DOM (saneado de HTML) y `fileinfo`
(tipos MIME de archivos subidos).

### Configuración por variables de entorno

Todo lo sensible puede salir del entorno y no del código:

| Variable | Por defecto |
|---|---|
| `APP_ENV` | `development` (`production` desactiva los mensajes de error) |
| `SITE_URL` | `http://localhost/telecom_wiki/frontend` |
| `SITE_NAME` | `TelecomWiki` |
| `DB_HOST` / `DB_PORT` / `DB_USER` / `DB_PASS` / `DB_NAME` | `127.0.0.1` / `3306` / `root` / *(vacía)* / `telecom_wiki` |

El correo saliente se configura en `backend/config/mail_config.php`
(PHPMailer vendeado, sirve con un SMTP de Mailtrap/Gmail para probar).

---

## 4. Estructura del proyecto

```
telecom_wiki/
├── index.php               Redirección a frontend/
├── .htaccess               Protecciones de la raíz (XAMPP/Apache)
├── dev-router.php          Router para el servidor de desarrollo de PHP
├── docs/                   Esta documentación (bloqueada por web)
├── backend/
│   ├── config/
│   │   ├── config.php      Constantes del sitio
│   │   ├── db.php          Conexión PDO (MySQL)
│   │   └── mail_config.php Credenciales SMTP
│   ├── database/
│   │   └── database.sql    Esquema + datos de ejemplo (importable)
│   ├── lib/                Toda la lógica (ver §5)
│   ├── tests/              Pruebas automatizadas (ver §12)
│   ├── storage/uploads/    Archivos subidos — FUERA del alcance web
│   └── vendor/             PHPMailer (composer)
├── frontend/
│   ├── *.php               Pantallas públicas (wiki, proyectos, noticias…)
│   ├── cuenta.php          Mi cuenta: datos y contraseña propios
│   ├── media.php           Entrega de archivos subidos (valida todo)
│   ├── admin/              Panel: usuarios, contenido, bitácora,
│   │                       sistema.php (diagnóstico del entorno)
│   ├── partials/           header.php / footer.php (bloqueados por web)
│   ├── css/                base, layout, components, pages, admin
│   ├── js/                 main.js, editor.js, articulo_form.js
│   └── assets/img/         Favicon y estáticos
```

---

## 5. Cómo está organizado el código (capa de helpers)

Toda pantalla arranca con un único punto de entrada:

```php
<?php require_once __DIR__ . '/../backend/lib/bootstrap.php';
```

`backend/lib/bootstrap.php` carga, en orden:

| Archivo | Responsabilidad |
|---|---|
| `security.php` | cabeceras HTTP + CSP con nonce, sesión, `logSeguridad()` |
| `db.php` | conexión PDO (`getDB()`, `dbUnica()`, `dbValor()`) |
| `helpers.php` | `e()`, `url()`, `urlMedia()`, `slugify()`, `icono()`, `fecha()`… |
| `auth.php` | `login()`, `logout()`, `csrfToken()`, `flash()`, `redirigir()` |
| `permisos.php` | matriz única de capacidades por rol |
| `validate.php` | `saneaGET()`, `saneaPOST()`, `validar()` (reglas) |
| `paginate.php` | `paginar()`, `htmlPaginacion()` |
| `sanitize.php` | `sanearHTML()` (lista blanca), `htmlATexto()` |
| `render.php` | `campo()`, tarjetas, `badgeEstado()`, `migaDePan()`… |
| `upload.php` | `subirAdjuntos()`, `adjuntarMedia()`, `eliminarMediaDe()` |
| `tags.php` | `sincronizarTags()`, `tagsDeArticulo()` |

Las pantallas se estructuran siempre igual:

```php
$pageTitle = 'Título de la pestaña';
$cssExtra  = 'admin.css';     // opcional
$jsExtra   = 'editor.js';     // opcional, admite 'a.js, b.js'
abrirPagina();                // imprime header.php
// … contenido …
cerrarPagina();                // imprime footer.php
```

---

## 6. URLs

Se usan **URLs planas con parámetros**, sin `mod_rewrite`:

- `articulo.php?slug=arquitectura-general-del-sistema`
- `proyecto.php?slug=batalla-bicentenaria`
- `wiki.php?tag=python&q=busqueda&estado=publicado`
- `media.php?f=9f2a…c1.png` (entrega de archivos con validación de MIME)

Los enlaces se construyen con `url('ruta')` (prefija `SITE_URL`) y
`urlMedia('archivo')`.

---

## 7. Roles y permisos

Roles jerárquicos: **lector (1) < editor (2) < supervisor (3) < admin (4)**.

La matriz está en un solo lugar: `backend/lib/permisos.php` (`CAPACIDADES`).

| Capacidad | lector | editor | supervisor | admin |
|---|:-:|:-:|:-:|:-:|
| ver contenidos | ✓ | ✓ | ✓ | ✓ |
| crear/editar artículos propios | — | ✓ | ✓ | ✓ |
| moderar y publicar noticias | — | — | ✓ | ✓ |
| gestionar proyectos y hitos | — | ✓ | ✓ | ✓ |
| gestionar usuarios | — | — | — | ✓ |
| ver la bitácora de seguridad | — | — | — | ✓ |

Reglas para quien extienda el código:

```php
requierePermiso('articulo.crear');   // corta con accesoDenegado() si no
if (puede('noticia.moderar')) { … }  // siempre por capacidad, nunca por rol
puedeEditarPropio($registro);        // autor o rol >= supervisor
```

> En las vistas **nunca** se compara contra `['editor','admin']` a mano:
> eso duplica la política y termina divergiendo.

Cualquier usuario, sin importar su rol, gestiona sus datos y su
contraseña desde **Mi cuenta** (enlace en la cabecera, o
`frontend/cuenta.php`): nombre, apellido y correo se editan ahí mismo;
la contraseña pide la actual, fuerza fortaleza (≥10 con letra y dígito)
y al cambiarse regenera el ID de sesión.

---

## 8. Editor de texto

Los campos con clase `editor-ric` reciben una barra de herramientas propia
(`frontend/js/editor.js`) que sólo inserta etiquetas permitidas:
encabezados, negrita, cursiva, listas, cita, código, enlace, imagen, tabla
y línea horizontal. Atajos: **Ctrl+B**, **Ctrl+I**.

El contenido viaja como HTML y se sanea dos veces (`backend/lib/sanitize.php`):
al guardar y al mostrar. Ver `docs/SEGURIDAD.md`.

---

## 9. Servidor de desarrollo (sin XAMPP)

Para probar sin Apache usá el lanzador incluido:

```bash
./telecom_wiki/dev-server.sh            # o ./dev-server.sh 8080
```

y abrí `http://127.0.0.1:8000/`. El script exporta las credenciales de
la base (usuario `telecom_wiki`, ver §3) y arranca el router:

```bash
cd ..                                   # carpeta que contiene telecom_wiki/
php -S 127.0.0.1:8000 -t telecom_wiki telecom_wiki/dev-router.php
```

Si preferís el comando largo, exportá a mano `DB_USER`/`DB_PASS` antes
(si no, `db.php` intenta conectarse como `root` sin contraseña).

El router también acepta **URLs cortas** (`/wiki.php`, `/login.php`):
si el archivo no existe en la raíz lo redirige (302) a su ruta
canónica dentro de `frontend/`, así no hay que recordar el prefijo.

El router replica las protecciones del `.htaccess` (bloquea `backend/`,
`docs/`, `storage/`, parciales, archivos `.sql`/`.md`, el listado de
directorios). **En XAMPP real no se usa**: manda el `.htaccess` de la raíz.

---

## 10. Multimedia

- Se pueden adjuntar archivos desde **proyectos**, **artículos** y
  **noticias** (hasta 6 por publicación, múltiple a la vez).
- Las subidas se guardan en `backend/storage/uploads/` (**dentro de
  `backend/`, que Apache bloquea**), nunca en `frontend/`.
- Se aceptan sólo las extensiones/MIME listados en `UPLOAD_MIME`
  (`backend/config/config.php`), con nombre aleatorio de 32 hex.
- Se entregan por `media.php?f=…`, que valida el nombre, el MIME real
  almacenado y el tipo de archivo (admite *Range* para `<video>`).
- Al borrar un proyecto o una noticia hay que llamar
  `eliminarMediaDe('proyecto', $id)` — `media` no tiene clave foránea.

---

## 11. Solución de problemas

| Síntoma | Causa probable |
|---|---|
| «El sitio no está disponible» (503) | MySQL apagado o base `telecom_wiki` sin importar |
| `Call to undefined function mb_strlen()` | falta `php8.3-mbstring` |
| `Call to undefined function DOMDocument` | falta `php8.3-xml` |
| `could not find driver` | falta `php8.3-mysql` (pdo_mysql) |
| Página sin estilos / scripts bloqueados | CSP: revisá que no haya `style=` ni `onclick=` en línea |
| `403` en `/backend/…` o `/docs/…` | es correcto: están protegidos |
| Subidas rechazadas | superan `UPLOAD_MAX_FILESIZE` (50 MB) o el MIME no está en la lista |
| Login bloqueado | 5 intentos fallidos en 10 min → esperá la ventana o revisá `eventos_seguridad` |

---

## 12. Pruebas y verificación

Hay un corredor de pruebas propio (sin dependencias externas):

```bash
php backend/tests/run.php
```

Ejecuta las cuatro suites del directorio y devuelve código de salida `0`
si todo pasa (ver [`PRUEBAS.md`](PRUEBAS.md) para el detalle):

| Suite | Qué comprueba |
|---|---|
| `sanitize_test.php` | 16 payloads XSS no sobreviven y el HTML legítimo se conserva (70 pruebas) |
| `estructura_test.php` | sintaxis PHP, CSP (sin handlers/`style=` inline), clases CSS, accesibilidad (`alt`, `<h1>`), CSRF en todo POST, enlaces `url()` existentes |
| `correo_test.php` | transporte de correo por archivo: `.eml` con cabeceras correctas |
| `http_test.php` | recorrido end-to-end con `php -S` + curl: login, CSRF 403, subidas, `media.php`, moderación, mantenimiento 503 (se omite si no hay BD) |

Verificación rápida suelta:

```bash
# sintaxis de todo el PHP (sin vendor)
find telecom_wiki -path '*/vendor' -prune -o -name '*.php' -print \
  | xargs -n1 php -l

# sintaxis de los JS (si tenés node instalado)
for j in telecom_wiki/frontend/js/*.js; do node --check "$j"; done
```
