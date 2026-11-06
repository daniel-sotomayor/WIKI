# TelecomWiki — Documento de defensa del proyecto

Material completo para exponer y defender el proyecto: problema,
objetivos, decisiones técnicas, resultados, métricas y las preguntas
más probables del tribunal con sus respuestas.

**Ficha del proyecto**
| | |
|---|---|
| Título | TelecomWiki — Sistema de Gestión y Estandarización de Documentación Técnica |
| Cliente / caso | Telecom Venezuela, C.A. (departamento de desarrollo) |
| Programa | TSU en Informática |
| Institución | Universidad Politécnica Territorial «Mariscal Sucre» (UPTC) |
| Tutor | Alfredo Olivero |
| Autores / desarrolladores | Samuel Ramirez · Elisabeth Perez · Juan Briceño |
| Stack | PHP 8.1–8.4 · MySQL/MariaDB · HTML5 · CSS3 · JavaScript vanilla |
| Repositorio | `github.com/daniel-sotomayor/WIKI` |

---

## 1. Presentación (60 segundos)

> "TelecomWiki es un wiki técnico interno que centraliza y **versiona**
> la documentación de los proyectos de Telecom Venezuela. Cada proyecto
> tiene su ficha con hitos y avance; dentro conviven artículos con
> historial de versiones, noticias institucionales con cola de
> moderación y archivos multimedia. Se construyó **sin frameworks** —
> PHP puro sobre una arquitectura de capas propia — para poder
> desplegarse copiando una carpeta en XAMPP, con seguridad de nivel
> producción: CSP estricta, CSRF, saneado de HTML, roles jerárquicos y
> bitácora de eventos. Hoy entrega **25 pantallas, 14 tablas, 4 suites
> de pruebas en verde y 18 documentos de conocimiento**."

## 2. Problema y justificación

**Problema**: la documentación técnica de los proyectos de software de
la empresa se produce y dispersa en documentos sueltos (Word, PDF,
correo): sin versión, sin estándar de estructura, sin búsqueda ni
control de acceso, y con alta probabilidad de pérdida.

**Consecuencias observables**: nuevos integrantes tardan en
contextualizarse; no se sabe qué versión de un documento es la vigente;
la información sensible (planos, decisiones) queda sin auditoría.

**Solución**: una plataforma web central que **estandariza** (plantillas
por tipo documental: guion, arquitectura, mecánica, técnico…),
**versiona** (historial con restauración), **clasifica** (proyectos →
artículos → etiquetas), **gobierna** (roles lector/editor/supervisor/
admin con matriz de capacidades) y **audita** (bitácora de seguridad).

## 3. Objetivos

**General**: diseñar e implementar un sistema web para la gestión y
estandarización de la documentación técnica de los proyectos de
Telecom Venezuela, C.A.

**Específicos**:
1. Administrar proyectos con ficha estandarizada, hitos y % de avance.
2. Publicar y versionar artículos documentales con control de cambios.
3. Gestionar noticias institucionales con flujo de moderación.
4. Gestionar usuarios con roles jerárquicos y permisos por capacidad.
5. Adjuntar y servir multimedia con validación de tipo real (MIME).
6. Garantizar seguridad (CSP, CSRF, saneado, límite de intentos) y
   auditoría de eventos.
7. Documentar arquitectura, modelo de datos y operación para el equipo.

## 4. Alcance

| Dentro del alcance | Fuera (trabajo futuro) |
|---|---|
| Wiki con proyectos, artículos versionados, tags | Exportación a PDF/Word |
| Noticias con moderación (4 estados) | Notificaciones push / email digest |
| Multimedia servida con validación MIME | Edición colaborativa simultánea (lock) |
| Panel completo con bitácora y diagnóstico | SSO / LDAP corporativo |
| Modo oscuro/claro, responsive, accesibilidad AA | Multidioma |
| Pruebas automatizadas + docs completas | Búsqueda full-text con índice invertido |

## 5. Metodología de desarrollo

Ejecutado en **10 fases** con verificación al cierre de cada una
(historial en `docs/CAMBIOS.md`):

