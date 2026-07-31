-- ==================================================================
--  17_hr.sql — งานฝ่ายบุคลากรครบระบบ
--  ลงเวลาปฏิบัติงาน + เงินเดือน + PA/วิทยฐานะ/ประเมิน (ลา/มาสาย ใช้ตารางเดิม)
--  นำเข้าหลัง 01-16
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- ลงเวลาปฏิบัติงานรายวัน
CREATE TABLE IF NOT EXISTS staff_attendance (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  personnel_id  BIGINT UNSIGNED NOT NULL,
  work_date     DATE NOT NULL,
  check_in      TIME DEFAULT NULL,
  check_out     TIME DEFAULT NULL,
  status        ENUM('present','late','absent','leave','official') NOT NULL DEFAULT 'present',
  note          VARCHAR(255) DEFAULT NULL,
  recorded_by   BIGINT UNSIGNED DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sa (personnel_id, work_date),
  KEY ix_sa_date (work_date),
  CONSTRAINT fk_sa_person FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เงินเดือน/ค่าตอบแทนรายเดือน
CREATE TABLE IF NOT EXISTS salary_records (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  personnel_id  BIGINT UNSIGNED NOT NULL,
  year_be       SMALLINT UNSIGNED NOT NULL,     -- ปี พ.ศ.
  month         TINYINT UNSIGNED NOT NULL,       -- 1-12
  base_salary   DECIMAL(12,2) NOT NULL DEFAULT 0,
  allowance     DECIMAL(12,2) NOT NULL DEFAULT 0, -- เงินประจำตำแหน่ง/วิทยฐานะ/ค่าครองชีพ
  deduction     DECIMAL(12,2) NOT NULL DEFAULT 0, -- กบข./ภาษี/หักอื่นๆ
  net_salary    DECIMAL(12,2) NOT NULL DEFAULT 0,
  note          VARCHAR(255) DEFAULT NULL,
  created_by    BIGINT UNSIGNED DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_salary (personnel_id, year_be, month),
  KEY ix_salary_period (year_be, month),
  CONSTRAINT fk_salary_person FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- การประเมินผลการปฏิบัติงาน (PA) / เลื่อนวิทยฐานะ
CREATE TABLE IF NOT EXISTS pa_evaluations (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  personnel_id  BIGINT UNSIGNED NOT NULL,
  year_be       SMALLINT UNSIGNED NOT NULL,
  round         TINYINT UNSIGNED NOT NULL DEFAULT 1, -- รอบที่ 1/2
  eval_type     ENUM('performance','academic_standing') NOT NULL DEFAULT 'performance', -- ประเมินผลงาน/เลื่อนวิทยฐานะ
  score         DECIMAL(5,2) DEFAULT NULL,           -- คะแนน 0-100
  grade         VARCHAR(30) DEFAULT NULL,            -- ดีเด่น/ดีมาก/ดี/พอใช้/ปรับปรุง
  target_standing VARCHAR(80) DEFAULT NULL,          -- วิทยฐานะเป้าหมาย (ชำนาญการ/พิเศษ/เชี่ยวชาญ)
  result        ENUM('pending','passed','failed') NOT NULL DEFAULT 'pending',
  comment       TEXT DEFAULT NULL,
  evaluator_id  BIGINT UNSIGNED DEFAULT NULL,
  eval_date     DATE DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pa_person (personnel_id),
  KEY ix_pa_year (year_be),
  CONSTRAINT fk_pa_person FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สิทธิ์ (ใช้สิทธิ์ hr.* เดิมที่มีใน config/menu.php อยู่แล้ว — เพิ่มลง permissions ให้ครบ)
INSERT IGNORE INTO permissions (code, name, module) VALUES
  ('hr.personnel',  'ข้อมูลบุคลากร',        'hr'),
  ('hr.leave',      'การลา/มาสาย',          'hr'),
  ('hr.attendance', 'ลงเวลาปฏิบัติงาน',      'hr'),
  ('hr.salary',     'เงินเดือน/ค่าตอบแทน',   'hr'),
  ('hr.evaluation', 'PA/วิทยฐานะ/ประเมิน',   'hr');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code IN ('hr.personnel','hr.leave','hr.attendance','hr.salary','hr.evaluation')
    AND r.code IN ('director','deputy_director','head_hr','clerk');

-- ===== ข้อมูลตัวอย่าง =====
-- ลงเวลาวันนี้ให้บุคลากร 3 คนแรก
INSERT IGNORE INTO staff_attendance (personnel_id, work_date, check_in, check_out, status)
  SELECT id, CURDATE(), '07:55:00', '16:35:00', 'present' FROM personnel WHERE deleted_at IS NULL ORDER BY id LIMIT 3;

-- เงินเดือนเดือนปัจจุบันให้บุคลากร 3 คนแรก
INSERT IGNORE INTO salary_records (personnel_id, year_be, month, base_salary, allowance, deduction, net_salary)
  SELECT id, YEAR(CURDATE())+543, MONTH(CURDATE()), 32000, 5600, 3800, 33800
  FROM personnel WHERE deleted_at IS NULL ORDER BY id LIMIT 3;

-- PA รอบ 1 ให้บุคลากร 2 คนแรก
INSERT INTO pa_evaluations (personnel_id, year_be, round, eval_type, score, grade, result, eval_date)
  SELECT id, YEAR(CURDATE())+543, 1, 'performance', 89.5, 'ดีมาก', 'passed', CURDATE()
  FROM personnel WHERE deleted_at IS NULL ORDER BY id LIMIT 2;

-- จบ 17
