<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Welfare;
use App\Models\AuditLog;

/**
 * งานร้านค้าสวัสดิการ (ฝ่ายบริหารทั่วไป) — สิทธิ์ general.welfare
 * บัญชีรายรับ-รายจ่าย (ผู้รับผิดชอบ/หมายเหตุ) + รายงาน
 */
class WelfareController extends Controller
{
    private Welfare $m;
    public function __construct(){ $this->m = new Welfare(); }

    private function guard(): void { $this->authorize('general.welfare'); }

    private function range(): array
    {
        return [(string)Request::input('from', date('Y-m-01')),
                (string)Request::input('to',   date('Y-m-d'))];
    }

    // ================= บัญชีรายรับ-รายจ่าย =================
    public function index(): void
    {
        $this->guard();
        [$from,$to] = $this->range();
        $f = ['from'=>$from,'to'=>$to,'type'=>(string)Request::input('type',''),
              'cat'=>(string)Request::input('cat',''),'q'=>trim((string)Request::input('q',''))];
        $this->view('welfare/index', [
            'title'=>'ร้านค้าสวัสดิการ',
            'rows'=>$this->m->transactions(array_filter($f)), 'f'=>$f,
            'sum'=>$this->m->summary($from,$to),
            'categories'=>Welfare::CATEGORIES,
            'people'=>$this->m->personnel(),
        ]);
    }

    public function store(): void
    {
        $this->guard(); $this->verifyCsrf();
        $d = [
            'tx_date'=>(string)Request::input('tx_date',''),
            'tx_type'=>(string)Request::input('tx_type','income'),
            'category'=>(string)Request::input('category','sale'),
            'description'=>trim((string)Request::input('description','')),
            'amount'=>(float)Request::input('amount',0),
            'ref_no'=>(string)Request::input('ref_no',''),
            'responsible_id'=>(int)Request::input('responsible_id',0),
            'note'=>(string)Request::input('note',''),
        ];
        if ($d['description']==='') $this->back('general/welfare','error','กรุณากรอกรายการ');
        if ($d['amount'] <= 0)      $this->back('general/welfare','error','กรุณากรอกจำนวนเงินให้ถูกต้อง');
        $id = $this->m->txCreate($d, Auth::id());
        AuditLog::record(Auth::id(),'create','welfare_transactions',$id);
        $this->back('general/welfare','success','บันทึกรายการเรียบร้อย');
    }

    public function delete(string $id): void
    {
        $this->guard(); $this->verifyCsrf();
        $tx = $this->m->txFind((int)$id);
        if (!$tx) $this->back('general/welfare','error','ไม่พบรายการ');
        $this->m->txDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','welfare_transactions',(int)$id);
        $this->back('general/welfare','success','ลบรายการแล้ว');
    }

    /** พิมพ์รายงานรายรับ-รายจ่าย */
    public function report(): void
    {
        $this->guard();
        [$from,$to] = $this->range();
        $f = ['from'=>$from,'to'=>$to,'type'=>(string)Request::input('type',''),
              'cat'=>(string)Request::input('cat',''),'q'=>trim((string)Request::input('q',''))];
        $this->view('welfare/report_print', [
            'title'=>'รายงานรายรับ-รายจ่าย ร้านค้าสวัสดิการ',
            'rows'=>$this->m->transactions(array_filter($f)),
            'sum'=>$this->m->summary($from,$to),
            'byCat'=>$this->m->byCategory($from,$to),
            'from'=>$from,'to'=>$to,
            'backUrl'=>'general/welfare','autoPrint'=>true,
        ], 'print');
    }

}