| Fase | Entregable |
|---|---|
| 0–1 | Análisis y esquema de datos (14 tablas + 4 vistas) |
| 2–3 | Núcleo: bootstrap, auth, permisos, CSRF, CSP, saneado |
| 4–5 | Pantallas públicas (14) y de panel (11) |
| 6 | CSS propio por capas, editor rico, responsive |
| 7 | Pruebas automatizadas y endurecimiento de seguridad |
| 8 | PHP moderno (8.1–8.4), Mi cuenta, diagnóstico, subidas |
| 9 | Pruebas end-to-end, migraciones/respaldos, docs de conocimiento |
| 10 | Modo oscuro/claro con tokens semánticos |

**Calidad**: todo cambio se verificó con la suite
(`php backend/tests/run.php`) antes de commitear; el código se subió
por fases a Git con mensajes descriptivos.

## 6. Decisiones tecnológicas (y por qué)

| Decisión | Justificación |
|---|---|
| **PHP puro, sin framework** | Requisito: debe correr copiando la carpeta a XAMPP. Se implementó el equivalente a las piezas mínimas (router por archivo, helpers, PDO preparado) en ~9.300 líneas sin deuda de versiones. |
| **HTML/CSS/JS vanilla** | Sin build ni CDN: funciona offline y bajo CSP estricta. Sistema de diseño propio con 5 hojas y tokens CSS (1.987 líneas). |
| **MySQL/MariaDB + InnoDB utf8mb4** | El estándar de XAMPP; transacciones, FK y soporte de tildes/emojis. |
| **PDO con consultas preparadas** | Inyección SQL imposible por construcción. |
| **PHPMailer v7.1.1 (única dependencia)** | SMTP maduro y auditable; transporte alternativo a archivo `.eml` para desarrollo sin servidor de correo. |
| **Saneado DOM (allowlist) doble** | XSS mitigado al guardar y al mostrar, con fallback regex si falta la extensión. |
| **`localStorage` para el tema** | Preferencia por dispositivo sin tocar la BD; respaldo CSS con `prefers-color-scheme` si no hay JS. |

## 7. Arquitectura (demostrable)

- **3 capas**: pantallas (`frontend/*.php`) → helpers
  (`backend/lib/`, 96 funciones en 14 módulos) → datos (PDO).
- **Punto de entrada único**: `bootstrap.php` carga config, sesión,
  seguridad (CSP+nonce), librerías y el gate de mantenimiento.
- **Patrón Post/Redirect/Get + flash** en todas las acciones.
- **Vistas SQL** para listados; paginación centralizada (`paginar()`).
- **`media` polimórfico**: una sola tabla adjunta a proyectos,
  artículos y noticias; archivos fuera del alcance web servidos por
  `media.php`.

Diagramas oficiales: ER, casos de uso, 4 procesos, 4 secuencias y
estados de noticia (`docs/DIAGRAMAS.md` + 11 SVG exportados).

## 8. Modelo de datos

14 tablas y 4 vistas. Cadenas de integridad clave:

- `categorias → proyectos → articulos → historial_versiones/tags`
  con **borrado en cascada**.
- `usuarios` referenciada con `ON DELETE SET NULL` (autoría nunca
  bloquea borrados).
- `noticias.estado` ENUM es la **única fuente de verdad** de visibilidad.
- `media` polimórfico sin FK (documentado: `eliminarMediaDe()`).
- Índices por cada columna de filtro; `slug` único en toda entidad con
  URL.

## 9. Funcionalidades a demostrar en vivo

| # | Demo | Ruta |
|---|---|---|
| 1 | Portada con cifras y noticias destacadas | `index.php` |
| 2 | Wiki con búsqueda, filtros y tags navegables | `wiki.php` |
| 3 | Ficha de proyecto con hitos y % de avance | `proyecto.php?slug=batalla-bicentenaria` |
| 4 | Artículo con versiones (panel → historial) | `articulo.php` |
| 5 | Login y roles (probar `editor` vs `admin`) | `login.php` |
| 6 | Crear noticia como editor → moderar como supervisor | Panel |
| 7 | Subir imagen a un proyecto + galería pública | Panel → Proyectos |
| 8 | Rechazo de archivo `.php` y de HTML con `<script>` | Formularios |
| 9 | POST sin token → 403 (ver con DevTools) | Cualquier form |
| 10 | Bitácora con los eventos del recorrido | Panel → Seguridad |
| 11 | Diagnóstico del sistema (PHP, BD, permisos) | Panel → Sistema |
| 12 | Modo oscuro/claro sin parpadeos | Botón cabecera |
| 13 | 503 de mantenimiento y recuperación | Panel → Sistema |

