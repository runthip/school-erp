<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class AuditLog extends Model
{
    protected string $table = 'audit_logs';

    /** บันทึก log (static เรียกง่าย) */
    public static function record(?int $userId, string $action, ?string $entityType = null,
                                  ?int $entityId = null, ?array $old = null, ?array $new = null): void
    {
        try {
            $stmt = Database::pdo()->prepare(
                "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address)
                 VALUES (?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $userId, $action, $entityType, $entityId,
                $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // ไม่ให้ log ล้มแล้วทำให้ทั้งระบบพัง
            error_log('audit_log failed: ' . $e->getMessage());
        }
    }

    public function paginate(string $search = '', string $action = '', int $limit = 30, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(a.entity_type LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)';
            $like = "%{$search}%";
            array_push($params, $like, $like, $like);
        }
        if ($action !== '') {
            $where[] = 'a.action = ?';
            $params[] = $action;
        }
        $wsql = 'WHERE ' . implode(' AND ', $where);
        return $this->query(
            "SELECT a.*, u.username, u.full_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             {$wsql}
             ORDER BY a.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public function countAll(string $search = '', string $action = ''): int
    {
        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(a.entity_type LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)';
            $like = "%{$search}%";
            array_push($params, $like, $like, $like);
        }
        if ($action !== '') { $where[] = 'a.action = ?'; $params[] = $action; }
        $row = $this->first(
            "SELECT COUNT(*) c FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE " . implode(' AND ', $where),
            $params
        );
        return (int) ($row['c'] ?? 0);
    }

    public function distinctActions(): array
    {
        $rows = $this->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
        return array_column($rows, 'action');
    }
}
