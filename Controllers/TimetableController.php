<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Timetable;
use App\Models\Academic;
use App\Models\AuditLog;

class TimetableController extends Controller
{
    private Timetable $t;
    public function __construct(){ $this->t = new Timetable(); }

    public function build(): void
    {
        $this->authorize('schedule.manage');
        $a=new Academic();
        $classroomId=(int)Request::input('classroom',0);
        $semId=$this->t->currentSemesterId();
        $data=[
            'title'=>'จัดตารางสอน (AI + ลาก-วาง)',
            'classrooms'=>$a->classroomsList(),'classroomId'=>$classroomId,
            'cfg'=>$classroomId?$this->t->settingsFor($classroomId):$this->t->settings()+['level'=>'secondary'],
            'rooms'=>$this->t->rooms(),
            'homeroom'=>$classroomId?$this->t->homeroomName($classroomId):'',
            'assigns'=>$classroomId?$this->t->classAssignments($classroomId,$semId):[],
            'grid'=>$classroomId?$this->t->grid($classroomId,$semId):[],
            'stats'=>$classroomId?$this->t->stats($classroomId,$semId):null,
            'published'=>$classroomId?$this->t->isPublished($classroomId,$semId):null,
            'issues'=>($classroomId && Request::input('check'))?$this->t->validateGrid($classroomId,$semId):null,
        ];
        $this->view('schedule/build',$data);
    }

    public function autoGenerate(): void
    {
        $this->authorize('schedule.manage');
        $this->verifyCsrf();
        $classroomId=(int)Request::input('classroom',0);
        if(!$classroomId) $this->back('schedule/build','error','กรุณาเลือกห้องเรียน');
        $semId=$this->t->currentSemesterId();
        $res=$this->t->autoGenerate($classroomId,$semId);
        AuditLog::record(Auth::id(),'auto_schedule','class_schedules',$classroomId);
        $msg=$res['unplaced']
            ? 'จัดตารางอัตโนมัติแล้ว — แต่มีบางคาบที่จัดไม่ลง: '.implode(', ',$res['unplaced'])
            : 'จัดตารางอัตโนมัติเรียบร้อย (ครบทุกวิชาตามเงื่อนไข)';
        $this->back('schedule/build?classroom='.$classroomId, $res['unplaced']?'error':'success', $msg);
    }

    public function clear(): void
    {
        $this->authorize('schedule.manage');
        $this->verifyCsrf();
        $classroomId=(int)Request::input('classroom',0);
        $semId=$this->t->currentSemesterId();
        $this->t->clearGrid($classroomId,$semId,(bool)Request::input('all',false));
        $this->back('schedule/build?classroom='.$classroomId,'success','ล้างตารางแล้ว');
    }

    // ---------- ตั้งค่าเงื่อนไขการจัดตาราง (admin) ----------
    public function settingsPage(): void
    {
        $this->authorize('schedule.manage');
        $this->view('schedule/settings',[
            'title'=>'ตั้งค่าเงื่อนไขการจัดตาราง',
            'cfg'=>$this->t->settings(),
        ]);
    }
    public function settingsSave(): void
    {
        $this->authorize('schedule.manage');
        $this->verifyCsrf();
        $map=[
            'periods_primary'=>[4,10],'periods_secondary'=>[4,12],
            'lunch_primary'=>[1,10],'lunch_secondary'=>[1,12],
            'days_per_week'=>[1,7],'max_per_subject_per_day'=>[1,6],
            'max_consecutive'=>[1,8],'teacher_max_weekly'=>[5,40],
        ];
        foreach($map as $k=>[$min,$max]){
            $v=Request::input($k);
            if($v===null||$v==='') continue;
            $v=max($min,min($max,(int)$v));
            $this->t->saveSetting($k,(string)$v);
        }
        // เวลาเริ่มเรียนแยก 2 แบบ: อนุบาล/ประถม กับ มัธยม
        foreach(['start_primary','start_secondary'] as $sk){
            $sv=trim((string)Request::input($sk,''));
            if(preg_match('/^\d{2}:\d{2}$/',$sv)) $this->t->saveSetting($sk,$sv);
        }
        AuditLog::record(Auth::id(),'update','system_settings',null,null,['timetable'=>1]);
        $this->back('schedule/settings','success','บันทึกเงื่อนไขการจัดตารางแล้ว — มีผลกับการจัดครั้งถัดไป');
    }

    // ---------- Dashboard ----------
    public function dashboard(): void
    {
        $this->authorize('academic.schedule');
        $semId=$this->t->currentSemesterId();
        $this->view('schedule/dashboard',[
            'title'=>'Dashboard ตารางสอน',
            'dash'=>$this->t->dashboardData($semId),
            'summary'=>$this->t->summaryAll($semId),
        ]);
    }

