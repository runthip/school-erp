-- ==================================================================
--  21_student_affairs.sql — งานกิจการนักเรียน
--  พฤติกรรม/SDQ · ระบบดูแลช่วยเหลือ-เยี่ยมบ้าน · ทุนการศึกษา
--  ตาราง behavior_records / sdq_assessments / home_visits / scholarships
--  มีอยู่แล้วใน 01_schema — ไฟล์นี้เพิ่มฟิลด์ให้ครบแบบฟอร์มราชการ
--  นำเข้าหลัง 01-20
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

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

-- ===== พฤติกรรม: เพิ่มหมวด + สถานะแจ้งผู้ปกครอง =====
CALL add_col_if_missing('behavior_records','category',
  "category VARCHAR(60) DEFAULT NULL COMMENT 'หมวดพฤติกรรม' AFTER type");
CALL add_col_if_missing('behavior_records','academic_year_id',
  'academic_year_id BIGINT UNSIGNED DEFAULT NULL AFTER student_id');
CALL add_col_if_missing('behavior_records','parent_notified',
  "parent_notified TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'แจ้งผู้ปกครองแล้ว'");
CALL add_col_if_missing('behavior_records','action_taken',
  'action_taken VARCHAR(500) DEFAULT NULL');

-- ===== SDQ: เก็บผลรายด้าน =====
CALL add_col_if_missing('sdq_assessments','emotional_group',
  "emotional_group ENUM('normal','risk','problem') DEFAULT NULL");
CALL add_col_if_missing('sdq_assessments','conduct_group',
  "conduct_group ENUM('normal','risk','problem') DEFAULT NULL");
CALL add_col_if_missing('sdq_assessments','hyperactivity_group',
  "hyperactivity_group ENUM('normal','risk','problem') DEFAULT NULL");
CALL add_col_if_missing('sdq_assessments','peer_group',
  "peer_group ENUM('normal','risk','problem') DEFAULT NULL");
CALL add_col_if_missing('sdq_assessments','prosocial_group',
  "prosocial_group ENUM('normal','risk','problem') DEFAULT NULL");
CALL add_col_if_missing('sdq_assessments','note','note VARCHAR(500) DEFAULT NULL');

-- ===== เยี่ยมบ้าน: ฟิลด์แบบบันทึกการเยี่ยมบ้านราชการ =====
CALL add_col_if_missing('home_visits','guardian_name','guardian_name VARCHAR(200) DEFAULT NULL');
CALL add_col_if_missing('home_visits','guardian_relation','guardian_relation VARCHAR(60) DEFAULT NULL');
CALL add_col_if_missing('home_visits','guardian_phone','guardian_phone VARCHAR(30) DEFAULT NULL');
CALL add_col_if_missing('home_visits','address','address VARCHAR(500) DEFAULT NULL');
CALL add_col_if_missing('home_visits','living_with',
  "living_with VARCHAR(60) DEFAULT NULL COMMENT 'อาศัยอยู่กับ'");
CALL add_col_if_missing('home_visits','family_status',
  "family_status ENUM('together','divorced','father_only','mother_only','relative','other') DEFAULT NULL");
CALL add_col_if_missing('home_visits','housing_type',
  "housing_type ENUM('own','rent','relative','other') DEFAULT NULL");
CALL add_col_if_missing('home_visits','family_income',
  "family_income DECIMAL(12,2) DEFAULT NULL COMMENT 'รายได้ครอบครัว/เดือน'");
CALL add_col_if_missing('home_visits','travel_method','travel_method VARCHAR(60) DEFAULT NULL');
CALL add_col_if_missing('home_visits','distance_km','distance_km DECIMAL(6,2) DEFAULT NULL');
CALL add_col_if_missing('home_visits','health_note','health_note VARCHAR(500) DEFAULT NULL');
CALL add_col_if_missing('home_visits','recommendation','recommendation VARCHAR(500) DEFAULT NULL');
CALL add_col_if_missing('home_visits','needs_help',
  "needs_help TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ควรได้รับการช่วยเหลือ'");
CALL add_col_if_missing('home_visits','next_visit_date','next_visit_date DATE DEFAULT NULL');

-- ===== ทุนการศึกษา: สถานะ + เอกสาร =====
CALL add_col_if_missing('scholarships','scholarship_type',
  "scholarship_type ENUM('poor','excellent','sport','art','special') NOT NULL DEFAULT 'poor' AFTER name");
CALL add_col_if_missing('scholarships','status',
  "status ENUM('proposed','approved','granted','rejected') NOT NULL DEFAULT 'proposed'");
CALL add_col_if_missing('scholarships','term',
  "term VARCHAR(60) DEFAULT NULL COMMENT 'ภาคเรียน/งวด'");
CALL add_col_if_missing('scholarships','note','note VARCHAR(500) DEFAULT NULL');
CALL add_col_if_missing('scholarships','approved_by','approved_by BIGINT UNSIGNED DEFAULT NULL');
CALL add_col_if_missing('scholarships','approved_at','approved_at DATETIME DEFAULT NULL');
CALL add_col_if_missing('scholarships','receipt_no','receipt_no VARCHAR(40) DEFAULT NULL');

DROP PROCEDURE IF EXISTS add_col_if_missing;

-- ===== สิทธิ์ (มีใน 03_seed_demo แล้ว — ผูกบทบาทเพิ่มให้ครบ) =====
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code IN ('student.behavior','student.care')
    AND r.code IN ('director','deputy_director','head_academic','advisor','teacher','clerk');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='student.scholarship'
    AND r.code IN ('director','deputy_director','head_academic','clerk','finance_officer','advisor');

-- ===== ข้อมูลตัวอย่าง =====
INSERT INTO behavior_records (student_id, record_date, type, category, points, description, parent_notified)
  SELECT s.id, CURDATE()-INTERVAL 7 DAY, 'merit', 'ช่วยเหลืองานโรงเรียน', 5, 'ช่วยครูจัดกิจกรรมวันไหว้ครู', 0
  FROM students s WHERE s.deleted_at IS NULL ORDER BY s.id LIMIT 1;
INSERT INTO behavior_records (student_id, record_date, type, category, points, description, parent_notified)
  SELECT s.id, CURDATE()-INTERVAL 3 DAY, 'demerit', 'มาสาย', -5, 'มาโรงเรียนสายเกิน 15 นาที', 1
  FROM students s WHERE s.deleted_at IS NULL ORDER BY s.id LIMIT 1;

INSERT INTO home_visits (student_id, visit_date, summary, risk_level, guardian_name, guardian_relation,
    living_with, family_status, housing_type, family_income, travel_method, distance_km, needs_help)
  SELECT s.id, CURDATE()-INTERVAL 30 DAY, 'ครอบครัวให้ความร่วมมือดี นักเรียนช่วยงานบ้าน', 'normal',
    'นางสมศรี ใจดี', 'มารดา', 'บิดามารดา', 'together', 'own', 15000, 'รถโรงเรียน', 3.5, 0
  FROM students s WHERE s.deleted_at IS NULL ORDER BY s.id LIMIT 1;

INSERT INTO scholarships (student_id, name, scholarship_type, source, amount, granted_date, status, term)
  SELECT s.id, 'ทุนปัจจัยพื้นฐานนักเรียนยากจน', 'poor', 'สพฐ.', 3000, CURDATE()-INTERVAL 60 DAY, 'granted', 'ภาคเรียนที่ 1'
  FROM students s WHERE s.deleted_at IS NULL ORDER BY s.id LIMIT 1;

-- จบ 21
