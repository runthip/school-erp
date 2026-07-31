<?php
namespace App\Core;

use App\Models\User;
use App\Models\AuditLog;

/**
 * ระบบยืนยันตัวตนและตรวจสอบสิทธิ์ (RBAC)
 */
final class Auth
{
    private static ?array $user = null;
    private static ?array $permissions = null;

    /** ผู้ใช้ปัจจุบัน (จาก session) */
    public static function user(): ?array
    {
        if (self::$user !== null) return self::$user;
        $id = Session::get('user_id');
        if (!$id) return null;
        self::$user = (new User())->find((int) $id) ?: null;
        return self::$user;
    }

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** login ด้วย username/password คืน [ok, message] */
    public static function attempt(string $username, string $password, array $security): array
    {
        $userModel = new User();
        $u = $userModel->findByUsername($username);

        if (!$u) {
            return [false, 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }

        // บัญชีถูกล็อก?
        if ($u['status'] === 'locked') {
            return [false, 'บัญชีถูกล็อกจากการเข้าสู่ระบบผิดหลายครั้ง กรุณาติดต่อผู้ดูแลระบบ'];
        }
        if ($u['status'] !== 'active') {
            return [false, 'บัญชีนี้ถูกระงับการใช้งาน'];
        }

        // ตรวจรหัสผ่าน
        if (!password_verify($password, $u['password_hash'])) {
            $attempts = (int) $u['failed_attempts'] + 1;
            $lock = $attempts >= $security['max_login_attempts'];
            $userModel->recordFailedAttempt((int) $u['id'], $attempts, $lock);
            $userModel->logLogin((int) $u['id'], $username, $lock ? 'locked' : 'failed');
            $left = $security['max_login_attempts'] - $attempts;
            $msg = $lock
                ? 'เข้าสู่ระบบผิดเกินกำหนด บัญชีถูกล็อกชั่วคราว'
                : "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง (เหลืออีก {$left} ครั้ง)";
            return [false, $msg];
        }

        // สำเร็จ
        $userModel->resetAttempts((int) $u['id']);
        $userModel->logLogin((int) $u['id'], $username, 'success');
        Session::regenerate();
        Session::set('user_id', (int) $u['id']);
        self::$user = $u;
        self::$permissions = null;

        AuditLog::record((int) $u['id'], 'login', 'users', (int) $u['id']);
        return [true, 'เข้าสู่ระบบสำเร็จ'];
    }

    public static function logout(): void
    {
        if ($id = self::id()) {
            AuditLog::record($id, 'logout', 'users', $id);
        }
        Session::destroy();
        self::$user = null;
        self::$permissions = null;
    }

    /** รหัสบทบาททั้งหมดของผู้ใช้ */
    public static function roles(): array
    {
        if (!self::check()) return [];
        return (new User())->roleCodes(self::id());
    }

    /** รหัสสิทธิ์ทั้งหมด (รวมจากทุกบทบาท) */
    public static function permissions(): array
    {
        if (self::$permissions !== null) return self::$permissions;
        if (!self::check()) return self::$permissions = [];
        self::$permissions = (new User())->permissionCodes(self::id());
        return self::$permissions;
    }

    public static function hasRole(string ...$codes): bool
    {
        return (bool) array_intersect($codes, self::roles());
    }

    /** super_admin ผ่านทุกสิทธิ์เสมอ */
    public static function can(string $permission): bool
    {
        if (self::hasRole('super_admin')) return true;
        return in_array($permission, self::permissions(), true);
    }
}
