<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\General;
use App\Models\AuditLog;

class GeneralController extends Controller
{
    private General $m;
    public function __construct(){ $this->m = new General(); }

    public function dashboard(): void
    {
        $this->authorize('general.repair');
        $this->view('general/dashboard', ['title'=>'ภาพรวมบริหารทั่วไป','d'=>$this->m->dashboard()]);
    }

    // ---------- แจ้งซ่อม ----------
    public function repairs(): void
    {
        $this->authorize('general.repair');
        $f=['status'=>Request::input('status',''),'priority'=>Request::input('priority','')];
        $this->view('general/repairs', ['title'=>'แจ้งซ่อม / อาคารสถานที่','rows'=>$this->m->repairs(array_filter($f)),'f'=>$f,
            'rooms'=>$this->m->rooms(),'assets'=>$this->m->assets(),'people'=>$this->m->personnelList()]);
    }
    public function repairStore(): void
    {
        $this->authorize('general.repair'); $this->verifyCsrf();
        $d=['reporter_id'=>Auth::id(),'room_id'=>(int)Request::input('room_id',0),'asset_id'=>(int)Request::input('asset_id',0),
            'title'=>trim((string)Request::input('title','')),'description'=>(string)Request::input('description',''),
            'priority'=>in_array(Request::input('priority'),['low','normal','high','urgent'],true)?Request::input('priority'):'normal'];
        if($d['title']==='') $this->back('general/repairs','error','กรอกหัวข้อการแจ้งซ่อม');
        $id=$this->m->repairCreate($d);
        AuditLog::record(Auth::id(),'create','repair_requests',$id);
        $this->back('general/repairs','success','แจ้งซ่อมเรียบร้อย');
    }
    public function repairUpdate(string $id): void
    {
        $this->authorize('general.repair'); $this->verifyCsrf();
        $d=['title'=>trim((string)Request::input('title','')),'description'=>(string)Request::input('description',''),
            'priority'=>Request::input('priority','normal'),
            'status'=>in_array(Request::input('status'),['reported','assigned','in_progress','done','cancelled'],true)?Request::input('status'):'reported',
            'assignee_id'=>(int)Request::input('assignee_id',0)];
        $this->m->repairUpdate((int)$id,$d);
        AuditLog::record(Auth::id(),'update','repair_requests',(int)$id);
        $this->back('general/repairs','success','อัปเดตงานซ่อมแล้ว');
    }
    public function repairDelete(string $id): void
    {
        $this->authorize('general.repair'); $this->verifyCsrf();
        $this->m->repairDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','repair_requests',(int)$id);
        $this->back('general/repairs','success','ลบรายการแจ้งซ่อมแล้ว');
    }

    // ---------- พัสดุ/ครุภัณฑ์ ----------
    /** ดูรายการครุภัณฑ์ได้: งานพัสดุ (inventory.manage) หรือ ฝ่ายบริหารทั่วไป (general.asset) */
    private function assetViewGuard(): void
    {
        if(!Auth::can('inventory.manage') && !Auth::can('general.asset')) $this->authorize('general.asset');
    }
    private function canManageAsset(): bool { return Auth::can('inventory.manage'); }

