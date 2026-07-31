<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Models\Catalog;
use App\Models\Academic;
use App\Models\AuditLog;
use App\Core\Schema;

class AcademicController extends Controller
{
    private Academic $a;
    public function __construct(){ $this->a = new Academic(); }

    /** กัน 500 ถ้ายังไม่ import 26_attendance_flag.sql */
    private function guardAttendance(): void
    {
        $miss=Schema::missingFor('attendance');
        if($miss){
            $this->view('documents/needs_migration',['title'=>'ต้องนำเข้าฐานข้อมูลเพิ่ม','missing'=>$miss]);
            exit;
        }
    }

    // ---------- หลักสูตร / รายวิชา ----------
    public function subjects(): void
    {
        $this->authorize('academic.curriculum');
        $q=trim((string)Request::input('q','')); $group=(string)Request::input('group',''); $type=(string)Request::input('type','');
        $c=new Catalog();
        $this->view('subjects/index',['title'=>'รายวิชา / หลักสูตร','rows'=>$c->subjects($q,$group,$type),'groups'=>$c->subjectGroups(),'q'=>$q,'group'=>$group,'type'=>$type]);
    }

    public function importForm(): void
    {
        $this->authorize('academic.curriculum');
        $this->view('subjects/import',['title'=>'นำเข้ารายวิชา (Excel/CSV)']);
    }

    /** ดาวน์โหลด template CSV */
    public function template(): void
    {
        $this->authorize('academic.curriculum');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="subjects_template.csv"');
        echo "\xEF\xBB\xBF"; // BOM ให้ Excel อ่านภาษาไทยถูก
        $out=fopen('php://output','w');
        fputcsv($out,['subject_code','name_th','subject_group_code','credit','hours_per_week','subject_type']);
        fputcsv($out,['ค22101','คณิตศาสตร์ 3','MATH','1.5','3','core']);
        fputcsv($out,['ว22102','วิทยาการคำนวณ 2','SCI','1.0','2','additional']);
        fclose($out);
        exit;
    }

