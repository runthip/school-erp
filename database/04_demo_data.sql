-- ==================================================================
--  04_demo_data.sql  —  ข้อมูลตัวอย่างสมจริง (นักเรียน/บุคลากร/วิชา/งบ/พัสดุ/สารบรรณ)
--  นำเข้าใน phpMyAdmin หลังจาก 01, 02, 03  (อ้างอิง school_id=1, academic_year_id=1)
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)
SET FOREIGN_KEY_CHECKS = 0;

-- ---- กลุ่มสาระ ----
INSERT INTO subject_groups (id, school_id, code, name) VALUES
 (1,1,'THAI','ภาษาไทย'),(2,1,'MATH','คณิตศาสตร์'),(3,1,'SCI','วิทยาศาสตร์และเทคโนโลยี'),
 (4,1,'SOC','สังคมศึกษา ศาสนา และวัฒนธรรม'),(5,1,'ENG','ภาษาต่างประเทศ');

-- ---- อาคาร/ห้อง ----
INSERT INTO buildings (id, school_id, name, floors) VALUES (1,1,'อาคาร 1 (เฉลิมพระเกียรติ)',4);
INSERT INTO rooms (id, building_id, school_id, room_code, name, room_type, capacity, is_bookable) VALUES
 (1,1,1,'111','ห้อง 111','classroom',40,0),
 (2,1,1,'112','ห้อง 112','classroom',40,0),
 (3,1,1,'121','ห้อง 121','classroom',40,0),
 (4,1,1,'LAB1','ห้องปฏิบัติการวิทย์ 1','lab',30,1),
 (5,1,1,'MEET','ห้องประชุมราชพฤกษ์','meeting',60,1);

-- ---- บุคลากร ----
INSERT INTO personnel (id, school_id, employee_code, prefix, first_name, last_name, gender, position, academic_standing, department_id, subject_group_id, employment_type, status) VALUES
 (1,1,'T001','นาย','สมศักดิ์','เรืองปัญญา','male','ผู้อำนวยการโรงเรียน','ชำนาญการพิเศษ',1,NULL,'civil_servant','active'),
 (2,1,'T002','นาง','วิไลวรรณ','ทองสุข','female','รองผู้อำนวยการ','ชำนาญการพิเศษ',1,NULL,'civil_servant','active'),
 (3,1,'T003','นาย','ประสิทธิ์','ใจงาม','male','ครู','ชำนาญการ',2,2,'civil_servant','active'),
 (4,1,'T004','นางสาว','กัลยา','สว่างศรี','female','ครู','ชำนาญการ',2,1,'civil_servant','active'),
 (5,1,'T005','นาย','อนุชา','พงษ์พันธ์','male','ครู',NULL,2,3,'government_employee','active'),
 (6,1,'T006','นางสาว','ปิยะนุช','แก้วมณี','female','ครู','ชำนาญการ',2,5,'civil_servant','active'),
 (7,1,'T007','นาง','สุดารัตน์','คงเจริญ','female','เจ้าหน้าที่การเงิน',NULL,3,NULL,'government_employee','active'),
 (8,1,'T008','นาย','ธนวัฒน์','มั่นคง','male','เจ้าหน้าที่พัสดุ',NULL,3,NULL,'contract','active');

UPDATE org_departments SET head_personnel_id=1 WHERE code='admin';
UPDATE org_departments SET head_personnel_id=3 WHERE code='academic';

-- ---- ห้องเรียน (ปีการศึกษา 1) ----
INSERT INTO classrooms (id, school_id, academic_year_id, grade_level_id, section, name, room_id, homeroom_teacher_id) VALUES
 (1,1,1,1,1,'ม.1/1',1,3),
 (2,1,1,1,2,'ม.1/2',2,4),
 (3,1,1,2,1,'ม.2/1',3,5),
 (4,1,1,3,1,'ม.3/1',NULL,6);

