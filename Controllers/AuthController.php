<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Core\Mailer;
use App\Core\Tenant;
use App\Models\User;
use App\Models\PasswordReset;
use App\Models\AuditLog;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) $this->redirect('dashboard');

        // ปุ่มเข้าสู่ระบบด่วน (บัญชีสาธิต) — แสดงเฉพาะโหมดพัฒนาเท่านั้น
        // ห้ามเปิดบนเครื่องจริง เพราะเปิดเผยรหัสผ่านบนหน้าเว็บ
        $accounts = [];
        $demoLogin = ($GLOBALS['config']['app']['env'] ?? 'production') !== 'production'
                  && filter_var($GLOBALS['config']['app']['demo_login'] ?? false, FILTER_VALIDATE_BOOL);
        if ($demoLogin) {
            try {
                $pdo = \App\Core\Database::pdo();
                $rows = $pdo->query("SELECT code, name, is_system FROM roles ORDER BY is_system DESC, id ASC")->fetchAll();
                foreach ($rows as $r) {
                    $isSuper = $r['code'] === 'super_admin';
                    $accounts[] = [
                        'name'     => $r['name'],
                        'username' => $isSuper ? 'admin' : $r['code'],
                        'password' => $isSuper ? 'Admin@123' : 'Demo@1234',
                    ];
                }
            } catch (\Throwable $e) { /* ฐานข้อมูลอาจยังไม่ seed */ }
        }

        $this->view('auth/login', [
            'title'      => 'เข้าสู่ระบบ',
            'accounts'   => $accounts,
            'multi'      => Tenant::enabled(),
            'old_school' => (string) (Session::flash('old_school') ?? Session::get('tenant_code') ?? ''),
        ], 'auth');
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $username = trim((string) Request::input('username'));
        $password = (string) Request::input('password');
        $school   = trim((string) Request::input('school_code'));

        // ---- ระบบหลายโรงเรียน: ต้องระบุรหัสโรงเรียน แล้วสลับไปฐานข้อมูลของโรงเรียนนั้นก่อน ----
        if (Tenant::enabled()) {
            $fail = function (string $msg) use ($username, $school) {
                Session::flash('old_username', $username);
                Session::flash('old_school', $school);
                $this->back('login', 'error', $msg);
            };
            if ($school === '')  $fail('กรุณากรอกรหัสโรงเรียน');
            $tenant = Tenant::findByCode($school);
            if (!$tenant)        $fail('ไม่พบรหัสโรงเรียนนี้ในระบบ — โปรดตรวจสอบอีกครั้ง');
            if (($tenant['status'] ?? '') !== 'active')
                                 $fail('โรงเรียนนี้ถูกระงับการใช้งานชั่วคราว — โปรดติดต่อผู้ดูแลระบบส่วนกลาง');
            if (!Tenant::use($tenant))
                                 $fail('เชื่อมต่อฐานข้อมูลของโรงเรียนนี้ไม่สำเร็จ — โปรดติดต่อผู้ดูแลระบบส่วนกลาง');
        }

        if ($username === '' || $password === '') {
            $this->back('login', 'error', 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
        }

        $config = $GLOBALS['config'];
        [$ok, $msg] = Auth::attempt($username, $password, $config['security']);

        if (!$ok) {
            Session::flash('old_username', $username);
            if ($school !== '') Session::flash('old_school', $school);
            $this->back('login', 'error', $msg);
        }
        $this->back('dashboard', 'success', 'ยินดีต้อนรับ ' . (Auth::user()['full_name'] ?? ''));
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        $supervising = Tenant::isSupervising();
        Auth::logout();
        Tenant::endSupervise();
        Tenant::forget();
        // Super admin ที่เข้าดูแลโรงเรียนอยู่ → กลับไปหน้าศูนย์ควบคุม
        if ($supervising) $this->back('platform/tenants', 'success', 'ออกจากระบบของโรงเรียนแล้ว');
        $this->back('login', 'success', 'ออกจากระบบเรียบร้อยแล้ว');
    }

    // ================= ลืมรหัสผ่าน (รีเซตด้วยตนเองผ่านอีเมล) =================
    public function showForgot(): void
    {
        if (Auth::check()) $this->redirect('dashboard');
        $this->view('auth/forgot', [
            'title'      => 'ลืมรหัสผ่าน',
            'multi'      => Tenant::enabled(),
            'old_school' => (string) (Session::flash('old_school') ?? Session::get('tenant_code') ?? ''),
        ], 'auth');
    }

    /** รับอีเมล → ถ้าพบผู้ใช้ ส่งลิงก์รีเซตไปที่อีเมลนั้น (ตอบข้อความกลางๆ เสมอ กันเดาบัญชี) */
    public function sendReset(): void
    {
        $this->verifyCsrf();
        $email = trim((string) Request::input('email'));
        $generic = 'หากอีเมลนี้มีอยู่ในระบบ เราได้ส่งลิงก์รีเซตรหัสผ่านไปให้แล้ว — โปรดตรวจสอบกล่องจดหมาย (และ Junk/Spam)';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->back('forgot', 'error', 'กรุณากรอกอีเมลให้ถูกต้อง');
        }

        // ระบบหลายโรงเรียน: ต้องระบุรหัสโรงเรียนเพื่อค้นหาบัญชีในฐานข้อมูลที่ถูกต้อง
        if (Tenant::enabled()) {
            $school = trim((string) Request::input('school_code'));
            $t = $school !== '' ? Tenant::findByCode($school) : null;
            if (!$t || $t['status'] !== 'active' || !Tenant::use($t)) {
                Session::flash('old_school', $school);
                $this->back('forgot', 'error', 'ไม่พบรหัสโรงเรียนนี้ หรือโรงเรียนถูกระงับการใช้งาน');
            }
        }
        $user = (new User())->findByEmail($email);
        if ($user && ($user['status'] ?? '') !== 'inactive') {
            $this->issueResetLink($user, 'self');
        }
        // ตอบเหมือนกันทุกกรณี (ไม่บอกว่าอีเมลมีจริงหรือไม่)
        $this->back('login', 'success', $generic);
    }

    /** ลิงก์รีเซตของระบบหลายโรงเรียน: /reset/{รหัสโรงเรียน}/{token} */
    public function showResetTenant(string $code, string $token): void
    {
        $this->useTenantOrFail($code);
        $this->showReset($token, $code);
    }

    public function doResetTenant(string $code, string $token): void
    {
        $this->useTenantOrFail($code);
        $this->doReset($token, $code);
    }

    private function useTenantOrFail(string $code): void
    {
        $t = Tenant::enabled() ? Tenant::findByCode($code) : null;
        if (!$t || $t['status'] !== 'active' || !Tenant::use($t)) {
            $this->back('forgot', 'error', 'ลิงก์รีเซตไม่ถูกต้อง หรือโรงเรียนถูกระงับการใช้งาน');
        }
    }

    /** หน้าตั้งรหัสผ่านใหม่จากลิงก์ (ตรวจโทเคน) */
    public function showReset(string $token, string $code = ''): void
    {
        if (Tenant::enabled() && !Tenant::current()) {
            $this->back('forgot', 'error', 'โปรดใช้ลิงก์รีเซตจากอีเมลของท่าน (ลิงก์ต้องมีรหัสโรงเรียน)');
        }
        $row = (new PasswordReset())->findValid($token);
        if (!$row) $this->back('forgot', 'error', 'ลิงก์รีเซตไม่ถูกต้องหรือหมดอายุแล้ว — โปรดขอลิงก์ใหม่');
        $path = $code !== '' ? "reset/{$code}/{$token}" : "reset/{$token}";
        $this->view('auth/reset', ['title' => 'ตั้งรหัสผ่านใหม่', 'token' => $token, 'action' => $path], 'auth');
    }

    public function doReset(string $token, string $code = ''): void
    {
        $this->verifyCsrf();
        $path = $code !== '' ? "reset/{$code}/{$token}" : "reset/{$token}";
        $pr  = new PasswordReset();
        $row = $pr->findValid($token);
        if (!$row) $this->back('forgot', 'error', 'ลิงก์รีเซตไม่ถูกต้องหรือหมดอายุแล้ว — โปรดขอลิงก์ใหม่');

        $new     = (string) Request::input('new_password');
        $confirm = (string) Request::input('confirm_password');
        if (strlen($new) < 8) $this->back($path, 'error', 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร');
        if ($new !== $confirm) $this->back($path, 'error', 'รหัสผ่านยืนยันไม่ตรงกัน');

        $users = new User();
        $uid   = (int) $row['user_id'];
        $users->updatePassword($uid, password_hash($new, PASSWORD_BCRYPT)); // ล้างธง must_change ด้วย
        $users->unlock($uid);          // ปลดล็อก + รีเซ็ตจำนวนพยายามล็อกอิน
        $pr->markUsed((int) $row['id']);
        AuditLog::record($uid, 'reset_password', 'users', $uid, null, ['via' => 'email_link']);
        $this->back('login', 'success', 'ตั้งรหัสผ่านใหม่เรียบร้อยแล้ว — เข้าสู่ระบบด้วยรหัสผ่านใหม่ได้เลย');
    }

    /** สร้างโทเคน + ส่งอีเมลลิงก์รีเซต (ใช้ทั้ง self-service และแอดมินสั่งรีเซต) */
    public static function issueResetLink(array $user, string $by = 'self'): bool
    {
        $ttl   = (int) ($GLOBALS['config']['security']['reset_ttl_minutes'] ?? 60);
        $token = (new PasswordReset())->create((int) $user['id'], $ttl);
        // ระบบหลายโรงเรียน: ใส่รหัสโรงเรียนในลิงก์ เพื่อให้เปิดจากเครื่องใดก็ชี้ฐานข้อมูลถูกต้อง
        $code  = Tenant::code();
        $link  = absolute_url($code ? "reset/{$code}/{$token}" : "reset/{$token}");
        $app   = $GLOBALS['config']['app']['name'] ?? 'School ERP';
        $name  = $user['full_name'] ?: $user['username'];
        $html  = '<div style="font-family:Tahoma,Arial,sans-serif;font-size:15px;color:#222;line-height:1.7">'
            . '<p>เรียน คุณ' . htmlspecialchars($name) . '</p>'
            . '<p>มีการขอรีเซตรหัสผ่านสำหรับบัญชี <b>' . htmlspecialchars($user['username']) . '</b> ในระบบ ' . htmlspecialchars($app) . '</p>'
            . '<p>กดปุ่มด้านล่างเพื่อตั้งรหัสผ่านใหม่ (ลิงก์ใช้ได้ ' . $ttl . ' นาที และใช้ได้ครั้งเดียว):</p>'
            . '<p><a href="' . htmlspecialchars($link) . '" style="display:inline-block;background:#1f2937;color:#fff;'
            . 'padding:10px 22px;border-radius:8px;text-decoration:none">ตั้งรหัสผ่านใหม่</a></p>'
            . '<p style="font-size:12px;color:#666">หรือคัดลอกลิงก์นี้ไปวางในเบราว์เซอร์:<br>' . htmlspecialchars($link) . '</p>'
            . '<hr style="border:none;border-top:1px solid #eee">'
            . '<p style="font-size:12px;color:#888">หากท่านไม่ได้ร้องขอ โปรดเพิกเฉยต่ออีเมลนี้ รหัสผ่านเดิมของท่านจะยังใช้ได้ตามปกติ</p>'
            . '</div>';
        AuditLog::record((int) $user['id'], 'request_password_reset', 'users', (int) $user['id'], null, ['by' => $by]);
        return Mailer::send((string) $user['email'], 'รีเซตรหัสผ่าน · ' . $app, $html);
    }
}
