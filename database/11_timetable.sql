-- ==================================================================
--  11_timetable.sql — ระบบจัดตารางสอน (AI + ลาก-วาง) + เงื่อนไข 20 ข้อ
--  นำเข้าหลังจาก 01-10
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- ---- ประเภทห้อง: เพิ่ม computer / gym ----
ALTER TABLE rooms MODIFY COLUMN room_type
  ENUM('classroom','lab','computer','gym','meeting','office','other') NOT NULL DEFAULT 'classroom';

-- ---- คุณสมบัติวิชาเพื่อการจัดตาราง ----
ALTER TABLE subjects
  ADD COLUMN required_room_type ENUM('any','lab','computer','gym') NOT NULL DEFAULT 'any' AFTER subject_type,
  ADD COLUMN is_heavy   TINYINT(1) NOT NULL DEFAULT 0 AFTER required_room_type,  -- วิชาหนัก (จัดช่วงเช้า)
  ADD COLUMN is_pe      TINYINT(1) NOT NULL DEFAULT 0 AFTER is_heavy,            -- พลศึกษา (วันละไม่เกิน 1)
  ADD COLUMN block_size TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER is_pe;         -- คาบติดกันต่อครั้ง (block 2-4)

-- ---- เป้าหมายคาบ/สัปดาห์ + ล็อก ----
ALTER TABLE teaching_assignments
  ADD COLUMN weekly_periods TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER is_primary;
ALTER TABLE class_schedules
  ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER room_id;

-- ---- ครูไม่ว่าง / part-time ระบุวันมาสอน ----
CREATE TABLE IF NOT EXISTS teacher_unavailable (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  teacher_id  BIGINT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,   -- 1..5
  period_no   TINYINT UNSIGNED DEFAULT NULL, -- NULL = ทั้งวัน
  reason      VARCHAR(150) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY ix_tu_teacher (teacher_id),
  CONSTRAINT fk_tu_teacher FOREIGN KEY (teacher_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- part-time: ระบุวันมาสอน (คั่นด้วย ,) — ว่าง=ทุกวัน
ALTER TABLE personnel
  ADD COLUMN is_part_time TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN work_days VARCHAR(20) DEFAULT NULL AFTER is_part_time,   -- เช่น "1,3,5"
  ADD COLUMN max_weekly_periods TINYINT UNSIGNED DEFAULT NULL AFTER work_days;

-- ---- ค่าตั้งค่าตารางสอน ----
INSERT INTO system_settings (group_key, setting_key, setting_value, value_type) VALUES
 ('timetable','periods_per_day','8','int'),
 ('timetable','days_per_week','5','int'),
 ('timetable','lunch_period','5','int'),               -- คาบพักกลางวัน (เว้นว่าง)
 ('timetable','max_per_subject_per_day','2','int'),
 ('timetable','max_consecutive','3','int'),
 ('timetable','morning_last_period','4','int'),         -- ช่วงเช้า = คาบ 1..4
 ('timetable','teacher_max_weekly','25','int')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- ================= ตัวอย่าง =================
-- ห้องพิเศษ
INSERT INTO rooms (school_id, room_code, name, room_type, capacity, is_bookable) VALUES
 (1,'LAB-SCI','ห้องปฏิบัติการวิทยาศาสตร์','lab',40,1),
 (1,'LAB-COM','ห้องคอมพิวเตอร์','computer',40,1),
 (1,'GYM','โรงยิม/สนามกีฬา','gym',80,1);

-- คุณสมบัติวิชา (อ้างอิงข้อมูล 04: 1=ภาษาไทย 2=คณิต 3=วิทย์ 5=อังกฤษ)
UPDATE subjects SET is_heavy=1 WHERE subject_code IN ('ค21101','ท21101');       -- คณิต/ไทย = วิชาหนัก
UPDATE subjects SET required_room_type='lab', is_heavy=1 WHERE subject_code='ว21101';  -- วิทยาศาสตร์ → Lab
-- เป้าหมายคาบ/สัปดาห์ตัวอย่าง
UPDATE teaching_assignments SET weekly_periods=3 WHERE subject_id IN (1,2,3);
UPDATE teaching_assignments SET weekly_periods=2 WHERE subject_id IN (5);

-- จบ 11
