<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\User;
use App\Models\AuditLog;

class ProfileController extends Controller
{
    public function index(): void
    {
        $this->view('profile/index', [
            'title'        => 'โปรไฟล์ของฉัน',
            'user'         => Auth::user(),
            'roles'        => Auth::roles(),
            'permissions'  => Auth::permissions(),
            'recentLogins' => (new User())->recentLogins(Auth::id(), 8),
        ]);
    }

    public function updatePassword(): void
    {
        $this->verifyCsrf();
        $current = (string) Request::input('current_password');
        $new     = (string) Request::input('new_password');
        $confirm = (string) Request::input('confirm_password');

        $user = Auth::user();
        if (!password_verify($current, $user['password_hash'])) {
            $this->back('profile', 'error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
        }
        if (strlen($new) < 8) {
            $this->back('profile', 'error', 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร');
        }
        if ($new !== $confirm) {
            $this->back('profile', 'error', 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน');
        }

        (new User())->updatePassword((int) $user['id'], password_hash($new, PASSWORD_BCRYPT));
        AuditLog::record(Auth::id(), 'change_password', 'users', (int) $user['id']);
        $this->back('profile', 'success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }

    /** อัปโหลด/ลบ ลายเซ็นประจำตัว — ใช้แนบแทนลายมือชื่อบนเอกสาร */
    public function signatureSave(): void
    {
        $this->verifyCsrf();
        $m=new User(); $uid=Auth::id();
        $cur=(string)(Auth::user()['signature_path'] ?? '');

        if (Request::input('remove_signature')) {
            $this->deleteAsset($cur); $m->updateSignature($uid, null);
            AuditLog::record($uid,'update','users',$uid,null,['signature'=>'removed']);
            $this->back('profile','success','ลบลายเซ็นแล้ว');
        }
        if (empty($_FILES['signature']['tmp_name']) || !is_uploaded_file($_FILES['signature']['tmp_name'])) {
            $this->back('profile','error','กรุณาเลือกไฟล์รูปลายเซ็น');
        }
        if (($_FILES['signature']['error'] ?? 1)!==UPLOAD_ERR_OK) $this->back('profile','error','อัปโหลดไม่สำเร็จ');
        if (($_FILES['signature']['size'] ?? 0) > 2*1024*1024) $this->back('profile','error','ไฟล์ต้องไม่เกิน 2 MB');
        $ext=strtolower(pathinfo($_FILES['signature']['name'] ?? '', PATHINFO_EXTENSION));
        if(!in_array($ext,['png','jpg','jpeg','gif','webp','svg'],true)) $this->back('profile','error','รองรับเฉพาะไฟล์รูป (png, jpg, gif, webp, svg)');

        $dir=\BASE_PATH.'/public/uploads';
        if(!is_dir($dir)) @mkdir($dir,0775,true);
        $name='signature_'.$uid.'_'.date('YmdHis').'_'.substr(bin2hex(random_bytes(3)),0,6).'.'.$ext;
        if(!move_uploaded_file($_FILES['signature']['tmp_name'], $dir.'/'.$name)) $this->back('profile','error','บันทึกไฟล์ไม่ได้');
        $this->deleteAsset($cur);
        $m->updateSignature($uid, 'uploads/'.$name);
        AuditLog::record($uid,'update','users',$uid,null,['signature'=>'uploaded']);
        $this->back('profile','success','บันทึกลายเซ็นแล้ว — จะถูกแนบแทนลายมือชื่อบนเอกสารที่คุณลงนาม');
    }

    private function deleteAsset(?string $path): void
    {
        if(!$path || !str_starts_with($path,'uploads/')) return;
        $f=\BASE_PATH.'/public/'.$path;
        if(is_file($f)) @unlink($f);
    }
}
