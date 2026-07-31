-- ==================================================================
--  13_desirable.sql — ประเมินอ่าน-คิด-เขียน / คุณลักษณะอันพึงประสงค์ / สมรรถนะสำคัญ
--  ระดับ: 0=ไม่ผ่าน 1=ผ่าน 2=ดี 3=ดีเยี่ยม   (นำเข้าหลัง 01-12)
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

CREATE TABLE IF NOT EXISTS student_evaluations (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  teaching_assignment_id BIGINT UNSIGNED NOT NULL,
  student_id             BIGINT UNSIGNED NOT NULL,
  category               ENUM('reading','character','competency') NOT NULL,
  item_key               VARCHAR(40) NOT NULL,   -- รหัสหัวข้อย่อย เช่น love_nation
  level                  TINYINT UNSIGNED DEFAULT NULL, -- 0..3 (NULL=ยังไม่ประเมิน)
  updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_eval (teaching_assignment_id, student_id, category, item_key),
  KEY ix_eval_ta (teaching_assignment_id),
  CONSTRAINT fk_ev_ta      FOREIGN KEY (teaching_assignment_id) REFERENCES teaching_assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_ev_student FOREIGN KEY (student_id)             REFERENCES students(id)              ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- จบ 13
