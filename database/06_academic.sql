-- ==================================================================
--  06_academic.sql  —  งานวัดผล: องค์ประกอบคะแนน / คะแนน / ผลการเรียน / ตารางสอน
--  นำเข้าหลังจาก 01-05  (อ้างอิง teaching_assignments 1-6, students 1-12)
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)
SET FOREIGN_KEY_CHECKS = 0;

-- ---- องค์ประกอบคะแนน สำหรับ TA1 (คณิต ม.1/1) ----
INSERT INTO grade_components (id, teaching_assignment_id, name, component_type, max_score, weight, sort_order) VALUES
 (1,1,'เก็บคะแนนครั้งที่ 1','formative',20,20,1),
 (2,1,'สอบกลางภาค','midterm',30,30,2),
 (3,1,'สอบปลายภาค','final',30,30,3),
 (4,1,'จิตพิสัย/คุณลักษณะ','formative',20,20,4);

-- ---- คะแนนรายคน (นักเรียน 1,2,3 ในห้อง ม.1/1) ----
INSERT INTO scores (grade_component_id, student_id, score, recorded_by) VALUES
 (1,1,18,3),(2,1,26,3),(3,1,27,3),(4,1,19,3),
 (1,2,15,3),(2,2,22,3),(3,2,20,3),(4,2,17,3),
 (1,3,12,3),(2,3,18,3),(3,3,16,3),(4,3,15,3);

-- ---- ผลการเรียนสรุป (final_grades) หลายวิชา เพื่อคำนวณ GPA/Transcript ----
-- ห้อง ม.1/1 : นักเรียน 1,2,3 × TA1-4
INSERT INTO final_grades (student_id, teaching_assignment_id, total_score, grade, special_result, is_finalized, finalized_at) VALUES
 (1,1,90,'4','none',1,NOW()),(2,1,74,'3','none',1,NOW()),(3,1,61,'2','none',1,NOW()),
 (1,2,85,'4','none',1,NOW()),(2,2,78,'3.5','none',1,NOW()),(3,2,66,'2.5','none',1,NOW()),
 (1,3,80,'4','none',1,NOW()),(2,3,72,'3','none',1,NOW()),(3,3,58,'1.5','none',1,NOW()),
 (1,4,88,'4','none',1,NOW()),(2,4,69,'2.5','none',1,NOW()),(3,4,52,'1','none',1,NOW());
-- ห้อง ม.2/1 : นักเรียน 7,8,9 × TA5-6 (มีตัวอย่างติด 0)
INSERT INTO final_grades (student_id, teaching_assignment_id, total_score, grade, special_result, is_finalized, finalized_at) VALUES
 (7,5,76,'3.5','none',1,NOW()),(8,5,63,'2','none',1,NOW()),(9,5,48,'0','0',1,NOW()),
 (7,6,82,'4','none',1,NOW()),(8,6,70,'3','none',1,NOW()),(9,6,NULL,NULL,'r',0,NULL);

-- ---- ตารางสอน (class_schedules) ห้อง ม.1/1 ----
INSERT INTO class_schedules (teaching_assignment_id, day_of_week, period_no, start_time, end_time, room_id) VALUES
 (1,2,1,'08:30','09:20',1),  -- จันทร์ คาบ1 คณิต
 (2,2,2,'09:20','10:10',1),  -- จันทร์ คาบ2 ไทย
 (3,3,1,'08:30','09:20',1),  -- อังคาร คาบ1 วิทย์
 (4,3,2,'09:20','10:10',1),  -- อังคาร คาบ2 อังกฤษ
 (1,4,3,'10:30','11:20',1),  -- พุธ คาบ3 คณิต
 (2,5,1,'08:30','09:20',1);  -- พฤหัส คาบ1 ไทย

-- ---- เช็คชื่อเพิ่มเติมสำหรับ TA1 (วันนี้) ----
INSERT INTO attendances (student_id, teaching_assignment_id, classroom_id, attendance_date, period_no, status, recorded_by) VALUES
 (1,1,1,CURDATE(),1,'present',3),
 (2,1,1,CURDATE(),1,'present',3),
 (3,1,1,CURDATE(),1,'late',3);

SET FOREIGN_KEY_CHECKS = 1;
-- จบ 06
