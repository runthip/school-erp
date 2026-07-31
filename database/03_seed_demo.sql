-- ==================================================================
--  03_seed_demo.sql  —  สิทธิ์ครบทุกฝ่าย + ผูกบทบาท + บัญชี 16 บทบาท
--  รหัสผ่านบัญชีสาธิต: Demo@1234   (admin = Admin@123)
--  นำเข้าใน phpMyAdmin หลังจาก 01_schema.sql และ 02_seed.sql
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- สิทธิ์ทั้งหมด
INSERT INTO permissions (code, name, module) VALUES
  ('admin.dashboard', 'ดู Dashboard ผู้บริหาร', 'admin'),
  ('admin.approve', 'อนุมัติเอกสาร', 'admin'),
  ('admin.eoffice', 'E-Office/หนังสือราชการ', 'admin'),
  ('admin.projects', 'ติดตามโครงการ', 'admin'),
  ('admin.reports', 'รายงานภาพรวม', 'admin'),
  ('academic.curriculum', 'จัดการหลักสูตร', 'academic'),
  ('academic.schedule', 'ตารางเรียน/สอน', 'academic'),
  ('academic.grades', 'บันทึกคะแนน/ผลการเรียน', 'academic'),
  ('academic.attendance', 'เช็คชื่อนักเรียน', 'academic'),
  ('academic.measurement', 'งานวัดผล/ข้อสอบ', 'academic'),
  ('academic.pp', 'เอกสาร ปพ./Transcript', 'academic'),
  ('budget.manage', 'จัดการงบประมาณ', 'budget'),
  ('budget.purchase', 'ขอซื้อ/ขอจ้าง', 'budget'),
  ('budget.po', 'ใบสั่งซื้อ/สัญญา', 'budget'),
  ('budget.report', 'รายงานงบประมาณ', 'budget'),
  ('inventory.manage', 'จัดการครุภัณฑ์/พัสดุ', 'inventory'),
  ('hr.personnel', 'ข้อมูลบุคลากร', 'hr'),
  ('hr.leave', 'จัดการการลา', 'hr'),
  ('hr.attendance', 'ลงเวลาปฏิบัติงาน', 'hr'),
  ('hr.salary', 'เงินเดือน', 'hr'),
  ('hr.evaluation', 'PA/วิทยฐานะ/ประเมิน', 'hr'),
  ('general.repair', 'แจ้งซ่อม/อาคาร', 'general'),
  ('general.booking', 'จองห้อง', 'general'),
  ('general.vehicle', 'ยานพาหนะ', 'general'),
  ('general.health', 'งานอนามัย', 'general'),
  ('general.pr', 'ผู้มาติดต่อ/ประชาสัมพันธ์', 'general'),
  ('student.profile', 'ข้อมูลนักเรียน', 'student'),
  ('student.behavior', 'พฤติกรรม/SDQ', 'student'),
  ('student.care', 'ระบบดูแลช่วยเหลือ', 'student'),
  ('student.scholarship', 'ทุนการศึกษา', 'student'),
  ('portal.student', 'พอร์ทัลนักเรียน', 'portal'),
  ('portal.guardian', 'พอร์ทัลผู้ปกครอง', 'portal'),
  ('document.manage', 'สารบรรณ รับ-ส่ง', 'document'),
  ('document.sign', 'ลงนามดิจิทัล/เวียน', 'document'),
  ('user.manage', 'จัดการผู้ใช้', 'user'),
  ('role.manage', 'จัดการบทบาท/สิทธิ์', 'role'),
  ('audit.view', 'ดูประวัติการใช้งาน', 'audit'),
  ('system.settings', 'ตั้งค่าระบบ', 'system'),
  ('system.backup', 'สำรอง/กู้คืนข้อมูล', 'system')
ON DUPLICATE KEY UPDATE name=VALUES(name), module=VALUES(module);

