-- ==================================================================
--  33_calendar_manage.sql — สิทธิ์จัดการปฏิทินโรงเรียน
--  เพิ่ม/แก้ไข/ลบ กิจกรรมปฏิทิน โดย แอดมิน · หัวหน้าฝ่าย · ธุรการ
--  นำเข้าหลัง 01-32 · รันซ้ำได้ปลอดภัย (idempotent)
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

INSERT INTO permissions (code, name, module)
  SELECT 'admin.calendar', 'จัดการปฏิทินโรงเรียน', 'admin'
  WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='admin.calendar');

-- ผูกสิทธิ์: แอดมิน + ผอ./รองผอ. + หัวหน้าฝ่ายทุกฝ่าย + ธุรการ
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='admin.calendar'
    AND r.code IN ('super_admin','director','deputy_director',
                   'head_academic','head_budget','head_hr','head_general','clerk');

-- จบ 33
