-- ==================================================================
--  34_signature.sql — ลายเซ็นอิเล็กทรอนิกส์
--  ผู้ใช้ตั้งค่าลายเซ็นไว้ในโปรไฟล์ → แนบแทนลายมือชื่อบนใบปะหน้าสารบรรณ
--  นำเข้าหลัง 01-33 · รันซ้ำได้ปลอดภัย (idempotent)
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

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

-- ลายเซ็นประจำตัวผู้ใช้ (ตั้งค่าในโปรไฟล์)
CALL add_col_if_missing('users','signature_path',
  "signature_path VARCHAR(255) DEFAULT NULL COMMENT 'ไฟล์ลายเซ็น (uploads/...)' AFTER avatar_path");

-- ลายเซ็นที่แนบกับข้อความเกษียน/คำสั่งการ (snapshot ตอนลงนาม)
CALL add_col_if_missing('document_notes','signature_path',
  "signature_path VARCHAR(255) DEFAULT NULL COMMENT 'ลายเซ็นที่แนบตอนลงนาม' AFTER author_position");

DROP PROCEDURE IF EXISTS add_col_if_missing;

-- จบ 34