    public function assets(): void
    {
        $this->assetViewGuard();
        $f=['category'=>Request::input('category',''),'condition'=>Request::input('condition',''),'q'=>Request::input('q','')];
        $this->view('general/assets', ['title'=>'พัสดุ / ครุภัณฑ์','rows'=>$this->m->assets(array_filter($f)),'f'=>$f,
            'canManage'=>$this->canManageAsset(),
            'categories'=>$this->m->assetCategories(),'rooms'=>$this->m->rooms(),'people'=>$this->m->personnelList()]);
    }
    public function assetStore(): void
    {
        $this->authorize('inventory.manage'); $this->verifyCsrf();   // เพิ่มครุภัณฑ์ = งานพัสดุเท่านั้น
        $d=$this->assetInput();
        if($d['asset_code']===''||$d['name']==='') $this->back('general/assets','error','กรอกรหัสและชื่อครุภัณฑ์');
        $id=$this->m->assetCreate($d);
        AuditLog::record(Auth::id(),'create','assets',$id);
        $this->back('general/assets','success','เพิ่มครุภัณฑ์เรียบร้อย');
    }
    public function assetUpdate(string $id): void
    {
        $this->authorize('inventory.manage'); $this->verifyCsrf();   // แก้ไขทั้งหมด = งานพัสดุเท่านั้น
        $this->m->assetUpdate((int)$id,$this->assetInput());
        AuditLog::record(Auth::id(),'update','assets',(int)$id);
        $this->back('general/assets','success','แก้ไขครุภัณฑ์แล้ว');
    }
    /** ฝ่ายบริหารทั่วไป: แก้เฉพาะสถานที่ตั้ง */
    public function assetLocation(string $id): void
    {
        $this->assetViewGuard(); $this->verifyCsrf();
        $this->m->assetSetLocation((int)$id,(int)Request::input('location_room_id',0));
        AuditLog::record(Auth::id(),'update','assets',(int)$id,null,['location'=>true]);
        $this->back('general/assets','success','อัปเดตสถานที่ตั้งแล้ว');
    }
    public function assetDelete(string $id): void
    {
        $this->authorize('inventory.manage'); $this->verifyCsrf();   // ลบ = งานพัสดุเท่านั้น
        $this->m->assetDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','assets',(int)$id);
        $this->back('general/assets','success','ลบครุภัณฑ์แล้ว');
    }
    public function assetPrint(string $id): void
    {
        $this->assetViewGuard();
        $a=$this->m->assetFind((int)$id);
        if(!$a) $this->back('general/assets','error','ไม่พบครุภัณฑ์');
        $this->view('general/asset_label', ['title'=>'ป้ายครุภัณฑ์','a'=>$a,'backUrl'=>'general/assets','autoPrint'=>false],'print');
    }

    /** ตัวเลือกรายงานครุภัณฑ์ รายเดือน/ไตรมาส/ปี */
    public function assetReport(): void
    {
        $this->authorize('general.asset');
        [$mode,$year,$period,$from,$to]=$this->reportRange();
        $this->view('general/asset_report', ['title'=>'รายงานครุภัณฑ์',
            'mode'=>$mode,'year'=>$year,'period'=>$period,'from'=>$from,'to'=>$to,
            'd'=>$this->m->assetReport($from,$to)]);
    }
    public function assetReportPrint(): void
    {
        $this->authorize('general.asset');
        [$mode,$year,$period,$from,$to]=$this->reportRange();
        $this->view('general/asset_report_print', ['title'=>'รายงานครุภัณฑ์',
            'mode'=>$mode,'year'=>$year,'period'=>$period,'from'=>$from,'to'=>$to,
            'd'=>$this->m->assetReport($from,$to),'backUrl'=>'general/assets/report','autoPrint'=>true],'print');
    }
    /** คำนวณช่วงวันที่จาก mode=month|quarter|year */
    private function reportRange(): array
    {
        $mode=in_array(Request::input('mode'),['month','quarter','year'],true)?Request::input('mode'):'month';
        $year=(int)Request::input('year',(int)date('Y')+543);   // พ.ศ.
        $y=$year-543;
        $period=(int)Request::input('period', $mode==='quarter'?(int)ceil((int)date('n')/3):(int)date('n'));
        if($mode==='month'){
            $period=max(1,min(12,$period));
            $from=sprintf('%04d-%02d-01',$y,$period);
            $to=date('Y-m-t',strtotime($from));
        } elseif($mode==='quarter'){
            $period=max(1,min(4,$period));
            $sm=($period-1)*3+1;
            $from=sprintf('%04d-%02d-01',$y,$sm);
            $to=date('Y-m-t',strtotime(sprintf('%04d-%02d-01',$y,$sm+2)));
        } else {
            $period=0; $from=sprintf('%04d-01-01',$y); $to=sprintf('%04d-12-31',$y);
        }
        return [$mode,$year,$period,$from,$to];
    }
    private function assetInput(): array
    {
        $fund=(string)Request::input('fund_type',''); $method=(string)Request::input('acquire_method','');
        return ['asset_code'=>trim((string)Request::input('asset_code','')),'barcode'=>(string)Request::input('barcode',''),
            'category_id'=>(int)Request::input('category_id',0),'name'=>trim((string)Request::input('name','')),
            'spec'=>trim((string)Request::input('spec','')),'model'=>trim((string)Request::input('model','')),
            'acquired_date'=>(string)Request::input('acquired_date',''),'acquired_price'=>(float)Request::input('acquired_price',0),
            'useful_life_years'=>(int)Request::input('useful_life_years',0),
            'vendor'=>trim((string)Request::input('vendor','')),'vendor_phone'=>trim((string)Request::input('vendor_phone','')),
            'doc_no'=>trim((string)Request::input('doc_no','')),
            'fund_type'=>in_array($fund,['budget','non_budget','donation','other'],true)?$fund:'',
            'acquire_method'=>in_array($method,['agree','inquiry','bid','special','donate'],true)?$method:'',
            'location_room_id'=>(int)Request::input('location_room_id',0),'responsible_id'=>(int)Request::input('responsible_id',0),
            'condition_status'=>in_array(Request::input('condition_status'),['normal','repair','damaged','disposed'],true)?Request::input('condition_status'):'normal'];
    }