    public function importCsv(): void
    {
        $this->authorize('academic.curriculum');
        $this->verifyCsrf();
        if(empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])){
            $this->back('subjects/import','error','กรุณาเลือกไฟล์ CSV');
        }
        $groups=$this->a->subjectGroupsMap();
        $validTypes=['core','additional','activity'];
        $fh=fopen($_FILES['file']['tmp_name'],'r');
        $first=fgets($fh); // ข้าม BOM + header
        if(str_starts_with($first,"\xEF\xBB\xBF")) $first=substr($first,3);
        $header=str_getcsv($first);
        $idx=array_flip(array_map('trim',$header));
        $need=['subject_code','name_th','subject_group_code','credit','hours_per_week','subject_type'];
        foreach($need as $col){ if(!isset($idx[$col])){ fclose($fh); $this->back('subjects/import','error','หัวคอลัมน์ไม่ถูกต้อง ต้องมี: '.implode(', ',$need)); } }

        $added=0; $skipped=0; $errors=[];
        $line=1;
        while(($row=fgetcsv($fh))!==false){
            $line++;
            if(count(array_filter($row, fn($v)=>trim((string)$v)!==''))===0) continue;
            $code=trim((string)($row[$idx['subject_code']]??''));
            $name=trim((string)($row[$idx['name_th']]??''));
            if($code===''||$name===''){ $errors[]="บรรทัด $line: ขาดรหัสวิชา/ชื่อวิชา"; continue; }
            if($this->a->subjectExists($code)){ $skipped++; continue; }
            $gcode=strtoupper(trim((string)($row[$idx['subject_group_code']]??'')));
            $type=trim((string)($row[$idx['subject_type']]??'core'));
            if(!in_array($type,$validTypes,true)) $type='core';
            $this->a->subjectInsert([
                'subject_code'=>$code,'name_th'=>$name,
                'subject_group_id'=>$groups[$gcode]??null,
                'credit'=>(float)($row[$idx['credit']]??0),
                'hours_per_week'=>(int)($row[$idx['hours_per_week']]??0),
                'subject_type'=>$type,
            ]);
            $added++;
        }
        fclose($fh);
        AuditLog::record(Auth::id(),'import','subjects',null,null,['added'=>$added]);
        $msg="นำเข้าสำเร็จ $added วิชา".($skipped?" · ข้าม $skipped วิชา (รหัสซ้ำ)":'');
        if($errors) Session::flash('error', implode(' | ', array_slice($errors,0,5)));
        $this->back('subjects','success',$msg);
    }

    // ---------- มอบหมายวิชาสอน ----------
    public function assignments(): void
    {
        $this->authorize('academic.curriculum');
        $sem=Request::input('sem','');
        $semId=$sem!==''?(int)$sem:null;
        $this->view('teaching/index',[
            'title'=>'มอบหมายวิชาสอน','rows'=>$this->a->assignments($semId),
            'subjects'=>$this->a->subjectsList(),'classrooms'=>$this->a->classroomsList(),
            'teachers'=>$this->a->teachersList(),'semesters'=>$this->a->semestersList(),
            'sem'=>$sem,
        ]);
    }
    public function assignmentStore(): void
    {
        $this->authorize('academic.curriculum');
        $this->verifyCsrf();
        $d=Request::only(['semester_id','subject_id','classroom_id','teacher_id']);
        foreach($d as $v){ if(!$v) $this->back('teaching','error','กรุณาเลือกข้อมูลให้ครบ'); }
        if($this->a->assignmentExists($d)) $this->back('teaching','error','มีการมอบหมายนี้อยู่แล้ว');
        $id=$this->a->assignmentCreate($d);
        AuditLog::record(Auth::id(),'create','teaching_assignments',$id);
        $this->back('teaching','success','มอบหมายวิชาสอนเรียบร้อยแล้ว');
    }
    public function assignmentLoad(string $id): void
    {
        $this->authorize('academic.curriculum');
        $this->verifyCsrf();
        $wp=max(1,min(20,(int)Request::input('weekly_periods',2)));
        $this->a->setWeeklyPeriods((int)$id,$wp);
        AuditLog::record(Auth::id(),'update','teaching_assignments',(int)$id,null,['weekly_periods'=>$wp]);
        $this->back('teaching','success','บันทึกภาระสอน '.$wp.' คาบ/สัปดาห์แล้ว');
    }
    public function assignmentDelete(string $id): void
    {
        $this->authorize('academic.curriculum');
        $this->verifyCsrf();
        $this->a->assignmentRemove((int)$id);
        AuditLog::record(Auth::id(),'delete','teaching_assignments',(int)$id);
        $this->back('teaching','success','ลบการมอบหมายวิชาสอนแล้ว (คะแนน/เช็คชื่อที่ผูกอยู่ถูกลบด้วย)');
    }

    // ---------- บันทึกคะแนน ----------
    public function scores(): void
    {
        $this->authorize('academic.grades');
        $this->view('scores/index',['title'=>'บันทึกคะแนน','rows'=>$this->a->assignments($this->a->currentSemester()['id']??null)]);
    }
    public function scoreEntry(string $taId): void
    {
        $this->authorize('academic.grades');
        $ta=$this->a->taDetail((int)$taId);
        if(!$ta) $this->back('scores','error','ไม่พบวิชาที่มอบหมาย');
        $this->view('scores/entry',[
            'title'=>'บันทึกคะแนน: '.$ta['subject_name'].' '.$ta['classroom'],
            'ta'=>$ta,'components'=>$this->a->components((int)$taId),
            'students'=>$this->a->taStudents((int)$ta['classroom_id']),
            'scores'=>$this->a->scoresMap((int)$taId),
            'ratio'=>$this->a->ratioFor((int)$taId),
            'special'=>$this->a->specialMap((int)$taId),
        ]);
    }
    // ---------- โครงสร้างรายวิชา (มาตรฐาน/ตัวชี้วัด/ชม./คะแนน) ----------
    public function pp5(string $taId): void
    {
        $this->authorize('academic.grades');
        $data=$this->a->pp5((int)$taId);
        if(!$data) $this->back('scores','error','ไม่พบวิชา');
        $data['title']='ปพ.5 - '.$data['ta']['classroom'];
        $data['backUrl']='scores/'.$taId;
        $data['autoPrint']=true;
        $this->view('scores/pp5',$data,'print');
    }

    // ---------- ประเมินอ่าน-คิด-เขียน / คุณลักษณะ / สมรรถนะ ----------
    public function evaluate(string $taId): void
    {
        $this->authorize('academic.grades');
        $ta=$this->a->taDetail((int)$taId);
        if(!$ta) $this->back('scores','error','ไม่พบวิชา');
        $tab=(string)Request::input('tab','reading');
        if(!in_array($tab,['reading','character','competency'],true)) $tab='reading';
        $this->view('scores/evaluate',[
            'title'=>'ประเมินคุณลักษณะ: '.$ta['subject_name'].' '.$ta['classroom'],
            'ta'=>$ta,'tab'=>$tab,
            'students'=>$this->a->taStudents((int)$ta['classroom_id']),
            'map'=>$this->a->evalMap((int)$taId),
        ]);
    }
    public function evaluateSave(string $taId): void
    {
        $this->authorize('academic.grades');
        $this->verifyCsrf();
        $ta=$this->a->taDetail((int)$taId);
        if(!$ta) $this->back('scores','error','ไม่พบวิชา');
        $tab=(string)Request::input('tab','reading');
        $items=\App\Models\Academic::evalItems()[$tab]??[];
        $ev=(array)Request::input('ev',[]); // ev[studentId][itemKey]=level|''
        foreach($this->a->taStudents((int)$ta['classroom_id']) as $st){
            $sid=(int)$st['id'];
            foreach($items as $key=>$label){
                $v=$ev[$sid][$key]??'';
                $level=($v===''||$v===null)?null:(int)$v;
                $this->a->evalSet((int)$taId,$sid,$tab,$key,$level);
            }
        }
        AuditLog::record(Auth::id(),'update','student_evaluations',(int)$taId,null,['tab'=>$tab]);
        $this->back("scores/$taId/evaluate?tab=$tab",'success','บันทึกผลประเมินเรียบร้อยแล้ว');
    }
    public function evaluateCopy(string $taId): void
    {
        $this->authorize('academic.grades');
        $this->verifyCsrf();
        $tab=(string)Request::input('tab','reading');
        if(!in_array($tab,['reading','character','competency'],true)) $tab='reading';
        $from=(int)Request::input('from_student',0);
        $to=(array)Request::input('to_students',[]);
        if(!$from||!$to) $this->back("scores/$taId/evaluate?tab=$tab",'error','กรุณาเลือกนักเรียนต้นทางและปลายทาง');
        $n=$this->a->evalCopy((int)$taId,$from,$to,$tab);
        AuditLog::record(Auth::id(),'copy','student_evaluations',(int)$taId,null,['tab'=>$tab,'count'=>$n]);
        $this->back("scores/$taId/evaluate?tab=$tab",'success',"คัดลอกผลประเมินไปยังนักเรียน $n คนแล้ว");
    }

    public function structure(string $taId): void
    {
        $this->authorize('academic.grades');
        $ta=$this->a->taDetail((int)$taId);
        if(!$ta) $this->back('scores','error','ไม่พบวิชา');
        $this->view('scores/structure',[
            'title'=>'โครงสร้างรายวิชา: '.$ta['subject_name'].' '.$ta['classroom'],
            'ta'=>$ta,'components'=>$this->a->components((int)$taId),
            'ratio'=>$this->a->ratioFor((int)$taId),'sysRatio'=>$this->a->getRatio(),
        ]);
    }
    public function structureRatio(string $taId): void
    {
        $this->authorize('academic.grades');
        $this->verifyCsrf();
        if(!$this->a->taDetail((int)$taId)) $this->back('scores','error','ไม่พบวิชา');
        if(Request::input('use_default')){
            $this->a->setTaRatio((int)$taId, null, null);
            $this->back("scores/$taId/structure",'success','ตั้งให้ใช้สัดส่วนค่าเริ่มต้นของระบบแล้ว');
        }
        $during=max(0,min(100,(int)Request::input('during',70)));
        $this->a->setTaRatio((int)$taId, $during, 100-$during);
        $this->back("scores/$taId/structure",'success','บันทึกสัดส่วนของวิชานี้เป็น '.$during.':'.(100-$during).' แล้ว');
    }
    public function componentStore(string $taId): void
    {
        $this->authorize('academic.grades');
        $this->verifyCsrf();
        $d=Request::only(['name','component_type','max_score','weight','sort_order','standard_code','indicator','hours']);
        if(trim((string)$d['name'])===''||!$d['max_score']) $this->back("scores/$taId/structure",'error','กรุณากรอกชื่อรายการและคะแนนเต็ม');
        $d['component_type']=in_array($d['component_type'],['formative','midterm','final','other'],true)?$d['component_type']:'formative';
        $d['sort_order']=(int)($d['sort_order']?:0);
        $d['hours']=$d['hours']!==''?(float)$d['hours']:null;
        $this->a->componentCreate((int)$taId,$d);
        $this->back("scores/$taId/structure",'success','เพิ่มรายการคะแนนแล้ว');
    }
    public function componentDelete(string $taId, string $id): void
    {
        $this->authorize('academic.grades');
        $this->verifyCsrf();
        $this->a->componentDelete((int)$id,(int)$taId);
        $this->back("scores/$taId/structure",'success','ลบรายการคะแนนแล้ว');
    }
    public function scoreSave(string $taId): void
    {
        $this->authorize('academic.grades');
        $this->verifyCsrf();
        $ta=$this->a->taDetail((int)$taId);
        if(!$ta) $this->back('scores','error','ไม่พบวิชา');
        $components=$this->a->components((int)$taId);
        $students=$this->a->taStudents((int)$ta['classroom_id']);
        $input=(array)Request::input('score',[]); // score[studentId][compId]=value
        $special=(array)Request::input('special',[]); // special[studentId] = ''|r|ms
        $ratio=$this->a->ratioFor((int)$taId);
        $by=Auth::id();
        foreach($students as $st){
            $sid=(int)$st['id']; $studentScores=[];
            foreach($components as $c){
                $cid=(int)$c['id'];
                $val=$input[$sid][$cid]??'';
                $score=($val===''||$val===null)?null:(float)$val;
                $this->a->upsertScore($cid,$sid,$score,$by);
                $studentScores[$cid]=$score??0;
            }
            $sp=$special[$sid]??'';
            if($sp==='r'||$sp==='ms'){
                // ผลพิเศษ ร/มส — ไม่คิดคะแนน/เกรด
                $this->a->upsertSpecialGrade($sid,(int)$taId,$sp);
            } else {
                $percent=\App\Models\Academic::computePercent($components,$studentScores,$ratio);
                $grade=Academic::scoreToGrade($percent);
                $this->a->upsertFinalGrade($sid,(int)$taId,$percent,$grade);
            }
        }
        AuditLog::record($by,'update','scores',(int)$taId);
        $this->back("scores/$taId",'success','บันทึกคะแนนและตัดเกรดตามสัดส่วน '.$ratio['during'].':'.$ratio['final'].' เรียบร้อยแล้ว');
    }

    // ---------- ผลการเรียน / GPA ----------
    public function grades(): void
    {
        $this->authorize('academic.grades');
        $classroomId=(int)Request::input('classroom',0);
        $semId=$this->a->currentSemester()['id']??0;
        $rows=$classroomId?$this->a->classroomFinalGrades($classroomId,$semId):[];
        // group by student
        $byStudent=[];
        foreach($rows as $r){ $byStudent[$r['student_id']]['info']=$r; $byStudent[$r['student_id']]['grades'][]=$r; }
        $this->view('grades/index',[
            'title'=>'ผลการเรียน (GPA)','classrooms'=>$this->a->classroomsList(),
            'classroomId'=>$classroomId,'byStudent'=>$byStudent,
        ]);
    }
    public function transcript(string $studentId): void
    {
        $this->authorize('academic.pp');
        $s=$this->a->studentInfo((int)$studentId);
        if(!$s) $this->back('grades','error','ไม่พบนักเรียน');
        $grades=$this->a->studentGrades((int)$studentId);
        $this->view('grades/transcript',[
            'title'=>'ผลการเรียน (Transcript)','s'=>$s,'grades'=>$grades,
            'gpa'=>Academic::computeGpa($grades),
        ]);
    }
    public function pp1(string $studentId): void
    {
        $this->authorize('academic.pp');
        $data=$this->a->pp1((int)$studentId);
        if(!$data) $this->back('grades','error','ไม่พบนักเรียน');
        $data['title']='ปพ.1 - '.($data['st']['first_name']??'');
        $data['backUrl']='transcript/'.$studentId;
        $data['autoPrint']=true;
        $this->view('grades/pp1',$data,'print');
    }

    public function transcriptPrint(string $studentId): void
    {
        $this->authorize('academic.pp');
        $s=$this->a->studentInfo((int)$studentId);
        if(!$s) $this->back('grades','error','ไม่พบนักเรียน');
        $grades=$this->a->studentGrades((int)$studentId);
        $this->view('grades/transcript_print',[
            'title'=>'ระเบียนแสดงผลการเรียน','s'=>$s,'grades'=>$grades,
            'gpa'=>Academic::computeGpa($grades),'backUrl'=>'transcript/'.$studentId,'autoPrint'=>true,
        ],'print');
    }

    public function transcriptList(): void
    {
        $this->authorize('academic.pp');
        $this->view('grades/transcript_list',['title'=>'ปพ. / Transcript','students'=>$this->a->studentsForTranscript()]);
    }
    public function zeroRms(): void
    {
        $this->authorize('academic.grades');
        $f=[
            'grade_level'   => (int)Request::input('grade_level',0),
            'classroom'     => (int)Request::input('classroom',0),
            'subject_group' => (int)Request::input('subject_group',0),
            'result'        => (string)Request::input('result',''),
            'status'        => (string)Request::input('status',''),
        ];
        $this->view('grades/zero_rms',[
            'title'=>'นักเรียนติด 0 ร มส',
            'canEdit'=>\App\Core\Auth::can('academic.curriculum'),
            'rows'=>$this->a->zeroRMsList($f),
            'stats'=>$this->a->zeroRMsStats($f),
            'opts'=>$this->a->zeroRMsFilterOptions(),
            'wk01'=>$this->a->wk01Assignments($f),
            'f'=>$f,
        ]);
    }

    /** พิมพ์แบบ วก.01 — ขออนุมัติผลการเรียน 0/ร/มผ (รายครู-รายวิชา-ห้อง) */
    public function zeroRmsWk01(string $taId): void
    {
        $this->authorize('academic.grades');
        $h=$this->a->wk01Header((int)$taId);
        if(!$h) $this->back('zero-rms','error','ไม่พบรายวิชา');
        $this->view('grades/wk01_print',[
            'title'=>'แบบ วก.01 ขออนุมัติผลการเรียน '.($h['subject_name']??''),
            'h'=>$h,'students'=>$this->a->wk01Students((int)$taId),
            'backUrl'=>'zero-rms','autoPrint'=>true,
        ],'print');
    }

    /** บันทึก/แก้ไข เกรดใหม่ (แก้ 0 ร มส) หรือยกเลิก */
    public function zeroRmsFix(): void
    {
        // แก้ไขผลการเรียนได้เฉพาะแอดมิน/หัวหน้าฝ่ายวิชาการ · ครูดูและพิมพ์ได้อย่างเดียว
        $this->authorize('academic.curriculum'); $this->verifyCsrf();
        $fgId=(int)Request::input('final_grade_id',0);
        $orig=(string)Request::input('original_result','0');
        if(Request::input('undo')){
            $this->a->undoGradeFix($fgId);
            $this->back('zero-rms','success','ยกเลิกการแก้เกรดแล้ว');
        }
        $newGrade=trim((string)Request::input('new_grade',''));
        if($fgId<=0 || $newGrade===''){ $this->back('zero-rms','error','กรุณาระบุเกรดใหม่'); }
        $this->a->recordGradeFix($fgId, in_array($orig,['0','r','ms'],true)?$orig:'0', $newGrade,
            Request::input('fixed_date',null)?:date('Y-m-d'), Request::input('note',null), \App\Core\Auth::id());
        $this->back('zero-rms','success','บันทึกเกรดใหม่แล้ว');
    }

    // ---------- เช็คชื่อ ----------
    public function attendance(): void
    {
        $this->authorize('academic.attendance');
        $this->view('attendance/index',['title'=>'เช็คชื่อ (เช็คเวลาเรียน)','rows'=>$this->a->assignments($this->a->currentSemester()['id']??null)]);
    }
    // ---------- เช็คชื่อหน้าเสาธง (เข้าโรงเรียน) ----------
