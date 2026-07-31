<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\StudentAffairs;
use App\Models\AuditLog;

class StudentAffairsController extends Controller
{
    private StudentAffairs $m;
    public function __construct(){ $this->m = new StudentAffairs(); }

    public function dashboard(): void
    {
        $this->authorize('student.behavior');
        $this->view('affairs/dashboard', ['title'=>'ภาพรวมกิจการนักเรียน','d'=>$this->m->dashboard()]);
    }

    // ================= พฤติกรรม =================
    public function behaviors(): void
    {
        $this->authorize('student.behavior');
        $f=$this->behaviorFilter();
        $this->view('affairs/behaviors', ['title'=>'พฤติกรรมนักเรียน',
            'rows'=>$this->m->behaviors(array_filter($f)),'f'=>$f,
            'students'=>$this->m->studentsList(),'classrooms'=>$this->m->classrooms(),
            'people'=>$this->m->personnelList()]);
    }
    private function behaviorFilter(): array
    {
        return ['type'=>Request::input('type',''),'student'=>Request::input('student',''),
            'classroom'=>Request::input('classroom',''),'from'=>Request::input('from',''),'to'=>Request::input('to','')];
    }
    public function behaviorStore(): void
    {
        $this->authorize('student.behavior'); $this->verifyCsrf();
        $d=$this->behaviorInput();
        if(!$d['student_id']) $this->back('affairs/behaviors','error','เลือกนักเรียน');
        $id=$this->m->behaviorCreate($d);
        AuditLog::record(Auth::id(),'create','behavior_records',$id);
        $this->back('affairs/behaviors','success','บันทึกพฤติกรรมเรียบร้อย');
    }
    public function behaviorUpdate(string $id): void
    {
        $this->authorize('student.behavior'); $this->verifyCsrf();
        $this->m->behaviorUpdate((int)$id,$this->behaviorInput());
        AuditLog::record(Auth::id(),'update','behavior_records',(int)$id);
        $this->back('affairs/behaviors','success','แก้ไขบันทึกแล้ว');
    }
    public function behaviorDelete(string $id): void
    {
        $this->authorize('student.behavior'); $this->verifyCsrf();
        $this->m->behaviorDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','behavior_records',(int)$id);
        $this->back('affairs/behaviors','success','ลบบันทึกแล้ว');
    }
    private function behaviorInput(): array
    {
        $type=in_array(Request::input('type'),['merit','demerit'],true)?Request::input('type'):'demerit';
        $pts=abs((int)Request::input('points',0));
        return ['student_id'=>(int)Request::input('student_id',0),
            'record_date'=>(string)Request::input('record_date',date('Y-m-d')),
            'type'=>$type,'category'=>trim((string)Request::input('category','')),
            'points'=>$type==='demerit'?-$pts:$pts,
            'description'=>(string)Request::input('description',''),
            'recorded_by'=>(int)Request::input('recorded_by',0),
            'parent_notified'=>Request::input('parent_notified')?1:0,
            'action_taken'=>(string)Request::input('action_taken','')];
    }
    /** แบบบันทึกพฤติกรรมนักเรียน (รายบุคคล) */
    public function behaviorPrint(string $id): void
    {
        $this->authorize('student.behavior');
        $b=$this->m->behaviorFind((int)$id);
        if(!$b) $this->back('affairs/behaviors','error','ไม่พบบันทึก');
        $this->view('affairs/behavior_print', ['title'=>'แบบบันทึกพฤติกรรมนักเรียน','b'=>$b,
            'score'=>$this->m->behaviorScore((int)$b['student_id']),
            'backUrl'=>'affairs/behaviors','autoPrint'=>true],'print');
    }
    /** หนังสือเชิญผู้ปกครอง (กรณีพฤติกรรมไม่เหมาะสม) */
    public function behaviorInvitePrint(string $id): void
    {
        $this->authorize('student.behavior');
        $b=$this->m->behaviorFind((int)$id);
        if(!$b) $this->back('affairs/behaviors','error','ไม่พบบันทึก');
        $this->view('affairs/behavior_invite_print', ['title'=>'หนังสือเชิญผู้ปกครอง','b'=>$b,
            'guardian'=>$this->m->guardianOf((int)$b['student_id']),
            'score'=>$this->m->behaviorScore((int)$b['student_id']),
            'backUrl'=>'affairs/behaviors','autoPrint'=>true],'print');
    }
    /** สรุปพฤติกรรมรายบุคคล */
    public function behaviorStudentPrint(string $sid): void
    {
        $this->authorize('student.behavior');
        $s=$this->m->studentFind((int)$sid);
        if(!$s) $this->back('affairs/behaviors','error','ไม่พบนักเรียน');
        $this->view('affairs/behavior_student_print', ['title'=>'สรุปพฤติกรรมรายบุคคล','s'=>$s,
            'rows'=>$this->m->behaviorOf((int)$sid),'score'=>$this->m->behaviorScore((int)$sid),
            'backUrl'=>'affairs/behaviors','autoPrint'=>true],'print');
    }

