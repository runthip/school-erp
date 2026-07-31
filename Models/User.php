<?php
namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';

    public function findByUsername(string $username): ?array
    {
        return $this->first(
            "SELECT * FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1",
            [$username]
        );
    }

    /** ผู้ใช้ที่ยังไม่ถูกลบ ตามอีเมล (สำหรับลืมรหัสผ่าน) — ต้องมีอีเมลตรงกันเป๊ะ */
    public function findByEmail(string $email): ?array
    {
        $email = trim($email);
        if ($email === '') return null;
        return $this->first(
            "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1",
            [$email]
        );
    }

    public function find(int $id): ?array
    {
        return $this->first("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1", [$id]);
    }

    /** รายการผู้ใช้พร้อมบทบาท (สำหรับตาราง) */
    /** สร้างเงื่อนไข WHERE ร่วมกัน (รองรับกรองบทบาท/ห้องเรียน/ชั้นสำหรับนักเรียน) */
    private function buildFilter(string $search, string $status, int $classroom = 0, int $grade = 0, int $role = 0): array
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if ($search !== '') {
            $where[] = '(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
            $like = "%{$search}%";
            array_push($params, $like, $like, $like);
        }
        if ($status !== '') { $where[] = 'u.status = ?'; $params[] = $status; }
        // กรองตามบทบาท
        if ($role > 0) {
            $where[] = 'u.id IN (SELECT ur2.user_id FROM user_roles ur2 WHERE ur2.role_id=?)';
            $params[] = $role;
        }
        // กรองตามห้องเรียน (เฉพาะบัญชีที่ผูกกับนักเรียนในห้องนั้น)
        if ($classroom > 0) {
            $where[] = "u.linked_type='student' AND u.linked_id IN
                (SELECT se.student_id FROM student_enrollments se WHERE se.classroom_id=?)";
            $params[] = $classroom;
        } elseif ($grade > 0) {
            $where[] = "u.linked_type='student' AND u.linked_id IN
                (SELECT se.student_id FROM student_enrollments se
                 JOIN classrooms c ON c.id=se.classroom_id WHERE c.grade_level_id=?)";
            $params[] = $grade;
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    public function paginate(string $search = '', string $status = '', int $limit = 20, int $offset = 0, int $classroom = 0, int $grade = 0, int $role = 0): array
    {
        [$wsql, $params] = $this->buildFilter($search, $status, $classroom, $grade, $role);
        $rows = $this->query(
            "SELECT u.*,
                    GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS role_names
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             {$wsql}
             GROUP BY u.id
             ORDER BY u.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        return $rows;
    }

    public function countAll(string $search = '', string $status = '', int $classroom = 0, int $grade = 0, int $role = 0): int
    {
        [$wsql, $params] = $this->buildFilter($search, $status, $classroom, $grade, $role);
        $row = $this->first("SELECT COUNT(*) c FROM users u {$wsql}", $params);
        return (int) ($row['c'] ?? 0);
    }

    /** รายการบทบาทสำหรับตัวกรอง */
    public function rolesForFilter(): array
    {
        return $this->query("SELECT id, name FROM roles ORDER BY name");
    }

    /** รายการห้องเรียนสำหรับตัวกรอง (พร้อมชั้น) */
    public function classroomsForFilter(): array
    {
        return $this->query("SELECT c.id, c.name, c.grade_level_id, gl.name AS grade
            FROM classrooms c LEFT JOIN grade_levels gl ON gl.id=c.grade_level_id
            ORDER BY c.grade_level_id, c.name");
    }

    /** รายการชั้นสำหรับตัวกรอง */
    public function gradesForFilter(): array
    {
        return $this->query("SELECT id, name FROM grade_levels ORDER BY id");
    }

    public function create(array $d): int
    {
        $this->execute(
            "INSERT INTO users (username, email, password_hash, full_name, phone, status, created_by)
             VALUES (?,?,?,?,?,?,?)",
            [$d['username'], $d['email'] ?: null, $d['password_hash'], $d['full_name'],
             $d['phone'] ?: null, $d['status'], $d['created_by'] ?? null]
        );
        return $this->lastId();
    }

    public function update(int $id, array $d): void
    {
        $this->execute(
            "UPDATE users SET full_name=?, email=?, phone=?, status=?, updated_by=? WHERE id=?",
            [$d['full_name'], $d['email'] ?: null, $d['phone'] ?: null, $d['status'], $d['updated_by'] ?? null, $id]
        );
    }

    public function updateSignature(int $id, ?string $path): void
    { $this->execute("UPDATE users SET signature_path=? WHERE id=?", [$path, $id]); }

    public function updatePassword(int $id, string $hash): void
    {
        // เจ้าของตั้งรหัสเอง → ล้างธงบังคับเปลี่ยนด้วย
        $this->execute(
            "UPDATE users SET password_hash=?, password_changed_at=NOW(), must_change_password=0 WHERE id=?",
            [$hash, $id]
        );
    }

    /** แอดมินรีเซตเป็นรหัสผ่านเริ่มต้น + บังคับให้ผู้ใช้เปลี่ยนเมื่อล็อกอินครั้งถัดไป */
    public function resetToDefault(int $id, string $hash): void
    {
        $this->execute(
            "UPDATE users SET password_hash=?, password_changed_at=NOW(), must_change_password=1,
                    failed_attempts=0, status=IF(status='locked','active',status) WHERE id=?",
            [$hash, $id]
        );
    }

    public function clearMustChange(int $id): void
    { $this->execute("UPDATE users SET must_change_password=0 WHERE id=?", [$id]); }

    public function softDelete(int $id): void
    {
        $this->execute("UPDATE users SET deleted_at = NOW() WHERE id = ?", [$id]);
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $sql = "SELECT id FROM users WHERE username = ? AND deleted_at IS NULL";
        $params = [$username];
        if ($exceptId) { $sql .= " AND id <> ?"; $params[] = $exceptId; }
        return $this->first($sql, $params) !== null;
    }

    // ---- RBAC ----
    public function roleCodes(int $userId): array
    {
        $rows = $this->query(
            "SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?",
            [$userId]
        );
        return array_column($rows, 'code');
    }

    public function roleIds(int $userId): array
    {
        $rows = $this->query("SELECT role_id FROM user_roles WHERE user_id = ?", [$userId]);
        return array_map('intval', array_column($rows, 'role_id'));
    }

    public function permissionCodes(int $userId): array
    {
        $rows = $this->query(
            "SELECT DISTINCT p.code
             FROM user_roles ur
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ?",
            [$userId]
        );
        return array_column($rows, 'code');
    }

    public function syncRoles(int $userId, array $roleIds): void
    {
        $this->execute("DELETE FROM user_roles WHERE user_id = ?", [$userId]);
        foreach (array_unique($roleIds) as $rid) {
            $this->execute("INSERT INTO user_roles (user_id, role_id) VALUES (?,?)", [$userId, (int) $rid]);
        }
    }

    // ---- login security ----
    public function recordFailedAttempt(int $id, int $attempts, bool $lock): void
    {
        $status = $lock ? 'locked' : null;
        if ($lock) {
            $this->execute("UPDATE users SET failed_attempts=?, status='locked' WHERE id=?", [$attempts, $id]);
        } else {
            $this->execute("UPDATE users SET failed_attempts=? WHERE id=?", [$attempts, $id]);
        }
    }

    public function resetAttempts(int $id): void
    {
        $this->execute("UPDATE users SET failed_attempts=0, last_login_at=NOW() WHERE id=?", [$id]);
    }

    public function unlock(int $id): void
    {
        $this->execute("UPDATE users SET status='active', failed_attempts=0 WHERE id=?", [$id]);
    }

    public function logLogin(int $userId, string $username, string $result): void
    {
        $this->execute(
            "INSERT INTO user_login_history (user_id, username, ip_address, user_agent, result)
             VALUES (?,?,?,?,?)",
            [$userId, $username, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $result]
        );
    }

    public function recentLogins(int $userId, int $limit = 5): array
    {
        return $this->query(
            "SELECT * FROM user_login_history WHERE user_id = ? ORDER BY id DESC LIMIT {$limit}",
            [$userId]
        );
    }
}
