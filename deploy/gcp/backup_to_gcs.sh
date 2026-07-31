#!/usr/bin/env bash
# ==================================================================
#  backup_to_gcs.sh — สำรองข้อมูล แล้วส่งขึ้น Google Cloud Storage
#
#  ทำไมต้องส่งขึ้น Cloud Storage:
#     ไฟล์สำรองที่อยู่บนเครื่องเดียวกับระบบ จะหายไปพร้อมกันถ้าเครื่องเสีย
#     เก็บไว้คนละที่ = กู้คืนได้แม้เครื่องหาย
#
#  เตรียมครั้งเดียว:
#     gcloud storage buckets create gs://erp-backup-โรงเรียนของคุณ \
#         --location=asia-southeast1 --uniform-bucket-level-access
#     # ให้ VM มีสิทธิ์เขียน (ทำจากเครื่องตัวเอง):
#     gcloud projects add-iam-policy-binding <PROJECT_ID> \
#         --member="serviceAccount:<เลขโปรเจกต์>-compute@developer.gserviceaccount.com" \
#         --role="roles/storage.objectAdmin"
#
#  ใช้งาน:
#     BUCKET=gs://erp-backup-โรงเรียนของคุณ bash deploy/gcp/backup_to_gcs.sh
#
#  ตั้งอัตโนมัติ (แทน cron เดิมที่สำรองลงเครื่องอย่างเดียว):
#     0 2 * * * root BUCKET=gs://... /bin/bash /var/www/school-erp/deploy/gcp/backup_to_gcs.sh
# ==================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BUCKET="${BUCKET:-}"
KEEP_LOCAL_DAYS="${KEEP_LOCAL_DAYS:-7}"   # บนเครื่องเก็บสั้น ๆ พอ ที่เหลืออยู่บนคลาวด์

[ -n "$BUCKET" ] || { echo "✗ ยังไม่ได้ระบุ BUCKET (เช่น BUCKET=gs://erp-backup-myschool)"; exit 1; }
command -v gcloud >/dev/null || { echo "✗ ไม่พบคำสั่ง gcloud บนเครื่องนี้"; exit 1; }

# 1) สำรองตามปกติ (รองรับหลายโรงเรียนอยู่แล้ว)
KEEP_DAYS="$KEEP_LOCAL_DAYS" bash "$ROOT/deploy/backup.sh"

# 2) ส่งขึ้น Cloud Storage (เฉพาะไฟล์ใหม่)
DEST="$BUCKET/$(hostname)/$(date +%Y/%m)"
echo "→ กำลังส่งขึ้น $DEST"
gcloud storage rsync "$ROOT/backups" "$DEST" --recursive --no-clobber

echo "✓ ส่งไฟล์สำรองขึ้น Cloud Storage แล้ว"
echo "  ตรวจรายการ: gcloud storage ls -r $DEST"
echo
echo "  แนะนำ: ตั้งกฎลบอัตโนมัติที่ bucket เพื่อไม่ให้ค่าเก็บข้อมูลบานปลาย เช่น เก็บ 180 วัน"
echo "  gcloud storage buckets update $BUCKET --lifecycle-file=lifecycle.json"
