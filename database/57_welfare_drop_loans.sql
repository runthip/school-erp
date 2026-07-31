-- ==================================================================
--  57_welfare_drop_loans.sql — ตัดระบบเงินยืม/สัญญายืมเงิน ออกจากร้านค้าสวัสดิการ
--  คงไว้เฉพาะบัญชีรายรับ-รายจ่าย (welfare_transactions)
--  นำเข้าหลัง 01-56 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

-- ล้างรายการบัญชีที่เคยผูกกับเงินยืม (ถ้ามี) ก่อนถอดคอลัมน์
DELETE FROM welfare_transactions WHERE category IN ('loan_out','loan_in');

ALTER TABLE welfare_transactions
  DROP COLUMN IF EXISTS loan_id,
  DROP COLUMN IF EXISTS auto_flag;

DROP TABLE IF EXISTS welfare_loan_payments;
DROP TABLE IF EXISTS welfare_loans;

-- จบ 57
