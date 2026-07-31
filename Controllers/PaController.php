<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\PaTopic;
use App\Models\AuditLog;

/**
 * PA ของฉัน — บุคลากรตั้งหัวข้อ PA (ข้อตกลงในการพัฒนางาน) + แนบไฟล์ PDF
 * ทุกอย่าง scope เฉพาะ personnel ของผู้ใช้ปัจจุบัน (แก้/ลบของคนอื่นไม่ได้)
 * ผู้ประเมิน (hr.evaluation) ดาวน์โหลดไฟล์ของทุกคนได้ (ผ่าน fileDownload)
 */
class PaController extends Controller
{
    private const ALLOWED = ['pdf'];
    private const MAX_BYTES = 20971520; // 20 MB
    private PaTopic $m;
    public function __construct(){ $this->m = new PaTopic(); }

    /** personnel_id ของผู้ใช้ปัจจุบัน — ถ้าไม่ผูกบุคลากร ให้แจ้งเตือน */
    private function myPid(): int
    { return $this->m->personnelIdOfUser(Auth::user()); }

    /** โหลดหัวข้อและตรวจว่าเป็นของผู้ใช้ปัจจุบัน (กันแก้ของคนอื่น) */
    private function ownTopic(int $id): array
    {
        $t=$this->m->find($id);
        if(!$t || (int)$t['personnel_id']!==$this->myPid())
            $this->back('pa','error','ไม่พบหัวข้อ หรือไม่ใช่ของท่าน');
        return $t;
    }

    public function index(): void
    {
        $this->authorize('pa.own');
        $pid=$this->myPid();
        if(!$pid){
            $this->view('pa/index',['title'=>'PA ของฉัน','pid'=>0,
                'topics'=>[],'files'=>[],'categories'=>PaTopic::CATEGORIES,
                'currentYear'=>(int)date('Y')+543,'personName'=>'']);
            return;
        }
        $year=(string)Request::input('year','');
        $topics=$this->m->byPersonnel($pid, array_filter(['year'=>$year]));
        $files=$this->m->filesForTopics(array_map(fn($t)=>(int)$t['id'],$topics));
        $this->view('pa/index',[
            'title'=>'PA ของฉัน','pid'=>$pid,
            'topics'=>$topics,'files'=>$files,'categories'=>PaTopic::CATEGORIES,
            'currentYear'=>(int)date('Y')+543,'personName'=>$this->m->personName($pid),
            'year'=>$year,'lim'=>upload_limits(self::MAX_BYTES),
        ]);
    }

    public function store(): void
    {
        $this->authorize('pa.own'); $this->verifyCsrf();
        $pid=$this->myPid();
        if(!$pid) $this->back('pa','error','บัญชีของท่านยังไม่ได้ผูกกับข้อมูลบุคลากร');
        $d=$this->collect();
        if($d['title']==='') $this->back('pa','error','กรุณากรอกชื่อหัวข้อ PA');
        $id=$this->m->create($pid,$d,Auth::id());
        $r=$this->uploadFiles($id);
        AuditLog::record(Auth::id(),'create','pa_topics',$id);
        $msg='บันทึกหัวข้อ PA แล้ว'.($r['ok']?' · แนบไฟล์ '.$r['ok'].' ไฟล์':'').($r['fail']?' (ข้าม '.count($r['fail']).')':'');
        $this->back('pa',$r['fail']&&!$r['ok']?'error':'success',$msg.($r['fail']?' — '.implode(', ',$r['fail']):''));
    }

    public function update(string $id): void
    {
        $this->authorize('pa.own'); $this->verifyCsrf();
        $this->ownTopic((int)$id);
        $this->m->update((int)$id,$this->collect());
        AuditLog::record(Auth::id(),'update','pa_topics',(int)$id);
        $this->back('pa','success','แก้ไขหัวข้อ PA แล้ว');
    }

    public function delete(string $id): void
    {
        $this->authorize('pa.own'); $this->verifyCsrf();
        $this->ownTopic((int)$id);
        foreach($this->m->files((int)$id) as $f) $this->removeFile($f['file_path']);
        $this->m->delete((int)$id);
        AuditLog::record(Auth::id(),'delete','pa_topics',(int)$id);
        $this->back('pa','success','ลบหัวข้อ PA แล้ว');
    }

    public function upload(string $id): void
    {
        $this->authorize('pa.own'); $this->verifyCsrf();
        $this->ownTopic((int)$id);
        $r=$this->uploadFiles((int)$id);
        if(!$r['ok'] && !$r['fail']) $this->back('pa','error','ไม่ได้เลือกไฟล์');
        $msg='แนบไฟล์แล้ว '.$r['ok'].' ไฟล์'.($r['fail']?' · ข้าม: '.implode(', ',$r['fail']):'');
        $this->back('pa',$r['ok']?'success':'error',$msg);
    }

