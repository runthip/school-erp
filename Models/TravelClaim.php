<?php
namespace App\Models;
use App\Core\Model;

/**
 * ใบเบิกค่าใช้จ่ายในการเดินทางไปราชการ (แบบ 8708)
 * ครูกรอกเอง ลงตำแหน่งตามการกรอก · เก็บข้อมูล · รวบรวมสถิติ/ยอดรวม (สิทธิ์ travel.claim)
 */
class TravelClaim extends Model
{
    protected string $table = 'travel_claims';

    public const PLACES = ['home'=>'บ้านพัก','office'=>'สำนักงาน','thailand'=>'ประเทศไทย'];
    public const STATUS = [
        'draft'    =>['ร่าง','bg-slate-100 text-slate-600'],
        'submitted'=>['ยื่นเบิก','bg-amber-100 text-amber-700'],
        'approved' =>['อนุมัติแล้ว','bg-emerald-100 text-emerald-700'],
        'paid'     =>['จ่ายเงินแล้ว','bg-blue-100 text-blue-700'],
    ];

    /** เลขที่เอกสารถัดไป: คด-{พ.ศ.}-0001 */
    private function nextNo(int $yearBe): string
    {
        $r = $this->first("SELECT claim_no FROM travel_claims WHERE claim_no LIKE ? ORDER BY id DESC LIMIT 1", ["คด-$yearBe-%"]);
        $n = $r ? ((int)substr($r['claim_no'], -4)) + 1 : 1;
        return sprintf('คด-%d-%04d', $yearBe, $n);
    }

    /** รวมเงินทั้งสิ้นจาก 4 หมวด */
    public static function computeTotal(array $d): float
    {
        return round((float)($d['perdiem_amount']??0) + (float)($d['lodging_amount']??0)
                   + (float)($d['transport_amount']??0) + (float)($d['other_amount']??0), 2);
    }

    public function create(array $d, ?int $by): int
    {
        $yearBe = (int)date('Y') + 543;
        $d['claim_no']     = $d['claim_no'] ?: $this->nextNo($yearBe);
        $d['total_amount'] = self::computeTotal($d);
        $cols = $this->fields();
        $set  = implode(',', $cols);
        $ph   = implode(',', array_fill(0, count($cols), '?'));
        $vals = array_map(fn($c)=>$d[$c] ?? null, $cols);
        $vals[] = $by;
        $this->execute("INSERT INTO travel_claims ($set, created_by) VALUES ($ph, ?)", $vals);
        return $this->lastId();
    }

    public function update(int $id, array $d, ?int $by): void
    {
        $d['total_amount'] = self::computeTotal($d);
        $cols = $this->fields();
        $set  = implode(',', array_map(fn($c)=>"$c=?", $cols));
        $vals = array_map(fn($c)=>$d[$c] ?? null, $cols);
        $vals[] = $by; $vals[] = $id;
        $this->execute("UPDATE travel_claims SET $set, updated_by=? WHERE id=?", $vals);
    }

    /** คอลัมน์ที่รับค่าจากฟอร์ม */
    private function fields(): array
    {
        return [
            'claim_no','claim_date','office_place','addressee','order_no','order_date',
            'traveler_id','traveler_name','traveler_position','affiliation','companions','purpose',
            'depart_from','depart_at','return_to','return_at','total_days','total_hours',
            'perdiem_type','perdiem_days','perdiem_amount','lodging_type','lodging_days','lodging_amount',
            'transport_detail','transport_amount','other_detail','other_amount','total_amount',
            'receipts_count','loan_no','loan_date','loan_amount','note','status','approver_id',
        ];
    }

    public function delete(int $id): void
    { $this->execute("DELETE FROM travel_claims WHERE id=?", [$id]); }