    /**
     * ตารางค่าเสื่อมราคาแบบเส้นตรง (straight-line) ตามปีงบประมาณ (สิ้นสุด 30 ก.ย.) มูลค่าซาก 1 บาท
     * @return array{annual:float,rate:float,salvage:float,life:int,rows:array}
     */
    public static function depreciation(float $cost, ?string $acquiredDate, int $life): array
    {
        $salvage=1.0;
        if($cost<=$salvage || !$acquiredDate || $life<=0) return ['annual'=>0,'rate'=>0,'salvage'=>$salvage,'life'=>$life,'rows'=>[]];
        $depreciable=$cost-$salvage; $annual=$depreciable/$life; $rate=round(100/$life,2);
        try { $acq=new \DateTime($acquiredDate); } catch(\Throwable $e){ return ['annual'=>round($annual,2),'rate'=>$rate,'salvage'=>$salvage,'life'=>$life,'rows'=>[]]; }
        $ay=(int)$acq->format('Y'); $am=(int)$acq->format('n');
        $fyEndYear=($am>=10)?$ay+1:$ay;                       // ปีงบประมาณสิ้นสุด 30 ก.ย.
        $rows=[]; $accum=0.0; $i=0;
        while($accum < $depreciable - 0.005 && $i <= $life+2){
            $endYear=$fyEndYear+$i;
            $end=new \DateTime("$endYear-09-30");
            if($i===0){
                $days=(int)$acq->diff($end)->days + 1;         // ปีแรกคิดตามสัดส่วนวัน
                $dep=$annual*min(1.0,$days/365);
            } else { $dep=$annual; }
            if($accum+$dep > $depreciable) $dep=$depreciable-$accum;   // ปีสุดท้ายปรับให้เหลือมูลค่าซาก
            $dep=round($dep,2); $accum=round($accum+$dep,2);
            $rows[]=['fy_end'=>$end->format('Y-m-d'),'years'=>$i+1,'dep'=>$dep,'accum'=>$accum,'net'=>round($cost-$accum,2)];
            $i++;
        }
        return ['annual'=>round($annual,2),'rate'=>$rate,'salvage'=>$salvage,'life'=>$life,'rows'=>$rows];
    }