    /** ดาวน์โหลด: เจ้าของ หรือผู้มีสิทธิ์ประเมิน (hr.evaluation) */
    public function fileDownload(string $fid): void
    {
        if(!Auth::can('pa.own') && !Auth::can('hr.evaluation')) $this->forbidden();
        $f=$this->m->fileFind((int)$fid);
        if(!$f) $this->back('pa','error','ไม่พบไฟล์');
        $owner=$this->m->fileOwner((int)$fid);
        if($owner!==$this->myPid() && !Auth::can('hr.evaluation'))
            $this->back('pa','error','ไม่มีสิทธิ์ดาวน์โหลดไฟล์นี้');
        $real=realpath(BASE_PATH.'/storage/'.$f['file_path']);
        $base=realpath(BASE_PATH.'/storage/pa');
        if(!$real || !$base || !str_starts_with($real,$base) || !is_file($real))
            $this->back('pa','error','ไม่พบไฟล์บนเซิร์ฟเวอร์');
        $name=$f['original_name'];
        $ascii=str_replace('"','',preg_replace('/[^\x20-\x7E]/','_',$name));
        header('Content-Type: '.($f['mime_type'] ?: 'application/pdf'));
        header('Content-Length: '.filesize($real));
        header('Content-Disposition: inline; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        readfile($real); exit;
    }

    public function fileDelete(string $fid): void
    {
        $this->authorize('pa.own'); $this->verifyCsrf();
        $f=$this->m->fileFind((int)$fid);
        if(!$f) $this->back('pa','error','ไม่พบไฟล์');
        if($this->m->fileOwner((int)$fid)!==$this->myPid())
            $this->back('pa','error','ลบไฟล์ของผู้อื่นไม่ได้');
        $this->removeFile($f['file_path']);
        $this->m->fileDelete((int)$fid);
        $this->back('pa','success','ลบไฟล์แล้ว');
    }

    // ---------- helpers ----------
    private function collect(): array
    {
        return [
            'year_be'=>(int)Request::input('year_be',(int)date('Y')+543),
            'round'=>(int)Request::input('round',1),
            'category'=>(string)Request::input('category','learning'),
            'title'=>trim((string)Request::input('title','')),
            'description'=>trim((string)Request::input('description','')),
        ];
    }

    private function forbidden(): void
    { http_response_code(403); echo 'Forbidden'; exit; }

    /** @return array{ok:int,fail:array<string>} */
    private function uploadFiles(int $topicId): array
    {
        $ok=0; $fail=[];
        if(empty($_FILES['files']) || !is_array($_FILES['files']['name'])) return ['ok'=>0,'fail'=>[]];
        $lim=upload_limits(self::MAX_BYTES); $cap=$lim['per_file'];
        $dir=BASE_PATH.'/storage/pa';
        if(!is_dir($dir)) @mkdir($dir,0775,true);
        $n=count($_FILES['files']['name']);
        for($i=0;$i<$n;$i++){
            $err=(int)$_FILES['files']['error'][$i];
            if($err===UPLOAD_ERR_NO_FILE) continue;
            $orig=(string)$_FILES['files']['name'][$i];
            $size=(int)$_FILES['files']['size'][$i];
            if($err!==UPLOAD_ERR_OK){ $fail[]=$orig.' (อัปโหลดไม่สำเร็จ)'; continue; }
            if($cap>0 && $size>$cap){ $fail[]=$orig.' (ใหญ่เกิน '.human_bytes($cap).')'; continue; }
            $ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));
            if(!in_array($ext,self::ALLOWED,true)){ $fail[]=$orig.' (รับเฉพาะ PDF)'; continue; }
            $safe='pa'.$topicId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
            if(move_uploaded_file($_FILES['files']['tmp_name'][$i], $dir.'/'.$safe)){
                $this->m->fileAdd($topicId,'pa/'.$safe,$orig,
                    (string)($_FILES['files']['type'][$i] ?? 'application/pdf'),$size,Auth::id());
                $ok++;
            } else { $fail[]=$orig.' (บันทึกไฟล์ไม่สำเร็จ)'; }
        }
        return ['ok'=>$ok,'fail'=>$fail];
    }

    private function removeFile(string $rel): void
    {
        $real=realpath(BASE_PATH.'/storage/'.$rel);
        $base=realpath(BASE_PATH.'/storage/pa');
        if($real && $base && str_starts_with($real,$base) && is_file($real)) @unlink($real);
    }
}
