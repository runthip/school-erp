-- ==================================================================
--  12_timetable_pro.sql — ตาราง 2 แบบ (ประถม 6 / มัธยม 7 คาบ) + เผยแพร่ตาราง
--  นำเข้าหลังจาก 01-11
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- ---- ค่าตั้งค่าแยกระดับ ----
INSERT INTO system_settings (group_key, setting_key, setting_value, value_type) VALUES
 ('timetable','periods_primary','6','int'),
 ('timetable','periods_secondary','7','int'),
 ('timetable','lunch_primary','4','int'),      -- ประถมพักหลังคาบ 4 (คาบพัก = คาบ 4? -> ใช้เป็นคาบพักที่เว้นว่าง)
 ('timetable','lunch_secondary','5','int'),
 ('timetable','start_time','08:30','string')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- ---- ทะเบียนเผยแพร่ตาราง ----
CREATE TABLE IF NOT EXISTS timetable_publications (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  classroom_id BIGINT UNSIGNED NOT NULL,
  semester_id  BIGINT UNSIGNED NOT NULL,
  published_by BIGINT UNSIGNED DEFAULT NULL,
  published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pub (classroom_id, semester_id),
  CONSTRAINT fk_pub_cr  FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_pub_sem FOREIGN KEY (semester_id)  REFERENCES semesters(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ห้องเรียนประถมตัวอย่าง (ป.6/1) เพื่อทดสอบตาราง 6 คาบ ----
INSERT IGNORE INTO grade_levels (school_id, code, name, level_order, stage) VALUES (1,'P6','ประถมศึกษาปีที่ 6',6,'primary');
INSERT IGNORE INTO classrooms (school_id, academic_year_id, grade_level_id, section, name)
  SELECT 1, 1, gl.id, 1, 'ป.6/1' FROM grade_levels gl WHERE gl.code='P6' LIMIT 1;

-- มอบหมายวิชาสอนให้ ป.6/1 (คณิต+ไทย) เพื่อทดสอบ
INSERT IGNORE INTO teaching_assignments (semester_id, subject_id, classroom_id, teacher_id, is_primary, weekly_periods)
  SELECT 1, 1, c.id, 3, 1, 3 FROM classrooms c WHERE c.name='ป.6/1';
INSERT IGNORE INTO teaching_assignments (semester_id, subject_id, classroom_id, teacher_id, is_primary, weekly_periods)
  SELECT 1, 2, c.id, 4, 1, 3 FROM classrooms c WHERE c.name='ป.6/1';

-- จบ 12
