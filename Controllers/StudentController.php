<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Student;

class StudentController extends Controller
{
    public function index(): void
    {
        $this->authorize('student.profile');
        $m=new Student();
        $q=trim((string)Request::input('q','')); $status=(string)Request::input('status','');
        $gender=(string)Request::input('gender',''); $grade=(int)Request::input('grade',0); $classroom=(int)Request::input('classroom',0);
        $page=max(1,(int)Request::input('page',1)); $limit=20; $offset=($page-1)*$limit;
        $total=$m->countAll($q,$status,$gender,$grade,$classroom);
        $this->view('students/index',[
            'title'=>'ข้อมูลนักเรียน','rows'=>$m->paginate($q,$status,$limit,$offset,$gender,$grade,$classroom),
            'total'=>$total,'counts'=>$m->statusCounts(),
            'page'=>$page,'pages'=>max(1,(int)ceil($total/$limit)),
            'q'=>$q,'status'=>$status,'gender'=>$gender,'grade'=>$grade,'classroom'=>$classroom,
            'grades'=>$m->gradesForFilter(),'classrooms'=>$m->classroomsForFilter(),
        ]);
    }
    // ====== นำเข้านักเรียนหลายคน (CSV รูปแบบ DMC) ======
    private const IMPORT_COLS = [
        'citizen_id'=>'เลขประจำตัวประชาชน','student_code'=>'รหัสนักเรียน','prefix'=>'คำนำหน้า',
        'first_name'=>'ชื่อ','last_name'=>'นามสกุล','gender'=>'เพศ','birth_date'=>'วันเกิด',
        'nationality'=>'สัญชาติ','religion'=>'ศาสนา','classroom'=>'ชั้น/ห้อง','roll_number'=>'เลขที่',
    ];

    public function importForm(): void
    {
        $this->authorize('student.profile');
        $this->view('students/import',['title'=>'นำเข้าข้อมูลนักเรียน (CSV)','cols'=>self::IMPORT_COLS,'report'=>null]);
    }

    /** ส่งออกนักเรียนหลายคนเป็น CSV (รูปแบบ DMC · ตามตัวกรองปัจจุบัน · เปิดใน Excel ได้) */
    public function exportCsv(): void
    {
        $this->authorize('student.profile');
        $m=new Student();
        $rows=$m->exportRows(
            trim((string)Request::input('q','')),(string)Request::input('status',''),
            (string)Request::input('gender',''),(int)Request::input('grade',0),(int)Request::input('classroom',0));
        $genN=['male'=>'ชาย','female'=>'หญิง','other'=>'อื่น ๆ'];
        $stN=['studying'=>'กำลังศึกษา','graduated'=>'จบการศึกษา','transferred'=>'ย้ายออก','dropped'=>'ออกกลางคัน','suspended'=>'พักการเรียน'];
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="students_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF";
        $fp=fopen('php://output','w');
        fputcsv($fp, array_merge(array_values(self::IMPORT_COLS), ['สถานะ']));
        foreach($rows as $r){
            fputcsv($fp, [
                $r['citizen_id']??'', $r['student_code'], $r['prefix']??'', $r['first_name'], $r['last_name'],
                $genN[$r['gender']??'']??'', $r['birth_date']??'', $r['nationality']??'', $r['religion']??'',
                $r['classroom']??'', $r['roll_number']??'', $stN[$r['status']]??$r['status'],
            ]);
        }
        fclose($fp); exit;
    }

    /** ดาวน์โหลดเทมเพลต CSV (มี BOM ให้ Excel อ่านภาษาไทยถูก) */
    public function importTemplate(): void
    {
        $this->authorize('student.profile');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="student_import_template.csv"');
        echo "\xEF\xBB\xBF";
        $fp=fopen('php://output','w');
        fputcsv($fp, array_values(self::IMPORT_COLS));
        fputcsv($fp, ['1100200300400','10101','เด็กชาย','สมชาย','ใจดี','ชาย','2557-05-01','ไทย','พุทธ','ม.1/1','1']);
        fputcsv($fp, ['1100200300401','10102','เด็กหญิง','สมหญิง','รักเรียน','หญิง','01/05/2557','ไทย','พุทธ','ม.1/1','2']);
        fclose($fp); exit;
    }