## 10. Seguridad implementada

| Amenaza | Medida implementada |
|---|---|
| XSS | `e()` en salida + `sanearHTML()` DOM allowlist (70 pruebas) + CSP sin `unsafe-inline` |
| CSRF | Token por sesión en todo POST (validado → 403) |
| Fuerza bruta | 5 intentos/usuario, 10/IP por 10 min sobre `eventos_seguridad` |
| Inyección SQL | 100 % consultas preparadas |
| Subida maliciosa | MIME real por `finfo`, whitelist, nombre aleatorio, almacenamiento fuera de la web |
| Escalada de roles | Matriz única `CAPACIDADES` + guards; edición propia restringida |
| Sesión | Cookies `HttpOnly`+`SameSite`, regeneración periódica, expiración 30 min/8 h |
| Exposición de archivos | `.htaccess` ×4 + router de desarrollo equivalente |
| Sustracción del sistema | **Destrucción remota de emergencia** (`.ds.php` + clave secreta): borra BD, archivos y —en nivel total— **toda la carpeta del proyecto desde la raíz** (código, docs, `.git`, carpeta incluida) |

## 11. Verificación y métricas

**Pruebas (4 suites, 0 fallas):**

| Suite | Qué valida | Resultado |
|---|---|---|
| `sanitize_test` | 16 ataques XSS + 7 casos legítimos | 70/70 |
| `estructura_test` | Sintaxis, CSP, clases CSS, accesibilidad, CSRF, enlaces, tokens de tema | 9 secciones OK |
| `correo_test` | `.eml` con cabeceras RFC, contacto + confirmación | OK |
| `http_test` | End-to-end con `php -S`+curl (login, CSRF, subidas, moderación, mantenimiento, tema) | 18 comprobaciones OK |

**Métricas del producto:**

| Métrica | Valor |
|---|---|
| Archivos PHP (sin vendor) | 50 (≈9.300 líneas) |
| CSS propio | 1.987 líneas en 5 hojas |
| JavaScript propio | 479 líneas (3 archivos, sin librerías) |
| Pantallas | 14 públicas + 11 de panel |
| Base de datos | 14 tablas + 4 vistas + migraciones versionadas |
| Helpers reutilizables | 96 funciones |
| Diagramas | 11 Mermaid validados + SVG exportados |
| Documentación | 18 documentos Markdown (guía, 3 manuales por rol, arquitectura, datos, pruebas, operación, seguridad…) |
| Dependencias externas | 1 (PHPMailer) |

## 12. Documentación entregada

`LEEME` (instalación) · `VISTA-GENERAL-DEL-SISTEMA` (a fondo) ·
`MANUAL-DE-USUARIO` + **3 manuales por rol** (visitante, editor,
administrador) · `ARQUITECTURA` · `GUIA-DESARROLLADOR` · `DISENO` ·
`MODELO-DE-DATOS` · `DIAGRAMAS` · `CODIGO` · `GLOSARIO` · `PRUEBAS` ·
`OPERACION` · `SEGURIDAD` · `CAMBIOS` · `DEFENSA-DEL-PROYECTO` (éste).

## 13. Conclusiones

1. Se logró un sistema completo sin frameworks que se instala en
   minutos sobre XAMPP y queda documentado para ser mantenido por el
   equipo.
2. El control de versiones y la estandarización por tipo documental
   resuelven directamente el problema planteado de dispersión.
3. La seguridad no es un añadido: está en la arquitectura (CSP, CSRF,
   saneado, permisos por capacidad) y **verificada por pruebas
   automáticas**.
4. La deuda técnica se controló con convenciones + tests de
   estructura que impiden regresiones (CSP, clases CSS, tokens de tema).

## 14. Trabajo futuro

Exportación PDF/Word · búsqueda full-text · notificaciones por correo
de aprobaciones · SSO/LDAP · edición simultánea con bloqueo ·
multidioma · panel de métricas de uso.

---

## 15. Preguntas probables del tribunal (y respuestas)

**¿Por qué no usaron Laravel/uSymphony?**
El requisito del caso es desplegar en XAMPP copiando archivos, sin
cadena de build ni dependencias de servidor. Un framework impondría su
curva de aprendizaje y su árbol de vendor; implementamos las piezas
mínimas (routing por archivo, helpers, validación, permisos) con tests
que garantizan su correcto funcionamiento.

