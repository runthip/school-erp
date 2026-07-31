-- ==================================================================
--  47_pa_topics.sql — หัวข้อ PA (ข้อตกลงในการพัฒนางาน) รายบุคคล + แนบไฟล์ PDF
--  บุคลากรตั้งหัวข้อ PA ของตนเอง แนบ PDF ประกอบ เพื่อใช้ประกอบการประเมิน
--  ผู้ประเมิน (hr.evaluation) เรียกดู/ดาวน์โหลดของทุกคนได้ในหน้า PA/วิทยฐานะ
--  สิทธิ์ pa.own → บุคลากรทุกฝ่าย · นำเข้าหลัง 01-46 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pa_topics (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  personnel_id  BIGINT UNSIGNED NOT NULL,
  year_be       SMALLINT NOT NULL,
  round         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  category      VARCHAR(40) NOT NULL DEFAULT 'learning',
  title         VARCHAR(300) NOT NULL,
  description   TEXT NULL,
  created_by    BIGINT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pa_topics_person (personnel_id, year_be, round)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pa_topic_files (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_id      BIGINT UNSIGNED NOT NULL,
  file_path     VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type     VARCHAR(120) NULL,
  size_bytes    INT NOT NULL DEFAULT 0,
  uploaded_by   BIGINT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pa_topic_files_topic (topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- สิทธิ์: pa.own (จัดการ PA ของตนเอง) ----------
INSERT INTO permissions (code, name, module)
  SELECT 'pa.own', 'จัดการ PA ของตนเอง (หัวข้อ+ไฟล์)', 'hr'
  WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='pa.own');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='pa.own'
    AND r.code IN ('super_admin','director','deputy_director','head_academic',
                   'head_budget','head_hr','head_general','finance_officer',
                   'inventory_officer','clerk','teacher','advisor');

-- จบ 47
