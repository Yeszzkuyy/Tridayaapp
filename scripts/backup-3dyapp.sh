#!/usr/bin/env bash
# Backup harian 3DY App: dump DB + file upload + .env terenkripsi.
# Tanpa rahasia di dalam skrip (dibaca dari .env & passfile saat jalan).
set -euo pipefail

APP_DIR=/var/www/3dyapp
BACKUP_DIR="$APP_DIR/backups"
PASS_FILE=/home/yeski/.backup-passphrase
STAMP=$(date +%Y%m%d-%H%M%S)
PKG="$BACKUP_DIR/3dyapp-$STAMP.tar.gz.enc"

env_val() { grep -a "^$1=" "$APP_DIR/.env" | cut -d= -f2-; }

DB_HOST=$(env_val DB_HOST)
DB_PORT=$(env_val DB_PORT)
DB_NAME=$(env_val DB_DATABASE)
DB_USER=$(env_val DB_USERNAME)
DB_PASS=$(env_val DB_PASSWORD)

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

export PGPASSWORD="$DB_PASS"
pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -Fc -f "$WORK/db.dump"
unset PGPASSWORD

cp "$APP_DIR/.env" "$WORK/.env"
chmod 600 "$WORK/.env"

# Video background statis & sudah di GitHub: tidak ikut paket harian.
tar -czf - -C "$APP_DIR" \
    storage/app/public \
    public/images \
    -C "$WORK" db.dump .env \
    | openssl enc -aes-256-cbc -salt -pbkdf2 -pass "file:$PASS_FILE" -out "$PKG"
chmod 600 "$PKG"

# Retensi lokal 7 hari (paket harian saja; dump guardrail pre-destructive dibiarkan).
find "$BACKUP_DIR" -maxdepth 1 -name '3dyapp-*.tar.gz.enc' -mtime +7 -delete

echo "[$STAMP] backup OK: $PKG ($(du -h "$PKG" | cut -f1))"

# Unggah ke Google Drive di folder Backup-3DYApp
if command -v rclone >/dev/null 2>&1; then
    rclone copy "$PKG" gdrive:Backup-3DYApp/
    # Hapus file backup di Google Drive yang umurnya lebih dari 30 hari
    rclone delete --min-age 30d gdrive:Backup-3DYApp/
    echo "[$STAMP] upload Google Drive OK: Backup-3DYApp/"
fi
