<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Substitution;
use App\Models\AuditLog;

/**
 * จัดสอนแทน — จัดครูสอนแทนในวันที่ครูลา โดยเน้นครูที่มีคาบสอนน้อยเป็นหลัก
 */
class SubstituteController extends Controller
{
    private Substitution $m;
    public function __construct(){ $this->m = new Substitution(); }

    private function guard(): void { $this->authorize('academic.schedule'); }

    public function index(): void
    {
        $this->guard();
        $date=(string)Request::input('date', date('Y-m-d'));
        $dow=(int)date('N', strtotime($date));
        $rows=$this->m->coverage($date);
        // เติมรายชื่อครูแนะนำ (คาบน้อยก่อน) ให้แต่ละคาบที่ยังไม่ได้จัด
        foreach($rows as &$r){
            $r['candidates']= empty($r['sub_id'])
                ? $this->m->candidates($date,$dow,(int)$r['period_no'],(int)$r['absent_id'])
                : [];
        }
        unset($r);
        $pendingCount=0; $approvedCount=0;
        foreach($rows as $r){ if(!empty($r['sub_id'])){ ($r['status']??'assigned')==='approved' ? $approvedCount++ : $pendingCount++; } }
        $this->view('substitute/index', [
            'title'=>'จัดสอนแทน',
            'date'=>$date,'dow'=>$dow,
            'onLeave'=>$this->m->teachersOnLeave($date),
            'rows'=>$rows,
            'isWeekend'=>($dow>=6),
            'canApprove'=>Auth::can('academic.curriculum'),
            'pendingCount'=>$pendingCount,'approvedCount'=>$approvedCount,
        ]);
    }

    /** อนุมัติการจัดสอนแทน + แจ้งเตือนครูสอนแทน (หัวหน้าฝ่าย/แอดมินวิชาการ) */
    public function approve(): void
    {
        $this->authorize('academic.curriculum'); $this->verifyCsrf();
        $date=(string)Request::input('date', date('Y-m-d'));
        $res=$this->m->approve($date, Auth::id());
        AuditLog::record(Auth::id(),'approve','substitute_teachings',0,null,['date'=>$date,'approved'=>$res['approved']]);
        if($res['approved']===0) $this->back('substitute?date='.$date,'error','ไม่มีรายการรออนุมัติ');
        $this->back('substitute?date='.$date,'success','อนุมัติ '.$res['approved'].' คาบ และแจ้งเตือนครูสอนแทน '.$res['notified'].' คนแล้ว');
    }

    public function assign(): void
    {
        $this->guard(); $this->verifyCsrf();
        $date=(string)Request::input('date', date('Y-m-d'));
        $sched=(int)Request::input('schedule_id',0);
        $sub=(int)Request::input('sub_teacher_id',0);
        $absent=(int)Request::input('absent_id',0);
        if(!$sched || !$sub) $this->back('substitute?date='.$date,'error','กรุณาเลือกครูสอนแทน');
        $this->m->assign($sched,$date,$absent,$sub,trim((string)Request::input('note','')) ?: null, Auth::id());
        AuditLog::record(Auth::id(),'assign','substitute_teachings',$sched,null,['date'=>$date,'sub'=>$sub]);
        $this->back('substitute?date='.$date,'success','จัดครูสอนแทนแล้ว');
    }

    public function remove(): void
    {
        $this->guard(); $this->verifyCsrf();
        $date=(string)Request::input('date', date('Y-m-d'));
        $this->m->remove((int)Request::input('schedule_id',0), $date);
        $this->back('substitute?date='.$date,'success','ยกเลิกการจัดสอนแทนแล้ว');
    }

    public function auto(): void
    {
        $this->guard(); $this->verifyCsrf();
        $date=(string)Request::input('date', date('Y-m-d'));
        $n=$this->m->autoAssign($date, Auth::id());
        AuditLog::record(Auth::id(),'assign','substitute_teachings',0,null,['auto'=>true,'date'=>$date,'n'=>$n]);
        $this->back('substitute?date='.$date, $n>0?'success':'error',
            $n>0?"จัดสอนแทนอัตโนมัติ $n คาบ (เลือกครูคาบสอนน้อยสุดที่ว่าง)":'ไม่มีคาบที่จัดเพิ่มได้ — อาจจัดครบแล้วหรือไม่มีครูว่าง');
    }
}
