<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Admin;
use App\Models\AuditLog;

class AdminController extends Controller
{
    private Admin $m;
    public function __construct(){ $this->m = new Admin(); }

    // ---------- Dashboard ผู้บริหาร ----------
    public function dashboard(): void
    {
        $this->authorize('admin.dashboard');
        // ยอดการมาเรียนหน้าเสาธงวันนี้ (ให้ผู้บริหารติดตามได้ทันที)
        $att = null;
        try {
            $ac = new \App\Models\Academic();
            $today = date('Y-m-d');
            $att = ['stats'=>$ac->flagStatsByDate($today),'progress'=>$ac->flagClassProgress($today),
                    'active'=>$ac->activeStudentCount(),'date'=>$today];
        } catch (\Throwable $e) { $att = null; }
        $this->view('admin/dashboard', [
            'title'    => 'Dashboard ผู้บริหาร',
            'stats'    => $this->m->execStats(),
            'kpis'     => $this->m->kpis(),
            'upcoming' => $this->m->upcomingEvents(5),
            'att'      => $att,
        ]);
    }

    // ---------- KPI ----------
    public function kpi(): void
    {
        $this->authorize('admin.dashboard');
        $level = (string) Request::input('level','');
        $this->view('admin/kpi', [
            'title' => 'KPI โรงเรียน',
            'rows'  => $this->m->kpis($level),
            'level' => $level,
        ]);
    }
    public function kpiStore(): void
    {
        $this->authorize('admin.dashboard');
        $this->verifyCsrf();
        $d = Request::only(['education_level','category','name','unit','target_value','actual_value','direction']);
        if (trim((string)$d['name'])==='') $this->back('kpi','error','กรุณากรอกชื่อตัวชี้วัด');
        $d['target_value']=(float)$d['target_value']; $d['actual_value']=(float)$d['actual_value'];
        $d['unit']=$d['unit']?:'%'; $d['direction']=in_array($d['direction'],['up','down'],true)?$d['direction']:'up';
        $d['education_level']=in_array($d['education_level'],['early_childhood','basic','all'],true)?$d['education_level']:'basic';
        $d['updated_by']=Auth::id();
        $id=$this->m->kpiCreate($d);
        AuditLog::record(Auth::id(),'create','kpis',$id,null,['name'=>$d['name']]);
        $this->back('kpi','success','เพิ่มตัวชี้วัดเรียบร้อยแล้ว');
    }
    public function kpiUpdate(string $id): void
    {
        $this->authorize('admin.dashboard');
        $this->verifyCsrf();
        $this->m->kpiUpdateActual((int)$id,(float)Request::input('actual_value',0),Auth::id());
        AuditLog::record(Auth::id(),'update','kpis',(int)$id);
        $this->back('kpi','success','อัปเดตผลจริงเรียบร้อยแล้ว');
    }

    public function kpiDelete(string $id): void
    {
        $this->authorize('admin.dashboard');
        $this->verifyCsrf();
        $this->m->kpiDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','kpis',(int)$id);
        $this->back('kpi','success','ลบตัวชี้วัดแล้ว');
    }

    // ---------- อนุมัติเอกสารออนไลน์ ----------
    public function approvals(): void
    {
        $this->authorize('admin.approve');
        $uid=Auth::id();
        $this->view('admin/approvals', [
            'title'    => 'อนุมัติเอกสารออนไลน์',
            'incoming' => $this->m->requestsForApprover($uid),
            'mine'     => $this->m->myRequests($uid),
            'approvers'=> $this->m->approversList(),
        ]);
    }
    public function requestStore(): void
    {
        // ผู้ใช้ทั่วไปก็บันทึกคำขอได้ (ต้องล็อกอิน)
        $this->verifyCsrf();
        $d = Request::only(['request_type','title','detail','amount','approver_id']);
        if (trim((string)$d['title'])==='') $this->back('approvals','error','กรุณากรอกเรื่องที่ขออนุมัติ');
        $d['requester_id']=Auth::id();
        $d['request_type']=in_array($d['request_type'],['leave','travel','purchase','general'],true)?$d['request_type']:'general';
        $id=$this->m->requestCreate($d);
        AuditLog::record(Auth::id(),'create','approval_requests',$id,null,['title'=>$d['title']]);
        $this->back('approvals','success','บันทึกคำขออนุมัติเรียบร้อยแล้ว');
    }
    public function requestDecide(string $id): void
    {
        $this->authorize('admin.approve');
        $this->verifyCsrf();
        $req=$this->m->requestFind((int)$id);
        if(!$req) $this->back('approvals','error','ไม่พบคำขอ');
        $status=Request::input('decision')==='approve'?'approved':'rejected';
        $this->m->requestDecide((int)$id,$status,(string)Request::input('note',''));
        AuditLog::record(Auth::id(),$status==='approved'?'approve':'reject','approval_requests',(int)$id);
        $this->back('approvals','success',$status==='approved'?'อนุมัติเรียบร้อยแล้ว':'ไม่อนุมัติคำขอแล้ว');
    }

