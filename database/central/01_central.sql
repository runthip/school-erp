-- ==================================================================
--  01_central.sql — ฐานข้อมูล "ศูนย์ควบคุมกลาง" (control plane)
--  ใช้กับระบบหลายโรงเรียน: โค้ดชุดเดียว · แยกฐานข้อมูลต่อโรงเรียน
--  คีย์หลักของแต่ละโรงเรียน = รหัสโรงเรียน (school_code) ต้องไม่ซ้ำกัน
--
--  ติดตั้ง:
--    mysql -u root -p -e "CREATE DATABASE school_erp_central
--        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--    mysql -u root -p school_erp_central < database/central/01_central.sql
--  รันซ้ำได้ปลอดภัย
-- ==================================================================
SET NAMES utf8mb4;

-- ---------- ทะเบียนโรงเรียน (1 แถว = 1 โรงเรียน = 1 ฐานข้อมูล) ----------
CREATE TABLE IF NOT EXISTS tenants (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_code   VARCHAR(20)  NOT NULL,               -- คีย์หลักที่ใช้เข้าระบบ (ห้ามซ้ำ)
  name_th       VARCHAR(255) NOT NULL,
  db_name       VARCHAR(64)  NOT NULL,               -- ฐานข้อมูลของโรงเรียนนี้
  affiliation   VARCHAR(150) NULL,
  province      VARCHAR(100) NULL,
  contact_name  VARCHAR(150) NULL,
  contact_phone VARCHAR(50)  NULL,
  contact_email VARCHAR(150) NULL,
  admin_username VARCHAR(100) NULL,                  -- ชื่อผู้ใช้แอดมินของโรงเรียน (แอดมินลูก)
  status        VARCHAR(12)  NOT NULL DEFAULT 'active', -- active | suspended
  note          VARCHAR(500) NULL,
  created_by    BIGINT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tenant_code (school_code),           -- รหัสโรงเรียนห้ามซ้ำ
  UNIQUE KEY uq_tenant_db (db_name),                 -- ฐานข้อมูลห้ามซ้ำ (กันข้อมูลทับกัน)
  KEY idx_tenant_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- ผู้ดูแลระบบส่วนกลาง (Super admin ของผู้ให้บริการ) ----------
CREATE TABLE IF NOT EXISTS platform_admins (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(200) NOT NULL,
  email         VARCHAR(150) NULL,
  status        VARCHAR(12)  NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_padmin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- ประวัติการเข้าดูแลระบบของโรงเรียน (ตรวจสอบย้อนหลังได้) ----------
CREATE TABLE IF NOT EXISTS tenant_access_logs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id      BIGINT UNSIGNED NOT NULL,
  school_code    VARCHAR(20)  NOT NULL,
  platform_admin_id BIGINT UNSIGNED NULL,
  admin_username VARCHAR(100) NULL,                  -- Super admin ที่เข้าดูแล
  action         VARCHAR(20)  NOT NULL DEFAULT 'enter', -- enter | exit | provision | suspend | activate | reset_admin
  ip_address     VARCHAR(64)  NULL,
  note           VARCHAR(300) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tal_tenant (tenant_id, created_at),
  KEY idx_tal_admin (platform_admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- จบ 01_central
