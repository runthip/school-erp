-- ==================================================================
--  44_asset_location.sql — ครุภัณฑ์/พัสดุ: จัดการที่งานพัสดุ · ฝ่ายบริหารทั่วไปดู+แก้สถานที่ตั้ง
--  ให้ head_general (ฝ่ายบริหารทั่วไป) มีสิทธิ์ general.asset (ดูทั้งหมด + แก้สถานที่ตั้ง)
--  นำเข้าหลัง 01-43 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='general.asset' AND r.code IN ('head_general','deputy_director','director');

-- จบ 44
