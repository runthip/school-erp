<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Schema;
use App\Models\Sar;
use App\Models\AuditLog;

/**
 * รายงานผลการปฏิบัติงานและผลการประเมินตนเอง (SAR)
 */
class SarController extends Controller
{
    private Sar $m;
    private const ALLOWED = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','gif','webp'];

    public function __construct() { $this->m = new Sar(); }

    /** กัน 500 ถ้ายังไม่ import 27 */
    private function guard(): void
    {
        $miss = Schema::missingFor('sar');
        if ($miss) { $this->view('documents/needs_migration', ['title'=>'ต้องนำเข้าฐานข้อมูลเพิ่ม','missing'=>$miss]); exit; }
    }

    /** personnel id ของผู้ใช้ปัจจุบัน (ครูที่ผูกบัญชี) */
    private function myPid(): int
    {
        $u = Auth::user();
        return ($u && ($u['linked_type'] ?? '')==='personnel') ? (int)($u['linked_id'] ?? 0) : 0;
    }

    private function canReview(): bool { return Auth::can('sar.review'); }

    /** ตรวจสิทธิ์เข้าถึง SAR ฉบับหนึ่ง: เจ้าของ หรือผู้ตรวจ */
    private function canAccess(array $sar): bool
    {
        return $this->canReview() || (int)$sar['personnel_id'] === $this->myPid();
    }

    /* ---------- รายการ ---------- */

    public function index(): void
    {
        $this->guard();
        $this->authorize('sar.own');
        $pid = $this->myPid();
        $review = $this->canReview();
        $year = (int)Request::input('year', (int)date('Y')+543);

        // ครู: เห็นของตัวเอง · ผู้ตรวจ: เห็นทั้งหมด
        $mine = $pid ? $this->m->findByPersonYear($pid, $year) : null;
        $all  = $review ? $this->m->listReports(['year'=>$year]) : [];
        $stats = $review ? $this->m->yearStats($year) : [];

        $this->view('sar/index', [
            'title' => 'รายงานประเมินตนเอง (SAR)',
            'mine' => $mine, 'all' => $all, 'stats' => $stats,
            'year' => $year, 'years' => $this->m->years(),
            'review' => $review, 'hasPid' => $pid > 0,
        ]);
    }

    /** สร้าง/เปิด SAR ของตัวเองในปีที่เลือก */
    public function start(): void
    {
        $this->guard();
        $this->authorize('sar.own');
        $pid = $this->myPid();
        if (!$pid) $this->back('sar', 'error', 'บัญชีของคุณยังไม่ได้ผูกกับข้อมูลบุคลากร ติดต่อผู้ดูแลระบบ');
        $this->verifyCsrf();
        $year = (int)Request::input('year', (int)date('Y')+543);
        $id = $this->m->ensure($pid, $year, Auth::id());
        $this->redirect('sar/'.$id.'/edit');
    }

    /* ---------- ดู/แก้ไข ---------- */

    public function show(string $id): void
    {
        $this->guard();
        $this->authorize('sar.own');
        $sar = $this->m->find((int)$id);
        if (!$sar) $this->back('sar','error','ไม่พบรายงาน');
        if (!$this->canAccess($sar)) { $this->view('errors/403',['title'=>'ไม่มีสิทธิ์']); return; }
        $this->view('sar/show', [
            'title' => 'SAR '.$sar['teacher_name'].' ปี '.$sar['academic_year'],
            's' => $sar, 'atts' => $this->m->attachments((int)$id),
            'canReview' => $this->canReview(), 'isOwner' => (int)$sar['personnel_id']===$this->myPid(),
        ]);
    }

    public function edit(string $id): void
    {
        $this->guard();
        $this->authorize('sar.own');
        $sar = $this->m->find((int)$id);
        if (!$sar) $this->back('sar','error','ไม่พบรายงาน');
        // แก้ได้เฉพาะเจ้าของ และสถานะ draft/returned
        if ((int)$sar['personnel_id'] !== $this->myPid())
            { $this->view('errors/403',['title'=>'แก้ได้เฉพาะเจ้าของ']); return; }
        if (!in_array($sar['status'], ['draft','returned'], true))
            $this->back('sar/'.$id, 'error', 'ส่งรายงานแล้ว ไม่สามารถแก้ไขได้ (ต้องให้ผู้ตรวจส่งกลับก่อน)');
        $this->view('sar/edit', [
            'title' => 'แก้ไข SAR ปี '.$sar['academic_year'],
            's' => $sar, 'atts' => $this->m->attachments((int)$id),
            'evalTpl' => Sar::EVAL_TEMPLATE,
        ]);
    }

