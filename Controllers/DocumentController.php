<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Document;
use App\Models\AuditLog;
use App\Core\Schema;

class DocumentController extends Controller
{
    private Document $m;
    public function __construct(){ $this->m = new Document(); }

    /**
     * ถ้ายังไม่ได้ import SQL ที่จำเป็น ให้บอกผู้ใช้ว่าต้องนำเข้าไฟล์ไหน
     * แทนที่จะโยน 500 (Unknown column) ซึ่งหาสาเหตุไม่ได้
     */
    private function guardSchema(): void
    {
        $miss=Schema::missingFor('documents');
        if($miss){
            $this->view('documents/needs_migration',
                ['title'=>'ต้องนำเข้าฐานข้อมูลเพิ่ม','missing'=>$miss]);
            exit;
        }
    }

    /** นามสกุลไฟล์ที่อนุญาตให้แนบ */
    private const ALLOWED = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','gif','webp','txt','zip','rar'];
    private const MAX_BYTES = 20971520; // 20 MB ต่อไฟล์

    public function index(): void
    {
        $this->guardSchema(); $this->authorize('document.manage');
        $f=['q'=>trim((string)Request::input('q','')),'type'=>(string)Request::input('type',''),
            'status'=>(string)Request::input('status',''),'urgency'=>(string)Request::input('urgency',''),
            'director'=>(string)Request::input('director','')];
        $this->view('documents/index',['title'=>'สารบรรณอิเล็กทรอนิกส์',
            'rows'=>$this->m->list(array_filter($f)),'f'=>$f,'stats'=>$this->m->stats(),
            'nextNo'=>$this->m->nextReceiveNo(),'nextSend'=>$this->m->nextSendNo(),
            'lim'=>upload_limits(self::MAX_BYTES),'allowed'=>self::ALLOWED]);
    }

    public function show(string $id): void
    {
        $this->guardSchema(); $this->authorize('document.manage');
        $d=$this->m->find((int)$id);
        if(!$d) $this->back('documents','error','ไม่พบหนังสือ');
        // บันทึกประวัติการเปิดอ่าน + ทำเครื่องหมายว่าอ่านแล้วถ้าเป็นผู้รับ
        $u=Auth::user(); $myPid=null; $myDept=null;
        if($u && ($u['linked_type'] ?? '')==='personnel' && !empty($u['linked_id'])){
            $myPid=(int)$u['linked_id'];
            $myDept=$this->m->personnelDept($myPid);
            $this->m->recipientMarkRead((int)$id,$myPid);
        }
        $this->m->viewLog((int)$id, Auth::id(), $myDept);

        $this->view('documents/show',['title'=>'รายละเอียดหนังสือ','d'=>$d,
            'files'=>$this->m->attachments((int)$id),
            'assignNotes'=>$this->m->notes((int)$id,'assign'),
            'orderNotes'=>$this->m->notes((int)$id,'order'),
            'recipients'=>$this->m->recipients((int)$id),
            'recipStats'=>$this->m->recipientStats((int)$id),
            'progress'=>$this->m->progress((int)$id),
            'timeline'=>$this->m->timeline((int)$id),
            'views'=>$this->m->views((int)$id),'viewCount'=>$this->m->viewCount((int)$id),
            'people'=>$this->m->personnelByDept(),'depts'=>$this->m->deptsWithCount(),
            'clerk'=>$this->m->clerk(),'me'=>$this->m->userIdentity((int)Auth::id()),
            'actions'=>Document::ACTIONS,'myPid'=>$myPid,
            'lim'=>upload_limits(self::MAX_BYTES),'allowed'=>self::ALLOWED,
            'canSign'=>Auth::can('document.sign')]);
    }

    /** ผู้ใช้ปัจจุบันเข้าถึงหนังสือฉบับนี้ได้ไหม: เจ้าหน้าที่สารบรรณ หรือเป็นผู้รับที่ถูกส่งถึง */
    private function canAccess(int $docId): bool
    {
        if(Auth::can('document.manage')) return true;
        $u=Auth::user();
        if($u && ($u['linked_type'] ?? '')==='personnel' && !empty($u['linked_id']))
            return $this->m->isRecipient($docId,(int)$u['linked_id']);
        return false;
    }

