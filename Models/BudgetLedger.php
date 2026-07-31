<?php
namespace App\Models;
use App\Core\Model;

/**
 * บัญชีคุมงบประมาณกลาง — Single Source of Truth
 * ทุกโมดูล (ใบเบิก/เงินยืม/จัดซื้อ) ต้องเรียก deduct()/refund() ที่นี่เท่านั้น
 * ห้ามเขียน UPDATE budgets/projects/project_activities โดยตรงจากที่อื่น
 */
class BudgetLedger extends Model
{
    protected string $table = 'budget_ledger';

    /** ตัดงบ (เบิกจ่าย) — คืน id ของรายการบัญชี */
    public function deduct(string $sourceType, ?int $sourceId, ?string $sourceNo,
        ?int $budgetId, ?int $projectId, ?int $activityId, float $amount, ?string $note=null, ?int $by=null): int
    {
        return $this->post('deduct',$sourceType,$sourceId,$sourceNo,$budgetId,$projectId,$activityId,$amount,$note,$by);
    }

    /** คืนงบ — ยอดกลับเข้าโครงการ/กิจกรรม/แหล่งงบเดิม */
    public function refund(string $sourceType, ?int $sourceId, ?string $sourceNo,
        ?int $budgetId, ?int $projectId, ?int $activityId, float $amount, ?string $note=null, ?int $by=null): int
    {
        return $this->post('refund',$sourceType,$sourceId,$sourceNo,$budgetId,$projectId,$activityId,$amount,$note,$by);
    }

    /** บันทึกบัญชี + ปรับยอด 3 ชั้นพร้อมกัน */
    private function post(string $direction, string $sourceType, ?int $sourceId, ?string $sourceNo,
        ?int $budgetId, ?int $projectId, ?int $activityId, float $amount, ?string $note, ?int $by): int
    {
        $amount = round(abs($amount), 2);
        if ($amount <= 0) return 0;
        $sign = $direction==='refund' ? -1 : 1;   // คืนงบ = ลบยอดใช้ไป

        $this->execute("INSERT INTO budget_ledger
            (source_type, source_id, source_no, direction, amount, budget_id, project_id, activity_id, note, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$sourceType,$sourceId,$sourceNo,$direction,$amount,$budgetId?:null,$projectId?:null,$activityId?:null,$note,$by]);
        $id=$this->lastId();

        $delta = $sign * $amount;
        if($budgetId)   $this->execute("UPDATE budgets SET used_amount=GREATEST(0,used_amount+?) WHERE id=?", [$delta,$budgetId]);
        if($projectId)  $this->execute("UPDATE projects SET spent_amount=GREATEST(0,spent_amount+?) WHERE id=?", [$delta,$projectId]);
        if($activityId) $this->execute("UPDATE project_activities SET spent_amount=GREATEST(0,spent_amount+?) WHERE id=?", [$delta,$activityId]);
        return $id;
    }

