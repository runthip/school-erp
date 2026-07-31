-- ==================================================================
--  15_planning.sql — แผนงานโครงการ: กิจกรรมย่อย + เบิกงบ + ยืมเงิน + อนุมัติหลายระดับ
--  นำเข้าหลัง 01-14
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- ---- กิจกรรมย่อยของโครงการ ----
CREATE TABLE IF NOT EXISTS project_activities (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id     BIGINT UNSIGNED NOT NULL,
  name           VARCHAR(255) NOT NULL,
  budget_amount  DECIMAL(14,2) NOT NULL DEFAULT 0,
  spent_amount   DECIMAL(14,2) NOT NULL DEFAULT 0,
  start_date     DATE DEFAULT NULL,
  end_date       DATE DEFAULT NULL,
  status         ENUM('planned','ongoing','completed','cancelled') NOT NULL DEFAULT 'planned',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pa_project (project_id),
  CONSTRAINT fk_pa_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- คำขอเบิกงบ (อนุมัติ 3 ระดับ) ----
CREATE TABLE IF NOT EXISTS budget_requests (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_no     VARCHAR(30) NOT NULL,
  project_id     BIGINT UNSIGNED DEFAULT NULL,
  activity_id    BIGINT UNSIGNED DEFAULT NULL,
  requester_id   BIGINT UNSIGNED DEFAULT NULL,       -- users.id
  amount         DECIMAL(14,2) NOT NULL,
  purpose        VARCHAR(500) NOT NULL,
  status         ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  current_level  TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- 1 หัวหน้าฝ่าย → 2 รองผอ. → 3 ผอ.
  paid_at        DATETIME DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_br_no (request_no),
  KEY ix_br_project (project_id),
  KEY ix_br_status (status),
  CONSTRAINT fk_br_project  FOREIGN KEY (project_id)  REFERENCES projects(id)          ON DELETE SET NULL,
  CONSTRAINT fk_br_activity FOREIGN KEY (activity_id) REFERENCES project_activities(id) ON DELETE SET NULL,
  CONSTRAINT fk_br_user     FOREIGN KEY (requester_id) REFERENCES users(id)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- บันทึกการอนุมัติแต่ละระดับ (ใช้ร่วมเบิกงบ/ยืมเงิน) ----
CREATE TABLE IF NOT EXISTS approval_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ref_type    ENUM('budget_request','cash_advance') NOT NULL,
  ref_id      BIGINT UNSIGNED NOT NULL,
  level       TINYINT UNSIGNED NOT NULL,
  level_name  VARCHAR(80) NOT NULL,
  approver_id BIGINT UNSIGNED DEFAULT NULL,
  action      ENUM('approved','rejected') NOT NULL,
  comment     VARCHAR(300) DEFAULT NULL,
  acted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_al_ref (ref_type, ref_id),
  CONSTRAINT fk_al_user FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ยืมเงินราชการ (อนุมัติ 3 ระดับ + ล้างหนี้) ----
CREATE TABLE IF NOT EXISTS cash_advances (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  advance_no     VARCHAR(30) NOT NULL,
  borrower_id    BIGINT UNSIGNED DEFAULT NULL,        -- users.id
  project_id     BIGINT UNSIGNED DEFAULT NULL,
  activity_id    BIGINT UNSIGNED DEFAULT NULL,
  amount         DECIMAL(14,2) NOT NULL,
  purpose        VARCHAR(500) NOT NULL,
  due_date       DATE DEFAULT NULL,                   -- กำหนดส่งใช้
  status         ENUM('pending','approved','rejected','paid','cleared') NOT NULL DEFAULT 'pending',
  current_level  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  paid_at        DATETIME DEFAULT NULL,
  cleared_amount DECIMAL(14,2) NOT NULL DEFAULT 0,    -- ยอดล้างหนี้ (ใช้จริง)
  cleared_at     DATETIME DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ca_no (advance_no),
  KEY ix_ca_status (status),
  CONSTRAINT fk_ca_user     FOREIGN KEY (borrower_id) REFERENCES users(id)             ON DELETE SET NULL,
  CONSTRAINT fk_ca_project  FOREIGN KEY (project_id)  REFERENCES projects(id)          ON DELETE SET NULL,
  CONSTRAINT fk_ca_activity FOREIGN KEY (activity_id) REFERENCES project_activities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- สิทธิ์ ----
INSERT IGNORE INTO permissions (code, name, module) VALUES
 ('budget.planning', 'แผนงาน/โครงการ/เบิกงบ/ยืมเงิน', 'budget');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r, permissions p
  WHERE p.code='budget.planning' AND r.code IN ('director','deputy_director','head_budget','account_officer','supply_officer');

-- ---- ตัวอย่างกิจกรรมย่อย ----
INSERT INTO project_activities (project_id, name, budget_amount, status)
 SELECT id, CONCAT('กิจกรรมที่ 1 ของ ', name), ROUND(budget_amount*0.6,2), 'ongoing' FROM projects LIMIT 2;
INSERT INTO project_activities (project_id, name, budget_amount, status)
 SELECT id, CONCAT('กิจกรรมที่ 2 ของ ', name), ROUND(budget_amount*0.4,2), 'planned' FROM projects LIMIT 2;

-- จบ 15
