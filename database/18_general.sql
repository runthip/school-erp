-- ==================================================================
--  18_general.sql — ฝ่ายบริหารทั่วไป
--  ยานพาหนะ + การจองใช้รถ (แจ้งซ่อม/พัสดุครุภัณฑ์/สารบรรณ ใช้ตารางเดิม)
--  นำเข้าหลัง 01-17
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- ทะเบียนยานพาหนะ
CREATE TABLE IF NOT EXISTS vehicles (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     BIGINT UNSIGNED NOT NULL DEFAULT 1,
  plate_no      VARCHAR(20) NOT NULL,
  brand         VARCHAR(80) DEFAULT NULL,
  vehicle_type  VARCHAR(60) DEFAULT NULL,          -- รถตู้/รถบัส/รถกระบะ/รถยนต์
  seats         SMALLINT UNSIGNED DEFAULT NULL,
  fuel_type     VARCHAR(30) DEFAULT NULL,
  status        ENUM('available','in_use','maintenance','inactive') NOT NULL DEFAULT 'available',
  note          VARCHAR(255) DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plate (plate_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- การขอใช้/จองยานพาหนะ
CREATE TABLE IF NOT EXISTS vehicle_bookings (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_no    VARCHAR(30) NOT NULL UNIQUE,
  vehicle_id    BIGINT UNSIGNED DEFAULT NULL,
  requester_id  BIGINT UNSIGNED DEFAULT NULL,      -- users.id
  purpose       VARCHAR(255) NOT NULL,
  destination   VARCHAR(255) DEFAULT NULL,
  depart_at     DATETIME NOT NULL,
  return_at     DATETIME DEFAULT NULL,
  passengers    SMALLINT UNSIGNED DEFAULT NULL,
  driver_id     BIGINT UNSIGNED DEFAULT NULL,      -- personnel.id
  status        ENUM('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
  approver_id   BIGINT UNSIGNED DEFAULT NULL,
  approved_at   DATETIME DEFAULT NULL,
  note          VARCHAR(255) DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_vb_vehicle (vehicle_id), KEY ix_vb_status (status),
  CONSTRAINT fk_vb_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
  CONSTRAINT fk_vb_driver  FOREIGN KEY (driver_id)  REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สิทธิ์ (เพิ่มลง permissions ให้ครบ + งานพัสดุ/สารบรรณ)
INSERT IGNORE INTO permissions (code, name, module) VALUES
  ('general.repair',  'แจ้งซ่อม/อาคารสถานที่', 'general'),
  ('general.asset',   'พัสดุ/ครุภัณฑ์',        'general'),
  ('general.document','งานสารบรรณ',            'general'),
  ('general.vehicle', 'งานยานพาหนะ',           'general'),
  ('general.booking', 'จองห้องประชุม',         'general');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code IN ('general.repair','general.asset','general.document','general.vehicle','general.booking')
    AND r.code IN ('director','deputy_director','head_general','clerk','inventory_officer');

-- ===== ข้อมูลตัวอย่าง =====
INSERT IGNORE INTO vehicles (plate_no, brand, vehicle_type, seats, fuel_type, status, note) VALUES
  ('กข 1234 กรุงเทพมหานคร', 'Toyota Commuter', 'รถตู้', 15, 'ดีเซล', 'available', 'รถตู้โรงเรียน'),
  ('บงจ 5678 นนทบุรี', 'Isuzu D-Max', 'รถกระบะ', 2, 'ดีเซล', 'available', 'รถกระบะบรรทุก'),
  ('40-1234 ปทุมธานี', 'Hino', 'รถบัส', 40, 'ดีเซล', 'maintenance', 'รถบัสนักเรียน');

INSERT INTO vehicle_bookings (booking_no, vehicle_id, requester_id, purpose, destination, depart_at, return_at, passengers, status)
  SELECT 'ยพ-2569-0001', v.id, 1, 'พานักเรียนแข่งวิชาการ', 'สพฐ. เขตพื้นที่', DATE_ADD(CURDATE(), INTERVAL 3 DAY)+INTERVAL 8 HOUR, DATE_ADD(CURDATE(), INTERVAL 3 DAY)+INTERVAL 16 HOUR, 12, 'pending'
  FROM vehicles v WHERE v.vehicle_type='รถตู้' LIMIT 1;

-- จบ 18
