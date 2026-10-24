# TelecomWiki — Diagramas del sistema

Diagramas oficiales del proyecto (entidad-relación, casos de uso, procesos
y secuencia). Están escritos en **Mermaid**: se ven en GitHub, en la
extensión *Mermaid Preview* de VS Code o pegándolos en <https://mermaid.live>.

> Toda la información de apoyo está en [`MODELO-DE-DATOS.md`](MODELO-DE-DATOS.md)
> (tabla por tabla), [`ARQUITECTURA.md`](ARQUITECTURA.md) (capas y flujo de
> una petición) y [`../backend/database/database.sql`](../backend/database/database.sql)
> (esquema real, vistas e índices).

---

## 1. Diagrama entidad-relación

```mermaid
erDiagram
    usuarios ||--o{ proyectos : "es responsable de"
    categorias ||--o{ proyectos : "agrupa"
    proyectos ||--o{ articulos : "documenta"
    usuarios |o--o{ articulos : "es autor de"
    articulos ||--o{ historial_versiones : "versiona"
    usuarios |o--o{ historial_versiones : "edita"
    proyectos ||--o{ hitos : "seguimiento"
    usuarios |o--o{ hitos : "registra"
    articulos ||--o{ articulo_tags : "recibe"
    tags ||--o{ articulo_tags : "clasifica"
    usuarios |o--o{ noticias : "escribe o aprueba"
    usuarios |o--o{ media : "sube"
    proyectos ||--o{ media : "adjunta polimorfico"
    articulos ||--o{ media : "adjunta polimorfico"
    noticias ||--o{ media : "adjunta polimorfico"
    usuarios {
        int id PK "auto increment"
        varchar nombre "max 100"
        varchar apellido "max 100"
        varchar usuario UK "max 50, login"
        varchar email UK "max 150"
        varchar password "hash bcrypt"
        varchar rol "lector/editor/supervisor/admin"
        tinyint activo "0 o 1"
        datetime ultimo_acceso
        datetime creado_en
    }
    categorias {
        int id PK "auto increment"
        varchar nombre "max 100"
        varchar slug UK "max 120"
        text descripcion
        varchar icono "nombre del icono SVG"
    }
    proyectos {
        int id PK "auto increment"
        varchar titulo "max 200"
        varchar slug UK "max 220, URL amigable"
        int categoria_id FK "a categorias.id"
        varchar descripcion_corta "max 500"
        longtext descripcion "HTML saneado"
        varchar tecnologias "lista separada por comas"
        varchar version_actual "max 20"
        varchar estado "activo/en_desarrollo/pausado/archivado"
        int responsable_id FK "a usuarios.id, nullable"
        date fecha_inicio
        datetime fecha_actualizacion "ON UPDATE"
    }
    articulos {
        int id PK "auto increment"
        int proyecto_id FK "a proyectos.id"
        varchar titulo "max 300"
        varchar slug UK "max 320"
        varchar resumen "max 500"
        longtext contenido "HTML saneado"
        varchar tipo "guion/arquitectura/mecanica/personaje/nivel/tecnico/otro"
        int autor_id FK "a usuarios.id, nullable"
        int version "contador de versiones"
        tinyint publicado "0 o 1"
        datetime actualizado "ON UPDATE"
    }
    historial_versiones {
        int id PK "auto increment"
        int articulo_id FK "a articulos.id"
        longtext contenido "copia de la versión anterior"
        int version "número de versión archivada"
        int editado_por FK "a usuarios.id, nullable"
        datetime editado_en
    }
    tags {
        int id PK "auto increment"
        varchar nombre "max 100"
        varchar slug UK "max 120"
    }
    articulo_tags {
        int articulo_id PK "FK a articulos.id"
        int tag_id PK "FK a tags.id"
    }
    hitos {
        int id PK "auto increment"
        int proyecto_id FK "a proyectos.id"
        varchar titulo "max 200"
        text descripcion
        tinyint porcentaje_avance "0 a 100"
        date fecha
        int creado_por FK "a usuarios.id, nullable"
    }
    noticias {
        int id PK "auto increment"
        varchar titulo "max 300"
        varchar slug UK "max 320"
        varchar resumen "max 600"
        longtext contenido "HTML saneado"
        varchar categoria "texto libre"
        int autor_id FK "a usuarios.id, nullable"
        varchar estado "borrador/pendiente/publicada/rechazada"
        tinyint destacada "0 o 1"
        int aprobado_por FK "a usuarios.id, nullable"
        datetime fecha_publicacion
        text razon_rechazo
    }
    media {
        int id PK "auto increment"
        varchar entidad_tipo "articulo/noticia/proyecto"
        int entidad_id "sin FK: polimorfico"
        varchar tipo "imagen/video/documento"
        varchar nombre_original "max 255"
        varchar nombre_guardado UK "32 hex + extension"
        varchar mime "MIME real por finfo"
        int tamano "bytes"
        tinyint es_principal "0 o 1"
        int subido_por FK "a usuarios.id, nullable"
    }
    mensajes_contacto {
        int id PK "auto increment"
        varchar nombre "max 150"
        varchar email "max 150"
        text mensaje
        varchar ip "IPv4 o IPv6"
        tinyint leido "0 o 1"
        tinyint enviado_ok "correo de respuesta"
    }
    eventos_seguridad {
        bigint id PK "auto increment"
        varchar tipo "login_exitoso, csrf_invalido, etc."
        varchar usuario "nombre o id, sin FK"
        varchar ip "IPv4 o IPv6"
        text detalle
    }
    migraciones {
        int id PK "auto increment"
        varchar archivo UK "NNNN_nombre.sql"
        char checksum "sha256 del archivo"
        datetime aplicado_en
    }
    config_sitio {
        varchar clave PK "max 60"
        text valor
        datetime actualizado_en "ON UPDATE"
    }
```

