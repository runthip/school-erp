<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Migrator;
use App\Models\Document;
use App\Models\AuditLog;

/**
 * นำเข้าไฟล์ SQL จากในระบบ — ผู้ใช้ไม่ต้องเปิด phpMyAdmin เอง
 * เฉพาะผู้ดูแลระบบ เพราะเป็นการแก้โครงสร้างฐานข้อมูล
 */
class SetupController extends Controller
{
    private function guard(): void { $this->authorize('system.settings'); }

    /** คัดเฉพาะชื่อไฟล์ที่มีอยู่จริงใน database/ (กันการส่งชื่อมั่ว) */
    private function validFiles(string $csv): array
    {
        $all=Migrator::files();
        $want=array_filter(array_map('trim', explode(',', $csv)));
        return array_values(array_filter($want, fn($f)=>in_array($f,$all,true)));
    }

    public function migrations(): void
    {
        $this->guard();
        $applied=Migrator::applied();
        $rows=[];
        foreach(Migrator::files() as $f){
            $rows[]=['file'=>$f,
                     'applied'=>isset($applied[$f]),
                     'at'=>$applied[$f]['applied_at'] ?? null,
                     'statements'=>$applied[$f]['statements'] ?? 0,
                     'rerunnable'=>Migrator::isRerunnable($f)];
        }
        $this->view('setup/migrations',[
            'title'=>'นำเข้าฐานข้อมูล','rows'=>$rows,
            'pending'=>Migrator::pending(),
            'fresh'=>Migrator::isFreshDatabase(),
            'results'=>$_SESSION['migrate_results'] ?? null,
        ]);
        unset($_SESSION['migrate_results']);
    }

    /** นำเข้าไฟล์ที่ระบุ */
    public function run(): void
    {
        $this->guard(); $this->verifyCsrf();
        $files=$this->validFiles((string)Request::input('files',''));
        if(!$files) $this->back('setup/migrations','error','ไม่ได้เลือกไฟล์');

        $res=Migrator::run($files);
        $ok=count(array_filter($res, fn($r)=>$r['ok']));
        $fail=array_values(array_filter($res, fn($r)=>!$r['ok'] && !$r['skipped']));
        $_SESSION['migrate_results']=$res;
        AuditLog::record(Auth::id(),'update','schema_migrations',null,null,['files'=>count($files),'ok'=>$ok]);

        if($fail) $this->back('setup/migrations','error','ล้มเหลวที่ '.$fail[0]['file'].' — ดูรายละเอียดด้านล่าง');
        $this->back('setup/migrations','success','นำเข้าสำเร็จ '.$ok.' ไฟล์');
    }

    /** ทำเครื่องหมายว่านำเข้าแล้ว โดยไม่รันจริง (ไฟล์ที่ import เองมาก่อน) */
    public function mark(): void
    {
        $this->guard(); $this->verifyCsrf();
        $files=$this->validFiles((string)Request::input('files',''));
        if(!$files) $this->back('setup/migrations','error','ไม่ได้เลือกไฟล์');
        foreach($files as $f) Migrator::markApplied($f);
        AuditLog::record(Auth::id(),'update','schema_migrations',null,null,['mark'=>count($files)]);
        $this->back('setup/migrations','success','ทำเครื่องหมายว่านำเข้าแล้ว '.count($files).' ไฟล์');
    }

    /**
     * ปุ่มลัดจากหน้าที่แจ้งว่าฐานข้อมูลไม่ครบ
     * รับเฉพาะไฟล์ที่ตัวตรวจ schema ระบุว่าขาดจริง และต้องเป็นไฟล์ที่รันซ้ำได้อย่างปลอดภัย
     */
    public function runMissing(): void
    {
        $this->guard(); $this->verifyCsrf();
        $back=(string)Request::input('back','documents');
        // คำนวณเองว่าขาดไฟล์ไหน ไม่รับรายชื่อจากฝั่งผู้ใช้
        $files=\App\Core\Schema::missingAll();
        if(!$files) $this->back($back,'success','ฐานข้อมูลครบอยู่แล้ว');

        // กันพลาด: ไม่รันไฟล์ที่รันซ้ำแล้วข้อมูลพัง (เช่น 01_schema, 02_seed)
        $unsafe=array_values(array_filter($files, fn($f)=>!Migrator::isRerunnable($f)));
        if($unsafe){
            $this->back('setup/migrations','error',
                'ไฟล์ '.implode(', ',$unsafe).' รันซ้ำอัตโนมัติไม่ได้ กรุณาตรวจสอบในหน้านี้');
        }

        $res=Migrator::run($files);
        $fail=array_values(array_filter($res, fn($r)=>!$r['ok'] && !$r['skipped']));
        $_SESSION['migrate_results']=$res;
        AuditLog::record(Auth::id(),'update','schema_migrations',null,null,['auto'=>true,'files'=>count($files)]);

        if($fail) $this->back('setup/migrations','error','นำเข้าไม่สำเร็จที่ '.$fail[0]['file'].' — ดูรายละเอียดด้านล่าง');
        unset($_SESSION['migrate_results']);
        $this->back($back,'success','นำเข้าฐานข้อมูลเรียบร้อย ใช้งานได้แล้ว');
    }

