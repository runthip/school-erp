<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Hr;
use App\Models\AuditLog;

class HrController extends Controller
{
    private Hr $m;
    public function __construct(){ $this->m = new Hr(); }

    // ---------- แดชบอร์ด HR ----------
    public function dashboard(): void
    {
        $this->authorize('hr.personnel');
        $this->view('hr/dashboard', ['title'=>'ภาพรวมงานบุคคล','d'=>$this->m->dashboard()]);
    }

    // ---------- การลา ----------
    public function leaves(): void
    {
        $this->authorize('hr.leave');
        $f=['status'=>Request::input('status',''),'person'=>Request::input('person','')];
        $this->view('hr/leaves', [
            'title'=>'การลา / มาสาย',
            'rows'=>$this->m->leaves(array_filter($f)),'f'=>$f,
            'people'=>$this->m->people(),'types'=>$this->m->leaveTypes(),
            'canApprove'=>Auth::can('hr.leave'),
        ]);
    }
    public function leaveStore(): void
    {
        $this->authorize('hr.leave');
        $this->verifyCsrf();
        $person=(int)Request::input('personnel_id',0);
        $type=(int)Request::input('leave_type_id',0);
        $start=(string)Request::input('start_date','');
        $end=(string)Request::input('end_date',$start);
        if(!$person||!$type||!$start) $this->back('hr/leaves','error','กรอกข้อมูลไม่ครบ');
        $id=$this->m->leaveCreate($person,$type,$start,$end?:$start,(string)Request::input('reason',''));
        AuditLog::record(Auth::id(),'create','leaves',$id);
        $this->back('hr/leaves','success','บันทึกใบลาเรียบร้อย (รออนุมัติ)');
    }
    public function leaveDecide(string $id): void
    {
        $this->authorize('hr.leave');
        $this->verifyCsrf();
        $status=Request::input('decision')==='approve'?'approved':'rejected';
        $this->m->leaveDecide((int)$id,$status,Auth::id());
        AuditLog::record(Auth::id(),$status==='approved'?'approve':'reject','leaves',(int)$id);
        $this->back('hr/leaves','success',$status==='approved'?'อนุมัติการลาแล้ว':'ไม่อนุมัติการลา');
    }
    public function leaveEdit(string $id): void
    {
        $this->authorize('hr.leave');
        $l=$this->m->leaveFind((int)$id);
        if(!$l) $this->back('hr/leaves','error','ไม่พบใบลา');
        $this->view('hr/leave_edit', ['title'=>'แก้ไขใบลา','l'=>$l,'people'=>$this->m->people(),'types'=>$this->m->leaveTypes()]);
    }
    public function leaveUpdate(string $id): void
    {
        $this->authorize('hr.leave');
        $this->verifyCsrf();
        $start=(string)Request::input('start_date','');
        $end=(string)Request::input('end_date',$start);
        $status=in_array(Request::input('status'),['pending','approved','rejected'],true)?Request::input('status'):'pending';
        $this->m->leaveUpdate((int)$id,(int)Request::input('personnel_id',0),(int)Request::input('leave_type_id',0),
            $start,$end?:$start,(string)Request::input('reason',''),$status);
        AuditLog::record(Auth::id(),'update','leaves',(int)$id);
        $this->back('hr/leaves','success','แก้ไขใบลาเรียบร้อย');
    }
    public function leaveDelete(string $id): void
    {
        $this->authorize('hr.leave');
        $this->verifyCsrf();
        $this->m->leaveDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','leaves',(int)$id);
        $this->back('hr/leaves','success','ลบใบลาแล้ว');
    }
    public function leavePrint(string $id): void
    {
        $this->authorize('hr.leave');
        $l=$this->m->leaveForPrint((int)$id);
        if(!$l) $this->back('hr/leaves','error','ไม่พบใบลา');
        $this->view('hr/leave_print', ['title'=>'ใบลา','l'=>$l,'backUrl'=>'hr/leaves','autoPrint'=>true],'print');
    }

