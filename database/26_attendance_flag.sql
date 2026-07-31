-- ==================================================================
--  26_attendance_flag.sql — เช็คชื่อหน้าเสาธง (เข้าโรงเรียน) + รายงานการมาเรียน
--   · ใช้ตาราง attendances เดิม โดย attendance_type='flag' = หน้าเสาธง
--     (teaching_assignment_id ยังคง NULL, period_no NULL)
--   · ตั้งเวลาสาย/ขาด เดียวทั้งโรงเรียนใน system_settings กลุ่ม 'attendance'
--   · สิทธิ์ academic.attendance ครูที่ปรึกษามีอยู่แล้ว
--  นำเข้าหลัง 01-25 · รันซ้ำได้อย่างปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

-- ---- เพิ่มคอลัมน์แยกประเภทการเช็คชื่อ (รายวิชา / หน้าเสาธง) ----
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

CALL add_col_if_missing('attendances','attendance_type',
  "attendance_type ENUM('subject','flag') NOT NULL DEFAULT 'subject' COMMENT 'subject=รายวิชา, flag=หน้าเสาธง/เข้าโรงเรียน'");
CALL add_col_if_missing('attendances','arrived_at',
  "arrived_at TIME DEFAULT NULL COMMENT 'เวลามาจริง (ถ้าบันทึก)'");

DROP PROCEDURE IF EXISTS add_col_if_missing;

-- ---- ดัชนีช่วยรายงาน + กันเช็คหน้าเสาธงซ้ำต่อคนต่อวัน ----
SET @has := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema='school_erp' AND table_name='attendances'
               AND index_name='ix_att_type_date');
SET @s := IF(@has=0,
  'CREATE INDEX ix_att_type_date ON attendances (attendance_type, attendance_date, classroom_id)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---- ค่าตั้งต้นเวลาเข้าเรียน (เดียวทั้งโรงเรียน) ----
INSERT INTO system_settings (group_key, setting_key, setting_value, value_type)
  VALUES
    ('attendance','late_time','08:00','string'),
    ('attendance','absent_time','08:30','string'),
    ('attendance','enabled','1','bool')
  ON DUPLICATE KEY UPDATE setting_key=setting_key;   -- ไม่ทับค่าที่ผู้ใช้ตั้งไว้

-- ---- สิทธิ์: ดูรายงานการมาเรียน (เพิ่มให้บทบาทที่เกี่ยวข้อง) ----
INSERT IGNORE INTO permissions (code, name)
  VALUES ('academic.attendance_report','ดูรายงานการมาเรียน');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='academic.attendance_report'
    AND r.code IN ('advisor','teacher','head_academic','director','deputy_director');

-- ถอนสิทธิ์ที่เคยให้ clerk ในเวอร์ชันก่อน (clerk = ธุรการสารบรรณ ไม่เกี่ยวการมาเรียน)
DELETE rp FROM role_permissions rp
  JOIN roles r ON r.id=rp.role_id
  JOIN permissions p ON p.id=rp.permission_id
  WHERE r.code='clerk' AND p.code='academic.attendance_report';

-- ผู้บริหาร/หัวหน้าวิชาการ ต้องเข้าหน้าเช็คชื่อ (ดูภาพรวมทุกห้อง) ได้ด้วย
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='academic.attendance'
    AND r.code IN ('head_academic','director','deputy_director');


-- ---- ผูกบัญชีครูที่ปรึกษา (demo) เข้ากับห้องประจำชั้น ----
--  บัญชี advisor สาธิตต้องเป็นครูที่ปรึกษาห้องใดห้องหนึ่ง จึงจะเช็คหน้าเสาธงได้
--  ผูกกับครูที่ปรึกษา (homeroom_teacher_id) ของห้องแรกที่มีอยู่
UPDATE users u
   JOIN (SELECT homeroom_teacher_id FROM classrooms
          WHERE homeroom_teacher_id IS NOT NULL ORDER BY id LIMIT 1) c
    SET u.linked_type='personnel', u.linked_id=c.homeroom_teacher_id
 WHERE u.username='advisor'
   AND (u.linked_type='none' OR u.linked_id IS NULL);

-- จบ 26
