-- ==================================================================
--  55_nurse_office.sql — งานพยาบาล/ห้องพยาบาล (ฝ่ายบริหารทั่วไป)
--  บันทึกการใช้บริการห้องพยาบาล + คลังยา/เวชภัณฑ์ (ตัดสต็อกอัตโนมัติเมื่อจ่ายยา)
--  ใช้สิทธิ์ general.health (งานอนามัย) ที่มีอยู่เดิม
--  นำเข้าหลัง 01-54 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

-- ---------- คลังยา/เวชภัณฑ์ ----------
CREATE TABLE IF NOT EXISTS medicines (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(40)  NULL,                       -- รหัสยา (ถ้ามี)
  name        VARCHAR(200) NOT NULL,                   -- ชื่อยา/เวชภัณฑ์
  category    VARCHAR(30)  NOT NULL DEFAULT 'medicine',-- medicine|supply|equipment
  unit        VARCHAR(40)  NOT NULL DEFAULT 'เม็ด',     -- หน่วยนับ
  stock_qty   INT          NOT NULL DEFAULT 0,         -- คงเหลือ
  min_qty     INT          NOT NULL DEFAULT 0,         -- จุดสั่งซื้อ (แจ้งเตือนเมื่อต่ำกว่า)
  expiry_date DATE         NULL,                       -- วันหมดอายุ
  note        VARCHAR(300) NULL,
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_medicines_name (name),
  KEY idx_medicines_active (active, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- ความเคลื่อนไหวสต็อก (รับเข้า/จ่ายออก/ปรับปรุง) ----------
CREATE TABLE IF NOT EXISTS medicine_movements (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  medicine_id   BIGINT UNSIGNED NOT NULL,
  movement_type VARCHAR(10) NOT NULL DEFAULT 'in',      -- in|out|adjust
  qty           INT NOT NULL DEFAULT 0,                 -- จำนวน (บวกเสมอ; ทิศทางดูที่ movement_type)
  balance_after INT NOT NULL DEFAULT 0,                 -- คงเหลือหลังรายการ
  visit_id      BIGINT UNSIGNED NULL,                   -- อ้างอิงการใช้บริการ (ถ้าจ่ายยา)
  note          VARCHAR(300) NULL,
  moved_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by    BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_med_mov_med (medicine_id, moved_at),
  KEY idx_med_mov_visit (visit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- บันทึกการใช้บริการห้องพยาบาล ----------
CREATE TABLE IF NOT EXISTS nurse_visits (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visit_at      DATETIME NOT NULL,
  patient_type  VARCHAR(10) NOT NULL DEFAULT 'student', -- student|personnel|other
  student_id    BIGINT UNSIGNED NULL,
  personnel_id  BIGINT UNSIGNED NULL,
  patient_name  VARCHAR(200) NULL,                      -- กรณี other หรือสำรองชื่อ
  symptom       VARCHAR(500) NULL,                      -- อาการ
  diagnosis     VARCHAR(300) NULL,                      -- การวินิจฉัยเบื้องต้น
  treatment     VARCHAR(500) NULL,                      -- การปฐมพยาบาล/รักษา
  outcome       VARCHAR(20) NOT NULL DEFAULT 'back_class', -- back_class|rest|home|refer|other
  refer_to      VARCHAR(200) NULL,                      -- ส่งต่อ รพ./สถานพยาบาล
  note          VARCHAR(500) NULL,
  recorded_by   BIGINT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_nurse_visit_at (visit_at),
  KEY idx_nurse_visit_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- ยาที่จ่ายในแต่ละครั้ง ----------
CREATE TABLE IF NOT EXISTS nurse_visit_medicines (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visit_id    BIGINT UNSIGNED NOT NULL,
  medicine_id BIGINT UNSIGNED NOT NULL,
  qty         INT NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_nvm_visit (visit_id),
  KEY idx_nvm_med (medicine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- สิทธิ์: ใช้ general.health เดิม + ขยายให้ผู้เกี่ยวข้อง ----------
INSERT INTO permissions (code, name, module)
  SELECT 'general.health', 'งานอนามัย/ห้องพยาบาล', 'general'
  WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='general.health');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='general.health'
    AND r.code IN ('super_admin','director','deputy_director','head_general','clerk');

-- จบ 55
