<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;

class UserController extends Controller
{
    private User $users;
    private Role $roles;

    public function __construct()
    {
        $this->users = new User();
        $this->roles = new Role();
    }

    public function index(): void
    {
        $this->authorize('user.manage');
        $search = trim((string) Request::input('q', ''));
        $status = (string) Request::input('status', '');
        $classroom = (int) Request::input('classroom', 0);
        $grade  = (int) Request::input('grade', 0);
        $role   = (int) Request::input('role', 0);
        $page   = max(1, (int) Request::input('page', 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $rows  = $this->users->paginate($search, $status, $limit, $offset, $classroom, $grade, $role);
        $total = $this->users->countAll($search, $status, $classroom, $grade, $role);

        $this->view('users/index', [
            'title'     => 'จัดการผู้ใช้งาน',
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'pages'     => max(1, (int) ceil($total / $limit)),
            'search'    => $search,
            'status'    => $status,
            'classroom' => $classroom,
            'grade'     => $grade,
            'role'      => $role,
            'roles'     => $this->users->rolesForFilter(),
            'classrooms'=> $this->users->classroomsForFilter(),
            'grades'    => $this->users->gradesForFilter(),
        ]);
    }

    public function create(): void
    {
        $this->authorize('user.manage');
        $this->view('users/create', [
            'title'    => 'เพิ่มผู้ใช้งาน',
            'allRoles' => $this->roles->allWithCounts(),
        ]);
    }

    public function store(): void
    {
        $this->authorize('user.manage');
        $this->verifyCsrf();
        $d = Request::only(['username', 'full_name', 'email', 'phone', 'password', 'status']);
        $roleIds = (array) Request::input('roles', []);

        $errors = $this->validate($d, null);
        if ($errors) {
            \App\Core\Session::flash('error', implode(' · ', $errors));
            $this->redirect('users/create');
        }

        $id = $this->users->create([
            'username'      => $d['username'],
            'email'         => $d['email'],
            'phone'         => $d['phone'],
            'full_name'     => $d['full_name'],
            'status'        => in_array($d['status'], ['active','inactive','suspended'], true) ? $d['status'] : 'active',
            'password_hash' => password_hash($d['password'], PASSWORD_BCRYPT),
            'created_by'    => Auth::id(),
        ]);
        $this->users->syncRoles($id, array_map('intval', $roleIds));

        AuditLog::record(Auth::id(), 'create', 'users', $id, null, ['username' => $d['username']]);
        $this->back('users', 'success', 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว');
    }

    /** ฟอร์มเพิ่มผู้ใช้ทีละหลายคน */
    public function createBulk(): void
    {
        $this->authorize('user.manage');
        $this->view('users/bulk', [
            'title'    => 'เพิ่มผู้ใช้งานหลายคน',
            'allRoles' => $this->roles->allWithCounts(),
        ]);
    }

    /** บันทึกผู้ใช้ทีละหลายคนในครั้งเดียว */
    public function storeBulk(): void
    {
        $this->authorize('user.manage');
        $this->verifyCsrf();
        $usernames = array_values((array) Request::input('username', []));
        $names     = array_values((array) Request::input('full_name', []));
        $emails    = array_values((array) Request::input('email', []));

        // ── นำเข้าจากไฟล์ Excel/CSV (ต่อท้ายแถวที่พิมพ์เอง) ──
        if (!empty($_FILES['import_file']['tmp_name']) && is_uploaded_file($_FILES['import_file']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['import_file']['name'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv','txt','xlsx','xlsm'], true)) {
                $this->back('users/bulk', 'error', 'รองรับเฉพาะไฟล์ .csv หรือ .xlsx');
            }
            foreach ($this->parseImport($_FILES['import_file']['tmp_name'], $ext) as $r) {
                $usernames[] = $r[0]; $names[] = $r[1]; $emails[] = $r[2];
            }
        }

        $roleIds   = array_map('intval', (array) Request::input('roles', []));
        $status    = (string) Request::input('status', 'active');
        $status    = in_array($status, ['active','inactive','suspended'], true) ? $status : 'active';
        $defPass   = (string) Request::input('default_password', '');

        if (strlen($defPass) < 8) {
            $this->back('users/bulk', 'error', 'รหัสผ่านเริ่มต้นต้องยาวอย่างน้อย 8 ตัวอักษร');
        }

        $created = 0; $skipped = [];
        $seen = [];
        foreach ($usernames as $i => $uname) {
            $uname = trim((string) $uname);
            $fname = trim((string) ($names[$i] ?? ''));
            if ($uname === '' && $fname === '') continue;            // แถวว่าง — ข้าม
            if ($uname === '' || $fname === '') { $skipped[] = ($uname ?: '(ไม่มีชื่อผู้ใช้)').' — กรอกไม่ครบ'; continue; }
            $email = trim((string) ($emails[$i] ?? ''));
            if (isset($seen[$uname]) || $this->users->usernameExists($uname)) { $skipped[] = $uname.' — ชื่อผู้ใช้ซ้ำ'; continue; }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped[] = $uname.' — อีเมลไม่ถูกต้อง'; continue; }
            $seen[$uname] = true;

            $id = $this->users->create([
                'username'      => $uname,
                'email'         => $email,
                'phone'         => null,
                'full_name'     => $fname,
                'status'        => $status,
                'password_hash' => password_hash($defPass, PASSWORD_BCRYPT),
                'created_by'    => Auth::id(),
            ]);
            if ($roleIds) $this->users->syncRoles($id, $roleIds);
            AuditLog::record(Auth::id(), 'create', 'users', $id, null, ['username' => $uname, 'bulk' => true]);
            $created++;
        }

        if ($created === 0 && $skipped) {
            $this->back('users/bulk', 'error', 'ไม่ได้เพิ่มบัญชีใด — '.implode(' · ', array_slice($skipped, 0, 8)));
        }
        $msg = "เพิ่มผู้ใช้งาน {$created} บัญชีเรียบร้อยแล้ว";
        if ($skipped) $msg .= ' · ข้าม '.count($skipped).' รายการ ('.implode(', ', array_slice($skipped, 0, 5)).(count($skipped)>5?'…':'').')';
        $this->back('users', 'success', $msg);
    }

    /** อ่านไฟล์ import → คืน [[username, full_name, email], ...] (ตัด header ถ้ามี) */
    private function parseImport(string $tmpPath, string $ext): array
    {
        $rows = \App\Core\SpreadsheetReader::rows($tmpPath, $ext);
        if (!$rows) return [];
        // แมปคอลัมน์: ตรวจ header ถ้าแถวแรกมีคำว่า username/ชื่อผู้ใช้
        $iU = 0; $iN = 1; $iE = 2;
        $first = array_map(fn($v) => mb_strtolower(trim((string)$v)), $rows[0]);
        $isHeader = false;
        foreach ($first as $idx => $cell) {
            if ($cell === 'username' || str_contains($cell, 'ชื่อผู้ใช้') || $cell === 'user') { $iU = $idx; $isHeader = true; }
            if ($cell === 'full_name' || $cell === 'name' || str_contains($cell, 'ชื่อ-นามสกุล') || str_contains($cell, 'ชื่อสกุล') || str_contains($cell, 'ชื่อ นามสกุล')) { $iN = $idx; $isHeader = true; }
            if ($cell === 'email' || str_contains($cell, 'อีเมล')) { $iE = $idx; $isHeader = true; }
        }
        if ($isHeader) array_shift($rows);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [ trim((string)($r[$iU] ?? '')), trim((string)($r[$iN] ?? '')), trim((string)($r[$iE] ?? '')) ];
        }
        return $out;
    }

    /** ดาวน์โหลดไฟล์ตัวอย่าง (CSV) สำหรับนำเข้า */
    public function importTemplate(): void
    {
        $this->authorize('user.manage');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="user_import_template.csv"');
        echo "\xEF\xBB\xBF";                       // BOM ให้ Excel อ่านภาษาไทยถูก
        $out = fopen('php://output', 'w');
        fputcsv($out, ['username', 'full_name', 'email']);
        fputcsv($out, ['somchai01', 'เด็กชายสมชาย ใจดี', 'somchai@school.ac.th']);
        fputcsv($out, ['sompong02', 'เด็กหญิงสมปอง รักเรียน', '']);
        fclose($out);
        exit;
    }

    public function show(string $id): void
    {
        $this->authorize('user.manage');
        $user = $this->users->find((int) $id);
        if (!$user) $this->back('users', 'error', 'ไม่พบผู้ใช้งาน');

        $this->view('users/show', [
            'title'        => 'ข้อมูลผู้ใช้งาน',
            'user'         => $user,
            'userRoles'    => $this->users->roleCodes((int) $id),
            'recentLogins' => $this->users->recentLogins((int) $id, 8),
        ]);
    }

    public function edit(string $id): void
    {
        $this->authorize('user.manage');
        $user = $this->users->find((int) $id);
        if (!$user) $this->back('users', 'error', 'ไม่พบผู้ใช้งาน');

        $this->view('users/edit', [
            'title'       => 'แก้ไขผู้ใช้งาน',
            'user'        => $user,
            'allRoles'    => $this->roles->allWithCounts(),
            'userRoleIds' => $this->users->roleIds((int) $id),
        ]);
    }

    public function update(string $id): void
    {
        $this->authorize('user.manage');
        $this->verifyCsrf();
        $uid  = (int) $id;
        $user = $this->users->find($uid);
        if (!$user) $this->back('users', 'error', 'ไม่พบผู้ใช้งาน');

        // หมายเหตุความปลอดภัย: แอดมินตั้ง/แก้รหัสผ่านของผู้อื่นโดยตรงไม่ได้
        // รีเซตได้ทางเดียวคือ "ส่งลิงก์รีเซต" ไปอีเมลผู้ใช้ (ดู resetPassword)
        $d = Request::only(['full_name', 'email', 'phone', 'status']);
        $roleIds = (array) Request::input('roles', []);

        if (trim((string) $d['full_name']) === '') {
            $this->back("users/{$uid}/edit", 'error', 'กรุณากรอกชื่อ-นามสกุล');
        }

        $this->users->update($uid, [
            'full_name'  => $d['full_name'],
            'email'      => $d['email'],
            'phone'      => $d['phone'],
            'status'     => in_array($d['status'], ['active','inactive','suspended','locked'], true) ? $d['status'] : 'active',
            'updated_by' => Auth::id(),
        ]);

        $this->users->syncRoles($uid, array_map('intval', $roleIds));
        AuditLog::record(Auth::id(), 'update', 'users', $uid, null, ['full_name' => $d['full_name']]);
        $this->back('users', 'success', 'บันทึกการแก้ไขเรียบร้อยแล้ว');
    }

    public function destroy(string $id): void
    {
        $this->authorize('user.manage');
        $this->verifyCsrf();
        $uid = (int) $id;

        if ($uid === Auth::id()) {
            $this->back('users', 'error', 'ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้');
        }
        $this->users->softDelete($uid);
        AuditLog::record(Auth::id(), 'delete', 'users', $uid);
        $this->back('users', 'success', 'ลบผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function unlock(string $id): void
    {
        $this->authorize('user.manage');
        $this->verifyCsrf();
        $this->users->unlock((int) $id);
        AuditLog::record(Auth::id(), 'unlock', 'users', (int) $id);
        $this->back('users', 'success', 'ปลดล็อกบัญชีเรียบร้อยแล้ว');
    }

    /**
     * แอดมินสั่งรีเซตรหัสผ่าน (กรณีผู้ใช้ลืมรหัส) → ตั้งเป็น "รหัสผ่านเริ่มต้น"
     * ผู้ใช้ล็อกอินด้วยรหัสเริ่มต้นแล้วถูกบังคับให้ตั้งรหัสใหม่ทันที
     * แอดมินไม่ได้ตั้งรหัสผ่านเอง (ใช้ค่าเริ่มต้นของระบบ) และไม่เห็นรหัสจริงของผู้ใช้
     */
    public function resetPassword(string $id): void
    {
        $this->authorize('user.manage');
        $this->verifyCsrf();
        $user = $this->users->find((int) $id);
        if (!$user) $this->back('users', 'error', 'ไม่พบผู้ใช้งาน');

        $default = (string) ($GLOBALS['config']['security']['default_password'] ?? 'Reset@1234');
        $this->users->resetToDefault((int) $id, password_hash($default, PASSWORD_BCRYPT));
        AuditLog::record(Auth::id(), 'admin_reset_password', 'users', (int) $id, null, ['to' => 'default']);
        $this->back("users/{$id}/edit", 'success',
            'รีเซตเป็นรหัสผ่านเริ่มต้นแล้ว — แจ้งผู้ใช้ให้เข้าสู่ระบบด้วยรหัส "' . $default
            . '" แล้วระบบจะบังคับให้ตั้งรหัสผ่านใหม่ทันที');
    }

    private function validate(array $d, ?int $exceptId): array
    {
        $errors = [];
        if (trim((string) $d['username']) === '') $errors[] = 'กรุณากรอกชื่อผู้ใช้';
        elseif ($this->users->usernameExists($d['username'], $exceptId)) $errors[] = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว';
        if (trim((string) $d['full_name']) === '') $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
        if (strlen((string) $d['password']) < 8) $errors[] = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
        if (!empty($d['email']) && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'อีเมลไม่ถูกต้อง';
        return $errors;
    }
}
