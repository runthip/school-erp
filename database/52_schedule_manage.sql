-- ==================================================================
--  52_schedule_manage.sql — จำกัดสิทธิ์ "จัดตารางสอน" ให้เฉพาะแอดมิน + หัวหน้าฝ่ายวิชาการ
--  สิทธิ์ใหม่ schedule.manage = จัด/สร้าง/เผยแพร่ตาราง + ตั้งค่าเงื่อนไข
--  (การ "ดู" ตารางเรียน/ตารางสอน ยังใช้ academic.schedule เดิม ครูดูได้ตามปกติ)
--  นำเข้าหลัง 01-51 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

INSERT INTO permissions (code, name, module)
  SELECT 'schedule.manage', 'จัดตารางสอน (สร้าง/เผยแพร่/ตั้งค่า)', 'academic'
  WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='schedule.manage');

-- ให้เฉพาะแอดมิน (ผ่าน wildcard อยู่แล้ว) + หัวหน้าฝ่ายวิชาการ
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='schedule.manage' AND r.code IN ('super_admin','head_academic');

-- จบ 52
