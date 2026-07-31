#!/bin/bash
# ==================================================================
# สำรองข้อมูลอัตโนมัติ School ERP — ตั้งให้รันทุกวัน 02:00 น.
# วิธีตั้ง cron (บน XAMPP/Linux/mac):
#   crontab -e
#   0 2 * * * /path/to/school-erp/scripts/backup.sh
# ==================================================================
set -e
DIR="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP_DIR="$DIR/storage/backups"
mkdir -p "$BACKUP_DIR"

# อ่านค่า DB จาก .env
if [ -f "$DIR/.env" ]; then export $(grep -vE '^#' "$DIR/.env" | xargs); fi
DB_NAME="${DB_NAME:-school_erp}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_SOCKET="${DB_SOCKET:-}"

# ถ้ามี socket ให้ใช้ socket (เช่น XAMPP บาง config)
CONN="-h $DB_HOST"
if [ -n "$DB_SOCKET" ]; then CONN="--socket=$DB_SOCKET"; fi

STAMP=$(date +%Y%m%d_%H%M%S)
OUT="$BACKUP_DIR/school_erp_$STAMP.sql.gz"

# dump + gzip
if [ -n "$DB_PASS" ]; then
  mysqldump $CONN -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$OUT"
else
  mysqldump $CONN -u "$DB_USER" "$DB_NAME" | gzip > "$OUT"
fi

# เก็บย้อนหลัง 30 วัน ลบที่เก่ากว่านั้น
find "$BACKUP_DIR" -name "school_erp_*.sql.gz" -mtime +30 -delete

echo "$(date '+%Y-%m-%d %H:%M:%S') backup: $OUT ($(du -h "$OUT" | cut -f1))" >> "$BACKUP_DIR/backup.log"
