<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\ActivityReport;
use App\Models\AuditLog;

/**
 * รายงานผลการดำเนินงานกิจกรรม/การแข่งขันของสถานศึกษา (งานฝ่ายบริหาร)
 * ครูทุกคนเพิ่ม/ดูได้ (activity.report) · แก้ไข/ลบเฉพาะผู้บันทึกหรือผู้บริหาร
 */
class ActivityController extends Controller
{
    private const ALLOWED = ['pdf','jpg','jpeg','png','webp'];
    private const MAX_BYTES = 20971520; // 20 MB
    private ActivityReport $m;
    public function __construct(){ $this->m = new ActivityReport(); }

    private function canEdit(array $r): bool
    { return (int)$r['created_by'] === (int)Auth::id() || Auth::can('admin.reports'); }

    private function guardEdit(int $id): array
    {
        $r = $this->m->find($id);
        if (!$r) $this->back('activities', 'error', 'ไม่พบรายงานกิจกรรม');
        if (!$this->canEdit($r)) $this->back('activities/'.$id, 'error', 'แก้ไข/ลบได้เฉพาะผู้บันทึกหรือผู้บริหาร');
        return $r;
    }

    public function index(): void
    {
        $this->authorize('activity.report');
        $f = ['q'=>trim((string)Request::input('q','')), 'cat'=>(string)Request::input('cat',''), 'year'=>(string)Request::input('year','')];
        $this->view('activities/index', [
            'title'=>'รายงานผลกิจกรรม/การแข่งขัน',
            'rows'=>$this->m->list(array_filter($f)), 'f'=>$f,
            'stats'=>$this->m->stats(), 'years'=>$this->m->years(),
            'categories'=>ActivityReport::CATEGORIES,
            'currentYear'=>(int)date('Y')+543,
            'grades'=>$this->grades(),
            'lim'=>upload_limits(self::MAX_BYTES),
        ]);
    }

    public function store(): void
    {
        $this->authorize('activity.report'); $this->verifyCsrf();
        $d = $this->collect();
        if ($d['title'] === '') $this->back('activities', 'error', 'กรุณากรอกชื่อกิจกรรม');
        $id = $this->m->create($d, Auth::id());
        // ผู้เข้าแข่งขัน (หลายแถว)
        $this->m->participantsAddMany($id,
            (array)Request::input('p_name', []), (array)Request::input('p_grade', []),
            (array)Request::input('p_type', []),  (array)Request::input('p_award', []));
        $r = $this->uploadFiles($id, (string)Request::input('file_kind','photo'), (string)Request::input('file_caption',''));
        AuditLog::record(Auth::id(), 'create', 'activity_reports', $id);
        $msg = 'บันทึกรายงานกิจกรรมแล้ว'.($r['ok']?' · แนบไฟล์ '.$r['ok'].' ไฟล์':'');
        $this->back('activities/'.$id, 'success', $msg);
    }

    public function show(string $id): void
    {
        $this->authorize('activity.report');
        $r = $this->m->find((int)$id);
        if (!$r) $this->back('activities', 'error', 'ไม่พบรายงานกิจกรรม');
        $this->view('activities/show', [
            'title'=>$r['title'], 'r'=>$r,
            'participants'=>$this->m->participants((int)$id),
            'files'=>$this->m->files((int)$id),
            'categories'=>ActivityReport::CATEGORIES, 'grades'=>$this->grades(),
            'canEdit'=>$this->canEdit($r), 'lim'=>upload_limits(self::MAX_BYTES),
        ]);
    }

    public function update(string $id): void
    {
        $this->authorize('activity.report'); $this->verifyCsrf();
        $this->guardEdit((int)$id);
        $d = $this->collect();
        if ($d['title'] === '') $this->back('activities/'.$id, 'error', 'กรุณากรอกชื่อกิจกรรม');
        $this->m->update((int)$id, $d);
        AuditLog::record(Auth::id(), 'update', 'activity_reports', (int)$id);
        $this->back('activities/'.$id, 'success', 'บันทึกการแก้ไขแล้ว');
    }

    public function delete(string $id): void
    {
        $this->authorize('activity.report'); $this->verifyCsrf();
        $this->guardEdit((int)$id);
        foreach ($this->m->files((int)$id) as $f) $this->removeFile($f['file_path']);
        $this->m->delete((int)$id);
        AuditLog::record(Auth::id(), 'delete', 'activity_reports', (int)$id);
        $this->back('activities', 'success', 'ลบรายงานกิจกรรมแล้ว');
    }