    /** บันทึกตอนหนึ่ง (AJAX-friendly แต่ที่นี่ redirect กลับ) */
    public function save(string $id): void
    {
        $this->guard();
        $this->authorize('sar.own');
        $this->verifyCsrf();
        $sar = $this->m->find((int)$id);
        if (!$sar) $this->back('sar','error','ไม่พบรายงาน');
        if ((int)$sar['personnel_id'] !== $this->myPid())
            { $this->view('errors/403',['title'=>'ไม่มีสิทธิ์']); return; }
        if (!in_array($sar['status'], ['draft','returned'], true))
            $this->back('sar/'.$id, 'error', 'ส่งรายงานแล้ว แก้ไขไม่ได้');

        // ส่วนหัว (ข้อความ)
        foreach (['position','academic_standing','subject_group','special_duties'] as $k) {
            $v = Request::input($k, null);
            if ($v !== null) $this->m->saveSection((int)$id, $k, (string)$v);
        }
        $th = Request::input('teach_hours', null);
        if ($th !== null) $this->m->saveSection((int)$id, 'teach_hours', $th);

        // ตอน JSON: รับเป็น array ตรงๆ จากฟอร์ม
        foreach (['self_data','develop_data','duties_data','results_data','student_data','improve_data','eval_data'] as $sec) {
            $v = Request::input($sec, null);
            if ($v !== null) {
                // ฟอร์มอาจส่งเป็น JSON string (จาก Alpine) หรือ array
                if (is_string($v)) { $dec = json_decode($v, true); $v = is_array($dec) ? $dec : $v; }
                $this->m->saveSection((int)$id, $sec, $v);
            }
        }

        AuditLog::record(Auth::id(),'update','sar_reports',(int)$id,null,['save'=>true]);
        $this->back('sar/'.$id.'/edit', 'success', 'บันทึกแล้ว');
    }

    /* ---------- workflow ---------- */

    public function submit(string $id): void
    {
        $this->guard(); $this->authorize('sar.own'); $this->verifyCsrf();
        $sar = $this->m->find((int)$id);
        if (!$sar || (int)$sar['personnel_id'] !== $this->myPid())
            { $this->view('errors/403',['title'=>'ไม่มีสิทธิ์']); return; }
        $this->m->submit((int)$id);
        AuditLog::record(Auth::id(),'update','sar_reports',(int)$id,null,['submit'=>true]);
        $this->back('sar/'.$id, 'success', 'ส่งรายงานเรียบร้อย รอผู้บริหารตรวจและอนุมัติ');
    }

    public function review(string $id): void
    {
        $this->guard(); $this->authorize('sar.review'); $this->verifyCsrf();
        $sar = $this->m->find((int)$id);
        if (!$sar) $this->back('sar','error','ไม่พบรายงาน');
        $action = (string)Request::input('action','review');
        $comment = trim((string)Request::input('comment',''));
        $pid = $this->myPid();

        if ($action === 'approve') {
            $this->m->approve((int)$id, $pid, $comment);
            $msg = 'อนุมัติรายงานเรียบร้อย';
        } elseif ($action === 'return') {
            if ($comment === '') $this->back('sar/'.$id,'error','กรุณาระบุเหตุผลที่ส่งกลับแก้ไข');
            $this->m->returnToTeacher((int)$id, $comment);
            $msg = 'ส่งกลับให้ครูแก้ไขแล้ว';
        } else {
            $this->m->review((int)$id, $pid, $comment);
            $msg = 'บันทึกความเห็นเรียบร้อย';
        }
        AuditLog::record(Auth::id(),'update','sar_reports',(int)$id,null,['review'=>$action]);
        $this->back('sar/'.$id, 'success', $msg);
    }

    /* ---------- ไฟล์แนบ ---------- */

