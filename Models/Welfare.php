<?php
namespace App\Models;
use App\Core\Model;

/**
 * ร้านค้าสวัสดิการ — บัญชีรายรับ-รายจ่าย (พร้อมผู้รับผิดชอบและหมายเหตุ)
 */
class Welfare extends Model
{
    public const CATEGORIES = [
        'sale'     => 'ขายสินค้า/บริการ',
        'purchase' => 'ซื้อสินค้าเข้าร้าน',
        'utility'  => 'ค่าน้ำ/ค่าไฟ/ค่าใช้จ่ายดำเนินงาน',
        'welfare'  => 'สวัสดิการ/ปันผล',
        'other'    => 'อื่น ๆ',
    ];
    public static function categoryName(string $k): string { return self::CATEGORIES[$k] ?? $k; }
    private function cat(string $k): string { return isset(self::CATEGORIES[$k]) ? $k : 'other'; }

    // ================= บัญชีรายรับ-รายจ่าย =================
    public function transactions(array $f = []): array
    {
        $w = []; $p = [];
        if (!empty($f['from'])) { $w[] = 't.tx_date >= ?'; $p[] = $f['from']; }
        if (!empty($f['to']))   { $w[] = 't.tx_date <= ?'; $p[] = $f['to']; }
        if (!empty($f['type'])) { $w[] = 't.tx_type = ?'; $p[] = $f['type']; }
        if (!empty($f['cat']))  { $w[] = 't.category = ?'; $p[] = $f['cat']; }
        if (!empty($f['q']))    { $w[] = '(t.description LIKE ? OR t.note LIKE ? OR t.ref_no LIKE ?)';
                                  $like='%'.$f['q'].'%'; array_push($p,$like,$like,$like); }
        $where = $w ? ('WHERE '.implode(' AND ', $w)) : '';
        return $this->query("SELECT t.*,
                CONCAT(COALESCE(pe.prefix,''),pe.first_name,' ',pe.last_name) AS responsible,
                u.full_name AS creator
            FROM welfare_transactions t
            LEFT JOIN personnel pe ON pe.id=t.responsible_id
            LEFT JOIN users u ON u.id=t.created_by
            $where ORDER BY t.tx_date DESC, t.id DESC LIMIT 500", $p);
    }

    /** ยอดคงเหลือสะสมทั้งหมด (ทุกช่วงเวลา) */
    public function balance(): float
    {
        $r = $this->first("SELECT COALESCE(SUM(CASE WHEN tx_type='income' THEN amount ELSE -amount END),0) bal
            FROM welfare_transactions");
        return (float)($r['bal'] ?? 0);
    }

    /** สรุปช่วงวันที่: รายรับ/รายจ่าย/คงเหลือช่วงนี้ + ยอดยกมา + คงเหลือสะสม */
    public function summary(string $from, string $to): array
    {
        $r = $this->first("SELECT
                COALESCE(SUM(CASE WHEN tx_type='income'  THEN amount ELSE 0 END),0) income,
                COALESCE(SUM(CASE WHEN tx_type='expense' THEN amount ELSE 0 END),0) expense
            FROM welfare_transactions WHERE tx_date BETWEEN ? AND ?", [$from,$to]) ?: [];
        $b = $this->first("SELECT COALESCE(SUM(CASE WHEN tx_type='income' THEN amount ELSE -amount END),0) bf
            FROM welfare_transactions WHERE tx_date < ?", [$from]);
        $income=(float)($r['income']??0); $expense=(float)($r['expense']??0); $bf=(float)($b['bf']??0);
        return ['income'=>$income, 'expense'=>$expense, 'net'=>$income-$expense,
                'brought_forward'=>$bf, 'balance'=>$bf+$income-$expense];
    }

    /** สรุปตามหมวด (สำหรับรายงาน) */
    public function byCategory(string $from, string $to): array
    {
        return $this->query("SELECT tx_type, category, COUNT(*) n, COALESCE(SUM(amount),0) total
            FROM welfare_transactions WHERE tx_date BETWEEN ? AND ?
            GROUP BY tx_type, category ORDER BY tx_type DESC, total DESC", [$from,$to]);
    }

    public function txFind(int $id): ?array
    { return $this->first("SELECT * FROM welfare_transactions WHERE id=?", [$id]); }

    public function txCreate(array $d, ?int $by): int
    {
        $type = in_array($d['tx_type'], ['income','expense'], true) ? $d['tx_type'] : 'income';
        $this->execute("INSERT INTO welfare_transactions
                (tx_date, tx_type, category, description, amount, ref_no, responsible_id, note, created_by)
            VALUES (?,?,?,?,?,?,?,?,?)",
            [$d['tx_date'] ?: date('Y-m-d'), $type, $this->cat((string)$d['category']),
             trim((string)$d['description']), max(0,(float)$d['amount']),
             trim((string)($d['ref_no']??'')) ?: null, ($d['responsible_id']??0) ?: null,
             trim((string)($d['note']??'')) ?: null, $by]);
        return $this->lastId();
    }

    public function txDelete(int $id): void
    { $this->execute("DELETE FROM welfare_transactions WHERE id=?", [$id]); }

    // ================= ตัวเลือก =================
    public function personnel(): array
    {
        return $this->query("SELECT id, CONCAT(COALESCE(prefix,''),first_name,' ',last_name) AS name, position
            FROM personnel WHERE deleted_at IS NULL AND status='active' ORDER BY first_name");
    }
}