Notas sobre el modelo:

- **`media` es polimórfico** (`entidad_tipo` + `entidad_id` sin clave
  foránea): por eso al borrar una noticia/proyecto el código llama a
  `eliminarMediaDe()`. Es la única relación sin FK.
- **`eventos_seguridad`** tampoco tiene FK hacia `usuarios`: debe seguir
  registrando intentos de acceso con nombres inexistentes.
- **Estado de noticia** es la única fuente de verdad de «¿está visible?»:
  no existe una columna `publicada`.
- **Vistas** disponibles en el esquema:
  `v_proyectos_resumen`, `v_articulos_publicados`, `v_noticias_publicadas`,
  `v_noticias_moderacion`.

---

## 2. Diagrama de casos de uso

Sistema delimitado por el recuadro; cada actor entra por los casos que su
rol permite (ver tabla de capacidades más abajo).

```mermaid
flowchart LR
    subgraph SYS["TelecomWiki — Sistema de Gestión y Estandarización de Documentación Técnica"]
        direction TB
        UC1["Ver portada y noticias publicadas"]
        UC2["Buscar proyectos y artículos"]
        UC3["Consultar ficha de proyecto"]
        UC4["Leer artículo de la wiki"]
        UC5["Enviar mensaje de contacto"]
        UC6["Iniciar sesión"]
        UC7["Cerrar sesión"]
        UC8["Mi cuenta: datos y contraseña"]
        UC9["Crear y editar proyectos"]
        UC10["Crear y editar artículos"]
        UC11["Gestionar hitos y avance"]
        UC12["Gestionar etiquetas"]
        UC13["Subir y eliminar archivos"]
        UC14["Crear y editar noticias"]
        UC15["Moderar noticias"]
        UC16["Administrar usuarios"]
        UC17["Administrar categorías"]
        UC18["Eliminar contenido"]
        UC19["Ver bitácora de seguridad"]
        UC20["Responder mensajes de contacto"]
        UC21["Diagnóstico del sistema"]
        UC22["Panel de administración"]
        UCA["Autenticar con límite de intentos"]
        UCB["Validar CSRF y saneado de HTML"]
    end

    visitante(["Visitante"]) --> UC1
    visitante --> UC2
    visitante --> UC3
    visitante --> UC4
    visitante --> UC5
    visitante --> UC6

    lector(["Lector"]) --> UC6
    lector --> UC7
    lector --> UC8

    editor(["Editor"]) --> UC8
    editor --> UC7
    editor --> UC22
    editor --> UC9
    editor --> UC10
    editor --> UC11
    editor --> UC12
    editor --> UC13
    editor --> UC14

    supervisor(["Supervisor"]) --> UC15
    supervisor --> UC14
    supervisor --> UC22

    admin(["Administrador"]) --> UC16
    admin --> UC17
    admin --> UC18
    admin --> UC19
    admin --> UC20
    admin --> UC21
    admin --> UC22

    UCA -.-> UC6
    UCB -.-> UC9
    UCB -.-> UC10
    UCB -.-> UC14
```

