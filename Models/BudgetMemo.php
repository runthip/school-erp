<?php
namespace App\Models;

use App\Core\Model;

/**
 * บันทึกข้อความขออนุมัติดำเนินการตามกิจกรรม/โครงการ (งป.01)
 * เชื่อมโครงการ/กิจกรรม/งบ + ตัดงบผ่าน budget_ledger
 */
class BudgetMemo extends Model
{
    protected string $table = 'budget_memos';

    public const WORK_GROUPS = [
        'academic' => 'บริหารวิชาการ', 'hr' => 'บริหารบุคลากร',
        'budget' => 'บริหารงบประมาณ', 'general' => 'บริหารทั่วไป', 'central' => 'งบส่วนกลาง',
    ];
    public const FUND_SOURCES = [
        'subsidy' => 'เงินอุดหนุน', 'lunch' => 'เงินอาหารกลางวัน',
        'income' => 'เงินรายได้สถานศึกษา', 'personnel' => 'เงินรายจ่ายบุคลากร',
    ];
    public const STATUS = [
        'draft'     => ['ร่าง','bg-slate-100 text-slate-600'],
        'submitted' => ['ส่งแล้ว รอพิจารณา','bg-amber-100 text-amber-700'],
        'head_ok'   => ['หัวหน้าฝ่ายเห็นชอบ','bg-blue-100 text-blue-700'],
        'budget_ok' => ['ผ่านงานงบประมาณ','bg-blue-100 text-blue-700'],
        'supply_ok' => ['ผ่านงานพัสดุ','bg-blue-100 text-blue-700'],
        'deputy_ok' => ['รอง ผอ.เห็นชอบ','bg-indigo-100 text-indigo-700'],
        'approved'  => ['ผอ.อนุมัติ · ตัดงบแล้ว','bg-emerald-100 text-emerald-700'],
        'paid'      => ['จ่ายเงินแล้ว','bg-teal-100 text-teal-700'],
        'rejected'  => ['ไม่อนุมัติ','bg-red-100 text-red-700'],
    ];

    /* ---------- อ่าน ---------- */

