-- ==================================================================
--  51_activity_problems.sql — เพิ่มหัวข้อ ปัญหา/อุปสรรค และ ข้อเสนอแนะ
--  ในรายงานผลกิจกรรม/การแข่งขัน (activity_reports)
--  นำเข้าหลัง 01-50 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

ALTER TABLE activity_reports
  ADD COLUMN IF NOT EXISTS problems    TEXT NULL AFTER summary,
  ADD COLUMN IF NOT EXISTS suggestions TEXT NULL AFTER problems;

-- จบ 51