    // ---------- ลงเวลาปฏิบัติงาน ----------
    public function attendance(): void
    {
        $this->authorize('hr.attendance');
        $date=(string)Request::input('date',date('Y-m-d'));
        $this->view('hr/attendance', [
            'title'=>'ลงเวลาปฏิบัติงาน',
            'date'=>$date,'people'=>$this->m->people(),'marks'=>$this->m->attendanceOfDate($date),
        ]);
    }
    public function attendanceSave(): void
    {
        $this->authorize('hr.attendance');
        $this->verifyCsrf();
        $date=(string)Request::input('date',date('Y-m-d'));
        $status=(array)Request::input('status',[]);
        $cin=(array)Request::input('check_in',[]);
        $cout=(array)Request::input('check_out',[]);
        $note=(array)Request::input('note',[]);
        $by=Auth::id();
        foreach($this->m->people() as $p){
            $pid=(int)$p['id'];
            $this->m->attendanceSave($pid,$date,(string)($status[$pid]??'present'),
                (string)($cin[$pid]??''),(string)($cout[$pid]??''),(string)($note[$pid]??''),$by);
        }
        AuditLog::record($by,'update','staff_attendance',0,null,['date'=>$date]);
        $this->back('hr/attendance?date='.$date,'success','บันทึกการลงเวลาเรียบร้อย');
    }

    // ---------- เงินเดือน ----------
    public function salary(): void
    {
        $this->authorize('hr.salary');
        $year=(int)Request::input('year',$this->m->currentYearBe());
        $month=(int)Request::input('month',$this->m->currentMonth());
        $this->view('hr/salary', [
            'title'=>'เงินเดือน / ค่าตอบแทน',
            'year'=>$year,'month'=>$month,'rows'=>$this->m->salaries($year,$month),
            'people'=>$this->m->people(),
        ]);
    }
    public function salaryStore(): void
    {
        $this->authorize('hr.salary');
        $this->verifyCsrf();
        $year=(int)Request::input('year',$this->m->currentYearBe());
        $month=(int)Request::input('month',$this->m->currentMonth());
        $person=(int)Request::input('personnel_id',0);
        if(!$person) $this->back('hr/salary','error','เลือกบุคลากร');
        $this->m->salaryUpsert($person,$year,$month,
            (float)Request::input('base_salary',0),(float)Request::input('allowance',0),
            (float)Request::input('deduction',0),(string)Request::input('note',''),Auth::id());
        AuditLog::record(Auth::id(),'update','salary_records',$person);
        $this->back('hr/salary?year='.$year.'&month='.$month,'success','บันทึกเงินเดือนเรียบร้อย');
    }
    public function payslip(string $id): void
    {
        $this->authorize('hr.salary');
        $s=$this->m->salaryFind((int)$id);
        if(!$s) $this->back('hr/salary','error','ไม่พบข้อมูล');
        $this->view('hr/payslip', ['title'=>'สลิปเงินเดือน','s'=>$s,'backUrl'=>'hr/salary','autoPrint'=>true],'print');
    }

