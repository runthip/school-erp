<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Planning;
use App\Models\AuditLog;

class PlanningController extends Controller
{
    private Planning $m;
    public function __construct(){ $this->m = new Planning(); }

    // ---------- โครงการ + กิจกรรมย่อย ----------
    public function index(): void
    {
        $this->authorize('budget.planning');
        $dept=(int)Request::input('dept',0);
        $this->view('planning/index',[
            'title'=>'แผนงาน/โครงการ',
            'rows'=>$this->m->projects($dept?:null),
            'depts'=>$this->m->projectDepartments(),
            'dept'=>$dept,
        ]);
    }
    public function project(string $id): void
    {
        $this->authorize('budget.planning');
        $p=$this->m->projectFind((int)$id);
        if(!$p) $this->back('planning','error','ไม่พบโครงการ');
        $this->view('planning/project',[
            'title'=>$p['name'],'p'=>$p,'activities'=>$this->m->activities((int)$id),
        ]);
    }
    public function activityStore(string $id): void
    {
        $this->authorize('budget.planning');
        $this->verifyCsrf();
        $name=trim((string)Request::input('name',''));
        if($name==='') $this->back('planning/'.$id,'error','กรุณากรอกชื่อกิจกรรม');
        $this->m->activityAdd((int)$id,$name,(float)Request::input('budget_amount',0),
            Request::input('start_date'),Request::input('end_date'));
        AuditLog::record(Auth::id(),'create','project_activities',(int)$id);
        $this->back('planning/'.$id,'success','เพิ่มกิจกรรมย่อยแล้ว');
    }
    public function activityDelete(string $id, string $aid): void
    {
        $this->authorize('budget.planning');
        $this->verifyCsrf();
        $this->m->activityDelete((int)$aid);
        $this->back('planning/'.$id,'success','ลบกิจกรรมแล้ว');
    }

    // ---------- เบิกงบ ----------
    public function requests(): void
    {
        // ── ยุบรวม: การเบิกงบใช้ งป.01 เป็นทางเดียว ──
        // ยังเปิดให้ดูรายการเดิมได้ แต่การสร้างใหม่ให้ไปที่ งป.01
        $this->authorize('budget.planning');
        if (\App\Core\Request::input('legacy','') !== '1') {
            $this->back('budget-memo', 'success', 'ระบบเบิกจ่ายย้ายไปที่ "บันทึกขอเบิก (งป.01)" แล้ว — ดูรายการเดิมได้ที่ planning/requests?legacy=1');
        }
        $this->authorize('budget.planning');
        $this->view('planning/requests',[
            'title'=>'เบิกงบประมาณ','rows'=>$this->m->requests((string)Request::input('status','')),
            'status'=>(string)Request::input('status',''),
            'projects'=>$this->m->projects(),'activitiesAll'=>$this->m->activitiesAll(),
            'levels'=>Planning::levels(),
        ]);
    }
    public function requestStore(): void
    {
        $this->authorize('budget.planning');
        $this->verifyCsrf();
        $amount=(float)Request::input('amount',0);
        $purpose=trim((string)Request::input('purpose',''));
        if($amount<=0 || $purpose==='') $this->back('planning/requests','error','กรุณากรอกจำนวนเงินและวัตถุประสงค์');
        $id=$this->m->requestCreate((int)Request::input('project_id',0)?:null,(int)Request::input('activity_id',0)?:null,
            Auth::id(),$amount,$purpose);
        AuditLog::record(Auth::id(),'create','budget_requests',$id);
        $this->back('planning/requests','success','ส่งคำขอเบิกงบแล้ว — รออนุมัติระดับที่ 1');
    }
    public function requestShow(string $id): void
    {
        $this->authorize('budget.planning');
        $r=$this->m->requestFind((int)$id);
        if(!$r) $this->back('planning/requests','error','ไม่พบคำขอ');
        $this->view('planning/request_show',[
            'title'=>'คำขอเบิกงบ '.$r['request_no'],'r'=>$r,'type'=>'budget_request',
            'logs'=>$this->m->approvalLogs('budget_request',(int)$id),'levels'=>Planning::levels(),
            'canApprove'=>Auth::can('budget.approve'),
        ]);
    }
    public function requestDecide(string $id): void
    {
        $this->authorize('budget.approve');
        $this->verifyCsrf();
        $st=$this->m->decide('budget_request',(int)$id,(bool)Request::input('approve'),Auth::id(),(string)Request::input('comment',''));
        AuditLog::record(Auth::id(),'approve','budget_requests',(int)$id,null,['result'=>$st]);
        $msg=['pending'=>'อนุมัติแล้ว — ส่งต่อระดับถัดไป','approved'=>'อนุมัติครบทุกระดับแล้ว (พร้อมเบิกจ่าย)','rejected'=>'ไม่อนุมัติ'][$st]??$st;
        $this->back('planning/requests/'.$id, $st==='rejected'?'error':'success', $msg);
    }
    public function requestPay(string $id): void
    {
        $this->authorize('budget.approve');
        $this->verifyCsrf();
        $ok=$this->m->payRequest((int)$id, Auth::id());
        AuditLog::record(Auth::id(),'pay','budget_requests',(int)$id);
        $this->back('planning/requests/'.$id, $ok?'success':'error', $ok?'เบิกจ่ายแล้ว — ตัดงบโครงการ/กิจกรรม/งบประมาณอัตโนมัติ':'สถานะไม่ถูกต้อง');
    }

