-- ==================================================================
--  31_substitute_teaching.sql — จัดสอนแทน (วันที่ครูลา)
--  บันทึกการจัดครูสอนแทนรายคาบ ในวันที่ครูประจำวิชาลา
--  นำเข้าหลัง 01-30 · รันซ้ำได้ปลอดภัย (idempotent)
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS substitute_teachings (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sub_date           DATE NOT NULL,
  class_schedule_id  BIGINT UNSIGNED NOT NULL,
  absent_teacher_id  BIGINT UNSIGNED NOT NULL COMMENT 'ครูที่ลา (personnel)',
  sub_teacher_id     BIGINT UNSIGNED NOT NULL COMMENT 'ครูสอนแทน (personnel)',
  note               VARCHAR(255) DEFAULT NULL,
  created_by         BIGINT UNSIGNED DEFAULT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sub_slot (sub_date, class_schedule_id),
  KEY idx_sub_date (sub_date),
  KEY idx_sub_teacher (sub_teacher_id, sub_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- จบ 31
