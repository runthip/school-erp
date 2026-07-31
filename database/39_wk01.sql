-- ==================================================================
--  39_wk01.sql — รองรับผลการเรียน มผ (ไม่ผ่าน) + แบบ วก.01 (ขออนุมัติผล 0/ร/มผ)
--  เพิ่ม 'mp' ใน enum special_result · นำเข้าหลัง 01-38 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

ALTER TABLE final_grades
  MODIFY special_result ENUM('none','0','r','ms','mp') NOT NULL DEFAULT 'none';

-- จบ 39