-- ---- นักเรียน ----
INSERT INTO students (id, school_id, student_code, prefix, first_name, last_name, nickname, gender, birth_date, blood_group, religion, status) VALUES
 (1,1,'10001','เด็กชาย','ธนกร','ศรีสุข','กร','male','2013-05-12','O','พุทธ','studying'),
 (2,1,'10002','เด็กหญิง','ณัฐธิดา','บุญมี','แนน','female','2013-08-03','A','พุทธ','studying'),
 (3,1,'10003','เด็กชาย','ภูริพัฒน์','ทองดี','ภู','male','2013-02-20','B','พุทธ','studying'),
 (4,1,'10004','เด็กหญิง','ปาริชาติ','แสนสุข','ปาย','female','2013-11-15','AB','พุทธ','studying'),
 (5,1,'10005','เด็กชาย','กิตติพงศ์','วงศ์คำ','กิต','male','2013-07-07','O','พุทธ','studying'),
 (6,1,'10006','เด็กหญิง','จิราภรณ์','เพชรงาม','จิ','female','2013-09-19','A','อิสลาม','studying'),
 (7,1,'20001','เด็กชาย','ศุภกร','มีสุข','กร','male','2012-04-25','B','พุทธ','studying'),
 (8,1,'20002','เด็กหญิง','อารยา','คงทน','อา','female','2012-06-30','O','พุทธ','studying'),
 (9,1,'20003','เด็กชาย','ธีรภัทร','สุขสันต์','ธี','male','2012-12-01','A','คริสต์','studying'),
 (10,1,'30001','นาย','วรเมธ','ประเสริฐ','เมธ','male','2011-03-14','O','พุทธ','studying'),
 (11,1,'30002','นางสาว','สิริกร','ทวีทรัพย์','กร','female','2011-05-22','B','พุทธ','studying'),
 (12,1,'30003','นางสาว','พิมพ์ชนก','ดีเลิศ','พิม','female','2011-10-10','A','พุทธ','studying');

-- ---- ผู้ปกครอง ----
INSERT INTO guardians (id, prefix, first_name, last_name, phone, occupation) VALUES
 (1,'นาย','สมชาย','ศรีสุข','081-111-1111','รับราชการ'),
 (2,'นาง','สมหญิง','บุญมี','081-222-2222','ค้าขาย'),
 (3,'นาย','วิชัย','ทองดี','081-333-3333','เกษตรกร'),
 (4,'นาง','มาลี','แสนสุข','081-444-4444','พนักงานบริษัท');
INSERT INTO student_guardians (student_id, guardian_id, relationship, is_primary) VALUES
 (1,1,'father',1),(2,2,'mother',1),(3,3,'father',1),(4,4,'mother',1);

-- ---- จัดนักเรียนเข้าห้อง ----
INSERT INTO student_enrollments (student_id, classroom_id, academic_year_id, roll_number) VALUES
 (1,1,1,1),(2,1,1,2),(3,1,1,3),(4,2,1,1),(5,2,1,2),(6,2,1,3),
 (7,3,1,1),(8,3,1,2),(9,3,1,3),(10,4,1,1),(11,4,1,2),(12,4,1,3);

-- ---- รายวิชา ----
INSERT INTO subjects (id, school_id, subject_code, name_th, subject_group_id, credit, hours_per_week, subject_type) VALUES
 (1,1,'ท21101','ภาษาไทย 1',1,1.5,3,'core'),
 (2,1,'ค21101','คณิตศาสตร์ 1',2,1.5,3,'core'),
 (3,1,'ว21101','วิทยาศาสตร์ 1',3,1.5,3,'core'),
 (4,1,'ส21101','สังคมศึกษา 1',4,1.5,3,'core'),
 (5,1,'อ21101','ภาษาอังกฤษ 1',5,1.5,3,'core'),
 (6,1,'ว21102','วิทยาการคำนวณ 1',3,1.0,2,'additional');

