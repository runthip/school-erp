<?php
namespace App\Middleware;

use App\Core\Auth;
use App\Core\View;

/**
 * ตรวจสิทธิ์เฉพาะ (ใช้ผ่านคลาสลูกที่กำหนด $permission)
 * ตัวอย่างการใช้: สร้างคลาสลูกหรือกำหนดผ่าน route helper
 */
class PermissionMiddleware
{
    protected string $permission = '';

    public function __construct(string $permission = '')
    {
        if ($permission) $this->permission = $permission;
    }

    public function handle(): void
    {
        if (!Auth::check()) {
            header('Location: ' . base_url('login'));
            exit;
        }
        if ($this->permission && !Auth::can($this->permission)) {
            http_response_code(403);
            echo View::render('errors/403', ['title' => 'ไม่มีสิทธิ์'], 'app');
            exit;
        }
    }
}