    // ================= SDQ =================
    public function sdq(): void
    {
        $this->authorize('student.behavior');
        $f=['group'=>Request::input('group',''),'assessor'=>Request::input('assessor',''),
            'classroom'=>Request::input('classroom',''),'student'=>Request::input('student','')];
        $ff=array_filter($f);
        $this->view('affairs/sdq', ['title'=>'ประเมิน SDQ','rows'=>$this->m->sdqList($ff),'f'=>$f,
            'sum'=>$this->m->sdqSummary($ff),'students'=>$this->m->studentsList(),
            'classrooms'=>$this->m->classrooms(),'years'=>$this->m->years()]);
    }
    public function sdqStore(): void
    {
        $this->authorize('student.behavior'); $this->verifyCsrf();
        $d=$this->sdqInput();
        if(!$d['student_id']) $this->back('affairs/sdq','error','เลือกนักเรียน');
        $id=$this->m->sdqSave($d);
        AuditLog::record(Auth::id(),'create','sdq_assessments',$id);
        $this->back('affairs/sdq','success','บันทึกผล SDQ และแปลผลอัตโนมัติแล้ว');
    }
    public function sdqUpdate(string $id): void
    {
        $this->authorize('student.behavior'); $this->verifyCsrf();
        $this->m->sdqSave($this->sdqInput(),(int)$id);
        AuditLog::record(Auth::id(),'update','sdq_assessments',(int)$id);
        $this->back('affairs/sdq','success','แก้ไขผล SDQ แล้ว');
    }
    public function sdqDelete(string $id): void
    {
        $this->authorize('student.behavior'); $this->verifyCsrf();
        $this->m->sdqDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','sdq_assessments',(int)$id);
        $this->back('affairs/sdq','success','ลบผลประเมินแล้ว');
    }
    private function sdqInput(): array
    {
        return ['student_id'=>(int)Request::input('student_id',0),
            'academic_year_id'=>(int)Request::input('academic_year_id',0),
            'assessor'=>in_array(Request::input('assessor'),['self','teacher','parent'],true)?Request::input('assessor'):'teacher',
            'emotional_score'=>(int)Request::input('emotional_score',0),
            'conduct_score'=>(int)Request::input('conduct_score',0),
            'hyperactivity_score'=>(int)Request::input('hyperactivity_score',0),
            'peer_score'=>(int)Request::input('peer_score',0),
            'prosocial_score'=>(int)Request::input('prosocial_score',0),
            'assessed_at'=>(string)Request::input('assessed_at',date('Y-m-d')),
            'note'=>(string)Request::input('note','')];
    }
    /** แบบสรุปผลการประเมิน SDQ รายบุคคล */
    public function sdqPrint(string $id): void
    {
        $this->authorize('student.behavior');
        $q=$this->m->sdqFind((int)$id);
        if(!$q) $this->back('affairs/sdq','error','ไม่พบผลประเมิน');
        $this->view('affairs/sdq_print', ['title'=>'แบบสรุปผลการประเมิน SDQ','q'=>$q,
            'backUrl'=>'affairs/sdq','autoPrint'=>true],'print');
    }
    /** รายงานสรุปผล SDQ ทั้งกลุ่ม */
    public function sdqReportPrint(): void
    {
        $this->authorize('student.behavior');
        $f=['group'=>Request::input('group',''),'assessor'=>Request::input('assessor',''),
            'classroom'=>Request::input('classroom','')];
        $ff=array_filter($f);
        $this->view('affairs/sdq_report_print', ['title'=>'รายงานผลการประเมิน SDQ',
            'rows'=>$this->m->sdqList($ff),'sum'=>$this->m->sdqSummary($ff),
            'backUrl'=>'affairs/sdq','autoPrint'=>true],'print');
    }

