-- ==================================================================
--  35_travel_claims.sql — คลังแบบฟอร์ม E-Office: ใบเบิกค่าใช้จ่ายเดินทางไปราชการ (แบบ 8708)
--  ครูกรอกเอง ลงตำแหน่งตามการกรอก เก็บข้อมูล + รวบรวมสถิติ/ยอดรวม
--  นำเข้าหลัง 01-34 · รันซ้ำได้ปลอดภัย (idempotent)
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS travel_claims (
  id                BIGINT AUTO_INCREMENT PRIMARY KEY,
  claim_no          VARCHAR(40)  NULL,
  claim_date        DATE         NULL,
  office_place      VARCHAR(255) NULL,                 -- ที่ทำการ
  addressee         VARCHAR(255) NULL,                 -- เรียน
  order_no          VARCHAR(120) NULL,                 -- ตามคำสั่ง/บันทึกที่
  order_date        DATE         NULL,                 -- ลงวันที่ (ของคำสั่ง)
  traveler_id       BIGINT       NULL,                 -- personnel id (ถ้าเลือกจากระบบ)
  traveler_name     VARCHAR(200) NULL,                 -- ชื่อผู้เดินทาง (ผู้ขอรับเงิน)
  traveler_position VARCHAR(200) NULL,                 -- ตำแหน่ง (ลงตามการกรอก)
  affiliation       VARCHAR(255) NULL,                 -- สังกัด
  companions        TEXT         NULL,                 -- พร้อมด้วย
  purpose           TEXT         NULL,                 -- เดินทางไปปฏิบัติราชการ (เรื่อง/สถานที่)
  depart_from       ENUM('home','office','thailand') NOT NULL DEFAULT 'office',
  depart_at         DATETIME     NULL,
  return_to         ENUM('home','office','thailand') NOT NULL DEFAULT 'office',
  return_at         DATETIME     NULL,
  total_days        DECIMAL(5,1) NOT NULL DEFAULT 0,
  total_hours       DECIMAL(5,1) NOT NULL DEFAULT 0,
  perdiem_type      VARCHAR(120) NULL,                 -- ประเภทเบี้ยเลี้ยง
  perdiem_days      DECIMAL(5,1) NOT NULL DEFAULT 0,
  perdiem_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
  lodging_type      VARCHAR(120) NULL,
  lodging_days      DECIMAL(5,1) NOT NULL DEFAULT 0,
  lodging_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
  transport_detail  VARCHAR(255) NULL,                 -- ค่าพาหนะ (รวมค่าชดเชยน้ำมัน)
  transport_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
  other_detail      VARCHAR(255) NULL,
  other_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,  -- รวมเงินทั้งสิ้น
  receipts_count    INT          NOT NULL DEFAULT 0,   -- จำนวนหลักฐาน (ฉบับ)
  loan_no           VARCHAR(80)  NULL,                 -- สัญญาเงินยืมเลขที่
  loan_date         DATE         NULL,
  loan_amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
  note              TEXT         NULL,                 -- หมายเหตุ
  status            ENUM('draft','submitted','approved','paid') NOT NULL DEFAULT 'draft',
  approver_id       BIGINT       NULL,                 -- personnel id ผู้อนุมัติ
  approved_at       DATETIME     NULL,
  created_by        BIGINT       NULL,
  updated_by        BIGINT       NULL,
  created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_traveler (traveler_id),
  KEY idx_status (status),
  KEY idx_claim_date (claim_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สิทธิ์: เบิกค่าเดินทางไปราชการ
INSERT INTO permissions (code, name, module)
  SELECT 'travel.claim', 'เบิกค่าเดินทางไปราชการ', 'general'
  WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='travel.claim');

-- ผูกสิทธิ์: บุคลากรทุกกลุ่มที่อาจเดินทางไปราชการ + ผู้บริหาร/การเงิน/ธุรการ
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='travel.claim'
    AND r.code IN ('super_admin','director','deputy_director',
                   'head_academic','head_budget','head_hr','head_general',
                   'finance_officer','clerk','teacher','advisor');

-- จบ 35
