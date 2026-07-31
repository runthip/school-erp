-- ==================================================================
--  23_document_flow.sql — งานสารบรรณ: ลงรับ · แนบไฟล์หลายไฟล์ · เกษียนหนังสือ
--  หนังสือเข้า → ประทับตรารับ → เกษียนเสนอ/มอบหมาย → ผอ.สั่งการ+ลงนาม
--  นำเข้าหลัง 01-22
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

-- ===== ข้อมูลการลงรับ (ตราปั๊มลงรับ) =====
CALL add_col_if_missing('documents','receive_no',
  "receive_no VARCHAR(40) DEFAULT NULL COMMENT 'เลขทะเบียนรับ' AFTER doc_number");
CALL add_col_if_missing('documents','received_at',
  "received_at DATETIME DEFAULT NULL COMMENT 'วันเวลาที่ลงรับ (ใช้บนตราปั๊ม)'");
CALL add_col_if_missing('documents','received_by',
  'received_by BIGINT UNSIGNED DEFAULT NULL');
CALL add_col_if_missing('documents','urgency',
  "urgency ENUM('normal','urgent','very_urgent','most_urgent') NOT NULL DEFAULT 'normal' COMMENT 'ชั้นความเร็ว'");
CALL add_col_if_missing('documents','secret_level',
  "secret_level ENUM('normal','confidential','secret','top_secret') NOT NULL DEFAULT 'normal' COMMENT 'ชั้นความลับ'");

-- ===== การมอบหมาย + คำสั่งการของผู้อำนวยการ =====
CALL add_col_if_missing('documents','assigned_to',
  "assigned_to BIGINT UNSIGNED DEFAULT NULL COMMENT 'มอบหมายให้ (personnel)'");
CALL add_col_if_missing('documents','assigned_dept',
  "assigned_dept BIGINT UNSIGNED DEFAULT NULL COMMENT 'มอบหมายให้ฝ่าย'");
CALL add_col_if_missing('documents','due_date','due_date DATE DEFAULT NULL');
CALL add_col_if_missing('documents','director_status',
  "director_status ENUM('pending','approved','rejected','noted') NOT NULL DEFAULT 'pending' COMMENT 'ผลการสั่งการของ ผอ.'");
CALL add_col_if_missing('documents','signed_by','signed_by BIGINT UNSIGNED DEFAULT NULL');
CALL add_col_if_missing('documents','signed_at','signed_at DATETIME DEFAULT NULL');

DROP PROCEDURE IF EXISTS add_col_if_missing;

-- ===== ไฟล์แนบหลายไฟล์ =====
CREATE TABLE IF NOT EXISTS document_attachments (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id   BIGINT UNSIGNED NOT NULL,
  file_path     VARCHAR(255)  NOT NULL,          -- path ใต้ storage/
  original_name VARCHAR(255)  NOT NULL,          -- ชื่อไฟล์ที่ผู้ใช้อัปโหลด
  mime_type     VARCHAR(120)  DEFAULT NULL,
  size_bytes    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  note          VARCHAR(255)  DEFAULT NULL,      -- คำอธิบายสิ่งที่ส่งมาด้วย
  uploaded_by   BIGINT UNSIGNED DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_att_doc (document_id),
  CONSTRAINT fk_att_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== เกษียนหนังสือ (ข้อความสีน้ำเงินบนหนังสือ) =====
--  kind = 'assign' : เจ้าหน้าที่/หัวหน้างาน ลงรายละเอียดการรับเข้าเพื่อเสนอและมอบหมาย
--  kind = 'order'  : ผู้อำนวยการ อนุมัติ/ชี้แจง/สั่งการ พร้อมลงลายมือชื่อ
CREATE TABLE IF NOT EXISTS document_notes (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id     BIGINT UNSIGNED NOT NULL,
  kind            ENUM('assign','order') NOT NULL DEFAULT 'assign',
  body            TEXT NOT NULL,                 -- ข้อความเกษียน (แสดงด้วยหมึกสีน้ำเงิน)
  decision        ENUM('none','approved','rejected','noted') NOT NULL DEFAULT 'none',
  author_id       BIGINT UNSIGNED DEFAULT NULL,  -- users
  author_name     VARCHAR(200) DEFAULT NULL,     -- เก็บชื่อ ณ เวลาเกษียน (กันข้อมูลเปลี่ยนภายหลัง)
  author_position VARCHAR(200) DEFAULT NULL,
  assigned_to     BIGINT UNSIGNED DEFAULT NULL,  -- มอบหมายให้ (personnel)
  due_date        DATE DEFAULT NULL,
  is_signed       TINYINT(1) NOT NULL DEFAULT 0, -- ลงลายมือชื่อแล้ว
  signed_at       DATETIME DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_note_doc (document_id, kind),
  CONSTRAINT fk_note_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== สิทธิ์: ผู้อำนวยการ/รอง ต้องสั่งการและลงนามได้ =====
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code IN ('document.manage','document.sign')
    AND r.code IN ('director','deputy_director');

-- ===== ข้อมูลตัวอย่าง: หนังสือเข้า 1 ฉบับ ลงรับแล้ว รอ ผอ. สั่งการ =====
INSERT INTO documents (school_id, doc_number, receive_no, doc_type, title, from_org, to_org,
    doc_date, received_date, received_at, urgency, status, director_status)
  SELECT s.id, 'ศธ 04123/ว 456', '0001', 'incoming',
    'ขอเชิญประชุมผู้บริหารสถานศึกษา ประจำเดือน',
    'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษา', 'โรงเรียน',
    CURDATE()-INTERVAL 3 DAY, CURDATE()-INTERVAL 2 DAY, NOW()-INTERVAL 2 DAY,
    'urgent', 'in_process', 'pending'
  FROM schools s
  WHERE NOT EXISTS (SELECT 1 FROM documents d WHERE d.receive_no='0001' AND d.doc_type='incoming')
  ORDER BY s.id LIMIT 1;

INSERT INTO document_notes (document_id, kind, body, author_name, author_position)
  SELECT d.id, 'assign',
    'เรียน ผู้อำนวยการ เพื่อโปรดพิจารณา หนังสือฉบับนี้เป็นการเชิญประชุมผู้บริหาร กำหนดประชุมวันที่ 25 ของเดือน เห็นควรมอบกลุ่มบริหารทั่วไปเตรียมข้อมูลประกอบการประชุม',
    'นางสาวสมหญิง ธุรการ', 'เจ้าหน้าที่ธุรการ'
  FROM documents d WHERE d.receive_no='0001' AND d.doc_type='incoming'
    AND NOT EXISTS (SELECT 1 FROM document_notes n WHERE n.document_id=d.id)
  LIMIT 1;

-- จบ 23
