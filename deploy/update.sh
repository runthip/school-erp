#!/usr/bin/env bash
# ==================================================================
#  update.sh — อัปเดตระบบให้ปลอดภัย ครบทุกโรงเรียน ในคำสั่งเดียว
#
#  ขั้นตอนที่สคริปต์ทำให้:
#     1) ตรวจว่ามีอะไรค้างอยู่บ้าง (ไม่แก้อะไร)
#     2) เปิดโหมดปิดปรับปรุง   ← ผู้ใช้หยุดบันทึกข้อมูล ป้องกันข้อมูลค้างกลางคัน
#     3) สำรองฐานข้อมูล + ไฟล์แนบ (ทุกโรงเรียน)
#     4) นำเข้าไฟล์ SQL ที่ค้าง ให้ทุกโรงเรียน
#     5) ตรวจว่าโครงสร้างครบ + ทดสอบว่าเว็บตอบสนอง
#     6) ปิดโหมดปรับปรุง → ใช้งานต่อได้
#
#  ใช้งาน:
#     bash deploy/update.sh --dry-run     ดูก่อนว่าจะเกิดอะไร (ไม่แตะระบบ)
#     bash deploy/update.sh               อัปเดตจริง
#     bash deploy/update.sh --url=https://erp.myschool.ac.th   ระบุ URL เพื่อทดสอบหลังอัปเดต
#     bash deploy/update.sh --no-backup   ข้ามการสำรอง (ไม่แนะนำ)
#
#  ⚠ อัปโหลด/ดึงโค้ดใหม่ให้เรียบร้อย "ก่อน" รันสคริปต์นี้
#     (อย่าทับ .env, storage/, public/uploads/, backups/)
# ==================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP="$(command -v php || echo /Applications/XAMPP/xamppfiles/bin/php)"
DRY=false; DO_BACKUP=true; URL=""

for a in "$@"; do
  case "$a" in
    --dry-run)   DRY=true ;;
    --no-backup) DO_BACKUP=false ;;
    --url=*)     URL="${a#--url=}" ;;
    -h|--help)   sed -n '2,25p' "$0"; exit 0 ;;
    *) echo "ไม่รู้จักตัวเลือก: $a"; exit 2 ;;
  esac
done

[ -x "$PHP" ] || { echo "✗ ไม่พบคำสั่ง php"; exit 1; }
[ -f "$ROOT/.env" ] || { echo "✗ ไม่พบไฟล์ .env — ยังไม่ได้ติดตั้งระบบ?"; exit 1; }

hr() { printf '%.0s─' $(seq 1 66); echo; }
step() { echo; hr; echo " $1"; hr; }

VERSION="$(cat "$ROOT/VERSION" 2>/dev/null || echo 'ไม่ระบุ')"
echo "═══════════════════════════════════════════════════════════════"
echo "  อัปเดตระบบ School ERP   ·   รุ่นในโฟลเดอร์นี้: $VERSION"
[ "$DRY" = true ] && echo "  โหมดทดลอง (--dry-run) — จะไม่แก้ไขอะไรทั้งสิ้น"
echo "═══════════════════════════════════════════════════════════════"

# ---------- 1) ตรวจก่อน ----------
step "1/6  ตรวจสิ่งที่ค้างอยู่"
"$PHP" "$ROOT/deploy/migrate.php" --status

if [ "$DRY" = true ]; then
  echo
  echo "จบโหมดทดลอง — ยังไม่ได้แก้ไขอะไร"
  echo "พร้อมแล้วให้รัน: bash deploy/update.sh"
  exit 0
fi

# ---------- 2) ปิดปรับปรุง ----------
step "2/6  เปิดโหมดปิดปรับปรุงระบบ"
bash "$ROOT/deploy/maintenance.sh" on
KEY="$(cat "$ROOT/storage/maintenance.flag")"

# ถ้าเกิดข้อผิดพลาดกลางทาง ต้องไม่ทิ้งระบบไว้ในโหมดปิด โดยไม่บอกผู้ใช้
trap 'echo; echo "✗ อัปเดตไม่สำเร็จ — ระบบยังอยู่ในโหมดปิดปรับปรุง"; \
      echo "  แก้ปัญหาแล้วรันต่อ หรือกู้คืนจาก backups/ แล้วสั่ง:"; \
      echo "  bash deploy/maintenance.sh off"' ERR

# ---------- 3) สำรองข้อมูล ----------
if [ "$DO_BACKUP" = true ]; then
  step "3/6  สำรองฐานข้อมูล + ไฟล์แนบ"
  bash "$ROOT/deploy/backup.sh"
else
  step "3/6  ข้ามการสำรองข้อมูล (--no-backup)"
fi

# ---------- 4) นำเข้าไฟล์ SQL ทุกโรงเรียน ----------
step "4/6  นำเข้าฐานข้อมูลให้ทุกโรงเรียน"
"$PHP" "$ROOT/deploy/migrate.php"

# ---------- 5) ตรวจหลังอัปเดต ----------
step "5/6  ตรวจความเรียบร้อย"
"$PHP" "$ROOT/deploy/migrate.php" --check

# สิทธิ์โฟลเดอร์ที่ต้องเขียนได้ (โค้ดใหม่อาจเพิ่มโฟลเดอร์อัปโหลด)
for d in storage/documents storage/pa storage/activities storage/sar storage/exam_files \
         storage/mail storage/logs storage/sessions public/uploads backups; do
  mkdir -p "$ROOT/$d" && chmod 777 "$ROOT/$d" 2>/dev/null || true
done
echo "✓ ตรวจสิทธิ์โฟลเดอร์ที่ต้องเขียนได้แล้ว"

if [ -n "$URL" ]; then
  CODE="$(curl -s -o /dev/null -w '%{http_code}' "${URL%/}/login?key=$KEY" || echo 000)"
  if [ "$CODE" = "200" ]; then echo "✓ ทดสอบหน้าเข้าสู่ระบบ: HTTP $CODE"
  else echo "⚠ ทดสอบหน้าเข้าสู่ระบบได้ HTTP $CODE — ตรวจ log ก่อนเปิดใช้งาน"; fi
else
  echo "• ข้ามการทดสอบเว็บ (ระบุ --url=https://... เพื่อให้ทดสอบอัตโนมัติ)"
fi

# ---------- 6) เปิดใช้งาน ----------
step "6/6  เปิดใช้งานระบบตามปกติ"
trap - ERR
bash "$ROOT/deploy/maintenance.sh" off

echo
echo "═══════════════════════════════════════════════════════════════"
echo "  ✓ อัปเดตเสร็จสมบูรณ์"
echo "  ไฟล์สำรองล่าสุดอยู่ที่: $ROOT/backups"
echo "  ถ้าพบปัญหาหลังอัปเดต ให้กู้คืนตามขั้นตอนใน UPGRADE.md หัวข้อ 'ย้อนกลับ'"
echo "═══════════════════════════════════════════════════════════════"