public function flag(): void
    {
        $this->authorize('academic.attendance'); $this->guardAttendance();
        $u=Auth::user();
        $pid=($u && ($u['linked_type'] ?? '')==='personnel') ? (int)($u['linked_id'] ?? 0) : 0;
        // ครูที่ปรึกษาเห็นเฉพาะห้องตน · ผู้บริหาร/หัวหน้าวิชาการเห็นทุกห้อง
        $seeAll = Auth::can('academic.attendance_report') &&
                  (Auth::can('admin.dashboard') || $this->hasAcademicHead());
        $classes = ($pid && !$seeAll) ? $this->a->homeroomClasses($pid) : $this->a->allClassesForFlag();
        $this->view('attendance/flag_index',[
            'title'=>'เช็คชื่อหน้าเสาธง (เข้าโรงเรียน)',
            'classes'=>$classes,'settings'=>$this->a->flagSettings(),
            'isAdvisor'=>($pid>0 && !$seeAll)]);
    }

    private function hasAcademicHead(): bool
    {
        return (bool)array_intersect(
            ['head_academic','director','deputy_director'], Auth::roles());
    }

public function flagEntry(string $classroomId): void
    {
        $this->authorize('academic.attendance'); $this->guardAttendance();
        $c=$this->a->classroom((int)$classroomId);
        if(!$c) $this->back('attendance/flag','error','ไม่พบห้องเรียน');
        $date=(string)Request::input('date',date('Y-m-d'));
        $this->view('attendance/flag_entry',[
            'title'=>'เช็คชื่อหน้าเสาธง: '.$c['name'],
            'c'=>$c,'date'=>$date,
            'students'=>$this->a->classRoster((int)$classroomId),
            'marks'=>$this->a->flagMap((int)$classroomId,$date),
            'settings'=>$this->a->flagSettings()]);
    }