    // ---------- PA / วิทยฐานะ ----------
    public function pa(): void
    {
        $this->authorize('hr.evaluation');
        $f=['year'=>Request::input('year',''),'type'=>Request::input('type',''),'person'=>Request::input('person','')];
        // หัวข้อ PA + ไฟล์ที่บุคลากรตั้งเอง (ประกอบการประเมิน) — กรองด้วยปี/บุคคลเดียวกัน
        $pt=new \App\Models\PaTopic();
        $topics=$pt->listAll(array_filter(['year'=>$f['year'],'person'=>$f['person']]));
        $topicFiles=$pt->filesForTopics(array_map(fn($t)=>(int)$t['id'],$topics));
        $this->view('hr/pa', [
            'title'=>'PA / วิทยฐานะ / ประเมิน',
            'rows'=>$this->m->paList(array_filter($f)),'f'=>$f,
            'people'=>$this->m->people(),'currentYear'=>$this->m->currentYearBe(),
            'topics'=>$topics,'topicFiles'=>$topicFiles,
        ]);
    }
    public function paStore(): void
    {
        $this->authorize('hr.evaluation');
        $this->verifyCsrf();
        $d=[
            'personnel_id'=>(int)Request::input('personnel_id',0),
            'year_be'=>(int)Request::input('year_be',$this->m->currentYearBe()),
            'round'=>(int)Request::input('round',1),
            'eval_type'=>in_array(Request::input('eval_type'),['performance','academic_standing'],true)?Request::input('eval_type'):'performance',
            'score'=>Request::input('score',''),
            'grade'=>(string)Request::input('grade',''),
            'target_standing'=>(string)Request::input('target_standing',''),
            'result'=>in_array(Request::input('result'),['pending','passed','failed'],true)?Request::input('result'):'pending',
            'comment'=>(string)Request::input('comment',''),
            'evaluator_id'=>Auth::id(),
            'eval_date'=>(string)Request::input('eval_date',date('Y-m-d')),
        ];
        if(!$d['personnel_id']) $this->back('hr/pa','error','เลือกบุคลากร');
        $id=$this->m->paCreate($d);
        AuditLog::record(Auth::id(),'create','pa_evaluations',$id);
        $this->back('hr/pa','success','บันทึกผลการประเมินเรียบร้อย');
    }
    public function paDelete(string $id): void
    {
        $this->authorize('hr.evaluation');
        $this->verifyCsrf();
        $this->m->paDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','pa_evaluations',(int)$id);
        $this->back('hr/pa','success','ลบผลการประเมินแล้ว');
    }

    // ---------- CRUD เพิ่มเติม ----------
    public function salaryDelete(string $id): void
    {
        $this->authorize('hr.salary');
        $this->verifyCsrf();
        $this->m->salaryDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','salary_records',(int)$id);
        $this->back('hr/salary','success','ลบรายการเงินเดือนแล้ว');
    }
    public function attendanceManage(): void
    {
        $this->authorize('hr.attendance');
        $year=(int)Request::input('year',$this->m->currentYearBe());
        $month=(int)Request::input('month',$this->m->currentMonth());
        $this->view('hr/attendance_manage', [
            'title'=>'จัดการบันทึกลงเวลา','year'=>$year,'month'=>$month,
            'rows'=>$this->m->attendanceList($year,$month),
        ]);
    }
    public function attendanceDelete(string $id): void
    {
        $this->authorize('hr.attendance');
        $this->verifyCsrf();
        $this->m->attendanceDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','staff_attendance',(int)$id);
        $this->back('hr/attendance/manage','success','ลบบันทึกลงเวลาแล้ว');
    }

    // ---------- SAR รายงานผลการปฏิบัติงาน ----------
    public function sar(): void
    {
        $this->authorize('hr.personnel');
        $year=(int)Request::input('year',$this->m->currentYearBe());
        $this->view('hr/sar', [
            'title'=>'SAR รายงานผลการปฏิบัติงาน','year'=>$year,'d'=>$this->m->sarData($year),
        ]);
    }
    public function sarPrint(): void
    {
        $this->authorize('hr.personnel');
        $year=(int)Request::input('year',$this->m->currentYearBe());
        $manual=[
            'vision'=>(string)Request::input('vision',''),
            'strengths'=>(string)Request::input('strengths',''),
            'improvements'=>(string)Request::input('improvements',''),
            'projects'=>(string)Request::input('projects',''),
            'awards'=>(string)Request::input('awards',''),
        ];
        $this->view('hr/sar_print', [
            'title'=>'รายงาน SAR ปี '.$year,'year'=>$year,'d'=>$this->m->sarData($year),'manual'=>$manual,
            'backUrl'=>'hr/sar','autoPrint'=>true,
        ],'print');
    }
}
