<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\StudentHeadcount;

/**
 * สรุปยอดนักเรียน — การ์ดยอดรวม + ตารางตามชั้น/ห้อง (ชาย/หญิง) + พิมพ์/PDF + Excel/CSV + บัญชีรายชื่อ
 * เข้าถึงได้: admin + ผู้บริหารทุกคน + ครู (สิทธิ์ student.headcount)
 */
class StudentHeadcountController extends Controller
{
    private StudentHeadcount $m;
    public function __construct(){ $this->m = new StudentHeadcount(); }

    private function guard(): void { $this->authorize('student.headcount'); }

    private function filter(): array
    {
        return [
            'stage'     => (string)Request::input('stage',''),
            'level'     => (int)Request::input('level',0),
            'classroom' => (int)Request::input('classroom',0),
        ];
    }

    public function index(): void
    {
        $this->guard();
        $f=$this->filter();
        $this->view('headcount/index',[
            'title'=>'สรุปยอดนักเรียน',
            'rows'=>$this->m->byClassroom($f),
            'opts'=>$this->m->filterOptions(),
            'f'=>$f,
        ]);
    }

    public function print(): void
    {
        $this->guard();
        $f=$this->filter();
        $this->view('headcount/print',[
            'title'=>'สรุปยอดนักเรียน',
            'rows'=>$this->m->byClassroom($f),
            'backUrl'=>'headcount','autoPrint'=>true,
        ],'print');
    }

    public function roster(): void
    {
        $this->guard();
        $f=$this->filter();
        $type=Request::input('type','signature')==='checklist'?'checklist':'signature';
        $cols=(int)Request::input('cols',12); $cols=max(5,min(20,$cols?:12));
        $this->view('headcount/roster',[
            'title'=>'บัญชีรายชื่อนักเรียน',
            'students'=>$this->m->roster($f),
            'type'=>$type,'cols'=>$cols,
            'backUrl'=>'headcount','autoPrint'=>true,
        ],'print');
    }

    /** ส่งออกยอดสรุปเป็น CSV (เปิดใน Excel ได้ ภาษาไทยไม่เพี้ยน ด้วย BOM) */
    public function exportCsv(): void
    {
        $this->guard();
        $rows=$this->m->byClassroom($this->filter());
        $out=[['ระดับชั้น','ห้อง','ชาย','หญิง','รวม']];
        $mSum=$fSum=$tSum=0;
        foreach($rows as $r){
            $out[]=[$r['level_name']?:'-', $r['classroom'], (int)$r['male'], (int)$r['female'], (int)$r['total']];
            $mSum+=(int)$r['male']; $fSum+=(int)$r['female']; $tSum+=(int)$r['total'];
        }
        $out[]=['รวมทั้งหมด','',$mSum,$fSum,$tSum];
        $this->csv('student_headcount_'.date('Ymd').'.csv', $out);
    }

    /** ส่งออกบัญชีรายชื่อเป็น CSV */
    public function rosterCsv(): void
    {
        $this->guard();
        $students=$this->m->roster($this->filter());
        $out=[['ลำดับ','ห้อง','เลขที่','รหัสนักเรียน','คำนำหน้า','ชื่อ-สกุล','ลงชื่อ/หมายเหตุ']];
        $i=0;
        foreach($students as $s){
            $i++;
            $out[]=[$i, $s['classroom'], $s['roll_number']?:'', $s['student_code'],
                    $s['prefix'], trim($s['first_name'].' '.$s['last_name']), ''];
        }
        $this->csv('student_roster_'.date('Ymd').'.csv', $out);
    }

    private function csv(string $filename, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM ให้ Excel อ่านภาษาไทยถูก
        $fp=fopen('php://output','w');
        foreach($rows as $r) fputcsv($fp, $r);
        fclose($fp);
        exit;
    }
}