    /**
     * หน้าติดตามหนังสือสำหรับผู้รับ (บุคคลที่ถูกส่งถึง) — อ่านอย่างเดียว + เห็นลำดับการดำเนินการ
     * เปิดได้แม้ไม่มีสิทธิ์ document.manage ขอเพียงเป็นผู้รับของหนังสือฉบับนั้น
     */
    public function track(string $id): void
    {
        $this->guardSchema(); $this->authorize('document.inbox');
        $d=$this->m->find((int)$id);
        if(!$d) $this->back('documents/inbox','error','ไม่พบหนังสือ');
        if(!$this->canAccess((int)$id))
            $this->back('documents/inbox','error','คุณไม่ได้เป็นผู้รับหนังสือฉบับนี้');

        // บันทึกการเปิดอ่าน + ทำเครื่องหมายว่าอ่านแล้ว (ถ้าเป็นผู้รับ)
        $u=Auth::user(); $myPid=null; $myDept=null; $myRecip=null;
        if($u && ($u['linked_type'] ?? '')==='personnel' && !empty($u['linked_id'])){
            $myPid=(int)$u['linked_id'];
            $myDept=$this->m->personnelDept($myPid);
            $this->m->recipientMarkRead((int)$id,$myPid);
            $myRecip=$this->m->recipientForPersonnel((int)$id,$myPid);
        }
        $this->m->viewLog((int)$id, Auth::id(), $myDept);

        $this->view('documents/track',['title'=>'ติดตามหนังสือ','d'=>$d,
            'files'=>$this->m->attachments((int)$id),
            'progress'=>$this->m->progress((int)$id),
            'timeline'=>$this->m->timeline((int)$id),
            'recipients'=>$this->m->recipients((int)$id),
            'recipStats'=>$this->m->recipientStats((int)$id),
            'assignNotes'=>$this->m->notes((int)$id,'assign'),
            'orderNotes'=>$this->m->notes((int)$id,'order'),
            'myRecip'=>$myRecip,'actions'=>Document::ACTIONS,
            'canManage'=>Auth::can('document.manage')]);
    }

    public function store(): void
    {
        $this->guardSchema(); $this->guardPostSize('documents');
        $this->authorize('document.manage'); $this->verifyCsrf();
        $d=$this->input();
        if($d['title']==='') $this->back('documents','error','กรอกชื่อเรื่อง');
        $id=$this->m->create($d,Auth::id());
        $r=$this->uploadFiles($id);
        AuditLog::record(Auth::id(),'create','documents',$id);
        $msg='ลงทะเบียนหนังสือเรียบร้อย';
        if($r['ok']) $msg.=' · แนบไฟล์ '.$r['ok'].' ไฟล์';
        if($r['fail']) $msg.=' (ข้าม '.count($r['fail']).' ไฟล์)';
        $this->back('documents/'.$id, 'success', $msg);
    }

    public function update(string $id): void
    {
        $this->guardSchema(); $this->authorize('document.manage'); $this->verifyCsrf();
        $this->m->update((int)$id,$this->input());
        AuditLog::record(Auth::id(),'update','documents',(int)$id);
        $this->back('documents/'.$id,'success','แก้ไขข้อมูลหนังสือแล้ว');
    }

    public function delete(string $id): void
    {
        $this->guardSchema(); $this->authorize('document.manage'); $this->verifyCsrf();
        foreach($this->m->attachments((int)$id) as $a) $this->removeFile($a['file_path']);
        $this->m->delete((int)$id);
        AuditLog::record(Auth::id(),'delete','documents',(int)$id);
        $this->back('documents','success','ลบหนังสือแล้ว');
    }

