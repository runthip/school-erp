-- ==================================================================
--  reset_operational.sql — ล้าง "ข้อมูลใช้งาน" ให้พร้อมใช้งานจริง
--  ------------------------------------------------------------------
--  ⚠ สคริปต์นี้ลบข้อมูลถาวร — สำรองฐานข้อมูลก่อนรันเสมอ
--     mysqldump -u root -p school_erp > backup_$(date +%F).sql
--
--  เก็บไว้ (ค่าตั้งต้น/โครงสร้างองค์กร):
--    roles, permissions, role_permissions · grade_levels · subject_groups
--    org_departments · asset_categories · leave_types · budget_sources
--    academic_years · semesters · system_settings · schools
--    document_templates · schema_migrations · แอดมิน 1 บัญชี
--
--  ล้าง: นักเรียน บุคลากร ผู้ใช้อื่น ชั้นเรียน รายวิชา ตารางสอน คะแนน
--        เช็คชื่อ เอกสาร งบประมาณ พัสดุ ยานพาหนะ ห้องพยาบาล ร้านค้าสวัสดิการ
--        กิจกรรม PA SAR การแจ้งเตือน และ log ทั้งหมด
--  ใช้ซ้ำได้: รันกี่ครั้งก็ได้ผลเหมือนเดิม
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `activity_files`;
TRUNCATE TABLE `activity_participants`;
TRUNCATE TABLE `activity_reports`;
TRUNCATE TABLE `advance_refund_memos`;
TRUNCATE TABLE `announcements`;
TRUNCATE TABLE `approvals`;
TRUNCATE TABLE `approval_logs`;
TRUNCATE TABLE `approval_requests`;
TRUNCATE TABLE `assets`;
TRUNCATE TABLE `attendances`;
TRUNCATE TABLE `audit_logs`;
TRUNCATE TABLE `behavior_records`;
TRUNCATE TABLE `budgets`;
TRUNCATE TABLE `budget_ledger`;
TRUNCATE TABLE `budget_memos`;
TRUNCATE TABLE `budget_requests`;
TRUNCATE TABLE `buildings`;
TRUNCATE TABLE `calendar_events`;
TRUNCATE TABLE `cash_advances`;
TRUNCATE TABLE `cash_advance_items`;
TRUNCATE TABLE `classrooms`;
TRUNCATE TABLE `class_schedules`;
TRUNCATE TABLE `documents`;
TRUNCATE TABLE `document_attachments`;
TRUNCATE TABLE `document_notes`;
TRUNCATE TABLE `document_recipients`;
TRUNCATE TABLE `document_views`;
TRUNCATE TABLE `exams`;
TRUNCATE TABLE `exam_results`;
TRUNCATE TABLE `final_grades`;
TRUNCATE TABLE `grade_components`;
TRUNCATE TABLE `grade_fixes`;
TRUNCATE TABLE `guardians`;
TRUNCATE TABLE `health_records`;
TRUNCATE TABLE `home_visits`;
TRUNCATE TABLE `kg_indicators`;
TRUNCATE TABLE `kg_indicator_scores`;
TRUNCATE TABLE `kindergarten_assessments`;
TRUNCATE TABLE `kpis`;
TRUNCATE TABLE `leaves`;
TRUNCATE TABLE `lesson_plans`;
TRUNCATE TABLE `materials`;
TRUNCATE TABLE `material_transactions`;
TRUNCATE TABLE `medicines`;
TRUNCATE TABLE `medicine_movements`;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `nurse_visits`;
TRUNCATE TABLE `nurse_visit_medicines`;
TRUNCATE TABLE `password_resets`;
TRUNCATE TABLE `pa_evaluations`;
TRUNCATE TABLE `pa_topics`;
TRUNCATE TABLE `pa_topic_files`;
TRUNCATE TABLE `personnel`;
TRUNCATE TABLE `projects`;
TRUNCATE TABLE `project_activities`;
TRUNCATE TABLE `purchase_orders`;
TRUNCATE TABLE `purchase_order_items`;
TRUNCATE TABLE `purchase_requests`;
TRUNCATE TABLE `purchase_request_items`;
TRUNCATE TABLE `repair_requests`;
TRUNCATE TABLE `rooms`;
TRUNCATE TABLE `room_bookings`;
TRUNCATE TABLE `salaries`;
TRUNCATE TABLE `salary_records`;
TRUNCATE TABLE `sar_attachments`;
TRUNCATE TABLE `sar_kg`;
TRUNCATE TABLE `sar_kg_scores`;
TRUNCATE TABLE `sar_reports`;
TRUNCATE TABLE `scholarships`;
TRUNCATE TABLE `scores`;
TRUNCATE TABLE `sdq_assessments`;
TRUNCATE TABLE `staff_attendance`;
TRUNCATE TABLE `staff_attendances`;
TRUNCATE TABLE `students`;
TRUNCATE TABLE `student_enrollments`;
TRUNCATE TABLE `student_evaluations`;
TRUNCATE TABLE `student_guardians`;
TRUNCATE TABLE `subjects`;
TRUNCATE TABLE `substitute_teachings`;
TRUNCATE TABLE `teacher_unavailable`;
TRUNCATE TABLE `teaching_assignments`;
TRUNCATE TABLE `teaching_logs`;
TRUNCATE TABLE `timetable_publications`;
TRUNCATE TABLE `travel_claims`;
TRUNCATE TABLE `user_login_history`;
TRUNCATE TABLE `vehicles`;
TRUNCATE TABLE `vehicle_bookings`;
TRUNCATE TABLE `vendors`;
TRUNCATE TABLE `welfare_transactions`;

-- ---------- ผู้ใช้: เก็บเฉพาะแอดมินคนแรก (super_admin) ----------
SET @admin_id = (SELECT u.id FROM users u
    JOIN user_roles ur ON ur.user_id=u.id
    JOIN roles r ON r.id=ur.role_id
    WHERE r.code='super_admin' AND u.deleted_at IS NULL
    ORDER BY u.id LIMIT 1);

DELETE FROM user_roles WHERE user_id <> @admin_id;
DELETE FROM users      WHERE id      <> @admin_id;

-- ล้างการเชื่อมโยงกับบุคลากร/นักเรียนที่ถูกลบไปแล้ว + บังคับเปลี่ยนรหัสผ่านเมื่อเข้าใช้ครั้งแรก
UPDATE users
   SET linked_type='none', linked_id=NULL,
       must_change_password=1, failed_attempts=0, status='active',
       last_login_at=NULL
 WHERE id = @admin_id;

SET FOREIGN_KEY_CHECKS = 1;

-- หมายเหตุ: ไฟล์แนบใน storage/ ที่อ้างถึงข้อมูลที่ถูกลบ ให้ลบด้วยคำสั่ง
--   rm -f storage/documents/* storage/pa/* storage/activities/* storage/sar/* storage/exam_files/* storage/mail/*
-- (โลโก้/ตราครุฑใน public/uploads ให้คงไว้)

-- ---------- สรุปผล ----------
SELECT 'พร้อมใช้งาน' AS status,
       (SELECT COUNT(*) FROM users)     AS users_left,
       (SELECT COUNT(*) FROM students)  AS students,
       (SELECT COUNT(*) FROM personnel) AS personnel,
       (SELECT COUNT(*) FROM roles)     AS roles_kept,
       (SELECT COUNT(*) FROM permissions) AS perms_kept,
       (SELECT COUNT(*) FROM grade_levels) AS grade_levels_kept;
