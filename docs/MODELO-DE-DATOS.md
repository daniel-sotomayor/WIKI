# Modelo de datos

Motor: **MySQL 5.7+ / MariaDB 10.2+**, charset `utf8mb4` (tildes, ñ,
emojis). Esquema completo en
[`backend/database/database.sql`](../backend/database/database.sql);
diagrama ER en [`DIAGRAMAS.md`](DIAGRAMAS.md).

Convenciones: `id` `INT UNSIGNED AUTO_INCREMENT` como PK en casi todas
las tablas; `slug` único por entidad para URLs limpias (`slugify()` /
`slugUnico()`); fechas en `DATETIME` con `CURRENT_TIMESTAMP`; borrados
lógicos no se usan (se borra físico, con cascadas).

## Entidades principales

### `usuarios`
Cuentas del sistema. `rol` es un `ENUM` de 4 valores jerárquicos:
`lector` < `editor` < `supervisor` < `admin` (ver `nivelRol()` en
`permisos.php`). `password` guarda el hash de `password_hash()`.
- Claves: PK `id`; únicas `usuario`, `email`; índice `(rol, activo)`.
- Usada por FK desde: `proyectos.responsable_id`, `articulos.autor_id`,
  `noticias.autor_id`/`aprobado_por`, `media.subido_por`, etc. (casi
  todas `ON DELETE SET NULL`).

### `categorias`
Clasificación de proyectos (Videojuegos, Simuladores, …). PK `id`,
única `slug`.

### `proyectos`
Ficha técnica estandarizada. `estado ENUM('activo','en_desarrollo',
'pausado','archivado')`. FK `categoria_id` → `categorias`
(`ON DELETE RESTRICT`: no se puede borrar una categoría con proyectos).
`responsable_id` → `usuarios` (`SET NULL`).

### `articulos`
Wiki documental por proyecto. `tipo ENUM('guion','arquitectura',
'mecanica','personaje','nivel','tecnico','otro')` (define la plantilla
HTML en `plantillas_articulos.php`). `publicado` es un booleano (no un
ENUM). `version` se incrementa en cada guardado.
- FK `proyecto_id` → `proyectos` **`ON DELETE CASCADE`** (borrar el
  proyecto borra su documentación).
- FK `autor_id` → `usuarios` (`SET NULL`).

### `historial_versiones`
Copia del contenido en cada edición de un artículo. FK
`articulo_id` → `articulos` **CASCADE**; `editado_por` → `usuarios`
(`SET NULL`). Índice `(articulo_id, version)`.

### `tags` + `articulo_tags`
Relación N:M artículo↔etiqueta. `articulo_tags` tiene PK compuesta
`(articulo_id, tag_id)` y **CASCADE en ambas FK** (se administra con
`sincronizarTags()` en `tags.php`).

### `hitos`
Seguimiento de avance por proyecto: `porcentaje_avance` (0–100) y
`fecha`. FK `proyecto_id` CASCADE; `creado_por` SET NULL.

### `noticias`
Portal institucional con flujo de moderación. `estado ENUM('borrador',
'pendiente','publicada','rechazada')` es **la única fuente de verdad**
de visibilidad (no existe columna `publicada`). `destacada` marca la
noticia principal de la portada. `razon_rechazo` sólo tiene sentido en
estado `rechazada`. Ambas FK a `usuarios` con `SET NULL`.

### `media` (polimórfico)
Adjuntos (imagen/video/documento) de artículos, noticias o proyectos.
- `entidad_tipo ENUM('articulo','noticia','proyecto')` + `entidad_id`:
  **sin FK** hacia las entidades (polimorfismo). Al borrar una entidad
  hay que llamar `eliminarMediaDe()` o quedarán huérfanos en disco y BD.
- `nombre_original` es el nombre real del archivo; `nombre_guardado`
  (único) es el nombre aleatorio en `backend/storage/uploads/<tipo>/`.
- El archivo **nunca** se sirve desde uploads: se entrega por
  `frontend/media.php?f=<nombre_guardado>`.
- Índice `(entidad_tipo, entidad_id, es_principal)`.

### `mensajes_contacto`
Formulario "Quiénes somos": nombre, email, mensaje, IP, `leido`,
`enviado_ok` (si el correo automático salió bien).

### `eventos_seguridad`
Bitácora y control de intentos de acceso. Sin FK (auditoría):
`tipo`, `usuario` (texto, puede no existir), `ip`, `detalle`.
Índices para las consultas de la bitácora y del límite de intentos
(`contarEventosRecientes`).

### `migraciones`
Registro de `backend/database/migraciones/NNNN_*.sql` aplicados
(vía `backend/bin/migrar.php`): `archivo` único + `checksum` SHA-256.

### `config_sitio`
Ajustes clave/valor editables desde el panel (`configObtener()` /
`configGuardar()` en `config_sitio.php`). Claves conocidas:
`mantenimiento`, `titulo_portada`, `contacto_institucional`.

## Vistas

| Vista | Uso |
|---|---|
| `v_proyectos_resumen` | Listado del panel y portada: categoría, responsable, conteo de artículos y `avance_medio` (AVG de hitos) |
| `v_articulos_publicados` | Wiki pública: sólo `publicado = 1`, con proyecto y autor |
| `v_noticias_publicadas` | Portal público: `estado = 'publicada'` y con `fecha_publicacion` |
| `v_noticias_moderacion` | Cola del panel: `pendiente`/`rechazada` con autor y revisor |

## Reglas de integridad relevantentes

- **Cascadas**: proyectos→artículos→historial/tags (todo se borra en
  cadena); categorías protegidas (`RESTRICT`) mientras tengan proyectos.
- **Sin FK (a propósito)**: `media.entidad_*`, `eventos_seguridad`,
  `historial_versiones.editado_por` (SET NULL sí), `mensajes_contacto`.
- **`ENUM` vs texto**: los estados y roles usan `ENUM` para que el motor
  garantice el dominio; `enLista()` en la capa PHP valida igual.
- **Collation** `utf8mb4_unicode_ci`: las comparaciones de `slug` y
  `usuario` son case-insensitive (esperado para URLs y login).
