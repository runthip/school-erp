<?php
namespace App\Models;
use App\Core\Model;

/** ระบบจัดตารางสอน: อ่านเงื่อนไข, ตรวจชน, จัดอัตโนมัติ (heuristic), บันทึก */
class Timetable extends Model
{
    protected string $table = 'class_schedules';

    public function settings(): array
    {
        $rows=$this->query("SELECT setting_key, setting_value FROM system_settings WHERE group_key='timetable'");
        $m=[]; foreach($rows as $r) $m[$r['setting_key']]=(int)$r['setting_value'];
        $str=$this->query("SELECT setting_key, setting_value FROM system_settings WHERE group_key='timetable'
            AND setting_key IN ('start_time','start_primary','start_secondary')");
        $sv=[]; foreach($str as $r) $sv[$r['setting_key']]=$r['setting_value'];
        $legacy=$sv['start_time']??'08:30';
        $startPrimary=$sv['start_primary']??$legacy;      // อนุบาล + ประถม
        $startSecondary=$sv['start_secondary']??$legacy;  // มัธยม (แยกเวลาได้)
        return [
            'periods'=>$m['periods_per_day']??8,
            'periods_primary'=>$m['periods_primary']??6,
            'periods_secondary'=>$m['periods_secondary']??7,
            'lunch_primary'=>$m['lunch_primary']??4,
            'lunch_secondary'=>$m['lunch_secondary']??5,
            'days'=>$m['days_per_week']??5,
            'lunch'=>$m['lunch_period']??5,
            'maxPerSubjectDay'=>$m['max_per_subject_per_day']??2,
            'maxConsecutive'=>$m['max_consecutive']??3,
            'morningLast'=>$m['morning_last_period']??4,
            'teacherMaxWeekly'=>$m['teacher_max_weekly']??25,
            'start_primary'=>$startPrimary,
            'start_secondary'=>$startSecondary,
            'start_time'=>$startPrimary,   // เผื่อโค้ดเก่าอ้างอิง
        ];
    }

    /** ระดับของห้อง: primary | secondary */
    public function classroomLevel(int $classroomId): string
    {
        $r=$this->first("SELECT gl.stage FROM classrooms c JOIN grade_levels gl ON gl.id=c.grade_level_id WHERE c.id=?", [$classroomId]);
        return ($r && $r['stage']==='primary') || ($r && $r['stage']==='kindergarten') ? 'primary' : 'secondary';
    }
    /** ค่าตั้งค่าตามระดับห้อง (ประถม 6 คาบ / มัธยม 7 คาบ) */
    public function settingsFor(int $classroomId): array
    {
        $cfg=$this->settings();
        $level=$this->classroomLevel($classroomId);
        $cfg['level']=$level;
        $cfg['periods']=$level==='primary'?$cfg['periods_primary']:$cfg['periods_secondary'];
        $cfg['lunch']=$level==='primary'?$cfg['lunch_primary']:$cfg['lunch_secondary'];
        $cfg['start']=$level==='primary'?$cfg['start_primary']:$cfg['start_secondary'];
        return $cfg;
    }

    public function currentSemesterId(): int
    { return (int)($this->first("SELECT id FROM semesters WHERE is_current=1 LIMIT 1")['id']??1); }

    public function saveSetting(string $key, string $val): void
    {
        $this->execute("INSERT INTO system_settings (group_key, setting_key, setting_value, value_type) VALUES ('timetable',?,?, 'int')
            ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", [$key,$val]);
    }

    public function rooms(): array { return $this->query("SELECT id, room_code, name, room_type FROM rooms ORDER BY room_type, name"); }

    /** ห้องประจำชั้นของห้องเรียน (จาก classrooms.room_id ถ้ามี ไม่งั้นใช้ชื่อห้องเรียน) */
    public function homeroomName(int $classroomId): string
    {
        $r=$this->first("SELECT c.name AS cname, rm.name AS rname
            FROM classrooms c LEFT JOIN rooms rm ON rm.id=c.room_id WHERE c.id=?", [$classroomId]);
        if(!$r) return '';
        return trim((string)($r['rname'] ?: $r['cname']));
    }

    /** teaching assignments ของห้อง พร้อมคุณสมบัติวิชา */
    public function classAssignments(int $classroomId, int $semId): array
    {
        return $this->query("SELECT ta.id, ta.teacher_id, ta.weekly_periods,
            s.subject_code, s.name_th AS subject_name, s.required_room_type, s.is_heavy, s.is_pe, s.block_size, s.subject_group_id,
            CONCAT(p.first_name,' ',p.last_name) AS teacher_name, p.is_part_time, p.work_days, p.max_weekly_periods
            FROM teaching_assignments ta
            JOIN subjects s ON s.id=ta.subject_id
            JOIN personnel p ON p.id=ta.teacher_id
            WHERE ta.classroom_id=? AND ta.semester_id=? ORDER BY s.is_heavy DESC, s.block_size DESC, ta.weekly_periods DESC", [$classroomId,$semId]);
    }

    /** ตารางปัจจุบันของห้อง → [day][period] = row */
    public function grid(int $classroomId, int $semId): array
    {
        $rows=$this->query("SELECT cs.*, ta.subject_id, ta.teacher_id, s.subject_code, s.name_th AS subject_name,
            r.name AS room_name, CONCAT(pe.first_name,' ',pe.last_name) AS teacher_name
            FROM class_schedules cs
            JOIN teaching_assignments ta ON ta.id=cs.teaching_assignment_id
            JOIN subjects s ON s.id=ta.subject_id
            JOIN personnel pe ON pe.id=ta.teacher_id
            LEFT JOIN rooms r ON r.id=cs.room_id
            WHERE ta.classroom_id=? AND ta.semester_id=? ", [$classroomId,$semId]);
        $g=[]; foreach($rows as $r) $g[(int)$r['day_of_week']][(int)$r['period_no']]=$r;
        return $g;
    }

    /** ครูไม่ว่าง (รวม part-time work_days) → set["tid-day-period"] และ ["tid-day-*"] */
    public function teacherUnavail(): array
    {
        $set=[];
        foreach($this->query("SELECT teacher_id, day_of_week, period_no FROM teacher_unavailable") as $r){
            $key=$r['period_no']!==null ? "{$r['teacher_id']}-{$r['day_of_week']}-{$r['period_no']}" : "{$r['teacher_id']}-{$r['day_of_week']}-*";
            $set[$key]=true;
        }
        // part-time: วันที่ไม่ได้อยู่ใน work_days = ไม่ว่างทั้งวัน
        foreach($this->query("SELECT id, work_days FROM personnel WHERE is_part_time=1 AND work_days IS NOT NULL AND work_days<>''") as $p){
            $days=array_map('intval',explode(',',$p['work_days']));
            for($d=1;$d<=7;$d++){ if(!in_array($d,$days,true)) $set["{$p['id']}-{$d}-*"]=true; }
        }
        return $set;
    }

    /** ช่องที่ครู/ห้องพิเศษถูกใช้ (ทุกห้องยกเว้นห้องที่กำลังจัด) */
    public function globalBusy(int $exceptClassroomId, int $semId): array
    {
        $rows=$this->query("SELECT cs.day_of_week d, cs.period_no p, cs.room_id, ta.teacher_id, ta.classroom_id
            FROM class_schedules cs JOIN teaching_assignments ta ON ta.id=cs.teaching_assignment_id
            WHERE ta.semester_id=? AND ta.classroom_id<>?", [$semId,$exceptClassroomId]);
        $teacher=[]; $room=[];
        foreach($rows as $r){
            $teacher["{$r['teacher_id']}-{$r['d']}-{$r['p']}"]=true;
            if($r['room_id']) $room["{$r['room_id']}-{$r['d']}-{$r['p']}"]=true;
        }
        return ['teacher'=>$teacher,'room'=>$room];
    }

    public function roomsByType(): array
    {
        $m=[]; foreach($this->rooms() as $r) $m[$r['room_type']][]=(int)$r['id'];
        return $m;
    }

    // ---------- จัดตารางอัตโนมัติ (heuristic greedy) ----------
    public function autoGenerate(int $classroomId, int $semId): array
    {
        $cfg=$this->settingsFor($classroomId);
        $D=$cfg['days']; $P=$cfg['periods']; $lunch=$cfg['lunch'];
        $assigns=$this->classAssignments($classroomId,$semId);
        $busy=$this->globalBusy($classroomId,$semId);
        $unavail=$this->teacherUnavail();
        $roomsByType=$this->roomsByType();

        // เก็บช่องที่ล็อกไว้เดิม (ไม่ทับ) + นับ busy จากล็อก
        $existing=$this->grid($classroomId,$semId);
        $cells=[]; // [d][p] = ['ta'=>id,'room'=>id,'locked'=>bool,'subject'=>code]
        $teacherUsed=[]; $roomUsed=[]; $subjPerDay=[]; $peDay=[]; $teacherWeekly=[];
        foreach($existing as $d=>$ps){ foreach($ps as $p=>$row){
            if(!empty($row['is_locked'])){
                $cells[$d][$p]=['ta'=>(int)$row['teaching_assignment_id'],'room'=>$row['room_id']?(int)$row['room_id']:null,'locked'=>true,'subject'=>$row['subject_code']];
                $teacherUsed["{$row['teacher_id']}-$d-$p"]=true;
                if($row['room_id']) $roomUsed["{$row['room_id']}-$d-$p"]=true;
                $subjPerDay["{$row['subject_id']}-$d"]=($subjPerDay["{$row['subject_id']}-$d"]??0)+1;
                $teacherWeekly[$row['teacher_id']]=($teacherWeekly[$row['teacher_id']]??0)+1;
            }
        }}

        $unplaced=[];

        foreach($assigns as $a){
            $taId=(int)$a['id']; $tid=(int)$a['teacher_id'];
            $need=(int)$a['weekly_periods']; $block=max(1,(int)$a['block_size']);
            $reqType=$a['required_room_type']; $isHeavy=(int)$a['is_heavy']; $isPe=(int)$a['is_pe'];
            $maxWeekly=$a['max_weekly_periods']?(int)$a['max_weekly_periods']:$cfg['teacherMaxWeekly'];

            // แตกเป็นบล็อก
            $units=[]; $left=$need;
            while($left>0){ $sz=min($block,$left); $units[]=$sz; $left-=$sz; }

            foreach($units as $size){
                $placed=false;
                // ลำดับวัน (กระจาย) และคาบ (วิชาหนัก→เช้าก่อน)
                $dayOrder=range(1,$D);
                $periodOrder=range(1,$P);
                if($isHeavy) usort($periodOrder,fn($x,$y)=>$x-$y); // เช้าก่อน (1..P)
                foreach($dayOrder as $d){
                    // เงื่อนไข part-time / ครูไม่ว่างทั้งวัน
                    if(isset($unavail["$tid-$d-*"])) continue;
                    // จำกัดวิชา/วัน
                    if(($subjPerDay["{$a['subject_code']}-$d"]??0) >= $cfg['maxPerSubjectDay']) continue;
                    if($isPe && ($peDay["$d"]??0) >= 1) continue;

                    foreach($periodOrder as $startP){
                        if($startP+$size-1 > $P) continue;
                        // บล็อกต้องไม่คร่อมพักกลางวัน และไม่ตรงคาบพัก
                        $slots=range($startP,$startP+$size-1);
                        if(in_array($lunch,$slots,true)) continue;
                        // วิชาหนักไว้เช้า: ถ้า heavy และเริ่มหลัง morningLast ให้ข้าม (ยอมเฉพาะเมื่อจำเป็นภายหลัง)
                        // ตรวจว่างทุกคาบในบล็อก
                        $ok=true; $roomId=null;
                        foreach($slots as $p){
                            if(isset($cells[$d][$p])){ $ok=false; break; }
                            if(isset($teacherUsed["$tid-$d-$p"]) || isset($busy['teacher']["$tid-$d-$p"])){ $ok=false; break; }
                            if(isset($unavail["$tid-$d-$p"])){ $ok=false; break; }
                        }
                        if(!$ok) continue;
                        // ครูเกินชั่วโมง/สัปดาห์
                        if(($teacherWeekly[$tid]??0)+$size > $maxWeekly) { $ok=false; }
                        if(!$ok) continue;
                        // ห้องพิเศษ: หา 1 ห้องที่ว่างตลอดบล็อก
                        if($reqType!=='any'){
                            $cand=$roomsByType[$reqType]??[];
                            foreach($cand as $rid){
                                $free=true;
                                foreach($slots as $p){ if(isset($roomUsed["$rid-$d-$p"])||isset($busy['room']["$rid-$d-$p"])){ $free=false; break; } }
                                if($free){ $roomId=$rid; break; }
                            }
                            if($roomId===null) continue; // ไม่มีห้องพิเศษว่าง
                        }
                        // วางได้!
                        foreach($slots as $p){
                            $cells[$d][$p]=['ta'=>$taId,'room'=>$roomId,'locked'=>false,'subject'=>$a['subject_code']];
                            $teacherUsed["$tid-$d-$p"]=true;
                            if($roomId) $roomUsed["$roomId-$d-$p"]=true;
                        }
                        $subjPerDay["{$a['subject_code']}-$d"]=($subjPerDay["{$a['subject_code']}-$d"]??0)+$size;
                        if($isPe) $peDay["$d"]=($peDay["$d"]??0)+1;
                        $teacherWeekly[$tid]=($teacherWeekly[$tid]??0)+$size;
                        $placed=true; break 2;
                    }
                }
                if(!$placed) $unplaced[]=$a['subject_name'].' ('.$a['subject_code'].')';
            }
        }

        // บันทึกลง DB (ลบของเดิมที่ไม่ล็อก)
        $this->saveCells($classroomId,$semId,$cells);
        return ['unplaced'=>array_values(array_unique($unplaced)),'placed'=>true];
    }

    /** บันทึกตาราง: ลบเฉพาะที่ไม่ล็อก แล้วเขียนใหม่ (ยกเว้นล็อก) */
    public function saveCells(int $classroomId, int $semId, array $cells): void
    {
        $taIds=$this->query("SELECT id FROM teaching_assignments WHERE classroom_id=? AND semester_id=?", [$classroomId,$semId]);
        $ids=array_map(fn($r)=>(int)$r['id'],$taIds);
        if($ids){
            $in=implode(',',$ids);
            $this->execute("DELETE FROM class_schedules WHERE teaching_assignment_id IN ($in) AND is_locked=0");
        }
        $cfg=$this->settingsFor($classroomId);
        foreach($cells as $d=>$ps){ foreach($ps as $p=>$c){
            if($c['locked']) continue; // ล็อกยังอยู่ ไม่แตะ
            [$st,$en]=$this->periodTime((int)$p,$cfg);
            $this->execute("INSERT INTO class_schedules (teaching_assignment_id, day_of_week, period_no, start_time, end_time, room_id, is_locked)
                VALUES (?,?,?,?,?,?,0)", [$c['ta'],$d,$p,$st,$en,$c['room']]);
        }}
    }

    /** เวลาคาบโดยประมาณ (คาบละ 50 นาที เริ่ม 08:30 เว้นพักกลางวัน) */
    public function periodTime(int $p, array $cfg): array
    {
        $base=strtotime($cfg['start'] ?? $cfg['start_primary'] ?? '08:30'); $len=50*60;
        // เลื่อนพักกลางวัน 50 นาที หลังคาบ lunch-1
        $offset=($p-1)*$len; if($p> $cfg['lunch']) $offset+=$len;
        $st=$base+$offset; $en=$st+$len;
        return [date('H:i',$st),date('H:i',$en)];
    }

    /** ลบตาราง (ยกเว้นล็อก ถ้า $all=false) */
    public function clearGrid(int $classroomId, int $semId, bool $all=false): void
    {
        $taIds=$this->query("SELECT id FROM teaching_assignments WHERE classroom_id=? AND semester_id=?", [$classroomId,$semId]);
        $ids=array_map(fn($r)=>(int)$r['id'],$taIds);
        if(!$ids) return;
        $in=implode(',',$ids);
        $this->execute("DELETE FROM class_schedules WHERE teaching_assignment_id IN ($in)".($all?'':' AND is_locked=0'));
    }

    // ---------- สถิติการจัด ----------
    /** ต่อห้อง: needed/placed/percent + รายวิชา */
    public function stats(int $classroomId, int $semId): array
    {
        $assigns=$this->classAssignments($classroomId,$semId);
        $placedRows=$this->query("SELECT teaching_assignment_id ta, COUNT(*) c FROM class_schedules cs
            JOIN teaching_assignments t ON t.id=cs.teaching_assignment_id
            WHERE t.classroom_id=? AND t.semester_id=? GROUP BY teaching_assignment_id", [$classroomId,$semId]);
        $placedMap=[]; foreach($placedRows as $r) $placedMap[(int)$r['ta']]=(int)$r['c'];
        $needed=0; $placed=0; $subjects=[]; $doneSubj=0; $pendingSubj=0;
        foreach($assigns as $a){
            $n=(int)$a['weekly_periods']; $p=min($placedMap[(int)$a['id']]??0,$n);
            $needed+=$n; $placed+=$p;
            $full=$p>=$n;
            if($full) $doneSubj++; else $pendingSubj++;
            $subjects[]=['ta'=>(int)$a['id'],'code'=>$a['subject_code'],'name'=>$a['subject_name'],
                'teacher'=>$a['teacher_name'],'needed'=>$n,'placed'=>$p,'full'=>$full];
        }
        return ['needed'=>$needed,'placed'=>$placed,
            'percent'=>$needed>0?round($placed/$needed*100):0,
            'subjects'=>$subjects,'done_subjects'=>$doneSubj,'pending_subjects'=>$pendingSubj,
            'total_subjects'=>count($assigns)];
    }
    /** สรุปทุกห้อง (สำหรับ Dashboard) */
    public function summaryAll(int $semId): array
    {
        $rooms=$this->query("SELECT c.id, c.name, gl.stage, gl.name AS grade_name FROM classrooms c
            JOIN grade_levels gl ON gl.id=c.grade_level_id ORDER BY gl.level_order, c.section");
        $out=[];
        foreach($rooms as $r){
            $st=$this->stats((int)$r['id'],$semId);
            $pub=$this->first("SELECT published_at FROM timetable_publications WHERE classroom_id=? AND semester_id=?", [(int)$r['id'],$semId]);
            $out[]=array_merge($r,['stats'=>$st,'published'=>$pub['published_at']??null,
                'level'=>in_array($r['stage'],['primary','kindergarten'],true)?'primary':'secondary']);
        }
        return $out;
    }

    // ---------- ตรวจสอบตาราง ----------
    public function validateGrid(int $classroomId, int $semId): array
    {
        $issues=[];
        $cfg=$this->settingsFor($classroomId);
        // ครูซ้อน (ทั้งระบบ)
        foreach($this->query("SELECT t.teacher_id, cs.day_of_week d, cs.period_no p, COUNT(*) c,
            GROUP_CONCAT(DISTINCT t.classroom_id) rooms
            FROM class_schedules cs JOIN teaching_assignments t ON t.id=cs.teaching_assignment_id
            WHERE t.semester_id=? GROUP BY t.teacher_id, cs.day_of_week, cs.period_no HAVING c>1", [$semId]) as $r){
            $issues[]="ครู #{$r['teacher_id']} สอนซ้อน วัน {$r['d']} คาบ {$r['p']} (ห้อง {$r['rooms']})";
        }
        // ห้องพิเศษซ้อน
        foreach($this->query("SELECT cs.room_id, cs.day_of_week d, cs.period_no p, COUNT(*) c
            FROM class_schedules cs JOIN teaching_assignments t ON t.id=cs.teaching_assignment_id
            WHERE t.semester_id=? AND cs.room_id IS NOT NULL GROUP BY cs.room_id, cs.day_of_week, cs.period_no HAVING c>1", [$semId]) as $r){
            $issues[]="ห้องพิเศษ #{$r['room_id']} ถูกใช้ซ้อน วัน {$r['d']} คาบ {$r['p']}";
        }
        // คาบพักกลางวันของห้องนี้
        foreach($this->query("SELECT cs.day_of_week d FROM class_schedules cs
            JOIN teaching_assignments t ON t.id=cs.teaching_assignment_id
            WHERE t.classroom_id=? AND t.semester_id=? AND cs.period_no=?", [$classroomId,$semId,$cfg['lunch']]) as $r){
            $issues[]="มีคาบสอนตรงพักกลางวัน วัน {$r['d']} (คาบ {$cfg['lunch']})";
        }
        // เกินคาบของระดับ
        foreach($this->query("SELECT cs.day_of_week d, cs.period_no p FROM class_schedules cs
            JOIN teaching_assignments t ON t.id=cs.teaching_assignment_id
            WHERE t.classroom_id=? AND t.semester_id=? AND cs.period_no>?", [$classroomId,$semId,$cfg['periods']]) as $r){
            $issues[]="คาบ {$r['p']} วัน {$r['d']} เกินจำนวนคาบของระดับนี้ ({$cfg['periods']} คาบ)";
        }
        // วิชาเกิน/วัน
        foreach($this->query("SELECT s.name_th n, cs.day_of_week d, COUNT(*) c
            FROM class_schedules cs JOIN teaching_assignments t ON t.id=cs.teaching_assignment_id
            JOIN subjects s ON s.id=t.subject_id
            WHERE t.classroom_id=? AND t.semester_id=? GROUP BY t.subject_id, cs.day_of_week HAVING c>?",
            [$classroomId,$semId,$cfg['maxPerSubjectDay']]) as $r){
            $issues[]="วิชา {$r['n']} เกิน {$cfg['maxPerSubjectDay']} คาบ/วัน (วัน {$r['d']}: {$r['c']} คาบ)";
        }
        // วิชาที่ยังจัดไม่ครบ
        $st=$this->stats($classroomId,$semId);
        foreach($st['subjects'] as $s2){
            if(!$s2['full']) $issues[]="วิชา {$s2['name']} จัดแล้ว {$s2['placed']}/{$s2['needed']} คาบ (ยังไม่ครบ)";
        }
        return $issues;
    }

    // ---------- เผยแพร่ ----------
    public function isPublished(int $classroomId, int $semId): ?string
    {
        $r=$this->first("SELECT published_at FROM timetable_publications WHERE classroom_id=? AND semester_id=?", [$classroomId,$semId]);
        return $r['published_at']??null;
    }
    public function publish(int $classroomId, int $semId, ?int $by): void
    {
        $this->execute("INSERT INTO timetable_publications (classroom_id, semester_id, published_by)
            VALUES (?,?,?) ON DUPLICATE KEY UPDATE published_by=VALUES(published_by), published_at=NOW()",
            [$classroomId,$semId,$by]);
    }
    public function unpublish(int $classroomId, int $semId): void
    { $this->execute("DELETE FROM timetable_publications WHERE classroom_id=? AND semester_id=?", [$classroomId,$semId]); }

    // ---------- ข้อมูล Dashboard ----------
    public function dashboardData(int $semId): array
    {
        $one=fn($sql)=>(int)($this->first($sql)['c']??0);
        $sem=$this->first("SELECT sem.term, ay.year_be FROM semesters sem JOIN academic_years ay ON ay.id=sem.academic_year_id WHERE sem.id=?", [$semId]);
        $cfg=$this->settings();
        return [
            'teachers'=>$one("SELECT COUNT(*) c FROM personnel WHERE deleted_at IS NULL AND status='active'"),
            'students'=>$one("SELECT COUNT(*) c FROM students WHERE deleted_at IS NULL AND status='studying'"),
            'classrooms'=>$one("SELECT COUNT(*) c FROM classrooms"),
            'subjects'=>$one("SELECT COUNT(*) c FROM subjects WHERE is_active=1"),
            'grade_levels'=>$one("SELECT COUNT(*) c FROM grade_levels"),
            'buildings'=>$one("SELECT COUNT(*) c FROM buildings"),
            'special_rooms'=>$this->query("SELECT room_code, name, room_type FROM rooms WHERE room_type IN ('lab','computer','gym') ORDER BY room_type"),
            'semester'=>$sem,
            'cfg'=>$cfg,
            'assignments'=>$one("SELECT COUNT(*) c FROM teaching_assignments WHERE semester_id=".$semId),
        ];
    }

    // ---------- สำหรับลาก-วาง (ตรวจชนแบบสด ฝั่ง server ตอนบันทึก) ----------
    public function placeOne(int $taId, int $d, int $p, ?int $roomId, bool $lock): array
    {
        // ตรวจชนครู/ห้องกับห้องอื่น
        $ta=$this->first("SELECT ta.teacher_id, ta.classroom_id, ta.semester_id, s.required_room_type
            FROM teaching_assignments ta JOIN subjects s ON s.id=ta.subject_id WHERE ta.id=?", [$taId]);
        if(!$ta) return ['ok'=>false,'msg'=>'ไม่พบวิชา'];
        $cfg=$this->settingsFor((int)$ta['classroom_id']);
        if($p > $cfg['periods']) return ['ok'=>false,'msg'=>'เกินจำนวนคาบของระดับนี้ ('.$cfg['periods'].' คาบ)'];
        if($p==$cfg['lunch']) return ['ok'=>false,'msg'=>'ห้ามจัดคาบพักกลางวัน'];
        // ครูซ้อน
        $c=$this->first("SELECT COUNT(*) c FROM class_schedules cs JOIN teaching_assignments t ON t.id=cs.teaching_assignment_id
            WHERE t.teacher_id=? AND cs.day_of_week=? AND cs.period_no=? AND t.classroom_id<>? AND t.semester_id=?",
            [$ta['teacher_id'],$d,$p,$ta['classroom_id'],$ta['semester_id']]);
        if((int)$c['c']>0) return ['ok'=>false,'msg'=>'ครูสอนซ้อนเวลากับอีกห้อง'];
        // ห้องพิเศษซ้อน
        if($roomId){
            $c=$this->first("SELECT COUNT(*) c FROM class_schedules cs JOIN teaching_assignments t ON t.id=cs.teaching_assignment_id
                WHERE cs.room_id=? AND cs.day_of_week=? AND cs.period_no=? AND t.classroom_id<>? AND t.semester_id=?",
                [$roomId,$d,$p,$ta['classroom_id'],$ta['semester_id']]);
            if((int)$c['c']>0) return ['ok'=>false,'msg'=>'ห้องถูกใช้เวลาเดียวกัน'];
        }
        [$st,$en]=$this->periodTime($p,$cfg);
        // ลบคาบเดิมในช่องนี้ของห้องนี้ แล้วใส่ใหม่
        $this->execute("DELETE cs FROM class_schedules cs JOIN teaching_assignments t ON t.id=cs.teaching_assignment_id
            WHERE t.classroom_id=? AND t.semester_id=? AND cs.day_of_week=? AND cs.period_no=?",
            [$ta['classroom_id'],$ta['semester_id'],$d,$p]);
        $this->execute("INSERT INTO class_schedules (teaching_assignment_id, day_of_week, period_no, start_time, end_time, room_id, is_locked)
            VALUES (?,?,?,?,?,?,?)", [$taId,$d,$p,$st,$en,$roomId,$lock?1:0]);
        return ['ok'=>true];
    }

    public function removeOne(int $classroomId, int $semId, int $d, int $p): void
    {
        $this->execute("DELETE cs FROM class_schedules cs JOIN teaching_assignments t ON t.id=cs.teaching_assignment_id
            WHERE t.classroom_id=? AND t.semester_id=? AND cs.day_of_week=? AND cs.period_no=?", [$classroomId,$semId,$d,$p]);
    }
    public function toggleLock(int $classroomId, int $semId, int $d, int $p): void
    {
        $this->execute("UPDATE class_schedules cs JOIN teaching_assignments t ON t.id=cs.teaching_assignment_id
            SET cs.is_locked = 1-cs.is_locked
            WHERE t.classroom_id=? AND t.semester_id=? AND cs.day_of_week=? AND cs.period_no=?", [$classroomId,$semId,$d,$p]);
    }
}
