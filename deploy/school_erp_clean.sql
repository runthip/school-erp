-- ==================================================================
--  school_erp_clean.sql — ชุดติดตั้งใหม่ (1 ฐานข้อมูล = 1 โรงเรียน)
--  มี: โครงสร้างตารางทั้งหมด + ค่าตั้งต้น
--      (บทบาท/สิทธิ์/ระดับชั้น/กลุ่มสาระ/ฝ่ายงาน/หมวดครุภัณฑ์/ประเภทการลา/
--       แหล่งงบประมาณ/แบบฟอร์มเอกสาร/ประวัติการติดตั้ง)
--  ไม่มี: ข้อมูลโรงเรียน ผู้ใช้ นักเรียน บุคลากร หรือข้อมูลใช้งานใด ๆ
--  วิธีใช้: mysql -u USER -p DBNAME < school_erp_clean.sql
--          จากนั้นรัน deploy/new_org.sh เพื่อตั้งค่าโรงเรียน+แอดมิน
--  สร้างเมื่อ: 2026-07-28 20:33
-- ==================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_years` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `year_be` smallint(5) unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ay` (`school_id`,`year_be`),
  CONSTRAINT `fk_ay_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(20) NOT NULL DEFAULT 'photo',
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `size_bytes` int(11) NOT NULL DEFAULT 0,
  `caption` varchar(300) DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_files_report` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `grade_level` varchar(60) DEFAULT NULL,
  `event_type` varchar(200) DEFAULT NULL,
  `award` varchar(200) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_activity_participants_report` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(400) NOT NULL,
  `category` varchar(30) NOT NULL DEFAULT 'academic',
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `location` varchar(300) DEFAULT NULL,
  `organizer` varchar(300) DEFAULT NULL,
  `coaches` varchar(500) DEFAULT NULL,
  `result_summary` varchar(500) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `problems` text DEFAULT NULL,
  `suggestions` text DEFAULT NULL,
  `memo_no` varchar(40) DEFAULT NULL,
  `memo_date` date DEFAULT NULL,
  `memo_agency` varchar(300) DEFAULT NULL,
  `memo_to` varchar(300) DEFAULT NULL,
  `memo_purpose` varchar(20) NOT NULL DEFAULT 'acknowledge',
  `reporter_name` varchar(200) DEFAULT NULL,
  `reporter_position` varchar(200) DEFAULT NULL,
  `year_be` smallint(6) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_reports_cat` (`category`,`date_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `advance_refund_memos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cash_advance_id` bigint(20) unsigned NOT NULL,
  `memo_no` varchar(40) DEFAULT NULL,
  `memo_date` date DEFAULT NULL,
  `borrowed_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `used_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `refund_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `refund_method` enum('cash','transfer') NOT NULL DEFAULT 'cash',
  `fund_type` enum('petty','budget') NOT NULL DEFAULT 'budget' COMMENT 'ทดรองราชการ/เงินงบประมาณ',
  `detail` text DEFAULT NULL,
  `operate_period` varchar(160) DEFAULT NULL COMMENT 'ระหว่างวันที่ (ช่วงดำเนินกิจกรรม)',
  `receiver_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ผู้รับเงินคืน (เจ้าหน้าที่การเงิน)',
  `status` enum('draft','confirmed') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_refund_advance` (`cash_advance_id`),
  KEY `idx_refund_advance` (`cash_advance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` mediumtext DEFAULT NULL,
  `audience` enum('all','teachers','students','parents') NOT NULL DEFAULT 'all',
  `published_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_ann_audience` (`audience`),
  KEY `fk_ann_school` (`school_id`),
  KEY `fk_ann_user` (`created_by`),
  CONSTRAINT `fk_ann_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ann_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ref_type` enum('budget_request','cash_advance') NOT NULL,
  `ref_id` bigint(20) unsigned NOT NULL,
  `level` tinyint(3) unsigned NOT NULL,
  `level_name` varchar(80) NOT NULL,
  `approver_id` bigint(20) unsigned DEFAULT NULL,
  `action` enum('approved','rejected') NOT NULL,
  `comment` varchar(300) DEFAULT NULL,
  `acted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_al_ref` (`ref_type`,`ref_id`),
  KEY `fk_al_user` (`approver_id`),
  CONSTRAINT `fk_al_user` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `request_no` varchar(40) DEFAULT NULL,
  `requester_id` bigint(20) unsigned NOT NULL,
  `request_type` enum('leave','travel','purchase','general') NOT NULL DEFAULT 'general',
  `title` varchar(255) NOT NULL,
  `detail` text DEFAULT NULL,
  `amount` decimal(14,2) DEFAULT NULL,
  `approver_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `decision_note` varchar(500) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_ar_requester` (`requester_id`),
  KEY `ix_ar_approver` (`approver_id`),
  KEY `ix_ar_status` (`status`),
  KEY `fk_ar_school` (`school_id`),
  CONSTRAINT `fk_ar_app` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_req` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ar_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approvals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `step_no` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `approver_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `comment` varchar(500) DEFAULT NULL,
  `acted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_approval_entity` (`entity_type`,`entity_id`),
  KEY `ix_approval_approver` (`approver_id`),
  CONSTRAINT `fk_approval_user` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(200) NOT NULL,
  `depreciation_rate` decimal(5,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_cat` (`school_id`,`code`),
  CONSTRAINT `fk_ac_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `asset_code` varchar(60) NOT NULL,
  `barcode` varchar(80) DEFAULT NULL,
  `qr_code` varchar(120) DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `spec` varchar(255) DEFAULT NULL,
  `model` varchar(150) DEFAULT NULL,
  `vendor` varchar(255) DEFAULT NULL,
  `vendor_phone` varchar(60) DEFAULT NULL,
  `doc_no` varchar(60) DEFAULT NULL,
  `fund_type` varchar(20) DEFAULT NULL,
  `acquire_method` varchar(20) DEFAULT NULL,
  `acquired_date` date DEFAULT NULL,
  `acquired_price` decimal(14,2) DEFAULT NULL,
  `useful_life_years` tinyint(3) unsigned DEFAULT NULL,
  `budget_source_id` bigint(20) unsigned DEFAULT NULL,
  `location_room_id` bigint(20) unsigned DEFAULT NULL,
  `responsible_id` bigint(20) unsigned DEFAULT NULL,
  `condition_status` enum('normal','repair','damaged','disposed') NOT NULL DEFAULT 'normal',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_code` (`school_id`,`asset_code`),
  KEY `ix_asset_category` (`category_id`),
  KEY `ix_asset_barcode` (`barcode`),
  KEY `fk_asset_source` (`budget_source_id`),
  KEY `fk_asset_room` (`location_room_id`),
  KEY `fk_asset_resp` (`responsible_id`),
  CONSTRAINT `fk_asset_category` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asset_resp` FOREIGN KEY (`responsible_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asset_room` FOREIGN KEY (`location_room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asset_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asset_source` FOREIGN KEY (`budget_source_id`) REFERENCES `budget_sources` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `teaching_assignment_id` bigint(20) unsigned DEFAULT NULL,
  `classroom_id` bigint(20) unsigned DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `period_no` tinyint(3) unsigned DEFAULT NULL,
  `status` enum('present','absent','late','leave','activity') NOT NULL DEFAULT 'present',
  `note` varchar(255) DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `attendance_type` enum('subject','flag') NOT NULL DEFAULT 'subject' COMMENT 'subject=รายวิชา, flag=หน้าเสาธง/เข้าโรงเรียน',
  `arrived_at` time DEFAULT NULL COMMENT 'เวลามาจริง (ถ้าบันทึก)',
  PRIMARY KEY (`id`),
  KEY `ix_att_student_date` (`student_id`,`attendance_date`),
  KEY `ix_att_ta` (`teaching_assignment_id`),
  KEY `ix_att_classroom` (`classroom_id`),
  KEY `ix_att_type_date` (`attendance_type`,`attendance_date`,`classroom_id`),
  CONSTRAINT `fk_att_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_att_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_ta` FOREIGN KEY (`teaching_assignment_id`) REFERENCES `teaching_assignments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_audit_user` (`user_id`),
  KEY `ix_audit_entity` (`entity_type`,`entity_id`),
  KEY `ix_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `behavior_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `academic_year_id` bigint(20) unsigned DEFAULT NULL,
  `record_date` date NOT NULL,
  `type` enum('merit','demerit') NOT NULL DEFAULT 'demerit',
  `category` varchar(60) DEFAULT NULL COMMENT 'หมวดพฤติกรรม',
  `points` smallint(6) NOT NULL DEFAULT 0,
  `description` varchar(500) DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `parent_notified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'แจ้งผู้ปกครองแล้ว',
  `action_taken` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_behavior_student` (`student_id`,`record_date`),
  CONSTRAINT `fk_behavior_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `txn_date` datetime NOT NULL DEFAULT current_timestamp(),
  `source_type` enum('request','advance','po','manual') NOT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `source_no` varchar(40) DEFAULT NULL,
  `direction` enum('deduct','refund') NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `budget_id` bigint(20) unsigned DEFAULT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `activity_id` bigint(20) unsigned DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_bl_source` (`source_type`,`source_id`),
  KEY `ix_bl_project` (`project_id`),
  KEY `ix_bl_date` (`txn_date`),
  KEY `fk_bl_budget` (`budget_id`),
  KEY `fk_bl_activity` (`activity_id`),
  CONSTRAINT `fk_bl_activity` FOREIGN KEY (`activity_id`) REFERENCES `project_activities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bl_budget` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bl_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_memos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL DEFAULT 1,
  `memo_no` varchar(40) DEFAULT NULL COMMENT 'เลขที่ งป.01 (ต่อปีงบ)',
  `memo_date` date DEFAULT NULL,
  `budget_year` smallint(6) NOT NULL COMMENT 'ปีงบประมาณ พ.ศ.',
  `department` varchar(160) DEFAULT NULL COMMENT 'ฝ่าย/กลุ่ม/สาระฯ',
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `activity_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อกิจกรรม',
  `purpose` text DEFAULT NULL COMMENT 'เพื่อ...',
  `operate_date` varchar(160) DEFAULT NULL COMMENT 'ดำเนินการในวันที่',
  `project_id` int(10) unsigned DEFAULT NULL,
  `activity_id` int(10) unsigned DEFAULT NULL,
  `budget_id` int(10) unsigned DEFAULT NULL,
  `budget_total` decimal(14,2) DEFAULT 0.00 COMMENT 'งบประมาณประจำปีของกิจกรรม',
  `already_spent` decimal(14,2) DEFAULT 0.00 COMMENT 'ขอเบิกแล้ว',
  `request_amount` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'ขออนุมัติครั้งนี้',
  `remaining` decimal(14,2) DEFAULT 0.00 COMMENT 'คงเหลือ (คำนวณ)',
  `work_group` varchar(40) DEFAULT NULL COMMENT 'บริหารวิชาการ/บุคลากร/งบประมาณ/ทั่วไป/ส่วนกลาง',
  `subject_group_id` bigint(20) unsigned DEFAULT NULL,
  `fund_source` varchar(40) DEFAULT NULL COMMENT 'อุดหนุน/อาหารกลางวัน/รายได้สถานศึกษา/รายจ่ายบุคลากร',
  `committee` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'กรรมการจัดซื้อจัดจ้าง' CHECK (json_valid(`committee`)),
  `inspectors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ผู้ตรวจรับพัสดุ' CHECK (json_valid(`inspectors`)),
  `responsible_id` int(10) unsigned DEFAULT NULL COMMENT 'ผู้รับผิดชอบโครงการ',
  `status` enum('draft','submitted','head_ok','budget_ok','supply_ok','deputy_ok','approved','paid','rejected') NOT NULL DEFAULT 'draft',
  `head_comment` varchar(255) DEFAULT NULL,
  `head_by` int(10) unsigned DEFAULT NULL,
  `budget_correct` tinyint(1) DEFAULT NULL COMMENT 'หัวหน้าแผน: ถูกต้อง/ไม่',
  `budget_by` int(10) unsigned DEFAULT NULL,
  `supply_approve` tinyint(1) DEFAULT NULL COMMENT 'พัสดุ: เห็นควร/ไม่',
  `supply_by` int(10) unsigned DEFAULT NULL,
  `deputy_approve` tinyint(1) DEFAULT NULL COMMENT 'รองผอ: เห็นควร/ไม่',
  `deputy_by` int(10) unsigned DEFAULT NULL,
  `director_approve` tinyint(1) DEFAULT NULL COMMENT 'ผอ: อนุมัติ/ไม่',
  `director_note` varchar(255) DEFAULT NULL,
  `director_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `ledger_id` int(10) unsigned DEFAULT NULL COMMENT 'อ้าง budget_ledger เมื่อตัดงบแล้ว',
  `deducted_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `paid_at` datetime DEFAULT NULL,
  `paid_by` int(10) unsigned DEFAULT NULL,
  `payment_note` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL COMMENT 'ถังขยะ กู้คืนได้ 30 วัน',
  `deleted_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_memo_year` (`budget_year`,`status`),
  KEY `ix_memo_project` (`project_id`),
  KEY `idx_memo_dept` (`department_id`),
  KEY `idx_memo_subject_group` (`subject_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_no` varchar(30) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `activity_id` bigint(20) unsigned DEFAULT NULL,
  `requester_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `refunded_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `purpose` varchar(500) NOT NULL,
  `status` enum('pending','approved','rejected','paid','cancelled') NOT NULL DEFAULT 'pending',
  `current_level` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_br_no` (`request_no`),
  KEY `ix_br_project` (`project_id`),
  KEY `ix_br_status` (`status`),
  KEY `fk_br_activity` (`activity_id`),
  KEY `fk_br_user` (`requester_id`),
  CONSTRAINT `fk_br_activity` FOREIGN KEY (`activity_id`) REFERENCES `project_activities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_br_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_br_user` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(200) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_source` (`school_id`,`code`),
  CONSTRAINT `fk_bs_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `budget_source_id` bigint(20) unsigned NOT NULL,
  `academic_year_id` bigint(20) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `allocated_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `used_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_budget_source` (`budget_source_id`),
  KEY `fk_budget_ay` (`academic_year_id`),
  CONSTRAINT `fk_budget_ay` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_budget_source` FOREIGN KEY (`budget_source_id`) REFERENCES `budget_sources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `buildings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `floors` tinyint(3) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_building_school` (`school_id`),
  CONSTRAINT `fk_building_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `event_type` enum('academic','activity','meeting','holiday','exam') NOT NULL DEFAULT 'activity',
  `event_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `meeting_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_cal_date` (`event_date`),
  KEY `fk_cal_school` (`school_id`),
  CONSTRAINT `fk_cal_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_advance_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cash_advance_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cai_advance` (`cash_advance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_advances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advance_no` varchar(30) NOT NULL,
  `borrower_id` bigint(20) unsigned DEFAULT NULL,
  `borrower_position` varchar(150) DEFAULT NULL COMMENT 'ตำแหน่งผู้ยืม',
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `activity_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `purpose` varchar(500) NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected','paid','cleared') NOT NULL DEFAULT 'pending',
  `current_level` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `paid_at` datetime DEFAULT NULL,
  `cleared_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `cleared_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `lend_from` varchar(200) DEFAULT NULL COMMENT 'ขอยืมเงินจาก (แหล่งเงิน/หน่วยงาน)',
  `repay_days` smallint(5) unsigned DEFAULT 30 COMMENT 'ส่งใช้คืนภายใน (วัน)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ca_no` (`advance_no`),
  KEY `ix_ca_status` (`status`),
  KEY `fk_ca_user` (`borrower_id`),
  KEY `fk_ca_project` (`project_id`),
  KEY `fk_ca_activity` (`activity_id`),
  CONSTRAINT `fk_ca_activity` FOREIGN KEY (`activity_id`) REFERENCES `project_activities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ca_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ca_user` FOREIGN KEY (`borrower_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `teaching_assignment_id` bigint(20) unsigned NOT NULL,
  `day_of_week` tinyint(3) unsigned NOT NULL,
  `period_no` tinyint(3) unsigned NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `room_id` bigint(20) unsigned DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_sched_ta` (`teaching_assignment_id`),
  KEY `ix_sched_day` (`day_of_week`,`period_no`),
  KEY `fk_sched_room` (`room_id`),
  CONSTRAINT `fk_sched_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sched_ta` FOREIGN KEY (`teaching_assignment_id`) REFERENCES `teaching_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classrooms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_year_id` bigint(20) unsigned NOT NULL,
  `grade_level_id` bigint(20) unsigned NOT NULL,
  `section` tinyint(3) unsigned NOT NULL,
  `name` varchar(60) NOT NULL,
  `room_id` bigint(20) unsigned DEFAULT NULL,
  `homeroom_teacher_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_classroom` (`academic_year_id`,`grade_level_id`,`section`),
  KEY `ix_classroom_grade` (`grade_level_id`),
  KEY `fk_cr_school` (`school_id`),
  KEY `fk_cr_room` (`room_id`),
  KEY `fk_cr_homeroom` (`homeroom_teacher_id`),
  CONSTRAINT `fk_cr_ay` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_grade` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_homeroom` FOREIGN KEY (`homeroom_teacher_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cr_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cr_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_att_doc` (`document_id`),
  CONSTRAINT `fk_att_doc` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint(20) unsigned NOT NULL,
  `kind` enum('assign','order') NOT NULL DEFAULT 'assign',
  `body` text NOT NULL,
  `decision` enum('none','approved','rejected','noted') NOT NULL DEFAULT 'none',
  `author_id` bigint(20) unsigned DEFAULT NULL,
  `author_name` varchar(200) DEFAULT NULL,
  `author_position` varchar(200) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL COMMENT 'ลายเซ็นที่แนบตอนลงนาม',
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `is_signed` tinyint(1) NOT NULL DEFAULT 0,
  `signed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_note_doc` (`document_id`,`kind`),
  CONSTRAINT `fk_note_doc` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint(20) unsigned NOT NULL,
  `recipient_id` bigint(20) unsigned DEFAULT NULL,
  `recipient_personnel_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ผู้รับ (personnel)',
  `action` enum('forward','approve','acknowledge','sign','comment') NOT NULL DEFAULT 'acknowledge',
  `status` enum('pending','done','rejected') NOT NULL DEFAULT 'pending',
  `note` varchar(500) DEFAULT NULL,
  `acted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `recipient_dept_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ฝ่ายผู้รับ',
  `instruction` varchar(500) DEFAULT NULL COMMENT 'ข้อความสั่งการ/มอบหมายที่ส่งไปกับหนังสือ',
  `due_date` date DEFAULT NULL COMMENT 'กำหนดแล้วเสร็จของผู้รับรายนี้',
  `forwarded_by` bigint(20) unsigned DEFAULT NULL,
  `forwarded_at` datetime DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'ผู้รับเปิดอ่านแล้ว',
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_docrecv_doc` (`document_id`),
  KEY `ix_docrecv_user` (`recipient_id`),
  KEY `ix_dr_person` (`recipient_personnel_id`,`status`),
  CONSTRAINT `fk_docrecv_doc` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_docrecv_user` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(255) NOT NULL,
  `doc_kind` enum('memo','announcement','order','letter') NOT NULL DEFAULT 'memo',
  `has_garuda` tinyint(1) NOT NULL DEFAULT 1,
  `body` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tpl` (`school_id`,`code`),
  CONSTRAINT `fk_tpl_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `viewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_dv_doc` (`document_id`),
  KEY `fk_dv_user` (`user_id`),
  CONSTRAINT `fk_dv_doc` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `doc_number` varchar(60) DEFAULT NULL,
  `receive_no` varchar(40) DEFAULT NULL COMMENT 'เลขทะเบียนรับ',
  `send_no` varchar(40) DEFAULT NULL COMMENT 'เลขทะเบียนส่ง',
  `doc_type` enum('incoming','outgoing','circular','internal') NOT NULL DEFAULT 'internal',
  `title` varchar(500) NOT NULL,
  `from_org` varchar(255) DEFAULT NULL,
  `to_org` varchar(255) DEFAULT NULL,
  `doc_date` date DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `qr_verify_code` varchar(120) DEFAULT NULL,
  `is_signed` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','registered','in_process','completed','archived') NOT NULL DEFAULT 'registered',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `received_at` datetime DEFAULT NULL COMMENT 'วันเวลาที่ลงรับ (ใช้บนตราปั๊ม)',
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `urgency` enum('normal','urgent','very_urgent','most_urgent') NOT NULL DEFAULT 'normal' COMMENT 'ชั้นความเร็ว',
  `secret_level` enum('normal','confidential','secret','top_secret') NOT NULL DEFAULT 'normal' COMMENT 'ชั้นความลับ',
  `assigned_to` bigint(20) unsigned DEFAULT NULL COMMENT 'มอบหมายให้ (personnel)',
  `assigned_dept` bigint(20) unsigned DEFAULT NULL COMMENT 'มอบหมายให้ฝ่าย',
  `due_date` date DEFAULT NULL,
  `director_status` enum('pending','approved','rejected','noted') NOT NULL DEFAULT 'pending' COMMENT 'ผลการสั่งการของ ผอ.',
  `signed_by` bigint(20) unsigned DEFAULT NULL,
  `signed_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL COMMENT 'วันเวลาที่ส่งออก',
  PRIMARY KEY (`id`),
  KEY `ix_doc_type` (`doc_type`),
  KEY `ix_doc_status` (`status`),
  KEY `ix_doc_number` (`doc_number`),
  KEY `fk_doc_school` (`school_id`),
  KEY `fk_doc_user` (`created_by`),
  KEY `ix_doc_verify` (`qr_verify_code`),
  CONSTRAINT `fk_doc_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doc_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_results` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `exam_id` bigint(20) NOT NULL,
  `student_id` bigint(20) NOT NULL,
  `answers` varchar(255) NOT NULL DEFAULT '',
  `correct_count` smallint(6) NOT NULL DEFAULT 0,
  `answered_count` smallint(6) NOT NULL DEFAULT 0,
  `score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `graded_by` bigint(20) DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exam_student` (`exam_id`,`student_id`),
  KEY `idx_exam` (`exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL DEFAULT 1,
  `semester_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `classroom_id` bigint(20) DEFAULT NULL,
  `exam_type` enum('midterm','final','quiz','pretest','posttest') NOT NULL DEFAULT 'midterm',
  `title` varchar(255) NOT NULL,
  `exam_date` date DEFAULT NULL,
  `duration_min` smallint(5) unsigned DEFAULT 60,
  `total_questions` smallint(5) unsigned NOT NULL DEFAULT 40,
  `choices` tinyint(3) unsigned NOT NULL DEFAULT 4,
  `total_score` decimal(6,2) NOT NULL DEFAULT 40.00,
  `instructions` text DEFAULT NULL,
  `answer_key` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_exam_sem` (`semester_id`),
  KEY `ix_exam_subject` (`subject_id`),
  KEY `ix_exam_teacher` (`teacher_id`),
  KEY `ix_exam_type` (`exam_type`),
  CONSTRAINT `fk_exam_sem` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `final_grades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `teaching_assignment_id` bigint(20) unsigned NOT NULL,
  `total_score` decimal(6,2) DEFAULT NULL,
  `grade` varchar(5) DEFAULT NULL,
  `special_result` enum('none','0','r','ms','mp') NOT NULL DEFAULT 'none',
  `is_finalized` tinyint(1) NOT NULL DEFAULT 0,
  `finalized_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_final` (`student_id`,`teaching_assignment_id`),
  KEY `ix_final_ta` (`teaching_assignment_id`),
  KEY `ix_final_special` (`special_result`),
  CONSTRAINT `fk_final_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_final_ta` FOREIGN KEY (`teaching_assignment_id`) REFERENCES `teaching_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grade_components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `teaching_assignment_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `standard_code` varchar(50) DEFAULT NULL,
  `indicator` varchar(300) DEFAULT NULL,
  `hours` decimal(5,1) DEFAULT NULL,
  `component_type` enum('formative','midterm','final','other') NOT NULL DEFAULT 'formative',
  `max_score` decimal(6,2) NOT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `sort_order` smallint(5) unsigned DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_gc_ta` (`teaching_assignment_id`),
  CONSTRAINT `fk_gc_ta` FOREIGN KEY (`teaching_assignment_id`) REFERENCES `teaching_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grade_fixes` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `final_grade_id` bigint(20) NOT NULL,
  `original_result` enum('0','r','ms') NOT NULL,
  `new_grade` varchar(5) NOT NULL,
  `fixed_date` date DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `fixed_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_final_grade` (`final_grade_id`),
  KEY `idx_orig` (`original_result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grade_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(80) NOT NULL,
  `level_order` smallint(5) unsigned NOT NULL,
  `stage` enum('kindergarten','primary','lower_secondary','upper_secondary') DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grade` (`school_id`,`code`),
  CONSTRAINT `fk_grade_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guardians` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `citizen_id` varchar(13) DEFAULT NULL,
  `prefix` varchar(30) DEFAULT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `occupation` varchar(120) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_guardian_citizen` (`citizen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `health_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `record_date` date NOT NULL,
  `height_cm` decimal(5,1) DEFAULT NULL,
  `weight_kg` decimal(5,1) DEFAULT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `chronic_disease` varchar(255) DEFAULT NULL,
  `allergy` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_health_student` (`student_id`,`record_date`),
  CONSTRAINT `fk_health_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `home_visits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `visit_date` date NOT NULL,
  `visitor_id` bigint(20) unsigned DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `risk_level` enum('normal','watch','risk','urgent') DEFAULT 'normal',
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `guardian_name` varchar(200) DEFAULT NULL,
  `guardian_relation` varchar(60) DEFAULT NULL,
  `guardian_phone` varchar(30) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `living_with` varchar(60) DEFAULT NULL COMMENT 'อาศัยอยู่กับ',
  `family_status` enum('together','divorced','father_only','mother_only','relative','other') DEFAULT NULL,
  `housing_type` enum('own','rent','relative','other') DEFAULT NULL,
  `family_income` decimal(12,2) DEFAULT NULL COMMENT 'รายได้ครอบครัว/เดือน',
  `travel_method` varchar(60) DEFAULT NULL,
  `distance_km` decimal(6,2) DEFAULT NULL,
  `health_note` varchar(500) DEFAULT NULL,
  `recommendation` varchar(500) DEFAULT NULL,
  `needs_help` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'ควรได้รับการช่วยเหลือ',
  `next_visit_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_visit_student` (`student_id`),
  KEY `fk_visit_visitor` (`visitor_id`),
  CONSTRAINT `fk_visit_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visit_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kg_indicator_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `semester_id` bigint(20) unsigned NOT NULL,
  `indicator_id` bigint(20) unsigned NOT NULL,
  `value` tinyint(4) NOT NULL DEFAULT 0,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kg_ind_score` (`student_id`,`semester_id`,`indicator_id`),
  KEY `idx_kg_ind_score_ind` (`indicator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kg_indicators` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(30) NOT NULL,
  `title` varchar(300) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kg_ind_domain` (`domain`,`active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kindergarten_assessments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) NOT NULL,
  `semester_id` bigint(20) NOT NULL,
  `physical` tinyint(4) NOT NULL DEFAULT 0,
  `emotional` tinyint(4) NOT NULL DEFAULT 0,
  `social` tinyint(4) NOT NULL DEFAULT 0,
  `intellectual` tinyint(4) NOT NULL DEFAULT 0,
  `note` varchar(500) DEFAULT NULL,
  `assessed_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `comp_physical` tinyint(4) NOT NULL DEFAULT 0,
  `comp_social` tinyint(4) NOT NULL DEFAULT 0,
  `comp_emotional` tinyint(4) NOT NULL DEFAULT 0,
  `comp_cognitive` tinyint(4) NOT NULL DEFAULT 0,
  `comp_language` tinyint(4) NOT NULL DEFAULT 0,
  `comp_moral` tinyint(4) NOT NULL DEFAULT 0,
  `comp_creative` tinyint(4) NOT NULL DEFAULT 0,
  `teacher_comment` varchar(500) DEFAULT NULL,
  `parent_comment` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_sem` (`student_id`,`semester_id`),
  KEY `idx_sem` (`semester_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kpis` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_year_id` bigint(20) unsigned DEFAULT NULL,
  `education_level` enum('early_childhood','basic','all') NOT NULL DEFAULT 'basic',
  `category` varchar(80) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `unit` varchar(40) DEFAULT '%',
  `target_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `actual_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `direction` enum('up','down') NOT NULL DEFAULT 'up',
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_kpi_level` (`education_level`),
  KEY `fk_kpi_school` (`school_id`),
  CONSTRAINT `fk_kpi_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(120) NOT NULL,
  `max_days_year` smallint(5) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leave_type` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leaves` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `personnel_id` bigint(20) unsigned NOT NULL,
  `leave_type_id` bigint(20) unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days` decimal(4,1) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approver_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_leave_personnel` (`personnel_id`),
  KEY `ix_leave_status` (`status`),
  KEY `fk_leave_type` (`leave_type_id`),
  KEY `fk_leave_approver` (`approver_id`),
  CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approver_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leave_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lesson_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` bigint(20) unsigned NOT NULL,
  `teacher_id` bigint(20) unsigned NOT NULL,
  `semester_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `unit_no` smallint(5) unsigned DEFAULT NULL,
  `hours` tinyint(3) unsigned DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_lp_subject` (`subject_id`),
  KEY `ix_lp_teacher` (`teacher_id`),
  KEY `fk_lp_semester` (`semester_id`),
  CONSTRAINT `fk_lp_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lp_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lp_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `material_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_id` bigint(20) unsigned NOT NULL,
  `txn_type` enum('in','out','adjust') NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) DEFAULT NULL,
  `reference` varchar(120) DEFAULT NULL,
  `requester_id` bigint(20) unsigned DEFAULT NULL,
  `txn_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_mtxn_material` (`material_id`,`txn_date`),
  KEY `fk_mtxn_requester` (`requester_id`),
  CONSTRAINT `fk_mtxn_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mtxn_requester` FOREIGN KEY (`requester_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `code` varchar(60) NOT NULL,
  `name` varchar(255) NOT NULL,
  `unit` varchar(40) DEFAULT NULL,
  `stock_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `min_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_material` (`school_id`,`code`),
  CONSTRAINT `fk_material_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicine_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `medicine_id` bigint(20) unsigned NOT NULL,
  `movement_type` varchar(10) NOT NULL DEFAULT 'in',
  `qty` int(11) NOT NULL DEFAULT 0,
  `balance_after` int(11) NOT NULL DEFAULT 0,
  `visit_id` bigint(20) unsigned DEFAULT NULL,
  `note` varchar(300) DEFAULT NULL,
  `moved_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_med_mov_med` (`medicine_id`,`moved_at`),
  KEY `idx_med_mov_visit` (`visit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `category` varchar(30) NOT NULL DEFAULT 'medicine',
  `unit` varchar(40) NOT NULL DEFAULT 'เม็ด',
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `min_qty` int(11) NOT NULL DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `note` varchar(300) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_medicines_name` (`name`),
  KEY `idx_medicines_active` (`active`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` varchar(500) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_notif_user` (`user_id`,`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nurse_visit_medicines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `visit_id` bigint(20) unsigned NOT NULL,
  `medicine_id` bigint(20) unsigned NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_nvm_visit` (`visit_id`),
  KEY `idx_nvm_med` (`medicine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nurse_visits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `visit_at` datetime NOT NULL,
  `patient_type` varchar(10) NOT NULL DEFAULT 'student',
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `personnel_id` bigint(20) unsigned DEFAULT NULL,
  `patient_name` varchar(200) DEFAULT NULL,
  `symptom` varchar(500) DEFAULT NULL,
  `diagnosis` varchar(300) DEFAULT NULL,
  `treatment` varchar(500) DEFAULT NULL,
  `outcome` varchar(20) NOT NULL DEFAULT 'back_class',
  `refer_to` varchar(200) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nurse_visit_at` (`visit_at`),
  KEY `idx_nurse_visit_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `org_departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(150) NOT NULL,
  `head_personnel_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept` (`school_id`,`code`),
  KEY `fk_dept_head` (`head_personnel_id`),
  CONSTRAINT `fk_dept_head` FOREIGN KEY (`head_personnel_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dept_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pa_evaluations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `personnel_id` bigint(20) unsigned NOT NULL,
  `year_be` smallint(5) unsigned NOT NULL,
  `round` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `eval_type` enum('performance','academic_standing') NOT NULL DEFAULT 'performance',
  `score` decimal(5,2) DEFAULT NULL,
  `grade` varchar(30) DEFAULT NULL,
  `target_standing` varchar(80) DEFAULT NULL,
  `result` enum('pending','passed','failed') NOT NULL DEFAULT 'pending',
  `comment` text DEFAULT NULL,
  `evaluator_id` bigint(20) unsigned DEFAULT NULL,
  `eval_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_pa_person` (`personnel_id`),
  KEY `ix_pa_year` (`year_be`),
  CONSTRAINT `fk_pa_person` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pa_topic_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `size_bytes` int(11) NOT NULL DEFAULT 0,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pa_topic_files_topic` (`topic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pa_topics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `personnel_id` bigint(20) unsigned NOT NULL,
  `year_be` smallint(6) NOT NULL,
  `round` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `category` varchar(40) NOT NULL DEFAULT 'learning',
  `title` varchar(300) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pa_topics_person` (`personnel_id`,`year_be`,`round`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_pwreset_user` (`user_id`),
  CONSTRAINT `fk_pwreset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `module` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_code` (`code`),
  KEY `ix_permissions_module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=375 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personnel` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `employee_code` varchar(40) NOT NULL,
  `citizen_id` varchar(13) DEFAULT NULL,
  `prefix` varchar(30) DEFAULT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `position` varchar(120) DEFAULT NULL,
  `academic_standing` varchar(80) DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `subject_group_id` bigint(20) unsigned DEFAULT NULL,
  `employment_type` enum('civil_servant','government_employee','contract','other') DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `status` enum('active','on_leave','resigned','retired') NOT NULL DEFAULT 'active',
  `is_part_time` tinyint(1) NOT NULL DEFAULT 0,
  `work_days` varchar(20) DEFAULT NULL,
  `max_weekly_periods` tinyint(3) unsigned DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personnel_code` (`school_id`,`employee_code`),
  KEY `ix_personnel_dept` (`department_id`),
  KEY `ix_personnel_sg` (`subject_group_id`),
  KEY `ix_personnel_status` (`status`),
  CONSTRAINT `fk_personnel_dept` FOREIGN KEY (`department_id`) REFERENCES `org_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_personnel_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_personnel_sg` FOREIGN KEY (`subject_group_id`) REFERENCES `subject_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `budget_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `spent_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('planned','ongoing','completed','cancelled') NOT NULL DEFAULT 'planned',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_pa_project` (`project_id`),
  CONSTRAINT `fk_pa_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `budget_id` bigint(20) unsigned DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `subject_group_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(40) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `responsible_id` bigint(20) unsigned DEFAULT NULL,
  `budget_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `spent_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('planned','ongoing','completed','cancelled') NOT NULL DEFAULT 'planned',
  `approval_status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft' COMMENT 'สถานะอนุมัติโครงการ',
  `progress_percent` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_project_budget` (`budget_id`),
  KEY `ix_project_status` (`status`),
  KEY `fk_project_school` (`school_id`),
  KEY `fk_project_dept` (`department_id`),
  KEY `fk_project_resp` (`responsible_id`),
  KEY `idx_proj_subject_group` (`subject_group_id`),
  CONSTRAINT `fk_project_budget` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_project_dept` FOREIGN KEY (`department_id`) REFERENCES `org_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_project_resp` FOREIGN KEY (`responsible_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_project_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(40) DEFAULT NULL,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `ix_poi_po` (`purchase_order_id`),
  CONSTRAINT `fk_poi_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `po_number` varchar(40) NOT NULL,
  `purchase_request_id` bigint(20) unsigned DEFAULT NULL,
  `vendor_id` bigint(20) unsigned DEFAULT NULL,
  `vendor_name` varchar(255) DEFAULT NULL,
  `vendor_tax_id` varchar(20) DEFAULT NULL,
  `order_date` date NOT NULL,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `vat_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('open','received','paid','cancelled') NOT NULL DEFAULT 'open',
  `committee` varchar(600) DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `receive_note` varchar(500) DEFAULT NULL,
  `paid_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_po_number` (`school_id`,`po_number`),
  KEY `ix_po_status` (`status`),
  KEY `fk_po_pr` (`purchase_request_id`),
  KEY `fk_po_vendor` (`vendor_id`),
  CONSTRAINT `fk_po_pr` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_po_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_request_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_request_id` bigint(20) unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(40) DEFAULT NULL,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `ix_pri_pr` (`purchase_request_id`),
  CONSTRAINT `fk_pri_pr` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `pr_number` varchar(40) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `activity_id` bigint(20) unsigned DEFAULT NULL,
  `budget_id` bigint(20) unsigned DEFAULT NULL,
  `request_type` enum('purchase','hire') NOT NULL DEFAULT 'purchase',
  `method` enum('specific','selection','e_bidding') NOT NULL DEFAULT 'specific',
  `requester_id` bigint(20) unsigned DEFAULT NULL,
  `request_date` date NOT NULL,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(500) DEFAULT NULL,
  `status` enum('draft','pending','approved','rejected','po_created') NOT NULL DEFAULT 'draft',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `decision_note` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_number` (`school_id`,`pr_number`),
  KEY `ix_pr_status` (`status`),
  KEY `fk_pr_project` (`project_id`),
  KEY `fk_pr_budget` (`budget_id`),
  KEY `fk_pr_requester` (`requester_id`),
  KEY `ix_pr_activity` (`activity_id`),
  CONSTRAINT `fk_pr_budget` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pr_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pr_requester` FOREIGN KEY (`requester_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pr_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repair_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `reporter_id` bigint(20) unsigned DEFAULT NULL,
  `room_id` bigint(20) unsigned DEFAULT NULL,
  `asset_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` enum('reported','assigned','in_progress','done','cancelled') NOT NULL DEFAULT 'reported',
  `assignee_id` bigint(20) unsigned DEFAULT NULL,
  `reported_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_repair_status` (`status`),
  KEY `fk_repair_school` (`school_id`),
  KEY `fk_repair_reporter` (`reporter_id`),
  KEY `fk_repair_room` (`room_id`),
  KEY `fk_repair_asset` (`asset_id`),
  KEY `fk_repair_assignee` (`assignee_id`),
  CONSTRAINT `fk_repair_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_repair_assignee` FOREIGN KEY (`assignee_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_repair_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_repair_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_repair_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `ix_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(60) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `room_bookings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint(20) unsigned NOT NULL,
  `booked_by` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_booking_room_time` (`room_id`,`start_time`),
  KEY `fk_booking_user` (`booked_by`),
  CONSTRAINT `fk_booking_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booking_user` FOREIGN KEY (`booked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rooms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `building_id` bigint(20) unsigned DEFAULT NULL,
  `school_id` bigint(20) unsigned NOT NULL,
  `room_code` varchar(40) NOT NULL,
  `name` varchar(150) NOT NULL,
  `room_type` enum('classroom','lab','computer','gym','meeting','office','other') NOT NULL DEFAULT 'classroom',
  `capacity` smallint(5) unsigned DEFAULT NULL,
  `is_bookable` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_room_building` (`building_id`),
  KEY `fk_room_school` (`school_id`),
  CONSTRAINT `fk_room_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_room_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salaries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `personnel_id` bigint(20) unsigned NOT NULL,
  `pay_year` smallint(5) unsigned NOT NULL,
  `pay_month` tinyint(3) unsigned NOT NULL,
  `base_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_at` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_salary` (`personnel_id`,`pay_year`,`pay_month`),
  CONSTRAINT `fk_salary_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salary_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `personnel_id` bigint(20) unsigned NOT NULL,
  `year_be` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `base_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_salary` (`personnel_id`,`year_be`,`month`),
  KEY `ix_salary_period` (`year_be`,`month`),
  CONSTRAINT `fk_salary_person` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sar_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sar_id` int(10) unsigned NOT NULL,
  `category` enum('training_cert','teaching_order','admin_order','report','award','photo','research','other') NOT NULL DEFAULT 'other' COMMENT 'ประเภทเอกสารแนบ (ตามภาคผนวก)',
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `size_bytes` int(10) unsigned DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_sar_att` (`sar_id`,`category`),
  CONSTRAINT `fk_sar_att` FOREIGN KEY (`sar_id`) REFERENCES `sar_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sar_kg` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `academic_year_id` bigint(20) unsigned NOT NULL,
  `summary_note` varchar(1000) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sar_kg_year` (`academic_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sar_kg_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sar_id` bigint(20) unsigned NOT NULL,
  `std_no` tinyint(3) unsigned NOT NULL,
  `ind_no` tinyint(3) unsigned NOT NULL,
  `total` int(11) NOT NULL DEFAULT 0,
  `n5` int(11) NOT NULL DEFAULT 0,
  `n4` int(11) NOT NULL DEFAULT 0,
  `n3` int(11) NOT NULL DEFAULT 0,
  `n2` int(11) NOT NULL DEFAULT 0,
  `n1` int(11) NOT NULL DEFAULT 0,
  `lvl` tinyint(4) NOT NULL DEFAULT 0,
  `evidence` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sar_kg_score` (`sar_id`,`std_no`,`ind_no`),
  KEY `idx_sar_kg_score_sar` (`sar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sar_reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL DEFAULT 1,
  `personnel_id` int(10) unsigned NOT NULL,
  `academic_year` smallint(6) NOT NULL COMMENT 'ปีการศึกษา พ.ศ. เช่น 2569',
  `position` varchar(120) DEFAULT NULL COMMENT 'ตำแหน่ง',
  `academic_standing` varchar(120) DEFAULT NULL COMMENT 'วิทยฐานะ',
  `subject_group` varchar(160) DEFAULT NULL COMMENT 'กลุ่มสาระการเรียนรู้',
  `teach_hours` decimal(5,1) DEFAULT NULL COMMENT 'ชั่วโมงสอน/สัปดาห์',
  `special_duties` text DEFAULT NULL COMMENT 'งานพิเศษ/หน้าที่ที่ได้รับมอบหมาย (สรุป)',
  `self_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ตอน1: ประวัติ/การศึกษา/รางวัล' CHECK (json_valid(`self_data`)),
  `develop_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ตอน2: อบรม/PLC/พัฒนาตนเอง' CHECK (json_valid(`develop_data`)),
  `duties_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ตอน3: วิชาที่สอน/กิจกรรม/งานพิเศษ' CHECK (json_valid(`duties_data`)),
  `results_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ตอน4: ผลการเรียน/เปรียบเทียบ' CHECK (json_valid(`results_data`)),
  `student_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ตอน5: ผลงานผู้เรียน/แหล่งเรียนรู้/วิจัย' CHECK (json_valid(`student_data`)),
  `improve_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ตอน6: แนวทางพัฒนา' CHECK (json_valid(`improve_data`)),
  `eval_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ประเมิน: การสอน/บริหารชั้นเรียน/พัฒนาตน/จรรยาบรรณ' CHECK (json_valid(`eval_data`)),
  `eval_score` decimal(5,2) DEFAULT NULL COMMENT 'คะแนนรวมเฉลี่ย (คำนวณตอนบันทึก)',
  `status` enum('draft','submitted','reviewed','approved','returned') NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `reviewer_id` int(10) unsigned DEFAULT NULL COMMENT 'รอง ผอ.วิชาการ',
  `reviewer_comment` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `director_id` int(10) unsigned DEFAULT NULL,
  `director_comment` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sar_person_year` (`personnel_id`,`academic_year`),
  KEY `ix_sar_year` (`academic_year`,`status`),
  KEY `ix_sar_person` (`personnel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schema_migrations` (
  `filename` varchar(191) NOT NULL,
  `statements` int(10) unsigned NOT NULL DEFAULT 0,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scholarships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `scholarship_type` enum('poor','excellent','sport','art','special') NOT NULL DEFAULT 'poor',
  `source` varchar(200) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `academic_year_id` bigint(20) unsigned DEFAULT NULL,
  `granted_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('proposed','approved','granted','rejected') NOT NULL DEFAULT 'proposed',
  `term` varchar(60) DEFAULT NULL COMMENT 'ภาคเรียน/งวด',
  `note` varchar(500) DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `receipt_no` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_scholar_student` (`student_id`),
  KEY `fk_scholar_ay` (`academic_year_id`),
  CONSTRAINT `fk_scholar_ay` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_scholar_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schools` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_code` varchar(30) NOT NULL,
  `name_th` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `district` varchar(120) DEFAULT NULL,
  `province` varchar(120) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `affiliation` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_school_code` (`school_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `grade_component_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `score` decimal(6,2) DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_score` (`grade_component_id`,`student_id`),
  KEY `ix_score_student` (`student_id`),
  CONSTRAINT `fk_score_gc` FOREIGN KEY (`grade_component_id`) REFERENCES `grade_components` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_score_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sdq_assessments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `academic_year_id` bigint(20) unsigned DEFAULT NULL,
  `assessor` enum('self','teacher','parent') NOT NULL DEFAULT 'teacher',
  `emotional_score` tinyint(3) unsigned DEFAULT NULL,
  `conduct_score` tinyint(3) unsigned DEFAULT NULL,
  `hyperactivity_score` tinyint(3) unsigned DEFAULT NULL,
  `peer_score` tinyint(3) unsigned DEFAULT NULL,
  `prosocial_score` tinyint(3) unsigned DEFAULT NULL,
  `total_difficulty` tinyint(3) unsigned DEFAULT NULL,
  `result_group` enum('normal','risk','problem') DEFAULT NULL,
  `assessed_at` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `emotional_group` enum('normal','risk','problem') DEFAULT NULL,
  `conduct_group` enum('normal','risk','problem') DEFAULT NULL,
  `hyperactivity_group` enum('normal','risk','problem') DEFAULT NULL,
  `peer_group` enum('normal','risk','problem') DEFAULT NULL,
  `prosocial_group` enum('normal','risk','problem') DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sdq_student` (`student_id`),
  KEY `fk_sdq_ay` (`academic_year_id`),
  CONSTRAINT `fk_sdq_ay` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sdq_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `semesters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `academic_year_id` bigint(20) unsigned NOT NULL,
  `term` tinyint(3) unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_semester` (`academic_year_id`,`term`),
  CONSTRAINT `fk_sem_ay` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_attendance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `personnel_id` bigint(20) unsigned NOT NULL,
  `work_date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `status` enum('present','late','absent','leave','official') NOT NULL DEFAULT 'present',
  `note` varchar(255) DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa` (`personnel_id`,`work_date`),
  KEY `ix_sa_date` (`work_date`),
  CONSTRAINT `fk_sa_person` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_attendances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `personnel_id` bigint(20) unsigned NOT NULL,
  `work_date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `method` enum('fingerprint','face','manual','mobile') NOT NULL DEFAULT 'fingerprint',
  `status` enum('normal','late','absent','leave') NOT NULL DEFAULT 'normal',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_att` (`personnel_id`,`work_date`),
  CONSTRAINT `fk_staffatt_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `classroom_id` bigint(20) unsigned NOT NULL,
  `academic_year_id` bigint(20) unsigned NOT NULL,
  `roll_number` smallint(5) unsigned DEFAULT NULL,
  `status` enum('active','moved','left') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enroll` (`student_id`,`academic_year_id`),
  KEY `ix_enroll_classroom` (`classroom_id`),
  KEY `fk_se_ay` (`academic_year_id`),
  CONSTRAINT `fk_se_ay` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_se_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_se_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_evaluations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `teaching_assignment_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `category` enum('reading','character','competency') NOT NULL,
  `item_key` varchar(40) NOT NULL,
  `level` tinyint(3) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eval` (`teaching_assignment_id`,`student_id`,`category`,`item_key`),
  KEY `ix_eval_ta` (`teaching_assignment_id`),
  KEY `fk_ev_student` (`student_id`),
  CONSTRAINT `fk_ev_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ev_ta` FOREIGN KEY (`teaching_assignment_id`) REFERENCES `teaching_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_guardians` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `guardian_id` bigint(20) unsigned NOT NULL,
  `relationship` enum('father','mother','guardian','relative','other') NOT NULL DEFAULT 'guardian',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sg` (`student_id`,`guardian_id`),
  KEY `ix_sg_guardian` (`guardian_id`),
  CONSTRAINT `fk_sg_guardian` FOREIGN KEY (`guardian_id`) REFERENCES `guardians` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sg_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `student_code` varchar(40) NOT NULL,
  `citizen_id` varchar(13) DEFAULT NULL,
  `prefix` varchar(30) DEFAULT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `nickname` varchar(60) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `blood_group` enum('A','B','AB','O') DEFAULT NULL,
  `nationality` varchar(60) DEFAULT NULL,
  `religion` varchar(60) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `admission_date` date DEFAULT NULL,
  `status` enum('studying','graduated','transferred','dropped','suspended') NOT NULL DEFAULT 'studying',
  `photo_path` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_code` (`school_id`,`student_code`),
  KEY `ix_student_name` (`first_name`,`last_name`),
  KEY `ix_student_status` (`status`),
  CONSTRAINT `fk_student_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subject_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subject_group` (`school_id`,`code`),
  CONSTRAINT `fk_sg_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `subject_code` varchar(30) NOT NULL,
  `name_th` varchar(200) NOT NULL,
  `name_en` varchar(200) DEFAULT NULL,
  `subject_group_id` bigint(20) unsigned DEFAULT NULL,
  `credit` decimal(4,1) DEFAULT NULL,
  `hours_per_week` tinyint(3) unsigned DEFAULT NULL,
  `subject_type` enum('core','additional','activity') NOT NULL DEFAULT 'core',
  `required_room_type` enum('any','lab','computer','gym') NOT NULL DEFAULT 'any',
  `is_heavy` tinyint(1) NOT NULL DEFAULT 0,
  `is_pe` tinyint(1) NOT NULL DEFAULT 0,
  `block_size` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subject` (`school_id`,`subject_code`),
  KEY `ix_subject_group` (`subject_group_id`),
  CONSTRAINT `fk_subject_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subject_sg` FOREIGN KEY (`subject_group_id`) REFERENCES `subject_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `substitute_teachings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sub_date` date NOT NULL,
  `class_schedule_id` bigint(20) unsigned NOT NULL,
  `absent_teacher_id` bigint(20) unsigned NOT NULL COMMENT 'ครูที่ลา (personnel)',
  `sub_teacher_id` bigint(20) unsigned NOT NULL COMMENT 'ครูสอนแทน (personnel)',
  `note` varchar(255) DEFAULT NULL,
  `status` enum('assigned','approved') NOT NULL DEFAULT 'assigned' COMMENT 'assigned=จัดแล้ว รออนุมัติ',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sub_slot` (`sub_date`,`class_schedule_id`),
  KEY `idx_sub_date` (`sub_date`),
  KEY `idx_sub_teacher` (`sub_teacher_id`,`sub_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_key` varchar(60) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `value_type` enum('string','int','bool','json') NOT NULL DEFAULT 'string',
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting` (`group_key`,`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_unavailable` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` bigint(20) unsigned NOT NULL,
  `day_of_week` tinyint(3) unsigned NOT NULL,
  `period_no` tinyint(3) unsigned DEFAULT NULL,
  `reason` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_tu_teacher` (`teacher_id`),
  CONSTRAINT `fk_tu_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teaching_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `semester_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `classroom_id` bigint(20) unsigned NOT NULL,
  `teacher_id` bigint(20) unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 1,
  `weekly_periods` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `ratio_during` tinyint(3) unsigned DEFAULT NULL,
  `ratio_final` tinyint(3) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ta` (`semester_id`,`subject_id`,`classroom_id`,`teacher_id`),
  KEY `ix_ta_teacher` (`teacher_id`),
  KEY `ix_ta_classroom` (`classroom_id`),
  KEY `fk_ta_subject` (`subject_id`),
  CONSTRAINT `fk_ta_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ta_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ta_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ta_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teaching_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `teaching_assignment_id` bigint(20) NOT NULL,
  `teacher_id` bigint(20) DEFAULT NULL,
  `log_date` date DEFAULT NULL,
  `period_no` varchar(30) DEFAULT NULL,
  `hours` decimal(4,1) NOT NULL DEFAULT 1.0,
  `unit_no` varchar(60) DEFAULT NULL,
  `lesson_topic` varchar(255) DEFAULT NULL,
  `students_total` smallint(6) NOT NULL DEFAULT 0,
  `students_present` smallint(6) NOT NULL DEFAULT 0,
  `students_absent` smallint(6) NOT NULL DEFAULT 0,
  `students_leave` smallint(6) NOT NULL DEFAULT 0,
  `learning_result` text DEFAULT NULL,
  `passed_count` smallint(6) NOT NULL DEFAULT 0,
  `problems` text DEFAULT NULL,
  `solutions` text DEFAULT NULL,
  `head_comment` text DEFAULT NULL,
  `head_id` bigint(20) DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ta` (`teaching_assignment_id`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_date` (`log_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timetable_publications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `classroom_id` bigint(20) unsigned NOT NULL,
  `semester_id` bigint(20) unsigned NOT NULL,
  `published_by` bigint(20) unsigned DEFAULT NULL,
  `published_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pub` (`classroom_id`,`semester_id`),
  KEY `fk_pub_sem` (`semester_id`),
  CONSTRAINT `fk_pub_cr` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pub_sem` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `travel_claims` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `claim_no` varchar(40) DEFAULT NULL,
  `claim_date` date DEFAULT NULL,
  `office_place` varchar(255) DEFAULT NULL,
  `addressee` varchar(255) DEFAULT NULL,
  `order_no` varchar(120) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `traveler_id` bigint(20) DEFAULT NULL,
  `traveler_name` varchar(200) DEFAULT NULL,
  `traveler_position` varchar(200) DEFAULT NULL,
  `affiliation` varchar(255) DEFAULT NULL,
  `companions` text DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `depart_from` enum('home','office','thailand') NOT NULL DEFAULT 'office',
  `depart_at` datetime DEFAULT NULL,
  `return_to` enum('home','office','thailand') NOT NULL DEFAULT 'office',
  `return_at` datetime DEFAULT NULL,
  `total_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `total_hours` decimal(5,1) NOT NULL DEFAULT 0.0,
  `perdiem_type` varchar(120) DEFAULT NULL,
  `perdiem_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `perdiem_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `lodging_type` varchar(120) DEFAULT NULL,
  `lodging_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `lodging_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transport_detail` varchar(255) DEFAULT NULL,
  `transport_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_detail` varchar(255) DEFAULT NULL,
  `other_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `receipts_count` int(11) NOT NULL DEFAULT 0,
  `loan_no` varchar(80) DEFAULT NULL,
  `loan_date` date DEFAULT NULL,
  `loan_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `note` text DEFAULT NULL,
  `status` enum('draft','submitted','approved','paid') NOT NULL DEFAULT 'draft',
  `approver_id` bigint(20) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `updated_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_traveler` (`traveler_id`),
  KEY `idx_status` (`status`),
  KEY `idx_claim_date` (`claim_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_login_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `result` enum('success','failed','locked','otp_required') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_login_user` (`user_id`),
  KEY `ix_login_created` (`created_at`),
  CONSTRAINT `fk_login_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `ix_ur_role` (`role_id`),
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL COMMENT 'ไฟล์ลายเซ็น (uploads/...)',
  `linked_type` enum('none','personnel','student','guardian') NOT NULL DEFAULT 'none',
  `linked_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','inactive','locked','suspended') NOT NULL DEFAULT 'active',
  `is_2fa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `twofa_secret` varchar(255) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `failed_attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `password_changed_at` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `ix_users_linked` (`linked_type`,`linked_id`),
  KEY `ix_users_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_bookings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_no` varchar(30) NOT NULL,
  `vehicle_id` bigint(20) unsigned DEFAULT NULL,
  `requester_id` bigint(20) unsigned DEFAULT NULL,
  `purpose` varchar(255) NOT NULL,
  `destination` varchar(255) DEFAULT NULL,
  `depart_at` datetime NOT NULL,
  `return_at` datetime DEFAULT NULL,
  `passengers` smallint(5) unsigned DEFAULT NULL,
  `driver_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
  `approver_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_no` (`booking_no`),
  KEY `ix_vb_vehicle` (`vehicle_id`),
  KEY `ix_vb_status` (`status`),
  KEY `fk_vb_driver` (`driver_id`),
  CONSTRAINT `fk_vb_driver` FOREIGN KEY (`driver_id`) REFERENCES `personnel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vb_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL DEFAULT 1,
  `plate_no` varchar(20) NOT NULL,
  `brand` varchar(80) DEFAULT NULL,
  `vehicle_type` varchar(60) DEFAULT NULL,
  `seats` smallint(5) unsigned DEFAULT NULL,
  `fuel_type` varchar(30) DEFAULT NULL,
  `status` enum('available','in_use','maintenance','inactive') NOT NULL DEFAULT 'available',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plate` (`plate_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `tax_id` varchar(20) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_vendor_name` (`name`),
  KEY `fk_vendor_school` (`school_id`),
  CONSTRAINT `fk_vendor_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `welfare_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tx_date` date NOT NULL,
  `tx_type` varchar(10) NOT NULL DEFAULT 'income',
  `category` varchar(30) NOT NULL DEFAULT 'sale',
  `description` varchar(400) NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `ref_no` varchar(60) DEFAULT NULL,
  `responsible_id` bigint(20) unsigned DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wt_date` (`tx_date`,`id`),
  KEY `idx_wt_type` (`tx_type`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- ---------- ค่าตั้งต้น (master data) ----------
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (1,'super_admin','Super Admin',NULL,1,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (2,'director','ผู้อำนวยการ',NULL,1,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (3,'deputy_director','รองผู้อำนวยการ',NULL,1,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (4,'head_academic','หัวหน้าฝ่ายวิชาการ',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (5,'head_budget','หัวหน้าฝ่ายงบประมาณ',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (6,'head_hr','หัวหน้าฝ่ายบุคคล',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (7,'head_general','หัวหน้าฝ่ายบริหารทั่วไป',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (8,'finance_officer','เจ้าหน้าที่การเงิน',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (9,'inventory_officer','เจ้าหน้าที่พัสดุ',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (10,'clerk','เจ้าหน้าที่ธุรการ',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (11,'teacher','ครู',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (12,'advisor','ครูที่ปรึกษา',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (13,'student','นักเรียน',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (14,'guardian','ผู้ปกครอง',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (15,'board','คณะกรรมการสถานศึกษา',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `roles` (`id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (16,'auditor','ผู้ตรวจสอบ',NULL,0,'2026-07-19 14:07:22','2026-07-19 14:07:22');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (1,'student.view','ดูข้อมูลนักเรียน','student','2026-07-19 14:07:22');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (2,'student.edit','แก้ไขข้อมูลนักเรียน','student','2026-07-19 14:07:22');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (3,'grade.view','ดูผลการเรียน','academic','2026-07-19 14:07:22');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (4,'grade.edit','บันทึกคะแนน','academic','2026-07-19 14:07:22');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (5,'attendance.record','เช็คชื่อ','academic','2026-07-19 14:07:22');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (6,'budget.view','ดูงบประมาณ','budget','2026-07-19 14:07:22');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (7,'budget.approve','อนุมัติขอซื้อ/ขอจ้าง','budget','2026-07-19 14:07:22');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (8,'asset.manage','จัดการพัสดุ','inventory','2026-07-19 14:07:22');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (9,'document.manage','สารบรรณ รับ-ส่ง','document','2026-07-19 14:07:22');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (10,'user.manage','จัดการผู้ใช้','user','2026-07-19 14:07:22');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (11,'admin.dashboard','ดู Dashboard ผู้บริหาร','admin','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (12,'admin.approve','อนุมัติเอกสาร','admin','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (13,'admin.eoffice','E-Office/หนังสือราชการ','admin','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (14,'admin.projects','ติดตามโครงการ','admin','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (15,'admin.reports','รายงานภาพรวม','admin','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (16,'academic.curriculum','จัดการหลักสูตร','academic','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (17,'academic.schedule','ตารางเรียน/สอน','academic','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (18,'academic.grades','บันทึกคะแนน/ผลการเรียน','academic','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (19,'academic.attendance','เช็คชื่อนักเรียน','academic','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (20,'academic.measurement','งานวัดผล/ข้อสอบ','academic','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (21,'academic.pp','เอกสาร ปพ./Transcript','academic','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (22,'budget.manage','จัดการงบประมาณ','budget','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (23,'budget.purchase','ขอซื้อ/ขอจ้าง','budget','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (24,'budget.po','ใบสั่งซื้อ/สัญญา','budget','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (25,'budget.report','รายงานงบประมาณ','budget','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (26,'inventory.manage','จัดการครุภัณฑ์/พัสดุ','inventory','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (27,'hr.personnel','ข้อมูลบุคลากร','hr','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (28,'hr.leave','จัดการการลา','hr','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (29,'hr.attendance','ลงเวลาปฏิบัติงาน','hr','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (30,'hr.salary','เงินเดือน','hr','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (31,'hr.evaluation','PA/วิทยฐานะ/ประเมิน','hr','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (32,'general.repair','แจ้งซ่อม/อาคาร','general','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (33,'general.booking','จองห้อง','general','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (34,'general.vehicle','ยานพาหนะ','general','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (35,'general.health','งานอนามัย','general','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (36,'general.pr','ผู้มาติดต่อ/ประชาสัมพันธ์','general','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (37,'student.profile','ข้อมูลนักเรียน','student','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (38,'student.behavior','พฤติกรรม/SDQ','student','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (39,'student.care','ระบบดูแลช่วยเหลือ','student','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (40,'student.scholarship','ทุนการศึกษา','student','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (41,'portal.student','พอร์ทัลนักเรียน','portal','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (42,'portal.guardian','พอร์ทัลผู้ปกครอง','portal','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (43,'document.sign','ลงนามดิจิทัล/เวียน','document','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (44,'role.manage','จัดการบทบาท/สิทธิ์','role','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (45,'audit.view','ดูประวัติการใช้งาน','audit','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (46,'system.settings','ตั้งค่าระบบ','system','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (47,'system.backup','สำรอง/กู้คืนข้อมูล','system','2026-07-19 14:07:27');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (50,'academic.monitor','ติดตามการกรอกคะแนน','academic','2026-07-19 14:07:49');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (52,'budget.planning','งานแผน/โครงการ/เบิกจ่าย','budget','2026-07-19 14:08:49');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (63,'general.asset','พัสดุ/ครุภัณฑ์','general','2026-07-19 14:09:30');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (64,'general.document','งานสารบรรณ','general','2026-07-19 14:09:30');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (68,'budget.ledger','บัญชีคุมงบประมาณ','budget','2026-07-19 14:09:36');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (78,'academic.attendance_report','รายงานการมาเรียน','academic','2026-07-19 14:12:47');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (282,'document.inbox','ดูหนังสือที่ส่งถึงตนเอง',NULL,'2026-07-19 15:41:57');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (284,'sar.own','จัดทำ SAR ของตนเอง','academic','2026-07-20 10:16:06');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (285,'sar.review','ตรวจ/อนุมัติ SAR','academic','2026-07-20 10:16:06');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (286,'sar.report','ดูรายงานภาพรวม SAR','academic','2026-07-20 10:16:06');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (301,'budget.memo','จัดทำบันทึกขออนุมัติ (งป.01)','budget','2026-07-21 10:23:56');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (302,'budget.memo_approve','พิจารณา/อนุมัติ งป.01','budget','2026-07-21 10:23:56');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (365,'budget.refund_memo','บันทึกข้อความขอคืนเงินยืม','budget','2026-07-22 13:37:21');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (366,'admin.calendar','จัดการปฏิทินโรงเรียน','admin','2026-07-23 21:07:14');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (367,'travel.claim','เบิกค่าเดินทางไปราชการ','general','2026-07-25 21:53:24');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (368,'student.headcount','สรุปยอดนักเรียน','student','2026-07-26 19:21:02');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (369,'student.health','บันทึกสุขภาพนักเรียน (BMI)','student','2026-07-27 10:53:07');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (370,'qa.kindergarten','SAR มาตรฐานการศึกษา ระดับปฐมวัย','admin','2026-07-27 13:20:57');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (371,'pa.own','จัดการ PA ของตนเอง (หัวข้อ+ไฟล์)','hr','2026-07-27 19:51:55');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (372,'activity.report','รายงานผลกิจกรรม/การแข่งขัน','admin','2026-07-27 22:15:37');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (373,'schedule.manage','จัดตารางสอน (สร้าง/เผยแพร่/ตั้งค่า)','academic','2026-07-27 23:13:51');
INSERT INTO `permissions` (`id`, `code`, `name`, `module`, `created_at`) VALUES (374,'general.welfare','ร้านค้าสวัสดิการ (รายรับ-รายจ่าย/เงินยืม)','general','2026-07-28 17:01:32');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,1);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,2);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,3);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,4);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,5);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,6);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,7);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,8);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,9);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,10);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,11);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,12);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,13);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,14);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,15);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,16);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,17);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,18);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,19);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,20);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,21);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,22);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,23);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,24);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,25);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,26);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,27);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,28);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,29);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,30);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,31);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,32);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,33);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,34);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,35);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,36);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,37);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,38);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,39);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,40);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,41);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,42);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,43);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,44);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,45);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,46);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,47);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,50);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,52);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,63);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,64);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,68);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,78);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,282);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,284);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,285);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,286);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,301);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,302);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,365);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,366);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,367);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,368);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,369);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,370);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,371);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,372);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,373);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (1,374);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,11);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,12);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,13);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,14);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,15);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,19);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,21);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,23);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,24);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,25);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,31);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,35);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,45);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,63);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,78);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,282);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,284);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,285);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,286);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,301);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,302);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,366);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,367);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,368);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,370);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,371);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,372);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,374);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,11);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,12);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,14);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,15);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,17);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,19);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,23);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,24);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,25);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,35);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,63);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,78);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,282);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,284);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,285);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,286);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,301);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,302);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,366);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,367);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,368);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,370);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,371);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,372);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,374);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,12);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,16);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,17);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,18);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,19);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,20);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,21);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,37);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,78);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,282);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,284);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,285);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,286);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,301);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,366);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,367);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,368);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,369);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,370);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,371);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,372);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,373);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,12);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,22);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,23);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,24);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,25);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,26);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,282);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,284);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,301);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,302);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,365);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,366);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,367);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,368);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,371);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,372);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,12);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,27);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,28);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,29);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,30);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,31);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,282);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,284);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,301);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,366);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,367);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,368);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,369);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,371);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (6,372);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,9);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,12);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,32);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,33);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,34);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,35);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,36);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,43);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,63);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,282);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,284);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,301);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,366);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,367);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,368);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,371);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,372);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (7,374);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (8,23);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (8,24);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (8,25);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (8,282);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (8,365);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (8,367);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (8,371);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (8,372);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (8,374);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (9,26);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (9,282);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (9,371);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (9,372);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,9);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,13);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,33);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,35);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,43);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,282);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,301);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,366);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,367);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,368);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,369);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,371);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,372);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (10,374);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,17);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,18);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,19);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,20);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,78);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,282);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,284);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,301);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,367);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,368);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,369);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,371);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (11,372);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (12,19);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (12,38);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (12,39);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (12,78);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (12,282);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (12,284);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (12,301);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (12,367);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (12,368);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (12,369);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (12,371);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (12,372);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (13,41);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (14,42);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (15,11);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (15,15);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (15,25);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (16,15);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (16,25);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (16,45);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (16,282);
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (1,1,'M1','มัธยมศึกษาปีที่ 1',10,'lower_secondary','2026-07-19 14:07:22');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (2,1,'M2','มัธยมศึกษาปีที่ 2',11,'lower_secondary','2026-07-19 14:07:22');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (3,1,'M3','มัธยมศึกษาปีที่ 3',12,'lower_secondary','2026-07-19 14:07:22');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (4,1,'P6','ประถมศึกษาปีที่ 6',9,'primary','2026-07-19 14:08:24');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (5,1,'K1','อนุบาลปีที่ 1',1,'kindergarten','2026-07-26 20:10:06');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (6,1,'K2','อนุบาลปีที่ 2',2,'kindergarten','2026-07-26 20:10:06');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (7,1,'K3','อนุบาลปีที่ 3',3,'kindergarten','2026-07-26 20:10:06');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (8,1,'P1','ประถมศึกษาปีที่ 1',4,'primary','2026-07-26 20:10:06');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (9,1,'P2','ประถมศึกษาปีที่ 2',5,'primary','2026-07-26 20:10:06');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (10,1,'P3','ประถมศึกษาปีที่ 3',6,'primary','2026-07-26 20:10:06');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (11,1,'P4','ประถมศึกษาปีที่ 4',7,'primary','2026-07-26 20:10:06');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (12,1,'P5','ประถมศึกษาปีที่ 5',8,'primary','2026-07-26 20:10:06');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (13,1,'M4','มัธยมศึกษาปีที่ 4',13,'upper_secondary','2026-07-26 20:10:06');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (14,1,'M5','มัธยมศึกษาปีที่ 5',14,'upper_secondary','2026-07-26 20:10:06');
INSERT INTO `grade_levels` (`id`, `school_id`, `code`, `name`, `level_order`, `stage`, `created_at`) VALUES (15,1,'M6','มัธยมศึกษาปีที่ 6',15,'upper_secondary','2026-07-26 20:10:06');
INSERT INTO `subject_groups` (`id`, `school_id`, `code`, `name`, `created_at`) VALUES (1,1,'THAI','ภาษาไทย','2026-07-19 14:07:32');
INSERT INTO `subject_groups` (`id`, `school_id`, `code`, `name`, `created_at`) VALUES (2,1,'MATH','คณิตศาสตร์','2026-07-19 14:07:32');
INSERT INTO `subject_groups` (`id`, `school_id`, `code`, `name`, `created_at`) VALUES (3,1,'SCI','วิทยาศาสตร์และเทคโนโลยี','2026-07-19 14:07:32');
INSERT INTO `subject_groups` (`id`, `school_id`, `code`, `name`, `created_at`) VALUES (4,1,'SOC','สังคมศึกษา ศาสนา และวัฒนธรรม','2026-07-19 14:07:32');
INSERT INTO `subject_groups` (`id`, `school_id`, `code`, `name`, `created_at`) VALUES (5,1,'ENG','ภาษาต่างประเทศ','2026-07-19 14:07:32');
INSERT INTO `subject_groups` (`id`, `school_id`, `code`, `name`, `created_at`) VALUES (6,1,'HPE','สุขศึกษาและพลศึกษา','2026-07-22 10:39:35');
INSERT INTO `subject_groups` (`id`, `school_id`, `code`, `name`, `created_at`) VALUES (7,1,'ART','ศิลปะ','2026-07-22 10:39:35');
INSERT INTO `subject_groups` (`id`, `school_id`, `code`, `name`, `created_at`) VALUES (8,1,'WORK','การงานอาชีพ','2026-07-22 10:39:35');
INSERT INTO `org_departments` (`id`, `school_id`, `code`, `name`, `head_personnel_id`, `created_at`) VALUES (1,1,'admin','ฝ่ายบริหาร',1,'2026-07-19 14:07:22');
INSERT INTO `org_departments` (`id`, `school_id`, `code`, `name`, `head_personnel_id`, `created_at`) VALUES (2,1,'academic','ฝ่ายวิชาการ',3,'2026-07-19 14:07:22');
INSERT INTO `org_departments` (`id`, `school_id`, `code`, `name`, `head_personnel_id`, `created_at`) VALUES (3,1,'budget','ฝ่ายแผนและงบประมาณ',NULL,'2026-07-19 14:07:22');
INSERT INTO `org_departments` (`id`, `school_id`, `code`, `name`, `head_personnel_id`, `created_at`) VALUES (4,1,'hr','ฝ่ายบุคคล',NULL,'2026-07-19 14:07:22');
INSERT INTO `org_departments` (`id`, `school_id`, `code`, `name`, `head_personnel_id`, `created_at`) VALUES (5,1,'general','ฝ่ายบริหารทั่วไป',NULL,'2026-07-19 14:07:22');
INSERT INTO `org_departments` (`id`, `school_id`, `code`, `name`, `head_personnel_id`, `created_at`) VALUES (6,1,'affairs','ฝ่ายกิจการนักเรียน',NULL,'2026-07-22 10:39:35');
INSERT INTO `asset_categories` (`id`, `school_id`, `code`, `name`, `depreciation_rate`, `created_at`) VALUES (1,1,'COMP','คอมพิวเตอร์และอุปกรณ์',20.00,'2026-07-19 14:07:32');
INSERT INTO `asset_categories` (`id`, `school_id`, `code`, `name`, `depreciation_rate`, `created_at`) VALUES (2,1,'FURN','ครุภัณฑ์สำนักงาน',10.00,'2026-07-19 14:07:32');
INSERT INTO `asset_categories` (`id`, `school_id`, `code`, `name`, `depreciation_rate`, `created_at`) VALUES (3,1,'EDU','ครุภัณฑ์การศึกษา',15.00,'2026-07-19 14:07:32');
INSERT INTO `leave_types` (`id`, `code`, `name`, `max_days_year`, `created_at`) VALUES (1,'sick','ลาป่วย',30,'2026-07-19 14:07:32');
INSERT INTO `leave_types` (`id`, `code`, `name`, `max_days_year`, `created_at`) VALUES (2,'personal','ลากิจส่วนตัว',10,'2026-07-19 14:07:32');
INSERT INTO `leave_types` (`id`, `code`, `name`, `max_days_year`, `created_at`) VALUES (3,'vacation','ลาพักผ่อน',10,'2026-07-19 14:07:32');
INSERT INTO `budget_sources` (`id`, `school_id`, `code`, `name`, `created_at`) VALUES (1,1,'SUBSIDY','เงินอุดหนุนรายหัว','2026-07-19 14:07:32');
INSERT INTO `budget_sources` (`id`, `school_id`, `code`, `name`, `created_at`) VALUES (2,1,'INCOME','รายได้สถานศึกษา','2026-07-19 14:07:32');
INSERT INTO `document_templates` (`id`, `school_id`, `code`, `name`, `doc_kind`, `has_garuda`, `body`, `created_at`) VALUES (1,1,'TPL-MEMO-TRAVEL','บันทึกข้อความขออนุญาตเดินทางไปราชการ','memo',1,'ข้าพเจ้า {{ชื่อผู้ขอ}} ตำแหน่ง {{ตำแหน่ง}} ขออนุญาตเดินทางไปราชการเพื่อ {{วัตถุประสงค์}} ณ {{สถานที่}} ระหว่างวันที่ {{วันที่เริ่ม}} ถึงวันที่ {{วันที่สิ้นสุด}} โดยขอเบิกค่าใช้จ่ายจำนวน {{จำนวนเงิน}} บาท จึงเรียนมาเพื่อโปรดพิจารณาอนุญาต','2026-07-19 14:07:38');
INSERT INTO `document_templates` (`id`, `school_id`, `code`, `name`, `doc_kind`, `has_garuda`, `body`, `created_at`) VALUES (2,1,'TPL-ANNOUNCE','ประกาศโรงเรียน','announcement',1,'ประกาศโรงเรียน{{ชื่อโรงเรียน}} เรื่อง {{เรื่อง}}\n\n{{เนื้อหาประกาศ}}\n\nประกาศ ณ วันที่ {{วันที่}}','2026-07-19 14:07:38');
INSERT INTO `document_templates` (`id`, `school_id`, `code`, `name`, `doc_kind`, `has_garuda`, `body`, `created_at`) VALUES (3,1,'TPL-ORDER','คำสั่งโรงเรียน','order',1,'คำสั่งโรงเรียน{{ชื่อโรงเรียน}} ที่ {{เลขที่คำสั่ง}} เรื่อง {{เรื่อง}}\n\nด้วย {{เหตุผล}} อาศัยอำนาจตามความในมาตรา {{มาตรา}} จึงแต่งตั้งให้บุคคลดังต่อไปนี้ {{รายละเอียด}}\n\nทั้งนี้ ตั้งแต่วันที่ {{วันที่มีผล}} เป็นต้นไป','2026-07-19 14:07:38');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('01_schema.sql',0,'2026-07-19 14:35:35');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('02_seed.sql',0,'2026-07-19 14:35:35');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('03_seed_demo.sql',79,'2026-07-19 14:35:35');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('04_demo_data.sql',0,'2026-07-19 14:35:35');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('05_admin_module.sql',0,'2026-07-19 14:35:35');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('06_academic.sql',0,'2026-07-19 14:35:35');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('07_scoring.sql',5,'2026-07-19 15:22:56');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('08_course_structure.sql',0,'2026-07-19 14:35:35');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('09_procurement.sql',0,'2026-07-19 14:35:35');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('10_budget_dashboard.sql',0,'2026-07-19 14:35:35');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('11_timetable.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('12_timetable_pro.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('13_desirable.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('14_exams.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('15_planning.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('16_pr_activity.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('17_hr.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('18_general.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('19_budget_ledger.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('20_settings.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('21_student_affairs.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('22_portal.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('23_document_flow.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('24_document_complete.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('25_document_routing.sql',0,'2026-07-19 14:35:36');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('26_attendance_flag.sql',0,'2026-07-19 15:22:39');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('27_budget_org_mapping.sql',0,'2026-07-22 12:26:49');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('27_sar.sql',6,'2026-07-21 19:06:16');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('28_advance_refund_memo.sql',0,'2026-07-22 13:49:49');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('28_budget_memo.sql',5,'2026-07-21 19:06:16');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('29_advance_form_8500.sql',0,'2026-07-22 14:11:28');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('29_budget_unify.sql',9,'2026-07-21 19:06:16');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('30_budget_rules.sql',11,'2026-07-21 19:32:46');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('30_refund_memo_clearance.sql',0,'2026-07-22 14:21:48');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('31_substitute_teaching.sql',0,'2026-07-23 16:25:56');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('32_substitute_approve.sql',0,'2026-07-23 20:19:25');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('33_calendar_manage.sql',0,'2026-07-23 21:07:14');
INSERT INTO `schema_migrations` (`filename`, `statements`, `applied_at`) VALUES ('34_signature.sql',0,'2026-07-24 07:31:20');

SET FOREIGN_KEY_CHECKS=1;

-- ------------------------------------------------------------------
--  ทำเครื่องหมายว่าไฟล์ปรับปรุงฐานข้อมูลทั้งหมดถูกรวมไว้ในชุดติดตั้งนี้แล้ว
--  (ชุดติดตั้งนี้สร้างจากโครงสร้างล่าสุด จึงไม่ต้องนำเข้าไฟล์เหล่านี้ซ้ำ)
--  รายการนี้ต้องอัปเดตทุกครั้งที่เพิ่มไฟล์ใน database/ — ดู UPGRADE.md
-- ------------------------------------------------------------------
INSERT IGNORE INTO schema_migrations (filename, statements) VALUES
  ('01_schema.sql',0),
  ('02_seed.sql',0),
  ('03_seed_demo.sql',0),
  ('04_demo_data.sql',0),
  ('05_admin_module.sql',0),
  ('06_academic.sql',0),
  ('07_scoring.sql',0),
  ('08_course_structure.sql',0),
  ('09_procurement.sql',0),
  ('10_budget_dashboard.sql',0),
  ('11_timetable.sql',0),
  ('12_timetable_pro.sql',0),
  ('13_desirable.sql',0),
  ('14_exams.sql',0),
  ('15_planning.sql',0),
  ('16_pr_activity.sql',0),
  ('17_hr.sql',0),
  ('18_general.sql',0),
  ('19_budget_ledger.sql',0),
  ('20_settings.sql',0),
  ('21_student_affairs.sql',0),
  ('22_portal.sql',0),
  ('23_document_flow.sql',0),
  ('24_document_complete.sql',0),
  ('25_document_routing.sql',0),
  ('26_attendance_flag.sql',0),
  ('27_budget_org_mapping.sql',0),
  ('27_sar.sql',0),
  ('28_advance_refund_memo.sql',0),
  ('28_budget_memo.sql',0),
  ('29_advance_form_8500.sql',0),
  ('29_budget_unify.sql',0),
  ('30_budget_rules.sql',0),
  ('30_refund_memo_clearance.sql',0),
  ('31_substitute_teaching.sql',0),
  ('32_substitute_approve.sql',0),
  ('33_calendar_manage.sql',0),
  ('34_signature.sql',0),
  ('35_travel_claims.sql',0),
  ('36_grade_fixes.sql',0),
  ('37_exam_grading.sql',0),
  ('38_teaching_logs.sql',0),
  ('39_wk01.sql',0),
  ('40_student_headcount.sql',0),
  ('41_grade_levels_full.sql',0),
  ('42_kindergarten.sql',0),
  ('43_student_academic.sql',0),
  ('44_asset_location.sql',0),
  ('45_kindergarten_v2.sql',0),
  ('46_sar_kindergarten.sql',0),
  ('47_pa_topics.sql',0),
  ('48_must_change_password.sql',0),
  ('49_activity_reports.sql',0),
  ('50_activity_memo.sql',0),
  ('51_activity_problems.sql',0),
  ('52_schedule_manage.sql',0),
  ('53_kg_indicators.sql',0),
  ('54_asset_register.sql',0),
  ('55_nurse_office.sql',0),
  ('56_welfare_shop.sql',0),
  ('57_welfare_drop_loans.sql',0);