    // ---------- ผู้เข้าแข่งขัน (เพิ่ม/ลบในหน้ารายละเอียด) ----------
    public function participantStore(string $id): void
    {
        $this->authorize('activity.report'); $this->verifyCsrf();
        $this->guardEdit((int)$id);
        $name = trim((string)Request::input('name',''));
        if ($name === '') $this->back('activities/'.$id, 'error', 'กรอกชื่อผู้เข้าแข่งขัน');
        $this->m->participantAdd((int)$id, [
            'name'=>$name, 'grade_level'=>(string)Request::input('grade_level',''),
            'event_type'=>(string)Request::input('event_type',''), 'award'=>(string)Request::input('award',''),
        ]);
        $this->back('activities/'.$id, 'success', 'เพิ่มผู้เข้าแข่งขันแล้ว');
    }
    public function participantDelete(string $pid): void
    {
        $this->authorize('activity.report'); $this->verifyCsrf();
        $p = $this->m->participantFind((int)$pid);
        if (!$p) $this->back('activities', 'error', 'ไม่พบรายการ');
        $this->guardEdit((int)$p['report_id']);
        $this->m->participantDelete((int)$pid);
        $this->back('activities/'.$p['report_id'], 'success', 'ลบผู้เข้าแข่งขันแล้ว');
    }

