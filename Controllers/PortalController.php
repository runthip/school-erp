<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Portal;
use App\Models\Academic;

class PortalController extends Controller
{
    private Portal $m;
    public function __construct(){ $this->m = new Portal(); }

    /**
     * รหัสนักเรียนของผู้ใช้ที่ล็อกอิน — มาจาก users.linked_id เท่านั้น
     * ไม่มีทางรับค่าจาก URL/ฟอร์ม จึงดูข้อมูลนักเรียนคนอื่นไม่ได้
     */
    private function sid(): int
    {
        $u=Auth::user();
        if(!$u || ($u['linked_type'] ?? '')!=='student' || empty($u['linked_id'])){
            $this->view('portal/unlinked', ['title'=>'พอร์ทัลนักเรียน'], 'app');
            exit;
        }
        return (int)$u['linked_id'];
    }

    public function dashboard(): void
    {
        $this->authorize('portal.student');
        $sid=$this->sid();
        $p=$this->m->profile($sid);
        if(!$p) $this->view('portal/unlinked', ['title'=>'พอร์ทัลนักเรียน'], 'app');
        $this->view('portal/dashboard', ['title'=>'พอร์ทัลของฉัน',
            'p'=>$p,'dash'=>$this->m->dashboard($sid),'guardians'=>$this->m->guardians($sid)]);
    }

    // ---------- ผลการเรียนของฉัน ----------
    public function grades(): void
    {
        $this->authorize('portal.student');
        $sid=$this->sid();
        $sem=(int)Request::input('semester',0);
        $rows=$this->m->grades($sid, $sem?:null);
        $this->view('portal/grades', ['title'=>'ผลการเรียนของฉัน',
            'p'=>$this->m->profile($sid),'rows'=>$rows,
            'gpa'=>Academic::computeGpa($rows),
            'gpaAll'=>Academic::computeGpa($this->m->grades($sid)),
            'bySem'=>$this->m->gpaBySemester($sid),
            'semesters'=>$this->m->semesters($sid),'sem'=>$sem,
            'scores'=>$sem?$this->m->scoreDetail($sid,$sem):[]]);
    }
    public function gradesPrint(): void
    {
        $this->authorize('portal.student');
        $sid=$this->sid();
        $sem=(int)Request::input('semester',0);
        $rows=$this->m->grades($sid, $sem?:null);
        $this->view('portal/grades_print', ['title'=>'ใบรายงานผลการเรียน',
            'p'=>$this->m->profile($sid),'rows'=>$rows,
            'gpa'=>Academic::computeGpa($rows),
            'gpaAll'=>Academic::computeGpa($this->m->grades($sid)),
            'bySem'=>$this->m->gpaBySemester($sid),
            'sem'=>$sem,'backUrl'=>'my/grades','autoPrint'=>true],'print');
    }

    // ---------- ห้องเรียนของฉัน ----------
    public function classroom(): void
    {
        $this->authorize('portal.student');
        $sid=$this->sid();
        $this->view('portal/classroom', ['title'=>'ห้องเรียนของฉัน',
            'p'=>$this->m->profile($sid),
            'timetable'=>$this->m->timetable($sid),
            'subjects'=>$this->m->subjects($sid),
            'classmates'=>$this->m->classmateCount($sid)]);
    }
    public function timetablePrint(): void
    {
        $this->authorize('portal.student');
        $sid=$this->sid();
        $this->view('portal/timetable_print', ['title'=>'ตารางเรียน',
            'p'=>$this->m->profile($sid),'timetable'=>$this->m->timetable($sid),
            'backUrl'=>'my/classroom','autoPrint'=>true],'print');
    }

    // ---------- การมาเรียนของฉัน ----------
    public function attendance(): void
    {
        $this->authorize('portal.student');
        $sid=$this->sid();
        $from=(string)Request::input('from','');
        $to=(string)Request::input('to','');
        $this->view('portal/attendance', ['title'=>'การมาเรียนของฉัน',
            'p'=>$this->m->profile($sid),
            'sum'=>$this->m->attendanceSummary($sid,$from?:null,$to?:null),
            'rows'=>$this->m->attendanceRecent($sid,60),
            'f'=>['from'=>$from,'to'=>$to]]);
    }

    // ---------- พฤติกรรมของฉัน ----------
    public function behavior(): void
    {
        $this->authorize('portal.student');
        $sid=$this->sid();
        $this->view('portal/behavior', ['title'=>'ความประพฤติของฉัน',
            'p'=>$this->m->profile($sid),
            'score'=>$this->m->behaviorScore($sid),
            'rows'=>$this->m->behaviors($sid),
            'scholarships'=>$this->m->scholarships($sid)]);
    }

    // ==================================================================
    //  พอร์ทัลผู้ปกครอง — ติดตามบุตรหลาน (เน้นการติดตามข้อมูล)
    // ==================================================================
    /** รหัสผู้ปกครองของผู้ใช้ที่ล็อกอิน — มาจาก users.linked_id เท่านั้น */
    private function guardianId(): ?int
    {
        $u=Auth::user();
        if($u && ($u['linked_type'] ?? '')==='guardian' && !empty($u['linked_id'])) return (int)$u['linked_id'];
        return null;
    }

    public function children(): void
    {
        $this->authorize('portal.guardian');
        $gid=$this->guardianId();
        $kids=$gid ? $this->m->children($gid) : [];
        $data=['title'=>'ติดตามบุตรหลาน','linked'=>$gid!==null,'kids'=>$kids,'sid'=>0,
               'p'=>null,'dash'=>null,'attRecent'=>[],'behaviors'=>[],'grades'=>[],'guardians'=>[]];
        if($kids){
            // ความปลอดภัย: child จาก URL ต้องเป็นบุตรหลานของผู้ปกครองรายนี้เท่านั้น
            $ids=array_map('intval', array_column($kids,'id'));
            $sel=(int)Request::input('child',0);
            $sid=in_array($sel,$ids,true) ? $sel : (int)$kids[0]['id'];
            $data['sid']=$sid;
            $data['p']=$this->m->profile($sid);
            $data['dash']=$this->m->dashboard($sid);
            $data['attRecent']=$this->m->attendanceRecent($sid,15);
            $data['behaviors']=array_slice($this->m->behaviors($sid),0,10);
            $data['grades']=$this->m->grades($sid);
            $data['guardians']=$this->m->guardians($sid);
        }
        $this->view('portal/children',$data);
    }
}
