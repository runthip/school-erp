#!/usr/bin/env bash
# ==================================================================
#  backup.sh — สำรองฐานข้อมูล + ไฟล์แนบของ School ERP
#  ใช้เอง:      bash deploy/backup.sh
#  ตั้งอัตโนมัติ: 0 2 * * * bash /var/www/school-erp/deploy/backup.sh
#  เก็บย้อนหลัง KEEP_DAYS วัน (ค่าเริ่มต้น 30 วัน)
# ==================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/backups"
KEEP_DAYS="${KEEP_DAYS:-30}"
TS="$(date +%Y%m%d_%H%M%S)"

MYSQLDUMP="$(command -v mysqldump || echo /Applications/XAMPP/xamppfiles/bin/mysqldump)"
[ -x "$MYSQLDUMP" ] || { echo "✗ ไม่พบคำสั่ง mysqldump"; exit 1; }

# อ่านค่าฐานข้อมูลจาก .env
[ -f "$ROOT/.env" ] || { echo "✗ ไม่พบไฟล์ .env"; exit 1; }
getenv() { grep -E "^$1=" "$ROOT/.env" | head -1 | cut -d= -f2- | sed 's/^"//; s/"$//'; }
DB_NAME="$(getenv DB_NAME)"; DB_USER="$(getenv DB_USER)"
DB_PASS="$(getenv DB_PASS)"; DB_HOST="$(getenv DB_HOST)"
MULTI="$(getenv MULTI_TENANT)"; CENTRAL_DB="$(getenv CENTRAL_DB_NAME)"
: "${DB_HOST:=127.0.0.1}"
[ -n "$DB_NAME" ] || { echo "✗ ไม่พบ DB_NAME ใน .env"; exit 1; }

mkdir -p "$OUT"

ARGS=(-h"$DB_HOST" -u"$DB_USER")
[ -n "$DB_PASS" ] && ARGS+=(-p"$DB_PASS")

dump_db() {  # dump_db <ชื่อฐานข้อมูล>
  local db="$1" f="$OUT/db_$1_$TS.sql"
  "$MYSQLDUMP" "${ARGS[@]}" --routines --triggers --single-transaction "$db" > "$f"
  gzip -f "$f"
  echo "✓ ฐานข้อมูล : $(basename "$f").gz ($(du -h "$f.gz" | cut -f1))"
}

# ---------- 1) ฐานข้อมูล ----------
if [ "$(printf '%s' "$MULTI" | tr 'A-Z' 'a-z')" = "true" ] && [ -n "$CENTRAL_DB" ]; then
  # ระบบหลายโรงเรียน — สำรองศูนย์กลาง + ทุกโรงเรียนในทะเบียน
  MYSQL_BIN="$(command -v mysql || echo /Applications/XAMPP/xamppfiles/bin/mysql)"
  dump_db "$CENTRAL_DB"
  TENANT_DBS="$("$MYSQL_BIN" "${ARGS[@]}" -N -B -e \
      "SELECT db_name FROM \`$CENTRAL_DB\`.tenants ORDER BY school_code" || true)"
  COUNT=0
  for db in $TENANT_DBS; do dump_db "$db"; COUNT=$((COUNT+1)); done
  echo "✓ สำรองครบ $COUNT โรงเรียน (+ ศูนย์กลาง)"
else
  dump_db "$DB_NAME"
fi

# ---------- 2) ไฟล์แนบ + โลโก้ ----------
FILES="$OUT/files_$TS.tar.gz"
tar -czf "$FILES" -C "$ROOT" \
    --exclude='storage/sessions/*' --exclude='storage/logs/*' \
    storage public/uploads 2>/dev/null || true
echo "✓ ไฟล์แนบ  : $(basename "$FILES") ($(du -h "$FILES" | cut -f1))"

# ---------- 3) ลบไฟล์เก่าเกินกำหนด ----------
DELETED=$(find "$OUT" -type f \( -name 'db_*.sql.gz' -o -name 'files_*.tar.gz' \) \
          -mtime +"$KEEP_DAYS" -print -delete | wc -l | tr -d ' ')
echo "✓ ลบไฟล์เก่ากว่า $KEEP_DAYS วัน: $DELETED ไฟล์"
echo "  ที่เก็บ: $OUT ($(du -sh "$OUT" | cut -f1))"
echo "  [$(date '+%Y-%m-%d %H:%M:%S')] สำรองข้อมูลเสร็จสมบูรณ์"
