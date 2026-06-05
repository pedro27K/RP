#!/usr/bin/env bash
# ============================================================
#  RP TRAVELS — COPIA DE SEGURIDAD AUTOMÁTICA DE LA BASE DE DATOS
#  Módulos: ASGBD (backups periódicos)
#           ASO (lenguajes de guiones / shell script)
# ------------------------------------------------------------
#  Qué hace?:
#    · Vuelca la base de datos completa (mysqldump) comprimida en .gz
#    · Nombra el archivo con fecha y hora
#    · Borra automáticamente las copias con más de RETENTION_DAYS días
#    · Escribe un registro en backup.log
#
#  Funciona de dos formas (autodetección):
#    · Si hay 'mysqldump' instalado en la máquina, lo usa directamente.
#    · Si no, pero hay Docker, ejecuta mysqldump dentro del contenedor rp_db.
#
#  Uso manual:
#    ./scripts/backup.sh
#
#  Automatización (cron): ver scripts/crontab.example
# ============================================================
set -euo pipefail

# ── Configuración (puedes sobreescribir con variables de entorno) ──
DB_NAME="${DB_NAME:-rp_travels}"
DB_USER="${DB_USER:-rp_backup}"          # usuario dedicado (ver sql/rp.sql)
DB_PASS="${DB_PASS:-Backup_RP_2026!}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_CONTAINER="${DB_CONTAINER:-rp_db}"    # nombre del contenedor MySQL
BACKUP_DIR="${BACKUP_DIR:-$(cd "$(dirname "$0")/.." && pwd)/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"

STAMP="$(date +%Y-%m-%d_%H-%M-%S)"
OUTFILE="${BACKUP_DIR}/rp_travels_${STAMP}.sql.gz"
LOGFILE="${BACKUP_DIR}/backup.log"

DUMP_OPTS="--single-transaction --routines --triggers --events --no-tablespaces"

mkdir -p "$BACKUP_DIR"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOGFILE"; }

log "Iniciando copia de seguridad de '${DB_NAME}'..."

if command -v mysqldump >/dev/null 2>&1; then
    log "Método: mysqldump local (host ${DB_HOST})"
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" $DUMP_OPTS "$DB_NAME" \
        | gzip > "$OUTFILE"
elif command -v docker >/dev/null 2>&1; then
    log "Método: mysqldump dentro del contenedor ${DB_CONTAINER}"
    docker exec "$DB_CONTAINER" \
        sh -c "exec mysqldump -u\"$DB_USER\" -p\"$DB_PASS\" $DUMP_OPTS \"$DB_NAME\"" \
        | gzip > "$OUTFILE"
else
    log "ERROR: no se encontró ni 'mysqldump' ni 'docker'. Abortando."
    exit 1
fi

SIZE="$(du -h "$OUTFILE" | cut -f1)"
log "Copia creada: ${OUTFILE} (${SIZE})"

# ── Rotación: eliminar copias antiguas ──────────────────────
DELETED="$(find "$BACKUP_DIR" -name 'rp_travels_*.sql.gz' -mtime +"$RETENTION_DAYS" -print -delete | wc -l)"
log "Rotación: ${DELETED} copia(s) anterior(es) a ${RETENTION_DAYS} días eliminada(s)."
log "Copia de seguridad finalizada correctamente."
