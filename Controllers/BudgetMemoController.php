<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Schema;
use App\Models\BudgetMemo;
use App\Models\AuditLog;

/**
 * บันทึกข้อความขออนุมัติดำเนินการตามกิจกรรม/โครงการ (งป.01) + ตัดงบ
 */
class BudgetMemoController extends Controller
{
    private BudgetMemo $m;

    public function __construct() { $this->m = new BudgetMemo(); }

    private function guard(): void
    {
        $miss = Schema::missingFor('budget_memo');
        if ($miss) { $this->view('documents/needs_migration', ['title'=>'ต้องนำเข้าฐานข้อมูลเพิ่ม','missing'=>$miss]); exit; }
    }
    private function canApprove(): bool { return Auth::can('budget.memo_approve'); }
    private function myPid(): int
    {
        $u = Auth::user();
        return ($u && ($u['linked_type'] ?? '')==='personnel') ? (int)($u['linked_id'] ?? 0) : 0;
    }

    /* ---------- รายการ ---------- */

    public function index(): void
    {
        $this->guard(); $this->authorize('budget.memo');
        $year = (int)Request::input('year', (int)date('Y')+543);
        $approve = $this->canApprove();
        // ผู้อนุมัติเห็นทั้งหมด · ครูเห็นของตัวเอง
        $f = ['year'=>$year];
        if (!$approve) $f['created_by'] = Auth::id();
        $this->view('budget_memo/index', [
            'title' => 'บันทึกขออนุมัติดำเนินการ (งป.01)',
            'memos' => $this->m->listMemos($f),
            'stats' => $approve ? $this->m->yearStats($year) : [],
            'year' => $year, 'years' => $this->m->years(),
            'approve' => $approve, 'myId' => Auth::id(),
        ]);
    }

    public function create(): void
    {
        $this->guard(); $this->authorize('budget.memo');
        $year = (int)date('Y')+543;
        $this->view('budget_memo/edit', [
            'title' => 'บันทึกขออนุมัติใหม่ (งป.01)',
            'm' => null, 'year' => $year,
            'projects' => $this->m->projectsForSelect(),
            'teachers' => $this->m->teachers(),
            'departments' => $this->m->departments(),
            'subjectGroups' => $this->m->subjectGroups(),
            'nextNo' => $this->m->nextNo($year),
            'workGroups' => BudgetMemo::WORK_GROUPS,
            'fundSources' => BudgetMemo::FUND_SOURCES,
        ]);
    }

    public function edit(string $id): void
    {
        $this->guard(); $this->authorize('budget.memo');
        $memo = $this->m->find((int)$id);
        if (!$memo) $this->back('budget-memo','error','ไม่พบบันทึก');
        if (!in_array($memo['status'], ['draft','rejected'], true))
            $this->back('budget-memo/'.$id, 'error', 'ส่งแล้ว แก้ไขไม่ได้');
        $this->view('budget_memo/edit', [
            'title' => 'แก้ไขบันทึก งป.01',
            'm' => $memo, 'year' => (int)$memo['budget_year'],
            'projects' => $this->m->projectsForSelect(),
            'activities' => $memo['project_id'] ? $this->m->activitiesForSelect((int)$memo['project_id']) : [],
            'teachers' => $this->m->teachers(),
            'departments' => $this->m->departments(),
            'subjectGroups' => $this->m->subjectGroups(),
            'nextNo' => $memo['memo_no'] ?: $this->m->nextNo((int)$memo['budget_year']),
            'workGroups' => BudgetMemo::WORK_GROUPS,
            'fundSources' => BudgetMemo::FUND_SOURCES,
        ]);
    }

    /** AJAX: กิจกรรมของโครงการ (JSON) */
    public function activities(string $projectId): void
    {
        $this->guard(); $this->authorize('budget.memo');
        $this->json($this->m->activitiesForSelect((int)$projectId));
    }

