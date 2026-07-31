-- ==================================================================
--  27_budget_org_mapping.sql — จัดหมวดงบประมาณตามฝ่าย + กลุ่มสาระ
--  * ฝ่าย: ครบ 6 (บริหาร + วิชาการ + แผนและงบประมาณ + บุคคล + บริหารทั่วไป + กิจการนักเรียน)
--  * กลุ่มสาระ: ครบ 8 กลุ่มตามหลักสูตรแกนกลาง
--  * โครงการ/บันทึก งป.01 เลือก ฝ่าย + กลุ่มสาระ ได้จากข้อมูลจริง
--  นำเข้าหลัง 01-26 · รันซ้ำได้ปลอดภัย (idempotent)
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

-- ---------- helper: เพิ่มคอลัมน์แบบ idempotent ----------
DROP PROCEDURE IF EXISTS add_col_if_missing;
DELIMITER //
CREATE PROCEDURE add_col_if_missing(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl VARCHAR(500))
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema='school_erp' AND table_name=tbl AND column_name=col)=0 THEN
    SET @s := CONCAT('ALTER TABLE ', tbl, ' ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //
DELIMITER ;

-- ===== 1) ฝ่าย (org_departments) : ปรับให้ครบ 6 ฝ่าย =====
-- เปลี่ยนชื่อ "ฝ่ายงบประมาณ" -> "ฝ่ายแผนและงบประมาณ" (คง code=budget เดิม)
UPDATE org_departments SET name='ฝ่ายแผนและงบประมาณ'
  WHERE code='budget' AND name<>'ฝ่ายแผนและงบประมาณ';

-- เพิ่ม "ฝ่ายกิจการนักเรียน" ถ้ายังไม่มี
INSERT INTO org_departments (school_id, code, name)
  SELECT 1,'affairs','ฝ่ายกิจการนักเรียน'
  WHERE NOT EXISTS (SELECT 1 FROM org_departments WHERE code='affairs');

-- ===== 2) กลุ่มสาระการเรียนรู้ (subject_groups) : ให้ครบ 8 กลุ่ม =====
INSERT INTO subject_groups (school_id, code, name)
  SELECT 1,'HPE','สุขศึกษาและพลศึกษา'
  WHERE NOT EXISTS (SELECT 1 FROM subject_groups WHERE code='HPE');
INSERT INTO subject_groups (school_id, code, name)
  SELECT 1,'ART','ศิลปะ'
  WHERE NOT EXISTS (SELECT 1 FROM subject_groups WHERE code='ART');
INSERT INTO subject_groups (school_id, code, name)
  SELECT 1,'WORK','การงานอาชีพ'
  WHERE NOT EXISTS (SELECT 1 FROM subject_groups WHERE code='WORK');

-- ===== 3) โครงการ : ผูกกลุ่มสาระ (ฝ่ายมี department_id อยู่แล้ว) =====
CALL add_col_if_missing('projects','subject_group_id',
  'subject_group_id BIGINT UNSIGNED DEFAULT NULL AFTER department_id');

-- ===== 4) บันทึก งป.01 : ผูกฝ่าย + กลุ่มสาระ จากข้อมูลจริง =====
--  เดิมเก็บ department (varchar) / work_group (varchar) แบบข้อความ
--  เพิ่ม FK เพื่อเลือกจากตารางจริง — คงคอลัมน์เดิมไว้เพื่อแสดงผลย้อนหลัง
CALL add_col_if_missing('budget_memos','department_id',
  'department_id BIGINT UNSIGNED DEFAULT NULL AFTER department');
CALL add_col_if_missing('budget_memos','subject_group_id',
  'subject_group_id BIGINT UNSIGNED DEFAULT NULL AFTER work_group');

-- เติม department_id ให้บันทึกเก่าที่ department (ข้อความ) ตรงกับชื่อฝ่ายจริง
UPDATE budget_memos m
  JOIN org_departments d ON d.name=m.department
  SET m.department_id=d.id
  WHERE m.department_id IS NULL AND m.department IS NOT NULL AND m.department<>'';

DROP PROCEDURE IF EXISTS add_col_if_missing;

-- ===== 5) ดัชนีช่วยรายงานจัดกลุ่ม =====
-- (สร้างเมื่อยังไม่มี — ใช้ stored proc กันซ้ำ)
DROP PROCEDURE IF EXISTS add_idx_if_missing;
DELIMITER //
CREATE PROCEDURE add_idx_if_missing(IN tbl VARCHAR(64), IN idx VARCHAR(64), IN cols VARCHAR(200))
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema='school_erp' AND table_name=tbl AND index_name=idx)=0 THEN
    SET @s := CONCAT('ALTER TABLE ', tbl, ' ADD INDEX ', idx, ' (', cols, ')');
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //
DELIMITER ;
CALL add_idx_if_missing('projects','idx_proj_subject_group','subject_group_id');
CALL add_idx_if_missing('budget_memos','idx_memo_dept','department_id');
CALL add_idx_if_missing('budget_memos','idx_memo_subject_group','subject_group_id');
DROP PROCEDURE IF EXISTS add_idx_if_missing;

-- จบ 27