    // ---------- ยืมเงิน ----------
    public function advances(): void
    {
        $this->authorize('budget.planning');
        $this->view('planning/advances',[
            'title'=>'ยืมเงินราชการ','rows'=>$this->m->advances((string)Request::input('status','')),
            'status'=>(string)Request::input('status',''),
            'projects'=>$this->m->projects(),'activitiesAll'=>$this->m->activitiesAll(),
        ]);
    }
    public function advanceStore(): void
    {
        $this->authorize('budget.planning');
        $this->verifyCsrf();
        $purpose=trim((string)Request::input('purpose',''));
        // รายการค่าใช้จ่ายย่อย (แบบ 8500) — ยอดรวมมาจากผลรวมรายการ
        $names=(array)Request::input('item_name',[]);
        $amts=(array)Request::input('item_amount',[]);
        $items=[]; $total=0;
        foreach($names as $i=>$n){
            $n=trim((string)$n); if($n==='') continue;
            $a=round((float)($amts[$i]??0),2); $total+=$a;
            $items[]=['name'=>$n,'amount'=>$a];
        }
        // เผื่อกรณีไม่กรอกรายการย่อย ให้ใช้ช่องจำนวนเงินรวมเดิม
        $amount = $total>0 ? $total : (float)Request::input('amount',0);
        if($amount<=0 || $purpose==='') $this->back('planning/advances','error','กรุณากรอกรายการ/จำนวนเงินและวัตถุประสงค์');
        $extra=[
            'borrower_position'=>trim((string)Request::input('borrower_position','')) ?: null,
            'lend_from'=>trim((string)Request::input('lend_from','')) ?: null,
            'repay_days'=>(int)Request::input('repay_days',30) ?: 30,
            'items'=>$items,
        ];
        $id=$this->m->advanceCreate((int)Request::input('project_id',0)?:null,(int)Request::input('activity_id',0)?:null,
            Auth::id(),$amount,$purpose,Request::input('due_date'),$extra);
        AuditLog::record(Auth::id(),'create','cash_advances',$id);
        $this->back('planning/advances','success','ส่งคำขอยืมเงินแล้ว — รออนุมัติระดับที่ 1');
    }
    public function advanceShow(string $id): void
    {
        $this->authorize('budget.planning');
        $r=$this->m->advanceFind((int)$id);
        if(!$r) $this->back('planning/advances','error','ไม่พบรายการ');
        $refundMemo = Auth::can('budget.refund_memo')
            ? (new \App\Models\AdvanceRefundMemo())->findByAdvance((int)$id) : null;
        $this->view('planning/advance_show',[
            'title'=>'ยืมเงิน '.$r['advance_no'],'r'=>$r,
            'items'=>$this->m->advanceItems((int)$id),
            'logs'=>$this->m->approvalLogs('cash_advance',(int)$id),'levels'=>Planning::levels(),
            'canApprove'=>Auth::can('budget.approve'),
            'refundMemo'=>$refundMemo,
        ]);
    }
    public function advanceDecide(string $id): void
    {
        $this->authorize('budget.approve');
        $this->verifyCsrf();
        $st=$this->m->decide('cash_advance',(int)$id,(bool)Request::input('approve'),Auth::id(),(string)Request::input('comment',''));
        AuditLog::record(Auth::id(),'approve','cash_advances',(int)$id,null,['result'=>$st]);
        $msg=['pending'=>'อนุมัติแล้ว — ส่งต่อระดับถัดไป','approved'=>'อนุมัติครบทุกระดับแล้ว (พร้อมจ่าย)','rejected'=>'ไม่อนุมัติ'][$st]??$st;
        $this->back('planning/advances/'.$id, $st==='rejected'?'error':'success', $msg);
    }
    public function advancePay(string $id): void
    {
        $this->authorize('budget.approve');
        $this->verifyCsrf();
        $ok=$this->m->payAdvance((int)$id, Auth::id());
        AuditLog::record(Auth::id(),'pay','cash_advances',(int)$id);
        $this->back('planning/advances/'.$id, $ok?'success':'error', $ok?'จ่ายเงินยืมแล้ว — ตัดงบอัตโนมัติ (ล้างหนี้เมื่อส่งใช้)':'สถานะไม่ถูกต้อง');
    }
    public function advanceClear(string $id): void
    {
        $this->authorize('budget.planning');
        $this->verifyCsrf();
        $used=(float)Request::input('used_amount',0);
        $ok=$this->m->clearAdvance((int)$id,$used, Auth::id());
        AuditLog::record(Auth::id(),'clear','cash_advances',(int)$id,null,['used'=>$used]);
        $this->back('planning/advances/'.$id, $ok?'success':'error', $ok?'ล้างหนี้เรียบร้อย — คืนส่วนต่างเข้างบอัตโนมัติ':'ต้องอยู่สถานะจ่ายแล้วเท่านั้น');
    }