    /** ยอดสุทธิที่ตัดไปแล้วของเอกสารหนึ่ง (ตัด - คืน) */
    public function netOf(string $sourceType, int $sourceId): float
    {
        $r=$this->first("SELECT COALESCE(SUM(CASE WHEN direction='deduct' THEN amount ELSE -amount END),0) n
            FROM budget_ledger WHERE source_type=? AND source_id=?", [$sourceType,$sourceId]);
        return (float)($r['n']??0);
    }

    /** รายการบัญชี (กรองได้) */
    public function entries(array $f=[]): array
    {
        $w=[]; $p=[];
        if(!empty($f['type'])){      $w[]='bl.source_type=?';  $p[]=$f['type']; }
        if(!empty($f['direction'])){ $w[]='bl.direction=?';     $p[]=$f['direction']; }
        if(!empty($f['project'])){   $w[]='bl.project_id=?';    $p[]=$f['project']; }
        if(!empty($f['from'])){      $w[]='DATE(bl.txn_date)>=?'; $p[]=$f['from']; }
        if(!empty($f['to'])){        $w[]='DATE(bl.txn_date)<=?'; $p[]=$f['to']; }
        $where=$w?('WHERE '.implode(' AND ',$w)):'';
        return $this->query("SELECT bl.*, pj.name AS project_name, ac.name AS activity_name,
            b.name AS budget_name, u.username AS by_user
            FROM budget_ledger bl
            LEFT JOIN projects pj ON pj.id=bl.project_id
            LEFT JOIN project_activities ac ON ac.id=bl.activity_id
            LEFT JOIN budgets b ON b.id=bl.budget_id
            LEFT JOIN users u ON u.id=bl.created_by
            $where ORDER BY bl.txn_date DESC, bl.id DESC LIMIT 500", $p);
    }

    /** สรุปยอดตามตัวกรองเดียวกัน */
    public function totals(array $f=[]): array
    {
        $w=[]; $p=[];
        if(!empty($f['type'])){      $w[]='source_type=?';  $p[]=$f['type']; }
        if(!empty($f['direction'])){ $w[]='direction=?';     $p[]=$f['direction']; }
        if(!empty($f['project'])){   $w[]='project_id=?';    $p[]=$f['project']; }
        if(!empty($f['from'])){      $w[]='DATE(txn_date)>=?'; $p[]=$f['from']; }
        if(!empty($f['to'])){        $w[]='DATE(txn_date)<=?'; $p[]=$f['to']; }
        $where=$w?('WHERE '.implode(' AND ',$w)):'';
        $r=$this->first("SELECT
            COALESCE(SUM(CASE WHEN direction='deduct' THEN amount ELSE 0 END),0) deducted,
            COALESCE(SUM(CASE WHEN direction='refund' THEN amount ELSE 0 END),0) refunded,
            COUNT(*) cnt FROM budget_ledger $where", $p);
        $r['net']=(float)$r['deducted']-(float)$r['refunded'];
        return $r;
    }

    /** ประวัติของโครงการหนึ่ง */
    public function forProject(int $projectId): array
    {
        return $this->query("SELECT bl.*, ac.name AS activity_name FROM budget_ledger bl
            LEFT JOIN project_activities ac ON ac.id=bl.activity_id
            WHERE bl.project_id=? ORDER BY bl.txn_date DESC, bl.id DESC", [$projectId]);
    }

    public function projectsList(): array { return $this->query("SELECT id, name FROM projects ORDER BY name"); }

    /** ตรวจความสอดคล้อง: ยอด ledger เทียบกับ projects.spent_amount */
    public function reconcile(): array
    {
        return $this->query("SELECT p.id, p.name, p.budget_amount, p.spent_amount,
            COALESCE(l.net,0) AS ledger_net,
            (p.spent_amount - COALESCE(l.net,0)) AS diff
            FROM projects p
            LEFT JOIN (SELECT project_id, SUM(CASE WHEN direction='deduct' THEN amount ELSE -amount END) net
                       FROM budget_ledger GROUP BY project_id) l ON l.project_id=p.id
            ORDER BY ABS(p.spent_amount - COALESCE(l.net,0)) DESC, p.name");
    }

    // ================= รายงานงบประมาณ (สร้างบนสมุดบัญชี = ตรงกับ ledger เสมอ) =================
    /** ช่วงเวลา: mode=month|quarter|year|custom, ปี พ.ศ. */
    public static function range(string $mode, int $yearBe, int $part=0, string $from='', string $to=''): array
    {
        $y=$yearBe-543;
        if($mode==='custom' && $from && $to) return [$from,$to,'ระหว่างวันที่ '.$from.' ถึง '.$to];
        $thm=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
        if($mode==='month'){
            $m=max(1,min(12,$part));
            return [sprintf('%04d-%02d-01',$y,$m), date('Y-m-t', mktime(0,0,0,$m,1,$y)), 'ประจำเดือน'.$thm[$m].' พ.ศ. '.$yearBe];
        }
        if($mode==='quarter'){
            $q=max(1,min(4,$part)); $sm=($q-1)*3+1;
            return [sprintf('%04d-%02d-01',$y,$sm), date('Y-m-t', mktime(0,0,0,$sm+2,1,$y)), 'ประจำไตรมาสที่ '.$q.' ปี พ.ศ. '.$yearBe];
        }
        return [sprintf('%04d-01-01',$y), sprintf('%04d-12-31',$y), 'ประจำปี พ.ศ. '.$yearBe];
    }

    private function periodWhere(string $from, string $to): array
    { return ['DATE(l.txn_date)>=? AND DATE(l.txn_date)<=?', [$from,$to]]; }

    /** ยอดสุทธิรวมในช่วง */
    public function reportTotals(string $from, string $to): array
    {
        [$w,$p]=$this->periodWhere($from,$to);
        $r=$this->first("SELECT
            COALESCE(SUM(CASE WHEN l.direction='deduct' THEN l.amount ELSE 0 END),0) deducted,
            COALESCE(SUM(CASE WHEN l.direction='refund' THEN l.amount ELSE 0 END),0) refunded,
            COUNT(*) cnt FROM budget_ledger l WHERE $w", $p);
        $r['net']=(float)$r['deducted']-(float)$r['refunded'];
        return $r;
    }

    /** แยกตามแหล่งงบ + เทียบยอดจัดสรร */
    public function reportByBudget(string $from, string $to): array
    {
        [$w,$p]=$this->periodWhere($from,$to);
        return $this->query("SELECT b.id, b.name, b.allocated_amount, b.used_amount,
            COALESCE(SUM(CASE WHEN l.direction='deduct' THEN l.amount ELSE 0 END),0) deducted,
            COALESCE(SUM(CASE WHEN l.direction='refund' THEN l.amount ELSE 0 END),0) refunded
            FROM budgets b LEFT JOIN budget_ledger l ON l.budget_id=b.id AND $w
            GROUP BY b.id, b.name, b.allocated_amount, b.used_amount ORDER BY b.id", $p);
    }

    /** แยกตามประเภทเอกสารต้นทาง */
    public function reportByType(string $from, string $to): array
    {
        [$w,$p]=$this->periodWhere($from,$to);
        return $this->query("SELECT l.source_type,
            COALESCE(SUM(CASE WHEN l.direction='deduct' THEN l.amount ELSE 0 END),0) deducted,
            COALESCE(SUM(CASE WHEN l.direction='refund' THEN l.amount ELSE 0 END),0) refunded,
            COUNT(*) cnt FROM budget_ledger l WHERE $w GROUP BY l.source_type ORDER BY deducted DESC", $p);
    }

    /** แยกตามฝ่าย (ผ่านโครงการ) */
    public function reportByDept(string $from, string $to): array
    {
        [$w,$p]=$this->periodWhere($from,$to);
        return $this->query("SELECT COALESCE(d.name,'ไม่ระบุฝ่าย') dept,
            COALESCE(SUM(CASE WHEN l.direction='deduct' THEN l.amount ELSE 0 END),0)
            - COALESCE(SUM(CASE WHEN l.direction='refund' THEN l.amount ELSE 0 END),0) net,
            COUNT(*) cnt
            FROM budget_ledger l
            LEFT JOIN projects pr ON pr.id=l.project_id
            LEFT JOIN org_departments d ON d.id=pr.department_id
            WHERE $w GROUP BY dept ORDER BY net DESC", $p);
    }

    /** แยกตามโครงการ */
    public function reportByProject(string $from, string $to): array
    {
        [$w,$p]=$this->periodWhere($from,$to);
        return $this->query("SELECT COALESCE(pr.name,'ไม่ผูกโครงการ') project, pr.budget_amount,
            COALESCE(SUM(CASE WHEN l.direction='deduct' THEN l.amount ELSE 0 END),0)
            - COALESCE(SUM(CASE WHEN l.direction='refund' THEN l.amount ELSE 0 END),0) net,
            COUNT(*) cnt
            FROM budget_ledger l LEFT JOIN projects pr ON pr.id=l.project_id
            WHERE $w GROUP BY project, pr.budget_amount ORDER BY net DESC LIMIT 20", $p);
    }

    /** รายเดือนสำหรับกราฟ */
    public function reportMonthly(string $from, string $to): array
    {
        [$w,$p]=$this->periodWhere($from,$to);
        return $this->query("SELECT DATE_FORMAT(l.txn_date,'%Y-%m') ym,
            COALESCE(SUM(CASE WHEN l.direction='deduct' THEN l.amount ELSE 0 END),0) deducted,
            COALESCE(SUM(CASE WHEN l.direction='refund' THEN l.amount ELSE 0 END),0) refunded
            FROM budget_ledger l WHERE $w GROUP BY ym ORDER BY ym", $p);
    }
}
