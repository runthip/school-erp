#!/usr/bin/env bash
# ==================================================================
#  new_org.sh — ติดตั้ง School ERP ให้โรงเรียน/องค์กรใหม่ 1 แห่ง
#  แนวคิด: 1 การติดตั้ง = 1 โรงเรียน (แยกฐานข้อมูลต่อองค์กร)
#
#  วิธีใช้:
#     bash deploy/new_org.sh
#  หรือกำหนดค่าล่วงหน้า (ไม่ต้องตอบคำถาม):
#     DB_NAME=erp_wat DB_USER=erp_wat DB_PASS='xxx' SCHOOL_NAME='โรงเรียนวัดใหม่' \
#     ADMIN_USER=admin ADMIN_PASS='Str0ng#Pass' bash deploy/new_org.sh
# ==================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CLEAN_SQL="$ROOT/deploy/school_erp_clean.sql"

# หา binary ของ mysql/php (รองรับ XAMPP บน macOS และ Linux ทั่วไป)
MYSQL="$(command -v mysql || echo /Applications/XAMPP/xamppfiles/bin/mysql)"
PHP="$(command -v php || echo /Applications/XAMPP/xamppfiles/bin/php)"

[ -f "$CLEAN_SQL" ] || { echo "✗ ไม่พบไฟล์ $CLEAN_SQL"; exit 1; }
[ -x "$MYSQL" ] || { echo "✗ ไม่พบคำสั่ง mysql"; exit 1; }
[ -x "$PHP" ]   || { echo "✗ ไม่พบคำสั่ง php"; exit 1; }

# ถ้าตัวแปรถูกกำหนดมาแล้ว (แม้เป็นค่าว่าง) จะไม่ถาม — ใช้รันแบบอัตโนมัติได้
ask() { # ask "คำถาม" "ค่าเริ่มต้น" ตัวแปร
  local q="$1" def="$2" var="$3"
  if [ -n "${!var+x}" ]; then
    [ -z "${!var}" ] && printf -v "$var" '%s' "$def"
    echo "  $q: ${!var}"; return
  fi
  read -r -p "  $q [$def]: " ans || true
  printf -v "$var" '%s' "${ans:-$def}"
}
asksecret() {
  local q="$1" var="$2"
  if [ -n "${!var+x}" ]; then echo "  $q: (กำหนดแล้ว)"; return; fi
  read -r -s -p "  $q: " ans; echo
  printf -v "$var" '%s' "$ans"
}

echo "=================================================="
echo " ติดตั้ง School ERP สำหรับองค์กรใหม่"
echo "=================================================="
echo "[1/5] ข้อมูลฐานข้อมูล"
ask "ชื่อฐานข้อมูลใหม่" "school_erp" DB_NAME
ask "ผู้ใช้ฐานข้อมูล (จะสร้างให้ถ้ายังไม่มี)" "$DB_NAME" DB_USER
asksecret "รหัสผ่านผู้ใช้ฐานข้อมูล" DB_PASS
ask "ผู้ดูแล MySQL (สำหรับสร้าง DB)" "root" MYSQL_ADMIN
asksecret "รหัสผ่าน MySQL ของ $MYSQL_ADMIN (เว้นว่างได้ถ้าไม่มี)" MYSQL_ADMIN_PASS

echo "[2/5] ข้อมูลโรงเรียน"
ask "ชื่อโรงเรียน (ไทย)" "โรงเรียนตัวอย่าง" SCHOOL_NAME
ask "รหัสสถานศึกษา" "1000000000" SCHOOL_CODE
ask "สังกัด" "สพป." SCHOOL_AFFIL
ask "จังหวัด" "" SCHOOL_PROVINCE