    // ---------- รายงานผู้บริหาร Real-time ----------
    public function execReport(): void
    {
        $this->authorize('budget.report');
        $this->view('planning/exec',['title'=>'รายงานผู้บริหาร (Real-time)','d'=>$this->m->execReport()]);
    }
    public function execData(): void
    {
        $this->authorize('budget.report');
        $this->json($this->m->execReport());
    }

    // ---------- พิมพ์เอกสาร ----------
    public function requestPrint(string $id): void
    {
        $this->authorize('budget.planning');
        $r=$this->m->requestFind((int)$id);
        if(!$r) $this->back('planning/requests','error','ไม่พบคำขอ');
        $this->view('planning/request_print',[
            'title'=>'ใบเบิกงบประมาณ '.$r['request_no'],'r'=>$r,
            'logs'=>$this->m->approvalLogs('budget_request',(int)$id),'levels'=>Planning::levels(),
            'backUrl'=>'planning/requests/'.$id,'autoPrint'=>true,
        ],'print');
    }
    public function advancePrint(string $id): void
    {
        $this->authorize('budget.planning');
        $r=$this->m->advanceFind((int)$id);
        if(!$r) $this->back('planning/advances','error','ไม่พบรายการ');
        $this->view('planning/advance_print',[
            'title'=>'สัญญายืมเงินราชการ '.$r['advance_no'],'r'=>$r,
            'items'=>$this->m->advanceItems((int)$id),
            'signers'=>$this->m->advanceSigners(),
            'logs'=>$this->m->approvalLogs('cash_advance',(int)$id),'levels'=>Planning::levels(),
            'backUrl'=>'planning/advances/'.$id,'autoPrint'=>true,
        ],'print');
    }

