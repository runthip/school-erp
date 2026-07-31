<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Models\Role;
use App\Models\Permission;
use App\Models\AuditLog;

class RoleController extends Controller
{
    private Role $roles;
    private Permission $perms;

    public function __construct()
    {
        $this->roles = new Role();
        $this->perms = new Permission();
    }

    public function index(): void
    {
        $this->authorize('role.manage');
        $this->view('roles/index', [
            'title' => 'บทบาทและสิทธิ์',
            'rows'  => $this->roles->allWithCounts(),
        ]);
    }

    public function create(): void
    {
        $this->authorize('role.manage');
        $this->view('roles/create', [
            'title'    => 'เพิ่มบทบาท',
            'grouped'  => $this->perms->grouped(),
            'selected' => [],
        ]);
    }

    public function store(): void
    {
        $this->authorize('role.manage');
        $this->verifyCsrf();
        $d = Request::only(['code', 'name', 'description']);
        $permIds = array_map('intval', (array) Request::input('permissions', []));

        $code = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', (string) $d['code'])));
        if ($code === '' || trim((string) $d['name']) === '') {
            $this->back('roles/create', 'error', 'กรุณากรอกรหัสและชื่อบทบาท');
        }
        if ($this->roles->codeExists($code)) {
            $this->back('roles/create', 'error', 'รหัสบทบาทนี้ถูกใช้แล้ว');
        }

        $id = $this->roles->create(['code' => $code, 'name' => $d['name'], 'description' => $d['description']]);
        $this->roles->syncPermissions($id, $permIds);
        AuditLog::record(Auth::id(), 'create', 'roles', $id, null, ['code' => $code]);
        $this->back('roles', 'success', 'เพิ่มบทบาทเรียบร้อยแล้ว');
    }

    public function edit(string $id): void
    {
        $this->authorize('role.manage');
        $role = $this->roles->find((int) $id);
        if (!$role) $this->back('roles', 'error', 'ไม่พบบทบาท');

        $this->view('roles/edit', [
            'title'    => 'แก้ไขบทบาท',
            'role'     => $role,
            'grouped'  => $this->perms->grouped(),
            'selected' => $this->roles->permissionIds((int) $id),
        ]);
    }

    public function update(string $id): void
    {
        $this->authorize('role.manage');
        $this->verifyCsrf();
        $rid  = (int) $id;
        $role = $this->roles->find($rid);
        if (!$role) $this->back('roles', 'error', 'ไม่พบบทบาท');

        $d = Request::only(['name', 'description']);
        $permIds = array_map('intval', (array) Request::input('permissions', []));
        if (trim((string) $d['name']) === '') {
            $this->back("roles/{$rid}/edit", 'error', 'กรุณากรอกชื่อบทบาท');
        }

        $this->roles->update($rid, ['name' => $d['name'], 'description' => $d['description']]);
        $this->roles->syncPermissions($rid, $permIds);
        AuditLog::record(Auth::id(), 'update', 'roles', $rid, null, ['name' => $d['name']]);
        $this->back('roles', 'success', 'บันทึกบทบาทเรียบร้อยแล้ว');
    }

    public function destroy(string $id): void
    {
        $this->authorize('role.manage');
        $this->verifyCsrf();
        $rid  = (int) $id;
        $role = $this->roles->find($rid);
        if (!$role) $this->back('roles', 'error', 'ไม่พบบทบาท');
        if ((int) $role['is_system'] === 1) {
            $this->back('roles', 'error', 'ไม่สามารถลบบทบาทของระบบได้');
        }
        $this->roles->delete($rid);
        AuditLog::record(Auth::id(), 'delete', 'roles', $rid);
        $this->back('roles', 'success', 'ลบบทบาทเรียบร้อยแล้ว');
    }
}
