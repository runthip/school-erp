<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Nurse;
use App\Models\AuditLog;

/**
 * งานพยาบาล/ห้องพยาบาล (ฝ่ายบริหารทั่วไป) — สิทธิ์ general.health
 * บันทึกการใช้บริการ (จ่ายยา → ตัดสต็อกอัตโนมัติ) + คลังยา/เวชภัณฑ์ + รายงาน
 */
class NurseController extends Controller
{
    private Nurse $m;
    public function __construct(){ $this->m = new Nurse(); }

    private function guard(): void { $this->authorize('general.health'); }

    /** ช่วงวันที่เริ่มต้น = เดือนปัจจุบัน */
    private function range(): array
    {
        $from = (string)Request::input('from', date('Y-m-01'));
        $to   = (string)Request::input('to', date('Y-m-d'));
        return [$from, $to];
    }

    // ================= การใช้บริการ =================
    public function index(): void
    {
        $this->guard();
        [$from, $to] = $this->range();
        $f = ['from'=>$from, 'to'=>$to,
              'type'=>(string)Request::input('type',''), 'outcome'=>(string)Request::input('outcome',''),
              'q'=>trim((string)Request::input('q',''))];
        $rows = $this->m->visits(array_filter($f));
        $this->view('nurse/index', [
            'title'=>'งานพยาบาล / ห้องพยาบาล',
            'rows'=>$rows, 'f'=>$f,
            'meds'=>$this->m->medicinesForVisits(array_map(fn($r)=>(int)$r['id'], $rows)),
            'stats'=>$this->m->stats($from, $to),
            'stock'=>$this->m->stockSummary(),
            'dispensable'=>$this->m->dispensable(),
            'students'=>$this->m->students(), 'personnel'=>$this->m->personnel(),
            'outcomes'=>Nurse::OUTCOMES,
        ]);
    }

    public function store(): void
    {
        $this->guard(); $this->verifyCsrf();
        $type = (string)Request::input('patient_type','student');
        $d = [
            'visit_at'=>str_replace('T',' ',(string)Request::input('visit_at','')) ?: date('Y-m-d H:i:s'),
            'patient_type'=>$type,
            'student_id'=>(int)Request::input('student_id',0),
            'personnel_id'=>(int)Request::input('personnel_id',0),
            'patient_name'=>(string)Request::input('patient_name',''),
            'symptom'=>(string)Request::input('symptom',''),
            'diagnosis'=>(string)Request::input('diagnosis',''),
            'treatment'=>(string)Request::input('treatment',''),
            'outcome'=>(string)Request::input('outcome','back_class'),
            'refer_to'=>(string)Request::input('refer_to',''),
            'note'=>(string)Request::input('note',''),
        ];
        if ($type==='student' && !$d['student_id']) $this->back('general/nurse','error','กรุณาเลือกนักเรียน');
        if ($type==='personnel' && !$d['personnel_id']) $this->back('general/nurse','error','กรุณาเลือกบุคลากร');
        if ($type==='other' && trim($d['patient_name'])==='') $this->back('general/nurse','error','กรุณากรอกชื่อผู้รับบริการ');
        if (trim($d['symptom'])==='') $this->back('general/nurse','error','กรุณากรอกอาการ');

        $id = $this->m->visitCreate($d, Auth::id());
        $r = $this->m->dispense($id, (array)Request::input('med_id',[]), (array)Request::input('med_qty',[]), Auth::id());
        AuditLog::record(Auth::id(),'create','nurse_visits',$id);
        $msg = 'บันทึกการใช้บริการแล้ว'.($r['ok']?' · จ่ายยา '.$r['ok'].' รายการ (ตัดสต็อกแล้ว)':'');
        if ($r['fail']) $msg .= ' — สต็อกไม่พอ: '.implode(', ', $r['fail']);
        $this->back('general/nurse', $r['fail']?'error':'success', $msg);
    }

    public function delete(string $id): void
    {
        $this->guard(); $this->verifyCsrf();
        if (!$this->m->visitFind((int)$id)) $this->back('general/nurse','error','ไม่พบรายการ');
        $this->m->visitDelete((int)$id, Auth::id());
        AuditLog::record(Auth::id(),'delete','nurse_visits',(int)$id);
        $this->back('general/nurse','success','ลบบันทึกและคืนสต็อกยาแล้ว');
    }

