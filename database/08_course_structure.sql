-- ==================================================================
--  08_course_structure.sql  —  โครงสร้างรายวิชา (มาตรฐาน/ตัวชี้วัด/ชม.) + สัดส่วนรายวิชา
--  นำเข้าหลังจาก 01-07
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- ---- โครงสร้างรายวิชาในองค์ประกอบคะแนน ----
ALTER TABLE grade_components
  ADD COLUMN standard_code VARCHAR(50)  DEFAULT NULL AFTER name,
  ADD COLUMN indicator     VARCHAR(300) DEFAULT NULL AFTER standard_code,
  ADD COLUMN hours         DECIMAL(5,1) DEFAULT NULL AFTER indicator;

-- ---- สัดส่วนคะแนนแยกรายวิชา (NULL = ใช้ค่าเริ่มต้นของระบบ) ----
ALTER TABLE teaching_assignments
  ADD COLUMN ratio_during TINYINT UNSIGNED DEFAULT NULL AFTER is_primary,
  ADD COLUMN ratio_final  TINYINT UNSIGNED DEFAULT NULL AFTER ratio_during;

-- ---- ตัวอย่างโครงสร้าง TA1 ----
UPDATE grade_components SET standard_code='ค 1.1', indicator='เข้าใจจำนวนและการดำเนินการ', hours=12 WHERE id=1;
UPDATE grade_components SET standard_code='ค 1.2', indicator='ใช้การประมาณค่าในการคำนวณ',   hours=10 WHERE id=2;
UPDATE grade_components SET standard_code='ค 1.1', indicator='สอบปลายภาคตามตัวชี้วัด',       hours=8  WHERE id=3;

-- ตัวอย่าง: วิชา TA5 ใช้สัดส่วน 60:40 (ต่างจากค่าระบบ 70:30)
UPDATE teaching_assignments SET ratio_during=60, ratio_final=40 WHERE id=5;

-- จบ 08