    public function upload(string $id): void
    {
        $this->guard(); $this->authorize('sar.own');
        $this->guardPostSize('sar/'.$id.'/edit');
        $this->verifyCsrf();
        $sar = $this->m->find((int)$id);
        if (!$sar || (int)$sar['personnel_id'] !== $this->myPid())
            { $this->view('errors/403',['title'=>'ไม่มีสิทธิ์']); return; }

        $category = (string)Request::input('category','other');
        $valid = ['training_cert','teaching_order','admin_order','report','award','photo','research','other'];
        if (!in_array($category, $valid, true)) $category = 'other';

        $lim = upload_limits(20*1024*1024);
        $dir = BASE_PATH.'/storage/sar';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $ok=0; $fail=[];
        $note = trim((string)Request::input('note',''));

        if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
            $n = count($_FILES['files']['name']);
            for ($i=0;$i<$n;$i++) {
                if ((int)$_FILES['files']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $orig = (string)$_FILES['files']['name'][$i];
                $size = (int)$_FILES['files']['size'][$i];
                if ((int)$_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) { $fail[]=$orig.' (อัปโหลดไม่สำเร็จ)'; continue; }
                if ($lim['per_file']>0 && $size>$lim['per_file']) { $fail[]=$orig.' (ใหญ่เกิน '.human_bytes($lim['per_file']).')'; continue; }
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, self::ALLOWED, true)) { $fail[]=$orig.' (ไม่รองรับ .'.$ext.')'; continue; }
                $safe = 'sar'.$id.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
                if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $dir.'/'.$safe)) {
                    $this->m->attachmentAdd((int)$id, $category, 'sar/'.$safe, $orig,
                        (string)($_FILES['files']['type'][$i] ?? ''), $size, $note?:null, Auth::id());
                    $ok++;
                } else { $fail[]=$orig.' (บันทึกไฟล์ไม่สำเร็จ)'; }
            }
        }
        $msg = 'แนบไฟล์แล้ว '.$ok.' ไฟล์'.($fail ? ' · ข้าม: '.implode(', ',$fail) : '');
        $this->back('sar/'.$id.'/edit', $fail?'error':'success', $msg);
    }

    public function download(string $aid): void
    {
        $this->guard(); $this->authorize('sar.own');
        $a = $this->m->attachmentFind((int)$aid);
        if (!$a) { http_response_code(404); echo 'ไม่พบไฟล์'; return; }
        $sar = $this->m->find((int)$a['sar_id']);
        if (!$sar || !$this->canAccess($sar)) { http_response_code(403); echo 'ไม่มีสิทธิ์'; return; }
        $full = BASE_PATH.'/storage/'.$a['file_path'];
        if (!is_file($full)) { http_response_code(404); echo 'ไฟล์หายไป'; return; }
        header('Content-Type: '.($a['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($a['original_name']));
        header('Content-Length: '.filesize($full));
        readfile($full); exit;
    }

    public function fileDelete(string $aid): void
    {
        $this->guard(); $this->authorize('sar.own'); $this->verifyCsrf();
        $a = $this->m->attachmentFind((int)$aid);
        if ($a) {
            $sar = $this->m->find((int)$a['sar_id']);
            if ($sar && (int)$sar['personnel_id']===$this->myPid() && in_array($sar['status'],['draft','returned'],true)) {
                $full = BASE_PATH.'/storage/'.$a['file_path'];
                $base = realpath(BASE_PATH.'/storage/sar');
                $rp = realpath($full);
                if ($rp && $base && str_starts_with($rp,$base) && is_file($rp)) @unlink($rp);
                $this->m->attachmentDelete((int)$aid);
                $this->back('sar/'.$a['sar_id'].'/edit','success','ลบไฟล์แล้ว');
            }
        }
        $this->back('sar','error','ลบไม่สำเร็จ');
    }

    /* ---------- พิมพ์ ---------- */

    public function print(string $id): void
    {
        $this->guard(); $this->authorize('sar.own');
        $sar = $this->m->find((int)$id);
        if (!$sar) $this->back('sar','error','ไม่พบรายงาน');
        if (!$this->canAccess($sar)) { $this->view('errors/403',['title'=>'ไม่มีสิทธิ์']); return; }
        $this->view('sar/print', [
            'title' => 'SAR '.$sar['teacher_name'],
            's' => $sar, 'atts' => $this->m->attachments((int)$id),
            'evalTpl' => Sar::EVAL_TEMPLATE, 'autoPrint' => true,
        ], 'print');
    }

    /* ---------- รายงานภาพรวม ---------- */

    public function report(): void
    {
        $this->guard(); $this->authorize('sar.report');
        $year = (int)Request::input('year', (int)date('Y')+543);
        $this->view('sar/report', [
            'title' => 'รายงานภาพรวม SAR',
            'year' => $year, 'years' => $this->m->years(),
            'stats' => $this->m->yearStats($year),
            'rows' => $this->m->listReports(['year'=>$year]),
        ]);
    }
}
