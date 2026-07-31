<?php
namespace App\Models;
use App\Core\Model;

/**
 * บันทึกหลังการสอน (ผลหลังการจัดการเรียนรู้)
 * ผูกกับ teaching_assignments → ดึงครู/ตำแหน่ง/รายวิชา/ห้อง/จำนวนนักเรียนอัตโนมัติ
 */
class TeachingLog extends Model
{
    protected string $table = 'teaching_logs';

    /** วิชาที่สอน (สำหรับ dropdown + ดึงข้อมูลอัตโนมัติ) — กรองเฉพาะครูรายนั้นได้ */
    public function assignments(?int $teacherId=null): array
    {
        $w=''; $p=[];
        if($teacherId){ $w='WHERE ta.teacher_id=?'; $p[]=$teacherId; }
        return $this->query("SELECT ta.id, ta.teacher_id,
                CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS teacher, pe.position,
                s.subject_code, s.name_th AS subject_name, sg.name AS group_name,
                c.name AS classroom, ay.year_be, sem.term,
                (SELECT COUNT(*) FROM student_enrollments se WHERE se.classroom_id=ta.classroom_id AND se.status='active') AS students
            FROM teaching_assignments ta
            JOIN personnel pe ON pe.id=ta.teacher_id
            JOIN subjects s ON s.id=ta.subject_id
            LEFT JOIN subject_groups sg ON sg.id=s.subject_group_id
            LEFT JOIN classrooms c ON c.id=ta.classroom_id
            JOIN semesters sem ON sem.id=ta.semester_id
            JOIN academic_years ay ON ay.id=sem.academic_year_id
            $w
            ORDER BY sem.is_current DESC, c.name, s.subject_code", $p);
    }

    /** รายการบันทึก + ตัวกรอง (teacher_id, subject_group, month) */
    public function listLogs(array $f=[]): array
    {
        $w=['1=1']; $p=[];
        if(!empty($f['teacher_id'])){ $w[]='tl.teacher_id=?'; $p[]=(int)$f['teacher_id']; }
        if(!empty($f['subject_group'])){ $w[]='s.subject_group_id=?'; $p[]=(int)$f['subject_group']; }
        if(!empty($f['month'])){ $w[]="DATE_FORMAT(tl.log_date,'%Y-%m')=?"; $p[]=$f['month']; }
        return $this->query("SELECT tl.*, CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS teacher, pe.position,
                s.subject_code, s.name_th AS subject_name, sg.name AS group_name, c.name AS classroom
            FROM teaching_logs tl
            JOIN teaching_assignments ta ON ta.id=tl.teaching_assignment_id
            JOIN subjects s ON s.id=ta.subject_id
            LEFT JOIN subject_groups sg ON sg.id=s.subject_group_id
            LEFT JOIN classrooms c ON c.id=ta.classroom_id
            LEFT JOIN personnel pe ON pe.id=tl.teacher_id
            WHERE ".implode(' AND ',$w)."
            ORDER BY tl.log_date DESC, tl.id DESC", $p);
    }

    public function find(int $id): ?array
    {
        return $this->first("SELECT tl.*, CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS teacher, pe.position,
                s.subject_code, s.name_th AS subject_name, sg.name AS group_name, c.name AS classroom,
                ay.year_be, sem.term,
                CONCAT(hp.prefix,hp.first_name,' ',hp.last_name) AS head_name, hp.position AS head_position,
                (SELECT u.signature_path FROM users u WHERE u.linked_type='personnel' AND u.linked_id=tl.teacher_id AND u.deleted_at IS NULL LIMIT 1) AS teacher_signature,
                (SELECT u.signature_path FROM users u WHERE u.linked_type='personnel' AND u.linked_id=tl.head_id AND u.deleted_at IS NULL LIMIT 1) AS head_signature
            FROM teaching_logs tl
            JOIN teaching_assignments ta ON ta.id=tl.teaching_assignment_id
            JOIN subjects s ON s.id=ta.subject_id
            LEFT JOIN subject_groups sg ON sg.id=s.subject_group_id
            LEFT JOIN classrooms c ON c.id=ta.classroom_id
            JOIN semesters sem ON sem.id=ta.semester_id
            JOIN academic_years ay ON ay.id=sem.academic_year_id
            LEFT JOIN personnel pe ON pe.id=tl.teacher_id
            LEFT JOIN personnel hp ON hp.id=tl.head_id
            WHERE tl.id=?", [$id]);
    }

    private const FIELDS = ['teaching_assignment_id','teacher_id','log_date','period_no','hours','unit_no',
        'lesson_topic','students_total','students_present','students_absent','students_leave',
        'learning_result','passed_count','problems','solutions','head_comment','head_id'];

    public function create(array $d, ?int $by): int
    {
        $cols=self::FIELDS; $set=implode(',',$cols); $ph=implode(',',array_fill(0,count($cols),'?'));
        $vals=array_map(fn($c)=>$d[$c]??null,$cols); $vals[]=$by;
        $this->execute("INSERT INTO teaching_logs ($set, created_by) VALUES ($ph, ?)", $vals);
        return $this->lastId();
    }
    public function update(int $id, array $d): void
    {
        $cols=self::FIELDS; $set=implode(',',array_map(fn($c)=>"$c=?",$cols));
        $vals=array_map(fn($c)=>$d[$c]??null,$cols); $vals[]=$id;
        $this->execute("UPDATE teaching_logs SET $set WHERE id=?", $vals);
    }
    public function delete(int $id): void
    { $this->execute("DELETE FROM teaching_logs WHERE id=?", [$id]); }

    /** สรุปสำหรับแดชบอร์ด (ตามตัวกรอง) */
    public function stats(array $f=[]): array
    {
        $w=['1=1']; $p=[];
        if(!empty($f['teacher_id'])){ $w[]='tl.teacher_id=?'; $p[]=(int)$f['teacher_id']; }
        if(!empty($f['subject_group'])){ $w[]='s.subject_group_id=?'; $p[]=(int)$f['subject_group']; }
        if(!empty($f['month'])){ $w[]="DATE_FORMAT(tl.log_date,'%Y-%m')=?"; $p[]=$f['month']; }
        $where=implode(' AND ',$w);
        $base="FROM teaching_logs tl JOIN teaching_assignments ta ON ta.id=tl.teaching_assignment_id
               JOIN subjects s ON s.id=ta.subject_id LEFT JOIN subject_groups sg ON sg.id=s.subject_group_id WHERE $where";
        $agg=$this->first("SELECT COUNT(*) total, COALESCE(SUM(tl.hours),0) hours,
                COUNT(DISTINCT tl.teacher_id) teachers, COUNT(DISTINCT tl.teaching_assignment_id) subjects $base", $p) ?: [];
        $byGroup=$this->query("SELECT COALESCE(sg.name,'ไม่ระบุกลุ่มสาระ') label, COUNT(*) n $base GROUP BY sg.name ORDER BY n DESC", $p);
        return ['agg'=>$agg,'byGroup'=>$byGroup];
    }

    public function subjectGroups(): array
    { return $this->query("SELECT id, name FROM subject_groups ORDER BY name"); }

    /** หัวหน้ากลุ่มสาระ/วิชาการ (สำหรับช่องความเห็น) */
    public function heads(): array
    {
        return $this->query("SELECT DISTINCT pe.id, CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS name, pe.position
            FROM personnel pe
            JOIN users u ON u.linked_type='personnel' AND u.linked_id=pe.id AND u.deleted_at IS NULL
            JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id
            WHERE pe.deleted_at IS NULL AND pe.status='active'
              AND r.code IN ('head_academic','director','deputy_director')
            ORDER BY pe.first_name");
    }

    /** personnel id ของผู้ใช้ปัจจุบัน (ถ้าเป็นครู) */
    public function myTeacherId(int $userId): ?int
    {
        $r=$this->first("SELECT linked_id FROM users WHERE id=? AND linked_type='personnel'", [$userId]);
        return $r && $r['linked_id'] ? (int)$r['linked_id'] : null;
    }
}
