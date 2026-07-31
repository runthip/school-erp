<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Health;
use App\Models\AuditLog;

/**
 * งานสุขภาพนักเรียน (BMI/น้ำหนัก/ส่วนสูง) — บันทึกรายบุคคล จัดเป็นรายห้องเรียน
 */
class HealthController extends Controller
{
    private Health $m;
    public function __construct(){ $this->m = new Health(); }

    private function guard(): void { $this->authorize('student.health'); }

    /** เลือกห้องเรียน */
    public function index(): void
    {
        $this->guard();
        $date=(string)Request::input('date', date('Y-m-d'));
        $this->view('health/index', [
            'title'=>'สุขภาพนักเรียน (BMI/น้ำหนัก/ส่วนสูง)',
            'classes'=>$this->m->classrooms($date),
            'date'=>$date,
        ]);
    }

    /** แผ่นบันทึกสุขภาพรายห้อง */
    public function entry(string $classroomId): void
    {
        $this->guard();
        $c=$this->m->classroomFind((int)$classroomId);
        if(!$c) $this->back('health','error','ไม่พบห้องเรียน');
        $date=(string)Request::input('date', date('Y-m-d'));
        $this->view('health/entry', [
            'title'=>'บันทึกสุขภาพ: '.$c['name'],
            'c'=>$c,'date'=>$date,
            'students'=>$this->m->roster((int)$classroomId,$date),
            'summary'=>$this->m->summary((int)$classroomId,$date),
        ]);
    }

    /** บันทึกทั้งห้อง (คำนวณ BMI อัตโนมัติ) */
    public function save(string $classroomId): void
    {
        $this->guard(); $this->verifyCsrf();
        $c=$this->m->classroomFind((int)$classroomId);
        if(!$c) $this->back('health','error','ไม่พบห้องเรียน');
        $date=(string)Request::input('date', date('Y-m-d'));
        $heights=(array)Request::input('height',[]);
        $weights=(array)Request::input('weight',[]);
        $ids=array_unique(array_merge(array_keys($heights),array_keys($weights)));
        $n=0;
        foreach($ids as $sid){
            $h=trim((string)($heights[$sid] ?? '')); $w=trim((string)($weights[$sid] ?? ''));
            if($h==='' && $w==='') continue;         // ไม่กรอก = ข้าม
            $this->m->save((int)$sid,$date, $h!==''?(float)$h:null, $w!==''?(float)$w:null);
            $n++;
        }
        AuditLog::record(Auth::id(),'update','health_records',(int)$classroomId,null,['date'=>$date,'n'=>$n]);
        $this->back("health/$classroomId?date=$date",'success','บันทึกสุขภาพ '.$n.' คนเรียบร้อย (คำนวณ BMI อัตโนมัติ)');
    }
}
