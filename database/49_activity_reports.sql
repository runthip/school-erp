-- ==================================================================
--  49_activity_reports.sql — รายงานผลการดำเนินงานกิจกรรม/การแข่งขันของสถานศึกษา
--  ครูทุกคนเพิ่ม/ดูได้ (activity.report) · อยู่ในงานฝ่ายบริหาร
--  ใช้ประกอบ SAR / รายงานประจำปี · แนบรูป/เกียรติบัตรได้
--  นำเข้าหลัง 01-48 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS activity_reports (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title          VARCHAR(400) NOT NULL,                 -- ชื่อกิจกรรม/การแข่งขัน
  category       VARCHAR(30) NOT NULL DEFAULT 'academic',
  date_start     DATE NULL,                             -- วันที่จัด
  date_end       DATE NULL,
  location       VARCHAR(300) NULL,                     -- สถานที่
  organizer      VARCHAR(300) NULL,                     -- หน่วยงานผู้จัด
  coaches        VARCHAR(500) NULL,                     -- ผู้ควบคุมทีม/ครูที่ปรึกษา
  result_summary VARCHAR(500) NULL,                     -- ผลการแข่งขัน/รางวัลโดยรวม
  summary        TEXT NULL,                             -- สรุปผลการดำเนินงาน
  year_be        SMALLINT NULL,
  created_by     BIGINT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_reports_cat (category, date_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_participants (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  report_id   BIGINT UNSIGNED NOT NULL,
  name        VARCHAR(200) NOT NULL,                    -- ชื่อ-สกุลผู้เข้าแข่งขัน
  grade_level VARCHAR(60) NULL,                         -- ระดับชั้น
  event_type  VARCHAR(200) NULL,                        -- ประเภท/รายการที่แข่ง
  award       VARCHAR(200) NULL,                        -- ผล/รางวัลรายบุคคล
  sort_order  INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_activity_participants_report (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_files (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  report_id     BIGINT UNSIGNED NOT NULL,
  kind          VARCHAR(20) NOT NULL DEFAULT 'photo',   -- photo | certificate
  file_path     VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type     VARCHAR(120) NULL,
  size_bytes    INT NOT NULL DEFAULT 0,
  caption       VARCHAR(300) NULL,
  uploaded_by   BIGINT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_files_report (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- สิทธิ์: activity.report (ครูทุกคนเพิ่ม/ดูได้) ----------
INSERT INTO permissions (code, name, module)
  SELECT 'activity.report', 'รายงานผลกิจกรรม/การแข่งขัน', 'admin'
  WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='activity.report');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='activity.report'
    AND r.code IN ('super_admin','director','deputy_director','head_academic',
                   'head_budget','head_hr','head_general','finance_officer',
                   'inventory_officer','clerk','teacher','advisor');

-- จบ 49
