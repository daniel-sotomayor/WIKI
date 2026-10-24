# Operación: despliegue, respaldos, migraciones y correo

## Despliegue en XAMPP (Windows / Linux)

1. Copiar la carpeta `telecom_wiki/` dentro de `htdocs/`.
2. phpMyAdmin → Importar → `backend/database/database.sql` (crea base,
   tablas, vistas y datos de ejemplo).
3. Editar `backend/config/db.php` si hace falta (`DB_HOST` 127.0.0.1,
   `DB_USER` root, `DB_PASS` vacío, `DB_NAME` telecom_wiki).
4. Verificar permisos de escritura de `backend/storage/uploads/` y
   `backend/storage/mail/`.
5. Abrir `http://localhost/telecom_wiki/` y entrar con
   `admin` / `Admin2026!` (cambiar de inmediato desde Panel → Mi cuenta).
6. Panel → Diagnóstico del sistema: todo en verde.

**Requisitos**: PHP 8.1–8.4 con `pdo_mysql`, `mbstring`, `dom`,
`fileinfo`; MySQL 5.7+ / MariaDB 10.2+; Apache con `mod_rewrite` (los
`.htaccess` de la raíz, `frontend/partials/`, `backend/` y
`backend/storage/` bloquean rutas sensibles y la ejecución de PHP en
uploads).

### php.ini sugerido

```ini
upload_max_filesize = 16M
post_max_size = 24M
memory_limit = 256M
max_execution_time = 60
```

### Linux sin XAMPP (desarrollo)

```bash
# MariaDB + PHP 8.x con pdo_mysql, mbstring, dom, fileinfo
mysql -u root -e "CREATE USER IF NOT EXISTS 'telecom_wiki'@'localhost' IDENTIFIED BY 'telecom2026';
  GRANT ALL ON telecom_wiki.* TO 'telecom_wiki'@'localhost'; FLUSH PRIVILEGES;"
mysql -u telecom_wiki -ptelecom2026 telecom_wiki < backend/database/database.sql
./telecom_wiki/dev-server.sh   # sirve en http://127.0.0.1:8000
```

`dev-server.sh` exporta las variables de BD (`DB_HOST`, `DB_USER`,
`DB_PASS`, `DB_NAME`, `SITE_URL`) y arranca
`php -S 127.0.0.1:8000` con `dev-router.php` (alias de URLs cortas y
protecciones 403/404).

## Respaldo y restauración

`backend/bin/respaldo.sh` genera un respaldo completo (mysqldump
comprimido + tar de las subidas):

```bash
bash backend/bin/respaldo.sh crear           # → backend/storage/backups/
bash backend/bin/respaldo.sh --listar
bash backend/bin/respaldo.sh --restaurar backend/storage/backups/telecom_wiki_YYYYmmdd_HHMMSS
```

Recomendación: respaldo automático semanal (cron) y antes de cada
actualización; las subidas y los `.eml` viven en `backend/storage/`,
que es la única ruta con datos volátiles.

## Migraciones de esquema

Los cambios de esquema se versionan como
`backend/database/migraciones/NNNN_descripcion.sql` (usar
`CREATE TABLE IF NOT EXISTS` / `ALTER TABLE …` idempotentes). La tabla
`migraciones` registra archivo + SHA-256 aplicado.

```bash
php backend/bin/migrar.php --estado     # pendientes vs aplicadas
php backend/bin/migrar.php --aplicar    # aplica todas las pendientes
```

En producción: respaldo → `--aplicar` → smoke test.

## Correo

`backend/config/mail_config.php` decide el transporte:

- **SMTP** si existe `SMTP_PASS` (entorno): `SMTP_HOST`, `SMTP_PORT`
  (587 con `SMTP_SECURE=tls`), `SMTP_USER`, `SMTP_PASS`.
- **Archivo** (defecto, ideal en desarrollo): cada correo queda como
  `.eml` con cabeceras RFC 5322 en `backend/storage/mail/`; se puede
  abrir con cualquier cliente de correo. `ultimoCorreoArchivo()` lo
  expone para diagnóstico.

Remitente por defecto: `CONTACT_FROM_EMAIL` /
`CONTACT_FROM_NAME` (Panel → Diagnóstico muestra el estado). El
formulario de contacto envía el mensaje al administrador **y** una
confirmación al interesado (`ENVIAR_CONFIRMACION_CONTACTO`).

## Modo mantenimiento

Se activa desde Panel → Diagnóstico (o directamente en
`config_sitio.mantenimiento = 1`): el público recibe **503** con
`Retry-After` en todas las rutas excepto login, logout y `media.php`.
El panel sigue accesible para diagnosticar.

## Registro y auditoría

- `eventos_seguridad`: logins, fallos, cambios de rol, subidas,
  moderaciones, mantenimiento (`logSeguridad()`).
- Panel → Bitácora (sólo admin) para consultarlo.
- El límite de intentos de login se calcula con
  `contarEventosRecientes()` sobre la misma tabla.

## Solución de problemas

| Síntoma | Acción |
|---|---|
| 503 permanente | Panel → Diagnóstico → desactivar mantenimiento |
| "El sitio no está disponible" con error de MySQL | Revisar `db.php`, que el servicio MySQL esté corriendo y exista la base |
| Subidas fallan | Permisos de `storage/uploads/`, `upload_max_filesize` |
| CSS roto / CSP en consola | Hay estilos o handlers inline prohibidos: mover a hojas/`app.js` |
| Correos no salen | Diagnóstico → "Enviar correo de prueba"; con transporte archivo revisar `storage/mail/` |
