-- =====================================================================
--  Seed Data (ข้อมูลตั้งต้น) - Roles, Permissions, School ตัวอย่าง
-- =====================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- ---- 15 บทบาทตามสเปก -----------------------------------------------
INSERT INTO roles (code, name, is_system) VALUES
 ('super_admin','Super Admin',1),
 ('director','ผู้อำนวยการ',1),
 ('deputy_director','รองผู้อำนวยการ',1),
 ('head_academic','หัวหน้าฝ่ายวิชาการ',0),
 ('head_budget','หัวหน้าฝ่ายงบประมาณ',0),
 ('head_hr','หัวหน้าฝ่ายบุคคล',0),
 ('head_general','หัวหน้าฝ่ายบริหารทั่วไป',0),
 ('finance_officer','เจ้าหน้าที่การเงิน',0),
 ('inventory_officer','เจ้าหน้าที่พัสดุ',0),
 ('clerk','เจ้าหน้าที่ธุรการ',0),
 ('teacher','ครู',0),
 ('advisor','ครูที่ปรึกษา',0),
 ('student','นักเรียน',0),
 ('guardian','ผู้ปกครอง',0),
 ('board','คณะกรรมการสถานศึกษา',0),
 ('auditor','ผู้ตรวจสอบ',0);

-- ---- ตัวอย่าง permissions -----------------------------------------
INSERT INTO permissions (code, name, module) VALUES
 ('student.view','ดูข้อมูลนักเรียน','student'),
 ('student.edit','แก้ไขข้อมูลนักเรียน','student'),
 ('grade.view','ดูผลการเรียน','academic'),
 ('grade.edit','บันทึกคะแนน','academic'),
 ('attendance.record','เช็คชื่อ','academic'),
 ('budget.view','ดูงบประมาณ','budget'),
 ('budget.approve','อนุมัติงบประมาณ','budget'),
 ('asset.manage','จัดการพัสดุ','inventory'),
 ('document.manage','จัดการสารบรรณ','general'),
 ('user.manage','จัดการผู้ใช้','system');

-- super_admin ได้ทุกสิทธิ์
INSERT INTO role_permissions (role_id, permission_id)
SELECT (SELECT id FROM roles WHERE code='super_admin'), id FROM permissions;

-- ---- โรงเรียนตัวอย่าง + ปีการศึกษา ---------------------------------
INSERT INTO schools (school_code, name_th, province, affiliation)
VALUES ('1000000001','โรงเรียนตัวอย่างวิทยา','กรุงเทพมหานคร','สพม.');

INSERT INTO academic_years (school_id, year_be, is_current) VALUES (1, 2568, 1);
INSERT INTO semesters (academic_year_id, term, is_current) VALUES (1, 1, 1), (1, 2, 0);

INSERT INTO org_departments (school_id, code, name) VALUES
 (1,'admin','ฝ่ายบริหาร'),
 (1,'academic','ฝ่ายวิชาการ'),
 (1,'budget','ฝ่ายงบประมาณ'),
 (1,'hr','ฝ่ายบุคคล'),
 (1,'general','ฝ่ายบริหารทั่วไป');

INSERT INTO grade_levels (school_id, code, name, level_order, stage) VALUES
 (1,'M1','มัธยมศึกษาปีที่ 1',7,'lower_secondary'),
 (1,'M2','มัธยมศึกษาปีที่ 2',8,'lower_secondary'),
 (1,'M3','มัธยมศึกษาปีที่ 3',9,'lower_secondary');

-- ผู้ใช้ super admin ตั้งต้น (รหัสผ่าน = Admin@123 เข้ารหัสด้วย bcrypt ในระบบจริง)
INSERT INTO users (username, full_name, password_hash, status)
VALUES ('admin','ผู้ดูแลระบบ','$2y$10$m84BR1MMAkEETyn.g9yS.OwklT4xcdOnRo0Qr2peeVU6/C/DIc4Am','active');
INSERT INTO user_roles (user_id, role_id)
VALUES (1, (SELECT id FROM roles WHERE code='super_admin'));