Relación con permisos (`backend/lib/permisos.php`):

| Caso de uso | Capacidad requerida | Rol mínimo |
|---|---|---|
| Ver/buscar/leer contenido público | — (público) | cualquiera |
| Iniciar sesión / cerrar sesión | — | — |
| Mi cuenta (datos y contraseña) | sesión iniciada | cualquier rol |
| Panel de administración | `panel.acceder` | editor |
| Crear/editar proyectos y artículos | `proyecto.*` / `articulo.*` | editor |
| Gestionar hitos, etiquetas y archivos | `hito.gestionar`, `etiqueta.gestionar`, `media.*` | editor |
| Crear noticias | `noticia.crear` | editor |
| Moderar noticias | `noticia.moderar` | supervisor |
| Eliminar noticias/proyectos | `noticia.eliminar`, `proyecto.eliminar` | admin |
| Usuarios, categorías, mensajes | `usuario.gestionar`, `categoria.gestionar`, `mensaje.gestionar` | admin |
| Bitácora de seguridad | `bitacora.ver` | admin |
| Diagnóstico del sistema | `panel.acceder` | editor |

---

## 3. Diagramas de procesos (actividad)

### 3.1 Alta y edición de artículos con control de versiones

```mermaid
flowchart TD
    A([Inicio: editor abre el formulario]) --> B{"¿Tiene permiso<br/>articulo.crear/editar?"}
    B -- no --> D1([Acceso denegado])
    B -- sí --> C["Carga artículo + proyecto<br/>y muestra plantillas sugeridas"]
    C --> D["Envía POST con token CSRF"]
    D --> E{"¿Token CSRF<br/>válido?"}
    E -- no --> D2([403])
    E -- sí --> F["Sanea campos<br/>título, resumen, tipo, contenido"]
    F --> G{"Validaciones OK?<br/>longitudes y contenido no vacío"}
    G -- no --> H["Muestra errores<br/>junto a cada campo"] --> D
    G -- sí --> I{"¿Es edición y<br/>cambió título/tipo/contenido?"}
    I -- sí --> J["INSERT en historial_versiones<br/>con la versión anterior"]
    J --> K["UPDATE articulos<br/>version = version + 1"]
    I -- no --> L["UPDATE sólo de publicado/resumen"]
    I -- "alta" --> M["INSERT articulos<br/>slug único con slugUnico()"]
    K --> N["sincronizarTags()<br/>alta/baja en tags y articulo_tags"]
    L --> N
    M --> N
    N --> O["logSeguridad(articulo_creado/editado)"]
    O --> P(["Redirige al formulario con flash: versión archivada"])
    H --> D
```

### 3.2 Moderación de noticias (separación de funciones)

```mermaid
flowchart TD
    A([Editor crea noticia]) --> B["Estado = pendiente<br/>si no tiene permiso de moderación"]
    B --> C([Supervisor abre<br/>Panel → Moderar noticias])
    C --> D{"¿Hay noticias<br/>en cola?"}
    D -- no --> E([Cola vacía])
    D -- sí --> F["Elige aprobar o rechazar<br/>(rechazo exige motivo)"]
    F --> G["POST con token CSRF"]
    G --> H{"UPDATE ... WHERE id = ?<br/>AND estado = 'pendiente'"}
    H -- "0 filas" --> I["Ya fue revisada<br/>o no existe"] --> C
    H -- aprobar --> J["estado = publicada<br/>aprobado_por = supervisor<br/>fecha_publicacion = NOW()"]
    H -- rechazar --> K["estado = rechazada<br/>razón guardada"]
    J --> L["logSeguridad(noticia_aprobada)"]
    K --> M["logSeguridad(noticia_rechazada)"]
    L --> N([El público la ve en noticias.php])
    M --> O([Queda en el historial<br/>del panel])
```

### 3.3 Subida y borrado de archivos

