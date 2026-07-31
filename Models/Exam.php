<?php
namespace App\Models;
use App\Core\Model;

class Exam extends Model
{
    protected string $table = 'exams';

    public static function types(): array
    { return ['midterm'=>'สอบกลางภาค','final'=>'สอบปลายภาค','quiz'=>'สอบย่อย/เก็บคะแนน','pretest'=>'ก่อนเรียน (Pre-test)','posttest'=>'หลังเรียน (Post-test)']; }

    /** รายการข้อสอบพร้อมตัวกรอง */
    public function list(array $f=[]): array
    {
        $w=[]; $p=[];
        if(!empty($f['year'])){ $w[]='ay.year_be=?'; $p[]=$f['year']; }
        if(!empty($f['type'])){ $w[]='e.exam_type=?'; $p[]=$f['type']; }
        if(!empty($f['group'])){ $w[]='s.subject_group_id=?'; $p[]=$f['group']; }
        if(!empty($f['teacher'])){ $w[]='e.teacher_id=?'; $p[]=$f['teacher']; }
        if(!empty($f['subject'])){ $w[]='e.subject_id=?'; $p[]=$f['subject']; }
        $where=$w?('WHERE '.implode(' AND ',$w)):'';
        return $this->query("SELECT e.*, s.subject_code, s.name_th AS subject_name,
            sg.name AS group_name, ay.year_be, sem.term,
            CONCAT(pe.first_name,' ',pe.last_name) AS teacher_name
            FROM exams e
            JOIN subjects s ON s.id=e.subject_id
            LEFT JOIN subject_groups sg ON sg.id=s.subject_group_id
            JOIN semesters sem ON sem.id=e.semester_id
            JOIN academic_years ay ON ay.id=sem.academic_year_id
            LEFT JOIN personnel pe ON pe.id=e.teacher_id
            $where ORDER BY ay.year_be DESC, sem.term, e.exam_type, s.subject_code", $p);
    }

    public function find(int $id): ?array
    {
        return $this->first("SELECT e.*, s.subject_code, s.name_th AS subject_name, s.credit,
            sg.name AS group_name, ay.year_be, sem.term,
            CONCAT(pe.first_name,' ',pe.last_name) AS teacher_name
            FROM exams e
            JOIN subjects s ON s.id=e.subject_id
            LEFT JOIN subject_groups sg ON sg.id=s.subject_group_id
            JOIN semesters sem ON sem.id=e.semester_id
            JOIN academic_years ay ON ay.id=sem.academic_year_id
            LEFT JOIN personnel pe ON pe.id=e.teacher_id
            WHERE e.id=?", [$id]);
    }

    public function create(array $d): int
    {
        $this->execute("INSERT INTO exams (semester_id, subject_id, teacher_id, exam_type, title, exam_date,
            duration_min, total_questions, choices, total_score, instructions, answer_key, file_path, file_name, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [
            $d['semester_id'],$d['subject_id'],$d['teacher_id']?:null,$d['exam_type'],$d['title'],$d['exam_date']?:null,
            $d['duration_min']?:60,$d['total_questions'],$d['choices'],$d['total_score'],$d['instructions']?:null,
            $d['answer_key']?:null,$d['file_path']?:null,$d['file_name']?:null,$d['created_by']?:null,
        ]);
        return $this->lastId();
    }
    public function update(int $id, array $d): void
    {
        $this->execute("UPDATE exams SET semester_id=?, subject_id=?, teacher_id=?, exam_type=?, title=?, exam_date=?,
            duration_min=?, total_questions=?, choices=?, total_score=?, instructions=?, answer_key=? WHERE id=?", [
            $d['semester_id'],$d['subject_id'],$d['teacher_id']?:null,$d['exam_type'],$d['title'],$d['exam_date']?:null,
            $d['duration_min']?:60,$d['total_questions'],$d['choices'],$d['total_score'],$d['instructions']?:null,$d['answer_key']?:null,$id,
        ]);
    }
    public function setFile(int $id, string $path, string $name): void
    { $this->execute("UPDATE exams SET file_path=?, file_name=? WHERE id=?", [$path,$name,$id]); }

    public function delete(int $id): void
    { $this->execute("DELETE FROM exams WHERE id=?", [$id]); }

    // ตัวช่วย dropdown
    public function years(): array
    { return $this->query("SELECT DISTINCT ay.year_be FROM academic_years ay ORDER BY ay.year_be DESC"); }
    public function subjectGroups(): array
    { return $this->query("SELECT id, name FROM subject_groups ORDER BY name"); }
    public function subjects(): array
    { return $this->query("SELECT id, subject_code, name_th FROM subjects WHERE is_active=1 ORDER BY subject_code"); }
    public function teachers(): array
    { return $this->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM personnel WHERE deleted_at IS NULL AND status='active' ORDER BY first_name"); }
    public function semesters(): array
    { return $this->query("SELECT sem.id, ay.year_be, sem.term FROM semesters sem JOIN academic_years ay ON ay.id=sem.academic_year_id ORDER BY ay.year_be DESC, sem.term"); }
    public function currentSemesterId(): int
    { return (int)($this->first("SELECT id FROM semesters WHERE is_current=1 LIMIT 1")['id']??1); }

    // ================= งานวัดผล: กระดาษคำตอบรายบุคคล + ตรวจผ่านระบบ =================

    public function classrooms(): array
    { return $this->query("SELECT id, name FROM classrooms ORDER BY name"); }

    /** นักเรียนในห้อง (active) พร้อมเลขที่ */
    public function students(int $classroomId): array
    {
        return $this->query("SELECT s.id, s.student_code,
                CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name, se.roll_number
            FROM student_enrollments se
            JOIN students s ON s.id=se.student_id AND s.deleted_at IS NULL
            WHERE se.classroom_id=? AND se.status='active'
            ORDER BY se.roll_number, s.student_code", [$classroomId]);
    }

    public function setClassroom(int $id, ?int $classroomId): void
    { $this->execute("UPDATE exams SET classroom_id=? WHERE id=?", [$classroomId?:null, $id]); }

    /** เก็บเฉลย (สตริงยาว total_questions: '1'..'M', '0'=ว่าง) */
    public function setKey(int $id, string $key): void
    { $this->execute("UPDATE exams SET answer_key=? WHERE id=?", [$key, $id]); }

    /** ตรวจคำตอบเทียบเฉลย → [ตอบถูก, ตอบแล้ว] */
    public static function scoreAnswers(string $key, string $answers, int $n): array
    {
        $correct=0; $answered=0;
        for($i=0;$i<$n;$i++){
            $a=$answers[$i] ?? '0'; $k=$key[$i] ?? '0';
            if($a!=='0' && $a!==''){ $answered++; if($a===$k && $k!=='0') $correct++; }
        }
        return [$correct,$answered];
    }

    /** บันทึกคำตอบนักเรียน + ให้คะแนนอัตโนมัติ (upsert) */
    public function saveResult(int $examId, int $studentId, string $answers, array $exam, ?int $by): void
    {
        $n=(int)$exam['total_questions'];
        $key=(string)($exam['answer_key'] ?? '');
        [$correct,$answered]=self::scoreAnswers($key, $answers, $n);
        $ppq=$n>0 ? (float)$exam['total_score']/$n : 0;
        $score=round($correct*$ppq, 2);
        $this->execute("INSERT INTO exam_results (exam_id, student_id, answers, correct_count, answered_count, score, graded_by, graded_at)
                VALUES (?,?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE answers=VALUES(answers), correct_count=VALUES(correct_count),
                answered_count=VALUES(answered_count), score=VALUES(score), graded_by=VALUES(graded_by), graded_at=NOW()",
            [$examId,$studentId,$answers,$correct,$answered,$score,$by]);
    }

    public function resultOf(int $examId, int $studentId): ?array
    { return $this->first("SELECT * FROM exam_results WHERE exam_id=? AND student_id=?", [$examId,$studentId]); }

    /** map student_id → result */
    public function resultsMap(int $examId): array
    {
        $rows=$this->query("SELECT * FROM exam_results WHERE exam_id=?", [$examId]);
        $m=[]; foreach($rows as $r) $m[(int)$r['student_id']]=$r; return $m;
    }

    /** สถิติการตรวจ (จำนวนตรวจแล้ว/คะแนนเฉลี่ย/สูงสุด/ต่ำสุด + ร้อยละตอบถูกรายข้อ) */
    public function stats(int $examId, array $exam): array
    {
        $agg=$this->first("SELECT COUNT(*) graded, COALESCE(AVG(score),0) avg_score,
                COALESCE(MAX(score),0) max_score, COALESCE(MIN(score),0) min_score,
                COALESCE(AVG(correct_count),0) avg_correct
            FROM exam_results WHERE exam_id=?", [$examId]) ?: [];
        // ร้อยละตอบถูกรายข้อ (คำนวณจากคำตอบเทียบเฉลย)
        $n=(int)$exam['total_questions']; $key=(string)($exam['answer_key'] ?? '');
        $rows=$this->query("SELECT answers FROM exam_results WHERE exam_id=?", [$examId]);
        $perQ=array_fill(0,$n,0); $graded=count($rows);
        foreach($rows as $r){ $a=(string)$r['answers'];
            for($i=0;$i<$n;$i++){ if(($a[$i]??'0')!=='0' && ($a[$i]??'0')===($key[$i]??'x') && ($key[$i]??'0')!=='0') $perQ[$i]++; }
        }
        return ['agg'=>$agg,'perQ'=>$perQ,'graded'=>$graded];
    }

    /** จำนวนช่องเฉลยที่ตั้งแล้ว */
    public static function keyFilled(string $key, int $n): int
    {
        $c=0; for($i=0;$i<$n;$i++){ if(($key[$i]??'0')!=='0' && ($key[$i]??'')!=='') $c++; } return $c;
    }
}
