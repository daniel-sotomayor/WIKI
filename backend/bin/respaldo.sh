#!/usr/bin/env bash
# ==============================================================
#  TelecomWiki — respaldo y restauración de la base y archivos
#  Archivo: backend/bin/respaldo.sh
#
#  Uso:
#      ./backend/bin/respaldo.sh                  # crear respaldo
#      ./backend/bin/respaldo.sh --listar         # ver respaldos
#      ./backend/bin/respaldo.sh --restaurar archivo.sql[.gz]
#
#  Qué guarda:
#    · telecom_wiki_YYYYmmdd_HHMMSS.sql.gz   (mysqldump de la base)
#    · uploads_YYYYmmdd_HHMMSS.tar.gz        (backend/storage/uploads)
#
#  Credenciales: variables de entorno DB_HOST / DB_PORT / DB_USER /
#  DB_PASS / DB_NAME (mismas que usa el sitio). En XAMPP típico:
#      DB_USER=root DB_PASS= ./backend/bin/respaldo.sh
# ==============================================================
set -euo pipefail

cd "$(dirname "$0")/../.."    # raíz del proyecto (telecom_wiki/)

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"
: "${DB_USER:=telecom_wiki}"
: "${DB_PASS:=telecom2026}"
: "${DB_NAME:=telecom_wiki}"

CARPETA="backend/storage/respaldos"
MARIADB_OPTS=(-h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER")
[ -n "$DB_PASS" ] && MARIADB_OPTS+=(-p"$DB_PASS")

mkdir -p "$CARPETA"

crear() {
    local sello
    sello="$(date +%Y%m%d_%H%M%S)"
    local dump="$CARPETA/${DB_NAME}_${sello}.sql.gz"
    local uploads="$CARPETA/uploads_${sello}.tar.gz"

    echo "→ Volcando la base $DB_NAME en $dump"
    mysqldump "${MARIADB_OPTS[@]}" --single-transaction --routines \
        --default-character-set=utf8mb4 "$DB_NAME" | gzip > "$dump"

    echo "→ Empaquetando backend/storage/uploads en $uploads"
    tar -czf "$uploads" -C backend/storage uploads 2>/dev/null || {
        echo "  (no hay carpeta uploads; se omite)"; rm -f "$uploads";
    }

    echo
    echo "Respaldo listo:"
    ls -lh "$dump" "$uploads" 2>/dev/null || ls -lh "$dump"
    echo
    echo "Guardá estos archivos fuera de la máquina (nube, disco externo)."
}

listar() {
    echo "Respaldos en $CARPETA:"
    ls -lht "$CARPETA" 2>/dev/null || echo "  (vacío)"
}

restaurar() {
    local archivo="${1:-}"
    if [ -z "$archivo" ] || [ ! -f "$archivo" ]; then
        echo "Falta el archivo a restaurar." >&2; exit 1
    fi

    echo "¡Atención! Esto SOBRESCRIBE la base $DB_NAME y carpeta uploads."
    printf "Escribí 'restaurar' para confirmar: "
    read -r confirmacion
    [ "$confirmacion" = "restaurar" ] || { echo "Cancelado."; exit 1; }

    echo "→ Restaurando base desde $archivo"
    case "$archivo" in
        *.gz) gunzip -c "$archivo" | mysql "${MARIADB_OPTS[@]}" "$DB_NAME" ;;
        *)    mysql "${MARIADB_OPTS[@]}" "$DB_NAME" < "$archivo" ;;
    esac

    local up
    up="$(dirname "$archivo")/uploads_$(basename "$archivo" | sed -E 's/.*_([0-9]{8}_[0-9]{6})\.(sql)(\.gz)?$/\1/').tar.gz"
    if [ -f "$up" ]; then
        echo "→ Restaurando archivos desde $up"
        tar -xzf "$up" -C backend/storage
    else
        echo "  (no se encontró el uploads emparejado; se omite)"
    fi
    echo "Listo."
}

case "${1:-crear}" in
    crear|"")        crear ;;
    --listar|listar) listar ;;
    --restaurar)     shift; restaurar "${1:-}" ;;
    *) Uso: $0 [--listar | --restaurar archivo.sql[.gz]] >&2; exit 1 ;;
esac