public function flagSave(string $classroomId): void
    {
        $this->authorize('academic.attendance'); $this->guardAttendance(); $this->verifyCsrf();
        $c=$this->a->classroom((int)$classroomId);
        if(!$c) $this->back('attendance/flag','error','ไม่พบห้องเรียน');
        $date=(string)Request::input('date',date('Y-m-d'));
        $marks=(array)Request::input('mark',[]);
        $notes=(array)Request::input('note',[]);
        $times=(array)Request::input('arrived',[]);
        $set=$this->a->flagSettings();
        $now=date('H:i:s');                     // เวลาปัจจุบันตอนกดบันทึก
        $by=Auth::id(); $n=0;
        foreach($marks as $sid=>$status){
            $arrived=trim((string)($times[$sid] ?? ''));
            // เช็ค "มา" โดยไม่ได้กรอกเวลา → ดึงเวลาปัจจุบันตอนบันทึกอัตโนมัติ
            if($arrived==='' && $status==='present') $arrived=$now;
            // มีเวลามา (กรอกเอง/อัตโนมัติ) → คำนวณสถานะ (มา/สาย/ขาด) จากเวลา
            if($arrived!=='') $status=$this->a->statusFromTime($arrived,$set);
            $status=in_array($status,['present','absent','late','leave','activity'],true)?$status:'present';
            $note=trim((string)($notes[$sid] ?? ''));
            $this->a->saveFlag((int)$classroomId,(int)$sid,$date,$status,$note?:null,$arrived?:null,$by);
            $n++;
        }
        AuditLog::record($by,'update','attendances',(int)$classroomId,null,['type'=>'flag','n'=>$n]);
        $this->back("attendance/flag/$classroomId?date=$date",'success','บันทึกการเช็คชื่อหน้าเสาธง '.$n.' คนเรียบร้อย');
    }

    /** มาทั้งห้อง (ปุ่มลัด) */