    private function parseGender(string $v): string
    {
        $v=trim($v);
        if(in_array($v,['ชาย','ช','male','ด.ช.','เด็กชาย','นาย'],true)) return 'male';
        if(in_array($v,['หญิง','ญ','female','ด.ญ.','เด็กหญิง','นางสาว','นาง'],true)) return 'female';
        return '';
    }
    private function parseDate(string $v): ?string
    {
        $v=trim($v); if($v==='') return null;
        if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$v,$m)){ $y=(int)$m[1]; if($y>2400)$y-=543; return sprintf('%04d-%s-%s',$y,$m[2],$m[3]); }
        if(preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#',$v,$m)){ $y=(int)$m[3]; if($y>2400)$y-=543; return sprintf('%04d-%02d-%02d',$y,(int)$m[2],(int)$m[1]); }
        return null;
    }

    public function importUpload(): void
    {
        $this->authorize('student.profile'); $this->verifyCsrf();
        if(($_FILES['file']['error'] ?? 1)!==UPLOAD_ERR_OK) $this->back('students/import','error','กรุณาเลือกไฟล์ CSV');
        $ext=strtolower(pathinfo($_FILES['file']['name'],PATHINFO_EXTENSION));
        if($ext!=='csv') $this->back('students/import','error','รองรับเฉพาะไฟล์ .csv — ถ้าเป็น Excel ให้บันทึกเป็น "CSV UTF-8" ก่อน');

        $fh=fopen($_FILES['file']['tmp_name'],'r');
        if(!$fh) $this->back('students/import','error','เปิดไฟล์ไม่ได้');
        // header row (ตัด BOM)
        $header=fgetcsv($fh);
        if($header){ $header[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$header[0]); }
        $labelToField=array_flip(self::IMPORT_COLS);
        $idx=[];
        foreach((array)$header as $i=>$h){ $h=trim((string)$h); if(isset($labelToField[$h])) $idx[$labelToField[$h]]=$i; }
        foreach(['student_code','first_name','last_name'] as $req){
            if(!isset($idx[$req])){ fclose($fh); $this->back('students/import','error','หัวตารางไม่ถูกต้อง — ต้องมีคอลัมน์ รหัสนักเรียน / ชื่อ / นามสกุล (ใช้เทมเพลตที่ดาวน์โหลด)'); }
        }
        $m=new Student(); $by=\App\Core\Auth::id();
        $get=fn($row,$f)=>isset($idx[$f])?trim((string)($row[$idx[$f]]??'')):'';
        $inserted=0; $skipped=0; $errors=[]; $line=1;
        while(($row=fgetcsv($fh))!==false){
            $line++;
            if(count(array_filter($row,fn($c)=>trim((string)$c)!==''))===0) continue; // แถวว่าง
            $code=$get($row,'student_code'); $fn=$get($row,'first_name'); $ln=$get($row,'last_name'); $cid=$get($row,'citizen_id');
            if($code===''||$fn===''||$ln===''){ $errors[]="แถว $line: ขาดรหัส/ชื่อ/นามสกุล"; continue; }
            if($cid!=='' && !preg_match('/^\d{13}$/',$cid)){ $errors[]="แถว $line: เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก"; continue; }
            if($m->codeExists($code)){ $skipped++; continue; }
            if($m->citizenExists($cid)){ $skipped++; continue; }
            $sid=$m->insertStudent([
                'student_code'=>$code,'citizen_id'=>$cid,'prefix'=>$get($row,'prefix'),
                'first_name'=>$fn,'last_name'=>$ln,'gender'=>$this->parseGender($get($row,'gender')),
                'birth_date'=>$this->parseDate($get($row,'birth_date')),
                'nationality'=>$get($row,'nationality'),'religion'=>$get($row,'religion'),
            ], $by);
            // ลงทะเบียนเข้าห้อง (ถ้าระบุชื่อห้องที่มีอยู่)
            $cls=$get($row,'classroom');
            if($cls!==''){ $c=$m->classroomByName($cls);
                if($c) $m->enroll($sid,(int)$c['id'],(int)$c['academic_year_id'], $get($row,'roll_number')!==''?(int)$get($row,'roll_number'):null);
                else $errors[]="แถว $line: เพิ่มนักเรียนแล้ว แต่ไม่พบห้อง \"$cls\" (ยังไม่ลงทะเบียนห้อง)";
            }
            $inserted++;
        }
        fclose($fh);
        \App\Models\AuditLog::record($by,'import','students',0,null,['inserted'=>$inserted,'skipped'=>$skipped]);
        $this->view('students/import',['title'=>'นำเข้าข้อมูลนักเรียน (CSV)','cols'=>self::IMPORT_COLS,
            'report'=>['inserted'=>$inserted,'skipped'=>$skipped,'errors'=>$errors]]);
    }

    public function show(string $id): void
    {
        $this->authorize('student.profile');
        $m=new Student(); $s=$m->detail((int)$id);
        if(!$s) $this->back('students','error','ไม่พบนักเรียน');
        $this->view('students/show',[
            'title'=>'ประวัตินักเรียน','s'=>$s,'guardians'=>$m->guardians((int)$id),
            'behavior'=>$m->behavior((int)$id),'health'=>$m->health((int)$id),
        ]);
    }
}
