<?php
namespace App\Models;
use App\Core\Model;

/**
 * โทเคนรีเซตรหัสผ่าน — เก็บเฉพาะ hash ของโทเคน (ไม่เก็บโทเคนดิบ)
 * ใช้ครั้งเดียว หมดอายุตามเวลาที่กำหนด · ออกใหม่จะยกเลิกของเก่าที่ยังไม่ใช้
 */
class PasswordReset extends Model
{
    private static function hash(string $raw): string
    { return hash('sha256', $raw); }

    /** สร้างโทเคนใหม่ให้ผู้ใช้ → คืนโทเคนดิบ (ไว้ใส่ในลิงก์อีเมล) */
    public function create(int $userId, int $ttlMinutes = 60): string
    {
        // ยกเลิกโทเคนเดิมที่ยังไม่ใช้ (ให้เหลือใบล่าสุดใบเดียว)
        $this->execute("UPDATE password_resets SET used_at=NOW()
            WHERE user_id=? AND used_at IS NULL", [$userId]);
        $raw = bin2hex(random_bytes(32));               // 64 hex
        $exp = date('Y-m-d H:i:s', time() + max(5,$ttlMinutes)*60);
        $this->execute("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,?)",
            [$userId, self::hash($raw), $exp]);
        return $raw;
    }

    /** ตรวจโทเคนดิบ → คืนแถว (id, user_id) ถ้ายังใช้ได้ ไม่งั้น null */
    public function findValid(string $raw): ?array
    {
        if ($raw === '' || !ctype_xdigit($raw)) return null;
        return $this->first("SELECT id, user_id FROM password_resets
            WHERE token_hash=? AND used_at IS NULL AND expires_at > NOW() LIMIT 1",
            [self::hash($raw)]);
    }

    public function markUsed(int $id): void
    { $this->execute("UPDATE password_resets SET used_at=NOW() WHERE id=?", [$id]); }

    /** ล้างโทเคนหมดอายุ/ใช้แล้วเกิน 7 วัน (housekeeping) */
    public function purgeOld(): void
    { $this->execute("DELETE FROM password_resets WHERE (used_at IS NOT NULL OR expires_at < NOW()) AND created_at < (NOW() - INTERVAL 7 DAY)"); }
}
