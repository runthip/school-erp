<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\TravelClaim;
use App\Models\AuditLog;

/**
 * คลังแบบฟอร์ม E-Office · ใบเบิกค่าใช้จ่ายเดินทางไปราชการ (แบบ 8708)
 * ครูกรอกเอง ลงตำแหน่งตามการกรอก · เก็บข้อมูล · รวบรวมสถิติ/ยอดรวม (สิทธิ์ travel.claim)
 */
class TravelController extends Controller
{
    private TravelClaim $m;
    public function __construct(){ $this->m = new TravelClaim(); }

    private function guard(): void { $this->authorize('travel.claim'); }

    private function filter(): array
    {
        return [
            'traveler_id' => (int)Request::input('traveler_id', 0),
            'status'      => (string)Request::input('status', ''),
            'month'       => (string)Request::input('month', ''),
        ];
    }

    public function index(): void
    {
        $this->guard();
        $f = $this->filter();
        $this->view('travel/index', [
            'title'    => 'เบิกค่าเดินทางไปราชการ (แบบ 8708)',
            'rows'     => $this->m->listClaims($f),
            'stats'    => $this->m->stats($f),
            'teachers' => $this->m->teachers(),
            'statusN'  => TravelClaim::STATUS,
            'f'        => $f,
        ]);
    }

    public function create(): void
    {
        $this->guard();
        $me = $this->m->identity((int)Auth::id());
        $this->view('travel/form', [
            'title'    => 'สร้างใบเบิกค่าเดินทางไปราชการ',
            't'        => null,
            'me'       => $me,
            'teachers' => $this->m->teachers(),
            'approvers'=> $this->m->approvers(),
            'places'   => TravelClaim::PLACES,
            'statusN'  => TravelClaim::STATUS,
        ]);
    }

    public function store(): void
    {
        $this->guard(); $this->verifyCsrf();
        $id = $this->m->create($this->input(), Auth::id());
        AuditLog::record(Auth::id(), 'create', 'travel_claims', $id);
        $this->back('travel/'.$id.'/edit', 'success', 'บันทึกใบเบิกค่าเดินทางแล้ว');
    }

    public function edit(string $id): void
    {
        $this->guard();
        $t = $this->m->find((int)$id);
        if (!$t) $this->back('travel', 'error', 'ไม่พบใบเบิก');
        $this->view('travel/form', [
            'title'    => 'แก้ไขใบเบิก '.($t['claim_no'] ?: ''),
            't'        => $t,
            'me'       => $this->m->identity((int)Auth::id()),
            'teachers' => $this->m->teachers(),
            'approvers'=> $this->m->approvers(),
            'places'   => TravelClaim::PLACES,
            'statusN'  => TravelClaim::STATUS,
        ]);
    }

    public function update(string $id): void
    {
        $this->guard(); $this->verifyCsrf();
        $t = $this->m->find((int)$id);
        if (!$t) $this->back('travel', 'error', 'ไม่พบใบเบิก');
        $this->m->update((int)$id, $this->input(), Auth::id());
        AuditLog::record(Auth::id(), 'update', 'travel_claims', (int)$id);
        $this->back('travel/'.$id.'/edit', 'success', 'บันทึกการแก้ไขแล้ว');
    }

    public function delete(string $id): void
    {
        $this->guard(); $this->verifyCsrf();
        $this->m->delete((int)$id);
        AuditLog::record(Auth::id(), 'delete', 'travel_claims', (int)$id);
        $this->back('travel', 'success', 'ลบใบเบิกแล้ว');
    }

    public function print(string $id): void
    {
        $this->guard();
        $t = $this->m->find((int)$id);
        if (!$t) $this->back('travel', 'error', 'ไม่พบใบเบิก');
        $this->view('travel/print', [
            'title'     => 'ใบเบิกค่าใช้จ่ายเดินทางไปราชการ '.($t['claim_no'] ?: ''),
            't'         => $t,
            'places'    => TravelClaim::PLACES,
            'backUrl'   => 'travel/'.$id.'/edit',
            'autoPrint' => true,
        ], 'print');
    }

    /** รับค่าจากฟอร์ม → array พร้อมลง DB */
    private function input(): array
    {
        $s = fn($k,$d='')=>trim((string)Request::input($k,$d));
        $n = fn($k)=>(float)Request::input($k,0);
        $i = fn($k)=>(int)Request::input($k,0);
        $dt= fn($k)=>Request::input($k, '') ?: null;      // date / datetime-local
        $place = fn($k)=>in_array(Request::input($k,'office'),['home','office','thailand'],true)?(string)Request::input($k,'office'):'office';
        $status= in_array(Request::input('status','draft'),['draft','submitted','approved','paid'],true)?(string)Request::input('status','draft'):'draft';
        return [
            'claim_no'         => $s('claim_no'),
            'claim_date'       => $dt('claim_date'),
            'office_place'     => $s('office_place'),
            'addressee'        => $s('addressee'),
            'order_no'         => $s('order_no'),
            'order_date'       => $dt('order_date'),
            'traveler_id'      => $i('traveler_id') ?: null,
            'traveler_name'    => $s('traveler_name'),
            'traveler_position'=> $s('traveler_position'),
            'affiliation'      => $s('affiliation'),
            'companions'       => $s('companions'),
            'purpose'          => $s('purpose'),
            'depart_from'      => $place('depart_from'),
            'depart_at'        => $dt('depart_at'),
            'return_to'        => $place('return_to'),
            'return_at'        => $dt('return_at'),
            'total_days'       => $n('total_days'),
            'total_hours'      => $n('total_hours'),
            'perdiem_type'     => $s('perdiem_type'),
            'perdiem_days'     => $n('perdiem_days'),
            'perdiem_amount'   => $n('perdiem_amount'),
            'lodging_type'     => $s('lodging_type'),
            'lodging_days'     => $n('lodging_days'),
            'lodging_amount'   => $n('lodging_amount'),
            'transport_detail' => $s('transport_detail'),
            'transport_amount' => $n('transport_amount'),
            'other_detail'     => $s('other_detail'),
            'other_amount'     => $n('other_amount'),
            'receipts_count'   => $i('receipts_count'),
            'loan_no'          => $s('loan_no'),
            'loan_date'        => $dt('loan_date'),
            'loan_amount'      => $n('loan_amount'),
            'note'             => $s('note'),
            'status'           => $status,
            'approver_id'      => $i('approver_id') ?: null,
        ];
    }
}