-- ---- มอบหมายสอน (ภาคเรียน 1) ----
INSERT INTO teaching_assignments (id, semester_id, subject_id, classroom_id, teacher_id, is_primary) VALUES
 (1,1,2,1,3,1),(2,1,1,1,4,1),(3,1,3,1,5,1),(4,1,5,1,6,1),
 (5,1,2,3,3,1),(6,1,3,3,5,1);

-- ---- งบประมาณ / โครงการ ----
INSERT INTO budget_sources (id, school_id, code, name) VALUES
 (1,1,'SUBSIDY','เงินอุดหนุนรายหัว'),(2,1,'INCOME','รายได้สถานศึกษา');
INSERT INTO budgets (id, budget_source_id, academic_year_id, name, allocated_amount, used_amount) VALUES
 (1,1,1,'งบดำเนินงาน ปีการศึกษา 2568',1500000.00,420000.00),
 (2,1,1,'งบพัฒนาวิชาการ 2568',600000.00,180000.00),
 (3,2,1,'งบกิจกรรมพัฒนาผู้เรียน 2568',300000.00,95000.00);
INSERT INTO projects (id, school_id, budget_id, department_id, code, name, responsible_id, budget_amount, start_date, end_date, status, progress_percent) VALUES
 (1,1,2,2,'AC-01','โครงการยกระดับผลสัมฤทธิ์ทางการเรียน',3,250000,'2025-05-01','2025-09-30','ongoing',65),
 (2,1,3,2,'AC-02','โครงการค่ายวิทยาศาสตร์',5,80000,'2025-07-01','2025-07-31','completed',100),
 (3,1,1,5,'GN-01','โครงการปรับปรุงอาคารเรียน',2,400000,'2025-06-01','2025-12-31','ongoing',30),
 (4,1,3,2,'AC-03','โครงการส่งเสริมการอ่าน',4,45000,'2025-08-01','2026-02-28','planned',0);

-- ---- ครุภัณฑ์ / วัสดุ ----
INSERT INTO asset_categories (id, school_id, code, name, depreciation_rate) VALUES
 (1,1,'COMP','คอมพิวเตอร์และอุปกรณ์',20.00),
 (2,1,'FURN','ครุภัณฑ์สำนักงาน',10.00),
 (3,1,'EDU','ครุภัณฑ์การศึกษา',15.00);
INSERT INTO assets (id, school_id, asset_code, barcode, qr_code, category_id, name, acquired_date, acquired_price, budget_source_id, location_room_id, responsible_id, condition_status) VALUES
 (1,1,'7440-001-0001','8850001','QR0001',1,'เครื่องคอมพิวเตอร์ Lenovo','2024-06-15',18500,1,4,8,'normal'),
 (2,1,'7440-001-0002','8850002','QR0002',1,'เครื่องคอมพิวเตอร์ Lenovo','2024-06-15',18500,1,4,8,'normal'),
 (3,1,'7440-001-0003','8850003','QR0003',1,'โปรเจกเตอร์ Epson','2024-07-01',22000,1,1,3,'normal'),
 (4,1,'7110-001-0001','8850004','QR0004',2,'โต๊ะทำงานพร้อมเก้าอี้','2023-05-10',4500,2,5,7,'normal'),
 (5,1,'7110-001-0002','8850005','QR0005',2,'ตู้เอกสารเหล็ก 4 ลิ้นชัก','2023-05-10',3200,2,5,7,'normal'),
 (6,1,'6730-001-0001','8850006','QR0006',3,'กล้องจุลทรรศน์','2024-08-20',12000,2,4,5,'normal'),
 (7,1,'7440-001-0004','8850007','QR0007',1,'เครื่องพิมพ์เลเซอร์ HP','2024-09-05',6800,1,5,7,'repair'),
 (8,1,'6730-001-0002','8850008','QR0008',3,'ชุดทดลองไฟฟ้า','2022-06-01',8500,2,4,5,'normal');
