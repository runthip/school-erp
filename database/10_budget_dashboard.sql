-- ==================================================================
--  10_budget_dashboard.sql — ตัดงบเข้าโครงการ/ฝ่ายอัตโนมัติ + Dashboard
--  นำเข้าหลังจาก 01-09
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- ---- ยอดเบิกจ่ายสะสมของโครงการ (ตัดอัตโนมัติเมื่อ PO ถูกเบิกจ่าย) ----
ALTER TABLE projects
  ADD COLUMN spent_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER budget_amount;

-- ---- ผูก PR ตัวอย่างเข้าโครงการ ----
UPDATE purchase_requests SET project_id=3 WHERE pr_number='PR-2568-002';  -- จ้างปรับปรุงไฟฟ้า → โครงการปรับปรุงอาคาร (ฝ่ายบริหารทั่วไป)
UPDATE purchase_requests SET project_id=1 WHERE pr_number='PR-2568-001';  -- วัสดุ → โครงการยกระดับผลสัมฤทธิ์ (ฝ่ายวิชาการ)

-- ---- ตัวอย่างการเบิกจ่ายย้อนหลัง (เพื่อให้ Dashboard มีข้อมูลกราฟ) ----
-- PR เพิ่มเติม (อนุมัติ+ออก PO+เบิกแล้ว)
INSERT INTO purchase_requests (school_id, pr_number, budget_id, project_id, request_type, method, requester_id, request_date, total_amount, reason, status, approved_by, approved_at) VALUES
 (1,'PR-2568-090',2,1,'purchase','specific',7,CURDATE()-INTERVAL 70 DAY,42000.00,'สื่อการสอนโครงการยกระดับผลสัมฤทธิ์','po_created',(SELECT id FROM users WHERE username='director'),NOW()-INTERVAL 68 DAY),
 (1,'PR-2568-091',1,3,'hire','specific',8,CURDATE()-INTERVAL 40 DAY,98000.00,'จ้างเหมาปรับปรุงระบบไฟฟ้า (งวด 1)','po_created',(SELECT id FROM users WHERE username='director'),NOW()-INTERVAL 38 DAY),
 (1,'PR-2568-092',3,2,'purchase','specific',5,CURDATE()-INTERVAL 12 DAY,36500.00,'วัสดุค่ายวิทยาศาสตร์','po_created',(SELECT id FROM users WHERE username='director'),NOW()-INTERVAL 10 DAY);

INSERT INTO purchase_orders (school_id, po_number, purchase_request_id, vendor_id, order_date, total_amount, vat_amount, status, committee, received_date, paid_date) VALUES
 (1,'PO-2568-090',(SELECT id FROM purchase_requests WHERE pr_number='PR-2568-090'),1,CURDATE()-INTERVAL 65 DAY,42000.00,2940.00,'paid','นายประสิทธิ์ ใจงาม, นางสาวกัลยา สว่างศรี, นายอนุชา พงษ์พันธ์',CURDATE()-INTERVAL 62 DAY,CURDATE()-INTERVAL 60 DAY),
 (1,'PO-2568-091',(SELECT id FROM purchase_requests WHERE pr_number='PR-2568-091'),2,CURDATE()-INTERVAL 35 DAY,98000.00,6860.00,'paid','นางวิไลวรรณ ทองสุข, นายธนวัฒน์ มั่นคง, นางสุดารัตน์ คงเจริญ',CURDATE()-INTERVAL 32 DAY,CURDATE()-INTERVAL 30 DAY),
 (1,'PO-2568-092',(SELECT id FROM purchase_requests WHERE pr_number='PR-2568-092'),3,CURDATE()-INTERVAL 8 DAY,36500.00,2555.00,'paid','นายอนุชา พงษ์พันธ์, นางสาวปิยะนุช แก้วมณี, นายประสิทธิ์ ใจงาม',CURDATE()-INTERVAL 5 DAY,CURDATE()-INTERVAL 3 DAY);

INSERT INTO purchase_order_items (purchase_order_id, item_name, quantity, unit, unit_price, amount) VALUES
 ((SELECT id FROM purchase_orders WHERE po_number='PO-2568-090'),'ชุดสื่อการสอนคณิตศาสตร์',20,'ชุด',2100.00,42000.00),
 ((SELECT id FROM purchase_orders WHERE po_number='PO-2568-091'),'จ้างเหมาปรับปรุงระบบไฟฟ้า งวด 1',1,'งาน',98000.00,98000.00),
 ((SELECT id FROM purchase_orders WHERE po_number='PO-2568-092'),'วัสดุทดลองวิทยาศาสตร์',73,'ชุด',500.00,36500.00);

-- ---- ตัดยอดสะสมให้ตรงกับการเบิกจ่ายตัวอย่าง ----
UPDATE projects SET spent_amount = spent_amount + 42000 WHERE id=1;
UPDATE projects SET spent_amount = spent_amount + 98000 WHERE id=3;
UPDATE projects SET spent_amount = spent_amount + 36500 WHERE id=2;
UPDATE budgets  SET used_amount  = used_amount  + 42000 WHERE id=2;
UPDATE budgets  SET used_amount  = used_amount  + 98000 WHERE id=1;
UPDATE budgets  SET used_amount  = used_amount  + 36500 WHERE id=3;

-- จบ 10