    public function find(int $id): ?array
    {
        return $this->first(
            "SELECT t.*,
                    CONCAT(ap.prefix,ap.first_name,' ',ap.last_name) AS approver_name, ap.position AS approver_position,
                    (SELECT u.signature_path FROM users u WHERE u.linked_type='personnel' AND u.linked_id=t.traveler_id AND u.deleted_at IS NULL LIMIT 1) AS traveler_signature,
                    (SELECT u.signature_path FROM users u WHERE u.linked_type='personnel' AND u.linked_id=t.approver_id AND u.deleted_at IS NULL LIMIT 1) AS approver_signature
             FROM travel_claims t
             LEFT JOIN personnel ap ON ap.id=t.approver_id
             WHERE t.id=?", [$id]);
    }

    /** รายการ + ตัวกรอง (traveler_id, status, month=YYYY-MM) */
    public function listClaims(array $f=[]): array
    {
        $w=['1=1']; $p=[];
        if(!empty($f['traveler_id'])){ $w[]='t.traveler_id=?'; $p[]=(int)$f['traveler_id']; }
        if(!empty($f['status'])){ $w[]='t.status=?'; $p[]=$f['status']; }
        if(!empty($f['month'])){ $w[]="DATE_FORMAT(t.claim_date,'%Y-%m')=?"; $p[]=$f['month']; }
        $sql="SELECT t.id,t.claim_no,t.claim_date,t.traveler_name,t.traveler_position,t.purpose,
                     t.total_amount,t.status,t.created_at
              FROM travel_claims t WHERE ".implode(' AND ',$w)."
              ORDER BY t.claim_date DESC, t.id DESC";
        return $this->query($sql,$p);
    }

    /** สถิติสรุป (ตามตัวกรองเดียวกับรายการ) */
    public function stats(array $f=[]): array
    {
        $w=['1=1']; $p=[];
        if(!empty($f['traveler_id'])){ $w[]='traveler_id=?'; $p[]=(int)$f['traveler_id']; }
        if(!empty($f['status'])){ $w[]='status=?'; $p[]=$f['status']; }
        if(!empty($f['month'])){ $w[]="DATE_FORMAT(claim_date,'%Y-%m')=?"; $p[]=$f['month']; }
        $where=implode(' AND ',$w);
        $agg=$this->first("SELECT COUNT(*) n, COALESCE(SUM(total_amount),0) sum_total,
                COALESCE(SUM(perdiem_amount),0) sum_perdiem, COALESCE(SUM(lodging_amount),0) sum_lodging,
                COALESCE(SUM(transport_amount),0) sum_transport, COALESCE(SUM(other_amount),0) sum_other,
                COALESCE(SUM(CASE WHEN status='approved' OR status='paid' THEN total_amount ELSE 0 END),0) sum_approved
              FROM travel_claims WHERE $where",$p) ?: [];
        $byTraveler=$this->query("SELECT traveler_name, COUNT(*) n, COALESCE(SUM(total_amount),0) total
              FROM travel_claims WHERE $where GROUP BY traveler_name ORDER BY total DESC",$p);
        $byMonth=$this->query("SELECT DATE_FORMAT(claim_date,'%Y-%m') ym, COUNT(*) n, COALESCE(SUM(total_amount),0) total
              FROM travel_claims WHERE $where AND claim_date IS NOT NULL GROUP BY ym ORDER BY ym DESC LIMIT 12",$p);
        return ['agg'=>$agg,'byTraveler'=>$byTraveler,'byMonth'=>$byMonth];
    }

    /** ตัวเลือกผู้เดินทาง (บุคลากร active) */
    public function teachers(): array
    {
        return $this->query("SELECT p.id, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS name, p.position
            FROM personnel p WHERE p.deleted_at IS NULL AND p.status='active'
            ORDER BY p.first_name");
    }

    /** ตัวเลือกผู้อนุมัติ (ผอ./รอง/หัวหน้า/การเงิน) */
    public function approvers(): array
    {
        return $this->query("SELECT DISTINCT p.id, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS name, p.position
            FROM personnel p
            JOIN users u ON u.linked_type='personnel' AND u.linked_id=p.id AND u.deleted_at IS NULL
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id
            WHERE p.deleted_at IS NULL AND p.status='active'
              AND r.code IN ('director','deputy_director','head_academic','head_budget','head_hr','head_general','finance_officer')
            ORDER BY p.first_name");
    }

    /** ระบุตัวผู้ใช้ปัจจุบัน (เติมชื่อ/ตำแหน่งผู้เดินทางอัตโนมัติ) */
    public function identity(int $userId): array
    {
        $r=$this->first("SELECT p.id AS personnel_id,
                COALESCE(NULLIF(TRIM(CONCAT(p.prefix,p.first_name,' ',p.last_name)),''), u.full_name) AS name,
                COALESCE(NULLIF(p.position,''),'') AS position, p.department_id
            FROM users u LEFT JOIN personnel p ON p.id=u.linked_id AND u.linked_type='personnel'
            WHERE u.id=?", [$userId]);
        return $r ?: ['personnel_id'=>null,'name'=>'','position'=>'','department_id'=>null];
    }
}
