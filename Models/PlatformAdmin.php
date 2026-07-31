<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Session;
use PDO;

/**
 * ผู้ดูแลระบบส่วนกลาง (Super admin ของผู้ให้บริการ)
 * อยู่บนฐานข้อมูลศูนย์ควบคุมกลาง — แยกจากผู้ใช้ของแต่ละโรงเรียนโดยสิ้นเชิง
 */
class PlatformAdmin
{
    private function pdo(): PDO
    {
        $pdo = Database::central();
        if (!$pdo) throw new \RuntimeException('ยังไม่ได้เชื่อมต่อศูนย์ควบคุมกลาง');
        return $pdo;
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo()->prepare("SELECT * FROM platform_admins WHERE id=? LIMIT 1");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function findByUsername(string $u): ?array
    {
        $st = $this->pdo()->prepare("SELECT * FROM platform_admins WHERE username=? LIMIT 1");
        $st->execute([$u]);
        return $st->fetch() ?: null;
    }

    /** ล็อกอินผู้ดูแลส่วนกลาง คืน [ok, message] */
    public function attempt(string $username, string $password, int $maxAttempts = 5): array
    {
        $a = $this->findByUsername($username);
        if (!$a) return [false, 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        if ($a['status'] !== 'active') return [false, 'บัญชีนี้ถูกระงับการใช้งาน'];
        if ((int)$a['failed_attempts'] >= $maxAttempts) {
            return [false, 'บัญชีถูกล็อกจากการเข้าสู่ระบบผิดหลายครั้ง — ปลดล็อกที่ฐานข้อมูลส่วนกลาง'];
        }
        if (!password_verify($password, $a['password_hash'])) {
            $this->pdo()->prepare("UPDATE platform_admins SET failed_attempts=failed_attempts+1 WHERE id=?")
                 ->execute([$a['id']]);
            $left = $maxAttempts - ((int)$a['failed_attempts'] + 1);
            return [false, 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง' . ($left > 0 ? " (เหลืออีก {$left} ครั้ง)" : '')];
        }
        $this->pdo()->prepare("UPDATE platform_admins SET failed_attempts=0, last_login_at=NOW() WHERE id=?")
             ->execute([$a['id']]);
        Session::regenerate();
        Session::set('platform_admin_id', (int)$a['id']);
        return [true, 'เข้าสู่ระบบสำเร็จ'];
    }

    /** ผู้ดูแลส่วนกลางที่ล็อกอินอยู่ (จาก session) */
    public static function current(): ?array
    {
        if (!Database::hasCentral()) return null;
        $id = Session::get('platform_admin_id');
        if (!$id) return null;
        static $cache = null;
        if ($cache !== null && (int)$cache['id'] === (int)$id) return $cache;
        return $cache = (new self())->find((int)$id);
    }

    public static function check(): bool { return self::current() !== null; }

    public function updatePassword(int $id, string $plain): void
    {
        $this->pdo()->prepare("UPDATE platform_admins SET password_hash=?, must_change_password=0 WHERE id=?")
             ->execute([password_hash($plain, PASSWORD_BCRYPT), $id]);
    }
}