    private function input(): array
    {
        $type=Request::input('doc_type');
        return ['doc_number'=>trim((string)Request::input('doc_number','')),
            'receive_no'=>trim((string)Request::input('receive_no','')),
            'send_no'=>trim((string)Request::input('send_no','')),
            'received_at'=>trim((string)Request::input('received_at','')),
            'doc_type'=>in_array($type,['incoming','outgoing','circular','internal'],true)?$type:'incoming',
            'title'=>trim((string)Request::input('title','')),
            'from_org'=>trim((string)Request::input('from_org','')),
            'to_org'=>trim((string)Request::input('to_org','')),
            'doc_date'=>(string)Request::input('doc_date',''),
            'urgency'=>in_array(Request::input('urgency'),['normal','urgent','very_urgent','most_urgent'],true)?Request::input('urgency'):'normal',
            'secret_level'=>in_array(Request::input('secret_level'),['normal','confidential','secret','top_secret'],true)?Request::input('secret_level'):'normal',
            'status'=>in_array(Request::input('status'),['draft','registered','in_process','completed','archived'],true)?Request::input('status'):'registered'];
    }

    // ---------- ลงรับ / สถานะ ----------
    public function receive(string $id): void
    {
        $this->guardSchema(); $this->authorize('document.manage'); $this->verifyCsrf();
        $this->m->receive((int)$id, Auth::id(),
            trim((string)Request::input('receive_no','')), trim((string)Request::input('received_at','')));
        AuditLog::record(Auth::id(),'update','documents',(int)$id,null,['action'=>'receive']);
        $this->back('documents/'.$id,'success','ประทับตราลงรับเรียบร้อย');
    }
    public function statusSet(string $id): void
    {
        $this->guardSchema(); $this->authorize('document.manage'); $this->verifyCsrf();
        $this->m->statusSet((int)$id,(string)Request::input('status',''));
        $this->back('documents/'.$id,'success','ปรับสถานะแล้ว');
    }

    // ---------- ไฟล์แนบหลายไฟล์ ----------
    public function upload(string $id): void
    {
        $this->guardSchema(); $this->guardPostSize('documents/'.$id);
        $this->authorize('document.manage'); $this->verifyCsrf();
        if(!$this->m->find((int)$id)) $this->back('documents','error','ไม่พบหนังสือ');
        $r=$this->uploadFiles((int)$id);
        if(!$r['ok'] && !$r['fail']) $this->back('documents/'.$id,'error','ไม่ได้เลือกไฟล์');
        $msg='แนบไฟล์แล้ว '.$r['ok'].' ไฟล์';
        if($r['fail']) $msg.=' · ข้าม: '.implode(', ',$r['fail']);
        $this->back('documents/'.$id, $r['ok']?'success':'error', $msg);
    }