    /** พิมพ์ทะเบียนคุมทรัพย์สิน (แบบราชการ) + ตารางค่าเสื่อมราคา/ปี */
    public function assetRegister(string $id): void
    {
        $this->assetViewGuard();
        $a=$this->m->assetFind((int)$id);
        if(!$a) $this->back('general/assets','error','ไม่พบครุภัณฑ์');
        $life=(int)($a['useful_life_years'] ?? 0);
        $dep=self::depreciation((float)$a['acquired_price'], $a['acquired_date'] ?? null, $life);
        $this->view('general/asset_register', [
            'title'=>'ทะเบียนคุมทรัพย์สิน - '.$a['name'],'a'=>$a,'dep'=>$dep,
            'backUrl'=>'general/assets','autoPrint'=>true,
        ], 'print');
    }

    // ---------- งานสารบรรณ ----------
    /**
     * งานสารบรรณย้ายไปโมดูลเต็มที่ /documents (ลงรับ · แนบไฟล์ · เกษียน · ลงนาม)
     * คงเส้นทางเดิมไว้เพื่อไม่ให้ลิงก์/บุ๊กมาร์กเก่าพัง
     */
    public function documents(): void
    {
        $this->redirect('documents');
    }

    public function documentStore(): void
    {
        $this->authorize('general.document'); $this->verifyCsrf();
        $d=$this->docInput(); $d['created_by']=Auth::id();
        if($d['doc_number']===''||$d['title']==='') $this->back('general/documents','error','กรอกเลขที่และเรื่อง');
        $id=$this->m->documentCreate($d);
        AuditLog::record(Auth::id(),'create','documents',$id);
        $this->back('general/documents','success','ลงทะเบียนหนังสือเรียบร้อย');
    }
    public function documentUpdate(string $id): void
    {
        $this->authorize('general.document'); $this->verifyCsrf();
        $this->m->documentUpdate((int)$id,$this->docInput());
        AuditLog::record(Auth::id(),'update','documents',(int)$id);
        $this->back('general/documents','success','แก้ไขหนังสือแล้ว');
    }
    public function documentDelete(string $id): void
    {
        $this->authorize('general.document'); $this->verifyCsrf();
        $this->m->documentDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','documents',(int)$id);
        $this->back('general/documents','success','ลบหนังสือแล้ว');
    }
    private function docInput(): array
    {
        return ['doc_number'=>trim((string)Request::input('doc_number','')),
            'doc_type'=>in_array(Request::input('doc_type'),['incoming','outgoing','circular','internal'],true)?Request::input('doc_type'):'internal',
            'title'=>trim((string)Request::input('title','')),'from_org'=>(string)Request::input('from_org',''),
            'to_org'=>(string)Request::input('to_org',''),'doc_date'=>(string)Request::input('doc_date',''),
            'received_date'=>(string)Request::input('received_date',''),
            'status'=>in_array(Request::input('status'),['draft','registered','in_process','completed','archived'],true)?Request::input('status'):'registered',
            'is_signed'=>Request::input('is_signed')?1:0];
    }

    // ---------- ยานพาหนะ ----------
    public function vehicles(): void
    {
        $this->authorize('general.vehicle');
        $this->view('general/vehicles', ['title'=>'ทะเบียนยานพาหนะ','rows'=>$this->m->vehicles()]);
    }
    public function vehicleStore(): void
    {
        $this->authorize('general.vehicle'); $this->verifyCsrf();
        $d=$this->vehicleInput();
        if($d['plate_no']==='') $this->back('general/vehicles','error','กรอกทะเบียนรถ');
        $id=$this->m->vehicleCreate($d);
        AuditLog::record(Auth::id(),'create','vehicles',$id);
        $this->back('general/vehicles','success','เพิ่มยานพาหนะเรียบร้อย');
    }
    public function vehicleUpdate(string $id): void
    {
        $this->authorize('general.vehicle'); $this->verifyCsrf();
        $this->m->vehicleUpdate((int)$id,$this->vehicleInput());
        AuditLog::record(Auth::id(),'update','vehicles',(int)$id);
        $this->back('general/vehicles','success','แก้ไขยานพาหนะแล้ว');
    }
    public function vehicleDelete(string $id): void
    {
        $this->authorize('general.vehicle'); $this->verifyCsrf();
        $this->m->vehicleDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','vehicles',(int)$id);
        $this->back('general/vehicles','success','ลบยานพาหนะแล้ว');
    }
    private function vehicleInput(): array
    {
        return ['plate_no'=>trim((string)Request::input('plate_no','')),'brand'=>(string)Request::input('brand',''),
            'vehicle_type'=>(string)Request::input('vehicle_type',''),'seats'=>(int)Request::input('seats',0),
            'fuel_type'=>(string)Request::input('fuel_type',''),
            'status'=>in_array(Request::input('status'),['available','in_use','maintenance','inactive'],true)?Request::input('status'):'available',
            'note'=>(string)Request::input('note','')];
    }

