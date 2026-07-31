-- ==================================================================
--  45_kindergarten_v2.sql — ประเมินพัฒนาการอนุบาล: 7 สมรรถนะสำคัญปฐมวัย + ความเห็นครู/ผู้ปกครอง
--  ระดับ 1-3 (ควรส่งเสริม/พอใช้/ดี) · ต่อภาคเรียน (เทอม 1 / สรุปเทอม 2)
--  นำเข้าหลัง 01-44 · รันซ้ำได้ปลอดภัย
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;

ALTER TABLE kindergarten_assessments
  ADD COLUMN IF NOT EXISTS comp_physical  TINYINT NOT NULL DEFAULT 0,   -- การเคลื่อนไหวและสุขภาวะทางกาย
  ADD COLUMN IF NOT EXISTS comp_social    TINYINT NOT NULL DEFAULT 0,   -- สังคม
  ADD COLUMN IF NOT EXISTS comp_emotional TINYINT NOT NULL DEFAULT 0,   -- อารมณ์
  ADD COLUMN IF NOT EXISTS comp_cognitive TINYINT NOT NULL DEFAULT 0,   -- การคิดและสติปัญญา
  ADD COLUMN IF NOT EXISTS comp_language  TINYINT NOT NULL DEFAULT 0,   -- ภาษา
  ADD COLUMN IF NOT EXISTS comp_moral     TINYINT NOT NULL DEFAULT 0,   -- จริยธรรม
  ADD COLUMN IF NOT EXISTS comp_creative  TINYINT NOT NULL DEFAULT 0,   -- การคิดสร้างสรรค์
  ADD COLUMN IF NOT EXISTS teacher_comment VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS parent_comment  VARCHAR(500) NULL;

-- จบ 45
