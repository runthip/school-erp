<?php
namespace App\Core;

/**
 * Base Controller
 */
abstract class Controller
{
    /** แสดงหน้าเว็บ */
    protected function view(string $view, array $data = [], string $layout = 'app'): void
    {
        echo View::render($view, $data, $layout);
    }

    /** ตอบ JSON */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** redirect */
    protected function redirect(string $to): void
    {
        header('Location: ' . base_url($to));
        exit;
    }

    /** redirect กลับพร้อม flash */
    protected function back(string $to, string $type, string $message): void
    {
        Session::flash($type, $message);
        $this->redirect($to);
    }

    /** ตรวจสิทธิ์; ถ้าไม่มี แสดงหน้า 403 */
    protected function authorize(string $permission): void
    {
        if (!Auth::can($permission)) {
            http_response_code(403);
            echo View::render('errors/403', ['title' => 'ไม่มีสิทธิ์'], 'app');
            exit;
        }
    }

    /** ตรวจ CSRF; ถ้าไม่ผ่าน หยุด */

    /**
     * ถ้าข้อมูลที่ส่งมาใหญ่เกิน post_max_size ของ PHP
     * PHP จะทิ้ง $_POST และ $_FILES ทั้งหมด → CSRF token หายไปด้วย
     * ทำให้ผู้ใช้เห็น "CSRF ไม่ถูกต้อง" ทั้งที่ปัญหาจริงคือไฟล์ใหญ่เกิน
     * จึงต้องตรวจก่อน verifyCsrf() เสมอ
     */
    protected function guardPostSize(string $back): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
        $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $max = ini_bytes((string)ini_get('post_max_size'));
        if ($max > 0 && $len > $max && empty($_POST)) {
            $this->back($back, 'error',
                'ไฟล์ที่แนบรวมกัน '.human_bytes($len).' ใหญ่เกินที่เซิร์ฟเวอร์รับได้ ('
                .human_bytes($max).') — แนบทีละน้อยไฟล์ลง หรือให้ผู้ดูแลเพิ่มค่า post_max_size ใน php.ini');
        }
    }

    protected function verifyCsrf(): void
    {
        if (!Csrf::check(Request::input('_csrf'))) {
            http_response_code(419);
            exit('CSRF token ไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
        }
    }
}