public function flagAllPresent(string $classroomId): void
    {
        $this->authorize('academic.attendance'); $this->guardAttendance(); $this->verifyCsrf();
        $date=(string)Request::input('date',date('Y-m-d'));
        $now=date('H:i:s');                     // เวลาปัจจุบันตอนกดบันทึก
        $by=Auth::id(); $n=0;
        foreach($this->a->classRoster((int)$classroomId) as $st){
            $this->a->saveFlag((int)$classroomId,(int)$st['id'],$date,'present',null,$now,$by);
            $n++;
        }
        AuditLog::record($by,'update','attendances',(int)$classroomId,null,['type'=>'flag','all_present'=>$n]);
        $this->back("attendance/flag/$classroomId?date=$date",'success','บันทึกมาเรียนทั้งห้อง '.$n.' คน (เวลา '.substr($now,0,5).')');
    }

    // ---------- จัดการนักเรียน (งานวิชาการ): แมพนักเรียน ↔ ห้องเรียน ----------
    public function students(): void
    {
        $this->authorize('academic.curriculum');
        $f=[
            'q'=>trim((string)Request::input('q','')),
            'grade'=>(int)Request::input('grade',0),
            'classroom'=>(int)Request::input('classroom',0),
            'status'=>(string)Request::input('status',''),
        ];
        $rows=$this->a->studentsWithClass($f);
        $assigned=0; foreach($rows as $r) if(!empty($r['classroom_id'])) $assigned++;
        $this->view('academic/students',[
            'title'=>'จัดการนักเรียน (งานวิชาการ)',
            'rows'=>$rows,'f'=>$f,
            'classrooms'=>$this->a->classroomsFull(),
            'grades'=>$this->a->gradeLevels(),
            'summary'=>['total'=>count($rows),'assigned'=>$assigned,'unassigned'=>count($rows)-$assigned],
        ]);
    }

    public function studentAssign(string $id): void
    {
        $this->authorize('academic.curriculum'); $this->verifyCsrf();
        $classroom=(int)Request::input('classroom_id',0);
        $rollIn=Request::input('roll_number');
        $roll=($rollIn!==''&&$rollIn!==null)?(int)$rollIn:null;
        $res=$this->a->assignClassroom((int)$id,$classroom,$roll);
        AuditLog::record(Auth::id(),'assign','student_enrollments',(int)$id,null,['classroom'=>$classroom]);
        $back='academic/students'.($this->keepQuery());
        $this->back($back, $res['ok']?'success':'error', $res['msg'] ?? 'บันทึกแล้ว');
    }

    /** คงตัวกรองเดิมไว้หลังบันทึก */
    private function keepQuery(): string
    {
        $keep=array_filter([
            'q'=>Request::input('f_q',''),'grade'=>Request::input('f_grade',''),
            'classroom'=>Request::input('f_classroom',''),'status'=>Request::input('f_status',''),
        ], fn($v)=>$v!==''&&$v!=='0');
        return $keep?('?'.http_build_query($keep)):'';
    }

    // ---------- รายงานการมาเรียน ----------
