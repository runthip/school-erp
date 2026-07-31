-- ==================================================================
--  50_activity_memo.sql — บันทึกข้อความเสนอ ผอ. สำหรับรายงานผลกิจกรรม/การแข่งขัน
--  เพิ่มฟิลด์หัวบันทึกข้อความในตาราง activity_reports (ที่/วันที่/ส่วนราชการ/เรียน/
--  วัตถุประสงค์/ผู้รายงาน) เพื่อพิมพ์เป็นบันทึกข้อความราชการ
--  นำเข้าหลัง 01-49 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

ALTER TABLE activity_reports
  ADD COLUMN IF NOT EXISTS memo_no           VARCHAR(40)  NULL AFTER summary,
  ADD COLUMN IF NOT EXISTS memo_date          DATE         NULL AFTER memo_no,
  ADD COLUMN IF NOT EXISTS memo_agency        VARCHAR(300) NULL AFTER memo_date,
  ADD COLUMN IF NOT EXISTS memo_to            VARCHAR(300) NULL AFTER memo_agency,
  ADD COLUMN IF NOT EXISTS memo_purpose       VARCHAR(20)  NOT NULL DEFAULT 'acknowledge' AFTER memo_to,
  ADD COLUMN IF NOT EXISTS reporter_name      VARCHAR(200) NULL AFTER memo_purpose,
  ADD COLUMN IF NOT EXISTS reporter_position  VARCHAR(200) NULL AFTER reporter_name;

-- จบ 50
