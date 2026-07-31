<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Procurement;
use App\Models\AuditLog;

class ProcurementController extends Controller
{
    private Procurement $m;
    public function __construct(){ $this->m = new Procurement(); }

    // ---------- ขอซื้อ/ขอจ้าง (PR) ----------
    public function purchase(): void
    {
        $this->authorize('budget.purchase');
        $status=(string)Request::input('status',''); $type=(string)Request::input('type','');
        $this->view('procurement/purchase',[
            'title'=>'ขอซื้อ/ขอจ้าง (ตามระเบียบพัสดุ 2560)',
            'rows'=>$this->m->prList($status,$type),'summary'=>$this->m->summary(),
            'budgets'=>$this->m->budgetsList(),'projects'=>$this->m->projectsList(),'people'=>$this->m->personnelList(),
            'activitiesAll'=>$this->m->activitiesAll(),
            'status'=>$status,'type'=>$type,'canApprove'=>Auth::can('budget.approve'),
        ]);
    }
    public function prStore(): void
    {
        $this->authorize('budget.purchase');
        $this->verifyCsrf();
        $names=(array)Request::input('item_name',[]);
        $qtys=(array)Request::input('item_qty',[]);
        $units=(array)Request::input('item_unit',[]);
        $prices=(array)Request::input('item_price',[]);
        $items=[]; $total=0;
        foreach($names as $i=>$n){
            $n=trim((string)$n); if($n==='') continue;
            $qty=(float)($qtys[$i]??1); $price=(float)($prices[$i]??0);
            $amount=round($qty*$price,2); $total+=$amount;
            $items[]=['name'=>$n,'qty'=>$qty,'unit'=>trim((string)($units[$i]??'')),'price'=>$price,'amount'=>$amount];
        }
        if(!$items) $this->back('purchase','error','กรุณากรอกรายการอย่างน้อย 1 รายการ');
        $method=(string)Request::input('method','');
        if(!in_array($method,['specific','selection','e_bidding'],true)) $method=Procurement::suggestMethod($total);
        // ตรวจตามระเบียบ: เฉพาะเจาะจงวงเงินเกิน 500,000 ไม่ได้
        if($method==='specific' && $total > Procurement::SPECIFIC_LIMIT){
            $this->back('purchase','error','วงเงิน '.number_format($total,2).' บาท เกิน 500,000 — วิธีเฉพาะเจาะจงใช้ไม่ได้ตามระเบียบ (เลือกคัดเลือก/e-bidding)');
        }
        // ตัดงบระดับกิจกรรมย่อยแบบ งป.01: ห้ามขอเกินงบคงเหลือของกิจกรรม/โครงการที่ผูก
        $projectId=(int)Request::input('project_id',0);
        $activityId=(int)Request::input('activity_id',0);
        if($projectId || $activityId){
            $avail=$this->m->availableBudget($projectId,$activityId);
            if($avail!==null && $total > $avail + 0.005){
                $scope=$activityId?'กิจกรรมย่อย':'โครงการ';
                $this->back('purchase','error','วงเงิน '.number_format($total,2).' บาท เกินงบคงเหลือของ'.$scope.' ('.number_format($avail,2).' บาท) — ตัดงบไม่ได้');
            }
        }
        $d=Request::only(['budget_id','project_id','activity_id','request_type','requester_id','reason']);
        $d['request_type']=in_array($d['request_type'],['purchase','hire'],true)?$d['request_type']:'purchase';
        $d['method']=$method; $d['total']=$total;
        $id=$this->m->prCreate($d,$items);
        AuditLog::record(Auth::id(),'create','purchase_requests',$id,null,['total'=>$total,'method'=>$method]);
        $this->back('purchase','success','บันทึกขอซื้อ/ขอจ้างเรียบร้อย (วิธี'.Procurement::methodLabel()[$method].' วงเงิน '.number_format($total,2).' บาท)');
    }
    public function prShow(string $id): void
    {
        $this->authorize('budget.purchase');
        $pr=$this->m->prFind((int)$id);
        if(!$pr) $this->back('purchase','error','ไม่พบใบขอซื้อ');
        $this->view('procurement/pr_show',[
            'title'=>'ใบขอซื้อ/ขอจ้าง '.$pr['pr_number'],
            'pr'=>$pr,'items'=>$this->m->prItems((int)$id),
            'canApprove'=>Auth::can('budget.approve'),'vendors'=>$this->m->vendorsList(),
        ]);
    }
    public function prDecide(string $id): void
    {
        $this->authorize('budget.approve');
        $this->verifyCsrf();
        $pr=$this->m->prFind((int)$id);
        if(!$pr || $pr['status']!=='pending') $this->back('purchase','error','สถานะไม่ถูกต้อง');
        $status=Request::input('decision')==='approve'?'approved':'rejected';
        $this->m->prDecide((int)$id,$status,Auth::id(),(string)Request::input('note',''));
        AuditLog::record(Auth::id(),$status==='approved'?'approve':'reject','purchase_requests',(int)$id);
        $this->back('purchase/'.$id,'success',$status==='approved'?'อนุมัติแล้ว — สร้างใบสั่งซื้อ (PO) ต่อได้':'ไม่อนุมัติคำขอแล้ว');
    }