    // ---------- ยกเลิก / คืนเงิน / ลบ ----------
    public function requestCancel(string $id): void
    {
        $this->authorize('budget.planning'); $this->verifyCsrf();
        $reason=(string)Request::input('reason','');
        $ok=$this->m->cancelRequest((int)$id,$reason,Auth::id());
        AuditLog::record(Auth::id(),'cancel','budget_requests',(int)$id,null,['reason'=>$reason]);
        $this->back('planning/requests', $ok?'success':'error', $ok?'ยกเลิกใบเบิกและคืนเงินเข้าโครงการแล้ว':'ยกเลิกไม่ได้');
    }
    public function requestRefund(string $id): void
    {
        $this->authorize('budget.planning'); $this->verifyCsrf();
        $amt=(float)Request::input('amount',0);
        $reason=(string)Request::input('reason','');
        $ok=$this->m->refundRequest((int)$id,$amt,$reason,Auth::id());
        AuditLog::record(Auth::id(),'refund','budget_requests',(int)$id,null,['amount'=>$amt]);
        $this->back('planning/requests/'.$id, $ok?'success':'error', $ok?'คืนเงินเข้าโครงการเรียบร้อย':'คืนเงินไม่ได้ (ตรวจสอบยอด)');
    }
    public function requestDelete(string $id): void
    {
        $this->authorize('budget.planning'); $this->verifyCsrf();
        $ok=$this->m->requestDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','budget_requests',(int)$id);
        $this->back('planning/requests', $ok?'success':'error', $ok?'ลบใบเบิกแล้ว':'ใบเบิกที่จ่ายแล้วต้องใช้ "ยกเลิก+คืนเงิน"');
    }
    public function advanceDelete(string $id): void
    {
        $this->authorize('budget.planning'); $this->verifyCsrf();
        $ok=$this->m->advanceDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','cash_advances',(int)$id);
        $this->back('planning/advances', $ok?'success':'error', $ok?'ลบสัญญายืมแล้ว':'ที่จ่าย/ล้างหนี้แล้วลบไม่ได้');
    }

    // ---------- ติดตามโครงการ ----------
    public function tracking(): void
    {
        $this->authorize('admin.projects');
        $f=['status'=>Request::input('status',''),'dept'=>Request::input('dept',''),
            'group'=>Request::input('group',''),'q'=>Request::input('q','')];
        $rows=$this->m->tracking(array_filter($f));
        $this->view('planning/tracking', ['title'=>'ติดตามโครงการ','rows'=>$rows,'f'=>$f,
            'sum'=>$this->m->trackingSummary($rows),'depts'=>$this->m->departments(),
            'groups'=>$this->m->subjectGroups(),
            'budgets'=>$this->m->budgetsList(),'people'=>$this->m->personnelList()]);
    }
    public function trackingPrint(): void
    {
        $this->authorize('admin.projects');
        $f=['status'=>Request::input('status',''),'dept'=>Request::input('dept',''),
            'group'=>Request::input('group',''),'q'=>Request::input('q','')];
        $rows=$this->m->tracking(array_filter($f));
        $this->view('planning/tracking_print', ['title'=>'รายงานติดตามโครงการ','rows'=>$rows,
            'sum'=>$this->m->trackingSummary($rows),'backUrl'=>'projects','autoPrint'=>true],'print');
    }
    public function projectStore(): void
    {
        $this->authorize('admin.projects'); $this->verifyCsrf();
        $d=$this->projectInput();
        if($d['name']==='') $this->back('projects','error','กรอกชื่อโครงการ');
        $id=$this->m->projectCreate($d);
        AuditLog::record(Auth::id(),'create','projects',$id);
        $this->back('projects','success','เพิ่มโครงการเรียบร้อย');
    }
    public function projectUpdate(string $id): void
    {
        $this->authorize('admin.projects'); $this->verifyCsrf();
        $this->m->projectUpdate((int)$id,$this->projectInput());
        AuditLog::record(Auth::id(),'update','projects',(int)$id);
        $this->back('projects','success','แก้ไขโครงการแล้ว');
    }
    public function projectDelete(string $id): void
    {
        $this->authorize('admin.projects'); $this->verifyCsrf();
        $ok=$this->m->projectDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','projects',(int)$id);
        $this->back('projects', $ok?'success':'error', $ok?'ลบโครงการแล้ว':'ลบไม่ได้ — โครงการนี้มีการใช้จ่ายงบแล้ว');
    }
    public function projectProgress(string $id): void
    {
        $this->authorize('admin.projects'); $this->verifyCsrf();
        $this->m->projectProgress((int)$id,(int)Request::input('progress_percent',0),(string)Request::input('status',''));
        AuditLog::record(Auth::id(),'update','projects',(int)$id,null,['progress'=>Request::input('progress_percent')]);
        $this->back('projects','success','อัปเดตความคืบหน้าแล้ว');
    }

