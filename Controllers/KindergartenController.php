<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Kindergarten;
use App\Models\AuditLog;

/**
 * ประเมินพัฒนาการเด็กปฐมวัย (อนุบาล) — 7 สมรรถนะ + ความเห็นครู/ผู้ปกครอง
 * เทอม 1 / สรุปเทอม 2 · คำนวณค่าเฉลี่ยร้อยละ + แดชบอร์ดสถานะ (สิทธิ์ academic.grades)
 */
class KindergartenController extends Controller
{
    private Kindergarten $m;
    public function __construct(){ $this->m = new Kindergarten(); }

    private function guard(): void
    {
        $this->authorize('academic.grades');
        if(!stage_enabled('kindergarten'))
            $this->back('dashboard','error','ยังไม่ได้เปิดใช้งานช่วงชั้นอนุบาล — เปิดได้ที่ ตั้งค่าระบบ → ช่วงชั้นที่เปิดสอน');
    }

    public function index(): void
    {
        $this->guard();
        $classrooms=$this->m->classrooms();
        $cid=(int)Request::input('classroom', $classrooms[0]['id'] ?? 0);
        $sem=(int)Request::input('semester',0) ?: $this->m->currentSemesterId();
        $this->view('kindergarten/index',[
            'title'=>'ประเมินพัฒนาการอนุบาล',
            'classrooms'=>$classrooms,'semesters'=>$this->m->semesters(),
            'cid'=>$cid,'sem'=>$sem,
            'classInfo'=>$cid?$this->m->classInfo($cid):null,
            'students'=>$cid?$this->m->students($cid):[],
            'assessments'=>($cid&&$sem)?$this->m->assessments($cid,$sem):[],
            'stats'=>($cid&&$sem)?$this->m->stats($cid,$sem):['total'=>0,'assessed'=>0,'overall'=>0,'perDomain'=>[]],
            'domains'=>Kindergarten::DOMAINS,'levels'=>Kindergarten::LEVELS,
            'indicators'=>$this->m->indicatorsByDomain(),
            'indScores'=>($cid&&$sem)?$this->m->indicatorScores($cid,$sem):[],
        ]);
    }

    public function save(): void
    {
        $this->guard(); $this->verifyCsrf();
        $cid=(int)Request::input('classroom',0);
        $sem=(int)Request::input('semester',0);
        if(!$sem) $this->back('kindergarten','error','กรุณาเลือกภาคเรียน');
        $by=Auth::id();
        $byDomain=$this->m->indicatorsByDomain();          // domain => [{id,title}]
        $ind=(array)Request::input('ind',[]);              // ind[indicator_id][student_id]=value
        $legacy=[]; foreach(array_keys(Kindergarten::DOMAINS) as $k) $legacy[$k]=(array)Request::input($k,[]);
        $tc=(array)Request::input('teacher_comment',[]);
        $pc=(array)Request::input('parent_comment',[]);

        // รวมรายชื่อ student id จากทุกแหล่ง
        $ids=[];
        foreach($ind as $byStu) $ids=array_merge($ids,array_keys((array)$byStu));
        foreach($legacy as $arr) $ids=array_merge($ids,array_keys($arr));
        $ids=array_merge($ids,array_keys($tc),array_keys($pc));

        foreach(array_unique(array_map('intval',$ids)) as $sid){
            if($sid<=0) continue;
            $d=[];
            foreach(array_keys(Kindergarten::DOMAINS) as $domain){
                $inds=$byDomain[$domain]??[];
                if($inds){
                    // ประเมินรายหัวข้อ → คะแนนรายด้าน = ค่าเฉลี่ยหัวข้อที่ประเมินแล้ว (ปัดเป็น 1-3)
                    $vals=[];
                    foreach($inds as $it){
                        $iid=(int)$it['id']; $v=(int)($ind[$iid][$sid]??0);
                        $this->m->saveIndicatorScore($sid,$sem,$iid,$v,$by);
                        if($v>0) $vals[]=$v;
                    }
                    $d[$domain]=$vals?(int)round(array_sum($vals)/count($vals)):0;
                } else {
                    $d[$domain]=(int)($legacy[$domain][$sid]??0);
                }
            }
            $d['teacher_comment']=$tc[$sid]??''; $d['parent_comment']=$pc[$sid]??'';
            $this->m->save($sid,$sem,$d,$by);
        }
        AuditLog::record($by,'assess','kindergarten_assessments',$cid);
        $this->back('kindergarten?classroom='.$cid.'&semester='.$sem,'success','บันทึกผลการประเมินแล้ว');
    }

