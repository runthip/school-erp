<?php
namespace App\Models;
use App\Core\Model;

/**
 * ประเมินพัฒนาการเด็กปฐมวัย (อนุบาล) — 7 สมรรถนะสำคัญ
 * ระดับ 1=ควรส่งเสริม 2=พอใช้ 3=ดี · ต่อภาคเรียน (เทอม 1 / สรุปเทอม 2)
 * + ความเห็นครู/ผู้ปกครอง + คำนวณค่าเฉลี่ยร้อยละอัตโนมัติ (สิทธิ์ academic.grades)
 */
class Kindergarten extends Model
{
    public const DOMAINS = [
        'comp_physical'  => 'การเคลื่อนไหวและสุขภาวะทางกาย',
        'comp_social'    => 'พัฒนาการด้านสังคม',
        'comp_emotional' => 'พัฒนาการด้านอารมณ์',
        'comp_cognitive' => 'พัฒนาการด้านการคิดและสติปัญญา',
        'comp_language'  => 'พัฒนาการด้านภาษา',
        'comp_moral'     => 'พัฒนาการด้านจริยธรรม',
        'comp_creative'  => 'พัฒนาการด้านการคิดสร้างสรรค์',
    ];
    public const LEVELS = [1=>'ควรส่งเสริม', 2=>'พอใช้', 3=>'ดี'];

    /** ร้อยละของนักเรียนคนหนึ่ง (เฉลี่ยจากด้านที่ประเมินแล้ว) — null ถ้ายังไม่ประเมิน */
    public static function studentPercent(array $a): ?float
    {
        $vals=[]; foreach(self::DOMAINS as $k=>$_){ $v=(int)($a[$k]??0); if($v>0) $vals[]=$v/3*100; }
        return $vals ? round(array_sum($vals)/count($vals),1) : null;
    }

