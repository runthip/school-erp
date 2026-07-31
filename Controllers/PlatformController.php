<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Tenant;
use App\Models\PlatformAdmin;
use App\Models\TenantRegistry;

/**
 * ศูนย์ควบคุมส่วนกลาง (Super admin ของผู้ให้บริการ)
 *  - ทะเบียนโรงเรียน: เปิดใช้งาน / ระงับ / แก้ข้อมูลติดต่อ
 *  - รีเซตรหัสผ่านให้แอดมินของโรงเรียน (ตั้งใหม่ได้เท่านั้น ดูรหัสเดิมไม่ได้)
 *  - "เข้าดูแลระบบ" ของโรงเรียนผ่านบัญชีแอดมินของโรงเรียนนั้น (บันทึกประวัติทุกครั้ง)
 */
class PlatformController extends Controller
{
    private function reg(): TenantRegistry { return new TenantRegistry(); }
    private function me(): array { return PlatformAdmin::current() ?? []; }

    private function requireCentral(): void
    {
        if (!Database::hasCentral()) {
            http_response_code(404);
            exit('ระบบนี้ไม่ได้เปิดใช้งานศูนย์ควบคุมส่วนกลาง');
        }
    }

    // ---------------- เข้าสู่ระบบส่วนกลาง ----------------
    public function showLogin(): void
    {
        $this->requireCentral();
        if (PlatformAdmin::check()) $this->redirect('platform/tenants');
        $this->view('platform/login', ['title' => 'ผู้ดูแลระบบส่วนกลาง'], 'auth');
    }