INSERT INTO materials (id, school_id, code, name, unit, stock_qty, min_qty) VALUES
 (1,1,'M001','กระดาษ A4 (รีม)','รีม',120,30),
 (2,1,'M002','หมึกพิมพ์ HP 12A','กล่อง',8,5),
 (3,1,'M003','ปากกาไวท์บอร์ด','ด้าม',45,20),
 (4,1,'M004','สมุดบันทึก','เล่ม',200,50),
 (5,1,'M005','แฟ้มเอกสาร','แฟ้ม',15,25);

-- ---- สารบรรณ ----
INSERT INTO documents (id, school_id, doc_number, doc_type, title, from_org, doc_date, received_date, status, is_signed) VALUES
 (1,1,'ศธ 04001/1234','incoming','ขอเชิญประชุมผู้บริหารสถานศึกษา','สำนักงานเขตพื้นที่การศึกษา','2025-06-01','2025-06-02','completed',0),
 (2,1,'ศธ 04001/1250','incoming','การจัดสรรงบประมาณประจำปี 2568','สพฐ.','2025-06-05','2025-06-06','in_process',0),
 (3,1,'รร.ตย 001/2568','outgoing','รายงานผลการดำเนินงานประจำเดือน','โรงเรียนตัวอย่างวิทยา','2025-06-10',NULL,'completed',1),
 (4,1,'รร.ตย 002/2568','outgoing','ขออนุมัติจัดซื้อครุภัณฑ์คอมพิวเตอร์','โรงเรียนตัวอย่างวิทยา','2025-06-12',NULL,'in_process',1),
 (5,1,'ศธ 04001/1300','circular','แนวปฏิบัติการรับนักเรียน ปีการศึกษา 2568','สพฐ.','2025-06-15','2025-06-16','registered',0),
 (6,1,'รร.ตย 003/2568','internal','บันทึกข้อความขออนุญาตใช้ห้องประชุม','กลุ่มบริหารวิชาการ','2025-06-18',NULL,'completed',0);

-- ---- การลา ----
INSERT INTO leave_types (id, code, name, max_days_year) VALUES
 (1,'sick','ลาป่วย',30),(2,'personal','ลากิจส่วนตัว',10),(3,'vacation','ลาพักผ่อน',10);
INSERT INTO leaves (id, personnel_id, leave_type_id, start_date, end_date, days, reason, status, approver_id) VALUES
 (1,4,1,'2025-06-10','2025-06-11',2,'เป็นไข้หวัด','approved',1),
 (2,5,2,'2025-06-20','2025-06-20',1,'ธุระครอบครัว','approved',1),
 (3,6,1,'2025-06-25','2025-06-25',1,'พบแพทย์','pending',NULL);

-- ---- เช็คชื่อ / พฤติกรรม / สุขภาพ (ตัวอย่าง) ----
INSERT INTO attendances (student_id, classroom_id, attendance_date, status, recorded_by) VALUES
 (1,1,'2025-06-16','present',3),(2,1,'2025-06-16','present',3),(3,1,'2025-06-16','late',3),
 (4,2,'2025-06-16','present',4),(5,2,'2025-06-16','absent',4),(6,2,'2025-06-16','leave',4);
INSERT INTO behavior_records (student_id, record_date, type, points, description, recorded_by) VALUES
 (3,'2025-06-16','demerit',-5,'มาสายเกิน 15 นาที',3),
 (1,'2025-06-14','merit',10,'ช่วยเหลืองานห้องเรียนดีเด่น',3);
INSERT INTO health_records (student_id, record_date, height_cm, weight_kg, bmi) VALUES
 (1,'2025-05-20',158.0,48.5,19.4),(2,'2025-05-20',152.0,42.0,18.2),(3,'2025-05-20',160.0,55.0,21.5);

SET FOREIGN_KEY_CHECKS = 1;
-- จบข้อมูลตัวอย่าง
