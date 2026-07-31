-- ==================================================================
--  20_settings.sql — ตั้งค่าระบบ + ติดตามโครงการ
--  seed ค่าตั้งต้น system_settings + เติมข้อมูลโรงเรียนให้ครบ
--  นำเข้าหลัง 01-19
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- ===== เติมข้อมูลโรงเรียนให้ครบ (หัวเอกสารทุกใบพิมพ์ใช้ข้อมูลนี้) =====
UPDATE schools SET
  name_en     = COALESCE(NULLIF(name_en,''), 'Tuayang Wittaya School'),
  address     = COALESCE(NULLIF(address,''), '123 หมู่ 4 ถนนตัวอย่าง'),
  district    = COALESCE(NULLIF(district,''), 'เขตตัวอย่าง'),
  postcode    = COALESCE(NULLIF(postcode,''), '10000'),
  phone       = COALESCE(NULLIF(phone,''), '02-000-0000'),
  email       = COALESCE(NULLIF(email,''), 'contact@school.ac.th')
WHERE id=1;

-- ===== ค่าตั้งต้นระบบ =====
INSERT IGNORE INTO system_settings (group_key, setting_key, setting_value, value_type) VALUES
  -- ทั่วไป
  ('general','site_name','School ERP Enterprise','string'),
  ('general','timezone','Asia/Bangkok','string'),
  ('general','date_format','d/m/Y','string'),
  ('general','records_per_page','25','int'),
  -- งานวิชาการ
  ('academic','attendance_min_percent','80','int'),
  ('academic','gpa_decimal','2','int'),
  -- งบประมาณ
  ('budget','approval_levels','3','int'),
  ('budget','advance_due_days','30','int'),
  ('budget','warn_budget_percent','80','int'),
  -- เอกสาร
  ('document','doc_prefix_request','บง','string'),
  ('document','doc_prefix_advance','ยม','string'),
  ('document','doc_prefix_booking','ยพ','string'),
  ('document','show_garuda','1','bool'),
  -- ความปลอดภัย
  ('security','session_timeout_min','120','int'),
  ('security','password_min_length','8','int'),
  ('security','audit_log_enabled','1','bool');

-- ล้างคีย์ซ้ำซ้อน: grade_ratio_collect ไม่มีโค้ดใดอ่าน (ของจริงคือ grade_ratio_during ที่ 07_scoring สร้าง)
DELETE FROM system_settings WHERE group_key='academic' AND setting_key='grade_ratio_collect';

-- ===== สิทธิ์: ให้ admin เข้าตั้งค่าระบบได้ด้วย =====
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code IN ('system.settings') AND r.code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code IN ('admin.projects') AND r.code IN ('head_budget','admin');

-- จบ 20
