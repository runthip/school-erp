-- ==================================================================
--  16_pr_activity.sql — ผูกใบขอซื้อ/ขอจ้าง (PR) เข้ากับกิจกรรมย่อยของโครงการ
--  นำเข้าหลัง 01-15
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- เพิ่มคอลัมน์ activity_id ให้ PR (ถ้ายังไม่มี)
SET @exists := (SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema='school_erp' AND table_name='purchase_requests' AND column_name='activity_id');
SET @sql := IF(@exists=0,
  'ALTER TABLE purchase_requests ADD COLUMN activity_id BIGINT UNSIGNED DEFAULT NULL AFTER project_id,
     ADD KEY ix_pr_activity (activity_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- จบ 16