-- ผูกบทบาท ↔ สิทธิ์
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='super_admin';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='super_admin'), id FROM permissions;
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='director';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='director'), id FROM permissions WHERE code IN ('admin.dashboard','admin.approve','admin.eoffice','admin.projects','admin.reports','academic.pp','budget.report','hr.evaluation','audit.view');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='deputy_director';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='deputy_director'), id FROM permissions WHERE code IN ('admin.dashboard','admin.approve','admin.projects','admin.reports','academic.schedule','budget.report');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='head_academic';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='head_academic'), id FROM permissions WHERE code IN ('academic.curriculum','academic.schedule','academic.grades','academic.attendance','academic.measurement','academic.pp','admin.approve');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='head_budget';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='head_budget'), id FROM permissions WHERE code IN ('budget.manage','budget.purchase','budget.po','budget.report','inventory.manage','admin.approve');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='head_hr';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='head_hr'), id FROM permissions WHERE code IN ('hr.personnel','hr.leave','hr.attendance','hr.salary','hr.evaluation','admin.approve');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='head_general';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='head_general'), id FROM permissions WHERE code IN ('general.repair','general.booking','general.vehicle','general.health','general.pr','document.manage','document.sign','admin.approve');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='finance_officer';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='finance_officer'), id FROM permissions WHERE code IN ('budget.purchase','budget.po','budget.report');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='inventory_officer';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='inventory_officer'), id FROM permissions WHERE code IN ('inventory.manage');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='clerk';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='clerk'), id FROM permissions WHERE code IN ('document.manage','document.sign','general.booking','admin.eoffice');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='teacher';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='teacher'), id FROM permissions WHERE code IN ('academic.schedule','academic.grades','academic.attendance','academic.measurement');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='advisor';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='advisor'), id FROM permissions WHERE code IN ('academic.attendance','student.behavior','student.care','student.profile');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='student';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='student'), id FROM permissions WHERE code IN ('portal.student');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='guardian';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='guardian'), id FROM permissions WHERE code IN ('portal.guardian');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='board';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='board'), id FROM permissions WHERE code IN ('admin.dashboard','admin.reports','budget.report');
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='auditor';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT (SELECT id FROM roles WHERE code='auditor'), id FROM permissions WHERE code IN ('audit.view','admin.reports','budget.report');

-- บัญชีผู้ใช้สาธิต (หนึ่งบัญชีต่อบทบาท)
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'director', 'ผู้อำนวยการ (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='director');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='director';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='director'), (SELECT id FROM roles WHERE code='director');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'deputy_director', 'รองผู้อำนวยการ (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='deputy_director');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='deputy_director';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='deputy_director'), (SELECT id FROM roles WHERE code='deputy_director');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'head_academic', 'หัวหน้าฝ่ายวิชาการ (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='head_academic');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='head_academic';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='head_academic'), (SELECT id FROM roles WHERE code='head_academic');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'head_budget', 'หัวหน้าฝ่ายงบประมาณ (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='head_budget');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='head_budget';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='head_budget'), (SELECT id FROM roles WHERE code='head_budget');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'head_hr', 'หัวหน้าฝ่ายบุคคล (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='head_hr');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='head_hr';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='head_hr'), (SELECT id FROM roles WHERE code='head_hr');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'head_general', 'หัวหน้าฝ่ายบริหารทั่วไป (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='head_general');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='head_general';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='head_general'), (SELECT id FROM roles WHERE code='head_general');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'finance_officer', 'เจ้าหน้าที่การเงิน (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='finance_officer');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='finance_officer';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='finance_officer'), (SELECT id FROM roles WHERE code='finance_officer');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'inventory_officer', 'เจ้าหน้าที่พัสดุ (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='inventory_officer');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='inventory_officer';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='inventory_officer'), (SELECT id FROM roles WHERE code='inventory_officer');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'clerk', 'เจ้าหน้าที่ธุรการ (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='clerk');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='clerk';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='clerk'), (SELECT id FROM roles WHERE code='clerk');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'teacher', 'ครู (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='teacher');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='teacher';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='teacher'), (SELECT id FROM roles WHERE code='teacher');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'advisor', 'ครูที่ปรึกษา (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='advisor');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='advisor';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='advisor'), (SELECT id FROM roles WHERE code='advisor');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'student', 'นักเรียน (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='student');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='student';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='student'), (SELECT id FROM roles WHERE code='student');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'guardian', 'ผู้ปกครอง (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='guardian');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='guardian';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='guardian'), (SELECT id FROM roles WHERE code='guardian');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'board', 'คณะกรรมการสถานศึกษา (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='board');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='board';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='board'), (SELECT id FROM roles WHERE code='board');
INSERT INTO users (username, full_name, password_hash, status, linked_type)
  SELECT 'auditor', 'ผู้ตรวจสอบ (สาธิต)', '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa', 'active', 'none'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='auditor');
DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='auditor';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='auditor'), (SELECT id FROM roles WHERE code='auditor');