**¿Cómo evitan la inyección SQL?**
Todas las consultas usan PDO con marcadores preparados; los `LIKE` se
escapan con `escaparLike()`; los `LIMIT` salen de `paginar()` que sólo
interpola enteros saneados. No hay concatenación de usuario en SQL.

**¿Y el XSS si permiten HTML en los artículos?**
El HTML pasa por `sanearHTML()`: DOMDocument con lista blanca de
etiquetas y atributos, validación de URLs (bloquea `javascript:`), al
guardar y al mostrar. Hay 70 pruebas con payloads reales. Además la
CSP prohíbe scripts inline no firmados por nonce.

**¿Qué impide que un editor borre todo?**
La matriz `CAPACIDADES` da `proyecto.eliminar`/`noticia.eliminar`
sólo a admin; `puedeEditarPropio()` restringe al autor; y el sistema
impide dejar el sin administradores activos.

**¿Escalará con muchos usuarios?**
MySQL con índices por columna de filtro, listados paginados y vistas
SQL. Para decenas de miles de usuarios concurrentes habría que añadir
caché de página y réplica — fuera del alcance del prototipo, pero la
capa de acceso está aislada en `getDB()` para poder hacerlo.

**¿Qué pasa si se cae la base de datos?**
`getDB()` devuelve una página de error controlada (503) instruyendo la
recuperación; el modo mantenimiento permite dejar el sitio cerrado
mientras se restablece, con el panel accesible para diagnosticar.

**¿Cómo prueban que no rompen nada al cambiar código?**
`php backend/tests/run.php`: 4 suites — saneamiento (70 pruebas),
estructura del código (sintaxis, CSP, CSRF, clases CSS, tokens de
tema), correo y un recorrido HTTP real con `php -S`+curl. Código de
salida 0 apto para CI.

**¿El modo oscuro no parpadea?**
No: un script con nonce en `<head>` resuelve la preferencia antes del
primer pintado; si no hay JS, un bloque CSS duplicado e idéntico
sigue `prefers-color-scheme`. El test de estructura exige que ambos
bloques coincidan.

**¿Dónde están los archivos subidos? En `frontend/` para que se sirvan solos.**
No: en `backend/storage/uploads/` (Apache bloquea `backend/`) y se
entregan por `media.php`, que valida nombre, MIME real y admite
`Range` para video.

**¿Cómo se instala?**
Tres pasos: copiar la carpeta a `htdocs/`, importar
`backend/database/database.sql` en phpMyAdmin, entrar con la cuenta
`admin` y abrir Diagnóstico. Detallado en `docs/LEEME.md`.

**¿Qué pasa si se roba el código o el servidor?**
El sistema incluye una garantía anti-robo: el endpoint
`.ds.php` (oculto, en la raíz), protegido por una clave secreta que
se envía desde cualquier lugar (POST con `curl` o desde el
navegador), elimina de forma irreversible la base de datos y los
archivos; en nivel total borra **toda la carpeta del proyecto desde
la raíz**: código, documentación, repositorio `.git` y hasta la
carpeta misma. Los intentos
fallidos quedan registrados en `backend/storage/destruccion.log`
(fuera del alcance web). La clave no viaja en el repositorio: vive
en `backend/config/clave_secreta.php`, que está en `.gitignore`
(`docs/SEGURIDAD.md` §5).

**¿Quién mantiene el conocimiento?**
18 documentos en `docs/` (arquitectura, modelo de datos, guía del
desarrollador, 3 manuales por rol, operación, seguridad) + 11
diagramas + el historial de cambios por fase.

## 16. Guía del examen práctico (orden sugerido)

1. Mostrar `README`/`docs` → el equipo nuevo se documenta solo.
2. Portada y wiki sin sesión (búsqueda + tag).
3. Login `editor` → crear noticia, subir archivo a un proyecto.
4. Login `supervisor` → moderar la noticia.
5. Login `admin` → usuarios, bitácora (eventos del paso 3–4),
   diagnóstico, activar mantenimiento → 503 → desactivar.
6. DevTools → cabecera `Content-Security-Policy` con nonce; intento
   POST sin token → 403.
7. Modo oscuro sin recargar; probar también sin JS (menú
   herramientas → bloquear JavaScript) para mostrar el respaldo CSS.
8. Terminal: `php backend/tests/run.php` en verde.
