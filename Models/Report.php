<?php
namespace App\Models;
use App\Core\Model;

/**
 * รายงานผลการดำเนินงานภาพรวมโรงเรียน — รวบรวมข้ามทุกฝ่าย
 * (ใช้สำหรับประชุม/SAR — คนละมุมกับ /planning/exec ที่เป็น snapshot real-time)
 */
class Report extends Model
{
    protected string $table = 'schools';

    private function n(string $sql, array $p=[]): float
    { return (float)($this->first($sql,$p)['c'] ?? 0); }

    /** ช่วงเวลารายงาน (ปี พ.ศ.) */
    public static function yearRange(int $yearBe): array
    {
        $y=$yearBe-543;
        return [sprintf('%04d-01-01',$y), sprintf('%04d-12-31',$y)];
    }

    // ---------- 1. นักเรียน ----------
    public function students(): array
    {
        return [
            'total'      =>(int)$this->n("SELECT COUNT(*) c FROM students WHERE deleted_at IS NULL AND status='studying'"),
            'male'       =>(int)$this->n("SELECT COUNT(*) c FROM students WHERE deleted_at IS NULL AND status='studying' AND gender='male'"),
            'female'     =>(int)$this->n("SELECT COUNT(*) c FROM students WHERE deleted_at IS NULL AND status='studying' AND gender='female'"),
            'graduated'  =>(int)$this->n("SELECT COUNT(*) c FROM students WHERE deleted_at IS NULL AND status='graduated'"),
            'transferred'=>(int)$this->n("SELECT COUNT(*) c FROM students WHERE deleted_at IS NULL AND status='transferred'"),
            'dropped'    =>(int)$this->n("SELECT COUNT(*) c FROM students WHERE deleted_at IS NULL AND status='dropped'"),
            'byLevel'    =>$this->query("SELECT gl.name AS level, COUNT(s.id) c
                FROM grade_levels gl
                LEFT JOIN classrooms cl ON cl.grade_level_id=gl.id
                LEFT JOIN student_enrollments se ON se.classroom_id=cl.id AND se.status='active'
                LEFT JOIN students s ON s.id=se.student_id AND s.deleted_at IS NULL AND s.status='studying'
                GROUP BY gl.id, gl.name, gl.level_order ORDER BY gl.level_order"),
        ];
    }

    // ---------- 2. บุคลากร ----------
    public function personnel(): array
    {
        return [
            'total'   =>(int)$this->n("SELECT COUNT(*) c FROM personnel WHERE deleted_at IS NULL AND status='active'"),
            'byDept'  =>$this->query("SELECT COALESCE(d.name,'ไม่ระบุ') dept, COUNT(p.id) c
                FROM personnel p LEFT JOIN org_departments d ON d.id=p.department_id
                WHERE p.deleted_at IS NULL AND p.status='active' GROUP BY dept ORDER BY c DESC"),
        ];
    }

    // ---------- 3. วิชาการ ----------
    public function academic(string $from, string $to): array
    {
        $g=$this->query("SELECT grade, COUNT(*) c FROM final_grades
            WHERE is_finalized=1 AND grade IS NOT NULL GROUP BY grade ORDER BY grade DESC");
        $total=0; foreach($g as $r) $total+=(int)$r['c'];
        return [
            'finalized'=>(int)$this->n("SELECT COUNT(*) c FROM final_grades WHERE is_finalized=1"),
            'byGrade'  =>$g,
            'gradeTotal'=>$total,
            'special'  =>$this->query("SELECT special_result, COUNT(*) c FROM final_grades
                WHERE special_result IS NOT NULL AND special_result<>'' GROUP BY special_result"),
            'avgScore' =>round($this->n("SELECT AVG(total_score) c FROM final_grades WHERE is_finalized=1 AND total_score IS NOT NULL"),2),
        ];
    }

    // ---------- 4. งบประมาณ ----------
    public function budget(string $from, string $to): array
    {
        return [
            'alloc'   =>$this->n("SELECT COALESCE(SUM(allocated_amount),0) c FROM budgets"),
            'used'    =>$this->n("SELECT COALESCE(SUM(used_amount),0) c FROM budgets"),
            'deducted'=>$this->n("SELECT COALESCE(SUM(amount),0) c FROM budget_ledger WHERE direction='deduct' AND DATE(txn_date) BETWEEN ? AND ?", [$from,$to]),
            'refunded'=>$this->n("SELECT COALESCE(SUM(amount),0) c FROM budget_ledger WHERE direction='refund' AND DATE(txn_date) BETWEEN ? AND ?", [$from,$to]),
            'reqPaid' =>(int)$this->n("SELECT COUNT(*) c FROM budget_requests WHERE status='paid'"),
            'reqPending'=>(int)$this->n("SELECT COUNT(*) c FROM budget_requests WHERE status='pending'"),
            'advOutstanding'=>$this->n("SELECT COALESCE(SUM(amount-COALESCE(cleared_amount,0)),0) c FROM cash_advances WHERE status='paid'"),
        ];
    }

    // ---------- 5. โครงการ ----------
    public function projects(): array
    {
        $rows=$this->query("SELECT status, COUNT(*) c FROM projects GROUP BY status");
        $by=[]; foreach($rows as $r) $by[$r['status']]=(int)$r['c'];
        return [
            'total'    =>(int)$this->n("SELECT COUNT(*) c FROM projects"),
            'byStatus' =>$by,
            'overdue'  =>(int)$this->n("SELECT COUNT(*) c FROM projects WHERE status IN ('planned','ongoing')
                AND end_date IS NOT NULL AND end_date<CURDATE() AND progress_percent<100"),
            'avgProgress'=>round($this->n("SELECT AVG(progress_percent) c FROM projects WHERE status IN ('ongoing','completed')"),1),
        ];
    }

    // ---------- 6. กิจการนักเรียน ----------
    public function affairs(string $from, string $to): array
    {
        return [
            'merit'    =>(int)$this->n("SELECT COUNT(*) c FROM behavior_records WHERE type='merit' AND record_date BETWEEN ? AND ?", [$from,$to]),
            'demerit'  =>(int)$this->n("SELECT COUNT(*) c FROM behavior_records WHERE type='demerit' AND record_date BETWEEN ? AND ?", [$from,$to]),
            'sdqTotal' =>(int)$this->n("SELECT COUNT(*) c FROM sdq_assessments"),
            'sdqRisk'  =>(int)$this->n("SELECT COUNT(*) c FROM sdq_assessments WHERE result_group IN ('risk','problem')"),
            'visits'   =>(int)$this->n("SELECT COUNT(*) c FROM home_visits WHERE visit_date BETWEEN ? AND ?", [$from,$to]),
            'visitRisk'=>(int)$this->n("SELECT COUNT(*) c FROM home_visits WHERE risk_level IN ('risk','urgent') AND visit_date BETWEEN ? AND ?", [$from,$to]),
            'scholarCnt'=>(int)$this->n("SELECT COUNT(*) c FROM scholarships WHERE status='granted'"),
            'scholarAmt'=>$this->n("SELECT COALESCE(SUM(amount),0) c FROM scholarships WHERE status='granted'"),
        ];
    }

    // ---------- 7. บริหารทั่วไป ----------
    public function general(string $from, string $to): array
    {
        return [
            'assets'     =>(int)$this->n("SELECT COUNT(*) c FROM assets"),
            'assetValue' =>$this->n("SELECT COALESCE(SUM(acquired_price),0) c FROM assets"),
            'repairs'    =>(int)$this->n("SELECT COUNT(*) c FROM repair_requests WHERE DATE(reported_at) BETWEEN ? AND ?", [$from,$to]),
            'repairDone' =>(int)$this->n("SELECT COUNT(*) c FROM repair_requests WHERE status='done' AND DATE(reported_at) BETWEEN ? AND ?", [$from,$to]),
            'documents'  =>(int)$this->n("SELECT COUNT(*) c FROM documents WHERE DATE(created_at) BETWEEN ? AND ?", [$from,$to]),
            'bookings'   =>(int)$this->n("SELECT COUNT(*) c FROM vehicle_bookings WHERE DATE(depart_at) BETWEEN ? AND ?", [$from,$to]),
        ];
    }

    // ---------- 8. บุคคล (การลา) ----------
    public function hr(string $from, string $to): array
    {
        return [
            'leaves'    =>(int)$this->n("SELECT COUNT(*) c FROM leaves WHERE start_date BETWEEN ? AND ?", [$from,$to]),
            'leaveDays' =>$this->n("SELECT COALESCE(SUM(days),0) c FROM leaves WHERE status='approved' AND start_date BETWEEN ? AND ?", [$from,$to]),
            'byType'    =>$this->query("SELECT lt.name AS type, COUNT(l.id) c, COALESCE(SUM(l.days),0) days
                FROM leaves l JOIN leave_types lt ON lt.id=l.leave_type_id
                WHERE l.status='approved' AND l.start_date BETWEEN ? AND ?
                GROUP BY lt.id, lt.name ORDER BY c DESC", [$from,$to]),
        ];
    }

    /** รวมทุกด้าน */
    public function summary(int $yearBe): array
    {
        [$from,$to]=self::yearRange($yearBe);
        return [
            'yearBe'=>$yearBe, 'from'=>$from, 'to'=>$to,
            'students'=>$this->students(),
            'personnel'=>$this->personnel(),
            'academic'=>$this->academic($from,$to),
            'budget'=>$this->budget($from,$to),
            'projects'=>$this->projects(),
            'affairs'=>$this->affairs($from,$to),
            'general'=>$this->general($from,$to),
            'hr'=>$this->hr($from,$to),
        ];
    }

    public function years(): array
    { return $this->query("SELECT year_be, is_current FROM academic_years ORDER BY year_be DESC"); }
}
