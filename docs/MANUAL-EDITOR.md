# Manual de usuario — Editor y Supervisor (contenidos)

Para el equipo que **crea y gestiona contenido**: proyectos, artículos
con versiones, hitos, archivos y noticias. Cubre el rol **editor** y
las funciones extra del **supervisor** (moderación).

**Requisitos**: usuario y contraseña entregados por el administrador;
navegador actualizado.

---

## 1. Entrar

1. Abrí el sitio y presioná **Iniciar sesión** (arriba a la derecha).
2. Ingresá usuario y contraseña → **Entrar**.
3. Aparece el **Panel de administración** (también desde el enlace
   **Panel** de la cabecera).

> Si te equivocás 5 veces en 10 minutos, el acceso se bloquea
> temporalmente: esperá la ventana o contactá al administrador.

## 2. Tu cuenta

En **Mi cuenta** (cabecera) podés:
- Editar **nombre**, **apellido** y **correo**.
- **Cambiar tu contraseña** (pide la actual; mínimo 10 caracteres con
  letra y dígito). Al cambiarla se cierran tus otras sesiones.

---

## 3. Proyectos

**Capacidades del editor**: crear y editar proyectos y gestionar sus
hitos. **Sólo admin** los elimina.

### Crear un proyecto

1. Panel → **Proyectos** → **Nuevo proyecto**.
2. Completá: título (se genera solo la dirección/*slug*), categoría,
   descripción corta y larga, tecnologías, versión, estado y
   responsable.
3. Opcional: **adjuntar archivos** (imágenes, video o documentos; hasta
   6, uno o varios a la vez, máx. 50 MB c/u).
4. **Guardar**. El proyecto queda visible en la ficha pública con su
   galería.

### Editar / cargar hitos

1. En el proyecto → **Hitos** → nuevo hito.
2. Titulo, descripción, fecha y **porcentaje de avance** (0–100).
3. El promedio de los hitos alimenta el indicador de avance de la
   ficha pública — mantenelo actualizado.

**Tip**: el estado (activo, en desarrollo, pausado, archivado) define
los filtros del listado público.

---

## 4. Artículos de la wiki (con versiones)

### Crear

1. Panel → **Artículos** → **Nuevo artículo**.
2. Elegí el **proyecto**, el **tipo documental** (guion, arquitectura,
   mecánica, personaje, nivel, técnico, otro) y, si querés, usá la
   **plantilla** que se carga para ese tipo.
3. Escribí título, resumen y contenido con la **barra de herramientas**
   (encabezados, listas, cita, código, enlaces, imágenes, tablas;
   atajos **Ctrl+B** negrita, **Ctrl+I** cursiva).
4. Etiquetas separadas por coma (ej: `python, arquitectura`).
5. Marcá **Publicar** para que aparezca en la wiki pública
   (si no, queda borrador).

### Editar y versionar

1. Cada guardado genera una **nueva versión** del artículo.
2. Panel → **Artículos** → en el artículo → **Historial**: ves todas
   las versiones con fecha y autor.
3. Para revertir: abrí la versión deseada → **Restaurar** → el
   contenido copiado se guarda como versión nueva (nunca se pierde
   nada).

**Regla**: un editor ve y edita **sus** artículos; los de otros
editores los ve pero no los modifica (salvo rol supervisor).

---

## 5. Archivos (multimedia)

- Desde cualquier formulario (proyecto, artículo, noticia) podés
  **adjuntar**: imágenes (jpg/png/gif/webp, máx. 5 MB), videos
  (mp4/webm, 50 MB) y documentos (pdf/doc/docx/odt/txt/csv/zip/rar,
  15 MB).
- Se aceptan sólo esos formatos verificando el **tipo real** del
  archivo: un `.php` renombrado se rechaza con "Tipo de archivo no
  permitido".
- Cada adjunto tiene **papelera** para eliminarlo (borra el archivo y
  su registro).

---

## 6. Noticias

### Como editor

1. Panel → **Noticias** → **Nueva noticia**.
2. Título, resumen, categoría (lista cerrada), contenido y, si
   querés, marcar **destacada** (aparece en la portada).
3. **Guardar** → la noticia queda en estado **pendiente** (hasta que
   la modere un supervisor). Podés seguir editándola mientras no se
   publique.

También podés crear noticias desde el sitio público
(**Noticias → Crear noticia**); el flujo es el mismo.

### Como supervisor (sólo supervisor/admin)

1. Panel → **Noticias** → sección **Pendientes de moderación**.
2. **Aprobar** → la noticia pasa a **publicada** con fecha y hora
   actuales → visible para todos.
3. **Rechazar** → obligatorio escribir el **motivo** → el autor la ve
   marcada como rechazada con ese motivo.
4. **Destacada**: la portada sólo admite una; al destacar otra se
   desmarca la anterior.

Estados: `borrador → pendiente → publicada | rechazada`.

---

## 7. Qué ve el supervisor además

| Función | Dónde |
|---|---|
| Moderar noticias (aprobar/rechazar) | Panel → Noticias |
| Ver la cola de moderación con autores y motivos | Panel → Noticias |
| Editar contenidos de otros (nivel supervisor) | Cualquier formulario |

Todo lo demás (proyectos, artículos, hitos, archivos) es común con el
editor.

## 8. Problemas frecuentes

| Situación | Solución |
|---|---|
| "No tienes permiso" | Tu rol no incluye esa acción; pedila a un supervisor/admin |
| No puedo editar un artículo ajeno | Es propiedad del autor; un supervisor puede |
| Rechaza el archivo | Formato fuera de la lista o supera el límite de MB |
| Perdí contenido | Panel → Artículos → Historial → restaurar la versión anterior |
| "Debes esperar" al entrar | Límite de intentos; 5 fallos / 10 min |
| El HTML pegado se limpia | Sólo se permiten etiquetas seguras (ver `SEGURIDAD.md`) |

Continuación general: [`MANUAL-DE-USUARIO.md`](MANUAL-DE-USUARIO.md).
