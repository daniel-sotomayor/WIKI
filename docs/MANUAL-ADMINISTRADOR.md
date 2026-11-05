# Manual de usuario — Administrador del sistema

Para el rol **admin**: gestión de usuarios, contenidos totales,
mensajes, auditoría y salud del sistema.

**Requisitos**: cuenta con rol *administrador*. Todo lo del
[`MANUAL-EDITOR.md`](MANUAL-EDITOR.md) también aplica (un admin puede
todo lo de roles inferiores).

---

## 1. Primeros pasos recomendados

1. Entrá con la cuenta `admin` y **cambiá la contraseña inmediatamente**
   (Mi cuenta → Cambiar contraseña).
2. Abrí **Panel → Sistema (Diagnóstico)**: verificá que todo esté en
   verde (PHP, extensiones, permisos, base de datos).
3. Revisá **Panel → Usuarios**: desactivá las cuentas de prueba que no
   se usen.

---

## 2. Usuarios

Panel → **Usuarios**.

| Acción | Cómo |
|---|---|
| **Crear** | **Nuevo usuario** → nombre, apellido, usuario, correo, contraseña, rol |
| **Editar** | Lápiz en la fila → cambiar datos, rol o contraseña |
| **Activar / desactivar** | Interruptor de la fila (un usuario desactivado no puede entrar) |
| **Eliminar** | Papelera (mejor desactivar: se conserva la autoría del contenido) |

**Roles** (jerárquicos): `lector < editor < supervisor < admin`.

- Un **lector** sólo consulta y tiene "Mi cuenta".
- Un **editor** crea proyectos, artículos, hitos y noticias.
- Un **supervisor** además modera (aprueba/rechaza) noticias.
- **Admin** gestiona todo: usuarios, categorías, mensajes, bitácora,
  eliminaciones y diagnóstico.

**Salvaguardas del sistema**: siempre debe quedar al menos **un admin
activo** (el sistema lo impide) y las contraseñas exigen ≥10 caracteres
con letra y dígito.

> No existe alta *self-service*: los usuarios los crea el admin.

---

## 3. Contenidos (todo lo del editor + borrados)

- **Proyectos / artículos / hitos / noticias**: idéntico al manual del
  editor, más:
  - **Eliminar** proyectos (borra en cascada su documentación, hitos
    y adjuntos) y noticias.
  - **Categorías** de proyectos (Panel → o formulario de proyecto)
    con lista propia (alta/baja).
  - Moderar y editar cualquier noticia, no sólo las propias.

## 4. Mensajes de contacto

Panel → **Mensajes**:

1. Ves la bandeja con **no leídos** primero (contador en el panel).
2. Abrí un mensaje para verlo completo.
3. Marcarlo **leído** cuando lo atiendas (el contador se actualiza).

Los envíos quedan registrados además en la tabla de contacto con IP y
resultado del correo automático.

## 5. Bitácora de seguridad

Panel → **Seguridad** (sólo admin):

- Lista todos los eventos: `login_exitoso`, `login_fallido`,
  `csrf_invalido`, `noticia_creada`, `cambio_password`,
  `mantenimiento`, subidas, etc.
- Filtros por **tipo**, **usuario** e **IP**.
- Usala para: investigar intentos de acceso fallidos (5 fallidos en
  10 min bloquean), verificar quién moderó o editó qué, y auditar
  cambios sensibles.

## 6. Diagnóstico y mantenimiento

Panel → **Sistema**:

| Sección | Qué muestra / hace |
|---|---|
| PHP y extensiones | Versión y estado de `pdo_mysql`, `mbstring`, `dom`, `fileinfo`… |
| Límites de subida | `php.ini` vs el mínimo requerido (avisa si alguno queda corto) |
| Permisos | Escritura de `backend/storage/uploads` y `/mail` |
| Base de datos | Conexión, carga del esquema y vistas |
| Protecciones | `.htaccess` y vendor de PHPMailer |
| Correo | Transporte activo (SMTP o archivo) + botón **Enviar correo de prueba** |
| **Modo mantenimiento** | **Activar** → todo el público ve 503 (login y archivos siguen) mientras diagnosticás; **Desactivar** → vuelve a 200 |

### Cuándo usar el modo mantenimiento

Ante: actualizaciones de base, caída de MySQL, reconfiguración de
correo. El panel **sigue accesible** durante el 503 para que puedas
operar y desactivarlo.

## 7. Copias de seguridad y actualizaciones (opcional, terminal)

```bash
# Respaldo completo (BD comprimida + archivos subidos)
bash backend/bin/respaldo.sh crear        # → backend/storage/backups/

# Cambios de esquema
php backend/bin/migrar.php --estado       # ver pendientes
php backend/bin/migrar.php --aplicar      # aplicar

# Salud de las pruebas
php backend/tests/run.php
```

Guía completa: [`OPERACION.md`](OPERACION.md).

## 8. Checklist mensual del administrador

- [ ] Contraseñas de cuentas sensibles rotadas.
- [ ] Panel → Usuarios: revisar altas/bajas y roles.
- [ ] Bitácora: revisar `login_fallido` inusuales.
- [ ] Respaldo reciente con `respaldo.sh`.
- [ ] Diagnóstico en verde (correo, permisos, límites).
- [ ] Mensajes de contacto atendidos.
- [ ] `php backend/tests/run.php` en verde tras cambios de código.

## 9. Problemas frecuentes

| Situación | Acción |
|---|---|
| Sitio en 503 permanente | Sistema → desactivar mantenimiento |
| "No se pudo conectar a MySQL" | Encender MySQL (XAMPP) y revisar `backend/config/db.php` |
| El correo no llega | Sistema → correo de prueba; si no hay SMTP, revisá `backend/storage/mail/*.eml` |
| Un usuario dice "bloqueado" | Bitácora → filtrar por IP/usuario; la ventana dura 10 min |
| Necesito recuperar contenido borrado | Restaurar respaldo (`respaldo.sh --restaurar`) o, en artículos, Panel → Historial |
| Subidas rechazadas | Diagnóstico → límites de `php.ini` vs 50 MB |

Documentación general: [`MANUAL-DE-USUARIO.md`](MANUAL-DE-USUARIO.md).
