# Manual de usuario — Visitante (sitio público)

Para quien entra al sistema **sin iniciar sesión**: consultar la
documentación, noticias y proyectos.

**Requisitos**: navegador moderno (Chrome, Firefox, Edge o Safari
actualizados) y conexión al servidor de la empresa. Sin instalar nada.

---

## 1. Entrar al sitio

1. Abrí `http://<servidor>/telecom_wiki/` (en desarrollo local:
   `http://localhost/telecom_wiki/` o `http://127.0.0.1:8000/`).
2. Aparece la **portada** con las cifras generales, los proyectos
   destacados y las últimas noticias.

> En la barra superior hay un botón **sol/luna** para cambiar entre
> modo claro y modo oscuro (se recuerda para la próxima visita).

## 2. Navegación

| Menú | Qué encontrás |
|---|---|
| **Inicio** | Portada: cifras, proyectos y noticias destacadas |
| **Proyectos** | Rejilla con todos los proyectos del portafolio |
| **Wiki** | Todos los artículos documentales con buscador |
| **Noticias** | Noticias institucionales publicadas |
| **Acerca de** | Quiénes somos y formulario de contacto |

## 3. Buscar en la wiki

1. Andá a **Wiki**.
2. Escribí lo que buscás en el buscador (título, resumen o contenido).
3. Podés filtrar por **tipo** de documento (guion, arquitectura,
   mecánica, técnico…), por **proyecto** o por **etiqueta**.
4. Los resultados muestran el resumen; hacé clic en un título para
   leer el artículo completo.
5. Al pie hay paginación si hay más resultados que los de una página.

**Tip**: las **etiquetas (tags)** de cada artículo son enlaces — al
hacer clic en uno ves todos los artículos relacionados.

## 4. Ver un proyecto

1. Andá a **Proyectos** y elegí uno (o buscá desde la portada).
2. En la ficha ves:
   - Descripción técnica y tecnologías utilizadas.
   - **Hitos** con su porcentaje de avance (barra y promedio).
   - **Artículos** del proyecto (documentación).
   - **Galería** de imágenes o videos adjuntos.
3. Los filtros del listado permiten acotar por **categoría** y
   **estado** (activo, en desarrollo, pausado, archivado).

## 5. Leer artículos y noticias

- **Artículo**: muestra el documento formateado, a qué proyecto
  pertenece, su tipo y versión. Las imágenes se ven en la galería.
- **Noticia**: entrá desde **Noticias**; la portada muestra la
  destacada con prioridad. Sólo se publican noticias aprobadas.

## 6. Enviar un mensaje de contacto

1. Andá a **Acerca de**.
2. Completá **nombre**, **correo** y **mensaje**.
3. Presioná **Enviar**. Recibís una copia por correo (si el sistema
   tiene correo configurado) y el mensaje queda en la bandeja del
   administrador.

**Límite**: 5 envíos cada 15 minutos por IP (anti-spam).

## 7. ¿No podés ver algo?

| Situación | Qué significa |
|---|---|
| Un artículo/proyecto no aparece | Sólo se muestran los publicados/activos |
| La página dice "No tienes permiso" | Ese contenido requiere iniciar sesión con un rol mayor |
| Un archivo no abre | El formato puede no estar soportado; reportalo al equipo |
| 503 "no está disponible" | El sitio está en **modo mantenimiento**; esperá o contactá al administrador |

## 8. Quiero más: crear una cuenta de lectura

El acceso lo habilita el administrador (**Usuarios** → alta con rol
*lector*). Con eso podés entrar con **Iniciar sesión** (menú superior
derecho) y, además de todo lo anterior, acceder a **Mi cuenta** para
ver tus datos. Las capacidades de cada rol están en el
[`MANUAL-DE-USUARIO.md`](MANUAL-DE-USUARIO.md) general.
