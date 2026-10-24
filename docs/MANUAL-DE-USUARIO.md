# Manual de usuario

Para quién sirve cada pantalla y cómo se hace el día a día del sistema.
La instalación y los requisitos están en [`LEEME.md`](LEEME.md).

## Los tres perfiles de uso

| Perfil | Entra por | Qué hace |
|---|---|---|
| **Visitante** (sin sesión) | Portada, wiki, noticias | Consulta proyectos, artículos y noticias |
| **Equipo de desarrollo** (editor/supervisor) | `login.php` → Panel | Redacta artículos, gestiona proyectos e hitos, crea noticias |
| **Administración** (admin) | Panel | Además: usuarios, categorías, moderación, bitácora, diagnóstico |

---

## Sitio público

| Pantalla | URL | Qué mostrar |
|---|---|---|
| Portada | `index.php` | Cifras, proyectos destacados, noticias, llamados a la acción |
| Wiki | `wiki.php` | Todos los artículos con buscador, filtro por tipo/proyecto/tag |
| Proyectos | `proyectos.php` | Rejilla con filtro por categoría y estado |
| Ficha de proyecto | `proyecto.php?slug=…` | Descripción, hitos con % de avance, artículos y galería |
| Artículo | `articulo.php?slug=…` | Documento versionado; el historial lo ven editores |
| Noticias | `noticias.php` | Noticias publicadas (la portada destaca una) |
| Crear noticia | `noticias_crear.php` | Cualquier usuario autenticado redacta una noticia |
| Quiénes somos / contacto | `acerca.php` | Formulario que guarda el mensaje y avisa por correo |
| Acceso | `login.php` | Usuario + contraseña (5 intentos fallidos / 10 min) |
| Mi cuenta | `cuenta.php` | Datos propios y cambio de contraseña (cualquier rol) |
| Archivos | `media.php?f=…` | Entrega segura de imágenes/videos/documentos subidos |

**Búsqueda**: la wiki busca por título, resumen y contenido; los filtros
se conservan al paginar. Los tags son enlaces navegables.

---

## Panel de administración (`frontend/admin/`)

Requiere sesión con capacidad `panel.acceder` (editor en adelante).

### Contenido

- **Proyectos** (`proyecto_form.php`): alta/edición con categoría,
  estado, tecnología, responsable, fechas y hasta 6 adjuntos. El borrado
  es de admin y elimina en cascada artículos, hitos, tags y adjuntos.
- **Hitos** (`hito_form.php`): por proyecto; cada hito lleva título,
  descripción, fecha y **porcentaje de avance** (el promedio alimenta
  la ficha del proyecto).
- **Artículos** (`articulo_form.php`): el editor elige proyecto, tipo
  documental (guion, arquitectura, mecánica…) y plantilla; editor
  enriquecido con barra propia; al guardar se incrementa `version` y se
  guarda copia en `articulo_historial.php` (restaurar = copiar esa
  versión al contenido actual).
- **Noticias** (`noticia_form.php`): los editores crean/editan; el
  flujo es `borrador → pendiente → publicada | rechazada` (ver
  «Flujo de moderación» más abajo).

### Administración

- **Usuarios** (`usuarios.php` / `usuario_form.php`): alta, rol,
  activar/desactivar. Siempre debe quedar al menos un admin activo
  (`contarAdminsActivos()` lo impide).
- **Mensajes de contacto** (`mensajes_contacto.php`): bandeja del
  formulario público; marcar como leído.
- **Bitácora** (`seguridad.php`, sólo admin): eventos de
  `eventos_seguridad` con filtros por tipo/usuario/IP.
- **Diagnóstico** (`sistema.php`): versión de PHP, extensiones, límites
  de subida vs `php.ini`, permisos de `storage/`, conexión a la base,
  `.htaccess`, vendor, estado del correo. Acciones: **enviar correo de
  prueba** y **activar/desactivar modo mantenimiento** (503 al público).

### Flujo de moderación de noticias

```
editor crea → pendiente ──supervisor aprueba──→ publicada (fecha_publicacion)
                     │
                     └──supervisor rechaza──→ rechazada (+ motivo obligatorio)
```

- El público sólo ve `publicada` con fecha (vista `v_noticias_publicadas`).
- El autor puede seguir editando hasta que se publica; después sólo
  moderadores.
- `destacada = 1` marca la noticia principal de la portada (una sola).

### Gestión de archivos

En cualquier formulario con adjuntos: elegir uno o varios (imágenes,
video, documento según `UPLOAD_MIME`), previsualización al subir, y
papelera por adjunto para eliminarlo (borra fila y archivo). La galería
aparece en la ficha pública correspondiente.

---

## Recetas rápidas

| Quiero… | Voy a |
|---|---|
| Publicar un artículo nuevo | Panel → Artículos → Nuevo → elegir proyecto y tipo → Escribir → Publicar |
| Corregir un artículo publicado | Panel → Artículos → Editar (queda nueva versión en el historial) |
| Aprobar una noticia | Panel → Noticias (pendientes) → Aprobar |
| Medir avance de un proyecto | Panel → Hitos → cargar % por hito (la ficha promedia) |
| Activar mantenimiento | Panel → Diagnóstico → Modo mantenimiento |
| Revisar quién entró y cuándo | Panel → Bitácora (admin) |
| Cambiar mi contraseña | Cabecera → Mi cuenta |

---

## Reglas que el usuario debe conocer

1. **Contraseñas**: mínimo 10 caracteres con letra y dígito; se pide la
   actual al cambiarla.
2. **Intentos de acceso**: 5 fallidos en 10 minutos bloquean esa IP
   temporalmente.
3. **Tamaños**: hasta `UPLOAD_MAX_FILESIZE` (50 MB) por archivo; los
   formatos aceptados están en `UPLOAD_MIME`.
4. **HTML permitido**: el editor sólo inserta etiquetas seguras; lo que
   se pegue con `<script>` se limpia al guardar (ver `SEGURIDAD.md`).
5. **Roles**: las capacidades están en la tabla de
   [`LEEME.md` §7](LEEME.md#7-roles-y-permisos); un editor sólo puede
   editar contenidos propios, un supervisor moderará cualquier noticia.
