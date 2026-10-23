#!/usr/bin/env bash
# ==============================================================
#  TelecomWiki — servidor de desarrollo (sin Apache ni XAMPP)
#
#  Uso:   ./dev-server.sh [puerto]      (por defecto 8000)
#  Luego: http://127.0.0.1:8000/
#
#  Reproduce las protecciones del .htaccess con dev-router.php
#  y acepta URLs cortas (/wiki.php → /frontend/wiki.php).
#
#  Si usás XAMPP con MySQL de root sin contraseña, no hace falta
#  definir nada: los valores por defecto de abajo ya apuntan ahí.
#  En Linux sin XAMPP (MariaDB de sistema) creá un usuario de la
#  base como indica docs/LEEME.md y ajustá las variables.
# ==============================================================
set -euo pipefail
cd "$(dirname "$0")/.."

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"
: "${DB_USER:=telecom_wiki}"
: "${DB_PASS:=telecom2026}"
: "${DB_NAME:=telecom_wiki}"
: "${SITE_URL:=http://127.0.0.1:${1:-8000}}"

export DB_HOST DB_PORT DB_USER DB_PASS DB_NAME SITE_URL

puerto="${1:-8000}"
echo "TelecomWiki → http://127.0.0.1:${puerto}/  (Ctrl+C para cortar)"
exec php -S "127.0.0.1:${puerto}" -t telecom_wiki telecom_wiki/dev-router.php
