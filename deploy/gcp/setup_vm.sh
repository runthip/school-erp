#!/usr/bin/env bash
# ==================================================================
#  setup_vm.sh — เตรียมเครื่อง VM บน Google Cloud (Debian 12) ให้พร้อมรัน School ERP
#
#  ใช้กับ: Compute Engine · Debian 12 (bookworm) · เครื่องเปล่า
#  รันบนเครื่อง VM (ไม่ใช่บนเครื่องตัวเอง):
#
#      sudo bash setup_vm.sh erp.yourschool.ac.th admin@yourschool.ac.th
#
#      อาร์กิวเมนต์ 1 = ชื่อโดเมน (ถ้าไม่มีโดเมน ใส่ IP ของเครื่องแทนได้ แต่จะทำ HTTPS ไม่ได้)
#      อาร์กิวเมนต์ 2 = อีเมลสำหรับใบรับรอง HTTPS (เว้นว่าง = ข้ามการขอใบรับรอง)
#
#  ทำอะไรให้บ้าง:
#    1) ติดตั้ง Apache + PHP 8.2 + ส่วนขยายที่ระบบต้องใช้ + MariaDB
#    2) ตั้งเขตเวลาไทย · ปรับ php.ini ให้รองรับไฟล์แนบ 25 MB
#    3) สร้าง VirtualHost ชี้ DocumentRoot ไปที่ public/ + เปิด mod_rewrite
#    4) ตั้งสิทธิ์โฟลเดอร์ที่ต้องเขียนได้ให้ www-data
#    5) ขอใบรับรอง HTTPS จาก Let's Encrypt (จำเป็นสำหรับกล้องตรวจข้อสอบ)
#    6) เปิดต่ออายุใบรับรองอัตโนมัติ + ตั้ง cron สำรองข้อมูลทุกคืน
#
#  รันซ้ำได้ปลอดภัย
# ==================================================================
set -euo pipefail

DOMAIN="${1:-}"
EMAIL="${2:-}"
APPDIR="/var/www/school-erp"

[ "$(id -u)" -eq 0 ] || { echo "✗ ต้องรันด้วย sudo"; exit 1; }
[ -n "$DOMAIN" ] || { echo "ใช้: sudo bash setup_vm.sh <โดเมนหรือ IP> [อีเมล]"; exit 2; }

echo "=================================================="
echo " เตรียมเครื่องสำหรับ School ERP"
echo " โดเมน: $DOMAIN"
echo "=================================================="

# ---------- 1) แพ็กเกจที่ต้องใช้ ----------
echo "[1/6] ติดตั้งแพ็กเกจ…"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq \
    apache2 libapache2-mod-php \
    php php-cli php-mysql php-mbstring php-gd php-zip php-curl php-xml \
    mariadb-server \
    certbot python3-certbot-apache \
    rsync curl unzip cron
echo "      ✓ PHP $(php -r 'echo PHP_VERSION;') · $(mysql --version | awk '{print $3,$4}')"

# ---------- 2) เขตเวลา + ค่า PHP ----------
echo "[2/6] ตั้งเขตเวลาและค่า PHP…"
timedatectl set-timezone Asia/Bangkok || true

PHPVER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
for ini in "/etc/php/$PHPVER/apache2/php.ini" "/etc/php/$PHPVER/cli/php.ini"; do
  [ -f "$ini" ] || continue
  sed -i \
    -e 's|^;\?date.timezone =.*|date.timezone = Asia/Bangkok|' \
    -e 's|^upload_max_filesize =.*|upload_max_filesize = 25M|' \
    -e 's|^post_max_size =.*|post_max_size = 30M|' \
    -e 's|^max_file_uploads =.*|max_file_uploads = 30|' \
    -e 's|^memory_limit =.*|memory_limit = 256M|' \
    -e 's|^max_execution_time =.*|max_execution_time = 120|' \
    -e 's|^;\?expose_php =.*|expose_php = Off|' \
    "$ini"
done
echo "      ✓ อัปโหลดได้ถึง 25 MB · เขตเวลา Asia/Bangkok"

