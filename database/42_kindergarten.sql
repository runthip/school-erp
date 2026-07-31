-- ==================================================================
--  42_kindergarten.sql — ประเมินพัฒนาการเด็กอนุบาล (4 ด้าน)
--  ร่างกาย · อารมณ์-จิตใจ · สังคม · สติปัญญา · ระดับ 1-3 (ควรส่งเสริม/พอใช้/ดี)
--  นำเข้าหลัง 01-41 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS kindergarten_assessments (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  student_id    BIGINT NOT NULL,
  semester_id   BIGINT NOT NULL,
  physical      TINYINT NOT NULL DEFAULT 0,   -- ด้านร่างกาย (0=ยังไม่ประเมิน,1-3)
  emotional     TINYINT NOT NULL DEFAULT 0,   -- ด้านอารมณ์-จิตใจ
  social        TINYINT NOT NULL DEFAULT 0,   -- ด้านสังคม
  intellectual  TINYINT NOT NULL DEFAULT 0,   -- ด้านสติปัญญา
  note          VARCHAR(500) NULL,
  assessed_by   BIGINT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_sem (student_id, semester_id),
  KEY idx_sem (semester_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- จบ 42