```mermaid
flowchart TD
    A([Usuario con sesión envía<br/>formulario multipart]) --> B{"POST con<br/>token CSRF"}
    B -- no --> X1([403])
    B -- sí --> C{"¿Hay archivos<br/>seleccionados?"}
    C -- no --> W1([Guarda el registro<br/>sin adjuntos])
    C -- sí --> D["normalizarFiles()<br/>archivo único o múltiple"]
    D --> E["validarSubida():<br/>código de subida, extensión<br/>y MIME real con finfo"]
    E --> F{"¿Aprobado?"}
    F -- no --> G["Error junto al campo:<br/>tipo no permitido / muy grande"] --> W1
    F -- sí --> H["Imágenes: getimagesize()<br/>Vídeos: prefijo video/*"]
    H --> I["guardarArchivo():<br/>nombre aleatorio de 32 hex<br/>en backend/storage/uploads/TIPO/"]
    I --> J["INSERT en media<br/>con entidad_tipo + entidad_id"]
    J --> K{"¿Era el primero<br/>de la entidad?"}
    K -- sí --> L["es_principal = 1"]
    K -- no --> M(["Sigue con el resto"])
    L --> M
    M --> N([Se muestra en la galería<br/>y se sirve por media.php])
    N --> O{"¿Borra un adjunto?"}
    O -- sí --> P["DELETE fila + archivo físico<br/>logSeguridad(media_eliminada)"]
    P --> N
```

### 3.4 Autenticación con límite de intentos

```mermaid
flowchart TD
    A([POST de login]) --> B{"¿Ventana activa<br/>con intentos agotados?<br/>5 por usuario / 10 por IP en 15 min"}
    B -- sí --> C["logSeguridad(login_bloqueado)"] --> D([«Intenta más tarde»])
    B -- no --> E{"Existe el usuario,<br/>está activo y<br/>password_verify()?"}
    E -- no --> F["logSeguridad(login_fallido)"] --> G(["Vuelve al formulario<br/>con mensaje genérico"])
    E -- sí --> H["session_regenerate_id(true)<br/>+ sesión con id, nombre y rol"]
    H --> I["UPDATE usuarios.ultimo_acceso"]
    I --> J["logSeguridad(login_exitoso)"]
    J --> K(["Destino: Panel si tiene<br/>panel.acceder, si no Inicio"])
```

---

## 4. Diagramas de secuencia

### 4.1 Inicio de sesión

```mermaid
sequenceDiagram
    autonumber
    actor U as Usuario
    participant F as login.php
    participant S as Sesión (security.php)
    participant A as auth.php
    participant DB as MySQL

    U->>F: GET /frontend/login.php
    F->>S: session_start() + token CSRF
    S-->>F: PHPSESSID + csrf_token
    F-->>U: Formulario (nonce en la CSP)

    U->>F: POST usuario, password, csrf_token
    F->>F: verifyCsrf() — ¿coincide con el token?
    alt token inválido
        F-->>U: 403
    end
    F->>A: login(usuario, password)
    A->>DB: contarEventosRecientes(login_fallido, usuario/IP, 15 min)
    DB-->>A: intentos
    alt intentos agotados
        A->>DB: logSeguridad(login_bloqueado)
        A-->>F: "bloqueado"
        F-->>U: Mensaje + no intenta verificar
    else con intentos disponibles
        A->>DB: SELECT usuario, password, rol, activo
        DB-->>A: fila o vacío
        alt credenciales inválidas o cuenta inactiva
            A->>DB: logSeguridad(login_fallido)
            A-->>F: false
            F-->>U: «Usuario o contraseña incorrectos»
        else válido
            A->>S: session_regenerate_id(true)
            A->>S: $_SESSION[user_id, user_rol, …]
            A->>DB: UPDATE usuarios.ultimo_acceso
            A->>DB: logSeguridad(login_exitoso)
            A-->>F: true
            F-->>U: 302 → Panel de administración
        end
    end
```

### 4.2 Guardado de un artículo (con versionado)