    // ---------- หนังสือราชการ ----------
    public function officialDocs(): void
    {
        // งานสารบรรณรวมอยู่ที่ /documents แล้ว (ลงรับ · แนบไฟล์ · เกษียน · ส่งต่อ · ลงนาม)
        $this->redirect('documents');
    }
    public function officialDocShow(string $id): void
    {
        // งานสารบรรณรวมอยู่ที่ /documents แล้ว (ลงรับ · แนบไฟล์ · เกษียน · ส่งต่อ · ลงนาม)
        $this->redirect('documents/'.$id);
    }

    // ---------- E-Office ----------
    public function eoffice(): void
    {
        $this->authorize('admin.eoffice');
        $this->view('admin/eoffice', ['title'=>'E-Office · คลังแบบฟอร์มเอกสาร','templates'=>$this->m->templates()]);
    }
    public function eofficeCreate(string $id): void
    {
        $this->authorize('admin.eoffice');
        $tpl=$this->m->templateFind((int)$id);
        if(!$tpl) $this->back('eoffice','error','ไม่พบแบบฟอร์ม');
        // ดึง placeholder จาก body
        preg_match_all('/\{\{([^}]+)\}\}/u',$tpl['body'],$mm);
        $this->view('admin/eoffice_form', ['title'=>'สร้างเอกสาร: '.$tpl['name'],'tpl'=>$tpl,'fields'=>array_values(array_unique($mm[1]))]);
    }
    public function eofficeGenerate(string $id): void
    {
        $this->authorize('admin.eoffice');
        $tpl=$this->m->templateFind((int)$id);
        if(!$tpl) $this->back('eoffice','error','ไม่พบแบบฟอร์ม');
        $body=$tpl['body'];
        foreach ((array)Request::input('f',[]) as $k=>$v){
            $body=str_replace('{{'.$k.'}}', (string)$v, $body);
        }
        AuditLog::record(Auth::id(),'generate','document_templates',(int)$id);
        // เรนเดอร์หน้าเอกสารพิมพ์ได้ (ไม่มี layout)
        $this->view('admin/eoffice_print', [
            'title'=>$tpl['name'],'tpl'=>$tpl,'body'=>$body,
            'school'=>$this->m->school(),'backUrl'=>'eoffice',
        ], 'print');
    }

    // ---------- ปฏิทินโรงเรียน ----------
    public function calendar(): void
    {
        // ดูได้ทุกคนที่ล็อกอิน (อยู่ในกลุ่มทั่วไป) · จัดการเฉพาะผู้มีสิทธิ์ admin.calendar
        $now=getdate();
        $year=(int)Request::input('y',$now['year']);
        $month=(int)Request::input('m',$now['mon']);
        $this->view('admin/calendar', [
            'title'=>'ปฏิทินวิชาการ','year'=>$year,'month'=>$month,
            'events'=>$this->m->events($year,$month),
            'canManage'=>Auth::can('admin.calendar'),
        ]);
    }

    private function eventInput(): array
    {
        $types=['academic','activity','meeting','holiday','exam'];
        return [
            'title'=>trim((string)Request::input('title','')),
            'event_type'=>in_array(Request::input('event_type'),$types,true)?Request::input('event_type'):'activity',
            'event_date'=>(string)Request::input('event_date',''),
            'end_date'=>(string)Request::input('end_date',''),
            'start_time'=>(string)Request::input('start_time',''),
            'end_time'=>(string)Request::input('end_time',''),
            'location'=>trim((string)Request::input('location','')),
            'meeting_url'=>trim((string)Request::input('meeting_url','')),
            'description'=>trim((string)Request::input('description','')),
        ];
    }
    private function backCal(): string
    {
        $y=(int)Request::input('y',0); $m=(int)Request::input('m',0);
        return 'calendar'.($y&&$m?"?y=$y&m=$m":'');
    }

    public function eventStore(): void
    {
        $this->authorize('admin.calendar'); $this->verifyCsrf();
        $d=$this->eventInput();
        if($d['title']==='' || $d['event_date']==='') $this->back($this->backCal(),'error','กรอกชื่อกิจกรรมและวันที่');
        $id=$this->m->eventCreate($d, Auth::id());
        AuditLog::record(Auth::id(),'create','calendar_events',$id);
        $this->back($this->backCal(),'success','เพิ่มกิจกรรมในปฏิทินแล้ว');
    }
    public function eventUpdate(string $id): void
    {
        $this->authorize('admin.calendar'); $this->verifyCsrf();
        if(!$this->m->eventFind((int)$id)) $this->back('calendar','error','ไม่พบกิจกรรม');
        $d=$this->eventInput();
        if($d['title']==='' || $d['event_date']==='') $this->back($this->backCal(),'error','กรอกชื่อกิจกรรมและวันที่');
        $this->m->eventUpdate((int)$id,$d);
        AuditLog::record(Auth::id(),'update','calendar_events',(int)$id);
        $this->back($this->backCal(),'success','แก้ไขกิจกรรมแล้ว');
    }
    public function eventDelete(string $id): void
    {
        $this->authorize('admin.calendar'); $this->verifyCsrf();
        $this->m->eventDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','calendar_events',(int)$id);
        $this->back($this->backCal(),'success','ลบกิจกรรมแล้ว');
    }
}
