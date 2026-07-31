<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Exam;
use App\Models\AuditLog;

class ExamController extends Controller
{
    private Exam $m;
    public function __construct(){ $this->m = new Exam(); }

    public function index(): void
    {
        $this->authorize('academic.measurement');
        $f=[
            'year'=>Request::input('year',''),'type'=>Request::input('type',''),
            'group'=>Request::input('group',''),'teacher'=>Request::input('teacher',''),
            'subject'=>Request::input('subject',''),
        ];
        $this->view('exams/index',[
            'title'=>'งานวัดผล / คลังข้อสอบ',
            'rows'=>$this->m->list(array_filter($f)),'f'=>$f,
            'years'=>$this->m->years(),'groups'=>$this->m->subjectGroups(),
            'teachers'=>$this->m->teachers(),'subjects'=>$this->m->subjects(),
            'types'=>Exam::types(),
        ]);
    }

    public function create(): void
    {
        $this->authorize('academic.measurement');
        $this->view('exams/form',[
            'title'=>'สร้างข้อสอบใหม่','exam'=>null,
            'subjects'=>$this->m->subjects(),'teachers'=>$this->m->teachers(),
            'semesters'=>$this->m->semesters(),'types'=>Exam::types(),
            'currentSem'=>$this->m->currentSemesterId(),
        ]);
    }

    public function store(): void
    {
        $this->authorize('academic.measurement');
        $this->verifyCsrf();
        $d=$this->collect();
        if($d['title']==='' || !$d['subject_id']) $this->back('exams/create','error','กรุณากรอกชื่อข้อสอบและเลือกวิชา');
        $d['created_by']=Auth::id();
        $id=$this->m->create($d);
        // อัปโหลดไฟล์ (ถ้ามี)
        $this->handleUpload($id);
        AuditLog::record(Auth::id(),'create','exams',$id);
        $this->redirect('exams/'.$id);
    }

    public function show(string $id): void
    {
        $this->authorize('academic.measurement');
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $this->view('exams/show',['title'=>$exam['title'],'exam'=>$exam,'types'=>Exam::types()]);
    }

    public function edit(string $id): void
    {
        $this->authorize('academic.measurement');
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $this->view('exams/form',[
            'title'=>'แก้ไขข้อสอบ','exam'=>$exam,
            'subjects'=>$this->m->subjects(),'teachers'=>$this->m->teachers(),
            'semesters'=>$this->m->semesters(),'types'=>Exam::types(),
            'currentSem'=>(int)$exam['semester_id'],
        ]);
    }
    public function update(string $id): void
    {
        $this->authorize('academic.measurement');
        $this->verifyCsrf();
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $this->m->update((int)$id,$this->collect());
        $this->handleUpload((int)$id);
        AuditLog::record(Auth::id(),'update','exams',(int)$id);
        $this->redirect('exams/'.$id);
    }
    public function destroy(string $id): void
    {
        $this->authorize('academic.measurement');
        $this->verifyCsrf();
        $this->m->delete((int)$id);
        AuditLog::record(Auth::id(),'delete','exams',(int)$id);
        $this->back('exams','success','ลบข้อสอบแล้ว');
    }

    // ---------- พิมพ์กระดาษคำตอบ OMR/OCR ----------
    public function answerSheet(string $id): void
    {
        $this->authorize('academic.measurement');
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $this->view('exams/answer_sheet',[
            'title'=>'กระดาษคำตอบ - '.$exam['title'],'exam'=>$exam,
            'backUrl'=>'exams/'.$id,'autoPrint'=>true,
        ],'print');
    }
    // ---------- พิมพ์หน้าปก/หัวข้อสอบ ----------
    public function cover(string $id): void
    {
        $this->authorize('academic.measurement');
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $this->view('exams/cover',[
            'title'=>'ปกข้อสอบ - '.$exam['title'],'exam'=>$exam,'types'=>Exam::types(),
            'backUrl'=>'exams/'.$id,'autoPrint'=>true,
        ],'print');
    }

