-- ==================================================================
--  29_budget_unify.sql — รวม flow ฝ่ายงบประมาณ/พัสดุ ให้ งป.01 เป็นทางเดียว
--   · เพิ่มสถานะ 'paid' (จ่ายแล้ว) ให้ budget_memos
--   · เพิ่มคอลัมน์บันทึกการจ่าย (paid_at, paid_by, payment_note)
--   นำเข้าหลัง 01-28 · รันซ้ำได้อย่างปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

-- เพิ่ม 'paid' ใน enum status (ต่อจาก approved)
ALTER TABLE budget_memos
  MODIFY COLUMN status ENUM('draft','submitted','head_ok','budget_ok','supply_ok','deputy_ok','approved','paid','rejected')
  NOT NULL DEFAULT 'draft';

-- คอลัมน์บันทึกการจ่าย (idempotent)
DROP PROCEDURE IF EXISTS add_col_bm;
DELIMITER //
CREATE PROCEDURE add_col_bm(IN col VARCHAR(64), IN ddl VARCHAR(300))
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema='school_erp' AND table_name='budget_memos' AND column_name=col)=0 THEN
    SET @s := CONCAT('ALTER TABLE budget_memos ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //
DELIMITER ;
CALL add_col_bm('paid_at',      "paid_at DATETIME DEFAULT NULL");
CALL add_col_bm('paid_by',      "paid_by INT UNSIGNED DEFAULT NULL");
CALL add_col_bm('payment_note', "payment_note VARCHAR(255) DEFAULT NULL");
DROP PROCEDURE IF EXISTS add_col_bm;

-- สิทธิ์ดูภาพรวมฝ่ายงบ (hub) — ใช้ budget.memo ที่มีอยู่ ไม่เพิ่มใหม่
-- จบ 29

-- ผู้บริหารเข้าถึงงานพัสดุทั้งฝ่าย (ดู/พิจารณา)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code IN ('budget.purchase','budget.po')
    AND r.code IN ('director','deputy_director');
