<?php
namespace App\Models;
use App\Core\Model;

class Academic extends Model
{
    protected string $table = 'subjects';

    // ---------- แปลงคะแนนรวมเป็นเกรด (เกณฑ์มาตรฐาน) ----------
    public static function scoreToGrade(float $total): string
    {
        return match(true){
            $total >= 80 => '4',
            $total >= 75 => '3.5',
            $total >= 70 => '3',
            $total >= 65 => '2.5',
            $total >= 60 => '2',
            $total >= 55 => '1.5',
            $total >= 50 => '1',
            default      => '0',
        };
    }

    // ---------- หลักสูตร / นำเข้า ----------
    public function subjectGroupsMap(): array
    {
        $rows=$this->query("SELECT id, code FROM subject_groups");
        $m=[]; foreach($rows as $r) $m[strtoupper($r['code'])]=(int)$r['id']; return $m;
    }
    public function subjectExists(string $code): bool
    { return $this->first("SELECT id FROM subjects WHERE school_id=1 AND subject_code=?", [$code])!==null; }
    public function subjectInsert(array $d): void
    {
        $this->execute("INSERT INTO subjects (school_id, subject_code, name_th, subject_group_id, credit, hours_per_week, subject_type, is_active)
            VALUES (1,?,?,?,?,?,?,1)",
            [$d['subject_code'],$d['name_th'],$d['subject_group_id'],$d['credit'],$d['hours_per_week'],$d['subject_type']]);
    }

