<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\Setting;
use App\Models\AuditLog;

class SettingController extends Controller
{
    private Setting $m;
    public function __construct(){ $this->m = new Setting(); }

    public function index(): void
    {
        $this->authorize('system.settings');
        $this->view('settings/index', [
            'title'=>'ตั้งค่าระบบ',
            'school'=>$this->m->school(),
            'garudaPath'=>(string)$this->m->get('document','garuda_path',''),
            'groups'=>$this->m->all(),
            'years'=>$this->m->years(),
            'semesters'=>$this->m->semesters(),
            'stats'=>$this->m->stats(),
            'allStages'=>all_stages(),
            'enabledStages'=>enabled_stages(),
        ]);
    }

    /** บันทึกช่วงชั้นที่โรงเรียนเปิดใช้งาน — ซ่อน/แสดงโมดูล+ระดับชั้นตามนี้ */
    public function stagesSave(): void
    {
        $this->authorize('system.settings'); $this->verifyCsrf();
        $by=Auth::id(); $on=0;
        foreach(array_keys(all_stages()) as $s){
            $v = Request::input('stage_'.$s) ? '1' : '0';
            $this->m->set('stages', $s, $v, $by);
            if($v==='1') $on++;
        }
        if($on===0) $this->back('settings','error','ต้องเปิดใช้งานอย่างน้อย 1 ช่วงชั้น');
        AuditLog::record($by,'update','system_settings',0,null,['group'=>'stages','on'=>$on]);
        $this->back('settings','success','บันทึกช่วงชั้นที่เปิดใช้งานแล้ว — เมนูและรายการระดับชั้นจะปรับตามทันที');
    }

    /** บันทึกข้อมูลโรงเรียน — มีผลกับหัวเอกสารทุกใบพิมพ์ทันที */
    public function schoolSave(): void
    {
        $this->authorize('system.settings');
        $this->verifyCsrf();
        $d=Request::only(['id','school_code','name_th','name_en','address','district','province','postcode','phone','email','affiliation']);
        if(trim((string)($d['name_th']??''))==='') $this->back('settings','error','กรอกชื่อโรงเรียน');
        $this->m->schoolUpdate($d);
        AuditLog::record(Auth::id(),'update','schools',(int)($d['id']??1));
        $this->back('settings','success','บันทึกข้อมูลโรงเรียนแล้ว — หัวเอกสารทุกใบจะใช้ข้อมูลใหม่ทันที');
    }

    /** อัปโหลด/ลบ โลโก้โรงเรียน + ตราครุฑ (มีผลกับหัวเอกสารทุกใบทันที) */
    public function brandingSave(): void
    {
        $this->authorize('system.settings');
        $this->verifyCsrf();
        $by=Auth::id();
        $done=[];

        // ลบรูปเดิม (ถ้าติ๊ก)
        if(Request::input('remove_logo')){ $this->deleteAsset($this->m->school()['logo_path'] ?? null); $this->m->schoolLogoUpdate(null); $done[]='ลบโลโก้'; }
        if(Request::input('remove_garuda')){ $this->deleteAsset((string)$this->m->get('document','garuda_path','')); $this->m->set('document','garuda_path','',$by); $done[]='ลบตราครุฑ'; }

        // อัปโหลดใหม่
        if($p=$this->saveUpload('logo','school_logo')){ $this->deleteAsset($this->m->school()['logo_path'] ?? null); $this->m->schoolLogoUpdate($p); $done[]='โลโก้โรงเรียน'; }
        if($p=$this->saveUpload('garuda','garuda')){ $this->deleteAsset((string)$this->m->get('document','garuda_path','')); $this->m->set('document','garuda_path',$p,$by); $done[]='ตราครุฑ'; }

        if(!$done) $this->back('settings','error','ไม่มีไฟล์ให้บันทึก — เลือกไฟล์รูปก่อน');
        AuditLog::record($by,'update','schools',(int)($this->m->school()['id'] ?? 1),null,['branding'=>$done]);
        $this->back('settings','success','บันทึกตราสัญลักษณ์แล้ว ('.implode(', ',$done).') — หัวเอกสารทุกใบใช้รูปใหม่ทันที');
    }

    /**
     * ตราสัญลักษณ์ระบบ (แถบเมนู/หน้าล็อกอิน): ชื่อระบบ + คำบรรยาย + โลโก้
     * ต้องยืนยันด้วยรหัสผ่านผู้ดูแลระบบก่อนบันทึก
     */
    public function appBrandingSave(): void
    {
        $this->authorize('system.settings');
        $this->verifyCsrf();

        // ยืนยันด้วยรหัสผ่านของผู้ดูแลระบบที่กำลังใช้งาน
        $pw = (string)Request::input('admin_password','');
        $u  = Auth::user();
        if ($pw==='' || !$u || !password_verify($pw, $u['password_hash']))
            $this->back('settings','error','รหัสผ่านผู้ดูแลระบบไม่ถูกต้อง — ยังไม่ได้เปลี่ยนโลโก้/ชื่อระบบ');

        $by=Auth::id(); $done=[];
        $name=trim((string)Request::input('app_name',''));
        $subtitle=trim((string)Request::input('app_subtitle',''));
        if($name!==''){ $this->m->set('branding','app_name',$name,$by); $done[]='ชื่อระบบ'; }
        $this->m->set('branding','app_subtitle',$subtitle,$by);

        if(Request::input('remove_app_logo')){
            $this->deleteAsset((string)$this->m->get('branding','app_logo',''));
            $this->m->set('branding','app_logo','',$by); $done[]='ลบโลโก้ระบบ';
        }
        if($p=$this->saveUpload('app_logo','app_logo')){
            $this->deleteAsset((string)$this->m->get('branding','app_logo',''));
            $this->m->set('branding','app_logo',$p,$by); $done[]='โลโก้ระบบ';
        }
        AuditLog::record($by,'update','system_settings',0,null,['branding_app'=>$done]);
        $this->back('settings','success','บันทึกตราสัญลักษณ์ระบบแล้ว'.($done?' ('.implode(', ',$done).')':'').' — แถบเมนูจะใช้ค่าใหม่ทันที');
    }