    // ---------- รีเซตระบบสารบรรณอิเล็กทรอนิกส์ ----------
    /** หน้าจัดการ: แสดงสถิติข้อมูลปัจจุบัน + ปุ่มล้างข้อมูล (super_admin เท่านั้น) */
    public function docReset(): void
    {
        $this->authorize('system.settings');   // ไม่มี role ใดได้รับ = เฉพาะ super_admin
        $m=new Document();
        $this->view('setup/doc_reset',[
            'title'=>'จัดการฐานข้อมูลสารบรรณ',
            'stats'=>$m->dataStats(),
        ]);
    }

    /** ลงมือล้างข้อมูล — ต้องพิมพ์คำยืนยันให้ตรงเป๊ะ */
    public function docResetRun(): void
    {
        $this->authorize('system.settings'); $this->verifyCsrf();

        // ชั้นกันพลาด: ต้องพิมพ์คำว่า "รีเซต" ให้ตรง
        $confirm=trim((string)Request::input('confirm',''));
        if($confirm!=='รีเซต')
            $this->back('setup/doc-reset','error','พิมพ์คำยืนยันไม่ถูกต้อง — ต้องพิมพ์คำว่า "รีเซต" เพื่อยืนยัน');

        $m=new Document();

        // 1) ลบไฟล์แนบบนดิสก์ก่อน (DB CASCADE ไม่ลบไฟล์ให้)
        $paths=$m->allAttachmentPaths();
        $filesDeleted=0;
        foreach($paths as $rel){
            $rel=ltrim((string)$rel,'/');
            // กัน path traversal: อนุญาตเฉพาะใต้ storage/documents
            $full=realpath(BASE_PATH.'/storage/'.$rel);
            $base=realpath(BASE_PATH.'/storage/documents');
            if($full && $base && str_starts_with($full,$base) && is_file($full)){
                if(@unlink($full)) $filesDeleted++;
            }
        }

        // 2) ล้างข้อมูลในฐานข้อมูล + รีเซตเลขทะเบียน
        $before=$m->resetAll();

        AuditLog::record(Auth::id(),'delete','documents',null,null,
            ['action'=>'reset_all','removed'=>$before,'files'=>$filesDeleted]);

        $this->back('setup/doc-reset','success',
            'ล้างข้อมูลสารบรรณเรียบร้อย — ลบหนังสือ '.$before['documents'].' ฉบับ · ไฟล์แนบ '.$filesDeleted.' ไฟล์ · เลขทะเบียนเริ่มนับใหม่');
    }


    /* ---------- สำรองข้อมูล ---------- */
    public function backup(): void
    {
        $this->authorize('system.settings');
        $dir = BASE_PATH.'/storage/backups';
        $files = [];
        if (is_dir($dir)) {
            foreach (glob($dir.'/school_erp_*.sql.gz') as $f) {
                $files[] = ['name'=>basename($f),'size'=>filesize($f),'time'=>filemtime($f)];
            }
            usort($files, fn($a,$b)=>$b['time']<=>$a['time']);
        }
        $this->view('setup/backup', [
            'title'=>'สำรองข้อมูล','files'=>array_slice($files,0,30),
            'lastRun'=>$files[0]['time']??null,
        ]);
    }

    public function backupRun(): void
    {
        $this->authorize('system.settings'); $this->verifyCsrf();
        $script = BASE_PATH.'/scripts/backup.sh';
        $dir = BASE_PATH.'/storage/backups';
        @mkdir($dir, 0775, true);
        $out = [];  $code = 0;
        if (is_file($script)) {
            @exec('bash '.escapeshellarg($script).' 2>&1', $out, $code);
        }
        \App\Models\AuditLog::record(\App\Core\Auth::id(),'backup','system',null,null,['manual'=>true]);
        $ok = $code === 0 && count(glob($dir.'/school_erp_*.sql.gz')) > 0;
        $this->back('setup/backup', $ok?'success':'error',
            $ok?'สำรองข้อมูลเรียบร้อย':'สำรองไม่สำเร็จ — ตรวจสอบสิทธิ์ mysqldump หรือใช้ cron แทน');
    }
}