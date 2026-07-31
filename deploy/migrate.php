<?php
/**
 * ==================================================================
 *  migrate.php — นำเข้าไฟล์ SQL ที่ยังค้างอยู่ ให้ "ทุกโรงเรียน" ในระบบ
 *
 *  ใช้งาน (จากโฟลเดอร์หลักของระบบ):
 *      php deploy/migrate.php --status      ดูว่าแต่ละโรงเรียนค้างไฟล์ไหนบ้าง (ไม่แก้อะไร)
 *      php deploy/migrate.php --dry-run     ลองดูว่าจะรันอะไร (ไม่แก้อะไร)
 *      php deploy/migrate.php               รันจริงทุกโรงเรียน
 *      php deploy/migrate.php --school=10123456    เฉพาะโรงเรียนเดียว
 *      php deploy/migrate.php --only=58_xxx.sql    เฉพาะไฟล์เดียว
 *      php deploy/migrate.php --check       ตรวจว่าโครงสร้างตารางครบตามที่โมดูลต้องใช้
 *
 *  - โหมดโรงเรียนเดียว (MULTI_TENANT=false) จะทำกับฐานข้อมูลตาม .env
 *  - โหมดหลายโรงเรียน จะไล่ทำทุกโรงเรียนในทะเบียนกลาง ทีละฐานข้อมูล
 *  - ไฟล์ที่นำเข้าแล้วจะถูกบันทึกในตาราง schema_migrations ของแต่ละโรงเรียน
 *    จึงรันซ้ำได้ปลอดภัย (ไฟล์เดิมจะถูกข้าม)
 *  - ออกด้วยรหัส 1 ถ้ามีโรงเรียนใดล้มเหลว (ใช้ใน CI/สคริปต์อัตโนมัติได้)
 * ==================================================================
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("สคริปต์นี้รันได้เฉพาะบรรทัดคำสั่งเท่านั้น\n");
}

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = BASE_PATH . '/app/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
    if (is_file($file)) require $file;
});
require BASE_PATH . '/app/Core/helpers.php';

use App\Core\Database;
use App\Core\Migrator;
use App\Core\Schema;

$config = require BASE_PATH . '/config/config.php';
$GLOBALS['config'] = $config;
date_default_timezone_set($config['app']['timezone']);

// ---------- อ่านตัวเลือกจากบรรทัดคำสั่ง ----------
$opt = ['dry' => false, 'status' => false, 'check' => false, 'school' => null, 'only' => null];
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--dry-run')                 $opt['dry'] = true;
    elseif ($a === '--status')              $opt['status'] = true;
    elseif ($a === '--check')               $opt['check'] = true;
    elseif (str_starts_with($a, '--school=')) $opt['school'] = substr($a, 9);
    elseif (str_starts_with($a, '--only='))   $opt['only']   = basename(substr($a, 7));
    elseif ($a === '--help' || $a === '-h') { fwrite(STDOUT, file_get_contents(__FILE__, false, null, 0, 1600)); exit(0); }
    else { fwrite(STDERR, "ไม่รู้จักตัวเลือก: {$a}\n"); exit(2); }
}

function line(string $s = ''): void { fwrite(STDOUT, $s . "\n"); }
function err(string $s): void       { fwrite(STDERR, $s . "\n"); }

// ---------- รวบรวมรายชื่อฐานข้อมูลที่ต้องทำ ----------
$targets = [];
$multi   = !empty($config['central']['enabled']);

if ($multi) {
    $central = Database::connectCentral($config['central']);
    if (!$central) {
        err('✗ เปิดโหมดหลายโรงเรียนไว้ แต่เชื่อมต่อฐานข้อมูลศูนย์กลางไม่ได้ (' . $config['central']['name'] . ')');
        exit(1);
    }
    $sql = "SELECT school_code, name_th, db_name, status FROM tenants ORDER BY school_code";
    foreach ($central->query($sql)->fetchAll() as $t) {
        if ($opt['school'] !== null && $t['school_code'] !== $opt['school']) continue;
        $targets[] = $t;
    }
    if (!$targets) {
        err($opt['school'] !== null
            ? "✗ ไม่พบโรงเรียนรหัส {$opt['school']} ในทะเบียนกลาง"
            : '• ยังไม่มีโรงเรียนในทะเบียนกลาง — ไม่มีอะไรต้องทำ');
        exit($opt['school'] !== null ? 1 : 0);
    }
} else {
    $targets[] = ['school_code' => '-', 'name_th' => $config['app']['name'],
                  'db_name' => $config['db']['name'], 'status' => 'active'];
}

line(str_repeat('=', 66));
line(' นำเข้าฐานข้อมูล · ' . ($multi ? 'ระบบหลายโรงเรียน (' . count($targets) . ' แห่ง)' : 'โรงเรียนเดียว'));
if ($opt['dry'] || $opt['status']) line(' โหมด: ดูอย่างเดียว ไม่แก้ไขข้อมูล');
line(str_repeat('=', 66));

$allFiles = Migrator::files();
if ($opt['only'] !== null && !in_array($opt['only'], $allFiles, true)) {
    err("✗ ไม่พบไฟล์ {$opt['only']} ในโฟลเดอร์ database/");
    exit(1);
}

$fail = 0; $changed = 0; $skipped = 0;

foreach ($targets as $t) {
    $label = $multi ? "[{$t['school_code']}] {$t['name_th']}" : $t['name_th'];
    line('');
    line("▸ {$label}  ·  ฐานข้อมูล {$t['db_name']}");

    if ($t['status'] !== 'active') {
        line('  • ถูกระงับการใช้งาน — ยังนำเข้าให้ตามปกติ เพื่อให้พร้อมเมื่อเปิดใช้อีกครั้ง');
    }

    $cfg = $config['db'];
    $cfg['name'] = $t['db_name'];
    if (!Database::switchTo($cfg)) {
        err('  ✗ เชื่อมต่อฐานข้อมูลไม่ได้ — ข้ามโรงเรียนนี้');
        $fail++;
        continue;
    }

    // ---- โหมดตรวจโครงสร้าง ----
    if ($opt['check']) {
        $missing = Schema::missingAll();
        if ($missing) {
            err('  ✗ โครงสร้างไม่ครบ ต้องนำเข้า: ' . implode(', ', $missing));
            $fail++;
        } else {
            line('  ✓ โครงสร้างครบตามที่ทุกโมดูลต้องใช้');
        }
        continue;
    }

    try {
        $pending = $opt['only'] !== null
            ? (isset(Migrator::applied()[$opt['only']]) ? [] : [$opt['only']])
            : Migrator::pending();
    } catch (\Throwable $e) {
        err('  ✗ อ่านตาราง schema_migrations ไม่ได้: ' . $e->getMessage());
        $fail++;
        continue;
    }

    if (!$pending) {
        line('  ✓ ไม่มีไฟล์ค้าง (เป็นรุ่นล่าสุดแล้ว)');
        $skipped++;
        continue;
    }

    line('  • ค้างอยู่ ' . count($pending) . ' ไฟล์: ' . implode(', ', $pending));

    if ($opt['dry'] || $opt['status']) { $changed++; continue; }

    // เตือนไฟล์ที่รันซ้ำแล้วอาจทำข้อมูลซ้ำ/พัง (เช่น 01_schema, 02_seed บนฐานที่มีข้อมูลแล้ว)
    $unsafe = array_values(array_filter($pending, fn($f) => !Migrator::isRerunnable($f)));
    if ($unsafe && !Migrator::isFreshDatabase()) {
        err('  ✗ ไฟล์ ' . implode(', ', $unsafe) . ' รันซ้ำอัตโนมัติไม่ได้บนฐานข้อมูลที่มีข้อมูลแล้ว');
        err('    → ถ้าฐานข้อมูลนี้นำเข้าไฟล์ดังกล่าวไปแล้ว ให้ทำเครื่องหมายว่านำเข้าแล้ว');
        err('      ที่หน้า "นำเข้าฐานข้อมูล" ในระบบ แล้วรันสคริปต์นี้ใหม่');
        $fail++;
        continue;
    }

    $results = Migrator::run($pending);
    foreach ($results as $r) {
        if ($r['ok'])           line("    ✓ {$r['file']} ({$r['statements']} คำสั่ง)");
        elseif ($r['skipped'])  line("    – {$r['file']} (ข้าม เพราะไฟล์ก่อนหน้าล้มเหลว)");
        else {
            err("    ✗ {$r['file']} — {$r['error']}");
        }
    }
    if (array_filter($results, fn($r) => !$r['ok'] && !$r['skipped'])) { $fail++; }
    else { $changed++; line('  ✓ นำเข้าครบแล้ว'); }
}

line('');
line(str_repeat('-', 66));
if ($opt['check']) {
    line($fail === 0 ? '✓ โครงสร้างฐานข้อมูลครบทุกโรงเรียน' : "✗ โครงสร้างไม่ครบ {$fail} โรงเรียน");
} elseif ($opt['dry'] || $opt['status']) {
    line("สรุป: ต้องนำเข้าเพิ่ม {$changed} โรงเรียน · เป็นรุ่นล่าสุดแล้ว {$skipped} โรงเรียน"
        . ($fail ? " · เชื่อมต่อไม่ได้ {$fail} โรงเรียน" : ''));
} else {
    line("สรุป: นำเข้าแล้ว {$changed} โรงเรียน · ไม่ต้องทำ {$skipped} โรงเรียน"
        . ($fail ? " · ล้มเหลว {$fail} โรงเรียน" : ''));
}
line(str_repeat('-', 66));

exit($fail > 0 ? 1 : 0);
