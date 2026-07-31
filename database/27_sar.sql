-- ==================================================================
--  27_sar.sql — รายงานผลการปฏิบัติงานและผลการประเมินตนเอง (SAR)
--   ครู 1 คน ทำ 1 ฉบับต่อปีการศึกษา ตามฟอร์ม สพป.นม.3
--   ข้อมูลตอนต่างๆ เก็บเป็น JSON (ยืดหยุ่น พิมพ์เป็นฟอร์มได้)
--   ส่วนที่ต้องรายงาน/รวมสถิติ เก็บเป็นคอลัมน์จริง
--   นำเข้าหลัง 01-26 · รันซ้ำได้อย่างปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sar_reports (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      INT UNSIGNED NOT NULL DEFAULT 1,
  personnel_id   INT UNSIGNED NOT NULL,
  academic_year  SMALLINT NOT NULL COMMENT 'ปีการศึกษา พ.ศ. เช่น 2569',

  -- ข้อมูลทั่วไป (ส่วนหัว)
  position          VARCHAR(120) DEFAULT NULL COMMENT 'ตำแหน่ง',
  academic_standing VARCHAR(120) DEFAULT NULL COMMENT 'วิทยฐานะ',
  subject_group     VARCHAR(160) DEFAULT NULL COMMENT 'กลุ่มสาระการเรียนรู้',
  teach_hours       DECIMAL(5,1) DEFAULT NULL COMMENT 'ชั่วโมงสอน/สัปดาห์',
  special_duties    TEXT DEFAULT NULL COMMENT 'งานพิเศษ/หน้าที่ที่ได้รับมอบหมาย (สรุป)',

  -- แต่ละตอนของฟอร์ม (โครงสร้างยืดหยุ่น)
  self_data      JSON DEFAULT NULL COMMENT 'ตอน1: ประวัติ/การศึกษา/รางวัล',
  develop_data   JSON DEFAULT NULL COMMENT 'ตอน2: อบรม/PLC/พัฒนาตนเอง',
  duties_data    JSON DEFAULT NULL COMMENT 'ตอน3: วิชาที่สอน/กิจกรรม/งานพิเศษ',
  results_data   JSON DEFAULT NULL COMMENT 'ตอน4: ผลการเรียน/เปรียบเทียบ',
  student_data   JSON DEFAULT NULL COMMENT 'ตอน5: ผลงานผู้เรียน/แหล่งเรียนรู้/วิจัย',
  improve_data   JSON DEFAULT NULL COMMENT 'ตอน6: แนวทางพัฒนา',

  -- การประเมินตามมาตรฐานตำแหน่ง + จรรยาบรรณ (โครงสร้างคงที่ เก็บ JSON คะแนน)
  eval_data      JSON DEFAULT NULL COMMENT 'ประเมิน: การสอน/บริหารชั้นเรียน/พัฒนาตน/จรรยาบรรณ',
  eval_score     DECIMAL(5,2) DEFAULT NULL COMMENT 'คะแนนรวมเฉลี่ย (คำนวณตอนบันทึก)',

  -- workflow (ตามบันทึกข้อความในฟอร์ม)
  status         ENUM('draft','submitted','reviewed','approved','returned') NOT NULL DEFAULT 'draft',
  submitted_at   DATETIME DEFAULT NULL,
  reviewer_id    INT UNSIGNED DEFAULT NULL COMMENT 'รอง ผอ.วิชาการ',
  reviewer_comment TEXT DEFAULT NULL,
  reviewed_at    DATETIME DEFAULT NULL,
  director_id    INT UNSIGNED DEFAULT NULL,
  director_comment TEXT DEFAULT NULL,
  approved_at    DATETIME DEFAULT NULL,

  created_by     INT UNSIGNED DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_sar_person_year (personnel_id, academic_year),
  KEY ix_sar_year (academic_year, status),
  KEY ix_sar_person (personnel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sar_attachments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sar_id        INT UNSIGNED NOT NULL,
  category      ENUM('training_cert','teaching_order','admin_order','report','award','photo','research','other')
                NOT NULL DEFAULT 'other' COMMENT 'ประเภทเอกสารแนบ (ตามภาคผนวก)',
  file_path     VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type     VARCHAR(120) DEFAULT NULL,
  size_bytes    INT UNSIGNED DEFAULT 0,
  note          VARCHAR(255) DEFAULT NULL,
  uploaded_by   INT UNSIGNED DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sar_att (sar_id, category),
  CONSTRAINT fk_sar_att FOREIGN KEY (sar_id) REFERENCES sar_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- สิทธิ์ ----
INSERT IGNORE INTO permissions (code, name, module) VALUES
  ('sar.own','จัดทำ SAR ของตนเอง','academic'),
  ('sar.review','ตรวจ/อนุมัติ SAR','academic'),
  ('sar.report','ดูรายงานภาพรวม SAR','academic');

-- ครูทุกคนทำ SAR ของตนเองได้
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='sar.own' AND r.code IN ('teacher','advisor','head_academic','head_budget','head_hr','head_general','deputy_director','director');

-- หัวหน้าวิชาการ/รอง/ผอ. ตรวจและอนุมัติ + ดูรายงานภาพรวม
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code IN ('sar.review','sar.report')
    AND r.code IN ('head_academic','deputy_director','director');

-- จบ 27
