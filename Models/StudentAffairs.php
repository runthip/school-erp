<?php
namespace App\Models;
use App\Core\Model;

/**
 * งานกิจการนักเรียน: พฤติกรรม/SDQ · เยี่ยมบ้าน · ทุนการศึกษา
 */
class StudentAffairs extends Model
{
    protected string $table = 'behavior_records';

    /** เกณฑ์แปลผล SDQ ฉบับครูประเมิน (กรมสุขภาพจิต) [ปกติสูงสุด, เสี่ยงสูงสุด] */
    private const SDQ_CUT = [
        'emotional'     => [4, 5],    // 0-4 ปกติ · 5 เสี่ยง · 6-10 มีปัญหา
        'conduct'       => [2, 3],
        'hyperactivity' => [5, 6],
        'peer'          => [3, 4],
        'total'         => [15, 18],  // 0-15 ปกติ · 16-18 เสี่ยง · 19-40 มีปัญหา
    ];
    // สัมพันธภาพ (จุดแข็ง) กลับด้าน: 6-10 ปกติ · 5 เสี่ยง · 0-4 มีปัญหา
    private const PROSOCIAL_CUT = [6, 5];

    public static function sdqGroupOf(string $scale, ?int $score): ?string
    {
        if($score===null) return null;
        if($scale==='prosocial'){
            [$normalMin,$riskVal]=self::PROSOCIAL_CUT;
            if($score>=$normalMin) return 'normal';
            if($score==$riskVal)   return 'risk';
            return 'problem';
        }
        if(!isset(self::SDQ_CUT[$scale])) return null;
        [$normalMax,$riskMax]=self::SDQ_CUT[$scale];
        if($score<=$normalMax) return 'normal';
        if($score<=$riskMax)   return 'risk';
        return 'problem';
    }

    // ================= ภาพรวม =================
    public function dashboard(): array
    {
        $one=fn($sql,$p=[])=>$this->first($sql,$p);
        return [
            'meritMonth'   =>(int)($one("SELECT COUNT(*) c FROM behavior_records WHERE type='merit' AND record_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")['c']??0),
            'demeritMonth' =>(int)($one("SELECT COUNT(*) c FROM behavior_records WHERE type='demerit' AND record_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01')")['c']??0),
            'sdqTotal'     =>(int)($one("SELECT COUNT(*) c FROM sdq_assessments")['c']??0),
            'sdqRisk'      =>(int)($one("SELECT COUNT(*) c FROM sdq_assessments WHERE result_group IN ('risk','problem')")['c']??0),
            'visitsYear'   =>(int)($one("SELECT COUNT(*) c FROM home_visits WHERE YEAR(visit_date)=YEAR(CURDATE())")['c']??0),
            'visitsRisk'   =>(int)($one("SELECT COUNT(*) c FROM home_visits WHERE risk_level IN ('risk','urgent')")['c']??0),
            'scholarCount' =>(int)($one("SELECT COUNT(*) c FROM scholarships WHERE status='granted'")['c']??0),
            'scholarAmount'=>(float)($one("SELECT COALESCE(SUM(amount),0) c FROM scholarships WHERE status='granted'")['c']??0),
            'scholarPending'=>(int)($one("SELECT COUNT(*) c FROM scholarships WHERE status IN ('proposed','approved')")['c']??0),
            'watchList'    =>$this->watchList(),
        ];
    }

