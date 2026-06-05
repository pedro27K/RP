#!/usr/bin/env bash
# ============================================================
#  RP TRAVELS — RESTAURAR UNA COPIA DE SEGURIDAD
# ------------------------------------------------------------
#  Uso:
#    ./scripts/restore.sh backups/rp_travels_2026-06-04_03-00-00.sql.gz
#
#  OJO: Sobrescribe los datos actuales de la base de datos.
# ============================================================
set -euo pipefail

DB_NAME="${DB_NAME:-rp_travels}"
DB_USER="${DB_USER:-rp_admin}"           # restaurar requiere privilegios de escritura/DDL
DB_PASS="${DB_PASS:-Admin_RP_2026!}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_CONTAINER="${DB_CONTAINER:-rp_db}"

FILE="${1:-}"
if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
    echo "Uso: $0 <archivo.sql.gz>"
    exit 1
fi

echo "Vas a restaurar '${FILE}' sobre la base de datos '${DB_NAME}'."
read -r -p "¿Seguro? Se perderán los datos actuales [escribe SI]: " ok
[ "$ok" = "SI" ] || { echo "Cancelado."; exit 0; }

if command -v mysql >/dev/null 2>&1; then
    gunzip -c "$FILE" | mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"
elif command -v docker >/dev/null 2>&1; then
    gunzip -c "$FILE" | docker exec -i "$DB_CONTAINER" \
        sh -c "exec mysql -u\"$DB_USER\" -p\"$DB_PASS\" \"$DB_NAME\""
else
    echo "ERROR: no se encontró ni 'mysql' ni 'docker'."
    exit 1
fi

echo "Restauración completada."
