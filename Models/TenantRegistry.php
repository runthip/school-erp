<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * ทะเบียนโรงเรียนบนศูนย์ควบคุมกลาง + การเปิดใช้งานโรงเรียนใหม่
 *
 * หลักการกันข้อมูลทับกัน:
 *   - school_code  ต้องไม่ซ้ำ (UNIQUE)  → ใช้เป็นคีย์หลักตอนเข้าระบบ
 *   - db_name      ต้องไม่ซ้ำ (UNIQUE)  → แต่ละโรงเรียนมีฐานข้อมูลของตัวเอง
 *   - ก่อนสร้างใหม่จะตรวจว่าฐานข้อมูลชื่อนี้ยังไม่มีอยู่จริงบนเซิร์ฟเวอร์
 */
class TenantRegistry
{
    private function pdo(): PDO
    {
        $pdo = Database::central();
        if (!$pdo) throw new \RuntimeException('ยังไม่ได้เชื่อมต่อศูนย์ควบคุมกลาง');
        return $pdo;
    }

    private function cfg(): array { return $GLOBALS['config']['central']; }

    // ---------------- อ่านข้อมูล ----------------
    public function all(string $q = ''): array
    {
        if ($q !== '') {
            $st = $this->pdo()->prepare(
                "SELECT * FROM tenants WHERE school_code LIKE ? OR name_th LIKE ? OR db_name LIKE ?
                 ORDER BY name_th");
            $like = '%' . $q . '%';
            $st->execute([$like, $like, $like]);
            return $st->fetchAll();
        }
        return $this->pdo()->query("SELECT * FROM tenants ORDER BY name_th")->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo()->prepare("SELECT * FROM tenants WHERE id=? LIMIT 1");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function codeExists(string $code): bool
    {
        $st = $this->pdo()->prepare("SELECT 1 FROM tenants WHERE school_code=? LIMIT 1");
        $st->execute([$code]);
        return (bool) $st->fetchColumn();
    }

    public function dbExistsInRegistry(string $db): bool
    {
        $st = $this->pdo()->prepare("SELECT 1 FROM tenants WHERE db_name=? LIMIT 1");
        $st->execute([$db]);
        return (bool) $st->fetchColumn();
    }

    public function setStatus(int $id, string $status): void
    {
        $this->pdo()->prepare("UPDATE tenants SET status=? WHERE id=?")->execute([$status, $id]);
    }

    public function updateInfo(int $id, array $d): void
    {
        $this->pdo()->prepare(
            "UPDATE tenants SET name_th=?, affiliation=?, province=?,
             contact_name=?, contact_phone=?, contact_email=?, note=? WHERE id=?"
        )->execute([$d['name_th'], $d['affiliation'], $d['province'],
                    $d['contact_name'], $d['contact_phone'], $d['contact_email'], $d['note'], $id]);
    }

    public function logs(?int $tenantId = null, int $limit = 100): array
    {
        if ($tenantId) {
            $st = $this->pdo()->prepare(
                "SELECT * FROM tenant_access_logs WHERE tenant_id=? ORDER BY id DESC LIMIT {$limit}");
            $st->execute([$tenantId]);
            return $st->fetchAll();
        }
        return $this->pdo()->query("SELECT * FROM tenant_access_logs ORDER BY id DESC LIMIT {$limit}")->fetchAll();
    }

    /** สถิติการใช้งานของโรงเรียน (อ่านจากฐานข้อมูลของโรงเรียนนั้นโดยตรง) */
    public function stats(array $tenant): array
    {
        $out = ['users' => null, 'students' => null, 'personnel' => null, 'size_mb' => null, 'error' => null];
        try {
            $pdo = $this->tenantPdo($tenant['db_name']);
            foreach (['users', 'students', 'personnel'] as $t) {
                $out[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
            }
            $st = $pdo->prepare("SELECT ROUND(SUM(data_length+index_length)/1048576,1)
                                 FROM information_schema.tables WHERE table_schema=?");
            $st->execute([$tenant['db_name']]);
            $out['size_mb'] = (float) $st->fetchColumn();
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
        }
        return $out;
    }

    // ---------------- เปิดใช้งานโรงเรียนใหม่ ----------------
    /** ชื่อฐานข้อมูลที่แนะนำจากรหัสโรงเรียน เช่น 10123456 → erp_10123456 */
    public function suggestDbName(string $code): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]/', '_', $code));
        return substr($this->cfg()['db_prefix'] . $slug, 0, 60);
    }

    public static function validCode(string $code): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,19}$/', $code);
    }

