<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\TeachingLog;
use App\Models\AuditLog;

/**
 * บันทึกหลังการสอน — ดึงครู/ตำแหน่ง/รายวิชา/ห้อง/จำนวนนักเรียนจากระบบ + CRUD
 * สิทธิ์ academic.grades (ครูผู้สอน) · หัวหน้า/แอดมินเห็นทุกคน
 */
class TeachingLogController extends Controller
{
    private TeachingLog $m;
    public function __construct(){ $this->m = new TeachingLog(); }

    private function guard(): void { $this->authorize('academic.grades'); }
    private function seesAll(): bool { return Auth::can('academic.curriculum') || Auth::can('academic.monitor'); }

    private function filter(): array
    {
        return [
            'teacher_id'    => (int)Request::input('teacher_id',0),
            'subject_group' => (int)Request::input('subject_group',0),
            'month'         => (string)Request::input('month',''),
        ];
    }

    public function index(): void
    {
        $this->guard();
        $f=$this->filter();
        // ครูทั่วไปเห็นเฉพาะของตัวเอง
        if(!$this->seesAll()){ $mine=$this->m->myTeacherId((int)Auth::id()); if($mine) $f['teacher_id']=$mine; }
        $this->view('teaching_log/index',[
            'title'=>'บันทึกหลังการสอน',
            'rows'=>$this->m->listLogs($f),
            'stats'=>$this->m->stats($f),
            'groups'=>$this->m->subjectGroups(),
            'f'=>$f, 'seesAll'=>$this->seesAll(),
        ]);
    }

    private function assignmentsFor(): array
    {
        $tid = $this->seesAll() ? null : $this->m->myTeacherId((int)Auth::id());
        return $this->m->assignments($tid);
    }

    public function create(): void
    {
        $this->guard();
        $this->view('teaching_log/form',[
            'title'=>'บันทึกหลังการสอน (สร้างใหม่)','t'=>null,
            'assignments'=>$this->assignmentsFor(),'heads'=>$this->m->heads(),
        ]);
    }
    public function store(): void
    {
        $this->guard(); $this->verifyCsrf();
        $id=$this->m->create($this->input(), Auth::id());
        AuditLog::record(Auth::id(),'create','teaching_logs',$id);
        $this->back('teaching-log/'.$id.'/edit','success','บันทึกหลังการสอนแล้ว');
    }
    public function edit(string $id): void
    {
        $this->guard();
        $t=$this->m->find((int)$id);
        if(!$t) $this->back('teaching-log','error','ไม่พบบันทึก');
        $this->view('teaching_log/form',[
            'title'=>'แก้ไขบันทึกหลังการสอน','t'=>$t,
            'assignments'=>$this->assignmentsFor(),'heads'=>$this->m->heads(),
        ]);
    }
    public function update(string $id): void
    {
        $this->guard(); $this->verifyCsrf();
        if(!$this->m->find((int)$id)) $this->back('teaching-log','error','ไม่พบบันทึก');
        $this->m->update((int)$id, $this->input());
        AuditLog::record(Auth::id(),'update','teaching_logs',(int)$id);
        $this->back('teaching-log/'.$id.'/edit','success','บันทึกการแก้ไขแล้ว');
    }
    public function delete(string $id): void
    {
        $this->guard(); $this->verifyCsrf();
        $this->m->delete((int)$id);
        AuditLog::record(Auth::id(),'delete','teaching_logs',(int)$id);
        $this->back('teaching-log','success','ลบบันทึกแล้ว');
    }
    public function print(string $id): void
    {
        $this->guard();
        $t=$this->m->find((int)$id);
        if(!$t) $this->back('teaching-log','error','ไม่พบบันทึก');
        $this->view('teaching_log/print',[
            'title'=>'บันทึกหลังการสอน','t'=>$t,
            'backUrl'=>'teaching-log/'.$id.'/edit','autoPrint'=>true,
        ],'print');
    }

    private function input(): array
    {
        $s=fn($k)=>trim((string)Request::input($k,''));
        $i=fn($k)=>(int)Request::input($k,0);
        return [
            'teaching_assignment_id'=>$i('teaching_assignment_id'),
            'teacher_id'=>$i('teacher_id') ?: null,
            'log_date'=>Request::input('log_date','')?:null,
            'period_no'=>$s('period_no'),
            'hours'=>(float)Request::input('hours',1),
            'unit_no'=>$s('unit_no'),
            'lesson_topic'=>$s('lesson_topic'),
            'students_total'=>$i('students_total'),
            'students_present'=>$i('students_present'),
            'students_absent'=>$i('students_absent'),
            'students_leave'=>$i('students_leave'),
            'learning_result'=>$s('learning_result'),
            'passed_count'=>$i('passed_count'),
            'problems'=>$s('problems'),
            'solutions'=>$s('solutions'),
            'head_comment'=>$s('head_comment'),
            'head_id'=>$i('head_id') ?: null,
        ];
    }
}
