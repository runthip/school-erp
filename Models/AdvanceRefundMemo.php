<?php
namespace App\Models;
use App\Core\Model;

/**
 * บันทึกข้อความขอคืนเงินตามสัญญายืมเงินราชการ
 * สร้างอัตโนมัติเมื่อล้างหนี้เงินยืมแล้วมีเงินคืน (ยืม − ใช้จริง > 0)
 * เรียกดู/แก้ไขได้เฉพาะฝ่ายการเงิน/งบประมาณ (สิทธิ์ budget.refund_memo)
 */
class AdvanceRefundMemo extends Model
{
    protected string $table = 'advance_refund_memos';

    public const METHODS = ['cash'=>'เงินสด', 'transfer'=>'โอนเข้าบัญชี'];

    private function nextNo(int $yearBe): string
    {
        $r = $this->first("SELECT memo_no FROM advance_refund_memos WHERE memo_no LIKE ? ORDER BY id DESC LIMIT 1", ["ขค-$yearBe-%"]);
        $n = $r ? ((int)substr($r['memo_no'], -4)) + 1 : 1;
        return sprintf('ขค-%d-%04d', $yearBe, $n);
    }

    /**
     * สร้างบันทึกขอคืนเงินจากรายการยืมที่ล้างหนี้แล้ว (idempotent ด้วย unique cash_advance_id)
     * คืน id ของบันทึก (ถ้ามีอยู่แล้วคืน id เดิม)
     */
    public function createFromAdvance(array $adv, float $used, float $refund, ?int $by): int
    {
        $exist = $this->first("SELECT id FROM advance_refund_memos WHERE cash_advance_id=?", [(int)$adv['id']]);
        if ($exist) return (int)$exist['id'];

        $yearBe = (int)date('Y') + 543;
        $detail = 'ตามที่ข้าพเจ้าได้ยืมเงินราชการตามสัญญายืมเลขที่ '.($adv['advance_no'] ?? '').
                  ' จำนวน '.number_format((float)$adv['amount'], 2).' บาท และได้ใช้จ่ายจริง '.number_format($used, 2).
                  ' บาท คงเหลือเงินยืมที่ต้องส่งคืน '.number_format($refund, 2).' บาท จึงขอส่งคืนเงินดังกล่าว';
        $this->execute(
            "INSERT INTO advance_refund_memos
                (cash_advance_id, memo_no, memo_date, borrowed_amount, used_amount, refund_amount, detail, created_by)
             VALUES (?,?,?,?,?,?,?,?)",
            [(int)$adv['id'], $this->nextNo($yearBe), date('Y-m-d'),
             (float)$adv['amount'], $used, $refund, $detail, $by]
        );
        return $this->lastId();
    }

    public function find(int $id): ?array
    {
        return $this->first(
            "SELECT m.*, ca.advance_no, ca.purpose, ca.due_date, ca.project_id, ca.activity_id, DATE(ca.created_at) AS advance_date,
                    pr.name AS project_name, ac.name AS activity_name,
                    COALESCE(NULLIF(CONCAT(bp.prefix,bp.first_name,' ',bp.last_name),'  '), bu.full_name, bu.username) AS borrower_name,
                    COALESCE(ca.borrower_position, bp.position) AS borrower_position,
                    CONCAT(rp.prefix,rp.first_name,' ',rp.last_name) AS receiver_name, rp.position AS receiver_position
             FROM advance_refund_memos m
             JOIN cash_advances ca ON ca.id=m.cash_advance_id
             LEFT JOIN users bu ON bu.id=ca.borrower_id
             LEFT JOIN personnel bp ON bp.id=bu.linked_id AND bu.linked_type='personnel'
             LEFT JOIN personnel rp ON rp.id=m.receiver_id
             LEFT JOIN projects pr ON pr.id=ca.project_id
             LEFT JOIN project_activities ac ON ac.id=ca.activity_id
             WHERE m.id=?", [$id]);
    }

    public function findByAdvance(int $advanceId): ?array
    {
        $r = $this->first("SELECT id FROM advance_refund_memos WHERE cash_advance_id=?", [$advanceId]);
        return $r ? $this->find((int)$r['id']) : null;
    }

    public function listMemos(): array
    {
        return $this->query(
            "SELECT m.id, m.memo_no, m.memo_date, m.refund_amount, m.status, ca.advance_no,
                    CONCAT(bp.prefix,bp.first_name,' ',bp.last_name) AS borrower_name
             FROM advance_refund_memos m
             JOIN cash_advances ca ON ca.id=m.cash_advance_id
             LEFT JOIN users bu ON bu.id=ca.borrower_id
             LEFT JOIN personnel bp ON bp.id=bu.linked_id AND bu.linked_type='personnel'
             ORDER BY m.id DESC");
    }

    public function update(int $id, array $d, ?int $by): void
    {
        $this->execute(
            "UPDATE advance_refund_memos SET
                memo_no=?, memo_date=?, used_amount=?, refund_amount=?, refund_method=?, fund_type=?,
                detail=?, operate_period=?, receiver_id=?, status=?, updated_by=?
             WHERE id=?",
            [$d['memo_no'] ?: null, $d['memo_date'] ?: null,
             (float)($d['used_amount'] ?? 0), (float)($d['refund_amount'] ?? 0),
             in_array($d['refund_method'] ?? '', ['cash','transfer'], true) ? $d['refund_method'] : 'cash',
             in_array($d['fund_type'] ?? '', ['petty','budget'], true) ? $d['fund_type'] : 'budget',
             $d['detail'] ?: null, $d['operate_period'] ?: null, $d['receiver_id'] ?: null,
             in_array($d['status'] ?? '', ['draft','confirmed'], true) ? $d['status'] : 'draft',
             $by, $id]
        );
    }

    /** รายชื่อบุคลากรฝ่ายการเงิน/ธุรการ สำหรับเลือกผู้รับเงินคืน */
    public function financeStaff(): array
    {
        return $this->query("SELECT id, CONCAT(prefix,first_name,' ',last_name) AS name, position,
                   (SELECT u.signature_path FROM users u WHERE u.linked_type='personnel' AND u.linked_id=personnel.id AND u.deleted_at IS NULL LIMIT 1) AS signature
            FROM personnel WHERE deleted_at IS NULL AND status='active' ORDER BY first_name");
    }

    /** ผู้ลงนามตามระบบ: เจ้าหน้าที่การเงิน + ผู้อำนวยการ */
    public function signers(): array
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

    public const FUND_TYPES = ['petty'=>'ทดรองราชการ', 'budget'=>'เงินงบประมาณ'];
}
