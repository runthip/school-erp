-- ==================================================================
--  46_sar_kindergarten.sql — SAR มาตรฐานการศึกษา ระดับปฐมวัย (11 มาตรฐาน / 100 คะแนน)
--  รายงานการประเมินคุณภาพภายในระดับปฐมวัย · อยู่ในงานบริหาร
--  sar_kg = หัวรายงาน (1 ปีการศึกษา) · sar_kg_scores = คะแนนรายตัวบ่งชี้
--  สิทธิ์ qa.kindergarten → super_admin (wildcard) + ผอ./รองผอ./หัวหน้าวิชาการ
--  นำเข้าหลัง 01-45 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sar_kg (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id  BIGINT UNSIGNED NOT NULL,
  summary_note      VARCHAR(1000) NULL,
  created_by        BIGINT UNSIGNED NULL,
  updated_by        BIGINT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sar_kg_year (academic_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sar_kg_scores (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sar_id    BIGINT UNSIGNED NOT NULL,
  std_no    TINYINT UNSIGNED NOT NULL,
  ind_no    TINYINT UNSIGNED NOT NULL,
  total     INT NOT NULL DEFAULT 0,   -- จำนวนผู้ถูกประเมินทั้งหมด (นักเรียน/ครู) — มฐ.1-5
  n5        INT NOT NULL DEFAULT 0,
  n4        INT NOT NULL DEFAULT 0,
  n3        INT NOT NULL DEFAULT 0,
  n2        INT NOT NULL DEFAULT 0,
  n1        INT NOT NULL DEFAULT 0,
  lvl       TINYINT NOT NULL DEFAULT 0, -- ระดับที่ได้ (1-5) สำหรับ มฐ.6-11
  evidence  VARCHAR(1000) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sar_kg_score (sar_id, std_no, ind_no),
  KEY idx_sar_kg_score_sar (sar_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- สิทธิ์: qa.kindergarten (SAR ระดับปฐมวัย) ----------
INSERT INTO permissions (code, name, module)
  SELECT 'qa.kindergarten', 'SAR มาตรฐานการศึกษา ระดับปฐมวัย', 'admin'
  WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='qa.kindergarten');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='qa.kindergarten'
    AND r.code IN ('super_admin','director','deputy_director','head_academic');

-- จบ 46