    /** นักเรียนที่ควรเฝ้าระวัง: SDQ เสี่ยง/มีปัญหา หรือ เยี่ยมบ้านเสี่ยง หรือ คะแนนพฤติกรรมติดลบมาก */
    public function watchList(): array
    {
        return $this->query("SELECT s.id, s.student_code, CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name,
            c.name AS classroom,
            (SELECT result_group FROM sdq_assessments q WHERE q.student_id=s.id ORDER BY q.assessed_at DESC, q.id DESC LIMIT 1) AS sdq,
            (SELECT risk_level FROM home_visits v WHERE v.student_id=s.id ORDER BY v.visit_date DESC LIMIT 1) AS visit_risk,
            COALESCE((SELECT SUM(points) FROM behavior_records b WHERE b.student_id=s.id),0) AS points
            FROM students s
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            WHERE s.deleted_at IS NULL AND s.status='studying'
              AND ((SELECT result_group FROM sdq_assessments q WHERE q.student_id=s.id ORDER BY q.assessed_at DESC, q.id DESC LIMIT 1) IN ('risk','problem')
                OR (SELECT risk_level FROM home_visits v WHERE v.student_id=s.id ORDER BY v.visit_date DESC LIMIT 1) IN ('risk','urgent')
                OR COALESCE((SELECT SUM(points) FROM behavior_records b WHERE b.student_id=s.id),0) <= -20)
            ORDER BY points ASC LIMIT 20");
    }

    // ================= ตัวเลือกร่วม =================
    public function studentsList(): array
    {
        return $this->query("SELECT s.id, s.student_code, CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name,
            c.name AS classroom
            FROM students s
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            WHERE s.deleted_at IS NULL AND s.status='studying' ORDER BY c.name, s.student_code");
    }
    public function studentFind(int $id): ?array
    {
        return $this->first("SELECT s.*, CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name,
            c.name AS classroom, CONCAT(t.prefix,t.first_name,' ',t.last_name) AS advisor
            FROM students s
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN personnel t ON t.id=c.homeroom_teacher_id
            WHERE s.id=?", [$id]);
    }
    public function guardianOf(int $studentId): ?array
    {
        return $this->first("SELECT g.*, CONCAT(g.prefix,g.first_name,' ',g.last_name) AS name, sg.relationship
            FROM student_guardians sg JOIN guardians g ON g.id=sg.guardian_id
            WHERE sg.student_id=? ORDER BY sg.is_primary DESC LIMIT 1", [$studentId]);
    }
    public function personnelList(): array
    { return $this->query("SELECT id, CONCAT(prefix,first_name,' ',last_name) AS name FROM personnel WHERE deleted_at IS NULL AND status='active' ORDER BY first_name"); }
    public function classrooms(): array
    { return $this->query("SELECT id, name FROM classrooms ORDER BY name"); }
    public function years(): array
    { return $this->query("SELECT id, year_be, is_current FROM academic_years ORDER BY year_be DESC"); }

    // ================= พฤติกรรม =================
    public function behaviors(array $f=[]): array
    {
        $w=['s.deleted_at IS NULL']; $p=[];
        if(!empty($f['type'])){      $w[]='b.type=?';       $p[]=$f['type']; }
        if(!empty($f['student'])){   $w[]='b.student_id=?';  $p[]=$f['student']; }
        if(!empty($f['classroom'])){ $w[]='c.id=?';          $p[]=$f['classroom']; }
        if(!empty($f['from'])){      $w[]='b.record_date>=?'; $p[]=$f['from']; }
        if(!empty($f['to'])){        $w[]='b.record_date<=?'; $p[]=$f['to']; }
        return $this->query("SELECT b.*, s.student_code, CONCAT(s.prefix,s.first_name,' ',s.last_name) AS student_name,
            c.name AS classroom, CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS recorder
            FROM behavior_records b
            JOIN students s ON s.id=b.student_id
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN personnel pe ON pe.id=b.recorded_by
            WHERE ".implode(' AND ',$w)." ORDER BY b.record_date DESC, b.id DESC LIMIT 300", $p);
    }
    public function behaviorFind(int $id): ?array
    {
        return $this->first("SELECT b.*, s.student_code, CONCAT(s.prefix,s.first_name,' ',s.last_name) AS student_name,
            c.name AS classroom, CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS recorder
            FROM behavior_records b
            JOIN students s ON s.id=b.student_id
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN personnel pe ON pe.id=b.recorded_by
            WHERE b.id=?", [$id]);
    }
    public function behaviorCreate(array $d): int
    {
        $this->execute("INSERT INTO behavior_records (student_id, record_date, type, category, points, description,
            recorded_by, parent_notified, action_taken) VALUES (?,?,?,?,?,?,?,?,?)",
            [$d['student_id'],$d['record_date'],$d['type'],$d['category']?:null,$d['points'],
             $d['description']?:null,$d['recorded_by']?:null,$d['parent_notified'],$d['action_taken']?:null]);
        return $this->lastId();
    }
    public function behaviorUpdate(int $id, array $d): void
    {
        $this->execute("UPDATE behavior_records SET record_date=?, type=?, category=?, points=?, description=?,
            parent_notified=?, action_taken=? WHERE id=?",
            [$d['record_date'],$d['type'],$d['category']?:null,$d['points'],$d['description']?:null,
             $d['parent_notified'],$d['action_taken']?:null,$id]);
    }
    public function behaviorDelete(int $id): void { $this->execute("DELETE FROM behavior_records WHERE id=?", [$id]); }

    /** คะแนนความประพฤติสะสมรายนักเรียน (ตั้งต้น 100) */
    public function behaviorScore(int $studentId): array
    {
        $r=$this->first("SELECT COALESCE(SUM(CASE WHEN type='merit' THEN points ELSE 0 END),0) merit,
            COALESCE(SUM(CASE WHEN type='demerit' THEN ABS(points) ELSE 0 END),0) demerit,
            COALESCE(SUM(points),0) net, COUNT(*) cnt
            FROM behavior_records WHERE student_id=?", [$studentId]);
        $r['score']=100+(float)$r['net'];
        return $r;
    }
    public function behaviorOf(int $studentId): array
    {
        return $this->query("SELECT b.*, CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS recorder
            FROM behavior_records b LEFT JOIN personnel pe ON pe.id=b.recorded_by
            WHERE b.student_id=? ORDER BY b.record_date DESC, b.id DESC", [$studentId]);
    }

    // ================= SDQ =================
    public function sdqList(array $f=[]): array
    {
        $w=['s.deleted_at IS NULL']; $p=[];
        if(!empty($f['group'])){     $w[]='q.result_group=?'; $p[]=$f['group']; }
        if(!empty($f['assessor'])){  $w[]='q.assessor=?';      $p[]=$f['assessor']; }
        if(!empty($f['classroom'])){ $w[]='c.id=?';            $p[]=$f['classroom']; }
        if(!empty($f['student'])){   $w[]='q.student_id=?';    $p[]=$f['student']; }
        return $this->query("SELECT q.*, s.student_code, CONCAT(s.prefix,s.first_name,' ',s.last_name) AS student_name,
            c.name AS classroom
            FROM sdq_assessments q
            JOIN students s ON s.id=q.student_id
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            WHERE ".implode(' AND ',$w)." ORDER BY q.assessed_at DESC, q.id DESC LIMIT 300", $p);
    }
    public function sdqFind(int $id): ?array
    {
        return $this->first("SELECT q.*, s.student_code, CONCAT(s.prefix,s.first_name,' ',s.last_name) AS student_name,
            s.birth_date, c.name AS classroom, CONCAT(t.prefix,t.first_name,' ',t.last_name) AS advisor
            FROM sdq_assessments q
            JOIN students s ON s.id=q.student_id
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN personnel t ON t.id=c.homeroom_teacher_id
            WHERE q.id=?", [$id]);
    }
    /** บันทึก SDQ + คำนวณคะแนนรวมและแปลผลอัตโนมัติทุกด้าน */
    public function sdqSave(array $d, ?int $id=null): int
    {
        $sc=[
            'emotional'=>(int)$d['emotional_score'], 'conduct'=>(int)$d['conduct_score'],
            'hyperactivity'=>(int)$d['hyperactivity_score'], 'peer'=>(int)$d['peer_score'],
            'prosocial'=>(int)$d['prosocial_score'],
        ];
        foreach($sc as $k=>$v) $sc[$k]=max(0,min(10,$v));
        $total=$sc['emotional']+$sc['conduct']+$sc['hyperactivity']+$sc['peer'];
        $g=[
            'emotional'=>self::sdqGroupOf('emotional',$sc['emotional']),
            'conduct'=>self::sdqGroupOf('conduct',$sc['conduct']),
            'hyperactivity'=>self::sdqGroupOf('hyperactivity',$sc['hyperactivity']),
            'peer'=>self::sdqGroupOf('peer',$sc['peer']),
            'prosocial'=>self::sdqGroupOf('prosocial',$sc['prosocial']),
            'total'=>self::sdqGroupOf('total',$total),
        ];
        $args=[$d['student_id'],$d['academic_year_id']?:null,$d['assessor'],
            $sc['emotional'],$sc['conduct'],$sc['hyperactivity'],$sc['peer'],$sc['prosocial'],
            $total,$g['total'],$g['emotional'],$g['conduct'],$g['hyperactivity'],$g['peer'],$g['prosocial'],
            $d['assessed_at']?:null,$d['note']?:null];
        if($id){
            $this->execute("UPDATE sdq_assessments SET student_id=?, academic_year_id=?, assessor=?,
                emotional_score=?, conduct_score=?, hyperactivity_score=?, peer_score=?, prosocial_score=?,
                total_difficulty=?, result_group=?, emotional_group=?, conduct_group=?, hyperactivity_group=?,
                peer_group=?, prosocial_group=?, assessed_at=?, note=? WHERE id=?", array_merge($args,[$id]));
            return $id;
        }
        $this->execute("INSERT INTO sdq_assessments (student_id, academic_year_id, assessor,
            emotional_score, conduct_score, hyperactivity_score, peer_score, prosocial_score,
            total_difficulty, result_group, emotional_group, conduct_group, hyperactivity_group,
            peer_group, prosocial_group, assessed_at, note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", $args);
        return $this->lastId();
    }
    public function sdqDelete(int $id): void { $this->execute("DELETE FROM sdq_assessments WHERE id=?", [$id]); }
    public function sdqOf(int $studentId): array
    { return $this->query("SELECT * FROM sdq_assessments WHERE student_id=? ORDER BY assessed_at DESC, id DESC", [$studentId]); }
    /** สรุปผล SDQ แยกกลุ่ม (สำหรับรายงาน) */
    public function sdqSummary(array $f=[]): array
    {
        $rows=$this->sdqList($f);
        $s=['total'=>count($rows),'normal'=>0,'risk'=>0,'problem'=>0];
        foreach($rows as $r){ if(isset($s[$r['result_group']])) $s[$r['result_group']]++; }
        return $s;
    }

    // ================= เยี่ยมบ้าน =================
    public function visits(array $f=[]): array
    {
        $w=['s.deleted_at IS NULL']; $p=[];
        if(!empty($f['risk'])){      $w[]='v.risk_level=?';  $p[]=$f['risk']; }
        if(!empty($f['classroom'])){ $w[]='c.id=?';           $p[]=$f['classroom']; }
        if(!empty($f['student'])){   $w[]='v.student_id=?';   $p[]=$f['student']; }
        if(!empty($f['from'])){      $w[]='v.visit_date>=?';  $p[]=$f['from']; }
        if(!empty($f['to'])){        $w[]='v.visit_date<=?';  $p[]=$f['to']; }
        return $this->query("SELECT v.*, s.student_code, CONCAT(s.prefix,s.first_name,' ',s.last_name) AS student_name,
            c.name AS classroom, CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS visitor
            FROM home_visits v
            JOIN students s ON s.id=v.student_id
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN personnel pe ON pe.id=v.visitor_id
            WHERE ".implode(' AND ',$w)." ORDER BY v.visit_date DESC, v.id DESC LIMIT 300", $p);
    }
    public function visitFind(int $id): ?array
    {
        return $this->first("SELECT v.*, s.student_code, s.birth_date, s.address AS student_address,
            CONCAT(s.prefix,s.first_name,' ',s.last_name) AS student_name,
            c.name AS classroom, CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS visitor
            FROM home_visits v
            JOIN students s ON s.id=v.student_id
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN personnel pe ON pe.id=v.visitor_id
            WHERE v.id=?", [$id]);
    }
    private function visitArgs(array $d): array
    {
        return [$d['student_id'],$d['visit_date'],$d['visitor_id']?:null,$d['summary']?:null,$d['risk_level'],
            $d['guardian_name']?:null,$d['guardian_relation']?:null,$d['guardian_phone']?:null,$d['address']?:null,
            $d['living_with']?:null,$d['family_status']?:null,$d['housing_type']?:null,
            $d['family_income']!==''?$d['family_income']:null,$d['travel_method']?:null,
            $d['distance_km']!==''?$d['distance_km']:null,$d['health_note']?:null,$d['recommendation']?:null,
            $d['needs_help'],$d['next_visit_date']?:null];
    }
    public function visitCreate(array $d): int
    {
        $this->execute("INSERT INTO home_visits (student_id, visit_date, visitor_id, summary, risk_level,
            guardian_name, guardian_relation, guardian_phone, address, living_with, family_status, housing_type,
            family_income, travel_method, distance_km, health_note, recommendation, needs_help, next_visit_date)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", $this->visitArgs($d));
        return $this->lastId();
    }
    public function visitUpdate(int $id, array $d): void
    {
        $this->execute("UPDATE home_visits SET student_id=?, visit_date=?, visitor_id=?, summary=?, risk_level=?,
            guardian_name=?, guardian_relation=?, guardian_phone=?, address=?, living_with=?, family_status=?,
            housing_type=?, family_income=?, travel_method=?, distance_km=?, health_note=?, recommendation=?,
            needs_help=?, next_visit_date=? WHERE id=?", array_merge($this->visitArgs($d),[$id]));
    }
    public function visitDelete(int $id): void { $this->execute("DELETE FROM home_visits WHERE id=?", [$id]); }
    public function visitsOf(int $studentId): array
    { return $this->query("SELECT * FROM home_visits WHERE student_id=? ORDER BY visit_date DESC", [$studentId]); }

    // ================= ทุนการศึกษา =================
    public function scholarships(array $f=[]): array
    {
        $w=['s.deleted_at IS NULL']; $p=[];
        if(!empty($f['status'])){    $w[]='sc.status=?';          $p[]=$f['status']; }
        if(!empty($f['type'])){      $w[]='sc.scholarship_type=?'; $p[]=$f['type']; }
        if(!empty($f['classroom'])){ $w[]='c.id=?';                $p[]=$f['classroom']; }
        if(!empty($f['student'])){   $w[]='sc.student_id=?';       $p[]=$f['student']; }
        if(!empty($f['year'])){      $w[]='sc.academic_year_id=?'; $p[]=$f['year']; }
        return $this->query("SELECT sc.*, s.student_code, CONCAT(s.prefix,s.first_name,' ',s.last_name) AS student_name,
            c.name AS classroom, ay.year_be
            FROM scholarships sc
            JOIN students s ON s.id=sc.student_id
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN academic_years ay ON ay.id=sc.academic_year_id
            WHERE ".implode(' AND ',$w)." ORDER BY sc.granted_date DESC, sc.id DESC LIMIT 300", $p);
    }
    public function scholarshipFind(int $id): ?array
    {
        return $this->first("SELECT sc.*, s.student_code, s.citizen_id, s.address AS student_address,
            CONCAT(s.prefix,s.first_name,' ',s.last_name) AS student_name,
            c.name AS classroom, ay.year_be
            FROM scholarships sc
            JOIN students s ON s.id=sc.student_id
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN academic_years ay ON ay.id=sc.academic_year_id
            WHERE sc.id=?", [$id]);
    }
    private function scArgs(array $d): array
    {
        return [$d['student_id'],$d['name'],$d['scholarship_type'],$d['source']?:null,
            $d['amount']!==''?$d['amount']:null,$d['academic_year_id']?:null,$d['term']?:null,
            $d['granted_date']?:null,$d['status'],$d['note']?:null,$d['receipt_no']?:null];
    }
    public function scholarshipCreate(array $d): int
    {
        $this->execute("INSERT INTO scholarships (student_id, name, scholarship_type, source, amount,
            academic_year_id, term, granted_date, status, note, receipt_no) VALUES (?,?,?,?,?,?,?,?,?,?,?)", $this->scArgs($d));
        return $this->lastId();
    }
    public function scholarshipUpdate(int $id, array $d): void
    {
        $this->execute("UPDATE scholarships SET student_id=?, name=?, scholarship_type=?, source=?, amount=?,
            academic_year_id=?, term=?, granted_date=?, status=?, note=?, receipt_no=? WHERE id=?",
            array_merge($this->scArgs($d),[$id]));
    }
    public function scholarshipDelete(int $id): void { $this->execute("DELETE FROM scholarships WHERE id=?", [$id]); }
    public function scholarshipApprove(int $id, bool $approve, ?int $by=null): void
    {
        $this->execute("UPDATE scholarships SET status=?, approved_by=?, approved_at=NOW() WHERE id=?",
            [$approve?'approved':'rejected',$by,$id]);
    }
    public function scholarshipGrant(int $id, ?string $receiptNo=null): void
    {
        $this->execute("UPDATE scholarships SET status='granted', granted_date=COALESCE(granted_date,CURDATE()),
            receipt_no=COALESCE(?,receipt_no) WHERE id=?", [$receiptNo?:null,$id]);
    }
    public function scholarshipsOf(int $studentId): array
    { return $this->query("SELECT * FROM scholarships WHERE student_id=? ORDER BY granted_date DESC", [$studentId]); }
    public function scholarshipSummary(array $f=[]): array
    {
        $rows=$this->scholarships($f);
        $s=['cnt'=>count($rows),'amount'=>0.0,'granted'=>0,'pending'=>0,'byType'=>[]];
        foreach($rows as $r){
            if($r['status']==='granted'){ $s['granted']++; $s['amount']+=(float)$r['amount']; }
            if(in_array($r['status'],['proposed','approved'],true)) $s['pending']++;
            $t=$r['scholarship_type'];
            $s['byType'][$t]=($s['byType'][$t]??0)+(float)($r['status']==='granted'?$r['amount']:0);
        }
        return $s;
    }
}
