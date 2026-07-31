-- ==================================================================
--  25_document_routing.sql — ส่งต่อ/มอบหมายหนังสือ (แท็กฝ่าย/เลือกครู)
--   · เพิ่มข้อมูลการส่งต่อใน document_recipients
--   · ผูกบัญชีบุคลากรเข้ากับทะเบียน personnel (จำเป็นสำหรับ "หนังสือถึงฉัน")
--  นำเข้าหลัง 01-24
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

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

-- ===== ข้อมูลการส่งต่อ =====
CALL add_col_if_missing('document_recipients','instruction',
  "instruction VARCHAR(500) DEFAULT NULL COMMENT 'ข้อความสั่งการ/มอบหมายที่ส่งไปกับหนังสือ'");
CALL add_col_if_missing('document_recipients','due_date',
  "due_date DATE DEFAULT NULL COMMENT 'กำหนดแล้วเสร็จของผู้รับรายนี้'");
CALL add_col_if_missing('document_recipients','forwarded_by',
  'forwarded_by BIGINT UNSIGNED DEFAULT NULL');
CALL add_col_if_missing('document_recipients','forwarded_at',
  'forwarded_at DATETIME DEFAULT NULL');
CALL add_col_if_missing('document_recipients','is_read',
  "is_read TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ผู้รับเปิดอ่านแล้ว'");
CALL add_col_if_missing('document_recipients','read_at',
  'read_at DATETIME DEFAULT NULL');

DROP PROCEDURE IF EXISTS add_col_if_missing;

SET @has := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema='school_erp' AND table_name='document_recipients'
               AND index_name='ix_dr_person');
SET @s := IF(@has=0,
  'CREATE INDEX ix_dr_person ON document_recipients (recipient_personnel_id, status)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- เติมวันเวลาส่งต่อให้ข้อมูลเดิม
UPDATE document_recipients SET forwarded_at=COALESCE(forwarded_at, created_at)
 WHERE forwarded_at IS NULL;

-- ===== ผูกบัญชีบุคลากรเข้ากับทะเบียน personnel =====
--  03_seed_demo สร้างบัญชีสาธิตไว้แบบ linked_type='none'
--  ทำให้ "หนังสือถึงฉัน" หาไม่เจอว่าผู้ใช้คือบุคลากรคนไหน จึงต้องผูกให้
UPDATE users u
   JOIN personnel p ON p.position LIKE '%ผู้อำนวยการ%' AND p.position NOT LIKE '%รอง%'
                   AND p.deleted_at IS NULL AND p.status='active'
    SET u.linked_type='personnel', u.linked_id=p.id
  WHERE u.username='director' AND (u.linked_type='none' OR u.linked_id IS NULL);

UPDATE users u
   JOIN personnel p ON p.position LIKE '%รองผู้อำนวยการ%' AND p.deleted_at IS NULL AND p.status='active'
    SET u.linked_type='personnel', u.linked_id=p.id
  WHERE u.username='deputy_director' AND (u.linked_type='none' OR u.linked_id IS NULL);

-- บัญชีสาธิตที่เหลือ: ผูกกับบุคลากรที่ยังไม่ถูกใช้ เรียงตาม id
DROP TEMPORARY TABLE IF EXISTS _link;
CREATE TEMPORARY TABLE _link (username VARCHAR(64), seq INT);
INSERT INTO _link VALUES ('teacher',1),('head_academic',2),('head_budget',3),('head_hr',4),
                         ('head_general',5),('clerk',6),('finance_officer',7),('advisor',8),
                         ('inventory_officer',9),('auditor',10);

SET @i := 0;
DROP TEMPORARY TABLE IF EXISTS _free;
CREATE TEMPORARY TABLE _free AS
  SELECT p.id, (@i := @i + 1) AS seq
    FROM personnel p
   WHERE p.deleted_at IS NULL AND p.status='active'
     AND p.id NOT IN (SELECT COALESCE(linked_id,0) FROM users WHERE linked_type='personnel')
   ORDER BY p.id;

UPDATE users u
   JOIN _link l ON l.username=u.username
   JOIN _free f ON f.seq=l.seq
    SET u.linked_type='personnel', u.linked_id=f.id
  WHERE u.linked_type='none' OR u.linked_id IS NULL;

DROP TEMPORARY TABLE IF EXISTS _link;
DROP TEMPORARY TABLE IF EXISTS _free;

-- ===== สิทธิ์: ทุกคนที่เป็นบุคลากรควรเปิดดูหนังสือที่ส่งถึงตนได้ =====
INSERT IGNORE INTO permissions (code, name)
  VALUES ('document.inbox', 'ดูหนังสือที่ส่งถึงตนเอง');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='document.inbox'
    AND r.code IN ('director','deputy_director','head_academic','head_budget','head_hr',
                   'head_general','clerk','teacher','advisor','finance_officer',
                   'inventory_officer','auditor');

-- ===== ตัวอย่าง: ส่งต่อหนังสือเข้าให้ฝ่ายดำเนินการ =====
UPDATE document_recipients r
   JOIN documents d ON d.id=r.document_id
    SET r.instruction='โปรดดำเนินการตามที่ได้รับมอบหมาย และรายงานผลกลับด้วย',
        r.action='acknowledge'
 WHERE d.doc_type='circular' AND r.instruction IS NULL;

-- จบ 25