public function attendanceReport(): void
    {
        $this->authorize('academic.attendance_report'); $this->guardAttendance();
        $u=Auth::user();
        $pid=($u && ($u['linked_type'] ?? '')==='personnel') ? (int)($u['linked_id'] ?? 0) : 0;
        $seeAll = Auth::can('admin.dashboard') || $this->hasAcademicHead();
        $classes = ($pid && !$seeAll) ? $this->a->homeroomClasses($pid) : $this->a->allClassesForFlag();

        $cid=(int)Request::input('classroom',0);
        if(!$cid && $classes) $cid=(int)$classes[0]['id'];
        $from=(string)Request::input('from',date('Y-m-01'));
        $to=(string)Request::input('to',date('Y-m-d'));
        $type=(string)Request::input('type','flag');
        if(!in_array($type,['flag','subject','all'],true)) $type='flag';

        $report=[]; $daily=[]; $alert=[]; $current=null;
        if($cid){
            $current=$this->a->classroom($cid);
            $report=$this->a->attendanceReport($cid,$from,$to,$type);
            $daily=$this->a->attendanceDaily($cid,$from,$to,$type);
            $alert=$this->a->attendanceAlert($cid,$from,$to);
        }
        $this->view('attendance/report',[
            'title'=>'รายงานการมาเรียน',
            'classes'=>$classes,'cid'=>$cid,'current'=>$current,
            'from'=>$from,'to'=>$to,'type'=>$type,
            'report'=>$report,'daily'=>$daily,'alert'=>$alert]);
    }

    /** คำนวณช่วงวันที่จาก period=day/week/month + anchor date */
    private function reportRange(): array
    {
        $period=(string)Request::input('period','month');
        $date=(string)Request::input('date',date('Y-m-d'));
        $ts=strtotime($date) ?: time();
        if($period==='day'){
            $from=$to=date('Y-m-d',$ts);
            $label='ประจำวันที่ '.$this->thDate($from);
        } elseif($period==='week'){
            $dow=(int)date('N',$ts);              // 1=จ. 7=อา.
            $from=date('Y-m-d',strtotime("-".($dow-1)." day",$ts));
            $to=date('Y-m-d',strtotime("+".(7-$dow)." day",$ts));
            $label='ประจำสัปดาห์ '.$this->thDate($from).' – '.$this->thDate($to);
        } else {
            $period='month';
            $from=date('Y-m-01',$ts);
            $to=date('Y-m-t',$ts);
            $label='ประจำเดือน '.$this->thMonth($from);
        }
        return [$period,$from,$to,$label];
    }
    private function thDate(string $d): string
    { return date('j/n/',strtotime($d)).(date('Y',strtotime($d))+543); }
    private function thMonth(string $d): string
    {
        $m=['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
            'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
        return $m[(int)date('n',strtotime($d))].' '.(date('Y',strtotime($d))+543);
    }

    /** รายงานภาพรวม export เป็น PDF (พิมพ์ผ่านเบราว์เซอร์) */
    public function reportExportPdf(): void
    {
        $this->authorize('academic.attendance_report'); $this->guardAttendance();
        [$period,$from,$to,$label]=$this->reportRange();
        $this->view('attendance/report_print',[
            'title'=>'รายงานการมาเรียน '.$label,
            'label'=>$label,'period'=>$period,'from'=>$from,'to'=>$to,
            'summary'=>$this->a->flagStatsRange($from,$to),
            'rows'=>$this->a->flagByClassroom($from,$to),
            'autoPrint'=>true,'backUrl'=>'attendance/report'],'print');
    }

    /** รายงานภาพรวม export เป็น Excel (CSV UTF-8 BOM เปิดใน Excel ได้) */
    public function reportExportExcel(): void
    {
        $this->authorize('academic.attendance_report'); $this->guardAttendance();
        [$period,$from,$to,$label]=$this->reportRange();
        $sum=$this->a->flagStatsRange($from,$to);
        $rows=$this->a->flagByClassroom($from,$to);
        $school=school_info()['name_th'] ?? 'โรงเรียน';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance_'.$period.'_'.$from.'.csv"');
        echo "\xEF\xBB\xBF";                     // BOM ให้ Excel รู้ว่าเป็น UTF-8
        $out=fopen('php://output','w');
        fputcsv($out,[$school]);
        fputcsv($out,['รายงานการมาเรียน (หน้าเสาธง) '.$label]);
        fputcsv($out,['ช่วงวันที่',$this->thDate($from).' ถึง '.$this->thDate($to),'จำนวนวันที่เช็ค',$sum['days']]);
        fputcsv($out,[]);
        fputcsv($out,['ห้องเรียน','ระดับชั้น','ครูที่ปรึกษา','มา','สาย','ขาด','ลา','กิจกรรม','รวมครั้ง','ร้อยละมา']);
        foreach($rows as $r){
            $tot=(int)$r['total']; $pres=(int)$r['present']+(int)$r['late'];
            $rate=$tot>0?round($pres/$tot*100).'%':'-';
            fputcsv($out,[$r['classroom'],$r['grade'],$r['homeroom_teacher'],
                (int)$r['present'],(int)$r['late'],(int)$r['absent'],
                (int)$r['leave_cnt'],(int)$r['activity'],$tot,$rate]);
        }
        fputcsv($out,[]);
        fputcsv($out,['รวมทั้งโรงเรียน','','',$sum['present'],$sum['late'],$sum['absent'],
            $sum['leave'],$sum['activity'],$sum['total'],
            $sum['total']>0?round(($sum['present']+$sum['late'])/$sum['total']*100).'%':'-']);
        fclose($out);
        exit;
    }

    /** ตั้งค่าเวลาเข้าเรียน (สาย/ขาด) เดียวทั้งโรงเรียน */
public function flagSettingsSave(): void
    {
        $this->authorize('academic.attendance_report'); $this->guardAttendance(); $this->verifyCsrf();
        $late=(string)Request::input('late_time','08:00');
        $absent=(string)Request::input('absent_time','08:30');
        $set=new \App\Models\Setting();
        $set->set('attendance','late_time',$late,Auth::id());
        $set->set('attendance','absent_time',$absent,Auth::id());
        AuditLog::record(Auth::id(),'update','system_settings',null,null,['attendance_time'=>true]);
        $this->back('attendance/flag','success','บันทึกเวลาเข้าเรียนแล้ว (สาย '.$late.' · ขาด '.$absent.')');
    }

    public function attendanceEntry(string $taId): void
    {
        $this->authorize('academic.attendance');
        $ta=$this->a->taDetail((int)$taId);
        if(!$ta) $this->back('attendance','error','ไม่พบวิชา');
        $date=(string)Request::input('date',date('Y-m-d'));
        $period=max(1,min(12,(int)Request::input('period',1)));
        $hours=(int)Request::input('hours',1)===2?2:1;
        $this->view('attendance/entry',[
            'title'=>'เช็คชื่อ: '.$ta['subject_name'].' '.$ta['classroom'],
            'ta'=>$ta,'date'=>$date,'period'=>$period,'hours'=>$hours,
            'students'=>$this->a->taStudents((int)$ta['classroom_id']),
            'marks'=>$this->a->attendanceMap((int)$taId,$date,$period),'summary'=>$this->a->attendanceSummary((int)$taId),
        ]);
    }
    public function attendanceSave(string $taId): void
    {
        $this->authorize('academic.attendance');
        $this->verifyCsrf();
        $ta=$this->a->taDetail((int)$taId);
        if(!$ta) $this->back('attendance','error','ไม่พบวิชา');
        $date=(string)Request::input('date',date('Y-m-d'));
        $period=max(1,min(12,(int)Request::input('period',1)));
        $hours=(int)Request::input('hours',1)===2?2:1;
        $marks=(array)Request::input('mark',[]);
        $notes=(array)Request::input('note',[]);
        $by=Auth::id();
        $periods=$hours===2?[$period,$period+1]:[$period];
        foreach($marks as $sid=>$status){
            $status=in_array($status,['present','absent','late','leave','activity'],true)?$status:'present';
            $note=trim((string)($notes[$sid]??''));
            foreach($periods as $pp){
                $this->a->saveAttendance((int)$taId,(int)$ta['classroom_id'],(int)$sid,$date,(int)$pp,$status,$note!==''?$note:null,$by);
            }
        }
        AuditLog::record($by,'update','attendances',(int)$taId);
        $this->back("attendance/$taId?date=$date&period=$period&hours=$hours",'success','บันทึกการเช็คชื่อ'.($hours===2?' (2 คาบ)':'').'เรียบร้อยแล้ว');
    }

    // ---------- ตารางสอน ----------
    public function schedule(): void
    {
        $this->authorize('academic.schedule');
        $classroomId=(int)Request::input('classroom',0);
        $semId=$this->a->currentSemester()['id']??0;
        $rows=$classroomId?$this->a->schedule($classroomId,$semId):[];
        $this->view('schedule/index',[
            'title'=>'ตารางเรียน / ตารางสอน','classrooms'=>$this->a->classroomsList(),
            'classroomId'=>$classroomId,'rows'=>$rows,
        ]);
    }

    // ---------- แดชบอร์ดติดตามการกรอกคะแนน ----------
    public function gradeMonitor(): void
    {
        $this->authorize('academic.monitor');
        $semId=$this->a->currentSemester()['id']??null;
        $rows=$this->a->entryStatusAll($semId);
        $filter=(string)Request::input('stat','');
        if($filter!==''){ $rows=array_values(array_filter($rows,fn($r)=>$r['stat']===$filter)); }
        $this->view('grades/monitor',[
            'title'=>'ติดตามการกรอกคะแนน',
            'rows'=>$rows,'summary'=>$this->a->monitorSummary($this->a->entryStatusAll($semId)),
            'ratio'=>$this->a->getRatio(),'filter'=>$filter,
        ]);
    }
    public function ratioUpdate(): void
    {
        $this->authorize('academic.monitor');
        $this->verifyCsrf();
        $during=max(0,min(100,(int)Request::input('during',70)));
        $final=100-$during;
        $this->a->setRatio($during,$final);
        AuditLog::record(Auth::id(),'update','system_settings',null,null,['ratio'=>"$during:$final"]);
        $this->back('grade-monitor','success',"ตั้งสัดส่วนคะแนนเป็น $during:$final เรียบร้อยแล้ว");
    }

    // สร้างชุดองค์ประกอบคะแนนเริ่มต้นตามสัดส่วน (ระหว่างภาค/ปลายภาค)
    public function initComponents(string $taId): void
    {
        $this->authorize('academic.grades');
        $this->verifyCsrf();
        if(!$this->a->taDetail((int)$taId)) $this->back('scores','error','ไม่พบวิชา');
        if($this->a->components((int)$taId)) $this->back("scores/$taId",'error','มีองค์ประกอบคะแนนอยู่แล้ว');
        $ratio=$this->a->getRatio();
        // ระหว่างภาค: เก็บคะแนน (เต็ม 40) + กลางภาค (เต็ม 30) ; ปลายภาค (เต็ม 30) — คิดเป็นร้อยละตามสัดส่วน
        $this->a->componentCreate((int)$taId,['name'=>'คะแนนเก็บระหว่างเรียน','component_type'=>'formative','max_score'=>40,'weight'=>null,'sort_order'=>1]);
        $this->a->componentCreate((int)$taId,['name'=>'สอบกลางภาค','component_type'=>'midterm','max_score'=>30,'weight'=>null,'sort_order'=>2]);
        $this->a->componentCreate((int)$taId,['name'=>'สอบปลายภาค','component_type'=>'final','max_score'=>30,'weight'=>null,'sort_order'=>3]);
        $this->back("scores/$taId/structure",'success','สร้างชุดองค์ประกอบเริ่มต้น (ระหว่างภาค '.$ratio['during'].' : ปลายภาค '.$ratio['final'].') แล้ว');
    }
}
