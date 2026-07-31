#!/usr/bin/env bash
# ==================================================================
#  central_setup.sh — ติดตั้ง "ศูนย์ควบคุมส่วนกลาง" (ระบบหลายโรงเรียน)
#
#  ใช้เมื่อ: ต้องการรันโค้ดชุดเดียว ให้บริการหลายโรงเรียน
#            โดยแยกฐานข้อมูลของแต่ละโรงเรียนออกจากกัน
#            และให้ Super admin เข้าไปดูแลผ่านแอดมินของแต่ละโรงเรียนได้
#
#  ทำอะไรบ้าง:
#    1) สร้างฐานข้อมูลศูนย์กลาง + ตาราง tenants / platform_admins / tenant_access_logs
#    2) สร้างบัญชี Super admin คนแรก
#    3) ให้สิทธิ์บัญชี MySQL ของแอป กับฐานข้อมูลโรงเรียนทุกตัว (erp_%)
#    4) แนะนำค่าที่ต้องใส่ใน .env
#
#  วิธีใช้: bash deploy/central_setup.sh
# ==================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SQL="$ROOT/database/central/01_central.sql"
MYSQL="$(command -v mysql || echo /Applications/XAMPP/xamppfiles/bin/mysql)"
PHP="$(command -v php || echo /Applications/XAMPP/xamppfiles/bin/php)"

[ -f "$SQL" ] || { echo "✗ ไม่พบไฟล์ $SQL"; exit 1; }
[ -x "$MYSQL" ] || { echo "✗ ไม่พบคำสั่ง mysql"; exit 1; }

ask() { local q="$1" def="$2" var="$3"
  if [ -n "${!var+x}" ]; then [ -z "${!var}" ] && printf -v "$var" '%s' "$def"; echo "  $q: ${!var}"; return; fi
  read -r -p "  $q [$def]: " ans || true; printf -v "$var" '%s' "${ans:-$def}"; }
asksecret() { local q="$1" var="$2"
  if [ -n "${!var+x}" ]; then echo "  $q: (กำหนดแล้ว)"; return; fi
  read -r -s -p "  $q: " ans; echo; printf -v "$var" '%s' "$ans"; }

echo "=================================================="
echo " ติดตั้งศูนย์ควบคุมส่วนกลาง (ระบบหลายโรงเรียน)"
echo "=================================================="
echo "[1/4] ฐานข้อมูลศูนย์กลาง"
ask "ชื่อฐานข้อมูลศูนย์กลาง" "school_erp_central" CENTRAL_DB
ask "ผู้ดูแล MySQL (มีสิทธิ์สร้างฐานข้อมูล)" "root" MYSQL_ADMIN
asksecret "รหัสผ่าน MySQL ของ $MYSQL_ADMIN (เว้นว่างได้)" MYSQL_ADMIN_PASS

echo "[2/4] บัญชี MySQL ที่ระบบเว็บใช้เชื่อมต่อ (ใช้ร่วมกันทุกโรงเรียน)"
ask "ผู้ใช้ฐานข้อมูลของแอป" "erp_app" APP_DB_USER
asksecret "รหัสผ่านผู้ใช้ฐานข้อมูลของแอป" APP_DB_PASS
ask "คำนำหน้าฐานข้อมูลโรงเรียน" "erp_" TENANT_PREFIX

echo "[3/4] บัญชีผู้ดูแลระบบส่วนกลาง (Super admin) คนแรก"
ask "ชื่อผู้ใช้" "superadmin" SA_USER
ask "ชื่อ-สกุล" "ผู้ดูแลระบบส่วนกลาง" SA_NAME
ask "อีเมล" "" SA_EMAIL
asksecret "รหัสผ่าน (อย่างน้อย 8 ตัว)" SA_PASS
[ ${#SA_PASS} -ge 8 ] || { echo "✗ รหัสผ่านสั้นเกินไป"; exit 1; }

MYARGS=(-u"$MYSQL_ADMIN")
[ -n "${MYSQL_ADMIN_PASS:-}" ] && MYARGS+=(-p"$MYSQL_ADMIN_PASS")

echo "[4/4] กำลังติดตั้ง…"
"$MYSQL" "${MYARGS[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$CENTRAL_DB\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"$MYSQL" "${MYARGS[@]}" "$CENTRAL_DB" < "$SQL"
echo "      ✓ สร้างตารางศูนย์กลางแล้ว"

# บัญชีของแอป: ต้องเข้าถึงฐานข้อมูลศูนย์กลาง + ฐานข้อมูลของทุกโรงเรียน (erp_%)
# ใช้ ESCAPE เพื่อให้ _ ในคำนำหน้าเป็นตัวอักษรจริง ไม่ใช่ wildcard
"$MYSQL" "${MYARGS[@]}" -e "
CREATE USER IF NOT EXISTS '$APP_DB_USER'@'localhost' IDENTIFIED BY '$APP_DB_PASS';
ALTER USER '$APP_DB_USER'@'localhost' IDENTIFIED BY '$APP_DB_PASS';
GRANT ALL PRIVILEGES ON \`$CENTRAL_DB\`.* TO '$APP_DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`${TENANT_PREFIX}%\`.* TO '$APP_DB_USER'@'localhost';
FLUSH PRIVILEGES;"
echo "      ✓ ให้สิทธิ์ '$APP_DB_USER' กับ $CENTRAL_DB และ ${TENANT_PREFIX}%"

HASH="$("$PHP" -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$SA_PASS")"
"$MYSQL" "${MYARGS[@]}" "$CENTRAL_DB" <<SQL
INSERT INTO platform_admins (username, password_hash, full_name, email, status)
VALUES ('$SA_USER', '$HASH', '$SA_NAME', NULLIF('$SA_EMAIL',''), 'active')
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), full_name=VALUES(full_name),
  email=VALUES(email), status='active', failed_attempts=0;
SQL
echo "      ✓ สร้างบัญชี Super admin '$SA_USER' แล้ว"

echo
echo "=================================================="
echo " ✓ ติดตั้งศูนย์ควบคุมเสร็จสมบูรณ์"
echo
echo " ใส่ค่าเหล่านี้ในไฟล์ .env (แล้วสั่ง chmod 644 .env):"
echo "   MULTI_TENANT=true"
echo "   CENTRAL_DB_NAME=$CENTRAL_DB"
echo "   TENANT_DB_PREFIX=$TENANT_PREFIX"
echo "   DB_USER=$APP_DB_USER"
echo "   DB_PASS=********"
echo "   PROVISION_DB_USER=$MYSQL_ADMIN     # ใช้เฉพาะตอนเปิดโรงเรียนใหม่ (CREATE DATABASE)"
echo "   PROVISION_DB_PASS=********"
echo
echo " จากนั้นเปิด  <เว็บของคุณ>/platform/login  เพื่อเข้าศูนย์ควบคุม"
echo " แล้วกด “เปิดใช้งานโรงเรียนใหม่” — ระบบจะสร้างฐานข้อมูลแยกให้เองทีละโรงเรียน"
echo "=================================================="