    private function serverPdo(bool $privileged = false): PDO
    {
        $c = $this->cfg();
        $user = $privileged && $c['provision_user'] !== '' ? $c['provision_user'] : $c['user'];
        $pass = $privileged && $c['provision_user'] !== '' ? $c['provision_pass'] : $c['pass'];
        $dsn  = !empty($c['socket'])
            ? "mysql:unix_socket={$c['socket']};charset=utf8mb4"
            : "mysql:host={$c['host']};port={$c['port']};charset=utf8mb4";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function tenantPdo(string $db): PDO
    {
        $c = $this->cfg();
        $dsn = !empty($c['socket'])
            ? "mysql:unix_socket={$c['socket']};dbname={$db};charset=utf8mb4"
            : "mysql:host={$c['host']};port={$c['port']};dbname={$db};charset=utf8mb4";
        return new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function databaseExists(string $db): bool
    {
        $st = $this->serverPdo()->prepare(
            "SELECT 1 FROM information_schema.schemata WHERE schema_name=? LIMIT 1");
        $st->execute([$db]);
        return (bool) $st->fetchColumn();
    }

    /**
     * สร้างโรงเรียนใหม่: สร้างฐานข้อมูล → นำเข้าโครงสร้าง+ค่าตั้งต้น →
     * ตั้งข้อมูลโรงเรียน + แอดมินคนแรก + ปีการศึกษา → บันทึกลงทะเบียนกลาง
     *
     * @return array{ok:bool, message:string, id:?int, db:?string}
     */
    public function provision(array $d): array
    {
        $code = trim($d['school_code']);
        $db   = trim($d['db_name']) !== '' ? trim($d['db_name']) : $this->suggestDbName($code);

        if (!self::validCode($code))            return $this->err('รหัสโรงเรียนต้องเป็น A-Z a-z 0-9 _ - ยาว 3–20 ตัว');
        if (!preg_match('/^[A-Za-z0-9_]{3,60}$/', $db))
                                                return $this->err('ชื่อฐานข้อมูลไม่ถูกต้อง (A-Z a-z 0-9 _ เท่านั้น)');
        if ($this->codeExists($code))           return $this->err('รหัสโรงเรียนนี้ถูกใช้แล้ว — ห้ามซ้ำกัน');
        if ($this->dbExistsInRegistry($db))     return $this->err('ชื่อฐานข้อมูลนี้ถูกใช้กับโรงเรียนอื่นแล้ว');
        if (strlen($d['admin_pass']) < 8)       return $this->err('รหัสผ่านแอดมินต้องยาวอย่างน้อย 8 ตัวอักษร');

        $sqlFile = BASE_PATH . '/deploy/school_erp_clean.sql';
        if (!is_file($sqlFile)) return $this->err('ไม่พบไฟล์ติดตั้ง deploy/school_erp_clean.sql');

        $created = false;
        try {
            if ($this->databaseExists($db)) {
                return $this->err("มีฐานข้อมูลชื่อ {$db} อยู่บนเซิร์ฟเวอร์แล้ว — เลือกชื่ออื่นเพื่อกันข้อมูลทับกัน");
            }
            // 1) สร้างฐานข้อมูล (ใช้บัญชีที่มีสิทธิ์ CREATE DATABASE)
            $srv = $this->serverPdo(true);
            $srv->exec("CREATE DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $created = true;

            // ให้สิทธิ์บัญชีที่ระบบใช้งาน (ถ้าใช้บัญชีคนละตัวและมีสิทธิ์ GRANT)
            $c = $this->cfg();
            if ($c['provision_user'] !== '' && $c['provision_user'] !== $c['user']) {
                try {
                    $srv->exec("GRANT ALL PRIVILEGES ON `{$db}`.* TO '{$c['user']}'@'localhost'");
                    $srv->exec("FLUSH PRIVILEGES");
                } catch (\Throwable $e) { /* อาจให้สิทธิ์ล่วงหน้าไว้แล้วแบบ erp_% */ }
            }

            // 2) นำเข้าโครงสร้าง + ค่าตั้งต้น
            $pdo = $this->tenantPdo($db);
            $pdo->exec(file_get_contents($sqlFile));

            // 3) ข้อมูลโรงเรียน + แอดมินคนแรก + ปีการศึกษาปัจจุบัน
            $pdo->prepare("INSERT INTO schools (id, school_code, name_th, affiliation, province)
                           VALUES (1,?,?,?,?)
                           ON DUPLICATE KEY UPDATE school_code=VALUES(school_code), name_th=VALUES(name_th),
                             affiliation=VALUES(affiliation), province=VALUES(province)")
                ->execute([$code, $d['name_th'], $d['affiliation'] ?: null, $d['province'] ?: null]);

            $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, status, linked_type, must_change_password)
                           VALUES (?,?,?,?, 'active','none', 1)")
                ->execute([$d['admin_user'], $d['admin_email'] ?: null,
                           password_hash($d['admin_pass'], PASSWORD_BCRYPT),
                           $d['admin_name'] ?: 'ผู้ดูแลระบบ']);
            $uid = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO user_roles (user_id, role_id)
                           SELECT ?, id FROM roles WHERE code='super_admin'")->execute([$uid]);

            $pdo->exec("INSERT INTO academic_years (school_id, year_be, is_current)
                        VALUES (1, YEAR(CURDATE())+543, 1)");
            $ay = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO semesters (academic_year_id, term, is_current) VALUES (?,1,1),(?,2,0)")
                ->execute([$ay, $ay]);

            // 3.5) ให้โรงเรียนใหม่ได้โครงสร้างล่าสุดเสมอ
            //      (เผื่อชุดติดตั้ง school_erp_clean.sql เก่ากว่าไฟล์ใน database/)
            $catchUp = $this->catchUpMigrations($db);

            // 4) ลงทะเบียนที่ศูนย์กลาง
            $this->pdo()->prepare(
                "INSERT INTO tenants (school_code, name_th, db_name, affiliation, province,
                    contact_name, contact_phone, contact_email, admin_username, status, note, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?, 'active', ?, ?)")
                ->execute([$code, $d['name_th'], $db, $d['affiliation'] ?: null, $d['province'] ?: null,
                           $d['contact_name'] ?: null, $d['contact_phone'] ?: null, $d['contact_email'] ?: null,
                           $d['admin_user'], $d['note'] ?: null, $d['created_by'] ?: null]);
            $id = (int) $this->pdo()->lastInsertId();

            return ['ok' => true, 'id' => $id, 'db' => $db,
                    'message' => "เปิดใช้งานโรงเรียนเรียบร้อย (ฐานข้อมูล {$db})" . $catchUp];
        } catch (\Throwable $e) {
            // ย้อนกลับ: ลบฐานข้อมูลที่เพิ่งสร้างถ้าติดตั้งไม่สำเร็จ (ยังไม่มีข้อมูลใคร)
            if ($created) {
                try { $this->serverPdo(true)->exec("DROP DATABASE `{$db}`"); } catch (\Throwable $e2) {}
            }
            return $this->err('เปิดใช้งานไม่สำเร็จ: ' . $e->getMessage());
        }
    }

    /**
     * นำเข้าไฟล์ปรับปรุงฐานข้อมูลที่ยังค้างให้ฐานข้อมูลของโรงเรียนหนึ่ง
     * ใช้ตอนเปิดโรงเรียนใหม่ เพื่อให้ได้โครงสร้างล่าสุดเท่ากับโรงเรียนอื่นเสมอ
     * @return string ข้อความสรุป (ว่างถ้าไม่มีอะไรต้องทำ)
     */
    private function catchUpMigrations(string $db): string
    {
        try {
            $cfg = $GLOBALS['config']['db'];
            $cfg['name'] = $db;
            if (!Database::switchTo($cfg)) return '';
            $pending = \App\Core\Migrator::pending();
            if (!$pending) return '';
            $res  = \App\Core\Migrator::run($pending);
            $ok   = count(array_filter($res, fn($r) => $r['ok']));
            $bad  = array_values(array_filter($res, fn($r) => !$r['ok'] && !$r['skipped']));
            if ($bad) return " · ⚠ นำเข้าไฟล์เพิ่มเติมไม่สำเร็จที่ {$bad[0]['file']} — โปรดตรวจที่หน้า “นำเข้าฐานข้อมูล”";
            return " · นำเข้าไฟล์ปรับปรุงเพิ่มอีก {$ok} ไฟล์ให้เป็นรุ่นล่าสุด";
        } catch (\Throwable $e) {
            return ' · ⚠ ตรวจรุ่นฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage();
        } finally {
            // กลับไปใช้ฐานข้อมูลเดิมของ request นี้ (ผู้ดูแลส่วนกลางไม่ได้เลือกโรงเรียนใด)
            $cur = \App\Core\Tenant::current();
            if ($cur) Database::switchTo(self::dbConfigFor($cur));
        }
    }

    private static function dbConfigFor(array $tenant): array
    {
        $db = $GLOBALS['config']['db'];
        $db['name'] = $tenant['db_name'];
        return $db;
    }

    /** ตั้งรหัสผ่านใหม่ให้แอดมินของโรงเรียน (บังคับเปลี่ยนเมื่อเข้าใช้ครั้งถัดไป) */
    public function resetTenantAdmin(array $tenant, string $plain): array
    {
        try {
            $pdo = $this->tenantPdo($tenant['db_name']);
            $user = $tenant['admin_username'];
            $st = $pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
            $st->execute([$user]);
            $id = (int) $st->fetchColumn();
            if (!$id) return $this->err("ไม่พบบัญชีแอดมิน '{$user}' ในฐานข้อมูลของโรงเรียนนี้");
            $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=1,
                           failed_attempts=0, status='active' WHERE id=?")
                ->execute([password_hash($plain, PASSWORD_BCRYPT), $id]);
            return ['ok' => true, 'message' => "ตั้งรหัสผ่านเริ่มต้นให้ '{$user}' แล้ว — ผู้ใช้ต้องเปลี่ยนรหัสทันทีที่เข้าระบบ"];
        } catch (\Throwable $e) {
            return $this->err('รีเซตรหัสผ่านไม่สำเร็จ: ' . $e->getMessage());
        }
    }

    private function err(string $m): array
    {
        return ['ok' => false, 'message' => $m, 'id' => null, 'db' => null];
    }
}
