# TelecomWiki

Sistema de Gestión y Estandarización de Documentación Técnica para
proyectos de **Telecom Venezuela, C.A.**

Proyecto académico — TSU en Informática · UPTC «Mariscal Sucre» ·
Tutor: Alfredo Olivero.

PHP 8.1–8.4 · HTML5 · CSS3 · JavaScript vanilla · MySQL/MariaDB.
Sin frameworks: se despliega copiando la carpeta a `htdocs/` de XAMPP.

## Instalación rápida (XAMPP)

1. Copiar `telecom_wiki/` dentro de `htdocs/`.
2. phpMyAdmin → Importar → `backend/database/database.sql`
   (crea la base, 14 tablas, 4 vistas y datos de ejemplo).
3. Ajustar credenciales en `backend/config/db.php` si es necesario
   (por defecto: root sin contraseña, base `telecom_wiki`).
4. Abrir `http://localhost/telecom_wiki/` y entrar con
   `admin` / `Admin2026!` (cambiar de inmediato).

Usuarios de prueba: `supervisor`, `editor`, `lector` con `Demo2026!`.

## Desarrollo sin XAMPP (Linux)

```bash
mysql -u root -e "CREATE USER IF NOT EXISTS 'telecom_wiki'@'localhost' IDENTIFIED BY 'telecom2026';
  GRANT ALL ON telecom_wiki.* TO 'telecom_wiki'@'localhost'; FLUSH PRIVILEGES;"
mysql -u telecom_wiki -ptelecom2026 telecom_wiki < backend/database/database.sql
./dev-server.sh    # http://127.0.0.1:8000
```

## Pruebas

```bash
php backend/tests/run.php
```

Cuatro suites: saneamiento XSS (70 pruebas), estructura del código,
correo por archivo y un recorrido HTTP end-to-end con `php -S` + curl.

## Documentación

Toda la documentación vive en [`docs/`](docs/):

| Documento | Contenido |
|---|---|
| [`LEEME.md`](docs/LEEME.md) | Guía general de uso e instalación |
| [`MANUAL-DE-USUARIO.md`](docs/MANUAL-DE-USUARIO.md) | Pantallas, flujos y recetas para usar el sistema |
| [`ARQUITECTURA.md`](docs/ARQUITECTURA.md) | Capas, flujo de petición, módulos |
| [`GUIA-DESARROLLADOR.md`](docs/GUIA-DESARROLLADOR.md) | Convenciones, recetas para extender y checklist |
| [`DISENO.md`](docs/DISENO.md) | Tokens, componentes, responsive, accesibilidad |
| [`MODELO-DE-DATOS.md`](docs/MODELO-DE-DATOS.md) | Tablas, relaciones, vistas, cascadas |
| [`DIAGRAMAS.md`](docs/DIAGRAMAS.md) | ER, casos de uso, procesos, secuencias (Mermaid + SVG) |
| [`CODIGO.md`](docs/CODIGO.md) | Catálogo de helpers |
| [`GLOSARIO.md`](docs/GLOSARIO.md) | Términos del sistema |
| [`PRUEBAS.md`](docs/PRUEBAS.md) | Cómo correr y escribir pruebas |
| [`OPERACION.md`](docs/OPERACION.md) | Despliegue, respaldos, migraciones, correo |
| [`SEGURIDAD.md`](docs/SEGURIDAD.md) | Modelo de amenazas y medidas |
| [`CAMBIOS.md`](docs/CAMBIOS.md) | Historial de desarrollo |

## Estructura

```
telecom_wiki/
├── frontend/            # Pantallas, CSS, JS, parciales, media.php
├── backend/
│   ├── config/          # config.php, db.php, mail_config.php
│   ├── lib/             # Toda la lógica (helpers)
│   ├── database/        # database.sql + migraciones/
│   ├── bin/             # migrar.php, respaldo.sh
│   ├── tests/           # run.php + suites *_test.php
│   └── storage/         # uploads/, mail/, respaldos/ (no versionados)
├── docs/                # Documentación completa
├── dev-server.sh        # Servidor de desarrollo (php -S)
└── dev-router.php       # Router/alias para php -S
```