# ---------- 3) Apache VirtualHost ----------
echo "[3/6] ตั้งค่าเว็บเซิร์ฟเวอร์…"
mkdir -p "$APPDIR/public"
cat > /etc/apache2/sites-available/school-erp.conf <<CONF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $APPDIR/public

    <Directory $APPDIR/public>
        AllowOverride All
        Require all granted
    </Directory>

    # กันการเข้าถึงไฟล์อ่อนไหวจากภายนอก (ซ้อนกับ .htaccess ที่ราก)
    <DirectoryMatch "^$APPDIR/(app|config|database|storage|backups|deploy)">
        Require all denied
    </DirectoryMatch>

    ServerTokens Prod
    ErrorLog  \${APACHE_LOG_DIR}/school-erp-error.log
    CustomLog \${APACHE_LOG_DIR}/school-erp-access.log combined
</VirtualHost>
CONF

a2enmod rewrite headers > /dev/null
a2dissite 000-default > /dev/null 2>&1 || true
a2ensite school-erp > /dev/null
apache2ctl configtest
systemctl reload apache2
echo "      ✓ DocumentRoot = $APPDIR/public"

# ---------- 4) สิทธิ์โฟลเดอร์ ----------
echo "[4/6] ตั้งสิทธิ์โฟลเดอร์…"
for d in storage/documents storage/pa storage/activities storage/sar storage/exam_files \
         storage/mail storage/logs storage/sessions public/uploads backups; do
  mkdir -p "$APPDIR/$d"
done
chown -R www-data:www-data "$APPDIR/storage" "$APPDIR/public/uploads" "$APPDIR/backups"
chmod -R 775 "$APPDIR/storage" "$APPDIR/public/uploads" "$APPDIR/backups"
# .env ต้องให้เว็บเซิร์ฟเวอร์อ่านได้ ไม่งั้นระบบจะถอยไปใช้ค่า default เงียบ ๆ
if [ -f "$APPDIR/.env" ]; then chown www-data:www-data "$APPDIR/.env"; chmod 640 "$APPDIR/.env"; fi
echo "      ✓ เจ้าของ www-data · เขียนได้ครบ"

# ---------- 5) HTTPS ----------
echo "[5/6] ใบรับรอง HTTPS…"
if [ -n "$EMAIL" ] && [[ "$DOMAIN" =~ [a-zA-Z] ]]; then
  if certbot --apache -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect; then
    echo "      ✓ เปิด HTTPS แล้ว (ต่ออายุอัตโนมัติผ่าน systemd timer)"
  else
    echo "      ⚠ ขอใบรับรองไม่สำเร็จ — ตรวจว่า DNS ของ $DOMAIN ชี้มาที่เครื่องนี้แล้ว"
    echo "        แก้ DNS เสร็จแล้วสั่ง: sudo certbot --apache -d $DOMAIN --redirect"
  fi
else
  echo "      • ข้ามการขอใบรับรอง (ไม่ได้ระบุอีเมล หรือใช้ IP แทนโดเมน)"
  echo "        ⚠ กล้องตรวจข้อสอบใช้ได้เฉพาะบน HTTPS เท่านั้น"
fi

# ---------- 6) สำรองข้อมูลอัตโนมัติ ----------
echo "[6/6] ตั้งสำรองข้อมูลอัตโนมัติทุกคืน…"
CRON="/etc/cron.d/school-erp-backup"
cat > "$CRON" <<CRONF
# สำรองฐานข้อมูล + ไฟล์แนบ ทุกวันตี 2 (เก็บ 30 วัน)
0 2 * * * root cd $APPDIR && /bin/bash deploy/backup.sh >> $APPDIR/storage/logs/backup.log 2>&1
CRONF
chmod 644 "$CRON"
echo "      ✓ ตั้ง cron แล้ว ($CRON)"

echo
echo "=================================================="
echo " ✓ เครื่องพร้อมใช้งาน"
echo
echo " ขั้นต่อไป:"
echo "   1) อัปโหลดโค้ดไปที่ $APPDIR"
echo "   2) ตั้งรหัสผ่าน MySQL:  sudo mysql_secure_installation"
echo "   3) ติดตั้งโรงเรียนแรก:  cd $APPDIR && sudo bash deploy/new_org.sh"
echo "      (หลายโรงเรียน: sudo bash deploy/central_setup.sh)"
echo "   4) เปิดเว็บ: https://$DOMAIN"
echo "=================================================="
