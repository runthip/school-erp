-- ==================================================================
--  37_exam_grading.sql — งานวัดผล: กระดาษคำตอบรายบุคคล + ตรวจคำตอบผ่านระบบ
--  เพิ่มห้องเรียนเป้าหมายในข้อสอบ + ตารางเก็บคำตอบ/คะแนนรายคน
--  เฉลย (answer_key) ใช้คอลัมน์เดิม exams.answer_key (สตริงยาว N ตัว: '1'..'M', '0'=ว่าง)
--  นำเข้าหลัง 01-36 · รันซ้ำได้ปลอดภัย (idempotent)
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

ALTER TABLE exams ADD COLUMN IF NOT EXISTS classroom_id BIGINT NULL AFTER teacher_id;

CREATE TABLE IF NOT EXISTS exam_results (
  id             BIGINT AUTO_INCREMENT PRIMARY KEY,
  exam_id        BIGINT NOT NULL,
  student_id     BIGINT NOT NULL,
  answers        VARCHAR(255) NOT NULL DEFAULT '',   -- คำตอบนักเรียน (สตริง: '1'..'M', '0'=ไม่ตอบ)
  correct_count  SMALLINT NOT NULL DEFAULT 0,
  answered_count SMALLINT NOT NULL DEFAULT 0,
  score          DECIMAL(6,2) NOT NULL DEFAULT 0,
  graded_by      BIGINT NULL,
  graded_at      DATETIME NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_exam_student (exam_id, student_id),
  KEY idx_exam (exam_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- จบ 37
