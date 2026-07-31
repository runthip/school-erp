-- ==================================================================
--  40_student_headcount.sql — สิทธิ์ดูสรุปยอดนักเรียน (student.headcount)
--  ให้ admin + ผู้บริหารทุกคน (ผอ./รอง/หัวหน้าฝ่าย) + ครู เข้าถึงได้
--  นำเข้าหลัง 01-39 · รันซ้ำได้ปลอดภัย (idempotent)
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

INSERT INTO permissions (code, name, module)
  SELECT 'student.headcount', 'สรุปยอดนักเรียน', 'student'
  WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='student.headcount');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='student.headcount'
    AND r.code IN ('super_admin','director','deputy_director',
                   'head_academic','head_budget','head_hr','head_general',
                   'teacher','advisor','clerk');

-- จบ 40