    // ---------- Dashboard งบประมาณ ----------
    public function budgetDashboard(): void
    {
        $this->authorize('budget.report');
        $this->view('procurement/dashboard',[
            'title'=>'Dashboard งบประมาณและการเบิกจ่าย',
            'totals'=>$this->m->budgetTotals(),
            'byDept'=>$this->m->spentByDepartment(),
            'byProject'=>$this->m->spentByProject(),
            'monthly'=>$this->m->paidMonthly(),
            'recent'=>$this->m->recentPaid(),
        ]);
    }

    public function prPrint(string $id): void
    {
        $this->authorize('budget.purchase');
        $pr=$this->m->prFind((int)$id);
        if(!$pr) $this->back('purchase','error','ไม่พบใบขอซื้อ');
        $this->view('procurement/pr_print',[
            'title'=>'ใบขอซื้อ '.$pr['pr_number'],'pr'=>$pr,'items'=>$this->m->prItems((int)$id),
            'backUrl'=>'purchase/'.$id,'autoPrint'=>true,
        ],'print');
    }

    // ---------- ใบสั่งซื้อ/สั่งจ้าง (PO) ----------
    public function po(): void
    {
        $this->authorize('budget.po');
        $status=(string)Request::input('status','');
        $this->view('procurement/po',[
            'title'=>'ใบสั่งซื้อ/สั่งจ้าง (PO)',
            'rows'=>$this->m->poList($status),'vendors'=>$this->m->vendorsList(),
            'approvedPrs'=>$this->m->approvedPrs(),'status'=>$status,
        ]);
    }
    public function poStore(): void
    {
        $this->authorize('budget.po');
        $this->verifyCsrf();
        $prId=(int)Request::input('pr_id',0); $vendorId=(int)Request::input('vendor_id',0);
        $pr=$this->m->prFind($prId);
        if(!$pr || $pr['status']!=='approved') $this->back('po','error','เลือกใบขอซื้อที่อนุมัติแล้วเท่านั้น');
        if(!$vendorId) $this->back('po','error','กรุณาเลือกผู้ขาย/ผู้รับจ้าง');
        $vat=round((float)$pr['total_amount']*0.07,2);
        $poId=$this->m->poCreateFromPr($prId,$vendorId,(float)Request::input('vat',$vat));
        AuditLog::record(Auth::id(),'create','purchase_orders',$poId,null,['pr'=>$pr['pr_number']]);
        $this->back('po/'.$poId,'success','สร้างใบสั่งซื้อจาก '.$pr['pr_number'].' เรียบร้อยแล้ว');
    }
    public function poShow(string $id): void
    {
        $this->authorize('budget.po');
        $po=$this->m->poFind((int)$id);
        if(!$po) $this->back('po','error','ไม่พบใบสั่งซื้อ');
        $this->view('procurement/po_show',[
            'title'=>'ใบสั่งซื้อ '.$po['po_number'],
            'po'=>$po,'items'=>$this->m->poItems((int)$id),
        ]);
    }
    public function poPrint(string $id): void
    {
        $this->authorize('budget.po');
        $po=$this->m->poFind((int)$id);
        if(!$po) $this->back('po','error','ไม่พบใบสั่งซื้อ');
        $this->view('procurement/po_print',[
            'title'=>'ใบสั่งซื้อ '.$po['po_number'],'po'=>$po,'items'=>$this->m->poItems((int)$id),
            'school'=>$this->m->schoolInfo(),'director'=>$this->m->director(),
            'backUrl'=>'po/'.$id,'autoPrint'=>true,
        ],'print');
    }

