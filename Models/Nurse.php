<?php
namespace App\Models;
use App\Core\Model;

/**
 * งานพยาบาล/ห้องพยาบาล — บันทึกการใช้บริการ + คลังยา/เวชภัณฑ์
 * จ่ายยาในการใช้บริการ → ตัดสต็อกอัตโนมัติ + บันทึกความเคลื่อนไหว (สิทธิ์ general.health)
 */
class Nurse extends Model
{
    public const OUTCOMES = [
        'back_class' => 'กลับเข้าเรียน',
        'rest'       => 'พักที่ห้องพยาบาล',
        'home'       => 'ให้ผู้ปกครองรับกลับบ้าน',
        'refer'      => 'ส่งต่อโรงพยาบาล',
        'other'      => 'อื่น ๆ',
    ];
    public const CATEGORIES = [
        'medicine'  => 'ยา',
        'supply'    => 'เวชภัณฑ์/วัสดุ',
        'equipment' => 'อุปกรณ์',
    ];
    public static function outcomeName(string $k): string { return self::OUTCOMES[$k] ?? $k; }
    public static function categoryName(string $k): string { return self::CATEGORIES[$k] ?? $k; }

    // ================= การใช้บริการ =================
    /** ชื่อผู้ป่วยแบบรวม (นักเรียน/บุคลากร/อื่นๆ) */
    private const PATIENT_SQL = "COALESCE(
            NULLIF(TRIM(CONCAT(COALESCE(s.prefix,''),s.first_name,' ',s.last_name)),''),
            NULLIF(TRIM(CONCAT(COALESCE(pe.prefix,''),pe.first_name,' ',pe.last_name)),''),
            v.patient_name, '-') ";

    public function visits(array $f = []): array
    {
        $w = []; $p = [];
        if (!empty($f['from'])) { $w[] = 'v.visit_at >= ?'; $p[] = $f['from'].' 00:00:00'; }
        if (!empty($f['to']))   { $w[] = 'v.visit_at <= ?'; $p[] = $f['to'].' 23:59:59'; }
        if (!empty($f['type'])) { $w[] = 'v.patient_type = ?'; $p[] = $f['type']; }
        if (!empty($f['outcome'])) { $w[] = 'v.outcome = ?'; $p[] = $f['outcome']; }
        if (!empty($f['q'])) {
            $w[] = '('.self::PATIENT_SQL.' LIKE ? OR v.symptom LIKE ? OR v.diagnosis LIKE ?)';
            $like = '%'.$f['q'].'%'; array_push($p, $like, $like, $like);
        }
        $where = $w ? ('WHERE '.implode(' AND ', $w)) : '';
        return $this->query("SELECT v.*, ".self::PATIENT_SQL." AS patient,
                c.name AS classroom, u.full_name AS recorder,
                (SELECT COUNT(*) FROM nurse_visit_medicines nm WHERE nm.visit_id=v.id) AS med_count
            FROM nurse_visits v
            LEFT JOIN students s ON s.id=v.student_id
            LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.status='active'
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            LEFT JOIN personnel pe ON pe.id=v.personnel_id
            LEFT JOIN users u ON u.id=v.recorded_by
            $where ORDER BY v.visit_at DESC, v.id DESC LIMIT 300", $p);
    }

    public function visitFind(int $id): ?array
    {
        return $this->first("SELECT v.*, ".self::PATIENT_SQL." AS patient
            FROM nurse_visits v
            LEFT JOIN students s ON s.id=v.student_id
            LEFT JOIN personnel pe ON pe.id=v.personnel_id
            WHERE v.id=?", [$id]);
    }

    /** ยาที่จ่ายในการใช้บริการนั้น */
    public function visitMedicines(int $visitId): array
    {
        return $this->query("SELECT nm.*, m.name, m.unit
            FROM nurse_visit_medicines nm JOIN medicines m ON m.id=nm.medicine_id
            WHERE nm.visit_id=? ORDER BY nm.id", [$visitId]);
    }
    /** ยาที่จ่าย จัดกลุ่มตาม visit (สำหรับแสดงในตารางรายการ) */
    public function medicinesForVisits(array $visitIds): array
    {
        if (!$visitIds) return [];
        $in = implode(',', array_fill(0, count($visitIds), '?'));
        $rows = $this->query("SELECT nm.visit_id, nm.qty, m.name, m.unit
            FROM nurse_visit_medicines nm JOIN medicines m ON m.id=nm.medicine_id
            WHERE nm.visit_id IN ($in) ORDER BY nm.id", array_map('intval', $visitIds));
        $m = []; foreach ($rows as $r) $m[(int)$r['visit_id']][] = $r; return $m;
    }

    public function visitCreate(array $d, ?int $by): int
    {
        $type = in_array($d['patient_type'], ['student','personnel','other'], true) ? $d['patient_type'] : 'student';
        $outcome = isset(self::OUTCOMES[$d['outcome']]) ? $d['outcome'] : 'back_class';
        $this->execute("INSERT INTO nurse_visits (visit_at, patient_type, student_id, personnel_id, patient_name,
                symptom, diagnosis, treatment, outcome, refer_to, note, recorded_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$d['visit_at'] ?: date('Y-m-d H:i:s'), $type,
             $type==='student' ? ($d['student_id'] ?: null) : null,
             $type==='personnel' ? ($d['personnel_id'] ?: null) : null,
             $type==='other' ? (trim((string)$d['patient_name']) ?: null) : null,
             trim((string)$d['symptom']) ?: null, trim((string)$d['diagnosis']) ?: null,
             trim((string)$d['treatment']) ?: null, $outcome,
             trim((string)$d['refer_to']) ?: null, trim((string)$d['note']) ?: null, $by]);
        return $this->lastId();
    }

    /** ลบการใช้บริการ + คืนสต็อกยาที่จ่ายไป */
    public function visitDelete(int $id, ?int $by): void
    {
        foreach ($this->visitMedicines($id) as $m) {
            $this->stockMove((int)$m['medicine_id'], 'in', (int)$m['qty'],
                'คืนสต็อก (ลบบันทึกการใช้บริการ #'.$id.')', $by, null);
        }
        $this->execute("DELETE FROM nurse_visit_medicines WHERE visit_id=?", [$id]);
        $this->execute("UPDATE medicine_movements SET visit_id=NULL WHERE visit_id=?", [$id]);
        $this->execute("DELETE FROM nurse_visits WHERE id=?", [$id]);
    }

    /** จ่ายยาให้การใช้บริการหนึ่ง → ตัดสต็อก (ข้ามรายการที่สต็อกไม่พอ) @return array{ok:int,fail:array} */
    public function dispense(int $visitId, array $medIds, array $qtys, ?int $by): array
    {
        $ok = 0; $fail = [];
        foreach ($medIds as $i => $mid) {
            $mid = (int)$mid; $qty = (int)($qtys[$i] ?? 0);
            if ($mid <= 0 || $qty <= 0) continue;
            $med = $this->medicineFind($mid);
            if (!$med) { continue; }
            if ((int)$med['stock_qty'] < $qty) { $fail[] = $med['name'].' (คงเหลือ '.(int)$med['stock_qty'].')'; continue; }
            $this->execute("INSERT INTO nurse_visit_medicines (visit_id, medicine_id, qty) VALUES (?,?,?)",
                [$visitId, $mid, $qty]);
            $this->stockMove($mid, 'out', $qty, 'จ่ายยา (การใช้บริการ #'.$visitId.')', $by, $visitId);
            $ok++;
        }
        return ['ok'=>$ok, 'fail'=>$fail];
    }

    /** สถิติช่วงวันที่: จำนวนครั้ง / แยกประเภทผู้ป่วย / ผลการดำเนินการ / ยาที่จ่ายบ่อย */
    public function stats(string $from, string $to): array
    {
        $agg = $this->first("SELECT COUNT(*) total,
                COALESCE(SUM(patient_type='student'),0) students,
                COALESCE(SUM(patient_type='personnel'),0) personnel,
                COALESCE(SUM(outcome='refer'),0) refer,
                COALESCE(SUM(outcome='home'),0) home
            FROM nurse_visits WHERE visit_at BETWEEN ? AND ?", [$from.' 00:00:00', $to.' 23:59:59']) ?: [];
        $top = $this->query("SELECT m.name, m.unit, SUM(nm.qty) total_qty, COUNT(*) times
            FROM nurse_visit_medicines nm
            JOIN nurse_visits v ON v.id=nm.visit_id
            JOIN medicines m ON m.id=nm.medicine_id
            WHERE v.visit_at BETWEEN ? AND ?
            GROUP BY m.id ORDER BY total_qty DESC LIMIT 10", [$from.' 00:00:00', $to.' 23:59:59']);
        $bySymptom = $this->query("SELECT COALESCE(NULLIF(TRIM(diagnosis),''),NULLIF(TRIM(symptom),''),'ไม่ระบุ') label, COUNT(*) n
            FROM nurse_visits WHERE visit_at BETWEEN ? AND ?
            GROUP BY label ORDER BY n DESC LIMIT 10", [$from.' 00:00:00', $to.' 23:59:59']);
        return [
            'total'=>(int)($agg['total']??0), 'students'=>(int)($agg['students']??0),
            'personnel'=>(int)($agg['personnel']??0), 'refer'=>(int)($agg['refer']??0),
            'home'=>(int)($agg['home']??0), 'topMedicines'=>$top, 'bySymptom'=>$bySymptom,
        ];
    }

    // ================= คลังยา/เวชภัณฑ์ =================
    public function medicines(array $f = []): array
    {
        $w = ['1=1']; $p = [];
        if (!empty($f['q']))   { $w[] = '(name LIKE ? OR code LIKE ?)'; $like='%'.$f['q'].'%'; array_push($p,$like,$like); }
        if (!empty($f['cat'])) { $w[] = 'category = ?'; $p[] = $f['cat']; }
        if (!empty($f['low'])) { $w[] = 'stock_qty <= min_qty'; }
        return $this->query("SELECT * FROM medicines WHERE ".implode(' AND ', $w)."
            ORDER BY active DESC, (stock_qty <= min_qty) DESC, name", $p);
    }
    /** รายการที่จ่ายได้ (active + มีสต็อก) สำหรับฟอร์มจ่ายยา */
    public function dispensable(): array
    { return $this->query("SELECT id, name, unit, stock_qty FROM medicines WHERE active=1 ORDER BY name"); }

    public function medicineFind(int $id): ?array
    { return $this->first("SELECT * FROM medicines WHERE id=?", [$id]); }

    public function medicineCreate(array $d, ?int $by): int
    {
        $cat = isset(self::CATEGORIES[$d['category']]) ? $d['category'] : 'medicine';
        $this->execute("INSERT INTO medicines (code, name, category, unit, stock_qty, min_qty, expiry_date, note)
            VALUES (?,?,?,?,?,?,?,?)",
            [trim((string)$d['code']) ?: null, trim((string)$d['name']), $cat,
             trim((string)$d['unit']) ?: 'เม็ด', max(0,(int)$d['stock_qty']), max(0,(int)$d['min_qty']),
             $d['expiry_date'] ?: null, trim((string)$d['note']) ?: null]);
        $id = $this->lastId();
        if ((int)$d['stock_qty'] > 0) {
            $this->execute("INSERT INTO medicine_movements (medicine_id, movement_type, qty, balance_after, note, created_by)
                VALUES (?,'in',?,?,?,?)", [$id, (int)$d['stock_qty'], (int)$d['stock_qty'], 'ยอดยกมาเริ่มต้น', $by]);
        }
        return $id;
    }
    public function medicineUpdate(int $id, array $d): void
    {
        $cat = isset(self::CATEGORIES[$d['category']]) ? $d['category'] : 'medicine';
        $this->execute("UPDATE medicines SET code=?, name=?, category=?, unit=?, min_qty=?, expiry_date=?, note=?, active=? WHERE id=?",
            [trim((string)$d['code']) ?: null, trim((string)$d['name']), $cat,
             trim((string)$d['unit']) ?: 'เม็ด', max(0,(int)$d['min_qty']),
             $d['expiry_date'] ?: null, trim((string)$d['note']) ?: null,
             !empty($d['active']) ? 1 : 0, $id]);
    }
    public function medicineDelete(int $id): void
    {
        $this->execute("DELETE FROM medicine_movements WHERE medicine_id=?", [$id]);
        $this->execute("DELETE FROM nurse_visit_medicines WHERE medicine_id=?", [$id]);
        $this->execute("DELETE FROM medicines WHERE id=?", [$id]);
    }

    /**
     * เคลื่อนไหวสต็อก: in=รับเข้า, out=จ่ายออก, adjust=ตั้งค่าคงเหลือใหม่
     * @return bool false ถ้าจ่ายออกแล้วสต็อกไม่พอ
     */
    public function stockMove(int $medicineId, string $type, int $qty, string $note, ?int $by, ?int $visitId = null): bool
    {
        $med = $this->medicineFind($medicineId);
        if (!$med || $qty < 0) return false;
        $cur = (int)$med['stock_qty'];
        if ($type === 'in')          $new = $cur + $qty;
        elseif ($type === 'out')   { if ($cur < $qty) return false; $new = $cur - $qty; }
        elseif ($type === 'adjust')  $new = $qty;              // ปรับยอดเป็นจำนวนที่ระบุ
        else return false;
        $this->execute("UPDATE medicines SET stock_qty=? WHERE id=?", [$new, $medicineId]);
        $this->execute("INSERT INTO medicine_movements (medicine_id, movement_type, qty, balance_after, visit_id, note, created_by)
            VALUES (?,?,?,?,?,?,?)",
            [$medicineId, $type, $type==='adjust' ? abs($new-$cur) : $qty, $new, $visitId, trim($note) ?: null, $by]);
        return true;
    }

    public function movements(int $medicineId, int $limit = 30): array
    {
        $limit = max(1, min(200, $limit));
        return $this->query("SELECT mv.*, u.full_name AS actor
            FROM medicine_movements mv LEFT JOIN users u ON u.id=mv.created_by
            WHERE mv.medicine_id=? ORDER BY mv.id DESC LIMIT $limit", [$medicineId]);
    }

    /** สรุปคลัง: ทั้งหมด / ต่ำกว่าจุดสั่งซื้อ / ใกล้หมดอายุ (90 วัน) / หมดอายุแล้ว */
    public function stockSummary(): array
    {
        $r = $this->first("SELECT COUNT(*) total,
                COALESCE(SUM(stock_qty <= min_qty AND active=1),0) low,
                COALESCE(SUM(expiry_date IS NOT NULL AND expiry_date < CURDATE()),0) expired,
                COALESCE(SUM(expiry_date IS NOT NULL AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)),0) expiring
            FROM medicines") ?: [];
        return ['total'=>(int)($r['total']??0), 'low'=>(int)($r['low']??0),
                'expired'=>(int)($r['expired']??0), 'expiring'=>(int)($r['expiring']??0)];
    }

    // ================= ตัวเลือก =================
    /** นักเรียน active พร้อมห้อง (สำหรับเลือกผู้ป่วย) */
    public function students(): array
    {
        return $this->query("SELECT s.id, CONCAT(COALESCE(s.prefix,''),s.first_name,' ',s.last_name) AS name,
                s.student_code, COALESCE(c.name,'-') AS classroom
            FROM student_enrollments se
            JOIN students s ON s.id=se.student_id AND s.deleted_at IS NULL
            LEFT JOIN classrooms c ON c.id=se.classroom_id
            WHERE se.status='active' ORDER BY c.name, se.roll_number, s.first_name");
    }
    public function personnel(): array
    {
        return $this->query("SELECT id, CONCAT(COALESCE(prefix,''),first_name,' ',last_name) AS name
            FROM personnel WHERE deleted_at IS NULL AND status='active' ORDER BY first_name");
    }
}
