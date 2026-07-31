-- =====================================================================
--  School ERP Enterprise  -  Core Database Schema (MySQL 8)
--  ระบบ ERP โรงเรียน : สคีมาแกนกลาง (~55 ตารางหลัก ครอบคลุม 5 ฝ่าย)
-- ---------------------------------------------------------------------
--  Engine   : InnoDB      Charset : utf8mb4 / utf8mb4_unicode_ci
--  Version  : 1.0         Target  : MySQL 8.0+
--
--  Convention:
--    - ชื่อตารางเป็น snake_case พหูพจน์  (users, students, subjects)
--    - PK ชื่อ id เป็น BIGINT UNSIGNED AUTO_INCREMENT
--    - FK ชื่อ <ตารางเอกพจน์>_id  (school_id, student_id)
--    - ทุกตารางมี created_at / updated_at
--    - ตารางข้อมูลหลักมี deleted_at (Soft Delete) + created_by / updated_by
--    - สถานะใช้ ENUM หรือ *_status ที่ชัดเจน
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = '+07:00';

CREATE DATABASE IF NOT EXISTS school_erp
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE school_erp;

-- =====================================================================
--  โซน A : ระบบความปลอดภัย + RBAC + Audit  (System / Security)
-- =====================================================================

-- ---- ผู้ใช้งานระบบ --------------------------------------------------
CREATE TABLE users (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username        VARCHAR(100)    NOT NULL,
  email           VARCHAR(150)    DEFAULT NULL,
  password_hash   VARCHAR(255)    NOT NULL,
  full_name       VARCHAR(200)    NOT NULL,
  phone           VARCHAR(30)     DEFAULT NULL,
  avatar_path     VARCHAR(255)    DEFAULT NULL,
  -- ผูกกับตัวตนจริงในระบบ (นักเรียน/บุคลากร) แบบ polymorphic
  linked_type     ENUM('none','personnel','student','guardian') NOT NULL DEFAULT 'none',
  linked_id       BIGINT UNSIGNED DEFAULT NULL,
  status          ENUM('active','inactive','locked','suspended') NOT NULL DEFAULT 'active',
  is_2fa_enabled  TINYINT(1)      NOT NULL DEFAULT 0,
  twofa_secret    VARCHAR(255)    DEFAULT NULL,
  last_login_at   DATETIME        DEFAULT NULL,
  failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  password_changed_at DATETIME    DEFAULT NULL,
  created_by      BIGINT UNSIGNED DEFAULT NULL,
  updated_by      BIGINT UNSIGNED DEFAULT NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME        DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY ix_users_linked (linked_type, linked_id),
  KEY ix_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- บทบาท (Roles) --------------------------------------------------
CREATE TABLE roles (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(60)     NOT NULL,           -- super_admin, director, teacher ...
  name         VARCHAR(150)    NOT NULL,
  description  VARCHAR(255)    DEFAULT NULL,
  is_system    TINYINT(1)      NOT NULL DEFAULT 0, -- role ระบบ ห้ามลบ
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- สิทธิ์ย่อย (Permissions) --------------------------------------
CREATE TABLE permissions (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(100)    NOT NULL,           -- student.view, grade.edit ...
  name         VARCHAR(150)    NOT NULL,
  module       VARCHAR(80)     DEFAULT NULL,       -- จัดกลุ่มตามโมดูล
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_code (code),
  KEY ix_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- role  <->  permission -----------------------------------------
CREATE TABLE role_permissions (
  role_id        BIGINT UNSIGNED NOT NULL,
  permission_id  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  KEY ix_rp_permission (permission_id),
  CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- user  <->  role -----------------------------------------------
CREATE TABLE user_roles (
  user_id     BIGINT UNSIGNED NOT NULL,
  role_id     BIGINT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id),
  KEY ix_ur_role (role_id),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ประวัติเข้าสู่ระบบ + OTP + reset -------------------------------
CREATE TABLE user_login_history (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED DEFAULT NULL,
  username    VARCHAR(100)    DEFAULT NULL,        -- เก็บกรณี login ผิด
  ip_address  VARCHAR(45)     DEFAULT NULL,
  user_agent  VARCHAR(255)    DEFAULT NULL,
  result      ENUM('success','failed','locked','otp_required') NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_login_user (user_id),
  KEY ix_login_created (created_at),
  CONSTRAINT fk_login_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  token_hash  VARCHAR(255)    NOT NULL,
  expires_at  DATETIME        NOT NULL,
  used_at     DATETIME        DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pwreset_user (user_id),
  CONSTRAINT fk_pwreset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Audit Log (บันทึกทุกการกระทำสำคัญ) ----------------------------
CREATE TABLE audit_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED DEFAULT NULL,
  action      VARCHAR(60)     NOT NULL,            -- create/update/delete/login/approve ...
  entity_type VARCHAR(80)     DEFAULT NULL,        -- ชื่อตาราง/โมดูล
  entity_id   BIGINT UNSIGNED DEFAULT NULL,
  old_values  JSON            DEFAULT NULL,
  new_values  JSON            DEFAULT NULL,
  ip_address  VARCHAR(45)     DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_user (user_id),
  KEY ix_audit_entity (entity_type, entity_id),
  KEY ix_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- การตั้งค่าระบบ (key-value) ------------------------------------
CREATE TABLE system_settings (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_key   VARCHAR(60)     NOT NULL,            -- general, security, mail ...
  setting_key VARCHAR(100)    NOT NULL,
  setting_value TEXT          DEFAULT NULL,
  value_type  ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
  updated_by  BIGINT UNSIGNED DEFAULT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_setting (group_key, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  โซน B : ข้อมูลองค์กร / Master Data  (โครงสร้างสถานศึกษา)
-- =====================================================================

-- ---- โรงเรียน (รองรับหลายสถานศึกษาในอนาคต) --------------------------
CREATE TABLE schools (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_code  VARCHAR(30)     NOT NULL,           -- รหัส 10 หลัก สพฐ.
  name_th      VARCHAR(255)    NOT NULL,
  name_en      VARCHAR(255)    DEFAULT NULL,
  address      VARCHAR(500)    DEFAULT NULL,
  district     VARCHAR(120)    DEFAULT NULL,
  province     VARCHAR(120)    DEFAULT NULL,
  postcode     VARCHAR(10)     DEFAULT NULL,
  phone        VARCHAR(30)     DEFAULT NULL,
  email        VARCHAR(150)    DEFAULT NULL,
  logo_path    VARCHAR(255)    DEFAULT NULL,
  affiliation  VARCHAR(150)    DEFAULT NULL,       -- สังกัด เช่น สพม./สพป.
  is_active    TINYINT(1)      NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_school_code (school_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ปีการศึกษา -----------------------------------------------------
CREATE TABLE academic_years (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    BIGINT UNSIGNED NOT NULL,
  year_be      SMALLINT UNSIGNED NOT NULL,         -- พ.ศ. เช่น 2568
  start_date   DATE            DEFAULT NULL,
  end_date     DATE            DEFAULT NULL,
  is_current   TINYINT(1)      NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ay (school_id, year_be),
  CONSTRAINT fk_ay_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ภาคเรียน -------------------------------------------------------
CREATE TABLE semesters (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  term             TINYINT UNSIGNED NOT NULL,      -- 1 หรือ 2
  start_date       DATE          DEFAULT NULL,
  end_date         DATE          DEFAULT NULL,
  is_current       TINYINT(1)    NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_semester (academic_year_id, term),
  CONSTRAINT fk_sem_ay FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ฝ่าย/กลุ่มงาน (5 ฝ่าย) ----------------------------------------
CREATE TABLE org_departments (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    BIGINT UNSIGNED NOT NULL,
  code         VARCHAR(40)     NOT NULL,           -- admin, academic, budget, hr, general
  name         VARCHAR(150)    NOT NULL,
  head_personnel_id BIGINT UNSIGNED DEFAULT NULL,  -- หัวหน้าฝ่าย (FK เพิ่มภายหลัง)
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dept (school_id, code),
  CONSTRAINT fk_dept_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- กลุ่มสาระการเรียนรู้ ------------------------------------------
CREATE TABLE subject_groups (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    BIGINT UNSIGNED NOT NULL,
  code         VARCHAR(40)     NOT NULL,           -- MATH, SCI, THAI ...
  name         VARCHAR(150)    NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subject_group (school_id, code),
  CONSTRAINT fk_sg_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- อาคาร ----------------------------------------------------------
CREATE TABLE buildings (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    BIGINT UNSIGNED NOT NULL,
  name         VARCHAR(150)    NOT NULL,
  floors       TINYINT UNSIGNED DEFAULT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_building_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ห้อง (ห้องเรียน/ห้องประชุม/ห้องปฏิบัติการ) --------------------
CREATE TABLE rooms (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  building_id  BIGINT UNSIGNED DEFAULT NULL,
  school_id    BIGINT UNSIGNED NOT NULL,
  room_code    VARCHAR(40)     NOT NULL,
  name         VARCHAR(150)    NOT NULL,
  room_type    ENUM('classroom','lab','meeting','office','other') NOT NULL DEFAULT 'classroom',
  capacity     SMALLINT UNSIGNED DEFAULT NULL,
  is_bookable  TINYINT(1)      NOT NULL DEFAULT 0, -- จองห้องได้หรือไม่
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_room_building (building_id),
  CONSTRAINT fk_room_building FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE SET NULL,
  CONSTRAINT fk_room_school   FOREIGN KEY (school_id)   REFERENCES schools(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ระดับชั้น (ป.1 ... ม.6) ---------------------------------------
CREATE TABLE grade_levels (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    BIGINT UNSIGNED NOT NULL,
  code         VARCHAR(20)     NOT NULL,           -- P1, M1, M6 ...
  name         VARCHAR(80)     NOT NULL,           -- ประถมศึกษาปีที่ 1
  level_order  SMALLINT UNSIGNED NOT NULL,         -- ใช้เรียงลำดับ
  stage        ENUM('kindergarten','primary','lower_secondary','upper_secondary') DEFAULT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_grade (school_id, code),
  CONSTRAINT fk_grade_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ห้องเรียน (เช่น ม.1/1) ระบุปีการศึกษา -------------------------
CREATE TABLE classrooms (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  grade_level_id   BIGINT UNSIGNED NOT NULL,
  section          TINYINT UNSIGNED NOT NULL,      -- เลขห้อง 1,2,3
  name             VARCHAR(60)   NOT NULL,         -- ม.1/1
  room_id          BIGINT UNSIGNED DEFAULT NULL,   -- ห้องประจำ
  homeroom_teacher_id BIGINT UNSIGNED DEFAULT NULL,-- ครูที่ปรึกษา (FK เพิ่มภายหลัง)
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_classroom (academic_year_id, grade_level_id, section),
  KEY ix_classroom_grade (grade_level_id),
  CONSTRAINT fk_cr_school FOREIGN KEY (school_id)        REFERENCES schools(id)        ON DELETE CASCADE,
  CONSTRAINT fk_cr_ay     FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
  CONSTRAINT fk_cr_grade  FOREIGN KEY (grade_level_id)   REFERENCES grade_levels(id)   ON DELETE CASCADE,
  CONSTRAINT fk_cr_room   FOREIGN KEY (room_id)          REFERENCES rooms(id)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  โซน C : บุคคล  (บุคลากร / นักเรียน / ผู้ปกครอง)
-- =====================================================================

-- ---- บุคลากร / ครู --------------------------------------------------
CREATE TABLE personnel (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      BIGINT UNSIGNED NOT NULL,
  employee_code  VARCHAR(40)     NOT NULL,
  citizen_id     VARCHAR(13)     DEFAULT NULL,
  prefix         VARCHAR(30)     DEFAULT NULL,
  first_name     VARCHAR(120)    NOT NULL,
  last_name      VARCHAR(120)    NOT NULL,
  gender         ENUM('male','female','other') DEFAULT NULL,
  birth_date     DATE            DEFAULT NULL,
  phone          VARCHAR(30)     DEFAULT NULL,
  email          VARCHAR(150)    DEFAULT NULL,
  position       VARCHAR(120)    DEFAULT NULL,     -- ตำแหน่ง เช่น ครู คศ.2
  academic_standing VARCHAR(80)  DEFAULT NULL,     -- วิทยฐานะ
  department_id  BIGINT UNSIGNED DEFAULT NULL,     -- ฝ่ายที่สังกัด
  subject_group_id BIGINT UNSIGNED DEFAULT NULL,   -- กลุ่มสาระ
  employment_type ENUM('civil_servant','government_employee','contract','other') DEFAULT NULL,
  start_date     DATE            DEFAULT NULL,
  status         ENUM('active','on_leave','resigned','retired') NOT NULL DEFAULT 'active',
  photo_path     VARCHAR(255)    DEFAULT NULL,
  created_by     BIGINT UNSIGNED DEFAULT NULL,
  updated_by     BIGINT UNSIGNED DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at     DATETIME        DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_personnel_code (school_id, employee_code),
  KEY ix_personnel_dept (department_id),
  KEY ix_personnel_sg (subject_group_id),
  KEY ix_personnel_status (status),
  CONSTRAINT fk_personnel_school FOREIGN KEY (school_id)        REFERENCES schools(id)        ON DELETE CASCADE,
  CONSTRAINT fk_personnel_dept   FOREIGN KEY (department_id)    REFERENCES org_departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_personnel_sg     FOREIGN KEY (subject_group_id) REFERENCES subject_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เติม FK ที่อ้างถึง personnel (หัวหน้าฝ่าย / ครูที่ปรึกษา)
ALTER TABLE org_departments
  ADD CONSTRAINT fk_dept_head FOREIGN KEY (head_personnel_id) REFERENCES personnel(id) ON DELETE SET NULL;
ALTER TABLE classrooms
  ADD CONSTRAINT fk_cr_homeroom FOREIGN KEY (homeroom_teacher_id) REFERENCES personnel(id) ON DELETE SET NULL;

-- ---- นักเรียน -------------------------------------------------------
CREATE TABLE students (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id      BIGINT UNSIGNED NOT NULL,
  student_code   VARCHAR(40)     NOT NULL,         -- รหัสนักเรียน
  citizen_id     VARCHAR(13)     DEFAULT NULL,
  prefix         VARCHAR(30)     DEFAULT NULL,
  first_name     VARCHAR(120)    NOT NULL,
  last_name      VARCHAR(120)    NOT NULL,
  nickname       VARCHAR(60)     DEFAULT NULL,
  gender         ENUM('male','female','other') DEFAULT NULL,
  birth_date     DATE            DEFAULT NULL,
  blood_group    ENUM('A','B','AB','O') DEFAULT NULL,
  nationality    VARCHAR(60)     DEFAULT NULL,
  religion       VARCHAR(60)     DEFAULT NULL,
  address        VARCHAR(500)    DEFAULT NULL,
  phone          VARCHAR(30)     DEFAULT NULL,
  admission_date DATE            DEFAULT NULL,
  status         ENUM('studying','graduated','transferred','dropped','suspended') NOT NULL DEFAULT 'studying',
  photo_path     VARCHAR(255)    DEFAULT NULL,
  created_by     BIGINT UNSIGNED DEFAULT NULL,
  updated_by     BIGINT UNSIGNED DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at     DATETIME        DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_code (school_id, student_code),
  KEY ix_student_name (first_name, last_name),
  KEY ix_student_status (status),
  CONSTRAINT fk_student_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ผู้ปกครอง ------------------------------------------------------
CREATE TABLE guardians (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  citizen_id     VARCHAR(13)     DEFAULT NULL,
  prefix         VARCHAR(30)     DEFAULT NULL,
  first_name     VARCHAR(120)    NOT NULL,
  last_name      VARCHAR(120)    NOT NULL,
  phone          VARCHAR(30)     DEFAULT NULL,
  email          VARCHAR(150)    DEFAULT NULL,
  occupation     VARCHAR(120)    DEFAULT NULL,
  address        VARCHAR(500)    DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_guardian_citizen (citizen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- นักเรียน <-> ผู้ปกครอง ----------------------------------------
CREATE TABLE student_guardians (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id    BIGINT UNSIGNED NOT NULL,
  guardian_id   BIGINT UNSIGNED NOT NULL,
  relationship  ENUM('father','mother','guardian','relative','other') NOT NULL DEFAULT 'guardian',
  is_primary    TINYINT(1)      NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sg (student_id, guardian_id),
  KEY ix_sg_guardian (guardian_id),
  CONSTRAINT fk_sg_student  FOREIGN KEY (student_id)  REFERENCES students(id)  ON DELETE CASCADE,
  CONSTRAINT fk_sg_guardian FOREIGN KEY (guardian_id) REFERENCES guardians(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- การจัดนักเรียนเข้าห้อง (ต่อปีการศึกษา) ------------------------
CREATE TABLE student_enrollments (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       BIGINT UNSIGNED NOT NULL,
  classroom_id     BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  roll_number      SMALLINT UNSIGNED DEFAULT NULL, -- เลขที่
  status           ENUM('active','moved','left') NOT NULL DEFAULT 'active',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enroll (student_id, academic_year_id),
  KEY ix_enroll_classroom (classroom_id),
  CONSTRAINT fk_se_student   FOREIGN KEY (student_id)       REFERENCES students(id)       ON DELETE CASCADE,
  CONSTRAINT fk_se_classroom FOREIGN KEY (classroom_id)     REFERENCES classrooms(id)     ON DELETE CASCADE,
  CONSTRAINT fk_se_ay        FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  โซน D : ฝ่ายวิชาการ  (หลักสูตร / ตารางสอน / คะแนน / เช็คชื่อ)
-- =====================================================================

-- ---- รายวิชา --------------------------------------------------------
CREATE TABLE subjects (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        BIGINT UNSIGNED NOT NULL,
  subject_code     VARCHAR(30)     NOT NULL,       -- ค21101
  name_th          VARCHAR(200)    NOT NULL,
  name_en          VARCHAR(200)    DEFAULT NULL,
  subject_group_id BIGINT UNSIGNED DEFAULT NULL,
  credit           DECIMAL(4,1)    DEFAULT NULL,   -- หน่วยกิต
  hours_per_week   TINYINT UNSIGNED DEFAULT NULL,
  subject_type     ENUM('core','additional','activity') NOT NULL DEFAULT 'core',
  is_active        TINYINT(1)      NOT NULL DEFAULT 1,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subject (school_id, subject_code),
  KEY ix_subject_group (subject_group_id),
  CONSTRAINT fk_subject_school FOREIGN KEY (school_id)        REFERENCES schools(id)        ON DELETE CASCADE,
  CONSTRAINT fk_subject_sg     FOREIGN KEY (subject_group_id) REFERENCES subject_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- การมอบหมายสอน (ครู + วิชา + ห้อง + ภาคเรียน) ------------------
CREATE TABLE teaching_assignments (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  semester_id      BIGINT UNSIGNED NOT NULL,
  subject_id       BIGINT UNSIGNED NOT NULL,
  classroom_id     BIGINT UNSIGNED NOT NULL,
  teacher_id       BIGINT UNSIGNED NOT NULL,       -- personnel
  is_primary       TINYINT(1)      NOT NULL DEFAULT 1, -- ครูหลัก/ครูร่วม
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ta (semester_id, subject_id, classroom_id, teacher_id),
  KEY ix_ta_teacher (teacher_id),
  KEY ix_ta_classroom (classroom_id),
  CONSTRAINT fk_ta_semester  FOREIGN KEY (semester_id)  REFERENCES semesters(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ta_subject   FOREIGN KEY (subject_id)   REFERENCES subjects(id)   ON DELETE CASCADE,
  CONSTRAINT fk_ta_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_teacher   FOREIGN KEY (teacher_id)   REFERENCES personnel(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ตารางเรียน/ตารางสอน (คาบ) -------------------------------------
CREATE TABLE class_schedules (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  teaching_assignment_id BIGINT UNSIGNED NOT NULL,
  day_of_week            TINYINT UNSIGNED NOT NULL,   -- 1=จันทร์ ... 7=อาทิตย์
  period_no              TINYINT UNSIGNED NOT NULL,   -- คาบที่
  start_time             TIME          DEFAULT NULL,
  end_time               TIME          DEFAULT NULL,
  room_id                BIGINT UNSIGNED DEFAULT NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sched_ta (teaching_assignment_id),
  KEY ix_sched_day (day_of_week, period_no),
  CONSTRAINT fk_sched_ta   FOREIGN KEY (teaching_assignment_id) REFERENCES teaching_assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_sched_room FOREIGN KEY (room_id)                REFERENCES rooms(id)                ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- องค์ประกอบคะแนน (เก็บคะแนน/กลางภาค/ปลายภาค) -------------------
CREATE TABLE grade_components (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  teaching_assignment_id BIGINT UNSIGNED NOT NULL,
  name                   VARCHAR(150)  NOT NULL,     -- เก็บคะแนนครั้งที่ 1
  component_type         ENUM('formative','midterm','final','other') NOT NULL DEFAULT 'formative',
  max_score              DECIMAL(6,2)  NOT NULL,
  weight                 DECIMAL(5,2)  DEFAULT NULL, -- สัดส่วน %
  sort_order             SMALLINT UNSIGNED DEFAULT 0,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_gc_ta (teaching_assignment_id),
  CONSTRAINT fk_gc_ta FOREIGN KEY (teaching_assignment_id) REFERENCES teaching_assignments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- คะแนนรายคน -----------------------------------------------------
CREATE TABLE scores (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  grade_component_id BIGINT UNSIGNED NOT NULL,
  student_id         BIGINT UNSIGNED NOT NULL,
  score              DECIMAL(6,2)  DEFAULT NULL,
  recorded_by        BIGINT UNSIGNED DEFAULT NULL,  -- personnel
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_score (grade_component_id, student_id),
  KEY ix_score_student (student_id),
  CONSTRAINT fk_score_gc      FOREIGN KEY (grade_component_id) REFERENCES grade_components(id) ON DELETE CASCADE,
  CONSTRAINT fk_score_student FOREIGN KEY (student_id)         REFERENCES students(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ผลการเรียนสรุป (เกรดต่อวิชาต่อภาคเรียน) -----------------------
CREATE TABLE final_grades (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id             BIGINT UNSIGNED NOT NULL,
  teaching_assignment_id BIGINT UNSIGNED NOT NULL,
  total_score            DECIMAL(6,2)  DEFAULT NULL,
  grade                  VARCHAR(5)    DEFAULT NULL, -- 4,3.5,...,0 / ผ,มผ
  special_result         ENUM('none','0','r','ms') NOT NULL DEFAULT 'none', -- 0/ร/มส
  is_finalized           TINYINT(1)    NOT NULL DEFAULT 0,
  finalized_at           DATETIME      DEFAULT NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_final (student_id, teaching_assignment_id),
  KEY ix_final_ta (teaching_assignment_id),
  KEY ix_final_special (special_result),
  CONSTRAINT fk_final_student FOREIGN KEY (student_id)             REFERENCES students(id)             ON DELETE CASCADE,
  CONSTRAINT fk_final_ta      FOREIGN KEY (teaching_assignment_id) REFERENCES teaching_assignments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- การเช็คชื่อ / มาเรียน -----------------------------------------
CREATE TABLE attendances (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id             BIGINT UNSIGNED NOT NULL,
  teaching_assignment_id BIGINT UNSIGNED DEFAULT NULL, -- เช็คชื่อรายวิชา (อาจ NULL = เช็คหน้าเสาธง)
  classroom_id           BIGINT UNSIGNED DEFAULT NULL,
  attendance_date        DATE          NOT NULL,
  period_no              TINYINT UNSIGNED DEFAULT NULL,
  status                 ENUM('present','absent','late','leave','activity') NOT NULL DEFAULT 'present',
  note                   VARCHAR(255)  DEFAULT NULL,
  recorded_by            BIGINT UNSIGNED DEFAULT NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_att_student_date (student_id, attendance_date),
  KEY ix_att_ta (teaching_assignment_id),
  KEY ix_att_classroom (classroom_id),
  CONSTRAINT fk_att_student   FOREIGN KEY (student_id)             REFERENCES students(id)             ON DELETE CASCADE,
  CONSTRAINT fk_att_ta        FOREIGN KEY (teaching_assignment_id) REFERENCES teaching_assignments(id) ON DELETE SET NULL,
  CONSTRAINT fk_att_classroom FOREIGN KEY (classroom_id)           REFERENCES classrooms(id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- แผนการสอน ------------------------------------------------------
CREATE TABLE lesson_plans (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_id   BIGINT UNSIGNED NOT NULL,
  teacher_id   BIGINT UNSIGNED NOT NULL,
  semester_id  BIGINT UNSIGNED DEFAULT NULL,
  title        VARCHAR(255)  NOT NULL,
  unit_no      SMALLINT UNSIGNED DEFAULT NULL,
  hours        TINYINT UNSIGNED DEFAULT NULL,
  content      MEDIUMTEXT    DEFAULT NULL,
  file_path    VARCHAR(255)  DEFAULT NULL,
  status       ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_lp_subject (subject_id),
  KEY ix_lp_teacher (teacher_id),
  CONSTRAINT fk_lp_subject  FOREIGN KEY (subject_id)  REFERENCES subjects(id)  ON DELETE CASCADE,
  CONSTRAINT fk_lp_teacher  FOREIGN KEY (teacher_id)  REFERENCES personnel(id) ON DELETE CASCADE,
  CONSTRAINT fk_lp_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  โซน E : ระบบดูแลช่วยเหลือนักเรียน (พฤติกรรม / SDQ / เยี่ยมบ้าน / สุขภาพ)
-- =====================================================================

CREATE TABLE behavior_records (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id    BIGINT UNSIGNED NOT NULL,
  record_date   DATE          NOT NULL,
  type          ENUM('merit','demerit') NOT NULL DEFAULT 'demerit',
  points        SMALLINT      NOT NULL DEFAULT 0,   -- +/- คะแนน
  description   VARCHAR(500)  DEFAULT NULL,
  recorded_by   BIGINT UNSIGNED DEFAULT NULL,       -- personnel
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_behavior_student (student_id, record_date),
  CONSTRAINT fk_behavior_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sdq_assessments (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED DEFAULT NULL,
  assessor         ENUM('self','teacher','parent') NOT NULL DEFAULT 'teacher',
  emotional_score  TINYINT UNSIGNED DEFAULT NULL,
  conduct_score    TINYINT UNSIGNED DEFAULT NULL,
  hyperactivity_score TINYINT UNSIGNED DEFAULT NULL,
  peer_score       TINYINT UNSIGNED DEFAULT NULL,
  prosocial_score  TINYINT UNSIGNED DEFAULT NULL,
  total_difficulty TINYINT UNSIGNED DEFAULT NULL,
  result_group     ENUM('normal','risk','problem') DEFAULT NULL,
  assessed_at      DATE          DEFAULT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sdq_student (student_id),
  CONSTRAINT fk_sdq_student FOREIGN KEY (student_id)       REFERENCES students(id)       ON DELETE CASCADE,
  CONSTRAINT fk_sdq_ay      FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE home_visits (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id   BIGINT UNSIGNED NOT NULL,
  visit_date   DATE          NOT NULL,
  visitor_id   BIGINT UNSIGNED DEFAULT NULL,        -- ครูที่ปรึกษา
  summary      TEXT          DEFAULT NULL,
  risk_level   ENUM('normal','watch','risk','urgent') DEFAULT 'normal',
  photo_path   VARCHAR(255)  DEFAULT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_visit_student (student_id),
  CONSTRAINT fk_visit_student FOREIGN KEY (student_id) REFERENCES students(id)  ON DELETE CASCADE,
  CONSTRAINT fk_visit_visitor FOREIGN KEY (visitor_id) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE health_records (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id    BIGINT UNSIGNED NOT NULL,
  record_date   DATE          NOT NULL,
  height_cm     DECIMAL(5,1)  DEFAULT NULL,
  weight_kg     DECIMAL(5,1)  DEFAULT NULL,
  bmi           DECIMAL(5,2)  DEFAULT NULL,
  chronic_disease VARCHAR(255) DEFAULT NULL,
  allergy       VARCHAR(255)  DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_health_student (student_id, record_date),
  CONSTRAINT fk_health_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scholarships (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id    BIGINT UNSIGNED NOT NULL,
  name          VARCHAR(200)  NOT NULL,
  source        VARCHAR(200)  DEFAULT NULL,
  amount        DECIMAL(12,2) DEFAULT NULL,
  academic_year_id BIGINT UNSIGNED DEFAULT NULL,
  granted_date  DATE          DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_scholar_student (student_id),
  CONSTRAINT fk_scholar_student FOREIGN KEY (student_id)       REFERENCES students(id)       ON DELETE CASCADE,
  CONSTRAINT fk_scholar_ay      FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  โซน F : ฝ่ายบุคคล  (การลา / ปฏิบัติงาน / เงินเดือน)
-- =====================================================================

CREATE TABLE leave_types (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(30)   NOT NULL,             -- sick, personal, vacation ...
  name         VARCHAR(120)  NOT NULL,
  max_days_year SMALLINT UNSIGNED DEFAULT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leave_type (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leaves (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  personnel_id  BIGINT UNSIGNED NOT NULL,
  leave_type_id BIGINT UNSIGNED NOT NULL,
  start_date    DATE          NOT NULL,
  end_date      DATE          NOT NULL,
  days          DECIMAL(4,1)  DEFAULT NULL,
  reason        VARCHAR(500)  DEFAULT NULL,
  status        ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  approver_id   BIGINT UNSIGNED DEFAULT NULL,
  approved_at   DATETIME      DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_leave_personnel (personnel_id),
  KEY ix_leave_status (status),
  CONSTRAINT fk_leave_personnel FOREIGN KEY (personnel_id)  REFERENCES personnel(id)   ON DELETE CASCADE,
  CONSTRAINT fk_leave_type      FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE RESTRICT,
  CONSTRAINT fk_leave_approver  FOREIGN KEY (approver_id)   REFERENCES personnel(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- การลงเวลาปฏิบัติงาน (สแกนนิ้ว/Face) ---------------------------
CREATE TABLE staff_attendances (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  personnel_id  BIGINT UNSIGNED NOT NULL,
  work_date     DATE          NOT NULL,
  check_in      TIME          DEFAULT NULL,
  check_out     TIME          DEFAULT NULL,
  method        ENUM('fingerprint','face','manual','mobile') NOT NULL DEFAULT 'fingerprint',
  status        ENUM('normal','late','absent','leave') NOT NULL DEFAULT 'normal',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_staff_att (personnel_id, work_date),
  CONSTRAINT fk_staffatt_personnel FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE salaries (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  personnel_id  BIGINT UNSIGNED NOT NULL,
  pay_year      SMALLINT UNSIGNED NOT NULL,
  pay_month     TINYINT UNSIGNED NOT NULL,
  base_salary   DECIMAL(12,2) NOT NULL DEFAULT 0,
  allowance     DECIMAL(12,2) NOT NULL DEFAULT 0,
  deduction     DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_pay       DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_at       DATE          DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_salary (personnel_id, pay_year, pay_month),
  CONSTRAINT fk_salary_personnel FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  โซน G : ฝ่ายงบประมาณ / การเงิน / พัสดุ
-- =====================================================================

-- ---- แหล่งงบประมาณ --------------------------------------------------
CREATE TABLE budget_sources (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    BIGINT UNSIGNED NOT NULL,
  code         VARCHAR(40)   NOT NULL,             -- เงินอุดหนุน/รายได้สถานศึกษา ...
  name         VARCHAR(200)  NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_budget_source (school_id, code),
  CONSTRAINT fk_bs_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- งบประมาณ (จัดสรรต่อปี) ----------------------------------------
CREATE TABLE budgets (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  budget_source_id BIGINT UNSIGNED NOT NULL,
  academic_year_id BIGINT UNSIGNED NOT NULL,
  name             VARCHAR(200)  NOT NULL,
  allocated_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  used_amount      DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_budget_source (budget_source_id),
  CONSTRAINT fk_budget_source FOREIGN KEY (budget_source_id) REFERENCES budget_sources(id) ON DELETE CASCADE,
  CONSTRAINT fk_budget_ay     FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- โครงการ --------------------------------------------------------
CREATE TABLE projects (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        BIGINT UNSIGNED NOT NULL,
  budget_id        BIGINT UNSIGNED DEFAULT NULL,
  department_id    BIGINT UNSIGNED DEFAULT NULL,
  code             VARCHAR(40)   DEFAULT NULL,
  name             VARCHAR(255)  NOT NULL,
  responsible_id   BIGINT UNSIGNED DEFAULT NULL,   -- personnel ผู้รับผิดชอบ
  budget_amount    DECIMAL(14,2) NOT NULL DEFAULT 0,
  start_date       DATE          DEFAULT NULL,
  end_date         DATE          DEFAULT NULL,
  status           ENUM('planned','ongoing','completed','cancelled') NOT NULL DEFAULT 'planned',
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_project_budget (budget_id),
  KEY ix_project_status (status),
  CONSTRAINT fk_project_school FOREIGN KEY (school_id)     REFERENCES schools(id)         ON DELETE CASCADE,
  CONSTRAINT fk_project_budget FOREIGN KEY (budget_id)     REFERENCES budgets(id)         ON DELETE SET NULL,
  CONSTRAINT fk_project_dept   FOREIGN KEY (department_id) REFERENCES org_departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_project_resp   FOREIGN KEY (responsible_id) REFERENCES personnel(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ใบขอซื้อ/ขอจ้าง (PR) ------------------------------------------
CREATE TABLE purchase_requests (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     BIGINT UNSIGNED NOT NULL,
  pr_number     VARCHAR(40)   NOT NULL,
  project_id    BIGINT UNSIGNED DEFAULT NULL,
  budget_id     BIGINT UNSIGNED DEFAULT NULL,
  request_type  ENUM('purchase','hire') NOT NULL DEFAULT 'purchase',
  requester_id  BIGINT UNSIGNED DEFAULT NULL,      -- personnel
  request_date  DATE          NOT NULL,
  total_amount  DECIMAL(14,2) NOT NULL DEFAULT 0,
  status        ENUM('draft','pending','approved','rejected','po_created') NOT NULL DEFAULT 'draft',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pr_number (school_id, pr_number),
  KEY ix_pr_status (status),
  CONSTRAINT fk_pr_school   FOREIGN KEY (school_id)  REFERENCES schools(id)   ON DELETE CASCADE,
  CONSTRAINT fk_pr_project  FOREIGN KEY (project_id) REFERENCES projects(id)  ON DELETE SET NULL,
  CONSTRAINT fk_pr_budget   FOREIGN KEY (budget_id)  REFERENCES budgets(id)   ON DELETE SET NULL,
  CONSTRAINT fk_pr_requester FOREIGN KEY (requester_id) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_request_items (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_request_id BIGINT UNSIGNED NOT NULL,
  item_name    VARCHAR(255)  NOT NULL,
  quantity     DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit         VARCHAR(40)   DEFAULT NULL,
  unit_price   DECIMAL(14,2) NOT NULL DEFAULT 0,
  amount       DECIMAL(14,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_pri_pr (purchase_request_id),
  CONSTRAINT fk_pri_pr FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ใบสั่งซื้อ (PO) ------------------------------------------------
CREATE TABLE purchase_orders (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     BIGINT UNSIGNED NOT NULL,
  po_number     VARCHAR(40)   NOT NULL,
  purchase_request_id BIGINT UNSIGNED DEFAULT NULL,
  vendor_name   VARCHAR(255)  DEFAULT NULL,
  vendor_tax_id VARCHAR(20)   DEFAULT NULL,
  order_date    DATE          NOT NULL,
  total_amount  DECIMAL(14,2) NOT NULL DEFAULT 0,
  vat_amount    DECIMAL(14,2) NOT NULL DEFAULT 0,
  status        ENUM('open','received','paid','cancelled') NOT NULL DEFAULT 'open',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_po_number (school_id, po_number),
  KEY ix_po_status (status),
  CONSTRAINT fk_po_school FOREIGN KEY (school_id)          REFERENCES schools(id)           ON DELETE CASCADE,
  CONSTRAINT fk_po_pr     FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_items (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  item_name    VARCHAR(255)  NOT NULL,
  quantity     DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit         VARCHAR(40)   DEFAULT NULL,
  unit_price   DECIMAL(14,2) NOT NULL DEFAULT 0,
  amount       DECIMAL(14,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_poi_po (purchase_order_id),
  CONSTRAINT fk_poi_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- พัสดุ : หมวดครุภัณฑ์ / ครุภัณฑ์ / วัสดุ -----------------------
CREATE TABLE asset_categories (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    BIGINT UNSIGNED NOT NULL,
  code         VARCHAR(40)   NOT NULL,
  name         VARCHAR(200)  NOT NULL,
  depreciation_rate DECIMAL(5,2) DEFAULT NULL,     -- % ค่าเสื่อม/ปี
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asset_cat (school_id, code),
  CONSTRAINT fk_ac_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assets (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id        BIGINT UNSIGNED NOT NULL,
  asset_code       VARCHAR(60)   NOT NULL,         -- เลขครุภัณฑ์
  barcode          VARCHAR(80)   DEFAULT NULL,
  qr_code          VARCHAR(120)  DEFAULT NULL,
  category_id      BIGINT UNSIGNED DEFAULT NULL,
  name             VARCHAR(255)  NOT NULL,
  acquired_date    DATE          DEFAULT NULL,
  acquired_price   DECIMAL(14,2) DEFAULT NULL,
  budget_source_id BIGINT UNSIGNED DEFAULT NULL,
  location_room_id BIGINT UNSIGNED DEFAULT NULL,
  responsible_id   BIGINT UNSIGNED DEFAULT NULL,   -- personnel ผู้ครอบครอง
  condition_status ENUM('normal','repair','damaged','disposed') NOT NULL DEFAULT 'normal',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asset_code (school_id, asset_code),
  KEY ix_asset_category (category_id),
  KEY ix_asset_barcode (barcode),
  CONSTRAINT fk_asset_school   FOREIGN KEY (school_id)        REFERENCES schools(id)         ON DELETE CASCADE,
  CONSTRAINT fk_asset_category FOREIGN KEY (category_id)      REFERENCES asset_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_asset_source   FOREIGN KEY (budget_source_id) REFERENCES budget_sources(id)  ON DELETE SET NULL,
  CONSTRAINT fk_asset_room     FOREIGN KEY (location_room_id) REFERENCES rooms(id)           ON DELETE SET NULL,
  CONSTRAINT fk_asset_resp     FOREIGN KEY (responsible_id)   REFERENCES personnel(id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE materials (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    BIGINT UNSIGNED NOT NULL,
  code         VARCHAR(60)   NOT NULL,
  name         VARCHAR(255)  NOT NULL,
  unit         VARCHAR(40)   DEFAULT NULL,
  stock_qty    DECIMAL(12,2) NOT NULL DEFAULT 0,
  min_qty      DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_material (school_id, code),
  CONSTRAINT fk_material_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE material_transactions (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  material_id  BIGINT UNSIGNED NOT NULL,
  txn_type     ENUM('in','out','adjust') NOT NULL,
  quantity     DECIMAL(12,2) NOT NULL,
  balance_after DECIMAL(12,2) DEFAULT NULL,
  reference    VARCHAR(120)  DEFAULT NULL,
  requester_id BIGINT UNSIGNED DEFAULT NULL,       -- personnel เบิก
  txn_date     DATE          NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_mtxn_material (material_id, txn_date),
  CONSTRAINT fk_mtxn_material  FOREIGN KEY (material_id)  REFERENCES materials(id)  ON DELETE CASCADE,
  CONSTRAINT fk_mtxn_requester FOREIGN KEY (requester_id) REFERENCES personnel(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  โซน H : ฝ่ายบริหารทั่วไป + สารบรรณ + สื่อสาร (E-Office / เอกสาร)
-- =====================================================================

-- ---- แจ้งซ่อม / อาคารสถานที่ ---------------------------------------
CREATE TABLE repair_requests (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     BIGINT UNSIGNED NOT NULL,
  reporter_id   BIGINT UNSIGNED DEFAULT NULL,      -- users
  room_id       BIGINT UNSIGNED DEFAULT NULL,
  asset_id      BIGINT UNSIGNED DEFAULT NULL,
  title         VARCHAR(255)  NOT NULL,
  description   TEXT          DEFAULT NULL,
  priority      ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status        ENUM('reported','assigned','in_progress','done','cancelled') NOT NULL DEFAULT 'reported',
  assignee_id   BIGINT UNSIGNED DEFAULT NULL,      -- personnel ผู้รับซ่อม
  reported_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at  DATETIME      DEFAULT NULL,
  PRIMARY KEY (id),
  KEY ix_repair_status (status),
  CONSTRAINT fk_repair_school   FOREIGN KEY (school_id)    REFERENCES schools(id)   ON DELETE CASCADE,
  CONSTRAINT fk_repair_reporter FOREIGN KEY (reporter_id)  REFERENCES users(id)     ON DELETE SET NULL,
  CONSTRAINT fk_repair_room     FOREIGN KEY (room_id)      REFERENCES rooms(id)     ON DELETE SET NULL,
  CONSTRAINT fk_repair_asset    FOREIGN KEY (asset_id)     REFERENCES assets(id)    ON DELETE SET NULL,
  CONSTRAINT fk_repair_assignee FOREIGN KEY (assignee_id)  REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- การจองห้อง -----------------------------------------------------
CREATE TABLE room_bookings (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_id      BIGINT UNSIGNED NOT NULL,
  booked_by    BIGINT UNSIGNED DEFAULT NULL,       -- users
  title        VARCHAR(255)  NOT NULL,
  start_time   DATETIME      NOT NULL,
  end_time     DATETIME      NOT NULL,
  status       ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_booking_room_time (room_id, start_time),
  CONSTRAINT fk_booking_room FOREIGN KEY (room_id)   REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_user FOREIGN KEY (booked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- สารบรรณอิเล็กทรอนิกส์ (หนังสือรับ-ส่ง) ------------------------
CREATE TABLE documents (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id     BIGINT UNSIGNED NOT NULL,
  doc_number    VARCHAR(60)   DEFAULT NULL,
  doc_type      ENUM('incoming','outgoing','circular','internal') NOT NULL DEFAULT 'internal',
  title         VARCHAR(500)  NOT NULL,
  from_org      VARCHAR(255)  DEFAULT NULL,
  to_org        VARCHAR(255)  DEFAULT NULL,
  doc_date      DATE          DEFAULT NULL,
  received_date DATE          DEFAULT NULL,
  file_path     VARCHAR(255)  DEFAULT NULL,
  qr_verify_code VARCHAR(120) DEFAULT NULL,         -- สำหรับ QR Verify
  is_signed     TINYINT(1)    NOT NULL DEFAULT 0,   -- ลงนามดิจิทัลแล้ว
  status        ENUM('draft','registered','in_process','completed','archived') NOT NULL DEFAULT 'registered',
  created_by    BIGINT UNSIGNED DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_doc_type (doc_type),
  KEY ix_doc_status (status),
  KEY ix_doc_number (doc_number),
  CONSTRAINT fk_doc_school FOREIGN KEY (school_id)  REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_user   FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ผู้รับ/เส้นทางเอกสาร (routing) --------------------------------
CREATE TABLE document_recipients (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id  BIGINT UNSIGNED NOT NULL,
  recipient_id BIGINT UNSIGNED DEFAULT NULL,       -- users
  action       ENUM('forward','approve','acknowledge','sign','comment') NOT NULL DEFAULT 'acknowledge',
  status       ENUM('pending','done','rejected') NOT NULL DEFAULT 'pending',
  note         VARCHAR(500)  DEFAULT NULL,
  acted_at     DATETIME      DEFAULT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_docrecv_doc (document_id),
  KEY ix_docrecv_user (recipient_id),
  CONSTRAINT fk_docrecv_doc  FOREIGN KEY (document_id)  REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_docrecv_user FOREIGN KEY (recipient_id) REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- คำขออนุมัติทั่วไป (Approval workflow) -------------------------
CREATE TABLE approvals (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type   VARCHAR(80)   NOT NULL,            -- leave / purchase_request / project ...
  entity_id     BIGINT UNSIGNED NOT NULL,
  step_no       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  approver_id   BIGINT UNSIGNED DEFAULT NULL,      -- users
  status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  comment       VARCHAR(500)  DEFAULT NULL,
  acted_at      DATETIME      DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_approval_entity (entity_type, entity_id),
  KEY ix_approval_approver (approver_id),
  CONSTRAINT fk_approval_user FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ประกาศ/ข่าวสาร -------------------------------------------------
CREATE TABLE announcements (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_id    BIGINT UNSIGNED NOT NULL,
  title        VARCHAR(255)  NOT NULL,
  content      MEDIUMTEXT    DEFAULT NULL,
  audience     ENUM('all','teachers','students','parents') NOT NULL DEFAULT 'all',
  published_at DATETIME      DEFAULT NULL,
  created_by   BIGINT UNSIGNED DEFAULT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ann_audience (audience),
  CONSTRAINT fk_ann_school FOREIGN KEY (school_id)  REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_ann_user   FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- แจ้งเตือน (Notification) --------------------------------------
CREATE TABLE notifications (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NOT NULL,
  title        VARCHAR(255)  NOT NULL,
  body         VARCHAR(500)  DEFAULT NULL,
  link         VARCHAR(255)  DEFAULT NULL,
  is_read      TINYINT(1)    NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_notif_user (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  จบ Core Schema  (รวม ~55 ตารางหลัก)
--  ส่วนขยาย (ธนาคารข้อสอบ, ปพ., e-GP, เช็ค/ภาษี, วิทยฐานะ/PA,
--  CCTV, ห้องเรียนออนไลน์, AI logs ฯลฯ) จะต่อยอดบนฐานนี้ได้ทันที
-- =====================================================================
