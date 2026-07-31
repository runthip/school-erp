-- ==================================================================
--  41_grade_levels_full.sql — ระดับชั้นครบ อนุบาล 1-3 · ประถม 1-6 · มัธยม 1-6
--  จัด level_order ให้เรียงถูก + stage ถูกช่วงชั้น · idempotent ตาม code
-- ==================================================================
USE school_erp;
SET NAMES utf8mb4;
SET @sid := (SELECT id FROM schools ORDER BY id LIMIT 1);

INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'K1','อนุบาลปีที่ 1',1,'kindergarten' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='K1');
UPDATE grade_levels SET name='อนุบาลปีที่ 1', level_order=1, stage='kindergarten' WHERE school_id=@sid AND code='K1';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'K2','อนุบาลปีที่ 2',2,'kindergarten' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='K2');
UPDATE grade_levels SET name='อนุบาลปีที่ 2', level_order=2, stage='kindergarten' WHERE school_id=@sid AND code='K2';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'K3','อนุบาลปีที่ 3',3,'kindergarten' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='K3');
UPDATE grade_levels SET name='อนุบาลปีที่ 3', level_order=3, stage='kindergarten' WHERE school_id=@sid AND code='K3';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'P1','ประถมศึกษาปีที่ 1',4,'primary' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='P1');
UPDATE grade_levels SET name='ประถมศึกษาปีที่ 1', level_order=4, stage='primary' WHERE school_id=@sid AND code='P1';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'P2','ประถมศึกษาปีที่ 2',5,'primary' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='P2');
UPDATE grade_levels SET name='ประถมศึกษาปีที่ 2', level_order=5, stage='primary' WHERE school_id=@sid AND code='P2';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'P3','ประถมศึกษาปีที่ 3',6,'primary' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='P3');
UPDATE grade_levels SET name='ประถมศึกษาปีที่ 3', level_order=6, stage='primary' WHERE school_id=@sid AND code='P3';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'P4','ประถมศึกษาปีที่ 4',7,'primary' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='P4');
UPDATE grade_levels SET name='ประถมศึกษาปีที่ 4', level_order=7, stage='primary' WHERE school_id=@sid AND code='P4';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'P5','ประถมศึกษาปีที่ 5',8,'primary' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='P5');
UPDATE grade_levels SET name='ประถมศึกษาปีที่ 5', level_order=8, stage='primary' WHERE school_id=@sid AND code='P5';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'P6','ประถมศึกษาปีที่ 6',9,'primary' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='P6');
UPDATE grade_levels SET name='ประถมศึกษาปีที่ 6', level_order=9, stage='primary' WHERE school_id=@sid AND code='P6';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'M1','มัธยมศึกษาปีที่ 1',10,'lower_secondary' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='M1');
UPDATE grade_levels SET name='มัธยมศึกษาปีที่ 1', level_order=10, stage='lower_secondary' WHERE school_id=@sid AND code='M1';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'M2','มัธยมศึกษาปีที่ 2',11,'lower_secondary' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='M2');
UPDATE grade_levels SET name='มัธยมศึกษาปีที่ 2', level_order=11, stage='lower_secondary' WHERE school_id=@sid AND code='M2';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'M3','มัธยมศึกษาปีที่ 3',12,'lower_secondary' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='M3');
UPDATE grade_levels SET name='มัธยมศึกษาปีที่ 3', level_order=12, stage='lower_secondary' WHERE school_id=@sid AND code='M3';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'M4','มัธยมศึกษาปีที่ 4',13,'upper_secondary' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='M4');
UPDATE grade_levels SET name='มัธยมศึกษาปีที่ 4', level_order=13, stage='upper_secondary' WHERE school_id=@sid AND code='M4';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'M5','มัธยมศึกษาปีที่ 5',14,'upper_secondary' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='M5');
UPDATE grade_levels SET name='มัธยมศึกษาปีที่ 5', level_order=14, stage='upper_secondary' WHERE school_id=@sid AND code='M5';
INSERT INTO grade_levels (school_id, code, name, level_order, stage)
  SELECT @sid,'M6','มัธยมศึกษาปีที่ 6',15,'upper_secondary' WHERE NOT EXISTS (SELECT 1 FROM grade_levels WHERE school_id=@sid AND code='M6');
UPDATE grade_levels SET name='มัธยมศึกษาปีที่ 6', level_order=15, stage='upper_secondary' WHERE school_id=@sid AND code='M6';
-- จบ 41
