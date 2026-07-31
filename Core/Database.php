<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * การเชื่อมต่อฐานข้อมูล (PDO / MySQL)
 *  - $pdo     = ฐานข้อมูลของโรงเรียนที่กำลังใช้งาน (tenant)
 *               หรือฐานข้อมูลเดียวเมื่อใช้โหมดโรงเรียนเดียว
 *  - $central = ฐานข้อมูลศูนย์ควบคุมกลาง (ทะเบียนโรงเรียน) — เฉพาะโหมดหลายโรงเรียน
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static ?PDO $central = null;

    private static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }

    private static function dsn(array $cfg): string
    {
        $charset = $cfg['charset'] ?? 'utf8mb4';
        if (!empty($cfg['socket'])) {
            return "mysql:unix_socket={$cfg['socket']};dbname={$cfg['name']};charset={$charset}";
        }
        return "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$charset}";
    }

    /** เชื่อมต่อฐานข้อมูลหลัก (ครั้งแรกเท่านั้น) */
    public static function connect(array $cfg): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;
        try {
            self::$pdo = new PDO(self::dsn($cfg), $cfg['user'], $cfg['pass'], self::options());
        } catch (PDOException $e) {
            http_response_code(500);
            exit('เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage());
        }
        return self::$pdo;
    }

    /** สลับไปฐานข้อมูลอื่น (ใช้ตอนเลือกโรงเรียน) — คืน null ถ้าเชื่อมต่อไม่ได้ */
    public static function switchTo(array $cfg): ?PDO
    {
        try {
            self::$pdo = new PDO(self::dsn($cfg), $cfg['user'], $cfg['pass'], self::options());
            return self::$pdo;
        } catch (PDOException $e) {
            return null;
        }
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo) throw new \RuntimeException('ยังไม่ได้เชื่อมต่อฐานข้อมูล');
        return self::$pdo;
    }

    public static function isConnected(): bool { return self::$pdo instanceof PDO; }

    // ---------- ศูนย์ควบคุมกลาง ----------
    /** เชื่อมต่อ central — คืน null ถ้าไม่ได้เปิดใช้โหมดหลายโรงเรียน หรือยังไม่ได้ติดตั้ง */
    public static function connectCentral(array $cfg): ?PDO
    {
        if (self::$central instanceof PDO) return self::$central;
        if (empty($cfg['name'])) return null;
        try {
            self::$central = new PDO(self::dsn($cfg), $cfg['user'], $cfg['pass'], self::options());
        } catch (PDOException $e) {
            self::$central = null;   // ยังไม่ติดตั้ง central → ใช้งานโหมดโรงเรียนเดียวต่อไปได้
        }
        return self::$central;
    }

    public static function central(): ?PDO { return self::$central; }
    public static function hasCentral(): bool { return self::$central instanceof PDO; }
}
