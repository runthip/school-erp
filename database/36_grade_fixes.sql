-- ==================================================================
--  36_grade_fixes.sql — บันทึกการแก้ผลการเรียน 0 / ร / มส (เกรดเดิม → เกรดใหม่)
--  ใช้กับหน้า "นักเรียนติด 0 ร มส" เพื่อติดตามสัดส่วนที่แก้แล้ว + เก็บสถิติเกรดเดิม/ใหม่
--  นำเข้าหลัง 01-35 · รันซ้ำได้ปลอดภัย (idempotent)
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS grade_fixes (
  id               BIGINT AUTO_INCREMENT PRIMARY KEY,
  final_grade_id   BIGINT NOT NULL,                     -- อ้างอิง final_grades.id (1 แถวต่อ 1 การติด)
  original_result  ENUM('0','r','ms') NOT NULL,         -- เกรดเดิม (ผลที่ติด)
  new_grade        VARCHAR(5) NOT NULL,                 -- เกรดใหม่หลังแก้ (เช่น 1, 1.5, 2 ... 4)
  fixed_date       DATE NULL,                           -- วันที่แก้เสร็จ
  note             VARCHAR(255) NULL,
  fixed_by         BIGINT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_final_grade (final_grade_id),
  KEY idx_orig (original_result)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- จบ 36