    /** เอกสารล้างหนี้/ตรวจรับ (ส่งมอบงาน · ตรวจรับพัสดุ · รายงานผลตรวจรับ+อนุมัติเบิกจ่าย) */
    public function poSettlementPrint(string $id): void
    {
        $this->authorize('budget.po');
        $po=$this->m->poFind((int)$id);
        if(!$po) $this->back('po','error','ไม่พบใบสั่งซื้อ');
        $this->view('procurement/po_settlement_print',[
            'title'=>'เอกสารล้างหนี้/ตรวจรับ '.$po['po_number'],
            'po'=>$po,'items'=>$this->m->poItems((int)$id),
            'school'=>$this->m->schoolInfo(),
            'director'=>$this->m->director(),
            'finance'=>$this->m->financeOfficer(),
            'supply'=>$this->m->supplyHead(),
            'backUrl'=>'po/'.$id,'autoPrint'=>true,
        ],'print');
    }

    public function poReceive(string $id): void
    {
        $this->authorize('budget.po');
        $this->verifyCsrf();
        $committee=trim((string)Request::input('committee',''));
        if($committee==='') $this->back('po/'.$id,'error','กรุณาระบุคณะกรรมการตรวจรับ');
        $this->m->poReceive((int)$id,$committee,(string)Request::input('received_date',date('Y-m-d')),(string)Request::input('note',''));
        AuditLog::record(Auth::id(),'receive','purchase_orders',(int)$id);
        $this->back('po/'.$id,'success','บันทึกการตรวจรับพัสดุแล้ว');
    }
    public function poPay(string $id): void
    {
        $this->authorize('budget.po');
        $this->verifyCsrf();
        if(!$this->m->poPay((int)$id, Auth::id())) $this->back('po/'.$id,'error','ต้องตรวจรับก่อนจึงเบิกจ่ายได้');
        AuditLog::record(Auth::id(),'pay','purchase_orders',(int)$id);
        $this->back('po/'.$id,'success','เบิกจ่ายเรียบร้อย — ตัดยอดงบประมาณแล้ว');
    }
    public function poCancel(string $id): void
    {
        $this->authorize('budget.po');
        $this->verifyCsrf();
        $this->m->poCancel((int)$id, (string)Request::input('reason',''), Auth::id());
        AuditLog::record(Auth::id(),'cancel','purchase_orders',(int)$id);
        $this->back('po','success','ยกเลิกใบสั่งซื้อแล้ว');
    }

    /** คืนเงินบางส่วนของ PO ที่จ่ายแล้ว → ยอดกลับเข้าโครงการนั้น */
    public function poRefund(string $id): void
    {
        $this->authorize('budget.po');
        $this->verifyCsrf();
        $amt=(float)Request::input('amount',0);
        $reason=(string)Request::input('reason','');
        $ok=$this->m->poRefund((int)$id,$amt,$reason,Auth::id());
        AuditLog::record(Auth::id(),'refund','purchase_orders',(int)$id,null,['amount'=>$amt]);
        $this->back('po/'.$id, $ok?'success':'error', $ok?'คืนเงินเข้าโครงการเรียบร้อย':'คืนเงินไม่ได้ (ต้องจ่ายแล้ว และยอดไม่เกินที่ตัดไป)');
    }
}
