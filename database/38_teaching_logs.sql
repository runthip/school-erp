-- ==================================================================
--  38_teaching_logs.sql — บันทึกหลังการสอน (ผลหลังการจัดการเรียนรู้)
--  ผูกกับ teaching_assignments → ดึงครู/ตำแหน่ง/รายวิชา/ห้อง/จำนวนนักเรียนอัตโนมัติ
--  นำเข้าหลัง 01-37 · รันซ้ำได้ปลอดภัย (idempotent) · ใช้สิทธิ์เดิม academic.grades
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS teaching_logs (
  id                     BIGINT AUTO_INCREMENT PRIMARY KEY,
  teaching_assignment_id BIGINT NOT NULL,               -- ผูกวิชาที่สอน (ครู+วิชา+ห้อง+ภาคเรียน)
  teacher_id             BIGINT NULL,
  log_date               DATE NULL,                      -- วันที่สอน
  period_no              VARCHAR(30) NULL,               -- คาบที่
  hours                  DECIMAL(4,1) NOT NULL DEFAULT 1,-- จำนวนชั่วโมง
  unit_no                VARCHAR(60) NULL,               -- หน่วยที่/แผนการสอนที่
  lesson_topic           VARCHAR(255) NULL,             -- เรื่อง/สาระสำคัญที่สอน
  students_total         SMALLINT NOT NULL DEFAULT 0,
  students_present       SMALLINT NOT NULL DEFAULT 0,
  students_absent        SMALLINT NOT NULL DEFAULT 0,
  students_leave         SMALLINT NOT NULL DEFAULT 0,
  learning_result        TEXT NULL,                      -- ผลการจัดการเรียนรู้ (ความรู้/ทักษะ/คุณลักษณะ)
  passed_count           SMALLINT NOT NULL DEFAULT 0,    -- จำนวนนักเรียนที่ผ่านจุดประสงค์
  problems               TEXT NULL,                      -- ปัญหา/อุปสรรค
  solutions              TEXT NULL,                      -- ข้อเสนอแนะ/แนวทางแก้ไข
  head_comment           TEXT NULL,                      -- ความเห็นหัวหน้ากลุ่มสาระ/วิชาการ
  head_id                BIGINT NULL,
  created_by             BIGINT NULL,
  created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ta (teaching_assignment_id),
  KEY idx_teacher (teacher_id),
  KEY idx_date (log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- จบ 38
