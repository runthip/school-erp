-- ==================================================================
--  19_budget_ledger.sql — บัญชีคุมงบประมาณกลาง (Single Source of Truth)
--  ทุกการตัดงบ/คืนงบจากทุกโมดูล (ใบเบิก/เงินยืม/จัดซื้อ) บันทึกที่นี่ที่เดียว
--  นำเข้าหลัง 01-18
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- สมุดบัญชีคุมงบ: ทุกความเคลื่อนไหวของยอดเงิน
CREATE TABLE IF NOT EXISTS budget_ledger (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  txn_date     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source_type  ENUM('request','advance','po','manual') NOT NULL,
  source_id    BIGINT UNSIGNED DEFAULT NULL,
  source_no    VARCHAR(40) DEFAULT NULL,          -- เลขที่เอกสารต้นทาง (บง-/ยม-/PO)
  direction    ENUM('deduct','refund') NOT NULL,  -- ตัดงบ / คืนงบ
  amount       DECIMAL(14,2) NOT NULL,            -- จำนวนเงินบวกเสมอ
  budget_id    BIGINT UNSIGNED DEFAULT NULL,
  project_id   BIGINT UNSIGNED DEFAULT NULL,
  activity_id  BIGINT UNSIGNED DEFAULT NULL,
  note         VARCHAR(255) DEFAULT NULL,
  created_by   BIGINT UNSIGNED DEFAULT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_bl_source (source_type, source_id),
  KEY ix_bl_project (project_id),
  KEY ix_bl_date (txn_date),
  CONSTRAINT fk_bl_budget   FOREIGN KEY (budget_id)   REFERENCES budgets(id)            ON DELETE SET NULL,
  CONSTRAINT fk_bl_project  FOREIGN KEY (project_id)  REFERENCES projects(id)           ON DELETE SET NULL,
  CONSTRAINT fk_bl_activity FOREIGN KEY (activity_id) REFERENCES project_activities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่มสถานะ 'cancelled' ให้ใบเบิกงบ (เพื่อยกเลิก+คืนเงิน)
SET @c := (SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema='school_erp' AND table_name='budget_requests'
    AND column_name='status' AND column_type LIKE '%cancelled%');
SET @s := IF(@c=0,
  "ALTER TABLE budget_requests MODIFY status ENUM('pending','approved','rejected','paid','cancelled') NOT NULL DEFAULT 'pending'",
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- เพิ่มคอลัมน์ยอดคืนสะสมให้ใบเบิก (ตรวจสอบง่าย)
SET @c := (SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema='school_erp' AND table_name='budget_requests' AND column_name='refunded_amount');
SET @s := IF(@c=0,
  'ALTER TABLE budget_requests ADD COLUMN refunded_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER amount',
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- สิทธิ์บัญชีคุมงบ
INSERT IGNORE INTO permissions (code, name, module) VALUES
  ('budget.ledger', 'บัญชีคุมงบประมาณ', 'budget');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='budget.ledger'
    AND r.code IN ('director','deputy_director','head_budget','finance_officer','auditor','account_officer');

-- ===== ย้อนหลัง: สร้างรายการ ledger จากเอกสารที่จ่ายแล้ว (idempotent) =====
-- ใบเบิกที่จ่ายแล้ว
INSERT INTO budget_ledger (txn_date, source_type, source_id, source_no, direction, amount, budget_id, project_id, activity_id, note)
  SELECT COALESCE(br.paid_at, br.created_at), 'request', br.id, br.request_no, 'deduct', br.amount,
         p.budget_id, br.project_id, br.activity_id, 'ยกยอดจากระบบเดิม'
  FROM budget_requests br LEFT JOIN projects p ON p.id=br.project_id
  WHERE br.status='paid'
    AND NOT EXISTS (SELECT 1 FROM budget_ledger bl WHERE bl.source_type='request' AND bl.source_id=br.id);

-- เงินยืมที่จ่ายแล้ว
INSERT INTO budget_ledger (txn_date, source_type, source_id, source_no, direction, amount, budget_id, project_id, activity_id, note)
  SELECT COALESCE(ca.paid_at, ca.created_at), 'advance', ca.id, ca.advance_no, 'deduct', ca.amount,
         p.budget_id, ca.project_id, ca.activity_id, 'ยกยอดจากระบบเดิม'
  FROM cash_advances ca LEFT JOIN projects p ON p.id=ca.project_id
  WHERE ca.status IN ('paid','cleared')
    AND NOT EXISTS (SELECT 1 FROM budget_ledger bl WHERE bl.source_type='advance' AND bl.source_id=ca.id AND bl.direction='deduct');

-- ใบสั่งซื้อที่จ่ายแล้ว
INSERT INTO budget_ledger (txn_date, source_type, source_id, source_no, direction, amount, budget_id, project_id, activity_id, note)
  SELECT COALESCE(po.paid_date, po.created_at), 'po', po.id, po.po_number, 'deduct', po.total_amount,
         pr.budget_id, pr.project_id, pr.activity_id, 'ยกยอดจากระบบเดิม'
  FROM purchase_orders po LEFT JOIN purchase_requests pr ON pr.id=po.purchase_request_id
  WHERE po.status='paid'
    AND NOT EXISTS (SELECT 1 FROM budget_ledger bl WHERE bl.source_type='po' AND bl.source_id=po.id);

-- จบ 19
