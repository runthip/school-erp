<?php
/**
 * Seeder สาธิต: สร้างสิทธิ์ทั้งหมด, ผูกบทบาท↔สิทธิ์ และบัญชีผู้ใช้ครบทุกบทบาท
 * รัน:  php database/seed_demo.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = BASE_PATH . '/app/' . str_replace(['App\\','\\'], ['','/'], $class) . '.php';
    if (is_file($file)) require $file;
});
require BASE_PATH . '/app/Core/helpers.php';

$config = require BASE_PATH . '/config/config.php';
$GLOBALS['config'] = $config;
$menu = require BASE_PATH . '/config/menu.php';

$pdo = App\Core\Database::connect($config['db']);
echo "เชื่อมต่อฐานข้อมูลสำเร็จ\n";

// ---- 1) permissions ----
$permNames = $menu['permission_names'];
$stmt = $pdo->prepare(
    "INSERT INTO permissions (code, name, module) VALUES (?,?,?)
     ON DUPLICATE KEY UPDATE name=VALUES(name), module=VALUES(module)"
);
foreach ($permNames as $code => $name) {
    $module = explode('.', $code)[0];
    $stmt->execute([$code, $name, $module]);
}
echo "สร้าง/อัปเดตสิทธิ์: " . count($permNames) . " รายการ\n";

// map code -> id
$permId = [];
foreach ($pdo->query("SELECT id, code FROM permissions") as $r) {
    $permId[$r['code']] = (int) $r['id'];
}
$allPermIds = array_values($permId);

// ---- 2) role_permissions ----
$roleId = [];
foreach ($pdo->query("SELECT id, code FROM roles") as $r) {
    $roleId[$r['code']] = (int) $r['id'];
}

$del = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
$ins = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?,?)");

foreach ($menu['role_permissions'] as $roleCode => $perms) {
    if (!isset($roleId[$roleCode])) { echo "  ! ไม่พบบทบาท {$roleCode}\n"; continue; }
    $rid = $roleId[$roleCode];
    $del->execute([$rid]);
    $ids = ($perms === ['*']) ? $allPermIds
         : array_values(array_filter(array_map(fn($c) => $permId[$c] ?? null, $perms)));
    foreach ($ids as $pid) $ins->execute([$rid, $pid]);
    echo sprintf("  บทบาท %-16s → %d สิทธิ์\n", $roleCode, count($ids));
}

// ---- 3) demo users (หนึ่งบัญชีต่อบทบาท) ----
$demoPass = password_hash('Demo@1234', PASSWORD_BCRYPT);

$roleLabel = [];
foreach ($pdo->query("SELECT code, name FROM roles") as $r) $roleLabel[$r['code']] = $r['name'];

$findUser = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$insUser  = $pdo->prepare(
    "INSERT INTO users (username, full_name, password_hash, status, linked_type)
     VALUES (?,?,?, 'active', 'none')"
);
$linkRole = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?,?)");

$created = 0;
foreach ($roleId as $code => $rid) {
    if ($code === 'super_admin') continue; // ใช้บัญชี admin เดิม
    $username = $code;                      // เช่น teacher, director
    $findUser->execute([$username]);
    $uid = $findUser->fetchColumn();
    if (!$uid) {
        $insUser->execute([$username, ($roleLabel[$code] ?? $code) . ' (สาธิต)', $demoPass]);
        $uid = (int) $pdo->lastInsertId();
        $created++;
    }
    // ล้าง role เดิมของบัญชีสาธิตแล้วผูกใหม่ให้ตรงบทบาทเดียว
    $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$uid]);
    $linkRole->execute([(int) $uid, $rid]);
}
echo "สร้างบัญชีผู้ใช้สาธิตใหม่: {$created} บัญชี (รวมทั้งหมด " . count($roleId) . " บทบาท)\n";
echo "รหัสผ่านบัญชีสาธิตทุกบทบาท: Demo@1234   (ยกเว้น admin = Admin@123)\n";
echo "เสร็จสิ้น\n";
