-- ==================================================================
--  29_advance_form_8500.sql — สัญญายืมเงิน (แบบ 8500) ครบตามฟอร์มราชการ
--  * รายการค่าใช้จ่ายย่อย (cash_advance_items) พร้อมยอดเงิน
--  * ตำแหน่งผู้ยืม · ยืมเงินจาก · ส่งใช้ภายใน N วัน
--  นำเข้าหลัง 01-28 · รันซ้ำได้ปลอดภัย (idempotent)
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

-- ===== รายการค่าใช้จ่ายย่อยของสัญญายืม =====
CREATE TABLE IF NOT EXISTS cash_advance_items (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cash_advance_id BIGINT UNSIGNED NOT NULL,
  name            VARCHAR(255) NOT NULL,
  amount          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cai_advance (cash_advance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== ฟิลด์เพิ่มเติมตามแบบ 8500 =====
CALL add_col_if_missing('cash_advances','borrower_position',
  "borrower_position VARCHAR(150) DEFAULT NULL COMMENT 'ตำแหน่งผู้ยืม' AFTER borrower_id");
CALL add_col_if_missing('cash_advances','lend_from',
  "lend_from VARCHAR(200) DEFAULT NULL COMMENT 'ขอยืมเงินจาก (แหล่งเงิน/หน่วยงาน)'");
CALL add_col_if_missing('cash_advances','repay_days',
  "repay_days SMALLINT UNSIGNED DEFAULT 30 COMMENT 'ส่งใช้คืนภายใน (วัน)'");

DROP PROCEDURE IF EXISTS add_col_if_missing;

-- จบ 29
