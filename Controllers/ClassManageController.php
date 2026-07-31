<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Academic;
use App\Models\AuditLog;

/** ฐานข้อมูลฝ่ายวิชาการ: ชั้นเรียน / ครูผู้สอน / แมพนักเรียนเข้าห้อง */
class ClassManageController extends Controller
{
    private Academic $a;
    public function __construct(){ $this->a = new Academic(); }

    // ---------- ชั้นเรียน ----------
    public function classes(): void
    {
        $this->authorize('academic.curriculum');
        $grade=(string)Request::input('grade','');
        $this->view('classes/index',[
            'title'=>'จัดการชั้นเรียน',
            'rows'=>$this->a->classroomsFull($grade),
            'grades'=>$this->a->gradeLevels(),'grade'=>$grade,
        ]);
    }
    public function classShow(string $id): void
    {
        $this->authorize('academic.curriculum');
        $cr=$this->a->classroomFind((int)$id);
        if(!$cr) $this->back('classes','error','ไม่พบชั้นเรียน');
        $this->view('classes/show',[
            'title'=>'ชั้นเรียน '.$cr['name'],
            'cr'=>$cr,
            'students'=>$this->a->taStudents((int)$id),
            'candidates'=>$this->a->unenrolledStudents(),
            'teaching'=>$this->a->classTeachingList((int)$id),
        ]);
    }
    public function enroll(string $id): void
    {
        $this->authorize('academic.curriculum');
        $this->verifyCsrf();
        $sid=(int)Request::input('student_id',0);
        if(!$sid) $this->back('classes/'.$id,'error','กรุณาเลือกนักเรียน');
        $roll=Request::input('roll_number');
        $res=$this->a->enroll((int)$id,$sid,$roll!==''&&$roll!==null?(int)$roll:null);
        if(!$res['ok']) $this->back('classes/'.$id,'error',$res['msg']);
        AuditLog::record(Auth::id(),'enroll','student_enrollments',$sid,null,['classroom'=>(int)$id]);
        $this->back('classes/'.$id,'success','เพิ่มนักเรียนเข้าห้องแล้ว');
    }
    public function unenroll(string $id, string $sid): void
    {
        $this->authorize('academic.curriculum');
        $this->verifyCsrf();
        $this->a->unenroll((int)$id,(int)$sid);
        AuditLog::record(Auth::id(),'unenroll','student_enrollments',(int)$sid,null,['classroom'=>(int)$id]);
        $this->back('classes/'.$id,'success','นำนักเรียนออกจากห้องแล้ว');
    }

    public function namelistPrint(string $id): void
    {
        $this->authorize('academic.curriculum');
        $cr=$this->a->classroomFind((int)$id);
        if(!$cr) $this->back('classes','error','ไม่พบชั้นเรียน');
        $this->view('classes/namelist_print',[
            'title'=>'บัญชีรายชื่อนักเรียน '.$cr['name'],'cr'=>$cr,
            'students'=>$this->a->taStudents((int)$id),'backUrl'=>'classes/'.$id,'autoPrint'=>true,
        ],'print');
    }

    // ---------- พิมพ์ ปพ.6 (รายงานรายบุคคล) ----------
    public function pp6(string $id): void
    {
        $this->authorize('academic.curriculum');
        $studentIds=null;
        $sel=Request::input('students');
        if($sel){ $studentIds=is_array($sel)?$sel:explode(',',(string)$sel); }
        $one=Request::input('student');
        if($one){ $studentIds=[(int)$one]; }
        $data=$this->a->pp6((int)$id, $studentIds);
        if(!$data['cr']) $this->back('classes','error','ไม่พบชั้นเรียน');
        if(!$data['reports']) $this->back('classes/'.$id,'error','ไม่มีนักเรียน/วิชาให้พิมพ์');
        $data['title']='ปพ.6 - '.$data['cr']['name'];
        $data['backUrl']='classes/'.$id;
        $data['autoPrint']=true;
        $this->view('grades/pp6',$data,'print');
    }

    // ---------- ครูผู้สอน ----------
    public function teachers(): void
    {
        $this->authorize('academic.curriculum');
        $q=trim((string)Request::input('q',''));
        $this->view('teachers/index',[
            'title'=>'จัดการครูผู้สอน',
            'rows'=>$this->a->teachersWithLoad($q),'q'=>$q,
            'unavail'=>$this->a->unavailList(),
        ]);
    }
    public function teacherConfig(string $id): void
    {
        $this->authorize('academic.curriculum');
        $this->verifyCsrf();
        $days=(array)Request::input('work_days',[]);
        $days=array_values(array_filter(array_map('intval',$days),fn($d)=>$d>=1&&$d<=7));
        $this->a->teacherConfig((int)$id,
            Request::input('is_part_time')?1:0,
            $days?implode(',',$days):null,
            Request::input('max_weekly_periods')!==''&&Request::input('max_weekly_periods')!==null?(int)Request::input('max_weekly_periods'):null);
        AuditLog::record(Auth::id(),'update','personnel',(int)$id,null,['teacher_config'=>1]);
        $this->back('teachers','success','บันทึกเงื่อนไขครูเรียบร้อยแล้ว');
    }
    public function unavailStore(): void
    {
        $this->authorize('academic.curriculum');
        $this->verifyCsrf();
        $tid=(int)Request::input('teacher_id',0);
        $day=(int)Request::input('day_of_week',0);
        if(!$tid || $day<1 || $day>7) $this->back('teachers','error','กรุณาเลือกครูและวัน');
        $period=Request::input('period_no');
        $this->a->unavailAdd($tid,$day,$period!==''&&$period!==null?(int)$period:null,(string)Request::input('reason',''));
        $this->back('teachers','success','บันทึกเวลาไม่ว่างแล้ว');
    }
    public function unavailDelete(string $id): void
    {
        $this->authorize('academic.curriculum');
        $this->verifyCsrf();
        $this->a->unavailDelete((int)$id);
        $this->back('teachers','success','ลบเวลาไม่ว่างแล้ว');
    }
}