    // ---------- การจองใช้รถ ----------
    public function bookings(): void
    {
        $this->authorize('general.vehicle');
        $f=['status'=>Request::input('status','')];
        $this->view('general/bookings', ['title'=>'ขอใช้ยานพาหนะ','rows'=>$this->m->bookings(array_filter($f)),'f'=>$f,
            'vehicles'=>$this->m->availableVehicles(),'people'=>$this->m->personnelList()]);
    }
    public function bookingStore(): void
    {
        $this->authorize('general.vehicle'); $this->verifyCsrf();
        $d=['vehicle_id'=>(int)Request::input('vehicle_id',0),'requester_id'=>Auth::id(),
            'purpose'=>trim((string)Request::input('purpose','')),'destination'=>(string)Request::input('destination',''),
            'depart_at'=>(string)Request::input('depart_at',''),'return_at'=>(string)Request::input('return_at',''),
            'passengers'=>(int)Request::input('passengers',0),'driver_id'=>(int)Request::input('driver_id',0),
            'note'=>(string)Request::input('note','')];
        if($d['purpose']===''||$d['depart_at']==='') $this->back('general/bookings','error','กรอกวัตถุประสงค์และวันเวลาเดินทาง');
        $id=$this->m->bookingCreate($d);
        AuditLog::record(Auth::id(),'create','vehicle_bookings',$id);
        $this->back('general/bookings','success','ส่งคำขอใช้รถเรียบร้อย');
    }
    public function bookingDecide(string $id): void
    {
        $this->authorize('general.vehicle'); $this->verifyCsrf();
        $status=Request::input('decision')==='approve'?'approved':'rejected';
        $this->m->bookingDecide((int)$id,$status,Auth::id());
        AuditLog::record(Auth::id(),$status==='approved'?'approve':'reject','vehicle_bookings',(int)$id);
        $this->back('general/bookings','success',$status==='approved'?'อนุมัติการใช้รถแล้ว':'ไม่อนุมัติ');
    }
    public function bookingComplete(string $id): void
    {
        $this->authorize('general.vehicle'); $this->verifyCsrf();
        $this->m->bookingComplete((int)$id);
        AuditLog::record(Auth::id(),'update','vehicle_bookings',(int)$id);
        $this->back('general/bookings','success','ปิดงานการใช้รถแล้ว (รถพร้อมใช้งาน)');
    }
    public function bookingDelete(string $id): void
    {
        $this->authorize('general.vehicle'); $this->verifyCsrf();
        $this->m->bookingDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','vehicle_bookings',(int)$id);
        $this->back('general/bookings','success','ลบคำขอแล้ว');
    }
    public function bookingPrint(string $id): void
    {
        $this->authorize('general.vehicle');
        $b=$this->m->bookingFind((int)$id);
        if(!$b) $this->back('general/bookings','error','ไม่พบคำขอ');
        $this->view('general/booking_print', ['title'=>'แบบขอใช้รถ','b'=>$b,'backUrl'=>'general/bookings','autoPrint'=>true],'print');
    }
}
