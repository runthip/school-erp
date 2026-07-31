<?php
namespace App\Middleware;

use App\Core\Auth;
use App\Core\Session;

/**
 * บังคับให้ล็อกอินก่อนเข้าถึง
 */
final class AuthMiddleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            Session::flash('error', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
            header('Location: ' . base_url('login'));
            exit;
        }

        // บังคับตั้งรหัสผ่านใหม่ ถ้าถูกแอดมินรีเซตเป็นรหัสผ่านเริ่มต้น
        // ผู้ดูแลส่วนกลางที่เข้ามาดูแลระบบ ไม่ต้องถูกบังคับเปลี่ยนรหัสของแอดมินโรงเรียน
        $u = Auth::user();
        if ($u && !empty($u['must_change_password']) && !\App\Core\Tenant::isSupervising()) {
            $base = rtrim($GLOBALS['config']['app']['base_url'] ?? '', '/');
            $uri  = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
            $path = trim(substr($uri, strlen($base)), '/');
            // อนุญาตเฉพาะหน้าโปรไฟล์ (เปลี่ยนรหัส) และออกจากระบบ เพื่อไม่ให้วนลูป
            if (!in_array($path, ['profile', 'profile/password', 'logout'], true)) {
                Session::flash('error', 'กรุณาตั้งรหัสผ่านใหม่ก่อนใช้งาน (บัญชีถูกรีเซตเป็นรหัสผ่านเริ่มต้น)');
                header('Location: ' . base_url('profile'));
                exit;
            }
        }
    }
}
