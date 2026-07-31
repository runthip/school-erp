<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\SarKindergarten;
use App\Models\AuditLog;

/**
 * SAR มาตรฐานการศึกษา ระดับปฐมวัย (11 มาตรฐาน / 100 คะแนน) — งานบริหาร
 * รายปีการศึกษา · กรอกคะแนนรายตัวบ่งชี้ → ระบบคำนวณระดับคุณภาพ/แปลความหมาย/สรุปรวมให้
 */
class SarKindergartenController extends Controller
{
    private SarKindergarten $m;
    public function __construct(){ $this->m = new SarKindergarten(); }

    private function guard(): void
    {
        $this->authorize('qa.kindergarten');
        if(!stage_enabled('kindergarten'))
            $this->back('dashboard','error','ยังไม่ได้เปิดใช้งานช่วงชั้นอนุบาล — เปิดได้ที่ ตั้งค่าระบบ → ช่วงชั้นที่เปิดสอน');
    }

    public function index(): void
    {
        $this->guard();
        $this->view('sar_kg/index', [
            'title'=>'SAR มาตรฐานการศึกษา ระดับปฐมวัย',
            'years'=>$this->m->years(),
        ]);
    }

    public function start(): void
    {
        $this->guard(); $this->verifyCsrf();
        $yearId=(int)Request::input('academic_year_id',0);
        if(!$yearId) $this->back('sar-kg','error','กรุณาเลือกปีการศึกษา');
        $id=$this->m->create($yearId, Auth::id());
        AuditLog::record(Auth::id(),'create','sar_kg',$id);
        $this->redirect('sar-kg/'.$id.'/edit');
    }

    public function edit(string $id): void
    {
        $this->guard();
        $sar=$this->m->find((int)$id);
        if(!$sar) $this->back('sar-kg','error','ไม่พบรายงาน');
        $rows=$this->m->scores((int)$id);
        // จัดเป็น [std][ind] = row
        $by=[]; foreach($rows as $r) $by[(int)$r['std_no']][(int)$r['ind_no']]=$r;
        $this->view('sar_kg/edit', [
            'title'=>'กรอก SAR ปฐมวัย · ปีการศึกษา '.$sar['year_be'],
            'sar'=>$sar, 'by'=>$by,
            'standards'=>SarKindergarten::STANDARDS,
            'kgStudents'=>$this->m->kgStudentCount(),
            'kgTeachers'=>$this->m->kgTeacherCount(),
        ]);
    }

    public function save(string $id): void
    {
        $this->guard(); $this->verifyCsrf();
        $sar=$this->m->find((int)$id);
        if(!$sar) $this->back('sar-kg','error','ไม่พบรายงาน');
        $this->m->save((int)$id, (array)Request::input('s',[]),
            (string)Request::input('summary_note',''), Auth::id());
        AuditLog::record(Auth::id(),'save','sar_kg',(int)$id);
        $this->back('sar-kg/'.$id.'/edit','success','บันทึกผลการประเมินแล้ว');
    }

    public function print(string $id): void
    {
        $this->guard();
        $sar=$this->m->find((int)$id);
        if(!$sar) $this->back('sar-kg','error','ไม่พบรายงาน');
        $result=SarKindergarten::computeAll($this->m->scores((int)$id));
        $this->view('sar_kg/print', [
            'title'=>'SAR ระดับปฐมวัย · ปีการศึกษา '.$sar['year_be'],
            'sar'=>$sar, 'result'=>$result,
            'standards'=>SarKindergarten::STANDARDS,
            'backUrl'=>'sar-kg/'.$id.'/edit', 'autoPrint'=>true,
        ], 'print');
    }
}
