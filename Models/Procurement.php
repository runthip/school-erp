<?php
namespace App\Models;
use App\Core\Model;

/** จัดซื้อจัดจ้างตามระเบียบกระทรวงการคลังฯ พ.ศ. 2560 */
class Procurement extends Model
{
    protected string $table = 'purchase_requests';

    /** เพดานวิธีเฉพาะเจาะจง ตามกฎกระทรวง (วงเงินไม่เกิน 500,000 บาท) */
    public const SPECIFIC_LIMIT = 500000;

    public static function methodLabel(): array
    {
        return ['specific'=>'เฉพาะเจาะจง','selection'=>'คัดเลือก','e_bidding'=>'ประกาศเชิญชวนทั่วไป (e-bidding)'];
    }
    /** แนะนำวิธีจัดหาตามวงเงิน (ระเบียบ 2560) */
    public static function suggestMethod(float $amount): string
    { return $amount <= self::SPECIFIC_LIMIT ? 'specific' : 'e_bidding'; }

    // ---------- PR ----------
    public function prList(string $status='', string $type=''): array
    {
        $w=['1=1']; $p=[];
        if($status!==''){ $w[]='pr.status=?'; $p[]=$status; }
        if($type!==''){ $w[]='pr.request_type=?'; $p[]=$type; }
        $ws='WHERE '.implode(' AND ',$w);
        return $this->query("SELECT pr.*, b.name AS budget_name, CONCAT(pe.first_name,' ',pe.last_name) AS requester_name,
            (SELECT COUNT(*) FROM purchase_request_items i WHERE i.purchase_request_id=pr.id) AS item_count
            FROM purchase_requests pr
            LEFT JOIN budgets b ON b.id=pr.budget_id
            LEFT JOIN personnel pe ON pe.id=pr.requester_id
            $ws ORDER BY pr.id DESC", $p);
    }
    public function prFind(int $id): ?array
    {
        return $this->first("SELECT pr.*, b.name AS budget_name, b.allocated_amount, b.used_amount,
            CONCAT(pe.first_name,' ',pe.last_name) AS requester_name, u.full_name AS approver_name
            FROM purchase_requests pr
            LEFT JOIN budgets b ON b.id=pr.budget_id
            LEFT JOIN personnel pe ON pe.id=pr.requester_id
            LEFT JOIN users u ON u.id=pr.approved_by
            WHERE pr.id=?", [$id]);
    }
    public function prItems(int $id): array
    { return $this->query("SELECT * FROM purchase_request_items WHERE purchase_request_id=? ORDER BY id", [$id]); }

    public function nextNumber(string $table, string $col, string $prefix): string
    {
        $r=$this->first("SELECT COUNT(*) c FROM $table WHERE $col LIKE ?", [$prefix.'%']);
        return $prefix . str_pad((string)(((int)($r['c']??0))+1), 3, '0', STR_PAD_LEFT);
    }

    public function activitiesAll(): array
    { return $this->query("SELECT id, project_id, name, budget_amount, spent_amount,
        (budget_amount - spent_amount) AS remaining FROM project_activities ORDER BY project_id, id"); }

    /** งบคงเหลือของกิจกรรม/โครงการ (กฎเดียวกับ งป.01 — ห้ามเบิกเกิน) */
    public function availableBudget(int $projectId, int $activityId): ?float
    {
        if ($activityId) {
            $r = $this->first("SELECT (budget_amount - spent_amount) rem FROM project_activities WHERE id=?", [$activityId]);
            return $r ? (float)$r['rem'] : null;
        }
        if ($projectId) {
            $r = $this->first("SELECT (budget_amount - spent_amount) rem FROM projects WHERE id=?", [$projectId]);
            return $r ? (float)$r['rem'] : null;
        }
        return null;
    }

    public function prCreate(array $d, array $items): int
    {
        $no=$this->nextNumber('purchase_requests','pr_number','PR-'.(date('Y')+543).'-');
        $this->execute("INSERT INTO purchase_requests (school_id, pr_number, budget_id, project_id, activity_id, request_type, method, requester_id, request_date, total_amount, reason, status)
            VALUES (1,?,?,?,?,?,?,?,CURDATE(),?,?, 'pending')",
            [$no,$d['budget_id']?:null,$d['project_id']?:null,$d['activity_id']?:null,$d['request_type'],$d['method'],$d['requester_id']?:null,$d['total'],$d['reason']]);
        $prId=$this->lastId();
        $ins=null;
        foreach($items as $it){
            $this->execute("INSERT INTO purchase_request_items (purchase_request_id, item_name, quantity, unit, unit_price, amount) VALUES (?,?,?,?,?,?)",
                [$prId,$it['name'],$it['qty'],$it['unit'],$it['price'],$it['amount']]);
        }
        return $prId;
    }
    public function prDecide(int $id, string $status, ?int $by, ?string $note): void
    {
        $this->execute("UPDATE purchase_requests SET status=?, approved_by=?, approved_at=NOW(), decision_note=? WHERE id=?",
            [$status,$by,$note,$id]);
    }
    public function prMarkPoCreated(int $id): void
    { $this->execute("UPDATE purchase_requests SET status='po_created' WHERE id=?", [$id]); }

    // ---------- PO ----------
    public function poList(string $status=''): array
    {
        $w=''; $p=[];
        if($status!==''){ $w='WHERE po.status=?'; $p[]=$status; }
        return $this->query("SELECT po.*, v.name AS vendor, pr.pr_number
            FROM purchase_orders po
            LEFT JOIN vendors v ON v.id=po.vendor_id
            LEFT JOIN purchase_requests pr ON pr.id=po.purchase_request_id
            $w ORDER BY po.id DESC", $p);
    }
    public function poFind(int $id): ?array
    {
        return $this->first("SELECT po.*, v.name AS vendor, v.tax_id AS vendor_tax, v.address AS vendor_address, v.phone AS vendor_phone,
            pr.pr_number, pr.budget_id, pr.project_id, pr.activity_id, pr.reason, pr.request_type
            FROM purchase_orders po
            LEFT JOIN vendors v ON v.id=po.vendor_id
            LEFT JOIN purchase_requests pr ON pr.id=po.purchase_request_id
            WHERE po.id=?", [$id]);
    }
    public function poItems(int $id): array
    { return $this->query("SELECT * FROM purchase_order_items WHERE purchase_order_id=? ORDER BY id", [$id]); }

    /** ข้อมูลโรงเรียน (แถวเต็ม สำหรับหัวใบสั่งซื้อ) */
    public function schoolInfo(): array
    { return $this->first("SELECT * FROM schools ORDER BY id LIMIT 1") ?: []; }

    /** ผู้อำนวยการ (ผู้ลงนามผู้ซื้อ) จากข้อมูลบุคลากรจริง */
    public function director(): ?array
    {
        return $this->first("SELECT CONCAT(prefix,first_name,' ',last_name) AS name, position,
                   (SELECT u.signature_path FROM users u WHERE u.linked_type='personnel' AND u.linked_id=personnel.id AND u.deleted_at IS NULL LIMIT 1) AS signature
            FROM personnel WHERE deleted_at IS NULL AND status='active'
              AND position LIKE '%ผู้อำนวยการ%' AND position NOT LIKE '%รอง%' ORDER BY id LIMIT 1");
    }

    /** เจ้าหน้าที่การเงิน (ผู้เสนอความเห็นเบิกจ่าย) */
    public function financeOfficer(): ?array
    {
        return $this->first("SELECT CONCAT(prefix,first_name,' ',last_name) AS name, position,
                   (SELECT u.signature_path FROM users u WHERE u.linked_type='personnel' AND u.linked_id=personnel.id AND u.deleted_at IS NULL LIMIT 1) AS signature
            FROM personnel WHERE deleted_at IS NULL AND status='active'
              AND (position LIKE '%การเงิน%' OR position LIKE '%งบประมาณ%') ORDER BY id LIMIT 1");
    }

    /** หัวหน้าเจ้าหน้าที่พัสดุ (ผู้ลงนามในเอกสารพัสดุ) */
    public function supplyHead(): ?array
    {
        return $this->first("SELECT CONCAT(prefix,first_name,' ',last_name) AS name, position,
                   (SELECT u.signature_path FROM users u WHERE u.linked_type='personnel' AND u.linked_id=personnel.id AND u.deleted_at IS NULL LIMIT 1) AS signature
            FROM personnel WHERE deleted_at IS NULL AND status='active'
              AND position LIKE '%พัสดุ%' ORDER BY id LIMIT 1");
    }

    public function poCreateFromPr(int $prId, int $vendorId, float $vat): int
    {
        $pr=$this->prFind($prId);
        $no=$this->nextNumber('purchase_orders','po_number','PO-'.(date('Y')+543).'-');
        $this->execute("INSERT INTO purchase_orders (school_id, po_number, purchase_request_id, vendor_id, order_date, total_amount, vat_amount, status)
            VALUES (1,?,?,?,CURDATE(),?,?, 'open')", [$no,$prId,$vendorId,(float)$pr['total_amount'],$vat]);
        $poId=$this->lastId();
        foreach($this->prItems($prId) as $it){
            $this->execute("INSERT INTO purchase_order_items (purchase_order_id, item_name, quantity, unit, unit_price, amount) VALUES (?,?,?,?,?,?)",
                [$poId,$it['item_name'],$it['quantity'],$it['unit'],$it['unit_price'],$it['amount']]);
        }
        $this->prMarkPoCreated($prId);
        return $poId;
    }
    /** ตรวจรับพัสดุโดยคณะกรรมการ */
    public function poReceive(int $id, string $committee, string $date, ?string $note): void
    {
        $this->execute("UPDATE purchase_orders SET status='received', committee=?, received_date=?, receive_note=? WHERE id=? AND status='open'",
            [$committee,$date,$note,$id]);
    }
    /** เบิกจ่าย + ตัดยอดงบประมาณ */
    /** จ่ายเงิน PO: ตัดงบผ่านบัญชีคุมงบกลาง (BudgetLedger) — จุดเดียวกับใบเบิก/เงินยืม */
    public function poPay(int $id, ?int $by=null): bool
    {
        $po=$this->poFind($id);
        if(!$po || $po['status']!=='received') return false;
        $this->execute("UPDATE purchase_orders SET status='paid', paid_date=CURDATE() WHERE id=?", [$id]);
        $this->ledger()->deduct('po',$id,$po['po_number'],
            !empty($po['budget_id'])?(int)$po['budget_id']:null,
            !empty($po['project_id'])?(int)$po['project_id']:null,
            !empty($po['activity_id'])?(int)$po['activity_id']:null,
            (float)$po['total_amount'], 'จ่ายเงินใบสั่งซื้อ/จ้าง', $by);
        return true;
    }
    /** ยกเลิก PO — ถ้าจ่ายเงินแล้วให้คืนยอดเข้าโครงการนั้นอัตโนมัติ */
    public function poCancel(int $id, ?string $reason=null, ?int $by=null): bool
    {
        $po=$this->poFind($id);
        if(!$po || $po['status']==='cancelled') return false;
        if($po['status']==='paid'){
            $net=$this->ledger()->netOf('po',$id);
            if($net>0){
                $this->ledger()->refund('po',$id,$po['po_number'],
                    !empty($po['budget_id'])?(int)$po['budget_id']:null,
                    !empty($po['project_id'])?(int)$po['project_id']:null,
                    !empty($po['activity_id'])?(int)$po['activity_id']:null,
                    $net, 'ยกเลิกใบสั่งซื้อ — คืนเงินเข้าโครงการ'.($reason?(': '.$reason):''), $by);
            }
        }
        $this->execute("UPDATE purchase_orders SET status='cancelled' WHERE id=?", [$id]);
        return true;
    }
    /** คืนเงินบางส่วนของ PO ที่จ่ายแล้ว (เช่น ส่งของไม่ครบ/ลดราคา) */
    public function poRefund(int $id, float $amount, ?string $reason=null, ?int $by=null): bool
    {
        $po=$this->poFind($id);
        if(!$po || $po['status']!=='paid') return false;
        $net=$this->ledger()->netOf('po',$id);
        $amount=min(round(abs($amount),2), $net);
        if($amount<=0) return false;
        $this->ledger()->refund('po',$id,$po['po_number'],
            !empty($po['budget_id'])?(int)$po['budget_id']:null,
            !empty($po['project_id'])?(int)$po['project_id']:null,
            !empty($po['activity_id'])?(int)$po['activity_id']:null,
            $amount, 'คืนเงิน PO'.($reason?(': '.$reason):''), $by);
        return true;
    }

    private ?BudgetLedger $_ledger=null;
    private function ledger(): BudgetLedger
    { return $this->_ledger ??= new BudgetLedger(); }

    // ---------- ตัวเลือก ----------
    public function budgetsList(): array { return $this->query("SELECT id, name, allocated_amount, used_amount FROM budgets ORDER BY id"); }
    public function projectsList(): array { return $this->query("SELECT id, name, budget_amount, spent_amount,
        (budget_amount - spent_amount) AS remaining FROM projects ORDER BY id"); }
    public function personnelList(): array { return $this->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM personnel WHERE deleted_at IS NULL ORDER BY first_name"); }
    public function vendorsList(): array { return $this->query("SELECT * FROM vendors WHERE is_active=1 ORDER BY name"); }
    public function approvedPrs(): array
    { return $this->query("SELECT id, pr_number, total_amount FROM purchase_requests WHERE status='approved' ORDER BY id DESC"); }

    // ---------- Dashboard งบประมาณ ----------
    /** ยอดเบิกจ่ายรวมตามฝ่าย (จากโครงการที่ผูกฝ่าย) */
    public function spentByDepartment(): array
    {
        return $this->query("SELECT od.name AS dept, COALESCE(SUM(p.spent_amount),0) AS spent, COALESCE(SUM(p.budget_amount),0) AS planned
            FROM org_departments od
            LEFT JOIN projects p ON p.department_id=od.id
            GROUP BY od.id, od.name
            HAVING spent > 0 OR planned > 0
            ORDER BY spent DESC");
    }
    /** ยอดเบิกจ่ายรายโครงการ */
    public function spentByProject(): array
    {
        return $this->query("SELECT p.code, p.name, p.budget_amount, p.spent_amount, od.name AS dept,
            CASE WHEN p.budget_amount>0 THEN ROUND(p.spent_amount/p.budget_amount*100,1) ELSE 0 END AS pct
            FROM projects p LEFT JOIN org_departments od ON od.id=p.department_id
            ORDER BY p.spent_amount DESC");
    }
    /** ยอดเบิกจ่ายรายเดือน (6 เดือนล่าสุด) */
    public function paidMonthly(): array
    {
        return $this->query("SELECT DATE_FORMAT(paid_date,'%Y-%m') AS ym, SUM(total_amount) AS amount
            FROM purchase_orders WHERE status='paid' AND paid_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY ym ORDER BY ym");
    }
    /** รายการเบิกจ่ายล่าสุด */
    public function recentPaid(int $limit=8): array
    {
        return $this->query("SELECT po.po_number, po.total_amount, po.paid_date, v.name AS vendor,
            pj.name AS project, od.name AS dept
            FROM purchase_orders po
            LEFT JOIN vendors v ON v.id=po.vendor_id
            LEFT JOIN purchase_requests pr ON pr.id=po.purchase_request_id
            LEFT JOIN projects pj ON pj.id=pr.project_id
            LEFT JOIN org_departments od ON od.id=pj.department_id
            WHERE po.status='paid' ORDER BY po.paid_date DESC, po.id DESC LIMIT $limit");
    }
    /** สรุปงบรวม */
    public function budgetTotals(): array
    {
        $r=$this->first("SELECT COALESCE(SUM(allocated_amount),0) a, COALESCE(SUM(used_amount),0) u FROM budgets");
        $p=$this->first("SELECT COALESCE(SUM(budget_amount),0) a, COALESCE(SUM(spent_amount),0) u FROM projects");
        return ['alloc'=>(float)$r['a'],'used'=>(float)$r['u'],'proj_budget'=>(float)$p['a'],'proj_spent'=>(float)$p['u']];
    }

    // ---------- สรุปรายงาน (สขร.) ----------
    public function summary(): array
    {
        return [
            'pr_pending'=>(int)($this->first("SELECT COUNT(*) c FROM purchase_requests WHERE status='pending'")['c']??0),
            'pr_approved'=>(int)($this->first("SELECT COUNT(*) c FROM purchase_requests WHERE status='approved'")['c']??0),
            'po_open'=>(int)($this->first("SELECT COUNT(*) c FROM purchase_orders WHERE status='open'")['c']??0),
            'po_paid_amount'=>(float)($this->first("SELECT COALESCE(SUM(total_amount),0) c FROM purchase_orders WHERE status='paid'")['c']??0),
        ];
    }
}