    // ---------- เผยแพร่ ----------
    public function publish(): void
    {
        $this->authorize('schedule.manage');
        $this->verifyCsrf();
        $classroomId=(int)Request::input('classroom',0);
        $semId=$this->t->currentSemesterId();
        if(Request::input('unpublish')){
            $this->t->unpublish($classroomId,$semId);
            AuditLog::record(Auth::id(),'unpublish','timetable_publications',$classroomId);
            $this->back('schedule/build?classroom='.$classroomId,'success','ยกเลิกการเผยแพร่แล้ว');
        }
        $issues=$this->t->validateGrid($classroomId,$semId);
        if($issues) $this->back('schedule/build?classroom='.$classroomId.'&check=1','error','เผยแพร่ไม่ได้ — พบปัญหา '.count($issues).' รายการ กรุณาแก้ก่อน');
        $this->t->publish($classroomId,$semId,Auth::id());
        AuditLog::record(Auth::id(),'publish','timetable_publications',$classroomId);
        $this->back('schedule/build?classroom='.$classroomId,'success','เผยแพร่ตารางเรียบร้อย — ครู/นักเรียนดูได้ที่เมนูตารางเรียน');
    }

    // ---------- Export ----------
    public function exportExcel(): void
    {
        $this->authorize('academic.schedule');
        $classroomId=(int)Request::input('classroom',0);
        $semId=$this->t->currentSemesterId();
        $cfg=$this->t->settingsFor($classroomId);
        $grid=$this->t->grid($classroomId,$semId);
        $a=new Academic();
        $name='timetable_'.$classroomId;
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$name.'.csv"');
        echo "\xEF\xBB\xBF";
        $out=fopen('php://output','w');
        $days=[1=>'จันทร์',2=>'อังคาร',3=>'พุธ',4=>'พฤหัสบดี',5=>'ศุกร์'];
        $head=['วัน/คาบ'];
        for($p=1;$p<=$cfg['periods'];$p++) $head[]='คาบ '.$p.($p==$cfg['lunch']?' (พัก)':'');
        fputcsv($out,$head);
        foreach($days as $d=>$dn){
            $row=[$dn];
            for($p=1;$p<=$cfg['periods'];$p++){
                if($p==$cfg['lunch']){ $row[]='พักกลางวัน'; continue; }
                $c=$grid[$d][$p]??null;
                $row[]=$c ? $c['subject_code'].' '.$c['subject_name'].($c['room_name']?' ['.$c['room_name'].']':'') : '';
            }
            fputcsv($out,$row);
        }
        fclose($out); exit;
    }
    public function exportPdf(): void
    {
        $this->authorize('academic.schedule');
        $classroomId=(int)Request::input('classroom',0);
        $semId=$this->t->currentSemesterId();
        $a=new Academic();
        $cr=null; foreach($a->classroomsList() as $c){ if((int)$c['id']===$classroomId){ $cr=$c; break; } }
        $this->view('schedule/print',[
            'title'=>'ตารางสอน '.($cr['name']??''),
            'classroom'=>$cr,'cfg'=>$this->t->settingsFor($classroomId),
            'grid'=>$this->t->grid($classroomId,$semId),
        ],'print');
    }

    // ---------- ตรวจสอบตาราง ----------
    public function check(): void
    {
        $this->authorize('schedule.manage');
        $classroomId=(int)Request::input('classroom',0);
        $this->redirect('schedule/build?classroom='.$classroomId.'&check=1');
    }

    // ---------- API สำหรับลาก-วาง (คืน JSON) ----------
    public function place(): void
    {
        $this->authorize('schedule.manage');
        $this->verifyCsrf();
        $res=$this->t->placeOne(
            (int)Request::input('ta',0),(int)Request::input('day',0),(int)Request::input('period',0),
            Request::input('room')?(int)Request::input('room'):null, (bool)Request::input('lock',false)
        );
        $this->json($res);
    }
    public function unplace(): void
    {
        $this->authorize('schedule.manage');
        $this->verifyCsrf();
        $semId=$this->t->currentSemesterId();
        $this->t->removeOne((int)Request::input('classroom',0),$semId,(int)Request::input('day',0),(int)Request::input('period',0));
        $this->json(['ok'=>true]);
    }
    public function lock(): void
    {
        $this->authorize('schedule.manage');
        $this->verifyCsrf();
        $semId=$this->t->currentSemesterId();
        $this->t->toggleLock((int)Request::input('classroom',0),$semId,(int)Request::input('day',0),(int)Request::input('period',0));
        $this->json(['ok'=>true]);
    }
}
