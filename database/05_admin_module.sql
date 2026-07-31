-- ==================================================================
--  05_admin_module.sql  —  ฝ่ายบริหาร (KPI / อนุมัติ / หนังสือราชการ / E-Office / ปฏิทิน)
--  นำเข้าหลังจาก 01-04
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)
SET FOREIGN_KEY_CHECKS = 0;

-- ---- KPI โรงเรียน (เป้าหมายตามระดับการศึกษา) ----
CREATE TABLE IF NOT EXISTS kpis (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED DEFAULT NULL,
  education_level  ENUM('early_childhood','basic','all') NOT NULL DEFAULT 'basic',
  category         VARCHAR(80)   DEFAULT NULL,
  name             VARCHAR(255)  NOT NULL,
  unit             VARCHAR(40)   DEFAULT '%',
  target_value     DECIMAL(10,2) NOT NULL DEFAULT 0,
  actual_value     DECIMAL(10,2) NOT NULL DEFAULT 0,
  direction        ENUM('up','down') NOT NULL DEFAULT 'up',
  updated_by       BIGINT UNSIGNED DEFAULT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_kpi_level (education_level),
  CONSTRAINT fk_kpi_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- คำขออนุมัติออนไลน์ (ผู้ใช้บันทึกขอ → ผู้มีสิทธิ์อนุมัติ) ----
CREATE TABLE IF NOT EXISTS approval_requests (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     BIGINT UNSIGNED NOT NULL,
  request_no    VARCHAR(40)   DEFAULT NULL,
  requester_id  BIGINT UNSIGNED NOT NULL,          -- users
  request_type  ENUM('leave','travel','purchase','general') NOT NULL DEFAULT 'general',
  title         VARCHAR(255)  NOT NULL,
  detail        TEXT          DEFAULT NULL,
  amount        DECIMAL(14,2) DEFAULT NULL,
  approver_id   BIGINT UNSIGNED DEFAULT NULL,       -- users (ผู้อนุมัติที่กำหนด)
  status        ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  decision_note VARCHAR(500)  DEFAULT NULL,
  decided_at    DATETIME      DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ar_requester (requester_id),
  KEY ix_ar_approver (approver_id),
  KEY ix_ar_status (status),
  CONSTRAINT fk_ar_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_ar_req    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ar_app    FOREIGN KEY (approver_id)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ประวัติการเรียกดูหนังสือราชการ ----
CREATE TABLE IF NOT EXISTS document_views (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id  BIGINT UNSIGNED NOT NULL,
  user_id      BIGINT UNSIGNED DEFAULT NULL,
  department_id BIGINT UNSIGNED DEFAULT NULL,
  viewed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_dv_doc (document_id),
  CONSTRAINT fk_dv_doc  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_dv_user FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Template เอกสาร E-Office ----
CREATE TABLE IF NOT EXISTS document_templates (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    BIGINT UNSIGNED NOT NULL,
  code         VARCHAR(40)   NOT NULL,
  name         VARCHAR(255)  NOT NULL,
  doc_kind     ENUM('memo','announcement','order','letter') NOT NULL DEFAULT 'memo',
  has_garuda   TINYINT(1)    NOT NULL DEFAULT 1,
  body         MEDIUMTEXT    DEFAULT NULL,          -- เนื้อหา มี {{placeholder}}
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tpl (school_id, code),
  CONSTRAINT fk_tpl_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ปฏิทินโรงเรียน / ประชุมออนไลน์ ----
CREATE TABLE IF NOT EXISTS calendar_events (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    BIGINT UNSIGNED NOT NULL,
  title        VARCHAR(255)  NOT NULL,
  event_type   ENUM('academic','activity','meeting','holiday','exam') NOT NULL DEFAULT 'activity',
  event_date   DATE          NOT NULL,
  end_date     DATE          DEFAULT NULL,
  start_time   TIME          DEFAULT NULL,
  end_time     TIME          DEFAULT NULL,
  location     VARCHAR(200)  DEFAULT NULL,
  meeting_url  VARCHAR(255)  DEFAULT NULL,          -- ประชุมออนไลน์
  description  TEXT          DEFAULT NULL,
  created_by   BIGINT UNSIGNED DEFAULT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_cal_date (event_date),
  CONSTRAINT fk_cal_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ================= ข้อมูลตัวอย่าง =================

-- KPI (ปฐมวัย + ขั้นพื้นฐาน)
INSERT INTO kpis (school_id, academic_year_id, education_level, category, name, unit, target_value, actual_value, direction) VALUES
 (1,1,'early_childhood','พัฒนาการ','ร้อยละเด็กมีพัฒนาการสมวัย 4 ด้าน','%',90,87.5,'up'),
 (1,1,'early_childhood','สุขภาพ','ร้อยละเด็กมีน้ำหนัก-ส่วนสูงตามเกณฑ์','%',85,82.0,'up'),
 (1,1,'basic','ผลสัมฤทธิ์','คะแนนเฉลี่ย O-NET ภาษาไทย ม.3','คะแนน',55,52.3,'up'),
 (1,1,'basic','ผลสัมฤทธิ์','ร้อยละนักเรียนมี GPAX ≥ 3.00','%',70,68.4,'up'),
 (1,1,'basic','การมาเรียน','อัตราการมาเรียนเฉลี่ย','%',95,96.2,'up'),
 (1,1,'basic','คุณภาพ','อัตรานักเรียนติด 0 ร มส','%',5,7.8,'down'),
 (1,1,'all','ความพึงพอใจ','ความพึงพอใจผู้ปกครองต่อโรงเรียน','%',85,88.0,'up');

-- คำขออนุมัติ (ผู้ขอ=teacher/advisor, ผู้อนุมัติ=director)
INSERT INTO approval_requests (school_id, request_no, requester_id, request_type, title, detail, amount, approver_id, status) VALUES
 (1,'REQ-2568-001',(SELECT id FROM users WHERE username='teacher'),'travel','ขออนุญาตเดินทางไปราชการอบรม PLC','อบรมเชิงปฏิบัติการ ณ สพม. วันที่ 20-21 ก.ค. 2568',1200.00,(SELECT id FROM users WHERE username='director'),'pending'),
 (1,'REQ-2568-002',(SELECT id FROM users WHERE username='advisor'),'purchase','ขออนุมัติจัดซื้อสื่อการสอน','หนังสือแบบฝึกหัดคณิตศาสตร์ 40 เล่ม',3600.00,(SELECT id FROM users WHERE username='director'),'pending'),
 (1,'REQ-2568-003',(SELECT id FROM users WHERE username='teacher'),'leave','ขออนุญาตลากิจ','ธุระครอบครัว 1 วัน',NULL,(SELECT id FROM users WHERE username='director'),'approved');
UPDATE approval_requests SET decision_note='อนุมัติ', decided_at=NOW() WHERE request_no='REQ-2568-003';

-- Template E-Office
INSERT INTO document_templates (school_id, code, name, doc_kind, has_garuda, body) VALUES
 (1,'TPL-MEMO-TRAVEL','บันทึกข้อความขออนุญาตเดินทางไปราชการ','memo',1,
  'ข้าพเจ้า {{ชื่อผู้ขอ}} ตำแหน่ง {{ตำแหน่ง}} ขออนุญาตเดินทางไปราชการเพื่อ {{วัตถุประสงค์}} ณ {{สถานที่}} ระหว่างวันที่ {{วันที่เริ่ม}} ถึงวันที่ {{วันที่สิ้นสุด}} โดยขอเบิกค่าใช้จ่ายจำนวน {{จำนวนเงิน}} บาท จึงเรียนมาเพื่อโปรดพิจารณาอนุญาต'),
 (1,'TPL-ANNOUNCE','ประกาศโรงเรียน','announcement',1,
  'ประกาศโรงเรียน{{ชื่อโรงเรียน}} เรื่อง {{เรื่อง}}\n\n{{เนื้อหาประกาศ}}\n\nประกาศ ณ วันที่ {{วันที่}}'),
 (1,'TPL-ORDER','คำสั่งโรงเรียน','order',1,
  'คำสั่งโรงเรียน{{ชื่อโรงเรียน}} ที่ {{เลขที่คำสั่ง}} เรื่อง {{เรื่อง}}\n\nด้วย {{เหตุผล}} อาศัยอำนาจตามความในมาตรา {{มาตรา}} จึงแต่งตั้งให้บุคคลดังต่อไปนี้ {{รายละเอียด}}\n\nทั้งนี้ ตั้งแต่วันที่ {{วันที่มีผล}} เป็นต้นไป');

-- routing หนังสือราชการรับเข้า ถึง user เฉพาะ + ประวัติเรียกดู
INSERT INTO document_recipients (document_id, recipient_id, action, status) VALUES
 (1,(SELECT id FROM users WHERE username='director'),'acknowledge','done'),
 (1,(SELECT id FROM users WHERE username='head_academic'),'acknowledge','pending'),
 (2,(SELECT id FROM users WHERE username='director'),'approve','pending'),
 (5,(SELECT id FROM users WHERE username='head_academic'),'forward','pending');
INSERT INTO document_views (document_id, user_id, department_id, viewed_at) VALUES
 (1,(SELECT id FROM users WHERE username='director'),1,NOW() - INTERVAL 2 DAY),
 (1,(SELECT id FROM users WHERE username='head_academic'),2,NOW() - INTERVAL 1 DAY);

-- ปฏิทินโรงเรียน + ประชุมออนไลน์ (อิงวันที่ปัจจุบันเพื่อให้เห็นบนปฏิทิน/Dashboard ทันที)
INSERT INTO calendar_events (school_id, title, event_type, event_date, end_date, start_time, end_time, location, meeting_url, description) VALUES
 (1,'ประชุมครูประจำเดือน','meeting', DATE_ADD(CURDATE(), INTERVAL 2 DAY), NULL,'15:30','17:00','ห้องประชุมราชพฤกษ์','https://meet.google.com/abc-defg-hij','วาระประจำเดือน'),
 (1,'กิจกรรมวันสุนทรภู่','activity', DATE_ADD(CURDATE(), INTERVAL 5 DAY), NULL,'08:00','12:00','หอประชุม',NULL,'กิจกรรมส่งเสริมภาษาไทย'),
 (1,'ประชุมผู้ปกครอง','meeting', DATE_ADD(CURDATE(), INTERVAL 9 DAY), NULL,'09:00','12:00','หอประชุม','https://meet.google.com/xyz-1234-abc','ประชุมผู้ปกครองประจำภาคเรียน'),
 (1,'สอบกลางภาค','exam', DATE_ADD(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(), INTERVAL 18 DAY),'08:30','15:30','อาคารเรียน',NULL,'สอบกลางภาคเรียนที่ 1'),
 (1,'วันหยุดนักขัตฤกษ์','holiday', DATE_ADD(CURDATE(), INTERVAL 20 DAY), NULL,NULL,NULL,NULL,NULL,'วันหยุดราชการ'),
 (1,'ทบทวนแผนพัฒนาคุณภาพ','academic', DATE_SUB(CURDATE(), INTERVAL 3 DAY), NULL,'13:00','16:00','ห้องวิชาการ',NULL,'ทบทวน SAR');

-- จบ 05
