-- ==================================================================
--  32_substitute_approve.sql — อนุมัติการจัดสอนแทน + แจ้งเตือน
--  หัวหน้าฝ่ายวิชาการ/แอดมินฝ่ายวิชาการ (academic.curriculum) อนุมัติ
--  แล้วแจ้งเตือนครูที่รับมอบหมายสอนแทน
--  นำเข้าหลัง 01-31 · รันซ้ำได้ปลอดภัย (idempotent)
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

CALL add_col_if_missing('substitute_teachings','status',
  "status ENUM('assigned','approved') NOT NULL DEFAULT 'assigned' COMMENT 'assigned=จัดแล้ว รออนุมัติ' AFTER note");
CALL add_col_if_missing('substitute_teachings','approved_by','approved_by BIGINT UNSIGNED DEFAULT NULL');
CALL add_col_if_missing('substitute_teachings','approved_at','approved_at DATETIME DEFAULT NULL');
CALL add_col_if_missing('substitute_teachings','notified_at','notified_at DATETIME DEFAULT NULL');

DROP PROCEDURE IF EXISTS add_col_if_missing;

-- จบ 32
