<?php
namespace App\Models;
use App\Core\Model;

/** แผนงาน/โครงการ/กิจกรรมย่อย + เบิกงบ + ยืมเงิน + อนุมัติหลายระดับ + ตัดงบอัตโนมัติ */
class Planning extends Model
{
    protected string $table = 'projects';

    public static function levels(): array
    { return [1=>'หัวหน้าฝ่ายงบประมาณ',2=>'รองผู้อำนวยการ',3=>'ผู้อำนวยการ']; }

    // ---------- โครงการ + กิจกรรม ----------
    public function projects(?int $deptId=null): array
    {
        $w=''; $p=[];
        if($deptId){ $w=' WHERE p.department_id=?'; $p[]=$deptId; }
        return $this->query("SELECT p.*, b.name AS budget_name, d.name AS department_name,
            CONCAT(pe.first_name,' ',pe.last_name) AS responsible,
            (SELECT COUNT(*) FROM project_activities a WHERE a.project_id=p.id) AS activity_count,
            (SELECT COALESCE(SUM(a.budget_amount),0) FROM project_activities a WHERE a.project_id=p.id) AS activities_budget
            FROM projects p LEFT JOIN budgets b ON b.id=p.budget_id
            LEFT JOIN org_departments d ON d.id=p.department_id
            LEFT JOIN personnel pe ON pe.id=p.responsible_id
            $w
            ORDER BY (p.department_id IS NULL), p.department_id, p.status='ongoing' DESC, p.id DESC", $p);
    }

    /** ฝ่ายงาน (org_departments) + จำนวนโครงการ — สำหรับตัวกรอง/แยกกลุ่ม */
    public function projectDepartments(): array
    {
        return $this->query("SELECT d.id, d.name,
            (SELECT COUNT(*) FROM projects p WHERE p.department_id=d.id) AS n
            FROM org_departments d ORDER BY d.id");
    }
    public function projectFind(int $id): ?array
    {
        return $this->first("SELECT p.*, b.name AS budget_name, CONCAT(pe.first_name,' ',pe.last_name) AS responsible
            FROM projects p LEFT JOIN budgets b ON b.id=p.budget_id
            LEFT JOIN personnel pe ON pe.id=p.responsible_id WHERE p.id=?", [$id]);
    }
    public function activities(int $projectId): array
    { return $this->query("SELECT * FROM project_activities WHERE project_id=? ORDER BY id", [$projectId]); }
    public function activityAdd(int $projectId, string $name, float $amount, ?string $s, ?string $e): void
    {
        $this->execute("INSERT INTO project_activities (project_id, name, budget_amount, start_date, end_date) VALUES (?,?,?,?,?)",
            [$projectId,$name,$amount,$s?:null,$e?:null]);
    }
    public function activityDelete(int $id): void
    { $this->execute("DELETE FROM project_activities WHERE id=?", [$id]); }
    public function activityStatus(int $id, string $st): void
    { $this->execute("UPDATE project_activities SET status=? WHERE id=?", [$st,$id]); }
    public function activitiesAll(): array
    {
        return $this->query("SELECT a.id, a.name, a.project_id, p.name AS project_name FROM project_activities a
            JOIN projects p ON p.id=a.project_id WHERE a.status<>'cancelled' ORDER BY p.id, a.id");
    }

    // ---------- เลขเอกสาร ----------
    private function nextNo(string $table, string $col, string $prefix): string
    {
        $y=date('Y')+543;
        $r=$this->first("SELECT $col FROM $table WHERE $col LIKE ? ORDER BY id DESC LIMIT 1", ["$prefix-$y-%"]);
        $n=$r?((int)substr($r[$col],-4))+1:1;
        return sprintf('%s-%d-%04d',$prefix,$y,$n);
    }

    // ---------- เบิกงบ ----------
    public function requests(string $status=''): array
    {
        $w=''; $p=[];
        if($status!==''){ $w='WHERE br.status=?'; $p[]=$status; }
        return $this->query("SELECT br.*, p.name AS project_name, a.name AS activity_name, u.username AS requester
            FROM budget_requests br
            LEFT JOIN projects p ON p.id=br.project_id
            LEFT JOIN project_activities a ON a.id=br.activity_id
            LEFT JOIN users u ON u.id=br.requester_id
            $w ORDER BY br.status='pending' DESC, br.id DESC", $p);
    }
    public function requestFind(int $id): ?array
    {
        return $this->first("SELECT br.*, p.name AS project_name, p.budget_id, a.name AS activity_name, u.username AS requester
            FROM budget_requests br
            LEFT JOIN projects p ON p.id=br.project_id
            LEFT JOIN project_activities a ON a.id=br.activity_id
            LEFT JOIN users u ON u.id=br.requester_id WHERE br.id=?", [$id]);
    }
    public function requestCreate(?int $projectId, ?int $activityId, ?int $userId, float $amount, string $purpose): int
    {
        $no=$this->nextNo('budget_requests','request_no','บง');
        $this->execute("INSERT INTO budget_requests (request_no, project_id, activity_id, requester_id, amount, purpose) VALUES (?,?,?,?,?,?)",
            [$no,$projectId?:null,$activityId?:null,$userId,$amount,$purpose]);
        return $this->lastId();
    }

    // ---------- ยืมเงิน ----------
    public function advances(string $status=''): array
    {
        $w=''; $p=[];
        if($status!==''){ $w='WHERE ca.status=?'; $p[]=$status; }
        return $this->query("SELECT ca.*, p.name AS project_name, u.username AS borrower,
            (ca.status='paid' AND ca.due_date IS NOT NULL AND ca.due_date<CURDATE()) AS overdue
            FROM cash_advances ca
            LEFT JOIN projects p ON p.id=ca.project_id
            LEFT JOIN users u ON u.id=ca.borrower_id
            $w ORDER BY ca.status='pending' DESC, ca.id DESC", $p);
    }
    public function advanceFind(int $id): ?array
    {
        return $this->first("SELECT ca.*, p.name AS project_name, p.budget_id, a.name AS activity_name,
            COALESCE(CONCAT(pe.prefix,pe.first_name,' ',pe.last_name), u.full_name, u.username) AS borrower,
            COALESCE(ca.borrower_position, pe.position) AS borrower_pos
            FROM cash_advances ca
            LEFT JOIN projects p ON p.id=ca.project_id
            LEFT JOIN project_activities a ON a.id=ca.activity_id
            LEFT JOIN users u ON u.id=ca.borrower_id
            LEFT JOIN personnel pe ON pe.id=u.linked_id AND u.linked_type='personnel'
            WHERE ca.id=?", [$id]);
    }
    /** รายการค่าใช้จ่ายย่อยของสัญญายืม (แบบ 8500) */
    public function advanceItems(int $advanceId): array
    { return $this->query("SELECT id, name, amount FROM cash_advance_items WHERE cash_advance_id=? ORDER BY sort_order, id", [$advanceId]); }

    /** ผู้ลงนามในสัญญายืม: เจ้าหน้าที่การเงิน + ผู้อนุมัติ (ผอ.) จากข้อมูลบุคลากรจริง */
    public function advanceSigners(): array
    {
        $byPos=function(string $like, ?string $not=null){
            $sql="SELECT CONCAT(prefix,first_name,' ',last_name) AS name, position,
                   (SELECT u.signature_path FROM users u WHERE u.linked_type='personnel' AND u.linked_id=personnel.id AND u.deleted_at IS NULL LIMIT 1) AS signature FROM personnel
                  WHERE deleted_at IS NULL AND status='active' AND position LIKE ?";
            $p=['%'.$like.'%']; if($not!==null){ $sql.=" AND position NOT LIKE ?"; $p[]='%'.$not.'%'; }
            return $this->first($sql.' ORDER BY id LIMIT 1',$p);
        };
        return ['finance'=>$byPos('การเงิน')?:$byPos('งบประมาณ'), 'director'=>$byPos('ผู้อำนวยการ','รอง')];
    }

    public function advanceCreate(?int $projectId, ?int $activityId, ?int $userId, float $amount, string $purpose, ?string $due, array $extra=[]): int
    {
        $no=$this->nextNo('cash_advances','advance_no','ยม');
        $this->execute("INSERT INTO cash_advances (advance_no, borrower_id, borrower_position, project_id, activity_id, amount, purpose, due_date, lend_from, repay_days) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$no,$userId,$extra['borrower_position']??null,$projectId?:null,$activityId?:null,$amount,$purpose,$due?:null,
             $extra['lend_from']??null,(int)($extra['repay_days']??30)]);
        $id=$this->lastId();
        foreach(($extra['items']??[]) as $i=>$it){
            $this->execute("INSERT INTO cash_advance_items (cash_advance_id, name, amount, sort_order) VALUES (?,?,?,?)",
                [$id, $it['name'], $it['amount'], $i]);
        }
        return $id;
    }

    // ---------- อนุมัติหลายระดับ (ใช้ร่วม) ----------
    public function approvalLogs(string $type, int $refId): array
    {
        return $this->query("SELECT al.*, u.username FROM approval_logs al LEFT JOIN users u ON u.id=al.approver_id
            WHERE al.ref_type=? AND al.ref_id=? ORDER BY al.level", [$type,$refId]);
    }
    /** อนุมัติ/ไม่อนุมัติระดับปัจจุบัน คืน status ใหม่ */
    public function decide(string $type, int $refId, bool $approve, ?int $userId, string $comment=''): string
    {
        $table=$type==='budget_request'?'budget_requests':'cash_advances';
        $row=$this->first("SELECT id, status, current_level FROM $table WHERE id=?", [$refId]);
        if(!$row || $row['status']!=='pending') return $row['status']??'unknown';
        $level=(int)$row['current_level'];
        $this->execute("INSERT INTO approval_logs (ref_type, ref_id, level, level_name, approver_id, action, comment)
            VALUES (?,?,?,?,?,?,?)", [$type,$refId,$level,self::levels()[$level]??('ระดับ '.$level),$userId,$approve?'approved':'rejected',$comment?:null]);
        if(!$approve){
            $this->execute("UPDATE $table SET status='rejected' WHERE id=?", [$refId]);
            return 'rejected';
        }
        if($level>=3){
            $this->execute("UPDATE $table SET status='approved' WHERE id=?", [$refId]);
            return 'approved';
        }
        $this->execute("UPDATE $table SET current_level=current_level+1 WHERE id=?", [$refId]);
        return 'pending';
    }

    // ---------- จ่าย/ตัดงบอัตโนมัติ ----------
    /** จ่ายเบิกงบ: ตัดงบผ่านบัญชีคุมงบกลาง (BudgetLedger) */
    public function payRequest(int $id, ?int $by=null): bool
    {
        $r=$this->requestFind($id);
        if(!$r || $r['status']!=='approved') return false;
        $this->execute("UPDATE budget_requests SET status='paid', paid_at=NOW() WHERE id=?", [$id]);
        $this->ledger()->deduct('request',$id,$r['request_no'],
            $r['budget_id']?(int)$r['budget_id']:null, $r['project_id']?(int)$r['project_id']:null,
            $r['activity_id']?(int)$r['activity_id']:null, (float)$r['amount'], 'จ่ายใบเบิกงบประมาณ', $by);
        return true;
    }
    /** ยกเลิกใบเบิกที่จ่ายแล้ว → คืนเงินเข้าโครงการนั้นเต็มจำนวน */
    public function cancelRequest(int $id, ?string $reason=null, ?int $by=null): bool
    {
        $r=$this->requestFind($id);
        if(!$r) return false;
        if($r['status']==='paid'){
            $net=$this->ledger()->netOf('request',$id);
            if($net>0){
                $this->ledger()->refund('request',$id,$r['request_no'],
                    $r['budget_id']?(int)$r['budget_id']:null, $r['project_id']?(int)$r['project_id']:null,
                    $r['activity_id']?(int)$r['activity_id']:null, $net,
                    'ยกเลิกใบเบิก — คืนเงินเข้าโครงการ'.($reason?(': '.$reason):''), $by);
                $this->execute("UPDATE budget_requests SET refunded_amount=refunded_amount+? WHERE id=?", [$net,$id]);
            }
        } elseif(!in_array($r['status'],['pending','approved'],true)) return false;
        $this->execute("UPDATE budget_requests SET status='cancelled' WHERE id=?", [$id]);
        return true;
    }
    /** คืนเงินบางส่วนของใบเบิกที่จ่ายแล้ว (เงินเหลือจ่าย) */
    public function refundRequest(int $id, float $amount, ?string $reason=null, ?int $by=null): bool
    {
        $r=$this->requestFind($id);
        if(!$r || $r['status']!=='paid') return false;
        $net=$this->ledger()->netOf('request',$id);
        $amount=min(round(abs($amount),2), $net);
        if($amount<=0) return false;
        $this->ledger()->refund('request',$id,$r['request_no'],
            $r['budget_id']?(int)$r['budget_id']:null, $r['project_id']?(int)$r['project_id']:null,
            $r['activity_id']?(int)$r['activity_id']:null, $amount,
            'คืนเงินเหลือจ่าย'.($reason?(': '.$reason):''), $by);
        $this->execute("UPDATE budget_requests SET refunded_amount=refunded_amount+? WHERE id=?", [$amount,$id]);
        return true;
    }
    public function requestDelete(int $id): bool
    {
        $r=$this->requestFind($id);
        if(!$r || $r['status']==='paid') return false;   // จ่ายแล้วต้องยกเลิก+คืนเงินเท่านั้น
        $this->execute("DELETE FROM approval_logs WHERE ref_type='request' AND ref_id=?", [$id]);
        $this->execute("DELETE FROM budget_requests WHERE id=?", [$id]);
        return true;
    }

    /** จ่ายเงินยืม: ตัดงบเมื่อจ่าย (ยอดเต็ม) */
    public function payAdvance(int $id, ?int $by=null): bool
    {
        $r=$this->advanceFind($id);
        if(!$r || $r['status']!=='approved') return false;
        $this->execute("UPDATE cash_advances SET status='paid', paid_at=NOW() WHERE id=?", [$id]);
        $this->ledger()->deduct('advance',$id,$r['advance_no'],
            $r['budget_id']?(int)$r['budget_id']:null, $r['project_id']?(int)$r['project_id']:null,
            $r['activity_id']?(int)$r['activity_id']:null, (float)$r['amount'], 'จ่ายเงินยืมราชการ', $by);
        return true;
    }
    /** ล้างหนี้เงินยืม: ใช้จริงน้อยกว่ายอดยืม → คืนส่วนต่างเข้าโครงการนั้น */
    public function clearAdvance(int $id, float $usedAmount, ?int $by=null): bool
    {
        $r=$this->advanceFind($id);
        if(!$r || $r['status']!=='paid') return false;
        $used=max(0,min((float)$r['amount'],$usedAmount));
        $refund=(float)$r['amount']-$used;
        $this->execute("UPDATE cash_advances SET status='cleared', cleared_amount=?, cleared_at=NOW() WHERE id=?", [$used,$id]);
        if($refund>0){
            $this->ledger()->refund('advance',$id,$r['advance_no'],
                $r['budget_id']?(int)$r['budget_id']:null, $r['project_id']?(int)$r['project_id']:null,
                $r['activity_id']?(int)$r['activity_id']:null, $refund, 'คืนเงินยืมเหลือจ่ายเข้าโครงการ', $by);
            // สร้างบันทึกข้อความขอคืนเงินอัตโนมัติ (ฝ่ายการเงิน/งบประมาณดู-แก้ต่อได้)
            (new AdvanceRefundMemo())->createFromAdvance($r, $used, $refund, $by);
        }
        return true;
    }
    public function advanceDelete(int $id): bool
    {
        $r=$this->advanceFind($id);
        if(!$r || in_array($r['status'],['paid','cleared'],true)) return false;
        $this->execute("DELETE FROM approval_logs WHERE ref_type='advance' AND ref_id=?", [$id]);
        $this->execute("DELETE FROM cash_advances WHERE id=?", [$id]);
        return true;
    }

    private ?BudgetLedger $_ledger=null;
    private function ledger(): BudgetLedger
    { return $this->_ledger ??= new BudgetLedger(); }

    // ---------- CRUD โครงการ ----------
    public function projectCreate(array $d): int
    {
        $this->execute("INSERT INTO projects (school_id, budget_id, department_id, subject_group_id, code, name, responsible_id,
            budget_amount, start_date, end_date, status, progress_percent)
            VALUES (1,?,?,?,?,?,?,?,?,?,?,?)",
            [$d['budget_id']?:null,$d['department_id']?:null,$d['subject_group_id']?:null,$d['code']?:null,$d['name'],$d['responsible_id']?:null,
             $d['budget_amount']?:0,$d['start_date']?:null,$d['end_date']?:null,$d['status'],(int)($d['progress_percent']??0)]);
        return $this->lastId();
    }
    public function projectUpdate(int $id, array $d): void
    {
        $this->execute("UPDATE projects SET budget_id=?, department_id=?, subject_group_id=?, code=?, name=?, responsible_id=?,
            budget_amount=?, start_date=?, end_date=?, status=?, progress_percent=? WHERE id=?",
            [$d['budget_id']?:null,$d['department_id']?:null,$d['subject_group_id']?:null,$d['code']?:null,$d['name'],$d['responsible_id']?:null,
             $d['budget_amount']?:0,$d['start_date']?:null,$d['end_date']?:null,$d['status'],(int)($d['progress_percent']??0),$id]);
    }
    /** ลบโครงการ — ห้ามลบถ้ามีการใช้เงินไปแล้ว */
    public function projectDelete(int $id): bool
    {
        $spent=(float)($this->first("SELECT spent_amount FROM projects WHERE id=?", [$id])['spent_amount']??0);
        $led=(int)($this->first("SELECT COUNT(*) c FROM budget_ledger WHERE project_id=?", [$id])['c']??0);
        if($spent>0 || $led>0) return false;
        $this->execute("DELETE FROM project_activities WHERE project_id=?", [$id]);
        $this->execute("DELETE FROM projects WHERE id=?", [$id]);
        return true;
    }
    /** อนุมัติ/ไม่อนุมัติโครงการ (กฎ: ต้องอนุมัติก่อนจึงเบิกได้) */
    public function approveProject(int $id, bool $ok, ?string $reason, ?int $by): void
    {
        if ($ok) {
            $this->execute("UPDATE projects SET approval_status='approved', approved_by=?, approved_at=NOW(), reject_reason=NULL WHERE id=?", [$by, $id]);
        } else {
            $this->execute("UPDATE projects SET approval_status='rejected', reject_reason=? WHERE id=?", [$reason, $id]);
        }
    }

    public function projectProgress(int $id, int $percent, ?string $status=null): void
    {
        $percent=max(0,min(100,$percent));
        if($status && in_array($status,['planned','ongoing','completed','cancelled'],true)){
            $this->execute("UPDATE projects SET progress_percent=?, status=? WHERE id=?", [$percent,$status,$id]);
        } else {
            $this->execute("UPDATE projects SET progress_percent=? WHERE id=?", [$percent,$id]);
        }
    }
    public function departments(): array { return $this->query("SELECT id, name FROM org_departments ORDER BY id"); }
    public function subjectGroups(): array { return $this->query("SELECT id, name FROM subject_groups ORDER BY id"); }
    public function budgetsList(): array { return $this->query("SELECT id, name, allocated_amount, used_amount FROM budgets ORDER BY id"); }
    public function personnelList(): array
    { return $this->query("SELECT id, CONCAT(prefix,first_name,' ',last_name) AS name FROM personnel WHERE deleted_at IS NULL AND status='active' ORDER BY first_name"); }

    // ---------- ติดตามโครงการ (Tracking) ----------
    /**
     * รายการโครงการพร้อมสถานะสุขภาพ:
     *  - time_percent: เวลาที่ผ่านไปตามแผน
     *  - use_percent : สัดส่วนงบที่ใช้
     *  - health      : on_track | delayed | overspend | overdue | done
     */
    public function tracking(array $f=[]): array
    {
        $w=[]; $p=[];
        if(!empty($f['status'])){ $w[]='p.status=?'; $p[]=$f['status']; }
        if(!empty($f['dept'])){   $w[]='p.department_id=?'; $p[]=$f['dept']; }
        if(!empty($f['group'])){  $w[]='p.subject_group_id=?'; $p[]=$f['group']; }
        if(!empty($f['q'])){      $w[]='(p.name LIKE ? OR p.code LIKE ?)'; $p[]='%'.$f['q'].'%'; $p[]='%'.$f['q'].'%'; }
        $where=$w?('WHERE '.implode(' AND ',$w)):'';
        $rows=$this->query("SELECT p.*, b.name AS budget_name, d.name AS dept_name, sg.name AS group_name,
            CONCAT(pe.prefix,pe.first_name,' ',pe.last_name) AS responsible,
            (SELECT COUNT(*) FROM project_activities a WHERE a.project_id=p.id) AS act_total,
            (SELECT COUNT(*) FROM project_activities a WHERE a.project_id=p.id AND a.status='completed') AS act_done
            FROM projects p
            LEFT JOIN budgets b ON b.id=p.budget_id
            LEFT JOIN org_departments d ON d.id=p.department_id
            LEFT JOIN subject_groups sg ON sg.id=p.subject_group_id
            LEFT JOIN personnel pe ON pe.id=p.responsible_id
            $where ORDER BY FIELD(p.status,'ongoing','planned','completed','cancelled'), p.end_date IS NULL, p.end_date", $p);
        foreach($rows as &$r) $r=$this->addHealth($r);
        return $rows;
    }

    private function addHealth(array $r): array
    {
        $budget=(float)$r['budget_amount']; $spent=(float)$r['spent_amount'];
        $r['use_percent'] = $budget>0 ? round($spent/$budget*100,1) : 0.0;
        $r['remaining']   = $budget-$spent;
        $prog=(int)$r['progress_percent'];

        $tp=null;
        if(!empty($r['start_date']) && !empty($r['end_date'])){
            $s=strtotime($r['start_date']); $e=strtotime($r['end_date']); $n=time();
            if($e>$s) $tp = round(max(0,min(100,($n-$s)/($e-$s)*100)),1);
        }
        $r['time_percent']=$tp;

        $health='on_track'; $note='';
        if($r['status']==='completed'){ $health='done'; $note='เสร็จสิ้น'; }
        elseif($r['status']==='cancelled'){ $health='cancelled'; $note='ยกเลิก'; }
        else {
            $overdue = !empty($r['end_date']) && strtotime($r['end_date'])<time() && $prog<100;
            if($overdue){ $health='overdue'; $note='เลยกำหนดสิ้นสุดแล้ว ยังไม่เสร็จ'; }
            elseif($budget>0 && $r['use_percent'] > $prog + 25){ $health='overspend'; $note='ใช้งบเร็วกว่าความคืบหน้ามาก'; }
            elseif($tp!==null && $tp > $prog + 20){ $health='delayed'; $note='ความคืบหน้าช้ากว่าแผนเวลา'; }
            else { $note='เป็นไปตามแผน'; }
        }
        $r['health']=$health; $r['health_note']=$note;
        return $r;
    }

    /** สรุปภาพรวมการติดตาม */
    public function trackingSummary(array $rows): array
    {
        $s=['total'=>count($rows),'ongoing'=>0,'done'=>0,'delayed'=>0,'overdue'=>0,'overspend'=>0,
            'budget'=>0.0,'spent'=>0.0];
        foreach($rows as $r){
            $s['budget']+=(float)$r['budget_amount'];
            $s['spent'] +=(float)$r['spent_amount'];
            if($r['status']==='ongoing') $s['ongoing']++;
            if($r['health']==='done') $s['done']++;
            if($r['health']==='delayed') $s['delayed']++;
            if($r['health']==='overdue') $s['overdue']++;
            if($r['health']==='overspend') $s['overspend']++;
        }
        $s['use_percent']=$s['budget']>0?round($s['spent']/$s['budget']*100,1):0;
        return $s;
    }

    // ---------- รายงานผู้บริหาร Real-time ----------
    public function execReport(): array
    {
        $one=fn($sql,$p=[])=>$this->first($sql,$p)??[];
        return [
            'projects_total'=>(int)($one("SELECT COUNT(*) c FROM projects")['c']??0),
            'projects_ongoing'=>(int)($one("SELECT COUNT(*) c FROM projects WHERE status='ongoing'")['c']??0),
            'budget_alloc'=>(float)($one("SELECT COALESCE(SUM(allocated_amount),0) c FROM budgets")['c']??0),
            'budget_used'=>(float)($one("SELECT COALESCE(SUM(used_amount),0) c FROM budgets")['c']??0),
            'req_pending'=>(int)($one("SELECT COUNT(*) c FROM budget_requests WHERE status='pending'")['c']??0),
            'req_paid_month'=>(float)($one("SELECT COALESCE(SUM(amount),0) c FROM budget_requests WHERE status='paid' AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())")['c']??0),
            'adv_outstanding'=>(float)($one("SELECT COALESCE(SUM(amount),0) c FROM cash_advances WHERE status='paid'")['c']??0),
            'adv_overdue'=>(int)($one("SELECT COUNT(*) c FROM cash_advances WHERE status='paid' AND due_date IS NOT NULL AND due_date<CURDATE()")['c']??0),
            'pr_pending'=>(int)($one("SELECT COUNT(*) c FROM purchase_requests WHERE status='pending'")['c']??0),
            'projects'=>$this->query("SELECT p.name, p.budget_amount, p.spent_amount, p.status FROM projects p ORDER BY p.spent_amount DESC LIMIT 8"),
            'pendings'=>$this->query("SELECT 'เบิกงบ' AS type, request_no AS no, amount, current_level, created_at FROM budget_requests WHERE status='pending'
                UNION ALL SELECT 'ยืมเงิน', advance_no, amount, current_level, created_at FROM cash_advances WHERE status='pending'
                ORDER BY created_at DESC LIMIT 10"),
            'monthly'=>$this->query("SELECT DATE_FORMAT(paid_at,'%Y-%m') ym, SUM(amount) total FROM budget_requests WHERE status='paid' AND paid_at IS NOT NULL GROUP BY ym ORDER BY ym DESC LIMIT 6"),
            'ts'=>date('H:i:s'),
        ];
    }
}
