-- ==================================================================
--  53_kg_indicators.sql — หัวข้อประเมินย่อยรายสมรรถนะ (ปฐมวัย)
--  ครูกำหนดหัวข้อประเมินได้หลายหัวข้อในแต่ละสมรรถนะ (7 ด้าน) → ประเมินได้มากกว่า 1 ครั้ง/ด้าน
--  คะแนนรายด้าน (comp_*) = ค่าเฉลี่ยของหัวข้อในด้านนั้น (ปัดเป็น 1-3) เพื่อให้รายงานเดิมใช้ต่อได้
--  ใช้สิทธิ์ academic.grades เดิม · นำเข้าหลัง 01-52 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS kg_indicators (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain      VARCHAR(30) NOT NULL,           -- comp_physical .. comp_creative
  title       VARCHAR(300) NOT NULL,          -- หัวข้อประเมินที่ครูกำหนด
  sort_order  INT NOT NULL DEFAULT 0,
  active      TINYINT(1) NOT NULL DEFAULT 1,
  created_by  BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_kg_ind_domain (domain, active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kg_indicator_scores (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id   BIGINT UNSIGNED NOT NULL,
  semester_id  BIGINT UNSIGNED NOT NULL,
  indicator_id BIGINT UNSIGNED NOT NULL,
  value        TINYINT NOT NULL DEFAULT 0,     -- 0=ยังไม่ประเมิน, 1-3
  updated_by   BIGINT UNSIGNED NULL,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kg_ind_score (student_id, semester_id, indicator_id),
  KEY idx_kg_ind_score_ind (indicator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- จบ 53