    // ================= เยี่ยมบ้าน =================
    public function visits(): void
    {
        $this->authorize('student.care');
        $f=['risk'=>Request::input('risk',''),'classroom'=>Request::input('classroom',''),
            'student'=>Request::input('student',''),'from'=>Request::input('from',''),'to'=>Request::input('to','')];
        $this->view('affairs/visits', ['title'=>'เยี่ยมบ้าน / ระบบดูแลช่วยเหลือ',
            'rows'=>$this->m->visits(array_filter($f)),'f'=>$f,
            'students'=>$this->m->studentsList(),'classrooms'=>$this->m->classrooms(),
            'people'=>$this->m->personnelList()]);
    }
    public function visitStore(): void
    {
        $this->authorize('student.care'); $this->verifyCsrf();
        $d=$this->visitInput();
        if(!$d['student_id']) $this->back('affairs/visits','error','เลือกนักเรียน');
        $id=$this->m->visitCreate($d);
        AuditLog::record(Auth::id(),'create','home_visits',$id);
        $this->back('affairs/visits','success','บันทึกการเยี่ยมบ้านเรียบร้อย');
    }
    public function visitUpdate(string $id): void
    {
        $this->authorize('student.care'); $this->verifyCsrf();
        $this->m->visitUpdate((int)$id,$this->visitInput());
        AuditLog::record(Auth::id(),'update','home_visits',(int)$id);
        $this->back('affairs/visits','success','แก้ไขบันทึกแล้ว');
    }
    public function visitDelete(string $id): void
    {
        $this->authorize('student.care'); $this->verifyCsrf();
        $this->m->visitDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','home_visits',(int)$id);
        $this->back('affairs/visits','success','ลบบันทึกแล้ว');
    }
    private function visitInput(): array
    {
        return ['student_id'=>(int)Request::input('student_id',0),
            'visit_date'=>(string)Request::input('visit_date',date('Y-m-d')),
            'visitor_id'=>(int)Request::input('visitor_id',0),
            'summary'=>(string)Request::input('summary',''),
            'risk_level'=>in_array(Request::input('risk_level'),['normal','watch','risk','urgent'],true)?Request::input('risk_level'):'normal',
            'guardian_name'=>(string)Request::input('guardian_name',''),
            'guardian_relation'=>(string)Request::input('guardian_relation',''),
            'guardian_phone'=>(string)Request::input('guardian_phone',''),
            'address'=>(string)Request::input('address',''),
            'living_with'=>(string)Request::input('living_with',''),
            'family_status'=>in_array(Request::input('family_status'),['together','divorced','father_only','mother_only','relative','other'],true)?Request::input('family_status'):null,
            'housing_type'=>in_array(Request::input('housing_type'),['own','rent','relative','other'],true)?Request::input('housing_type'):null,
            'family_income'=>(string)Request::input('family_income',''),
            'travel_method'=>(string)Request::input('travel_method',''),
            'distance_km'=>(string)Request::input('distance_km',''),
            'health_note'=>(string)Request::input('health_note',''),
            'recommendation'=>(string)Request::input('recommendation',''),
            'needs_help'=>Request::input('needs_help')?1:0,
            'next_visit_date'=>(string)Request::input('next_visit_date','')];
    }
    /** แบบบันทึกการเยี่ยมบ้านนักเรียน */
    public function visitPrint(string $id): void
    {
        $this->authorize('student.care');
        $v=$this->m->visitFind((int)$id);
        if(!$v) $this->back('affairs/visits','error','ไม่พบบันทึก');
        $this->view('affairs/visit_print', ['title'=>'แบบบันทึกการเยี่ยมบ้านนักเรียน','v'=>$v,
            'sdq'=>$this->m->sdqOf((int)$v['student_id'])[0] ?? null,
            'backUrl'=>'affairs/visits','autoPrint'=>true],'print');
    }
    /** รายงานสรุปการเยี่ยมบ้าน */
    public function visitReportPrint(): void
    {
        $this->authorize('student.care');
        $f=['risk'=>Request::input('risk',''),'classroom'=>Request::input('classroom',''),
            'from'=>Request::input('from',''),'to'=>Request::input('to','')];
        $this->view('affairs/visit_report_print', ['title'=>'รายงานการเยี่ยมบ้านนักเรียน',
            'rows'=>$this->m->visits(array_filter($f)),'f'=>$f,
            'backUrl'=>'affairs/visits','autoPrint'=>true],'print');
    }

