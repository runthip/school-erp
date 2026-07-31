-- ==================================================================
--  07_scoring.sql  —  สัดส่วนคะแนน 70:30 + สิทธิ์ติดตามการกรอกคะแนน
--  นำเข้าหลังจาก 01-06
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- ---- ค่าเริ่มต้นสัดส่วนคะแนน (ระหว่างภาค : ปลายภาค = 70:30) ----
INSERT INTO system_settings (group_key, setting_key, setting_value, value_type) VALUES
 ('academic','grade_ratio_during','70','int'),
 ('academic','grade_ratio_final','30','int')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- ---- สิทธิ์ใหม่: ติดตามการกรอกคะแนน (แอดมิน/ฝ่ายวิชาการ) ----
INSERT INTO permissions (code, name, module) VALUES
 ('academic.monitor','ติดตามการกรอกคะแนน','academic')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ผูกสิทธิ์ให้ ผอ./รองผอ./หัวหน้าวิชาการ  (super_admin ผ่านทุกสิทธิ์อยู่แล้ว)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r, permissions p
  WHERE p.code='academic.monitor' AND r.code IN ('director','deputy_director','head_academic');

-- ---- ปรับ weight ขององค์ประกอบเดิม (TA1) ให้สอดคล้องบัคเก็ต ----
-- ระหว่างภาค (formative+midterm) รวม 70 / ปลายภาค (final) 30
UPDATE grade_components SET weight=NULL;  -- ใช้บัคเก็ตจาก component_type แทน weight รายช่อง

-- จบ 07
