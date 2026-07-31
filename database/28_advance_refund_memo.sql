-- ==================================================================
--  28_advance_refund_memo.sql — บันทึกข้อความขอคืนเงินตามสัญญายืมเงินราชการ
--  * สร้างอัตโนมัติเมื่อล้างหนี้เงินยืมแล้วมีเงินคืน (ยืม − ใช้จริง > 0)
--  * เรียกดู/แก้ไขได้เฉพาะฝ่ายการเงิน/งบประมาณ (สิทธิ์ budget.refund_memo)
--  นำเข้าหลัง 01-27 · รันซ้ำได้ปลอดภัย (idempotent)
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

-- ===== ตารางบันทึกข้อความขอคืนเงิน =====
CREATE TABLE IF NOT EXISTS advance_refund_memos (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cash_advance_id BIGINT UNSIGNED NOT NULL,
  memo_no         VARCHAR(40) DEFAULT NULL,
  memo_date       DATE DEFAULT NULL,
  borrowed_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  used_amount     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  refund_amount   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  refund_method   ENUM('cash','transfer') NOT NULL DEFAULT 'cash',
  detail          TEXT DEFAULT NULL,
  receiver_id     BIGINT UNSIGNED DEFAULT NULL COMMENT 'ผู้รับเงินคืน (เจ้าหน้าที่การเงิน)',
  status          ENUM('draft','confirmed') NOT NULL DEFAULT 'draft',
  created_by      BIGINT UNSIGNED DEFAULT NULL,
  updated_by      BIGINT UNSIGNED DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_refund_advance (cash_advance_id),
  KEY idx_refund_advance (cash_advance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== สิทธิ์: เฉพาะฝ่ายการเงิน/งบประมาณ =====
INSERT INTO permissions (code, name, module)
  SELECT 'budget.refund_memo', 'บันทึกข้อความขอคืนเงินยืม', 'budget'
  WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='budget.refund_memo');

-- ผูกสิทธิ์ให้ ผู้ดูแลระบบ + หัวหน้าฝ่ายแผนและงบประมาณ + เจ้าหน้าที่การเงิน
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='budget.refund_memo'
    AND r.code IN ('super_admin','head_budget','finance_officer');

-- จบ 28