    /** จัดการไฟล์อัปโหลดรูป — คืน path (สัมพัทธ์กับ public) หรือ null */
    private function saveUpload(string $field, string $prefix): ?string
    {
        if(empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) return null;
        if(($_FILES[$field]['error'] ?? 1)!==UPLOAD_ERR_OK) $this->back('settings','error','อัปโหลดไฟล์ไม่สำเร็จ');
        if(($_FILES[$field]['size'] ?? 0) > 2*1024*1024) $this->back('settings','error','ไฟล์รูปต้องไม่เกิน 2 MB');
        $ext=strtolower(pathinfo($_FILES[$field]['name'] ?? '', PATHINFO_EXTENSION));
        if(!in_array($ext,['png','jpg','jpeg','gif','webp','svg'],true)) $this->back('settings','error','รองรับเฉพาะไฟล์รูป (png, jpg, gif, webp, svg)');
        $dir=\BASE_PATH.'/public/uploads';
        if(!is_dir($dir)) @mkdir($dir,0775,true);
        $name=$prefix.'_'.date('YmdHis').'_'.substr(bin2hex(random_bytes(3)),0,6).'.'.$ext;
        if(!move_uploaded_file($_FILES[$field]['tmp_name'], $dir.'/'.$name)) $this->back('settings','error','บันทึกไฟล์ไม่ได้ (ตรวจสิทธิ์โฟลเดอร์ public/uploads)');
        return 'uploads/'.$name;
    }

    /** ลบไฟล์เดิมออกจากดิสก์ (เฉพาะที่อยู่ใน uploads/) */
    private function deleteAsset(?string $path): void
    {
        if(!$path || !str_starts_with($path,'uploads/')) return;
        $f=\BASE_PATH.'/public/'.$path;
        if(is_file($f)) @unlink($f);
    }

    /** บันทึกค่าตั้งค่าเป็นกลุ่ม */
    public function save(): void
    {
        $this->authorize('system.settings');
        $this->verifyCsrf();
        $group=(string)Request::input('group','general');
        $vals=(array)Request::input('s',[]);
        $by=Auth::id();
        // ตรวจสัดส่วนคะแนนต้องรวมได้ 100
        if($group==='academic' && isset($vals['grade_ratio_during'], $vals['grade_ratio_final'])){
            if((int)$vals['grade_ratio_during'] + (int)$vals['grade_ratio_final'] !== 100)
                $this->back('settings','error','สัดส่วนคะแนนระหว่างภาค + ปลายภาค ต้องรวมได้ 100');
        }
        $n=0;
        foreach($vals as $k=>$v){ $this->m->set($group,(string)$k,is_array($v)?json_encode($v):(string)$v,$by); $n++; }
        AuditLog::record($by,'update','system_settings',0,null,['group'=>$group,'count'=>$n]);
        $this->back('settings','success','บันทึกการตั้งค่า '.$n.' รายการแล้ว');
    }

    // ---------- ปีการศึกษา ----------
    public function yearAdd(): void
    {
        $this->authorize('system.settings');
        $this->verifyCsrf();
        $y=(int)Request::input('year_be',0);
        if($y<2500||$y>2700) $this->back('settings','error','ปี พ.ศ. ไม่ถูกต้อง');
        $id=$this->m->yearAdd($y);
        AuditLog::record(Auth::id(),'create','academic_years',$id);
        $this->back('settings','success','เพิ่มปีการศึกษา '.$y.' (พร้อม 2 ภาคเรียน) แล้ว');
    }
    public function yearCurrent(string $id): void
    {
        $this->authorize('system.settings');
        $this->verifyCsrf();
        $this->m->yearSetCurrent((int)$id);
        AuditLog::record(Auth::id(),'update','academic_years',(int)$id,null,['set'=>'current']);
        $this->back('settings','success','ตั้งเป็นปีการศึกษาปัจจุบันแล้ว');
    }
    public function semesterCurrent(string $id): void
    {
        $this->authorize('system.settings');
        $this->verifyCsrf();
        $this->m->semesterSetCurrent((int)$id);
        AuditLog::record(Auth::id(),'update','semesters',(int)$id,null,['set'=>'current']);
        $this->back('settings','success','ตั้งเป็นภาคเรียนปัจจุบันแล้ว');
    }
    public function yearDelete(string $id): void
    {
        $this->authorize('system.settings');
        $this->verifyCsrf();
        $ok=$this->m->yearDelete((int)$id);
        AuditLog::record(Auth::id(),'delete','academic_years',(int)$id);
        $this->back('settings', $ok?'success':'error', $ok?'ลบปีการศึกษาแล้ว':'ลบไม่ได้ — เป็นปีปัจจุบันหรือมีการใช้งานแล้ว');
    }
}