    public function store(): void
    {
        $this->guard(); $this->authorize('budget.memo'); $this->verifyCsrf();
        $d = $this->collect();

        // ── บังคับกฎการทำงาน ──
        $errors = $this->m->validateRules($d);
        if ($errors) {
            $id = (int)Request::input('id', 0);
            $back = $id ? 'budget-memo/'.$id.'/edit' : 'budget-memo/create';
            $this->back($back, 'error', implode(' · ', $errors));
        }

        $id = (int)Request::input('id', 0);
        if ($id) {
            $memo = $this->m->find($id);
            if (!$memo || !in_array($memo['status'],['draft','rejected'],true))
                $this->back('budget-memo','error','แก้ไขไม่ได้');
            $this->m->update($id, $d);
            AuditLog::record(Auth::id(),'update','budget_memos',$id,null,null);
            $this->back('budget-memo/'.$id, 'success', 'บันทึกแล้ว');
        } else {
            $id = $this->m->create($d, Auth::id());
            AuditLog::record(Auth::id(),'create','budget_memos',$id,null,null);
            $this->back('budget-memo/'.$id, 'success', 'สร้างบันทึกแล้ว');
        }
    }

    private function collect(): array
    {
        $req = (float)Request::input('request_amount', 0);
        $total = (float)Request::input('budget_total', 0);
        $spent = (float)Request::input('already_spent', 0);
        // คณะกรรมการ: personnel ids
        $committee = array_values(array_filter(array_map('intval', (array)Request::input('committee', []))));
        $inspectors = array_values(array_filter(array_map('intval', (array)Request::input('inspectors', []))));
        return [
            'memo_no' => trim((string)Request::input('memo_no','')),
            'memo_date' => Request::input('memo_date', null) ?: null,
            'budget_year' => (int)Request::input('budget_year', (int)date('Y')+543),
            'department_id' => (int)Request::input('department_id', 0),
            'subject_group_id' => (int)Request::input('subject_group_id', 0),
            'department' => $this->deptName((int)Request::input('department_id', 0), (string)Request::input('department','')),
            'activity_name' => trim((string)Request::input('activity_name','')),
            'purpose' => trim((string)Request::input('purpose','')),
            'operate_date' => trim((string)Request::input('operate_date','')),
            'project_id' => (int)Request::input('project_id', 0),
            'activity_id' => (int)Request::input('activity_id', 0),
            'budget_id' => (int)Request::input('budget_id', 0),
            'budget_total' => $total, 'already_spent' => $spent,
            'request_amount' => $req, 'remaining' => $total - $spent - $req,
            'work_group' => Request::input('work_group', null) ?: null,
            'fund_source' => Request::input('fund_source', null) ?: null,
            'committee' => $committee, 'inspectors' => $inspectors,
            'responsible_id' => (int)Request::input('responsible_id', 0),
        ];
    }

    /** ชื่อฝ่ายจาก id (ซิงก์คอลัมน์ department ข้อความไว้แสดง/พิมพ์ย้อนหลัง) */
    private function deptName(int $deptId, string $fallback): string
    {
        if ($deptId > 0) {
            foreach ($this->m->departments() as $d) {
                if ((int)$d['id'] === $deptId) return (string)$d['name'];
            }
        }
        return trim($fallback);
    }

    /* ---------- ดู ---------- */

    public function show(string $id): void
    {
        $this->guard(); $this->authorize('budget.memo');
        $memo = $this->m->find((int)$id);
        if (!$memo) $this->back('budget-memo','error','ไม่พบบันทึก');
        // ดึงชื่อกรรมการ
        $this->view('budget_memo/show', [
            'title' => 'งป.01 '.($memo['activity_name'] ?: ''),
            'm' => $memo, 'names' => $this->nameMap(),
            'signers' => $this->m->signatories($memo),
            'canApprove' => $this->canApprove(),
            'isOwner' => (int)$memo['created_by'] === Auth::id(),
            'workGroups' => BudgetMemo::WORK_GROUPS, 'fundSources' => BudgetMemo::FUND_SOURCES,
        ]);
    }

    /** map personnel id → [name, position] */
    private function nameMap(): array
    {
        $map = [];
        foreach ($this->m->teachers() as $t) $map[(int)$t['id']] = $t;
        return $map;
    }

    /* ---------- workflow ---------- */

    public function submit(string $id): void
    {
        $this->guard(); $this->authorize('budget.memo'); $this->verifyCsrf();
        $this->m->submit((int)$id);
        AuditLog::record(Auth::id(),'update','budget_memos',(int)$id,null,['submit'=>true]);
        $this->back('budget-memo/'.$id, 'success', 'ส่งบันทึกเรียบร้อย รอพิจารณาตามลำดับ');
    }

