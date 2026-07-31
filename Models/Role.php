<?php
namespace App\Models;

use App\Core\Model;

class Role extends Model
{
    protected string $table = 'roles';

    public function allWithCounts(): array
    {
        return $this->query(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS user_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perm_count
             FROM roles r
             ORDER BY r.is_system DESC, r.id ASC"
        );
    }

    public function findByCode(string $code): ?array
    {
        return $this->first("SELECT * FROM roles WHERE code = ? LIMIT 1", [$code]);
    }

    public function create(array $d): int
    {
        $this->execute(
            "INSERT INTO roles (code, name, description, is_system) VALUES (?,?,?,0)",
            [$d['code'], $d['name'], $d['description'] ?: null]
        );
        return $this->lastId();
    }

    public function update(int $id, array $d): void
    {
        $this->execute(
            "UPDATE roles SET name=?, description=? WHERE id=?",
            [$d['name'], $d['description'] ?: null, $id]
        );
    }

    public function delete(int $id): void
    {
        $this->execute("DELETE FROM roles WHERE id = ? AND is_system = 0", [$id]);
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = "SELECT id FROM roles WHERE code = ?";
        $params = [$code];
        if ($exceptId) { $sql .= " AND id <> ?"; $params[] = $exceptId; }
        return $this->first($sql, $params) !== null;
    }

    public function permissionIds(int $roleId): array
    {
        $rows = $this->query("SELECT permission_id FROM role_permissions WHERE role_id = ?", [$roleId]);
        return array_map('intval', array_column($rows, 'permission_id'));
    }

    public function syncPermissions(int $roleId, array $permIds): void
    {
        $this->execute("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
        foreach (array_unique($permIds) as $pid) {
            $this->execute("INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)", [$roleId, (int) $pid]);
        }
    }
}
