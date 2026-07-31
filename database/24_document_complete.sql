-- ==================================================================
--  24_document_complete.sql — เติมงานสารบรรณให้ครบวงจร
--   · เลขทะเบียนส่ง (หนังสือส่ง/เวียน)
--   · รหัสตรวจสอบเอกสาร (QR verify) — เดิมมีฟิลด์แต่ไม่เคยถูกใช้
--   · หนังสือเวียน + ติดตามการรับทราบ (ใช้ document_recipients ที่มีอยู่)
--  นำเข้าหลัง 01-23
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

-- ===== เลขทะเบียนส่ง (สำหรับหนังสือส่ง/เวียน — คนละเล่มกับทะเบียนรับ) =====
CALL add_col_if_missing('documents','send_no',
  "send_no VARCHAR(40) DEFAULT NULL COMMENT 'เลขทะเบียนส่ง' AFTER receive_no");
CALL add_col_if_missing('documents','sent_at',
  "sent_at DATETIME DEFAULT NULL COMMENT 'วันเวลาที่ส่งออก'");

-- ===== ผู้รับหนังสือเวียน =====
CALL add_col_if_missing('document_recipients','recipient_personnel_id',
  "recipient_personnel_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'ผู้รับ (personnel)' AFTER recipient_id");
CALL add_col_if_missing('document_recipients','recipient_dept_id',
  "recipient_dept_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'ฝ่ายผู้รับ'");

DROP PROCEDURE IF EXISTS add_col_if_missing;

-- ===== รหัสตรวจสอบเอกสาร: เติมให้หนังสือที่มีอยู่ซึ่งยังไม่มีรหัส =====
UPDATE documents
   SET qr_verify_code = UPPER(CONCAT(
         'DOC', DATE_FORMAT(COALESCE(received_at, created_at, NOW()), '%y'), '-',
         LPAD(id, 5, '0'), '-', SUBSTRING(MD5(CONCAT(id, title, COALESCE(created_at,''))), 1, 6)))
 WHERE qr_verify_code IS NULL OR qr_verify_code='';

-- ดัชนีให้ค้นหารหัสตรวจสอบได้เร็ว (สร้างเฉพาะเมื่อยังไม่มี)
SET @has := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema='school_erp' AND table_name='documents' AND index_name='ix_doc_verify');
SET @s := IF(@has=0, 'CREATE INDEX ix_doc_verify ON documents (qr_verify_code)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ===== ข้อมูลตัวอย่าง: หนังสือเวียนแจ้งบุคลากร =====
INSERT INTO documents (school_id, doc_number, send_no, doc_type, title, from_org, to_org,
    doc_date, sent_at, urgency, status)
  SELECT s.id, 'ว 12/2569', '0001', 'circular',
    'แจ้งกำหนดการเปิดภาคเรียนและการเตรียมความพร้อม',
    'โรงเรียน', 'ครูและบุคลากรทุกท่าน',
    CURDATE()-INTERVAL 5 DAY, NOW()-INTERVAL 5 DAY, 'normal', 'in_process'
  FROM schools s
  WHERE NOT EXISTS (SELECT 1 FROM documents d WHERE d.doc_type='circular' AND d.send_no='0001')
  ORDER BY s.id LIMIT 1;

UPDATE documents
   SET qr_verify_code = UPPER(CONCAT('DOC', DATE_FORMAT(NOW(),'%y'), '-', LPAD(id,5,'0'), '-',
       SUBSTRING(MD5(CONCAT(id,title)),1,6)))
 WHERE qr_verify_code IS NULL OR qr_verify_code='';

-- ผู้รับหนังสือเวียน: บุคลากร 3 คนแรก
INSERT INTO document_recipients (document_id, recipient_personnel_id, action, status)
  SELECT d.id, p.id, 'acknowledge', 'pending'
  FROM documents d
  CROSS JOIN (SELECT id FROM personnel WHERE deleted_at IS NULL AND status='active' ORDER BY id LIMIT 3) p
  WHERE d.doc_type='circular' AND d.send_no='0001'
    AND NOT EXISTS (SELECT 1 FROM document_recipients r WHERE r.document_id=d.id);

-- ==================================================================
--  เติมเลขทะเบียนให้เอกสารเดิมที่ตกหล่น — รันเลขแยกรายปี
--  (ตรงกับที่ nextReceiveNo()/nextSendNo() ในโค้ดคาดหวัง)
-- ==================================================================

-- ---------- เลขทะเบียนรับ: หนังสือรับที่ลงรับแล้ว (มี received_date) แต่ไม่มีเลขรับ ----------
DROP TEMPORARY TABLE IF EXISTS _mx_recv;
CREATE TEMPORARY TABLE _mx_recv AS
  SELECT YEAR(COALESCE(received_date, doc_date, created_at)) y,
         COALESCE(MAX(CAST(receive_no AS UNSIGNED)),0) mx
    FROM documents
   WHERE doc_type='incoming' AND receive_no REGEXP '^[0-9]+$'
   GROUP BY y;

SET @yr := NULL; SET @n := 0;
UPDATE documents d
  JOIN (SELECT t.id,
          @n := IF(@yr <=> t.y, @n + 1, IFNULL((SELECT mx FROM _mx_recv WHERE _mx_recv.y = t.y), 0) + 1) AS seq,
          @yr := t.y AS yy
        FROM (SELECT id, YEAR(COALESCE(received_date, doc_date, created_at)) y, received_date
                FROM documents
               WHERE doc_type='incoming' AND (receive_no IS NULL OR receive_no='')
                 AND received_date IS NOT NULL
               ORDER BY y, received_date, id) t) x ON x.id = d.id
   SET d.receive_no  = LPAD(x.seq, 4, '0'),
       d.received_at = COALESCE(d.received_at, TIMESTAMP(d.received_date, '08:30:00'));
DROP TEMPORARY TABLE IF EXISTS _mx_recv;

-- ---------- เลขทะเบียนส่ง: หนังสือส่ง/เวียนที่ยังไม่มีเลขส่ง ----------
DROP TEMPORARY TABLE IF EXISTS _mx_send;
CREATE TEMPORARY TABLE _mx_send AS
  SELECT YEAR(COALESCE(sent_at, doc_date, created_at)) y,
         COALESCE(MAX(CAST(send_no AS UNSIGNED)),0) mx
    FROM documents
   WHERE doc_type IN ('outgoing','circular') AND send_no REGEXP '^[0-9]+$'
   GROUP BY y;

SET @yr2 := NULL; SET @n2 := 0;
UPDATE documents d
  JOIN (SELECT t.id,
          @n2 := IF(@yr2 <=> t.y, @n2 + 1, IFNULL((SELECT mx FROM _mx_send WHERE _mx_send.y = t.y), 0) + 1) AS seq,
          @yr2 := t.y AS yy
        FROM (SELECT id, YEAR(COALESCE(sent_at, doc_date, created_at)) y, doc_date
                FROM documents
               WHERE doc_type IN ('outgoing','circular') AND (send_no IS NULL OR send_no='')
               ORDER BY y, doc_date, id) t) x ON x.id = d.id
   SET d.send_no = LPAD(x.seq, 4, '0'),
       d.sent_at = COALESCE(d.sent_at, TIMESTAMP(COALESCE(d.doc_date, DATE(d.created_at)), '16:30:00'));
DROP TEMPORARY TABLE IF EXISTS _mx_send;

-- จบ 24
