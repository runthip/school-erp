-- ==================================================================
--  22_portal.sql — พอร์ทัลนักเรียน
--  ผูกบัญชีผู้ใช้เข้ากับ record นักเรียนจริง (users.linked_type/linked_id)
--  นำเข้าหลัง 01-21
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;  -- บังคับ charset ให้ภาษาไทยถูกต้องทุก client (CLI/phpMyAdmin)

-- ===== สิทธิ์พอร์ทัลนักเรียน (มี permission อยู่แล้วใน 03_seed_demo) =====
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
  WHERE p.code='portal.student' AND r.code='student';

-- ===== บัญชีสาธิตของนักเรียน — ผูกกับ record นักเรียนจริง =====
-- หมายเหตุ: 03_seed_demo สร้าง user 'student' ไว้แล้วแบบ linked_type='none'
-- จึงต้อง UPDATE ให้ผูกด้วย ไม่ใช่แค่ INSERT (ไม่งั้นพอร์ทัลจะไม่มีข้อมูล)

-- 1) สร้างถ้ายังไม่มี
INSERT INTO users (username, full_name, password_hash, status, linked_type, linked_id)
  SELECT 'student', CONCAT(s.prefix, s.first_name, ' ', s.last_name, ' (สาธิต)'),
         '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa',
         'active', 'student', s.id
  FROM students s
  WHERE s.deleted_at IS NULL AND s.status='studying'
    AND NOT EXISTS (SELECT 1 FROM users u WHERE u.username='student')
  ORDER BY s.id LIMIT 1;

-- 2) ถ้ามีอยู่แล้วแต่ยังไม่ผูก → ผูกให้ (เลือกนักเรียนที่มีผลการเรียนจริง เพื่อให้พอร์ทัลมีข้อมูลแสดง)
UPDATE users u
  SET u.linked_type='student',
      u.linked_id=(SELECT s.id FROM students s
                   LEFT JOIN final_grades fg ON fg.student_id=s.id
                   WHERE s.deleted_at IS NULL AND s.status='studying'
                   GROUP BY s.id ORDER BY COUNT(fg.id) DESC, s.id ASC LIMIT 1)
  WHERE u.username='student' AND (u.linked_type<>'student' OR u.linked_id IS NULL);

DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='student';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='student'),
         (SELECT id FROM roles WHERE code='student');

-- บัญชีนักเรียนคนที่ 2 (ไว้ทดสอบว่าต่างคนเห็นข้อมูลต่างกัน / ไม่เห็นข้ามคน)
INSERT INTO users (username, full_name, password_hash, status, linked_type, linked_id)
  SELECT 'student2', CONCAT(s.prefix, s.first_name, ' ', s.last_name, ' (สาธิต)'),
         '$2y$10$ynTh9cDTyJXY8YY1xNvoyeGNFeIT6kDYvrmlGzSFlRN4L0P3Cb3Aa',
         'active', 'student', s.id
  FROM students s
  WHERE s.deleted_at IS NULL AND s.status='studying'
    AND s.id <> (SELECT linked_id FROM users WHERE username='student')
    AND NOT EXISTS (SELECT 1 FROM users u WHERE u.username='student2')
  ORDER BY s.id LIMIT 1;

UPDATE users u
  SET u.linked_type='student',
      u.linked_id=(SELECT s.id FROM students s WHERE s.deleted_at IS NULL AND s.status='studying'
                   AND s.id <> (SELECT linked_id FROM users WHERE username='student')
                   ORDER BY s.id LIMIT 1)
  WHERE u.username='student2' AND (u.linked_type<>'student' OR u.linked_id IS NULL);

DELETE ur FROM user_roles ur JOIN users u ON u.id=ur.user_id WHERE u.username='student2';
INSERT IGNORE INTO user_roles (user_id, role_id)
  SELECT (SELECT id FROM users WHERE username='student2'),
         (SELECT id FROM roles WHERE code='student');

-- จบ 22
