-- ==================================================================
--  56_welfare_shop.sql — งานร้านค้าสวัสดิการ (ฝ่ายบริหารทั่วไป)
--  บัญชีรายรับ-รายจ่าย + รายการยืมเงิน (มีสัญญายืมเงิน) + การชำระคืน
--  เงินยืม/รับคืน จะบันทึกเข้าบัญชีรายรับ-รายจ่ายอัตโนมัติ (ยอดคงเหลือถูกต้องเสมอ)
--  สิทธิ์ general.welfare · นำเข้าหลัง 01-55 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

-- ---------- รายการยืมเงิน (สัญญายืมเงินร้านค้าสวัสดิการ) ----------
CREATE TABLE IF NOT EXISTS welfare_loans (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  loan_no           VARCHAR(40)  NULL,                     -- เลขที่สัญญา
  borrower_type     VARCHAR(10)  NOT NULL DEFAULT 'personnel', -- personnel|other
  personnel_id      BIGINT UNSIGNED NULL,
  borrower_name     VARCHAR(200) NULL,                     -- กรณีบุคคลภายนอก/สำรองชื่อ
  borrower_position VARCHAR(150) NULL,
  id_card           VARCHAR(30)  NULL,
  phone             VARCHAR(40)  NULL,
  address           VARCHAR(300) NULL,
  amount            DECIMAL(14,2) NOT NULL DEFAULT 0,      -- จำนวนเงินที่ยืม
  purpose           VARCHAR(400) NULL,                     -- วัตถุประสงค์
  loan_date         DATE NOT NULL,                         -- วันที่ยืม
  due_date          DATE NULL,                             -- กำหนดชำระคืน
  guarantor_name    VARCHAR(200) NULL,                     -- ผู้ค้ำประกัน
  witness1          VARCHAR(200) NULL,                     -- พยาน 1
  witness2          VARCHAR(200) NULL,                     -- พยาน 2
  approver_name     VARCHAR(200) NULL,                     -- ผู้อนุมัติ
  responsible_id    BIGINT UNSIGNED NULL,                  -- ผู้รับผิดชอบ (บุคลากร)
  status            VARCHAR(12) NOT NULL DEFAULT 'open',   -- open|partial|paid
  note              VARCHAR(500) NULL,
  created_by        BIGINT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wl_status (status, loan_date),
  KEY idx_wl_person (personnel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- การชำระคืนเงินยืม ----------
CREATE TABLE IF NOT EXISTS welfare_loan_payments (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  loan_id    BIGINT UNSIGNED NOT NULL,
  pay_date   DATE NOT NULL,
  amount     DECIMAL(14,2) NOT NULL DEFAULT 0,
  note       VARCHAR(300) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wlp_loan (loan_id, pay_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- บัญชีรายรับ-รายจ่ายร้านค้าสวัสดิการ ----------
CREATE TABLE IF NOT EXISTS welfare_transactions (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tx_date        DATE NOT NULL,
  tx_type        VARCHAR(10) NOT NULL DEFAULT 'income',   -- income|expense
  category       VARCHAR(30) NOT NULL DEFAULT 'sale',     -- sale|purchase|utility|welfare|loan_out|loan_in|other
  description    VARCHAR(400) NOT NULL,
  amount         DECIMAL(14,2) NOT NULL DEFAULT 0,
  ref_no         VARCHAR(60)  NULL,                       -- เลขที่เอกสาร/ใบเสร็จ
  responsible_id BIGINT UNSIGNED NULL,                    -- ผู้รับผิดชอบ (บุคลากร)
  note           VARCHAR(500) NULL,                       -- หมายเหตุ
  loan_id        BIGINT UNSIGNED NULL,                    -- อ้างอิงรายการยืม (ถ้าเกิดจากเงินยืม/รับคืน)
  auto_flag      TINYINT(1) NOT NULL DEFAULT 0,           -- 1 = ระบบสร้างจากเงินยืม/รับคืน
  created_by     BIGINT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wt_date (tx_date, id),
  KEY idx_wt_type (tx_type, category),
  KEY idx_wt_loan (loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- สิทธิ์: general.welfare ----------
INSERT INTO permissions (code, name, module)
  SELECT 'general.welfare', 'ร้านค้าสวัสดิการ (รายรับ-รายจ่าย/เงินยืม)', 'general'
  WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='general.welfare');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='general.welfare'
    AND r.code IN ('super_admin','director','deputy_director','head_general','clerk','finance_officer');

-- จบ 56