```mermaid
sequenceDiagram
    autonumber
    actor E as Editor
    participant F as articulo_form.php
    participant V as validate.php / sanitize.php
    participant T as tags.php
    participant DB as MySQL

    E->>F: POST (CSRF, título, tipo, contenido, etiquetas)
    F->>F: verifyCsrf()
    F->>V: saneaPOST() por campo
    F->>V: sanearHTML(contenido) — lista blanca de etiquetas
    V-->>F: HTML limpio (sin script, sin on*, sin javascript:)
    F->>F: validar() — longitudes y contenido no vacío
    alt errores de validación
        F-->>E: 200 con errores junto a cada campo
    else sin errores
        alt edición con cambios
            F->>DB: INSERT INTO historial_versiones (versión anterior)
            F->>DB: UPDATE articulos SET …, version = version + 1
        else alta
            F->>DB: INSERT INTO articulos (slug = slugUnico(...))
        end
        F->>T: sincronizarTags(id, "arquitectura, python")
        T->>DB: INSERT/DELETE en tags y articulo_tags
        F->>DB: logSeguridad(articulo_creado | articulo_editado)
        F-->>E: 302 + flash «se archivó la versión anterior»
    end
```

### 4.3 Subida de un archivo multimedia

```mermaid
sequenceDiagram
    autonumber
    actor U as Usuario (editor)
    participant F as Form (multipart)
    participant P as upload.php
    participant FS as backend/storage/uploads
    participant DB as MySQL

    U->>F: adjunta foto y envía
    F->>P: subirAdjuntos(entidad_tipo, entidad_id, $_FILES, usuario_id)
    P->>P: normalizarFiles() — unifica único y múltiple
    P->>P: validarSubida() — código, extensión, finfo MIME, getimagesize()
    alt rechazado
        P-->>F: mensaje de error (queda junto al campo y en el flash)
    else aprobado
        P->>FS: guardarArchivo() → 32hex.png (nombre aleatorio)
        FS-->>P: ruta final
        P->>DB: INSERT INTO media (entidad_tipo, entidad_id, es_principal)
        P-->>F: cantidad de archivos guardados
        F-->>U: 302 + galería actualizada
    end
    Note over U,DB: La entrega al navegador siempre es por media.php?f=…<br/>que valida nombre, MIME real y tamaño (admite Range para vídeo)
```

### 4.4 Aprobación de una noticia

```mermaid
sequenceDiagram
    autonumber
    actor S as Supervisor
    participant M as noticias_moderar.php
    participant P as permisos.php
    participant DB as MySQL
    actor P2 as Sitio público

    S->>M: GET Panel → Moderar noticias
    M->>P: puede('noticia.moderar')
    P-->>M: true (rol ≥ supervisor)
    M->>DB: SELECT colas pendiente e historial
    DB-->>M: filas
    M-->>S: cola + historial de revisiones

    S->>M: POST accion=aprobar, noticia_id, csrf_token
    M->>M: verifyCsrf()
    M->>DB: UPDATE noticias SET estado='publicada', aprobado_por=?, fecha_publicacion=NOW() WHERE id=? AND estado='pendiente'
    DB-->>M: filas afectadas
    alt 0 filas
        M-->>S: «La noticia ya fue revisada»
    else 1 fila
        M->>DB: logSeguridad(noticia_aprobada)
        M-->>S: 302 + confirmación
        P2->>DB: SELECT v_noticias_publicadas
        DB-->>P2: la noticia aparece en noticias.php
    end
```

---

## 5. Diagrama de estados de una noticia

```mermaid
stateDiagram-v2
    [*] --> borrador : editor guarda sin enviar
    [*] --> pendiente : editor crea (sin permiso de moderación)
    pendiente --> publicada : supervisor aprueba
    pendiente --> rechazada : supervisor rechaza (con motivo)
    rechazada --> pendiente : editor corrige y reenvía
    publicada --> [*] : admin elimina
    borrador --> pendiente : editor envía a revisión

    note right of publicada
        Sólo `publicada` con fecha_publicacion
        aparece en el sitio público
    end note
```

---

## 6. Cómo reproducir los diagramas

```bash
# Extensiones recomendadas de VS Code:
#   - Mermaid Preview  (markdown.mermaid / "Markdown Preview Mermaid Support")
# En línea:  https://mermaid.live
# En GitHub: los bloques ```mermaid se renderizan solos
```

Si agregás un diagrama nuevo, mantené este orden: **entidad-relación →
casos de uso → procesos → secuencia → estados**.
