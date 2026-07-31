<?php
namespace App\Models;
use App\Core\Model;

class Hr extends Model
{
    protected string $table = 'personnel';

    // ---------- ตัวช่วยทั่วไป ----------
    public function people(): array
    {
        return $this->query("SELECT p.id, p.employee_code, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS name,
            p.position, p.employment_type, d.name AS dept
            FROM personnel p LEFT JOIN org_departments d ON d.id=p.department_id
            WHERE p.deleted_at IS NULL AND p.status='active' ORDER BY p.first_name");
    }
    public function personFind(int $id): ?array
    {
        return $this->first("SELECT p.*, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS name, d.name AS dept
            FROM personnel p LEFT JOIN org_departments d ON d.id=p.department_id WHERE p.id=?", [$id]);
    }
    private function thaiYear(): int { return (int)date('Y')+543; }
    public function currentYearBe(): int { return $this->thaiYear(); }
    public function currentMonth(): int { return (int)date('n'); }

    // ---------- การลา / มาสาย ----------
    public function leaveTypes(): array
    { return $this->query("SELECT id, code, name, max_days_year FROM leave_types ORDER BY id"); }

    public function leaves(array $f=[]): array
    {
        $w=[]; $p=[];
        if(!empty($f['status'])){ $w[]='l.status=?'; $p[]=$f['status']; }
        if(!empty($f['person'])){ $w[]='l.personnel_id=?'; $p[]=$f['person']; }
        $where=$w?('WHERE '.implode(' AND ',$w)):'';
        return $this->query("SELECT l.*, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS person_name,
            lt.name AS type_name, CONCAT(a.prefix,a.first_name,' ',a.last_name) AS approver_name
            FROM leaves l
            JOIN personnel p ON p.id=l.personnel_id
            JOIN leave_types lt ON lt.id=l.leave_type_id
            LEFT JOIN personnel a ON a.id=l.approver_id
            $where ORDER BY l.created_at DESC", $p);
    }
    public function leaveFind(int $id): ?array
    {
        return $this->first("SELECT l.*, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS person_name, lt.name AS type_name
            FROM leaves l JOIN personnel p ON p.id=l.personnel_id JOIN leave_types lt ON lt.id=l.leave_type_id WHERE l.id=?", [$id]);
    }
    public function leaveCreate(int $personId, int $typeId, string $start, string $end, string $reason): int
    {
        $days=max(1,(int)((strtotime($end)-strtotime($start))/86400)+1);
        $this->execute("INSERT INTO leaves (personnel_id, leave_type_id, start_date, end_date, days, reason, status)
            VALUES (?,?,?,?,?,?, 'pending')", [$personId,$typeId,$start,$end,$days,$reason]);
        return $this->lastId();
    }
    public function leaveDecide(int $id, string $status, ?int $approverId): void
    {
        $this->execute("UPDATE leaves SET status=?, approver_id=?, approved_at=NOW() WHERE id=?", [$status,$approverId,$id]);
    }
    public function leaveUpdate(int $id, int $personId, int $typeId, string $start, string $end, string $reason, string $status): void
    {
        $days=max(1,(int)((strtotime($end)-strtotime($start))/86400)+1);
        $this->execute("UPDATE leaves SET personnel_id=?, leave_type_id=?, start_date=?, end_date=?, days=?, reason=?, status=? WHERE id=?",
            [$personId,$typeId,$start,$end,$days,$reason,$status,$id]);
    }
    public function leaveDelete(int $id): void { $this->execute("DELETE FROM leaves WHERE id=?", [$id]); }
    /** ใบลาพร้อมข้อมูลบุคคลเต็ม (สำหรับพิมพ์) */
    public function leaveForPrint(int $id): ?array
    {
        return $this->first("SELECT l.*, lt.name AS type_name,
            CONCAT(p.prefix,p.first_name,' ',p.last_name) AS person_name, p.position, p.employee_code,
            d.name AS dept, CONCAT(a.prefix,a.first_name,' ',a.last_name) AS approver_name
            FROM leaves l
            JOIN personnel p ON p.id=l.personnel_id
            JOIN leave_types lt ON lt.id=l.leave_type_id
            LEFT JOIN org_departments d ON d.id=p.department_id
            LEFT JOIN personnel a ON a.id=l.approver_id
            WHERE l.id=?", [$id]);
    }
    /** สรุปวันลาของบุคคลในปีปัจจุบัน (นับเฉพาะที่อนุมัติ) */
    public function leaveSummary(int $personId): array
    {
        return $this->query("SELECT lt.name, lt.max_days_year, COALESCE(SUM(CASE WHEN l.status='approved' THEN l.days ELSE 0 END),0) AS used
            FROM leave_types lt
            LEFT JOIN leaves l ON l.leave_type_id=lt.id AND l.personnel_id=? AND YEAR(l.start_date)=YEAR(CURDATE())
            GROUP BY lt.id ORDER BY lt.id", [$personId]);
    }

    // ---------- ลงเวลาปฏิบัติงาน ----------
    public function attendanceOfDate(string $date): array
    {
        $rows=$this->query("SELECT personnel_id, status, check_in, check_out, note FROM staff_attendance WHERE work_date=?", [$date]);
        $m=[]; foreach($rows as $r) $m[(int)$r['personnel_id']]=$r; return $m;
    }
    public function attendanceSave(int $personId, string $date, string $status, ?string $in, ?string $out, ?string $note, ?int $by): void
    {
        $status=in_array($status,['present','late','absent','leave','official'],true)?$status:'present';
        $this->execute("INSERT INTO staff_attendance (personnel_id, work_date, check_in, check_out, status, note, recorded_by)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), check_out=VALUES(check_out), status=VALUES(status), note=VALUES(note), recorded_by=VALUES(recorded_by)",
            [$personId,$date,$in?:null,$out?:null,$status,$note?:null,$by]);
    }
    public function attendanceMonth(int $personId, int $yearBe, int $month): array
    {
        return $this->query("SELECT work_date, status, check_in, check_out FROM staff_attendance
            WHERE personnel_id=? AND YEAR(work_date)=? AND MONTH(work_date)=? ORDER BY work_date",
            [$personId, $yearBe-543, $month]);
    }

    // ---------- เงินเดือน ----------
    public function salaries(int $yearBe, int $month): array
    {
        return $this->query("SELECT s.*, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS person_name, p.position, p.employee_code
            FROM salary_records s JOIN personnel p ON p.id=s.personnel_id
            WHERE s.year_be=? AND s.month=? ORDER BY p.first_name", [$yearBe,$month]);
    }
    public function salaryFind(int $id): ?array
    {
        return $this->first("SELECT s.*, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS person_name, p.position, p.employee_code
            FROM salary_records s JOIN personnel p ON p.id=s.personnel_id WHERE s.id=?", [$id]);
    }
    public function salaryUpsert(int $personId, int $yearBe, int $month, float $base, float $allow, float $deduct, ?string $note, ?int $by): void
    {
        $net=$base+$allow-$deduct;
        $this->execute("INSERT INTO salary_records (personnel_id, year_be, month, base_salary, allowance, deduction, net_salary, note, created_by)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE base_salary=VALUES(base_salary), allowance=VALUES(allowance), deduction=VALUES(deduction), net_salary=VALUES(net_salary), note=VALUES(note)",
            [$personId,$yearBe,$month,$base,$allow,$deduct,$net,$note?:null,$by]);
    }
    public function salaryHistory(int $personId): array
    {
        return $this->query("SELECT year_be, month, base_salary, allowance, deduction, net_salary FROM salary_records
            WHERE personnel_id=? ORDER BY year_be DESC, month DESC LIMIT 12", [$personId]);
    }

    // ---------- PA / วิทยฐานะ / ประเมิน ----------
    public function paList(array $f=[]): array
    {
        $w=[]; $p=[];
        if(!empty($f['year'])){ $w[]='e.year_be=?'; $p[]=$f['year']; }
        if(!empty($f['type'])){ $w[]='e.eval_type=?'; $p[]=$f['type']; }
        if(!empty($f['person'])){ $w[]='e.personnel_id=?'; $p[]=$f['person']; }
        $where=$w?('WHERE '.implode(' AND ',$w)):'';
        return $this->query("SELECT e.*, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS person_name, p.position, p.academic_standing
            FROM pa_evaluations e JOIN personnel p ON p.id=e.personnel_id
            $where ORDER BY e.year_be DESC, e.round, p.first_name", $p);
    }
    public function paFind(int $id): ?array
    {
        return $this->first("SELECT e.*, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS person_name, p.position, p.academic_standing
            FROM pa_evaluations e JOIN personnel p ON p.id=e.personnel_id WHERE e.id=?", [$id]);
    }
    public function paCreate(array $d): int
    {
        $this->execute("INSERT INTO pa_evaluations (personnel_id, year_be, round, eval_type, score, grade, target_standing, result, comment, evaluator_id, eval_date)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)", [
            $d['personnel_id'],$d['year_be'],$d['round'],$d['eval_type'],$d['score']?:null,$d['grade']?:null,
            $d['target_standing']?:null,$d['result'],$d['comment']?:null,$d['evaluator_id']?:null,$d['eval_date']?:null]);
        return $this->lastId();
    }
    public function paDelete(int $id): void { $this->execute("DELETE FROM pa_evaluations WHERE id=?", [$id]); }
    public function salaryDelete(int $id): void { $this->execute("DELETE FROM salary_records WHERE id=?", [$id]); }
    public function attendanceList(int $yearBe, int $month): array
    {
        return $this->query("SELECT sa.*, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS person_name
            FROM staff_attendance sa JOIN personnel p ON p.id=sa.personnel_id
            WHERE YEAR(sa.work_date)=? AND MONTH(sa.work_date)=? ORDER BY sa.work_date DESC, p.first_name",
            [$yearBe-543,$month]);
    }
    public function attendanceDelete(int $id): void { $this->execute("DELETE FROM staff_attendance WHERE id=?", [$id]); }

    /** ข้อมูลสำหรับ SAR — ดึงสถิติจากทั้งระบบ */
    public function sarData(int $yearBe): array
    {
        $y=$yearBe-543;
        $staff=(int)($this->first("SELECT COUNT(*) c FROM personnel WHERE deleted_at IS NULL AND status='active'")['c']??0);
        $students=(int)($this->first("SELECT COUNT(*) c FROM students WHERE deleted_at IS NULL")['c']??0);
        $classes=(int)($this->first("SELECT COUNT(*) c FROM classrooms")['c']??0);
        $subjects=(int)($this->first("SELECT COUNT(*) c FROM subjects WHERE is_active=1")['c']??0);
        // ผลการเรียนเฉลี่ยทั้งโรงเรียน (จาก final_grades ปีที่เลือก)
        $gpaRow=$this->first("SELECT AVG(CASE fg.grade
              WHEN '4' THEN 4 WHEN '3.5' THEN 3.5 WHEN '3' THEN 3 WHEN '2.5' THEN 2.5
              WHEN '2' THEN 2 WHEN '1.5' THEN 1.5 WHEN '1' THEN 1 WHEN '0' THEN 0 ELSE NULL END) g
            FROM final_grades fg
            JOIN teaching_assignments ta ON ta.id=fg.teaching_assignment_id
            JOIN semesters sem ON sem.id=ta.semester_id
            JOIN academic_years ay ON ay.id=sem.academic_year_id
            WHERE ay.year_be=?", [$yearBe]);
        // วิทยฐานะบุคลากร
        $standing=$this->query("SELECT COALESCE(NULLIF(academic_standing,''),'ไม่ระบุ') s, COUNT(*) c
            FROM personnel WHERE deleted_at IS NULL AND status='active' GROUP BY academic_standing");
        // งบประมาณปีนี้
        $budget=$this->first("SELECT COALESCE(SUM(allocated_amount),0) alloc, COALESCE(SUM(used_amount),0) used FROM budgets");
        // PA ผ่านปีนี้
        $paPass=(int)($this->first("SELECT COUNT(*) c FROM pa_evaluations WHERE result='passed' AND year_be=?", [$yearBe])['c']??0);
        // การมาปฏิบัติงาน (เฉลี่ยทั้งปี)
        $att=$this->first("SELECT
            SUM(status='present') p, SUM(status='late') l, SUM(status='absent') a, COUNT(*) t
            FROM staff_attendance WHERE YEAR(work_date)=?", [$y]);
        return [
            'staff'=>$staff,'students'=>$students,'classes'=>$classes,'subjects'=>$subjects,
            'gpa'=>$gpaRow&&$gpaRow['g']!==null?round((float)$gpaRow['g'],2):null,
            'standing'=>$standing,'budget'=>$budget,'paPass'=>$paPass,'att'=>$att,
        ];
    }
    public function paForPerson(int $personId): array
    {
        return $this->query("SELECT * FROM pa_evaluations WHERE personnel_id=? ORDER BY year_be DESC, round DESC", [$personId]);
    }

    // ---------- แดชบอร์ด HR ----------
    public function dashboard(): array
    {
        $total=(int)($this->first("SELECT COUNT(*) c FROM personnel WHERE deleted_at IS NULL AND status='active'")['c']??0);
        $byType=$this->query("SELECT employment_type, COUNT(*) c FROM personnel WHERE deleted_at IS NULL AND status='active' GROUP BY employment_type");
        $pendLeave=(int)($this->first("SELECT COUNT(*) c FROM leaves WHERE status='pending'")['c']??0);
        $today=$this->first("SELECT
            SUM(status='present') p, SUM(status='late') l, SUM(status='absent') a, SUM(status IN ('leave','official')) o
            FROM staff_attendance WHERE work_date=CURDATE()");
        $paPass=(int)($this->first("SELECT COUNT(*) c FROM pa_evaluations WHERE result='passed' AND year_be=".$this->thaiYear())['c']??0);
        return ['total'=>$total,'byType'=>$byType,'pendLeave'=>$pendLeave,'today'=>$today,'paPass'=>$paPass];
    }
}