    // ---------- ไฟล์ ----------
    public function fileUpload(string $id): void
    {
        $this->authorize('activity.report'); $this->verifyCsrf();
        $this->guardEdit((int)$id);
        $r = $this->uploadFiles((int)$id, (string)Request::input('file_kind','photo'), (string)Request::input('file_caption',''));
        if (!$r['ok'] && !$r['fail']) $this->back('activities/'.$id, 'error', 'ไม่ได้เลือกไฟล์');
        $msg = 'แนบไฟล์แล้ว '.$r['ok'].' ไฟล์'.($r['fail']?' · ข้าม: '.implode(', ', $r['fail']):'');
        $this->back('activities/'.$id, $r['ok']?'success':'error', $msg);
    }
    public function fileDownload(string $fid): void
    {
        $this->authorize('activity.report');
        $f = $this->m->fileFind((int)$fid);
        if (!$f) $this->back('activities', 'error', 'ไม่พบไฟล์');
        $real = realpath(BASE_PATH.'/storage/'.$f['file_path']);
        $base = realpath(BASE_PATH.'/storage/activities');
        if (!$real || !$base || !str_starts_with($real, $base) || !is_file($real))
            $this->back('activities', 'error', 'ไม่พบไฟล์บนเซิร์ฟเวอร์');
        $name = $f['original_name'];
        $ascii = str_replace('"','',preg_replace('/[^\x20-\x7E]/','_',$name));
        header('Content-Type: '.($f['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: '.filesize($real));
        header('Content-Disposition: inline; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        readfile($real); exit;
    }
    public function fileDelete(string $fid): void
    {
        $this->authorize('activity.report'); $this->verifyCsrf();
        $f = $this->m->fileFind((int)$fid);
        if (!$f) $this->back('activities', 'error', 'ไม่พบไฟล์');
        $this->guardEdit((int)$f['report_id']);
        $this->removeFile($f['file_path']);
        $this->m->fileDelete((int)$fid);
        $this->back('activities/'.$f['report_id'], 'success', 'ลบไฟล์แล้ว');
    }

    // ---------- พิมพ์รายงาน ----------
    public function print(string $id): void
    {
        $this->authorize('activity.report');
        $r = $this->m->find((int)$id);
        if (!$r) $this->back('activities', 'error', 'ไม่พบรายงานกิจกรรม');
        $this->view('activities/print', [
            'title'=>'รายงานผลกิจกรรม - '.$r['title'], 'r'=>$r,
            'participants'=>$this->m->participants((int)$id),
            'files'=>$this->m->files((int)$id),
            'backUrl'=>'activities/'.$id, 'autoPrint'=>true,
        ], 'print');
    }
    // ---------- บันทึกข้อความ (เสนอ ผอ.) ----------
    public function memoSave(string $id): void
    {
        $this->authorize('activity.report'); $this->verifyCsrf();
        $this->guardEdit((int)$id);
        $this->m->memoSave((int)$id, [
            'memo_no'=>(string)Request::input('memo_no',''),
            'memo_date'=>(string)Request::input('memo_date',''),
            'memo_agency'=>(string)Request::input('memo_agency',''),
            'memo_to'=>(string)Request::input('memo_to',''),
            'memo_purpose'=>(string)Request::input('memo_purpose','acknowledge'),
            'reporter_name'=>(string)Request::input('reporter_name',''),
            'reporter_position'=>(string)Request::input('reporter_position',''),
        ]);
        AuditLog::record(Auth::id(), 'update', 'activity_reports', (int)$id, null, ['memo'=>true]);
        $this->back('activities/'.$id, 'success', 'บันทึกข้อมูลบันทึกข้อความแล้ว');
    }

    public function memo(string $id): void
    {
        $this->authorize('activity.report');
        $r = $this->m->find((int)$id);
        if (!$r) $this->back('activities', 'error', 'ไม่พบรายงานกิจกรรม');
        $this->view('activities/memo_print', [
            'title'=>'บันทึกข้อความ - '.$r['title'], 'r'=>$r,
            'participants'=>$this->m->participants((int)$id),
            'purposes'=>ActivityReport::MEMO_PURPOSES,
            'backUrl'=>'activities/'.$id, 'autoPrint'=>true,
        ], 'print');
    }

    /** สรุปทะเบียนกิจกรรม (สำหรับรายงานประจำปี/SAR) */
    public function summaryPrint(): void
    {
        $this->authorize('activity.report');
        $f = ['q'=>trim((string)Request::input('q','')), 'cat'=>(string)Request::input('cat',''), 'year'=>(string)Request::input('year','')];
        $this->view('activities/summary_print', [
            'title'=>'สรุปทะเบียนกิจกรรม', 'rows'=>$this->m->list(array_filter($f)), 'f'=>$f,
            'categories'=>ActivityReport::CATEGORIES, 'backUrl'=>'activities', 'autoPrint'=>true,
        ], 'print');
    }

    // ---------- helpers ----------
    private function collect(): array
    {
        return [
            'title'=>trim((string)Request::input('title','')),
            'category'=>(string)Request::input('category','academic'),
            'date_start'=>(string)Request::input('date_start',''),
            'date_end'=>(string)Request::input('date_end',''),
            'location'=>(string)Request::input('location',''),
            'organizer'=>(string)Request::input('organizer',''),
            'coaches'=>(string)Request::input('coaches',''),
            'result_summary'=>(string)Request::input('result_summary',''),
            'summary'=>(string)Request::input('summary',''),
            'problems'=>(string)Request::input('problems',''),
            'suggestions'=>(string)Request::input('suggestions',''),
            'year_be'=>(int)Request::input('year_be',0) ?: null,
        ];
    }
    private function grades(): array
    { return $this->m->gradeLevels(); }

    /** @return array{ok:int,fail:array<string>} */
    private function uploadFiles(int $reportId, string $kind, string $caption): array
    {
        $ok = 0; $fail = [];
        if (empty($_FILES['files']) || !is_array($_FILES['files']['name'])) return ['ok'=>0,'fail'=>[]];
        $lim = upload_limits(self::MAX_BYTES); $cap = $lim['per_file'];
        $dir = BASE_PATH.'/storage/activities';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $n = count($_FILES['files']['name']);
        for ($i=0; $i<$n; $i++) {
            $err = (int)$_FILES['files']['error'][$i];
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            $orig = (string)$_FILES['files']['name'][$i];
            $size = (int)$_FILES['files']['size'][$i];
            if ($err !== UPLOAD_ERR_OK) { $fail[] = $orig.' (อัปโหลดไม่สำเร็จ)'; continue; }
            if ($cap>0 && $size>$cap) { $fail[] = $orig.' (ใหญ่เกิน '.human_bytes($cap).')'; continue; }
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED, true)) { $fail[] = $orig.' (รับ PDF/รูปภาพ)'; continue; }
            $safe = 'act'.$reportId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
            if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $dir.'/'.$safe)) {
                $this->m->fileAdd($reportId, $kind, 'activities/'.$safe, $orig,
                    (string)($_FILES['files']['type'][$i] ?? ''), $size, $caption, Auth::id());
                $ok++;
            } else { $fail[] = $orig.' (บันทึกไฟล์ไม่สำเร็จ)'; }
        }
        return ['ok'=>$ok, 'fail'=>$fail];
    }
    private function removeFile(string $rel): void
    {
        $real = realpath(BASE_PATH.'/storage/'.$rel);
        $base = realpath(BASE_PATH.'/storage/activities');
        if ($real && $base && str_starts_with($real, $base) && is_file($real)) @unlink($real);
    }
}
