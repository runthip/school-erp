# คู่มือติดตั้งขึ้นเซิร์ฟเวอร์จริง — School ERP

ระบบนี้ออกแบบแบบ **1 การติดตั้ง = 1 โรงเรียน** (แยกฐานข้อมูลต่อองค์กร)
ใช้โค้ดชุดเดียวกันได้ทุกโรงเรียน เพียงแยกฐานข้อมูลและไฟล์ `.env`

---

## 1. ความต้องการของเซิร์ฟเวอร์

| รายการ | ขั้นต่ำ | แนะนำ |
|---|---|---|
| PHP | 8.0 | **8.2+** (ต้องมี `pdo_mysql`, `mbstring`, `fileinfo`, `gd`) |
| ฐานข้อมูล | MySQL 5.7 / MariaDB 10.3 | **MariaDB 10.4+** |
| เว็บเซิร์ฟเวอร์ | Apache + `mod_rewrite` | Apache 2.4 / Nginx |
| พื้นที่ | 500 MB | 2 GB ขึ้นไป (ตามไฟล์แนบ) |
| อื่น ๆ | — | ใบรับรอง HTTPS (Let's Encrypt) |

> ระบบทำงาน **ออฟไลน์ได้เต็มรูปแบบ** — ไลบรารีหน้าเว็บ (Tailwind, Alpine, Chart.js, QR) และฟอนต์ทั้งหมดอยู่ใน `public/assets/` ไม่เรียกอินเทอร์เน็ต

---

## 2. อัปโหลดไฟล์

อัปโหลดทั้งโปรเจกต์ขึ้นเซิร์ฟเวอร์ (เช่น `/var/www/school-erp`) **ยกเว้น**
`backups/`, `.env` เก่า และ `storage/*` ของเครื่องเดิม

```bash
# ตัวอย่างด้วย rsync (แนะนำ)
rsync -av --exclude '.env' --exclude 'backups/' --exclude 'storage/*/*' \
      ./ user@server:/var/www/school-erp/
```

> **สำคัญ:** ต้องอัปโหลดโฟลเดอร์ `public/assets/` ไปด้วย (มีไลบรารี + ฟอนต์สำหรับใช้งานออฟไลน์)

---

## 3. ตั้งค่าเว็บเซิร์ฟเวอร์ — ชี้ DocumentRoot ไปที่ `public/`

**Apache** (`/etc/apache2/sites-available/school-erp.conf`)

```apache
<VirtualHost *:80>
    ServerName erp.yourschool.ac.th
    DocumentRoot /var/www/school-erp/public

    <Directory /var/www/school-erp/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/school-erp-error.log
    CustomLog ${APACHE_LOG_DIR}/school-erp-access.log combined
</VirtualHost>
```

```bash
sudo a2enmod rewrite && sudo a2ensite school-erp && sudo systemctl reload apache2
```

**Nginx**

```nginx
server {
    listen 80;
    server_name erp.yourschool.ac.th;
    root /var/www/school-erp/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
    client_max_body_size 25M;   # รองรับไฟล์แนบสูงสุด 20 MB
}
```

> ถ้าจำเป็นต้องวางในโฟลเดอร์ย่อยและชี้เว็บรูทมาที่โฟลเดอร์โปรเจกต์
> ไฟล์ `.htaccess` ที่รากจะเปลี่ยนเส้นทางเข้า `public/` และบล็อกไฟล์อ่อนไหวให้อัตโนมัติ

---

## 4. ติดตั้งฐานข้อมูลของโรงเรียน

### วิธีที่ 1 — ใช้สคริปต์อัตโนมัติ (แนะนำ)

```bash
cd /var/www/school-erp
bash deploy/new_org.sh
```

สคริปต์จะ: สร้างฐานข้อมูล + ผู้ใช้ DB → นำเข้าโครงสร้าง + ค่าตั้งต้น →
ตั้งข้อมูลโรงเรียน → สร้างบัญชีแอดมิน → สร้างปีการศึกษาปัจจุบัน →
สร้างไฟล์ `.env` → ตั้งสิทธิ์โฟลเดอร์

รันแบบไม่ต้องตอบคำถาม (สำหรับติดตั้งหลายโรงเรียน):

```bash
DB_NAME=erp_wat DB_USER=erp_wat DB_PASS='รหัสที่ปลอดภัย' \
MYSQL_ADMIN=root MYSQL_ADMIN_PASS='รหัส root' \
SCHOOL_NAME='โรงเรียนวัดใหม่' SCHOOL_CODE='1030200123' \
SCHOOL_AFFIL='สพป.' SCHOOL_PROVINCE='นครราชสีมา' \
ADMIN_USER=admin ADMIN_NAME='ผู้ดูแลระบบ' ADMIN_EMAIL='admin@wat.ac.th' \
ADMIN_PASS='Str0ng#Pass' bash deploy/new_org.sh
```

### วิธีที่ 2 — ติดตั้งเอง

```bash
mysql -u root -p -e "CREATE DATABASE erp_wat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p erp_wat < deploy/school_erp_clean.sql
cp .env.example .env && nano .env      # แก้ DB_NAME / DB_USER / DB_PASS
# แล้วเพิ่มข้อมูลโรงเรียนและบัญชีแอดมินเองผ่าน SQL
```

---

## 5. ตั้งสิทธิ์ไฟล์และโฟลเดอร์

```bash
cd /var/www/school-erp

# เจ้าของไฟล์ = ผู้ใช้ที่รันเว็บเซิร์ฟเวอร์ (Ubuntu/Debian = www-data)
sudo chown -R www-data:www-data .

# โฟลเดอร์ที่ต้องเขียนได้
sudo chmod -R 775 storage public/uploads backups

# ไฟล์ตั้งค่า — เว็บเซิร์ฟเวอร์ต้อง "อ่านได้" ไม่งั้นระบบจะถอยไปใช้ค่า default
sudo chown www-data:www-data .env && sudo chmod 640 .env
```

> ⚠ ถ้า `.env` อ่านไม่ได้ ระบบจะเชื่อมฐานข้อมูลผิดตัวโดยไม่แจ้งเตือน
> ตรวจด้วย: `sudo -u www-data php -r '$c=require "config/config.php"; echo $c["db"]["name"];'`

---

## 6. เปิด HTTPS (จำเป็นถ้าจะใช้กล้องตรวจข้อสอบ)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d erp.yourschool.ac.th
```

> การตรวจข้อสอบด้วยกล้องใช้ `getUserMedia` ซึ่งเบราว์เซอร์อนุญาตเฉพาะ **HTTPS** หรือ `localhost`
> ถ้ายังไม่มี HTTPS ให้ใช้ปุ่ม “อัปโหลดรูป” แทนได้

---

## 7. ตรวจความปลอดภัยก่อนเปิดใช้จริง

- [ ] `.env` ตั้ง `APP_ENV=production`, `APP_DEBUG=false`, `DEMO_LOGIN=false`
- [ ] เปลี่ยนรหัสผ่านแอดมินเป็นรหัสที่คาดเดายาก
- [ ] ผู้ใช้ DB **ไม่ใช่ root** และมีสิทธิ์เฉพาะฐานข้อมูลของตนเอง
- [ ] เปิด HTTPS แล้ว
- [ ] เปิด `https://โดเมน/.env` แล้วต้องได้ 403/404 (ไม่ใช่เนื้อไฟล์)
- [ ] หน้าเข้าสู่ระบบต้อง **ไม่มี** ปุ่มเข้าสู่ระบบด่วน/รหัสสาธิต
- [ ] ตั้งค่าอีเมลใน `.env` เพื่อให้ผู้ใช้กู้รหัสผ่านเองได้

---

## 8. ตั้งค่าเริ่มใช้งาน (ในระบบ)

1. เข้าสู่ระบบด้วยบัญชีแอดมิน
2. **ตั้งค่าระบบ** → ข้อมูลโรงเรียน · โลโก้/ตราครุฑ · ช่วงชั้นที่เปิดสอน · ปีการศึกษา
3. **ฝ่ายบุคคล → ข้อมูลบุคลากร** → เพิ่มครู/เจ้าหน้าที่
4. **ความปลอดภัย → ผู้ใช้งาน** → สร้างบัญชีให้บุคลากร + กำหนดบทบาท
5. **วิชาการ · การจัดการ** → ชั้นเรียน → รายวิชา → มอบหมายวิชาสอน → จัดตารางสอน

---

## 9. สำรองข้อมูล (ตั้งอัตโนมัติ)

```bash
# ทดสอบก่อน
bash /var/www/school-erp/deploy/backup.sh

# ตั้ง cron ทุกวันตี 2
sudo crontab -e
0 2 * * * bash /var/www/school-erp/deploy/backup.sh >> /var/log/school-erp-backup.log 2>&1
```

สำรองทั้งฐานข้อมูลและไฟล์แนบ เก็บย้อนหลัง 30 วัน

---

## 10. ติดตั้งเพิ่มโรงเรียนที่ 2, 3, …

เลือกได้ 2 แบบ

### แบบ ก — โค้ดชุดเดียว หลายโรงเรียน (ศูนย์ควบคุมส่วนกลาง) ★ แนะนำเมื่อดูแลหลายโรงเรียน

ติดตั้งครั้งเดียว ใช้ได้ทุกโรงเรียน · **แยกฐานข้อมูลต่อโรงเรียน** · ผู้ใช้เข้าระบบด้วย **รหัสโรงเรียน**
· Super admin เข้าไปดูแลระบบของแต่ละโรงเรียนได้ผ่านบัญชีแอดมินของโรงเรียนนั้น และถูกบันทึกประวัติทุกครั้ง

```bash
cd /var/www/school-erp
bash deploy/central_setup.sh
```

สคริปต์จะสร้างฐานข้อมูลศูนย์กลาง (`tenants` / `platform_admins` / `tenant_access_logs`),
บัญชี Super admin คนแรก และให้สิทธิ์บัญชี MySQL ของแอปกับฐานข้อมูลโรงเรียนทุกตัว (`erp_%`)
จากนั้นเติมค่าใน `.env`:

```ini
MULTI_TENANT=true
CENTRAL_DB_NAME=school_erp_central
TENANT_DB_PREFIX=erp_
DB_USER=erp_app            # บัญชีที่ระบบใช้ ใช้ร่วมกันทุกโรงเรียน (มีสิทธิ์เฉพาะ erp_%)
DB_PASS=********
PROVISION_DB_USER=root     # ใช้เฉพาะตอนกด "เปิดใช้งานโรงเรียนใหม่" (CREATE DATABASE)
PROVISION_DB_PASS=********
```

**การใช้งาน**

| หน้า | ใคร | ทำอะไร |
|---|---|---|
| `/platform/login` | Super admin (ผู้ให้บริการ) | เข้าศูนย์ควบคุม |
| `/platform/tenants` | Super admin | ทะเบียนโรงเรียน · เปิดใช้งานโรงเรียนใหม่ (สร้าง DB + นำเข้าโครงสร้าง + สร้างแอดมินให้อัตโนมัติ) · ระงับ/เปิดใช้งาน · รีเซตรหัสแอดมิน · **เข้าดูแลระบบ** |
| `/platform/logs` | Super admin | ประวัติการเข้าดูแลทั้งหมด (ใคร เข้าโรงเรียนไหน เมื่อไร จาก IP ใด) |
| `/login` | ผู้ใช้ของโรงเรียน | กรอก **รหัสโรงเรียน** + ชื่อผู้ใช้ + รหัสผ่าน |

**การกันข้อมูลทับกัน**

- `school_code` และ `db_name` เป็น UNIQUE ที่ศูนย์กลาง — รหัสโรงเรียนซ้ำถูกปฏิเสธทันที
- ก่อนสร้าง ระบบตรวจว่าไม่มีฐานข้อมูลชื่อนั้นอยู่จริงบนเซิร์ฟเวอร์ ถ้าติดตั้งไม่สำเร็จจะลบฐานข้อมูลที่เพิ่งสร้างทิ้งอัตโนมัติ
- ทุก request จะสลับไปฐานข้อมูลของโรงเรียนตาม session เท่านั้น ถ้ายังไม่ได้เลือกโรงเรียน **ระบบจะไม่ต่อฐานข้อมูลของใครเลย**
- โรงเรียนที่ถูกระงับ เข้าระบบไม่ได้ทันที ทั้งการล็อกอินใหม่และ session ที่ค้างอยู่ (ข้อมูลยังอยู่ครบ ไม่ถูกลบ)
- Super admin ต้องยืนยันรหัสผ่านของตัวเองทุกครั้งก่อน "เข้าดูแลระบบ" และหน้าจอจะขึ้นแถบสีเหลืองเตือนตลอดเวลาที่อยู่ในโหมดนี้
- **ไม่มีใครดูรหัสผ่านของผู้อื่นได้** — Super admin ตั้งรหัสใหม่ให้แอดมินโรงเรียนได้เท่านั้น และผู้ใช้ถูกบังคับเปลี่ยนรหัสทันทีที่เข้าระบบ

> แอดมินของแต่ละโรงเรียนยังจัดการระบบของตัวเองได้ทั้งหมด (ผู้ใช้ · สิทธิ์ · ข้อมูล · การตั้งค่า) เหมือนติดตั้งแยกกัน

### แบบ ข — แยกโค้ด/โดเมนต่อโรงเรียน

```bash
cp -r /var/www/school-erp /var/www/erp-school2
cd /var/www/erp-school2 && rm -f .env
bash deploy/new_org.sh          # ใส่ DB/ชื่อโรงเรียนใหม่
```

แล้วสร้าง VirtualHost ใหม่ชี้ไป `/var/www/erp-school2/public`

> ทั้งสองแบบแยกฐานข้อมูลกันสมบูรณ์ — ไม่มีทางเห็นข้อมูลข้ามโรงเรียน

---

## 11. อัปเดตระบบในอนาคต

อัปโหลดโค้ดใหม่ทับ (ยกเว้น `.env`, `storage/`, `public/uploads/`, `backups/`) แล้วสั่ง

```bash
cd /var/www/school-erp
bash deploy/update.sh --dry-run     # ดูก่อนว่าจะเกิดอะไร
bash deploy/update.sh               # อัปเดตจริง
```

สคริปต์จะ **ปิดปรับปรุงระบบ → สำรองข้อมูลทุกโรงเรียน → นำเข้าไฟล์ SQL ที่ค้างให้ทุกโรงเรียน → ตรวจความเรียบร้อย → เปิดใช้งาน**
ถ้าขั้นใดล้มเหลว ระบบจะคงโหมดปิดปรับปรุงไว้ ไม่ปล่อยให้ผู้ใช้เจอระบบครึ่ง ๆ กลาง ๆ

📖 รายละเอียดทั้งหมด — กติกาการแก้โค้ด/ฐานข้อมูล · การทดสอบบน staging · การย้อนกลับ · ใบตรวจก่อน-หลัง
อยู่ใน **[UPGRADE.md](UPGRADE.md)**

ไฟล์ใน `database/` เป็น migration ที่ **รันซ้ำได้ปลอดภัย** (ใช้ `IF NOT EXISTS`)

---

## 12. แก้ปัญหาที่พบบ่อย

| อาการ | สาเหตุ/วิธีแก้ |
|---|---|
| เข้าเว็บแล้วขึ้น 500 | ดู `storage/logs/` และ error log ของ Apache · ตรวจสิทธิ์โฟลเดอร์ |
| ล็อกอินไม่ผ่านทั้งที่รหัสถูก | เว็บเซิร์ฟเวอร์อ่าน `.env` ไม่ได้ → ต่อ DB ผิดตัว (ดูข้อ 5) |
| “CSRF ไม่ถูกต้อง” | `storage/sessions` เขียนไม่ได้ → `chmod 775` และตั้งเจ้าของให้ถูก |
| อัปโหลดไฟล์ไม่สำเร็จ | โฟลเดอร์ปลายทางใน `storage/` เขียนไม่ได้ · ตรวจ `upload_max_filesize`/`post_max_size` |
| กล้องตรวจข้อสอบไม่เปิด | ต้องใช้ HTTPS (ดูข้อ 6) |
| หน้าเว็บไม่มีสไตล์ | อัปโหลด `public/assets/` ไม่ครบ |

---

## เอกสาร/สคริปต์ที่เกี่ยวข้อง

| ไฟล์ | ใช้ทำอะไร |
|---|---|
| `deploy/school_erp_clean.sql` | ชุดติดตั้งใหม่ (โครงสร้าง + ค่าตั้งต้น ไม่มีข้อมูลองค์กร) |
| `deploy/new_org.sh` | ติดตั้งโรงเรียนใหม่แบบอัตโนมัติ (แบบแยกโค้ดต่อโรงเรียน) |
| `deploy/central_setup.sh` | ติดตั้งศูนย์ควบคุมส่วนกลาง (โค้ดชุดเดียว หลายโรงเรียน — ข้อ 10 แบบ ก) |
| `database/central/01_central.sql` | ตารางของศูนย์ควบคุมกลาง (`tenants` / `platform_admins` / `tenant_access_logs`) |
| `deploy/backup.sh` | สำรองฐานข้อมูล + ไฟล์แนบ |
| `database/tools/reset_operational.sql` | ล้างข้อมูลใช้งาน เก็บค่าตั้งต้น (เริ่มปีการศึกษาใหม่/ส่งมอบระบบ) |
| `.env.example` | ต้นแบบไฟล์ตั้งค่า |
| `MANUAL.md` | **คู่มือการใช้งานระบบ** (สำหรับผู้ใช้ทุกระดับ — ครู ธุรการ ผู้บริหาร แอดมิน) |
| `UPGRADE.md` | **คู่มืออัปเดต/แก้ไขระบบ** ให้ไม่กระทบการทำงาน และครบทุกโรงเรียน |
| `deploy/update.sh` | อัปเดตครบวงจร: ปิดปรับปรุง → สำรอง → นำเข้า SQL ทุกโรงเรียน → ตรวจ → เปิดใช้ |
| `deploy/migrate.php` | นำเข้าไฟล์ SQL ที่ค้าง ให้ทุกโรงเรียน (`--status` `--check` `--school=`) |
| `deploy/maintenance.sh` | เปิด/ปิดโหมดปิดปรับปรุงระบบ |