    public function find(int $id): ?array
    {
        $r = $this->first("SELECT m.*,
                pr.name AS project_name, pr.budget_amount AS project_budget, pr.spent_amount AS project_spent,
                a.name AS activity_name_ref, a.budget_amount AS activity_budget, a.spent_amount AS activity_spent,
                dp.name AS department_name, sg.name AS subject_group_name,
                CONCAT(rp.prefix,rp.first_name,' ',rp.last_name) AS responsible_name,
                rp.position AS responsible_position
            FROM budget_memos m
            LEFT JOIN projects pr ON pr.id = m.project_id
            LEFT JOIN project_activities a ON a.id = m.activity_id
            LEFT JOIN org_departments dp ON dp.id = m.department_id
            LEFT JOIN subject_groups sg ON sg.id = m.subject_group_id
            LEFT JOIN personnel rp ON rp.id = m.responsible_id
            WHERE m.id = ?", [$id]);
        if ($r) {
            $r['committee']  = !empty($r['committee'])  ? (json_decode($r['committee'], true) ?: []) : [];
            $r['inspectors'] = !empty($r['inspectors']) ? (json_decode($r['inspectors'], true) ?: []) : [];
        }
        return $r;
    }

    public function listMemos(array $f = []): array
    {
        $w = "m.deleted_at IS NULL"; $p = [];
        if (!empty($f['year']))   { $w .= " AND m.budget_year=?"; $p[] = (int)$f['year']; }
        if (!empty($f['status'])) { $w .= " AND m.status=?";      $p[] = $f['status']; }
        if (!empty($f['created_by'])) { $w .= " AND m.created_by=?"; $p[] = (int)$f['created_by']; }
        return $this->query("SELECT m.id, m.memo_no, m.memo_date, m.activity_name, m.department,
                m.request_amount, m.status, m.created_by, pr.name AS project_name
            FROM budget_memos m
            LEFT JOIN projects pr ON pr.id = m.project_id
            WHERE $w ORDER BY m.budget_year DESC, m.id DESC", $p);
    }

    public function yearStats(int $year): array
    {
        $r = $this->first("SELECT COUNT(*) total,
            SUM(status='approved') approved,
            SUM(status NOT IN ('approved','rejected','draft')) pending,
            SUM(status='rejected') rejected,
            COALESCE(SUM(CASE WHEN status='approved' THEN request_amount END),0) approved_amount
            FROM budget_memos WHERE deleted_at IS NULL AND budget_year=?", [$year]);
        return $r ?: [];
    }

    public function nextNo(int $year): string
    {
        $r = $this->first("SELECT COALESCE(MAX(CAST(memo_no AS UNSIGNED)),0)+1 n
            FROM budget_memos WHERE deleted_at IS NULL AND budget_year=? AND memo_no REGEXP '^[0-9]+$'", [$year]);
        return (string)((int)($r['n'] ?? 1));
    }

    public function years(): array
    {
        $rows = $this->query("SELECT DISTINCT budget_year y FROM budget_memos ORDER BY y DESC");
        $ys = array_map(fn($r)=>(int)$r['y'], $rows);
        $cur = (int)date('Y') + 543;
        if (!in_array($cur, $ys, true)) array_unshift($ys, $cur);
        return $ys;
    }

    /* ---------- เขียน ---------- */

    public function create(array $d, ?int $by): int
    {
        $this->execute("INSERT INTO budget_memos
            (school_id, memo_no, memo_date, budget_year, department, department_id, subject_group_id, activity_name, purpose, operate_date,
             project_id, activity_id, budget_id, budget_total, already_spent, request_amount, remaining,
             work_group, fund_source, committee, inspectors, responsible_id, created_by)
            VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [
            $d['memo_no'] ?? null, $d['memo_date'] ?? null, $d['budget_year'],
            $d['department'] ?? null, $d['department_id'] ?: null, $d['subject_group_id'] ?: null,
            $d['activity_name'] ?? null, $d['purpose'] ?? null, $d['operate_date'] ?? null,
            $d['project_id'] ?: null, $d['activity_id'] ?: null, $d['budget_id'] ?: null,
            $d['budget_total'] ?? 0, $d['already_spent'] ?? 0, $d['request_amount'] ?? 0, $d['remaining'] ?? 0,
            $d['work_group'] ?? null, $d['fund_source'] ?? null,
            json_encode($d['committee'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($d['inspectors'] ?? [], JSON_UNESCAPED_UNICODE),
            $d['responsible_id'] ?: null, $by,
        ]);
        return $this->lastId();
    }

    public function update(int $id, array $d): void
    {
        $this->execute("UPDATE budget_memos SET
            memo_no=?, memo_date=?, department=?, department_id=?, subject_group_id=?, activity_name=?, purpose=?, operate_date=?,
            project_id=?, activity_id=?, budget_id=?, budget_total=?, already_spent=?, request_amount=?, remaining=?,
            work_group=?, fund_source=?, committee=?, inspectors=?, responsible_id=?
            WHERE id=? AND status IN ('draft','rejected')", [
            $d['memo_no'] ?? null, $d['memo_date'] ?? null,
            $d['department'] ?? null, $d['department_id'] ?: null, $d['subject_group_id'] ?: null,
            $d['activity_name'] ?? null, $d['purpose'] ?? null, $d['operate_date'] ?? null,
            $d['project_id'] ?: null, $d['activity_id'] ?: null, $d['budget_id'] ?: null,
            $d['budget_total'] ?? 0, $d['already_spent'] ?? 0, $d['request_amount'] ?? 0, $d['remaining'] ?? 0,
            $d['work_group'] ?? null, $d['fund_source'] ?? null,
            json_encode($d['committee'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($d['inspectors'] ?? [], JSON_UNESCAPED_UNICODE),
            $d['responsible_id'] ?: null, $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->execute("DELETE FROM budget_memos WHERE id=? AND status IN ('draft','rejected')", [$id]);
    }

    /* ---------- workflow ---------- */

    public function submit(int $id): void
    {
        $this->execute("UPDATE budget_memos SET status='submitted' WHERE id=? AND status IN ('draft','rejected')", [$id]);
    }

    /** บันทึกความเห็นแต่ละขั้น (head/budget/supply/deputy) */
    public function step(int $id, string $step, bool $ok, ?string $comment, int $by): void
    {
        $map = [
            'head'   => ['head_comment', 'head_by',   'head_ok'],
            'budget' => ['budget_correct','budget_by', 'budget_ok'],
            'supply' => ['supply_approve','supply_by', 'supply_ok'],
            'deputy' => ['deputy_approve','deputy_by', 'deputy_ok'],
        ];
        if (!isset($map[$step])) return;
        [$col, $byCol, $newStatus] = $map[$step];
        if ($step === 'head') {
            $this->execute("UPDATE budget_memos SET head_comment=?, head_by=?, status=? WHERE id=?",
                [$comment, $by, $ok?'head_ok':'rejected', $id]);
        } else {
            $this->execute("UPDATE budget_memos SET $col=?, $byCol=?, status=? WHERE id=?",
                [$ok?1:0, $by, $ok?$newStatus:'rejected', $id]);
        }
    }

    /**
     * ผอ.อนุมัติ → ตัดงบจริงผ่าน budget_ledger
     * คืน ledger_id (0 = ไม่ได้ตัด)
     */
    public function approve(int $id, bool $ok, ?string $note, int $by): int
    {
        $m = $this->find($id);
        if (!$m) return 0;

        if (!$ok) {
            $this->execute("UPDATE budget_memos SET status='rejected', director_approve=0, director_note=?, director_by=? WHERE id=?",
                [$note, $by, $id]);
            return 0;
        }

        // ตัดงบ (ถ้ายังไม่เคยตัด และมีจำนวนเงิน)
        $ledgerId = (int)($m['ledger_id'] ?? 0);
        if (!$ledgerId && (float)$m['request_amount'] > 0) {
            $ledger = new BudgetLedger();
            $ledgerId = $ledger->deduct(
                'request', $id, $m['memo_no'] ?? null,
                $m['budget_id'] ? (int)$m['budget_id'] : null,
                $m['project_id'] ? (int)$m['project_id'] : null,
                $m['activity_id'] ? (int)$m['activity_id'] : null,
                (float)$m['request_amount'],
                'งป.01 '.($m['activity_name'] ?? ''), $by
            );
        }
        $this->execute("UPDATE budget_memos SET status='approved', director_approve=1, director_note=?, director_by=?,
            approved_at=NOW(), ledger_id=?, deducted_at=NOW() WHERE id=?",
            [$note, $by, $ledgerId ?: null, $id]);
        return $ledgerId;
    }

    /* ---------- ข้อมูลอ้างอิงสำหรับฟอร์ม ---------- */

    /** โครงการ + งบคงเหลือ */
    public function projectsForSelect(): array
    {
        return $this->query("SELECT id, name,
            department_id, subject_group_id,
            budget_amount, spent_amount,
            (budget_amount - spent_amount) AS remaining
            FROM projects ORDER BY name");
    }

    /**
     * ผู้ลงนามแต่ละตำแหน่งตามระบบ (เติมบล็อกลงนามในเอกสารให้ครบทุกข้อ)
     * คืน [key => ['id','name','position']] สำหรับ 5 ขั้น:
     *  head=หัวหน้าฝ่าย/กลุ่มสาระ · budget=หัวหน้าแผนและงบประมาณ ·
     *  supply=หัวหน้าเจ้าหน้าที่พัสดุ · deputy=รองผอ. · director=ผอ.
     */
    public function signatories(array $memo): array
    {
        $byPos = function(string $like, ?string $notLike=null) {
            $sql = "SELECT id, CONCAT(prefix,first_name,' ',last_name) AS name, position,
                   (SELECT u.signature_path FROM users u WHERE u.linked_type='personnel' AND u.linked_id=personnel.id AND u.deleted_at IS NULL LIMIT 1) AS signature
                    FROM personnel WHERE deleted_at IS NULL AND status='active' AND position LIKE ?";
            $p = ['%'.$like.'%'];
            if ($notLike !== null) { $sql .= " AND position NOT LIKE ?"; $p[] = '%'.$notLike.'%'; }
            return $this->first($sql.' ORDER BY id LIMIT 1', $p);
        };
        $deptHead = function($cond, $val) {
            return $this->first("SELECT p.id, CONCAT(p.prefix,p.first_name,' ',p.last_name) AS name, p.position
                FROM org_departments d JOIN personnel p ON p.id=d.head_personnel_id WHERE d.$cond=?", [$val]);
        };
        $did = (int)($memo['department_id'] ?? 0);
        return [
            'head'     => $did ? $deptHead('id', $did) : null,
            'budget'   => $deptHead('code', 'budget') ?: $byPos('งบประมาณ') ?: $byPos('การเงิน'),
            'supply'   => $byPos('พัสดุ'),
            'deputy'   => $byPos('รองผู้อำนวยการ'),
            'director' => $byPos('ผู้อำนวยการ', 'รอง'),
        ];
    }

    /** ฝ่าย 6 ฝ่าย (จากข้อมูลจริง) */
    public function departments(): array
    { return $this->query("SELECT id, name FROM org_departments ORDER BY id"); }

    /** กลุ่มสาระ 8 กลุ่ม (จากข้อมูลจริง) */
    public function subjectGroups(): array
    { return $this->query("SELECT id, name FROM subject_groups ORDER BY id"); }

    /** กิจกรรมของโครงการ + งบคงเหลือ */
    public function activitiesForSelect(int $projectId): array
    {
        return $this->query("SELECT id, name, budget_amount, spent_amount,
            (budget_amount - spent_amount) AS remaining
            FROM project_activities WHERE project_id=? ORDER BY id", [$projectId]);
    }

    /** รายชื่อครูทั้งหมด + ตำแหน่ง (สำหรับเลือกกรรมการ/ผู้รับผิดชอบ) */
    public function teachers(): array
    {
        return $this->query("SELECT id,
            CONCAT(prefix,first_name,' ',last_name) AS name, position,
                   (SELECT u.signature_path FROM users u WHERE u.linked_type='personnel' AND u.linked_id=personnel.id AND u.deleted_at IS NULL LIMIT 1) AS signature
            FROM personnel WHERE deleted_at IS NULL AND status='active'
            ORDER BY first_name");
    }

    /** บันทึกการจ่ายเงิน (หลัง ผอ.อนุมัติแล้ว) */
    public function pay(int $id, ?string $note, int $by): bool
    {
        $m = $this->first("SELECT status FROM budget_memos WHERE id=?", [$id]);
        if (!$m || $m['status'] !== 'approved') return false;
        $this->execute("UPDATE budget_memos SET status='paid', paid_at=NOW(), paid_by=?, payment_note=? WHERE id=?",
            [$by, $note, $id]);
        return true;
    }

    /** สถิติรวมทั้งฝ่าย (สำหรับ hub) — นับข้ามหลายตาราง */
    public function hubStats(int $year): array
    {
        $one = fn($sql,$p=[]) => (int)($this->first($sql,$p)['c'] ?? 0);
        $sum = fn($sql,$p=[]) => (float)($this->first($sql,$p)['c'] ?? 0);
        return [
            // แผน/โครงการ/กิจกรรม
            'projects'   => $one("SELECT COUNT(*) c FROM projects"),
            'activities' => $one("SELECT COUNT(*) c FROM project_activities"),
            'budget_alloc' => $sum("SELECT COALESCE(SUM(budget_amount),0) c FROM projects"),
            'budget_spent' => $sum("SELECT COALESCE(SUM(spent_amount),0) c FROM projects"),
            // งป.01
            'memo_total'   => $one("SELECT COUNT(*) c FROM budget_memos WHERE deleted_at IS NULL AND budget_year=?", [$year]),
            'memo_pending' => $one("SELECT COUNT(*) c FROM budget_memos WHERE deleted_at IS NULL AND budget_year=? AND status NOT IN ('draft','approved','paid','rejected')", [$year]),
            'memo_approved'=> $one("SELECT COUNT(*) c FROM budget_memos WHERE deleted_at IS NULL AND budget_year=? AND status IN ('approved','paid')", [$year]),
            'memo_paid'    => $one("SELECT COUNT(*) c FROM budget_memos WHERE deleted_at IS NULL AND budget_year=? AND status='paid'", [$year]),
            'memo_amount'  => $sum("SELECT COALESCE(SUM(request_amount),0) c FROM budget_memos WHERE deleted_at IS NULL AND budget_year=? AND status IN ('approved','paid')", [$year]),
        ];
    }

    /** งป.01 ที่รอผู้ใช้ดำเนินการ (pending tasks) */
    public function pendingApprovals(): array
    {
        return $this->query("SELECT m.id, m.memo_no, m.activity_name, m.request_amount, m.status,
                pr.name AS project_name
            FROM budget_memos m LEFT JOIN projects pr ON pr.id=m.project_id
            WHERE m.deleted_at IS NULL AND m.status NOT IN ('draft','approved','paid','rejected')
            ORDER BY m.id DESC LIMIT 20");
    }

    /* ---------- กฎการทำงาน (business rules) ---------- */

    /** ตรวจว่าโครงการอนุมัติแล้วหรือยัง (กฎ: ห้ามเบิกถ้าโครงการยังไม่อนุมัติ) */
    public function projectApproved(int $projectId): bool
    {
        if (!$projectId) return true; // ไม่ผูกโครงการ = ไม่บังคับ
        $r = $this->first("SELECT approval_status FROM projects WHERE id=?", [$projectId]);
        return $r && $r['approval_status'] === 'approved';
    }

    /** งบคงเหลือของกิจกรรม/โครงการ (กฎ: ห้ามเบิกเกิน) */
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

    /**
     * ตรวจกฎก่อนสร้าง/แก้ไข → คืน array ของข้อความ error (ว่าง = ผ่าน)
     */
    public function validateRules(array $d, ?int $excludeMemoId = null): array
    {
        $errors = [];
        $pid = (int)($d['project_id'] ?? 0);
        $aid = (int)($d['activity_id'] ?? 0);
        $req = (float)($d['request_amount'] ?? 0);

        // กฎ 1: โครงการต้องอนุมัติก่อน
        if ($pid && !$this->projectApproved($pid))
            $errors[] = 'โครงการนี้ยังไม่ได้รับการอนุมัติ จึงยังสร้างคำขอเบิกจ่ายไม่ได้';

        // กฎ 2: ห้ามเบิกเกินงบที่จัดสรร
        if ($req <= 0) {
            $errors[] = 'จำนวนเงินที่ขอเบิกต้องมากกว่า 0';
        } else {
            $avail = $this->availableBudget($pid, $aid);
            if ($avail !== null && $req > $avail + 0.001)
                $errors[] = 'จำนวนที่ขอเบิก ('.number_format($req,2).') เกินงบคงเหลือ ('.number_format($avail,2).')';
        }
        return $errors;
    }

    /* ---------- ถังขยะ (soft delete 30 วัน) ---------- */

    public function softDelete(int $id, int $by): void
    {
        $this->execute("UPDATE budget_memos SET deleted_at=NOW(), deleted_by=? WHERE id=? AND status IN ('draft','rejected')", [$by, $id]);
    }
    /** ลบถาวรทันที (จัดการในหน้าเดียว) — เฉพาะร่าง/ตีกลับ ของเจ้าของ · งบที่อนุมัติ/ตัดแล้วลบไม่ได้ */
    public function hardDelete(int $id, int $by): void
    {
        $this->execute("DELETE FROM budget_memos WHERE id=? AND created_by=? AND status IN ('draft','rejected')", [$id, $by]);
    }
    public function restore(int $id): void
    {
        $this->execute("UPDATE budget_memos SET deleted_at=NULL, deleted_by=NULL WHERE id=?", [$id]);
    }
    /** รายการในถังขยะ (ยังไม่เกิน 30 วัน) */
    public function trash(): array
    {
        return $this->query("SELECT m.id, m.memo_no, m.activity_name, m.request_amount, m.deleted_at,
                DATEDIFF(DATE_ADD(m.deleted_at, INTERVAL 30 DAY), NOW()) AS days_left
            FROM budget_memos m WHERE m.deleted_at IS NOT NULL ORDER BY m.deleted_at DESC");
    }
    /** ลบถาวรรายการที่เกิน 30 วัน (เรียกจาก cron/หน้าถังขยะ) — คืนจำนวนที่ลบ */
    public function purgeExpired(): int
    {
        $rows = $this->query("SELECT id FROM budget_memos WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        foreach ($rows as $r) $this->execute("DELETE FROM budget_memos WHERE id=?", [(int)$r['id']]);
        return count($rows);
    }
    public function forceDelete(int $id): void
    {
        $this->execute("DELETE FROM budget_memos WHERE id=? AND deleted_at IS NOT NULL", [$id]);
    }

    /** รายงานงบแยกตามฝ่าย (6 ฝ่าย) — งบโครงการ + ยอดอนุมัติ งป.01 ปีนั้น */
    public function reportByDepartment(int $year): array
    {
        return $this->query("SELECT d.id, d.name,
            (SELECT COUNT(*) FROM projects p WHERE p.department_id=d.id) AS proj_count,
            (SELECT COALESCE(SUM(p.budget_amount),0) FROM projects p WHERE p.department_id=d.id) AS budget,
            (SELECT COALESCE(SUM(p.spent_amount),0) FROM projects p WHERE p.department_id=d.id) AS spent,
            (SELECT COALESCE(SUM(m.request_amount),0) FROM budget_memos m
               WHERE m.department_id=d.id AND m.status='approved' AND m.budget_year=? AND m.deleted_at IS NULL) AS memo_approved
            FROM org_departments d ORDER BY d.id", [$year]);
    }

    /** รายงานงบแยกตามกลุ่มสาระ (8 กลุ่ม) — งบโครงการวิชาการ + ยอดอนุมัติ งป.01 ปีนั้น */
    public function reportBySubjectGroup(int $year): array
    {
        return $this->query("SELECT g.id, g.name,
            (SELECT COUNT(*) FROM projects p WHERE p.subject_group_id=g.id) AS proj_count,
            (SELECT COALESCE(SUM(p.budget_amount),0) FROM projects p WHERE p.subject_group_id=g.id) AS budget,
            (SELECT COALESCE(SUM(p.spent_amount),0) FROM projects p WHERE p.subject_group_id=g.id) AS spent,
            (SELECT COALESCE(SUM(m.request_amount),0) FROM budget_memos m
               WHERE m.subject_group_id=g.id AND m.status='approved' AND m.budget_year=? AND m.deleted_at IS NULL) AS memo_approved
            FROM subject_groups g ORDER BY g.id", [$year]);
    }

    /** รายงานงบตามโครงการ (ยอดอนุมัติผ่าน งป.01) */
    public function budgetReport(int $year): array
    {
        return $this->query("SELECT pr.id, pr.name AS project, pr.budget_amount, pr.spent_amount,
            (pr.budget_amount - pr.spent_amount) AS remaining,
            COUNT(m.id) AS memo_count,
            COALESCE(SUM(CASE WHEN m.status='approved' THEN m.request_amount END),0) AS memo_approved
            FROM projects pr
            LEFT JOIN budget_memos m ON m.project_id = pr.id AND m.budget_year = ?
            GROUP BY pr.id, pr.name, pr.budget_amount, pr.spent_amount
            ORDER BY pr.name", [$year]);
    }
}
