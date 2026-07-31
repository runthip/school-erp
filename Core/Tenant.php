<?php
namespace App\Core;

use PDO;

/**
 * ตัวจัดการ "โรงเรียนที่กำลังใช้งาน" (tenant) สำหรับระบบหลายโรงเรียน
 *
 *  แนวคิด: โค้ดชุดเดียว · แยกฐานข้อมูลต่อโรงเรียน · ใช้ "รหัสโรงเรียน" เป็นคีย์หลัก
 *  - ศูนย์ควบคุมกลาง (central) เก็บทะเบียนโรงเรียน → รหัสโรงเรียน ↔ ชื่อฐานข้อมูล
 *  - เมื่อผู้ใช้ล็อกอินด้วยรหัสโรงเรียน ระบบจะสลับไปฐานข้อมูลของโรงเรียนนั้น
 *  - ถ้าไม่ได้เปิดโหมดหลายโรงเรียน ระบบทำงานแบบโรงเรียนเดียวเหมือนเดิมทุกประการ
 */
final class Tenant
{
    private static ?array $current = null;

    /** เปิดใช้โหมดหลายโรงเรียนอยู่หรือไม่ (ต้องตั้งค่า + ติดตั้ง central แล้ว) */
    public static function enabled(): bool
    {
        return !empty($GLOBALS['config']['central']['enabled']) && Database::hasCentral();
    }

    /** โรงเรียนที่กำลังใช้งาน (จาก session) — null ถ้ายังไม่ได้เลือก/โหมดเดียว */
    public static function current(): ?array { return self::$current; }
    public static function code(): ?string   { return self::$current['school_code'] ?? null; }
    public static function name(): ?string   { return self::$current['name_th'] ?? null; }

    /** ค้นหาโรงเรียนจากรหัสโรงเรียน (คีย์หลัก) */
    public static function findByCode(string $code): ?array
    {
        $pdo = Database::central();
        if (!$pdo) return null;
        $st = $pdo->prepare("SELECT * FROM tenants WHERE school_code = ? LIMIT 1");
        $st->execute([trim($code)]);
        return $st->fetch() ?: null;
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::central();
        if (!$pdo) return null;
        $st = $pdo->prepare("SELECT * FROM tenants WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** ค่าเชื่อมต่อฐานข้อมูลของโรงเรียน (ใช้ผู้ใช้ DB เดียวกับระบบ ไม่เก็บรหัสผ่านรายโรงเรียน) */
    public static function dbConfig(array $tenant): array
    {
        $db = $GLOBALS['config']['db'];
        $db['name'] = $tenant['db_name'];
        return $db;
    }

    /**
     * สลับไปใช้ฐานข้อมูลของโรงเรียนนี้ + จำไว้ใน session
     * @return bool สำเร็จหรือไม่ (เชื่อมต่อฐานข้อมูลได้จริง)
     */
    public static function use(array $tenant, bool $remember = true): bool
    {
        if (($tenant['status'] ?? '') === 'suspended') return false;
        if (!Database::switchTo(self::dbConfig($tenant))) return false;
        self::$current = $tenant;
        if ($remember) Session::set('tenant_code', $tenant['school_code']);
        return true;
    }

    /** โหลดโรงเรียนจาก session ตอนเริ่มคำขอ (เรียกจาก bootstrap) */
    public static function boot(): bool
    {
        if (!self::enabled()) return false;
        $code = Session::get('tenant_code');
        if (!$code) return false;
        $t = self::findByCode((string)$code);
        if (!$t || $t['status'] === 'suspended') { self::forget(); return false; }
        if (!self::use($t, false)) { self::forget(); return false; }
        return true;
    }

    /** ล้างโรงเรียนที่เลือกไว้ (ออกจากระบบ/ออกจากการดูแล) */
    public static function forget(): void
    {
        self::$current = null;
        Session::forget('tenant_code');
    }

    // ---------- โหมดผู้ดูแลส่วนกลางเข้าดูแลโรงเรียน (impersonation) ----------
    /** กำลังเข้าดูแลระบบของโรงเรียนโดย Super admin อยู่หรือไม่ */
    public static function isSupervising(): bool
    { return (bool)Session::get('platform_supervise'); }

    public static function beginSupervise(): void
    { Session::set('platform_supervise', 1); }

    public static function endSupervise(): void
    { Session::forget('platform_supervise'); }

    /** บันทึกประวัติการเข้าดูแล/จัดการโรงเรียน */
    public static function log(int $tenantId, string $schoolCode, string $action,
                               ?int $adminId, ?string $adminUsername, ?string $note = null): void
    {
        $pdo = Database::central();
        if (!$pdo) return;
        try {
            $st = $pdo->prepare("INSERT INTO tenant_access_logs
                (tenant_id, school_code, platform_admin_id, admin_username, action, ip_address, note)
                VALUES (?,?,?,?,?,?,?)");
            $st->execute([$tenantId, $schoolCode, $adminId, $adminUsername, $action,
                          $_SERVER['REMOTE_ADDR'] ?? null, $note]);
        } catch (\Throwable $e) { /* ไม่ให้ log ล้มแล้วกระทบการใช้งาน */ }
    }
}
