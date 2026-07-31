-- ==================================================================
--  28_budget_memo.sql — บันทึกข้อความขออนุมัติดำเนินการตามกิจกรรม/โครงการ (งป.01)
--   เชื่อมกับ projects/project_activities/budgets + budget_ledger (ตัดงบ)
--   เมื่อ ผอ.อนุมัติ = ตัดงบผ่าน budget_ledger source_type='request'
--   นำเข้าหลัง 01-27 · รันซ้ำได้อย่างปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS budget_memos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     INT UNSIGNED NOT NULL DEFAULT 1,
  memo_no       VARCHAR(40)  DEFAULT NULL COMMENT 'เลขที่ งป.01 (ต่อปีงบ)',
  memo_date     DATE         DEFAULT NULL,
  budget_year   SMALLINT     NOT NULL COMMENT 'ปีงบประมาณ พ.ศ.',

  -- หัวเรื่อง
  department    VARCHAR(160) DEFAULT NULL COMMENT 'ฝ่าย/กลุ่ม/สาระฯ',
  activity_name VARCHAR(255) DEFAULT NULL COMMENT 'ชื่อกิจกรรม',
  purpose       TEXT         DEFAULT NULL COMMENT 'เพื่อ...',
  operate_date  VARCHAR(160) DEFAULT NULL COMMENT 'ดำเนินการในวันที่',

  -- เชื่อมโครงการ/กิจกรรม/แหล่งงบ (สำหรับตัดงบ)
  project_id    INT UNSIGNED DEFAULT NULL,
  activity_id   INT UNSIGNED DEFAULT NULL,
  budget_id     INT UNSIGNED DEFAULT NULL,

  -- จำนวนเงิน
  budget_total  DECIMAL(14,2) DEFAULT 0 COMMENT 'งบประมาณประจำปีของกิจกรรม',
  already_spent DECIMAL(14,2) DEFAULT 0 COMMENT 'ขอเบิกแล้ว',
  request_amount DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'ขออนุมัติครั้งนี้',
  remaining     DECIMAL(14,2) DEFAULT 0 COMMENT 'คงเหลือ (คำนวณ)',

  -- ประเภท (ตามฟอร์ม)
  work_group    VARCHAR(40) DEFAULT NULL COMMENT 'บริหารวิชาการ/บุคลากร/งบประมาณ/ทั่วไป/ส่วนกลาง',
  fund_source   VARCHAR(40) DEFAULT NULL COMMENT 'อุดหนุน/อาหารกลางวัน/รายได้สถานศึกษา/รายจ่ายบุคลากร',

  -- คณะกรรมการ (personnel id) — เก็บ JSON [{id,role}] เพื่อดึงชื่อ+ตำแหน่ง
  committee     JSON DEFAULT NULL COMMENT 'กรรมการจัดซื้อจัดจ้าง',
  inspectors    JSON DEFAULT NULL COMMENT 'ผู้ตรวจรับพัสดุ',
  responsible_id INT UNSIGNED DEFAULT NULL COMMENT 'ผู้รับผิดชอบโครงการ',

  -- workflow 5 ช่องความเห็น (ตามฟอร์ม)
  status        ENUM('draft','submitted','head_ok','budget_ok','supply_ok','deputy_ok','approved','rejected')
                NOT NULL DEFAULT 'draft',
  head_comment      VARCHAR(255) DEFAULT NULL,
  head_by           INT UNSIGNED DEFAULT NULL,
  budget_correct    TINYINT(1) DEFAULT NULL COMMENT 'หัวหน้าแผน: ถูกต้อง/ไม่',
  budget_by         INT UNSIGNED DEFAULT NULL,
  supply_approve    TINYINT(1) DEFAULT NULL COMMENT 'พัสดุ: เห็นควร/ไม่',
  supply_by         INT UNSIGNED DEFAULT NULL,
  deputy_approve    TINYINT(1) DEFAULT NULL COMMENT 'รองผอ: เห็นควร/ไม่',
  deputy_by         INT UNSIGNED DEFAULT NULL,
  director_approve  TINYINT(1) DEFAULT NULL COMMENT 'ผอ: อนุมัติ/ไม่',
  director_note     VARCHAR(255) DEFAULT NULL,
  director_by       INT UNSIGNED DEFAULT NULL,
  approved_at       DATETIME DEFAULT NULL,

  -- การตัดงบ
  ledger_id     INT UNSIGNED DEFAULT NULL COMMENT 'อ้าง budget_ledger เมื่อตัดงบแล้ว',
  deducted_at   DATETIME DEFAULT NULL,

  created_by    INT UNSIGNED DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY ix_memo_year (budget_year, status),
  KEY ix_memo_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- สิทธิ์ ----
INSERT IGNORE INTO permissions (code, name, module) VALUES
  ('budget.memo','จัดทำบันทึกขออนุมัติ (งป.01)','budget'),
  ('budget.memo_approve','พิจารณา/อนุมัติ งป.01','budget');

-- ครู/หัวหน้ากลุ่มจัดทำได้
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='budget.memo'
    AND r.code IN ('teacher','advisor','head_academic','head_budget','head_hr','head_general','deputy_director','director','clerk');

-- ผู้บริหาร/หัวหน้างบ พิจารณา-อนุมัติ
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='budget.memo_approve'
    AND r.code IN ('head_budget','deputy_director','director');

-- จบ 28
