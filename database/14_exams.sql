-- ==================================================================
--  14_exams.sql — งานวัดผล/คลังข้อสอบ + กระดาษคำตอบ OMR/OCR
--  นำเข้าหลัง 01-13
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

CREATE TABLE IF NOT EXISTS exams (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      BIGINT UNSIGNED NOT NULL DEFAULT 1,
  semester_id    BIGINT UNSIGNED NOT NULL,
  subject_id     BIGINT UNSIGNED NOT NULL,
  teacher_id     BIGINT UNSIGNED DEFAULT NULL,
  exam_type      ENUM('midterm','final','quiz','pretest','posttest') NOT NULL DEFAULT 'midterm',
  title          VARCHAR(255) NOT NULL,
  exam_date      DATE DEFAULT NULL,
  duration_min   SMALLINT UNSIGNED DEFAULT 60,
  total_questions SMALLINT UNSIGNED NOT NULL DEFAULT 40,
  choices        TINYINT UNSIGNED NOT NULL DEFAULT 4,   -- ตัวเลือก 4 หรือ 5 (ก-ง / ก-จ)
  total_score    DECIMAL(6,2) NOT NULL DEFAULT 40,
  instructions   TEXT DEFAULT NULL,
  answer_key     VARCHAR(255) DEFAULT NULL,             -- เฉลย เช่น "1,2,3,4..." (index ตัวเลือก)
  file_path      VARCHAR(255) DEFAULT NULL,             -- ไฟล์ข้อสอบที่อัปโหลด
  file_name      VARCHAR(255) DEFAULT NULL,
  created_by     BIGINT UNSIGNED DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_exam_sem (semester_id),
  KEY ix_exam_subject (subject_id),
  KEY ix_exam_teacher (teacher_id),
  KEY ix_exam_type (exam_type),
  CONSTRAINT fk_exam_sem     FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
  CONSTRAINT fk_exam_subject FOREIGN KEY (subject_id)  REFERENCES subjects(id)  ON DELETE CASCADE,
  CONSTRAINT fk_exam_teacher FOREIGN KEY (teacher_id)  REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สิทธิ์ใช้งาน (ผูกกับ academic.measurement ที่มีอยู่)
-- แผนที่สิทธิ์อยู่ใน config/menu.php แล้ว (academic.measurement)

-- ตัวอย่างข้อสอบ
INSERT INTO exams (semester_id, subject_id, teacher_id, exam_type, title, exam_date, total_questions, choices, total_score, duration_min, instructions)
 SELECT 1, 2, 3, 'midterm', 'ข้อสอบกลางภาค คณิตศาสตร์', CURDATE(), 40, 4, 40, 90,
   'ให้นักเรียนเลือกคำตอบที่ถูกต้องที่สุดเพียงข้อเดียว ระบายวงกลมในกระดาษคำตอบด้วยดินสอ 2B'
 FROM DUAL WHERE EXISTS(SELECT 1 FROM subjects WHERE id=2);
INSERT INTO exams (semester_id, subject_id, teacher_id, exam_type, title, exam_date, total_questions, choices, total_score, duration_min, instructions)
 SELECT 1, 1, 4, 'final', 'ข้อสอบปลายภาค ภาษาไทย', CURDATE(), 50, 5, 50, 120,
   'เลือกคำตอบที่ถูกต้องที่สุด ระบายในกระดาษคำตอบ'
 FROM DUAL WHERE EXISTS(SELECT 1 FROM subjects WHERE id=1);

-- จบ 14