    public function projectApprove(string $id): void
    {
        $this->authorize('budget.memo_approve'); $this->verifyCsrf();
        $ok = \App\Core\Request::input('decision','ok') === 'ok';
        $reason = trim((string)\App\Core\Request::input('reason',''));
        $this->m->approveProject((int)$id, $ok, $reason ?: null, \App\Core\Auth::id());
        \App\Models\AuditLog::record(\App\Core\Auth::id(), $ok?'approve':'reject', 'projects', (int)$id, null, ['reason'=>$reason]);
        $this->back('projects', 'success', $ok?'อนุมัติโครงการแล้ว — พร้อมเบิกจ่าย':'ไม่อนุมัติโครงการ');
    }

    /** เอกสารอัตโนมัติ 1: แบบเสนอโครงการ (PDF) */
    public function projectProposal(string $id): void
    {
        $this->authorize('budget.planning');
        $p = $this->m->projectFind((int)$id);
        if (!$p) $this->back('projects','error','ไม่พบโครงการ');
        $this->view('planning/proposal_print', [
            'title' => 'แบบเสนอโครงการ '.($p['name']??''),
            'p' => $p, 'activities' => $this->m->activities((int)$id),
            'autoPrint' => true,
        ], 'print');
    }

    /** เอกสารอัตโนมัติ 5: รายงานผลการดำเนินงานโครงการ (PDF) */
    public function projectResult(string $id): void
    {
        $this->authorize('budget.planning');
        $p = $this->m->projectFind((int)$id);
        if (!$p) $this->back('projects','error','ไม่พบโครงการ');
        $this->view('planning/result_print', [
            'title' => 'รายงานผลการดำเนินงาน '.($p['name']??''),
            'p' => $p, 'activities' => $this->m->activities((int)$id),
            'autoPrint' => true,
        ], 'print');
    }
    private function projectInput(): array
    {
        return ['code'=>trim((string)Request::input('code','')),'name'=>trim((string)Request::input('name','')),
            'budget_id'=>(int)Request::input('budget_id',0),'department_id'=>(int)Request::input('department_id',0),
            'subject_group_id'=>(int)Request::input('subject_group_id',0),
            'responsible_id'=>(int)Request::input('responsible_id',0),
            'budget_amount'=>(float)Request::input('budget_amount',0),
            'start_date'=>(string)Request::input('start_date',''),'end_date'=>(string)Request::input('end_date',''),
            'status'=>in_array(Request::input('status'),['planned','ongoing','completed','cancelled'],true)?Request::input('status'):'planned',
            'progress_percent'=>(int)Request::input('progress_percent',0)];
    }

    // ---------- บัญชีคุมงบประมาณ (Ledger) ----------
    public function ledger(): void
    {
        $this->authorize('budget.ledger');
        $l=new \App\Models\BudgetLedger();
        $f=['type'=>Request::input('type',''),'direction'=>Request::input('direction',''),
            'project'=>Request::input('project',''),'from'=>Request::input('from',''),'to'=>Request::input('to','')];
        $ff=array_filter($f);
        $this->view('planning/ledger', ['title'=>'บัญชีคุมงบประมาณ',
            'rows'=>$l->entries($ff),'tot'=>$l->totals($ff),'f'=>$f,
            'projects'=>$l->projectsList(),'recon'=>$l->reconcile()]);
    }
    public function ledgerPrint(): void
    {
        $this->authorize('budget.ledger');
        $l=new \App\Models\BudgetLedger();
        $f=['type'=>Request::input('type',''),'direction'=>Request::input('direction',''),
            'project'=>Request::input('project',''),'from'=>Request::input('from',''),'to'=>Request::input('to','')];
        $ff=array_filter($f);
        $this->view('planning/ledger_print', ['title'=>'รายงานบัญชีคุมงบประมาณ',
            'rows'=>$l->entries($ff),'tot'=>$l->totals($ff),'f'=>$f,
            'backUrl'=>'planning/ledger','autoPrint'=>true],'print');
    }
}