    /** ความเห็นแต่ละขั้น (head/budget/supply/deputy) */
    public function step(string $id): void
    {
        $this->guard(); $this->authorize('budget.memo_approve'); $this->verifyCsrf();
        $step = (string)Request::input('step','head');
        $ok = Request::input('decision','ok') === 'ok';
        $comment = trim((string)Request::input('comment',''));
        $this->m->step((int)$id, $step, $ok, $comment ?: null, $this->myPid() ?: Auth::id());
        AuditLog::record(Auth::id(),'update','budget_memos',(int)$id,null,['step'=>$step,'ok'=>$ok]);
        $this->back('budget-memo/'.$id, 'success', $ok?'บันทึกความเห็นแล้ว':'ตีกลับบันทึกแล้ว');
    }

    /** ผอ.อนุมัติ → ตัดงบ */
    public function approve(string $id): void
    {
        $this->guard(); $this->authorize('budget.memo_approve'); $this->verifyCsrf();
        $ok = Request::input('decision','ok') === 'ok';
        $note = trim((string)Request::input('note',''));
        $ledgerId = $this->m->approve((int)$id, $ok, $note ?: null, $this->myPid() ?: Auth::id());
        AuditLog::record(Auth::id(),'update','budget_memos',(int)$id,null,['approve'=>$ok,'ledger'=>$ledgerId]);
        if ($ok) $this->back('budget-memo/'.$id, 'success', 'อนุมัติและตัดงบเรียบร้อย'.($ledgerId?' (ledger #'.$ledgerId.')':''));
        else $this->back('budget-memo/'.$id, 'success', 'ไม่อนุมัติบันทึกนี้');
    }

    public function delete(string $id): void
    {
        $this->guard(); $this->authorize('budget.memo'); $this->verifyCsrf();
        $memo = $this->m->find((int)$id);
        // ลบได้เฉพาะร่าง/ตีกลับ ของเจ้าของ · งบที่อนุมัติ/ตัดแล้วลบไม่ได้
        if ($memo && (int)$memo['created_by']===Auth::id() && in_array($memo['status'],['draft','rejected'],true)) {
            $this->m->hardDelete((int)$id, Auth::id());
            AuditLog::record(Auth::id(),'delete','budget_memos',(int)$id,null,['hard'=>true]);
            $this->back('budget-memo', 'success', 'ลบบันทึกแล้ว');
        }
        $this->back('budget-memo', 'error', 'ลบได้เฉพาะบันทึกที่เป็นร่างหรือถูกตีกลับ (ที่ยังไม่ตัดงบ)');
    }

    /** บันทึกการจ่ายเงิน (หลังอนุมัติ) */
    public function pay(string $id): void
    {
        $this->guard(); $this->authorize('budget.memo_approve'); $this->verifyCsrf();
        $note = trim((string)Request::input('payment_note',''));
        $ok = $this->m->pay((int)$id, $note ?: null, $this->myPid() ?: Auth::id());
        AuditLog::record(Auth::id(),'pay','budget_memos',(int)$id,null,null);
        $this->back('budget-memo/'.$id, $ok?'success':'error', $ok?'บันทึกการจ่ายเงินแล้ว':'ต้องอนุมัติก่อนจึงจ่ายได้');
    }

    /* ---------- พิมพ์ + รายงาน ---------- */

    public function print(string $id): void
    {
        $this->guard(); $this->authorize('budget.memo');
        $memo = $this->m->find((int)$id);
        if (!$memo) $this->back('budget-memo','error','ไม่พบบันทึก');
        $this->view('budget_memo/print', [
            'title' => 'งป.01 '.($memo['activity_name'] ?: ''),
            'm' => $memo, 'names' => $this->nameMap(),
            'signers' => $this->m->signatories($memo),
            'workGroups' => BudgetMemo::WORK_GROUPS, 'fundSources' => BudgetMemo::FUND_SOURCES,
            'autoPrint' => true,
        ], 'print');
    }

    public function report(): void
    {
        $this->guard(); $this->authorize('budget.memo');
        $year = (int)Request::input('year', (int)date('Y')+543);
        $this->view('budget_memo/report', [
            'title' => 'รายงานงบประมาณ (งป.01)',
            'year' => $year, 'years' => $this->m->years(),
            'stats' => $this->m->yearStats($year),
            'rows' => $this->m->budgetReport($year),
            'byDept' => $this->m->reportByDepartment($year),
            'byGroup' => $this->m->reportBySubjectGroup($year),
        ]);
    }
}
