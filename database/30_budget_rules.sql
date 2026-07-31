-- ==================================================================
--  30_budget_rules.sql — กฎการทำงานฝ่ายงบประมาณ + ถังขยะ (soft delete)
--   · projects.approval_status : บังคับ "โครงการต้องอนุมัติก่อนเบิก"
--   · budget_memos.deleted_at  : ถังขยะ 30 วัน
--   · budget_memos: บังคับ workflow ผ่านหัวหน้า→การเงิน→ผอ. (มี step อยู่แล้ว)
--   นำเข้าหลัง 01-29 · รันซ้ำได้อย่างปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS add_col_r;
DELIMITER //
CREATE PROCEDURE add_col_r(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl VARCHAR(400))
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema='school_erp' AND table_name=tbl AND column_name=col)=0 THEN
    SET @s := CONCAT('ALTER TABLE ', tbl, ' ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END //
DELIMITER ;

-- โครงการ: สถานะอนุมัติ (แยกจาก lifecycle planned/ongoing/...)
CALL add_col_r('projects','approval_status',
  "approval_status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft' COMMENT 'สถานะอนุมัติโครงการ' AFTER status");
CALL add_col_r('projects','approved_by', "approved_by INT UNSIGNED DEFAULT NULL");
CALL add_col_r('projects','approved_at', "approved_at DATETIME DEFAULT NULL");
CALL add_col_r('projects','reject_reason', "reject_reason VARCHAR(255) DEFAULT NULL");

-- ถังขยะ งป.01 (soft delete 30 วัน)
CALL add_col_r('budget_memos','deleted_at', "deleted_at DATETIME DEFAULT NULL COMMENT 'ถังขยะ กู้คืนได้ 30 วัน'");
CALL add_col_r('budget_memos','deleted_by', "deleted_by INT UNSIGNED DEFAULT NULL");

DROP PROCEDURE IF EXISTS add_col_r;

-- โครงการเดิม (demo) ให้ถือว่าอนุมัติแล้ว เพื่อไม่ให้ระบบล็อกการเบิกของข้อมูลเก่า
UPDATE projects SET approval_status='approved', approved_at=NOW()
  WHERE approval_status='draft' AND (spent_amount > 0 OR status IN ('ongoing','completed'));

-- สิทธิ์อนุมัติโครงการ (ใช้ budget.memo_approve ที่มีอยู่ — ไม่เพิ่มใหม่)
-- จบ 30
