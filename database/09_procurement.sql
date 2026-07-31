-- ==================================================================
--  09_procurement.sql — จัดซื้อจัดจ้างตามระเบียบกระทรวงการคลังฯ พ.ศ. 2560
--  นำเข้าหลังจาก 01-08
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- ---- วิธีจัดหา + สายอนุมัติ ใน PR ----
ALTER TABLE purchase_requests
  ADD COLUMN method ENUM('specific','selection','e_bidding') NOT NULL DEFAULT 'specific' AFTER request_type,  -- เฉพาะเจาะจง/คัดเลือก/e-bidding
  ADD COLUMN reason VARCHAR(500) DEFAULT NULL AFTER total_amount,          -- เหตุผลความจำเป็น
  ADD COLUMN approved_by BIGINT UNSIGNED DEFAULT NULL AFTER status,        -- users
  ADD COLUMN approved_at DATETIME DEFAULT NULL AFTER approved_by,
  ADD COLUMN decision_note VARCHAR(500) DEFAULT NULL AFTER approved_at;

-- ---- ทะเบียนผู้ขาย/ผู้รับจ้าง ----
CREATE TABLE IF NOT EXISTS vendors (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id   BIGINT UNSIGNED NOT NULL,
  name        VARCHAR(255) NOT NULL,
  tax_id      VARCHAR(20)  DEFAULT NULL,
  address     VARCHAR(500) DEFAULT NULL,
  phone       VARCHAR(30)  DEFAULT NULL,
  email       VARCHAR(120) DEFAULT NULL,
  contact_name VARCHAR(150) DEFAULT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_vendor_name (name),
  CONSTRAINT fk_vendor_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ตรวจรับ + เบิกจ่าย ใน PO ----
ALTER TABLE purchase_orders
  ADD COLUMN vendor_id BIGINT UNSIGNED DEFAULT NULL AFTER purchase_request_id,
  ADD COLUMN committee VARCHAR(600) DEFAULT NULL AFTER status,           -- คณะกรรมการตรวจรับ (ชื่อคั่นด้วย ,)
  ADD COLUMN received_date DATE DEFAULT NULL AFTER committee,
  ADD COLUMN receive_note VARCHAR(500) DEFAULT NULL AFTER received_date,
  ADD COLUMN paid_date DATE DEFAULT NULL AFTER receive_note,
  ADD CONSTRAINT fk_po_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL;

-- ---- สิทธิ์เพิ่ม: อนุมัติจัดซื้อ ----
INSERT INTO permissions (code, name, module) VALUES
 ('budget.approve','อนุมัติขอซื้อ/ขอจ้าง','budget')
ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r, permissions p
  WHERE p.code='budget.approve' AND r.code IN ('director','deputy_director','head_budget');

-- ================= ข้อมูลตัวอย่าง =================
INSERT INTO vendors (school_id, name, tax_id, address, phone, contact_name) VALUES
 (1,'บริษัท ศึกษาภัณฑ์พาณิชย์ จำกัด','0105536000011','กรุงเทพมหานคร','02-514-4000','คุณสมพร ขายดี'),
 (1,'หจก. รุ่งเรืองครุภัณฑ์','0103545000222','นนทบุรี','02-999-8888','คุณวิชัย รุ่งเรือง'),
 (1,'ร้านคลังเครื่องเขียน','1409900333444','ขอนแก่น','043-222-333','คุณมาลี ใจดี');

-- PR ตัวอย่าง (เฉพาะเจาะจง ≤ 500,000)
INSERT INTO purchase_requests (school_id, pr_number, budget_id, request_type, method, requester_id, request_date, total_amount, reason, status) VALUES
 (1,'PR-2568-001',1,'purchase','specific',7,CURDATE() - INTERVAL 7 DAY,45600.00,'จัดซื้อวัสดุสำนักงานสำหรับภาคเรียนที่ 1','pending'),
 (1,'PR-2568-002',2,'hire','specific',8,CURDATE() - INTERVAL 5 DAY,98000.00,'จ้างเหมาปรับปรุงระบบไฟฟ้าห้องปฏิบัติการ','approved'),
 (1,'PR-2568-003',1,'purchase','e_bidding',7,CURDATE() - INTERVAL 2 DAY,750000.00,'จัดซื้อครุภัณฑ์คอมพิวเตอร์ 30 เครื่อง (เกิน 5 แสน ใช้ e-bidding)','draft');
UPDATE purchase_requests SET approved_by=(SELECT id FROM users WHERE username='director'), approved_at=NOW(), decision_note='อนุมัติตามเสนอ' WHERE pr_number='PR-2568-002';

INSERT INTO purchase_request_items (purchase_request_id, item_name, quantity, unit, unit_price, amount) VALUES
 ((SELECT id FROM purchase_requests WHERE pr_number='PR-2568-001'),'กระดาษ A4 80 แกรม',100,'รีม',120.00,12000.00),
 ((SELECT id FROM purchase_requests WHERE pr_number='PR-2568-001'),'หมึกพิมพ์เลเซอร์',12,'กล่อง',2800.00,33600.00),
 ((SELECT id FROM purchase_requests WHERE pr_number='PR-2568-002'),'จ้างเหมาปรับปรุงระบบไฟฟ้า',1,'งาน',98000.00,98000.00),
 ((SELECT id FROM purchase_requests WHERE pr_number='PR-2568-003'),'เครื่องคอมพิวเตอร์ตั้งโต๊ะ',30,'เครื่อง',25000.00,750000.00);

-- จบ 09
