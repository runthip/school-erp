<?php
namespace App\Models;
use App\Core\Model;

/** การแจ้งเตือนผู้ใช้ (bell + หน้ารายการ) */
class Notification extends Model
{
    protected string $table = 'notifications';

    public function create(int $userId, string $title, string $body='', string $link=''): int
    {
        $this->execute("INSERT INTO notifications (user_id, title, body, link) VALUES (?,?,?,?)",
            [$userId, $title, $body?:null, $link?:null]);
        return $this->lastId();
    }

    /** สร้างแจ้งเตือนให้บุคลากร (แมป personnel → user) — คืน true ถ้ามีบัญชีผู้ใช้ */
    public function notifyPersonnel(int $personnelId, string $title, string $body='', string $link=''): bool
    {
        $u=$this->first("SELECT id FROM users WHERE linked_type='personnel' AND linked_id=? AND deleted_at IS NULL LIMIT 1", [$personnelId]);
        if(!$u) return false;
        $this->create((int)$u['id'], $title, $body, $link);
        return true;
    }

    public function forUser(int $userId, int $limit=20): array
    { return $this->query("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT $limit", [$userId]); }

    public function unreadCount(int $userId): int
    { return (int)($this->first("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0", [$userId])['c']??0); }

    public function markRead(int $id, int $userId): void
    { $this->execute("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?", [$id,$userId]); }

    public function markAllRead(int $userId): void
    { $this->execute("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0", [$userId]); }
}
