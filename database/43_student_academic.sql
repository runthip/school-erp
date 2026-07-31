-- ==================================================================
--  43_student_academic.sql — ยกข้อมูลนักเรียนไปฝ่ายวิชาการ
--  จัดการข้อมูลนักเรียน (student.profile) = แอดมิน + หัวหน้าฝ่ายวิชาการเท่านั้น
--  แยกสิทธิ์บันทึกสุขภาพ (student.health) ให้ครูใช้ได้ · สรุปยอด (student.headcount) ยังกว้างเหมือนเดิม
--  นำเข้าหลัง 01-42 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

-- student.profile → เฉพาะ super_admin (ผ่าน wildcard อยู่แล้ว) + head_academic
DELETE rp FROM role_permissions rp
  JOIN roles r ON r.id=rp.role_id
  JOIN permissions p ON p.id=rp.permission_id
  WHERE p.code='student.profile' AND r.code NOT IN ('super_admin','head_academic');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='student.profile' AND r.code IN ('super_admin','head_academic');

-- สิทธิ์ใหม่: บันทึกสุขภาพนักเรียน (แยกจากการจัดการข้อมูลนักเรียน)
INSERT INTO permissions (code, name, module)
  SELECT 'student.health', 'บันทึกสุขภาพนักเรียน (BMI)', 'student'
  WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='student.health');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='student.health'
    AND r.code IN ('super_admin','head_academic','head_hr','teacher','advisor','clerk');

-- จบ 43
