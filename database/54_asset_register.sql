-- ==================================================================
--  54_asset_register.sql — ฟิลด์เพิ่มเติมสำหรับทะเบียนคุมทรัพย์สิน (แบบราชการ)
--  รองรับการพิมพ์ทะเบียนคุม + คำนวณค่าเสื่อมราคา/ปี (อายุใช้งาน)
--  นำเข้าหลัง 01-53 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

ALTER TABLE assets
  ADD COLUMN IF NOT EXISTS useful_life_years TINYINT UNSIGNED NULL AFTER acquired_price,  -- อายุการใช้งาน (ปี)
  ADD COLUMN IF NOT EXISTS spec           VARCHAR(255) NULL AFTER name,                    -- ลักษณะ/คุณสมบัติ
  ADD COLUMN IF NOT EXISTS model          VARCHAR(150) NULL AFTER spec,                    -- แบบ/รุ่น
  ADD COLUMN IF NOT EXISTS vendor         VARCHAR(255) NULL AFTER model,                   -- ผู้ขาย/ผู้รับจ้าง/ผู้บริจาค
  ADD COLUMN IF NOT EXISTS vendor_phone   VARCHAR(60)  NULL AFTER vendor,
  ADD COLUMN IF NOT EXISTS doc_no         VARCHAR(60)  NULL AFTER vendor_phone,            -- ที่เอกสาร
  ADD COLUMN IF NOT EXISTS fund_type      VARCHAR(20)  NULL AFTER doc_no,                  -- budget|non_budget|donation|other
  ADD COLUMN IF NOT EXISTS acquire_method VARCHAR(20)  NULL AFTER fund_type;               -- agree|inquiry|bid|special|donate

-- จบ 54
