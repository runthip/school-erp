<?php
namespace App\Models;
use App\Core\Model;

/**
 * พอร์ทัลนักเรียน — ข้อมูลของนักเรียนที่ล็อกอินเท่านั้น
 * ทุกเมธอดรับ $sid ที่มาจาก users.linked_id ของ session เสมอ (ไม่รับจาก URL)
 */
class Portal extends Model
{
    protected string $table = 'students';

    /** โปรไฟล์นักเรียน + ครูที่ปรึกษา */
    public function profile(int $sid): ?array
    {
        return $this->first("SELECT s.id, s.student_code, s.prefix, s.first_name, s.last_name, s.nickname,
            s.gender, s.birth_date, s.blood_group, s.phone, s.address, s.status, s.photo_path, s.admission_date,
            CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name,
            c.id AS classroom_id, c.name AS classroom,
            CONCAT(t.prefix,t.first_name,' ',t.last_name) AS advisor, t.phone AS advisor_phone,
            gl.name AS level
            FROM students s
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN grade_levels gl ON gl.id=c.grade_level_id
            LEFT JOIN personnel t ON t.id=c.homeroom_teacher_id
            WHERE s.id=? AND s.deleted_at IS NULL", [$sid]);
    }

    /** บุตรหลานที่ผูกกับผู้ปกครองรายนี้ (สำหรับพอร์ทัลผู้ปกครอง) */
    public function children(int $guardianId): array
    {
        return $this->query("SELECT s.id, s.student_code, s.nickname, s.photo_path, s.status, s.gender,
            CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name,
            c.name AS classroom, gl.name AS level,
            CONCAT(t.prefix,t.first_name,' ',t.last_name) AS advisor, t.phone AS advisor_phone,
            sg.relationship, sg.is_primary
            FROM student_guardians sg
            JOIN students s ON s.id=sg.student_id AND s.deleted_at IS NULL
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN grade_levels gl ON gl.id=c.grade_level_id
            LEFT JOIN personnel t ON t.id=c.homeroom_teacher_id
            WHERE sg.guardian_id=?
            ORDER BY sg.is_primary DESC, s.first_name", [$guardianId]);
    }

    public function guardians(int $sid): array
    {
        return $this->query("SELECT CONCAT(g.prefix,g.first_name,' ',g.last_name) AS name,
            g.phone, g.occupation, sg.relationship, sg.is_primary
            FROM student_guardians sg JOIN guardians g ON g.id=sg.guardian_id
            WHERE sg.student_id=? ORDER BY sg.is_primary DESC", [$sid]);
    }

    // ---------- ผลการเรียน ----------
    /** ภาคเรียนที่นักเรียนมีผลการเรียน */
    public function semesters(int $sid): array
    {
        return $this->query("SELECT DISTINCT sm.id, sm.term, ay.year_be, sm.is_current
            FROM final_grades fg
            JOIN teaching_assignments ta ON ta.id=fg.teaching_assignment_id
            JOIN semesters sm ON sm.id=ta.semester_id
            JOIN academic_years ay ON ay.id=sm.academic_year_id
            WHERE fg.student_id=?
            ORDER BY ay.year_be DESC, sm.term DESC", [$sid]);
    }

    /** ผลการเรียนรายวิชา (กรองภาคเรียนได้) — คืน field ที่ Academic::computeGpa ใช้ได้ */
    public function grades(int $sid, ?int $semesterId=null): array
    {
        $w='fg.student_id=?'; $p=[$sid];
        if($semesterId){ $w.=' AND ta.semester_id=?'; $p[]=$semesterId; }
        return $this->query("SELECT fg.id, fg.total_score, fg.grade, fg.special_result, fg.is_finalized,
            sub.subject_code, sub.name_th AS subject, sub.credit, sub.subject_type,
            sg.name AS subject_group,
            CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS teacher,
            sm.term, ay.year_be, sm.id AS semester_id
            FROM final_grades fg
            JOIN teaching_assignments ta ON ta.id=fg.teaching_assignment_id
            JOIN subjects sub ON sub.id=ta.subject_id
            LEFT JOIN subject_groups sg ON sg.id=sub.subject_group_id
            LEFT JOIN personnel pe ON pe.id=ta.teacher_id
            JOIN semesters sm ON sm.id=ta.semester_id
            JOIN academic_years ay ON ay.id=sm.academic_year_id
            WHERE $w AND fg.is_finalized=1
            ORDER BY ay.year_be DESC, sm.term DESC, sub.subject_code", $p);
    }

    /** GPA แยกรายภาคเรียน (ใช้ Academic::computeGpa เพื่อไม่ให้สูตรต่างจากที่อื่น) */
    public function gpaBySemester(int $sid): array
    {
        $all=$this->grades($sid);
        $by=[];
        foreach($all as $g){
            $k=$g['year_be'].'/'.$g['term'];
            $by[$k]['label']='ภาคเรียนที่ '.$g['term'].' / '.$g['year_be'];
            $by[$k]['semester_id']=$g['semester_id'];
            $by[$k]['rows'][]=$g;
        }
        $out=[];
        foreach($by as $k=>$v){
            $out[]=['key'=>$k,'label'=>$v['label'],'semester_id'=>$v['semester_id'],
                'gpa'=>Academic::computeGpa($v['rows']),'count'=>count($v['rows'])];
        }
        return $out;
    }

    /** คะแนนเก็บรายวิชา (ช่วยให้นักเรียนเห็นที่มาของคะแนน) */
    public function scoreDetail(int $sid, int $semesterId): array
    {
        return $this->query("SELECT sub.name_th AS subject, gc.name AS component, gc.max_score,
            sc.score
            FROM scores sc
            JOIN grade_components gc ON gc.id=sc.grade_component_id
            JOIN teaching_assignments ta ON ta.id=gc.teaching_assignment_id
            JOIN subjects sub ON sub.id=ta.subject_id
            WHERE sc.student_id=? AND ta.semester_id=?
            ORDER BY sub.subject_code, gc.id", [$sid,$semesterId]);
    }

    // ---------- ห้องเรียน / ตารางเรียน ----------
    public function timetable(int $sid): array
    {
        return $this->query("SELECT cs.day_of_week, cs.period_no, cs.start_time, cs.end_time,
            sub.subject_code, sub.name_th AS subject,
            CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS teacher,
            r.name AS room
            FROM student_enrollments se
            JOIN teaching_assignments ta ON ta.classroom_id=se.classroom_id
            JOIN class_schedules cs ON cs.teaching_assignment_id=ta.id
            JOIN subjects sub ON sub.id=ta.subject_id
            LEFT JOIN personnel pe ON pe.id=ta.teacher_id
            LEFT JOIN rooms r ON r.id=cs.room_id
            JOIN semesters sm ON sm.id=ta.semester_id
            WHERE se.student_id=? AND se.status='active' AND sm.is_current=1
            ORDER BY cs.day_of_week, cs.period_no", [$sid]);
    }

    /** รายวิชาที่เรียนภาคเรียนปัจจุบัน + ครูผู้สอน */
    public function subjects(int $sid): array
    {
        return $this->query("SELECT sub.subject_code, sub.name_th AS subject, sub.credit, sub.subject_type,
            sg.name AS subject_group,
            CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS teacher, pe.phone AS teacher_phone
            FROM student_enrollments se
            JOIN teaching_assignments ta ON ta.classroom_id=se.classroom_id
            JOIN subjects sub ON sub.id=ta.subject_id
            LEFT JOIN subject_groups sg ON sg.id=sub.subject_group_id
            LEFT JOIN personnel pe ON pe.id=ta.teacher_id
            JOIN semesters sm ON sm.id=ta.semester_id
            WHERE se.student_id=? AND se.status='active' AND sm.is_current=1
            GROUP BY sub.id, sub.subject_code, sub.name_th, sub.credit, sub.subject_type, sg.name, teacher, pe.phone
            ORDER BY sub.subject_code", [$sid]);
    }

    public function classmateCount(int $sid): int
    {
        $r=$this->first("SELECT COUNT(*) c FROM student_enrollments se2
            JOIN student_enrollments me ON me.classroom_id=se2.classroom_id AND me.student_id=? AND me.status='active'
            JOIN students s ON s.id=se2.student_id AND s.deleted_at IS NULL AND s.status='studying'
            WHERE se2.status='active'", [$sid]);
        return (int)($r['c'] ?? 0);
    }

    // ---------- การมาเรียน ----------
    public function attendanceSummary(int $sid, ?string $from=null, ?string $to=null): array
    {
        $w='student_id=?'; $p=[$sid];
        if($from){ $w.=' AND attendance_date>=?'; $p[]=$from; }
        if($to){   $w.=' AND attendance_date<=?'; $p[]=$to; }
        $r=$this->first("SELECT COUNT(*) total,
            SUM(status='present') present, SUM(status='absent') absent,
            SUM(status='late') late, SUM(status='leave') `leave`, SUM(status='activity') activity
            FROM attendances WHERE $w", $p) ?? [];
        $total=(int)($r['total'] ?? 0);
        $ok=(int)($r['present'] ?? 0)+(int)($r['activity'] ?? 0)+(int)($r['leave'] ?? 0);
        $r['percent']=$total>0?round($ok/$total*100,1):0;
        return $r;
    }
    public function attendanceRecent(int $sid, int $limit=30): array
    {
        return $this->query("SELECT a.attendance_date, a.period_no, a.status, a.note,
            sub.name_th AS subject
            FROM attendances a
            LEFT JOIN teaching_assignments ta ON ta.id=a.teaching_assignment_id
            LEFT JOIN subjects sub ON sub.id=ta.subject_id
            WHERE a.student_id=? ORDER BY a.attendance_date DESC, a.period_no DESC LIMIT $limit", [$sid]);
    }

    // ---------- พฤติกรรม / ทุน ----------
    public function behaviors(int $sid): array
    {
        return $this->query("SELECT record_date, type, category, points, description
            FROM behavior_records WHERE student_id=? ORDER BY record_date DESC, id DESC LIMIT 50", [$sid]);
    }
    public function behaviorScore(int $sid): array
    {
        $r=$this->first("SELECT COALESCE(SUM(CASE WHEN type='merit' THEN points ELSE 0 END),0) merit,
            COALESCE(SUM(CASE WHEN type='demerit' THEN ABS(points) ELSE 0 END),0) demerit,
            COALESCE(SUM(points),0) net FROM behavior_records WHERE student_id=?", [$sid]);
        $r['score']=100+(float)$r['net'];
        return $r;
    }
    public function scholarships(int $sid): array
    {
        return $this->query("SELECT name, scholarship_type, source, amount, granted_date, status, term
            FROM scholarships WHERE student_id=? AND status IN ('approved','granted')
            ORDER BY granted_date DESC", [$sid]);
    }

    /** ภาพรวมหน้าแรกพอร์ทัล */
    public function dashboard(int $sid): array
    {
        $grades=$this->grades($sid);
        $cur=$this->first("SELECT sm.id FROM semesters sm WHERE sm.is_current=1 LIMIT 1");
        $today=(int)date('N'); // 1=จันทร์
        $tt=array_values(array_filter($this->timetable($sid), fn($r)=>(int)$r['day_of_week']===$today));
        return [
            'gpaAll'    =>Academic::computeGpa($grades),
            'gpaBySem'  =>$this->gpaBySemester($sid),
            'attendance'=>$this->attendanceSummary($sid),
            'behavior'  =>$this->behaviorScore($sid),
            'scholarships'=>$this->scholarships($sid),
            'todayClasses'=>$tt,
            'subjectCount'=>count($this->subjects($sid)),
            'classmates'=>$this->classmateCount($sid),
            'curSemester'=>$cur['id'] ?? null,
        ];
    }
}
