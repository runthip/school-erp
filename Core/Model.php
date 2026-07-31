<?php
namespace App\Core;

use App\Core\Database;
use PDO;

/**
 * Base Model : ครอบ PDO ด้วยเมธอดที่ใช้บ่อย
 */
abstract class Model
{
    protected string $table = '';

    protected function db(): PDO
    {
        return Database::pdo();
    }

    /** คืนทุกแถว */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** คืนแถวเดียว หรือ null */
    protected function first(string $sql, array $params = []): ?array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** รัน INSERT/UPDATE/DELETE คืน rowCount */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function lastId(): int
    {
        return (int) $this->db()->lastInsertId();
    }

    public function find(int $id): ?array
    {
        return $this->first("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1", [$id]);
    }

    public function all(): array
    {
        return $this->query("SELECT * FROM {$this->table} ORDER BY id DESC");
    }
}
