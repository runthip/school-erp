-- ==================================================================
--  30_refund_memo_clearance.sql — บันทึกข้อความล้างหนี้เงินยืม + ขอคืนเงิน
--  ปรับฟอร์มคืนเงินให้ตรงแบบราชการ (ขอส่งเอกสารล้างหนี้ฯ)
--  * fund_type: ทดรองราชการ / เงินงบประมาณ
--  * operate_period: ระหว่างวันที่ (ช่วงดำเนินกิจกรรม)
--  นำเข้าหลัง 01-29 · รันซ้ำได้ปลอดภัย (idempotent)
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

CALL add_col_if_missing('advance_refund_memos','fund_type',
  "fund_type ENUM('petty','budget') NOT NULL DEFAULT 'budget' COMMENT 'ทดรองราชการ/เงินงบประมาณ' AFTER refund_method");
CALL add_col_if_missing('advance_refund_memos','operate_period',
  "operate_period VARCHAR(160) DEFAULT NULL COMMENT 'ระหว่างวันที่ (ช่วงดำเนินกิจกรรม)' AFTER detail");

DROP PROCEDURE IF EXISTS add_col_if_missing;

-- จบ 30
