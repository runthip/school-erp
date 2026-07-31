#!/usr/bin/env bash
# ==================================================================
#  maintenance.sh — เปิด/ปิด "โหมดปิดปรับปรุงระบบ"
#
#      bash deploy/maintenance.sh on      ปิดระบบชั่วคราว (ผู้ใช้เห็นหน้าแจ้งปรับปรุง)
#      bash deploy/maintenance.sh off     เปิดใช้งานตามปกติ
#      bash deploy/maintenance.sh status  ดูสถานะ + ลิงก์สำหรับผู้ดูแล
#
#  ระหว่างเปิดโหมดนี้ ผู้ดูแลยังเข้าทดสอบได้ด้วยลิงก์ที่มี ?key=... ที่สคริปต์แสดงให้
# ==================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FLAG="$ROOT/storage/maintenance.flag"
ACTION="${1:-status}"

show_key() {
  local key; key="$(cat "$FLAG")"
  echo "  กุญแจสำหรับผู้ดูแล: $key"
  echo "  เข้าทดสอบได้ที่   : https://<เว็บของคุณ>/?key=$key"
  echo "  (ระบบจะจำไว้ในคุกกี้ 2 ชั่วโมง เปิดหน้าอื่นต่อได้เลย)"
}

case "$ACTION" in
  on)
    mkdir -p "$ROOT/storage"
    if [ -f "$FLAG" ]; then
      echo "• โหมดปิดปรับปรุงเปิดอยู่แล้ว"
    else
      head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$FLAG"
      chmod 644 "$FLAG"
      echo "✓ เปิดโหมดปิดปรับปรุงแล้ว — ผู้ใช้ทั่วไปจะเห็นหน้าแจ้งปรับปรุง (HTTP 503)"
    fi
    show_key
    ;;
  off)
    if [ -f "$FLAG" ]; then rm -f "$FLAG"; echo "✓ ปิดโหมดปรับปรุงแล้ว — ระบบใช้งานได้ตามปกติ"
    else echo "• ระบบเปิดใช้งานอยู่แล้ว"; fi
    ;;
  status)
    if [ -f "$FLAG" ]; then
      echo "🛠 สถานะ: กำลังปิดปรับปรุง (ตั้งแต่ $(date -r "$FLAG" '+%Y-%m-%d %H:%M:%S'))"
      show_key
    else
      echo "✓ สถานะ: เปิดใช้งานตามปกติ"
    fi
    ;;
  *)
    echo "ใช้: bash deploy/maintenance.sh [on|off|status]"; exit 2 ;;
esac