    // ---------- ดาวน์โหลดไฟล์แนบ ----------
    public function download(string $id): void
    {
        $this->authorize('academic.measurement');
        $exam=$this->m->find((int)$id);
        if(!$exam || !$exam['file_path']) $this->back('exams','error','ไม่พบไฟล์');
        $path=BASE_PATH.'/storage/'.$exam['file_path'];
        if(!is_file($path)) $this->back('exams/'.$id,'error','ไฟล์หาย');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.($exam['file_name']?:basename($path)).'"');
        header('Content-Length: '.filesize($path));
        readfile($path); exit;
    }

    // ---------- helpers ----------
    private function collect(): array
    {
        return [
            'semester_id'=>(int)Request::input('semester_id',$this->m->currentSemesterId()),
            'subject_id'=>(int)Request::input('subject_id',0),
            'teacher_id'=>(int)Request::input('teacher_id',0),
            'exam_type'=>in_array(Request::input('exam_type'),array_keys(Exam::types()),true)?Request::input('exam_type'):'midterm',
            'title'=>trim((string)Request::input('title','')),
            'exam_date'=>Request::input('exam_date',''),
            'duration_min'=>max(0,(int)Request::input('duration_min',60)),
            'total_questions'=>max(1,min(200,(int)Request::input('total_questions',40))),
            'choices'=>in_array((int)Request::input('choices',4),[4,5],true)?(int)Request::input('choices',4):4,
            'total_score'=>(float)Request::input('total_score',40),
            'instructions'=>trim((string)Request::input('instructions','')),
            'answer_key'=>trim((string)Request::input('answer_key','')),
        ];
    }
    // ================= เฉลย · ตรวจคำตอบผ่านระบบ · กระดาษคำตอบรายบุคคล =================

    /** ตัวเลือก ก ข ค ง จ ตามจำนวน choices */
    private function letters(int $ch): array
    { return array_slice(['ก','ข','ค','ง','จ'],0,$ch); }

    /** อ่านคำตอบจากฟอร์ม (a[q]=choiceIndex 1..M) → สตริงยาว N */
    private function readAnswers(int $n): string
    {
        $a=(array)Request::input('a',[]);
        $s='';
        for($q=1;$q<=$n;$q++){ $v=(string)($a[$q]??'0'); $s.=(ctype_digit($v)&&(int)$v>=1&&(int)$v<=5)?$v:'0'; }
        return $s;
    }

    public function answerKey(string $id): void
    {
        $this->authorize('academic.measurement');
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $this->view('exams/answer_key',[
            'title'=>'เฉลย - '.$exam['title'],'exam'=>$exam,'letters'=>$this->letters((int)$exam['choices']),
        ]);
    }
    public function answerKeySave(string $id): void
    {
        $this->authorize('academic.measurement'); $this->verifyCsrf();
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $this->m->setKey((int)$id, $this->readAnswers((int)$exam['total_questions']));
        AuditLog::record(Auth::id(),'update','exams',(int)$id,null,['answer_key'=>true]);
        $this->back('exams/'.$id.'/answer-key','success','บันทึกเฉลยแล้ว');
    }

    public function setClass(string $id): void
    {
        $this->authorize('academic.measurement'); $this->verifyCsrf();
        $this->m->setClassroom((int)$id, (int)Request::input('classroom_id',0) ?: null);
        $this->back('exams/'.$id.'/grade','success','กำหนดห้องสอบแล้ว');
    }

    /** แดชบอร์ดตรวจข้อสอบ: เลือกห้อง + คะแนนรายคน + สถิติ */
    public function grade(string $id): void
    {
        $this->authorize('academic.measurement');
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $cid=(int)($exam['classroom_id']??0);
        $students=$cid ? $this->m->students($cid) : [];
        $this->view('exams/grade',[
            'title'=>'ตรวจข้อสอบ - '.$exam['title'],'exam'=>$exam,
            'classrooms'=>$this->m->classrooms(),'students'=>$students,
            'results'=>$cid ? $this->m->resultsMap((int)$id) : [],
            'stats'=>$this->m->stats((int)$id,$exam),
            'keyFilled'=>Exam::keyFilled((string)($exam['answer_key']??''),(int)$exam['total_questions']),
        ]);
    }

    /** หน้ากรอกคำตอบของนักเรียนรายคน */
    public function gradeStudent(string $id, string $sid): void
    {
        $this->authorize('academic.measurement');
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $cid=(int)($exam['classroom_id']??0);
        $student=null;
        foreach($this->m->students($cid) as $s){ if((int)$s['id']===(int)$sid){ $student=$s; break; } }
        if(!$student) $this->back('exams/'.$id.'/grade','error','ไม่พบนักเรียนในห้องสอบ');
        $this->view('exams/grade_student',[
            'title'=>'กรอกคำตอบ - '.$student['name'],'exam'=>$exam,'student'=>$student,
            'letters'=>$this->letters((int)$exam['choices']),
            'result'=>$this->m->resultOf((int)$id,(int)$sid),
        ]);
    }
    public function gradeSave(string $id): void
    {
        $this->authorize('academic.measurement'); $this->verifyCsrf();
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $sid=(int)Request::input('student_id',0);
        if($sid<=0) $this->back('exams/'.$id.'/grade','error','ไม่พบนักเรียน');
        $this->m->saveResult((int)$id,$sid,$this->readAnswers((int)$exam['total_questions']),$exam,Auth::id());
        AuditLog::record(Auth::id(),'grade','exam_results',$sid);
        $this->back('exams/'.$id.'/grade','success','บันทึกและตรวจคำตอบแล้ว');
    }

    /** กระดาษคำตอบรายบุคคล (พิมพ์ทีละคนทั้งห้อง) */
    public function sheets(string $id): void
    {
        $this->authorize('academic.measurement');
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $cid=(int)($exam['classroom_id']??0);
        if(!$cid) $this->back('exams/'.$id.'/grade','error','กรุณากำหนดห้องสอบก่อนพิมพ์กระดาษคำตอบรายบุคคล');
        $this->view('exams/sheets',[
            'title'=>'กระดาษคำตอบรายบุคคล - '.$exam['title'],'exam'=>$exam,
            'students'=>$this->m->students($cid),'backUrl'=>'exams/'.$id.'/grade','autoPrint'=>true,
        ],'print');
    }

    /** หน้าตรวจข้อสอบด้วยกล้องมือถือ/แท็บเล็ต (OMR) */
    public function scan(string $id): void
    {
        $this->authorize('academic.measurement');
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $cid=(int)($exam['classroom_id']??0);
        if(!$cid) $this->back('exams/'.$id.'/grade','error','กรุณากำหนดห้องสอบก่อนตรวจด้วยกล้อง');
        if(!Exam::keyFilled((string)($exam['answer_key']??''),(int)$exam['total_questions']))
            $this->back('exams/'.$id.'/answer-key','error','กรุณาตั้งเฉลยให้ครบก่อนตรวจด้วยกล้อง');
        $this->view('exams/scan',[
            'title'=>'ตรวจด้วยกล้อง - '.$exam['title'],'exam'=>$exam,
            'students'=>$this->m->students($cid),
            'results'=>$this->m->resultsMap((int)$id),
            'letters'=>$this->letters((int)$exam['choices']),
        ]);
    }

    /** พิมพ์กระดาษคำตอบสำหรับตรวจด้วยกล้อง (เรขาคณิตตรงกับ omr.js) */
    public function scanSheet(string $id): void
    {
        $this->authorize('academic.measurement');
        $exam=$this->m->find((int)$id);
        if(!$exam) $this->back('exams','error','ไม่พบข้อสอบ');
        $cid=(int)($exam['classroom_id']??0);
        $this->view('exams/scan_sheet',[
            'title'=>'กระดาษคำตอบ (กล้อง) - '.$exam['title'],'exam'=>$exam,
            'students'=>$cid?$this->m->students($cid):[],
            'backUrl'=>'exams/'.$id.'/scan','autoPrint'=>true,
        ],'print');
    }

    private function handleUpload(int $id): void
    {
        if(empty($_FILES['file']) || ($_FILES['file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return;
        $orig=$_FILES['file']['name'];
        $ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));
        $allow=['pdf','doc','docx','jpg','jpeg','png'];
        if(!in_array($ext,$allow,true)) return;
        $dir=BASE_PATH.'/storage/exam_files';
        if(!is_dir($dir)) @mkdir($dir,0775,true);
        $safe='exam_'.$id.'_'.time().'.'.$ext;
        if(move_uploaded_file($_FILES['file']['tmp_name'],$dir.'/'.$safe)){
            $this->m->setFile($id,'exam_files/'.$safe,$orig);
        }
    }
}
