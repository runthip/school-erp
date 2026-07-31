<?php
namespace App\Core;

/**
 * จัดการ Session แบบปลอดภัย
 */
final class Session
{
    public static function start(int $lifetime = 7200): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        // ใช้ไดเรกทอรีเซสชันเฉพาะของแอป (นอก temp ที่ XAMPP แชร์กัน)
        $dir = dirname(__DIR__, 2) . '/storage/sessions';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        if (is_dir($dir) && is_writable($dir)) session_save_path($dir);

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // ชื่อคุกกี้แยกตามพอร์ต: Apache (:80, user=daemon) กับ dev server (php -S :8000, user=อื่น)
        // จะไม่ใช้ไฟล์เซสชันร่วมกัน → ไม่เกิด "Permission denied" ข้ามเจ้าของไฟล์ → CSRF ไม่เพี้ยนอีก
        $port = preg_replace('/\D/', '', (string)($_SERVER['SERVER_PORT'] ?? '80')) ?: '80';
        session_name('SCHOOLERP_SID_' . $port);
        session_start();
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        session_destroy();
    }

    /** ข้อความแจ้งเตือนแบบครั้งเดียว (flash) */
    public static function flash(string $type, ?string $message = null)
    {
        if ($message === null) {
            $msg = $_SESSION['_flash'][$type] ?? null;
            unset($_SESSION['_flash'][$type]);
            return $msg;
        }
        $_SESSION['_flash'][$type] = $message;
        return null;
    }
}