    public function login(): void
    {
        $this->requireCentral();
        $this->verifyCsrf();
        $u = trim((string) Request::input('username'));
        $p = (string) Request::input('password');
        if ($u === '' || $p === '') $this->back('platform/login', 'error', 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');

        $max = (int) ($GLOBALS['config']['security']['max_login_attempts'] ?? 5);
        [$ok, $msg] = (new PlatformAdmin())->attempt($u, $p, $max);
        if (!$ok) $this->back('platform/login', 'error', $msg);
        $this->back('platform/tenants', 'success', 'ยินดีต้อนรับสู่ศูนย์ควบคุมส่วนกลาง');
    }

    public function logout(): void
    {
        $this->verifyCsrf();
        Session::forget('platform_admin_id');
        Tenant::endSupervise();
        Tenant::forget();
        Session::forget('user_id');
        $this->back('platform/login', 'success', 'ออกจากระบบส่วนกลางแล้ว');
    }

    // ---------------- ทะเบียนโรงเรียน ----------------
    public function index(): void
    {
        $q = trim((string) Request::input('q'));
        $tenants = $this->reg()->all($q);
        $this->view('platform/tenants', [
            'title'   => 'ทะเบียนโรงเรียน',
            'tenants' => $tenants,
            'q'       => $q,
            'me'      => $this->me(),
        ], 'platform');
    }

    public function create(): void
    {
        $this->view('platform/tenant_new', [
            'title'  => 'เปิดใช้งานโรงเรียนใหม่',
            'me'     => $this->me(),
            'prefix' => $GLOBALS['config']['central']['db_prefix'],
        ], 'platform');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $me = $this->me();
        $d = [
            'school_code'   => trim((string) Request::input('school_code')),
            'name_th'       => trim((string) Request::input('name_th')),
            'db_name'       => trim((string) Request::input('db_name')),
            'affiliation'   => trim((string) Request::input('affiliation')),
            'province'      => trim((string) Request::input('province')),
            'contact_name'  => trim((string) Request::input('contact_name')),
            'contact_phone' => trim((string) Request::input('contact_phone')),
            'contact_email' => trim((string) Request::input('contact_email')),
            'note'          => trim((string) Request::input('note')),
            'admin_user'    => trim((string) Request::input('admin_user')) ?: 'admin',
            'admin_name'    => trim((string) Request::input('admin_name')),
            'admin_email'   => trim((string) Request::input('admin_email')),
            'admin_pass'    => (string) Request::input('admin_pass'),
            'created_by'    => $me['id'] ?? null,
        ];
        if ($d['name_th'] === '') $this->back('platform/tenants/new', 'error', 'กรุณากรอกชื่อโรงเรียน');

        $res = $this->reg()->provision($d);
        if (!$res['ok']) $this->back('platform/tenants/new', 'error', $res['message']);

        Tenant::log((int) $res['id'], $d['school_code'], 'provision',
                    $me['id'] ?? null, $me['username'] ?? null, 'ฐานข้อมูล ' . $res['db']);
        $this->back('platform/tenants/' . $res['id'], 'success', $res['message']);
    }

    public function show(string $id): void
    {
        $t = $this->reg()->find((int) $id);
        if (!$t) $this->back('platform/tenants', 'error', 'ไม่พบโรงเรียนนี้');
        $this->view('platform/tenant_show', [
            'title' => $t['name_th'],
            'me'    => $this->me(),
            't'     => $t,
            'stats' => $this->reg()->stats($t),
            'logs'  => $this->reg()->logs((int) $t['id'], 50),
        ], 'platform');
    }

    public function updateInfo(string $id): void
    {
        $this->verifyCsrf();
        $t = $this->reg()->find((int) $id);
        if (!$t) $this->back('platform/tenants', 'error', 'ไม่พบโรงเรียนนี้');
        $this->reg()->updateInfo((int) $id, [
            'name_th'       => trim((string) Request::input('name_th')) ?: $t['name_th'],
            'affiliation'   => trim((string) Request::input('affiliation')) ?: null,
            'province'      => trim((string) Request::input('province')) ?: null,
            'contact_name'  => trim((string) Request::input('contact_name')) ?: null,
            'contact_phone' => trim((string) Request::input('contact_phone')) ?: null,
            'contact_email' => trim((string) Request::input('contact_email')) ?: null,
            'note'          => trim((string) Request::input('note')) ?: null,
        ]);
        $this->back('platform/tenants/' . $id, 'success', 'บันทึกข้อมูลโรงเรียนแล้ว');
    }

    public function setStatus(string $id): void
    {
        $this->verifyCsrf();
        $t = $this->reg()->find((int) $id);
        if (!$t) $this->back('platform/tenants', 'error', 'ไม่พบโรงเรียนนี้');
        $to = Request::input('status') === 'suspended' ? 'suspended' : 'active';
        $this->reg()->setStatus((int) $id, $to);
        $me = $this->me();
        Tenant::log((int) $t['id'], $t['school_code'], $to === 'suspended' ? 'suspend' : 'activate',
                    $me['id'] ?? null, $me['username'] ?? null);
        $this->back('platform/tenants/' . $id, 'success',
            $to === 'suspended' ? 'ระงับการใช้งานโรงเรียนนี้แล้ว' : 'เปิดใช้งานโรงเรียนนี้อีกครั้งแล้ว');
    }

    /** ตั้งรหัสผ่านเริ่มต้นให้แอดมินของโรงเรียน (ดูรหัสผ่านเดิมของใครไม่ได้) */
    public function resetAdmin(string $id): void
    {
        $this->verifyCsrf();
        $t = $this->reg()->find((int) $id);
        if (!$t) $this->back('platform/tenants', 'error', 'ไม่พบโรงเรียนนี้');

        $new = (string) Request::input('new_password');
        if ($new === '') $new = (string) ($GLOBALS['config']['security']['default_password'] ?? 'Reset@1234');
        if (strlen($new) < 8) $this->back('platform/tenants/' . $id, 'error', 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร');

        $res = $this->reg()->resetTenantAdmin($t, $new);
        if (!$res['ok']) $this->back('platform/tenants/' . $id, 'error', $res['message']);
        $me = $this->me();
        Tenant::log((int) $t['id'], $t['school_code'], 'reset_admin',
                    $me['id'] ?? null, $me['username'] ?? null, 'บัญชี ' . $t['admin_username']);
        $this->back('platform/tenants/' . $id, 'success', $res['message']);
    }

    // ---------------- เข้าดูแลระบบของโรงเรียน ----------------
    public function enter(string $id): void
    {
        $this->verifyCsrf();
        $me = $this->me();
        $t  = $this->reg()->find((int) $id);
        if (!$t) $this->back('platform/tenants', 'error', 'ไม่พบโรงเรียนนี้');

        // ยืนยันด้วยรหัสผ่านของผู้ดูแลส่วนกลางเอง ก่อนเข้าถึงข้อมูลของโรงเรียน
        $pass = (string) Request::input('confirm_password');
        if (!$pass || !password_verify($pass, $me['password_hash'] ?? '')) {
            $this->back('platform/tenants/' . $id, 'error', 'รหัสผ่านผู้ดูแลส่วนกลางไม่ถูกต้อง');
        }
        if ($t['status'] !== 'active') {
            $this->back('platform/tenants/' . $id, 'error', 'โรงเรียนนี้ถูกระงับอยู่ — เปิดใช้งานก่อนจึงจะเข้าดูแลได้');
        }
        if (!Tenant::use($t)) {
            $this->back('platform/tenants/' . $id, 'error', 'เชื่อมต่อฐานข้อมูลของโรงเรียนนี้ไม่สำเร็จ');
        }

        // เข้าใช้งานผ่านบัญชีแอดมินของโรงเรียนนั้น (แอดมินลูก)
        $st = Database::pdo()->prepare("SELECT id, full_name FROM users WHERE username=? LIMIT 1");
        $st->execute([$t['admin_username']]);
        $u = $st->fetch();
        if (!$u) {
            Tenant::forget();
            $this->back('platform/tenants/' . $id, 'error',
                "ไม่พบบัญชีแอดมิน '{$t['admin_username']}' ในฐานข้อมูลของโรงเรียนนี้");
        }

        Session::set('user_id', (int) $u['id']);
        Tenant::beginSupervise();
        Tenant::log((int) $t['id'], $t['school_code'], 'enter',
                    $me['id'] ?? null, $me['username'] ?? null, 'ผ่านบัญชี ' . $t['admin_username']);
        $this->back('dashboard', 'success',
            'เข้าดูแลระบบของ ' . $t['name_th'] . ' — ทุกการกระทำถูกบันทึกไว้');
    }

    public function exit(): void
    {
        $this->verifyCsrf();
        $me = $this->me();
        $t  = Tenant::current();
        if ($t) {
            Tenant::log((int) $t['id'], $t['school_code'], 'exit', $me['id'] ?? null, $me['username'] ?? null);
        }
        Session::forget('user_id');
        Tenant::endSupervise();
        Tenant::forget();
        $this->back('platform/tenants', 'success', 'ออกจากการดูแลระบบของโรงเรียนแล้ว');
    }

    // ---------------- ประวัติการเข้าดูแล ----------------
    public function logs(): void
    {
        $this->view('platform/logs', [
            'title' => 'ประวัติการเข้าดูแลระบบ',
            'me'    => $this->me(),
            'logs'  => $this->reg()->logs(null, 200),
        ], 'platform');
    }

    // ---------------- รหัสผ่านของตนเอง ----------------
    public function account(): void
    {
        $this->view('platform/account', ['title' => 'บัญชีของฉัน', 'me' => $this->me()], 'platform');
    }

    public function updatePassword(): void
    {
        $this->verifyCsrf();
        $me  = $this->me();
        $cur = (string) Request::input('current_password');
        $new = (string) Request::input('new_password');
        $cf  = (string) Request::input('confirm_password');
        if (!password_verify($cur, $me['password_hash'] ?? '')) {
            $this->back('platform/account', 'error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
        }
        if (strlen($new) < 8)  $this->back('platform/account', 'error', 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร');
        if ($new !== $cf)      $this->back('platform/account', 'error', 'รหัสผ่านยืนยันไม่ตรงกัน');
        (new PlatformAdmin())->updatePassword((int) $me['id'], $new);
        $this->back('platform/account', 'success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }
}