    public function classrooms(): array
    {
        return $this->query("SELECT c.id, c.name, gl.name AS level
            FROM classrooms c JOIN grade_levels gl ON gl.id=c.grade_level_id
            WHERE gl.stage='kindergarten' ORDER BY gl.level_order, c.name");
    }
    public function classInfo(int $classroomId): ?array
    {
        return $this->first("SELECT c.id, c.name, gl.name AS level,
                CONCAT(t.prefix,t.first_name,' ',t.last_name) AS advisor
            FROM classrooms c JOIN grade_levels gl ON gl.id=c.grade_level_id
            LEFT JOIN personnel t ON t.id=c.homeroom_teacher_id WHERE c.id=?", [$classroomId]);
    }
    public function students(int $classroomId): array
    {
        return $this->query("SELECT s.id, s.student_code, s.prefix, s.first_name, s.last_name, se.roll_number
            FROM student_enrollments se
            JOIN students s ON s.id=se.student_id AND s.deleted_at IS NULL
            WHERE se.classroom_id=? AND se.status='active'
            ORDER BY se.roll_number, s.student_code", [$classroomId]);
    }
    public function assessments(int $classroomId, int $semesterId): array
    {
        $rows=$this->query("SELECT ka.* FROM kindergarten_assessments ka
            JOIN student_enrollments se ON se.student_id=ka.student_id AND se.classroom_id=? AND se.status='active'
            WHERE ka.semester_id=?", [$classroomId, $semesterId]);
        $m=[]; foreach($rows as $r) $m[(int)$r['student_id']]=$r; return $m;
    }

    public function save(int $studentId, int $semesterId, array $d, ?int $by): void
    {
        $g=fn($k)=>in_array((int)($d[$k]??0),[0,1,2,3],true)?(int)($d[$k]??0):0;
        $cols=array_keys(self::DOMAINS);
        $set=implode(',', $cols);
        $ph=implode(',', array_fill(0,count($cols),'?'));
        $vals=array_map($g, $cols);
        $upd=implode(',', array_map(fn($c)=>"$c=VALUES($c)", $cols));
        $this->execute("INSERT INTO kindergarten_assessments (student_id, semester_id, $set, teacher_comment, parent_comment, assessed_by)
                VALUES (?,?,$ph,?,?,?)
            ON DUPLICATE KEY UPDATE $upd, teacher_comment=VALUES(teacher_comment), parent_comment=VALUES(parent_comment), assessed_by=VALUES(assessed_by)",
            array_merge([$studentId,$semesterId], $vals,
                [trim((string)($d['teacher_comment']??''))?:null, trim((string)($d['parent_comment']??''))?:null, $by]));
    }

    /** สถิติ/แดชบอร์ด: ประเมินแล้วกี่คน + ค่าเฉลี่ยร้อยละรายสมรรถนะ + ภาพรวม */
    public function stats(int $classroomId, int $semesterId): array
    {
        $students=$this->students($classroomId);
        $map=$this->assessments($classroomId,$semesterId);
        $total=count($students); $assessed=0; $overall=[]; $perDom=[];
        foreach(self::DOMAINS as $k=>$_) $perDom[$k]=[];
        foreach($students as $s){
            $a=$map[(int)$s['id']]??null; if(!$a) continue;
            $p=self::studentPercent($a); if($p!==null){ $assessed++; $overall[]=$p; }
            foreach(self::DOMAINS as $k=>$_){ $v=(int)($a[$k]??0); if($v>0) $perDom[$k][]=$v/3*100; }
        }
        $avg=fn($arr)=>$arr?round(array_sum($arr)/count($arr),1):0;
        $pd=[]; foreach($perDom as $k=>$arr) $pd[$k]=$avg($arr);
        return ['total'=>$total,'assessed'=>$assessed,'overall'=>$avg($overall),'perDomain'=>$pd];
    }

    public function semesters(): array
    {
        return $this->query("SELECT sem.id, ay.year_be, sem.term, sem.is_current
            FROM semesters sem JOIN academic_years ay ON ay.id=sem.academic_year_id
            ORDER BY ay.year_be DESC, sem.term");
    }
    public function currentSemesterId(): int
    { return (int)($this->first("SELECT id FROM semesters WHERE is_current=1 LIMIT 1")['id'] ?? 0); }

    // ---------- หัวข้อประเมินย่อยรายสมรรถนะ (ครูกำหนดเอง) ----------
    /** หัวข้อประเมินทั้งหมด จัดกลุ่มตามด้าน [domain => [ {id,title}, ... ]] */
    public function indicatorsByDomain(): array
    {
        $rows=$this->query("SELECT id, domain, title FROM kg_indicators WHERE active=1 ORDER BY domain, sort_order, id");
        $m=[]; foreach(array_keys(self::DOMAINS) as $k) $m[$k]=[];
        foreach($rows as $r){ if(isset($m[$r['domain']])) $m[$r['domain']][]=$r; }
        return $m;
    }
    public function indicatorAdd(string $domain, string $title, ?int $by): int
    {
        if(!isset(self::DOMAINS[$domain])) return 0;
        $n=(int)($this->first("SELECT COALESCE(MAX(sort_order),0)+1 s FROM kg_indicators WHERE domain=?",[$domain])['s'] ?? 1);
        $this->execute("INSERT INTO kg_indicators (domain, title, sort_order, created_by) VALUES (?,?,?,?)",
            [$domain, trim($title), $n, $by]);
        return $this->lastId();
    }
    public function indicatorFind(int $id): ?array
    { return $this->first("SELECT * FROM kg_indicators WHERE id=?", [$id]); }
    public function indicatorDelete(int $id): void
    {
        $this->execute("DELETE FROM kg_indicator_scores WHERE indicator_id=?", [$id]);
        $this->execute("DELETE FROM kg_indicators WHERE id=?", [$id]);
    }

    /** คะแนนรายหัวข้อของทั้งห้อง+ภาคเรียน → map[student_id][indicator_id]=value */
    public function indicatorScores(int $classroomId, int $semesterId): array
    {
        $rows=$this->query("SELECT s.student_id, s.indicator_id, s.value
            FROM kg_indicator_scores s
            JOIN student_enrollments se ON se.student_id=s.student_id AND se.classroom_id=? AND se.status='active'
            WHERE s.semester_id=?", [$classroomId, $semesterId]);
        $m=[]; foreach($rows as $r) $m[(int)$r['student_id']][(int)$r['indicator_id']]=(int)$r['value']; return $m;
    }
    public function saveIndicatorScore(int $studentId, int $semesterId, int $indicatorId, int $value, ?int $by): void
    {
        $v=in_array($value,[0,1,2,3],true)?$value:0;
        $this->execute("INSERT INTO kg_indicator_scores (student_id, semester_id, indicator_id, value, updated_by)
                VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE value=VALUES(value), updated_by=VALUES(updated_by)",
            [$studentId,$semesterId,$indicatorId,$v,$by]);
    }

    // ---------- สมุดรายงานประจำตัวเด็กปฐมวัย (รายบุคคล) ----------
    public function reportStudent(int $id): ?array
    {
        return $this->first("SELECT s.*, CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name,
                se.roll_number, c.name AS classroom, gl.name AS level,
                CONCAT(t.prefix,t.first_name,' ',t.last_name) AS advisor
            FROM students s
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN grade_levels gl ON gl.id=c.grade_level_id
            LEFT JOIN personnel t ON t.id=c.homeroom_teacher_id
            WHERE s.id=? AND s.deleted_at IS NULL", [$id]);
    }
    public function reportGuardian(int $id): ?array
    {
        return $this->first("SELECT CONCAT(g.prefix,g.first_name,' ',g.last_name) AS name, g.phone, g.address, sg.relationship
            FROM student_guardians sg JOIN guardians g ON g.id=sg.guardian_id
            WHERE sg.student_id=? ORDER BY sg.is_primary DESC, sg.id LIMIT 1", [$id]);
    }
    public function growth(int $id): array
    {
        return $this->query("SELECT record_date, weight_kg, height_cm, bmi
            FROM health_records WHERE student_id=? ORDER BY record_date LIMIT 4", [$id]);
    }
    /** ภาคเรียนของปีปัจจุบัน (เทอม 1, เทอม 2) */
    public function yearSemesters(): array
    {
        return $this->query("SELECT sem.id, sem.term, ay.year_be FROM semesters sem
            JOIN academic_years ay ON ay.id=sem.academic_year_id WHERE ay.is_current=1 ORDER BY sem.term");
    }
    public function assessmentOne(int $studentId, int $semId): ?array
    { return $this->first("SELECT * FROM kindergarten_assessments WHERE student_id=? AND semester_id=?", [$studentId,$semId]); }

    public function attendanceTerm(int $studentId, int $semId): array
    {
        $r=$this->first("SELECT COUNT(*) total,
                COALESCE(SUM(a.status='present'),0) present,
                COALESCE(SUM(a.status='absent'),0) absent,
                COALESCE(SUM(a.status='leave'),0) `leave`
            FROM attendances a JOIN teaching_assignments ta ON ta.id=a.teaching_assignment_id
            WHERE a.student_id=? AND ta.semester_id=?", [$studentId,$semId]);
        return $r ?: ['total'=>0,'present'=>0,'absent'=>0,'leave'=>0];
    }
}