    // ---------- มอบหมายวิชาสอน ----------
    public function assignments(?int $semesterId=null): array
    {
        $w=''; $p=[];
        if($semesterId){ $w='WHERE ta.semester_id=?'; $p[]=$semesterId; }
        return $this->query("SELECT ta.*, s.subject_code, s.name_th AS subject_name, s.credit,
            c.name AS classroom, CONCAT(p.first_name,' ',p.last_name) AS teacher_name,
            sem.term, ay.year_be,
            (SELECT COUNT(*) FROM student_enrollments se WHERE se.classroom_id=ta.classroom_id) AS student_count,
            (SELECT COUNT(*) FROM grade_components gc WHERE gc.teaching_assignment_id=ta.id) AS comp_count
            FROM teaching_assignments ta
            JOIN subjects s ON s.id=ta.subject_id
            JOIN classrooms c ON c.id=ta.classroom_id
            JOIN personnel p ON p.id=ta.teacher_id
            JOIN semesters sem ON sem.id=ta.semester_id
            JOIN academic_years ay ON ay.id=sem.academic_year_id
            $w ORDER BY c.name, s.subject_code", $p);
    }
    public function taDetail(int $id): ?array
    {
        return $this->first("SELECT ta.*, s.subject_code, s.name_th AS subject_name, s.credit,
            c.name AS classroom, c.id AS classroom_id, CONCAT(p.first_name,' ',p.last_name) AS teacher_name,
            sg.name AS subject_group
            FROM teaching_assignments ta JOIN subjects s ON s.id=ta.subject_id
            JOIN classrooms c ON c.id=ta.classroom_id JOIN personnel p ON p.id=ta.teacher_id
            LEFT JOIN subject_groups sg ON sg.id=s.subject_group_id
            WHERE ta.id=?", [$id]);
    }
    public function assignmentCreate(array $d): int
    {
        $this->execute("INSERT INTO teaching_assignments (semester_id, subject_id, classroom_id, teacher_id, is_primary)
            VALUES (?,?,?,?,1)", [$d['semester_id'],$d['subject_id'],$d['classroom_id'],$d['teacher_id']]);
        return $this->lastId();
    }
    public function setWeeklyPeriods(int $id, int $wp): void
    { $this->execute("UPDATE teaching_assignments SET weekly_periods=? WHERE id=?", [$wp,$id]); }

    public function assignmentRemove(int $id): void
    { $this->execute("DELETE FROM teaching_assignments WHERE id=?", [$id]); }

    public function assignmentExists(array $d): bool
    {
        return $this->first("SELECT id FROM teaching_assignments WHERE semester_id=? AND subject_id=? AND classroom_id=? AND teacher_id=?",
            [$d['semester_id'],$d['subject_id'],$d['classroom_id'],$d['teacher_id']])!==null;
    }

    // ตัวเลือกสำหรับฟอร์ม
    public function subjectsList(): array { return $this->query("SELECT id, subject_code, name_th FROM subjects WHERE is_active=1 ORDER BY subject_code"); }
    public function classroomsList(): array { return $this->query("SELECT id, name FROM classrooms ORDER BY name"); }
    public function teachersList(): array { return $this->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM personnel WHERE deleted_at IS NULL AND status='active' ORDER BY first_name"); }
    public function semestersList(): array
    { return $this->query("SELECT sem.id, sem.term, ay.year_be FROM semesters sem JOIN academic_years ay ON ay.id=sem.academic_year_id ORDER BY ay.year_be DESC, sem.term"); }
    public function currentSemester(): ?array
    { return $this->first("SELECT id FROM semesters WHERE is_current=1 LIMIT 1"); }

    // ---------- นักเรียนในห้องของ TA ----------
    public function taStudents(int $classroomId): array
    {
        return $this->query("SELECT s.id, s.student_code, s.prefix, s.first_name, s.last_name, se.roll_number
            FROM student_enrollments se JOIN students s ON s.id=se.student_id
            WHERE se.classroom_id=? AND se.status='active' ORDER BY se.roll_number, s.student_code", [$classroomId]);
    }

    // ---------- องค์ประกอบคะแนน ----------
    public function components(int $taId): array
    { return $this->query("SELECT * FROM grade_components WHERE teaching_assignment_id=? ORDER BY sort_order, id", [$taId]); }
    public function componentCreate(int $taId, array $d): void
    {
        $this->execute("INSERT INTO grade_components (teaching_assignment_id, name, standard_code, indicator, hours, component_type, max_score, weight, sort_order)
            VALUES (?,?,?,?,?,?,?,?,?)",
            [$taId,$d['name'],$d['standard_code']??null,$d['indicator']??null,$d['hours']??null,
             $d['component_type'],$d['max_score'],$d['weight']?:null,$d['sort_order']]);
    }
    public function componentDelete(int $id, int $taId): void
    { $this->execute("DELETE FROM grade_components WHERE id=? AND teaching_assignment_id=?", [$id,$taId]); }

    // ---------- คะแนน ----------
    public function scoresMap(int $taId): array
    {
        $rows=$this->query("SELECT sc.grade_component_id, sc.student_id, sc.score
            FROM scores sc JOIN grade_components gc ON gc.id=sc.grade_component_id
            WHERE gc.teaching_assignment_id=?", [$taId]);
        $m=[]; foreach($rows as $r){ $m[(int)$r['student_id']][(int)$r['grade_component_id']]=$r['score']; }
        return $m;
    }
    public function upsertScore(int $compId, int $studentId, ?float $score, ?int $by): void
    {
        $this->execute("INSERT INTO scores (grade_component_id, student_id, score, recorded_by) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE score=VALUES(score), recorded_by=VALUES(recorded_by)",
            [$compId,$studentId,$score,$by]);
    }
    public function upsertFinalGrade(int $studentId, int $taId, float $total, string $grade): void
    {
        $this->execute("INSERT INTO final_grades (student_id, teaching_assignment_id, total_score, grade, special_result, is_finalized, finalized_at)
            VALUES (?,?,?,?, 'none', 1, NOW())
            ON DUPLICATE KEY UPDATE total_score=VALUES(total_score), grade=VALUES(grade), special_result='none', is_finalized=1, finalized_at=NOW()",
            [$studentId,$taId,$total,$grade]);
    }
    /** บันทึกผลพิเศษ ร (r) หรือ มส (ms) — ไม่คิด GPA */
    public function upsertSpecialGrade(int $studentId, int $taId, string $special): void
    {
        $this->execute("INSERT INTO final_grades (student_id, teaching_assignment_id, total_score, grade, special_result, is_finalized, finalized_at)
            VALUES (?,?, NULL, NULL, ?, 0, NULL)
            ON DUPLICATE KEY UPDATE total_score=NULL, grade=NULL, special_result=VALUES(special_result), is_finalized=0, finalized_at=NULL",
            [$studentId,$taId,$special]);
    }
    /** ผลพิเศษปัจจุบันของนักเรียนในวิชานี้ (r/ms/none) */
    public function specialMap(int $taId): array
    {
        $rows=$this->query("SELECT student_id, special_result FROM final_grades WHERE teaching_assignment_id=?", [$taId]);
        $m=[]; foreach($rows as $r) $m[(int)$r['student_id']]=$r['special_result']; return $m;
    }

    // ---------- ผลการเรียนรายห้อง ----------
    public function classroomFinalGrades(int $classroomId, int $semesterId): array
    {
        return $this->query("SELECT s.id AS student_id, s.student_code, s.prefix, s.first_name, s.last_name, se.roll_number,
            sub.name_th AS subject_name, sub.subject_code, sub.credit, fg.total_score, fg.grade, fg.special_result
            FROM student_enrollments se JOIN students s ON s.id=se.student_id
            JOIN teaching_assignments ta ON ta.classroom_id=se.classroom_id AND ta.semester_id=?
            JOIN subjects sub ON sub.id=ta.subject_id
            LEFT JOIN final_grades fg ON fg.student_id=s.id AND fg.teaching_assignment_id=ta.id
            WHERE se.classroom_id=? AND se.status='active'
            ORDER BY se.roll_number, s.student_code, sub.subject_code", [$semesterId,$classroomId]);
    }

    // ---------- Transcript + GPA ----------
    public function studentGrades(int $studentId): array
    {
        return $this->query("SELECT fg.*, sub.subject_code, sub.name_th AS subject_name, sub.credit,
            sem.term, ay.year_be
            FROM final_grades fg
            JOIN teaching_assignments ta ON ta.id=fg.teaching_assignment_id
            JOIN subjects sub ON sub.id=ta.subject_id
            JOIN semesters sem ON sem.id=ta.semester_id
            JOIN academic_years ay ON ay.id=sem.academic_year_id
            WHERE fg.student_id=? ORDER BY ay.year_be, sem.term, sub.subject_code", [$studentId]);
    }
    public static function computeGpa(array $grades): array
    {
        $totalCredit=0; $totalPoint=0; $countZero=0; $countIncomplete=0;
        foreach($grades as $g){
            if(in_array($g['special_result'],['r','ms'],true) || $g['grade']===null){ $countIncomplete++; continue; }
            $credit=(float)$g['credit']; $point=(float)$g['grade'];
            $totalCredit+=$credit; $totalPoint+=$point*$credit;
            if($point==0) $countZero++;
        }
        return [
            'gpax'=>$totalCredit>0?round($totalPoint/$totalCredit,2):0,
            'credit'=>$totalCredit,'zero'=>$countZero,'incomplete'=>$countIncomplete,
        ];
    }
    public function studentInfo(int $id): ?array
    {
        return $this->first("SELECT s.*, c.name AS classroom FROM students s
            LEFT JOIN student_enrollments se ON se.student_id=s.id
            LEFT JOIN classrooms c ON c.id=se.classroom_id WHERE s.id=?", [$id]);
    }
    public function studentsForTranscript(): array
    {
        return $this->query("SELECT s.id, s.student_code, s.prefix, s.first_name, s.last_name, c.name AS classroom
            FROM students s LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            WHERE s.deleted_at IS NULL AND s.status='studying' ORDER BY c.name, s.student_code");
    }

    // ---------- 0 ร มส ----------
    /** เงื่อนไข WHERE + params ร่วมสำหรับ list/stats ของหน้า 0 ร มส */
    private function zeroRMsWhere(array $f): array
    {
        $w=["(fg.special_result IN ('0','r','ms','mp') OR fg.grade='0')"]; $p=[];
        if(!empty($f['grade_level'])){ $w[]='c.grade_level_id=?'; $p[]=(int)$f['grade_level']; }
        if(!empty($f['classroom'])){ $w[]='c.id=?'; $p[]=(int)$f['classroom']; }
        if(!empty($f['subject_group'])){ $w[]='sub.subject_group_id=?'; $p[]=(int)$f['subject_group']; }
        if(!empty($f['result'])){
            if($f['result']==='0') $w[]="(fg.special_result='0' OR fg.grade='0')";
            else { $w[]='fg.special_result=?'; $p[]=$f['result']; }
        }
        if(($f['status']??'')==='fixed'){ $w[]='gx.id IS NOT NULL'; }
        elseif(($f['status']??'')==='pending'){ $w[]='gx.id IS NULL'; }
        return [implode(' AND ',$w), $p];
    }

    /** รายการนักเรียนติด 0 ร มส + เกรดใหม่ (ถ้าแก้แล้ว) + ตัวกรอง */
    public function zeroRMsList(array $f=[]): array
    {
        [$where,$p]=$this->zeroRMsWhere($f);
        return $this->query("SELECT fg.id AS final_grade_id, s.student_code, s.prefix, s.first_name, s.last_name,
            c.name AS classroom, c.grade_level_id, gl.name AS level,
            sub.name_th AS subject_name, sg.name AS subject_group,
            fg.special_result, fg.grade,
            gx.new_grade, gx.fixed_date, gx.note AS fix_note
            FROM final_grades fg
            JOIN students s ON s.id=fg.student_id
            JOIN teaching_assignments ta ON ta.id=fg.teaching_assignment_id
            JOIN subjects sub ON sub.id=ta.subject_id
            LEFT JOIN subject_groups sg ON sg.id=sub.subject_group_id
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN grade_levels gl ON gl.id=c.grade_level_id
            LEFT JOIN grade_fixes gx ON gx.final_grade_id=fg.id
            WHERE $where
            ORDER BY (gx.id IS NOT NULL), c.name, s.student_code", $p);
    }

    /** สถิติสัดส่วน สำหรับแดชบอร์ดหน้า 0 ร มส (ตามตัวกรองเดียวกัน) */
    public function zeroRMsStats(array $f=[]): array
    {
        [$where,$p]=$this->zeroRMsWhere($f);
        $base="FROM final_grades fg
            JOIN students s ON s.id=fg.student_id
            JOIN teaching_assignments ta ON ta.id=fg.teaching_assignment_id
            JOIN subjects sub ON sub.id=ta.subject_id
            LEFT JOIN subject_groups sg ON sg.id=sub.subject_group_id
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN grade_levels gl ON gl.id=c.grade_level_id
            LEFT JOIN grade_fixes gx ON gx.final_grade_id=fg.id
            WHERE $where";
        $agg=$this->first("SELECT COUNT(*) total,
              SUM(CASE WHEN fg.special_result='0' OR fg.grade='0' THEN 1 ELSE 0 END) c0,
              SUM(fg.special_result='r') cr,
              SUM(fg.special_result='ms') cms,
              SUM(gx.id IS NOT NULL) fixed,
              SUM(gx.id IS NULL) pending,
              COUNT(DISTINCT s.id) students
              $base", $p) ?: [];
        $byGroup=$this->query("SELECT COALESCE(sg.name,'ไม่ระบุกลุ่มสาระ') label, COUNT(*) n,
              SUM(gx.id IS NOT NULL) fixed $base GROUP BY sg.name ORDER BY n DESC", $p);
        $byClass=$this->query("SELECT COALESCE(c.name,'ไม่ระบุห้อง') label, COUNT(*) n,
              SUM(gx.id IS NOT NULL) fixed $base GROUP BY c.name ORDER BY n DESC", $p);
        $newGrades=$this->query("SELECT gx.new_grade label, COUNT(*) n $base AND gx.id IS NOT NULL
              GROUP BY gx.new_grade ORDER BY gx.new_grade DESC", $p);
        return ['agg'=>$agg,'byGroup'=>$byGroup,'byClass'=>$byClass,'newGrades'=>$newGrades];
    }

    /** ตัวเลือกสำหรับ dropdown ฟิลเตอร์ (เฉพาะที่มีรายการติด 0 ร มส) */
    public function zeroRMsFilterOptions(): array
    {
        $levels=$this->query("SELECT DISTINCT gl.id, gl.name FROM grade_levels gl ORDER BY gl.id");
        $classes=$this->query("SELECT id, name, grade_level_id FROM classrooms ORDER BY name");
        $groups=$this->query("SELECT id, name FROM subject_groups ORDER BY name");
        return ['levels'=>$levels,'classes'=>$classes,'groups'=>$groups];
    }

    /** บันทึกการแก้ 0 ร มส (เกรดเดิม→เกรดใหม่) — upsert ตาม final_grade_id */
    public function recordGradeFix(int $finalGradeId, string $origResult, string $newGrade, ?string $date, ?string $note, ?int $by): void
    {
        $this->execute("INSERT INTO grade_fixes (final_grade_id, original_result, new_grade, fixed_date, note, fixed_by)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE new_grade=VALUES(new_grade), fixed_date=VALUES(fixed_date),
                note=VALUES(note), fixed_by=VALUES(fixed_by), original_result=VALUES(original_result)",
            [$finalGradeId, $origResult, $newGrade, $date?:null, $note?:null, $by]);
    }

    /** ยกเลิกการแก้ (ลบบันทึกเกรดใหม่) */
    public function undoGradeFix(int $finalGradeId): void
    { $this->execute("DELETE FROM grade_fixes WHERE final_grade_id=?", [$finalGradeId]); }

    // ---------- แบบ วก.01: ขออนุมัติผลการเรียน 0 / ร / มผ (ต่อครู-รายวิชา-ห้อง) ----------
    private const WK01_COND = "(fg.special_result IN ('0','r','ms','mp') OR fg.grade='0')";

    /** รายวิชา (teaching_assignment) ที่มีนักเรียนติด 0/ร/มส/มผ — สำหรับปุ่มพิมพ์ วก.01 */
    public function wk01Assignments(array $f=[]): array
    {
        $w=[self::WK01_COND]; $p=[];
        if(!empty($f['grade_level'])){ $w[]='c.grade_level_id=?'; $p[]=(int)$f['grade_level']; }
        if(!empty($f['classroom'])){ $w[]='c.id=?'; $p[]=(int)$f['classroom']; }
        if(!empty($f['subject_group'])){ $w[]='sub.subject_group_id=?'; $p[]=(int)$f['subject_group']; }
        $where=implode(' AND ',$w);
        return $this->query("SELECT ta.id AS ta_id,
                CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS teacher, pe.position,
                sub.subject_code, sub.name_th AS subject_name, sg.name AS group_name,
                c.name AS classroom, ay.year_be, sem.term, COUNT(*) AS n
            FROM final_grades fg
            JOIN teaching_assignments ta ON ta.id=fg.teaching_assignment_id
            JOIN subjects sub ON sub.id=ta.subject_id
            LEFT JOIN subject_groups sg ON sg.id=sub.subject_group_id
            LEFT JOIN personnel pe ON pe.id=ta.teacher_id
            LEFT JOIN classrooms c ON c.id=ta.classroom_id
            JOIN semesters sem ON sem.id=ta.semester_id
            JOIN academic_years ay ON ay.id=sem.academic_year_id
            WHERE $where
            GROUP BY ta.id ORDER BY c.name, sub.subject_code", $p);
    }

    /** ข้อมูลหัวเรื่อง วก.01 ของ teaching_assignment (ครู/วิชา/ห้อง/ภาคเรียน + ลายเซ็นครู) */
    public function wk01Header(int $taId): ?array
    {
        return $this->first("SELECT ta.id AS ta_id, ta.teacher_id,
                CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS teacher, pe.position,
                sub.subject_code, sub.name_th AS subject_name, sub.credit, sg.name AS group_name,
                c.name AS classroom, gl.name AS level, ay.year_be, sem.term,
                (SELECT u.signature_path FROM users u WHERE u.linked_type='personnel' AND u.linked_id=ta.teacher_id AND u.deleted_at IS NULL LIMIT 1) AS teacher_signature
            FROM teaching_assignments ta
            JOIN subjects sub ON sub.id=ta.subject_id
            LEFT JOIN subject_groups sg ON sg.id=sub.subject_group_id
            LEFT JOIN personnel pe ON pe.id=ta.teacher_id
            LEFT JOIN classrooms c ON c.id=ta.classroom_id
            LEFT JOIN grade_levels gl ON gl.id=c.grade_level_id
            JOIN semesters sem ON sem.id=ta.semester_id
            JOIN academic_years ay ON ay.id=sem.academic_year_id
            WHERE ta.id=?", [$taId]);
    }

    /** รายชื่อนักเรียนติด 0/ร/มส/มผ ในรายวิชานั้น (สำหรับตาราง วก.01) */
    public function wk01Students(int $taId): array
    {
        return $this->query("SELECT s.student_code, CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name,
                se.roll_number, fg.special_result, fg.grade
            FROM final_grades fg
            JOIN students s ON s.id=fg.student_id AND s.deleted_at IS NULL
            JOIN teaching_assignments ta ON ta.id=fg.teaching_assignment_id
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.classroom_id=ta.classroom_id AND se.status='active'
            WHERE fg.teaching_assignment_id=? AND ".self::WK01_COND."
            ORDER BY se.roll_number, s.student_code", [$taId]);
    }

    // ---------- เช็คชื่อ ----------
    public function attendanceMap(int $taId, string $date, int $period): array
    {
        $rows=$this->query("SELECT student_id, status, note FROM attendances WHERE teaching_assignment_id=? AND attendance_date=? AND period_no=?", [$taId,$date,$period]);
        $m=[]; foreach($rows as $r) $m[(int)$r['student_id']]=['status'=>$r['status'],'note'=>$r['note']]; return $m;
    }
    public function saveAttendance(int $taId, int $classroomId, int $studentId, string $date, int $period, string $status, ?string $note, ?int $by): void
    {
        // ลบของเดิม (วิชา+นักเรียน+วัน+คาบ) กันซ้ำ แล้วเพิ่มใหม่
        $this->execute("DELETE FROM attendances WHERE teaching_assignment_id=? AND student_id=? AND attendance_date=? AND period_no=?", [$taId,$studentId,$date,$period]);
        $this->execute("INSERT INTO attendances (student_id, teaching_assignment_id, classroom_id, attendance_date, period_no, status, note, recorded_by)
            VALUES (?,?,?,?,?,?,?,?)", [$studentId,$taId,$classroomId,$date,$period,$status,$note?:null,$by]);
    }
    public function attendanceSummary(int $taId): array
    {
        $rows=$this->query("SELECT status, COUNT(*) c FROM attendances WHERE teaching_assignment_id=? GROUP BY status", [$taId]);
        $m=[]; foreach($rows as $r) $m[$r['status']]=(int)$r['c']; return $m;
    }

    // ---------- เช็คชื่อหน้าเสาธง (เข้าโรงเรียน) ----------
    /** ห้องที่ครูคนนี้เป็นครูที่ปรึกษา (สำหรับเช็คหน้าเสาธง) */
    public function homeroomClasses(int $personnelId): array
    {
        return $this->query("SELECT c.id, c.name, c.section,
            g.name AS grade, r.name AS room_name,
            (SELECT COUNT(*) FROM student_enrollments se
              WHERE se.classroom_id=c.id AND se.status='active') AS student_count
            FROM classrooms c
            LEFT JOIN grade_levels g ON g.id=c.grade_level_id
            LEFT JOIN rooms r ON r.id=c.room_id
            WHERE c.homeroom_teacher_id=?
            ORDER BY c.name", [$personnelId]);
    }

    /** ทุกห้อง (สำหรับผู้บริหาร/ธุรการที่เห็นทั้งโรงเรียน) */
    public function allClassesForFlag(): array
    {
        return $this->query("SELECT c.id, c.name, c.section,
            g.name AS grade, r.name AS room_name,
            CONCAT(pe.first_name,' ',pe.last_name) AS homeroom_teacher,
            (SELECT COUNT(*) FROM student_enrollments se
              WHERE se.classroom_id=c.id AND se.status='active') AS student_count
            FROM classrooms c
            LEFT JOIN grade_levels g ON g.id=c.grade_level_id
            LEFT JOIN rooms r ON r.id=c.room_id
            LEFT JOIN personnel pe ON pe.id=c.homeroom_teacher_id
            ORDER BY c.name");
    }

    public function classroom(int $id): ?array
    {
        return $this->first("SELECT c.*, g.name AS grade, r.name AS room_name,
            CONCAT(pe.first_name,' ',pe.last_name) AS homeroom_teacher
            FROM classrooms c
            LEFT JOIN grade_levels g ON g.id=c.grade_level_id
            LEFT JOIN rooms r ON r.id=c.room_id
            LEFT JOIN personnel pe ON pe.id=c.homeroom_teacher_id
            WHERE c.id=?", [$id]);
    }

    /** รายชื่อนักเรียนในห้อง (เรียงตามเลขที่) */
    public function classRoster(int $classroomId): array
    {
        return $this->query("SELECT s.id, s.student_code,
            CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name, se.roll_number
            FROM student_enrollments se
            JOIN students s ON s.id=se.student_id
            WHERE se.classroom_id=? AND se.status='active' AND s.deleted_at IS NULL
            ORDER BY se.roll_number, s.first_name", [$classroomId]);
    }

    /** สถานะเช็คหน้าเสาธงของห้อง ในวันหนึ่ง */
    public function flagMap(int $classroomId, string $date): array
    {
        $rows=$this->query("SELECT student_id, status, note, arrived_at
            FROM attendances
            WHERE attendance_type='flag' AND classroom_id=? AND attendance_date=?", [$classroomId,$date]);
        $m=[]; foreach($rows as $r)
            $m[(int)$r['student_id']]=['status'=>$r['status'],'note'=>$r['note'],'arrived_at'=>$r['arrived_at']];
        return $m;
    }

    /** บันทึกเช็คหน้าเสาธง (1 คน 1 วัน — ลบเดิมก่อน) */
    public function saveFlag(int $classroomId, int $studentId, string $date,
                             string $status, ?string $note, ?string $arrivedAt, ?int $by): void
    {
        $this->execute("DELETE FROM attendances
            WHERE attendance_type='flag' AND student_id=? AND attendance_date=?", [$studentId,$date]);
        $this->execute("INSERT INTO attendances
            (student_id, attendance_type, classroom_id, attendance_date, status, note, arrived_at, recorded_by)
            VALUES (?, 'flag', ?, ?, ?, ?, ?, ?)",
            [$studentId,$classroomId,$date,$status,$note?:null,$arrivedAt?:null,$by]);
    }

    /** ตั้งค่าเวลาเข้าเรียน (สาย/ขาด) ของทั้งโรงเรียน */
    public function flagSettings(): array
    {
        $rows=$this->query("SELECT setting_key, setting_value FROM system_settings WHERE group_key='attendance'");
        $m=['late_time'=>'08:00','absent_time'=>'08:30','enabled'=>'1'];
        foreach($rows as $r) $m[$r['setting_key']]=$r['setting_value'];
        return $m;
    }

    /** คำนวณสถานะจากเวลามาจริง เทียบเวลาที่ตั้งไว้ */
    public function statusFromTime(string $arrivedAt, array $set): string
    {
        $a=strtotime($arrivedAt); $late=strtotime($set['late_time']); $absent=strtotime($set['absent_time']);
        if($a >= $absent) return 'absent';   // เกินรอบสอง = ขาดทั้งวัน
        if($a >= $late)   return 'late';      // เกินรอบแรก = สาย
        return 'present';
    }

    // ---------- รายงานการมาเรียน ----------
    /** สรุปรายคนในห้อง ช่วงวันที่ (รวมทั้งหน้าเสาธงและทุกวิชา) */
    public function attendanceReport(int $classroomId, string $from, string $to, string $type='flag'): array
    {
        $typeWhere = $type==='all' ? '' : " AND a.attendance_type='".($type==='subject'?'subject':'flag')."'";
        return $this->query("SELECT s.id, s.student_code, se.roll_number,
            CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name,
            SUM(a.status='present')  AS present,
            SUM(a.status='late')     AS late,
            SUM(a.status='absent')   AS absent,
            SUM(a.status='leave')    AS leave_cnt,
            SUM(a.status='activity') AS activity,
            COUNT(a.id) AS total
            FROM student_enrollments se
            JOIN students s ON s.id=se.student_id
            LEFT JOIN attendances a ON a.student_id=s.id
                 AND a.attendance_date BETWEEN ? AND ? $typeWhere
            WHERE se.classroom_id=? AND se.status='active' AND s.deleted_at IS NULL
            GROUP BY s.id, s.student_code, se.roll_number, name
            ORDER BY se.roll_number, s.first_name", [$from,$to,$classroomId]);
    }

    /** สรุปภาพรวมของห้อง แยกรายวัน (สำหรับกราฟ) */
    public function attendanceDaily(int $classroomId, string $from, string $to, string $type='flag'): array
    {
        $typeWhere = $type==='all' ? '' : " AND attendance_type='".($type==='subject'?'subject':'flag')."'";
        return $this->query("SELECT attendance_date d,
            SUM(status='present') present, SUM(status='late') late,
            SUM(status='absent') absent, SUM(status IN ('leave','activity')) excused
            FROM attendances
            WHERE classroom_id=? AND attendance_date BETWEEN ? AND ? $typeWhere
            GROUP BY attendance_date ORDER BY attendance_date", [$classroomId,$from,$to]);
    }

    /** นักเรียนที่ขาด/สายบ่อย (ช่วงวันที่) */
    public function attendanceAlert(int $classroomId, string $from, string $to): array
    {
        return $this->query("SELECT s.id, se.roll_number,
            CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name,
            SUM(a.status='absent') absent, SUM(a.status='late') late
            FROM student_enrollments se
            JOIN students s ON s.id=se.student_id
            LEFT JOIN attendances a ON a.student_id=s.id
                 AND a.attendance_type='flag' AND a.attendance_date BETWEEN ? AND ?
            WHERE se.classroom_id=? AND se.status='active' AND s.deleted_at IS NULL
            GROUP BY s.id, se.roll_number, name
            HAVING absent > 0 OR late >= 3
            ORDER BY absent DESC, late DESC", [$from,$to,$classroomId]);
    }

    // ---------- สถิติการมาเรียนภาพรวม (แดชบอร์ด + รายงาน export) ----------
    /** สรุปทั้งโรงเรียนในวันเดียว (สำหรับการ์ดแดชบอร์ด) */
    public function flagStatsByDate(string $date): array
    {
        $r=$this->first("SELECT
            SUM(status='present') present, SUM(status='late') late,
            SUM(status='absent') absent, SUM(status='leave') leave_cnt,
            SUM(status='activity') activity, COUNT(*) total
            FROM attendances WHERE attendance_type='flag' AND attendance_date=?", [$date]);
        return ['present'=>(int)($r['present']??0),'late'=>(int)($r['late']??0),
                'absent'=>(int)($r['absent']??0),'leave'=>(int)($r['leave_cnt']??0),
                'activity'=>(int)($r['activity']??0),'total'=>(int)($r['total']??0)];
    }

    /** จำนวนนักเรียนทั้งหมด (active) — ใช้เทียบว่าเช็คครบไหม */
    public function activeStudentCount(): int
    {
        $r=$this->first("SELECT COUNT(*) c FROM student_enrollments WHERE status='active'");
        return (int)($r['c']??0);
    }

    /** กี่ห้องที่เช็คหน้าเสาธงแล้ววันนี้ / ทั้งหมด */
    public function flagClassProgress(string $date): array
    {
        $done=$this->first("SELECT COUNT(DISTINCT classroom_id) c FROM attendances
            WHERE attendance_type='flag' AND attendance_date=?", [$date]);
        $all=$this->first("SELECT COUNT(*) c FROM classrooms WHERE homeroom_teacher_id IS NOT NULL");
        return ['done'=>(int)($done['c']??0),'total'=>(int)($all['c']??0)];
    }

    /** สถิติรวมทั้งโรงเรียนในช่วงวันที่ (สำหรับ export) */
    public function flagStatsRange(string $from, string $to): array
    {
        $r=$this->first("SELECT
            SUM(status='present') present, SUM(status='late') late,
            SUM(status='absent') absent, SUM(status='leave') leave_cnt,
            SUM(status='activity') activity, COUNT(*) total,
            COUNT(DISTINCT attendance_date) days
            FROM attendances WHERE attendance_type='flag' AND attendance_date BETWEEN ? AND ?", [$from,$to]);
        return ['present'=>(int)($r['present']??0),'late'=>(int)($r['late']??0),
                'absent'=>(int)($r['absent']??0),'leave'=>(int)($r['leave_cnt']??0),
                'activity'=>(int)($r['activity']??0),'total'=>(int)($r['total']??0),
                'days'=>(int)($r['days']??0)];
    }

    /** แยกรายห้องในช่วงวันที่ (สำหรับตารางรายงาน export) */
    public function flagByClassroom(string $from, string $to): array
    {
        return $this->query("SELECT c.id, c.name AS classroom, g.name AS grade,
            CONCAT(pe.first_name,' ',pe.last_name) AS homeroom_teacher,
            SUM(a.status='present') present, SUM(a.status='late') late,
            SUM(a.status='absent') absent, SUM(a.status='leave') leave_cnt,
            SUM(a.status='activity') activity, COUNT(a.id) total
            FROM classrooms c
            LEFT JOIN grade_levels g ON g.id=c.grade_level_id
            LEFT JOIN personnel pe ON pe.id=c.homeroom_teacher_id
            LEFT JOIN attendances a ON a.classroom_id=c.id
                 AND a.attendance_type='flag' AND a.attendance_date BETWEEN ? AND ?
            GROUP BY c.id, c.name, g.name, homeroom_teacher
            ORDER BY c.name", [$from,$to]);
    }

    /** แนวโน้มรายวันทั้งโรงเรียน (กราฟแดชบอร์ด) */
    public function flagTrend(string $from, string $to): array
    {
        return $this->query("SELECT attendance_date d,
            SUM(status='present') present, SUM(status='late') late, SUM(status='absent') absent
            FROM attendances WHERE attendance_type='flag' AND attendance_date BETWEEN ? AND ?
            GROUP BY attendance_date ORDER BY attendance_date", [$from,$to]);
    }

    // ---------- ตารางสอน ----------
    public function schedule(int $classroomId, int $semesterId): array
    {
        return $this->query("SELECT cs.*, s.name_th AS subject_name, s.subject_code,
            CONCAT(p.first_name,' ',p.last_name) AS teacher_name, r.name AS room_name
            FROM class_schedules cs
            JOIN teaching_assignments ta ON ta.id=cs.teaching_assignment_id
            JOIN subjects s ON s.id=ta.subject_id
            JOIN personnel p ON p.id=ta.teacher_id
            LEFT JOIN rooms r ON r.id=cs.room_id
            WHERE ta.classroom_id=? AND ta.semester_id=?
            ORDER BY cs.day_of_week, cs.period_no", [$classroomId,$semesterId]);
    }

    // ---------- ข้อมูลสำหรับพิมพ์ ปพ.1 (ระเบียนแสดงผลการเรียน / ระเบียนสะสม) ----------
    public function pp1(int $studentId): array
    {
        $st=$this->studentInfo($studentId);
        if(!$st) return [];
        // ผลการเรียนทุกภาคเรียนของนักเรียน
        $rows=$this->query("SELECT ay.year_be, sem.term, sem.id AS sem_id,
              s.subject_code, s.name_th AS subject_name, s.credit, s.subject_type,
              sg.name AS group_name, sg.code AS group_code,
              fg.grade, fg.special_result, gl.stage
            FROM final_grades fg
            JOIN teaching_assignments ta ON ta.id=fg.teaching_assignment_id
            JOIN subjects s   ON s.id=ta.subject_id
            LEFT JOIN subject_groups sg ON sg.id=s.subject_group_id
            JOIN semesters sem ON sem.id=ta.semester_id
            JOIN academic_years ay ON ay.id=sem.academic_year_id
            LEFT JOIN classrooms c ON c.id=ta.classroom_id
            LEFT JOIN grade_levels gl ON gl.id=c.grade_level_id
            WHERE fg.student_id=?
            ORDER BY ay.year_be, sem.term, s.subject_code", [$studentId]);

        $specialLabel=['0'=>'0','r'=>'ร','ms'=>'มส'];
        $terms=[]; // key = year-term
        $sumPoint=0; $sumCredit=0; $creditTotal=0; $stage='secondary';
        foreach($rows as $r){
            if($r['stage']) $stage=in_array($r['stage'],['primary','kindergarten'],true)?'primary':'secondary';
            $key=$r['year_be'].'-'.$r['term'];
            if(!isset($terms[$key])) $terms[$key]=['year_be'=>$r['year_be'],'term'=>$r['term'],'subjects'=>[],'credit'=>0,'point'=>0,'creditGpa'=>0];
            $isSpecial=in_array($r['special_result'],['0','r','ms'],true) && $r['special_result']!=='none';
            $grade=$isSpecial?($specialLabel[$r['special_result']]??$r['grade']):$r['grade'];
            $credit=(float)($r['credit']??0);
            $gp=self::gradePoint((string)$grade);
            $terms[$key]['subjects'][]=['code'=>$r['subject_code'],'name'=>$r['subject_name'],
                'credit'=>$credit,'grade'=>$grade,'group'=>$r['group_name']];
            $terms[$key]['credit']+=$credit;
            if($gp!==null){ $terms[$key]['point']+=$gp*$credit; $terms[$key]['creditGpa']+=$credit; $sumPoint+=$gp*$credit; $sumCredit+=$credit; }
            $creditTotal+=$credit;
        }
        foreach($terms as $k=>$t) $terms[$k]['gpa']=$t['creditGpa']>0?round($t['point']/$t['creditGpa'],2):0;
        $gpax=$sumCredit>0?round($sumPoint/$sumCredit,2):0;
        return ['st'=>$st,'terms'=>array_values($terms),'gpax'=>$gpax,'creditTotal'=>$creditTotal,'stage'=>$stage];
    }

    // ---------- ข้อมูลสำหรับพิมพ์ ปพ.6 (รายงานรายบุคคล) ----------
    public static function gradePoint(string $grade): ?float
    {
        $map=['4'=>4,'3.5'=>3.5,'3'=>3,'2.5'=>2.5,'2'=>2,'1.5'=>1.5,'1'=>1,'0'=>0];
        return array_key_exists($grade,$map)?(float)$map[$grade]:null;
    }
    /** รายงาน ปพ.6 ของนักเรียนในห้อง (ทุกวิชา) — เลือกบางคนได้ */
    public function pp6(int $classroomId, ?array $studentIds=null): array
    {
        $cr=$this->classroomFind($classroomId);
        $students=$this->taStudents($classroomId);
        if($studentIds){ $ids=array_map('intval',$studentIds); $students=array_values(array_filter($students,fn($s)=>in_array((int)$s['id'],$ids,true))); }

        // TAs ของห้อง + ข้อมูลวิชา
        $tas=$this->query("SELECT ta.id, ta.subject_id, s.subject_code, s.name_th AS subject_name, s.credit, s.subject_type
            FROM teaching_assignments ta JOIN subjects s ON s.id=ta.subject_id
            WHERE ta.classroom_id=? ORDER BY s.subject_code", [$classroomId]);
        if(!$tas) return ['cr'=>$cr,'students'=>[],'reports'=>[]];
        $taIds=array_map(fn($t)=>(int)$t['id'],$tas);
        $in=implode(',',$taIds);

        // components ต่อ TA
        $compRows=$this->query("SELECT * FROM grade_components WHERE teaching_assignment_id IN ($in) ORDER BY sort_order, id");
        $compByTa=[]; foreach($compRows as $c) $compByTa[(int)$c['teaching_assignment_id']][]=$c;
        // scores ทั้งหมด
        $scoreRows=$this->query("SELECT gc.teaching_assignment_id ta, sc.grade_component_id cid, sc.student_id sid, sc.score
            FROM scores sc JOIN grade_components gc ON gc.id=sc.grade_component_id WHERE gc.teaching_assignment_id IN ($in)");
        $scoreMap=[]; foreach($scoreRows as $r) $scoreMap[(int)$r['ta']][(int)$r['sid']][(int)$r['cid']]=$r['score'];
        // final grades
        $fgRows=$this->query("SELECT teaching_assignment_id ta, student_id sid, grade, special_result FROM final_grades WHERE teaching_assignment_id IN ($in)");
        $fgMap=[]; foreach($fgRows as $r) $fgMap[(int)$r['ta']][(int)$r['sid']]=['grade'=>$r['grade'],'special'=>$r['special_result']];
        // ratio ต่อ TA
        $ratioMap=[]; foreach($taIds as $tid) $ratioMap[$tid]=$this->ratioFor($tid);
        // evaluations
        $evAll=$this->query("SELECT teaching_assignment_id ta, student_id sid, category, item_key, level FROM student_evaluations WHERE teaching_assignment_id IN ($in)");
        $evMap=[]; foreach($evAll as $r) $evMap[(int)$r['sid']][$r['category']][]=$r['level']!==null?(int)$r['level']:null;

        $specialLabel=['0'=>'0','r'=>'ร','ms'=>'มส'];
        $reports=[];
        foreach($students as $st){
            $sid=(int)$st['id'];
            $rows=[]; $sumPoint=0; $sumCredit=0; $creditCore=0; $creditAdd=0; $creditTotal=0;
            foreach($tas as $t){
                $tid=(int)$t['id']; $comps=$compByTa[$tid]??[];
                $during=array_filter($comps,fn($c)=>self::bucketOf($c)!=='final');
                $final =array_filter($comps,fn($c)=>self::bucketOf($c)==='final');
                $sScore=[]; foreach($comps as $c) $sScore[(int)$c['id']]=$scoreMap[$tid][$sid][(int)$c['id']]??null;
                $keep=0; foreach($during as $c) $keep+=(float)($scoreMap[$tid][$sid][(int)$c['id']]??0);
                $exam=0; foreach($final as $c) $exam+=(float)($scoreMap[$tid][$sid][(int)$c['id']]??0);
                $fg=$fgMap[$tid][$sid]??null;
                if($fg && in_array($fg['special'],['0','r','ms'],true) && $fg['special']!=='none'){
                    $grade=$specialLabel[$fg['special']]??$fg['grade'];
                } else {
                    $grade=$fg['grade']??self::scoreToGrade(self::computePercent($comps,$sScore,$ratioMap[$tid]));
                }
                $credit=(float)($t['credit']??0);
                $gp=self::gradePoint((string)$grade);
                if($gp!==null){ $sumPoint+=$gp*$credit; $sumCredit+=$credit; }
                $creditTotal+=$credit;
                if($t['subject_type']==='additional') $creditAdd+=$credit; elseif($t['subject_type']==='core') $creditCore+=$credit;
                $rows[]=['code'=>$t['subject_code'],'name'=>$t['subject_name'],'credit'=>$credit,
                    'keep'=>round($keep,1),'exam'=>round($exam,1),'total'=>round($keep+$exam,1),'grade'=>$grade];
            }
            $gpa=$sumCredit>0?round($sumPoint/$sumCredit,2):0;
            // สรุปผลประเมิน (min ข้ามทุกวิชา; ไม่มี = ยังไม่ได้ประเมิน)
            $evalOverall=function(?array $levels): string {
                if(!$levels) return 'ยังไม่ได้ประเมิน';
                $vals=array_filter($levels,fn($v)=>$v!==null);
                if(!$vals) return 'ยังไม่ได้ประเมิน';
                $min=min($vals);
                return ['0'=>'ไม่ผ่าน',1=>'ผ่าน',2=>'ดี',3=>'ดีเยี่ยม'][$min]??'ผ่าน';
            };
            $reports[]=[
                'st'=>$st,'rows'=>$rows,'gpa'=>$gpa,
                'creditCore'=>$creditCore,'creditAdd'=>$creditAdd,'creditTotal'=>$creditTotal,
                'char'=>$evalOverall($evMap[$sid]['character']??null),
                'read'=>$evalOverall($evMap[$sid]['reading']??null),
                'comp'=>$evalOverall($evMap[$sid]['competency']??null),
            ];
        }
        return ['cr'=>$cr,'students'=>$students,'reports'=>$reports];
    }

    // ---------- ข้อมูลสำหรับพิมพ์ ปพ.5 ----------
    /** เซสชันเช็คชื่อ (distinct วัน+คาบ) ของวิชา เรียงเวลา */
    public function attendanceSessions(int $taId): array
    {
        return $this->query("SELECT attendance_date, period_no FROM attendances WHERE teaching_assignment_id=?
            GROUP BY attendance_date, period_no ORDER BY attendance_date, period_no", [$taId]);
    }
    /** สถานะเช็คชื่อทั้งหมดของวิชา → [student_id]['date|period'] = status */
    public function attendanceFull(int $taId): array
    {
        $rows=$this->query("SELECT student_id, attendance_date, period_no, status FROM attendances WHERE teaching_assignment_id=?", [$taId]);
        $m=[]; foreach($rows as $r){ $m[(int)$r['student_id']][$r['attendance_date'].'|'.(int)$r['period_no']]=$r['status']; }
        return $m;
    }

    /** รวมข้อมูลทั้งหมดของ ปพ.5 สำหรับวิชาหนึ่ง */
    public function pp5(int $taId): array
    {
        $ta=$this->taDetail($taId);
        if(!$ta) return [];
        $students=$this->taStudents((int)$ta['classroom_id']);
        $components=$this->components($taId);
        $scores=$this->scoresMap($taId);
        $ratio=$this->ratioFor($taId);
        $evalMap=$this->evalMap($taId);
        $sessions=$this->attendanceSessions($taId);
        $attFull=$this->attendanceFull($taId);
        $evalItems=self::evalItems();

        // แยกองค์ประกอบ ระหว่างภาค/ปลายภาค
        $during=array_values(array_filter($components,fn($c)=>self::bucketOf($c)!=='final'));
        $final =array_values(array_filter($components,fn($c)=>self::bucketOf($c)==='final'));

        // ระดับผลรวมของด้านประเมิน (character/reading = 4 ระดับ; competency = 4 ระดับต่าง)
        $overall=function(array $itemsDef, ?array $studMap): int {
            // -1 = ยังไม่ประเมิน/ไม่ผ่าน  0..3 = min level
            if(!$studMap) return -1;
            $min=3; $any=false;
            foreach($itemsDef as $k=>$label){
                $v=$studMap[$k]??null;
                if($v===null) return -1;
                $any=true; if($v<$min) $min=$v;
            }
            return $any?$min:-1;
        };

        $gradeTally=['4'=>0,'3.5'=>0,'3'=>0,'2.5'=>0,'2'=>0,'1.5'=>0,'1'=>0,'0'=>0,'ร'=>0,'มส'=>0];
        $charTally=[3=>0,2=>0,1=>0,-1=>0]; // ดีเยี่ยม/ดี/ผ่าน/ไม่ผ่าน
        $readTally=[3=>0,2=>0,1=>0,-1=>0];
        $compTally=[3=>0,2=>0,1=>0,-1=>0]; // ดีเยี่ยม/ดี/พอใช้/ปรับปรุง

        $rows=[];
        foreach($students as $st){
            $sid=(int)$st['id'];
            // คะแนน
            $sScore=[]; foreach($components as $c) $sScore[(int)$c['id']]=$scores[$sid][$c['id']]??null;
            $dSum=0; foreach($during as $c) $dSum+=(float)($scores[$sid][$c['id']]??0);
            $fSum=0; foreach($final as $c) $fSum+=(float)($scores[$sid][$c['id']]??0);
            $percent=self::computePercent($components,$sScore,$ratio);
            $grade=self::scoreToGrade($percent);
            // นับ grade distribution (ตามคะแนนดิบรวม? ที่นี่ใช้เกรดจาก percent)
            if(isset($gradeTally[$grade])) $gradeTally[$grade]++;

            // ประเมิน
            $charLv=$overall($evalItems['character'],$evalMap[$sid]['character']??null);
            $readLv=$overall($evalItems['reading'],$evalMap[$sid]['reading']??null);
            $compLv=$overall($evalItems['competency'],$evalMap[$sid]['competency']??null);
            $charTally[$charLv]++; $readTally[$readLv]++; $compTally[$compLv]++;

            // เช็คชื่อ
            $att=['present'=>0,'late'=>0,'absent'=>0,'leave'=>0,'activity'=>0];
            $cells=[];
            foreach($sessions as $sess){
                $key=$sess['attendance_date'].'|'.(int)$sess['period_no'];
                $stt=$attFull[$sid][$key]??null;
                $cells[]=$stt;
                if($stt && isset($att[$stt])) $att[$stt]++;
            }
            $totalSess=count($sessions);
            $comeSum=$att['present']+$att['late']+$att['activity']; // มารวม (มา+สาย+กิจกรรม)
            $pct=$totalSess>0?round($comeSum/$totalSess*100,1):0;

            $rows[]=[
                'st'=>$st,'scores'=>$sScore,'dSum'=>$dSum,'fSum'=>$fSum,'percent'=>$percent,'grade'=>$grade,
                'eval'=>$evalMap[$sid]??[],'charLv'=>$charLv,'readLv'=>$readLv,'compLv'=>$compLv,
                'att'=>$att,'cells'=>$cells,'totalSess'=>$totalSess,'comeSum'=>$comeSum,'attPct'=>$pct,
            ];
        }
        return compact('ta','students','components','during','final','ratio','sessions',
            'evalItems','rows','gradeTally','charTally','readTally','compTally');
    }

    // ---------- ประเมินอ่าน-คิด-เขียน / คุณลักษณะ / สมรรถนะ ----------
    /** นิยามหัวข้อแต่ละด้าน (item_key => ชื่อไทย) */
    public static function evalItems(): array
    {
        return [
            'reading'=>[
                'reading'=>'การอ่าน','thinking'=>'การคิด','writing'=>'การเขียน',
            ],
            'character'=>[
                'love_nation'=>'รักชาติ ศาสน์ กษัตริย์','honest'=>'ซื่อสัตย์สุจริต','discipline'=>'มีวินัย',
                'learning'=>'ใฝ่เรียนรู้','sufficiency'=>'อยู่อย่างพอเพียง','committed'=>'มุ่งมั่นในการทำงาน',
                'thai_pride'=>'รักความเป็นไทย','public_mind'=>'มีจิตสาธารณะ',
            ],
            'competency'=>[
                'communication'=>'การสื่อสาร','thinking'=>'การคิด','problem_solving'=>'การแก้ปัญหา',
                'technology'=>'การใช้เทคโนโลยี','life_skill'=>'ทักษะชีวิต',
            ],
        ];
    }
    public static function evalLevels(): array
    { return [0=>'ไม่ผ่าน',1=>'ผ่าน',2=>'ดี',3=>'ดีเยี่ยม']; }

    /** ผลประเมินของทั้งห้อง: [student_id][category][item_key] = level */
    public function evalMap(int $taId): array
    {
        $rows=$this->query("SELECT student_id, category, item_key, level FROM student_evaluations WHERE teaching_assignment_id=?", [$taId]);
        $m=[];
        foreach($rows as $r) $m[(int)$r['student_id']][$r['category']][$r['item_key']]=$r['level']!==null?(int)$r['level']:null;
        return $m;
    }
    /** บันทึกผลประเมินหนึ่งช่อง (upsert) */
    public function evalSet(int $taId, int $studentId, string $category, string $itemKey, ?int $level): void
    {
        if(!isset(self::evalItems()[$category][$itemKey])) return;
        if($level!==null && ($level<0||$level>3)) $level=null;
        $this->execute("INSERT INTO student_evaluations (teaching_assignment_id, student_id, category, item_key, level)
            VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE level=VALUES(level), updated_at=NOW()",
            [$taId,$studentId,$category,$itemKey,$level]);
    }
    /** คัดลอกผลประเมินของนักเรียนต้นทาง ไปยังนักเรียนหลายคน (เฉพาะ category ที่ระบุ) */
    public function evalCopy(int $taId, int $fromStudent, array $toStudents, string $category): int
    {
        $src=$this->query("SELECT item_key, level FROM student_evaluations WHERE teaching_assignment_id=? AND student_id=? AND category=?",
            [$taId,$fromStudent,$category]);
        if(!$src) return 0;
        $n=0;
        foreach($toStudents as $sid){
            $sid=(int)$sid; if($sid===$fromStudent||$sid<=0) continue;
            foreach($src as $row){ $this->evalSet($taId,$sid,$category,$row['item_key'],$row['level']!==null?(int)$row['level']:null); }
            $n++;
        }
        return $n;
    }

    // ---------- จัดการชั้นเรียน / ครู / นักเรียน (ฐานข้อมูลวิชาการ) ----------
    public function gradeLevels(): array
    { return $this->query("SELECT id, code, name, stage FROM grade_levels WHERE ".enabled_stages_sql('stage')." ORDER BY level_order"); }

    public function classroomsFull(string $grade=''): array
    {
        $w=''; $p=[];
        if($grade!==''){ $w='WHERE c.grade_level_id=?'; $p[]=$grade; }
        return $this->query("SELECT c.*, gl.name AS grade_name, gl.stage,
            CONCAT(pe.first_name,' ',pe.last_name) AS homeroom_teacher, r.name AS room_name,
            (SELECT COUNT(*) FROM student_enrollments se WHERE se.classroom_id=c.id AND se.status='active') AS student_count,
            (SELECT COUNT(*) FROM teaching_assignments ta WHERE ta.classroom_id=c.id) AS subject_count
            FROM classrooms c
            JOIN grade_levels gl ON gl.id=c.grade_level_id
            LEFT JOIN personnel pe ON pe.id=c.homeroom_teacher_id
            LEFT JOIN rooms r ON r.id=c.room_id
            $w ORDER BY gl.level_order, c.section", $p);
    }
    public function classroomFind(int $id): ?array
    {
        return $this->first("SELECT c.*, gl.name AS grade_name,
            CONCAT(pe.first_name,' ',pe.last_name) AS homeroom_teacher, r.name AS room_name
            FROM classrooms c JOIN grade_levels gl ON gl.id=c.grade_level_id
            LEFT JOIN personnel pe ON pe.id=c.homeroom_teacher_id
            LEFT JOIN rooms r ON r.id=c.room_id WHERE c.id=?", [$id]);
    }
    public function unenrolledStudents(): array
    {
        return $this->query("SELECT s.id, s.student_code, s.prefix, s.first_name, s.last_name
            FROM students s
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.academic_year_id=1 AND se.status='active'
            WHERE s.deleted_at IS NULL AND s.status='studying' AND se.id IS NULL
            ORDER BY s.student_code");
    }
    public function enroll(int $classroomId, int $studentId, ?int $roll): array
    {
        $dup=$this->first("SELECT id FROM student_enrollments WHERE student_id=? AND academic_year_id=1", [$studentId]);
        if($dup) return ['ok'=>false,'msg'=>'นักเรียนคนนี้มีห้องเรียนแล้ว (ย้ายห้องให้ลบออกจากห้องเดิมก่อน)'];
        if($roll===null){
            $r=$this->first("SELECT COALESCE(MAX(roll_number),0)+1 n FROM student_enrollments WHERE classroom_id=? AND status='active'", [$classroomId]);
            $roll=(int)($r['n']??1);
        }
        $this->execute("INSERT INTO student_enrollments (student_id, classroom_id, academic_year_id, roll_number, status)
            VALUES (?,?,1,?, 'active')", [$studentId,$classroomId,$roll]);
        return ['ok'=>true];
    }
    public function unenroll(int $classroomId, int $studentId): void
    { $this->execute("DELETE FROM student_enrollments WHERE classroom_id=? AND student_id=?", [$classroomId,$studentId]); }

    // ---------- จัดการนักเรียน (งานวิชาการ): แมพนักเรียน ↔ ห้องเรียน ----------
    /** รายชื่อนักเรียนพร้อมห้องเรียนปัจจุบัน + ตัวกรอง (ค้นหา/ชั้น/ห้อง/สถานะ/ยังไม่จัดห้อง) */
    public function studentsWithClass(array $f=[]): array
    {
        $w=['s.deleted_at IS NULL']; $p=[];
        if(!empty($f['q'])){ $w[]='(s.student_code LIKE ? OR CONCAT(s.prefix,s.first_name," ",s.last_name) LIKE ?)'; $p[]='%'.$f['q'].'%'; $p[]='%'.$f['q'].'%'; }
        if(!empty($f['status'])){ $w[]='s.status=?'; $p[]=$f['status']; }
        if(($f['classroom'] ?? 0)==-1){ $w[]='se.id IS NULL'; }          // ยังไม่จัดห้อง
        elseif(!empty($f['classroom'])){ $w[]='se.classroom_id=?'; $p[]=(int)$f['classroom']; }
        elseif(!empty($f['grade'])){ $w[]='c.grade_level_id=?'; $p[]=(int)$f['grade']; }
        $where='WHERE '.implode(' AND ',$w);
        return $this->query("SELECT s.id, s.student_code, s.prefix, s.first_name, s.last_name, s.status,
            CONCAT(s.prefix,s.first_name,' ',s.last_name) AS name,
            se.classroom_id, se.roll_number, c.name AS classroom, gl.name AS grade
            FROM students s
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active' AND se.academic_year_id=1
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN grade_levels gl ON gl.id=c.grade_level_id
            $where
            ORDER BY se.classroom_id IS NULL DESC, c.grade_level_id, c.section, se.roll_number, s.student_code", $p);
    }

    /** จัด/ย้าย/นำออก ห้องเรียนของนักเรียน (แบบย้ายได้ทันที ไม่ต้องลบห้องเดิมก่อน) */
    public function assignClassroom(int $studentId, int $classroomId, ?int $roll): array
    {
        $cur=$this->first("SELECT id, classroom_id, roll_number FROM student_enrollments
            WHERE student_id=? AND academic_year_id=1 AND status='active'", [$studentId]);
        if($classroomId<=0){                                    // นำออกจากห้อง
            if($cur) $this->execute("DELETE FROM student_enrollments WHERE id=?", [$cur['id']]);
            return ['ok'=>true,'msg'=>'นำนักเรียนออกจากห้องแล้ว'];
        }
        if($roll===null || $roll<=0){
            if($cur && (int)$cur['classroom_id']===$classroomId){ $roll=(int)$cur['roll_number'] ?: null; }
            if($roll===null){
                $r=$this->first("SELECT COALESCE(MAX(roll_number),0)+1 n FROM student_enrollments WHERE classroom_id=? AND status='active'", [$classroomId]);
                $roll=(int)($r['n']??1);
            }
        }
        if($cur){
            $this->execute("UPDATE student_enrollments SET classroom_id=?, roll_number=? WHERE id=?", [$classroomId,$roll,$cur['id']]);
            return ['ok'=>true,'msg'=>(int)$cur['classroom_id']===$classroomId?'อัปเดตเลขที่แล้ว':'ย้ายห้องเรียบร้อย'];
        }
        $this->execute("INSERT INTO student_enrollments (student_id, classroom_id, academic_year_id, roll_number, status)
            VALUES (?,?,1,?, 'active')", [$studentId,$classroomId,$roll]);
        return ['ok'=>true,'msg'=>'จัดเข้าห้องเรียบร้อย'];
    }

    public function classTeachingList(int $classroomId): array
    {
        return $this->query("SELECT ta.id, ta.weekly_periods, s.subject_code, s.name_th AS subject_name,
            CONCAT(pe.first_name,' ',pe.last_name) AS teacher_name
            FROM teaching_assignments ta JOIN subjects s ON s.id=ta.subject_id
            JOIN personnel pe ON pe.id=ta.teacher_id
            WHERE ta.classroom_id=? ORDER BY s.subject_code", [$classroomId]);
    }

    public function teachersWithLoad(string $q=''): array
    {
        $w="WHERE pe.deleted_at IS NULL AND pe.status='active'"; $p=[];
        if($q!==''){ $w.=" AND (pe.first_name LIKE ? OR pe.last_name LIKE ? OR pe.employee_code LIKE ?)"; $l="%$q%"; array_push($p,$l,$l,$l); }
        return $this->query("SELECT pe.id, pe.employee_code, pe.prefix, pe.first_name, pe.last_name, pe.position,
            pe.is_part_time, pe.work_days, pe.max_weekly_periods, sg.name AS group_name,
            (SELECT COUNT(*) FROM teaching_assignments ta WHERE ta.teacher_id=pe.id) AS class_count,
            (SELECT COALESCE(SUM(ta.weekly_periods),0) FROM teaching_assignments ta WHERE ta.teacher_id=pe.id) AS load_periods,
            (SELECT COUNT(*) FROM teacher_unavailable tu WHERE tu.teacher_id=pe.id) AS unavail_count
            FROM personnel pe LEFT JOIN subject_groups sg ON sg.id=pe.subject_group_id
            $w ORDER BY pe.first_name", $p);
    }
    public function teacherConfig(int $id, int $partTime, ?string $workDays, ?int $maxWeekly): void
    {
        $this->execute("UPDATE personnel SET is_part_time=?, work_days=?, max_weekly_periods=? WHERE id=?",
            [$partTime,$workDays?:null,$maxWeekly?:null,$id]);
    }
    public function unavailList(): array
    {
        return $this->query("SELECT tu.*, CONCAT(pe.first_name,' ',pe.last_name) AS teacher_name
            FROM teacher_unavailable tu JOIN personnel pe ON pe.id=tu.teacher_id
            ORDER BY pe.first_name, tu.day_of_week, tu.period_no");
    }
    public function unavailAdd(int $teacherId, int $day, ?int $period, ?string $reason): void
    {
        $this->execute("INSERT INTO teacher_unavailable (teacher_id, day_of_week, period_no, reason) VALUES (?,?,?,?)",
            [$teacherId,$day,$period,$reason?:null]);
    }
    public function unavailDelete(int $id): void
    { $this->execute("DELETE FROM teacher_unavailable WHERE id=?", [$id]); }

    // ---------- สัดส่วนคะแนน (ระหว่างภาค : ปลายภาค) ----------
    public function getRatio(): array
    {
        $rows=$this->query("SELECT setting_key, setting_value FROM system_settings WHERE group_key='academic' AND setting_key IN ('grade_ratio_during','grade_ratio_final')");
        $m=[]; foreach($rows as $r) $m[$r['setting_key']]=(int)$r['setting_value'];
        return ['during'=>$m['grade_ratio_during']??70, 'final'=>$m['grade_ratio_final']??30];
    }
    /** สัดส่วนที่มีผลจริงของรายวิชา (ใช้ของวิชาถ้าตั้งไว้ มิฉะนั้นใช้ค่าระบบ) */
    public function ratioFor(int $taId): array
    {
        $r=$this->first("SELECT ratio_during, ratio_final FROM teaching_assignments WHERE id=?", [$taId]);
        if($r && $r['ratio_during']!==null && $r['ratio_final']!==null){
            return ['during'=>(int)$r['ratio_during'],'final'=>(int)$r['ratio_final'],'custom'=>true];
        }
        $sys=$this->getRatio(); $sys['custom']=false; return $sys;
    }
    public function setTaRatio(int $taId, ?int $during, ?int $final): void
    {
        $this->execute("UPDATE teaching_assignments SET ratio_during=?, ratio_final=? WHERE id=?", [$during,$final,$taId]);
    }
    public function setRatio(int $during, int $final): void
    {
        foreach(['grade_ratio_during'=>$during,'grade_ratio_final'=>$final] as $k=>$v){
            $this->execute("INSERT INTO system_settings (group_key, setting_key, setting_value, value_type) VALUES ('academic',?,?, 'int')
                ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", [$k,(string)$v]);
        }
    }
    /** บัคเก็ตของ component: final → 'final', อื่น ๆ → 'during' */
    public static function bucketOf(array $component): string
    { return $component['component_type']==='final' ? 'final' : 'during'; }

    /** คำนวณคะแนนรวมเป็นร้อยละตามสัดส่วน (คืน 0-100) */
    public static function computePercent(array $components, array $studentScores, array $ratio): float
    {
        $dRaw=0;$dMax=0;$fRaw=0;$fMax=0;
        foreach($components as $c){
            $max=(float)$c['max_score']; $raw=(float)($studentScores[(int)$c['id']]??0);
            if(self::bucketOf($c)==='final'){ $fRaw+=$raw; $fMax+=$max; }
            else { $dRaw+=$raw; $dMax+=$max; }
        }
        $dPct=$dMax>0 ? $dRaw/$dMax*$ratio['during'] : 0;
        $fPct=$fMax>0 ? $fRaw/$fMax*$ratio['final'] : 0;
        return min(100, round($dPct+$fPct, 2));
    }

    // ---------- ติดตามการกรอกคะแนน ----------
    public function filledCount(int $taId, int $compCount): int
    {
        if($compCount<=0) return 0;
        $r=$this->first("SELECT COUNT(*) c FROM (
            SELECT sc.student_id FROM scores sc
            JOIN grade_components gc ON gc.id=sc.grade_component_id
            WHERE gc.teaching_assignment_id=? AND sc.score IS NOT NULL
            GROUP BY sc.student_id HAVING COUNT(DISTINCT sc.grade_component_id) >= ?
        ) t", [$taId,$compCount]);
        return (int)($r['c']??0);
    }
    public function entryStatusAll(?int $semesterId): array
    {
        $rows=$this->assignments($semesterId);
        foreach($rows as &$r){
            $students=(int)$r['student_count']; $comps=(int)$r['comp_count'];
            $filled=$comps>0 ? $this->filledCount((int)$r['id'],$comps) : 0;
            $r['filled']=$filled; $r['students']=$students;
            $r['percent']=$students>0 ? (int)round($filled/$students*100) : 0;
            if($comps===0) $r['stat']='no_component';
            elseif($students>0 && $filled>=$students) $r['stat']='complete';
            elseif($filled>0) $r['stat']='incomplete';
            else $r['stat']='not_started';
        }
        unset($r);
        return $rows;
    }
    public function monitorSummary(array $rows): array
    {
        $s=['complete'=>0,'incomplete'=>0,'not_started'=>0,'no_component'=>0];
        foreach($rows as $r) $s[$r['stat']]=($s[$r['stat']]??0)+1;
        return $s;
    }
}
