# Glosario

Términos usados en la documentación y en el código.

| Término | Significado |
|---|---|
| **Bootstrap** | `backend/lib/bootstrap.php`: punto de entrada único que carga config, sesión y librerías en cada petición |
| **Capacidad** | Permiso atómico con forma `dominio.accion` (ej. `noticia.moderar`) definido en la matriz `CAPACIDADES` de `permisos.php`; las pantallas preguntan con `puede()`/`requierePermiso()` |
| **CSP (Content-Security-Policy)** | Cabecera que limita qué scripts/estilos puede ejecutar la página; en este proyecto es estricta (`'self'` + nonce), por eso no hay HTML inline |
| **CSRF / token CSRF** | Mecanismo que impide que otra página envíe POST en tu nombre; todo formulario POST lleva `campoCSRF()` y el backend valida con `verifyCsrf()` (403 si falta) |
| **Diagnóstico** | Pantalla `admin/sistema.php` que comprueba PHP, extensiones, permisos, BD, correo y permite mantenimiento/enviar correo de prueba |
| **Editor rico** | Barra de herramientas propia (`frontend/js/editor.js`) que sólo inserta etiquetas HTML permitidas |
| **Entidad (media)** | Propietario de un archivo adjunto: `articulo`, `noticia` o `proyecto` (`entidad_tipo` + `entidad_id`); relación **polimórfica sin FK** |
| **Estado de noticia** | `borrador → pendiente → publicada \| rechazada`; única fuente de verdad de visibilidad (no hay columna `publicada`) |
| **Flash** | Mensaje de una sola lectura (`flash()` → `obtenerFlash()`) que se muestra en la página siguiente a una redirección |
| **Gate de mantenimiento** | Control en `bootstrap.php`: si `config_sitio.mantenimiento = 1`, el público recibe 503 salvo login/logout/media |
| **Hito** | Etapa de seguimiento de un proyecto con `porcentaje_avance`; el promedio alimenta la ficha pública |
| **Historial de versiones** | `historial_versiones`: copia del contenido en cada guardado de artículo; permite restaurar versiones anteriores |
| **Idempotente (DDL)** | Migración SQL que se puede re-ejecutar sin efecto adicional (`CREATE TABLE IF NOT EXISTS`, etc.) |
| **Media / adjunto** | Archivo (imagen/video/documento) subido; se guarda en `backend/storage/uploads/<tipo>/` con nombre aleatorio y se sirve por `media.php` |
| **Migración** | Archivo `backend/database/migraciones/NNNN_*.sql` versionado y registrado en la tabla `migraciones` con SHA-256 (`bin/migrar.php`) |
| **Nonce** | Valor aleatorio único por petición que firma el `<script>` inline permitido por la CSP |
| **Nonce-script inline** | El único `<script>` sin `src` permitido es el del header, firmado con el nonce |
| **Pantalla / vista** | Archivo `frontend/*.php` que renderiza una URL; no contiene lógica de negocio reutilizable |
| **Post/Redirect/Get** | Patrón usado en todos los POST: actuar → `redirigir()` → la página destino muestra el flash (evita reenvío al recargar) |
| **Respaldo** | Dump gzip de la base + tar de `storage/` (`bin/respaldo.sh`) |
| **Slug** | Versión ASCII-amigable de un título para URLs (`Batalla Bicentenaria` → `batalla-bicentenaria`); único por tabla |
| **Slug único** | `slugUnico()` garantiza que el slug no colisione agregando `-2`, `-3`… |
| **Saneado de HTML** | `sanearHTML()`: limpieza del HTML enriquecido con DOMDocument y lista blanca de etiquetas/atributos (anti-XSS) |
| **Transporte de correo** | Cómo sale el correo: **SMTP** (si hay `SMTP_PASS`) o **archivo** (`.eml` en `storage/mail/`) |
| **Vista (SQL)** | `v_*`: consultas de lectura reutilizadas por panel y sitio (`v_proyectos_resumen`, `v_noticias_publicadas`…) |
| **`eventos_seguridad`** | Bitácora de logins, fallos, moderaciones, etc.; también alimenta el límite de intentos de acceso |
| **`config_sitio`** | Tabla clave/valor de ajustes del sitio (mantenimiento, títulos), con caché por petición |
| **XSS** | Cross-Site Scripting: inyección de scripts en el contenido; mitigado con `e()`, `sanearHTML()` y CSP |
