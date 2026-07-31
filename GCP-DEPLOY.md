# นำระบบขึ้นใช้งานจริงบน Google Cloud

> คู่มือนี้พาไปทีละขั้นตั้งแต่ยังไม่มีอะไรเลย จนเปิดเว็บใช้งานได้จริงพร้อม HTTPS
> ติดตั้งบนเซิร์ฟเวอร์ทั่วไปดูที่ [DEPLOY.md](DEPLOY.md) · การอัปเดตดูที่ [UPGRADE.md](UPGRADE.md)

---

## สารบัญ

1. [เลือกบริการให้ถูกตัว](#1-เลือกบริการให้ถูกตัว)
2. [ขนาดเครื่องและค่าใช้จ่าย](#2-ขนาดเครื่องและค่าใช้จ่าย)
3. [เตรียมตัวก่อนเริ่ม](#3-เตรียมตัวก่อนเริ่ม)
4. [สร้างเครื่อง (VM)](#4-สร้างเครื่อง-vm)
5. [ตั้งค่า IP ถาวรและโดเมน](#5-ตั้งค่า-ip-ถาวรและโดเมน)
6. [อัปโหลดโค้ดขึ้นเครื่อง](#6-อัปโหลดโค้ดขึ้นเครื่อง)
7. [ติดตั้งระบบบนเครื่อง](#7-ติดตั้งระบบบนเครื่อง)
8. [ย้ายข้อมูลจากเครื่องเดิม](#8-ย้ายข้อมูลจากเครื่องเดิม)
9. [ความปลอดภัยบนคลาวด์](#9-ความปลอดภัยบนคลาวด์)
10. [สำรองข้อมูล 2 ชั้น](#10-สำรองข้อมูล-2-ชั้น)
11. [อัปเดตระบบบนคลาวด์](#11-อัปเดตระบบบนคลาวด์)
12. [หลายโรงเรียนบน Google Cloud](#12-หลายโรงเรียนบน-google-cloud)
13. [ประหยัดค่าใช้จ่าย](#13-ประหยัดค่าใช้จ่าย)
14. [แก้ปัญหาที่พบบ่อย](#14-แก้ปัญหาที่พบบ่อย)
15. [ใบตรวจก่อนเปิดใช้จริง](#15-ใบตรวจก่อนเปิดใช้จริง)

---

## 1. เลือกบริการให้ถูกตัว

Google Cloud มีหลายบริการ แต่ระบบนี้เหมาะกับแบบเดียว

| บริการ | เหมาะกับระบบนี้ไหม | เหตุผล |
|---|---|---|
| **Compute Engine (VM)** | ✅ **แนะนำ** | เหมือนเซิร์ฟเวอร์ทั่วไปทุกอย่าง — ไฟล์แนบเก็บในดิสก์ได้ · เซสชันเป็นไฟล์ได้ · สร้างฐานข้อมูลใหม่ให้โรงเรียนใหม่ได้ · ใช้สคริปต์ที่มีอยู่ได้ทันที |
| Compute Engine + **Cloud SQL** | ✅ ดีเมื่อดูแลหลายโรงเรียน | ฐานข้อมูลมีสำรอง/กู้คืนอัตโนมัติในตัว แต่ค่าใช้จ่ายสูงกว่าราว 2 เท่า |
| Cloud Run / App Engine | ❌ ไม่เหมาะ (ยังไม่ได้) | ระบบเก็บ **ไฟล์แนบและเซสชันไว้ในเครื่อง** ซึ่งคอนเทนเนอร์จะลบทิ้งทุกครั้งที่รีสตาร์ต ต้องแก้โค้ดให้ใช้ Cloud Storage + เซสชันในฐานข้อมูลก่อน |
| GKE / Kubernetes | ❌ เกินความจำเป็น | ระบบโรงเรียนเดียวไม่ต้องใช้ และค่าใช้จ่าย/ความซับซ้อนสูงมาก |

> **สรุป: ใช้ Compute Engine (VM) 1 เครื่อง** — เร็ว ถูก ดูแลง่าย และใช้สคริปต์ทั้งหมดที่ระบบมีอยู่แล้วได้เลย

---

## 2. ขนาดเครื่องและค่าใช้จ่าย

**เลือกโซน `asia-southeast1` (สิงคโปร์)** — ใกล้ไทยที่สุด ความเร็วดีที่สุด

| ขนาดโรงเรียน | เครื่องที่แนะนำ | สเปก | ค่าใช้จ่ายโดยประมาณ |
|---|---|---|---|
| เล็ก (< 300 คน, ใช้พร้อมกัน < 20) | `e2-small` | 2 vCPU (แชร์) · RAM 2 GB | ~US$16/เดือน |
| กลาง (300–1,200 คน) | `e2-medium` | 2 vCPU (แชร์) · RAM 4 GB | ~US$32/เดือน |
| ใหญ่ / หลายโรงเรียนรวมศูนย์ | `e2-standard-2` | 2 vCPU · RAM 8 GB | ~US$65/เดือน |

**ค่าใช้จ่ายเพิ่มเติม (ทุกขนาด)**

| รายการ | ประมาณ |
|---|---|
| ดิสก์ `pd-balanced` 30 GB | ~US$3.4/เดือน |
| IP ถาวร (ใช้งานอยู่) | ~US$3.6/เดือน |
| Cloud Storage เก็บไฟล์สำรอง 20 GB | ~US$0.5/เดือน |
| **รวมสำหรับโรงเรียนขนาดเล็ก** | **~US$23–25/เดือน (ราว 800–900 บาท)** |

> ⚠ ราคาข้างต้นเป็นค่าประมาณ ณ เวลาที่เขียน — ตรวจราคาจริงที่ [cloud.google.com/products/calculator](https://cloud.google.com/products/calculator)
> ผู้ใช้ใหม่มักได้เครดิตทดลองใช้ ~US$300 ใช้ได้ราว 1 ปีสำหรับเครื่องขนาดเล็ก

---

## 3. เตรียมตัวก่อนเริ่ม

**สิ่งที่ต้องมี**

- [ ] บัญชี Google + เปิด **Billing** (ผูกบัตร) ในโปรเจกต์
- [ ] **ชื่อโดเมน** เช่น `erp.myschool.ac.th` — จำเป็นสำหรับ HTTPS และ HTTPS จำเป็นสำหรับ **กล้องตรวจข้อสอบ**
- [ ] โค้ดระบบชุดล่าสุด + ไฟล์สำรองข้อมูลจากเครื่องเดิม (ถ้าย้ายมา)

**เลือกวิธีสั่งงาน** — ทำได้ 2 ทาง เลือกอย่างใดอย่างหนึ่ง

| ทาง | เหมาะกับ | เริ่มยังไง |
|---|---|---|
| **Cloud Shell** (แนะนำสำหรับมือใหม่) | ไม่ต้องติดตั้งอะไรเลย | เปิด [console.cloud.google.com](https://console.cloud.google.com) → กดไอคอน `>_` มุมขวาบน |
| **gcloud CLI บนเครื่องตัวเอง** | อัปโหลดไฟล์จากเครื่องสะดวกกว่า | ติดตั้งจาก [cloud.google.com/sdk](https://cloud.google.com/sdk) แล้ว `gcloud init` |

```bash
# ตั้งโปรเจกต์และโซนให้เป็นค่าเริ่มต้น (ทำครั้งเดียว)
gcloud config set project <PROJECT_ID>
gcloud config set compute/zone asia-southeast1-b

# เปิดบริการที่ต้องใช้
gcloud services enable compute.googleapis.com storage.googleapis.com
```

---

## 4. สร้างเครื่อง (VM)

```bash
gcloud compute instances create school-erp \
  --zone=asia-southeast1-b \
  --machine-type=e2-small \
  --image-family=debian-12 --image-project=debian-cloud \
  --boot-disk-size=30GB --boot-disk-type=pd-balanced \
  --tags=http-server,https-server \
  --scopes=https://www.googleapis.com/auth/cloud-platform \
  --metadata=enable-oslogin=TRUE
```

**เปิดพอร์ตเว็บ** (ถ้าเครือข่าย default ยังไม่มีกฎนี้)

```bash
gcloud compute firewall-rules create allow-http  --allow=tcp:80  --target-tags=http-server  --network=default
gcloud compute firewall-rules create allow-https --allow=tcp:443 --target-tags=https-server --network=default
```

> 🔒 **ห้ามเปิดพอร์ต 3306 (MySQL) ออกสู่อินเทอร์เน็ตเด็ดขาด** — ฐานข้อมูลอยู่เครื่องเดียวกับเว็บ ไม่ต้องเปิดพอร์ตใด ๆ เพิ่ม

**เข้าเครื่อง**

```bash
gcloud compute ssh school-erp --zone=asia-southeast1-b
```

---

## 5. ตั้งค่า IP ถาวรและโดเมน

IP ที่ได้ตอนสร้างเครื่องเป็นแบบชั่วคราว — รีสตาร์ตแล้วเปลี่ยน ต้องจองเป็นแบบถาวร

```bash
# 1) จอง IP ถาวรในภูมิภาคเดียวกับเครื่อง
gcloud compute addresses create school-erp-ip --region=asia-southeast1
gcloud compute addresses describe school-erp-ip --region=asia-southeast1 --format='value(address)'

# 2) ผูก IP ถาวรเข้ากับเครื่อง
gcloud compute instances delete-access-config school-erp \
    --zone=asia-southeast1-b --access-config-name="external-nat"
gcloud compute instances add-access-config school-erp \
    --zone=asia-southeast1-b --access-config-name="external-nat" \
    --address=<IP ที่จองได้>
```

**ตั้ง DNS** ที่ผู้ให้บริการโดเมนของท่าน (หรือ Cloud DNS)

| ชนิด | ชื่อ | ค่า |
|---|---|---|
| A | `erp` (หรือ `erp.myschool.ac.th`) | `<IP ถาวรที่จองได้>` |

ตรวจว่า DNS ทำงานแล้ว (อาจใช้เวลา 5 นาที – 1 ชั่วโมง)

```bash
dig +short erp.myschool.ac.th      # ต้องได้ IP ของเครื่อง
```

> ✅ **ต้องรอให้ DNS ชี้มาที่เครื่องก่อน** จึงจะขอใบรับรอง HTTPS ได้

---

## 6. อัปโหลดโค้ดขึ้นเครื่อง

**วิธีที่ 1 — บีบอัดแล้วส่งขึ้นไป (ง่ายที่สุด)**

```bash
# บนเครื่องตัวเอง (ในโฟลเดอร์เหนือโปรเจกต์)
tar -czf school-erp.tar.gz \
    --exclude='school-erp/backups' \
    --exclude='school-erp/.env' \
    --exclude='school-erp/storage/sessions' \
    --exclude='school-erp/storage/logs' \
    school-erp

gcloud compute scp school-erp.tar.gz school-erp:~/ --zone=asia-southeast1-b
```

```bash
# บนเครื่อง VM
sudo mkdir -p /var/www && cd /var/www
sudo tar -xzf ~/school-erp.tar.gz
sudo chown -R $USER:$USER /var/www/school-erp
ls /var/www/school-erp/public/assets     # ต้องมีไฟล์ (ไลบรารีสำหรับใช้งานออฟไลน์)
```

**วิธีที่ 2 — ใช้ git** (ถ้าเก็บโค้ดไว้บน GitHub/Cloud Source Repositories)

```bash
sudo apt install -y git
sudo git clone <URL ของ repo> /var/www/school-erp
```

> ⚠ ทั้งสองวิธี **อย่านำ `.env` ของเครื่องเดิมขึ้นไป** — ค่าฐานข้อมูลคนละชุดกัน จะสร้างใหม่ในขั้นถัดไป

---

## 7. ติดตั้งระบบบนเครื่อง

```bash
cd /var/www/school-erp

# 1) เตรียมเครื่องทั้งหมดในคำสั่งเดียว (Apache + PHP 8.2 + MariaDB + HTTPS + cron สำรอง)
sudo bash deploy/gcp/setup_vm.sh erp.myschool.ac.th admin@myschool.ac.th

# 2) ตั้งรหัสผ่าน root ของฐานข้อมูล + ปิดช่องโหว่เริ่มต้น
sudo mysql_secure_installation
#    ตอบ: ตั้งรหัส root = ใช่ · ลบผู้ใช้นิรนาม = ใช่ · ห้าม root ล็อกอินระยะไกล = ใช่
#         ลบฐานข้อมูล test = ใช่ · โหลดสิทธิ์ใหม่ = ใช่

# 3) ติดตั้งโรงเรียน
sudo bash deploy/new_org.sh              # โรงเรียนเดียว
# หรือ
sudo bash deploy/central_setup.sh        # ให้บริการหลายโรงเรียน (ดูข้อ 12)

# 4) ตรวจว่าเว็บเซิร์ฟเวอร์อ่าน .env ได้ (กับดักที่เจอบ่อยที่สุด)
sudo chown www-data:www-data .env && sudo chmod 640 .env
sudo -u www-data php -r '$c=require "config/config.php"; echo $c["db"]["name"],"\n";'
```

**เปิดเว็บทดสอบ** → `https://erp.myschool.ac.th`

- [ ] หน้าเข้าสู่ระบบขึ้นครบ **มีสไตล์และฟอนต์ไทย** (ถ้าหน้าโล่ง = อัปโหลด `public/assets/` ไม่ครบ)
- [ ] เข้าสู่ระบบด้วยบัญชีแอดมินที่สร้างไว้
- [ ] มีรูปกุญแจ 🔒 ที่ช่อง URL (HTTPS ทำงาน)
- [ ] ลองอัปโหลดไฟล์แนบ 1 ไฟล์
- [ ] ลองพิมพ์เอกสาร 1 ใบ

---

## 8. ย้ายข้อมูลจากเครื่องเดิม

ถ้าเคยใช้ระบบบน XAMPP/เครื่องในโรงเรียนอยู่แล้ว และต้องการยกข้อมูลทั้งหมดขึ้นคลาวด์

```bash
# ---------- บนเครื่องเดิม ----------
cd /Applications/XAMPP/xamppfiles/htdocs/school-erp     # หรือที่ตั้งของท่าน
bash deploy/backup.sh          # ได้ไฟล์ใน backups/ : db_*.sql.gz และ files_*.tar.gz

gcloud compute scp backups/db_school_erp_*.sql.gz backups/files_*.tar.gz \
       school-erp:~/ --zone=asia-southeast1-b
```

```bash
# ---------- บนเครื่อง VM ----------
cd /var/www/school-erp

# 1) ปิดระบบชั่วคราว กันไม่ให้มีข้อมูลใหม่เข้ามาระหว่างย้าย
sudo bash deploy/maintenance.sh on

# 2) นำฐานข้อมูลเข้า (ชื่อฐานข้อมูลต้องตรงกับ DB_NAME ใน .env ของเครื่องใหม่)
gunzip < ~/db_school_erp_*.sql.gz | sudo mysql school_erp

# 3) นำไฟล์แนบ/โลโก้/ตราครุฑ เข้า
sudo tar -xzf ~/files_*.tar.gz -C /var/www/school-erp
sudo chown -R www-data:www-data storage public/uploads
sudo chmod -R 775 storage public/uploads

# 4) ตรวจโครงสร้างฐานข้อมูลให้เป็นรุ่นล่าสุด
sudo php deploy/migrate.php --status
sudo php deploy/migrate.php
sudo php deploy/migrate.php --check

# 5) เปิดใช้งาน
sudo bash deploy/maintenance.sh off
```

**ตรวจหลังย้าย**: จำนวนนักเรียน/บุคลากรตรงกับของเดิม · โลโก้และตราครุฑแสดงผล · เปิดไฟล์แนบเก่าได้ · พิมพ์ ปพ. ได้

> 📌 **ยังอย่าเพิ่งลบเครื่องเดิม** — เก็บไว้อย่างน้อย 2–4 สัปดาห์จนมั่นใจว่าใช้งานบนคลาวด์ได้ราบรื่น

---

## 9. ความปลอดภัยบนคลาวด์

| เรื่อง | ต้องทำ |
|---|---|
| **เข้าเครื่อง (SSH)** | ใช้ `gcloud compute ssh` + เปิด **OS Login** (ตั้งไว้แล้วในคำสั่งสร้างเครื่อง) · อย่าตั้งรหัสผ่าน SSH |
| **ไฟร์วอลล์** | เปิดเฉพาะ 80/443 · ห้ามเปิด 3306 · ถ้าต้องการจำกัดผู้เข้าใช้เฉพาะในโรงเรียน ให้ใส่ `--source-ranges=<IP โรงเรียน>` |
| **ฐานข้อมูล** | ผ่าน `mysql_secure_installation` แล้ว · ผู้ใช้ของแอปมีสิทธิ์เฉพาะฐานข้อมูลของตัวเอง ไม่ใช่ root |
| **ไฟล์ `.env`** | `chown www-data:www-data` + `chmod 640` — ห้าม 600 (เว็บอ่านไม่ได้) และห้าม 644 บนเครื่องที่มีผู้ใช้หลายคน |
| **อัปเดตระบบปฏิบัติการ** | `sudo apt update && sudo apt upgrade -y` เดือนละครั้ง (หรือเปิด unattended-upgrades) |
| **HTTPS** | certbot ต่ออายุอัตโนมัติ · ตรวจด้วย `sudo certbot renew --dry-run` |
| **บัญชีในระบบ** | ปิด `DEMO_LOGIN=false` · `APP_ENV=production` · `APP_DEBUG=false` (ค่าเริ่มต้นถูกต้องแล้ว) |
| **สิทธิ์ใน Google Cloud** | ให้สิทธิ์คนอื่นเท่าที่จำเป็น (Viewer สำหรับคนดูอย่างเดียว) · เปิด 2-Step Verification ของบัญชี Google |

**เสริมความปลอดภัย SSH (ไม่บังคับ แต่แนะนำ)**

```bash
sudo apt install -y fail2ban        # บล็อก IP ที่พยายามเดารหัสซ้ำ ๆ
sudo systemctl enable --now fail2ban
```

---

## 10. สำรองข้อมูล 2 ชั้น

> ไฟล์สำรองที่อยู่บนเครื่องเดียวกับระบบ **ไม่นับว่าปลอดภัย** — เครื่องเสียก็หายไปด้วยกัน

### ชั้นที่ 1 — สแนปช็อตทั้งดิสก์ (กู้ทั้งเครื่องได้ในไม่กี่นาที)

```bash
# ตั้งตารางสแนปช็อตอัตโนมัติ เก็บ 14 วัน (19:00 UTC = ตี 2 เวลาไทย)
gcloud compute resource-policies create snapshot-schedule daily-erp \
  --region=asia-southeast1 --max-retention-days=14 \
  --start-time=19:00 --daily-schedule

gcloud compute disks add-resource-policies school-erp \
  --zone=asia-southeast1-b --resource-policies=daily-erp
```

### ชั้นที่ 2 — ไฟล์สำรองฐานข้อมูลขึ้น Cloud Storage (กู้เฉพาะข้อมูลได้)

```bash
# สร้างที่เก็บ (ทำครั้งเดียว)
gcloud storage buckets create gs://erp-backup-myschool \
  --location=asia-southeast1 --uniform-bucket-level-access

# ทดลองสำรอง + ส่งขึ้นคลาวด์
cd /var/www/school-erp
sudo BUCKET=gs://erp-backup-myschool bash deploy/gcp/backup_to_gcs.sh

# ตั้งอัตโนมัติทุกคืน (แทนบรรทัดเดิมใน /etc/cron.d/school-erp-backup)
sudo tee /etc/cron.d/school-erp-backup >/dev/null <<'CRON'
0 2 * * * root cd /var/www/school-erp && BUCKET=gs://erp-backup-myschool /bin/bash deploy/gcp/backup_to_gcs.sh >> /var/www/school-erp/storage/logs/backup.log 2>&1
CRON
```

**ซ้อมกู้คืนอย่างน้อยปีละครั้ง** — ไฟล์สำรองที่กู้ไม่ได้ = ไม่มีไฟล์สำรอง

```bash
gcloud storage cp gs://erp-backup-myschool/school-erp/2026/07/db_school_erp_*.sql.gz .
gunzip < db_school_erp_*.sql.gz | mysql -u root -p ชื่อฐานข้อมูลทดสอบ
```

---

## 11. อัปเดตระบบบนคลาวด์

ใช้ขั้นตอนเดียวกับ [UPGRADE.md](UPGRADE.md) ทุกประการ เพียงส่งโค้ดขึ้นไปก่อน

```bash
# บนเครื่องตัวเอง — ส่งเฉพาะไฟล์ที่เปลี่ยน
tar -czf update.tar.gz --exclude='backups' --exclude='.env' --exclude='storage' school-erp
gcloud compute scp update.tar.gz school-erp:~/ --zone=asia-southeast1-b
```

```bash
# บนเครื่อง VM
cd /var/www
sudo tar -xzf ~/update.tar.gz --exclude='school-erp/.env' \
     --exclude='school-erp/storage' --exclude='school-erp/backups' \
     --exclude='school-erp/public/uploads'
cd school-erp
sudo bash deploy/update.sh --dry-run
sudo bash deploy/update.sh --url=https://erp.myschool.ac.th
```

> 💡 อยากปลอดภัยขึ้นอีกขั้น: กด **สแนปช็อตดิสก์** ก่อนอัปเดต แล้วถ้าพังจริง ๆ สร้างเครื่องใหม่จากสแนปช็อตได้เลย
> `gcloud compute disks snapshot school-erp --zone=asia-southeast1-b --snapshot-names=before-update-$(date +%Y%m%d)`

---

## 12. หลายโรงเรียนบน Google Cloud

เมื่อดูแลหลายโรงเรียนด้วยเครื่องเดียว (ดูแนวคิดที่ [DEPLOY.md ข้อ 10](DEPLOY.md))

```bash
cd /var/www/school-erp
sudo bash deploy/central_setup.sh        # สร้างฐานข้อมูลศูนย์กลาง + บัญชี Super admin
sudo nano .env                           # MULTI_TENANT=true และค่าอื่นตามที่สคริปต์แจ้ง
```

จากนั้นเปิด `https://erp.myschool.ac.th/platform/login` → กด **“เปิดใช้งานโรงเรียนใหม่”** ทีละแห่ง

**ข้อควรรู้เมื่อรันหลายโรงเรียนบน VM เดียว**

| เรื่อง | คำแนะนำ |
|---|---|
| ขนาดเครื่อง | เริ่มที่ `e2-medium` (RAM 4 GB) · เกิน 5 โรงเรียนหรือคนใช้พร้อมกันเยอะ ให้ขยับเป็น `e2-standard-2` |
| ดิสก์ | เริ่ม 50 GB (ไฟล์แนบโตเร็วกว่าฐานข้อมูลมาก) · ขยายภายหลังได้โดยไม่ต้องสร้างเครื่องใหม่ |
| สำรองข้อมูล | `backup.sh` สำรอง **ศูนย์กลาง + ทุกโรงเรียน** ให้อัตโนมัติอยู่แล้ว |
| อัปเดต | `deploy/update.sh` ครั้งเดียว ครบทุกโรงเรียน |
| แยกโดเมนต่อโรงเรียน (ถ้าต้องการ) | ชี้ DNS ของทุกโดเมนมาที่ IP เดียวกัน แล้วเพิ่ม `ServerAlias` ใน VirtualHost + ขอใบรับรองเพิ่ม `sudo certbot --apache -d erp.school2.ac.th` |

**ขยายเครื่องเมื่อโตขึ้น** (ต้องปิดเครื่องสักครู่)

```bash
gcloud compute instances stop school-erp --zone=asia-southeast1-b
gcloud compute instances set-machine-type school-erp --zone=asia-southeast1-b --machine-type=e2-medium
gcloud compute instances start school-erp --zone=asia-southeast1-b
```

**ขยายดิสก์** (ไม่ต้องปิดเครื่อง)

```bash
gcloud compute disks resize school-erp --zone=asia-southeast1-b --size=50GB
# แล้วบน VM:
sudo growpart /dev/sda 1 && sudo resize2fs /dev/sda1
```

---

## 13. ประหยัดค่าใช้จ่าย

| วิธี | ประหยัดได้ | ข้อควรระวัง |
|---|---|---|
| **Committed use discount 1 ปี** | ~37% | ผูกพันจ่ายครบปี — คุ้มถ้าใช้แน่นอน |
| เลือกดิสก์ `pd-balanced` แทน `pd-ssd` | ~40% ของค่าดิสก์ | เร็วพอสำหรับระบบขนาดนี้ |
| ลบสแนปช็อต/ไฟล์สำรองเก่า | ตามที่เก็บ | ตั้ง lifecycle rule ที่ bucket ให้ลบอัตโนมัติ เช่น 180 วัน |
| ตั้ง **งบเตือน (Budget alert)** | — | `Billing → Budgets & alerts` ตั้งเตือนที่ 80% ของงบที่ตั้งไว้ |
| ปล่อย IP ถาวรที่ไม่ได้ใช้ | ~US$3.6/เดือน/IP | IP ที่จองไว้แต่ไม่ผูกกับเครื่อง **เสียเงินแพงกว่า** ตอนใช้งาน |

> ❌ **อย่าใช้เครื่องแบบ Spot/Preemptible** กับระบบนี้ — Google ปิดเครื่องได้ทุกเมื่อ ครูจะทำงานค้างกลางคัน
> ❌ **อย่าปิดเครื่องกลางคืนเพื่อประหยัด** ถ้ามีครูทำงานนอกเวลา หรือมีงานสำรองข้อมูลตอนตี 2

---

## 14. แก้ปัญหาที่พบบ่อย

| อาการ | สาเหตุ / วิธีแก้ |
|---|---|
| เปิดเว็บไม่ขึ้นเลย (timeout) | ยังไม่ได้สร้างกฎไฟร์วอลล์ หรือเครื่องไม่มี tag `http-server`/`https-server` → `gcloud compute instances add-tags school-erp --tags=http-server,https-server --zone=...` |
| certbot ขอใบรับรองไม่ผ่าน | DNS ยังไม่ชี้มาที่ IP ของเครื่อง → `dig +short โดเมน` ต้องได้ IP เดียวกับเครื่อง แล้วลองใหม่ |
| หน้าเว็บโล่ง ไม่มีสไตล์/ฟอนต์ | อัปโหลด `public/assets/` ไม่ครบ → ส่งขึ้นใหม่แล้ว `Ctrl+Shift+R` |
| ล็อกอินไม่ผ่านทั้งที่รหัสถูก | เว็บเซิร์ฟเวอร์อ่าน `.env` ไม่ได้ → `sudo chown www-data:www-data .env && sudo chmod 640 .env` |
| “CSRF ไม่ถูกต้อง” ตลอด | `storage/sessions` เขียนไม่ได้ → `sudo chown -R www-data:www-data storage && sudo chmod -R 775 storage` |
| อัปโหลดไฟล์ไม่สำเร็จ | โฟลเดอร์ปลายทางไม่มีหรือเขียนไม่ได้ → สร้างแล้ว `chown www-data:www-data` · ตรวจ `upload_max_filesize` ใน php.ini |
| กล้องตรวจข้อสอบไม่เปิด | ต้องเป็น HTTPS เท่านั้น → ทำข้อ 5 ให้ครบแล้วรัน certbot |
| เว็บช้า / ค้าง | RAM ไม่พอ → `free -h` ถ้าเหลือน้อยให้ขยายเป็น `e2-medium` (ข้อ 12) |
| SSH เข้าไม่ได้ | ใช้ Serial console จากหน้าเว็บ Console → `gcloud compute connect-to-serial-port school-erp` |
| ดูข้อผิดพลาดของเว็บ | `sudo tail -50 /var/log/apache2/school-erp-error.log` และ `storage/logs/` |

---

## 15. ใบตรวจก่อนเปิดใช้จริง

**ระบบ**

- [ ] เปิด `https://โดเมน` ได้ มีกุญแจ 🔒 และ `http://` ถูกเปลี่ยนเส้นทางไป `https://` อัตโนมัติ
- [ ] `APP_ENV=production` · `APP_DEBUG=false` · `DEMO_LOGIN=false` ใน `.env`
- [ ] `.env` เจ้าของ `www-data` สิทธิ์ `640` และ `sudo -u www-data php -r ...` อ่านค่าได้ถูกต้อง
- [ ] เข้าถึง `https://โดเมน/.env` และ `https://โดเมน/app/` จากเบราว์เซอร์แล้ว **ถูกปฏิเสธ**
- [ ] เข้าสู่ระบบด้วยแอดมินได้ · เปลี่ยนรหัสผ่านเริ่มต้นแล้ว
- [ ] ตั้งข้อมูลโรงเรียน · โลโก้ · ตราครุฑ · ปีการศึกษาปัจจุบัน เรียบร้อย
- [ ] ทดสอบ: อัปโหลดไฟล์ · พิมพ์เอกสาร 1 ใบ · ตรวจข้อสอบด้วยกล้องจากมือถือจริง

**คลาวด์**

- [ ] IP ถาวรผูกกับเครื่องแล้ว (รีสตาร์ตแล้ว IP ไม่เปลี่ยน)
- [ ] เปิดเฉพาะพอร์ต 80/443 · ไม่มีกฎเปิด 3306
- [ ] ตารางสแนปช็อตดิสก์ทำงาน (`gcloud compute snapshots list` มีรายการหลังผ่านไป 1 คืน)
- [ ] cron สำรองขึ้น Cloud Storage ทำงาน (`gcloud storage ls -r gs://...`)
- [ ] **ซ้อมกู้คืนจากไฟล์สำรองสำเร็จอย่างน้อย 1 ครั้ง**
- [ ] ตั้ง Budget alert แล้ว
- [ ] เก็บข้อมูลสำคัญไว้ที่ปลอดภัย: PROJECT_ID · ชื่อเครื่อง/โซน · IP · รหัส root ฐานข้อมูล · บัญชีแอดมินระบบ

---

### ไฟล์ที่เกี่ยวข้อง

| ไฟล์ | หน้าที่ |
|---|---|
| `deploy/gcp/setup_vm.sh` | เตรียมเครื่อง Debian 12 ให้พร้อมรันระบบ (Apache + PHP + MariaDB + HTTPS + cron) |
| `deploy/gcp/backup_to_gcs.sh` | สำรองข้อมูลแล้วส่งขึ้น Cloud Storage |
| `deploy/new_org.sh` · `deploy/central_setup.sh` | ติดตั้งโรงเรียนเดียว · ศูนย์ควบคุมหลายโรงเรียน |
| `deploy/update.sh` · `deploy/migrate.php` | อัปเดตระบบครบทุกโรงเรียน |
| [DEPLOY.md](DEPLOY.md) · [UPGRADE.md](UPGRADE.md) · [MANUAL.md](MANUAL.md) | ติดตั้งทั่วไป · อัปเดต · คู่มือใช้งาน |