    /** พิมพ์รายงานสรุปการใช้บริการห้องพยาบาล */
    public function report(): void
    {
        $this->guard();
        [$from, $to] = $this->range();
        $f = ['from'=>$from,'to'=>$to,'type'=>(string)Request::input('type',''),
              'outcome'=>(string)Request::input('outcome',''),'q'=>trim((string)Request::input('q',''))];
        $rows = $this->m->visits(array_filter($f));
        $this->view('nurse/report_print', [
            'title'=>'รายงานการใช้บริการห้องพยาบาล',
            'rows'=>$rows, 'from'=>$from, 'to'=>$to,
            'meds'=>$this->m->medicinesForVisits(array_map(fn($r)=>(int)$r['id'], $rows)),
            'stats'=>$this->m->stats($from,$to),
            'backUrl'=>'general/nurse','autoPrint'=>true,
        ], 'print');
    }

    // ================= คลังยา/เวชภัณฑ์ =================
    public function medicines(): void
    {
        $this->guard();
        $f = ['q'=>trim((string)Request::input('q','')),'cat'=>(string)Request::input('cat',''),
              'low'=>(string)Request::input('low','')];
        $mid = (int)Request::input('view',0);
        $this->view('nurse/medicines', [
            'title'=>'คลังยา / เวชภัณฑ์',
            'rows'=>$this->m->medicines(array_filter($f)), 'f'=>$f,
            'stock'=>$this->m->stockSummary(),
            'categories'=>Nurse::CATEGORIES,
            'viewId'=>$mid, 'movements'=>$mid?$this->m->movements($mid):[],
            'viewMed'=>$mid?$this->m->medicineFind($mid):null,
        ]);
    }

    public function medStore(): void
    {
        $this->guard(); $this->verifyCsrf();
        $d = $this->medInput();
        if ($d['name']==='') $this->back('general/medicines','error','กรุณากรอกชื่อยา/เวชภัณฑ์');
        $id = $this->m->medicineCreate($d, Auth::id());
        AuditLog::record(Auth::id(),'create','medicines',$id);
        $this->back('general/medicines','success','เพิ่มรายการในคลังแล้ว');
    }

    public function medUpdate(string $id): void
    {
        $this->guard(); $this->verifyCsrf();
        if (!$this->m->medicineFind((int)$id)) $this->back('general/medicines','error','ไม่พบรายการ');
        $this->m->medicineUpdate((int)$id, $this->medInput());
        AuditLog::record(Auth::id(),'update','medicines',(int)$id);
        $this->back('general/medicines','success','แก้ไขข้อมูลแล้ว');
    }

    /** รับเข้า / จ่ายออก / ปรับยอดคงเหลือ */
    public function medStock(string $id): void
    {
        $this->guard(); $this->verifyCsrf();
        $type = (string)Request::input('movement_type','in');
        $qty  = (int)Request::input('qty',0);
        $note = (string)Request::input('note','');
        if (!in_array($type,['in','out','adjust'],true)) $this->back('general/medicines','error','ประเภทรายการไม่ถูกต้อง');
        if ($qty < 0) $this->back('general/medicines','error','จำนวนไม่ถูกต้อง');
        $ok = $this->m->stockMove((int)$id, $type, $qty, $note, Auth::id());
        AuditLog::record(Auth::id(),'update','medicines',(int)$id,null,['stock'=>$type,'qty'=>$qty]);
        $label = ['in'=>'รับเข้า','out'=>'จ่ายออก','adjust'=>'ปรับยอดคงเหลือ'][$type];
        $this->back('general/medicines', $ok?'success':'error',
            $ok ? ($label.' เรียบร้อย') : 'สต็อกไม่พอสำหรับจ่ายออก');
    }

    public function medDelete(string $id): void
    {
        $this->guard(); $this->verifyCsrf();
        $this->m->medicineDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','medicines',(int)$id);
        $this->back('general/medicines','success','ลบรายการในคลังแล้ว');
    }

    /** พิมพ์ทะเบียนคลังยา/เวชภัณฑ์ */
    public function medPrint(): void
    {
        $this->guard();
        $f = ['q'=>trim((string)Request::input('q','')),'cat'=>(string)Request::input('cat',''),
              'low'=>(string)Request::input('low','')];
        $this->view('nurse/medicines_print', [
            'title'=>'ทะเบียนคลังยา/เวชภัณฑ์',
            'rows'=>$this->m->medicines(array_filter($f)),
            'stock'=>$this->m->stockSummary(),
            'backUrl'=>'general/medicines','autoPrint'=>true,
        ], 'print');
    }

    private function medInput(): array
    {
        return [
            'code'=>(string)Request::input('code',''),
            'name'=>trim((string)Request::input('name','')),
            'category'=>(string)Request::input('category','medicine'),
            'unit'=>(string)Request::input('unit','เม็ด'),
            'stock_qty'=>(int)Request::input('stock_qty',0),
            'min_qty'=>(int)Request::input('min_qty',0),
            'expiry_date'=>(string)Request::input('expiry_date',''),
            'note'=>(string)Request::input('note',''),
            'active'=>Request::input('active') !== null ? 1 : 0,
        ];
    }
}
