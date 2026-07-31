<?php
namespace App\Middleware;

use App\Core\Database;
use App\Core\Session;
use App\Models\PlatformAdmin;

/**
 * บังคับให้เป็นผู้ดูแลระบบส่วนกลาง (Super admin) เท่านั้น
 */
final class PlatformMiddleware
{
    public function handle(): void
    {
        if (!Database::hasCentral()) {
            http_response_code(404);
            exit('ระบบนี้ไม่ได้เปิดใช้งานศูนย์ควบคุมส่วนกลาง');
        }
        if (!PlatformAdmin::check()) {
            Session::flash('error', 'กรุณาเข้าสู่ระบบผู้ดูแลส่วนกลางก่อน');
            header('Location: ' . base_url('platform/login'));
            exit;
        }
    }
}
