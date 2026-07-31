<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\AdvanceRefundMemo;
use App\Models\AuditLog;

/**
 * บันทึกข้อความขอคืนเงินตามสัญญายืมเงินราชการ
 * เรียกดู/แก้ไข/พิมพ์ ได้เฉพาะฝ่ายการเงิน/งบประมาณ (สิทธิ์ budget.refund_memo)
 */
class RefundMemoController extends Controller
{
    private AdvanceRefundMemo $m;
    public function __construct(){ $this->m = new AdvanceRefundMemo(); }

    private function guard(): void { $this->authorize('budget.refund_memo'); }

    public function index(): void
    {
        $this->guard();
        $this->view('refund_memo/index', [
            'title' => 'บันทึกขอคืนเงินยืมราชการ',
            'rows'  => $this->m->listMemos(),
        ]);
    }

    public function edit(string $id): void
    {
        $this->guard();
        $memo = $this->m->find((int)$id);
        if (!$memo) $this->back('refund-memo', 'error', 'ไม่พบบันทึก');
        $this->view('refund_memo/edit', [
            'title'   => 'บันทึกขอคืนเงิน '.($memo['memo_no'] ?: ''),
            'm'       => $memo,
            'methods' => AdvanceRefundMemo::METHODS,
            'fundTypes' => AdvanceRefundMemo::FUND_TYPES,
            'staff'   => $this->m->financeStaff(),
        ]);
    }

    public function update(string $id): void
    {
        $this->guard(); $this->verifyCsrf();
        $memo = $this->m->find((int)$id);
        if (!$memo) $this->back('refund-memo', 'error', 'ไม่พบบันทึก');
        $d = [
            'memo_no'       => trim((string)Request::input('memo_no','')),
            'memo_date'     => Request::input('memo_date', null) ?: null,
            'used_amount'   => (float)Request::input('used_amount', 0),
            'refund_amount' => (float)Request::input('refund_amount', 0),
            'refund_method' => (string)Request::input('refund_method','cash'),
            'fund_type'     => (string)Request::input('fund_type','budget'),
            'detail'        => trim((string)Request::input('detail','')),
            'operate_period'=> trim((string)Request::input('operate_period','')),
            'receiver_id'   => (int)Request::input('receiver_id', 0),
            'status'        => (string)Request::input('status','draft'),
        ];
        $this->m->update((int)$id, $d, Auth::id());
        AuditLog::record(Auth::id(), 'update', 'advance_refund_memos', (int)$id);
        $this->back('refund-memo/'.$id.'/edit', 'success', 'บันทึกการแก้ไขแล้ว');
    }

    public function print(string $id): void
    {
        $this->guard();
        $memo = $this->m->find((int)$id);
        if (!$memo) $this->back('refund-memo', 'error', 'ไม่พบบันทึก');
        $this->view('refund_memo/print', [
            'title'   => 'บันทึกขอคืนเงิน '.($memo['memo_no'] ?: ''),
            'm'       => $memo,
            'methods' => AdvanceRefundMemo::METHODS,
            'fundTypes' => AdvanceRefundMemo::FUND_TYPES,
            'signers' => $this->m->signers(),
            'backUrl' => 'refund-memo/'.$id.'/edit',
            'autoPrint' => true,
        ], 'print');
    }
}