    /** เพิ่ม/ลบ หัวข้อประเมินย่อยในสมรรถนะ (ครูกำหนดเอง) */
    public function indicatorAdd(): void
    {
        $this->guard(); $this->verifyCsrf();
        $domain=(string)Request::input('domain','');
        $title=trim((string)Request::input('title',''));
        if(!isset(Kindergarten::DOMAINS[$domain]) || $title==='')
            $this->back('kindergarten'.$this->kgQs(),'error','เลือกสมรรถนะและกรอกชื่อหัวข้อ');
        $this->m->indicatorAdd($domain,$title,Auth::id());
        $this->back('kindergarten'.$this->kgQs(),'success','เพิ่มหัวข้อประเมินแล้ว');
    }
    public function indicatorDelete(string $id): void
    {
        $this->guard(); $this->verifyCsrf();
        $this->m->indicatorDelete((int)$id);
        $this->back('kindergarten'.$this->kgQs(),'success','ลบหัวข้อประเมินแล้ว (รวมคะแนนของหัวข้อนี้)');
    }
    private function kgQs(): string
    {
        $cid=(int)Request::input('classroom',0); $sem=(int)Request::input('semester',0);
        return ($cid||$sem)?('?classroom='.$cid.'&semester='.$sem):'';
    }

    /** สมุดรายงานประจำตัวเด็กปฐมวัย รายบุคคล (รวมประวัติ+เติบโต+มาเรียน+7 สมรรถนะ 2 เทอม+ความเห็น) */
    public function report(string $sid): void
    {
        $this->guard();
        $st=$this->m->reportStudent((int)$sid);
        if(!$st) $this->back('kindergarten','error','ไม่พบนักเรียน');
        $terms=$this->m->yearSemesters();
        $byTerm=[];
        foreach($terms as $t){
            $byTerm[(int)$t['term']]=[
                'sem'=>$t,
                'assess'=>$this->m->assessmentOne((int)$sid,(int)$t['id']),
                'att'=>$this->m->attendanceTerm((int)$sid,(int)$t['id']),
            ];
        }
        $this->view('kindergarten/report_book',[
            'title'=>'สมุดรายงานประจำตัว - '.$st['name'],
            'st'=>$st,'guardian'=>$this->m->reportGuardian((int)$sid),
            'growth'=>$this->m->growth((int)$sid),'byTerm'=>$byTerm,
            'domains'=>Kindergarten::DOMAINS,'levels'=>Kindergarten::LEVELS,
            'backUrl'=>'kindergarten','autoPrint'=>true,
        ],'print');
    }

    public function print(): void
    {
        $this->guard();
        $cid=(int)Request::input('classroom',0);
        $sem=(int)Request::input('semester',0) ?: $this->m->currentSemesterId();
        if(!$cid) $this->back('kindergarten','error','กรุณาเลือกห้อง');
        $sems=$this->m->semesters(); $semLabel='';
        foreach($sems as $s){ if((int)$s['id']===$sem){ $semLabel='ภาคเรียนที่ '.$s['term'].'/'.$s['year_be']; break; } }
        $this->view('kindergarten/print',[
            'title'=>'แบบประเมินพัฒนาการอนุบาล','semLabel'=>$semLabel,
            'classInfo'=>$this->m->classInfo($cid),
            'students'=>$this->m->students($cid),
            'assessments'=>$this->m->assessments($cid,$sem),
            'stats'=>$this->m->stats($cid,$sem),
            'domains'=>Kindergarten::DOMAINS,'levels'=>Kindergarten::LEVELS,
            'backUrl'=>'kindergarten?classroom='.$cid.'&semester='.$sem,'autoPrint'=>true,
        ],'print');
    }
}