    /**
     * รับไฟล์จาก input name="files[]" (แนบได้หลายไฟล์พร้อมกัน)
     * @return array{ok:int,fail:array<string>}
     */
    private function uploadFiles(int $docId): array
    {
        $ok=0; $fail=[];
        if(empty($_FILES['files']) || !is_array($_FILES['files']['name'])) return ['ok'=>0,'fail'=>[]];

        $lim = upload_limits(self::MAX_BYTES);        // เพดานที่มีผลจริง (php.ini ตัดก่อนเสมอ)
        $cap = $lim['per_file'];

        $dir=BASE_PATH.'/storage/documents';
        if(!is_dir($dir)) @mkdir($dir,0775,true);
        $note=trim((string)Request::input('file_note',''));
        $n=count($_FILES['files']['name']);
        for($i=0;$i<$n;$i++){
            $err=(int)$_FILES['files']['error'][$i];
            if($err===UPLOAD_ERR_NO_FILE) continue;
            $orig=(string)$_FILES['files']['name'][$i];
            $size=(int)$_FILES['files']['size'][$i];

            // บอกเหตุผลที่ตรงกับความจริง แทนคำว่า "อัปโหลดไม่สำเร็จ" ลอยๆ
            if($err!==UPLOAD_ERR_OK){
                $fail[]=$orig.' ('.$this->uploadErrorText($err).')';
                continue;
            }
            if($cap>0 && $size>$cap){ $fail[]=$orig.' (ใหญ่เกิน '.human_bytes($cap).')'; continue; }

            $ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));
            if(!in_array($ext,self::ALLOWED,true)){
                $fail[]=$orig.' (ไม่รองรับนามสกุล .'.$ext.')'; continue;
            }
            $safe='doc'.$docId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
            if(move_uploaded_file($_FILES['files']['tmp_name'][$i], $dir.'/'.$safe)){
                $this->m->attachmentAdd($docId,'documents/'.$safe,$orig,
                    (string)($_FILES['files']['type'][$i] ?? ''),$size,$note,Auth::id());
                $ok++;
            } else { $fail[]=$orig.' (บันทึกไฟล์ลงเซิร์ฟเวอร์ไม่สำเร็จ)'; }
        }
        return ['ok'=>$ok,'fail'=>$fail];
    }

    /** แปลงรหัสข้อผิดพลาดของ PHP เป็นข้อความที่ผู้ใช้เข้าใจและแก้ได้ */
    private function uploadErrorText(int $err): string
    {
        $u=ini_get('upload_max_filesize');
        return match($err){
            UPLOAD_ERR_INI_SIZE   => 'ใหญ่เกินลิมิตเซิร์ฟเวอร์ ('.$u.') — ให้ผู้ดูแลเพิ่ม upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE  => 'ใหญ่เกินที่ฟอร์มกำหนด',
            UPLOAD_ERR_PARTIAL    => 'อัปโหลดไม่ครบ การเชื่อมต่อหลุด',
            UPLOAD_ERR_NO_TMP_DIR => 'เซิร์ฟเวอร์ไม่มีโฟลเดอร์ชั่วคราว',
            UPLOAD_ERR_CANT_WRITE => 'เซิร์ฟเวอร์เขียนไฟล์ไม่ได้',
            UPLOAD_ERR_EXTENSION  => 'ถูกส่วนขยาย PHP ระงับ',
            default               => 'อัปโหลดไม่สำเร็จ (รหัส '.$err.')',
        };
    }

    public function download(string $id): void
    {
        $this->guardSchema(); $this->authorize('document.inbox');
        $a=$this->m->attachmentFind((int)$id);
        if(!$a) $this->back('documents','error','ไม่พบไฟล์');
        // เจ้าหน้าที่สารบรรณ หรือผู้รับของหนังสือฉบับนี้เท่านั้น
        if(!$this->canAccess((int)$a['document_id']))
            $this->back('documents/inbox','error','ไม่มีสิทธิ์ดาวน์โหลดไฟล์นี้');
        // กัน path traversal: ไฟล์ต้องอยู่ใต้ storage/documents เท่านั้น
        $real=realpath(BASE_PATH.'/storage/'.$a['file_path']);
        $base=realpath(BASE_PATH.'/storage/documents');
        if(!$real || !$base || !str_starts_with($real,$base) || !is_file($real))
            $this->back('documents','error','ไม่พบไฟล์บนเซิร์ฟเวอร์');
        // ชื่อไฟล์ภาษาไทยต้องใช้ RFC 5987 (filename* ) ไม่งั้นเบราว์เซอร์จะได้ชื่อเป็น %E0%B8...
        $name=$a['original_name'];
        $ascii=preg_replace('/[^\x20-\x7E]/','_',$name);          // ชื่อสำรองสำหรับเบราว์เซอร์เก่า
        $ascii=str_replace('"','',$ascii);
        header('Content-Type: '.($a['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: '.filesize($real));
        header('Content-Disposition: attachment; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        readfile($real);
        exit;
    }

    public function fileDelete(string $id): void
    {
        $this->guardSchema(); $this->authorize('document.manage'); $this->verifyCsrf();
        $a=$this->m->attachmentFind((int)$id);
        if(!$a) $this->back('documents','error','ไม่พบไฟล์');
        $this->removeFile($a['file_path']);
        $this->m->attachmentDelete((int)$id);
        $this->back('documents/'.$a['document_id'],'success','ลบไฟล์แนบแล้ว');
    }

    private function removeFile(string $rel): void
    {
        $real=realpath(BASE_PATH.'/storage/'.$rel);
        $base=realpath(BASE_PATH.'/storage/documents');
        if($real && $base && str_starts_with($real,$base) && is_file($real)) @unlink($real);
    }

    // ---------- เกษียนหนังสือ ----------
    /** ช่องที่ 1: ลงรายละเอียดการรับเข้าเพื่อเสนอ/มอบหมาย */
    public function noteAssign(string $id): void
    {
        $this->guardSchema(); $this->authorize('document.manage'); $this->verifyCsrf();
        $body=trim((string)Request::input('body',''));
        if($body==='') $this->back('documents/'.$id,'error','กรอกข้อความเกษียน');
        $u=Auth::user();
        $this->m->noteAssign((int)$id, [
            'body'=>$body,
            'author_name'=>trim((string)Request::input('author_name','')) ?: ($u['full_name'] ?? ''),
            'author_position'=>trim((string)Request::input('author_position','')),
            'signature_path'=>(string)($u['signature_path'] ?? ''),
            'assigned_to'=>(int)Request::input('assigned_to',0),
            'assigned_dept'=>(int)Request::input('assigned_dept',0),
            'due_date'=>(string)Request::input('due_date',''),
        ], Auth::id());
        AuditLog::record(Auth::id(),'create','document_notes',(int)$id,null,['kind'=>'assign']);
        $this->back('documents/'.$id,'success','บันทึกการเกษียนเสนอเรียบร้อย');
    }

    /** ช่องที่ 2: ผู้อำนวยการอนุมัติ/ชี้แจง/สั่งการ + ลงลายมือชื่อ */
    public function noteOrder(string $id): void
    {
        $this->guardSchema(); $this->authorize('document.sign'); $this->verifyCsrf();
        $body=trim((string)Request::input('body',''));
        if($body==='') $this->back('documents/'.$id,'error','กรอกคำสั่งการ');
        $u=Auth::user();
        $this->m->noteOrder((int)$id, [
            'body'=>$body,
            'decision'=>(string)Request::input('decision','noted'),
            'author_name'=>trim((string)Request::input('author_name','')) ?: ($u['full_name'] ?? ''),
            'author_position'=>trim((string)Request::input('author_position','')) ?: 'ผู้อำนวยการโรงเรียน',
            'assigned_to'=>(int)Request::input('assigned_to',0),
            'due_date'=>(string)Request::input('due_date',''),
            'sign'=>Request::input('sign')?1:0,
            'signature_path'=>(string)($u['signature_path'] ?? ''),
        ], Auth::id());
        AuditLog::record(Auth::id(),'update','documents',(int)$id,null,['kind'=>'order']);
        $this->back('documents/'.$id,'success','บันทึกคำสั่งการเรียบร้อย');
    }

    public function noteDelete(string $id): void
    {
        $this->guardSchema(); $this->authorize('document.manage'); $this->verifyCsrf();
        $docId=(int)Request::input('document_id',0);
        $this->m->noteDelete((int)$id);
        $this->back('documents/'.$docId,'success','ลบข้อความเกษียนแล้ว');
    }


    // ---------- ส่งต่อ / มอบหมายงาน (แท็กฝ่าย หรือเลือกครูรายคน) ----------
    /** ส่งต่อหนังสือ: เลือกครูได้หลายคน และ/หรือ แท็กทั้งฝ่าย */
    public function forward(string $id): void
    {
        $this->guardSchema();
        $this->authorize('document.manage'); $this->verifyCsrf();
        if(!$this->m->find((int)$id)) $this->back('documents','error','ไม่พบหนังสือ');

        $action=(string)Request::input('action','forward');
        $instruction=trim((string)Request::input('instruction',''));
        $due=(string)Request::input('due_date','');
        $people=(array)(Request::input('personnel_ids') ?? []);
        $depts=(array)(Request::input('dept_ids') ?? []);

        $n=0;
        if($people) $n += $this->m->recipientAddMany((int)$id,$people,$action,$instruction,$due,Auth::id());
        foreach($depts as $d){
            $d=(int)$d;
            if($d>0) $n += $this->m->recipientAddDept((int)$id,$d,$action,$instruction,$due,Auth::id());
        }
        if($n===0) $this->back('documents/'.$id,'error','เลือกผู้รับอย่างน้อย 1 คน หรือเลือกฝ่าย (ผู้ที่เคยส่งแล้วจะไม่ถูกส่งซ้ำ)');

        // ส่งต่อแล้วถือว่าหนังสืออยู่ระหว่างดำเนินการ
        $this->m->statusSet((int)$id,'in_process');
        AuditLog::record(Auth::id(),'update','documents',(int)$id,null,['action'=>'forward','n'=>$n]);
        $this->back('documents/'.$id,'success','ส่งต่อหนังสือให้ '.$n.' คนเรียบร้อย');
    }

    public function recipientAck(string $id): void
    {
        $this->guardSchema();
        $this->authorize('document.inbox'); $this->verifyCsrf();
        $r=$this->m->recipientFind((int)$id);
        if(!$r) $this->back('documents','error','ไม่พบรายการ');
        // ผู้รับกดเองได้ · เจ้าหน้าที่สารบรรณกดแทนได้
        $u=Auth::user();
        $mine=$u && ($u['linked_type'] ?? '')==='personnel'
              && (int)($u['linked_id'] ?? 0)===(int)$r['recipient_personnel_id'];
        if(!$mine && !Auth::can('document.manage'))
            $this->back('documents/inbox','error','บันทึกรับทราบแทนผู้อื่นไม่ได้');
        $this->m->recipientAck((int)$id,(string)Request::input('note',''));
        $back=(string)Request::input('back','') ?: 'documents/'.(int)$r['document_id'];
        $this->back($back,'success','บันทึกผลเรียบร้อย');
    }

    public function recipientDelete(string $id): void
    {
        $this->guardSchema();
        $this->authorize('document.manage'); $this->verifyCsrf();
        $docId=(int)Request::input('document_id',0);
        $this->m->recipientDelete((int)$id);
        $this->back('documents/'.$docId,'success','ลบผู้รับแล้ว');
    }

    // ---------- หนังสือถึงฉัน ----------
    public function inbox(): void
    {
        $this->guardSchema();
        $this->authorize('document.inbox');
        $u=Auth::user();
        if(!$u || ($u['linked_type'] ?? '')!=='personnel' || empty($u['linked_id'])){
            $this->view('documents/inbox_unlinked',['title'=>'หนังสือถึงฉัน']);
            exit;
        }
        $pid=(int)$u['linked_id'];
        $filter=(string)Request::input('filter','pending');
        if(!in_array($filter,['all','pending','done','overdue'],true)) $filter='pending';
        $this->view('documents/inbox',['title'=>'หนังสือถึงฉัน',
            'rows'=>$this->m->inbox($pid,$filter),
            'stats'=>$this->m->inboxStats($pid),
            'filter'=>$filter,'actions'=>Document::ACTIONS]);
    }

    /** หน้าตรวจสอบความถูกต้องของเอกสารจากรหัส/QR — เปิดสาธารณะ ไม่ต้องล็อกอิน */
    public function verify(string $code=''): void
    {
        $this->guardSchema(); $code=trim($code!==''?$code:(string)Request::input('code',''));
        $doc=$code!==''?$this->m->verify(strtoupper($code)):null;
        $this->view('documents/verify',['title'=>'ตรวจสอบเอกสาร','doc'=>$doc,'code'=>$code],'auth');
    }

    // ---------- พิมพ์ ----------
    /** ใบปะหน้าหนังสือ: ตราปั๊มลงรับ + เกษียน 2 ช่อง */
    public function coverPrint(string $id): void
    {
        $this->guardSchema(); $this->authorize('document.manage');
        $d=$this->m->find((int)$id);
        if(!$d) $this->back('documents','error','ไม่พบหนังสือ');
        $this->view('documents/cover_print',['title'=>'ใบปะหน้าหนังสือราชการ','d'=>$d,
            'files'=>$this->m->attachments((int)$id),
            'assignNotes'=>$this->m->notes((int)$id,'assign'),
            'orderNotes'=>$this->m->notes((int)$id,'order'),
            'recipients'=>$this->m->recipients((int)$id),
            'backUrl'=>'documents/'.$id,'autoPrint'=>true],'print');
    }

    /** ทะเบียนหนังสือรับ */
    public function registerPrint(): void
    {
        $this->guardSchema(); $this->authorize('document.manage');
        $f=['type'=>(string)Request::input('type','incoming'),
            'status'=>(string)Request::input('status',''),'q'=>trim((string)Request::input('q',''))];
        $this->view('documents/register_print',['title'=>'ทะเบียนหนังสือ',
            'rows'=>$this->m->list(array_filter($f)),'f'=>$f,
            'backUrl'=>'documents','autoPrint'=>true],'print');
    }
}