    // ================= ทุนการศึกษา =================
    public function scholarships(): void
    {
        $this->authorize('student.scholarship');
        $f=['status'=>Request::input('status',''),'type'=>Request::input('type',''),
            'classroom'=>Request::input('classroom',''),'student'=>Request::input('student',''),
            'year'=>Request::input('year','')];
        $ff=array_filter($f);
        $this->view('affairs/scholarships', ['title'=>'ทุนการศึกษา',
            'rows'=>$this->m->scholarships($ff),'f'=>$f,'sum'=>$this->m->scholarshipSummary($ff),
            'students'=>$this->m->studentsList(),'classrooms'=>$this->m->classrooms(),
            'years'=>$this->m->years()]);
    }
    public function scholarshipStore(): void
    {
        $this->authorize('student.scholarship'); $this->verifyCsrf();
        $d=$this->scInput();
        if(!$d['student_id']||$d['name']==='') $this->back('affairs/scholarships','error','เลือกนักเรียนและกรอกชื่อทุน');
        $id=$this->m->scholarshipCreate($d);
        AuditLog::record(Auth::id(),'create','scholarships',$id);
        $this->back('affairs/scholarships','success','เพิ่มรายการทุนเรียบร้อย');
    }
    public function scholarshipUpdate(string $id): void
    {
        $this->authorize('student.scholarship'); $this->verifyCsrf();
        $this->m->scholarshipUpdate((int)$id,$this->scInput());
        AuditLog::record(Auth::id(),'update','scholarships',(int)$id);
        $this->back('affairs/scholarships','success','แก้ไขรายการทุนแล้ว');
    }
    public function scholarshipDelete(string $id): void
    {
        $this->authorize('student.scholarship'); $this->verifyCsrf();
        $this->m->scholarshipDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','scholarships',(int)$id);
        $this->back('affairs/scholarships','success','ลบรายการทุนแล้ว');
    }
    public function scholarshipDecide(string $id): void
    {
        $this->authorize('student.scholarship'); $this->verifyCsrf();
        $ok=(bool)Request::input('approve');
        $this->m->scholarshipApprove((int)$id,$ok,Auth::id());
        AuditLog::record(Auth::id(),$ok?'approve':'reject','scholarships',(int)$id);
        $this->back('affairs/scholarships','success',$ok?'อนุมัติทุนแล้ว':'ไม่อนุมัติ');
    }
    public function scholarshipGrant(string $id): void
    {
        $this->authorize('student.scholarship'); $this->verifyCsrf();
        $this->m->scholarshipGrant((int)$id,(string)Request::input('receipt_no',''));
        AuditLog::record(Auth::id(),'update','scholarships',(int)$id,null,['action'=>'granted']);
        $this->back('affairs/scholarships','success','บันทึกการมอบทุนแล้ว');
    }
    private function scInput(): array
    {
        return ['student_id'=>(int)Request::input('student_id',0),
            'name'=>trim((string)Request::input('name','')),
            'scholarship_type'=>in_array(Request::input('scholarship_type'),['poor','excellent','sport','art','special'],true)?Request::input('scholarship_type'):'poor',
            'source'=>(string)Request::input('source',''),
            'amount'=>(string)Request::input('amount',''),
            'academic_year_id'=>(int)Request::input('academic_year_id',0),
            'term'=>(string)Request::input('term',''),
            'granted_date'=>(string)Request::input('granted_date',''),
            'status'=>in_array(Request::input('status'),['proposed','approved','granted','rejected'],true)?Request::input('status'):'proposed',
            'note'=>(string)Request::input('note',''),
            'receipt_no'=>(string)Request::input('receipt_no','')];
    }
    /** ใบสำคัญรับเงินทุนการศึกษา */
    public function scholarshipPrint(string $id): void
    {
        $this->authorize('student.scholarship');
        $sc=$this->m->scholarshipFind((int)$id);
        if(!$sc) $this->back('affairs/scholarships','error','ไม่พบรายการทุน');
        $this->view('affairs/scholarship_print', ['title'=>'ใบสำคัญรับเงินทุนการศึกษา','sc'=>$sc,
            'guardian'=>$this->m->guardianOf((int)$sc['student_id']),
            'backUrl'=>'affairs/scholarships','autoPrint'=>true],'print');
    }
    /** ทะเบียนผู้รับทุนการศึกษา */
    public function scholarshipReportPrint(): void
    {
        $this->authorize('student.scholarship');
        $f=['status'=>Request::input('status',''),'type'=>Request::input('type',''),
            'classroom'=>Request::input('classroom',''),'year'=>Request::input('year','')];
        $ff=array_filter($f);
        $this->view('affairs/scholarship_report_print', ['title'=>'ทะเบียนผู้รับทุนการศึกษา',
            'rows'=>$this->m->scholarships($ff),'sum'=>$this->m->scholarshipSummary($ff),
            'backUrl'=>'affairs/scholarships','autoPrint'=>true],'print');
    }
}