echo "[3/5] บัญชีผู้ดูแลระบบคนแรก"
ask "ชื่อผู้ใช้แอดมิน" "admin" ADMIN_USER
ask "ชื่อ-สกุลแอดมิน" "ผู้ดูแลระบบ" ADMIN_NAME
ask "อีเมลแอดมิน (ใช้กู้รหัสผ่าน)" "" ADMIN_EMAIL
asksecret "รหัสผ่านแอดมิน (อย่างน้อย 8 ตัว)" ADMIN_PASS
[ ${#ADMIN_PASS} -ge 8 ] || { echo "✗ รหัสผ่านสั้นเกินไป (ต้อง >= 8 ตัวอักษร)"; exit 1; }

MYARGS=(-u"$MYSQL_ADMIN")
[ -n "${MYSQL_ADMIN_PASS:-}" ] && MYARGS+=(-p"$MYSQL_ADMIN_PASS")

echo "[4/5] สร้างฐานข้อมูลและนำเข้าโครงสร้าง…"
"$MYSQL" "${MYARGS[@]}" -e "
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;"
"$MYSQL" "${MYARGS[@]}" "$DB_NAME" < "$CLEAN_SQL"
echo "      ✓ นำเข้าโครงสร้าง + ค่าตั้งต้นแล้ว"

echo "[5/5] ตั้งค่าโรงเรียน + สร้างแอดมิน…"
HASH="$("$PHP" -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$ADMIN_PASS")"
"$MYSQL" "${MYARGS[@]}" "$DB_NAME" <<SQL
INSERT INTO schools (id, school_code, name_th, affiliation, province)
VALUES (1, '$SCHOOL_CODE', '$SCHOOL_NAME', '$SCHOOL_AFFIL', '$SCHOOL_PROVINCE')
ON DUPLICATE KEY UPDATE school_code=VALUES(school_code), name_th=VALUES(name_th),
  affiliation=VALUES(affiliation), province=VALUES(province);

INSERT INTO users (username, email, password_hash, full_name, status, linked_type, must_change_password)
VALUES ('$ADMIN_USER', NULLIF('$ADMIN_EMAIL',''), '$HASH', '$ADMIN_NAME', 'active', 'none', 0);
SET @uid = LAST_INSERT_ID();
INSERT INTO user_roles (user_id, role_id)
SELECT @uid, id FROM roles WHERE code='super_admin';

-- ปีการศึกษาปัจจุบัน + 2 ภาคเรียน (พ.ศ. ปีปัจจุบัน)
INSERT INTO academic_years (school_id, year_be, is_current) VALUES (1, YEAR(CURDATE())+543, 1);
SET @ay = LAST_INSERT_ID();
INSERT INTO semesters (academic_year_id, term, is_current) VALUES (@ay, 1, 1), (@ay, 2, 0);
SQL

# สร้างไฟล์ .env ถ้ายังไม่มี
if [ ! -f "$ROOT/.env" ]; then
  sed -e "s/^DB_NAME=.*/DB_NAME=$DB_NAME/" \
      -e "s/^DB_USER=.*/DB_USER=$DB_USER/" \
      -e "s|^DB_PASS=.*|DB_PASS=$DB_PASS|" \
      -e "s/^APP_NAME=.*/APP_NAME=\"$SCHOOL_NAME\"/" \
      "$ROOT/.env.example" > "$ROOT/.env"
  # ต้องให้ผู้ใช้ที่รันเว็บเซิร์ฟเวอร์อ่านได้ ไม่งั้นระบบจะถอยไปใช้ค่า default
  # ปลอดภัยเพราะไฟล์อยู่นอก public/ และถูกบล็อกด้วย .htaccess
  # บนเซิร์ฟเวอร์จริงแนะนำ: chown www-data:www-data .env && chmod 640 .env
  chmod 644 "$ROOT/.env"
  echo "      ✓ สร้างไฟล์ .env แล้ว (สิทธิ์ 644 — เว็บเซิร์ฟเวอร์ต้องอ่านได้)"
else
  echo "      • มีไฟล์ .env อยู่แล้ว — ไม่เขียนทับ (ตรวจค่า DB_NAME/DB_USER/DB_PASS เอง)"
fi

# เตรียมสิทธิ์โฟลเดอร์ที่ต้องเขียนได้
for d in storage/documents storage/pa storage/activities storage/sar storage/exam_files \
         storage/mail storage/logs storage/sessions public/uploads backups; do
  mkdir -p "$ROOT/$d" && chmod 777 "$ROOT/$d"
done
echo "      ✓ ตั้งสิทธิ์โฟลเดอร์อัปโหลด/เซสชันแล้ว"

echo
echo "=================================================="
echo " ✓ ติดตั้งเสร็จสมบูรณ์"
echo "   โรงเรียน   : $SCHOOL_NAME"
echo "   ฐานข้อมูล  : $DB_NAME (ผู้ใช้ $DB_USER)"
echo "   เข้าระบบ   : $ADMIN_USER"
echo
echo " ขั้นต่อไป:"
echo "   1) ชี้ DocumentRoot ของเว็บไปที่โฟลเดอร์ public/"
echo "   2) เปิดเว็บ → เข้าสู่ระบบด้วยบัญชีแอดมินข้างต้น"
echo "   3) ตั้งค่าระบบ → ข้อมูลโรงเรียน/โลโก้/ตราครุฑ, ช่วงชั้นที่เปิดสอน"
echo "   4) เพิ่มบุคลากร → ผู้ใช้ → ชั้นเรียน → รายวิชา"
echo "=================================================="
